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
import easyocr

base = Path(r"C:\xampp\htdocs\ndbs.com")
img_path = base / "public" / "uploads" / "vehicles" / "entry_1784553544_6a5e204884287.jpg"
img = cv2.imread(str(img_path))
model = YOLO(str(base / "best.pt"))
reader = easyocr.Reader(["en"], gpu=False)
h, w = img.shape[:2]
res = model(img, conf=0.25, verbose=False)[0]
box = max(res.boxes, key=lambda b: float(b.conf[0]))
x1, y1, x2, y2 = map(int, box.xyxy[0].cpu().numpy())
pad = 8
x1, y1 = max(0, x1 - pad), max(0, y1 - pad)
x2, y2 = min(w, x2 + pad), min(h, y2 + pad)
crop = img[y1:y2, x1:x2]
cv2.imwrite(str(base / "_debug_crop.jpg"), crop)

def variants(c):
    gray = cv2.cvtColor(c, cv2.COLOR_BGR2GRAY)
    out = {"raw": c}
    # upscale
    up = cv2.resize(gray, None, fx=3, fy=3, interpolation=cv2.INTER_CUBIC)
    out["up3"] = cv2.cvtColor(up, cv2.COLOR_GRAY2BGR)
    clahe = cv2.createCLAHE(2.0, (8, 8)).apply(up)
    out["clahe"] = cv2.cvtColor(clahe, cv2.COLOR_GRAY2BGR)
    blur = cv2.GaussianBlur(clahe, (3, 3), 0)
    _, otsu = cv2.threshold(blur, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    out["otsu"] = cv2.cvtColor(otsu, cv2.COLOR_GRAY2BGR)
    out["otsu_inv"] = cv2.cvtColor(255 - otsu, cv2.COLOR_GRAY2BGR)
    # sharpen
    sharp = cv2.filter2D(clahe, -1, np.array([[0, -1, 0], [-1, 5, -1], [0, -1, 0]]))
    out["sharp"] = cv2.cvtColor(sharp, cv2.COLOR_GRAY2BGR)
    return out


allow = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-"
print("FULL CROP variants:")
for name, im in variants(crop).items():
    r = reader.readtext(im, detail=1, paragraph=False, allowlist=allow)
    print(name, [(t[1], round(float(t[2]), 2)) for t in r])

print("\nLINE SPLIT:")
hh = crop.shape[0]
for ratio in (0.40, 0.45, 0.50, 0.55):
    mid = int(hh * ratio)
    top = crop[:mid]
    bot = crop[mid:]
    for tname, tim in [("top_raw", top), ("top_up", cv2.resize(top, None, fx=3, fy=3, interpolation=cv2.INTER_CUBIC)),
                       ("bot_raw", bot), ("bot_up", cv2.resize(bot, None, fx=3, fy=3, interpolation=cv2.INTER_CUBIC))]:
        if not tname.startswith(("top", "bot")):
            continue
        # only print upscaled
    top_up = cv2.resize(top, None, fx=3, fy=3, interpolation=cv2.INTER_CUBIC)
    bot_up = cv2.resize(bot, None, fx=3, fy=3, interpolation=cv2.INTER_CUBIC)
    # clahe
    def prep(im):
        g = cv2.cvtColor(im, cv2.COLOR_BGR2GRAY)
        g = cv2.createCLAHE(2.0, (8, 8)).apply(g)
        return cv2.cvtColor(g, cv2.COLOR_GRAY2BGR)
    tr = reader.readtext(prep(top_up), detail=1, paragraph=False, allowlist=allow)
    br = reader.readtext(prep(bot_up), detail=1, paragraph=False, allowlist=allow)
    print(f"ratio={ratio}", "TOP", [(t[1], round(float(t[2]), 2)) for t in tr], "BOT", [(t[1], round(float(t[2]), 2)) for t in br])

# also try bestRead if exists
for name in ("bestRead.pt", "bestread.pt"):
    p = base / name
    if p.exists():
        print("\nbestRead exists", p)
        read_model = YOLO(str(p))
        for conf in (0.15, 0.25, 0.35):
            r = read_model(crop, conf=conf, verbose=False)[0]
            chars = []
            for b in r.boxes:
                xy = b.xyxy[0].cpu().numpy()
                chars.append({
                    "x": float(xy[0]),
                    "y": (float(xy[1]) + float(xy[3])) / 2,
                    "label": read_model.names[int(b.cls[0])],
                    "conf": float(b.conf[0]),
                })
            if not chars:
                print("conf", conf, "none")
                continue
            ys = [c["y"] for c in chars]
            if max(ys) - min(ys) > 0.28 * crop.shape[0]:
                midy = (min(ys) + max(ys)) / 2
                l1 = sorted([c for c in chars if c["y"] < midy], key=lambda c: c["x"])
                l2 = sorted([c for c in chars if c["y"] >= midy], key=lambda c: c["x"])
                text = "".join(c["label"] for c in l1) + "-" + "".join(c["label"] for c in l2)
            else:
                text = "".join(c["label"] for c in sorted(chars, key=lambda c: c["x"]))
            print("conf", conf, text, [(c["label"], round(c["conf"], 2)) for c in sorted(chars, key=lambda c: (c["y"], c["x"]))])
        break
else:
    print("no bestRead")
