@extends('layouts.app')

@section('title', 'Giám sát Xe ra vào')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h5 class="mb-0 fw-bold">Màn hình giám sát (camera điện thoại)</h5>
        <span class="badge bg-dark" id="device-role-badge">Máy tính giám sát</span>
    </div>

    <div class="row g-4 mb-4">
        <!-- Cot 1: Camera Xe Vao -->
        <div class="col-12 col-xl-4">
            <div class="card h-100 border-primary shadow-sm">
                <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">login</i> Camera Xe Vào (Check-In)</h5>
                </div>
                <div class="card-body text-center p-3">
                    <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded overflow-hidden live-frame-box"
                        style="height: 350px; width: 100%; border: 2px dashed #0d6efd; position: relative;">
                        <video id="entry-live-video" playsinline muted autoplay
                            style="width: 100%; height: 100%; object-fit: contain; display: none; position: absolute; inset: 0; background: #f8f9fa;"></video>
                        <img id="entry-camera" src="" alt="Camera Xe Vào"
                            style="width: 100%; height: 100%; object-fit: contain; display: none; position: absolute; inset: 0;">
                        <div id="entry-live-badge" class="position-absolute top-0 start-0 m-2 badge bg-danger"
                            style="display: none; z-index: 2; font-size: 11px;">
                            <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span>
                            <span id="entry-live-badge-text">LIVE ĐT</span>
                        </div>
                        <div id="entry-placeholder" class="text-muted text-center p-3">
                            <i class="material-icons-outlined text-primary" style="font-size: 54px;">smartphone</i>
                            <h6 class="mt-2 text-dark fw-bold small" id="entry-placeholder-text">Chờ ĐT quét xe vào</h6>
                        </div>
                    </div>

                    <div id="entry-result" class="mt-2" style="display: none;">
                        <div class="alert alert-success border-0 shadow-sm mb-0 p-2">
                            <h6 class="alert-heading fw-bold mb-1"><i class="material-icons-outlined align-middle">check_circle</i> Nhận diện thành công!</h6>
                            <div class="fs-6 mb-1">Biển số: <strong id="res-plate" class="text-danger"></strong></div>
                            <div class="fs-6 fw-bold">Mã Code: <strong id="res-code" class="text-primary badge bg-light border text-primary px-2 py-1"></strong></div>
                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">Tự động làm mới sau <span id="entry-timer" class="fw-bold">10</span> giây...</small>
                        </div>
                    </div>
                    <div id="entry-error" class="mt-2" style="display: none;">
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

        <!-- Cot 2: Camera Xe Ra -->
        <div class="col-12 col-xl-4">
            <div class="card h-100 border-danger shadow-sm">
                <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="mb-0 text-white fw-bold fs-6"><i class="material-icons-outlined align-middle me-1">logout</i> Camera Xe Ra (Check-Out)</h5>
                </div>
                <div class="card-body text-center p-3">
                    <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded overflow-hidden live-frame-box"
                        style="height: 350px; width: 100%; border: 2px dashed #dc3545; position: relative;">
                        <video id="exit-live-video" playsinline muted autoplay
                            style="width: 100%; height: 100%; object-fit: contain; display: none; position: absolute; inset: 0; background: #f8f9fa;"></video>
                        <img id="exit-camera" src="" alt="Camera Xe Ra"
                            style="width: 100%; height: 100%; object-fit: contain; display: none; position: absolute; inset: 0;">
                        <div id="exit-live-badge" class="position-absolute top-0 start-0 m-2 badge bg-danger"
                            style="display: none; z-index: 2; font-size: 11px;">
                            <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span>
                            <span id="exit-live-badge-text">LIVE ĐT</span>
                        </div>
                        <div id="exit-placeholder" class="text-muted text-center p-3">
                            <i class="material-icons-outlined text-danger" style="font-size: 54px;">smartphone</i>
                            <h6 class="mt-2 text-dark fw-bold small" id="exit-placeholder-text">Chờ ĐT quét xe ra</h6>
                        </div>
                    </div>

                    <div class="input-group mb-2 shadow-sm">
                        <span class="input-group-text bg-light text-danger fw-bold small"><i class="material-icons-outlined me-1 fs-6">qr_code</i> Mã Code</span>
                        <input type="text" id="exit-code" class="form-control text-uppercase fw-bold" placeholder="Nhập mã" maxlength="6">
                    </div>
                    <small id="exit-code-arm-hint" class="text-muted d-block mb-2" style="font-size: 13px;">
                        Nhập mã 6 ký tự để ĐT bắt đầu quét
                    </small>

                    <div id="exit-result" class="mt-2" style="display: none;">
                        <div class="alert mb-0 shadow-sm p-2 text-center" id="exit-alert-box">
                            <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                    <i class="material-icons-outlined flex-shrink-0" id="exit-alert-icon" style="font-size: 22px; line-height: 1;">check_circle</i>
                                    <h6 class="mb-0 fw-bold fs-6" id="exit-message"></h6>
                                </div>
                                <div id="exit-auto-timer-wrap" class="text-muted d-none" style="font-size: 11px;">
                                    Tự động cho ra sau <span id="exit-auto-timer" class="fw-bold">10</span> giây...
                                </div>
                                <button type="button" id="btn-exit-retry-inline" class="btn btn-sm btn-outline-danger fw-bold mt-1" style="display: none;">
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
            <div class="card h-100 border-dark shadow-sm" id="comparison-section">
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
        liveStatus: @json(route('api.live_status', [], false)),
        webrtcSignal: @json(route('guard.webrtc_signal_post', [], false)),
        webrtcPoll: @json(route('guard.webrtc_signal_poll', [], false)),
        armExit: @json(route('api.arm_exit_code', [], false)),
        clearExit: @json(route('api.clear_exit_code', [], false)),
        retryExit: @json(route('api.retry_exit', [], false)),
        validate: @json(route('api.validate_checkout', [], false))
    };

    const MONITOR_POLL_MS = 1500;
    const LIVE_POLL_MS = 120;
    const LIVE_POLL_IDLE_MS = 280;
    const RTC_POLL_MS = 280;
    const RTC_CONFIG = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };
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
    let liveObjectUrls = { entry: null, exit: null };
    const rtcState = {
        entry: { pc: null, session: null, after: 0, connected: false, pollTimer: null },
        exit: { pc: null, session: null, after: 0, connected: false, pollTimer: null }
    };

    function clearExitTimer() {
        if (exitTimerInterval) {
            clearInterval(exitTimerInterval);
            exitTimerInterval = null;
        }
    }

    function revokeLiveUrl(side) {
        if (liveObjectUrls[side]) {
            try { URL.revokeObjectURL(liveObjectUrls[side]); } catch (e) {}
            liveObjectUrls[side] = null;
        }
    }

    function frameToObjectUrl(b64) {
        try {
            const bin = atob(b64);
            const len = bin.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
            return URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
        } catch (e) {
            return null;
        }
    }

    function rtcEncode(obj) {
        return btoa(unescape(encodeURIComponent(JSON.stringify(obj))));
    }

    function rtcDecode(payload) {
        return JSON.parse(decodeURIComponent(escape(atob(payload))));
    }

    function rtcPost(side, type, payloadObj, session) {
        return $.ajax({
            url: API.webrtcSignal,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                side: side,
                from: 'monitor',
                type: type,
                session: session,
                payload: payloadObj ? rtcEncode(payloadObj) : ''
            }),
            timeout: 4000
        });
    }

    function setRtcUi(side, connected) {
        const video = document.getElementById(side + '-live-video');
        const img = $('#' + side + '-camera');
        const placeholder = $('#' + side + '-placeholder');
        const badge = $('#' + side + '-live-badge');
        const badgeText = $('#' + side + '-live-badge-text');
        if (connected) {
            placeholder.hide();
            img.hide();
            if (video) {
                video.style.display = 'block';
            }
            badgeText.text('LIVE RTC');
            badge.show();
        } else {
            // Soft off: không xóa srcObject (disconnected tạm lúc OCR sẽ tự connected lại)
            badgeText.text('LIVE ĐT');
        }
    }

    function hardClearRtcVideo(side) {
        const video = document.getElementById(side + '-live-video');
        if (video) {
            video.style.display = 'none';
            try { video.srcObject = null; } catch (e) {}
        }
    }

    function isRtcPrefer(side) {
        const st = rtcState[side];
        const video = document.getElementById(side + '-live-video');
        if (st && st.connected) return true;
        if (video && video.srcObject) {
            const cs = st && st.pc ? st.pc.connectionState : '';
            if (!cs || cs === 'connected' || cs === 'connecting' || cs === 'disconnected') {
                return true;
            }
        }
        return false;
    }

    function stopRtcSide(side) {
        const st = rtcState[side];
        if (st.pollTimer) {
            clearTimeout(st.pollTimer);
            st.pollTimer = null;
        }
        if (st.pc) {
            try { st.pc.close(); } catch (e) {}
            st.pc = null;
        }
        st.session = null;
        st.after = 0;
        st.connected = false;
        hardClearRtcVideo(side);
        setRtcUi(side, false);
    }

    function handleRtcMessages(side, messages, session) {
        const st = rtcState[side];
        if (!messages || !messages.length) return;
        messages.forEach(function(m) {
            st.after = Math.max(st.after, Number(m.id) || 0);
            let data = null;
            try {
                data = m.payload ? rtcDecode(m.payload) : null;
            } catch (e) {
                console.warn('webrtc decode', e);
                return;
            }
            if (m.type === 'offer') {
                acceptRtcOffer(side, session, data);
            } else if (m.type === 'ice' && st.pc && st.session === session && data) {
                st.pc.addIceCandidate(data).catch(function() {});
            } else if (m.type === 'bye') {
                stopRtcSide(side);
            }
        });
    }

    async function acceptRtcOffer(side, session, desc) {
        const st = rtcState[side];
        if (!desc || !desc.type || !desc.sdp) return;

        // Session mới / đổi offer → tạo PC mới
        if (st.pc && st.session !== session) {
            try { st.pc.close(); } catch (e) {}
            st.pc = null;
            st.connected = false;
        }
        st.session = session;

        if (!st.pc) {
            const pc = new RTCPeerConnection(RTC_CONFIG);
            st.pc = pc;
            pc.onicecandidate = function(ev) {
                if (!ev.candidate || !st.session) return;
                rtcPost(side, 'ice', ev.candidate.toJSON ? ev.candidate.toJSON() : ev.candidate, st.session);
            };
            pc.onconnectionstatechange = function() {
                const cs = pc.connectionState;
                if (cs === 'connected') {
                    st.connected = true;
                    setRtcUi(side, true);
                } else if (cs === 'failed' || cs === 'closed') {
                    // Chỉ chết hẳn — bỏ qua 'disconnected' (hay xảy ra lúc ĐT upload OCR)
                    st.connected = false;
                    if (cs === 'failed') {
                        try { if (pc.restartIce) pc.restartIce(); } catch (e) {}
                    }
                    if (cs === 'closed') {
                        hardClearRtcVideo(side);
                        setRtcUi(side, false);
                    }
                }
                // 'disconnected' / 'connecting': giữ nguyên video RTC
            };
            pc.ontrack = function(ev) {
                const video = document.getElementById(side + '-live-video');
                if (!video) return;
                const ms = ev.streams && ev.streams[0]
                    ? ev.streams[0]
                    : new MediaStream([ev.track]);
                video.srcObject = ms;
                video.muted = true;
                video.playsInline = true;
                video.play().catch(function() {});
                st.connected = true;
                setRtcUi(side, true);
            };
        }

        try {
            await st.pc.setRemoteDescription(desc);
            const answer = await st.pc.createAnswer();
            await st.pc.setLocalDescription(answer);
            await rtcPost(side, 'answer', {
                type: answer.type,
                sdp: answer.sdp
            }, session);
        } catch (e) {
            console.warn('webrtc answer fail', e);
            stopRtcSide(side);
        }
    }

    function pollRtcSide(side) {
        const st = rtcState[side];
        if (st.pollTimer) clearTimeout(st.pollTimer);

        $.ajax({
            url: API.webrtcPoll,
            method: 'GET',
            cache: false,
            timeout: 4000,
            data: {
                side: side,
                role: 'monitor',
                after: st.after,
                session: st.session || '',
                _: Date.now()
            }
        }).done(function(res) {
            if (!res || !res.success) return;
            if (!res.session) {
                if (st.session) stopRtcSide(side);
                return;
            }
            // Session đổi từ ĐT
            if (st.session && res.session !== st.session) {
                stopRtcSide(side);
                st.after = 0;
            }
            handleRtcMessages(side, res.messages || [], res.session);
        }).always(function() {
            st.pollTimer = setTimeout(function() { pollRtcSide(side); }, RTC_POLL_MS);
        });
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
    let lastArmConflictCode = '';
    let armInFlight = false;
    let conflictRetryTimer = null;

    function stopConflictRetry() {
        if (conflictRetryTimer) {
            clearTimeout(conflictRetryTimer);
            conflictRetryTimer = null;
        }
    }

    function scheduleConflictRetry() {
        stopConflictRetry();
        conflictRetryTimer = setTimeout(function() {
            conflictRetryTimer = null;
            const code = ($('#exit-code').val() || '').trim().toUpperCase();
            if (code.length === 6 && lastArmConflictCode === code && !exitLockedByPending) {
                armExitCodeIfReady(true);
            }
        }, 600);
    }

    function markArmFailed(msg) {
        stopConflictRetry();
        lastArmedCode = '';
        lastArmConflictCode = '';
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

    function markArmConflict(msg) {
        lastArmedCode = '';
        lastArmFailedCode = '';
        lastArmConflictCode = ($('#exit-code').val() || '').trim().toUpperCase();
        $('#exit-code-arm-hint').removeClass('text-muted text-success').addClass('text-danger')
            .text(msg || 'Mã đang được tài khoản khác sử dụng — không nhận mã.');
        scheduleConflictRetry();
    }

    function armExitCodeIfReady(forceRetry) {
        const code = ($('#exit-code').val() || '').trim().toUpperCase();
        if (code.length !== 6) {
            // Xóa/sửa còn dưới 6 ký tự → luôn tắt kích hoạt + reset hint (kể cả poll đang treo chữ xanh)
            const hadArmUi = !!(lastArmedCode || lastArmFailedCode || lastArmConflictCode
                || $('#exit-code-arm-hint').hasClass('text-success')
                || $('#exit-code-arm-hint').hasClass('text-danger'));
            if (hadArmUi || code.length === 0) {
                stopConflictRetry();
                if (lastArmedCode || hadArmUi) {
                    $.post(API.clearExit);
                }
                lastArmedCode = '';
                lastArmFailedCode = '';
                lastArmConflictCode = '';
                $('#exit-code-arm-hint').removeClass('text-success text-danger').addClass('text-muted')
                    .text('Nhập mã 6 ký tự để ĐT bắt đầu quét');
            }
            return;
        }
        // Đã kích hoạt OK, hoặc fail cứng cùng mã — chỉ thử lại khi user sửa/xóa rồi nhập lại
        // Conflict: cho forceRetry để khi bên kia xóa mã / quét xong thì cập nhật ngay
        if (!forceRetry && (code === lastArmedCode || code === lastArmFailedCode || code === lastArmConflictCode)) {
            return;
        }
        if (armInFlight) return;
        armInFlight = true;
        $.post(API.armExit, { code: code })
            .done(function(res) {
                if (res && res.success) {
                    stopConflictRetry();
                    lastArmedCode = code;
                    lastArmFailedCode = '';
                    lastArmConflictCode = '';
                    $('#exit-code-arm-hint').removeClass('text-muted text-danger').addClass('text-success')
                        .text('Đã kích hoạt — mã ' + code + (res.plate_number ? (' (' + res.plate_number + ')') : ''));
                } else if (res && res.conflict) {
                    markArmConflict((res && res.message) || 'Mã đang được tài khoản khác sử dụng — không nhận mã.');
                } else {
                    markArmFailed((res && res.message) || 'Không kích hoạt được mã này');
                }
            })
            .fail(function(xhr) {
                const body = (xhr && xhr.responseJSON) || {};
                if (body.conflict || xhr.status === 409) {
                    markArmConflict(body.message || 'Mã đang được tài khoản khác sử dụng — không nhận mã.');
                    return;
                }
                if (xhr.status === 404) {
                    markArmFailed(body.message || 'Mã không tồn tại.');
                    return;
                }
                markArmFailed(body.message || 'Mã không hợp lệ');
            })
            .always(function() {
                armInFlight = false;
            });
    }

    function setLiveFrame(side, live) {
        // WebRTC đang có stream → luôn ưu tiên, không để JPEG đè khi OCR
        if (isRtcPrefer(side)) {
            const video = document.getElementById(side + '-live-video');
            const img = $('#' + side + '-camera');
            $('#' + side + '-placeholder').hide();
            img.hide();
            if (video) video.style.display = 'block';
            $('#' + side + '-live-badge-text').text('LIVE RTC');
            $('#' + side + '-live-badge').show();
            return;
        }

        const imgId = side === 'exit' ? '#exit-camera' : '#entry-camera';
        const placeholderId = side === 'exit' ? '#exit-placeholder' : '#entry-placeholder';
        const badgeId = side === 'exit' ? '#exit-live-badge' : '#entry-live-badge';
        const lastTs = side === 'exit' ? lastLiveExitTs : lastLiveEntryTs;

        if (live && live.active && (live.frame || live.url)) {
            if (live.ts && live.ts === lastTs) {
                $(badgeId).show();
                $('#' + side + '-live-badge-text').text('LIVE ĐT');
                return;
            }

            const nextTs = live.ts || Date.now();
            const apply = function(src) {
                if (side === 'exit') {
                    if (nextTs < lastLiveExitTs) return;
                    lastLiveExitTs = nextTs;
                } else {
                    if (nextTs < lastLiveEntryTs) return;
                    lastLiveEntryTs = nextTs;
                }
                $(placeholderId).hide();
                const video = document.getElementById(side + '-live-video');
                if (video) video.style.display = 'none';
                $(imgId).attr('src', src).css('object-fit', 'contain').show();
                $('#' + side + '-live-badge-text').text('LIVE ĐT');
                $(badgeId).show();
            };

            // Frame nhúng sẵn → hiện ngay, không GET thêm file
            if (live.frame) {
                const objUrl = frameToObjectUrl(live.frame);
                if (objUrl) {
                    revokeLiveUrl(side);
                    liveObjectUrls[side] = objUrl;
                    apply(objUrl);
                    return;
                }
                apply('data:image/jpeg;base64,' + live.frame);
                return;
            }

            // Fallback URL file
            const pre = new Image();
            pre.onload = function() { apply(live.url); };
            pre.onerror = function() { $(badgeId).hide(); };
            pre.src = live.url;
        } else {
            $(badgeId).hide();
            const src = $(imgId).attr('src') || '';
            if (!src || src.indexOf('/uploads/live/') !== -1 || src.indexOf('blob:') === 0 || src.indexOf('data:image') === 0) {
                revokeLiveUrl(side);
                $(imgId).hide().attr('src', '').css('object-fit', 'contain');
                $(placeholderId).show();
                if (side === 'exit') lastLiveExitTs = 0;
                else lastLiveEntryTs = 0;
            }
        }
    }

    function applyLivePreview(live) {
        setLiveFrame('entry', live && live.entry);
        setLiveFrame('exit', live && live.exit);
    }

    function pollLive() {
        $.ajax({
            url: API.liveStatus,
            method: 'GET',
            cache: false,
            timeout: 2500,
            data: {
                entry_ts: lastLiveEntryTs,
                exit_ts: lastLiveExitTs,
                _: Date.now()
            }
        }).done(function(res) {
            if (res && res.success) applyLivePreview(res.live_preview);
        }).always(function() {
            const active = (lastLiveEntryTs > 0) || (lastLiveExitTs > 0);
            setTimeout(pollLive, active ? LIVE_POLL_MS : LIVE_POLL_IDLE_MS);
        });
    }

    function resetEntryUi() {
        if (entryTimerInterval) clearInterval(entryTimerInterval);
        entryShowingResult = false;
        // Không đụng ô camera LIVE — chỉ ẩn kết quả + xóa ảnh đối chiếu xe vào
        $('#entry-result, #entry-error').fadeOut();
        // Nếu đang chờ xác nhận xe ra thì giữ nguyên cột đối chiếu
        if (!exitLockedByPending && !currentLogId) {
            $('#comp-entry-img').hide().attr('src', '');
            $('#comp-entry-empty').show();
            $('#comp-entry-plate').text('-');
            $('#comp-exit-img').hide().attr('src', '');
            $('#comp-exit-empty').show();
            $('#comp-exit-plate').text('-');
            $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark')
                .text('Đang chờ nhận diện xe ra...');
            $('#validation-buttons').hide();
        }
    }

    function resetExitAndComparison(opts) {
        opts = opts || {};
        clearExitTimer();
        autoExitInProgress = false;
        exitLockedByPending = false;
        $('#exit-code').prop('disabled', false);
        if (!opts.keepCode) {
            $('#exit-code').val('');
        }
        // Không đụng ô camera LIVE — chỉ xóa đối chiếu / kết quả
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
        lastArmedCode = '';
        lastArmFailedCode = '';
        lastArmConflictCode = '';
        if (typeof stopConflictRetry === 'function') stopConflictRetry();
        $('#exit-code-arm-hint').removeClass('text-success text-danger').addClass('text-muted')
            .text(opts.keepCode ? 'Nhập đủ 6 ký tự hoặc sửa mã rồi thử lại' : 'Nhập mã 6 ký tự để ĐT bắt đầu quét');
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
                    $('#exit-alert-icon').text('check_circle');
                    $('#exit-message').html('Đã cho phép xe ra!');
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
        entryShowingResult = false;
        $('#entry-error').hide();
        // Ảnh kết quả chỉ hiện ở cột Đối chiếu — không đè lên ô LIVE
        if (imageUrl) {
            $('#comp-entry-empty').hide();
            $('#comp-entry-img').attr('src', imageUrl).show();
        }
        $('#comp-entry-plate').text(plate || '-');
        $('#comp-exit-img').hide().attr('src', '');
        $('#comp-exit-empty').show();
        $('#comp-exit-plate').text('-');
        $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark')
            .text('Xe vào OK — chờ nhận diện xe ra...');
        $('#validation-buttons').hide();

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
        entryShowingResult = false;
        $('#entry-result').hide();

        if (alert.entry_image) {
            $('#comp-entry-empty').hide();
            $('#comp-entry-img').attr('src', alert.entry_image).show();
            $('#comp-entry-plate').text(alert.plate_number || '-');
        }

        $('#entry-error-title').text('Xe vẫn nằm trong bãi');
        $('#entry-error-msg').text(alert.message || 'Xe này chưa ra khỏi bãi — không thể nhận diện vào lần nữa.');
        $('#entry-error').show();

        if (opts.modal !== false) {
            showNotificationModal(false, 'Xe vẫn nằm trong bãi', alert.message || 'Xe này chưa ra khỏi bãi.');
        }

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
        // Ảnh chỉ vào cột Đối chiếu — ô Camera Xe Ra vẫn dành cho LIVE
        if (data.entry_image) {
            $('#comp-entry-empty').hide();
            $('#comp-entry-img').attr('src', data.entry_image).show();
        }
        $('#comp-entry-plate').text(data.entry_plate || '-');
        if (data.exit_image) {
            $('#comp-exit-empty').hide();
            $('#comp-exit-img').attr('src', data.exit_image).show();
        }
        $('#comp-exit-plate').text(data.exit_plate || '-');

        const alertBox = $('#exit-alert-box');
        $('#btn-exit-retry-inline').hide();
        $('#exit-code').prop('disabled', true);
        $('#exit-result').show();

        if (data.match) {
            // Biển khớp → đếm giây rồi tự động cho ra (giống làm mới sau xe vào)
            alertBox.removeClass('alert-danger alert-info').addClass('alert-success');
            $('#exit-alert-icon').text('check_circle');
            $('#exit-message').html(
                'Biển số khớp: <strong>' + (data.exit_plate || data.entry_plate || '') + '</strong>'
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
            $('#exit-alert-icon').text('warning');
            $('#exit-message').text(data.message || 'Biển số không khớp');
            $('#exit-auto-timer-wrap').addClass('d-none');
            $('#comp-status-badge').removeClass('bg-warning bg-success text-dark').addClass('bg-danger text-white')
                .text('BSX không trùng — cần xác nhận thủ công');
            $('#validation-buttons').show();
        }
    }

    function showExitError(message) {
        exitLockedByPending = false;
        $('#exit-code').prop('disabled', false);
        const alertBox = $('#exit-alert-box');
        alertBox.removeClass('alert-success alert-info').addClass('alert-danger');
        $('#exit-alert-icon').text('error');
        $('#exit-message').html(
            (message || 'Có lỗi xảy ra') +
            '<div class="small fw-normal mt-1">Sửa mã rồi để ĐT quét lại, hoặc bấm Làm lại.</div>'
        );
        $('#exit-auto-timer-wrap').addClass('d-none');
        $('#btn-exit-retry-inline').show();
        $('#exit-result').show();
        $('#validation-buttons').hide();
        $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark')
            .text('Lỗi — có thể làm lại');
    }

    function isPageReload() {
        try {
            const nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
            if (nav && nav.type === 'reload') return true;
        } catch (e) {}
        try {
            if (performance.navigation && performance.navigation.type === 1) return true;
        } catch (e) {}
        return false;
    }

    function pollMonitor() {
        $.get(API.monitor)
            .done(function(res) {
                if (!res || !res.success) return;

                // LIVE đã poll riêng — không apply ở đây để tránh đè chậm

                // Lần poll đầu: F5 → về mặc định; vào trang bình thường → giữ đối chiếu đang chờ
                if (!monitorBootstrapped) {
                    if (res.last_entry && res.last_entry.id) {
                        lastSeenEntryId = res.last_entry.id;
                    }
                    if (res.entry_alert && res.entry_alert.id) {
                        lastSeenEntryAlertId = String(res.entry_alert.id);
                    }

                    if (isPageReload()) {
                        // F5: hủy pending + mã kích hoạt, UI về mặc định
                        if (res.pending_validation && res.pending_validation.log_id) {
                            lastSeenPendingId = res.pending_validation.log_id;
                        } else {
                            lastSeenPendingId = null;
                        }
                        currentLogId = null;
                        exitLockedByPending = false;
                        clearExitTimer();
                        autoExitInProgress = false;
                        $('#exit-result').hide();
                        $('#validation-buttons').hide();
                        $('#exit-code').prop('disabled', false).val('');
                        $('#comp-entry-img, #comp-exit-img').hide().attr('src', '');
                        $('#comp-entry-empty, #comp-exit-empty').show();
                        $('#comp-entry-plate, #comp-exit-plate').text('-');
                        $('#comp-status-badge').removeClass('bg-success bg-danger text-white').addClass('bg-warning text-dark')
                            .text('Đang chờ nhận diện xe ra...');
                        lastArmedCode = '';
                        lastArmFailedCode = '';
                        lastArmConflictCode = '';
                        $('#exit-code-arm-hint').removeClass('text-success text-danger').addClass('text-muted')
                            .text('Nhập mã 6 ký tự để ĐT bắt đầu quét');

                        const clearJobs = [$.post(API.retryExit, {})];
                        if (res.armed_exit_code) {
                            clearJobs.push($.post(API.clearExit));
                        }
                        $.when.apply($, clearJobs).always(function() {
                            lastSeenPendingId = null;
                            monitorBootstrapped = true;
                        });
                    } else {
                        // Vào trang / tắt cam ĐT: giữ nguyên đối chiếu nếu đang chờ xác nhận
                        if (res.pending_validation && res.pending_validation.log_id) {
                            lastSeenPendingId = res.pending_validation.log_id;
                            showPendingValidation(res.pending_validation);
                        }
                        monitorBootstrapped = true;
                    }
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
                    // Chỉ hiện lượt mới — không hiện lại sau F5 (đã hủy pending lúc bootstrap)
                    if (pending.log_id !== lastSeenPendingId) {
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
                // Conflict: poll lại liên tục — bên kia xóa mã → xanh; đã quét xong → "Mã không tồn tại"
                const codeNow = ($('#exit-code').val() || '').trim().toUpperCase();
                if (codeNow.length === 6 && !res.armed_exit_code && !pending && !exitLockedByPending
                    && codeNow !== lastArmFailedCode) {
                    const wasConflict = (lastArmConflictCode === codeNow);
                    if (lastArmedCode === codeNow) lastArmedCode = '';
                    armExitCodeIfReady(wasConflict);
                } else if (res.armed_exit_code && codeNow === String(res.armed_exit_code).toUpperCase()) {
                    // Chỉ hiện xanh khi ô nhập vẫn đúng mã đang kích hoạt
                    lastArmedCode = String(res.armed_exit_code).toUpperCase();
                    lastArmFailedCode = '';
                    lastArmConflictCode = '';
                    stopConflictRetry();
                    $('#exit-code-arm-hint').removeClass('text-muted text-danger').addClass('text-success')
                        .text('Đã kích hoạt — mã ' + lastArmedCode);
                } else if (!codeNow) {
                    // User đã xóa mã trên ô nhập — tắt kích hoạt + về hint mặc định (không để poll kéo lại chữ xanh)
                    if (lastArmedCode || lastArmConflictCode || lastArmFailedCode
                        || res.armed_exit_code
                        || $('#exit-code-arm-hint').hasClass('text-success')
                        || $('#exit-code-arm-hint').hasClass('text-danger')) {
                        stopConflictRetry();
                        if (lastArmedCode || res.armed_exit_code) {
                            $.post(API.clearExit);
                        }
                        lastArmedCode = '';
                        lastArmFailedCode = '';
                        lastArmConflictCode = '';
                        $('#exit-code-arm-hint').removeClass('text-success text-danger').addClass('text-muted')
                            .text('Nhập mã 6 ký tự để ĐT bắt đầu quét');
                    }
                }
            })
            .always(function() {
                setTimeout(pollMonitor, MONITOR_POLL_MS);
            });
    }

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

    pollLive();
    pollRtcSide('entry');
    pollRtcSide('exit');
    pollMonitor();

    $('#exit-code').on('input', function() {
        this.value = (this.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
        armExitCodeIfReady();
    });
});
</script>
@endpush
