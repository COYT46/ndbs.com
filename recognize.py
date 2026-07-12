import sys
import os
import json
import site
import io

# Ensure UTF-8 output on Windows
try:
    if sys.stdout.encoding != 'utf-8':
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
except Exception:
    pass

# Tự động nạp thư viện từ Roaming site-packages (khi chạy qua XAMPP Apache)
try:
    user_site = site.getusersitepackages()
    if user_site and os.path.exists(user_site) and user_site not in sys.path:
        sys.path.append(user_site)
except Exception:
    pass

for py_ver in ["314", "313", "312", "311", "310", "39", "38"]:
    admin_path = f"C:\\Users\\ADMIN\\AppData\\Roaming\\Python\\Python{py_ver}\\site-packages"
    if os.path.exists(admin_path) and admin_path not in sys.path:
        sys.path.append(admin_path)

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No image path provided"}, ensure_ascii=True))
        return

    img_path = sys.argv[1]
    if not os.path.exists(img_path):
        print(json.dumps({"success": False, "error": f"Image file not found: {img_path}"}, ensure_ascii=True))
        return

    try:
        import cv2
        from ultralytics import YOLO
    except ImportError as e:
        print(json.dumps({"success": False, "error": f"Import error: {str(e)}"}, ensure_ascii=True))
        return

    base_dir = os.path.dirname(os.path.abspath(__file__))
    best_pt = os.path.join(base_dir, "best.pt")
    bestread_pt = os.path.join(base_dir, "bestRead.pt")
    if not os.path.exists(bestread_pt):
        bestread_pt = os.path.join(base_dir, "bestread.pt")

    if not os.path.exists(best_pt) or not os.path.exists(bestread_pt):
        print(json.dumps({"success": False, "error": "Model files best.pt or bestRead.pt not found"}, ensure_ascii=True))
        return

    try:
        # Load models
        plate_model = YOLO(best_pt)
        read_model = YOLO(bestread_pt)

        # Load image
        img = cv2.imread(img_path)
        if img is None:
            print(json.dumps({"success": False, "error": "Could not read image"}, ensure_ascii=True))
            return

        h_img, w_img, _ = img.shape

        # Detect license plate box
        # Detect license plate box với ngưỡng conf thấp (0.15)
        plate_results = plate_model(img, conf=0.15, verbose=False)
        boxes = plate_results[0].boxes

        crop_img = img
        best_box = None
        if len(boxes) > 0:
            max_conf = -1
            for box in boxes:
                conf = float(box.conf[0])
                if conf > max_conf:
                    max_conf = conf
                    best_box = box

            if best_box is not None:
                xyxy = best_box.xyxy[0].cpu().numpy()
                x1, y1, x2, y2 = map(int, xyxy)
                x1 = max(0, x1 - 5)
                y1 = max(0, y1 - 5)
                x2 = min(w_img, x2 + 5)
                y2 = min(h_img, y2 + 5)
                if x2 > x1 and y2 > y1:
                    crop_img = img[y1:y2, x1:x2]

        h_crop, w_crop, _ = crop_img.shape

        def extract_chars(image, thresh):
            # CỰC KỲ QUAN TRỌNG: Truyền trực tiếp conf=thresh vào hàm YOLO để không bị lọc mất ở ngưỡng mặc định 0.25
            res = read_model(image, conf=thresh, verbose=False)
            c_boxes = res[0].boxes
            res_chars = []
            for c_box in c_boxes:
                xy = c_box.xyxy[0].cpu().numpy()
                cx1, cy1, cx2, cy2 = map(float, xy)
                conf = float(c_box.conf[0])
                cls_id = int(c_box.cls[0])
                label = str(read_model.names[cls_id])
                y_center = (cy1 + cy2) / 2.0
                res_chars.append({
                    'x1': cx1,
                    'y_center': y_center,
                    'label': label
                })
            return res_chars

        # Detect characters với ngưỡng tự động thích nghi
        chars = extract_chars(crop_img, 0.25)
        if not chars or len(chars) < 3:
            chars_low = extract_chars(crop_img, 0.10)
            if len(chars_low) > len(chars):
                chars = chars_low

        if not chars or len(chars) < 3:
            chars_super_low = extract_chars(crop_img, 0.05)
            if len(chars_super_low) > len(chars):
                chars = chars_super_low

        if not chars or len(chars) < 3:
            # Thử crop rộng hơn (padding 15px)
            if best_box is not None:
                xyxy = best_box.xyxy[0].cpu().numpy()
                x1, y1, x2, y2 = map(int, xyxy)
                x1 = max(0, x1 - 15)
                y1 = max(0, y1 - 15)
                x2 = min(w_img, x2 + 15)
                y2 = min(h_img, y2 + 15)
                if x2 > x1 and y2 > y1:
                    crop_wider = img[y1:y2, x1:x2]
                    chars_wider = extract_chars(crop_wider, 0.08)
                    if len(chars_wider) > len(chars):
                        chars = chars_wider
                        h_crop, w_crop, _ = crop_wider.shape

        if not chars or len(chars) < 3:
            # Cuối cùng thử trên toàn bộ ảnh gốc (uncropped)
            chars_full = extract_chars(img, 0.08)
            if len(chars_full) > len(chars):
                chars = chars_full
                h_crop = h_img

        if not chars:
            print(json.dumps({"success": False, "error": "Không đọc được ký tự biển số từ ảnh (mô hình AI bestRead.pt chưa nhận diện được ký tự nào trên bức ảnh này)"}, ensure_ascii=True))
            return

        # Sort / Group into lines
        y_centers = [c['y_center'] for c in chars]
        min_y = min(y_centers)
        max_y = max(y_centers)

        # Check if 2 lines (difference in y_center > 25% of height)
        if (max_y - min_y) > (0.28 * h_crop):
            mid_y = (min_y + max_y) / 2.0
            line1 = [c for c in chars if c['y_center'] < mid_y]
            line2 = [c for c in chars if c['y_center'] >= mid_y]
            line1.sort(key=lambda x: x['x1'])
            line2.sort(key=lambda x: x['x1'])
            str1 = "".join([c['label'] for c in line1])
            str2 = "".join([c['label'] for c in line2])
            if str1 and str2:
                final_plate = f"{str1}-{str2}"
            else:
                final_plate = str1 + str2
        else:
            chars.sort(key=lambda x: x['x1'])
            final_plate = "".join([c['label'] for c in chars])

        # Clean plate text if needed
        print(json.dumps({"success": True, "plate": final_plate}, ensure_ascii=True))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=True))

if __name__ == "__main__":
    main()
