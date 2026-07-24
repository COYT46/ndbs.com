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
from ultralytics import YOLO

base = Path(r"C:\xampp\htdocs\ndbs.com")
img = cv2.imread(str(base / "public" / "uploads" / "vehicles" / "entry_1784553544_6a5e204884287.jpg"))
plate = YOLO(str(base / "best.pt"))
read = YOLO(str(base / "bestRead.pt"))
h, w = img.shape[:2]
res = plate(img, conf=0.25, verbose=False)[0]
box = max(res.boxes, key=lambda b: float(b.conf[0]))
x1, y1, x2, y2 = map(int, box.xyxy[0].cpu().numpy())
crop = img[max(0, y1 - 5): min(h, y2 + 5), max(0, x1 - 5): min(w, x2 + 5)]
r = read(crop, conf=0.15, verbose=False)[0]
chars = []
for b in r.boxes:
    xy = b.xyxy[0].cpu().numpy()
    chars.append({
        "label": read.names[int(b.cls[0])],
        "conf": float(b.conf[0]),
        "x1": float(xy[0]),
        "x2": float(xy[2]),
        "y1": float(xy[1]),
        "y2": float(xy[3]),
        "yc": (float(xy[1]) + float(xy[3])) / 2,
        "xc": (float(xy[0]) + float(xy[2])) / 2,
        "h": float(xy[3] - xy[1]),
    })
chars.sort(key=lambda c: (c["yc"], c["xc"]))
print("crop", crop.shape)
for c in chars:
    print(c["label"], "conf", round(c["conf"], 2), "yc", round(c["yc"], 1), "xc", round(c["xc"], 1), "h", round(c["h"], 1))

# gap-based clustering
chars_y = sorted(chars, key=lambda c: c["yc"])
gaps = []
for i in range(1, len(chars_y)):
    gaps.append((chars_y[i]["yc"] - chars_y[i - 1]["yc"], i))
print("gaps", [(round(g, 1), i) for g, i in gaps])
if gaps:
    best_gap, split_i = max(gaps, key=lambda t: t[0])
    print("best_gap", round(best_gap, 1), "split_i", split_i)
    top = sorted(chars_y[:split_i], key=lambda c: c["xc"])
    bot = sorted(chars_y[split_i:], key=lambda c: c["xc"])
    print("TOP", "".join(c["label"] for c in top), "BOT", "".join(c["label"] for c in bot))
