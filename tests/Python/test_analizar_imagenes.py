"""Synthetic regression cases, not accuracy measurements on real toy captures."""

import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFilter


SCRIPT = Path(__file__).resolve().parents[2] / "app" / "Services" / "Fotogrametria" / "analizar_imagenes.py"
SPEC = importlib.util.spec_from_file_location("analizar_imagenes", SCRIPT)
ENGINE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(ENGINE)


def textured_object(seed: int, shared_background: bool = False) -> Image.Image:
    rng = np.random.default_rng(seed)
    if shared_background:
        background_rng = np.random.default_rng(2026)
        gray = background_rng.integers(130, 230, (640, 640), dtype=np.uint8)
        image = Image.fromarray(np.repeat(gray[:, :, None], 3, axis=2))
    else:
        image = Image.new("RGB", (640, 640), (215, 215, 215))
    draw = ImageDraw.Draw(image)
    draw.rounded_rectangle((150, 110, 490, 530), radius=25, fill=(145, 45, 70))
    for _ in range(450):
        x, y = int(rng.integers(175, 455)), int(rng.integers(135, 495))
        radius = int(rng.integers(3, 10))
        color = (int(rng.integers(45, 240)), int(rng.integers(5, 95)), int(rng.integers(15, 170)))
        draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=color)
    return image


def rotated(image: Image.Image, degrees: float) -> Image.Image:
    return image.rotate(degrees, resample=Image.Resampling.BICUBIC, fillcolor=(215, 215, 215))


class ImageValidationTest(unittest.TestCase):
    def setUp(self) -> None:
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.folder = Path(self.directory.name)

    def photo(self, identifier: int, image: Image.Image) -> dict:
        path = self.folder / f"fotografía_{identifier}.png"
        image.save(path)
        return {"id": identifier, "path": str(path), "sha256": hashlib.sha256(path.read_bytes()).hexdigest()}

    def jpeg_photo(self, identifier: int, image: Image.Image, quality: int) -> dict:
        path = self.folder / f"fotografía_{identifier}.jpg"
        image.save(path, quality=quality)
        return {"id": identifier, "path": str(path), "sha256": hashlib.sha256(path.read_bytes()).hexdigest()}

    def analyze(self, photos: list[dict], reference_id: int = 1) -> dict:
        return ENGINE.analyze({"images": photos, "reference_id": reference_id})

    def test_accepts_transformed_views_of_textured_object_and_reports_evidence(self) -> None:
        original = textured_object(42)
        photos = [self.photo(1, original), self.photo(2, rotated(original, 7))]

        result = self.analyze(photos)

        self.assertEqual(["compatible", "compatible"], [image["status"] for image in result["images"]])
        self.assertGreaterEqual(result["pairs"][0]["inliers"], 16)
        self.assertEqual([1, 2], result["suggested_order"])
        self.assertTrue(all(image["metrics"]["mask_reliable"] for image in result["images"]))

    def test_rejects_exact_duplicate_while_preserving_selected_reference(self) -> None:
        original = textured_object(12)
        photos = [self.photo(1, original), self.photo(2, original), self.photo(3, rotated(original, 5))]

        result = self.analyze(photos, reference_id=2)

        self.assertEqual("rejected", result["images"][0]["status"])
        self.assertEqual(2, result["images"][0]["metrics"]["duplicate_of"])
        self.assertEqual("compatible", result["images"][1]["status"])
        self.assertEqual({2, 3}, set(result["suggested_order"]))

    def test_reviews_recoded_copies_without_connecting_them_and_preserves_reference(self) -> None:
        original = textured_object(42)
        photos = [self.jpeg_photo(identifier, original, quality) for identifier, quality in enumerate((90, 95, 98), 1)]
        photos.append(self.photo(4, rotated(original, 7)))

        result = self.analyze(photos, reference_id=2)

        self.assertEqual(["review", "compatible", "review", "compatible"], [image["status"] for image in result["images"]])
        for index in (0, 2):
            with self.subTest(photo_id=photos[index]["id"]):
                record = result["images"][index]
                self.assertEqual(2, record["metrics"]["visual_duplicate_of"])
                self.assertEqual(0, record["metrics"]["connections"])
                self.assertIn("no parece aportar una vista nueva", record["reasons"][0])
        self.assertEqual({2, 4}, set(result["suggested_order"]))
        self.assertTrue(all({pair["a"], pair["b"]} == {2, 4} for pair in result["pairs"]))

    def test_does_not_approve_a_collection_containing_only_recodings_of_one_capture(self) -> None:
        original = textured_object(42)
        photos = [self.jpeg_photo(identifier, original, quality) for identifier, quality in enumerate((90, 95, 98), 1)]

        result = self.analyze(photos)

        self.assertEqual(["review", "review", "review"], [image["status"] for image in result["images"]])
        self.assertEqual([], result["pairs"])
        self.assertEqual([], result["suggested_order"])
        self.assertNotIn("visual_duplicate_of", result["images"][0]["metrics"])

    def test_does_not_flag_neighboring_transformed_views_as_recoded_copies(self) -> None:
        original = textured_object(42)
        photos = [self.photo(1, original), self.photo(2, rotated(original, 1)), self.photo(3, rotated(original, 2))]

        result = self.analyze(photos)

        self.assertEqual(["compatible", "compatible", "compatible"], [image["status"] for image in result["images"]])
        self.assertTrue(all("visual_duplicate_of" not in image["metrics"] for image in result["images"]))
        self.assertEqual({1, 2, 3}, set(result["suggested_order"]))

    def test_does_not_accept_different_objects_on_identical_textured_background(self) -> None:
        photos = [self.photo(1, textured_object(42, True)), self.photo(2, textured_object(97, True))]

        result = self.analyze(photos)

        self.assertEqual(["review", "review"], [image["status"] for image in result["images"]])
        self.assertFalse(any(pair["compatible"] for pair in result["pairs"]))
        self.assertEqual([], result["suggested_order"])

    def test_uses_reference_group_even_when_another_group_is_larger(self) -> None:
        reference_object = textured_object(42)
        other_object = textured_object(97)
        photos = [
            self.photo(1, reference_object), self.photo(2, rotated(reference_object, 7)),
            self.photo(3, other_object), self.photo(4, rotated(other_object, -6)), self.photo(5, rotated(other_object, 9)),
        ]

        result = self.analyze(photos)

        self.assertEqual(["compatible", "compatible", "review", "review", "review"], [image["status"] for image in result["images"]])
        self.assertEqual({1, 2}, set(result["suggested_order"]))
        self.assertNotEqual(result["images"][0]["metrics"]["group"], result["images"][2]["metrics"]["group"])

    def test_requires_review_for_blur_and_uncertain_foreground(self) -> None:
        original = textured_object(42)
        photos = [self.photo(1, original.filter(ImageFilter.GaussianBlur(9))), self.photo(2, Image.new("RGB", (640, 640), "white"))]

        result = self.analyze(photos)

        self.assertTrue(all(image["status"] == "review" for image in result["images"]))
        self.assertLess(result["images"][0]["metrics"]["sharpness"], 55)
        self.assertTrue(any("desenfoque" in reason for reason in result["images"][0]["reasons"]))
        self.assertFalse(result["images"][1]["metrics"]["mask_reliable"])
        self.assertEqual([], result["suggested_order"])

    def test_rejects_corrupt_low_resolution_and_changed_files_without_stopping_session(self) -> None:
        corrupt = self.folder / "corrupt.png"
        corrupt.write_bytes(b"not an image")
        changed = self.photo(3, textured_object(42))
        changed["sha256"] = "0" * 64
        photos = [
            {"id": 1, "path": str(corrupt)},
            self.photo(2, Image.new("RGB", (256, 256), "blue")), changed,
        ]

        result = self.analyze(photos)

        self.assertEqual(["rejected"] * 3, [image["status"] for image in result["images"]])
        self.assertIn("Resolución insuficiente", result["images"][1]["reasons"][0])
        self.assertIn("cambió", result["images"][2]["reasons"][0])

    def test_does_not_approve_isolated_reference(self) -> None:
        result = self.analyze([self.photo(1, textured_object(42))])

        self.assertEqual("review", result["images"][0]["status"])
        self.assertEqual(0, result["images"][0]["metrics"]["connections"])
        self.assertEqual([], result["suggested_order"])

    def test_rejects_oversized_batch_and_missing_reference(self) -> None:
        for payload in [
            {"images": [{"id": number, "path": "unused"} for number in range(49)], "reference_id": 1},
            {"images": [{"id": 1, "path": "unused"}], "reference_id": 2},
        ]:
            with self.subTest(payload=payload):
                with self.assertRaises(ENGINE.AnalysisError):
                    ENGINE.analyze(payload)

    def test_cli_returns_only_utf8_json_and_does_not_modify_originals(self) -> None:
        source = self.photo(1, textured_object(42))
        before = Path(source["path"]).read_bytes()

        result = subprocess.run([sys.executable, str(SCRIPT)], input=json.dumps({"images": [source], "reference_id": 1}).encode("utf-8"), capture_output=True, check=False)

        self.assertEqual(0, result.returncode, result.stderr.decode("utf-8"))
        report = json.loads(result.stdout.decode("utf-8"))
        self.assertEqual("opencv-sift-v2", report["version"])
        self.assertEqual(1, report["reference_id"])
        self.assertEqual(before, Path(source["path"]).read_bytes())
        self.assertEqual(b"", result.stderr)


if __name__ == "__main__":
    unittest.main()
