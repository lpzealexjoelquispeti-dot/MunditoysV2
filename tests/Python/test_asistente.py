"""Regresiones del ML del asistente con datos pequeños; no usan M5 ni Online Retail."""

import datetime as dt
import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path

import numpy as np

CARPETA = Path(__file__).resolve().parents[2] / "app" / "Services" / "Asistente"
sys.path.insert(0, str(CARPETA))


def cargar(nombre: str):
    spec = importlib.util.spec_from_file_location(nombre, CARPETA / f"{nombre}.py")
    modulo = importlib.util.module_from_spec(spec)
    sys.modules[nombre] = modulo
    spec.loader.exec_module(modulo)
    return modulo


PRONOSTICO = cargar("pronostico")
RECOMENDADOR = cargar("recomendador")
INTENCIONES = cargar("intenciones")


class IndiceEstacionalTest(unittest.TestCase):
    def test_detecta_el_pico_de_navidad_aunque_la_serie_vaya_bajando(self) -> None:
        fechas = [dt.date(2021, 1, 4) + dt.timedelta(weeks=i) for i in range(260)]
        tendencia = np.linspace(40, 20, len(fechas))
        valores = np.array([t * (2.5 if f.month == 12 and 14 <= f.day <= 24 else 1.0) for f, t in zip(fechas, tendencia)])

        indice = PRONOSTICO.indice_estacional(fechas, valores)

        self.assertAlmostEqual(indice.mean(), 1.0, places=6)
        self.assertIn(int(np.argmax(indice)), (49, 50, 51))
        self.assertLess(indice[20], 1.0)

    def test_lee_el_csv_de_google_trends_con_valores_menores_a_uno(self) -> None:
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "trends.csv"
            ruta.write_text("Categoría: Todas las categorías\n\nSemana,juguetes: (Bolivia)\n2025-01-05,<1\n2025-01-12,40\n", encoding="utf-8")
            fechas, valores = PRONOSTICO.leer_trends(ruta)
        self.assertEqual(fechas, [dt.date(2025, 1, 8), dt.date(2025, 1, 15)])
        self.assertEqual(valores.tolist(), [0.5, 40.0])


class VariablesPronosticoTest(unittest.TestCase):
    def test_las_variables_son_relativas_al_nivel_de_la_serie(self) -> None:
        semanas = 70
        base = np.tile([1.0, 3.0], semanas // 2)
        ventas = np.vstack([base, base * 100])
        precios = np.ones_like(ventas)
        indice = np.ones(semanas)

        filas = PRONOSTICO.construir_filas(ventas, precios, indice, origen=60, horizonte=4)
        matriz = PRONOSTICO.a_matriz(filas)

        relativas = [PRONOSTICO.VARIABLES.index(n) for n in PRONOSTICO.RELATIVAS]
        np.testing.assert_allclose(matriz[0, relativas], matriz[1, relativas], rtol=1e-5)
        self.assertAlmostEqual(float(filas["anio_anterior"][1]), ventas[1, 12])

    def test_las_semanas_antes_del_lanzamiento_no_cuentan_como_cero(self) -> None:
        ventas = np.array([[np.nan] * 40 + [4.0] * 30])
        filas = PRONOSTICO.construir_filas(ventas, np.ones_like(ventas), np.ones(70), origen=60, horizonte=1)
        self.assertAlmostEqual(float(filas["nivel_52"][0]), 4.0)
        self.assertAlmostEqual(float(filas["ceros_12"][0]), 0.0)


class RecomendadorTest(unittest.TestCase):
    CATALOGO = [
        {"id": 1, "text": "Iron Man Marvel figura armadura roja", "price": 350, "age": "6-8", "stock": 4},
        {"id": 2, "text": "Thanos Marvel guantelete del infinito", "price": 495, "age": "13+", "stock": 2},
        {"id": 3, "text": "Colossus Marvel X-Men cromado", "price": 380, "age": "9-12", "stock": 0},
        {"id": 4, "text": "Peluche oso suave", "price": 120, "age": "3-5", "stock": 9},
    ]

    def test_nunca_recomienda_sin_stock_fuera_de_presupuesto_o_de_otra_edad(self) -> None:
        resultado = RECOMENDADOR.recomendar({
            "products": self.CATALOGO,
            "criteria": {"age": 7, "max_price": 400, "query": "marvel"},
        })
        self.assertEqual([item["id"] for item in resultado["items"]], [1])
        self.assertIn("edad", resultado["items"][0]["reasons"])

    def test_prioriza_lo_que_se_compra_junto_con_el_carrito(self) -> None:
        ventas = [[1, 2], [1, 2], [1, 4]]
        resultado = RECOMENDADOR.recomendar({
            "products": self.CATALOGO, "sales": ventas, "criteria": {"seed_ids": [1]},
        })
        ids = [item["id"] for item in resultado["items"]]
        self.assertEqual(ids[0], 2)
        self.assertNotIn(1, ids)
        self.assertNotIn(3, ids)

    def test_interpreta_los_rangos_de_edad_del_catalogo(self) -> None:
        self.assertEqual(RECOMENDADOR.rango_edad("6-8"), (6, 8))
        self.assertEqual(RECOMENDADOR.rango_edad("13+"), (13, 99))
        self.assertIsNone(RECOMENDADOR.rango_edad(None))

    def test_las_explicaciones_no_atribuyen_el_resultado_a_senales_con_peso_cero(self) -> None:
        resultado = RECOMENDADOR.recomendar({
            "products": self.CATALOGO, "sales": [[1, 2]] * 10,
            "weights": {"contenido": 1, "coocurrencia": 0, "popularidad": 0},
            "criteria": {"query": "Marvel", "age": 7},
        })
        self.assertEqual([item["id"] for item in resultado["items"]], [1])
        self.assertIn("tema", resultado["items"][0]["reasons"])
        self.assertNotIn("popular", resultado["items"][0]["reasons"])
        self.assertNotIn("comprado_junto", resultado["items"][0]["reasons"])

    def test_no_sugiere_productos_ajenos_al_tema_aunque_sean_populares(self) -> None:
        catalogo = [
            {"id": 1, "text": "rompecabezas dinosaurios fosiles prehistoria", "price": 100, "age": "6+", "stock": 4},
            {"id": 2, "text": "auto carreras vehiculo pista", "price": 90, "age": "3+", "stock": 4},
        ]
        resultado = RECOMENDADOR.recomendar({"products": catalogo, "sales": [[2]] * 20,
                                             "criteria": {"age": 7, "max_price": 150, "query": "dinosaurios"}})
        self.assertEqual([i["id"] for i in resultado["items"]], [1])

    def test_gustos_del_regalo_actual_prevalecen_sobre_compras_del_carrito(self) -> None:
        catalogo = [
            {"id": 1, "text": "auto carreras", "price": 100, "age": "6+", "stock": 4},
            {"id": 2, "text": "pista carreras autos", "price": 100, "age": "6+", "stock": 4},
            {"id": 3, "text": "rompecabezas dinosaurios", "price": 100, "age": "6+", "stock": 4},
        ]
        resultado = RECOMENDADOR.recomendar({"products": catalogo, "sales": [[1, 2]] * 10,
            "criteria": {"seed_ids": [1], "query": "dinosaurios", "age": 7}})
        self.assertEqual([i["id"] for i in resultado["items"]], [3])

    def test_la_edad_desconocida_y_el_precio_nulo_no_se_presentan_como_regalos_aptos(self) -> None:
        catalogo = [
            {"id": 1, "text": "peluche oso", "price": 100, "age": None, "stock": 4},
            {"id": 2, "text": "peluche oso", "price": None, "age": "3+", "stock": 4},
        ]
        resultado = RECOMENDADOR.recomendar({"products": catalogo, "criteria": {"age": 4, "query": "oso"}})
        self.assertEqual(resultado["items"], [])


class IntencionesTest(unittest.TestCase):
    def test_normaliza_tildes_mayusculas_y_numeros(self) -> None:
        self.assertEqual(INTENCIONES.normalizar("¿Qué le regalo a mi Hijo de 7 AÑOS?"), "que le regalo a mi hijo de 0 anos?")

    def test_el_modelo_guardado_se_carga_y_clasifica_desde_otro_proceso(self) -> None:
        with tempfile.TemporaryDirectory() as carpeta:
            informe = INTENCIONES.entrenar(CARPETA / "intenciones.csv", Path(carpeta))
            resultado = INTENCIONES.clasificar({"model": str(Path(carpeta) / "intenciones.joblib"),
                                                "texts": ["donde esta mi pedido", "que le regalo a mi hijo de 6 años"]})
        self.assertGreater(informe["metricas_test"][informe["modelo_elegido"]]["f1_macro"],
                           informe["metricas_test"]["clase_mayoritaria"]["f1_macro"])
        self.assertEqual([i["intent"] for i in resultado["items"]], ["estado_pedido", "recomendacion_regalo"])


if __name__ == "__main__":
    unittest.main()
