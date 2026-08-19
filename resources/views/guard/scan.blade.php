@extends('layouts.app')

@php
    $isExit = ($side ?? 'entry') === 'exit';
    $title = $isExit ? 'Quét Xe Ra' : 'Quét Xe Vào';
    $theme = $isExit ? 'danger' : 'primary';
@endphp

@section('title', $title)

@section('content')
<div class="container-fluid px-2" style="max-width: 640px;">
    <div class="card border-{{ $theme }} shadow-sm">
        <div class="card-header bg-{{ $theme }} text-white py-3">
            <h5 class="mb-0 text-white fw-bold">
                <i class="material-icons-outlined align-middle me-1">{{ $isExit ? 'logout' : 'login' }}</i>
                {{ $title }}
            </h5>
        </div>
        <div class="card-body p-2">
            <div class="bg-dark rounded overflow-hidden position-relative" style="height: 70vh; min-height: 320px;">
                <video id="scan-video" playsinline muted autoplay
                    style="width: 100%; height: 100%; object-fit: cover;"></video>
                <div class="position-absolute top-0 start-0 m-2">
                    <span class="badge bg-danger">
                        <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span>
                        LIVE
                    </span>
                </div>
                <div class="position-absolute bottom-0 start-0 end-0 m-2 text-center">
                    <span class="badge bg-dark bg-opacity-75 px-3 py-2 fs-6" id="scan-status">Đang mở camera...</span>
                </div>
            </div>
            <div id="scan-result" class="alert mt-2 mb-0 py-2" style="display:none;"></div>
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

    const SIDE = @json($side);
    const IS_EXIT = SIDE === 'exit';
    const SCAN_MS = 1200;
    const RETRY_MS = 2500;
    // LIVE: WebRTC ưu tiên; JPEG chỉ dự phòng / heartbeat
    const LIVE_PUSH_MS = 90;
    const LIVE_PUSH_BUSY_MS = 220;
    const LIVE_PUSH_RTC_MS = 2500;
    const LIVE_MAX_W = 360;
    const LIVE_QUALITY = 0.28;
    const RTC_POLL_MS = 280;
    const RTC_CONFIG = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };
    const API = {
        livePreview: @json(route('api.live_preview', [], false)),
        webrtcSignal: @json(route('guard.webrtc_signal_post', [], false)),
        webrtcPoll: @json(route('guard.webrtc_signal_poll', [], false)),
        armedCode: @json(route('api.armed_exit_code', [], false)),
        detect: @json(route('api.detect_preview', [], false)),
        preview: @json(route('api.recognize_preview', [], false)),
        entry: @json(route('api.recognize_entry', [], false)),
        exit: @json(route('api.checkout_exit', [], false)),
        scanHold: @json(route('api.scan_hold', [], false))
    };

    let stream = null;
    let scanTimer = null;
    let liveTimer = null;
    let armedPollTimer = null;
    let rtcPollTimer = null;
    let holdPollTimer = null;
    let scanning = false;
    let busy = false;
    let livePushing = false;
    let liveNeedsPush = false;
    let liveStarted = false;
    let liveBlocked = false;
    let liveClaimed = false;
    let liveWaitTimer = null;
    let liveWaiting = false;
    let cameraStarting = false;
    let rtcPc = null;
    let rtcSession = null;
    let rtcAfter = 0;
    let rtcConnected = false;
    let armedCode = null;
    let cooldownUntil = 0;
    let ocrPausedUntil = 0;
    let currentScanPhase = 'detect';
    let pauseTickTimer = null;
    let scanAttempt = 0;
    let insideHold = false;
    const FRONTEND_HOLD_S = 10;
    const CLAIM_RETRY_MS = 1000;
    const canvas = document.createElement('canvas');
    const liveCanvas = document.createElement('canvas');
    const liveCtx = liveCanvas.getContext('2d', { alpha: false });
    const video = document.getElementById('scan-video');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    function getLiveDeviceId() {
        const key = 'ndbs_live_device_id';
        try {
            let id = localStorage.getItem(key);
            if (id && /^[a-zA-Z0-9_-]{8,64}$/.test(id)) return id;
            id = 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
            localStorage.setItem(key, id);
            return id;
        } catch (e) {
            return 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        }
    }
    const DEVICE_ID = getLiveDeviceId();

    function setStatus(text) {
        $('#scan-status').text(text);
    }

    function setScanPhase(phase) {
        phase = phase || 'detect';
        if (currentScanPhase === phase) return;
        currentScanPhase = phase;
        if (!stream || liveBlocked) return;
        fetch(API.livePreview
            + '?side=' + encodeURIComponent(SIDE)
            + '&device_id=' + encodeURIComponent(DEVICE_ID)
            + '&phase=' + encodeURIComponent(phase), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'X-Live-Device-Id': DEVICE_ID
            }
        }).catch(function() {});
    }

    function showResult(ok, html) {
        const box = $('#scan-result');
        box.removeClass('alert-success alert-danger alert-warning')
            .addClass(ok === true ? 'alert-success' : (ok === 'warn' ? 'alert-warning' : 'alert-danger'))
            .html(html)
            .show();
    }

    function clearLiveWait() {
        if (liveWaitTimer) {
            clearTimeout(liveWaitTimer);
            liveWaitTimer = null;
        }
        liveWaiting = false;
    }

    function enterLiveWaitQueue(message) {
        const wasClaimed = liveClaimed;
        liveBlocked = true;
        liveClaimed = false;
        liveWaiting = true;
        const msg = message || 'Đang có thiết bị khác kết nối camera này';
        setStatus(msg + ' — đang chờ nhả quyền...');
        showResult('warn',
            '<i class="material-icons-outlined align-middle me-1" style="font-size:18px;">hourglass_top</i> '
            + msg + '<br><small>Sẽ tự kết nối khi thiết bị kia rời trang hoặc đăng xuất.</small>'
        );
        // Không nhả khóa của thiết bị đang giữ
        stopCameraFully(wasClaimed);
        scheduleLiveClaimRetry(CLAIM_RETRY_MS);
    }

    function scheduleLiveClaimRetry(delay) {
        if (liveWaitTimer) clearTimeout(liveWaitTimer);
        if (!liveWaiting) return;
        liveWaitTimer = setTimeout(retryLiveClaimFromQueue, typeof delay === 'number' ? delay : CLAIM_RETRY_MS);
    }

    function retryLiveClaimFromQueue() {
        if (!liveWaiting) return;
        setStatus('Đang chờ thiết bị khác nhả camera...');
        rtcPost('claim', null).then(function(res) {
            if (!liveWaiting) return;
            if (res && res.conflict) {
                scheduleLiveClaimRetry(CLAIM_RETRY_MS);
                return;
            }
            if (!res || res.success === false) {
                scheduleLiveClaimRetry(CLAIM_RETRY_MS);
                return;
            }
            // Được quyền → tự mở camera, không cần F5
            clearLiveWait();
            liveBlocked = false;
            liveClaimed = true;
            $('#scan-result').fadeOut();
            setStatus('Đã nhận quyền LIVE — đang mở camera...');
            openCameraAfterClaim();
        }).catch(function() {
            if (liveWaiting) scheduleLiveClaimRetry(CLAIM_RETRY_MS);
        });
    }

    function stopCameraFully(releaseLock) {
        if (armedPollTimer) {
            clearTimeout(armedPollTimer);
            armedPollTimer = null;
        }
        if (liveTimer) {
            clearTimeout(liveTimer);
            liveTimer = null;
        }
        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }
        stopRtcPublisher(!!releaseLock);
        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
        scanning = false;
        busy = false;
        cameraStarting = false;
    }

    /** Nhả khóa ngay khi thoát trang / đổi menu (fetch keepalive — không cần F5 bên kia) */
    function releaseLiveSlotKeepalive() {
        clearLiveWait();
        if (!liveClaimed && !rtcSession) return;
        const session = rtcSession || ('claim_' + DEVICE_ID);
        liveClaimed = false;
        rtcSession = null;
        try {
            const token = ($('meta[name="csrf-token"]').attr('content') || csrfToken || '');
            fetch(API.webrtcSignal, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    side: SIDE,
                    from: 'phone',
                    type: 'bye',
                    session: session,
                    device_id: DEVICE_ID,
                    payload: ''
                })
            });
        } catch (e) {}
    }

    function captureFrame(maxW, quality, targetCanvas) {
        if (!video || video.readyState < 2 || !video.videoWidth) return Promise.resolve(null);
        let w = video.videoWidth, h = video.videoHeight;
        const limit = maxW || 1280;
        if (w > limit) { h = Math.round(h * (limit / w)); w = limit; }
        const c = targetCanvas || canvas;
        c.width = w; c.height = h;
        const ctx = (c === liveCanvas) ? liveCtx : c.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);
        return new Promise(function(resolve) {
            c.toBlob(function(b) { resolve(b); }, 'image/jpeg', quality || 0.9);
        });
    }

    function canScanNow() {
        if (insideHold) return false;
        if (Date.now() < ocrPausedUntil) return false;
        return !!(stream && !busy && (!IS_EXIT || armedCode));
    }

    /** Đảm bảo vòng quét luôn chạy khi đã có mã (tránh bị đứt im lặng) */
    function ensureScanLoop(delay) {
        if (!canScanNow()) return;
        if (scanning) return;
        scheduleScan(typeof delay === 'number' ? delay : 150);
    }

    function scheduleLivePush(delay) {
        if (liveTimer) clearTimeout(liveTimer);
        liveTimer = setTimeout(pushLiveFrame, typeof delay === 'number' ? delay : LIVE_PUSH_MS);
    }

    function liveDelay() {
        if (insideHold) return 400;
        // Còn PC WebRTC (kể cả disconnected tạm) → JPEG chỉ heartbeat
        if (rtcConnected || (rtcPc && rtcPc.connectionState !== 'failed' && rtcPc.connectionState !== 'closed')) {
            return LIVE_PUSH_RTC_MS;
        }
        if (scanning || busy) return LIVE_PUSH_BUSY_MS;
        return LIVE_PUSH_MS;
    }

    function pushLiveFrame() {
        if (liveTimer) {
            clearTimeout(liveTimer);
            liveTimer = null;
        }
        if (!stream || liveBlocked) return;

        // Đang detect/OCR + RTC còn sống → không spam JPEG (tránh nghẽn Wi‑Fi làm rớt LIVE RTC)
        // Đang chờ đếm 10s trên PC (busy + ocrPausedUntil) → vẫn heartbeat để nhận pause_ocr_s
        const rtcAlive = !!(rtcPc && rtcPc.connectionState !== 'failed' && rtcPc.connectionState !== 'closed');
        const holdingFrontend = busy && Date.now() < ocrPausedUntil;
        if (rtcAlive && (scanning || (busy && !holdingFrontend && !insideHold))) {
            scheduleLivePush(LIVE_PUSH_RTC_MS);
            return;
        }

        if (livePushing) {
            liveNeedsPush = true;
            return;
        }

        livePushing = true;
        liveNeedsPush = false;
        liveStarted = true;

        captureFrame(LIVE_MAX_W, LIVE_QUALITY, liveCanvas)
            .then(function(blob) {
                if (!blob || !stream || liveBlocked) return null;
                return fetch(API.livePreview
                    + '?side=' + encodeURIComponent(SIDE)
                    + '&device_id=' + encodeURIComponent(DEVICE_ID)
                    + '&phase=' + encodeURIComponent(currentScanPhase), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'image/jpeg',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Live-Device-Id': DEVICE_ID
                    },
                    body: blob
                }).then(function(r) {
                    if (r.status === 409) {
                        return r.json().catch(function() { return null; }).then(function(body) {
                            enterLiveWaitQueue((body && body.message) || null);
                            return null;
                        });
                    }
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json().catch(function() { return null; });
                }).then(function(body) {
                    if (body && body.hold_scan) {
                        enterInsideHold();
                    } else if (insideHold) {
                        releaseInsideHold();
                    } else if (body && Number(body.pause_ocr_s) > 0) {
                        const until = Date.now() + (Number(body.pause_ocr_s) * 1000);
                        if (until > ocrPausedUntil) ocrPausedUntil = until;
                        scanning = false;
                        if (scanTimer) {
                            clearTimeout(scanTimer);
                            scanTimer = null;
                        }
                        if (pauseTickTimer) {
                            setScanPhase('detect');
                        } else {
                            holdForFrontendCountdown(Math.ceil((ocrPausedUntil - Date.now()) / 1000));
                        }
                    }
                    return body;
                });
            })
            .catch(function(err) {
                console.warn('live-preview error', err);
            })
            .finally(function() {
                livePushing = false;
                if (!stream || liveBlocked) return;
                if (liveNeedsPush && !rtcConnected && !rtcAlive) {
                    pushLiveFrame();
                    return;
                }
                scheduleLivePush(liveDelay());
            });
    }

    function rtcEncode(obj) {
        return btoa(unescape(encodeURIComponent(JSON.stringify(obj))));
    }

    function rtcDecode(payload) {
        return JSON.parse(decodeURIComponent(escape(atob(payload))));
    }

    function rtcPost(type, payloadObj, forceSession) {
        if (!rtcSession && type !== 'claim' && !forceSession) return Promise.resolve(null);
        const session = rtcSession || forceSession || ('claim_' + DEVICE_ID);
        return fetch(API.webrtcSignal, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                side: SIDE,
                from: 'phone',
                type: type,
                session: session,
                device_id: DEVICE_ID,
                payload: payloadObj ? rtcEncode(payloadObj) : ''
            })
        }).then(function(r) {
            return r.json().catch(function() { return null; }).then(function(body) {
                if (r.status === 409 || (body && body.conflict)) {
                    return {
                        success: false,
                        conflict: true,
                        message: (body && body.message) || 'Đang có thiết bị khác kết nối camera này'
                    };
                }
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return body;
            });
        }).catch(function(err) {
            console.warn('webrtc post', err);
            return null;
        });
    }

    function claimLiveSlot() {
        return rtcPost('claim', null).then(function(res) {
            if (res && res.conflict) {
                enterLiveWaitQueue(res.message);
                return false;
            }
            if (!res || res.success === false) {
                enterLiveWaitQueue('Không xác nhận được quyền LIVE — đang thử lại...');
                return false;
            }
            liveClaimed = true;
            liveBlocked = false;
            clearLiveWait();
            return true;
        });
    }

    function stopRtcPublisher(releaseLock) {
        if (rtcPollTimer) {
            clearTimeout(rtcPollTimer);
            rtcPollTimer = null;
        }
        const oldSession = rtcSession;
        if (oldSession) {
            rtcPost('bye', null, oldSession);
            liveClaimed = false;
        } else if (releaseLock && liveClaimed) {
            rtcPost('bye', null, 'claim_' + DEVICE_ID);
            liveClaimed = false;
        }
        if (rtcPc) {
            try { rtcPc.close(); } catch (e) {}
            rtcPc = null;
        }
        rtcSession = null;
        rtcAfter = 0;
        rtcConnected = false;
    }

    async function startRtcPublisher() {
        if (!stream || !window.RTCPeerConnection || liveBlocked) return;
        stopRtcPublisher();

        rtcSession = 'p' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
        rtcAfter = 0;
        rtcConnected = false;

        const pc = new RTCPeerConnection(RTC_CONFIG);
        rtcPc = pc;

        stream.getTracks().forEach(function(track) {
            pc.addTrack(track, stream);
        });

        // Ưu tiên bitrate thấp cho LAN mượt, ít tốn băng thông
        try {
            const sender = pc.getSenders().find(function(s) { return s.track && s.track.kind === 'video'; });
            if (sender && sender.getParameters) {
                const params = sender.getParameters();
                if (!params.encodings) params.encodings = [{}];
                params.encodings[0].maxBitrate = 600000;
                params.encodings[0].maxFramerate = 24;
                await sender.setParameters(params);
            }
        } catch (e) {}

        pc.onicecandidate = function(ev) {
            if (!ev.candidate || !rtcSession) return;
            const c = ev.candidate.toJSON ? ev.candidate.toJSON() : ev.candidate;
            rtcPost('ice', c);
        };
        pc.onconnectionstatechange = function() {
            const cs = pc.connectionState;
            if (cs === 'connected') {
                rtcConnected = true;
                setStatus(IS_EXIT
                    ? 'LIVE RTC → PC | Chờ quẹt mã...'
                    : 'LIVE RTC → PC | Đang chờ biển số...');
            } else if (cs === 'failed' || cs === 'closed') {
                // Không tắt RTC khi 'disconnected' tạm (lúc upload ảnh OCR)
                rtcConnected = false;
                if (cs === 'failed') {
                    try { if (pc.restartIce) pc.restartIce(); } catch (e) {}
                }
            }
        };

        try {
            const offer = await pc.createOffer({ offerToReceiveAudio: false, offerToReceiveVideo: false });
            await pc.setLocalDescription(offer);
            const offerRes = await rtcPost('offer', { type: offer.type, sdp: offer.sdp });
            if (offerRes && offerRes.conflict) {
                enterLiveWaitQueue(offerRes.message);
                return;
            }
            pollRtcPhone();
        } catch (e) {
            console.warn('webrtc offer fail', e);
            stopRtcPublisher();
        }
    }

    function pollRtcPhone() {
        if (rtcPollTimer) clearTimeout(rtcPollTimer);
        if (!rtcSession) return;

        fetch(API.webrtcPoll + '?side=' + encodeURIComponent(SIDE)
            + '&role=phone&after=' + rtcAfter
            + '&session=' + encodeURIComponent(rtcSession)
            + '&_=' + Date.now(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function(res) {
            if (!res || !res.success) return;
            if (!res.session || res.session !== rtcSession) return;
            (res.messages || []).forEach(function(m) {
                rtcAfter = Math.max(rtcAfter, Number(m.id) || 0);
                let data = null;
                try {
                    data = m.payload ? rtcDecode(m.payload) : null;
                } catch (e) {
                    return;
                }
                if (!rtcPc) return;
                if (m.type === 'answer' && data) {
                    rtcPc.setRemoteDescription(data).catch(function(err) {
                        console.warn('setRemote answer', err);
                    });
                } else if (m.type === 'ice' && data) {
                    rtcPc.addIceCandidate(data).catch(function() {});
                }
            });
        }).catch(function(err) {
            console.warn('webrtc poll', err);
        }).finally(function() {
            if (rtcSession) {
                rtcPollTimer = setTimeout(pollRtcPhone, RTC_POLL_MS);
            }
        });
    }

    function waitForVideoReady(timeoutMs) {
        return new Promise(function(resolve) {
            const start = Date.now();
            (function check() {
                if (video && video.readyState >= 2 && video.videoWidth > 0) {
                    resolve(true);
                    return;
                }
                if (Date.now() - start > (timeoutMs || 8000)) {
                    resolve(false);
                    return;
                }
                setTimeout(check, 100);
            })();
        });
    }

    async function openCameraStream() {
        const attempts = [
            { audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } } },
            { audio: false, video: { facingMode: 'environment' } },
            { audio: false, video: true }
        ];
        let lastErr = null;
        for (let i = 0; i < attempts.length; i++) {
            try {
                return await navigator.mediaDevices.getUserMedia(attempts[i]);
            } catch (e) {
                lastErr = e;
            }
        }
        throw lastErr || new Error('getUserMedia failed');
    }

    function scheduleScan(delay) {
        if (scanTimer) clearTimeout(scanTimer);
        scanTimer = setTimeout(runScan, typeof delay === 'number' ? delay : SCAN_MS);
    }

    function clearPauseTick() {
        if (pauseTickTimer) {
            clearInterval(pauseTickTimer);
            pauseTickTimer = null;
        }
    }

    function holdForFrontendCountdown(seconds) {
        clearPauseTick();
        const holdS = Math.max(1, parseInt(seconds, 10) || FRONTEND_HOLD_S);
        ocrPausedUntil = Math.max(ocrPausedUntil, Date.now() + holdS * 1000);
        busy = true;
        scanning = false;
        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }
        setScanPhase('detect');

        function tick() {
            const left = Math.ceil((ocrPausedUntil - Date.now()) / 1000);
            if (left <= 0) {
                clearPauseTick();
                $('#scan-result').fadeOut();
                setStatus(IS_EXIT
                    ? 'Chờ quẹt mã (nhập trên máy tính)...'
                    : 'LIVE → màn giám sát | Đang chờ biển số...');
                resumeAfterAttempt(300);
                return;
            }
            setStatus('Chờ máy tính đếm ' + left + 's rồi quét tiếp...');
        }
        tick();
        pauseTickTimer = setInterval(tick, 250);
    }

    function enterInsideHold() {
        if (insideHold) {
            scheduleHoldPoll();
            return;
        }
        insideHold = true;
        busy = true;
        scanning = false;
        clearPauseTick();
        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }
        setScanPhase('detect');
        setStatus('Xe vẫn trong bãi — chờ máy tính bấm Đồng ý...');
        scheduleHoldPoll();
    }

    function scheduleHoldPoll() {
        if (holdPollTimer) clearTimeout(holdPollTimer);
        if (!insideHold) return;
        holdPollTimer = setTimeout(pollScanHold, 400);
    }

    function pollScanHold() {
        holdPollTimer = null;
        if (!insideHold || !stream) return;
        fetch(API.scanHold + '?t=' + Date.now(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function(res) {
            if (insideHold && res && res.hold_scan === false) {
                releaseInsideHold();
                return;
            }
        }).catch(function() {
            // ignore, poll lại
        }).finally(function() {
            if (insideHold) scheduleHoldPoll();
        });
    }

    function releaseInsideHold() {
        if (!insideHold) return;
        insideHold = false;
        if (holdPollTimer) {
            clearTimeout(holdPollTimer);
            holdPollTimer = null;
        }
        $('#scan-result').fadeOut();
        setStatus(IS_EXIT
            ? 'Chờ quẹt mã (nhập trên máy tính)...'
            : 'LIVE → màn giám sát | Đang chờ biển số...');
        resumeAfterAttempt(300);
    }

    function resumeAfterAttempt(delay) {
        if (insideHold) return;
        if (Date.now() < ocrPausedUntil) {
            holdForFrontendCountdown(Math.ceil((ocrPausedUntil - Date.now()) / 1000));
            return;
        }
        clearPauseTick();
        busy = false;
        scanning = false;
        cooldownUntil = Date.now() + (delay || RETRY_MS);
        setScanPhase('detect');
        ensureScanLoop(delay || RETRY_MS);
    }

    function onArmedCode(next) {
        const prev = armedCode;
        armedCode = next || null;

        if (!busy && stream) {
            if (armedCode) {
                if (!scanning) {
                    setStatus('Mã ' + armedCode + ' — đang chờ biển...');
                }
            } else {
                setStatus('Chờ quẹt mã (nhập trên máy tính)...');
            }
        }

        if (armedCode) {
            ensureScanLoop(armedCode !== prev ? 80 : 200);
        } else if (IS_EXIT) {
            setStatus('Chờ quẹt mã (nhập trên máy tính)...');
        }
    }

    function pollArmedCode() {
        if (!IS_EXIT) return;
        if (armedPollTimer) clearTimeout(armedPollTimer);

        fetch(API.armedCode + '?t=' + Date.now(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function(res) {
            if (!res || res.success === false) {
                setStatus('Lỗi đọc mã — reload trang');
                return;
            }
            if (!busy) {
                onArmedCode(res.armed_exit_code ? String(res.armed_exit_code).toUpperCase() : null);
            }
        }).catch(function(err) {
            setStatus('Không nối máy tính: ' + (err && err.message ? err.message : 'lỗi mạng'));
        }).finally(function() {
            armedPollTimer = setTimeout(pollArmedCode, armedCode ? 1200 : 500);
        });
    }

    function runScan() {
        scanTimer = null;
        if (!stream) return;
        if (insideHold) return;
        if (busy) return;
        if (IS_EXIT && !armedCode) {
            setStatus('Chờ quẹt mã (nhập trên máy tính)...');
            return;
        }
        if (scanning) {
            scheduleScan(250);
            return;
        }
        if (Date.now() < cooldownUntil) {
            scheduleScan(Math.max(200, cooldownUntil - Date.now()));
            return;
        }
        if (Date.now() < ocrPausedUntil) {
            if (!pauseTickTimer) {
                holdForFrontendCountdown(Math.ceil((ocrPausedUntil - Date.now()) / 1000));
            }
            return;
        }

        scanning = true;
        const codeForThisScan = armedCode;
        scanAttempt += 1;
        setScanPhase('detect');
        setStatus(IS_EXIT
            ? ('Mã ' + codeForThisScan + ' — đang tìm biển... #' + scanAttempt)
            : ('Đang tìm biển số... #' + scanAttempt));

        captureFrame(960, 0.75, canvas).then(function(blob) {
            if (!blob || !stream || busy) {
                scanning = false;
                setStatus(IS_EXIT
                    ? ('Mã ' + (armedCode || '') + ' — camera chưa sẵn, thử lại...')
                    : 'Camera chưa sẵn, thử lại...');
                ensureScanLoop(400);
                return;
            }

            // Bước 1: chỉ detect khung biển (không OCR ký tự)
            const fdDetect = new FormData();
            fdDetect.append('image', blob, 'detect.jpg');
            let startedOcr = false;
            $.ajax({
                url: API.detect,
                type: 'POST',
                data: fdDetect,
                processData: false,
                contentType: false,
                timeout: 30000
            }).done(function(det) {
                if (!stream || busy) return;
                if (!det || !det.detected) {
                    setScanPhase('detect');
                    setStatus(IS_EXIT
                        ? ('Mã ' + (armedCode || codeForThisScan) + ' — chưa thấy biển (#' + scanAttempt + ')')
                        : ('Chưa thấy biển số (#' + scanAttempt + ')'));
                    return;
                }

                // Bước 2: đã chắc là biển → mới đọc ký tự
                startedOcr = true;
                setScanPhase('ocr');
                setStatus(IS_EXIT
                    ? ('Mã ' + codeForThisScan + ' — thấy biển, đang đọc ký tự...')
                    : 'Thấy biển — đang đọc ký tự...');

                const fdOcr = new FormData();
                fdOcr.append('image', blob, 'preview.jpg');
                $.ajax({
                    url: API.preview,
                    type: 'POST',
                    data: fdOcr,
                    processData: false,
                    contentType: false,
                    timeout: 60000
                }).done(function(res) {
                    if (!stream || busy) return;
                    if (res && res.success && res.plate_number) {
                        const plate = String(res.plate_number).toUpperCase();
                        setScanPhase('saving');
                        setStatus(plate + ' — đang lưu...');
                        busy = true;
                        scanning = false;
                        if (IS_EXIT) submitExit(blob, codeForThisScan, plate);
                        else submitEntry(blob, plate);
                        return;
                    }
                    const why = (res && res.message) ? String(res.message) : 'chưa đọc được ký tự';
                    setScanPhase('detect');
                    setStatus(IS_EXIT
                        ? ('Mã ' + (armedCode || codeForThisScan) + ' — ' + why)
                        : why);
                }).fail(function(xhr) {
                    const st = xhr && xhr.status ? ('HTTP ' + xhr.status) : 'lỗi mạng';
                    setScanPhase('detect');
                    setStatus(IS_EXIT
                        ? ('Mã ' + (armedCode || '') + ' — lỗi đọc ký tự ' + st)
                        : ('Lỗi đọc ký tự ' + st));
                }).always(function() {
                    if (!busy) {
                        scanning = false;
                        setScanPhase('detect');
                        ensureScanLoop(SCAN_MS);
                    }
                });
            }).fail(function(xhr) {
                const st = xhr && xhr.status ? ('HTTP ' + xhr.status) : 'lỗi mạng';
                setScanPhase('detect');
                setStatus(IS_EXIT
                    ? ('Mã ' + (armedCode || '') + ' — lỗi tìm biển ' + st)
                    : ('Lỗi tìm biển ' + st));
            }).always(function() {
                if (!startedOcr && !busy) {
                    scanning = false;
                    setScanPhase('detect');
                    ensureScanLoop(SCAN_MS);
                }
            });
        }).catch(function() {
            scanning = false;
            setScanPhase('detect');
            setStatus('Lỗi chụp frame — thử lại...');
            ensureScanLoop(500);
        });
    }

    function submitEntry(blob, plateHint) {
        const fd = new FormData();
        fd.append('image', blob, 'entry_capture.jpg');
        $.ajax({
            url: API.entry,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(res) {
            if (res && res.success) {
                if (res.monthly_pending) {
                    showResult(true,
                        '<strong>Chờ xác nhận vé tháng</strong><br>Biển: <b>' + (res.plate_number || plateHint) +
                        '</b><br>' + (res.message || 'BSX không khớp — chờ máy tính xác nhận.')
                    );
                    holdForFrontendCountdown(res.pause_ocr_s || FRONTEND_HOLD_S);
                    return;
                }
                showResult(true,
                    '<strong>Xe vào OK</strong><br>Biển: <b>' + (res.plate_number || plateHint) +
                    '</b><br>Mã code: <b class="text-primary">' + res.code + '</b>'
                );
                holdForFrontendCountdown(res.pause_ocr_s || FRONTEND_HOLD_S);
            } else if (res && res.already_inside) {
                showResult(false,
                    '<strong>Xe vẫn trong bãi</strong><br>' +
                    (res.message || 'Biển này chưa ra khỏi bãi — không thể vào lại.')
                );
                enterInsideHold();
            } else {
                showResult(false, (res && res.message) || 'Nhận diện thất bại — sẽ quét lại');
                setStatus('Lỗi — đang quét lại...');
                resumeAfterAttempt(RETRY_MS);
            }
        }).fail(function() {
            showResult(false, 'Lỗi kết nối máy chủ — sẽ quét lại');
            setStatus('Lỗi — đang quét lại...');
            resumeAfterAttempt(RETRY_MS);
        });
    }

    function waitForNewCode(msg) {
        armedCode = null;
        liveStarted = false;
        busy = false;
        scanning = false;
        setScanPhase('detect');
        showResult(false, msg || 'Sai mã / lỗi — chờ máy tính nhập lại mã');
        setStatus('Chờ quẹt mã (nhập trên máy tính)...');
    }

    function submitExit(blob, code, plateHint) {
        if (!code) {
            waitForNewCode('Mất mã code — chờ máy tính nhập lại');
            return;
        }
        armedCode = code;

        const fd = new FormData();
        fd.append('image', blob, 'exit_capture.jpg');
        fd.append('code', code);
        $.ajax({
            url: API.exit,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(res) {
            if (res && res.success) {
                armedCode = null;
                liveStarted = false;
                showResult(true,
                    '<strong>Đã gửi đối chiếu</strong><br>Biển ra: <b>' + (res.exit_plate || plateHint) +
                    '</b><br>Máy tính: biển khớp sẽ tự cho ra'
                );
                holdForFrontendCountdown(res.pause_ocr_s || FRONTEND_HOLD_S);
            } else {
                const msg = (res && res.message) || 'Đối chiếu thất bại';
                // Sai mã / bị clear trên PC → chờ nhập lại. Lỗi biển (keep_armed) → quét lại.
                if (res && res.keep_armed) {
                    showResult(false, msg + ' — đang quét lại biển...');
                    setStatus('Mã ' + code + ' — lỗi biển, đang quét lại...');
                    resumeAfterAttempt(RETRY_MS);
                } else {
                    waitForNewCode(msg);
                }
            }
        }).fail(function(xhr) {
            const body = xhr.responseJSON || {};
            const msg = body.message || 'Lỗi kết nối máy chủ';
            if (body.keep_armed) {
                showResult(false, msg + ' — đang quét lại biển...');
                setStatus('Mã ' + code + ' — lỗi, đang quét lại...');
                resumeAfterAttempt(RETRY_MS);
            } else {
                waitForNewCode(msg + ' — chờ nhập lại mã trên máy tính');
            }
        });
    }

    async function openCameraAfterClaim() {
        if (cameraStarting || liveBlocked || liveWaiting) return;
        cameraStarting = true;
        try {
            setStatus('Đang mở camera...');
            stream = await openCameraStream();
            video.setAttribute('playsinline', 'true');
            video.setAttribute('muted', 'true');
            video.muted = true;
            video.srcObject = stream;
            await video.play().catch(function() {});
            const ready = await waitForVideoReady(8000);
            if (liveBlocked || liveWaiting) {
                stopCameraFully(true);
                return;
            }
            if (!ready) {
                setStatus('Camera mở nhưng chưa sẵn sàng — thử tải lại trang');
            } else {
                setScanPhase('detect');
                setStatus(IS_EXIT ? 'LIVE → màn giám sát | Chờ quẹt mã...' : 'LIVE → màn giám sát | Đang chờ biển số...');
            }
            pushLiveFrame();
            startRtcPublisher();
            if (IS_EXIT) {
                pollArmedCode();
            } else {
                scheduleScan();
            }
        } catch (e) {
            console.error(e);
            setStatus('Không mở được camera — cấp quyền camera + dùng HTTPS');
            stopCameraFully(true);
        } finally {
            cameraStarting = false;
        }
    }

    async function startCamera() {
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            location.href = 'https://' + location.host + location.pathname + location.search;
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Trình duyệt không hỗ trợ camera / cần HTTPS');
            return;
        }
        try {
            setStatus('Đang kiểm tra kết nối LIVE...');
            const claimed = await claimLiveSlot();
            if (!claimed) return;
            await openCameraAfterClaim();
        } catch (e) {
            console.error(e);
            setStatus('Không mở được camera — cấp quyền camera + dùng HTTPS');
        }
    }

    function onLeaveScanPage() {
        setScanPhase('idle');
        releaseLiveSlotKeepalive();
        // Dọn local; khóa đã gửi bye keepalive ở trên
        if (armedPollTimer) {
            clearTimeout(armedPollTimer);
            armedPollTimer = null;
        }
        if (holdPollTimer) {
            clearTimeout(holdPollTimer);
            holdPollTimer = null;
        }
        if (liveTimer) {
            clearTimeout(liveTimer);
            liveTimer = null;
        }
        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }
        clearPauseTick();
        if (rtcPollTimer) {
            clearTimeout(rtcPollTimer);
            rtcPollTimer = null;
        }
        if (rtcPc) {
            try { rtcPc.close(); } catch (e) {}
            rtcPc = null;
        }
        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
    }

    // pagehide tin cậy hơn beforeunload trên mobile / khi bấm menu
    window.addEventListener('pagehide', onLeaveScanPage);
    window.addEventListener('beforeunload', onLeaveScanPage);

    // Đăng xuất: nhả LIVE trước khi submit form
    const prevLogout = window.ndbsLogout;
    window.ndbsLogout = function() {
        releaseLiveSlotKeepalive();
        if (typeof prevLogout === 'function') return prevLogout.apply(this, arguments);
        const form = document.getElementById('logout-form');
        if (form) form.submit();
    };

    startCamera();
});
</script>
@endpush
