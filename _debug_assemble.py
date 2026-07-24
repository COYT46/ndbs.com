import os
import sys
import site
from pathlib import Path

for py_ver in ["314", "313", "312", "311", "310"]:
    admin_path = fr"C:\Users\ADMIN\AppData\Roaming\Python\Python{py_ver}\site-packages"
    if os.path.exists(admin_path):
        sys.path.append(admin_path)
user_site = site.getusersitepackages()
if user_site and os.path.exists(user_site):
    sys.path.append(user_site)

import cv2
import numpy as np
from ultralytics import YOLO

base = Path(r"C:\xampp\htdocs\ndbs.com")
img = cv2.imread(str(base / "public" / "uploads" / "vehicles" / "entry_1784553544_6a5e204884287.jpg"))
plate = YOLO(str(base / "best.pt"))
read = YOLO(str(base / "bestRead.pt"))
h, w = img.shape[:2]
res = plate(img, conf=0.25, verbose=False)[0]
box = max(res.boxes, key=lambda b: float(b.conf[0]))
x1, y1, x2, y2 = map(int, box.xyxy[0].cpu().numpy())
crop = img[max(0, y1 - 8): min(h, y2 + 8), max(0, x1 - 8): min(w, x2 + 8)]


def rotate(image, angle):
    if abs(angle) < 0.1:
        return image
    center = (image.shape[1] / 2.0, image.shape[0] / 2.0)
    m = cv2.getRotationMatrix2D(center, angle, 1.0)
    return cv2.warpAffine(image, m, (image.shape[1], image.shape[0]), flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_REPLICATE)


def read_chars(image, conf=0.2):
    r = read(image, conf=conf, verbose=False)[0]
    chars = []
    for b in r.boxes:
        xy = b.xyxy[0].cpu().numpy()
        chars.append({
            "label": read.names[int(b.cls[0])],
            "conf": float(b.conf[0]),
            "xc": (float(xy[0]) + float(xy[2])) / 2,
            "yc": (float(xy[1]) + float(xy[3])) / 2,
        })
    return chars


def assemble_by_regression(chars):
    """Deskew by fitting y~x slope, then cluster into 2 rows."""
    if len(chars) < 4:
        return "".join(c["label"] for c in sorted(chars, key=lambda c: c["xc"]))
    xs = np.array([c["xc"] for c in chars], dtype=float)
    ys = np.array([c["yc"] for c in chars], dtype=float)
    # fit yc = a*xc + b
    a, b = np.polyfit(xs, ys, 1)
    # residual after removing tilt
    residuals = ys - (a * xs + b)
    # 1D 2-means on residuals
    r_sorted = np.sort(residuals)
    # find largest gap in residuals
    gaps = [(r_sorted[i] - r_sorted[i - 1], i) for i in range(1, len(r_sorted))]
    gap, split_i = max(gaps, key=lambda t: t[0])
    thresh = (r_sorted[split_i - 1] + r_sorted[split_i]) / 2.0
    top = [c for c, r in zip(chars, residuals) if r <= thresh]
    bot = [c for c, r in zip(chars, residuals) if r > thresh]
    # ensure top is the upper row (smaller mean original y after untilt... use residual)
    if np.mean([r for r in residuals if r <= thresh]) > np.mean([r for r in residuals if r > thresh]):
        top, bot = bot, top
    top = sorted(top, key=lambda c: c["xc"])
    bot = sorted(bot, key=lambda c: c["xc"])
    return "".join(c["label"] for c in top), "".join(c["label"] for c in bot), a, gap


print("angle trials with bestRead + regression assemble:")
for angle in range(-30, 31, 5):
    im = rotate(crop, angle)
    chars = read_chars(im)
    if len(chars) < 6:
        print(angle, "few", len(chars), [c["label"] for c in chars])
        continue
    top, bot, slope, gap = assemble_by_regression(chars)
    print(f"angle={angle:3d} n={len(chars)} slope={slope:.3f} gap={gap:.1f} => {top}-{bot}")

# also try regression on original without rotate
chars = read_chars(crop)
top, bot, slope, gap = assemble_by_regression(chars)
print("orig regression", f"{top}-{bot}", "slope", slope)
