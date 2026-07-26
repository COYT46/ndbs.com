# Đánh giá phân lớp ký tự 30 lớp (kết quả sau huấn luyện)

> Nguồn hình: thư mục Ultralytics sau train  
> `E:\Do An\kytu_8x\detect\train\`  
> (đã copy sang `char_eval_plots/from_train_kytu_8x/`)

---

## 1. Mục tiêu

Sau khi huấn luyện mô hình phát hiện/phân lớp ký tự biển số, đề tài trình bày hai biểu đồ do Ultralytics tự xuất khi kết thúc quá trình train + validate:

1. **Biểu đồ phân bố số mẫu theo lớp** (`labels.jpg`) — kiểm tra mức cân bằng dữ liệu.
2. **Ma trận nhầm lẫn 30 lớp** (`confusion_matrix.png` / `confusion_matrix_normalized.png`) — xem lớp nào dễ đoán đúng, cặp nào dễ nhầm.

## 2. Thiết lập huấn luyện

| Thông số | Giá trị |
|----------|---------|
| Mô hình nền | YOLOv8x (`yolov8x.pt`) |
| Dữ liệu | `bo_train_ky_tu/dataset.yaml` (30 lớp ký tự biển VN) |
| Số epoch | 40 |
| Kích thước ảnh | 640 |
| Batch | 16 |
| Thư mục kết quả | `E:\Do An\kytu_8x\detect\train` |

**30 lớp:** `0–9`, `A`, `B`, `C`, `D`, `E`, `F`, `G`, `H`, `K`, `L`, `M`, `N`, `P`, `R`, `S`, `T`, `U`, `V`, `X`, `Z`  
(không gồm `I`, `J`, `O`, `Q`, `W`, `Y`).

Chỉ số trên tập validate ở epoch cuối (theo `results.csv`):

| Precision | Recall | mAP50 | mAP50-95 |
|----------:|-------:|------:|---------:|
| 80,21% | 92,88% | 94,64% | 56,99% |

## 3. Phân bố số mẫu theo lớp

**Hình 1.** Phân bố số instance theo lớp trong bộ dữ liệu huấn luyện.  
*(Chèn file: `labels.jpg` — khung biểu đồ cột phía trên bên trái)*

Biểu đồ `labels.jpg` do Ultralytics xuất ngay khi bắt đầu train, thống kê nhãn trong dataset. Trục ngang là chỉ số lớp (0→29), trục đứng là số instance.

Nhận xét:

- Phân bố **không cân bằng**: các lớp chữ số (đầu trục) có số mẫu cao hơn rõ rệt so với chữ cái.
- Lớp có nhiều mẫu nhất khoảng trên 400 instance; nhiều lớp chữ cái chỉ vài chục hoặc thấp hơn.
- Ngoài biểu đồ cột, cùng file còn thể hiện phân bố vị trí tâm hộp `(x, y)` và kích thước `(width, height)` — cho thấy ký tự thường nằm theo hai cụm theo chiều cao (phù hợp biển một dòng / hai dòng).

## 4. Ma trận nhầm lẫn 30 lớp

**Hình 2.** Ma trận nhầm lẫn trên tập validate sau khi train.  
*(Khuyến nghị chèn: `confusion_matrix_normalized.png`; nếu cần số đếm tuyệt đối thì dùng `confusion_matrix.png`)*

### Cách đọc hình (Ultralytics)

- Trục ngang (**True**): lớp đúng (ground truth).
- Trục đứng (**Predicted**): lớp mô hình dự đoán.
- Đường chéo đậm = đoán đúng nhiều.
- Có thêm hàng/cột **background**: trường hợp detection không khớp được với nhãn (false positive / false negative kiểu phát hiện hộp), không phải một lớp ký tự thật.

Ma trận chuẩn hóa (`confusion_matrix_normalized.png`) chia theo cột True nên dễ so sánh tỷ lệ đúng/sai giữa các lớp, kể cả lớp ít mẫu.

### Nhận xét từ ma trận

1. **Đường chéo rõ** với hầu hết chữ số `0–9` và nhiều chữ cái phổ biến → mô hình phân lớp tốt trên tập validate.
2. Lỗi chủ yếu xuất hiện dạng **ô lệch đường chéo** giữa các ký tự gần hình dạng (ví dụ nhóm chữ–số hoặc cặp chữ cái dễ giống nhau).
3. Một số lớp chữ cái ít mẫu vẫn có tín hiệu trên đường chéo nhưng cần thận trọng khi kết luận vì support thấp.
4. Hàng/cột `background` cho thấy vẫn còn một phần hộp bị bỏ sót hoặc dự đoán thừa — đặc trưng của bài toán detection, không chỉ classification thuần.

## 5. Kết luận ngắn

- Hai hình trong thư mục train đã đủ đáp ứng yêu cầu: **biểu đồ phân bố theo lớp** và **ma trận nhầm lẫn 30 lớp**.
- Dữ liệu lệch về chữ số; cần bổ sung mẫu chữ cái và các cặp dễ nhầm nếu muốn cải thiện thêm.
- Nên đưa vào báo cáo bản **chuẩn hóa** (`confusion_matrix_normalized.png`) kèm chú thích trục True / Predicted / background như trên.
