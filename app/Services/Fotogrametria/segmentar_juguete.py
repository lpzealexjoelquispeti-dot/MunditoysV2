"""Máscaras del juguete con BiRefNet (ONNX) en la GPU; si CUDA no está disponible, usa la CPU.

Uso por lotes: recibe por stdin {"model": str, "images": [{"id": int, "path": str, "output": str}]}
y emite líneas JSON: {"event": "mask", "id", "foreground"}, {"event": "mask_error", "id", "message"}
y al final {"event": "result", "provider", "duration_ms"}. La máscara PNG (0 fondo, 255 juguete)
tiene el tamaño de la foto ya orientada según su EXIF.
"""

from __future__ import annotations

import json
import sys
import time
from pathlib import Path
from typing import Any

import numpy as np
from PIL import Image, ImageOps

TAMANO_MODELO = 1024
MEDIA = np.array([0.485, 0.456, 0.406], dtype=np.float32)
DESVIACION = np.array([0.229, 0.224, 0.225], dtype=np.float32)


class Segmentador:
    """Carga el modelo una sola vez; la asignación de memoria exacta evita desbordar los 6 GB de VRAM."""

    def __init__(self, modelo: str) -> None:
        import onnxruntime as ort

        if hasattr(ort, "preload_dlls"):
            ort.preload_dlls()
        ort.set_default_logger_severity(3)
        proveedores: list[Any] = ["CPUExecutionProvider"]
        if "CUDAExecutionProvider" in ort.get_available_providers():
            proveedores.insert(0, ("CUDAExecutionProvider", {
                "cudnn_conv_algo_search": "HEURISTIC",
                "arena_extend_strategy": "kSameAsRequested",
            }))
        self.sesion = ort.InferenceSession(modelo, providers=proveedores)
        self.entrada = self.sesion.get_inputs()[0].name
        self.proveedor = self.sesion.get_providers()[0]

    def mascara(self, imagen: Image.Image) -> np.ndarray:
        ancho, alto = imagen.size
        reducida = imagen.convert("RGB").resize((TAMANO_MODELO, TAMANO_MODELO), Image.Resampling.BILINEAR)
        tensor = ((np.asarray(reducida, np.float32) / 255 - MEDIA) / DESVIACION).transpose(2, 0, 1)[None]
        salida = self.sesion.run(None, {self.entrada: np.ascontiguousarray(tensor, dtype=np.float32)})[-1][0, 0]
        probabilidad = 1 / (1 + np.exp(-salida))
        escalada = Image.fromarray((probabilidad * 255).astype(np.uint8)).resize((ancho, alto), Image.Resampling.BILINEAR)
        return np.where(np.asarray(escalada) > 127, 255, 0).astype(np.uint8)


def emitir(evento: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(evento, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def main() -> int:
    inicio = time.perf_counter()
    try:
        solicitud = json.loads(sys.stdin.buffer.read().decode("utf-8-sig"))
        if not Path(solicitud.get("model", "")).is_file():
            raise ValueError("No se encontró el modelo de segmentación.")
        segmentador = Segmentador(solicitud["model"])
    except Exception as error:  # noqa: BLE001
        emitir({"event": "error", "message": f"No se pudo iniciar la segmentación: {type(error).__name__}."})
        return 2

    for imagen in solicitud.get("images", []):
        try:
            with Image.open(imagen["path"]) as original:
                mascara = segmentador.mascara(ImageOps.exif_transpose(original))
            destino = Path(imagen["output"])
            destino.parent.mkdir(parents=True, exist_ok=True)
            Image.fromarray(mascara).save(destino, "PNG", optimize=True)
            emitir({"event": "mask", "id": imagen["id"], "foreground": round(float((mascara > 0).mean()), 4)})
        except Exception as error:  # noqa: BLE001
            emitir({"event": "mask_error", "id": imagen.get("id"), "message": type(error).__name__})

    emitir({"event": "result", "provider": segmentador.proveedor, "duration_ms": round((time.perf_counter() - inicio) * 1000)})
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
