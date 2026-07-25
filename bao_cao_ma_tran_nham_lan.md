# Đánh giá phân lớp ký tự theo 30 lớp (bestRead.pt)

> Đoạn dưới đây viết sẵn để copy vào báo cáo đồ án.  
> Chèn hình từ thư mục `char_eval_plots/`:  
> - Hình phân bố: `class_distribution.png`  
> - Hình ma trận (chuẩn hóa): `confusion_matrix_normalized.png`  
> - (Tuỳ chọn) Ma trận số đếm: `confusion_matrix_counts.png`

---

## 1. Mục tiêu đánh giá

Ngoài việc đo độ chính xác nhận dạng **cả biển** (Exact Match / LPRA) trên ảnh thực tế, đề tài đánh giá thêm khả năng **phân lớp từng ký tự** của mô hình `bestRead.pt`. Mục tiêu là:

- kiểm tra mức độ cân bằng dữ liệu huấn luyện theo từng lớp ký tự;
- xác định các cặp ký tự dễ bị nhầm (ví dụ `1`/`7`, `B`/`8`);
- làm cơ sở đề xuất hướng bổ sung dữ liệu và cải thiện mô hình.

## 2. Tập dữ liệu

Thí nghiệm dùng bộ dữ liệu ký tự biển số Việt Nam (`bo_train_ky_tu`), được chia thành ba tập train / valid / test theo chuẩn YOLO.

Tập nhãn gồm **30 lớp ký tự** thường xuất hiện trên biển số Việt Nam:

- chữ số: `0`, `1`, `2`, `3`, `4`, `5`, `6`, `7`, `8`, `9`;
- chữ cái: `A`, `B`, `C`, `D`, `E`, `F`, `G`, `H`, `K`, `L`, `M`, `N`, `P`, `R`, `S`, `T`, `U`, `V`, `X`, `Z`.

Các ký tự `I`, `J`, `O`, `Q`, `W`, `Y` không được đưa vào tập 30 lớp vì ít dùng trên biển số Việt Nam và dễ gây nhầm với `1` hoặc `0`.

**Bảng 1.** Quy mô tập dữ liệu theo số ảnh và số instance (mỗi instance là một hộp ký tự được gán nhãn).

| Tập dữ liệu | Số ảnh | Số instance |
|-------------|-------:|------------:|
| Train | 311 | 2.637 |
| Valid | 89 | 760 |
| Test | 44 | 371 |
| **Tổng** | **444** | **3.768** |

## 3. Phân bố số mẫu theo lớp

**Hình 1.** Biểu đồ phân bố số mẫu theo lớp trên các tập train, valid và test.  
*(Chèn file: `class_distribution.png`)*

Kết quả cho thấy phân bố dữ liệu **không cân bằng**:

- nhóm chữ số chiếm đa số instance, trong đó lớp `1` và `5` có số mẫu cao nhất (lần lượt 582 và 483 mẫu trên toàn bộ);
- nhiều lớp chữ cái có số mẫu rất thấp (ví dụ `U` chỉ 1 mẫu; `R`, `M`, `T`, `V` chỉ từ 3 đến 4 mẫu).

Sự mất cân bằng này cần được nêu rõ khi đọc chỉ số theo từng lớp: với các lớp ít mẫu, độ tin cậy thống kê thấp hơn so với các lớp chữ số phổ biến.

## 4. Phương pháp xây dựng ma trận nhầm lẫn

Ma trận nhầm lẫn được xây dựng trên tập **valid** với mô hình `bestRead.pt`, theo các bước sau:

1. Chạy suy luận với ngưỡng tin cậy `conf = 0,25` và kích thước ảnh `imgsz = 640`.
2. Ghép mỗi hộp ground truth với hộp dự đoán có độ chồng lấp IoU lớn nhất, với điều kiện **IoU ≥ 0,45**.
3. So sánh lớp theo **tên ký tự** (ví dụ ground truth là `K`, dự đoán là `X`), không so theo chỉ số class id trong file trọng số.
4. Chỉ các cặp hộp đã ghép thành công mới được đưa vào ma trận kích thước 30×30.
5. Ma trận được trình bày ở dạng **chuẩn hóa theo hàng**: mỗi hàng tương ứng một lớp ground truth và tổng các phần tử trên hàng bằng 1. Ô `(i, j)` thể hiện tỷ lệ các mẫu thuộc lớp *i* bị dự đoán thành lớp *j*.

Việc so khớp theo tên ký tự là cần thiết vì `bestRead.pt` được huấn luyện với **36 lớp** (`0–9` và `A–Z`), trong khi bộ dữ liệu đánh giá chỉ có **30 lớp** và thứ tự class id không trùng khớp hoàn toàn. Nếu so theo class id thô, kết quả với các lớp chữ cái sẽ bị sai nghĩa.

**Bảng 2.** Kết quả ghép hộp và phân lớp trên tập valid.

| Chỉ số | Giá trị |
|--------|--------:|
| Số hộp ground truth | 760 |
| Số hộp dự đoán | 746 |
| Số cặp ghép được (IoU ≥ 0,45) | 729 |
| Số cặp dự đoán đúng lớp | 691 |
| Số cặp nhầm lớp | 38 |
| Số hộp ground truth không ghép được | 30 |
| **Độ chính xác trên các cặp đã ghép** | **94,79% (691/729)** |

## 5. Kết quả ma trận nhầm lẫn

**Hình 2.** Ma trận nhầm lẫn chuẩn hóa theo hàng ground truth của mô hình `bestRead.pt` trên tập valid.  
*(Chèn file: `confusion_matrix_normalized.png`)*

Quan sát ma trận cho thấy:

- phần lớn các lớp có tỷ lệ đúng cao trên đường chéo, phù hợp với độ chính xác tổng thể 94,79%;
- lỗi không phân tán đều mà tập trung ở một số cặp ký tự có hình dạng gần nhau.

**Bảng 3.** Các cặp nhầm lẫn xuất hiện nhiều nhất trên tập valid.

| Ground truth | Dự đoán | Số lần | Nhận xét |
|--------------|---------|-------:|----------|
| 1 | 7 | 12 | Cặp nhầm nhiều nhất; hai ký tự dễ giống nhau trên font biển |
| B | 8 | 3 | Nhầm giữa chữ cái và chữ số |
| D | 0 | 2 | Hình dạng gần nhau |
| 9 | 0 | 2 | Nhầm giữa các chữ số |
| 2 | Z | 2 | Nhầm giữa chữ số và chữ cái |

Ngoài ra còn xuất hiện một số lỗi đơn lẻ khác (ví dụ `L`→`Z`, `8`→`B`, `C`→`9`). Các lỗi này nhất quán với hiện tượng quan sát được khi nhận dạng cả biển trên ảnh thực tế của hệ thống, chẳng hạn nhầm `K`/`X` hoặc `0`/`O`.

## 6. Nhận xét và hướng cải thiện

Từ kết quả phân bố mẫu và ma trận nhầm lẫn, có thể rút ra các nhận xét sau:

1. Mô hình `bestRead.pt` phân lớp ký tự tốt trên tập valid, với độ chính xác **94,79%** trên các hộp đã ghép theo IoU.
2. Lỗi còn lại chủ yếu thuộc nhóm ký tự dễ nhầm về hình dạng, đặc biệt là cặp **1/7** và các cặp **chữ–số** như **B/8**, **D/0**.
3. Bộ dữ liệu mất cân bằng giữa các lớp; các lớp chữ cái ít mẫu cần được bổ sung trước khi đưa ra kết luận mạnh về hiệu năng từng lớp riêng lẻ.
4. Hướng cải thiện tiếp theo: tăng cường dữ liệu cho các cặp dễ nhầm (`1`/`7`, `B`/`8`, `D`/`0`, `K`/`X`, `0`/`O`) và cân bằng lại số mẫu giữa chữ số với chữ cái.
