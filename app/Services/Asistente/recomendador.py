"""Recomendador híbrido del asistente: compras conjuntas + parecido del texto + popularidad.

La parte que aprende de datos (qué productos se compran juntos y cuánto pesa cada señal) se evalúa
con compras reales de Online Retail II (UCI): se entrena con las facturas anteriores a junio de 2011
y se prueba con las posteriores ocultando un producto de cada compra (leave-one-out).

En MunditoysV2 se aplica igual sobre el catálogo y las ventas del sistema, con filtros duros que el
modelo nunca puede saltarse: stock disponible, presupuesto y edad recomendada.

Uso:
  python recomendador.py evaluar --datos <online_retail_II.csv.gz> --salida <carpeta>
  python recomendador.py recomendar < solicitud JSON   (emite una línea JSON: {"event": "result", ...})
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import time
import unicodedata
from pathlib import Path
from typing import Any

import numpy as np

CORTE_ENTRENAMIENTO = "2011-06-01"
CORTE_VALIDACION = "2011-04-01"
MAX_CESTAS_PRUEBA = 20000
MAX_SEMILLA = 10
K_METRICAS = (5, 10)
PESOS_POR_DEFECTO = {"coocurrencia": 0.6, "contenido": 0.3, "popularidad": 0.1}


def normalizar_texto(texto: str) -> str:
    """Minúsculas y sin tildes: 'Muñecas' y 'munecas' deben coincidir."""
    texto = unicodedata.normalize("NFKD", texto or "").encode("ascii", "ignore").decode("ascii")
    return re.sub(r"\s+", " ", texto.lower()).strip()


def matriz_cestas(cestas: list[list[int]], productos: int):
    """Matriz binaria compra × producto (dispersa)."""
    from scipy import sparse

    filas = np.repeat(np.arange(len(cestas)), [len(c) for c in cestas])
    columnas = np.fromiter((p for c in cestas for p in c), dtype=np.int64, count=len(filas))
    return sparse.csr_matrix((np.ones(len(filas), dtype=np.float32), (filas, columnas)), shape=(len(cestas), productos))


def similitud_coocurrencia(cestas: list[list[int]], productos: int) -> np.ndarray:
    """Coseno entre productos según las compras en que aparecen juntos (filtrado colaborativo ítem-ítem)."""
    x = matriz_cestas(cestas, productos)
    conjunta = (x.T @ x).toarray().astype(np.float32)
    frecuencia = np.sqrt(np.diag(conjunta)).astype(np.float32)
    np.fill_diagonal(conjunta, 0)
    with np.errstate(invalid="ignore", divide="ignore"):
        similitud = conjunta / np.outer(frecuencia, frecuencia)
    return np.nan_to_num(similitud)


def similitud_contenido(textos: list[str]) -> np.ndarray:
    """Coseno TF-IDF entre descripciones (palabras y fragmentos de palabras, útil con catálogos pequeños)."""
    from sklearn.feature_extraction.text import TfidfVectorizer

    vectores = TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), min_df=1, sublinear_tf=True).fit_transform(
        [normalizar_texto(t) for t in textos]
    )
    similitud = (vectores @ vectores.T).toarray().astype(np.float32)
    np.fill_diagonal(similitud, 0)
    return similitud


def popularidad(cestas: list[list[int]], productos: int) -> np.ndarray:
    conteo = np.bincount([p for c in cestas for p in c], minlength=productos).astype(np.float32)
    return conteo / max(conteo.max(), 1.0)


def puntuar(semillas: np.ndarray, similitudes: dict[str, np.ndarray], populares: np.ndarray, pesos: dict[str, float]) -> np.ndarray:
    """Puntaje de cada producto para un lote de semillas (filas binarias); cada señal se lleva a 0..1."""
    total = np.zeros(semillas.shape, dtype=np.float32)
    for nombre, matriz in similitudes.items():
        if pesos.get(nombre, 0) == 0:
            continue
        senal = semillas @ matriz
        maximo = senal.max(axis=1, keepdims=True)
        total += pesos[nombre] * np.divide(senal, maximo, out=np.zeros_like(senal), where=maximo > 0)
    total += pesos.get("popularidad", 0) * populares[None, :]
    total[semillas > 0] = -np.inf
    return total


def evaluar_metodo(prueba: list[tuple[list[int], int]], productos: int, similitudes: dict[str, np.ndarray],
                   populares: np.ndarray, pesos: dict[str, float]) -> dict[str, float]:
    """Hit rate / recall@k, precision@k, MRR@10 y cobertura del catálogo."""
    aciertos = {k: 0 for k in K_METRICAS}
    rango_reciproco = 0.0
    recomendados: set[int] = set()
    for inicio in range(0, len(prueba), 2000):
        lote = prueba[inicio:inicio + 2000]
        semillas = np.zeros((len(lote), productos), dtype=np.float32)
        for fila, (semilla, _) in enumerate(lote):
            semillas[fila, semilla] = 1
        puntajes = puntuar(semillas, similitudes, populares, pesos)
        mejores = np.argsort(-puntajes, axis=1)[:, :max(K_METRICAS)]
        recomendados.update(mejores[:, :10].ravel().tolist())
        for fila, (_, oculto) in enumerate(lote):
            posicion = np.flatnonzero(mejores[fila] == oculto)
            if posicion.size:
                rango_reciproco += 1 / (posicion[0] + 1)
                for k in K_METRICAS:
                    aciertos[k] += int(posicion[0] < k)
    n = len(prueba)
    resultado = {f"recall@{k}": aciertos[k] / n for k in K_METRICAS}
    resultado.update({f"precision@{k}": aciertos[k] / (n * k) for k in K_METRICAS})
    resultado["mrr@10"] = rango_reciproco / n
    resultado["cobertura"] = len(recomendados) / productos
    return resultado


def cargar_online_retail(ruta: Path) -> tuple[list[dict[str, Any]], list[str]]:
    """Facturas válidas (sin anulaciones, cargos ni cantidades negativas) con sus productos."""
    import pandas as pd

    datos = pd.read_csv(ruta, dtype={"Invoice": str, "StockCode": str}, parse_dates=["InvoiceDate"])
    datos = datos[~datos["Invoice"].str.startswith("C") & (datos["Quantity"] > 0) & (datos["Price"] > 0)]
    datos = datos[datos["StockCode"].str.match(r"^\d{5}")]
    descripciones = datos.groupby("StockCode")["Description"].agg(lambda d: d.dropna().mode().iat[0] if d.notna().any() else "")
    codigos = {codigo: i for i, codigo in enumerate(descripciones.index)}
    facturas = datos.groupby("Invoice").agg(fecha=("InvoiceDate", "min"), productos=("StockCode", lambda s: sorted({codigos[c] for c in s})))
    return facturas.reset_index().to_dict("records"), descripciones.astype(str).tolist()


def casos_prueba(facturas: list[dict[str, Any]], conocidos: np.ndarray, semilla_aleatoria: int = 7) -> list[tuple[list[int], int]]:
    """Por cada compra con 2+ productos conocidos, oculta uno al azar: la semilla es el resto."""
    generador = np.random.default_rng(semilla_aleatoria)
    casos = []
    for factura in facturas:
        productos = [p for p in factura["productos"] if conocidos[p]]
        if len(productos) < 2:
            continue
        oculto = productos.pop(generador.integers(len(productos)))
        generador.shuffle(productos)
        casos.append((productos[:MAX_SEMILLA], oculto))
    if len(casos) > MAX_CESTAS_PRUEBA:
        casos = [casos[i] for i in sorted(generador.choice(len(casos), MAX_CESTAS_PRUEBA, replace=False))]
    return casos


def evaluar(ruta: Path, salida: Path) -> dict[str, Any]:
    inicio = time.monotonic()
    facturas, descripciones = cargar_online_retail(ruta)
    productos = len(descripciones)
    corte_ent, corte_val = np.datetime64(CORTE_ENTRENAMIENTO), np.datetime64(CORTE_VALIDACION)

    def cestas(desde: np.datetime64 | None, hasta: np.datetime64 | None) -> list[dict[str, Any]]:
        return [f for f in facturas if (desde is None or f["fecha"] >= desde) and (hasta is None or f["fecha"] < hasta)]

    contenido = similitud_contenido(descripciones)

    # 1) Ajuste de pesos: se entrena hasta abril de 2011 y se valida con abril-mayo.
    previas = [f["productos"] for f in cestas(None, corte_val)]
    vistos = popularidad(previas, productos) > 0
    validacion = casos_prueba(cestas(corte_val, corte_ent), vistos)
    similitudes = {"coocurrencia": similitud_coocurrencia(previas, productos), "contenido": contenido}
    populares = popularidad(previas, productos)
    grilla = [{"coocurrencia": a, "contenido": b, "popularidad": round(1 - a - b, 2)}
              for a in (0.4, 0.5, 0.6, 0.7, 0.8) for b in (0.1, 0.2, 0.3, 0.4) if a + b <= 1]
    ajuste = [(pesos, evaluar_metodo(validacion[:5000], productos, similitudes, populares, pesos)["recall@10"]) for pesos in grilla]
    mejores_pesos = max(ajuste, key=lambda par: par[1])[0]

    # 2) Evaluación final: todo lo anterior a junio de 2011 contra las compras posteriores.
    entrenamiento = [f["productos"] for f in cestas(None, corte_ent)]
    populares = popularidad(entrenamiento, productos)
    prueba = casos_prueba(cestas(corte_ent, None), populares > 0)
    similitudes = {"coocurrencia": similitud_coocurrencia(entrenamiento, productos), "contenido": contenido}
    metodos = {
        "aleatorio": None,
        "popularidad": {"popularidad": 1.0},
        "contenido": {"contenido": 1.0},
        "coocurrencia": {"coocurrencia": 1.0},
        "hibrido": mejores_pesos,
    }
    resultados = {}
    for nombre, pesos in metodos.items():
        if pesos is None:
            azar = np.random.default_rng(1).random(productos).astype(np.float32)
            resultados[nombre] = evaluar_metodo(prueba, productos, {}, azar, {"popularidad": 1.0})
        else:
            resultados[nombre] = evaluar_metodo(prueba, productos, similitudes, populares, pesos)

    informe = {
        "version": "recomendador-v1",
        "datos": {
            "fuente": "Online Retail II (UCI), facturas 2009-12 a 2011-12",
            "productos": productos,
            "facturas_entrenamiento": len(entrenamiento),
            "casos_prueba": len(prueba),
            "corte": CORTE_ENTRENAMIENTO,
        },
        "protocolo": "leave-one-out: se oculta un producto de cada compra posterior al corte y se busca en el top-k",
        "pesos": mejores_pesos,
        "ajuste_pesos": [{"pesos": p, "recall@10_validacion": round(r, 4)} for p, r in ajuste],
        "metricas_test": resultados,
        "duracion_s": round(time.monotonic() - inicio, 1),
    }
    salida.mkdir(parents=True, exist_ok=True)
    (salida / "recomendador_metricas.json").write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
    return informe


def rango_edad(texto: str | None) -> tuple[int, int] | None:
    """'6-8' → (6, 8); '13+' → (13, 99); '3 años' → (3, 3)."""
    numeros = [int(n) for n in re.findall(r"\d+", texto or "")]
    if not numeros:
        return None
    if "+" in (texto or ""):
        return numeros[0], 99
    return numeros[0], numeros[-1]


def recomendar(solicitud: dict[str, Any]) -> dict[str, Any]:
    """Ordena el catálogo de MunditoysV2 para una consulta del asistente.

    solicitud = {"products": [{"id", "text", "price", "age", "stock"}], "sales": [[id, id, ...], ...],
                 "criteria": {"age": int|null, "max_price": float|null, "query": str, "seed_ids": [id]},
                 "weights": {...}, "limit": int}
    """
    catalogo = solicitud["products"]
    criterios = solicitud.get("criteria", {})
    pesos = solicitud.get("weights") or PESOS_POR_DEFECTO
    if not catalogo:
        return {"event": "result", "items": []}

    posiciones = {p["id"]: i for i, p in enumerate(catalogo)}
    cestas = [list(dict.fromkeys(posiciones[i] for i in venta if i in posiciones)) for venta in solicitud.get("sales", [])]
    cestas = [c for c in cestas if c]
    n = len(catalogo)
    textos = [p.get("text", "") for p in catalogo]
    consulta = normalizar_texto(criterios.get("query") or "")

    # La consulta del cliente se trata como un producto más para medir su parecido con el catálogo.
    contenido = similitud_contenido(textos + [consulta]) if consulta else None
    coocurrencia = similitud_coocurrencia(cestas, n) if cestas else np.zeros((n, n), dtype=np.float32)
    populares = popularidad(cestas, n) if cestas else np.zeros(n, dtype=np.float32)

    semilla = np.zeros(n, dtype=np.float32)
    semilla[[posiciones[i] for i in criterios.get("seed_ids", []) if i in posiciones]] = 1

    def escalar(valores: np.ndarray) -> np.ndarray:
        return valores / valores.max() if valores.max() > 0 else valores

    senales = {
        "coocurrencia": escalar(semilla @ coocurrencia) if semilla.any() else np.zeros(n, dtype=np.float32),
        "contenido": escalar(contenido[-1, :n]) if contenido is not None else np.zeros(n, dtype=np.float32),
        "popularidad": populares,
    }
    if not semilla.any():
        # Sin productos de referencia (carrito o vistos) el peso de las compras conjuntas pasa al texto.
        pesos = {**pesos, "contenido": pesos.get("contenido", 0) + pesos.get("coocurrencia", 0), "coocurrencia": 0}
    elif consulta:
        # Los gustos actuales pesan más que un carrito para otro regalo.
        resto = pesos.get("coocurrencia", 0) + pesos.get("popularidad", 0)
        pesos = {"contenido": 0.7, "coocurrencia": 0.3 * pesos.get("coocurrencia", 0) / resto if resto else 0,
                 "popularidad": 0.3 * pesos.get("popularidad", 0) / resto if resto else 0}
    puntaje = sum(pesos.get(nombre, 0) * valor for nombre, valor in senales.items())

    edad, maximo = criterios.get("age"), criterios.get("max_price")
    items = []
    for i, producto in enumerate(catalogo):
        motivos = []
        if semilla[i] or int(producto.get("stock") or 0) <= 0:
            continue
        if not producto.get("price") or float(producto["price"]) <= 0:
            continue
        if consulta and contenido[-1, i] < 0.08:
            continue
        if maximo is not None and float(producto["price"]) > float(maximo):
            continue
        rango = rango_edad(producto.get("age"))
        if edad is not None and (rango is None or not rango[0] <= int(edad) <= rango[1]):
            continue
        if edad is not None and rango:
            motivos.append("edad")
        if pesos.get("contenido", 0) > 0 and senales["contenido"][i] >= 0.5:
            motivos.append("tema")
        if pesos.get("coocurrencia", 0) > 0 and senales["coocurrencia"][i] > 0:
            motivos.append("comprado_junto")
        if pesos.get("popularidad", 0) > 0 and senales["popularidad"][i] >= 0.5:
            motivos.append("popular")
        if maximo is not None:
            motivos.append("presupuesto")
        if not motivos:
            motivos.append("disponible")
        items.append({"id": producto["id"], "score": round(float(puntaje[i]), 4), "reasons": motivos})

    items.sort(key=lambda item: (-item["score"], float(catalogo[posiciones[item["id"]]]["price"])))
    return {"event": "result", "items": items[: int(solicitud.get("limit", 5))]}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="comando", required=True)
    ev = sub.add_parser("evaluar")
    ev.add_argument("--datos", type=Path, required=True)
    ev.add_argument("--salida", type=Path, required=True)
    sub.add_parser("recomendar")
    argumentos = parser.parse_args()

    if argumentos.comando == "evaluar":
        informe = evaluar(argumentos.datos, argumentos.salida)
        print(json.dumps({"event": "result", "pesos": informe["pesos"], "metricas_test": informe["metricas_test"]}, ensure_ascii=False))
        return

    try:
        print(json.dumps(recomendar(json.load(sys.stdin)), ensure_ascii=False))
    except Exception as error:  # noqa: BLE001 - el servicio de Laravel muestra el mensaje
        print(json.dumps({"event": "error", "message": str(error)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
