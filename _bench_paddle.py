"""Chạy PaddleOCR 2.7 trên crop đã xuất (venv 3.12)."""
from __future__ import annotations

import json
import os
import re
import time
from pathlib import Path

os.environ["FLAGS_use_mkldnn"] = "0"
os.environ["PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK"] = "True"

import cv2
import numpy as np
from paddleocr import PaddleOCR

BASE = Path(__file__).resolve().parent
MANIFEST = BASE / "_bench_crops" / "manifest.json"


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


def paddle_recognize(crop, ocr):
    trial = crop
    if max(crop.shape[:2]) < 400:
        trial = cv2.resize(crop, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)
    result = ocr.ocr(trial, cls=True)
    lines = []
    if not result or result[0] is None:
        return ""
    for item in result[0]:
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


def main():
    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    print("Loading PaddleOCR 2.7…", flush=True)
    ocr = PaddleOCR(use_angle_cls=True, lang="en", show_log=False, use_gpu=False, enable_mkldnn=False)

    crop0 = cv2.imread(manifest[0]["crop"])
    _ = paddle_recognize(crop0, ocr)

    rows = []
    for item in manifest:
        crop = cv2.imread(item["crop"])
        t0 = time.perf_counter()
        pred = paddle_recognize(crop, ocr)
        ms = (time.perf_counter() - t0) * 1000
        rows.append({
            "image": item["image"],
            "gt": item["gt"],
            "pred": pred,
            "exact": compact(pred) == compact(item["gt"]),
            "valid_fmt": bool(re.match(r"^\d{2}[A-Z][A-Z0-9]?\d{4,5}$", compact(pred))),
            "cer": cer(pred, item["gt"]),
            "ms": ms,
        })
        print(f"{Path(item['image']).name}: GT={item['gt']} | Paddle={pred}", flush=True)

    n = len(rows)
    summary = {
        "engine": "PaddleOCR",
        "n": n,
        "lpra_pct": round(100.0 * sum(1 for r in rows if r["exact"]) / n, 2),
        "valid_fmt_pct": round(100.0 * sum(1 for r in rows if r["valid_fmt"]) / n, 2),
        "avg_cer_pct": round(100.0 * sum(r["cer"] for r in rows) / n, 2),
        "avg_latency_ms": round(sum(r["ms"] for r in rows) / n, 1),
        "rows": rows,
    }
    out_path = BASE / "ocr_benchmark_paddle.json"
    out_path.write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")

    # Merge into main benchmark file if present
    main_path = BASE / "ocr_benchmark_ndbs.json"
    if main_path.exists():
        main = json.loads(main_path.read_text(encoding="utf-8"))
        main["summary"] = [s for s in main.get("summary", []) if s.get("engine") != "PaddleOCR"]
        main["summary"].append(summary)
        main["paddle_error"] = None
        main["paddle_runtime"] = "Python 3.12 venv (.venv-paddle), paddleocr==2.7.3, paddlepaddle==2.6.2"
        main_path.write_text(json.dumps(main, ensure_ascii=False, indent=2), encoding="utf-8")

    print(json.dumps({k: summary[k] for k in ("engine", "n", "lpra_pct", "valid_fmt_pct", "avg_cer_pct", "avg_latency_ms")}, ensure_ascii=False, indent=2))
    print(f"Wrote {out_path}", flush=True)


if __name__ == "__main__":
    main()
