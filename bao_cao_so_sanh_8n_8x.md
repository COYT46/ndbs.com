# So sánh YOLOv8n và YOLOv8x cho phát hiện biển số và nhận diện ký tự

> Đoạn viết sẵn để đưa vào báo cáo.  
> Nguồn: `results.csv` của bốn lần huấn luyện tại `E:\Do An\`  
> (`bienso_8n`, `bienso_8x`, `kytu_8n`, `kytu_8x`).  
> Chi tiết số: `so_sanh_8n_8x.json`.

---

## 1. Mục tiêu và nguyên tắc lấy số liệu

Đề tài so sánh hai biến thể YOLOv8:

| Bài toán | Biến thể nhỏ | Biến thể lớn |
|----------|--------------|--------------|
| Phát hiện biển số | YOLOv8n (`bienso_8n`) | YOLOv8x (`bienso_8x`) |
| Nhận diện ký tự | YOLOv8n (`kytu_8n`) | YOLOv8x (`kytu_8x`) |

**Nguyên tắc trình bày (bắt buộc):** với mỗi lần huấn luyện, **toàn bộ chỉ số** Precision, Recall, mAP@0,5 và mAP@0,5:0,95 được lấy tại **cùng một epoch** — là epoch có *fitness* cao nhất trên tập validation (đúng tiêu chí Ultralytics lưu file `best.pt`):

\[
\text{fitness} = 0{,}1 \times \text{mAP@0,5} + 0{,}9 \times \text{mAP@0,5:0,95}
\]

Không lấy max từng chỉ số ở các epoch khác nhau.

Điều kiện huấn luyện chung (theo `args.yaml`): 40 epoch, ảnh 640×640, batch 16.  
Hai bài toán dùng **hai bộ dữ liệu khác nhau** (biển số / ký tự), nên chỉ so sánh **trong cùng bài toán** (8n với 8x), không so trực tiếp số tuyệt đối giữa bài biển và bài ký tự.

---

## 2. Phát hiện biển số — YOLOv8n so với YOLOv8x

- Dữ liệu: cùng `dataset.yaml` (bộ biển số).  
- Checkpoint tốt nhất của cả hai biến thể đều tại **epoch 40/40**.

**Bảng 2.** Kết quả validation tại checkpoint `best.pt` (phát hiện biển số)

| Biến thể | Checkpoint | Epoch | Precision | Recall | mAP@0,5 | mAP@0,5:0,95 | Fitness |
|----------|------------|------:|----------:|-------:|--------:|-------------:|--------:|
| YOLOv8n | `bienso_8n/.../best.pt` | 40/40 | 98,34% | 98,97% | 99,38% | 89,97% | 0,9091 |
| YOLOv8x | `bienso_8x/.../best.pt` | 40/40 | 98,24% | 98,69% | 99,38% | 89,69% | 0,9066 |

**Nhận xét:** Trên bài phát hiện biển số, hai biến thể đạt mAP@0,5 gần như bằng nhau (99,38%). YOLOv8n nhỉnh hơn nhẹ về Precision, Recall và mAP@0,5:0,95 (fitness 0,9091 so với 0,9066). Trong điều kiện dữ liệu và cấu hình này, biến thể nhỏ đã đủ tốt cho giai đoạn định vị biển; YOLOv8x không mang lại lợi thế rõ về độ chính xác validation.

---

## 3. Nhận diện ký tự — YOLOv8n so với YOLOv8x

- Dữ liệu: cùng `bo_train_ky_tu/dataset.yaml` (30 lớp ký tự biển số).  
- Checkpoint tốt nhất: YOLOv8n tại **epoch 39/40**; YOLOv8x tại **epoch 26/40**.

**Bảng 3.** Kết quả validation tại checkpoint `best.pt` (nhận diện ký tự)

| Biến thể | Checkpoint | Epoch | Precision | Recall | mAP@0,5 | mAP@0,5:0,95 | Fitness |
|----------|------------|------:|----------:|-------:|--------:|-------------:|--------:|
| YOLOv8n | `kytu_8n/.../best.pt` | 39/40 | 82,68% | 70,92% | 73,28% | 45,51% | 0,4828 |
| YOLOv8x | `kytu_8x/.../best.pt` | 26/40 | 89,29% | 82,60% | 95,22% | 58,92% | 0,6255 |

**Nhận xét:** Trên bài nhận diện ký tự, YOLOv8x vượt rõ YOLOv8n ở mọi chỉ số chính (mAP@0,5 tăng từ 73,28% lên 95,22%; mAP@0,5:0,95 từ 45,51% lên 58,92%). Epoch tốt nhất của YOLOv8x là 26/40 (không phải epoch cuối), nên các chỉ số báo cáo gắn với epoch 26, không lấy hàng epoch 40.

---

## 4. Bảng tổng hợp đưa vào báo cáo

**Bảng 4.** Tổng hợp so sánh YOLOv8n và YOLOv8x theo từng bài toán  
*(mỗi dòng: mọi chỉ số cùng một epoch của `best.pt`)*

| Bài toán | Biến thể | Epoch của best.pt | Precision | Recall | mAP@0,5 | mAP@0,5:0,95 |
|----------|----------|------------------:|----------:|-------:|--------:|-------------:|
| Phát hiện biển số | YOLOv8n | 40/40 | 98,34% | 98,97% | 99,38% | 89,97% |
| Phát hiện biển số | YOLOv8x | 40/40 | 98,24% | 98,69% | 99,38% | 89,69% |
| Nhận diện ký tự | YOLOv8n | 39/40 | 82,68% | 70,92% | 73,28% | 45,51% |
| Nhận diện ký tự | YOLOv8x | 26/40 | 89,29% | 82,60% | 95,22% | 58,92% |

---

## 5. Kết luận lựa chọn mô hình

1. **Phát hiện biển số:** YOLOv8n và YOLOv8x tương đương về độ chính xác validation; có thể ưu tiên **YOLOv8n** nếu cần mô hình nhẹ, hoặc giữ **YOLOv8x** nếu hệ thống đã triển khai ổn định với biến thể này.  
2. **Nhận diện ký tự:** nên chọn **YOLOv8x** vì vượt trội rõ so với YOLOv8n trên cùng bộ dữ liệu ký tự.  
3. Khi trình bày số liệu, luôn ghi kèm **epoch của checkpoint `best.pt`** để tránh hiểu nhầm với chỉ số ở epoch cuối hoặc chỉ số “max theo từng metric”.

---

## Phụ lục — đường dẫn nguồn

| Run | Model | Thư mục kết quả |
|-----|-------|-----------------|
| `bienso_8n` | `yolov8n.pt` | `E:\Do An\bienso_8n\detect\train` |
| `bienso_8x` | `yolov8x.pt` | `E:\Do An\bienso_8x\detect\train` |
| `kytu_8n` | `yolov8n.pt` | `E:\Do An\kytu_8n\detect\train` |
| `kytu_8x` | `yolov8x.pt` | `E:\Do An\kytu_8x\detect\train` |
