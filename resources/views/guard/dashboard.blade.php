@extends('layouts.app')

@section('title', 'Giám sát Xe ra vào')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
        <span class="badge bg-dark" id="device-role-badge">Máy tính giám sát</span>
    </div>

    <div class="row g-4 mb-4">
        <!-- Cot 1: Camera Xe Vao -->
        <div class="col-12 col-xl-4">
            <div class="card h-100 border-primary shadow-sm">
                <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">login</i> Camera Xe Vào (Check-In)</h5>
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-between p-3">
                    <div>
                        <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded overflow-hidden"
                            style="height: 260px; border: 2px dashed #0d6efd; position: relative;">
                            <img id="entry-camera" src="" alt="Camera Xe Vào"
                                style="max-height: 100%; max-width: 100%; object-fit: contain; display: none;">
                            <div id="entry-live-badge" class="position-absolute top-0 start-0 m-2 badge bg-danger"
                                style="display: none; z-index: 2; font-size: 11px;">
                                <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span>
                                LIVE ĐT
                            </div>
                            <div id="entry-placeholder" class="text-muted text-center p-3">
                                <i class="material-icons-outlined text-primary" style="font-size: 54px;">add_a_photo</i>
                                <h6 class="mt-2 text-dark fw-bold small" id="entry-placeholder-text">Chờ ĐT quét xe vào</h6>
                            </div>
                        </div>

                        <div class="mb-3" id="entry-controls">
                            <input type="file" id="entry-file" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div>
                        <button id="btn-entry-recognize" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                            <i class="material-icons-outlined align-middle me-1">photo_camera</i> <span id="btn-entry-recognize-label">Nhận diện</span>
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
                            <div class="alert alert-danger border-danger shadow-sm mb-0 p-3">
                                <h6 class="alert-heading fw-bold mb-1 text-danger">
                                    <i class="material-icons-outlined align-middle">warning</i>
                                    <span id="entry-error-title">Cảnh báo</span>
                                </h6>
                                <div class="fs-6 fw-semibold" id="entry-error-msg"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cot 2: Camera Xe Ra -->
        <div class="col-12 col-xl-4">
            <div class="card h-100 border-danger shadow-sm">
                <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">logout</i> Camera Xe Ra (Check-Out)</h5>
                </div>
                <div class="card-body text-center d-flex flex-column justify-content-between p-3">
                    <div>
                        <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded overflow-hidden"
                            style="height: 260px; border: 2px dashed #dc3545; position: relative;">
                            <img id="exit-camera" src="" alt="Camera Xe Ra"
                                style="max-height: 100%; max-width: 100%; object-fit: contain; display: none;">
                            <div id="exit-live-badge" class="position-absolute top-0 start-0 m-2 badge bg-danger"
                                style="display: none; z-index: 2; font-size: 11px;">
                                <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span>
                                LIVE ĐT
                            </div>
                            <div id="exit-placeholder" class="text-muted text-center p-3">
                                <i class="material-icons-outlined text-danger" style="font-size: 54px;">add_a_photo</i>
                                <h6 class="mt-2 text-dark fw-bold small" id="exit-placeholder-text">Chờ ĐT quét xe ra</h6>
                            </div>
                        </div>

                        <div class="mb-2" id="exit-controls">
                            <input type="file" id="exit-file" class="form-control mb-2" accept="image/*">
                        </div>

                        <div class="input-group mb-2 shadow-sm">
                            <span class="input-group-text bg-light text-danger fw-bold small"><i class="material-icons-outlined me-1 fs-6">qr_code</i> Mã Code</span>
                            <input type="text" id="exit-code" class="form-control text-uppercase fw-bold" placeholder="Nhập mã" maxlength="6">
                        </div>
                        <small id="exit-code-arm-hint" class="text-muted d-block mb-2" style="font-size: 13px;">
                            Nhập mã 6 ký tự
                        </small>
                        
                    </div>

                    <div>
                        <button id="btn-exit-recognize" class="btn btn-danger w-100 fw-bold shadow-sm py-2" type="button">
                            <i class="material-icons-outlined align-middle me-1">photo_camera</i> <span id="btn-exit-recognize-label">Nhận diện</span>
                        </button>
                        <div id="exit-result" class="mt-3" style="display: none;">
                            <div class="alert mb-0 shadow-sm p-2" id="exit-alert-box">
                                <h6 class="mb-0 fw-bold fs-6" id="exit-message"></h6>
                                <small id="exit-auto-timer-wrap" class="text-muted mt-1 d-none" style="font-size: 11px;">
                                    Tự động cho ra sau <span id="exit-auto-timer" class="fw-bold">10</span> giây...
                                </small>
                                <button type="button" id="btn-exit-retry-inline" class="btn btn-sm btn-outline-danger fw-bold mt-2" style="display: none;">
                                    <i class="material-icons-outlined align-middle me-1" style="font-size: 16px;">refresh</i> Làm lại
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cot 3: Doi chieu -->
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
                    <div class="mt-3 pt-3 border-top" id="validation-buttons" style="display: none;">
                        <p class="text-center text-muted mb-2 fw-semibold" style="font-size: 13px;">Đối chiếu ảnh:</p>
                        <div class="d-flex gap-2">
                            <button type="button" id="btn-valid" class="btn btn-success flex-fill fw-bold shadow-sm py-2">
                                <i class="material-icons-outlined align-middle me-1">check_circle</i> Hợp lệ
                            </button>
                            <button type="button" id="btn-invalid" class="btn btn-outline-danger flex-fill fw-bold shadow-sm py-2">
                                <i class="material-icons-outlined align-middle me-1">cancel</i> Không hợp lệ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    const API = {
        monitor: @json(route('api.guard_monitor', [], false)),
        armExit: @json(route('api.arm_exit_code', [], false)),
        clearExit: @json(route('api.clear_exit_code', [], false)),
        retryExit: @json(route('api.retry_exit', [], false)),
        entry: @json(route('api.recognize_entry', [], false)),
        exit: @json(route('api.checkout_exit', [], false)),
        validate: @json(route('api.validate_checkout', [], false))
    };

    const MONITOR_POLL_MS = 2000;
    const isPhone = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
        || (navigator.maxTouchPoints > 1 && window.innerWidth < 900);

    let entryTimerInterval = null;
    let exitTimerInterval = null;
    let autoExitInProgress = false;
    let currentLogId = null;
    let lastSeenEntryId = null;
    let lastSeenPendingId = null;
    let lastSeenEntryAlertId = null;
    let monitorBootstrapped = false;
    let lastLiveEntryTs = 0;
    let lastLiveExitTs = 0;
    let entryShowingResult = false;
    let exitLockedByPending = false;

    function clearExitTimer() {
        if (exitTimerInterval) {
            clearInterval(exitTimerInterval);
            exitTimerInterval = null;
        }
    }

    function showNotificationModal(isSuccess, title, message) {
        if (isSuccess) {
            $('#statusModalIcon').html('<i class="material-icons-outlined text-success" style="font-size: 80px;">check_circle</i>');
            $('#statusModalTitle').removeClass('text-danger').addClass('text-success').text(title);
        } else {
            $('#statusModalIcon').html('<i class="material-icons-outlined text-danger" style="font-size: 80px;">cancel</i>');
            $('#statusModalTitle').removeClass('text-success').addClass('text-danger').text(title);
        }
        $('#statusModalMessage').text(message);
        new bootstrap.Modal(document.getElementById('statusNotificationModal')).show();
    }

    let lastArmedCode = '';
    let lastArmFailedCode = '';

    function markArmFailed(msg) {
        lastArmedCode = '';
        lastArmFailedCode = ($('#exit-code').val() || '').trim().toUpperCase();
        $.post(API.clearExit);
        $('#exit-code-arm-hint').removeClass('text-muted text-success').addClass('text-danger')
            .text((msg || 'Mã không hợp lệ') + ' — sửa mã rồi nhập lại');
        const input = $('#exit-code')[0];
        if (input) {
            input.focus();
            input.select();
        }
    }

    function armExitCodeIfReady() {
        const code = ($('#exit-code').val() || '').trim().toUpperCase();
        if (code.length !== 6) {
            if (lastArmedCode || lastArmFailedCode) {
                $.post(API.clearExit);
                lastArmedCode = '';
                lastArmFailedCode = '';
                $('#exit-code-arm-hint').removeClass('text-success text-danger').addClass('text-muted')
                    .text('Nhập mã 6 ký tự');
            }
            return;
        }
        // Đã kích hoạt OK, hoặc vừa fail cùng mã (tránh spam) — chỉ thử lại khi user sửa/xóa rồi nhập lại
        if (code === lastArmedCode || code === lastArmFailedCode) return;
        $.post(API.armExit, { code: code })
            .done(function(res) {
                if (res && res.success) {
                    lastArmedCode = code;
                    lastArmFailedCode = '';
                    $('#exit-code-arm-hint').removeClass('text-muted text-danger').addClass('text-success')
                        .text('Đã kích hoạt — mã ' + code + (res.plate_number ? (' (' + res.plate_number + ')') : ''));
                } else {
                    markArmFailed((res && res.message) || 'Không kích hoạt được mã này');
                }
            })
            .fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Mã không hợp lệ';
                markArmFailed(msg);
            });
    }

    function applyLivePreview(live) {
        const entryLive = live && live.entry;
        const exitLive = live && live.exit;

        if (entryLive && entryLive.active && entryLive.url && !entryShowingResult) {
            if (entryLive.ts !== lastLiveEntryTs) {
                lastLiveEntryTs = entryLive.ts;
                $('#entry-placeholder').hide();
                $('#entry-camera').attr('src', entryLive.url).css('object-fit', 'cover').show();
            }
            $('#entry-live-badge').show();
        } else {
            $('#entry-live-badge').hide();
            if (!entryShowingResult) {
                const src = $('#entry-camera').attr('src') || '';
                if (!src || src.indexOf('/uploads/live/') !== -1) {
                    $('#entry-camera').hide().attr('src', '').css('object-fit', 'contain');
                    $('#entry-placeholder').show();
                }
                lastLiveEntryTs = 0;
            }
        }

        if (exitLive && exitLive.active && exitLive.url && !exitLockedByPending) {
            if (exitLive.ts !== lastLiveExitTs) {
                lastLiveExitTs = exitLive.ts;
                $('#exit-placeholder').hide();
                $('#exit-camera').attr('src', exitLive.url).css('object-fit', 'cover').show();
            }
            $('#exit-live-badge').show();
        } else {
            $('#exit-live-badge').hide();
            if (!exitLockedByPending) {
                const src = $('#exit-camera').attr('src') || '';
                if (!src || src.indexOf('/uploads/live/') !== -1) {
                    $('#exit-camera').hide().attr('src', '').css('object-fit', 'contain');
                    $('#exit-placeholder').show();
                }
                lastLiveExitTs = 0;
            }
        }
    }

    function resetEntryUi() {
        if (entryTimerInterval) clearInterval(entryTimerInterval);
        entryShowingResult = false;
        $('#entry-file').val('');
        $('#entry-camera').hide().attr('src', '').css('object-fit', 'contain');
        $('#entry-live-badge').hide();
        $('#entry-placeholder').show();
        $('#entry-result, #entry-error').fadeOut();
        lastLiveEntryTs = 0;
    }

    function resetExitAndComparison(opts) {
        opts = opts || {};
        clearExitTimer();
        autoExitInProgress = false;
        $('#exit-file').data('locked', false);
        exitLockedByPending = false;
        $('#exit-file, #exit-code, #btn-exit-recognize').prop('disabled', false);
        $('#btn-exit-recognize').html(exitBtnHtml(false));
        $('#exit-file').val('');
        if (!opts.keepCode) {
            $('#exit-code').val('');
        }
        $('#exit-camera').hide().attr('src', '').css('object-fit', 'contain');
        $('#exit-live-badge').hide();
        $('#exit-placeholder').show();
        $('#exit-result').hide();
        $('#exit-auto-timer-wrap').addClass('d-none');
        $('#btn-exit-retry-inline').hide();
        $('#comp-entry-img, #comp-exit-img').hide().attr('src', '');
        $('#comp-entry-empty, #comp-exit-empty').show();
        $('#comp-entry-plate, #comp-exit-plate').text('-');
        $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark').text('Đang chờ nhận diện xe ra...');
        $('#validation-buttons').hide();
        currentLogId = null;
        lastSeenPendingId = null;
        lastLiveExitTs = 0;
        lastArmedCode = '';
        lastArmFailedCode = '';
        $('#exit-code-arm-hint').removeClass('text-success text-danger').addClass('text-muted')
            .text(opts.keepCode ? 'Nhập đủ 6 ký tự hoặc sửa mã rồi thử lại' : 'Nhập mã 6 ký tự');
        if (opts.focusCode) {
            const input = $('#exit-code')[0];
            if (input) {
                input.focus();
                input.select();
            }
        }
    }

    function autoApproveExit(logId) {
        if (!logId || autoExitInProgress) return;
        autoExitInProgress = true;
        $('#comp-status-badge').removeClass('bg-warning bg-danger text-dark').addClass('bg-success text-white')
            .text('Đang xác nhận cho ra...');
        $.post(API.validate, { log_id: logId, is_valid: true })
            .done(function(res) {
                if (res && res.success) {
                    $('#exit-auto-timer-wrap').addClass('d-none');
                    $('#exit-message').html(
                        '<i class="material-icons-outlined align-middle me-1">check_circle</i> Đã cho phép xe ra!'
                    );
                    $('#comp-status-badge').text('Đã cho ra thành công');
                    setTimeout(function() {
                        resetExitAndComparison();
                    }, 1500);
                } else {
                    autoExitInProgress = false;
                    $('#exit-auto-timer-wrap').addClass('d-none');
                    $('#validation-buttons').show();
                    $('#comp-status-badge').removeClass('bg-success').addClass('bg-danger text-white')
                        .text('Tự động cho ra thất bại — xác nhận thủ công');
                    showNotificationModal(false, 'Không cho ra được', (res && res.message) || 'Vui lòng bấm Hợp lệ / Không hợp lệ.');
                }
            })
            .fail(function(xhr) {
                autoExitInProgress = false;
                $('#exit-auto-timer-wrap').addClass('d-none');
                $('#validation-buttons').show();
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Lỗi kết nối khi tự động cho ra.';
                showNotificationModal(false, 'Lỗi tự động cho ra', msg);
            });
    }

    function doRetryExit(keepCode) {
        const payload = {};
        if (currentLogId) payload.log_id = currentLogId;
        $.post(API.retryExit, payload)
            .always(function() {
                resetExitAndComparison({ keepCode: !!keepCode, focusCode: true });
                $('#exit-code-arm-hint').removeClass('text-muted text-success').addClass('text-danger')
                    .text('Đã hủy lượt ra — nhập lại mã để quét biển lại');
            });
    }

    function showEntrySuccess(plate, code, imageUrl) {
        entryShowingResult = true;
        $('#entry-error').hide();
        $('#entry-live-badge').hide();
        if (imageUrl) {
            $('#entry-placeholder').hide();
            $('#entry-camera').attr('src', imageUrl).css('object-fit', 'contain').show();
        }
        $('#res-plate').text(plate || '');
        $('#res-code').text(code || '');
        $('#entry-result').fadeIn();
        if (entryTimerInterval) clearInterval(entryTimerInterval);
        let seconds = 10;
        $('#entry-timer').text(seconds);
        entryTimerInterval = setInterval(function() {
            seconds--;
            $('#entry-timer').text(seconds);
            if (seconds <= 0) {
                clearInterval(entryTimerInterval);
                resetEntryUi();
            }
        }, 1000);
    }

    function showAlreadyInsideAlert(alert, opts) {
        opts = opts || {};
        if (!alert) return;
        if (entryTimerInterval) clearInterval(entryTimerInterval);
        entryShowingResult = true;
        $('#entry-result').hide();
        $('#entry-live-badge').hide();

        if (alert.entry_image) {
            $('#entry-placeholder').hide();
            $('#entry-camera').attr('src', alert.entry_image).css('object-fit', 'contain').show();
        }

        $('#entry-error-title').text('Xe vẫn nằm trong bãi');
        $('#entry-error-msg').text(alert.message || 'Xe này chưa ra khỏi bãi — không thể nhận diện vào lần nữa.');
        $('#entry-error').show();

        // Máy tính bắt buộc hiện modal
        if (opts.modal !== false) {
            showNotificationModal(false, 'Xe vẫn nằm trong bãi', alert.message || 'Xe này chưa ra khỏi bãi.');
        }

        // Tự ẩn sau 12s để không che lượt tiếp theo
        setTimeout(function() {
            if ($('#entry-error').is(':visible')) {
                resetEntryUi();
            }
        }, 12000);
    }

    function showPendingValidation(data) {
        // Đang đếm giây / đang tự cho ra cùng lượt → bỏ qua poll lặp lại
        if (autoExitInProgress && currentLogId && data.log_id === currentLogId) return;
        if (exitTimerInterval && currentLogId && data.log_id === currentLogId && data.match) return;

        clearExitTimer();
        currentLogId = data.log_id;
        lastSeenPendingId = data.log_id;
        exitLockedByPending = true;
        $('#exit-live-badge').hide();
        if (data.entry_image) {
            $('#comp-entry-empty').hide();
            $('#comp-entry-img').attr('src', data.entry_image).show();
        }
        $('#comp-entry-plate').text(data.entry_plate || '-');
        if (data.exit_image) {
            $('#comp-exit-empty').hide();
            $('#comp-exit-img').attr('src', data.exit_image).show();
            $('#exit-placeholder').hide();
            $('#exit-camera').attr('src', data.exit_image).css('object-fit', 'contain').show();
        }
        $('#comp-exit-plate').text(data.exit_plate || '-');

        const alertBox = $('#exit-alert-box');
        $('#btn-exit-retry-inline').hide();
        $('#exit-file, #exit-code, #btn-exit-recognize').prop('disabled', true);
        $('#exit-file').data('locked', true);
        $('#exit-result').show();

        if (data.match) {
            // Biển khớp → đếm giây rồi tự động cho ra (giống làm mới sau xe vào)
            alertBox.removeClass('alert-danger alert-info').addClass('alert-success');
            $('#exit-message').html(
                '<i class="material-icons-outlined align-middle me-1">check_circle</i> Biển số khớp: <strong>' +
                (data.exit_plate || data.entry_plate || '') + '</strong>'
            );
            $('#validation-buttons').hide();
            let seconds = 10;
            $('#exit-auto-timer').text(seconds);
            $('#exit-auto-timer-wrap').removeClass('d-none');
            $('#comp-status-badge').removeClass('bg-warning bg-danger text-dark').addClass('bg-success text-white')
                .text('Biển khớp — tự động cho ra sau ' + seconds + 's');
            exitTimerInterval = setInterval(function() {
                seconds--;
                $('#exit-auto-timer').text(seconds);
                $('#comp-status-badge').text('Biển khớp — tự động cho ra sau ' + seconds + 's');
                if (seconds <= 0) {
                    clearExitTimer();
                    autoApproveExit(data.log_id);
                }
            }, 1000);
        } else {
            alertBox.removeClass('alert-success alert-info').addClass('alert-danger');
            $('#exit-message').html('<i class="material-icons-outlined align-middle me-1">warning</i> ' + (data.message || ''));
            $('#exit-auto-timer-wrap').addClass('d-none');
            $('#comp-status-badge').removeClass('bg-warning bg-success text-dark').addClass('bg-danger text-white')
                .text('BSX không trùng — cần xác nhận thủ công');
            $('#validation-buttons').show();
        }
    }

    function showExitError(message) {
        exitLockedByPending = false;
        $('#exit-file').data('locked', false);
        $('#exit-file, #exit-code, #btn-exit-recognize').prop('disabled', false);
        $('#btn-exit-recognize').html(exitBtnHtml(false));
        const alertBox = $('#exit-alert-box');
        alertBox.removeClass('alert-success alert-info').addClass('alert-danger');
        $('#exit-message').html(
            '<i class="material-icons-outlined align-middle me-1">error</i> ' +
            (message || 'Có lỗi xảy ra') +
            '<div class="small fw-normal mt-1">Bạn có thể sửa mã / chọn ảnh khác rồi nhận diện lại.</div>'
        );
        $('#btn-exit-retry-inline').show();
        $('#exit-result').show();
        $('#validation-buttons').hide();
        $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark')
            .text('Lỗi — có thể làm lại');
    }

    function entryBtnHtml(busy) {
        if (busy) return '<span class="spinner-border spinner-border-sm me-1"></span> Đang nhận diện AI...';
        return '<i class="material-icons-outlined align-middle me-1">photo_camera</i> <span id="btn-entry-recognize-label">Nhận diện</span>';
    }

    function exitBtnHtml(busy) {
        if (busy) return '<span class="spinner-border spinner-border-sm me-1"></span> Đang đối chiếu AI...';
        return '<i class="material-icons-outlined align-middle me-1">photo_camera</i> <span id="btn-exit-recognize-label">Nhận diện</span>';
    }

    function submitEntry(imageSource) {
        const fd = new FormData();
        fd.append('image', imageSource);
        const btn = $('#btn-entry-recognize');
        btn.prop('disabled', true).html(entryBtnHtml(true));
        $('#entry-result, #entry-error').hide();
        if (entryTimerInterval) clearInterval(entryTimerInterval);
        $.ajax({
            url: API.entry,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showEntrySuccess(response.plate_number, response.code, response.image_url || null);
                    if (response.log_id) lastSeenEntryId = response.log_id;
                } else if (response.already_inside) {
                    if (response.id) lastSeenEntryAlertId = String(response.id);
                    showAlreadyInsideAlert(response);
                } else {
                    $('#entry-error-title').text('Lỗi nhận diện');
                    $('#entry-error-msg').text(response.message || 'Có lỗi xảy ra!');
                    $('#entry-error').fadeIn();
                }
            },
            error: function(xhr) {
                const body = xhr.responseJSON || {};
                if (body.already_inside) {
                    if (body.id) lastSeenEntryAlertId = String(body.id);
                    showAlreadyInsideAlert(body);
                    return;
                }
                let msg = 'Lỗi kết nối máy chủ (HTTP ' + xhr.status + ').';
                if (body.message) msg += '\nChi tiết: ' + body.message;
                showNotificationModal(false, 'Lỗi Kết Nối Máy Chủ', msg);
            },
            complete: function() {
                btn.prop('disabled', false).html(entryBtnHtml(false));
            }
        });
    }

    function submitExit(imageSource, codeVal) {
        const fd = new FormData();
        fd.append('image', imageSource);
        fd.append('code', codeVal);
        const btn = $('#btn-exit-recognize');
        btn.prop('disabled', true).html(exitBtnHtml(true));
        $('#btn-exit-retry-inline').hide();
        $.ajax({
            url: API.exit,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showPendingValidation({
                        log_id: response.log_id,
                        code: response.code,
                        entry_plate: response.entry_plate,
                        exit_plate: response.exit_plate,
                        entry_image: response.entry_image,
                        exit_image: response.exit_image || null,
                        match: response.match === true || response.match === 1 || response.match === 'true',
                        message: response.message
                    });
                } else {
                    // Sai mã → bỏ kích hoạt cũ; lỗi biển → vẫn mở khóa để làm lại
                    if (!response.keep_armed) {
                        lastArmedCode = '';
                        lastArmFailedCode = codeVal;
                        $.post(API.clearExit);
                    }
                    showExitError(response.message || 'Có lỗi xảy ra');
                }
            },
            error: function(xhr) {
                let msg = 'Lỗi kết nối máy chủ (HTTP ' + xhr.status + ').';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                lastArmedCode = '';
                showExitError(msg);
            },
            complete: function() {
                if (!$('#exit-file').prop('disabled')) {
                    btn.prop('disabled', false).html(exitBtnHtml(false));
                }
            }
        });
    }

    function pollMonitor() {
        $.get(API.monitor)
            .done(function(res) {
                if (!res || !res.success) return;

                applyLivePreview(res.live_preview);

                // Lần poll đầu sau F5: chỉ ghi nhận ID hiện có, KHÔNG hiện dữ liệu cũ lên màn hình
                if (!monitorBootstrapped) {
                    if (res.last_entry && res.last_entry.id) {
                        lastSeenEntryId = res.last_entry.id;
                    }
                    if (res.pending_validation && res.pending_validation.log_id) {
                        lastSeenPendingId = res.pending_validation.log_id;
                    }
                    if (res.entry_alert && res.entry_alert.id) {
                        lastSeenEntryAlertId = String(res.entry_alert.id);
                    }
                    if (res.armed_exit_code) {
                        lastArmedCode = String(res.armed_exit_code).toUpperCase();
                        $('#exit-code-arm-hint').removeClass('text-muted text-danger').addClass('text-success')
                            .text('Đã kích hoạt — mã ' + lastArmedCode);
                    }
                    monitorBootstrapped = true;
                    return;
                }

                // Cảnh báo "xe vẫn trong bãi" từ ĐT/PC → bắt buộc hiện trên máy tính
                const entryAlert = res.entry_alert;
                if (entryAlert && entryAlert.id && String(entryAlert.id) !== String(lastSeenEntryAlertId || '')) {
                    lastSeenEntryAlertId = String(entryAlert.id);
                    showAlreadyInsideAlert(entryAlert);
                }

                const entry = res.last_entry;
                if (entry && entry.id && entry.id !== lastSeenEntryId) {
                    lastSeenEntryId = entry.id;
                    if (!isPhone || !$('#entry-result').is(':visible')) {
                        showEntrySuccess(entry.plate_number, entry.code, entry.entry_image);
                    }
                }

                const pending = res.pending_validation;
                if (pending && pending.log_id) {
                    if (pending.log_id !== lastSeenPendingId || currentLogId !== pending.log_id) {
                        lastSeenPendingId = pending.log_id;
                        showPendingValidation(pending);
                    }
                } else if (lastSeenPendingId && !pending) {
                    if (currentLogId === lastSeenPendingId) {
                        resetExitAndComparison();
                    }
                    lastSeenPendingId = null;
                }

                // Nếu mã còn trên ô nhập mà server mất kích hoạt (lỗi/hết hạn) → kích hoạt lại để ĐT quét tiếp
                // Không tự kích hoạt lại mã vừa fail (user phải sửa/xóa rồi nhập lại)
                const codeNow = ($('#exit-code').val() || '').trim().toUpperCase();
                if (codeNow.length === 6 && !res.armed_exit_code && !pending && !$('#exit-file').data('locked')
                    && codeNow !== lastArmFailedCode) {
                    if (lastArmedCode === codeNow) lastArmedCode = '';
                    armExitCodeIfReady();
                } else if (res.armed_exit_code) {
                    lastArmedCode = String(res.armed_exit_code).toUpperCase();
                    lastArmFailedCode = '';
                    $('#exit-code-arm-hint').removeClass('text-muted text-danger').addClass('text-success')
                        .text('Đã kích hoạt — mã ' + lastArmedCode);
                }
            })
            .always(function() {
                const liveOn = $('#entry-live-badge').is(':visible') || $('#exit-live-badge').is(':visible');
                setTimeout(pollMonitor, liveOn ? 500 : MONITOR_POLL_MS);
            });
    }

    $('#entry-file').change(function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#entry-placeholder').hide();
            $('#entry-camera').attr('src', e.target.result).show();
        };
        reader.readAsDataURL(file);
    });

    $('#exit-file').change(function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#exit-placeholder').hide();
            $('#exit-camera').attr('src', e.target.result).show();
        };
        reader.readAsDataURL(file);
    });

    $('#btn-entry-recognize').click(function() {
        const fileInput = $('#entry-file')[0];
        if (fileInput.files.length > 0) {
            submitEntry(fileInput.files[0]);
            return;
        }
        showNotificationModal(false, 'Chưa có ảnh', 'Chọn ảnh từ máy, hoặc dùng ĐT quét xe vào.');
    });

    $('#btn-exit-recognize').click(function() {
        const codeVal = ($('#exit-code').val() || '').trim();
        if (!codeVal) return showNotificationModal(false, 'Chưa Đủ Điều Kiện', 'Thiếu: Mã code xe vào');
        if (codeVal.length !== 6) return showNotificationModal(false, 'Mã Code Không Hợp Lệ', 'Mã code phải có đúng 6 ký tự!');

        const fileInput = $('#exit-file')[0];
        if (fileInput.files.length > 0) {
            submitExit(fileInput.files[0], codeVal);
            return;
        }
        showNotificationModal(false, 'Chưa có ảnh', 'Chọn ảnh từ máy, hoặc dùng ĐT quét xe ra.');
    });

    $('#btn-exit-retry-inline').click(function() {
        doRetryExit(false);
    });

    let pendingValidationAction = null;
    const confirmModalObj = new bootstrap.Modal(document.getElementById('confirmValidationModal'));
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
    $('#confirmModalSubmitBtn').click(function() {
        if (pendingValidationAction === null || !currentLogId) return;
        const isValid = pendingValidationAction;
        const btnSubmit = $(this);
        btnSubmit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...');
        $.post(API.validate, { log_id: currentLogId, is_valid: isValid }, function(res) {
            confirmModalObj.hide();
            if (res.success) {
                showNotificationModal(isValid, isValid ? 'Đã Cho Phép Xe Ra!' : 'Đã Từ Chối Phương Tiện!', res.message);
                resetExitAndComparison();
            } else {
                showNotificationModal(false, 'Thao Tác Thất Bại', res.message);
            }
        }).always(function() {
            btnSubmit.prop('disabled', false).text(isValid ? 'Đồng ý Cho Ra' : 'Xác Nhận Từ Chối');
        });
    });

    pollMonitor();

    $('#exit-code').on('input', function() {
        this.value = (this.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
        armExitCodeIfReady();
    });
});
</script>
@endpush
