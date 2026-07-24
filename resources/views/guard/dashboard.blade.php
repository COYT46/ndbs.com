@extends('layouts.app')

@section('title', 'Giám sát Xe ra vào')

@section('content')
<div class="container-fluid">
    <!-- 1 Row chia thành 3 cột 4 - 4 - 4 -->
    <div class="row g-4 mb-4">
        <!-- Cột 1 (col-4): Camera Xe Vào -->
        <div class="col-12 col-xl-4">
            <div class="card h-100 border-primary shadow-sm">
                <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">login</i> Camera Xe Vào (Check-In)</h5>
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-between p-3">
                    <div>
                        <!-- Preview Box -->
                        <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded overflow-hidden"
                            style="height: 260px; border: 2px dashed #0d6efd; position: relative;">
                            <img id="entry-camera" src=""
                                alt="Camera Xe Vào" style="max-height: 100%; max-width: 100%; object-fit: contain; display: none;">
                            <div id="entry-placeholder" class="text-muted text-center p-3">
                                <i class="material-icons-outlined text-primary" style="font-size: 54px;">add_a_photo</i>
                                <h6 class="mt-2 text-dark fw-bold small">Chọn ảnh xe vào</h6>
                            </div>
                        </div>

                        <!-- Input File -->
                        <div class="mb-3">
                            <input type="file" id="entry-file" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div>
                        <button id="btn-entry-recognize" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                            <i class="material-icons-outlined align-middle me-1">search</i> Nhận diện
                        </button>

                        <div id="entry-result" class="mt-3" style="display: none;">
                            <div class="alert alert-success border-0 shadow-sm mb-0 p-2">
                                <h6 class="alert-heading fw-bold mb-1"><i class="material-icons-outlined align-middle">check_circle</i> Nhận diện thành công!</h6>
                                <div class="fs-6 mb-1">Biển số: <strong id="res-plate" class="text-danger"></strong></div>
                                <div class="fs-6 fw-bold">Mã Code: <strong id="res-code" class="text-primary badge bg-light border text-primary px-2 py-1"></strong></div>
                                <small class="text-muted mt-1 d-block" style="font-size: 11px;">Tự động làm mới sau <span id="entry-timer" class="fw-bold">10</span> giây...</small>
                            </div>
                        </div>
                        <div id="entry-error" class="mt-3" style="display: none;">
                            <div class="alert alert-danger mb-0 p-2 small" id="entry-error-msg"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột 2 (col-4): Camera Xe Ra -->
        <div class="col-12 col-xl-4">
            <div class="card h-100 border-danger shadow-sm">
                <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">logout</i> Camera Xe Ra (Check-Out)</h5>
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-between p-3">
                    <div>
                        <!-- Preview Box -->
                        <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded overflow-hidden"
                            style="height: 260px; border: 2px dashed #dc3545; position: relative;">
                            <img id="exit-camera" src=""
                                alt="Camera Xe Ra" style="max-height: 100%; max-width: 100%; object-fit: contain; display: none;">
                            <div id="exit-placeholder" class="text-muted text-center p-3">
                                <i class="material-icons-outlined text-danger" style="font-size: 54px;">add_a_photo</i>
                                <h6 class="mt-2 text-dark fw-bold small">Chọn ảnh xe ra</h6>
                            </div>
                        </div>

                        <!-- Input File -->
                        <div class="mb-2">
                            <input type="file" id="exit-file" class="form-control" accept="image/*">
                        </div>

                        <!-- Input Code -->
                        <div class="input-group mb-3 shadow-sm">
                            <span class="input-group-text bg-light text-danger fw-bold small"><i class="material-icons-outlined me-1 fs-6">qr_code</i> Mã Code</span>
                            <input type="text" id="exit-code" class="form-control text-uppercase fw-bold" placeholder="VD: AB1234" maxlength="6">
                        </div>
                    </div>

                    <div>
                        <button id="btn-exit-recognize" class="btn btn-danger w-100 fw-bold shadow-sm py-2" type="button">
                            <i class="material-icons-outlined align-middle me-1">fact_check</i> Nhận diện
                        </button>

                        <div id="exit-result" class="mt-3" style="display: none;">
                            <div class="alert mb-0 shadow-sm p-2" id="exit-alert-box">
                                <h6 class="mb-0 fw-bold fs-6" id="exit-message"></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột 3 (col-4): Ảnh Xe Vào & Xe Ra chồng lên nhau -->
        <div class="col-12 col-xl-4">
            <div class="card border-dark shadow-sm" id="comparison-section">
                <div class="card-header bg-dark text-white p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">compare</i> Đối chiếu Hình ảnh</h5>
                    </div>
                    <span class="badge bg-warning text-dark w-100 py-2 d-block text-truncate" id="comp-status-badge">Đang chờ nhận diện xe ra...</span>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex flex-column gap-3">
                        <!-- Khung Ảnh Xe Vào -->
                        <div class="border rounded p-2 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary px-2 py-1">Ảnh Xe Vào</span>
                                <span class="small fw-semibold">BSX: <strong id="comp-entry-plate" class="text-primary fs-6">-</strong></span>
                            </div>
                            <div class="bg-white rounded d-flex align-items-center justify-content-center border overflow-hidden" style="height: 140px;">
                                <img id="comp-entry-img" src="" alt="Ảnh xe vào" style="max-height: 100%; max-width: 100%; object-fit: contain; display: none;">
                                <span id="comp-entry-empty" class="text-muted text-center" style="font-size: 12px;"><i class="material-icons-outlined fs-3">image_not_supported</i><br>Chưa có ảnh vào</span>
                            </div>
                        </div>

                        <!-- Khung Ảnh Xe Ra -->
                        <div class="border rounded p-2 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-danger px-2 py-1">Ảnh Xe Ra</span>
                                <span class="small fw-semibold">BSX: <strong id="comp-exit-plate" class="text-danger fs-6">-</strong></span>
                            </div>
                            <div class="bg-white rounded d-flex align-items-center justify-content-center border overflow-hidden" style="height: 140px;">
                                <img id="comp-exit-img" src="" alt="Ảnh xe ra" style="max-height: 100%; max-width: 100%; object-fit: contain; display: none;">
                                <span id="comp-exit-empty" class="text-muted text-center" style="font-size: 12px;"><i class="material-icons-outlined fs-3">image_not_supported</i><br>Chưa có ảnh ra</span>
                            </div>
                        </div>
                    </div>

                    <!-- Nút dưới ảnh đối chiếu — hiện cả khi khớp lẫn không khớp -->
                    <div class="mt-3 pt-3 border-top" id="validation-buttons" style="display: none;">
                        <p class="text-center text-muted mb-2 fw-semibold" style="font-size: 13px;">Đối chiếu bằng mắt rồi chọn:</p>
                        <div class="d-flex gap-2">
                            <button type="button" id="btn-valid" class="btn btn-success flex-fill fw-bold shadow-sm py-2">
                                <i class="material-icons-outlined align-middle me-1">check_circle</i> Hợp lệ
                            </button>
                            <button type="button" id="btn-invalid" class="btn btn-outline-danger flex-fill fw-bold shadow-sm py-2">
                                <i class="material-icons-outlined align-middle me-1">cancel</i> Không hợp lệ
                            </button>
                        </div>
                        <small class="text-muted d-block text-center mt-2" style="font-size: 11px;">
                            Hợp lệ → “Xe vào đã ra”. Không hợp lệ → giữ “Xe vào chưa ra”.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal xác nhận thao tác Hợp lệ / Không hợp lệ -->
    <div class="modal fade" id="confirmValidationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white" id="confirmModalHeader">
                    <h5 class="modal-title fw-bold" id="confirmModalTitle"><i class="material-icons-outlined align-middle me-1">help_outline</i> Xác nhận thao tác</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div id="confirmModalIcon" class="mb-3"></div>
                    <h5 class="fw-bold mb-2" id="confirmModalQuestion"></h5>
                    <p class="text-muted mb-0 fs-6" id="confirmModalSubtext">Vui lòng kiểm tra kỹ hình ảnh và biển số xe trước khi xác nhận.</p>
                </div>
                <div class="modal-footer justify-content-center bg-light">
                    <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="button" class="btn px-4 fw-bold text-white shadow" id="confirmModalSubmitBtn">Xác nhận</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Thông báo Kết quả (Hợp lệ / Không hợp lệ) -->
    <div class="modal fade" id="statusNotificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-center">
                <div class="modal-body p-5">
                    <div id="statusModalIcon" class="mb-3"></div>
                    <h4 class="fw-bold mb-3" id="statusModalTitle"></h4>
                    <p class="fs-5 text-secondary mb-4" id="statusModalMessage"></p>
                    <button type="button" class="btn btn-primary btn-lg px-5 fw-bold shadow" data-bs-dismiss="modal">Đồng ý</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
$(document).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let entryTimerInterval = null;
    let exitTimerInterval = null;
    let currentLogId = null;

    function fetchRecentLogs() {
        fetch('{{ route("api.recent_logs") }}')
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    $('#count-pending').text(res.pending.length);
                    $('#count-completed').text(res.completed.length);

                    let pendingHtml = '';
                    if (res.pending.length === 0) {
                        pendingHtml = '<tr><td colspan="5" class="text-center text-muted py-3">Không có phương tiện nào đang trong bãi</td></tr>';
                    } else {
                        res.pending.forEach(function(item, idx) {
                            pendingHtml += `<tr>
                                <td class="text-center">${idx + 1}</td>
                                <td><span class="badge bg-primary fs-6">${item.code}</span></td>
                                <td><strong class="text-danger fs-6">${item.plate_number}</strong></td>
                                <td>${item.entry_time}</td>
                                <td>${item.guard_in}</td>
                            </tr>`;
                        });
                    }
                    $('#tbody-pending').html(pendingHtml);

                    let completedHtml = '';
                    if (res.completed.length === 0) {
                        completedHtml = '<tr><td colspan="6" class="text-center text-muted py-3">Chưa có lượt xe rời bãi nào</td></tr>';
                    } else {
                        res.completed.forEach(function(item, idx) {
                            completedHtml += `<tr>
                                <td class="text-center">${idx + 1}</td>
                                <td><span class="badge bg-success fs-6">${item.code}</span></td>
                                <td><strong class="text-danger">${item.plate_number}</strong></td>
                                <td><strong class="text-success">${item.exit_plate_number || '-'}</strong></td>
                                <td>${item.entry_time}</td>
                                <td>${item.exit_time}</td>
                            </tr>`;
                        });
                    }
                    $('#tbody-completed').html(completedHtml);
                }
            })
            .catch(err => console.error('Lỗi fetch recent logs:', err));
    }

    fetchRecentLogs();

    // Reset Camera Xe Vào về default
    function resetEntryCamera() {
        if (entryTimerInterval) clearInterval(entryTimerInterval);
        $('#entry-file').val('');
        $('#entry-camera').hide().attr('src', '');
        $('#entry-placeholder').show();
        $('#entry-result, #entry-error').fadeOut();
    }

    // Reset Camera Xe Ra và 2 Khung đối chiếu về default
    function resetExitAndComparison() {
        if (exitTimerInterval) clearInterval(exitTimerInterval);
        exitTimerInterval = null;
        $('#exit-file, #exit-code, #btn-exit-recognize').prop('disabled', false);
        $('#exit-file').val('');
        $('#exit-code').val('');
        $('#exit-camera').hide().attr('src', '');
        $('#exit-placeholder').show();
        $('#exit-result').hide();

        // Reset Khung đối chiếu
        $('#comp-entry-img, #comp-exit-img').hide().attr('src', '');
        $('#comp-entry-empty, #comp-exit-empty').show();
        $('#comp-entry-plate, #comp-exit-plate').text('-');
        $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark').text('Đang chờ nhận diện xe ra...');
        $('#validation-buttons').hide();
        currentLogId = null;
    }

    // Preview ảnh Xe Vào
    $('#entry-file').change(function() {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $('#entry-placeholder').hide();
                $('#entry-camera').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(file);
        } else {
            $('#entry-camera').hide();
            $('#entry-placeholder').show();
        }
    });

    // Preview ảnh Xe Ra
    $('#exit-file').change(function() {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $('#exit-placeholder').hide();
                $('#exit-camera').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(file);
        } else {
            $('#exit-camera').hide();
            $('#exit-placeholder').show();
        }
    });

    // Xử lý nút Nhận diện (Camera Xe Vào)
    $('#btn-entry-recognize').click(function() {
        let fileInput = $('#entry-file')[0];
        if (fileInput.files.length === 0) {
            showNotificationModal(false, 'Chưa Chọn Ảnh', 'Vui lòng chọn ảnh xe vào từ máy tính trước!');
            return;
        }

        let formData = new FormData();
        formData.append('image', fileInput.files[0]);

        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Đang nhận diện AI...');
        $('#entry-result, #entry-error').hide();
        if (entryTimerInterval) clearInterval(entryTimerInterval);

        $.ajax({
            url: '{{ route("api.recognize_entry") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#res-plate').text(response.plate_number);
                    $('#res-code').text(response.code);
                    $('#entry-result').fadeIn();
                    fetchRecentLogs();

                    // Đếm ngược 10s
                    let seconds = 10;
                    $('#entry-timer').text(seconds);
                    entryTimerInterval = setInterval(function() {
                        seconds--;
                        $('#entry-timer').text(seconds);
                        if (seconds <= 0) {
                            clearInterval(entryTimerInterval);
                            resetEntryCamera();
                        }
                    }, 1000);
                } else {
                    $('#entry-error-msg').text(response.message || 'Có lỗi xảy ra!');
                    $('#entry-error').fadeIn();
                }
            },
            error: function(xhr) {
                let msg = 'Lỗi kết nối máy chủ (HTTP ' + xhr.status + ').';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg += '\nChi tiết: ' + xhr.responseJSON.message;
                } else if (xhr.status === 500) {
                    msg += '\nVui lòng kiểm tra log lỗi PHP hoặc đảm bảo lệnh Python và thư viện đã sẵn sàng.';
                }
                showNotificationModal(false, 'Lỗi Kết Nối Máy Chủ', msg);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="material-icons-outlined align-middle me-1">search</i> Nhận diện');
            }
        });
    });

    // Xử lý nút Nhận diện (Camera Xe Ra)
    $('#btn-exit-recognize').click(function() {
        let fileInput = $('#exit-file')[0];
        let codeVal = $('#exit-code').val().trim();

        // Kiểm tra đủ 2 điều kiện
        let missing = [];
        if (fileInput.files.length === 0) missing.push('Ảnh tải lên xe ra');
        if (!codeVal) missing.push('Mã code xe vào');

        if (missing.length > 0) {
            showNotificationModal(false, 'Chưa Đủ Điều Kiện', 'Bạn chưa đủ 2 điều kiện để nhận diện xe ra:\n- Thiếu: ' + missing.join(', '));
            return;
        }

        if (codeVal.length !== 6) {
            showNotificationModal(false, 'Mã Code Không Hợp Lệ', 'Mã code phải có đúng 6 ký tự!');
            return;
        }

        if (exitTimerInterval) clearInterval(exitTimerInterval);

        let formData = new FormData();
        formData.append('image', fileInput.files[0]);
        formData.append('code', codeVal);

        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Đang đối chiếu AI...');

        $.ajax({
            url: '{{ route("api.checkout_exit") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let alertBox = $('#exit-alert-box');

                if (response.success) {
                    currentLogId = response.log_id;

                    // Hiển thị khung ảnh xe vào & xe ra để bảo vệ đối chiếu
                    if (response.entry_image) {
                        $('#comp-entry-empty').hide();
                        $('#comp-entry-img').attr('src', response.entry_image).show();
                    }
                    $('#comp-entry-plate').text(response.entry_plate || '-');

                    if (response.exit_image) {
                        $('#comp-exit-empty').hide();
                        $('#comp-exit-img').attr('src', response.exit_image).show();
                    }
                    $('#comp-exit-plate').text(response.exit_plate || '-');

                    // Khóa form; hiện kết quả + nút Hợp lệ / Không hợp lệ (cả khi khớp lẫn khi lệch)
                    $('#exit-file, #exit-code, #btn-exit-recognize').prop('disabled', true);

                    if (response.match === true || response.match === 1 || response.match === 'true') {
                        alertBox.removeClass('alert-danger alert-info').addClass('alert-success');
                        $('#exit-message').html('<i class="material-icons-outlined align-middle me-1">check_circle</i> ' + response.message);
                        $('#comp-status-badge').removeClass('bg-warning bg-danger text-dark').addClass('bg-success text-white').text('BSX trùng khớp — xác nhận cho ra?');
                    } else {
                        alertBox.removeClass('alert-success alert-info').addClass('alert-danger');
                        $('#exit-message').html('<i class="material-icons-outlined align-middle me-1">warning</i> ' + response.message);
                        $('#comp-status-badge').removeClass('bg-warning bg-success text-dark').addClass('bg-danger text-white').text('BSX không trùng — vẫn cần xác nhận');
                    }

                    // Hiện kết quả + nút Hợp lệ/Không hợp lệ dưới khung đối chiếu
                    $('#exit-result').show();
                    $('#validation-buttons').show();
                } else {
                    alertBox.removeClass('alert-success alert-info').addClass('alert-danger');
                    $('#exit-message').text(response.message || 'Có lỗi xảy ra');
                    $('#exit-result').show();
                    $('#validation-buttons').hide();
                }
            },
            error: function(xhr) {
                let msg = 'Lỗi kết nối máy chủ (HTTP ' + xhr.status + ').';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg += '\nChi tiết: ' + xhr.responseJSON.message;
                } else if (xhr.status === 500) {
                    msg += '\nVui lòng kiểm tra log lỗi PHP hoặc đảm bảo lệnh Python và thư viện đã sẵn sàng.';
                }
                showNotificationModal(false, 'Lỗi Kết Nối Máy Chủ', msg);
            },
            complete: function() {
                btn.html('<i class="material-icons-outlined align-middle me-1">fact_check</i> Nhận diện');
                if (!$('#exit-file').prop('disabled')) {
                    btn.prop('disabled', false);
                }
            }
        });
    });

    function showNotificationModal(isSuccess, title, message) {
        if (isSuccess) {
            $('#statusModalIcon').html('<i class="material-icons-outlined text-success" style="font-size: 80px;">check_circle</i>');
            $('#statusModalTitle').removeClass('text-danger').addClass('text-success').text(title);
        } else {
            $('#statusModalIcon').html('<i class="material-icons-outlined text-danger" style="font-size: 80px;">cancel</i>');
            $('#statusModalTitle').removeClass('text-success').addClass('text-danger').text(title);
        }
        $('#statusModalMessage').text(message);
        let modal = new bootstrap.Modal(document.getElementById('statusNotificationModal'));
        modal.show();
    }

    let pendingValidationAction = null;
    let confirmModalObj = new bootstrap.Modal(document.getElementById('confirmValidationModal'));

    // Nút Hợp lệ
    $('#btn-valid').click(function() {
        if (!currentLogId) return;
        pendingValidationAction = true;
        $('#confirmModalHeader').removeClass('bg-danger').addClass('bg-success');
        $('#confirmModalTitle').html('<span class="text-white"><i class="material-icons-outlined align-middle me-1">check_circle</i> Xác nhận Hợp Lệ</span>');
        $('#confirmModalIcon').html('<i class="material-icons-outlined text-success" style="font-size: 70px;">check_circle_outline</i>');
        $('#confirmModalQuestion').text('Xác nhận phương tiện HỢP LỆ và cho phép ra?');
        $('#confirmModalSubmitBtn').removeClass('btn-danger').addClass('btn-success').text('Đồng ý Cho Ra');
        confirmModalObj.show();
    });

    // Nút Không hợp lệ
    $('#btn-invalid').click(function() {
        if (!currentLogId) return;
        pendingValidationAction = false;
        $('#confirmModalHeader').removeClass('bg-success').addClass('bg-danger');
        $('#confirmModalTitle').html('<span class="text-white"><i class="material-icons-outlined align-middle me-1">warning</i> Xác nhận Không Hợp Lệ</span>');
        $('#confirmModalIcon').html('<i class="material-icons-outlined text-danger" style="font-size: 70px;">gpp_bad</i>');
        $('#confirmModalQuestion').text('Xác nhận phương tiện KHÔNG HỢP LỆ (Từ chối cho ra)?');
        $('#confirmModalSubmitBtn').removeClass('btn-success').addClass('btn-danger').text('Xác Nhận Từ Chối');
        confirmModalObj.show();
    });

    // Xử lý khi bấm nút Xác nhận trong Modal
    $('#confirmModalSubmitBtn').click(function() {
        if (pendingValidationAction === null || !currentLogId) return;
        let isValid = pendingValidationAction;
        let btnSubmit = $(this);
        btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...');

        $.post('{{ route("api.validate_checkout") }}', { log_id: currentLogId, is_valid: isValid }, function(res) {
            confirmModalObj.hide();
            if (res.success) {
                showNotificationModal(
                    isValid,
                    isValid ? 'Đã Cho Phép Xe Ra!' : 'Đã Từ Chối Phương Tiện!',
                    res.message
                );
                resetExitAndComparison();
                fetchRecentLogs();
            } else {
                showNotificationModal(false, 'Thao Tác Thất Bại', res.message);
            }
        }).always(() => {
            btnSubmit.prop('disabled', false).text(isValid ? 'Đồng ý Cho Ra' : 'Xác Nhận Từ Chối');
        });
    });
});
</script>
@endpush
