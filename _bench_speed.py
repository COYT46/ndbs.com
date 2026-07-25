"""
Đo tốc độ suy luận (ms, FPS) trên ảnh NDBS — CPU, có warmup.
Phạm vi:
  - YOLO detect (best.pt) trên ảnh full
  - YOLO read (bestRead.pt) trên crop biển
  - YOLO E2E = detect + read
  - EasyOCR trên cùng crop
  - (tuỳ chọn) PaddleOCR nếu có sẵn số từ ocr_benchmark_paddle.json
"""
from __future__ import annotations

import json
import os
import platform
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
import numpy as np
from ultralytics import YOLO

LABELED = [
    "public/uploads/vehicles/entry_1784804876_6a61f60c731bb.jpg",
    "public/uploads/vehicles/exit_1784804905_6a61f6295f7cb.jpg",
    "public/uploads/vehicles/entry_1784804425_6a61f449858f2.jpg",
    "public/uploads/vehicles/exit_1784804825_6a61f5d905705.jpg",
    "public/uploads/vehicles/entry_1784804106_6a61f30a3bd7e.jpg",
    "public/uploads/vehicles/exit_1784804118_6a61f3164f937.jpg",
    "public/uploads/vehicles/entry_1784803526_6a61f0c6a669a.jpg",
    "public/uploads/vehicles/entry_1784804100_6a61f30476da3.jpg",
    "public/uploads/vehicles/entry_1784641751_6a5f78d7cafe2.jpg",
    "public/uploads/vehicles/exit_1784641890_6a5f796238a9c.jpg",
    "public/uploads/vehicles/entry_1784804415_6a61f43f88883.jpg",
    "public/uploads/vehicles/entry_1784804087_6a61f2f76a89c.jpg",
    "public/uploads/vehicles/entry_1784804069_6a61f2e5b18e0.jpg",
]

WARMUP = 5
ROUNDS = 3  # mỗi ảnh chạy 3 lần timed (sau warmup) → trung bình ổn định hơn


def stats(ms_list: list[float]) -> dict:
    arr = sorted(ms_list)
    n = len(arr)
    if n == 0:
        return {
            "n": 0,
            "avg_ms": 0.0,
            "min_ms": 0.0,
            "max_ms": 0.0,
            "p50_ms": 0.0,
            "fps": 0.0,
        }
    avg = sum(arr) / n
    p50 = arr[n // 2] if n % 2 == 1 else 0.5 * (arr[n // 2 - 1] + arr[n // 2])
    return {
        "n": n,
        "avg_ms": round(avg, 1),
        "min_ms": round(arr[0], 1),
        "max_ms": round(arr[-1], 1),
        "p50_ms": round(p50, 1),
        "fps": round(1000.0 / avg, 2) if avg > 0 else 0.0,
    }


def prepare_detect(img):
    h, w = img.shape[:2]
    detect_img, scale = img, 1.0
    max_side = max(h, w)
    if max_side > 1280:
        scale = 1280.0 / max_side
        detect_img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)
    return detect_img, scale


def crop_from_detect(img, plate_model):
    h, w = img.shape[:2]
    detect_img, scale = prepare_detect(img)
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


def yolo_read(crop, read_model):
    return read_model(crop, conf=0.2, verbose=False, imgsz=640)


def easyocr_run(crop, reader):
    trial = crop
    if max(crop.shape[:2]) < 400:
        trial = cv2.resize(crop, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
    allow = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-"
    try:
        return reader.readtext(trial, detail=1, paragraph=False, allowlist=allow)
    except TypeError:
        return reader.readtext(trial, detail=1, paragraph=False)


def main():
    paths = []
    for rel in LABELED:
        p = BASE / rel.replace("/", os.sep)
        if p.exists() and p.stat().st_size > 0:
            paths.append((str(p), rel))
    if not paths:
        print(json.dumps({"success": False, "error": "No images"}))
        return

    print(f"Images: {len(paths)} | warmup={WARMUP} | rounds={ROUNDS}", flush=True)
    plate_model = YOLO(str(BASE / "best.pt"))
    read_model = YOLO(str(BASE / "bestRead.pt"))

    import easyocr
    easy_reader = easyocr.Reader(["en"], gpu=False, verbose=False)

    images = []
    crops = []
    for path, rel in paths:
        img = cv2.imread(path)
        if img is None:
            continue
        images.append((img, rel))
        crops.append((crop_from_detect(img, plate_model), rel))

    # Warmup
    for i in range(min(WARMUP, len(images))):
        img = images[i][0]
        detect_img, _ = prepare_detect(img)
        _ = plate_model(detect_img, conf=0.25, verbose=False, imgsz=640)
        _ = yolo_read(crops[i][0], read_model)
        _ = easyocr_run(crops[i][0], easy_reader)

    detect_ms, read_ms, e2e_ms, easy_ms = [], [], [], []
    per_image = []

    for (img, rel), (crop, _) in zip(images, crops):
        for _round in range(ROUNDS):
            detect_img, scale = prepare_detect(img)

            t0 = time.perf_counter()
            res = plate_model(detect_img, conf=0.25, verbose=False, imgsz=640)[0]
            d_ms = (time.perf_counter() - t0) * 1000

            # crop trong E2E (giống production)
            h, w = img.shape[:2]
            if len(res.boxes):
                box = max(res.boxes, key=lambda b: float(b.conf[0]))
                x1, y1, x2, y2 = map(int, box.xyxy[0].cpu().numpy() / scale)
                x1, y1 = max(0, x1 - 8), max(0, y1 - 8)
                x2, y2 = min(w, x2 + 8), min(h, y2 + 8)
                e2e_crop = img[y1:y2, x1:x2] if x2 > x1 and y2 > y1 else crop
            else:
                e2e_crop = crop

            t0 = time.perf_counter()
            _ = yolo_read(e2e_crop, read_model)
            r_ms = (time.perf_counter() - t0) * 1000

            # đo EasyOCR riêng trên crop cố định (công bằng giữa engines)
            t0 = time.perf_counter()
            _ = easyocr_run(crop, easy_reader)
            e_ms = (time.perf_counter() - t0) * 1000

            detect_ms.append(d_ms)
            read_ms.append(r_ms)
            e2e_ms.append(d_ms + r_ms)
            easy_ms.append(e_ms)
            per_image.append({
                "image": rel,
                "detect_ms": d_ms,
                "read_ms": r_ms,
                "e2e_ms": d_ms + r_ms,
                "easyocr_ms": e_ms,
            })
            print(
                f"{Path(rel).name}: detect={d_ms:.1f} read={r_ms:.1f} e2e={d_ms+r_ms:.1f} easy={e_ms:.1f}",
                flush=True,
            )

    stages = {
        "yolo_detect_best_pt": stats(detect_ms),
        "yolo_read_bestRead_pt": stats(read_ms),
        "yolo_e2e_detect_plus_read": stats(e2e_ms),
        "easyocr_on_crop": stats(easy_ms),
    }

    # Ghép Paddle từ file benchmark cũ nếu có (cùng 13 crop)
    paddle_path = BASE / "ocr_benchmark_paddle.json"
    if paddle_path.exists():
        try:
            paddle = json.loads(paddle_path.read_text(encoding="utf-8"))
            rows = paddle.get("rows") or []
            p_ms = [float(r["ms"]) for r in rows if "ms" in r]
            if p_ms:
                stages["paddleocr_on_crop"] = stats(p_ms)
                stages["paddleocr_on_crop"]["source"] = "ocr_benchmark_paddle.json"
        except Exception as e:
            stages["paddleocr_note"] = str(e)

    # Số từ ocr_benchmark_ndbs.json (read-only, không gồm detect) để đối chiếu
    ndbs_path = BASE / "ocr_benchmark_ndbs.json"
    legacy = {}
    if ndbs_path.exists():
        try:
            ndbs = json.loads(ndbs_path.read_text(encoding="utf-8"))
            for s in ndbs.get("summary") or []:
                avg = float(s.get("avg_latency_ms") or 0)
                legacy[s["engine"]] = {
                    "avg_ms": avg,
                    "fps": round(1000.0 / avg, 2) if avg > 0 else 0.0,
                }
        except Exception:
            pass

    out = {
        "success": True,
        "device": "CPU",
        "platform": platform.platform(),
        "python": sys.version.split()[0],
        "n_images": len(images),
        "warmup": WARMUP,
        "rounds": ROUNDS,
        "note": (
            "FPS = 1000 / avg_ms. Warmup không tính vào trung bình. "
            "YOLO E2E = detect(best.pt) + read(bestRead.pt). "
            "EasyOCR/PaddleOCR đo trên cùng crop do best.pt cắt."
        ),
        "stages": stages,
        "legacy_avg_from_ocr_benchmark_ndbs": legacy,
        "per_image": per_image,
    }

    out_path = BASE / "ocr_speed_benchmark.json"
    out_path.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")

    print("\n=== SUMMARY (ms / FPS) ===", flush=True)
    for name, st in stages.items():
        if not isinstance(st, dict) or "avg_ms" not in st:
            continue
        print(
            f"{name}: avg={st['avg_ms']} ms | p50={st['p50_ms']} ms | "
            f"min={st['min_ms']} max={st['max_ms']} | FPS={st['fps']}",
            flush=True,
        )
    print(f"Wrote {out_path}", flush=True)


if __name__ == "__main__":
    main()
