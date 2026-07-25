"""
Ma trận nhầm lẫn 30 lớp + phân bố mẫu theo lớp.

Dataset 30 lớp (không có I,J,O,Q,W,Y) dùng ID khác bestRead.pt (36 lớp 0-9+A-Z).
Đánh giá khớp theo IoU + so sánh theo TÊN ký tự, không theo class id thô.
"""
from __future__ import annotations

import json
from collections import Counter, defaultdict
from pathlib import Path

import cv2
import matplotlib.pyplot as plt
import numpy as np
from ultralytics import YOLO

BASE = Path(__file__).resolve().parent
DATA_ROOT = Path(r"E:\Do An\anh_train\bo_train_ky_tu")
OUT_DIR = BASE / "char_eval_plots"
NAMES_30 = [
    "0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
    "A", "B", "C", "D", "E", "F", "G", "H", "K", "L",
    "M", "N", "P", "R", "S", "T", "U", "V", "X", "Z",
]
NC = len(NAMES_30)
NAME_TO_I = {n: i for i, n in enumerate(NAMES_30)}


def count_instances(split: str) -> Counter:
    labels_dir = DATA_ROOT / split / "labels"
    c: Counter = Counter()
    for lf in labels_dir.glob("*.txt"):
        for line in lf.read_text(encoding="utf-8", errors="ignore").splitlines():
            line = line.strip()
            if not line:
                continue
            cid = int(line.split()[0])
            if 0 <= cid < NC:
                c[NAMES_30[cid]] += 1
    return c


def plot_distribution(dist: dict[str, dict[str, int]], dest: Path) -> None:
    x = np.arange(NC)
    width = 0.25
    fig, ax = plt.subplots(figsize=(16, 6))
    colors = {"train": "#2563eb", "valid": "#16a34a", "test": "#ea580c"}
    for i, split in enumerate(("train", "valid", "test")):
        vals = [dist[split].get(n, 0) for n in NAMES_30]
        ax.bar(x + (i - 1) * width, vals, width, label=split, color=colors[split])
    ax.set_xticks(x)
    ax.set_xticklabels(NAMES_30)
    ax.set_xlabel("Lớp ký tự")
    ax.set_ylabel("Số mẫu (instance)")
    ax.set_title("Phân bố số mẫu theo lớp — bộ train ký tự biển số VN (30 lớp)")
    ax.legend(title="Tập")
    ax.grid(axis="y", linestyle="--", alpha=0.35)
    fig.tight_layout()
    fig.savefig(dest, dpi=160)
    plt.close(fig)


def plot_confusion(matrix: np.ndarray, dest: Path, normalize: bool, title: str) -> None:
    m = matrix.astype(float).copy()
    if normalize:
        row_sum = m.sum(axis=1, keepdims=True)
        row_sum[row_sum == 0] = 1.0
        m = m / row_sum
        cbar_label = "Tỷ lệ"
        fmt = ".2f"
        vmin, vmax = 0.0, 1.0
        thr = 0.005
    else:
        cbar_label = "Số mẫu"
        fmt = ".0f"
        vmin, vmax = None, None
        thr = 0.5

    fig, ax = plt.subplots(figsize=(14, 12))
    im = ax.imshow(m, cmap="Blues", aspect="equal", vmin=vmin, vmax=vmax)
    cbar = fig.colorbar(im, ax=ax, fraction=0.046, pad=0.04)
    cbar.set_label(cbar_label)
    ax.set_xticks(range(NC))
    ax.set_yticks(range(NC))
    ax.set_xticklabels(NAMES_30)
    ax.set_yticklabels(NAMES_30)
    ax.set_xlabel("Dự đoán (Predicted)")
    ax.set_ylabel("Thực tế (Ground Truth)")
    ax.set_title(title)
    for i in range(NC):
        for j in range(NC):
            val = m[i, j]
            if val < thr:
                continue
            ax.text(
                j,
                i,
                format(val, fmt),
                ha="center",
                va="center",
                color="white" if val > (0.55 if normalize else max(1.0, m.max() * 0.55)) else "black",
                fontsize=7,
            )
    fig.tight_layout()
    fig.savefig(dest, dpi=160)
    plt.close(fig)


def yolo_to_xyxy(box: list[float], w: int, h: int) -> tuple[float, float, float, float]:
    xc, yc, bw, bh = box
    x1 = (xc - bw / 2) * w
    y1 = (yc - bh / 2) * h
    x2 = (xc + bw / 2) * w
    y2 = (yc + bh / 2) * h
    return x1, y1, x2, y2


def iou(a: tuple[float, float, float, float], b: tuple[float, float, float, float]) -> float:
    ax1, ay1, ax2, ay2 = a
    bx1, by1, bx2, by2 = b
    ix1, iy1 = max(ax1, bx1), max(ay1, by1)
    ix2, iy2 = min(ax2, bx2), min(ay2, by2)
    iw, ih = max(0.0, ix2 - ix1), max(0.0, iy2 - iy1)
    inter = iw * ih
    if inter <= 0:
        return 0.0
    area_a = max(0.0, ax2 - ax1) * max(0.0, ay2 - ay1)
    area_b = max(0.0, bx2 - bx1) * max(0.0, by2 - by1)
    union = area_a + area_b - inter
    return inter / union if union > 0 else 0.0


def load_gt(label_path: Path, w: int, h: int) -> list[tuple[str, tuple[float, float, float, float]]]:
    out = []
    if not label_path.exists():
        return out
    for line in label_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        parts = line.strip().split()
        if len(parts) < 5:
            continue
        cid = int(parts[0])
        if not (0 <= cid < NC):
            continue
        box = yolo_to_xyxy([float(x) for x in parts[1:5]], w, h)
        out.append((NAMES_30[cid], box))
    return out


def build_confusion(
    model: YOLO,
    split: str,
    conf: float = 0.25,
    iou_thr: float = 0.45,
) -> tuple[np.ndarray, dict]:
    """
    Ma trận nc x nc trên các GT được gán pred (IoU >= thr).
    GT không match / pred ngoài 30 lớp → không vào ma trận (ghi vào stats).
    """
    img_dir = DATA_ROOT / split / "images"
    lbl_dir = DATA_ROOT / split / "labels"
    cm = np.zeros((NC, NC), dtype=np.int64)
    stats = defaultdict(int)
    confuse_pairs: Counter = Counter()

    images = sorted(
        [p for p in img_dir.iterdir() if p.suffix.lower() in {".jpg", ".jpeg", ".png", ".bmp", ".webp"}]
    )
    for img_path in images:
        im = cv2.imread(str(img_path))
        if im is None:
            stats["bad_image"] += 1
            continue
        h, w = im.shape[:2]
        gt = load_gt(lbl_dir / f"{img_path.stem}.txt", w, h)
        stats["gt_boxes"] += len(gt)

        res = model.predict(source=str(img_path), conf=conf, imgsz=640, verbose=False)[0]
        preds: list[tuple[str, tuple[float, float, float, float], float]] = []
        if res.boxes is not None and len(res.boxes):
            for b in res.boxes:
                name = str(model.names[int(b.cls.item())])
                xyxy = tuple(float(x) for x in b.xyxy[0].tolist())
                score = float(b.conf.item())
                preds.append((name, xyxy, score))
        stats["pred_boxes"] += len(preds)
        preds.sort(key=lambda t: t[2], reverse=True)

        used_pred = set()
        for gt_name, gt_box in gt:
            best_j, best_iou = -1, 0.0
            for j, (pn, pb, _) in enumerate(preds):
                if j in used_pred:
                    continue
                v = iou(gt_box, pb)
                if v > best_iou:
                    best_iou, best_j = v, j
            if best_j < 0 or best_iou < iou_thr:
                stats["gt_unmatched"] += 1
                continue
            used_pred.add(best_j)
            pred_name = preds[best_j][0]
            if pred_name not in NAME_TO_I:
                stats["pred_outside_30"] += 1
                confuse_pairs[(gt_name, pred_name)] += 1
                continue
            gi, pi = NAME_TO_I[gt_name], NAME_TO_I[pred_name]
            cm[gi, pi] += 1
            stats["matched"] += 1
            if gi != pi:
                stats["mismatched"] += 1
                confuse_pairs[(gt_name, pred_name)] += 1
            else:
                stats["correct"] += 1

        stats["pred_unmatched"] += len(preds) - len(used_pred)

    return cm, {"stats": dict(stats), "top_confusions": confuse_pairs.most_common(20)}


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    dist: dict[str, dict[str, int]] = {}
    total: Counter = Counter()
    for split in ("train", "valid", "test"):
        c = count_instances(split)
        dist[split] = dict(c)
        total.update(c)
        n_img = len(list((DATA_ROOT / split / "images").glob("*")))
        print(f"{split}: {sum(c.values())} instances / {n_img} images")
    dist["total"] = dict(total)

    plot_distribution(dist, OUT_DIR / "class_distribution.png")
    print(f"Saved: {OUT_DIR / 'class_distribution.png'}")

    model = YOLO(str(BASE / "bestRead.pt"))
    print("model names (36):", model.names)
    print("dataset names (30):", NAMES_30)

    cm, extra = build_confusion(model, split="valid", conf=0.25, iou_thr=0.45)
    print("match stats:", extra["stats"])
    print("top confusions:", extra["top_confusions"][:10])

    plot_confusion(
        cm,
        OUT_DIR / "confusion_matrix_normalized.png",
        normalize=True,
        title="Ma trận nhầm lẫn chuẩn hóa (theo hàng GT) — bestRead.pt @ valid (khớp theo tên lớp)",
    )
    plot_confusion(
        cm,
        OUT_DIR / "confusion_matrix_counts.png",
        normalize=False,
        title="Ma trận nhầm lẫn (số đếm) — bestRead.pt @ valid (khớp theo tên lớp)",
    )

    row_sum = cm.sum(axis=1)
    diag = np.diag(cm)
    per_class = []
    for i, name in enumerate(NAMES_30):
        support = int(row_sum[i])
        recall = float(diag[i] / row_sum[i]) if row_sum[i] > 0 else None
        per_class.append(
            {
                "class": name,
                "matched_support": support,
                "correct": int(diag[i]),
                "recall": None if recall is None else round(recall, 4),
                "train": dist["train"].get(name, 0),
                "valid": dist["valid"].get(name, 0),
                "test": dist["test"].get(name, 0),
                "total": dist["total"].get(name, 0),
            }
        )

    matched = int(extra["stats"].get("matched", 0))
    correct = int(extra["stats"].get("correct", 0))
    summary = {
        "dataset": str(DATA_ROOT),
        "model": str(BASE / "bestRead.pt"),
        "note": (
            "Dataset 30 lớp; model 36 lớp (0-9+A-Z). "
            "Confusion ghép box theo IoU rồi so sánh theo tên ký tự."
        ),
        "names_30": NAMES_30,
        "images": {
            s: len(list((DATA_ROOT / s / "images").glob("*"))) for s in ("train", "valid", "test")
        },
        "instances": {
            "train": sum(dist["train"].values()),
            "valid": sum(dist["valid"].values()),
            "test": sum(dist["test"].values()),
            "total": sum(dist["total"].values()),
        },
        "eval": {
            "split": "valid",
            "conf": 0.25,
            "iou_thr": 0.45,
            **extra["stats"],
            "accuracy_matched": None if matched == 0 else round(correct / matched, 4),
        },
        "top_confusions": [
            {"gt": a, "pred": b, "count": n} for (a, b), n in extra["top_confusions"]
        ],
        "per_class": per_class,
        "confusion_matrix_counts": cm.astype(int).tolist(),
        "plots": {
            "class_distribution": str(OUT_DIR / "class_distribution.png"),
            "confusion_normalized": str(OUT_DIR / "confusion_matrix_normalized.png"),
            "confusion_counts": str(OUT_DIR / "confusion_matrix_counts.png"),
        },
    }
    dest = OUT_DIR / "char_eval_summary.json"
    dest.write_text(json.dumps(summary, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Saved: {dest}")
    print(f"Matched accuracy: {summary['eval']['accuracy_matched']}")


if __name__ == "__main__":
    main()
