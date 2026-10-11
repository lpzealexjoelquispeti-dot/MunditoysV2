"""Regresiones del empaquetado GLB y de la orientación; no ejecutan COLMAP ni OpenMVS."""

import importlib.util
import json
from pathlib import Path
import struct
import tempfile
import unittest

import numpy as np
from PIL import Image


SCRIPT = Path(__file__).resolve().parents[2] / "app" / "Services" / "Fotogrametria" / "reconstruir_modelo.py"
SPEC = importlib.util.spec_from_file_location("reconstruir_modelo", SCRIPT)
MOTOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MOTOR)


def glb_como_openmvs(carpeta: Path, posiciones: np.ndarray) -> Path:
    """GLB con textura externa, como lo exporta OpenMVS 2.4."""
    Image.new("RGB", (64, 32), (200, 40, 30)).save(carpeta / "modelo_0.png")
    indices = np.array([0, 1, 2, 0, 2, 3], dtype="<u4")
    uv = np.array([[0, 0], [1, 0], [1, 1], [0, 1]], dtype="<f4")
    binario = posiciones.astype("<f4").tobytes() + indices.tobytes() + uv.tobytes()
    documento = {
        "asset": {"version": "2.0", "generator": "OpenMVS"},
        "buffers": [{"byteLength": len(binario)}],
        "bufferViews": [
            {"buffer": 0, "byteLength": 48},
            {"buffer": 0, "byteOffset": 48, "byteLength": 24},
            {"buffer": 0, "byteOffset": 72, "byteLength": 32},
        ],
        "accessors": [
            {"bufferView": 0, "componentType": 5126, "count": 4, "type": "VEC3"},
            {"bufferView": 1, "componentType": 5125, "count": 6, "type": "SCALAR"},
            {"bufferView": 2, "componentType": 5126, "count": 4, "type": "VEC2"},
        ],
        "images": [{"name": "modelo_0", "uri": "modelo_0.png"}],
        "textures": [{"source": 0}],
        "materials": [{"pbrMetallicRoughness": {"baseColorTexture": {"index": 0}}}],
        "meshes": [{"primitives": [{"attributes": {"POSITION": 0, "TEXCOORD_0": 2}, "indices": 1, "material": 0}]}],
        "nodes": [{"mesh": 0}], "scenes": [{"nodes": [0]}], "scene": 0,
    }
    ruta = carpeta / "modelo_texturizado.glb"
    MOTOR.escribir_glb(ruta, documento, bytearray(binario))
    return ruta


class EmpaquetadoGlbTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporal = tempfile.TemporaryDirectory()
        self.carpeta = Path(self.temporal.name)

    def tearDown(self) -> None:
        self.temporal.cleanup()

    def test_incrusta_la_textura_como_jpeg_y_no_deja_referencias_externas(self) -> None:
        origen = glb_como_openmvs(self.carpeta, np.array([[0, 0, 0], [2, 0, 0], [2, 1, 0], [0, 1, 0]], dtype=float))
        destino = self.carpeta / "final.glb"

        datos = MOTOR.empaquetar_glb(origen, destino, np.eye(3), dict(MOTOR.DEFAULTS))

        documento, binario = MOTOR.leer_glb(destino)
        imagen = documento["images"][0]
        self.assertNotIn("uri", imagen)
        self.assertEqual(imagen["mimeType"], "image/jpeg")
        vista = documento["bufferViews"][imagen["bufferView"]]
        self.assertEqual(bytes(binario[vista["byteOffset"]:vista["byteOffset"] + 2]), b"\xff\xd8")
        self.assertEqual(documento["buffers"][0]["byteLength"], len(binario))
        self.assertEqual(datos, {"faces": 2, "vertices": 4, "texture_size": 64})

    def test_orienta_escala_y_apoya_la_base_en_el_suelo(self) -> None:
        # Objeto 0,4 de alto sobre el eje Z; la orientación lleva Z al eje vertical Y de glTF.
        posiciones = np.array([[0, 0, 5], [1, 0, 5], [1, 0.5, 5.4], [0, 0.5, 5.4]], dtype=float)
        origen = glb_como_openmvs(self.carpeta, posiciones)
        z_hacia_arriba = np.array([[1, 0, 0], [0, 0, 1], [0, -1, 0]], dtype=float)
        opciones = dict(MOTOR.DEFAULTS, max_dimension_m=0.5)

        MOTOR.empaquetar_glb(origen, self.carpeta / "final.glb", z_hacia_arriba, opciones)

        documento, _ = MOTOR.leer_glb(self.carpeta / "final.glb")
        accesor = documento["accessors"][0]
        self.assertAlmostEqual(accesor["min"][1], 0.0, places=5)
        self.assertAlmostEqual(accesor["max"][1], 0.2, places=5)
        self.assertAlmostEqual(accesor["max"][0] - accesor["min"][0], 0.5, places=5)
        self.assertAlmostEqual(accesor["max"][0] + accesor["min"][0], 0.0, places=5)

    def test_la_orientacion_usa_el_arriba_de_las_camaras_y_mira_hacia_la_foto_de_frente(self) -> None:
        camaras = {}
        for nombre, angulo in {"001_1.jpg": 0.0, "005_5.jpg": np.pi / 2, "009_9.jpg": np.pi}.items():
            centro = np.array([np.sin(angulo), 0.0, np.cos(angulo)]) * 3
            adelante = -centro / np.linalg.norm(centro)
            derecha = np.cross(adelante, [0, 1, 0])
            derecha /= np.linalg.norm(derecha)
            abajo = np.cross(adelante, derecha)
            camaras[nombre] = {"rotation": np.stack([derecha, abajo, adelante]), "center": centro}

        matriz = MOTOR.matriz_orientacion(camaras, "005_5.jpg", np.zeros(3))

        np.testing.assert_allclose(matriz[1], [0, 1, 0], atol=1e-9)
        np.testing.assert_allclose(matriz[2], [1, 0, 0], atol=1e-9)
        np.testing.assert_allclose(np.linalg.det(matriz), 1.0, atol=1e-9)

    def test_rechaza_una_solicitud_sin_motores(self) -> None:
        imagen = self.carpeta / "a.jpg"
        Image.new("RGB", (10, 10)).save(imagen)
        solicitud = {
            "images": [{"id": i, "slot": i, "path": str(imagen)} for i in range(1, 4)],
            "workspace": str(self.carpeta / "w"), "output_path": str(self.carpeta / "m.glb"),
            "colmap": str(self.carpeta / "colmap.exe"), "openmvs": str(self.carpeta),
        }

        with self.assertRaisesRegex(MOTOR.ReconstruccionError, "No se encontró COLMAP"):
            MOTOR.validar_solicitud(solicitud)


if __name__ == "__main__":
    unittest.main()


class CoherenciaCamarasTest(unittest.TestCase):
    def camaras_en_anillo(self, radio_atipico: float | None = None) -> dict:
        camaras = {}
        for indice in range(16):
            angulo = indice * np.pi / 8
            radio = radio_atipico if radio_atipico and indice == 5 else 3.0
            camaras[f"{indice:03d}.jpg"] = {"rotation": np.eye(3), "center": np.array([np.sin(angulo), 0.2, np.cos(angulo)]) * radio}
        return camaras

    def puntos(self, carpeta: Path) -> Path:
        rng = np.random.default_rng(1)
        ruta = carpeta / "points3D.txt"
        ruta.write_text("\n".join(f"{i} {x} {y} {z} 0 0 0 0.5" for i, (x, y, z) in enumerate(rng.normal(0, 0.3, (200, 3)))), encoding="utf-8")
        return ruta

    def test_acepta_un_anillo_de_camaras_a_distancias_parecidas(self) -> None:
        with tempfile.TemporaryDirectory() as carpeta:
            resultado = MOTOR.verificar_camaras(self.camaras_en_anillo(), self.puntos(Path(carpeta)), 4.0)
        self.assertTrue(resultado["consistent"])

    def test_rechaza_una_camara_colocada_a_una_distancia_imposible(self) -> None:
        with tempfile.TemporaryDirectory() as carpeta:
            resultado = MOTOR.verificar_camaras(self.camaras_en_anillo(radio_atipico=200.0), self.puntos(Path(carpeta)), 4.0)
        self.assertFalse(resultado["consistent"])


class PlantasTest(unittest.TestCase):
    def test_quita_solo_las_caras_horizontales_pegadas_al_suelo(self) -> None:
        posiciones = np.array([
            [0, 0, 0], [1, 0, 0], [0, 0, 1],        # planta: horizontal en el suelo
            [0, 0, 0], [1, 0, 0], [0, 1, 0],        # costado del pie: vertical
            [0, 1, 0], [1, 1, 0], [0, 1, 1],        # techo horizontal pero arriba
        ], dtype=float)
        caras = np.arange(9).reshape(3, 3)

        restantes = MOTOR.quitar_plantas(caras, posiciones)

        self.assertEqual(restantes.tolist(), [[3, 4, 5], [6, 7, 8]])
