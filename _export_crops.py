"""Xuất crop biển (best.pt) + chạy PaddleOCR trong .venv-paddle trên cùng crop."""
from __future__ import annotations

import json
import os
import re
import site
import sys
import time
from pathlib import Path

BASE = Path(__file__).resolve().parent
for py_ver in ["314", "313", "312", "311", "310"]:
    admin_path = fr"C:\Users\ADMIN\AppData\Roaming\Python\Python{py_ver}\site-packages"
    if os.path.exists(admin_path) and admin_path not in sys.path:
        sys.path.append(admin_path)
user_site = site.getusersitepackages()
if user_site and os.path.exists(user_site) and user_site not in sys.path:
    sys.path.append(user_site)

import cv2
from ultralytics import YOLO

LABELED = [
    ("public/uploads/vehicles/entry_1784804876_6a61f60c731bb.jpg", "29AF-82853"),
    ("public/uploads/vehicles/exit_1784804905_6a61f6295f7cb.jpg", "29AF-82853"),
    ("public/uploads/vehicles/entry_1784804425_6a61f449858f2.jpg", "29K1-02536"),
    ("public/uploads/vehicles/exit_1784804825_6a61f5d905705.jpg", "29K1-02536"),
    ("public/uploads/vehicles/entry_1784804106_6a61f30a3bd7e.jpg", "29S6-61468"),
    ("public/uploads/vehicles/exit_1784804118_6a61f3164f937.jpg", "29S6-61468"),
    ("public/uploads/vehicles/entry_1784803526_6a61f0c6a669a.jpg", "29S6-61468"),
    ("public/uploads/vehicles/entry_1784804100_6a61f30476da3.jpg", "92CA-03484"),
    ("public/uploads/vehicles/entry_1784641751_6a5f78d7cafe2.jpg", "92CA-03484"),
    ("public/uploads/vehicles/exit_1784641890_6a5f796238a9c.jpg", "92CA-03484"),
    ("public/uploads/vehicles/entry_1784804415_6a61f43f88883.jpg", "29K2-09456"),
    ("public/uploads/vehicles/entry_1784804087_6a61f2f76a89c.jpg", "29K2-09456"),
    ("public/uploads/vehicles/entry_1784804069_6a61f2e5b18e0.jpg", "29K2-09456"),
]


def crop_plate(img, plate_model):
    h, w = img.shape[:2]
    detect_img, scale = img, 1.0
    max_side = max(h, w)
    if max_side > 1280:
        scale = 1280.0 / max_side
        detect_img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)
    res = plate_model(detect_img, conf=0.25, verbose=False, imgsz=640)[0]
    if len(res.boxes) == 0:
        return img
    box = max(res.boxes, key=lambda b: float(b.conf[0]))
    x1, y1, x2, y2 = map(int, box.xyxy[0].cpu().numpy() / scale)
    x1, y1 = max(0, x1 - 8), max(0, y1 - 8)
    x2, y2 = min(w, x2 + 8), min(h, y2 + 8)
    if x2 <= x1 or y2 <= y1:
        return img
    return img[y1:y2, x1:x2]


def main():
    out_dir = BASE / "_bench_crops"
    out_dir.mkdir(exist_ok=True)
    plate_model = YOLO(str(BASE / "best.pt"))
    manifest = []
    for rel, gt in LABELED:
        path = BASE / rel.replace("/", os.sep)
        if not path.exists():
            continue
        img = cv2.imread(str(path))
        if img is None:
            continue
        crop = crop_plate(img, plate_model)
        name = path.stem + "_crop.jpg"
        crop_path = out_dir / name
        cv2.imwrite(str(crop_path), crop)
        manifest.append({"image": rel, "gt": gt, "crop": str(crop_path)})
        print(f"crop {name} <- {rel}", flush=True)
    (out_dir / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Wrote {len(manifest)} crops", flush=True)


if __name__ == "__main__":
    main()
