#!/usr/bin/env python3
"""Embed the official FedEx baseline workbook cover logo into project branding assets."""

from __future__ import annotations

import base64
import hashlib
import json
import struct
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "storage/app/temp-fedex-xlsx/xl/media/image1.png"
OUT_DIR = ROOT / "public/assets/carriers/fedex"
SVG_PATH = OUT_DIR / "fedex-unified-logo.svg"
PNG_COPY = OUT_DIR / "fedex-unified-logo-source.png"
META_PATH = ROOT / "resources/fedex-validation/branding/fedex-logo-metadata.json"


def main() -> None:
    png_bytes = SRC.read_bytes()
    width, height = struct.unpack(">II", png_bytes[16:24])
    encoded = base64.b64encode(png_bytes).decode("ascii")

    svg = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        f'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
        f'width="{width}" height="{height}" viewBox="0 0 {width} {height}" role="img" aria-label="FedEx">\n'
        "  <title>FedEx</title>\n"
        f'  <image width="{width}" height="{height}" xlink:href="data:image/png;base64,{encoded}" />\n'
        "</svg>\n"
    )

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    SVG_PATH.write_text(svg, encoding="utf-8", newline="\n")
    PNG_COPY.write_bytes(png_bytes)

    sha256 = hashlib.sha256(SVG_PATH.read_bytes()).hexdigest()
    metadata = {
        "source": "fedex_supplied",
        "filename": "fedex-unified-logo.svg",
        "public_path": "assets/carriers/fedex/fedex-unified-logo.svg",
        "sha256": sha256,
        "mime_type": "image/svg+xml",
        "approved_for_validation": True,
        "clear_space_x_ratio": 0.5,
        "notes": (
            "Embedded PNG extracted unchanged from FedEx_Integrator_Test_Case_Baseline.xlsx "
            "cover page (xl/media/image1.png)."
        ),
        "baseline_source": "FedEx_Integrator_Test_Case_Baseline.xlsx",
        "baseline_media_file": "xl/media/image1.png",
        "intrinsic_width": width,
        "intrinsic_height": height,
    }
    META_PATH.write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8")

    print(f"Created {SVG_PATH}")
    print(f"SHA256 {sha256}")
    print(f"Dimensions {width}x{height}")


if __name__ == "__main__":
    main()
