"""Reconstrucción fotogramétrica local: fotos validadas -> GLB autocontenido.

Etapas: COLMAP (características, correspondencias, SfM, corrección de distorsión) y
OpenMVS (nube densa, malla, textura). Después se orienta el modelo (base abajo, frente
hacia +Z), se escala y se incrusta la textura como JPEG dentro del GLB.

Protocolo: recibe un JSON por stdin y emite una línea JSON por evento en stdout:
{"event": "progress", "stage": str, "progress": int}, y al final
{"event": "result", ...} o {"event": "error", "stage": str, "message": str}.
"""

from __future__ import annotations

import io
import json
import math
import os
import re
import shutil
import struct
import subprocess
import sys
import time
from pathlib import Path
from typing import Any

import numpy as np
from PIL import Image, ImageOps

sys.path.insert(0, str(Path(__file__).resolve().parent))

VERSION = "colmap-openmvs-v5"
# Juguetes de plástico liso o brillante tienen poca textura: SIFT con domain-size pooling y forma afín
# (solo CPU en COLMAP), emparejamiento guiado y un reconstructor más permisivo. En la captura de prueba
# (Iron Man, 48 fotos) pasó de 13/48 a 48/48 cámaras ubicadas con 0,95 px de error de reproyección.
SIFT_ROBUSTO = ["--SiftExtraction.peak_threshold", "0.004", "--SiftExtraction.domain_size_pooling", "1",
                "--SiftExtraction.estimate_affine_shape", "1"]
MAPPER_ROBUSTO = ["--Mapper.init_min_num_inliers", "50", "--Mapper.abs_pose_min_num_inliers", "15",
                  "--Mapper.abs_pose_min_inlier_ratio", "0.15", "--Mapper.init_min_tri_angle", "8"]
MAX_INPUT_BYTES = 1_048_576
DEFAULTS = {
    "max_image_size": 2000,
    "use_gpu": True,
    "robust_features": True,
    "min_registered_ratio": 0.8,
    "densify_resolution_level": 1,
    "target_faces": 150_000,
    "texture_size": 4096,
    "seam_leveling": False,
    "jpeg_quality": 85,
    "max_dimension_m": 0.3,
    "use_masks": True,
    "capture_mode": "alrededor",
    "mask_dilation_px": 8,
    "mask_erosion_px": 4,
    "min_component_ratio": 0.02,
    "max_camera_distance_ratio": 3.0,
    "refine_mesh": True,
    "refine_resolution_level": 1,
    "refine_max_face_area": 16,
    "close_holes": 100,
    "mask_texture_erosion_px": 6,
    "remove_soles": True,
    "threads": 0,
}
ETAPAS = {
    "preparacion": 3,
    "mascaras": 8,
    "caracteristicas": 14,
    "correspondencias": 28,
    "estructura": 38,
    "correccion": 42,
    "nube_densa": 70,
    "malla": 76,
    "refinado": 86,
    "textura": 94,
    "empaquetado": 100,
}


class ReconstruccionError(RuntimeError):
    def __init__(self, stage: str, message: str) -> None:
        super().__init__(message)
        self.stage = stage


def emitir(evento: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(evento, ensure_ascii=False, allow_nan=False) + "\n")
    sys.stdout.flush()


def ejecutar(stage: str, comando: list[str], trabajo: Path, log: Path, entorno: dict[str, str] | None = None) -> None:
    with log.open("ab") as salida:
        salida.write(("\n$ " + " ".join(comando) + "\n").encode("utf-8"))
        salida.flush()
        proceso = subprocess.run(
            comando, cwd=trabajo, stdout=salida, stderr=subprocess.STDOUT, env=entorno,
            creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
        )
    if proceso.returncode != 0:
        raise ReconstruccionError(stage, f"La etapa «{stage}» terminó con error (código {proceso.returncode}). Revisa el registro de la reconstrucción.")


def validar_solicitud(payload: Any) -> dict[str, Any]:
    if not isinstance(payload, dict):
        raise ReconstruccionError("preparacion", "La solicitud debe ser un objeto JSON.")
    imagenes = payload.get("images")
    if not isinstance(imagenes, list) or not 3 <= len(imagenes) <= 200:
        raise ReconstruccionError("preparacion", "Se necesitan entre 3 y 200 fotografías.")
    for imagen in imagenes:
        if not isinstance(imagen, dict) or type(imagen.get("id")) is not int or type(imagen.get("slot")) is not int:
            raise ReconstruccionError("preparacion", "Cada fotografía necesita un identificador y una posición enteros.")
        if not isinstance(imagen.get("path"), str) or not Path(imagen["path"]).is_file():
            raise ReconstruccionError("preparacion", f"No se encontró el archivo de la fotografía {imagen.get('id')}.")
    for clave in ("workspace", "output_path", "colmap", "openmvs"):
        if not isinstance(payload.get(clave), str) or not payload[clave]:
            raise ReconstruccionError("preparacion", f"Falta la ruta «{clave}».")
    if not Path(payload["colmap"]).is_file():
        raise ReconstruccionError("preparacion", "No se encontró COLMAP. Revisa la instalación de los motores de reconstrucción.")
    if not (Path(payload["openmvs"]) / "TextureMesh.exe").is_file() and not (Path(payload["openmvs"]) / "TextureMesh").is_file():
        raise ReconstruccionError("preparacion", "No se encontró OpenMVS. Revisa la instalación de los motores de reconstrucción.")
    opciones = dict(DEFAULTS)
    for clave, valor in (payload.get("options") or {}).items():
        if clave in DEFAULTS and type(valor) in (int, float, bool, str) and (type(valor) is str) == (type(DEFAULTS[clave]) is str):
            opciones[clave] = type(DEFAULTS[clave])(valor)
    if opciones["capture_mode"] not in ("alrededor", "plato_giratorio"):
        raise ReconstruccionError("preparacion", "El modo de captura debe ser «alrededor» o «plato_giratorio».")
    payload["options"] = opciones
    return payload


def preparar_imagenes(imagenes: list[dict[str, Any]], destino: Path) -> dict[str, int]:
    """Copia orientada (EXIF) y en orden de captura; el nombre conserva el id de la fotografía."""
    destino.mkdir(parents=True, exist_ok=True)
    nombres: dict[str, int] = {}
    for imagen in sorted(imagenes, key=lambda item: item["slot"]):
        nombre = f"{imagen['slot']:03d}_{imagen['id']}.jpg"
        with Image.open(imagen["path"]) as original:
            orientada = ImageOps.exif_transpose(original).convert("RGB")
            exif = orientada.getexif()
            exif.pop(0x0112, None)
            orientada.save(destino / nombre, "JPEG", quality=95, exif=exif.tobytes())
        nombres[nombre] = imagen["id"]
    return nombres


def estadisticas_modelo(colmap: str, modelo: Path, trabajo: Path, log: Path) -> dict[str, float]:
    proceso = subprocess.run([colmap, "model_analyzer", "--path", str(modelo)], cwd=trabajo, capture_output=True, text=True,
                             creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
    texto = proceso.stdout + proceso.stderr
    with log.open("a", encoding="utf-8") as salida:
        salida.write(texto)
    valores: dict[str, float] = {}
    for clave, patron in {
        "registered_images": r"Registered images:\s*(\d+)",
        "points": r"Points:\s*(\d+)",
        "reprojection_error": r"Mean reprojection error:\s*([\d.]+)",
        "track_length": r"Mean track length:\s*([\d.]+)",
    }.items():
        encontrado = re.search(patron, texto)
        if encontrado:
            valores[clave] = float(encontrado.group(1))
    return valores


def leer_camaras(colmap: str, modelo: Path, trabajo: Path, log: Path) -> dict[str, dict[str, Any]]:
    texto_dir = trabajo / "sparse_txt"
    texto_dir.mkdir(exist_ok=True)
    ejecutar("estructura", [colmap, "model_converter", "--input_path", str(modelo), "--output_path", str(texto_dir), "--output_type", "TXT"], trabajo, log)
    camaras: dict[str, dict[str, Any]] = {}
    lineas = [linea for linea in (texto_dir / "images.txt").read_text(encoding="utf-8").splitlines() if linea and not linea.startswith("#")]
    for linea in lineas[0::2]:
        partes = linea.split()
        qw, qx, qy, qz, tx, ty, tz = map(float, partes[1:8])
        rotacion = np.array([
            [1 - 2 * (qy * qy + qz * qz), 2 * (qx * qy - qz * qw), 2 * (qx * qz + qy * qw)],
            [2 * (qx * qy + qz * qw), 1 - 2 * (qx * qx + qz * qz), 2 * (qy * qz - qx * qw)],
            [2 * (qx * qz - qy * qw), 2 * (qy * qz + qx * qw), 1 - 2 * (qx * qx + qy * qy)],
        ])
        centro = -rotacion.T @ np.array([tx, ty, tz])
        camaras[partes[9]] = {"rotation": rotacion, "center": centro}
    return camaras


def verificar_camaras(camaras: dict[str, dict[str, Any]], puntos: Path, razon_maxima: float) -> dict[str, Any]:
    """Detecta soluciones degeneradas: en una captura real todas las cámaras están a distancias parecidas del objeto.

    Registrar muchas cámaras no basta; con poca textura COLMAP puede colocarlas en posiciones imposibles.
    """
    coordenadas = [list(map(float, linea.split()[1:4])) for linea in puntos.read_text(encoding="utf-8").splitlines()
                   if linea and not linea.startswith("#")] if puntos.is_file() else []
    if len(coordenadas) < 50 or len(camaras) < 3:
        return {"consistent": False, "reason": "pocos_puntos", "points": len(coordenadas)}
    objeto = np.median(np.array(coordenadas), axis=0)
    distancias = np.array([np.linalg.norm(camara["center"] - objeto) for camara in camaras.values()])
    razon = float(distancias.max() / max(float(np.median(distancias)), 1e-9))
    return {"consistent": razon <= razon_maxima, "distance_ratio": round(razon, 2), "points": len(coordenadas)}


def mejor_modelo(colmap: str, carpeta: Path, trabajo: Path, log: Path) -> tuple[Path, dict[str, float]]:
    candidatos = []
    for modelo in sorted(path for path in carpeta.iterdir() if path.is_dir()):
        candidatos.append((estadisticas_modelo(colmap, modelo, trabajo, log), modelo))
    if not candidatos:
        raise ReconstruccionError("estructura", "No se pudo estimar la posición de las cámaras. Revisa el solapamiento entre fotografías consecutivas.")
    estadisticas, modelo = max(candidatos, key=lambda item: item[0].get("registered_images", 0))
    return modelo, estadisticas


def matriz_orientacion(camaras: dict[str, dict[str, Any]], frente: str | None, centro_objeto: np.ndarray) -> np.ndarray:
    """Eje Y = promedio del 'arriba' de las cámaras; eje Z = dirección horizontal hacia la foto de frente."""
    arriba = np.mean([-camara["rotation"][1] for camara in camaras.values()], axis=0)
    arriba /= np.linalg.norm(arriba)
    referencia = camaras.get(frente) if frente else None
    if referencia is None:
        referencia = next(iter(camaras.values()))
    hacia_frente = referencia["center"] - centro_objeto
    hacia_frente -= np.dot(hacia_frente, arriba) * arriba
    if np.linalg.norm(hacia_frente) < 1e-9:
        hacia_frente = np.cross(arriba, [1.0, 0.0, 0.0])
    hacia_frente /= np.linalg.norm(hacia_frente)
    lateral = np.cross(arriba, hacia_frente)
    return np.stack([lateral, arriba, hacia_frente])


def leer_glb(ruta: Path) -> tuple[dict[str, Any], bytearray]:
    datos = ruta.read_bytes()
    magia, _, _ = struct.unpack_from("<4sII", datos, 0)
    if magia != b"glTF":
        raise ReconstruccionError("empaquetado", "OpenMVS no produjo un archivo GLB válido.")
    desplazamiento, documento, binario = 12, None, bytearray()
    while desplazamiento < len(datos):
        largo, tipo = struct.unpack_from("<I4s", datos, desplazamiento)
        contenido = datos[desplazamiento + 8: desplazamiento + 8 + largo]
        if tipo == b"JSON":
            documento = json.loads(contenido)
        elif tipo == b"BIN\x00":
            binario = bytearray(contenido)
        desplazamiento += 8 + largo
    if documento is None:
        raise ReconstruccionError("empaquetado", "El GLB generado no contiene su descripción JSON.")
    return documento, binario


def escribir_glb(ruta: Path, documento: dict[str, Any], binario: bytearray) -> None:
    while len(binario) % 4:
        binario.append(0)
    documento["buffers"] = [{"byteLength": len(binario)}]
    texto = json.dumps(documento, separators=(",", ":")).encode("utf-8")
    texto += b" " * ((4 - len(texto) % 4) % 4)
    total = 12 + 8 + len(texto) + 8 + len(binario)
    with ruta.open("wb") as salida:
        salida.write(struct.pack("<4sII", b"glTF", 2, total))
        salida.write(struct.pack("<I4s", len(texto), b"JSON") + texto)
        salida.write(struct.pack("<I4s", len(binario), b"BIN\x00") + bytes(binario))


def empaquetar_glb(origen: Path, destino: Path, orientacion: np.ndarray, opciones: dict[str, Any]) -> dict[str, Any]:
    documento, binario = leer_glb(origen)
    vistas = documento["bufferViews"]
    caras = vertices = 0
    for malla in documento.get("meshes", []):
        for primitiva in malla.get("primitives", []):
            accesor = documento["accessors"][primitiva["attributes"]["POSITION"]]
            vista = vistas[accesor["bufferView"]]
            inicio = vista.get("byteOffset", 0) + accesor.get("byteOffset", 0)
            posiciones = np.frombuffer(binario, dtype="<f4", count=accesor["count"] * 3, offset=inicio).reshape(-1, 3).astype(np.float64)
            posiciones = posiciones @ orientacion.T
            minimo, maximo = posiciones.min(axis=0), posiciones.max(axis=0)
            escala = opciones["max_dimension_m"] / max(float((maximo - minimo).max()), 1e-9)
            desplazamiento = np.array([(minimo[0] + maximo[0]) / 2, minimo[1], (minimo[2] + maximo[2]) / 2])
            posiciones = ((posiciones - desplazamiento) * escala).astype("<f4")
            binario[inicio: inicio + posiciones.nbytes] = posiciones.tobytes()
            accesor["min"] = posiciones.min(axis=0).astype(float).tolist()
            accesor["max"] = posiciones.max(axis=0).astype(float).tolist()
            vertices += accesor["count"]
            if "indices" in primitiva:
                indices = documento["accessors"][primitiva["indices"]]
                vista_indices = vistas[indices["bufferView"]]
                inicio_indices = vista_indices.get("byteOffset", 0) + indices.get("byteOffset", 0)
                tipo = {5125: "<u4", 5123: "<u2"}[indices["componentType"]]
                triangulos = np.frombuffer(binario, dtype=tipo, count=indices["count"], offset=inicio_indices).reshape(-1, 3)
                cambiados = False
                if opciones["remove_soles"] and len(triangulos):
                    triangulos = quitar_plantas(triangulos, posiciones.astype(np.float64))
                    cambiados = True
                if opciones["min_component_ratio"] > 0 and len(triangulos):
                    triangulos = filtrar_fragmentos(triangulos, posiciones.astype(np.float64), opciones["min_component_ratio"])
                    cambiados = True
                if cambiados:
                    datos = np.ascontiguousarray(triangulos, dtype=tipo).tobytes()
                    binario[inicio_indices: inicio_indices + len(datos)] = datos
                    indices["count"] = len(triangulos) * 3
                caras += len(triangulos)
    tamano_textura = 0
    for imagen in documento.get("images", []):
        if "uri" not in imagen:
            continue
        with Image.open(origen.parent / imagen.pop("uri")) as textura:
            textura = textura.convert("RGB")
            if max(textura.size) > opciones["texture_size"]:
                textura.thumbnail((opciones["texture_size"], opciones["texture_size"]), Image.Resampling.LANCZOS)
            tamano_textura = max(tamano_textura, *textura.size)
            memoria = io.BytesIO()
            textura.save(memoria, "JPEG", quality=opciones["jpeg_quality"], optimize=True)
        while len(binario) % 4:
            binario.append(0)
        vistas.append({"buffer": 0, "byteOffset": len(binario), "byteLength": memoria.tell()})
        binario.extend(memoria.getvalue())
        imagen.update({"bufferView": len(vistas) - 1, "mimeType": "image/jpeg"})
    documento.setdefault("asset", {})["generator"] = f"MundiToys fotogrametría local ({VERSION})"
    escribir_glb(destino, documento, binario)
    return {"faces": caras, "vertices": vertices, "texture_size": tamano_textura}


def generar_mascaras(payload: dict[str, Any], opciones: dict[str, Any], nombres: dict[str, int], trabajo: Path) -> dict[str, Any]:
    """Reúne la máscara del juguete de cada foto: usa las calculadas al subirlas y genera con BiRefNet las que falten.

    Deja 'mascaras_origen/<foto>.jpg' para OpenMVS y, en modo plato giratorio, 'mascaras_sfm/<foto>.png'
    (reducida unos píxeles) para que COLMAP ubique las cámaras solo con el juguete: el fondo quieto
    haría creer que la cámara no se movió.
    """
    if not opciones["use_masks"]:
        return {"used": False, "reason": "desactivadas"}
    modelo_ia = payload.get("segmentation_model")
    precalculadas = {imagen["id"]: imagen.get("mask") for imagen in payload["images"]}
    origen = trabajo / "mascaras_origen"
    sfm = trabajo / "mascaras_sfm"
    origen.mkdir(exist_ok=True)
    giratorio = opciones["capture_mode"] == "plato_giratorio"
    if giratorio:
        sfm.mkdir(exist_ok=True)
    segmentador = None
    generadas = 0
    try:
        import cv2
        nucleo = np.ones((opciones["mask_erosion_px"] * 2 + 1,) * 2, np.uint8)
        for nombre, identificador in nombres.items():
            ruta = precalculadas.get(identificador)
            if ruta and Path(ruta).is_file():
                mascara = np.asarray(Image.open(ruta).convert("L"))
            else:
                if segmentador is None:
                    if not modelo_ia or not Path(modelo_ia).is_file():
                        return {"used": False, "reason": "sin_modelo"}
                    from segmentar_juguete import Segmentador
                    segmentador = Segmentador(modelo_ia)
                with Image.open(trabajo / "imagenes" / nombre) as foto:
                    mascara = segmentador.mascara(foto)
                generadas += 1
            Image.fromarray(mascara).save(origen / nombre, "JPEG", quality=95)
            if giratorio:
                binaria = np.where(mascara > 127, 255, 0).astype(np.uint8)
                cv2.imwrite(str(sfm / (nombre + ".png")), cv2.erode(binaria, nucleo))
    except Exception as error:  # noqa: BLE001
        shutil.rmtree(origen, ignore_errors=True)
        shutil.rmtree(sfm, ignore_errors=True)
        return {"used": False, "reason": f"error_{type(error).__name__}"}
    return {"used": True, "generated": generadas, "precomputed": len(nombres) - generadas,
            "provider": segmentador.proveedor if segmentador else None, "for_alignment": giratorio}


def corregir_mascaras(payload: dict[str, Any], opciones: dict[str, Any], modelo: Path, trabajo: Path, denso: Path,
                      log: Path, entorno: dict[str, str]) -> None:
    """Aplica a las máscaras la misma corrección de distorsión que a las fotos y deja '<foto>.mask.png' para OpenMVS."""
    ejecutar("correccion", [payload["colmap"], "image_undistorter", "--image_path", str(trabajo / "mascaras_origen"), "--input_path", str(modelo),
                            "--output_path", "mascaras_denso", "--output_type", "COLMAP", "--max_image_size", str(opciones["max_image_size"])],
             trabajo, log, entorno)
    import cv2
    nucleo = np.ones((opciones["mask_dilation_px"] * 2 + 1,) * 2, np.uint8)
    for corregida in (trabajo / "mascaras_denso" / "images").glob("*.jpg"):
        binaria = (np.asarray(Image.open(corregida).convert("L")) > 127).astype(np.uint8) * 255
        cv2.imwrite(str(denso / "images" / (corregida.stem + ".mask.png")), cv2.dilate(binaria, nucleo))
        estricta = trabajo / "mascaras_textura"
        estricta.mkdir(exist_ok=True)
        erosion = np.ones((opciones["mask_texture_erosion_px"] * 2 + 1,) * 2, np.uint8)
        cv2.imwrite(str(estricta / (corregida.stem + ".mask.png")), cv2.erode(binaria, erosion))


def quitar_plantas(caras: np.ndarray, posiciones: np.ndarray, altura_relativa: float = 0.03) -> np.ndarray:
    """Quita las caras casi horizontales pegadas al suelo: la planta del juguete no aparece en ninguna foto y toma el color de la base."""
    vertices = posiciones[caras]
    normales = np.cross(vertices[:, 1] - vertices[:, 0], vertices[:, 2] - vertices[:, 0])
    largo = np.linalg.norm(normales, axis=1)
    horizontales = np.abs(normales[:, 1]) > 0.8 * np.maximum(largo, 1e-12)
    minimo, alto = posiciones[:, 1].min(), np.ptp(posiciones[:, 1])
    abajo = vertices[:, :, 1].max(axis=1) <= minimo + altura_relativa * alto
    return caras[~(horizontales & abajo)]


def filtrar_fragmentos(caras: np.ndarray, posiciones: np.ndarray, proporcion_minima: float) -> np.ndarray:
    """Elimina piezas sueltas de la malla. Une vértices por posición porque la textura duplica vértices en las costuras."""
    escala = max(float(np.ptp(posiciones, axis=0).max()), 1e-9) * 1e-6
    _, unicos = np.unique(np.round(posiciones / escala).astype(np.int64), axis=0, return_inverse=True)
    unicos = unicos.ravel()
    etiquetas = np.arange(unicos.max() + 1)
    origen = unicos[np.concatenate([caras[:, 0], caras[:, 1], caras[:, 2]])]
    destino = unicos[np.concatenate([caras[:, 1], caras[:, 2], caras[:, 0]])]
    while True:
        minimo = np.minimum(etiquetas[origen], etiquetas[destino])
        nuevas = etiquetas.copy()
        np.minimum.at(nuevas, origen, minimo)
        np.minimum.at(nuevas, destino, minimo)
        nuevas = nuevas[nuevas]
        if np.array_equal(nuevas, etiquetas):
            break
        etiquetas = nuevas
    componente = etiquetas[unicos[caras[:, 0]]]
    tamanos = np.bincount(componente)
    return caras[tamanos[componente] >= proporcion_minima * tamanos.max()]


def reconstruir(payload: dict[str, Any]) -> dict[str, Any]:
    opciones = payload["options"]
    trabajo = Path(payload["workspace"])
    if trabajo.exists():
        shutil.rmtree(trabajo)
    trabajo.mkdir(parents=True)
    log = trabajo / "reconstruccion.log"
    colmap = payload["colmap"]
    openmvs = Path(payload["openmvs"])
    entorno = dict(os.environ, PATH=str(Path(colmap).parent) + os.pathsep + os.environ.get("PATH", ""))
    duraciones: dict[str, int] = {}
    inicio_total = time.perf_counter()

    def etapa(nombre: str, accion) -> Any:
        inicio = time.perf_counter()
        resultado = accion()
        duraciones[nombre] = round((time.perf_counter() - inicio) * 1000)
        emitir({"event": "progress", "stage": nombre, "progress": ETAPAS[nombre]})
        return resultado

    emitir({"event": "progress", "stage": "preparacion", "progress": 1})
    nombres = etapa("preparacion", lambda: preparar_imagenes(payload["images"], trabajo / "imagenes"))
    mascaras = etapa("mascaras", lambda: generar_mascaras(payload, opciones, nombres, trabajo))
    if opciones["capture_mode"] == "plato_giratorio" and not mascaras["used"]:
        raise ReconstruccionError("mascaras", "El modo plato giratorio necesita las máscaras de IA del juguete y no se pudieron generar. Revisa el modelo de segmentación.")
    solo_juguete = ["--ImageReader.mask_path", "mascaras_sfm"] if mascaras.get("for_alignment") else []
    gpu = "1" if opciones["use_gpu"] else "0"
    comunes = ["--database_path", "base.db"]

    robusto = bool(opciones["robust_features"])

    def extraer() -> str:
        argumentos = [colmap, "feature_extractor", *comunes, "--image_path", "imagenes", "--ImageReader.single_camera", "1",
                      "--ImageReader.camera_model", "SIMPLE_RADIAL", "--FeatureExtraction.max_image_size", str(opciones["max_image_size"]), *solo_juguete]
        if robusto:
            ejecutar("caracteristicas", [*argumentos, *SIFT_ROBUSTO, "--FeatureExtraction.use_gpu", "0"], trabajo, log, entorno)
            return gpu
        try:
            ejecutar("caracteristicas", [*argumentos, "--FeatureExtraction.use_gpu", gpu], trabajo, log, entorno)
            return gpu
        except ReconstruccionError:
            if gpu == "0":
                raise
            (trabajo / "base.db").unlink(missing_ok=True)
            ejecutar("caracteristicas", [*argumentos, "--FeatureExtraction.use_gpu", "0"], trabajo, log, entorno)
            return "0"

    gpu = etapa("caracteristicas", extraer)
    etapa("correspondencias", lambda: ejecutar("correspondencias", [colmap, "exhaustive_matcher", *comunes, "--FeatureMatching.use_gpu", gpu, "--FeatureMatching.guided_matching", "1"], trabajo, log, entorno))
    (trabajo / "sparse").mkdir()
    etapa("estructura", lambda: ejecutar("estructura", [colmap, "mapper", *comunes, "--image_path", "imagenes", "--output_path", "sparse", *(MAPPER_ROBUSTO if robusto else [])], trabajo, log, entorno))
    modelo, estadisticas = mejor_modelo(colmap, trabajo / "sparse", trabajo, log)
    camaras = leer_camaras(colmap, modelo, trabajo, log)
    no_registradas = sorted(identificador for nombre, identificador in nombres.items() if nombre not in camaras)
    proporcion = len(camaras) / len(nombres)
    if proporcion < opciones["min_registered_ratio"]:
        raise ReconstruccionError("estructura", (
            f"Solo se ubicaron {len(camaras)} de {len(nombres)} cámaras. Revisa el solapamiento de las fotos no ubicadas "
            f"(ids: {', '.join(map(str, no_registradas[:15]))}) y vuelve a intentarlo."
        ))
    coherencia = verificar_camaras(camaras, trabajo / "sparse_txt" / "points3D.txt", opciones["max_camera_distance_ratio"])
    if not coherencia["consistent"]:
        raise ReconstruccionError("estructura", (
            "Las posiciones de cámara calculadas no son coherentes: algunas quedaron a distancias imposibles del juguete. "
            "Suele pasar con juguetes lisos o brillantes con pocos detalles. Pon una base con textura (periódico o revista) "
            "debajo del juguete, usa luz suave y vuelve a capturar."
        ))

    denso = trabajo / "denso"

    def correccion() -> None:
        ejecutar("correccion", [colmap, "image_undistorter", "--image_path", "imagenes", "--input_path", str(modelo),
                                "--output_path", "denso", "--output_type", "COLMAP", "--max_image_size", str(opciones["max_image_size"])], trabajo, log, entorno)
        if mascaras["used"]:
            corregir_mascaras(payload, opciones, modelo, trabajo, denso, log, entorno)

    etapa("correccion", correccion)
    con_mascaras = ["--ignore-mask-label", "0"] if mascaras["used"] else []
    herramienta = lambda nombre: str(openmvs / (nombre + (".exe" if os.name == "nt" else "")))  # noqa: E731
    hilos = ["--max-threads", str(opciones["threads"]), "-v", "1"]
    # En OpenMVS 2.4 el nivelado de costuras deja zonas negras en la textura; se deja configurable.
    nivelado = "1" if opciones["seam_leveling"] else "0"

    def nube_densa() -> None:
        ejecutar("nube_densa", [herramienta("InterfaceCOLMAP"), "-i", ".", "-o", "escena.mvs", "--image-folder", "images", *hilos], denso, log)
        ejecutar("nube_densa", [herramienta("DensifyPointCloud"), "escena.mvs", "--resolution-level", str(opciones["densify_resolution_level"]),
                                "--max-resolution", str(opciones["max_image_size"]), *(["-m", "images", *con_mascaras] if con_mascaras else []), *hilos], denso, log)

    etapa("nube_densa", nube_densa)
    etapa("malla", lambda: ejecutar("malla", [herramienta("ReconstructMesh"), "escena_dense.mvs", "--target-face-num", str(opciones["target_faces"]), *hilos], denso, log))

    def refinado() -> str:
        """RefineMesh ajusta la malla comparándola con las fotos: superficies más limpias y detalles más definidos."""
        if not opciones["refine_mesh"]:
            return "escena_dense_mesh.ply"
        try:
            ejecutar("refinado", [herramienta("RefineMesh"), "escena_dense.mvs", "-m", "escena_dense_mesh.ply", "-o", "refinada.mvs",
                                  "--resolution-level", str(opciones["refine_resolution_level"]), "--max-face-area", str(opciones["refine_max_face_area"]),
                                  "--close-holes", str(opciones["close_holes"]), *hilos], denso, log)
        except ReconstruccionError:
            return "escena_dense_mesh.ply"
        return "refinada.ply" if (denso / "refinada.ply").is_file() else "escena_dense_mesh.ply"

    malla_final = etapa("refinado", refinado)

    def textura() -> None:
        # La textura toma color solo del interior del juguete, para no arrastrar el borde del fondo.
        if con_mascaras and (trabajo / "mascaras_textura").is_dir():
            for estricta in (trabajo / "mascaras_textura").glob("*.mask.png"):
                shutil.copyfile(estricta, denso / "images" / estricta.name)
        ejecutar("textura", [herramienta("TextureMesh"), "escena_dense.mvs", "-m", malla_final, "--export-type", "glb",
                             "-o", "modelo_texturizado.glb", "--max-texture-size", str(opciones["texture_size"]), "--close-holes", str(opciones["close_holes"]),
                             "--global-seam-leveling", nivelado, "--local-seam-leveling", nivelado, *con_mascaras, *hilos], denso, log)

    etapa("textura", textura)

    puntos = np.array([camara["center"] for camara in camaras.values()])
    frente = next((nombre for nombre, identificador in nombres.items() if identificador == payload.get("front_image_id")), None)
    orientacion = matriz_orientacion(camaras, frente, puntos.mean(axis=0))
    salida = Path(payload["output_path"])
    salida.parent.mkdir(parents=True, exist_ok=True)
    malla = etapa("empaquetado", lambda: empaquetar_glb(denso / "modelo_texturizado.glb", salida, orientacion, opciones))

    for temporal in ("imagenes", "denso", "sparse_txt", "base.db", "mascaras_origen", "mascaras_denso", "mascaras_sfm", "mascaras_textura"):
        ruta = trabajo / temporal
        shutil.rmtree(ruta, ignore_errors=True) if ruta.is_dir() else ruta.unlink(missing_ok=True)

    return {
        "event": "result",
        "version": VERSION,
        "glb_bytes": salida.stat().st_size,
        "registered_images": len(camaras),
        "total_images": len(nombres),
        "unregistered_ids": no_registradas,
        "sparse_points": int(estadisticas.get("points", 0)),
        "reprojection_error": round(estadisticas.get("reprojection_error", 0.0), 4),
        "track_length": round(estadisticas.get("track_length", 0.0), 3),
        "gpu_used": gpu == "1",
        "masks": mascaras,
        "durations_ms": duraciones,
        "duration_ms": round((time.perf_counter() - inicio_total) * 1000),
        "parameters": opciones,
        **malla,
    }


def main() -> int:
    etapa_actual = "preparacion"
    try:
        crudo = sys.stdin.buffer.read(MAX_INPUT_BYTES + 1)
        if len(crudo) > MAX_INPUT_BYTES:
            raise ReconstruccionError("preparacion", "La solicitud supera el tamaño permitido.")
        emitir(reconstruir(validar_solicitud(json.loads(crudo.decode("utf-8-sig")))))
        return 0
    except ReconstruccionError as error:
        emitir({"event": "error", "stage": error.stage, "message": str(error)})
        return 2
    except Exception as error:  # noqa: BLE001
        emitir({"event": "error", "stage": etapa_actual, "message": f"Falló la reconstrucción local: {type(error).__name__}."})
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
