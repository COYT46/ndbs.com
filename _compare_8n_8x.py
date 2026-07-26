"""So sánh YOLOv8n vs YOLOv8x — biển số và ký tự — chỉ số tại CÙNG epoch best."""
from __future__ import annotations

import csv
import json
from pathlib import Path

import yaml

RUNS = {
    "bienso_8n": Path(r"E:\Do An\bienso_8n\detect\train"),
    "bienso_8x": Path(r"E:\Do An\bienso_8x\detect\train"),
    "kytu_8n": Path(r"E:\Do An\kytu_8n\detect\train"),
    "kytu_8x": Path(r"E:\Do An\kytu_8x\detect\train"),
}


def fitness(map50: float, map5095: float) -> float:
    return 0.1 * map50 + 0.9 * map5095


def load_args(train_dir: Path) -> dict:
    p = train_dir / "args.yaml"
    if not p.exists():
        return {}
    with open(p, encoding="utf-8") as f:
        return yaml.safe_load(f) or {}


def load_results(train_dir: Path) -> list[dict]:
    p = train_dir / "results.csv"
    rows = []
    with open(p, encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f)
        for r in reader:
            # strip whitespace from keys/values
            clean = {k.strip(): (v.strip() if isinstance(v, str) else v) for k, v in r.items()}
            rows.append(clean)
    return rows


def parse_float(row: dict, *keys: str) -> float:
    for k in keys:
        if k in row and row[k] not in ("", None):
            return float(row[k])
    raise KeyError(f"Missing keys {keys} in row keys={list(row)}")


def best_row(rows: list[dict]) -> tuple[dict, int]:
    """Chọn epoch có fitness cao nhất (cùng tiêu chí Ultralytics best.pt)."""
    best_i, best_f = 0, -1.0
    parsed = []
    for i, r in enumerate(rows):
        ep = int(float(parse_float(r, "epoch")))
        p = parse_float(r, "metrics/precision(B)", "precision")
        rec = parse_float(r, "metrics/recall(B)", "recall")
        m50 = parse_float(r, "metrics/mAP50(B)", "mAP50")
        m95 = parse_float(r, "metrics/mAP50-95(B)", "mAP50-95")
        fit = fitness(m50, m95)
        item = {
            "epoch": ep,
            "precision": p,
            "recall": rec,
            "mAP50": m50,
            "mAP50_95": m95,
            "fitness": fit,
        }
        # optional losses
        for loss_key in ("val/box_loss", "val/cls_loss", "val/dfl_loss"):
            if loss_key in r and r[loss_key] not in ("", None):
                item[loss_key.split("/")[-1]] = float(r[loss_key])
        parsed.append(item)
        if fit > best_f:
            best_f, best_i = fit, i
    return parsed[best_i], len(parsed)


def analyze(name: str, train_dir: Path) -> dict:
    args = load_args(train_dir)
    rows = load_results(train_dir)
    best, n_epochs = best_row(rows)
    weights = train_dir / "weights"
    best_pt = weights / "best.pt"
    last_pt = weights / "last.pt"
    return {
        "run": name,
        "train_dir": str(train_dir),
        "model": args.get("model"),
        "data": args.get("data"),
        "epochs_configured": args.get("epochs"),
        "epochs_recorded": n_epochs,
        "imgsz": args.get("imgsz"),
        "batch": args.get("batch"),
        "best_pt_exists": best_pt.exists(),
        "best_pt": str(best_pt) if best_pt.exists() else None,
        "last_pt_exists": last_pt.exists(),
        "checkpoint_epoch": best["epoch"],
        "metrics_at_best_checkpoint": best,
        "note": (
            f"Tất cả chỉ số lấy tại epoch {best['epoch']}/{n_epochs} "
            "(fitness cao nhất = tiêu chí lưu best.pt)."
        ),
    }


def main() -> None:
    out = {name: analyze(name, path) for name, path in RUNS.items()}

    def pct(x: float) -> float:
        return round(x * 100, 2)

    bienso = [
        {
            "Bien the": "YOLOv8n",
            "Checkpoint": "bienso_8n/.../best.pt",
            "Epoch": f"{out['bienso_8n']['checkpoint_epoch']}/{out['bienso_8n']['epochs_recorded']}",
            "Precision (%)": pct(out["bienso_8n"]["metrics_at_best_checkpoint"]["precision"]),
            "Recall (%)": pct(out["bienso_8n"]["metrics_at_best_checkpoint"]["recall"]),
            "mAP@0.5 (%)": pct(out["bienso_8n"]["metrics_at_best_checkpoint"]["mAP50"]),
            "mAP@0.5:0.95 (%)": pct(out["bienso_8n"]["metrics_at_best_checkpoint"]["mAP50_95"]),
            "Fitness": round(out["bienso_8n"]["metrics_at_best_checkpoint"]["fitness"], 5),
        },
        {
            "Bien the": "YOLOv8x",
            "Checkpoint": "bienso_8x/.../best.pt",
            "Epoch": f"{out['bienso_8x']['checkpoint_epoch']}/{out['bienso_8x']['epochs_recorded']}",
            "Precision (%)": pct(out["bienso_8x"]["metrics_at_best_checkpoint"]["precision"]),
            "Recall (%)": pct(out["bienso_8x"]["metrics_at_best_checkpoint"]["recall"]),
            "mAP@0.5 (%)": pct(out["bienso_8x"]["metrics_at_best_checkpoint"]["mAP50"]),
            "mAP@0.5:0.95 (%)": pct(out["bienso_8x"]["metrics_at_best_checkpoint"]["mAP50_95"]),
            "Fitness": round(out["bienso_8x"]["metrics_at_best_checkpoint"]["fitness"], 5),
        },
    ]
    kytu = [
        {
            "Bien the": "YOLOv8n",
            "Checkpoint": "kytu_8n/.../best.pt",
            "Epoch": f"{out['kytu_8n']['checkpoint_epoch']}/{out['kytu_8n']['epochs_recorded']}",
            "Precision (%)": pct(out["kytu_8n"]["metrics_at_best_checkpoint"]["precision"]),
            "Recall (%)": pct(out["kytu_8n"]["metrics_at_best_checkpoint"]["recall"]),
            "mAP@0.5 (%)": pct(out["kytu_8n"]["metrics_at_best_checkpoint"]["mAP50"]),
            "mAP@0.5:0.95 (%)": pct(out["kytu_8n"]["metrics_at_best_checkpoint"]["mAP50_95"]),
            "Fitness": round(out["kytu_8n"]["metrics_at_best_checkpoint"]["fitness"], 5),
        },
        {
            "Bien the": "YOLOv8x",
            "Checkpoint": "kytu_8x/.../best.pt",
            "Epoch": f"{out['kytu_8x']['checkpoint_epoch']}/{out['kytu_8x']['epochs_recorded']}",
            "Precision (%)": pct(out["kytu_8x"]["metrics_at_best_checkpoint"]["precision"]),
            "Recall (%)": pct(out["kytu_8x"]["metrics_at_best_checkpoint"]["recall"]),
            "mAP@0.5 (%)": pct(out["kytu_8x"]["metrics_at_best_checkpoint"]["mAP50"]),
            "mAP@0.5:0.95 (%)": pct(out["kytu_8x"]["metrics_at_best_checkpoint"]["mAP50_95"]),
            "Fitness": round(out["kytu_8x"]["metrics_at_best_checkpoint"]["fitness"], 5),
        },
    ]

    payload = {
        "method": (
            "Moi dong: tat ca chi so (P, R, mAP50, mAP50-95) lay tai CUNG epoch "
            "co fitness cao nhat trong results.csv (= tieu chi luu best.pt)."
        ),
        "runs": out,
        "table_bienso_8n_vs_8x": bienso,
        "table_kytu_8n_vs_8x": kytu,
    }
    dest = Path(__file__).resolve().parent / "so_sanh_8n_8x.json"
    dest.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")

    print("=== PHAT HIEN BIEN SO: YOLOv8n vs YOLOv8x (cung checkpoint best) ===")
    print(f"{'Bien the':<10} {'Epoch':>10} {'P%':>8} {'R%':>8} {'mAP50%':>8} {'mAP50-95%':>10} {'fit':>9}")
    for r in bienso:
        print(
            f"{r['Bien the']:<10} {r['Epoch']:>10} {r['Precision (%)']:8.2f} "
            f"{r['Recall (%)']:8.2f} {r['mAP@0.5 (%)']:8.2f} {r['mAP@0.5:0.95 (%)']:10.2f} {r['Fitness']:9.5f}"
        )
    print()
    print("=== NHAN DIEN KY TU: YOLOv8n vs YOLOv8x (cung checkpoint best) ===")
    for r in kytu:
        print(
            f"{r['Bien the']:<10} {r['Epoch']:>10} {r['Precision (%)']:8.2f} "
            f"{r['Recall (%)']:8.2f} {r['mAP@0.5 (%)']:8.2f} {r['mAP@0.5:0.95 (%)']:10.2f} {r['Fitness']:9.5f}"
        )
    print()
    for name, info in out.items():
        print(
            f"{name}: model={info['model']} epochs_cfg={info['epochs_configured']} "
            f"best_ep={info['checkpoint_epoch']} best.pt={info['best_pt_exists']}"
        )
    print(f"\nSaved {dest}")


if __name__ == "__main__":
    main()
