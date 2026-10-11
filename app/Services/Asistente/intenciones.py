"""Clasificador de intención del asistente (TF-IDF + modelo lineal).

Decide qué quiere el usuario antes de llamar al modelo de lenguaje: así el asistente usa la
herramienta correcta (catálogo, stock, pedidos, pronóstico…), rechaza lo que está fuera de la
tienda y bloquea las consultas de administrador para los clientes.

Se entrena con intenciones.csv (texto, intención). Se reserva un 20 % estratificado como prueba
y el modelo se elige con validación cruzada de 5 particiones sobre el resto.

El modelo final también se exporta a intenciones_modelo.json (vocabularios, IDF y pesos): Laravel lo
evalúa en PHP en milisegundos, sin arrancar Python en cada mensaje.

Uso:
  python intenciones.py entrenar --datos intenciones.csv --salida <carpeta>
  python intenciones.py clasificar < {"model": ruta, "texts": [...]}   → {"event": "result", "items": [...]}
"""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import time
import unicodedata
from pathlib import Path
from typing import Any

import numpy as np

SEMILLA = 7
PROPORCION_PRUEBA = 0.2
COBERTURA_OBJETIVO = 0.9


def normalizar(texto: str) -> str:
    """Minúsculas, sin tildes y con los números agrupados: '7 años' y '10 años' son la misma señal."""
    texto = unicodedata.normalize("NFKD", texto or "").encode("ascii", "ignore").decode("ascii").lower()
    texto = re.sub(r"\d+", " 0 ", texto)
    return re.sub(r"\s+", " ", texto).strip()


def leer_datos(ruta: Path) -> tuple[list[str], list[str]]:
    with open(ruta, encoding="utf-8") as archivo:
        filas = list(csv.DictReader(archivo))
    return [f["texto"] for f in filas], [f["intencion"] for f in filas]


def candidatos() -> dict[str, Any]:
    from sklearn.calibration import CalibratedClassifierCV
    from sklearn.dummy import DummyClassifier
    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.linear_model import LogisticRegression
    from sklearn.naive_bayes import ComplementNB
    from sklearn.pipeline import make_pipeline, make_union
    from sklearn.svm import LinearSVC

    def rasgos():
        # Palabras (1-2) para el significado y fragmentos de 2-5 letras para las faltas de ortografía.
        return make_union(
            TfidfVectorizer(preprocessor=normalizar, ngram_range=(1, 2), sublinear_tf=True),
            TfidfVectorizer(preprocessor=normalizar, analyzer="char_wb", ngram_range=(2, 5), sublinear_tf=True),
        )

    return {
        "clase_mayoritaria": make_pipeline(rasgos(), DummyClassifier(strategy="most_frequent")),
        "naive_bayes": make_pipeline(rasgos(), ComplementNB(alpha=0.3)),
        "regresion_logistica": make_pipeline(rasgos(), LogisticRegression(C=20, max_iter=3000)),
        "svm_lineal": make_pipeline(rasgos(), CalibratedClassifierCV(LinearSVC(C=1.0), cv=3)),
    }


def entrenar(ruta: Path, salida: Path) -> dict[str, Any]:
    import joblib
    from sklearn.metrics import accuracy_score, classification_report, confusion_matrix, precision_recall_fscore_support
    from sklearn.model_selection import StratifiedKFold, cross_val_score, train_test_split

    inicio = time.monotonic()
    textos, etiquetas = leer_datos(ruta)
    x_ent, x_test, y_ent, y_test = train_test_split(
        textos, etiquetas, test_size=PROPORCION_PRUEBA, stratify=etiquetas, random_state=SEMILLA
    )
    particiones = StratifiedKFold(n_splits=5, shuffle=True, random_state=SEMILLA)
    validacion = {
        nombre: float(np.mean(cross_val_score(modelo, x_ent, y_ent, cv=particiones, scoring="f1_macro")))
        for nombre, modelo in candidatos().items()
    }
    elegido = max((n for n in validacion if n != "clase_mayoritaria"), key=validacion.get)

    resultados = {}
    modelos = {}
    for nombre, modelo in candidatos().items():
        modelo.fit(x_ent, y_ent)
        prediccion = modelo.predict(x_test)
        precision, recall, f1, _ = precision_recall_fscore_support(y_test, prediccion, average="macro", zero_division=0)
        resultados[nombre] = {"accuracy": accuracy_score(y_test, prediccion), "precision_macro": precision,
                              "recall_macro": recall, "f1_macro": f1}
        modelos[nombre] = modelo

    final = modelos[elegido]
    clases = list(final.classes_)
    prediccion = final.predict(x_test)
    probabilidades = final.predict_proba(x_test)
    confianza = probabilidades.max(axis=1)
    correcta = prediccion == np.array(y_test)

    # Umbral de confianza: por debajo, el asistente pide que el usuario aclare en lugar de adivinar.
    umbral = float(np.quantile(confianza, 1 - COBERTURA_OBJETIVO))
    cubiertas = confianza >= umbral

    informe = {
        "version": "intenciones-v1",
        "datos": {"archivo": ruta.name, "ejemplos": len(textos), "entrenamiento": len(x_ent), "prueba": len(x_test),
                  "por_intencion": {c: etiquetas.count(c) for c in sorted(set(etiquetas))}},
        "validacion_cruzada_f1_macro": validacion,
        "modelo_elegido": elegido,
        "metricas_test": resultados,
        "matriz_confusion": {"clases": clases, "valores": confusion_matrix(y_test, prediccion, labels=clases).tolist()},
        "reporte_por_clase": classification_report(y_test, prediccion, labels=clases, output_dict=True, zero_division=0),
        "umbral_confianza": round(umbral, 4),
        "con_umbral": {"cobertura": float(cubiertas.mean()), "accuracy_cubiertas": float(correcta[cubiertas].mean())},
        "errores": [{"texto": t, "real": r, "predicha": p} for t, r, p in zip(x_test, y_test, prediccion) if r != p],
        "duracion_s": round(time.monotonic() - inicio, 1),
    }

    # El modelo que se usa en producción se reentrena con todos los ejemplos.
    produccion = candidatos()[elegido].fit(textos, etiquetas)
    salida.mkdir(parents=True, exist_ok=True)
    joblib.dump(produccion, salida / "intenciones.joblib")
    (salida / "intenciones_modelo.json").write_text(json.dumps(exportar(produccion), ensure_ascii=False), encoding="utf-8")
    (salida / "intenciones_metricas.json").write_text(json.dumps(informe, ensure_ascii=False, indent=2), encoding="utf-8")
    return informe


def exportar(modelo: Any) -> dict[str, Any]:
    """Pesos del pipeline en JSON: TF-IDF (vocabulario e IDF) + clasificador lineal (SVM calibrado o logística)."""
    from sklearn.calibration import CalibratedClassifierCV
    from sklearn.linear_model import LogisticRegression

    union, clasificador = modelo.steps[0][1], modelo.steps[-1][1]
    vectorizadores = []
    for _, vectorizador in union.transformer_list:
        vectorizadores.append({
            "analyzer": vectorizador.analyzer,
            "ngram_range": list(vectorizador.ngram_range),
            "vocabulary": {termino: int(i) for termino, i in vectorizador.vocabulary_.items()},
            "idf": [round(float(v), 6) for v in vectorizador.idf_],
        })

    def lineal(estimador: Any) -> dict[str, Any]:
        return {"coef": np.round(estimador.coef_, 6).tolist(), "intercept": np.round(estimador.intercept_, 6).tolist(),
                "classes": [str(c) for c in estimador.classes_]}

    if isinstance(clasificador, CalibratedClassifierCV):
        tipo = "svm_calibrado"
        modelos = [lineal(c.estimator) | {"calibrators": [[float(k.a_), float(k.b_)] for k in c.calibrators]}
                   for c in clasificador.calibrated_classifiers_]
    elif isinstance(clasificador, LogisticRegression):
        tipo, modelos = "logistica", [lineal(clasificador)]
    else:
        raise ValueError(f"No se puede exportar {type(clasificador).__name__}")
    return {"version": "intenciones-v1", "tipo": tipo, "classes": [str(c) for c in clasificador.classes_],
            "vectorizers": vectorizadores, "models": modelos}


def probabilidades_exportadas(exportado: dict[str, Any], textos: list[str]) -> np.ndarray:
    """Reimplementación con NumPy de lo que hace el PHP; sirve para comprobar que la exportación es fiel."""
    filas = []
    for texto in textos:
        partes = []
        for vectorizador in exportado["vectorizers"]:
            vector = np.zeros(len(vectorizador["idf"]))
            for termino in terminos(normalizar(texto), vectorizador["analyzer"], vectorizador["ngram_range"]):
                if termino in vectorizador["vocabulary"]:
                    vector[vectorizador["vocabulary"][termino]] += 1
            vector = np.where(vector > 0, 1 + np.log(np.where(vector > 0, vector, 1)), 0) * np.asarray(vectorizador["idf"])
            norma = np.linalg.norm(vector)
            partes.append(vector / norma if norma > 0 else vector)
        filas.append(np.concatenate(partes))
    x = np.array(filas)
    clases = exportado["classes"]
    total = np.zeros((len(textos), len(clases)))
    for modelo in exportado["models"]:
        decision = x @ np.asarray(modelo["coef"]).T + np.asarray(modelo["intercept"])
        if exportado["tipo"] == "logistica":
            decision = np.exp(decision - decision.max(axis=1, keepdims=True))
            total += decision / decision.sum(axis=1, keepdims=True)
            continue
        proba = np.zeros_like(total)
        for columna, (clase, (a, b)) in enumerate(zip(modelo["classes"], modelo["calibrators"])):
            proba[:, clases.index(clase)] = 1 / (1 + np.exp(a * decision[:, columna] + b))
        suma = proba.sum(axis=1, keepdims=True)
        total += np.where(suma > 0, proba / np.where(suma > 0, suma, 1), 1 / len(clases))
    return total / len(exportado["models"])


def terminos(texto: str, analizador: str, rango: list[int]) -> list[str]:
    """Mismos términos que TfidfVectorizer: palabras (token_pattern por defecto) o n-gramas char_wb."""
    minimo, maximo = rango
    if analizador == "word":
        palabras = re.findall(r"(?u)\b\w\w+\b", texto)
        return [" ".join(palabras[i:i + n]) for n in range(minimo, maximo + 1) for i in range(len(palabras) - n + 1)]
    resultado = []
    for palabra in re.sub(r"\s\s+", " ", texto).split():
        palabra = f" {palabra} "
        for n in range(minimo, maximo + 1):
            desplazamiento = 0
            resultado.append(palabra[desplazamiento:desplazamiento + n])
            while desplazamiento + n < len(palabra):
                desplazamiento += 1
                resultado.append(palabra[desplazamiento:desplazamiento + n])
            if desplazamiento == 0:
                break
    return resultado


def clasificar(solicitud: dict[str, Any]) -> dict[str, Any]:
    import joblib

    modelo = joblib.load(solicitud["model"])
    textos = solicitud["texts"]
    probabilidades = modelo.predict_proba(textos)
    clases = list(modelo.classes_)
    items = []
    for fila in probabilidades:
        orden = np.argsort(-fila)
        items.append({
            "intent": clases[orden[0]],
            "confidence": round(float(fila[orden[0]]), 4),
            "alternatives": [{"intent": clases[i], "confidence": round(float(fila[i]), 4)} for i in orden[1:3]],
        })
    return {"event": "result", "items": items}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="comando", required=True)
    ent = sub.add_parser("entrenar")
    ent.add_argument("--datos", type=Path, required=True)
    ent.add_argument("--salida", type=Path, required=True)
    sub.add_parser("clasificar")
    argumentos = parser.parse_args()

    if argumentos.comando == "entrenar":
        informe = entrenar(argumentos.datos, argumentos.salida)
        print(json.dumps({"event": "result", "modelo_elegido": informe["modelo_elegido"],
                          "metricas_test": informe["metricas_test"]}, ensure_ascii=False))
        return

    try:
        print(json.dumps(clasificar(json.load(sys.stdin)), ensure_ascii=False))
    except Exception as error:  # noqa: BLE001 - el servicio de Laravel muestra el mensaje
        print(json.dumps({"event": "error", "message": str(error)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    # El modelo guardado debe referenciar 'intenciones.normalizar' y no '__main__.normalizar',
    # para que se pueda cargar desde cualquier proceso.
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    import intenciones

    intenciones.main()
