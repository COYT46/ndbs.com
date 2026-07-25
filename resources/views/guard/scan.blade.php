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
    const LIVE_PUSH_MS = 450;
    const API = {
        livePreview: @json(route('api.live_preview', [], false)),
        armedCode: @json(route('api.armed_exit_code', [], false)),
        detect: @json(route('api.detect_preview', [], false)),
        preview: @json(route('api.recognize_preview', [], false)),
        entry: @json(route('api.recognize_entry', [], false)),
        exit: @json(route('api.checkout_exit', [], false))
    };

    let stream = null;
    let scanTimer = null;
    let liveTimer = null;
    let armedPollTimer = null;
    let scanning = false;
    let busy = false;
    let livePushing = false;
    let liveStarted = false;
    let armedCode = null;
    let cooldownUntil = 0;
    let scanAttempt = 0;
    const canvas = document.createElement('canvas');
    const liveCanvas = document.createElement('canvas');
    const video = document.getElementById('scan-video');

    function setStatus(text) {
        $('#scan-status').text(text);
    }

    function showResult(ok, html) {
        const box = $('#scan-result');
        box.removeClass('alert-success alert-danger alert-warning')
            .addClass(ok ? 'alert-success' : 'alert-danger')
            .html(html)
            .show();
    }

    function captureFrame(maxW, quality, targetCanvas) {
        if (!video || video.readyState < 2 || !video.videoWidth) return Promise.resolve(null);
        let w = video.videoWidth, h = video.videoHeight;
        const limit = maxW || 1280;
        if (w > limit) { h = Math.round(h * (limit / w)); w = limit; }
        const c = targetCanvas || canvas;
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(video, 0, 0, w, h);
        return new Promise(function(resolve) {
            c.toBlob(function(b) { resolve(b); }, 'image/jpeg', quality || 0.9);
        });
    }

    function canScanNow() {
        return !!(stream && !busy && (!IS_EXIT || armedCode));
    }

    /** Đảm bảo vòng quét luôn chạy khi đã có mã (tránh bị đứt im lặng) */
    function ensureScanLoop(delay) {
        if (!canScanNow()) return;
        if (scanning) return;
        scheduleScan(typeof delay === 'number' ? delay : 150);
    }

    function pushLiveFrame() {
        if (liveTimer) clearTimeout(liveTimer);
        if (!stream) return;

        // Xe ra: chưa có mã thì không đẩy LIVE lên server
        if (IS_EXIT && !armedCode) {
            liveStarted = false;
            liveTimer = setTimeout(pushLiveFrame, 1000);
            return;
        }

        if (livePushing) {
            liveTimer = setTimeout(pushLiveFrame, LIVE_PUSH_MS);
            return;
        }

        livePushing = true;
        liveStarted = true;
        captureFrame(640, 0.5, liveCanvas).then(function(blob) {
            if (!blob || !stream) return;
            // Đang OCR thì bỏ frame LIVE này, ưu tiên nhận diện
            if (scanning || busy) return;
            const fd = new FormData();
            fd.append('image', blob, 'live.jpg');
            fd.append('side', SIDE);
            return $.ajax({
                url: API.livePreview,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false
            });
        }).always(function() {
            livePushing = false;
            if (stream) liveTimer = setTimeout(pushLiveFrame, IS_EXIT ? 900 : LIVE_PUSH_MS);
        });
    }

    function scheduleScan(delay) {
        if (scanTimer) clearTimeout(scanTimer);
        scanTimer = setTimeout(runScan, typeof delay === 'number' ? delay : SCAN_MS);
    }

    function resumeAfterAttempt(delay) {
        busy = false;
        scanning = false;
        cooldownUntil = Date.now() + (delay || RETRY_MS);
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
            if (armedCode !== prev && !liveStarted) pushLiveFrame();
            ensureScanLoop(armedCode !== prev ? 80 : 200);
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

        scanning = true;
        const codeForThisScan = armedCode;
        scanAttempt += 1;
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
                    setStatus(IS_EXIT
                        ? ('Mã ' + (armedCode || codeForThisScan) + ' — chưa thấy biển (#' + scanAttempt + ')')
                        : ('Chưa thấy biển số (#' + scanAttempt + ')'));
                    return;
                }

                // Bước 2: đã chắc là biển → mới đọc ký tự
                startedOcr = true;
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
                        setStatus(plate + ' — đang lưu...');
                        busy = true;
                        scanning = false;
                        if (IS_EXIT) submitExit(blob, codeForThisScan, plate);
                        else submitEntry(blob, plate);
                        return;
                    }
                    const why = (res && res.message) ? String(res.message) : 'chưa đọc được ký tự';
                    setStatus(IS_EXIT
                        ? ('Mã ' + (armedCode || codeForThisScan) + ' — ' + why)
                        : why);
                }).fail(function(xhr) {
                    const st = xhr && xhr.status ? ('HTTP ' + xhr.status) : 'lỗi mạng';
                    setStatus(IS_EXIT
                        ? ('Mã ' + (armedCode || '') + ' — lỗi đọc ký tự ' + st)
                        : ('Lỗi đọc ký tự ' + st));
                }).always(function() {
                    if (!busy) {
                        scanning = false;
                        ensureScanLoop(SCAN_MS);
                    }
                });
            }).fail(function(xhr) {
                const st = xhr && xhr.status ? ('HTTP ' + xhr.status) : 'lỗi mạng';
                setStatus(IS_EXIT
                    ? ('Mã ' + (armedCode || '') + ' — lỗi tìm biển ' + st)
                    : ('Lỗi tìm biển ' + st));
            }).always(function() {
                if (!startedOcr && !busy) {
                    scanning = false;
                    ensureScanLoop(SCAN_MS);
                }
            });
        }).catch(function() {
            scanning = false;
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
                showResult(true,
                    '<strong>Xe vào OK</strong><br>Biển: <b>' + (res.plate_number || plateHint) +
                    '</b><br>Mã code: <b class="text-primary">' + res.code + '</b>'
                );
                setStatus('Xong — chờ xe tiếp theo...');
                setTimeout(function() {
                    $('#scan-result').fadeOut();
                    resumeAfterAttempt(1500);
                }, 4000);
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

    function submitExit(blob, code, plateHint) {
        if (!code) {
            showResult(false, 'Mất mã code — chờ máy tính nhập lại');
            setStatus('Chờ quẹt mã (nhập trên máy tính)...');
            resumeAfterAttempt(1000);
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
                    '</b><br>Máy tính xác nhận Hợp lệ / Không hợp lệ'
                );
                setStatus('Xong — chờ mã code tiếp theo...');
                setTimeout(function() {
                    $('#scan-result').fadeOut();
                    resumeAfterAttempt(1500);
                }, 3500);
            } else {
                showResult(false, (res && res.message) || 'Đối chiếu thất bại — sẽ quét lại');
                setStatus('Mã ' + code + ' — lỗi, đang quét lại...');
                resumeAfterAttempt(RETRY_MS);
            }
        }).fail(function(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Lỗi kết nối máy chủ — sẽ quét lại';
            showResult(false, msg);
            setStatus('Mã ' + code + ' — lỗi, đang quét lại...');
            resumeAfterAttempt(RETRY_MS);
        });
    }

    async function startCamera() {
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            location.href = 'https://' + location.host + location.pathname;
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Trình duyệt không hỗ trợ camera / cần HTTPS');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
            });
            video.srcObject = stream;
            await video.play().catch(function() {});
            setStatus(IS_EXIT ? 'Chờ quẹt mã (nhập trên máy tính)...' : 'Đang chờ biển số...');
            if (IS_EXIT) {
                pollArmedCode();
                liveTimer = setTimeout(pushLiveFrame, 1000);
            } else {
                scheduleScan();
                pushLiveFrame();
            }
        } catch (e) {
            console.error(e);
            setStatus('Không mở được camera — kiểm tra quyền / HTTPS');
        }
    }

    $(window).on('beforeunload', function() {
        if (armedPollTimer) clearTimeout(armedPollTimer);
        if (liveTimer) clearTimeout(liveTimer);
        if (scanTimer) clearTimeout(scanTimer);
        if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
    });

    startCamera();
});
</script>
@endpush
