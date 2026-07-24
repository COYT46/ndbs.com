"""
Benchmark OCR trên ảnh thật của NDBS:
  - YOLO tự huấn luyện: best.pt (detect) + bestRead.pt (chars)
  - EasyOCR trên cùng crop biển
  - PaddleOCR trên cùng crop (nếu có)

Ground truth được gắn bằng mắt trên ảnh (không lấy plate_number từ DB
vì nhiều bản ghi là OCR sai trước đó).
"""
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
import numpy as np
from ultralytics import YOLO

# Ground truth gắn tay từ ảnh uploads/vehicles (đã xem trực tiếp).
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


def compact(text: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (text or "").upper())


def format_plate(text: str) -> str:
    c = compact(text)
    m = re.match(r"^(\d{2})([A-Z][A-Z0-9])(\d{4,5})$", c)
    if m:
        return f"{m.group(1)}{m.group(2)}-{m.group(3)}"
    m = re.match(r"^(\d{2})([A-Z])(\d{4,5})$", c)
    if m:
        return f"{m.group(1)}{m.group(2)}-{m.group(3)}"
    return c


def cer(pred: str, gt: str) -> float:
    a, b = compact(pred), compact(gt)
    if not b:
        return 1.0 if a else 0.0
    # Levenshtein
    n, m = len(a), len(b)
    dp = list(range(m + 1))
    for i in range(1, n + 1):
        prev, dp[0] = dp[0], i
        for j in range(1, m + 1):
            cur = dp[j]
            if a[i - 1] == b[j - 1]:
                dp[j] = prev
            else:
                dp[j] = 1 + min(prev, dp[j], dp[j - 1])
            prev = cur
    return dp[m] / m


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


def yolo_recognize(crop, read_model):
    # Logic rút gọn từ recognize.py: detect chars + lắp 2 dòng theo residual
    results = read_model(crop, conf=0.2, verbose=False, imgsz=640)[0]
    if results.boxes is None or len(results.boxes) == 0:
        return ""
    names = results.names
    chars = []
    for box in results.boxes:
        cls_id = int(box.cls[0])
        label = str(names.get(cls_id, "")).upper().strip()
        if not re.match(r"^[A-Z0-9]$", label):
            continue
        x1, y1, x2, y2 = box.xyxy[0].cpu().numpy()
        chars.append({
            "label": label,
            "conf": float(box.conf[0]),
            "xc": float((x1 + x2) / 2),
            "yc": float((y1 + y2) / 2),
        })
    if len(chars) < 5:
        return ""

    # NMS đơn giản theo khoảng cách
    chars = sorted(chars, key=lambda c: -c["conf"])
    kept = []
    for c in chars:
        if all(abs(c["xc"] - k["xc"]) + abs(c["yc"] - k["yc"]) > 10 for k in kept):
            kept.append(c)
    chars = kept
    if len(chars) < 5:
        return ""

    xs = np.array([c["xc"] for c in chars], dtype=float)
    ys = np.array([c["yc"] for c in chars], dtype=float)
    if xs.max() - xs.min() < 1e-3:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return format_plate("".join(c["label"] for c in ordered))

    a, b = np.polyfit(xs, ys, 1)
    residuals = ys - (a * xs + b)
    r_sorted = np.sort(residuals)
    best_gap, split_i = -1.0, 1
    for i in range(1, len(r_sorted)):
        gap = float(r_sorted[i] - r_sorted[i - 1])
        if gap > best_gap:
            best_gap, split_i = gap, i

    # một dòng nếu gap nhỏ
    if best_gap < max(8.0, 0.15 * crop.shape[0]):
        ordered = sorted(chars, key=lambda c: c["xc"])
        return format_plate("".join(c["label"] for c in ordered))

    thresh = (r_sorted[split_i - 1] + r_sorted[split_i]) / 2.0
    top = [c for c, r in zip(chars, residuals) if r <= thresh]
    bot = [c for c, r in zip(chars, residuals) if r > thresh]
    if not top or not bot:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return format_plate("".join(c["label"] for c in ordered))
    if np.mean([r for r in residuals if r <= thresh]) > np.mean([r for r in residuals if r > thresh]):
        top, bot = bot, top
    top = sorted(top, key=lambda c: c["xc"])
    bot = sorted(bot, key=lambda c: c["xc"])
    return format_plate("".join(c["label"] for c in top) + "-" + "".join(c["label"] for c in bot))


def easyocr_recognize(crop, reader):
    trial = crop
    if max(crop.shape[:2]) < 400:
        trial = cv2.resize(crop, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
    allow = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-"
    try:
        raw = reader.readtext(trial, detail=1, paragraph=False, allowlist=allow)
    except TypeError:
        raw = reader.readtext(trial, detail=1, paragraph=False)
    detections = []
    for item in raw or []:
        if not item or len(item) < 2:
            continue
        text_value = re.sub(r"[^A-Za-z0-9-]", "", str(item[1]).strip().upper())
        if not text_value:
            continue
        bbox = item[0]
        cx = sum(p[0] for p in bbox) / len(bbox) if bbox else 0.0
        cy = sum(p[1] for p in bbox) / len(bbox) if bbox else 0.0
        detections.append({"text": text_value, "x": cx, "y": cy})
    if not detections:
        return ""
    ys = [d["y"] for d in detections]
    if max(ys) - min(ys) > max(12.0, 0.2 * trial.shape[0]):
        mid = (min(ys) + max(ys)) / 2.0
        top = sorted([d for d in detections if d["y"] < mid], key=lambda d: d["x"])
        bot = sorted([d for d in detections if d["y"] >= mid], key=lambda d: d["x"])
        raw_text = "".join(d["text"] for d in top) + "-" + "".join(d["text"] for d in bot)
    else:
        raw_text = "".join(d["text"] for d in sorted(detections, key=lambda d: (d["y"], d["x"])))
    return format_plate(raw_text)


def paddle_recognize(crop, ocr):
    trial = crop
    if max(crop.shape[:2]) < 400:
        trial = cv2.resize(crop, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
    # PaddleOCR API differs by version
    try:
        result = ocr.ocr(trial, cls=True)
    except TypeError:
        result = ocr.ocr(trial)
    lines = []
    # result can be [ [ [box, (text, conf)], ... ] ] or newer dict-like
    if not result:
        return ""
    first = result[0]
    if first is None:
        return ""
    if isinstance(first, dict):
        texts = first.get("rec_texts") or first.get("texts") or []
        scores = first.get("rec_scores") or [1.0] * len(texts)
        boxes = first.get("rec_polys") or first.get("dt_polys") or [None] * len(texts)
        for text, box in zip(texts, boxes):
            text_value = re.sub(r"[^A-Za-z0-9-]", "", str(text).strip().upper())
            if not text_value:
                continue
            if box is not None:
                arr = np.array(box, dtype=float)
                cx, cy = float(arr[:, 0].mean()), float(arr[:, 1].mean())
            else:
                cx, cy = 0.0, 0.0
            lines.append({"text": text_value, "x": cx, "y": cy})
    else:
        for item in first:
            if not item or len(item) < 2:
                continue
            text_value = re.sub(r"[^A-Za-z0-9-]", "", str(item[1][0]).strip().upper())
            if not text_value:
                continue
            bbox = item[0]
            cx = sum(p[0] for p in bbox) / len(bbox)
            cy = sum(p[1] for p in bbox) / len(bbox)
            lines.append({"text": text_value, "x": cx, "y": cy})
    if not lines:
        return ""
    ys = [d["y"] for d in lines]
    if max(ys) - min(ys) > max(12.0, 0.2 * trial.shape[0]):
        mid = (min(ys) + max(ys)) / 2.0
        top = sorted([d for d in lines if d["y"] < mid], key=lambda d: d["x"])
        bot = sorted([d for d in lines if d["y"] >= mid], key=lambda d: d["x"])
        raw_text = "".join(d["text"] for d in top) + "-" + "".join(d["text"] for d in bot)
    else:
        raw_text = "".join(d["text"] for d in sorted(lines, key=lambda d: (d["y"], d["x"])))
    return format_plate(raw_text)


def summarize(name, rows):
    n = len(rows)
    exact = sum(1 for r in rows if r["exact"])
    valid_fmt = sum(1 for r in rows if r["valid_fmt"])
    avg_cer = sum(r["cer"] for r in rows) / n if n else 0.0
    avg_ms = sum(r["ms"] for r in rows) / n if n else 0.0
    return {
        "engine": name,
        "n": n,
        "lpra_pct": round(100.0 * exact / n, 2) if n else 0.0,
        "valid_fmt_pct": round(100.0 * valid_fmt / n, 2) if n else 0.0,
        "avg_cer_pct": round(100.0 * avg_cer, 2) if n else 0.0,
        "avg_latency_ms": round(avg_ms, 1) if n else 0.0,
        "rows": rows,
    }


def main():
    samples = []
    for rel, gt in LABELED:
        path = BASE / rel.replace("/", os.sep)
        if path.exists() and path.stat().st_size > 0:
            samples.append((str(path), gt, rel))
    if not samples:
        print(json.dumps({"success": False, "error": "No labeled images found"}))
        return

    print(f"Loading YOLO models… ({len(samples)} images)", flush=True)
    plate_model = YOLO(str(BASE / "best.pt"))
    read_model = YOLO(str(BASE / "bestRead.pt"))

    print("Loading EasyOCR…", flush=True)
    import easyocr
    easy_reader = easyocr.Reader(["en"], gpu=False, verbose=False)

    paddle_ocr = None
    paddle_error = None
    try:
        from paddleocr import PaddleOCR
        paddle_ocr = PaddleOCR(use_angle_cls=True, lang="en", show_log=False)
        print("PaddleOCR loaded", flush=True)
    except Exception as e:
        paddle_error = str(e)
        print(f"PaddleOCR unavailable: {e}", flush=True)

    # Warmup
    img0 = cv2.imread(samples[0][0])
    crop0 = crop_plate(img0, plate_model)
    _ = yolo_recognize(crop0, read_model)
    _ = easyocr_recognize(crop0, easy_reader)
    if paddle_ocr is not None:
        try:
            _ = paddle_recognize(crop0, paddle_ocr)
        except Exception as e:
            paddle_error = str(e)
            paddle_ocr = None

    yolo_rows, easy_rows, paddle_rows = [], [], []

    for path, gt, rel in samples:
        img = cv2.imread(path)
        if img is None:
            continue
        crop = crop_plate(img, plate_model)

        t0 = time.perf_counter()
        yolo_pred = yolo_recognize(crop, read_model)
        yolo_ms = (time.perf_counter() - t0) * 1000
        yolo_rows.append({
            "image": rel,
            "gt": gt,
            "pred": yolo_pred,
            "exact": compact(yolo_pred) == compact(gt),
            "valid_fmt": bool(re.match(r"^\d{2}[A-Z][A-Z0-9]?\d{4,5}$", compact(yolo_pred))),
            "cer": cer(yolo_pred, gt),
            "ms": yolo_ms,
        })

        t0 = time.perf_counter()
        easy_pred = easyocr_recognize(crop, easy_reader)
        easy_ms = (time.perf_counter() - t0) * 1000
        easy_rows.append({
            "image": rel,
            "gt": gt,
            "pred": easy_pred,
            "exact": compact(easy_pred) == compact(gt),
            "valid_fmt": bool(re.match(r"^\d{2}[A-Z][A-Z0-9]?\d{4,5}$", compact(easy_pred))),
            "cer": cer(easy_pred, gt),
            "ms": easy_ms,
        })

        if paddle_ocr is not None:
            t0 = time.perf_counter()
            try:
                paddle_pred = paddle_recognize(crop, paddle_ocr)
            except Exception as e:
                paddle_pred = ""
                paddle_error = str(e)
            paddle_ms = (time.perf_counter() - t0) * 1000
            paddle_rows.append({
                "image": rel,
                "gt": gt,
                "pred": paddle_pred,
                "exact": compact(paddle_pred) == compact(gt),
                "valid_fmt": bool(re.match(r"^\d{2}[A-Z][A-Z0-9]?\d{4,5}$", compact(paddle_pred))),
                "cer": cer(paddle_pred, gt),
                "ms": paddle_ms,
            })

        print(
            f"{Path(rel).name}: GT={gt} | YOLO={yolo_pred} | Easy={easy_pred}"
            + (f" | Paddle={paddle_rows[-1]['pred']}" if paddle_rows else ""),
            flush=True,
        )

    out = {
        "success": True,
        "dataset": {
            "n_images": len(yolo_rows),
            "source": "public/uploads/vehicles + visual GT (not DB plate_number)",
            "note": "Cùng crop từ best.pt; đo CPU; warmup 1 ảnh trước khi tính latency",
            "labels": [{"image": rel, "gt": gt} for _, gt, rel in samples[: len(yolo_rows)]],
        },
        "summary": [
            summarize("YOLO tự huấn luyện (best.pt + bestRead.pt)", yolo_rows),
            summarize("EasyOCR", easy_rows),
        ],
        "paddle_error": paddle_error,
        "python": sys.version,
    }
    if paddle_rows:
        out["summary"].append(summarize("PaddleOCR", paddle_rows))

    out_path = BASE / "ocr_benchmark_ndbs.json"
    out_path.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({k: out[k] for k in ("success", "dataset", "summary", "paddle_error")}, ensure_ascii=False, indent=2))
    print(f"Wrote {out_path}", flush=True)


if __name__ == "__main__":
    main()
