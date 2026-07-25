# So sánh các phương pháp nhận dạng ký tự biển số và mô hình tự huấn luyện

> Phần này viết sẵn để đưa vào báo cáo đồ án/hệ thống NDBS.
> Số liệu lấy từ thực nghiệm trên ảnh thực tế của hệ thống (`public/uploads/vehicles`), không lấy từ bài báo bên ngoài.

---

## 1. Mục tiêu so sánh

Trong hệ thống nhận diện biển số xe Việt Nam (NDBS), bước quan trọng nhất sau khi phát hiện vùng biển là **nhận dạng chuỗi ký tự** trên biển. Để lựa chọn phương pháp phù hợp cho triển khai thực tế, đề tài so sánh ba hướng tiếp cận:

1. **EasyOCR** — thư viện OCR tổng quát mã nguồn mở (dựa trên PyTorch), dùng sẵn không cần huấn luyện lại.
2. **PaddleOCR** — thư viện OCR tổng quát của Baidu, dùng sẵn không cần huấn luyện lại.
3. **Mô hình nhận diện ký tự tự huấn luyện** — mô hình YOLO phát hiện từng ký tự trên biển (`bestRead.pt`), kết hợp mô hình phát hiện vùng biển (`best.pt`), được tinh chỉnh cho định dạng biển số Việt Nam.

Ba phương pháp được đánh giá trên **cùng một tập ảnh**, **cùng vùng cắt biển**, và **cùng cách chuẩn hóa kết quả**, nhằm đảm bảo sự công bằng khi so sánh.

---

## 2. Mô hình nhận diện ký tự tự huấn luyện

### 2.1. Ý tưởng tổng quát

Khác với EasyOCR và PaddleOCR (đọc cả khối chữ trên ảnh theo kiểu OCR tài liệu), hướng tự huấn luyện tách bài toán thành hai giai đoạn chuyên biệt:

| Giai đoạn | Mô hình | Vai trò | File trọng số |
|-----------|---------|---------|---------------|
| 1. Phát hiện biển | YOLO (object detection) | Định vị khung biển số trên ảnh xe | `best.pt` |
| 2. Nhận diện ký tự | YOLO (object detection theo lớp ký tự) | Phát hiện và phân lớp từng ký tự trên vùng biển đã cắt | `bestRead.pt` |

Cách tiếp cận này phù hợp với biển số Việt Nam vì:

- Biển có **font đặc thù**, ít biến thể so với chữ in thông thường;
- Biển xe máy thường **hai dòng** (ví dụ `29-AF` / `828.53`), dễ bị OCR tổng quát ghép sai thứ tự;
- Biển thực tế thường **nghiêng, mờ, thiếu sáng**, cần mô hình học trực tiếp từ dữ liệu miền bài toán.

### 2.2. Quy trình nhận diện của mô hình tự huấn luyện

Quy trình triển khai trong mã nguồn `recognize.py` như sau:

1. **Đọc ảnh đầu vào** từ camera/upload.
2. **Phát hiện vùng biển** bằng `best.pt` (ngưỡng tin cậy 0.25, kích thước suy luận 640).
3. **Cắt vùng biển** (crop) và mở rộng nhẹ biên (padding) để tránh cắt mất ký tự.
4. **Phát hiện từng ký tự** trên crop bằng `bestRead.pt`. Mỗi hộp dự đoán gắn một nhãn thuộc tập `{0–9, A–Z}`.
5. **Lọc trùng (NMS đơn giản)** theo vị trí tâm hộp để loại bỏ phát hiện trùng.
6. **Lắp chuỗi theo hình học** (`assemble_char_sequence` trong `recognize.py`) — chi tiết ở mục 2.2.1.
7. **Chuẩn hóa theo cấu trúc biển Việt Nam**, ví dụ:
   - Xe máy: `29AF-82853`
   - Ô tô (một dòng): `30A-12345`
8. **Chấm điểm kết quả** (độ khớp format + độ tin cậy). Nếu kết quả đủ tốt thì trả về ngay; nếu không đạt mới gọi EasyOCR làm phương án dự phòng (fallback).

Điểm then chốt so với OCR tổng quát: mô hình **không “đọc chữ” theo nghĩa nhận dạng chuỗi liên tục**, mà **phát hiện từng ký tự như vật thể**, rồi lắp lại theo vị trí hình học. Nhờ đó, biển nghiêng hai dòng vẫn có thể được lắp đúng nếu các ký tự được phát hiện đủ.

### 2.2.1. Thuật toán sắp xếp ký tự cho biển hai dòng

Sau khi `bestRead.pt` trả về các hộp ký tự (nhãn + tâm `(xc, yc)` + độ tin cậy), hệ thống lọc trùng (NMS theo khoảng cách tâm ≈ 12 px), rồi quyết định **một dòng hay hai dòng** và sắp xếp như sau:

1. **Fallback một dòng sớm** — nếu số ký tự ≤ 3, hoặc biên độ theo trục Y nhỏ (`y_span < max(18, 0.18·x_span)`), hoặc còn < 5 ký tự: sắp xếp trái → phải theo `xc` và trả về một chuỗi liên tục.
2. **Ước lượng độ nghiêng** — hồi quy tuyến tính bậc 1: `y ≈ slope·x + intercept` trên các tâm ký tự (khắc phục biển bị nghiêng).
3. **Phần dư (residual)** — với mỗi ký tự: `r = yc − (slope·xc + intercept)`. Residual đo độ lệch “vuông góc” với đường nghiêng; dòng trên và dòng dưới tạo hai cụm residual tách biệt.
4. **Tách hai dòng bằng khoảng trống lớn nhất** — sắp xếp residual tăng dần, tìm cặp liền kề có `gap = r[i] − r[i−1]` lớn nhất. Ngưỡng tách là trung điểm của hai residual ở hai phía khoảng trống đó.
5. **Kiểm tra gap** — nếu `gap < max(10, 0.12·y_span)` và số ký tự < 7: coi như vẫn một dòng (tránh tách nhầm khi biển chỉ hơi lệch).
6. **Gán dòng trên / dưới** — nhóm `residual ≤ thresh` và `residual > thresh`; đảm bảo nhóm “trên” có mean residual nhỏ hơn (đổi chỗ nếu ngược).
7. **Sắp trong từng dòng** — mỗi dòng sort theo `xc` tăng dần (trái → phải).
8. **Ghép kết quả** — `DÒNGTRÊN + "-" + DÒNGDƯỚI` (ví dụ `29AF-82853`).

Ý tưởng then chốt: **không** cắt theo `mid_y` tuyệt đối (sai khi biển nghiêng), mà cắt theo residual sau khi đã “đưa biển về ngang” bằng hồi quy.

### 2.3. Vai trò của EasyOCR trong hệ thống thực tế

Trong pipeline sản phẩm, EasyOCR **không phải đường chính**. Nó chỉ được gọi khi mô hình tự huấn luyện chưa cho ra chuỗi biển hợp lệ đủ điểm. Việc này giúp:

- Giữ tốc độ cao trong trường hợp phổ biến (YOLO đủ tốt);
- Vẫn có cơ hội cứu kết quả khi YOLO thiếu ký tự hoặc lắp sai.

---

## 3. Hai phương pháp OCR tổng quát dùng để so sánh

### 3.1. EasyOCR

EasyOCR là thư viện OCR đa ngôn ngữ, dễ tích hợp. Trong thí nghiệm:

- Ngôn ngữ: tiếng Anh (`en`) — đủ cho chữ Latin và chữ số trên biển Việt Nam;
- Chạy trên CPU;
- Chỉ cho phép tập ký tự `A–Z`, `0–9`, `-` (allowlist) để giảm nhiễu;
- Sau khi đọc các hộp chữ, hệ thống ghép theo tọa độ Y (tách 2 dòng nếu khoảng cách đủ lớn), rồi chuẩn hóa format biển giống mô hình tự huấn luyện.

Ưu điểm: cài đặt đơn giản, không cần dữ liệu huấn luyện riêng.  
Nhược điểm quan sát được trên dữ liệu NDBS: dễ sai trên biển nghiêng/mờ, dễ ghép nhầm hai dòng, thời gian suy luận trên CPU cao.

### 3.2. PaddleOCR

PaddleOCR là bộ OCR mạnh cho văn bản tổng quát. Trong thí nghiệm dùng phiên bản ổn định trên môi trường phụ (Python 3.12):

- `paddleocr==2.7.3`, `paddlepaddle==2.6.2`;
- Chạy CPU, tắt MKLDNN để tránh lỗi runtime trên Windows;
- Cùng cách ghép dòng và chuẩn hóa format như EasyOCR.

Ưu điểm: độ chính xác toàn biển trên tập thử tốt hơn EasyOCR rõ rệt, nhanh hơn EasyOCR.  
Nhược điểm: vẫn là OCR tổng quát — khi biển quá nghiêng/mờ có thể chỉ đọc được một phần (ví dụ chỉ ra `AF` thay vì cả biển `29AF-82853`); CER trung bình cao hơn mô hình tự huấn luyện dù LPRA bằng nhau trên tập này.

---

## 4. Thiết lập thực nghiệm trên dữ liệu dự án

### 4.1. Tập dữ liệu thử nghiệm

- **Số ảnh:** 13 ảnh thật lấy từ thư mục upload của hệ thống NDBS (`public/uploads/vehicles`).
- **Loại biển:** chủ yếu biển xe máy Việt Nam **hai dòng**.
- **Ground truth (nhãn đúng):** được gắn **bằng mắt** trực tiếp trên ảnh, **không** lấy từ trường `plate_number` trong cơ sở dữ liệu — vì nhiều bản ghi DB chính là kết quả OCR cũ bị sai (ví dụ ảnh ghi `29K1-02536` nhưng DB từng lưu `29X1-02536`).

Các biển dùng làm nhãn đúng trong thí nghiệm:

| Biển (ground truth) | Số ảnh |
|---------------------|--------|
| 29AF-82853 | 2 |
| 29K1-02536 | 2 |
| 29S6-61468 | 3 |
| 92CA-03484 | 3 |
| 29K2-09456 | 3 |
| **Tổng** | **13** |

### 4.2. Điều kiện công bằng

Để so sánh công bằng:

1. Cả ba phương pháp đều nhận **cùng một crop biển** do `best.pt` cắt ra (không để EasyOCR/PaddleOCR tự tìm biển trên ảnh full).
2. Kết quả được chuẩn hóa về dạng compact (chỉ còn chữ và số) trước khi so khớp.
3. Thời gian suy luận đo trên CPU, đã **warmup** 1 ảnh trước khi tính trung bình (không tính thời gian nạp model lần đầu).

### 4.3. Chỉ số đánh giá

| Chỉ số | Ý nghĩa | Cách tính |
|--------|---------|-----------|
| **LPRA** (License Plate Recognition Accuracy) | Tỷ lệ ảnh nhận đúng **toàn bộ** biển | Số ảnh `compact(pred) = compact(gt)` / tổng ảnh |
| **Tỷ lệ format hợp lệ** | Kết quả có dạng biển VN hợp lệ hay không | Khớp regex biển VN sau chuẩn hóa |
| **CER** (Character Error Rate) | Mức sai lệch từng ký tự (càng thấp càng tốt) | Khoảng cách Levenshtein(pred, gt) / độ dài gt |
| **Latency** | Thời gian xử lý trung bình trên một crop | Trung bình mili-giây (ms) trên CPU |
| **FPS** | Thông lượng suy luận tương đương | `FPS = 1000 / Latency_ms` |

---

## 5. Kết quả thực nghiệm

### 5.1. Bảng tổng hợp

| Chỉ số | EasyOCR | PaddleOCR | Mô hình tự huấn luyện (YOLO) |
|--------|--------:|----------:|-----------------------------:|
| LPRA / Exact Match (đúng toàn biển) | 46,15% (6/13) | 84,62% (11/13) | **84,62% (11/13)** |
| Format biển VN hợp lệ | 46,15% | 84,62% | **100%** |
| CER trung bình | 16,24% | 11,97% | **1,71%** |
| Thời gian TB / crop (CPU) | 888,8 ms | 302,7 ms | **110,5 ms** |
| Thông lượng (FPS) | 1,13 | 3,30 | **9,05** |

### 5.1.1. Exact Match theo loại layout (YOLO)

Phân loại layout theo quan sát ảnh (không lấy từ DB). Script đo: `_em_by_layout.py` → `ocr_exact_match_by_layout.json`.

| Layout | Số ảnh | Exact Match (đúng toàn biển) | Ghi chú |
|--------|-------:|-----------------------------:|---------|
| Biển **một dòng** | 8 | **87,50% (7/8)** | Biển ô tô; chỉ sort trái→phải theo `xc` |
| Biển **hai dòng** | 13 | **84,62% (11/13)** | Biển xe máy; residual-gap tách dòng |
| **Tổng** | 21 | **85,71% (18/21)** | |

Bộ tách layout của thuật toán (một dòng / hai dòng) khớp nhãn quan sát trên các mẫu đã đo. Hai ảnh sai Exact Match ở nhóm hai dòng đều là `29K1-02536` → `29X1-02536` (lỗi phân lớp K/X), **không** phải lỗi sắp xếp dòng. Ở nhóm một dòng, một ảnh sai do nhầm `0`/`O` (`51F-67890` → `51F-6789O`).

### 5.2. Nhận xét định lượng

1. **Về độ chính xác toàn biển (LPRA)**  
   Mô hình tự huấn luyện và PaddleOCR cùng đạt 84,62% (11/13 ảnh đúng). EasyOCR chỉ đạt 46,15% (6/13), thấp hơn rõ rệt.

2. **Về sai lệch ký tự (CER)**  
   Dù LPRA bằng PaddleOCR, mô hình tự huấn luyện có CER chỉ **1,71%**, thấp hơn nhiều so với PaddleOCR (**11,97%**) và EasyOCR (**16,24%**).  
   Nguyên nhân: khi PaddleOCR sai trên biển `29AF-82853`, kết quả chỉ còn `AF` (sai rất nặng); còn khi YOLO sai trên `29K1-02536`, kết quả là `29X1-02536` (chỉ lệch 1 ký tự K→X).

3. **Về tính ổn định format**  
   Mô hình tự huấn luyện luôn trả về chuỗi có dạng biển Việt Nam hợp lệ (100%). EasyOCR và PaddleOCR khi lỗi thường cho ra chuỗi gãy format (`14F0028531`, `AF`, `29AS661468`…).

4. **Về tốc độ**  
   Trên CPU, YOLO nhanh nhất (~110,5 ms/crop ≈ **9,05 FPS**), khoảng **2,7 lần** nhanh hơn PaddleOCR (~3,30 FPS) và **8 lần** nhanh hơn EasyOCR (~1,13 FPS). Điều này quan trọng với hệ thống bãi xe chạy trên máy chủ XAMPP/CPU.

### 5.1.2. Phân rã tốc độ suy luận YOLO (detect / read / E2E)

Ngoài so sánh OCR trên crop (mục 5.1), đề tài đo thêm latency từng giai của pipeline YOLO trên cùng 13 ảnh (CPU, warmup 5 ảnh, mỗi ảnh 3 lần; script `_bench_speed.py` → `ocr_speed_benchmark.json`).

| Giai đoạn | avg (ms) | p50 (ms) | FPS | Ghi chú |
|-----------|---------:|---------:|----:|---------|
| Detect biển (`best.pt`) | 986,2 | 916,0 | 1,01 | Ảnh full (resize max-side 1280, imgsz=640) |
| Read ký tự (`bestRead.pt`) | 186,9 | 187,7 | **5,35** | Trên crop biển |
| E2E = detect + read | 1173,2 | 1090,7 | 0,85 | Pipeline đầy đủ trước fallback |
| EasyOCR (cùng crop) | 1551,0 | 1590,2 | 0,64 | Đối chứng cùng phiên đo |
| PaddleOCR (cùng crop) | 302,7 | 204,9 | 3,30 | Từ `ocr_benchmark_paddle.json` |

Nhận xét: phần nhận dạng ký tự (`bestRead.pt`) vẫn nhanh hơn rõ PaddleOCR/EasyOCR; thời gian E2E bị chi phối chủ yếu bởi bước detect trên ảnh full độ phân giải cao (ví dụ 1080×2400). Trong vận hành thực tế, sau khi model đã nạp sẵn (OCR server), chi phí cold-start không còn; bottleneck còn lại là detect + read trên CPU.

### 5.3. Phân tích lỗi tiêu biểu

| Trường hợp | Ground truth | YOLO | PaddleOCR | EasyOCR | Nhận xét |
|------------|--------------|------|-----------|---------|----------|
| Biển nghiêng, mờ | 29AF-82853 | **Đúng** | AF | 14F0028531 | OCR tổng quát thất bại; YOLO vẫn lắp đủ ký tự |
| Nhầm K/X | 29K1-02536 | 29X1-02536 | **Đúng** | **Đúng** | Lỗi phân lớp ký tự của `bestRead.pt` |
| Ghép dòng | 29S6-61468 | **Đúng** | **Đúng** | 29AS661468 | EasyOCR dễ dính chữ giữa hai dòng |
| Biển rõ, chính diện | 92CA-03484 | **Đúng** | **Đúng** | **Đúng** | Cả ba đều ổn khi điều kiện tốt |
| Biển rõ | 29K2-09456 | **Đúng** | **Đúng** | Đúng 1/3 ảnh | EasyOCR không ổn định giữa các góc chụp |

---

## 6. Kết luận và lựa chọn triển khai

Từ thực nghiệm trên dữ liệu thật của hệ thống NDBS, có thể kết luận:

1. **Mô hình nhận diện ký tự tự huấn luyện (`best.pt` + `bestRead.pt`) là lựa chọn phù hợp nhất** cho hệ thống: cân bằng tốt giữa độ chính xác (LPRA cao, CER thấp), tính ổn định format biển Việt Nam, và tốc độ trên CPU.

2. **PaddleOCR** đạt LPRA ngang YOLO trên tập thử nhưng CER cao hơn và chậm hơn; phù hợp nếu chưa có mô hình chuyên biệt, **không** tối ưu bằng mô hình tự huấn luyện trong ngữ cảnh NDBS.

3. **EasyOCR** có độ chính xác và tốc độ kém nhất trên tập ảnh dự án; trong hệ thống chỉ nên giữ vai trò **fallback** khi đường YOLO chưa đủ tin cậy.

4. Hướng cải thiện tiếp theo cho mô hình tự huấn luyện: bổ sung dữ liệu huấn luyện cho các cặp ký tự dễ nhầm (đặc biệt **K/X**, **0/O**, **8/B**), và tăng cường ảnh biển nghiêng/thiếu sáng để giảm các lỗi còn lại như trường hợp `29K1-02536`.

---

## 7. Ghi chú tái lập thí nghiệm

- Script đo độ chính xác + latency crop: `_bench_ocr_ndbs.py` (YOLO + EasyOCR), `_bench_paddle.py` (PaddleOCR trên cùng crop).
- Script đo tốc độ ms/FPS (detect / read / E2E): `_bench_speed.py`.
- File kết quả: `ocr_benchmark_ndbs.json`, `ocr_speed_benchmark.json`.
- Môi trường: CPU Windows; YOLO/EasyOCR chạy Python 3.14; PaddleOCR chạy venv Python 3.12 (do PaddlePaddle chưa hỗ trợ Python 3.14 tại thời điểm thí nghiệm).
- Công thức thông lượng: `FPS = 1000 / avg_latency_ms`.
