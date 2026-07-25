"""Bảng chỉ số tại CÙNG checkpoint — không cherry-pick max từng metric."""
from __future__ import annotations

import json
from pathlib import Path

import torch

BASE = Path(__file__).resolve().parent


def row_at(tr: dict, i: int) -> dict:
    return {
        "epoch": int(tr["epoch"][i]),
        "precision": float(tr["metrics/precision(B)"][i]),
        "recall": float(tr["metrics/recall(B)"][i]),
        "mAP50": float(tr["metrics/mAP50(B)"][i]),
        "mAP50_95": float(tr["metrics/mAP50-95(B)"][i]),
        "box_loss": float(tr["val/box_loss"][i]),
        "cls_loss": float(tr["val/cls_loss"][i]),
        "dfl_loss": float(tr["val/dfl_loss"][i]),
        "fitness": 0.1 * float(tr["metrics/mAP50(B)"][i])
        + 0.9 * float(tr["metrics/mAP50-95(B)"][i]),
    }


def cherry_pick(tr: dict) -> dict:
    """Sai: lấy max từng chỉ số ở epoch khác nhau."""
    keys = {
        "precision": "metrics/precision(B)",
        "recall": "metrics/recall(B)",
        "mAP50": "metrics/mAP50(B)",
        "mAP50_95": "metrics/mAP50-95(B)",
    }
    out = {}
    for name, k in keys.items():
        vals = list(tr[k])
        i = max(range(len(vals)), key=lambda j: vals[j])
        out[name] = {"value": float(vals[i]), "epoch": int(tr["epoch"][i])}
    return out


def analyze(path: str) -> dict:
    ckpt = torch.load(path, map_location="cpu", weights_only=False)
    tm = ckpt["train_metrics"]
    tr = ckpt["train_results"]
    args = ckpt.get("train_args") or {}
    epochs = list(tr["epoch"])

    # Epoch khớp train_metrics đã lưu trong file .pt
    main = [
        "metrics/precision(B)",
        "metrics/recall(B)",
        "metrics/mAP50(B)",
        "metrics/mAP50-95(B)",
    ]
    match_i = None
    for i in range(len(epochs)):
        if all(abs(float(tr[k][i]) - float(tm[k])) < 1e-5 for k in main):
            match_i = i
            break
    assert match_i is not None, f"No epoch matches train_metrics in {path}"

    same = row_at(tr, match_i)
    # Also report best fitness epoch (should be same for properly saved best.pt)
    fitness = [
        0.1 * float(tr["metrics/mAP50(B)"][i])
        + 0.9 * float(tr["metrics/mAP50-95(B)"][i])
        for i in range(len(epochs))
    ]
    fit_i = max(range(len(fitness)), key=lambda i: fitness[i])

    return {
        "file": path,
        "model": args.get("model"),
        "data": args.get("data"),
        "total_epochs": args.get("epochs"),
        "imgsz": args.get("imgsz"),
        "batch": args.get("batch"),
        "date": ckpt.get("date"),
        "ultralytics": ckpt.get("version"),
        "checkpoint_epoch": same["epoch"],
        "best_fitness_epoch": int(epochs[fit_i]),
        "metrics_at_checkpoint": same,
        "train_metrics_saved": {
            "precision": float(tm["metrics/precision(B)"]),
            "recall": float(tm["metrics/recall(B)"]),
            "mAP50": float(tm["metrics/mAP50(B)"]),
            "mAP50_95": float(tm["metrics/mAP50-95(B)"]),
            "fitness": float(tm.get("fitness", 0)),
        },
        "cherry_pick_WRONG": cherry_pick(tr),
        "note": (
            "Tất cả chỉ số trong metrics_at_checkpoint lấy tại CÙNG một epoch "
            f"(epoch {same['epoch']}) — khớp train_metrics lưu trong file."
        ),
    }


def main() -> None:
    plate = analyze("best.pt")
    read = analyze("bestRead.pt")
    out = {
        "plate_detect": plate,
        "char_read": read,
        "comparison_table": [
            {
                "model": "best.pt (detect biển)",
                "checkpoint": "best.pt",
                "epoch": plate["checkpoint_epoch"],
                "of_epochs": plate["total_epochs"],
                "Precision": round(plate["metrics_at_checkpoint"]["precision"] * 100, 2),
                "Recall": round(plate["metrics_at_checkpoint"]["recall"] * 100, 2),
                "mAP50": round(plate["metrics_at_checkpoint"]["mAP50"] * 100, 2),
                "mAP50-95": round(plate["metrics_at_checkpoint"]["mAP50_95"] * 100, 2),
                "fitness": round(plate["metrics_at_checkpoint"]["fitness"], 5),
            },
            {
                "model": "bestRead.pt (đọc ký tự)",
                "checkpoint": "bestRead.pt",
                "epoch": read["checkpoint_epoch"],
                "of_epochs": read["total_epochs"],
                "Precision": round(read["metrics_at_checkpoint"]["precision"] * 100, 2),
                "Recall": round(read["metrics_at_checkpoint"]["recall"] * 100, 2),
                "mAP50": round(read["metrics_at_checkpoint"]["mAP50"] * 100, 2),
                "mAP50-95": round(read["metrics_at_checkpoint"]["mAP50_95"] * 100, 2),
                "fitness": round(read["metrics_at_checkpoint"]["fitness"], 5),
            },
        ],
    }
    dest = BASE / "ckpt_metrics_same_epoch.json"
    dest.write_text(json.dumps(out, indent=2, ensure_ascii=False), encoding="utf-8")

    print("=== BẢNG CHỈ SỐ TẠI CÙNG CHECKPOINT (không cherry-pick) ===\n")
    print(
        f"{'Model':<28} {'Epoch':>10} {'P%':>8} {'R%':>8} {'mAP50%':>8} {'mAP50-95%':>10} {'fitness':>9}"
    )
    print("-" * 90)
    for r in out["comparison_table"]:
        ep = f"{r['epoch']}/{r['of_epochs']}"
        print(
            f"{r['model']:<28} {ep:>10} {r['Precision']:8.2f} {r['Recall']:8.2f} "
            f"{r['mAP50']:8.2f} {r['mAP50-95']:10.2f} {r['fitness']:9.5f}"
        )

    print("\n--- best.pt @ epoch", plate["checkpoint_epoch"], "(đầy đủ) ---")
    m = plate["metrics_at_checkpoint"]
    for k in [
        "precision",
        "recall",
        "mAP50",
        "mAP50_95",
        "box_loss",
        "cls_loss",
        "dfl_loss",
        "fitness",
    ]:
        print(f"  {k}: {m[k]}")

    print("\n--- SAI nếu cherry-pick max từng metric (best.pt) ---")
    for k, v in plate["cherry_pick_WRONG"].items():
        print(f"  {k}: {v['value']:.5f} @ epoch {v['epoch']}  ← epoch khác nhau!")

    print(f"\nSaved: {dest}")


if __name__ == "__main__":
    main()
