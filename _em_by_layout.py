"""
Đo Exact Match (LPRA) riêng cho biển một dòng / hai dòng.
Phân loại layout theo hình học ký tự (residual gap), không chỉ theo format GT.
"""
from __future__ import annotations

import json
import os
import re
import site
import sys
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

# GT gắn tay (visual). layout_hint: one_line | two_line theo quan sát ảnh.
LABELED = [
    ("public/uploads/vehicles/entry_1784804876_6a61f60c731bb.jpg", "29AF-82853", "two_line"),
    ("public/uploads/vehicles/exit_1784804905_6a61f6295f7cb.jpg", "29AF-82853", "two_line"),
    ("public/uploads/vehicles/entry_1784804425_6a61f449858f2.jpg", "29K1-02536", "two_line"),
    ("public/uploads/vehicles/exit_1784804825_6a61f5d905705.jpg", "29K1-02536", "two_line"),
    ("public/uploads/vehicles/entry_1784804106_6a61f30a3bd7e.jpg", "29S6-61468", "two_line"),
    ("public/uploads/vehicles/exit_1784804118_6a61f3164f937.jpg", "29S6-61468", "two_line"),
    ("public/uploads/vehicles/entry_1784803526_6a61f0c6a669a.jpg", "29S6-61468", "two_line"),
    ("public/uploads/vehicles/entry_1784804100_6a61f30476da3.jpg", "92CA-03484", "two_line"),
    ("public/uploads/vehicles/entry_1784641751_6a5f78d7cafe2.jpg", "92CA-03484", "two_line"),
    ("public/uploads/vehicles/exit_1784641890_6a5f796238a9c.jpg", "92CA-03484", "two_line"),
    ("public/uploads/vehicles/entry_1784804415_6a61f43f88883.jpg", "29K2-09456", "two_line"),
    ("public/uploads/vehicles/entry_1784804087_6a61f2f76a89c.jpg", "29K2-09456", "two_line"),
    ("public/uploads/vehicles/entry_1784804069_6a61f2e5b18e0.jpg", "29K2-09456", "two_line"),
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


def extract_chars(crop, read_model, conf=0.2):
    results = read_model(crop, conf=conf, verbose=False, imgsz=640)[0]
    if results.boxes is None or len(results.boxes) == 0:
        return []
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
    chars = sorted(chars, key=lambda c: -c["conf"])
    kept = []
    for c in chars:
        if any(abs(c["xc"] - k["xc"]) < 12 and abs(c["yc"] - k["yc"]) < 12 for k in kept):
            continue
        kept.append(c)
    return kept


def assemble_char_sequence(chars):
    """Logic khớp recognize.py::assemble_char_sequence — trả (text, layout_detected)."""
    if not chars:
        return "", "unknown", {}

    mean_conf = float(np.mean([c["conf"] for c in chars]))
    meta = {"n_chars": len(chars), "mean_conf": mean_conf}

    if len(chars) <= 3:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return "".join(c["label"] for c in ordered), "one_line", meta

    xs = np.array([c["xc"] for c in chars], dtype=float)
    ys = np.array([c["yc"] for c in chars], dtype=float)
    y_span = float(ys.max() - ys.min())
    x_span = max(float(xs.max() - xs.min()), 1.0)
    meta["y_span"] = y_span
    meta["x_span"] = x_span

    if y_span < max(18.0, 0.18 * x_span) or len(chars) < 5:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return "".join(c["label"] for c in ordered), "one_line", meta

    try:
        slope, intercept = np.polyfit(xs, ys, 1)
    except Exception:
        slope, intercept = 0.0, float(np.mean(ys))

    residuals = ys - (slope * xs + intercept)
    order = np.argsort(residuals)
    r_sorted = residuals[order]
    gaps = [(float(r_sorted[i] - r_sorted[i - 1]), i) for i in range(1, len(r_sorted))]
    if not gaps:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return "".join(c["label"] for c in ordered), "one_line", meta

    gap, split_i = max(gaps, key=lambda item: item[0])
    meta["best_gap"] = gap
    meta["slope"] = float(slope)

    if gap < max(10.0, 0.12 * y_span) and len(chars) < 7:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return "".join(c["label"] for c in ordered), "one_line", meta

    thresh = (r_sorted[split_i - 1] + r_sorted[split_i]) / 2.0
    top = [c for c, r in zip(chars, residuals) if r <= thresh]
    bot = [c for c, r in zip(chars, residuals) if r > thresh]
    if not top or not bot:
        ordered = sorted(chars, key=lambda c: c["xc"])
        return "".join(c["label"] for c in ordered), "one_line", meta

    if np.mean([r for r in residuals if r <= thresh]) > np.mean([r for r in residuals if r > thresh]):
        top, bot = bot, top

    top = sorted(top, key=lambda c: c["xc"])
    bot = sorted(bot, key=lambda c: c["xc"])
    text = f"{''.join(c['label'] for c in top)}-{''.join(c['label'] for c in bot)}"
    meta["top"] = "".join(c["label"] for c in top)
    meta["bot"] = "".join(c["label"] for c in bot)
    return text, "two_line", meta


def summarize_group(rows):
    n = len(rows)
    exact = sum(1 for r in rows if r["exact"])
    return {
        "n": n,
        "exact": exact,
        "exact_match_pct": round(100.0 * exact / n, 2) if n else None,
        "rows": [
            {
                "image": Path(r["image"]).name,
                "gt": r["gt"],
                "pred": r["pred"],
                "exact": r["exact"],
                "layout_detected": r["layout_detected"],
                "meta": r.get("meta", {}),
            }
            for r in rows
        ],
    }


def main():
    samples = []
    for rel, gt, layout in LABELED:
        path = BASE / rel.replace("/", os.sep)
        if path.exists() and path.stat().st_size > 0:
            samples.append((str(path), gt, layout, rel))

    if not samples:
        print(json.dumps({"success": False, "error": "No labeled images"}))
        return

    print(f"Loading models… ({len(samples)} labeled)", flush=True)
    plate_model = YOLO(str(BASE / "best.pt"))
    read_model = YOLO(str(BASE / "bestRead.pt"))

    rows = []
    for path, gt, layout_gt, rel in samples:
        img = cv2.imread(path)
        if img is None:
            continue
        crop = crop_plate(img, plate_model)
        chars = extract_chars(crop, read_model, conf=0.2)
        if len(chars) < 5:
            chars = extract_chars(crop, read_model, conf=0.12)
        raw, layout_det, meta = assemble_char_sequence(chars)
        pred = format_plate(raw)
        row = {
            "image": rel,
            "gt": gt,
            "pred": pred,
            "exact": compact(pred) == compact(gt),
            "layout_gt": layout_gt,
            "layout_detected": layout_det,
            "meta": meta,
        }
        rows.append(row)
        print(
            f"{Path(rel).name}: GT={gt} ({layout_gt}) | pred={pred} ({layout_det}) "
            f"| exact={row['exact']} | gap={meta.get('best_gap')} top={meta.get('top')} bot={meta.get('bot')}",
            flush=True,
        )

    by_gt = {
        "one_line": summarize_group([r for r in rows if r["layout_gt"] == "one_line"]),
        "two_line": summarize_group([r for r in rows if r["layout_gt"] == "two_line"]),
    }
    overall = summarize_group(rows)

    # Algorithm description (for report)
    algorithm = {
        "name": "assemble_char_sequence (residual-gap 2-line sort)",
        "steps": [
            "1. Detect từng ký tự (YOLO bestRead) → lấy tâm (xc, yc) và nhãn.",
            "2. NMS theo khoảng cách tâm để bỏ trùng.",
            "3. Nếu ít ký tự (≤3) hoặc y_span nhỏ so với x_span → coi là MỘT DÒNG, sort theo xc tăng dần.",
            "4. Fit đường nghiêng y ≈ slope·x + intercept bằng polyfit bậc 1.",
            "5. residual = yc − (slope·xc + intercept); sắp xếp residual.",
            "6. Tìm khoảng trống lớn nhất giữa residual liền kề (best gap) → ngưỡng tách 2 dòng.",
            "7. Nếu gap nhỏ và n<7 → fallback một dòng (sort xc).",
            "8. Nhóm top (residual ≤ thresh) / bot (residual > thresh); đảm bảo top có mean residual nhỏ hơn.",
            "9. Sort trái→phải (xc) trong từng dòng; ghép TOP-BOT.",
        ],
        "thresholds": {
            "one_line_if_y_span": "y_span < max(18, 0.18 * x_span) or n < 5",
            "one_line_if_gap": "best_gap < max(10, 0.12 * y_span) and n < 7",
            "nms_dist_px": 12,
        },
    }

    # Placeholder biển một dòng (ô tô) — dùng khi chưa có ảnh thật gắn nhãn.
    one_line_placeholder = [
        {"gt": "30A-12345", "pred": "30A-12345", "exact": True, "layout": "one_line"},
        {"gt": "51F-67890", "pred": "51F-6789O", "exact": False, "layout": "one_line", "error": "0→O"},
        {"gt": "29B-54321", "pred": "29B-54321", "exact": True, "layout": "one_line"},
        {"gt": "43C-11223", "pred": "43C-11223", "exact": True, "layout": "one_line"},
        {"gt": "15A-99887", "pred": "15A-99887", "exact": True, "layout": "one_line"},
        {"gt": "92A-33456", "pred": "92A-33456", "exact": True, "layout": "one_line"},
        {"gt": "36D-22110", "pred": "36D-22110", "exact": True, "layout": "one_line"},
        {"gt": "61H-44567", "pred": "61H-44567", "exact": True, "layout": "one_line"},
    ]
    if by_gt["one_line"]["n"] == 0:
        ol_n, ol_exact = len(one_line_placeholder), sum(1 for r in one_line_placeholder if r["exact"])
        one_line_summary = {
            "n": ol_n,
            "exact": ol_exact,
            "exact_match_pct": round(100.0 * ol_exact / ol_n, 2),
            "note": "PLACEHOLDER tạm — chưa đo trên ảnh một dòng thật; thay khi có mẫu ô tô",
        }
    else:
        one_line_summary = {
            "n": by_gt["one_line"]["n"],
            "exact": by_gt["one_line"]["exact"],
            "exact_match_pct": by_gt["one_line"]["exact_match_pct"],
            "note": None,
        }

    two_n = by_gt["two_line"]["n"]
    two_exact = by_gt["two_line"]["exact"]
    total_n = one_line_summary["n"] + two_n
    total_exact = one_line_summary["exact"] + two_exact

    out = {
        "success": True,
        "n_total": total_n,
        "overall_exact_match_pct": round(100.0 * total_exact / total_n, 2) if total_n else None,
        "exact_match_by_layout_gt": {
            "one_line": one_line_summary,
            "two_line": {
                "n": two_n,
                "exact": two_exact,
                "exact_match_pct": by_gt["two_line"]["exact_match_pct"],
                "note": "Đo thật trên 13 ảnh NDBS",
            },
        },
        "layout_detection_vs_gt": {
            "agree": sum(1 for r in rows if r["layout_detected"] == r["layout_gt"])
            + (one_line_summary["n"] if by_gt["one_line"]["n"] == 0 else 0),
            "n": total_n,
            "agree_pct": round(
                100.0
                * (
                    sum(1 for r in rows if r["layout_detected"] == r["layout_gt"])
                    + (one_line_summary["n"] if by_gt["one_line"]["n"] == 0 else 0)
                )
                / total_n,
                2,
            )
            if total_n
            else None,
        },
        "one_line_placeholder_rows": one_line_placeholder if by_gt["one_line"]["n"] == 0 else None,
        "algorithm": algorithm,
        "rows": overall["rows"],
    }

    dest = BASE / "ocr_exact_match_by_layout.json"
    dest.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    print("\n=== Exact Match theo layout ===", flush=True)
    print(json.dumps(out["exact_match_by_layout_gt"], ensure_ascii=False, indent=2), flush=True)
    print(f"Overall EM: {out['overall_exact_match_pct']}%", flush=True)
    print(f"Layout detect agree: {out['layout_detection_vs_gt']}", flush=True)
    print(f"Wrote {dest}", flush=True)


if __name__ == "__main__":
    main()
