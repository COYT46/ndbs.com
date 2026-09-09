import sys
import os
import json
import site
import io
import re
import numpy as np

_plate_model = None
_read_model = None
_easyocr_reader = None

try:
    if sys.stdout.encoding != 'utf-8':
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
except Exception:
    pass

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

# Điểm đủ tốt + format hợp lệ → trả ngay, bỏ EasyOCR
GOOD_SCORE = 160

# Biển ô tô VN 1 dòng ~520×110 (aspect ≈ 4.7); xe máy 2 dòng ~190×140 (aspect ≈ 1.4)
CAR_ASPECT_MIN = 2.35
MOTO_ASPECT_MAX = 2.05


def compact_alnum(text):
    return re.sub(r'[^A-Z0-9]', '', (text or '').upper())


def unique_nonempty(values):
    seen = set()
    ordered = []
    for value in values:
        if value and value not in seen:
            seen.add(value)
            ordered.append(value)
    return ordered


def infer_plate_layout(raw_text='', aspect=None, char_layout=None):
    """one_line = ô tô; two_line = xe máy 2 dòng. Tỷ lệ khung biển ưu tiên hơn cụm ký tự."""
    if aspect is not None:
        try:
            ratio = float(aspect)
            if ratio >= CAR_ASPECT_MIN:
                return 'one_line'
            if ratio <= MOTO_ASPECT_MAX:
                return 'two_line'
        except (TypeError, ValueError):
            pass
    if char_layout in ('one_line', 'two_line'):
        return char_layout
    hint = re.sub(r'[^A-Z0-9-]', '', (raw_text or '').upper()).strip('-')
    if '-' in hint:
        left = compact_alnum(hint.split('-', 1)[0])
        if len(left) >= 4:
            return 'two_line'
        if len(left) == 3:
            return 'one_line'
    return 'one_line'


def format_plate(text, layout=None, aspect=None):
    """Chỉ giữ chữ và số. Không chèn '-' vì ô tô/xe máy đều có biển 1 dòng và 2 dòng."""
    return compact_alnum(text)


def is_valid_plate(text):
    compact = compact_alnum(text)
    return bool(
        re.match(r'^\d{2}[A-Z][A-Z0-9]\d{4,5}$', compact)
        or re.match(r'^\d{2}[A-Z]\d{4,5}$', compact)
    )


def score_plate_text(text, raw_hint='', mean_conf=0.0, layout=None, aspect=None):
    compact = compact_alnum(text)
    if not compact:
        return (-1, 0, 0, 0, 0.0)

    score = 0
    if re.match(r'^\d{2}[A-Z][A-Z0-9]\d{4,5}$', compact) or re.match(r'^\d{2}[A-Z]\d{4,5}$', compact):
        score += 140
    elif re.match(r'^\d{2}[A-Z]{1,2}\d{4,5}$', compact):
        score += 90
    elif len(compact) >= 7:
        score += 30

    digits = sum(ch.isdigit() for ch in compact)
    letters = sum(ch.isalpha() for ch in compact)
    score += digits * 4 + letters * 3
    if len(compact) in (7, 8, 9):
        score += 12

    hint_compact = compact_alnum(raw_hint)
    if hint_compact:
        same = sum(1 for a, b in zip(compact, hint_compact) if a == b)
        score += same * 8
        score -= abs(len(compact) - len(hint_compact)) * 6

    score += int(mean_conf * 40)
    return (score, digits, letters, len(compact), mean_conf)


def normalize_plate_text(text, raw_hint='', layout=None, aspect=None):
    text = re.sub(r'\s+', '', (text or '').upper())
    text = re.sub(r'[^A-Z0-9-]', '', text)
    if not text:
        return text

    layout = infer_plate_layout(raw_hint or text, aspect=aspect, char_layout=layout)
    compact = text.replace('-', '')
    digit_from_letter = {
        'O': '0', 'Q': '0', 'D': '0', 'I': '1', 'L': '1',
        'Z': '2', 'S': '5', 'B': '8', 'G': '6', 'T': '7',
    }
    letter_from_digit = {
        '0': 'O', '1': 'I', '2': 'Z', '4': 'A', '5': 'S',
        '6': 'G', '8': 'B',
    }

    def coerce_digit(ch):
        ch = ch.upper()
        return ch if ch.isdigit() else digit_from_letter.get(ch, ch)

    def coerce_letter(ch):
        ch = ch.upper()
        return ch if ch.isalpha() else letter_from_digit.get(ch, ch)

    candidates = []

    def try_build(variant):
        for top_len, tail_len in ((3, 5), (3, 4), (4, 5), (4, 4)):
            if len(variant) != top_len + tail_len:
                continue
            top = variant[:top_len]
            tail = variant[top_len:]
            if top_len == 3:
                built = (
                    coerce_digit(top[0]) + coerce_digit(top[1]) + coerce_letter(top[2])
                    + ''.join(coerce_digit(ch) for ch in tail)
                )
                if re.match(r'^\d{2}[A-Z]\d{4,5}$', built):
                    candidates.append(format_plate(built))
            else:
                series2 = top[3].upper() if top[3].isalnum() else top[3]
                built = (
                    coerce_digit(top[0]) + coerce_digit(top[1]) + coerce_letter(top[2])
                    + series2 + ''.join(coerce_digit(ch) for ch in tail)
                )
                if re.match(r'^\d{2}[A-Z][A-Z0-9]\d{4,5}$', built):
                    candidates.append(format_plate(built))

    variants = [compact]
    if len(compact) > 1:
        variants.extend([compact[1:], compact[:-1]])
    if len(compact) > 2:
        variants.append(compact[1:-1])

    for variant in unique_nonempty(variants):
        try_build(variant)

    already = format_plate(text, layout=layout, aspect=aspect)
    if is_valid_plate(already):
        candidates.insert(0, already)

    candidates = unique_nonempty(candidates)
    if not candidates:
        return already or text

    candidates.sort(
        key=lambda c: score_plate_text(
            c, raw_hint=raw_hint or text, layout=layout, aspect=aspect
        ),
        reverse=True,
    )
    return candidates[0]


def _bootstrap_cv_yolo():
    import cv2
    from ultralytics import YOLO
    return cv2, YOLO


def load_models(base_dir=None):
    global _plate_model, _read_model
    cv2, YOLO = _bootstrap_cv_yolo()

    if base_dir is None:
        base_dir = os.path.dirname(os.path.abspath(__file__))

    if _plate_model is None:
        best_pt = os.path.join(base_dir, "best.pt")
        if not os.path.exists(best_pt):
            raise FileNotFoundError("Model file best.pt not found")
        _plate_model = YOLO(best_pt)

    if _read_model is None:
        bestread_pt = os.path.join(base_dir, "bestRead.pt")
        if not os.path.exists(bestread_pt):
            bestread_pt = os.path.join(base_dir, "bestread.pt")
        if os.path.exists(bestread_pt):
            _read_model = YOLO(bestread_pt)
        else:
            _read_model = False

    return _plate_model, (_read_model if _read_model is not False else None)


def load_easyocr_reader():
    global _easyocr_reader
    if _easyocr_reader is not None:
        return _easyocr_reader if _easyocr_reader is not False else None
    try:
        import easyocr
        _easyocr_reader = easyocr.Reader(['en'], gpu=False, verbose=False)
    except Exception:
        _easyocr_reader = False
        return None
    return _easyocr_reader


def detect_plate(img_path, conf_thres=0.22):
    """
    Chỉ phát hiện khung biển (best.pt), KHÔNG đọc ký tự.
    Trả: {success, detected, confidence, box?}
    """
    cv2, _YOLO = _bootstrap_cv_yolo()
    plate_model, _read_model = load_models()

    img = cv2.imread(img_path)
    if img is None:
        return {"success": False, "detected": False, "error": "Could not read image"}

    h_img, w_img = img.shape[:2]
    detect_img = img
    detect_scale = 1.0
    max_side = max(h_img, w_img)
    if max_side > 1280:
        detect_scale = 1280.0 / max_side
        detect_img = cv2.resize(
            img,
            (int(w_img * detect_scale), int(h_img * detect_scale)),
            interpolation=cv2.INTER_AREA,
        )

    plate_results = plate_model(detect_img, conf=min(0.12, conf_thres), verbose=False, imgsz=640)
    boxes = plate_results[0].boxes
    if boxes is None or len(boxes) == 0:
        return {"success": True, "detected": False, "confidence": 0.0}

    best_box = max(boxes, key=lambda b: float(b.conf[0]))
    confidence = float(best_box.conf[0])
    if confidence < conf_thres:
        return {"success": True, "detected": False, "confidence": confidence}

    xyxy = best_box.xyxy[0].cpu().numpy() / detect_scale
    x1, y1, x2, y2 = map(float, xyxy)
    box_w = max(1.0, x2 - x1)
    box_h = max(1.0, y2 - y1)
    aspect = box_w / box_h
    area_ratio = (box_w * box_h) / float(max(1, w_img * h_img))

    # Biển VN thường ngang (1 dòng ~2-5, 2 dòng ~1.2-2.5); loại box quá kỳ
    if aspect < 1.05 or aspect > 6.5:
        return {"success": True, "detected": False, "confidence": confidence, "reason": "aspect"}
    if area_ratio < 0.003:
        return {"success": True, "detected": False, "confidence": confidence, "reason": "too_small"}

    return {
        "success": True,
        "detected": True,
        "confidence": confidence,
        "box": [int(x1), int(y1), int(x2), int(y2)],
        "area_ratio": round(area_ratio, 4),
    }


def recognize_plate(img_path):
    """Nhận diện biển số từ đường dẫn ảnh. Trả dict {success, plate|error}.
    Bắt buộc detect được khung biển trước, rồi mới đọc ký tự.
    """
    cv2, _YOLO = _bootstrap_cv_yolo()
    plate_model, read_model = load_models()

    img = cv2.imread(img_path)
    if img is None:
        return {"success": False, "error": "Could not read image", "stage": "load"}

    h_img, w_img = img.shape[:2]

    detect_img = img
    detect_scale = 1.0
    max_side = max(h_img, w_img)
    if max_side > 1280:
        detect_scale = 1280.0 / max_side
        detect_img = cv2.resize(
            img,
            (int(w_img * detect_scale), int(h_img * detect_scale)),
            interpolation=cv2.INTER_AREA,
        )

    def rotate_image(image, angle_degrees):
        if abs(angle_degrees) < 0.5:
            return image
        center = (image.shape[1] / 2.0, image.shape[0] / 2.0)
        matrix = cv2.getRotationMatrix2D(center, angle_degrees, 1.0)
        return cv2.warpAffine(
            image, matrix, (image.shape[1], image.shape[0]),
            flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE,
        )

    def extract_chars(image, thresh=0.2):
        if read_model is None:
            return []
        # 640: đủ rõ để không nhầm A/4; inference ~0.1s trên crop
        res = read_model(image, conf=thresh, verbose=False, imgsz=640)
        chars = []
        for c_box in res[0].boxes:
            xy = c_box.xyxy[0].cpu().numpy()
            cx1, cy1, cx2, cy2 = map(float, xy)
            chars.append({
                'label': str(read_model.names[int(c_box.cls[0])]),
                'conf': float(c_box.conf[0]),
                'xc': (cx1 + cx2) / 2.0,
                'yc': (cy1 + cy2) / 2.0,
            })
        return chars

    def nms_chars(chars, dist=12):
        chars = sorted(chars, key=lambda c: c['conf'], reverse=True)
        kept = []
        for ch in chars:
            if any(abs(ch['xc'] - k['xc']) < dist and abs(ch['yc'] - k['yc']) < dist for k in kept):
                continue
            kept.append(ch)
        return kept

    def crop_aspect(image):
        if image is None or image.size == 0:
            return None
        h, w = image.shape[:2]
        if h <= 0:
            return None
        return w / float(h)

    def assemble_char_sequence(chars):
        if not chars:
            return "", 0.0, 'one_line'

        mean_conf = float(np.mean([c['conf'] for c in chars]))
        if len(chars) <= 3:
            ordered = sorted(chars, key=lambda c: c['xc'])
            return ''.join(c['label'] for c in ordered), mean_conf, 'one_line'

        xs = np.array([c['xc'] for c in chars], dtype=float)
        ys = np.array([c['yc'] for c in chars], dtype=float)
        y_span = float(ys.max() - ys.min())
        x_span = max(float(xs.max() - xs.min()), 1.0)

        if y_span < max(18.0, 0.18 * x_span) or len(chars) < 5:
            ordered = sorted(chars, key=lambda c: c['xc'])
            return ''.join(c['label'] for c in ordered), mean_conf, 'one_line'

        try:
            slope, intercept = np.polyfit(xs, ys, 1)
        except Exception:
            slope, intercept = 0.0, float(np.mean(ys))

        residuals = ys - (slope * xs + intercept)
        order = np.argsort(residuals)
        r_sorted = residuals[order]
        gaps = [(float(r_sorted[i] - r_sorted[i - 1]), i) for i in range(1, len(r_sorted))]
        if not gaps:
            ordered = sorted(chars, key=lambda c: c['xc'])
            return ''.join(c['label'] for c in ordered), mean_conf, 'one_line'

        gap, split_i = max(gaps, key=lambda item: item[0])
        if gap < max(10.0, 0.12 * y_span) and len(chars) < 7:
            ordered = sorted(chars, key=lambda c: c['xc'])
            return ''.join(c['label'] for c in ordered), mean_conf, 'one_line'

        thresh = (r_sorted[split_i - 1] + r_sorted[split_i]) / 2.0
        top = [c for c, r in zip(chars, residuals) if r <= thresh]
        bot = [c for c, r in zip(chars, residuals) if r > thresh]
        if not top or not bot:
            ordered = sorted(chars, key=lambda c: c['xc'])
            return ''.join(c['label'] for c in ordered), mean_conf, 'one_line'

        if np.mean([r for r in residuals if r <= thresh]) > np.mean([r for r in residuals if r > thresh]):
            top, bot = bot, top

        top = sorted(top, key=lambda c: c['xc'])
        bot = sorted(bot, key=lambda c: c['xc'])
        return (
            ''.join(c['label'] for c in top) + ''.join(c['label'] for c in bot),
            mean_conf,
            'two_line',
        )

    def try_recognize_image(image, conf=0.2):
        chars = nms_chars(extract_chars(image, thresh=conf))
        if len(chars) < 5:
            return "", (-1, 0, 0, 0, 0.0)
        raw, mean_conf, char_layout = assemble_char_sequence(chars)
        if not raw:
            return "", (-1, 0, 0, 0, 0.0)
        aspect = crop_aspect(image)
        layout = infer_plate_layout(raw, aspect=aspect, char_layout=char_layout)
        normalized = normalize_plate_text(raw, raw_hint=raw, layout=layout, aspect=aspect)
        return normalized, score_plate_text(
            normalized, raw_hint=raw, mean_conf=mean_conf, layout=layout, aspect=aspect
        )

    def is_good_result(text, score):
        return is_valid_plate(text) and score[0] >= GOOD_SCORE and len(compact_alnum(text)) >= 7

    def recognize_fast(crop):
        best_text, best_score = try_recognize_image(crop, conf=0.2)
        if is_good_result(best_text, best_score):
            return best_text, best_score

        if len(compact_alnum(best_text)) < 7:
            text, score = try_recognize_image(crop, conf=0.12)
            if score > best_score:
                best_text, best_score = text, score
            if is_good_result(best_text, best_score):
                return best_text, best_score

        for angle in (-8.0, 8.0):
            text, score = try_recognize_image(rotate_image(crop, angle), conf=0.2)
            if score > best_score:
                best_text, best_score = text, score
            if is_good_result(best_text, best_score):
                return best_text, best_score

        return best_text, best_score

    def recognize_easyocr_fallback(crop):
        reader = load_easyocr_reader()
        if reader is None:
            return "", (-1, 0, 0, 0, 0.0)

        trial = crop
        if max(crop.shape[:2]) < 400:
            trial = cv2.resize(crop, None, fx=2.0, fy=2.0, interpolation=cv2.INTER_CUBIC)

        allow = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-'
        try:
            raw_results = reader.readtext(trial, detail=1, paragraph=False, allowlist=allow)
        except TypeError:
            try:
                raw_results = reader.readtext(trial, detail=1, paragraph=False)
            except Exception:
                return "", (-1, 0, 0, 0, 0.0)
        except Exception:
            return "", (-1, 0, 0, 0, 0.0)

        detections = []
        for item in raw_results or []:
            if not item or len(item) < 2:
                continue
            text_value = re.sub(r'[^A-Za-z0-9-]', '', str(item[1]).strip().upper())
            if not text_value:
                continue
            bbox = item[0]
            if isinstance(bbox, (list, tuple)) and bbox:
                cx = sum(p[0] for p in bbox) / len(bbox)
                cy = sum(p[1] for p in bbox) / len(bbox)
            else:
                cx, cy = 0.0, 0.0
            detections.append({'text': text_value, 'x': cx, 'y': cy})

        if not detections:
            return "", (-1, 0, 0, 0, 0.0)

        ys = [d['y'] for d in detections]
        if max(ys) - min(ys) > max(12.0, 0.2 * trial.shape[0]):
            mid = (min(ys) + max(ys)) / 2.0
            top = sorted([d for d in detections if d['y'] < mid], key=lambda d: d['x'])
            bot = sorted([d for d in detections if d['y'] >= mid], key=lambda d: d['x'])
            raw = ''.join(d['text'] for d in top) + ''.join(d['text'] for d in bot)
            char_layout = 'two_line'
        else:
            raw = ''.join(d['text'] for d in sorted(detections, key=lambda d: (d['y'], d['x'])))
            char_layout = 'one_line'

        aspect = crop_aspect(crop)
        layout = infer_plate_layout(raw, aspect=aspect, char_layout=char_layout)
        normalized = normalize_plate_text(raw, raw_hint=raw, layout=layout, aspect=aspect)
        return normalized, score_plate_text(
            normalized, raw_hint=raw, mean_conf=0.3, layout=layout, aspect=aspect
        )

    # --- Bước 1: bắt buộc thấy khung biển ---
    DETECT_CONF = 0.22
    plate_results = plate_model(detect_img, conf=0.12, verbose=False, imgsz=640)
    boxes = plate_results[0].boxes

    if boxes is None or len(boxes) == 0:
        return {
            "success": False,
            "error": "Chưa phát hiện biển số trong khung hình",
            "stage": "detect",
        }

    best_box = max(boxes, key=lambda b: float(b.conf[0]))
    det_conf = float(best_box.conf[0])
    if det_conf < DETECT_CONF:
        return {
            "success": False,
            "error": "Chưa chắc là biển số (độ tin cậy thấp)",
            "stage": "detect",
            "confidence": det_conf,
        }

    xyxy = best_box.xyxy[0].cpu().numpy() / detect_scale
    x1, y1, x2, y2 = map(int, xyxy)
    box_w = max(1, x2 - x1)
    box_h = max(1, y2 - y1)
    aspect = box_w / float(box_h)
    area_ratio = (box_w * box_h) / float(max(1, w_img * h_img))
    if aspect < 1.05 or aspect > 6.5 or area_ratio < 0.003:
        return {
            "success": False,
            "error": "Đối tượng không giống biển số",
            "stage": "detect",
            "confidence": det_conf,
        }

    # --- Bước 2: crop biển rồi mới đọc ký tự ---
    close_up = area_ratio >= 0.35
    pad = 4 if close_up else 8
    x1 = max(0, x1 - pad)
    y1 = max(0, y1 - pad)
    x2 = min(w_img, x2 + pad)
    y2 = min(h_img, y2 + pad)
    if x2 <= x1 or y2 <= y1:
        return {
            "success": False,
            "error": "Khung biển không hợp lệ",
            "stage": "detect",
        }

    crop_img = img[y1:y2, x1:x2]

    ch, cw = crop_img.shape[:2]
    if max(ch, cw) > 900:
        scale = 900.0 / max(ch, cw)
        crop_img = cv2.resize(
            crop_img,
            (max(1, int(cw * scale)), max(1, int(ch * scale))),
            interpolation=cv2.INTER_AREA,
        )

    final_plate, final_score = recognize_fast(crop_img)

    if not is_valid_plate(final_plate):
        xyxy = best_box.xyxy[0].cpu().numpy() / detect_scale
        wx1, wy1, wx2, wy2 = map(int, xyxy)
        wx1 = max(0, wx1 - 16)
        wy1 = max(0, wy1 - 16)
        wx2 = min(w_img, wx2 + 16)
        wy2 = min(h_img, wy2 + 16)
        if wx2 > wx1 and wy2 > wy1:
            wider = img[wy1:wy2, wx1:wx2]
            wider_plate, wider_score = recognize_fast(wider)
            if wider_score > final_score:
                final_plate, final_score = wider_plate, wider_score

    if not is_valid_plate(final_plate):
        easy_plate, easy_score = recognize_easyocr_fallback(crop_img)
        if easy_score > final_score:
            final_plate, final_score = easy_plate, easy_score

    if not final_plate:
        return {
            "success": False,
            "error": "Đã thấy biển nhưng chưa đọc được ký tự",
            "stage": "ocr",
        }

    if not is_valid_plate(final_plate):
        return {
            "success": False,
            "error": "Đã thấy biển nhưng chuỗi ký tự chưa hợp lệ",
            "stage": "ocr",
            "plate_hint": final_plate,
        }

    return {"success": True, "plate": final_plate, "detect_confidence": det_conf}



def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No image path provided"}, ensure_ascii=True))
        return

    img_path = sys.argv[1]
    if not os.path.exists(img_path):
        print(json.dumps({"success": False, "error": f"Image file not found: {img_path}"}, ensure_ascii=True))
        return

    try:
        result = recognize_plate(img_path)
        print(json.dumps(result, ensure_ascii=True))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=True))


if __name__ == "__main__":
    main()
