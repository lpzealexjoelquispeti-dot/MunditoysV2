"""Local, conservative photo preflight; compatibility is evidence, not identity.

The thresholds are an initial heuristic baseline for CRISP-DM evaluation. They
must be calibrated on labelled toy captures before reporting accuracy. This
process neither reconstructs a model nor estimates semantic camera directions.
"""

from __future__ import annotations

import hashlib
import json
import math
import os
from pathlib import Path
import sys
import time
from typing import Any
import warnings

os.environ["OMP_NUM_THREADS"] = "2"
os.environ["OPENBLAS_NUM_THREADS"] = "2"
os.environ["MKL_NUM_THREADS"] = "2"

DEPENDENCY_ERROR: str | None = None
try:
    import cv2
    import numpy as np
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError:
    DEPENDENCY_ERROR = "Instala las dependencias locales del validador: OpenCV, NumPy y Pillow."


VERSION = "opencv-sift-v2"
MAX_IMAGES = 48
MAX_FILE_BYTES = 25 * 1024 * 1024
MAX_PIXELS = 40_000_000
MAX_INPUT_BYTES = 1_048_576
DEFAULTS = {
    "analysis_size": 1024,
    "min_side": 480,
    "max_features": 1400,
    "sharpness_threshold": 55.0,
    "exposure_fraction": 0.50,
    "ratio_threshold": 0.72,
    "min_inliers": 16,
    "min_inlier_ratio": 0.50,
    "min_spread": 0.08,
    "redundancy_mean_threshold": 0.008,
    "redundancy_p95_threshold": 0.025,
}


class AnalysisError(ValueError):
    """A request or local runtime cannot safely be analysed."""


def validated_request(payload: Any) -> tuple[list[dict[str, Any]], int, dict[str, Any]]:
    if not isinstance(payload, dict):
        raise AnalysisError("La entrada debe ser un objeto JSON.")
    images = payload.get("images")
    if not isinstance(images, list) or not 1 <= len(images) <= MAX_IMAGES:
        raise AnalysisError("La sesión debe contener entre 1 y 48 fotografías.")
    identifiers: set[int] = set()
    for image in images:
        if not isinstance(image, dict) or type(image.get("id")) is not int:
            raise AnalysisError("Cada fotografía debe tener un identificador entero.")
        if image["id"] in identifiers:
            raise AnalysisError("Los identificadores de fotografías no pueden repetirse.")
        identifiers.add(image["id"])
        if not isinstance(image.get("path"), str) or not image["path"]:
            raise AnalysisError("Cada fotografía debe tener una ruta local absoluta.")
    reference_id = payload.get("reference_id")
    if type(reference_id) is not int or reference_id not in identifiers:
        raise AnalysisError("Selecciona una fotografía de referencia de esta sesión.")
    options = payload.get("options", {})
    if not isinstance(options, dict):
        raise AnalysisError("Las opciones deben ser un objeto JSON.")
    settings = dict(DEFAULTS)
    bounds = {
        "analysis_size": (512, 1024),
        "min_side": (320, 1024),
        "max_features": (400, 1400),
        "sharpness_threshold": (10, 150),
        "exposure_fraction": (0.25, 0.80),
        "ratio_threshold": (0.60, 0.78),
        "min_inliers": (12, 40),
        "min_inlier_ratio": (0.40, 0.80),
        "min_spread": (0.05, 0.25),
        "redundancy_mean_threshold": (0.001, 0.01),
        "redundancy_p95_threshold": (0.005, 0.03),
    }
    for key, value in options.items():
        if key not in bounds:
            continue
        low, high = bounds[key]
        if type(value) not in (int, float) or not math.isfinite(value) or not low <= value <= high:
            raise AnalysisError(f"La opción {key} está fuera del rango permitido.")
        settings[key] = int(value) if type(DEFAULTS[key]) is int else float(value)
    return images, reference_id, settings


def image_record(identifier: int) -> dict[str, Any]:
    return {
        "id": identifier,
        "status": "review",
        "reasons": [],
        "metrics": {
            "width": None,
            "height": None,
            "sharpness": None,
            "dark_fraction": None,
            "bright_fraction": None,
            "features": 0,
            "connections": 0,
            "group": None,
            "foreground_fraction": None,
            "masking": "unavailable",
            "mask_reliable": False,
        },
    }


def foreground_mask(rgb: Any) -> tuple[Any, dict[str, Any]]:
    """Estimate one central foreground; ambiguous segmentation cannot pass."""
    height, width = rgb.shape[:2]
    scale = min(1.0, 512 / max(width, height))
    small = cv2.resize(rgb, (round(width * scale), round(height * scale)), interpolation=cv2.INTER_AREA)
    small_height, small_width = small.shape[:2]
    margin_x, margin_y = max(2, round(small_width * 0.06)), max(2, round(small_height * 0.06))
    rectangle = (margin_x, margin_y, small_width - 2 * margin_x, small_height - 2 * margin_y)
    labels = np.zeros((small_height, small_width), np.uint8)
    cv2.grabCut(
        small,
        labels,
        rectangle,
        np.zeros((1, 65), np.float64),
        np.zeros((1, 65), np.float64),
        4,
        cv2.GC_INIT_WITH_RECT,
    )
    estimated = np.isin(labels, (cv2.GC_FGD, cv2.GC_PR_FGD)).astype(np.uint8)
    count, components, stats, centers = cv2.connectedComponentsWithStats(estimated, connectivity=8)
    metrics: dict[str, Any] = {"masking": "grabcut_centered", "mask_reliable": False, "foreground_fraction": 0.0}
    if count < 2:
        return np.zeros((height, width), np.uint8), metrics
    component = max(range(1, count), key=lambda index: int(stats[index, cv2.CC_STAT_AREA]))
    selected = (components == component).astype(np.uint8)
    area = int(stats[component, cv2.CC_STAT_AREA])
    fraction = area / selected.size
    center_x, center_y = centers[component]
    center_distance = math.hypot((center_x / small_width) - 0.5, (center_y / small_height) - 0.5)
    rectangle_border = np.zeros_like(selected)
    cv2.rectangle(rectangle_border, (margin_x, margin_y), (small_width - margin_x - 1, small_height - margin_y - 1), 1, 3)
    border_contact = float(np.count_nonzero(selected & rectangle_border) / max(1, np.count_nonzero(rectangle_border)))
    dominance = area / max(1, int(np.count_nonzero(estimated)))
    reliable = 0.025 <= fraction <= 0.70 and center_distance <= 0.24 and border_contact < 0.12 and dominance >= 0.75
    mask = cv2.resize(selected * 255, (width, height), interpolation=cv2.INTER_NEAREST)
    metrics.update({
        "foreground_fraction": round(fraction, 4),
        "mask_reliable": bool(reliable),
        "mask_border_contact": round(border_contact, 4),
        "mask_dominance": round(dominance, 4),
    })
    return mask, metrics


def load_photo(source: dict[str, Any], record: dict[str, Any], settings: dict[str, Any]) -> tuple[Any, str]:
    path_text = source["path"]
    path = Path(path_text)
    if not path.is_absolute() or path_text.startswith(("\\\\", "//")) or not path.is_file():
        raise AnalysisError("El archivo no está disponible en una ruta local válida.")
    if path.stat().st_size > MAX_FILE_BYTES:
        raise AnalysisError("La fotografía supera el límite de 25 MB por archivo.")
    with path.open("rb") as stream:
        digest = hashlib.file_digest(stream, "sha256").hexdigest()
    expected_digest = source.get("sha256")
    if expected_digest and expected_digest != digest:
        raise AnalysisError("El archivo cambió desde su carga. Vuelve a subir esta fotografía.")
    with warnings.catch_warnings():
        warnings.simplefilter("error", Image.DecompressionBombWarning)
        with Image.open(path) as original:
            width, height = original.size
            record["metrics"].update({"width": width, "height": height, "sha256": digest})
            if width * height > MAX_PIXELS:
                raise AnalysisError("La fotografía supera los 40 megapíxeles. Reduce su resolución antes de subirla.")
            if min(width, height) < settings["min_side"]:
                raise AnalysisError(f"Resolución insuficiente: el lado menor debe tener al menos {settings['min_side']} píxeles.")
            if original.format not in ("JPEG", "PNG", "WEBP") or getattr(original, "n_frames", 1) != 1:
                raise AnalysisError("Usa una fotografía estática JPG, PNG o WebP.")
            original.verify()
        with Image.open(path) as original:
            original.draft("RGB", (settings["analysis_size"], settings["analysis_size"]))
            oriented = ImageOps.exif_transpose(original)
            oriented.thumbnail((settings["analysis_size"], settings["analysis_size"]), Image.Resampling.LANCZOS)
            rgb = np.asarray(oriented.convert("RGB"))
    return rgb, digest


def prepare_photo(source: dict[str, Any], settings: dict[str, Any], hashes: dict[str, int], sift: Any) -> dict[str, Any]:
    record = image_record(source["id"])
    prepared = {"record": record, "descriptors": None, "keypoints": [], "mask_area": 0, "mask_bounds": None, "matcher": None}
    try:
        rgb, digest = load_photo(source, record, settings)
        if digest in hashes:
            record["status"] = "rejected"
            record["metrics"]["duplicate_of"] = hashes[digest]
            record["reasons"].append(f"Duplicado exacto de la fotografía {hashes[digest]}. Sustitúyela por una vista diferente.")
            return prepared
        hashes[digest] = source["id"]
        gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
        mask, mask_metrics = foreground_mask(rgb)
        record["metrics"].update(mask_metrics)
        reliable = mask_metrics["mask_reliable"]
        quality_region = mask > 0 if reliable else np.ones(gray.shape, dtype=bool)
        laplacian = cv2.Laplacian(gray, cv2.CV_64F)
        sharpness = float(laplacian[quality_region].var())
        dark_fraction = float(np.mean(gray[quality_region] <= 10))
        bright_fraction = float(np.mean(gray[quality_region] >= 245))
        record["metrics"].update({
            "sharpness": round(sharpness, 2),
            "dark_fraction": round(dark_fraction, 4),
            "bright_fraction": round(bright_fraction, 4),
            "quality_region": "foreground" if reliable else "whole_image_uncertain",
            "analysis_width": gray.shape[1],
            "analysis_height": gray.shape[0],
        })
        if sharpness < settings["sharpness_threshold"]:
            record["reasons"].append("Poco detalle o posible desenfoque. Revisa el enfoque y la textura del juguete.")
        if dark_fraction > settings["exposure_fraction"]:
            record["reasons"].append("Predominan zonas muy oscuras. Revisa la iluminación y los detalles visibles.")
        if bright_fraction > settings["exposure_fraction"]:
            record["reasons"].append("Predominan zonas muy claras. Revisa reflejos y posible sobreexposición.")
        if not reliable:
            record["reasons"].append("No se pudo separar el juguete del fondo con confianza. Centra el objeto completo y usa un fondo sencillo contrastante.")
            return prepared
        keypoints, descriptors = sift.detectAndCompute(gray, mask)
        if descriptors is not None:
            distance = cv2.distanceTransform(mask, cv2.DIST_L2, 3)
            interior_indices = [
                index for index, point in enumerate(keypoints)
                if distance[min(gray.shape[0] - 1, round(point.pt[1])), min(gray.shape[1] - 1, round(point.pt[0]))] >= max(3, point.size * 1.5)
            ]
            keypoints = [keypoints[index] for index in interior_indices]
            descriptors = np.ascontiguousarray(descriptors[interior_indices], dtype=np.float32)
        record["metrics"]["features"] = len(keypoints)
        if descriptors is None or len(keypoints) < settings["min_inliers"]:
            record["reasons"].append("No hay suficientes detalles del objeto para verificar correspondencias entre vistas.")
            return prepared
        points = cv2.findNonZero(mask)
        prepared.update({
            "keypoints": keypoints,
            "descriptors": descriptors,
            "mask_area": int(np.count_nonzero(mask)),
            "mask_bounds": cv2.boundingRect(points),
            "comparison_rgb": cv2.resize(rgb, (256, 256), interpolation=cv2.INTER_AREA),
            "comparison_mask": cv2.resize(mask, (256, 256), interpolation=cv2.INTER_NEAREST) > 0,
            "aspect_ratio": rgb.shape[1] / rgb.shape[0],
        })
        matcher = cv2.FlannBasedMatcher(dict(algorithm=1, trees=4), dict(checks=48))
        matcher.add([descriptors])
        matcher.train()
        prepared["matcher"] = matcher
    except AnalysisError as error:
        record["status"] = "rejected"
        record["reasons"].append(str(error))
    except (OSError, ValueError, UnidentifiedImageError, Image.DecompressionBombError, Image.DecompressionBombWarning):
        record["status"] = "rejected"
        record["reasons"].append("No se pudo leer la fotografía de forma segura. El archivo puede estar dañado.")
    except cv2.error:
        record["reasons"].append("No fue posible analizar el objeto de esta fotografía. Revisa el encuadre y vuelve a intentarlo.")
    return prepared


def flag_visual_redundancy(photos: list[dict[str, Any]], settings: dict[str, Any]) -> None:
    """Abstain on near-identical, unaligned captures; keep reference-first representatives."""
    representatives: list[dict[str, Any]] = []
    for photo in photos:
        record = photo["record"]
        if photo["matcher"] is None or record["reasons"]:
            continue
        for earlier in representatives:
            if abs(photo["aspect_ratio"] - earlier["aspect_ratio"]) > 0.001:
                continue
            region = photo["comparison_mask"] | earlier["comparison_mask"]
            difference = np.abs(photo["comparison_rgb"].astype(np.int16) - earlier["comparison_rgb"].astype(np.int16))
            difference = difference[region].mean(axis=1) / 255.0
            mean_difference = float(difference.mean())
            if mean_difference > settings["redundancy_mean_threshold"]:
                continue
            p95_difference = float(np.percentile(difference, 95))
            if p95_difference > settings["redundancy_p95_threshold"]:
                continue
            representative_id = earlier["record"]["id"]
            record["metrics"].update({
                "visual_duplicate_of": representative_id,
                "redundancy_mean_difference": round(mean_difference, 6),
                "redundancy_p95_difference": round(p95_difference, 6),
            })
            record["reasons"].append(
                f"Posible copia recodificada o captura prácticamente idéntica a la fotografía {representative_id}: "
                "no parece aportar una vista nueva. Revisa y sustituye esta foto por otra posición de cámara."
            )
            photo.update({"matcher": None, "descriptors": None, "keypoints": []})
            break
        else:
            representatives.append(photo)
    for photo in photos:
        photo.pop("comparison_rgb", None)
        photo.pop("comparison_mask", None)


def spatial_spread(points: Any, photo: dict[str, Any]) -> tuple[float, int]:
    if len(points) < 3:
        return 0.0, 0
    hull_area = float(cv2.contourArea(cv2.convexHull(points.astype(np.float32))))
    x, y, width, height = photo["mask_bounds"]
    quadrants = {
        (int(point[0] >= x + width / 2), int(point[1] >= y + height / 2))
        for point in points
    }
    return hull_area / max(1, photo["mask_area"]), len(quadrants)


def compare_pair(left: dict[str, Any], right: dict[str, Any], settings: dict[str, Any]) -> dict[str, Any]:
    result = {"a": left["record"]["id"], "b": right["record"]["id"], "inliers": 0, "ratio": 0.0, "compatible": False, "matches": 0, "geometry": None, "spread": 0.0}
    if left["matcher"] is None or right["matcher"] is None:
        return result
    try:
        forward = right["matcher"].knnMatch(left["descriptors"], k=2)
        backward = left["matcher"].knnMatch(right["descriptors"], k=2)
        reverse_good = {
            (pair[0].trainIdx, pair[0].queryIdx) for pair in backward
            if len(pair) == 2 and pair[0].distance < settings["ratio_threshold"] * pair[1].distance
        }
        matches = [
            pair[0] for pair in forward
            if len(pair) == 2 and pair[0].distance < settings["ratio_threshold"] * pair[1].distance
            and (pair[0].queryIdx, pair[0].trainIdx) in reverse_good
        ]
        result["matches"] = len(matches)
        if len(matches) < settings["min_inliers"]:
            return result
        source_points = np.float32([left["keypoints"][match.queryIdx].pt for match in matches])
        target_points = np.float32([right["keypoints"][match.trainIdx].pt for match in matches])
        candidates: list[tuple[str, Any]] = []
        fundamental, fundamental_mask = cv2.findFundamentalMat(source_points, target_points, cv2.FM_RANSAC, 2.0, 0.995, 2000)
        if fundamental is not None and fundamental.shape == (3, 3) and fundamental_mask is not None and np.isfinite(fundamental).all():
            candidates.append(("fundamental", fundamental_mask.ravel().astype(bool)))
        homography, homography_mask = cv2.findHomography(source_points, target_points, cv2.RANSAC, 3.0, maxIters=2000, confidence=0.995)
        if homography is not None and homography_mask is not None and np.isfinite(homography).all():
            candidates.append(("homography", homography_mask.ravel().astype(bool)))
        for geometry, inlier_mask in candidates:
            inliers = int(inlier_mask.sum())
            ratio = inliers / len(matches)
            left_spread, left_quadrants = spatial_spread(source_points[inlier_mask], left)
            right_spread, right_quadrants = spatial_spread(target_points[inlier_mask], right)
            spread = min(left_spread, right_spread)
            compatible = inliers >= settings["min_inliers"] and ratio >= settings["min_inlier_ratio"] and spread >= settings["min_spread"] and min(left_quadrants, right_quadrants) >= 3
            if (compatible, inliers) > (result["compatible"], result["inliers"]):
                result.update({"inliers": inliers, "ratio": round(ratio, 4), "compatible": bool(compatible), "geometry": geometry, "spread": round(spread, 4)})
    except cv2.error:
        result["error"] = "No se pudo verificar la geometría de este par."
    return result


def connected_groups(identifiers: list[int], adjacency: dict[int, dict[int, int]], reference_id: int) -> list[list[int]]:
    groups: list[list[int]] = []
    pending = set(identifiers)
    for identifier in [reference_id] + sorted(identifiers):
        if identifier not in pending:
            continue
        group: list[int] = []
        stack = [identifier]
        pending.remove(identifier)
        while stack:
            current = stack.pop()
            group.append(current)
            for neighbor in sorted(adjacency[current], reverse=True):
                if neighbor in pending:
                    pending.remove(neighbor)
                    stack.append(neighbor)
        groups.append(group)
    return groups


def suggested_sequence(reference_id: int, adjacency: dict[int, dict[int, int]], accepted: set[int]) -> list[int]:
    """A graph walk only; neither capture chronology nor a camera pose estimate."""
    if reference_id not in accepted:
        return []
    order: list[int] = []
    visited: set[int] = set()
    stack = [reference_id]
    while stack:
        current = stack.pop()
        if current in visited or current not in accepted:
            continue
        visited.add(current)
        order.append(current)
        neighbors = sorted(adjacency[current], key=lambda item: (adjacency[current][item], -item))
        stack.extend(neighbors)
    return order


def analyze(payload: Any) -> dict[str, Any]:
    started = time.perf_counter()
    images, reference_id, settings = validated_request(payload)
    if DEPENDENCY_ERROR:
        raise AnalysisError(DEPENDENCY_ERROR)
    cv2.setNumThreads(2)
    cv2.setRNGSeed(20260925)
    cv2.ocl.setUseOpenCL(False)
    sift = cv2.SIFT_create(nfeatures=settings["max_features"])
    hashes: dict[str, int] = {}
    prepared = {
        image["id"]: prepare_photo(image, settings, hashes, sift)
        for image in sorted(images, key=lambda image: image["id"] != reference_id)
    }
    flag_visual_redundancy(list(prepared.values()), settings)
    records = [prepared[image["id"]]["record"] for image in images]
    eligible = [photo for photo in prepared.values() if photo["matcher"] is not None and not photo["record"]["reasons"]]
    adjacency: dict[int, dict[int, int]] = {record["id"]: {} for record in records}
    pairs: list[dict[str, Any]] = []
    for index, left in enumerate(eligible):
        for right in eligible[index + 1:]:
            pair = compare_pair(left, right, settings)
            pairs.append(pair)
            if pair["compatible"]:
                adjacency[pair["a"]][pair["b"]] = pair["inliers"]
                adjacency[pair["b"]][pair["a"]] = pair["inliers"]
    identifiers = [record["id"] for record in records if record["status"] != "rejected"]
    groups = connected_groups(identifiers, adjacency, reference_id)
    reference_group = next((set(group) for group in groups if reference_id in group), set())
    group_by_id = {identifier: index + 1 for index, group in enumerate(groups) for identifier in group}
    accepted: set[int] = set()
    for record in records:
        identifier = record["id"]
        record["metrics"]["connections"] = len(adjacency[identifier])
        record["metrics"]["group"] = group_by_id.get(identifier)
        if record["status"] == "rejected":
            continue
        if not record["reasons"] and identifier in reference_group and adjacency[identifier]:
            record["status"] = "compatible"
            record["reasons"].append("Se encontraron correspondencias geométricas en el objeto conectadas a la referencia. Revisa visualmente la selección.")
            accepted.add(identifier)
        elif not record["reasons"]:
            record["reasons"].append("Sin conexión verificable con la referencia: puede ser otro objeto o faltar solapamiento. Revisa la foto o añade una vista intermedia.")
    return {
        "version": VERSION,
        "reference_id": reference_id,
        "images": records,
        "pairs": pairs,
        "suggested_order": suggested_sequence(reference_id, adjacency, accepted),
        "duration_ms": round((time.perf_counter() - started) * 1000),
        "parameters": settings,
        "limitations": [
            "Validación heurística experimental: no demuestra identidad del objeto ni éxito de reconstrucción.",
            "Un fondo compartido, texturas repetidas o juguetes idénticos pueden producir falsos positivos.",
            "La revisión de redundancia compara capturas sin alinearlas; no demuestra que todas las vistas sean distintas ni comprueba cobertura angular.",
            "El orden sugerido solo sigue correspondencias; frente, costado, arriba y abajo requieren clasificación manual.",
        ],
    }


def main() -> int:
    try:
        raw = sys.stdin.buffer.read(MAX_INPUT_BYTES + 1)
        if len(raw) > MAX_INPUT_BYTES:
            raise AnalysisError("La solicitud supera el tamaño permitido.")
        result = analyze(json.loads(raw.decode("utf-8-sig")))
    except (AnalysisError, UnicodeError, json.JSONDecodeError) as error:
        result = {"version": VERSION, "error": str(error), "images": [], "pairs": [], "suggested_order": []}
        code = 2
    except Exception:
        result = {"version": VERSION, "error": "Falló el análisis local de las fotografías.", "images": [], "pairs": [], "suggested_order": []}
        code = 1
    else:
        code = 0
    sys.stdout.buffer.write((json.dumps(result, ensure_ascii=False, allow_nan=False) + "\n").encode("utf-8"))
    return code


if __name__ == "__main__":
    raise SystemExit(main())
