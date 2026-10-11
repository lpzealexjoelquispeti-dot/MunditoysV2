"""Pronóstico de demanda semanal para el modo administrador del asistente.

Método: desestacionalizar → pronosticar con ML → reestacionalizar.
1. Las ventas se dividen por el índice estacional de su mercado (Walmart: sus propias ventas;
   Bolivia: Google Trends), de modo que queda la demanda base.
2. LightGBM, entrenado y evaluado con ventas reales (M5, Walmart, departamento Hobbies), pronostica esa
   demanda base con variables que existen en cualquier tienda: niveles recientes, mismo periodo del año
   anterior, intermitencia y precio.
3. El resultado se multiplica por el índice del mercado donde se usa. Así la temporada boliviana
   (Navidad × 2,4) no depende de que Walmart tenga picos igual de fuertes.

Uso:
  python pronostico.py entrenar --m5 <carpeta M5> --trends <csv Google Trends> --salida <carpeta>
  python pronostico.py predecir  < solicitud JSON   (emite una línea JSON: {"event": "result", ...})
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import sys
import time
import warnings
from pathlib import Path
from typing import Any

import numpy as np

SEMANAS_ANIO = 52
HORIZONTES = (1, 2, 4, 8, 13, 26)
VENTANA_TEST = 52
PASO_ORIGENES = 3
MINIMO_HISTORIA = 12
RELATIVAS = ("nivel_4", "nivel_12", "anio_anterior", "anio_anterior_vecindad")
VARIABLES = ["horizonte", "log_escala", *RELATIVAS, "tendencia", "ceros_12", "precio_relativo", "cambio_precio"]
PARAMETROS_LGBM = {
    "objective": "tweedie", "tweedie_variance_power": 1.2, "learning_rate": 0.05, "num_leaves": 63,
    "min_child_samples": 100, "feature_fraction": 0.8, "bagging_fraction": 0.8, "bagging_freq": 1,
    "lambda_l2": 1.0, "verbose": -1, "seed": 7,
}


def semana_del_anio(fecha: dt.date) -> int:
    """Semana ISO en 0..51; la semana 53 se une a la 52 para que todos los años tengan 52."""
    return min(fecha.isocalendar().week, SEMANAS_ANIO) - 1


def indice_estacional(fechas: list[dt.date], valores: np.ndarray) -> np.ndarray:
    """Índice multiplicativo por semana del año (media 1).

    Cada semana se divide por la media móvil centrada de 52 semanas, así la tendencia de largo plazo
    (por ejemplo, que el interés en Google baje año tras año) no se confunde con la temporada.
    """
    valores = np.asarray(valores, dtype=float)
    mitad = SEMANAS_ANIO // 2
    razones: list[list[float]] = [[] for _ in range(SEMANAS_ANIO)]
    for i in range(mitad, len(valores) - mitad):
        media = valores[i - mitad:i + mitad + 1].mean()
        if media > 0:
            razones[semana_del_anio(fechas[i])].append(valores[i] / media)
    indice = np.array([np.mean(r) if r else 1.0 for r in razones])
    suavizado = 0.25 * np.roll(indice, 1) + 0.5 * indice + 0.25 * np.roll(indice, -1)
    return suavizado / suavizado.mean()


def leer_trends(ruta: Path, columna: int = 1) -> tuple[list[dt.date], np.ndarray]:
    """CSV de Google Trends: dos líneas de cabecera y luego 'Semana,<término>...'; '<1' cuenta como 0.5.

    Trends fecha cada semana por su domingo inicial; se usa el miércoles para que caiga en la semana ISO correcta.
    """
    with open(ruta, encoding="utf-8-sig") as archivo:
        filas = [fila for fila in csv.reader(archivo) if fila and fila[0][:2] in ("19", "20")]
    fechas = [dt.date.fromisoformat(fila[0]) + dt.timedelta(days=3) for fila in filas]
    valores = np.array([float(fila[columna].replace("<1", "0.5")) for fila in filas])
    return fechas, valores


def medias_moviles(ventas: np.ndarray) -> tuple[np.ndarray, np.ndarray]:
    """Sumas y conteos acumulados que ignoran las semanas previas al lanzamiento (NaN)."""
    validos = ~np.isnan(ventas)
    ceros = np.zeros((ventas.shape[0], 1))
    suma = np.concatenate([ceros, np.cumsum(np.where(validos, ventas, 0.0), axis=1)], axis=1)
    conteo = np.concatenate([ceros, np.cumsum(validos, axis=1)], axis=1)
    return suma, conteo


def media_ventana(suma: np.ndarray, conteo: np.ndarray, fin: int, largo: int) -> np.ndarray:
    """Media de las semanas fin-largo+1..fin de cada serie."""
    inicio = max(fin - largo + 1, 0)
    n = conteo[:, fin + 1] - conteo[:, inicio]
    with np.errstate(invalid="ignore", divide="ignore"):
        return np.where(n > 0, (suma[:, fin + 1] - suma[:, inicio]) / np.maximum(n, 1), np.nan)


def construir_filas(ventas: np.ndarray, precios: np.ndarray, indice_semana: np.ndarray,
                    origen: int, horizonte: int) -> dict[str, np.ndarray]:
    """Variables de todas las series para un origen (última semana conocida) y un horizonte.

    ventas: (series, semanas) con NaN antes del lanzamiento; precios: igual forma, NaN si no hay precio;
    indice_semana: índice estacional del mercado ya asignado a cada semana del calendario.
    """
    objetivo = origen + horizonte
    suma, conteo = medias_moviles(ventas)
    nivel_4 = media_ventana(suma, conteo, origen, 4)
    nivel_12 = media_ventana(suma, conteo, origen, 12)
    nivel_52 = media_ventana(suma, conteo, origen, SEMANAS_ANIO)
    pasado = objetivo - SEMANAS_ANIO
    anio_anterior = ventas[:, pasado] if pasado >= 0 else np.full(len(ventas), np.nan)
    vecindad = ventas[:, max(pasado - 1, 0):max(pasado + 2, 0)] if pasado >= 1 else np.full((len(ventas), 1), np.nan)
    ultimas = ventas[:, max(origen - 11, 0):origen + 1]
    with np.errstate(invalid="ignore", divide="ignore"), warnings.catch_warnings():
        warnings.simplefilter("ignore", RuntimeWarning)
        indice_origen = float(np.mean(indice_semana[max(origen - 11, 0):origen + 1]))
        precio_base = np.nanmean(precios[:, max(origen - SEMANAS_ANIO + 1, 0):origen + 1], axis=1)
        filas = {
            "horizonte": np.full(len(ventas), float(horizonte)),
            "nivel_4": nivel_4,
            "nivel_12": nivel_12,
            "nivel_52": nivel_52,
            "anio_anterior": anio_anterior,
            "anio_anterior_vecindad": np.nanmean(vecindad, axis=1),
            "tendencia": np.where(nivel_52 > 0, nivel_12 / nivel_52, np.nan),
            "ceros_12": np.mean(ultimas == 0, axis=1),
            "indice_objetivo": np.full(len(ventas), indice_semana[objetivo]),
            "indice_origen": np.full(len(ventas), indice_origen),
            "razon_indice": np.full(len(ventas), indice_semana[objetivo] / indice_origen),
            "precio_relativo": precios[:, objetivo] / precio_base,
            "cambio_precio": precios[:, objetivo] / precios[:, origen],
        }
    return filas


def escala(filas: dict[str, np.ndarray]) -> np.ndarray:
    """Nivel de ventas del último año de cada serie: el modelo aprende proporciones sobre ese nivel.

    Así un producto que vende 2 unidades por semana y otro que vende 200 se comportan igual ante la
    temporada, y el modelo entrenado con Walmart sirve para la escala de una juguetería pequeña.
    """
    return np.nan_to_num(filas["nivel_52"])


def a_matriz(filas: dict[str, np.ndarray]) -> np.ndarray:
    nivel = escala(filas)
    with np.errstate(invalid="ignore", divide="ignore"):
        relativas = {nombre: np.where(nivel > 0, filas[nombre] / nivel, np.nan) for nombre in RELATIVAS}
        relativas["log_escala"] = np.log1p(nivel)
    return np.column_stack([relativas.get(nombre, filas.get(nombre)) for nombre in VARIABLES]).astype(np.float32)


def lineas_base(filas: dict[str, np.ndarray]) -> dict[str, np.ndarray]:
    """Métodos clásicos contra los que se compara el modelo."""
    estacional = filas["anio_anterior"]
    return {
        "ingenuo_ultimas_4": np.nan_to_num(filas["nivel_4"]),
        "ingenuo_estacional": np.nan_to_num(np.where(np.isnan(estacional), filas["nivel_4"], estacional)),
        "media_por_indice": np.nan_to_num(filas["nivel_12"] * filas["razon_indice"]),
    }


def cargar_m5(carpeta: Path) -> dict[str, Any]:
    """Ventas semanales de Hobbies por producto y tienda, con precios y fechas de cada semana."""
    import pandas as pd

    calendario = pd.read_csv(carpeta / "calendar.csv", usecols=["date", "wm_yr_wk", "d"])
    ventas = pd.read_csv(carpeta / "sales_train_evaluation.csv")
    ventas = ventas[ventas["cat_id"] == "HOBBIES"].reset_index(drop=True)
    dias = [c for c in ventas.columns if c.startswith("d_")]
    semanas_dia = calendario.set_index("d").loc[dias, "wm_yr_wk"].to_numpy()
    inicios = np.flatnonzero(np.r_[True, semanas_dia[1:] != semanas_dia[:-1]])
    completas = inicios[np.diff(np.r_[inicios, len(dias)]) == 7]
    diarias = ventas[dias].to_numpy(dtype=np.float64)
    semanales = np.add.reduceat(diarias, inicios, axis=1)[:, np.isin(inicios, completas)]
    codigos_semana = semanas_dia[completas]
    # Walmart empieza la semana en sábado: el martes (día central) cae en la semana ISO correcta.
    fechas = [dt.date.fromisoformat(f) + dt.timedelta(days=3) for f in calendario.set_index("d").loc[[dias[i] for i in completas], "date"]]

    # Antes de la primera venta el producto no estaba a la venta: no cuenta como demanda cero.
    lanzamiento = np.argmax(semanales > 0, axis=1)
    semanales[np.arange(semanales.shape[1])[None, :] < lanzamiento[:, None]] = np.nan

    precios = pd.read_csv(carpeta / "sell_prices.csv", dtype={"store_id": "category", "item_id": "category"})
    precios = precios[precios["item_id"].str.startswith("HOBBIES")]
    tabla = precios.pivot_table(index=["item_id", "store_id"], columns="wm_yr_wk", values="sell_price", observed=True)
    tabla = tabla.reindex(columns=codigos_semana)
    claves = pd.MultiIndex.from_arrays([ventas["item_id"], ventas["store_id"]])
    matriz_precios = tabla.reindex(claves).to_numpy(dtype=np.float64)
    return {"ventas": semanales, "precios": matriz_precios, "fechas": fechas, "departamento": ventas["dept_id"].to_numpy()}


def conjunto(datos: dict[str, Any], indice_semana: np.ndarray, origenes: range, limite: int | None = None,
             desde: int | None = None) -> tuple[np.ndarray, np.ndarray, np.ndarray, dict[str, np.ndarray], np.ndarray]:
    """Filas (origen, horizonte) cuyo objetivo cae en [desde, limite).

    Las variables salen de la serie desestacionalizada; la escala devuelta ya incluye el índice de la
    semana objetivo, así que ventas reales ≈ salida del modelo × escala. Las líneas base usan la serie real.
    """
    ventas, precios = datos["ventas"], datos["precios"]
    desestacionalizadas = ventas / indice_semana[None, :]
    lanzamiento = np.argmax(~np.isnan(ventas), axis=1)
    bloques_x, bloques_y, bloques_escala, bases, semanas = [], [], [], {}, []
    for origen in origenes:
        for horizonte in HORIZONTES:
            objetivo = origen + horizonte
            if objetivo >= ventas.shape[1] or (limite is not None and objetivo >= limite) or (desde is not None and objetivo < desde):
                continue
            filas = construir_filas(ventas, precios, indice_semana, origen, horizonte)
            base = construir_filas(desestacionalizadas, precios, indice_semana, origen, horizonte)
            valido = (origen - lanzamiento >= MINIMO_HISTORIA) & ~np.isnan(ventas[:, objetivo]) & (escala(base) > 0)
            if not valido.any():
                continue
            bloques_x.append(a_matriz(base)[valido])
            bloques_escala.append(escala(base)[valido] * indice_semana[objetivo])
            bloques_y.append(ventas[valido, objetivo])
            for nombre, valor in lineas_base(filas).items():
                bases.setdefault(nombre, []).append(valor[valido])
            semanas.append(np.column_stack([np.full(valido.sum(), objetivo), np.full(valido.sum(), horizonte)]))
    return (np.concatenate(bloques_x), np.concatenate(bloques_y), np.concatenate(bloques_escala),
            {k: np.concatenate(v) for k, v in bases.items()}, np.concatenate(semanas))


def metricas(real: np.ndarray, prediccion: np.ndarray, semanas: np.ndarray) -> dict[str, float]:
    """Error por producto-tienda-semana y MAPE del total semanal (el que importa para planificar compras)."""
    error = prediccion - real
    claves = semanas[:, 0] * 100 + semanas[:, 1]
    unicas, posicion = np.unique(claves, return_inverse=True)
    total_real = np.bincount(posicion, weights=real, minlength=len(unicas))
    total_pred = np.bincount(posicion, weights=prediccion, minlength=len(unicas))
    return {
        "mae": float(np.mean(np.abs(error))),
        "rmse": float(np.sqrt(np.mean(error ** 2))),
        "wape": float(np.sum(np.abs(error)) / np.sum(real)),
        "mape_total_semanal": float(np.mean(np.abs(total_pred - total_real) / total_real)),
        "sesgo": float(np.sum(error) / np.sum(real)),
    }


def entrenar(carpeta_m5: Path, ruta_trends: Path, salida: Path) -> dict[str, Any]:
    import lightgbm as lgb

    inicio = time.monotonic()
    datos = cargar_m5(carpeta_m5)
    ventas, fechas = datos["ventas"], datos["fechas"]
    semanas_total = ventas.shape[1]
    inicio_test = semanas_total - VENTANA_TEST
    inicio_validacion = inicio_test - 26

    # El índice estacional de Walmart sale solo de las semanas de entrenamiento: sin fuga de información.
    total = np.nansum(ventas[:, :inicio_validacion], axis=0)
    indice_m5 = indice_estacional(fechas[:inicio_validacion], total)
    indice_semana = np.array([indice_m5[semana_del_anio(f)] for f in fechas])

    origenes = range(SEMANAS_ANIO, semanas_total - 1, PASO_ORIGENES)
    x_ent, y_ent, e_ent, _, _ = conjunto(datos, indice_semana, origenes, limite=inicio_validacion)
    x_val, y_val, e_val, _, _ = conjunto(datos, indice_semana, origenes, limite=inicio_test, desde=inicio_validacion)
    x_test, y_test, e_test, bases, semanas_test = conjunto(datos, indice_semana, range(inicio_test - 1, semanas_total - 1), desde=inicio_test)

    # Objetivo relativo (ventas / nivel) ponderado por el nivel: minimiza el error en unidades reales.
    modelo = lgb.train(
        PARAMETROS_LGBM, lgb.Dataset(x_ent, y_ent / e_ent, weight=e_ent, feature_name=VARIABLES),
        num_boost_round=2000, valid_sets=[lgb.Dataset(x_val, y_val / e_val, weight=e_val, feature_name=VARIABLES)],
        callbacks=[lgb.early_stopping(100, verbose=False)],
    )
    # Calibración: el árbol con enlace logarítmico tiende a quedarse corto en el total; el factor sale
    # del periodo de validación (nunca del test) y se guarda junto al modelo para usarlo al predecir.
    prediccion_val = np.clip(modelo.predict(x_val, num_iteration=modelo.best_iteration), 0, None) * e_val
    calibracion = float(np.sum(y_val) / np.sum(prediccion_val))
    prediccion = np.clip(modelo.predict(x_test, num_iteration=modelo.best_iteration), 0, None) * e_test * calibracion

    resultados = {"lightgbm": metricas(y_test, prediccion, semanas_test)}
    resultados.update({nombre: metricas(y_test, valor, semanas_test) for nombre, valor in bases.items()})
    por_horizonte = {}
    for horizonte in HORIZONTES:
        filtro = semanas_test[:, 1] == horizonte
        por_horizonte[str(horizonte)] = {
            "lightgbm": metricas(y_test[filtro], prediccion[filtro], semanas_test[filtro]),
            "media_por_indice": metricas(y_test[filtro], bases["media_por_indice"][filtro], semanas_test[filtro]),
        }

    fechas_trends, valores_trends = leer_trends(ruta_trends)
    indice_bolivia = indice_estacional(fechas_trends, valores_trends)

    salida.mkdir(parents=True, exist_ok=True)
    modelo.save_model(str(salida / "pronostico_demanda.txt"), num_iteration=modelo.best_iteration)
    importancia = dict(zip(VARIABLES, modelo.feature_importance("gain").round(1).tolist()))
    informe = {
        "version": "pronostico-v3",
        "datos": {
            "fuente": "M5 Forecasting (Walmart), categoría HOBBIES, ventas semanales por producto y tienda",
            "series": int(ventas.shape[0]),
            "semanas": int(semanas_total),
            "desde": str(fechas[0]), "hasta": str(fechas[-1]),
            "test_desde": str(fechas[inicio_test]),
            "filas_entrenamiento": int(len(y_ent)), "filas_validacion": int(len(y_val)), "filas_test": int(len(y_test)),
        },
        "metodo": "desestacionalizar (índice del mercado) → LightGBM sobre demanda base → reestacionalizar",
        "horizontes": list(HORIZONTES),
        "iteraciones": int(modelo.best_iteration),
        "calibracion": round(calibracion, 4),
        "metricas_test": resultados,
        "metricas_por_horizonte": por_horizonte,
        "importancia": importancia,
        "indice_estacional_m5": indice_m5.round(4).tolist(),
        "indice_estacional_bolivia": indice_bolivia.round(4).tolist(),
        "trends": {"archivo": ruta_trends.name, "desde": str(fechas_trends[0]), "hasta": str(fechas_trends[-1])},
        "duracion_s": round(time.monotonic() - inicio, 1),
    }
    (salida / "pronostico_metricas.json").write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
    return informe


def completar_historia(historia: np.ndarray, fechas: list[dt.date], indice: np.ndarray, semanas_objetivo: int,
                       semilla: int) -> tuple[np.ndarray, int]:
    """Antepone semanas simuladas cuando la tienda tiene poca historia real.

    El nivel base sale de las ventas reales desestacionalizadas (ventas / índice boliviano de esas semanas)
    y cada semana simulada es Poisson(nivel × índice de su semana). La cantidad simulada se informa siempre.
    """
    faltan = max(semanas_objetivo - len(historia), 0)
    if faltan == 0:
        return historia, 0
    reales = np.array([indice[semana_del_anio(f)] for f in fechas])
    nivel = float(np.mean(historia / reales)) if len(historia) else 0.0
    primera = fechas[0] if fechas else dt.date.today()
    previas = [primera - dt.timedelta(weeks=faltan - i) for i in range(faltan)]
    generador = np.random.default_rng(semilla)
    simuladas = generador.poisson(max(nivel, 0.05) * np.array([indice[semana_del_anio(f)] for f in previas])).astype(float)
    return np.concatenate([simuladas, historia]), faltan


def predecir(solicitud: dict[str, Any]) -> dict[str, Any]:
    """Pronóstico por producto para las próximas semanas con el índice estacional boliviano.

    solicitud = {"model": ruta, "origin": "AAAA-MM-DD" (lunes de la última semana conocida), "weeks": N,
                 "history_weeks": 52, "min_real_weeks": 12,
                 "series": [{"id", "sales": [unidades por semana; la última es la del origen], "price": opcional}]}
    El índice estacional y la calibración se leen de pronostico_metricas.json, junto al modelo.
    """
    import lightgbm as lgb

    ruta_modelo = Path(solicitud["model"])
    informe = json.loads((ruta_modelo.parent / "pronostico_metricas.json").read_text(encoding="utf-8"))
    modelo = lgb.Booster(model_file=str(ruta_modelo))
    calibracion = float(informe.get("calibracion", 1.0))
    indice = np.asarray(solicitud.get("seasonal_index") or informe["indice_estacional_bolivia"], dtype=float)
    origen_fecha = dt.date.fromisoformat(solicitud["origin"])
    semanas = int(solicitud.get("weeks", 8))
    semanas_historia = int(solicitud.get("history_weeks", SEMANAS_ANIO))
    minimo_reales = int(solicitud.get("min_real_weeks", MINIMO_HISTORIA))
    resultados = []
    for serie in solicitud["series"]:
        reales = np.asarray(serie["sales"], dtype=float)
        fechas_reales = [origen_fecha - dt.timedelta(weeks=len(reales) - 1 - i) for i in range(len(reales))]
        simuladas = 0
        historia = reales
        if len(reales) < minimo_reales or len(reales) < semanas_historia:
            historia, simuladas = completar_historia(reales, fechas_reales, indice, semanas_historia, int(serie["id"]))
        largo = len(historia)
        precio = float(serie.get("price") or 1.0)
        fechas = [origen_fecha + dt.timedelta(weeks=i - largo + 1) for i in range(largo + semanas)]
        indice_semana = np.array([indice[semana_del_anio(f)] for f in fechas])
        ventas = np.concatenate([historia / indice_semana[:largo], np.full(semanas, np.nan)])[None, :]
        precios = np.full_like(ventas, precio)
        pronostico = []
        for horizonte in range(1, semanas + 1):
            filas = construir_filas(ventas, precios, indice_semana, largo - 1, horizonte)
            base = float(np.clip(modelo.predict(a_matriz(filas))[0], 0, None) * escala(filas)[0] * calibracion)
            valor = base * float(indice_semana[largo - 1 + horizonte])
            pronostico.append({"week": str(fechas[largo - 1 + horizonte]), "units": round(valor, 2),
                               "seasonal_index": round(float(indice_semana[largo - 1 + horizonte]), 3)})
        resultados.append({
            "id": serie["id"], "forecast": pronostico, "total": round(sum(p["units"] for p in pronostico), 2),
            "real_weeks": int(len(reales)), "simulated_weeks": int(simuladas),
            "recent_weekly_average": round(float(np.mean(historia[-4:])), 2),
        })
    return {"event": "result", "model_version": informe.get("version"), "series": resultados}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="comando", required=True)
    ent = sub.add_parser("entrenar")
    ent.add_argument("--m5", type=Path, required=True)
    ent.add_argument("--trends", type=Path, required=True)
    ent.add_argument("--salida", type=Path, required=True)
    sub.add_parser("predecir")
    argumentos = parser.parse_args()

    if argumentos.comando == "entrenar":
        informe = entrenar(argumentos.m5, argumentos.trends, argumentos.salida)
        print(json.dumps({"event": "result", "metricas_test": informe["metricas_test"], "duracion_s": informe["duracion_s"]}, ensure_ascii=False))
        return

    try:
        print(json.dumps(predecir(json.load(sys.stdin)), ensure_ascii=False))
    except Exception as error:  # noqa: BLE001 - el servicio de Laravel muestra el mensaje
        print(json.dumps({"event": "error", "message": str(error)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
