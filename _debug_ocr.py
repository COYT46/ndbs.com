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
import easyocr

base = Path(r"C:\xampp\htdocs\ndbs.com")
model = YOLO(str(base / "best.pt"))
reader = easyocr.Reader(["en"], gpu=False)
files = sorted((base / "public" / "uploads" / "vehicles").glob("*.jpg"), key=lambda x: x.stat().st_mtime, reverse=True)[:12]

for f in files:
    img = cv2.imread(str(f))
    if img is None:
        continue
    h, w = img.shape[:2]
    res = model(img, conf=0.25, verbose=False)[0]
    if len(res.boxes) == 0:
        print(f.name, "NO_PLATE")
        continue
    box = max(res.boxes, key=lambda b: float(b.conf[0]))
    x1, y1, x2, y2 = map(int, box.xyxy[0].cpu().numpy())
    x1, y1 = max(0, x1 - 5), max(0, y1 - 5)
    x2, y2 = min(w, x2 + 5), min(h, y2 + 5)
    crop = img[y1:y2, x1:x2]
    out = reader.readtext(crop, detail=1, paragraph=False, allowlist="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-")
    texts = [(t[1], round(float(t[2]), 2)) for t in out]
    mid = crop.shape[0] // 2
    top = reader.readtext(crop[: mid + 5], detail=0, paragraph=False, allowlist="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-")
    bot = reader.readtext(crop[mid - 5 :], detail=0, paragraph=False, allowlist="ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-")
    print(f.name, "conf", round(float(box.conf[0]), 2), "crop", crop.shape, "ocr", texts, "halves", top, bot)
