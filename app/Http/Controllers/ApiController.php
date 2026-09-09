<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleLog;
use App\Models\MonthlyTicket;
use App\Services\ParkingFee;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    /**
     * Nhả session sớm để request LIVE / poll song song không bị kẹt trên file session.
     */
    private function releaseSessionLock()
    {
        try {
            if (!session()->isStarted()) {
                return;
            }
            session()->save();
            $handler = session()->getHandler();
            if (method_exists($handler, 'close')) {
                $handler->close();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Mỗi tài khoản guard có kênh LIVE / WebRTC / alert riêng — không chia sẻ giữa các user.
     */
    private function currentUserScopeId()
    {
        return max(0, (int) auth()->id());
    }

    private function ensureDir($dir)
    {
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function unrecognizedPlateLabel()
    {
        return 'không thể nhận diện';
    }

    private function normalizeScanPhase($phase)
    {
        $phase = strtolower(trim((string) $phase));
        return in_array($phase, ['detect', 'ocr', 'saving', 'idle'], true) ? $phase : '';
    }

    private function readLiveMetaFile($metaPath)
    {
        if (!is_file($metaPath)) {
            return [];
        }
        $meta = json_decode((string) @file_get_contents($metaPath), true);
        return is_array($meta) ? $meta : [];
    }

    private function liveScanPhaseFromMeta(array $meta)
    {
        $phase = $this->normalizeScanPhase($meta['scan_phase'] ?? '');
        if ($phase === '') {
            return 'detect';
        }
        $phaseTs = (int) ($meta['phase_ts'] ?? 0);
        if ($phaseTs > 0) {
            $ageMs = (int) round(microtime(true) * 1000) - $phaseTs;
            // OCR có thể ~60s; quá 90s không refresh → coi như đang tìm biển
            if ($ageMs > 90000) {
                return 'detect';
            }
        }
        return $phase;
    }

    private function isUnrecognizedPlate($plate)
    {
        $p = trim(mb_strtolower((string) $plate));
        return $p === '' || $p === 'không thể nhận diện';
    }

    private function platesMatch($entryPlate, $exitPlate)
    {
        if ($this->isUnrecognizedPlate($entryPlate) || $this->isUnrecognizedPlate($exitPlate)) {
            return false;
        }
        $cleanEntry = preg_replace('/[^A-Z0-9]/i', '', (string) $entryPlate);
        $cleanExit = preg_replace('/[^A-Z0-9]/i', '', (string) $exitPlate);
        return $cleanEntry !== '' && strcasecmp($cleanEntry, $cleanExit) === 0;
    }

    private function generateUniqueVehicleCode()
    {
        do {
            $code = chr(rand(65, 90)) . chr(rand(65, 90)) . rand(1000, 9999);
        } while (
            VehicleLog::where('code', $code)->exists()
            || MonthlyTicket::where('code', $code)->exists()
        );

        return $code;
    }

    private function assignEntryCode(?MonthlyTicket $ticket): string
    {
        if ($ticket) {
            return strtoupper(trim((string) $ticket->code));
        }

        return $this->generateUniqueVehicleCode();
    }

    private function armedMonthlyCodePath()
    {
        return $this->monitorStateDir() . DIRECTORY_SEPARATOR . 'armed_monthly_code.json';
    }

    private function writeArmedMonthlyCode(MonthlyTicket $ticket): void
    {
        @file_put_contents($this->armedMonthlyCodePath(), json_encode([
            'code' => $ticket->code,
            'ticket_id' => $ticket->id,
            'plate_number' => $ticket->plate_number,
            'ts' => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function clearArmedMonthlyCode(): void
    {
        $path = $this->armedMonthlyCodePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function readArmedMonthlyPayload(): ?array
    {
        $path = $this->armedMonthlyCodePath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data) || empty($data['code'])) {
            return null;
        }
        if ((time() - (int) ($data['ts'] ?? 0)) > 3600) {
            @unlink($path);
            return null;
        }
        $ticket = MonthlyTicket::findUsableByCode((string) $data['code']);
        if (!$ticket || $this->findOccupiedMonthlyLog($ticket)) {
            @unlink($path);
            return null;
        }
        return $data;
    }

    private function pendingMonthlyEntryPath()
    {
        return $this->monitorStateDir() . DIRECTORY_SEPARATOR . 'pending_monthly_entry.json';
    }

    private function pendingMonthlyImageFullPath(?string $relative): ?string
    {
        $relative = str_replace('\\', '/', (string) $relative);
        if ($relative === '' || !str_contains($relative, 'uploads/vehicles/')) {
            return null;
        }
        $full = base_path($relative);
        return is_file($full) ? $full : null;
    }

    private function deletePendingMonthlyImage(?string $relative): void
    {
        $full = $this->pendingMonthlyImageFullPath($relative);
        if ($full) {
            @unlink($full);
        }
    }

    private function writePendingMonthlyEntry(array $payload): array
    {
        $old = $this->readPendingMonthlyEntry();
        if ($old && ($old['entry_image'] ?? '') !== ($payload['entry_image'] ?? '')) {
            $this->deletePendingMonthlyImage($old['entry_image'] ?? null);
        }

        $payload['id'] = 'pm_' . time() . '_' . uniqid();
        $payload['user_id'] = $this->currentUserScopeId();
        $payload['ts'] = time();
        @file_put_contents(
            $this->pendingMonthlyEntryPath(),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return $payload;
    }

    private function readPendingMonthlyEntry(): ?array
    {
        $path = $this->pendingMonthlyEntryPath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data) || empty($data['id'])) {
            return null;
        }
        return $data;
    }

    private function clearPendingMonthlyEntry(bool $deleteImage = false): void
    {
        $pending = $deleteImage ? $this->readPendingMonthlyEntry() : null;
        $path = $this->pendingMonthlyEntryPath();
        if (is_file($path)) {
            @unlink($path);
        }
        if ($deleteImage && $pending) {
            $this->deletePendingMonthlyImage($pending['entry_image'] ?? null);
        }
    }

    private function pendingMonthlyPublicPayload(array $pending): array
    {
        return [
            'log_id' => $pending['id'],
            'pending_id' => $pending['id'],
            'code' => $pending['code'] ?? null,
            'registered_plate' => $pending['registered_plate'] ?? null,
            'entry_plate' => $pending['plate_number'] ?? null,
            'plate_number' => $pending['plate_number'] ?? null,
            'entry_image' => !empty($pending['entry_image']) ? asset($pending['entry_image']) : null,
            'image_url' => !empty($pending['entry_image']) ? asset($pending['entry_image']) : null,
            'monthly_match' => false,
            'message' => 'Biển số nhận diện (' . ($pending['plate_number'] ?? '-')
                . ') không khớp biển đăng ký vé tháng (' . ($pending['registered_plate'] ?? '-') . ').',
        ];
    }

    private function resolveMonthlyTicket(Request $request): ?MonthlyTicket
    {
        $code = strtoupper(trim((string) $request->input('monthly_code', '')));
        if ($code === '') {
            $armed = $this->readArmedMonthlyPayload();
            $code = strtoupper(trim((string) ($armed['code'] ?? '')));
        }
        if ($code === '') {
            return null;
        }
        $ticket = MonthlyTicket::findUsableByCode($code);
        if (!$ticket || $this->findOccupiedMonthlyLog($ticket)) {
            return null;
        }
        return $ticket;
    }

    /**
     * Vé tháng đang có xe trong bãi (chưa ra) — không cho dùng lại mã này.
     */
    private function findOccupiedMonthlyLog(?MonthlyTicket $ticket): ?VehicleLog
    {
        if (!$ticket) {
            return null;
        }

        return VehicleLog::query()
            ->where('status', 'in')
            ->where(function ($q) use ($ticket) {
                $q->where('monthly_ticket_id', $ticket->id)
                    ->orWhere(function ($q2) use ($ticket) {
                        $q2->where('ticket_type', 'monthly')
                            ->where('code', $ticket->code);
                    });
            })
            ->latest('id')
            ->first();
    }

    private function findOpenLogByCode(string $code): ?VehicleLog
    {
        $code = strtoupper(trim($code));
        $log = VehicleLog::with('monthlyTicket')
            ->where('code', $code)
            ->where(function ($q) {
                $q->where('status', 'in')->orWhere('is_valid', false);
            })
            ->latest()
            ->first();
        if ($log) {
            return $log;
        }
        if (!preg_match('/^[A-Z]\d{5}$/', $code)) {
            return null;
        }
        $ticket = MonthlyTicket::notDeleted()->where('code', $code)->first();
        if (!$ticket) {
            return null;
        }
        return VehicleLog::with('monthlyTicket')
            ->where('monthly_ticket_id', $ticket->id)
            ->where(function ($q) {
                $q->where('status', 'in')->orWhere('is_valid', false);
            })
            ->latest()
            ->first();
    }

    /**
     * Xe vé tháng đã vào nhưng vé bị vô hiệu / hết hạn → không cho quét ra bằng mã đó.
     */
    private function monthlyExitBlockedReason(?VehicleLog $log): ?string
    {
        if (!$log) {
            return null;
        }
        $isMonthly = (($log->ticket_type ?? '') === 'monthly') || (int) ($log->monthly_ticket_id ?? 0) > 0;
        if (!$isMonthly) {
            return null;
        }

        $ticket = $log->monthlyTicket;
        if (!$ticket && (int) ($log->monthly_ticket_id ?? 0) > 0) {
            $ticket = MonthlyTicket::find($log->monthly_ticket_id);
        }
        if (!$ticket) {
            $ticket = MonthlyTicket::where('code', strtoupper((string) $log->code))->first();
        }

        if (!$ticket || $ticket->deleted || !$ticket->is_active) {
            return 'Vé tháng đã vô hiệu hóa — không cho quét xe ra.';
        }
        if ($ticket->isExpired()) {
            return 'Vé tháng đã hết hạn — không cho quét xe ra.';
        }

        return null;
    }

    private function feePayload(VehicleLog $log, ?array $calc = null): array
    {
        $isMonthly = ($log->ticket_type ?? 'daily') === 'monthly';
        $fee = $calc['fee'] ?? (int) ($log->fee ?? 0);
        $hours = $calc['hours'] ?? null;
        return [
            'ticket_type' => $isMonthly ? 'monthly' : 'daily',
            'fee' => $fee,
            'fee_text' => ParkingFee::formatVnd($fee),
            'hours' => $hours,
            'monthly_code' => $isMonthly && $log->monthlyTicket ? $log->monthlyTicket->code : null,
        ];
    }

    private function monthlyLookupResponse(?string $code)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return [
                'success' => true,
                'found' => false,
                'empty' => true,
                'message' => 'Không nhập mã — tính vé ngày.',
            ];
        }

        $ticket = MonthlyTicket::notDeleted()->where('code', $code)->first();
        if (!$ticket) {
            return [
                'success' => true,
                'found' => false,
                'code' => $code,
                'message' => 'Không tìm thấy vé tháng — sẽ tính vé ngày.',
            ];
        }
        if ($ticket->deleted || !$ticket->is_active) {
            return [
                'success' => true,
                'found' => false,
                'disabled' => true,
                'code' => $code,
                'message' => 'Vé tháng đã vô hiệu hóa.',
            ];
        }
        if ($ticket->isExpired()) {
            return [
                'success' => true,
                'found' => false,
                'expired' => true,
                'code' => $code,
                'message' => 'Vé tháng đã hết hạn.',
            ];
        }

        $occupied = $this->findOccupiedMonthlyLog($ticket);
        if ($occupied) {
            $plate = trim((string) ($occupied->plate_number ?: ''));
            return [
                'success' => true,
                'found' => false,
                'in_use' => true,
                'code' => $ticket->code,
                'occupied_plate' => $plate !== '' ? $plate : null,
                'message' => 'Vé tháng đang có xe'
                    . ($plate !== '' ? (' ' . $plate) : '')
                    . ' trong bãi (chưa ra) — sẽ tính vé ngày.',
            ];
        }

        return [
            'success' => true,
            'found' => true,
            'code' => $ticket->code,
            'ticket_id' => $ticket->id,
            'plate_number' => $ticket->plate_number,
            'expires_on' => $ticket->expires_on->format('d/m/Y'),
            'message' => 'Đã tìm thấy vé tháng ' . $ticket->code,
        ];
    }

    /**
     * Ảnh chụp từ PC (canvas) hoặc fallback frame LIVE cuối cùng trên đĩa.
     */
    private function storeManualVehicleImage($side, Request $request)
    {
        $side = $side === 'exit' ? 'exit' : 'entry';
        $uploadDir = $this->ensureDir(public_path('uploads/vehicles'));

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $ext = 'jpg';
            }
            $filename = $side . '_' . time() . '_' . uniqid() . '.' . $ext;
            $file->move($uploadDir, $filename);
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($fullPath) || (int) @filesize($fullPath) < 200) {
                @unlink($fullPath);
                return null;
            }
            return 'public/uploads/vehicles/' . $filename;
        }

        $uid = $this->currentUserScopeId();
        $livePath = public_path('uploads/live/' . $uid . '/' . $side . '.jpg');
        if (!is_file($livePath)) {
            return null;
        }
        $bin = @file_get_contents($livePath);
        if (!is_string($bin) || strlen($bin) < 200) {
            return null;
        }
        $filename = $side . '_' . time() . '_' . uniqid() . '.jpg';
        $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($fullPath, $bin) === false) {
            return null;
        }
        return 'public/uploads/vehicles/' . $filename;
    }

    private function manualOcrPausePath()
    {
        return $this->monitorStateDir() . DIRECTORY_SEPARATOR . 'ocr_pause.json';
    }

    private function writeManualOcrPause($seconds = 10)
    {
        @file_put_contents($this->manualOcrPausePath(), json_encode([
            'until' => time() + max(1, (int) $seconds),
        ]));
    }

    private function readManualOcrPauseRemaining()
    {
        $path = $this->manualOcrPausePath();
        if (!is_file($path)) {
            return 0;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        $left = (int) ($data['until'] ?? 0) - time();
        if ($left <= 0) {
            @unlink($path);
            return 0;
        }
        return $left;
    }

    private function scanHoldPath()
    {
        return $this->monitorStateDir() . DIRECTORY_SEPARATOR . 'scan_hold.json';
    }

    private function writeScanHold($reason = 'already_inside')
    {
        @file_put_contents($this->scanHoldPath(), json_encode([
            'reason' => $reason,
            'ts' => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function clearScanHold()
    {
        $path = $this->scanHoldPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function readScanHold(): ?array
    {
        $path = $this->scanHoldPath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data)) {
            return ['reason' => 'already_inside'];
        }
        if (empty($data['reason'])) {
            $data['reason'] = 'already_inside';
        }
        return $data;
    }

    private function isScanHoldActive()
    {
        return is_file($this->scanHoldPath());
    }

    private function scanHoldReason(): ?string
    {
        $hold = $this->readScanHold();
        return $hold['reason'] ?? null;
    }

    private function clearEntryAlert()
    {
        $path = $this->entryAlertPath();
        if (is_file($path)) {
            @unlink($path);
        }
        try {
            \Illuminate\Support\Facades\Cache::forget($this->entryAlertCacheKey());
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function getPythonExecutable()
    {
        $envPython = env('PYTHON_PATH');
        if (!empty($envPython)) {
            return $envPython;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $python3 = trim((string) @shell_exec('command -v python3 2>/dev/null'));
            if (!empty($python3)) {
                return $python3;
            }

            $commonPaths = [
                '/usr/bin/python3',
                '/usr/local/bin/python3',
                '/bin/python3',
                '/usr/bin/python',
            ];
            foreach ($commonPaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
            return 'python3';
        }

        return 'python';
    }

    private function getRecognizeServerUrl()
    {
        return rtrim((string) env('NDBS_OCR_URL', 'http://127.0.0.1:8766'), '/');
    }

    private function isRecognizeServerUp()
    {
        $url = $this->getRecognizeServerUrl() . '/health';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200 && is_string($response) && str_contains($response, 'ndbs-recognize')) {
                return true;
            }
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        return is_string($response) && str_contains($response, 'ndbs-recognize');
    }

    /**
     * Tự khởi động OCR server nền (không cần mở IDE) nếu chưa chạy.
     * Lần đầu nạp model có thể mất ~10–30s; các lần sau ~1s.
     */
    private function ensureRecognizeServerRunning()
    {
        if ($this->isRecognizeServerUp()) {
            return true;
        }

        if (!function_exists('exec') && !function_exists('popen')) {
            return false;
        }

        $lockPath = storage_path('framework/ocr_server.lock');
        $lockDir = dirname($lockPath);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0777, true);
        }

        $lockFp = @fopen($lockPath, 'c+');
        if ($lockFp === false) {
            return $this->isRecognizeServerUp();
        }

        // Chỉ 1 request được phép spawn server
        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            // Request khác đang start — chờ health
            for ($i = 0; $i < 45; $i++) {
                if ($this->isRecognizeServerUp()) {
                    fclose($lockFp);
                    return true;
                }
                usleep(1000000);
            }
            fclose($lockFp);
            return $this->isRecognizeServerUp();
        }

        try {
            if ($this->isRecognizeServerUp()) {
                return true;
            }

            $pythonBin = $this->getPythonExecutable();
            $script = base_path('recognize_server.py');
            $workDir = base_path();
            $logFile = storage_path('logs/ocr_server.log');
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }

            if (PHP_OS_FAMILY === 'Windows') {
                // Chạy ẩn, không mở cửa sổ console / IDE
                $outLog = storage_path('logs/ocr_server.out.log');
                $errLog = storage_path('logs/ocr_server.err.log');
                $ps = sprintf(
                    'powershell -NoProfile -WindowStyle Hidden -Command "Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s"',
                    escapeshellarg($pythonBin),
                    escapeshellarg($script),
                    escapeshellarg($workDir),
                    escapeshellarg($outLog),
                    escapeshellarg($errLog)
                );
                if (function_exists('popen')) {
                    pclose(@popen($ps, 'r'));
                } else {
                    exec($ps);
                }
            } else {
                $cmd = sprintf(
                    'cd %s && nohup %s %s >> %s 2>&1 &',
                    escapeshellarg($workDir),
                    escapeshellcmd($pythonBin),
                    escapeshellarg($script),
                    escapeshellarg($logFile)
                );
                exec($cmd);
            }

            // Chờ server sẵn sàng (nạp YOLO)
            for ($i = 0; $i < 60; $i++) {
                if ($this->isRecognizeServerUp()) {
                    return true;
                }
                usleep(1000000);
            }

            return $this->isRecognizeServerUp();
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * Chỉ phát hiện khung biển (không đọc ký tự). Trả null nếu server chưa chạy.
     */
    private function runDetectionViaServer($imageFullPath)
    {
        $url = $this->getRecognizeServerUrl() . '/detect';
        $payload = json_encode(['image' => $imageFullPath], JSON_UNESCAPED_SLASHES);

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false || $httpCode === 0) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'success' => !empty($data['success']),
            'detected' => !empty($data['detected']),
            'confidence' => isset($data['confidence']) ? (float) $data['confidence'] : 0.0,
            'error' => $data['error'] ?? null,
        ];
    }

    private function runDetection($imageFullPath)
    {
        $this->ensureRecognizeServerRunning();
        $viaServer = $this->runDetectionViaServer($imageFullPath);
        if ($viaServer !== null) {
            return $viaServer;
        }
        // Fallback: chạy full recognize — nếu fail ở stage detect thì coi như chưa thấy biển
        $full = $this->runRecognition($imageFullPath);
        if (!empty($full['success'])) {
            return ['success' => true, 'detected' => true, 'confidence' => 1.0];
        }
        $err = (string) ($full['error'] ?? '');
        $isDetectFail = str_contains(mb_strtolower($err), 'biển') || str_contains(mb_strtolower($err), 'detect');
        return [
            'success' => true,
            'detected' => false,
            'confidence' => 0.0,
            'error' => $isDetectFail ? $err : 'Chưa phát hiện biển số',
        ];
    }

    /**
     * Gọi server AI giữ model trong RAM (nhanh). Trả null nếu server chưa chạy.
     */
    private function runRecognitionViaServer($imageFullPath)
    {
        $url = $this->getRecognizeServerUrl() . '/recognize';
        $payload = json_encode(['image' => $imageFullPath], JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 90,
            ]);
            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0 || $response === false || $httpCode === 0) {
                return null; // server chưa chạy
            }

            $data = json_decode($response, true);
            if (is_array($data)) {
                if (!empty($data['success']) && !empty($data['plate'])) {
                    return ['success' => true, 'plate' => $data['plate']];
                }
                return [
                    'success' => false,
                    'error' => $data['error'] ?? 'Không nhận diện được ký tự biển số.',
                    'stage' => $data['stage'] ?? null,
                ];
            }

            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 90,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }
        if (!empty($data['success']) && !empty($data['plate'])) {
            return ['success' => true, 'plate' => $data['plate']];
        }
        return ['success' => false, 'error' => $data['error'] ?? 'Không nhận diện được ký tự biển số.'];
    }

    private function runRecognition($imageFullPath)
    {
        // Tự bật OCR server nền nếu chưa chạy (không cần mở IDE)
        $this->ensureRecognizeServerRunning();

        $viaServer = $this->runRecognitionViaServer($imageFullPath);
        if ($viaServer !== null) {
            return $viaServer;
        }

        if (!function_exists('exec')) {
            return ['success' => false, 'error' => 'Không kết nối được OCR server và hàm exec() trong PHP đã bị khóa. Kiểm tra PYTHON_PATH trong .env hoặc chạy start_ocr_server.bat.'];
        }

        $pythonScript = base_path('recognize.py');
        $pythonBin = $this->getPythonExecutable();
        $command = escapeshellcmd($pythonBin) . " " . escapeshellarg($pythonScript) . " " . escapeshellarg($imageFullPath) . " 2>&1";

        exec($command, $output, $returnCode);

        if (!empty($output)) {
            $output = array_map(function ($line) {
                if (!mb_check_encoding($line, 'UTF-8')) {
                    $line = mb_convert_encoding($line, 'UTF-8', 'auto');
                }
                return iconv('UTF-8', 'UTF-8//IGNORE', $line);
            }, $output);
        }

        $jsonStr = '';
        if (!empty($output)) {
            foreach (array_reverse($output) as $line) {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
                    $jsonStr = $trimmed;
                    break;
                }
            }
        }

        if (!empty($jsonStr)) {
            $data = json_decode($jsonStr, true);
            if (isset($data['success']) && $data['success'] === true && !empty($data['plate'])) {
                return ['success' => true, 'plate' => $data['plate']];
            }
            return ['success' => false, 'error' => $data['error'] ?? 'Không nhận diện được ký tự biển số.'];
        }

        $errorMsg = 'Lỗi chạy script AI (Mã lỗi ' . $returnCode . '): ' . implode("\n", $output ?? []);
        return ['success' => false, 'error' => iconv('UTF-8', 'UTF-8//IGNORE', $errorMsg)];
    }

    /**
     * Chỉ nhận diện biển số từ ảnh (không lưu DB) — dùng cho camera realtime.
     */
    public function recognizePreview(Request $request)
    {
        try {
            if (!$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có ảnh để nhận diện!'
                ]);
            }

            $this->releaseSessionLock();

            $file = $request->file('image');
            $tmpDir = storage_path('app/ocr_preview');
            if (!file_exists($tmpDir)) {
                @mkdir($tmpDir, 0777, true);
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'], true)) {
                $ext = 'jpg';
            }
            $filename = 'preview_' . time() . '_' . uniqid() . '.' . $ext;
            $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
            $file->move($tmpDir, $filename);

            try {
                $aiResult = $this->runRecognition($fullPath);
            } finally {
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            if (!$aiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $aiResult['error'] ?? 'Không nhận diện được biển số.',
                    'stage' => $aiResult['stage'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'plate_number' => normalize_plate($aiResult['plate']),
            ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (!mb_check_encoding($msg, 'UTF-8')) {
                $msg = mb_convert_encoding($msg, 'UTF-8', 'auto');
            }
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . iconv('UTF-8', 'UTF-8//IGNORE', $msg)
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Bước 1 LIVE: chỉ kiểm tra có khung biển hay chưa (không OCR ký tự).
     */
    public function detectPreview(Request $request)
    {
        try {
            if (!$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'detected' => false,
                    'message' => 'Không có ảnh!'
                ]);
            }

            $this->releaseSessionLock();

            $file = $request->file('image');
            $tmpDir = storage_path('app/ocr_preview');
            if (!file_exists($tmpDir)) {
                @mkdir($tmpDir, 0777, true);
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'], true)) {
                $ext = 'jpg';
            }
            $filename = 'detect_' . time() . '_' . uniqid() . '.' . $ext;
            $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
            $file->move($tmpDir, $filename);

            try {
                $aiResult = $this->runDetection($fullPath);
            } finally {
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            return response()->json([
                'success' => true,
                'detected' => !empty($aiResult['detected']),
                'confidence' => $aiResult['confidence'] ?? 0,
                'message' => !empty($aiResult['detected'])
                    ? 'Đã thấy biển số'
                    : ($aiResult['error'] ?? 'Chưa thấy biển số'),
            ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (!mb_check_encoding($msg, 'UTF-8')) {
                $msg = mb_convert_encoding($msg, 'UTF-8', 'auto');
            }
            return response()->json([
                'success' => false,
                'detected' => false,
                'message' => 'Lỗi hệ thống: ' . iconv('UTF-8', 'UTF-8//IGNORE', $msg)
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    public function recognizeEntry(Request $request)
    {
        try {
            if (!$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng chọn ảnh xe vào!'
                ]);
            }

            $file = $request->file('image');
            $uploadDir = public_path('uploads/vehicles');
            if (!file_exists($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filename = 'entry_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $relativePath = 'public/uploads/vehicles/' . $filename;
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            $this->releaseSessionLock();

            // Run AI Recognition
            $aiResult = $this->runRecognition($fullPath);
            if (!$aiResult['success']) {
                return response()->json([
                    'success' => false,
                    'ocr_failed' => true,
                    'message' => $aiResult['error']
                ]);
            }

            $plate = normalize_plate($aiResult['plate']);
            $cleanPlate = preg_replace('/[^A-Z0-9]/i', '', $plate);

            // Xe vẫn còn trong bãi (chưa ra) → không cho nhận diện vào lần nữa
            $stillInside = null;
            if ($cleanPlate !== '') {
                $stillInside = VehicleLog::where('status', 'in')
                    ->where('guard_in_id', auth()->id())
                    ->whereRaw(
                        "REPLACE(REPLACE(REPLACE(UPPER(plate_number), '-', ''), ' ', ''), '.', '') = ?",
                        [$cleanPlate]
                    )
                    ->latest('id')
                    ->first();
            }
            if ($stillInside) {
                @unlink($fullPath);
                $entryAt = $stillInside->entry_time
                    ? \Carbon\Carbon::parse($stillInside->entry_time)->format('d/m/Y H:i')
                    : null;
                $message = 'Xe biển ' . $stillInside->plate_number
                    . ' vẫn đang nằm trong bãi'
                    . ($stillInside->code ? (' (mã ' . $stillInside->code . ')') : '')
                    . ($entryAt ? (', vào lúc ' . $entryAt) : '')
                    . '. Cần nhận diện xe ra trước khi vào lại.';

                $alert = [
                    'type' => 'already_inside',
                    'log_id' => $stillInside->id,
                    'plate_number' => $stillInside->plate_number,
                    'code' => $stillInside->code,
                    'entry_time' => $entryAt,
                    'entry_image' => $stillInside->entry_image ? asset($stillInside->entry_image) : null,
                    'message' => $message,
                ];
                // Đẩy lên PC giám sát (kể cả khi quét từ ĐT)
                $this->writeEntryAlert($alert);
                $this->writeScanHold('already_inside');

                return response()->json(array_merge([
                    'success' => false,
                    'already_inside' => true,
                    'hold_scan' => true,
                ], $alert));
            }

            $monthlyTicket = $this->resolveMonthlyTicket($request);
            $isMonthly = (bool) $monthlyTicket;
            $monthlyMatch = $isMonthly ? $this->platesMatch($monthlyTicket->plate_number, $plate) : null;

            if ($isMonthly && !$monthlyMatch) {
                $pending = $this->writePendingMonthlyEntry([
                    'plate_number' => $plate,
                    'code' => $monthlyTicket->code,
                    'ticket_id' => $monthlyTicket->id,
                    'registered_plate' => $monthlyTicket->plate_number,
                    'entry_image' => $relativePath,
                ]);
                $this->writeScanHold('monthly_pending');

                return response()->json(array_merge($this->pendingMonthlyPublicPayload($pending), [
                    'success' => true,
                    'ticket_type' => 'monthly',
                    'monthly_code' => $monthlyTicket->code,
                    'monthly_pending' => true,
                    'hold_scan' => true,
                    'hold_reason' => 'monthly_pending',
                    'pause_ocr_s' => 0,
                ]));
            }

            $code = $this->assignEntryCode($monthlyTicket);
            $log = VehicleLog::create([
                'plate_number' => $plate,
                'code' => $code,
                'ticket_type' => $isMonthly ? 'monthly' : 'daily',
                'monthly_ticket_id' => $isMonthly ? $monthlyTicket->id : null,
                'status' => 'in',
                'entry_time' => now(),
                'entry_image' => $relativePath,
                'guard_in_id' => auth()->id(),
                'monthly_match' => $monthlyMatch,
                'monthly_confirmed' => $isMonthly ? true : null,
                'fee' => $isMonthly ? 0 : null,
            ]);

            if ($isMonthly) {
                $this->clearArmedMonthlyCode();
            }

            $this->writeManualOcrPause(10);

            return response()->json([
                'success' => true,
                'log_id' => $log->id,
                'plate_number' => $plate,
                'code' => $code,
                'ticket_type' => $isMonthly ? 'monthly' : 'daily',
                'monthly_code' => $isMonthly ? $monthlyTicket->code : null,
                'registered_plate' => $isMonthly ? $monthlyTicket->plate_number : null,
                'monthly_match' => $monthlyMatch,
                'monthly_pending' => false,
                'image_url' => asset($relativePath),
                'message' => $isMonthly
                    ? ('Nhận diện thành công. Xe vé tháng ' . $monthlyTicket->code . ' đã vào.')
                    : 'Nhận diện thành công. Xe đã vào.',
                'pause_ocr_s' => 10,
            ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (!mb_check_encoding($msg, 'UTF-8')) {
                $msg = mb_convert_encoding($msg, 'UTF-8', 'auto');
            }
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . iconv('UTF-8', 'UTF-8//IGNORE', $msg)
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    public function checkoutExit(Request $request)
    {
        try {
            if (!$request->hasFile('image') || !$request->filled('code')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng tải ảnh xe ra VÀ nhập đủ mã code!'
                ]);
            }

            $code = strtoupper(trim($request->code));
            if (strlen($code) !== 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã code phải gồm đúng 6 ký tự!'
                ]);
            }

            $log = $this->findOpenLogByCode($code);

            if (!$log) {
                // Sai mã → tắt kích hoạt để PC/ĐT nhập lại từ đầu
                $this->clearArmedExitCodeStorage();
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy xe chưa ra với mã code: ' . $code . '. Hãy nhập lại mã.',
                    'retryable' => true,
                ]);
            }

            $blocked = $this->monthlyExitBlockedReason($log);
            if ($blocked) {
                $this->clearArmedExitCodeStorage();
                return response()->json([
                    'success' => false,
                    'message' => $blocked,
                    'monthly_disabled' => true,
                    'retryable' => true,
                ]);
            }

            $claim = $this->claimGlobalArmedCode($code, $log->id);
            if (empty($claim['ok'])) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => $claim['message'] ?? 'Mã đang được tài khoản khác sử dụng — không nhận mã.',
                    'retryable' => true,
                ], 409);
            }

            $file = $request->file('image');
            $uploadDir = public_path('uploads/vehicles');
            if (!file_exists($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filename = 'exit_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $relativePath = 'public/uploads/vehicles/' . $filename;
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            $this->releaseSessionLock();

            // Run AI Recognition on Exit Image
            $aiResult = $this->runRecognition($fullPath);
            if (!$aiResult['success']) {
                @unlink($fullPath);
                // Giữ mã đã kích hoạt để ĐT quét lại biển; báo retryable cho UI
                return response()->json([
                    'success' => false,
                    'ocr_failed' => true,
                    'message' => 'Lỗi nhận diện ảnh ra: ' . $aiResult['error'] . '. Có thể quét lại.',
                    'retryable' => true,
                    'keep_armed' => true,
                ]);
            }

            $exitPlate = normalize_plate($aiResult['plate']);
            $isMatch = $this->platesMatch($log->plate_number, $exitPlate);

            if ($isMatch) {
                // Biển khớp → đổi in → out ngay (không đợi đếm 10s trên frontend / F5)
                $exitAt = now();
                $calc = ParkingFee::applyOnCheckout($log, $exitAt);
                $log->fill([
                    'status' => 'out',
                    'exit_time' => $exitAt,
                    'exit_image' => $relativePath,
                    'exit_plate_number' => $exitPlate,
                    'guard_out_id' => auth()->id(),
                    'is_valid' => true,
                ]);
                $log->save();
                $this->purgeArmedExitCodeEverywhere($code);
                $this->purgeArmedExitCodeEverywhere(strtoupper((string) $log->code));
                $this->writeManualOcrPause(10);
            } else {
                // Biển không khớp — chờ bảo vệ bấm Hợp lệ / Không hợp lệ (ĐT không quét)
                $log->update([
                    'status' => 'in',
                    'exit_time' => null,
                    'exit_image' => $relativePath,
                    'exit_plate_number' => $exitPlate,
                    'guard_out_id' => auth()->id(),
                    'is_valid' => null,
                ]);
                $this->clearLocalArmedExitCodeOnly();
                $this->touchGlobalArmedCode(strtoupper((string) $log->code), $log->id);
                $this->writeScanHold('exit_pending');
            }

            $log->load('monthlyTicket');
            $hours = 0;
            if ($isMatch) {
                $hours = ($log->ticket_type === 'monthly')
                    ? 0
                    : ParkingFee::billedHours($log->entry_time, $log->exit_time ?: now());
            }

            return response()->json(array_merge([
                'success' => true,
                'match' => $isMatch,
                'log_id' => $log->id,
                'code' => $log->code,
                'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
                'entry_plate' => $log->plate_number,
                'exit_image' => asset($relativePath),
                'exit_plate' => $exitPlate,
                'pause_ocr_s' => $isMatch ? 10 : 0,
                'hold_scan' => !$isMatch,
                'hold_reason' => $isMatch ? null : 'exit_pending',
                'already_out' => $isMatch,
                'message' => $isMatch
                    ? ('Biển số khớp: ' . $exitPlate)
                    : ('Biển số xe ra (' . $exitPlate . ') không khớp với xe vào (' . $log->plate_number . '). Vui lòng xác nhận.')
            ], $this->feePayload($log, $isMatch ? [
                'fee' => (int) ($log->fee ?? 0),
                'hours' => $hours,
            ] : ['fee' => 0, 'hours' => 0])));
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (!mb_check_encoding($msg, 'UTF-8')) {
                $msg = mb_convert_encoding($msg, 'UTF-8', 'auto');
            }
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . iconv('UTF-8', 'UTF-8//IGNORE', $msg)
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * PC giám sát xác nhận thủ công khi OCR không đọc được biển (xe vào).
     * Chụp frame hiện tại, lưu lượt vào với BSX "không thể nhận diện".
     */
    public function manualConfirmEntry(Request $request)
    {
        $relativePath = $this->storeManualVehicleImage('entry', $request);
        if (!$relativePath) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa có hình camera xe vào để chụp. Hãy mở camera điện thoại trước.',
            ]);
        }

        $this->releaseSessionLock();

        $plate = $this->unrecognizedPlateLabel();
        $monthlyTicket = $this->resolveMonthlyTicket($request);
        $isMonthly = (bool) $monthlyTicket;

        if ($isMonthly) {
            $pending = $this->writePendingMonthlyEntry([
                'plate_number' => $plate,
                'code' => $monthlyTicket->code,
                'ticket_id' => $monthlyTicket->id,
                'registered_plate' => $monthlyTicket->plate_number,
                'entry_image' => $relativePath,
            ]);
            $this->writeScanHold('monthly_pending');

            return response()->json(array_merge($this->pendingMonthlyPublicPayload($pending), [
                'success' => true,
                'unrecognized' => true,
                'ticket_type' => 'monthly',
                'monthly_code' => $monthlyTicket->code,
                'monthly_pending' => true,
                'hold_scan' => true,
                'hold_reason' => 'monthly_pending',
                'pause_ocr_s' => 0,
                'message' => 'Đã chụp xe vào (không thể nhận diện biển). Vui lòng xác nhận Hợp lệ / Không hợp lệ với vé tháng.',
            ]));
        }

        $code = $this->generateUniqueVehicleCode();
        $log = VehicleLog::create([
            'plate_number' => $plate,
            'code' => $code,
            'ticket_type' => 'daily',
            'monthly_ticket_id' => null,
            'status' => 'in',
            'entry_time' => now(),
            'entry_image' => $relativePath,
            'guard_in_id' => auth()->id(),
            'monthly_match' => null,
            'monthly_confirmed' => null,
            'fee' => null,
        ]);

        $this->writeManualOcrPause(10);

        return response()->json([
            'success' => true,
            'unrecognized' => true,
            'log_id' => $log->id,
            'plate_number' => $plate,
            'code' => $code,
            'ticket_type' => 'daily',
            'monthly_code' => null,
            'registered_plate' => null,
            'monthly_match' => null,
            'monthly_pending' => false,
            'image_url' => asset($relativePath),
            'message' => 'Xe đã vào thành công.',
            'pause_ocr_s' => 10,
        ]);
    }

    /**
     * PC giám sát xác nhận thủ công khi OCR không đọc được biển (xe ra).
     * Cần mã code; không chạy OCR; bảo vệ xác nhận Hợp lệ / Không hợp lệ.
     */
    public function manualConfirmExit(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if (strlen($code) !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng nhập mã code 6 ký tự trước khi xác nhận xe ra.',
            ]);
        }

        $log = $this->findOpenLogByCode($code);

        if (!$log) {
            $this->clearArmedExitCodeStorage();
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy xe chưa ra với mã code: ' . $code . '. Hãy nhập lại mã.',
                'retryable' => true,
            ]);
        }

        $blocked = $this->monthlyExitBlockedReason($log);
        if ($blocked) {
            $this->clearArmedExitCodeStorage();
            return response()->json([
                'success' => false,
                'message' => $blocked,
                'monthly_disabled' => true,
                'retryable' => true,
            ]);
        }

        $claim = $this->claimGlobalArmedCode($code, $log->id);
        if (empty($claim['ok'])) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $claim['message'] ?? 'Mã đang được tài khoản khác sử dụng — không nhận mã.',
                'retryable' => true,
            ], 409);
        }

        $relativePath = $this->storeManualVehicleImage('exit', $request);
        if (!$relativePath) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa có hình camera xe ra để chụp. Hãy mở camera điện thoại trước.',
            ]);
        }

        $this->releaseSessionLock();

        $exitPlate = $this->unrecognizedPlateLabel();

        $log->update([
            'status' => 'in',
            'exit_time' => null,
            'exit_image' => $relativePath,
            'exit_plate_number' => $exitPlate,
            'guard_out_id' => auth()->id(),
            'is_valid' => null,
        ]);

        $this->clearLocalArmedExitCodeOnly();
        $this->touchGlobalArmedCode(strtoupper((string) $log->code), $log->id);
        $this->writeScanHold('exit_pending');

        return response()->json([
            'success' => true,
            'match' => false,
            'unrecognized' => true,
            'log_id' => $log->id,
            'code' => $log->code,
            'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
            'entry_plate' => $log->plate_number,
            'exit_image' => asset($relativePath),
            'exit_plate' => $exitPlate,
            'message' => 'Đã chụp xe ra (không thể nhận diện biển). Vui lòng xác nhận Hợp lệ / Không hợp lệ.',
            'pause_ocr_s' => 0,
            'hold_scan' => true,
            'hold_reason' => 'exit_pending',
        ]);
    }

    /**
     * Máy tính bắt đầu đếm 10s → ĐT phải chờ hết mới được quét tiếp.
     */
    public function scanCooldown(Request $request)
    {
        $seconds = (int) $request->input('seconds', 10);
        if ($seconds < 1) {
            $seconds = 10;
        }
        if ($seconds > 30) {
            $seconds = 30;
        }
        $this->writeManualOcrPause($seconds);
        $this->releaseSessionLock();

        return response()->json([
            'success' => true,
            'pause_ocr_s' => $this->readManualOcrPauseRemaining(),
        ]);
    }

    /**
     * Bảo vệ bấm Đồng ý trên cảnh báo "xe vẫn trong bãi" → cho ĐT quét lại.
     */
    public function ackScanHold()
    {
        // F5 / Đồng ý chỉ nhả hold "xe trong bãi". Đối chiếu (vé tháng / xe ra) chỉ nhả khi Hợp lệ / Không hợp lệ.
        if (!in_array($this->scanHoldReason(), ['monthly_pending', 'exit_pending'], true)) {
            $this->clearScanHold();
        }
        $this->clearEntryAlert();
        $this->releaseSessionLock();

        return response()->json([
            'success' => true,
            'hold_scan' => $this->isScanHoldActive(),
            'hold_reason' => $this->scanHoldReason(),
            'pause_ocr_s' => $this->readManualOcrPauseRemaining(),
        ]);
    }

    public function scanHoldStatus()
    {
        $this->releaseSessionLock();

        return response()->json([
            'success' => true,
            'hold_scan' => $this->isScanHoldActive(),
            'hold_reason' => $this->scanHoldReason(),
            'pause_ocr_s' => $this->readManualOcrPauseRemaining(),
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function validateCheckout(Request $request)
    {
        $request->validate([
            'log_id' => 'required',
            'is_valid' => 'required'
        ]);

        $log = VehicleLog::find($request->log_id);
        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin phương tiện!'
            ]);
        }

        $uid = auth()->id();
        // Chỉ tài khoản đã quét xe ra mới được xác nhận đối chiếu
        if ((int) $log->guard_out_id !== (int) $uid) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền xác nhận lượt này.'
            ], 403);
        }

        $isValid = filter_var($request->is_valid, FILTER_VALIDATE_BOOLEAN);

        if ($isValid) {
            // Đã cho ra rồi (bấm F5 lúc đang đếm) → không đổi lại
            if ((string) $log->status === 'out' && $log->is_valid) {
                $this->writeManualOcrPause(10);
                if ($this->scanHoldReason() === 'exit_pending') {
                    $this->clearScanHold();
                }
                $log->load('monthlyTicket');
                return response()->json(array_merge([
                    'success' => true,
                    'message' => 'Đã xác nhận Hợp lệ! Xe đã chuyển sang danh sách xe vào đã ra.',
                    'pause_ocr_s' => 10,
                ], $this->feePayload($log, [
                    'fee' => (int) ($log->fee ?? 0),
                    'hours' => ($log->ticket_type === 'monthly') ? 0 : ParkingFee::billedHours($log->entry_time, $log->exit_time ?: now()),
                ])));
            }

            $exitAt = now();
            ParkingFee::applyOnCheckout($log, $exitAt);
            $log->fill([
                'status' => 'out',
                'exit_time' => $exitAt,
                'guard_out_id' => auth()->id(),
                'is_valid' => true
            ]);
            $log->save();

            $this->purgeArmedExitCodeEverywhere(strtoupper((string) $log->code));
            $this->writeManualOcrPause(10);
            if ($this->scanHoldReason() === 'exit_pending') {
                $this->clearScanHold();
            }

            $log->load('monthlyTicket');
            return response()->json(array_merge([
                'success' => true,
                'message' => 'Đã xác nhận Hợp lệ! Xe đã chuyển sang danh sách xe vào đã ra.',
                'pause_ocr_s' => 10,
            ], $this->feePayload($log, [
                'fee' => (int) ($log->fee ?? 0),
                'hours' => ($log->ticket_type === 'monthly') ? 0 : ParkingFee::billedHours($log->entry_time, $log->exit_time),
            ])));
        }

        // Không hợp lệ → giữ trong danh sách "Xe vào chưa ra", xóa ảnh ra để có thể quét lại
        $log->update([
            'status' => 'in',
            'exit_time' => null,
            'exit_image' => null,
            'exit_plate_number' => null,
            'is_valid' => false,
            'guard_out_id' => auth()->id()
        ]);
        // Nhả khóa — tài khoản khác có thể kích hoạt lại (xanh)
        $this->purgeArmedExitCodeEverywhere(strtoupper((string) $log->code));
        if ($this->scanHoldReason() === 'exit_pending') {
            $this->clearScanHold();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận Không hợp lệ! Có thể nhập lại mã để quét xe ra lại.',
            'pause_ocr_s' => 0,
        ]);
    }

    /**
     * Hủy lượt đối chiếu xe ra (sai mã / biển lỗi / F5 chưa xác nhận) → cho phép nhập mã và quét lại.
     * Không truyền log_id → hủy mọi lượt đang chờ xác nhận.
     */
    public function retryExitAttempt(Request $request)
    {
        $logId = $request->input('log_id');
        if ($logId) {
            $uid = auth()->id();
            $log = VehicleLog::where('id', $logId)
                ->where('guard_out_id', $uid)
                ->first();
            if ($log && $log->status === 'in' && $log->is_valid === null && $log->exit_image) {
                $code = strtoupper((string) $log->code);
                $log->update([
                    'exit_time' => null,
                    'exit_image' => null,
                    'exit_plate_number' => null,
                    'is_valid' => null,
                    'guard_out_id' => null,
                ]);
                $this->releaseGlobalArmedCode($code);
            }
        } else {
            // F5 / mở lại trang giám sát — chỉ hủy lượt đối chiếu do chính TK này quét ra
            $uid = auth()->id();
            $pendingLogs = VehicleLog::where('status', 'in')
                ->where('guard_out_id', $uid)
                ->whereNull('is_valid')
                ->whereNotNull('exit_image')
                ->get(['id', 'code']);

            VehicleLog::where('status', 'in')
                ->where('guard_out_id', $uid)
                ->whereNull('is_valid')
                ->whereNotNull('exit_image')
                ->update([
                    'exit_time' => null,
                    'exit_image' => null,
                    'exit_plate_number' => null,
                    'is_valid' => null,
                    'guard_out_id' => null,
                ]);

            foreach ($pendingLogs as $pendingLog) {
                $this->releaseGlobalArmedCode(strtoupper((string) $pendingLog->code));
            }
        }

        $this->clearArmedExitCodeStorage();
        if ($this->scanHoldReason() === 'exit_pending') {
            $this->clearScanHold();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy lượt ra. Nhập lại mã code để quét biển lại.',
        ]);
    }

    public function getRecentLogs()
    {
        $user = auth()->user();
        $uid = $user ? (int) $user->id : 0;
        $isManager = ($user->role ?? null) === 'manager';
        $ticketType = request()->query('type', 'daily');
        if (!in_array($ticketType, ['daily', 'monthly'], true)) {
            $ticketType = 'daily';
        }

        $typeFilter = function ($query) use ($ticketType) {
            if ($ticketType === 'monthly') {
                $query->where('ticket_type', 'monthly');
            } else {
                $query->where(function ($q) {
                    $q->where('ticket_type', 'daily')->orWhereNull('ticket_type');
                });
            }
        };

        // Manager xem toàn bộ; bảo vệ chỉ xem log liên quan tài khoản mình
        $pendingQuery = VehicleLog::with(['guardIn', 'monthlyTicket'])
            ->where($typeFilter)
            ->where(function ($query) {
                $query->where('status', 'in')->orWhere('is_valid', false);
            });
        if (!$isManager) {
            $pendingQuery->where('guard_in_id', $uid)->take(50);
        }

        $pendingLogs = $pendingQuery
            ->orderBy('entry_time', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'code' => $log->code,
                    'plate_number' => $log->plate_number,
                    'entry_time' => \Carbon\Carbon::parse($log->entry_time)->format('d/m/Y H:i:s'),
                    'guard_in' => $log->guardIn ? $log->guardIn->fullname : 'N/A',
                    'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
                    'is_valid' => $log->is_valid,
                    'ticket_type' => $log->ticket_type ?? 'daily',
                    'monthly_code' => $log->monthlyTicket?->code,
                    'registered_plate' => $log->monthlyTicket?->plate_number,
                    'fee' => (int) ($log->fee ?? 0),
                    'fee_text' => ParkingFee::formatVnd((int) ($log->fee ?? 0)),
                ];
            });

        $completedQuery = VehicleLog::with(['guardIn', 'guardOut', 'monthlyTicket'])
            ->where($typeFilter)
            ->where('status', 'out')
            ->where(function ($query) {
                $query->whereNull('is_valid')->orWhere('is_valid', true);
            });
        if (!$isManager) {
            $completedQuery->where(function ($q) use ($uid) {
                $q->where('guard_in_id', $uid)->orWhere('guard_out_id', $uid);
            });
            $completedQuery->take(50);
        }

        $completedLogs = $completedQuery
            ->orderBy('exit_time', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'code' => $log->code,
                    'plate_number' => $log->plate_number,
                    'exit_plate_number' => $log->exit_plate_number,
                    'entry_time' => \Carbon\Carbon::parse($log->entry_time)->format('d/m/Y H:i:s'),
                    'exit_time' => $log->exit_time ? \Carbon\Carbon::parse($log->exit_time)->format('d/m/Y H:i:s') : 'N/A',
                    'guard_in' => $log->guardIn ? $log->guardIn->fullname : 'N/A',
                    'guard_out' => $log->guardOut ? $log->guardOut->fullname : 'N/A',
                    'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
                    'exit_image' => $log->exit_image ? asset($log->exit_image) : null,
                    'ticket_type' => $log->ticket_type ?? 'daily',
                    'monthly_code' => $log->monthlyTicket?->code,
                    'registered_plate' => $log->monthlyTicket?->plate_number,
                    'fee' => (int) ($log->fee ?? 0),
                    'fee_text' => ParkingFee::formatVnd((int) ($log->fee ?? 0)),
                ];
            });

        $pendingCountQuery = VehicleLog::query()
            ->where($typeFilter)
            ->where(function ($query) {
                $query->where('status', 'in')->orWhere('is_valid', false);
            });
        if (!$isManager) {
            $pendingCountQuery->where('guard_in_id', $uid);
        }

        $completedCountQuery = VehicleLog::query()
            ->where($typeFilter)
            ->where('status', 'out')
            ->where(function ($query) {
                $query->whereNull('is_valid')->orWhere('is_valid', true);
            });
        if (!$isManager) {
            $completedCountQuery->where(function ($q) use ($uid) {
                $q->where('guard_in_id', $uid)->orWhere('guard_out_id', $uid);
            });
        }

        return response()->json([
            'success' => true,
            'pending' => $pendingLogs,
            'completed' => $completedLogs,
            'pending_count' => $pendingCountQuery->count(),
            'completed_count' => $completedCountQuery->count(),
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Máy tính poll trạng thái: xe vào mới + lượt đang chờ xác nhận Hợp lệ.
     */
    public function guardMonitorState()
    {
        $this->releaseSessionLock();

        $uid = auth()->id();

        $lastEntry = VehicleLog::query()
            ->with('monthlyTicket')
            ->where('guard_in_id', $uid)
            ->where('entry_time', '>=', now()->subMinutes(5))
            ->where(function ($q) {
                $q->whereNull('monthly_ticket_id')
                    ->orWhere('monthly_confirmed', true)
                    ->orWhere('monthly_confirmed', false);
            })
            ->orderByDesc('id')
            ->first();

        $pendingMonthly = $this->readPendingMonthlyEntry();
        $pendingMonthlyPayload = $pendingMonthly ? $this->pendingMonthlyPublicPayload($pendingMonthly) : null;

        $pendingValidation = VehicleLog::query()
            // Chỉ hiện trên TK đã quét xe ra (checkout) — không hiện trên TK check-in mã đó
            ->where('guard_out_id', $uid)
            ->whereNotNull('exit_image')
            ->whereNull('is_valid')
            ->where('status', 'in')
            ->orderByDesc('id')
            ->first();

        $entryPayload = null;
        if ($lastEntry) {
            $isMonthly = ($lastEntry->ticket_type ?? 'daily') === 'monthly';
            $entryPayload = [
                'id' => $lastEntry->id,
                'plate_number' => $lastEntry->plate_number,
                'code' => $isMonthly && $lastEntry->monthlyTicket ? $lastEntry->monthlyTicket->code : $lastEntry->code,
                'ticket_type' => $isMonthly ? 'monthly' : 'daily',
                'monthly_code' => $lastEntry->monthlyTicket?->code,
                'entry_image' => $lastEntry->entry_image ? asset($lastEntry->entry_image) : null,
                'entry_time' => optional($lastEntry->entry_time)->toDateTimeString(),
            ];
        }

        $pendingPayload = null;
        if ($pendingValidation) {
            $pendingValidation->load('monthlyTicket');
            $isMatch = $this->platesMatch($pendingValidation->plate_number, $pendingValidation->exit_plate_number);
            $pendingPayload = array_merge([
                'log_id' => $pendingValidation->id,
                'code' => $pendingValidation->code,
                'entry_plate' => $pendingValidation->plate_number,
                'exit_plate' => $pendingValidation->exit_plate_number,
                'entry_image' => $pendingValidation->entry_image ? asset($pendingValidation->entry_image) : null,
                'exit_image' => $pendingValidation->exit_image ? asset($pendingValidation->exit_image) : null,
                'match' => $isMatch,
                'message' => $isMatch
                    ? ('Biển số khớp: ' . $pendingValidation->exit_plate_number . '. Vui lòng xác nhận.')
                    : ('Biển số xe ra (' . $pendingValidation->exit_plate_number . ') không khớp với xe vào (' . $pendingValidation->plate_number . ').'),
            ], $this->feePayload($pendingValidation, ['fee' => 0, 'hours' => 0]));
        }

        $matchedPayload = null;
        if (!$pendingPayload) {
            $matchedExit = VehicleLog::query()
                ->where('guard_out_id', $uid)
                ->where('status', 'out')
                ->where('is_valid', true)
                ->whereNotNull('exit_image')
                ->where('exit_time', '>=', now()->subSeconds(15))
                ->orderByDesc('id')
                ->first();
            if ($matchedExit && $this->platesMatch($matchedExit->plate_number, $matchedExit->exit_plate_number)) {
                $matchedExit->load('monthlyTicket');
                $matchedPayload = array_merge([
                    'log_id' => $matchedExit->id,
                    'code' => $matchedExit->code,
                    'entry_plate' => $matchedExit->plate_number,
                    'exit_plate' => $matchedExit->exit_plate_number,
                    'entry_image' => $matchedExit->entry_image ? asset($matchedExit->entry_image) : null,
                    'exit_image' => $matchedExit->exit_image ? asset($matchedExit->exit_image) : null,
                    'match' => true,
                    'already_out' => true,
                    'message' => 'Biển số khớp: ' . $matchedExit->exit_plate_number,
                ], $this->feePayload($matchedExit, [
                    'fee' => (int) ($matchedExit->fee ?? 0),
                    'hours' => ($matchedExit->ticket_type === 'monthly')
                        ? 0
                        : ParkingFee::billedHours($matchedExit->entry_time, $matchedExit->exit_time ?: now()),
                ]));
            }
        }

        $armedMonthly = $this->readArmedMonthlyPayload();

        return response()->json([
            'success' => true,
            'last_entry' => $entryPayload,
            'pending_validation' => $pendingPayload,
            'pending_monthly_entry' => $pendingMonthlyPayload,
            'matched_exit' => $matchedPayload,
            'armed_exit_code' => $this->readArmedExitCode(),
            'armed_monthly_code' => $armedMonthly['code'] ?? null,
            'armed_monthly_plate' => $armedMonthly['plate_number'] ?? null,
            'entry_alert' => $this->readEntryAlert(),
            'hold_scan' => $this->isScanHoldActive(),
            'hold_reason' => $this->scanHoldReason(),
            'live_preview' => [
                // Không nhúng frame ở đây — LIVE dùng /api/live-status riêng
                'entry' => $this->readLivePreview('entry', 0, false),
                'exit' => $this->readLivePreview('exit', 0, false),
            ],
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * ĐT đẩy frame LIVE → máy tính giám sát hiện lại.
     * Nhận multipart file hoặc raw body image/jpeg (nhanh hơn trên LAN).
     */
    public function uploadLivePreview(Request $request)
    {
        $side = $request->input('side', $request->query('side', 'entry')) === 'exit' ? 'exit' : 'entry';
        $deviceId = $this->normalizeLiveDeviceId(
            $request->input('device_id', $request->query('device_id', $request->header('X-Live-Device-Id', '')))
        );

        $bin = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->getRealPath();
            if ($path && is_file($path)) {
                $bin = @file_get_contents($path);
            }
        } else {
            $raw = $request->getContent();
            if (is_string($raw) && strlen($raw) > 200) {
                $bin = $raw;
            }
        }

        $phase = $this->normalizeScanPhase($request->input('phase', $request->query('phase', '')));
        $hasImage = is_string($bin) && strlen($bin) >= 200;

        if (!$hasImage && $phase === '') {
            return response()->json(['success' => false, 'message' => 'Thiếu ảnh'], 422);
        }

        // Giới hạn ~180KB — LIVE chỉ cần khung nhỏ
        if ($hasImage && strlen($bin) > 180000) {
            return response()->json(['success' => false, 'message' => 'Frame quá lớn'], 413);
        }

        $this->releaseSessionLock();

        if ($deviceId !== '') {
            $claim = $this->assertLivePublisherAllowed($side, $deviceId);
            if (!$claim['ok']) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => $claim['message'],
                ], 409);
            }
            $this->touchLivePublisher($side, $deviceId, $claim['state'] ?? null);
        }

        $uid = $this->currentUserScopeId();
        $dir = $this->ensureDir(public_path('uploads/live/' . $uid));

        $filename = $side . '.jpg';
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        $tmpPath = $dir . DIRECTORY_SEPARATOR . $side . '.uploading.jpg';
        $metaPath = $dir . DIRECTORY_SEPARATOR . $side . '.json';
        $meta = $this->readLiveMetaFile($metaPath);
        $meta['user_id'] = $uid;

        $ts = (int) ($meta['ts'] ?? 0);

        if ($hasImage) {
            // Ghi file tạm rồi rename — tránh đọc ảnh nửa chừng
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            if (@file_put_contents($tmpPath, $bin, LOCK_EX) === false) {
                return response()->json(['success' => false, 'message' => 'Không ghi được frame'], 500);
            }
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            if (!@rename($tmpPath, $fullPath)) {
                @copy($tmpPath, $fullPath);
                @unlink($tmpPath);
            }

            // Timestamp ms (Windows filemtime chỉ ~1s → LIVE bị giật/lag)
            $ts = (int) round(microtime(true) * 1000);
            $meta['ts'] = $ts;
        }

        if ($phase !== '') {
            $meta['scan_phase'] = $phase;
            $meta['phase_ts'] = (int) round(microtime(true) * 1000);
        }

        @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES));

        $url = $ts
            ? ('/public/uploads/live/' . $uid . '/' . $filename . '?t=' . $ts)
            : null;
        $pauseOcr = $this->readManualOcrPauseRemaining();
        return response()->json([
            'success' => true,
            'side' => $side,
            'url' => $url,
            'ts' => $ts ?: null,
            'scan_phase' => $meta['scan_phase'] ?? 'detect',
            'pause_ocr' => $pauseOcr > 0,
            'pause_ocr_s' => $pauseOcr,
            'hold_scan' => $this->isScanHoldActive(),
            'hold_reason' => $this->scanHoldReason(),
        ]);
    }

    /**
     * Poll LIVE siêu nhẹ — chỉ gửi base64 frame khi có frame mới hơn entry_ts/exit_ts.
     */
    public function livePreviewStatus(Request $request)
    {
        $this->releaseSessionLock();

        $sinceEntry = (int) $request->query('entry_ts', 0);
        $sinceExit = (int) $request->query('exit_ts', 0);

        return response()->json([
            'success' => true,
            'hold_scan' => $this->isScanHoldActive(),
            'hold_reason' => $this->scanHoldReason(),
            'live_preview' => [
                'entry' => $this->readLivePreview('entry', $sinceEntry),
                'exit' => $this->readLivePreview('exit', $sinceExit),
            ],
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function readLivePreview($side, $sinceTs = 0, $includeFrame = true)
    {
        $side = $side === 'exit' ? 'exit' : 'entry';
        $uid = $this->currentUserScopeId();
        $dir = public_path('uploads/live/' . $uid);
        $fullPath = $dir . DIRECTORY_SEPARATOR . $side . '.jpg';
        $metaPath = $dir . DIRECTORY_SEPARATOR . $side . '.json';

        if (!is_file($fullPath)) {
            return ['active' => false, 'url' => null, 'ts' => null, 'frame' => null, 'scan_phase' => null];
        }

        clearstatcache(true, $fullPath);
        clearstatcache(true, $metaPath);

        $ts = null;
        $meta = $this->readLiveMetaFile($metaPath);
        if (isset($meta['ts'])) {
            $ts = (int) $meta['ts'];
        }
        if (!$ts) {
            // Fallback cũ: giây → nhân 1000
            $ts = ((int) @filemtime($fullPath)) * 1000;
        }

        $ageMs = (int) round(microtime(true) * 1000) - $ts;
        // Frame cũ hơn 2.5s → ĐT đã tắt / mất mạng
        $active = $ageMs >= 0 && $ageMs <= 2500;

        $payload = [
            'active' => $active,
            'url' => $active ? ('/public/uploads/live/' . $uid . '/' . $side . '.jpg?t=' . $ts) : null,
            'ts' => $ts,
            'frame' => null,
            'scan_phase' => $this->liveScanPhaseFromMeta($meta),
        ];

        // Chỉ đính frame khi monitor chưa có bản này — bỏ HTTP tải ảnh lần 2
        if ($includeFrame && $active && $ts > (int) $sinceTs) {
            $bin = @file_get_contents($fullPath);
            if (is_string($bin) && strlen($bin) > 200) {
                $payload['frame'] = base64_encode($bin);
            }
        }

        return $payload;
    }

    public function armedExitCodeStatus()
    {
        $this->releaseSessionLock();

        return response()->json([
            'success' => true,
            'armed_exit_code' => $this->readArmedExitCode(),
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function armedMonthlyCodeStatus()
    {
        $this->releaseSessionLock();
        $armed = $this->readArmedMonthlyPayload();

        return response()->json([
            'success' => true,
            'armed_monthly_code' => $armed['code'] ?? null,
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function monitorStateDir()
    {
        $uid = $this->currentUserScopeId();
        return $this->ensureDir(storage_path('app/monitor/' . $uid));
    }

    private function entryAlertPath()
    {
        return $this->monitorStateDir() . DIRECTORY_SEPARATOR . 'entry_alert.json';
    }

    private function entryAlertCacheKey()
    {
        return 'entry_alert_' . $this->currentUserScopeId();
    }

    private function writeEntryAlert(array $payload)
    {
        $payload['id'] = $payload['id'] ?? ('ea_' . time() . '_' . uniqid());
        $payload['ts'] = time();
        $payload['user_id'] = $this->currentUserScopeId();
        file_put_contents($this->entryAlertPath(), json_encode($payload, JSON_UNESCAPED_UNICODE));
        try {
            \Illuminate\Support\Facades\Cache::put($this->entryAlertCacheKey(), $payload, 90);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function readEntryAlert()
    {
        $data = null;
        try {
            $cached = \Illuminate\Support\Facades\Cache::get($this->entryAlertCacheKey());
            if (is_array($cached) && !empty($cached['id'])) {
                $data = $cached;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        if (!$data) {
            $path = $this->entryAlertPath();
            if (!is_file($path)) {
                return null;
            }
            $data = json_decode(@file_get_contents($path), true);
            if (!is_array($data) || empty($data['id'])) {
                return null;
            }
        }

        $age = time() - (int) ($data['ts'] ?? 0);
        // Chỉ hiện trên PC trong ~60s — trừ khi đang chờ bấm Đồng ý (giữ đến khi ack)
        if ($age > 60 && !$this->isScanHoldActive()) {
            $path = $this->entryAlertPath();
            if (is_file($path)) {
                @unlink($path);
            }
            try {
                \Illuminate\Support\Facades\Cache::forget($this->entryAlertCacheKey());
            } catch (\Throwable $e) {
                // ignore
            }
            return null;
        }

        return [
            'id' => (string) $data['id'],
            'type' => $data['type'] ?? 'already_inside',
            'log_id' => $data['log_id'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'code' => $data['code'] ?? null,
            'entry_time' => $data['entry_time'] ?? null,
            'entry_image' => $data['entry_image'] ?? null,
            'message' => $data['message'] ?? 'Xe vẫn đang nằm trong bãi.',
            'ts' => (int) ($data['ts'] ?? 0),
        ];
    }

    private function armedExitCodePath()
    {
        return $this->monitorStateDir() . DIRECTORY_SEPARATOR . 'armed_exit_code.json';
    }

    private function armedExitCodeCacheKey()
    {
        return 'armed_exit_code_' . $this->currentUserScopeId();
    }

    /**
     * Khóa mã toàn cục (file + flock) — tài khoản nào nhập trước giữ mã,
     * tài khoản khác nhập cùng mã trong lúc còn hiệu lực → từ chối.
     */
    private function globalArmedLocksPath()
    {
        return storage_path('app' . DIRECTORY_SEPARATOR . 'armed_exit_locks.json');
    }

    private function withGlobalArmedLocks(callable $fn)
    {
        $path = $this->globalArmedLocksPath();
        $dir = dirname($path);
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return $fn([]);
        }

        try {
            flock($fp, LOCK_EX);
            rewind($fp);
            $raw = stream_get_contents($fp);
            $locks = json_decode(is_string($raw) ? $raw : '{}', true);
            if (!is_array($locks)) {
                $locks = [];
            }

            $now = time();
            foreach ($locks as $code => $row) {
                if (!is_array($row) || ($now - (int) ($row['ts'] ?? 0)) > 300) {
                    unset($locks[$code]);
                }
            }

            $result = $fn($locks);

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($locks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);

            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    private function claimGlobalArmedCode($code, $logId)
    {
        $code = strtoupper((string) $code);
        $uid = (int) auth()->id();
        $logId = (int) $logId;

        return $this->withGlobalArmedLocks(function (&$locks) use ($code, $logId, $uid) {
            $holder = $locks[$code] ?? null;
            if (is_array($holder)) {
                $holderUid = (int) ($holder['user_id'] ?? 0);
                if ($holderUid !== $uid) {
                    return [
                        'ok' => false,
                        'message' => 'Mã đang được tài khoản khác sử dụng — không nhận mã.',
                    ];
                }
            }

            // Đổi mã: nhả các mã khác do chính tài khoản này đang giữ
            foreach ($locks as $c => $row) {
                if ($c !== $code && (int) ($row['user_id'] ?? 0) === $uid) {
                    unset($locks[$c]);
                }
            }

            $locks[$code] = [
                'user_id' => $uid,
                'log_id' => $logId,
                'ts' => time(),
            ];

            return ['ok' => true];
        });
    }

    private function releaseGlobalArmedCode($code = null)
    {
        $uid = (int) auth()->id();
        $code = $code !== null ? strtoupper((string) $code) : null;

        $this->withGlobalArmedLocks(function (&$locks) use ($uid, $code) {
            if ($code) {
                if (isset($locks[$code]) && (int) ($locks[$code]['user_id'] ?? 0) === $uid) {
                    unset($locks[$code]);
                }
                return true;
            }

            foreach ($locks as $c => $row) {
                if ((int) ($row['user_id'] ?? 0) === $uid) {
                    unset($locks[$c]);
                }
            }
            return true;
        });
    }

    /**
     * Gia hạn khóa toàn cục khi đang đối chiếu (tránh hết hạn 5 phút giữa chừng).
     */
    private function touchGlobalArmedCode($code, $logId)
    {
        $code = strtoupper((string) $code);
        $uid = (int) auth()->id();
        $logId = (int) $logId;

        $this->withGlobalArmedLocks(function (&$locks) use ($code, $logId, $uid) {
            $locks[$code] = [
                'user_id' => $uid,
                'log_id' => $logId,
                'ts' => time(),
                'pending' => true,
            ];
            return true;
        });
    }

    /**
     * Xe đã ra / mã không còn dùng được: nhả khóa toàn cục + xóa kích hoạt local của mọi tài khoản.
     */
    private function purgeArmedExitCodeEverywhere(string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }

        $this->withGlobalArmedLocks(function (&$locks) use ($code) {
            unset($locks[$code]);
            return true;
        });

        $root = storage_path('app/monitor');
        if (!is_dir($root)) {
            return;
        }
        foreach (glob($root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'armed_exit_code.json') ?: [] as $path) {
            $data = json_decode((string) @file_get_contents($path), true);
            if (!is_array($data) || strtoupper((string) ($data['code'] ?? '')) !== $code) {
                continue;
            }
            @unlink($path);
            $scope = basename(dirname($path));
            try {
                \Illuminate\Support\Facades\Cache::forget('armed_exit_code_' . $scope);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Chỉ tắt kích hoạt local (ĐT ngừng quét) — không nhả khóa toàn cục.
     */
    private function clearLocalArmedExitCodeOnly()
    {
        $path = $this->armedExitCodePath();
        if (is_file($path)) {
            @unlink($path);
        }
        try {
            \Illuminate\Support\Facades\Cache::forget($this->armedExitCodeCacheKey());
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function writeArmedExitCode($code, $logId)
    {
        $payload = [
            'code' => strtoupper((string) $code),
            'log_id' => $logId,
            'ts' => time(),
            'by_user' => auth()->id(),
        ];
        file_put_contents($this->armedExitCodePath(), json_encode($payload, JSON_UNESCAPED_UNICODE));
        try {
            \Illuminate\Support\Facades\Cache::put($this->armedExitCodeCacheKey(), $payload, 300);
        } catch (\Throwable $e) {
            // ignore cache errors — file vẫn là nguồn chính
        }
    }

    private function clearArmedExitCodeStorage()
    {
        $current = null;
        try {
            $current = $this->readArmedExitCodeRaw();
        } catch (\Throwable $e) {
            $current = null;
        }

        $path = $this->armedExitCodePath();
        if (is_file($path)) {
            @unlink($path);
        }
        try {
            \Illuminate\Support\Facades\Cache::forget($this->armedExitCodeCacheKey());
        } catch (\Throwable $e) {
            // ignore
        }

        $this->releaseGlobalArmedCode($current);
    }

    private function dropArmedExitIfMonthlyBlocked(?string $code): bool
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return false;
        }
        if ($this->monthlyExitBlockedReason($this->findOpenLogByCode($code)) === null) {
            return false;
        }

        $path = $this->armedExitCodePath();
        if (is_file($path)) {
            @unlink($path);
        }
        try {
            \Illuminate\Support\Facades\Cache::forget($this->armedExitCodeCacheKey());
        } catch (\Throwable $e) {
            // ignore
        }
        $this->releaseGlobalArmedCode($code);
        return true;
    }

    private function readArmedExitCode()
    {
        $code = $this->readArmedExitCodeRaw();
        if ($code && $this->dropArmedExitIfMonthlyBlocked($code)) {
            return null;
        }
        return $code;
    }

    private function readArmedExitCodeRaw()
    {
        // Ưu tiên cache (nhanh, ít race), fallback file
        try {
            $cached = \Illuminate\Support\Facades\Cache::get($this->armedExitCodeCacheKey());
            if (is_array($cached) && !empty($cached['code'])) {
                $age = time() - (int) ($cached['ts'] ?? 0);
                if ($age <= 300) {
                    return strtoupper((string) $cached['code']);
                }
                \Illuminate\Support\Facades\Cache::forget($this->armedExitCodeCacheKey());
            }
        } catch (\Throwable $e) {
            // fall through to file
        }

        $path = $this->armedExitCodePath();
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode(@file_get_contents($path), true);
        if (!is_array($data) || empty($data['code'])) {
            return null;
        }
        $age = time() - (int) ($data['ts'] ?? 0);
        if ($age > 300) {
            // Hết hạn: xóa local + nhả khóa toàn cục nếu mình đang giữ
            $expiredCode = strtoupper((string) $data['code']);
            @unlink($path);
            try {
                \Illuminate\Support\Facades\Cache::forget($this->armedExitCodeCacheKey());
            } catch (\Throwable $e) {
                // ignore
            }
            $this->releaseGlobalArmedCode($expiredCode);
            return null;
        }
        // Đồng bộ lại cache nếu file còn hạn
        try {
            \Illuminate\Support\Facades\Cache::put($this->armedExitCodeCacheKey(), $data, 300 - $age);
        } catch (\Throwable $e) {
            // ignore
        }
        return strtoupper((string) $data['code']);
    }

    public function lookupMonthlyTicket(Request $request)
    {
        $this->releaseSessionLock();
        $code = strtoupper(trim((string) $request->input('code', '')));
        return response()->json($this->monthlyLookupResponse($code), 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function armMonthlyCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            $this->clearArmedMonthlyCode();
            return response()->json($this->monthlyLookupResponse(''));
        }

        $payload = $this->monthlyLookupResponse($code);
        if (!empty($payload['found'])) {
            $ticket = MonthlyTicket::findUsableByCode($code);
            if ($ticket) {
                $this->writeArmedMonthlyCode($ticket);
            }
        } else {
            $this->clearArmedMonthlyCode();
        }

        return response()->json($payload, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function clearMonthlyCode()
    {
        $this->clearArmedMonthlyCode();
        return response()->json(['success' => true]);
    }

    /**
     * F5 / mở lại trang giám sát: hủy đối chiếu vé tháng chưa xác nhận, không lưu DB.
     */
    public function dismissPendingMonthlyEntry()
    {
        $this->clearPendingMonthlyEntry(true);
        if ($this->scanHoldReason() === 'monthly_pending') {
            $this->clearScanHold();
        }
        $this->clearArmedMonthlyCode();
        $this->releaseSessionLock();

        return response()->json([
            'success' => true,
            'hold_scan' => $this->isScanHoldActive(),
        ]);
    }

    public function validateMonthlyEntry(Request $request)
    {
        $request->validate([
            'is_valid' => 'required',
        ]);

        $pendingId = (string) $request->input('pending_id', $request->input('log_id', ''));
        $pending = $this->readPendingMonthlyEntry();
        if (!$pending) {
            return response()->json([
                'success' => false,
                'message' => 'Không có lượt đối chiếu vé tháng đang chờ.',
            ]);
        }
        if ($pendingId !== '' && (string) ($pending['id'] ?? '') !== $pendingId) {
            return response()->json([
                'success' => false,
                'message' => 'Lượt đối chiếu đã hết hạn. Hãy quét lại.',
            ]);
        }
        if ((int) ($pending['user_id'] ?? 0) !== (int) auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền xác nhận lượt này.',
            ], 403);
        }

        $isValid = filter_var($request->is_valid, FILTER_VALIDATE_BOOLEAN);
        $ticket = MonthlyTicket::findUsableByCode((string) ($pending['code'] ?? ''))
            ?: MonthlyTicket::find($pending['ticket_id'] ?? 0);
        $monthlyCode = $ticket?->code ?? ($pending['code'] ?? '');

        if (!$isValid) {
            $this->clearPendingMonthlyEntry(true);
            $this->clearScanHold();
            return response()->json([
                'success' => true,
                'rejected' => true,
                'ticket_type' => 'monthly',
                'code' => $monthlyCode,
                'plate_number' => $pending['plate_number'] ?? null,
                'message' => 'Đã từ chối xe vào.',
                'pause_ocr_s' => 0,
            ]);
        }

        if (!$ticket || !$ticket->isUsable()) {
            $this->clearPendingMonthlyEntry(true);
            $this->clearScanHold();
            return response()->json([
                'success' => false,
                'message' => 'Vé tháng không còn hiệu lực. Không lưu lượt này.',
            ]);
        }

        if ($this->findOccupiedMonthlyLog($ticket)) {
            $this->clearPendingMonthlyEntry(true);
            $this->clearScanHold();
            return response()->json([
                'success' => false,
                'message' => 'Vé tháng đang có xe trong bãi (chưa ra). Không lưu lượt vé tháng này.',
            ]);
        }

        $log = VehicleLog::create([
            'plate_number' => $pending['plate_number'] ?? $this->unrecognizedPlateLabel(),
            'code' => strtoupper((string) $ticket->code),
            'ticket_type' => 'monthly',
            'monthly_ticket_id' => $ticket->id,
            'status' => 'in',
            'entry_time' => now(),
            'entry_image' => $pending['entry_image'] ?? null,
            'guard_in_id' => auth()->id(),
            'monthly_match' => false,
            'monthly_confirmed' => true,
            'fee' => 0,
        ]);

        $this->clearPendingMonthlyEntry(false);
        $this->clearArmedMonthlyCode();
        $this->writeManualOcrPause(10);
        $this->clearScanHold();

        return response()->json([
            'success' => true,
            'rejected' => false,
            'log_id' => $log->id,
            'ticket_type' => 'monthly',
            'code' => $ticket->code,
            'plate_number' => $log->plate_number,
            'image_url' => $log->entry_image ? asset($log->entry_image) : null,
            'message' => 'Đã xác nhận hợp lệ. Xe vào bằng vé tháng ' . $ticket->code . '.',
            'pause_ocr_s' => 10,
        ]);
    }

    /**
     * Máy tính nhập mã code (như quẹt thẻ) → kích hoạt ĐT quét xe ra.
     */
    public function armExitCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if (strlen($code) !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Mã code phải gồm đúng 6 ký tự!',
            ], 422);
        }

        $log = $this->findOpenLogByCode($code);

        if (!$log) {
            $this->clearArmedExitCodeStorage();
            return response()->json([
                'success' => false,
                'message' => 'Mã không tồn tại.',
                'retryable' => true,
            ], 404);
        }

        $blocked = $this->monthlyExitBlockedReason($log);
        if ($blocked) {
            $this->clearArmedExitCodeStorage();
            return response()->json([
                'success' => false,
                'message' => $blocked,
                'monthly_disabled' => true,
                'retryable' => true,
            ], 422);
        }

        // Đang đối chiếu ở tài khoản khác → giữ báo đỏ conflict (chưa Hợp lệ / Không hợp lệ / F5)
        if ($log->exit_image && $log->is_valid === null && (string) $log->status === 'in') {
            $holderId = (int) ($log->guard_out_id ?? 0);
            if ($holderId && $holderId !== (int) auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã đang được tài khoản khác sử dụng — không nhận mã.',
                    'conflict' => true,
                    'retryable' => true,
                ], 409);
            }
        }

        $claim = $this->claimGlobalArmedCode($code, $log->id);
        if (empty($claim['ok'])) {
            return response()->json([
                'success' => false,
                'message' => $claim['message'] ?? 'Mã đang được tài khoản khác sử dụng — không nhận mã.',
                'conflict' => true,
                'retryable' => true,
            ], 409);
        }

        $this->writeArmedExitCode($code, $log->id);

        return response()->json([
            'success' => true,
            'code' => $code,
            'plate_number' => $log->plate_number,
            'message' => 'Đã kích hoạt quét xe ra. Điện thoại sẽ tự chụp khi thấy biển.',
        ]);
    }

    public function clearExitCode()
    {
        $this->clearArmedExitCodeStorage();
        return response()->json(['success' => true]);
    }

    /**
     * WebRTC signaling (file) — ĐT publish / PC subscribe trên LAN.
     * Payload SDP/ICE luôn base64 JSON để tránh lỗi encoding.
     */
    public function webrtcPostSignal(Request $request)
    {
        $this->releaseSessionLock();

        $side = $request->input('side', 'entry') === 'exit' ? 'exit' : 'entry';
        $from = $request->input('from') === 'monitor' ? 'monitor' : 'phone';
        $type = (string) $request->input('type', '');
        $session = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->input('session', ''));
        $payload = (string) $request->input('payload', '');
        $deviceId = $this->normalizeLiveDeviceId($request->input('device_id', ''));

        if (!in_array($type, ['offer', 'answer', 'ice', 'bye', 'claim'], true)) {
            return response()->json(['success' => false, 'message' => 'type không hợp lệ'], 422);
        }
        if ($session === '' || strlen($session) > 64) {
            return response()->json(['success' => false, 'message' => 'session không hợp lệ'], 422);
        }
        if (!in_array($type, ['bye', 'claim'], true) && $payload === '') {
            return response()->json(['success' => false, 'message' => 'Thiếu payload'], 422);
        }
        // Offer/answer/ICE payload ~ vài KB; chặn spam
        if (strlen($payload) > 200000) {
            return response()->json(['success' => false, 'message' => 'Payload quá lớn'], 413);
        }

        $state = $this->readWebrtcState($side);

        // ĐT claim quyền LIVE trước khi mở camera — chặn thiết bị thứ 2
        if ($type === 'claim' && $from === 'phone') {
            if ($deviceId === '') {
                return response()->json(['success' => false, 'message' => 'Thiếu device_id'], 422);
            }
            $claim = $this->assertLivePublisherAllowed($side, $deviceId, $state);
            if (!$claim['ok']) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => $claim['message'],
                ], 409);
            }
            $state = $claim['state'] ?? $state;
            if ($this->isLivePublisherActive($state) && ($state['device_id'] ?? '') === $deviceId) {
                $state['updated_at'] = time();
                $state['user_id'] = $this->currentUserScopeId();
            } else {
                $state = [
                    'session' => null,
                    'device_id' => $deviceId,
                    'seq' => 0,
                    'updated_at' => time(),
                    'user_id' => $this->currentUserScopeId(),
                    'msgs' => [],
                ];
            }
            $this->writeWebrtcState($side, $state);
            return response()->json(['success' => true, 'claimed' => true]);
        }

        if ($type === 'offer' && $from === 'phone') {
            if ($deviceId !== '') {
                $claim = $this->assertLivePublisherAllowed($side, $deviceId, $state);
                if (!$claim['ok']) {
                    return response()->json([
                        'success' => false,
                        'conflict' => true,
                        'message' => $claim['message'],
                    ], 409);
                }
            }
            // Session mới từ ĐT → xóa tín hiệu cũ
            $state = [
                'session' => $session,
                'device_id' => $deviceId !== '' ? $deviceId : ($state['device_id'] ?? null),
                'seq' => 0,
                'updated_at' => time(),
                'user_id' => $this->currentUserScopeId(),
                'msgs' => [],
            ];
        } elseif ($type === 'bye') {
            $ownsSession = ($state['session'] ?? null) === $session;
            $ownsDevice = $deviceId !== '' && ($state['device_id'] ?? null) === $deviceId;
            if ($ownsSession || $ownsDevice) {
                $state = [
                    'session' => null,
                    'device_id' => null,
                    'seq' => 0,
                    'updated_at' => time(),
                    'user_id' => $this->currentUserScopeId(),
                    'msgs' => [],
                ];
                $this->writeWebrtcState($side, $state);
            }
            return response()->json(['success' => true]);
        } elseif (($state['session'] ?? null) !== $session) {
            // Answer/ICE cho session đã hết hạn
            return response()->json([
                'success' => false,
                'message' => 'Session không khớp',
                'session' => $state['session'] ?? null,
            ], 409);
        }

        $seq = (int) ($state['seq'] ?? 0) + 1;
        $state['seq'] = $seq;
        $state['updated_at'] = time();
        $state['user_id'] = $this->currentUserScopeId();
        if ($from === 'phone' && $deviceId !== '') {
            $state['device_id'] = $deviceId;
        }
        $state['msgs'][] = [
            'id' => $seq,
            'from' => $from,
            'type' => $type,
            'payload' => $payload,
            'ts' => (int) round(microtime(true) * 1000),
        ];
        // Giữ tối đa 50 tin
        if (count($state['msgs']) > 50) {
            $state['msgs'] = array_slice($state['msgs'], -50);
        }
        $this->writeWebrtcState($side, $state);

        return response()->json([
            'success' => true,
            'id' => $seq,
            'session' => $session,
        ]);
    }

    public function webrtcPollSignal(Request $request)
    {
        $this->releaseSessionLock();

        $side = $request->query('side', 'entry') === 'exit' ? 'exit' : 'entry';
        $role = $request->query('role') === 'phone' ? 'phone' : 'monitor';
        $after = (int) $request->query('after', 0);
        $wantSession = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->query('session', ''));

        $state = $this->readWebrtcState($side);
        $session = $state['session'] ?? null;

        // State cũ > 3 phút không ai poll → coi như chết
        if ($session && (time() - (int) ($state['updated_at'] ?? 0)) > 180) {
            $state = [
                'session' => null,
                'device_id' => null,
                'seq' => 0,
                'updated_at' => time(),
                'user_id' => $this->currentUserScopeId(),
                'msgs' => [],
            ];
            $this->writeWebrtcState($side, $state);
            $session = null;
        }

        // Client còn session cũ trong khi ĐT đã mở session mới → trả full tin mới
        if ($wantSession !== '' && $session && $wantSession !== $session) {
            $after = 0;
        }

        // Keepalive: đang poll = đang LIVE → gia hạn session (tránh OCR lâu bị cắt RTC)
        if ($session && (time() - (int) ($state['updated_at'] ?? 0)) >= 8) {
            $state['updated_at'] = time();
            $this->writeWebrtcState($side, $state);
        }

        $msgs = [];
        if ($session) {
            foreach (($state['msgs'] ?? []) as $m) {
                if (!is_array($m)) {
                    continue;
                }
                if ((int) ($m['id'] ?? 0) <= $after) {
                    continue;
                }
                // Chỉ lấy tin từ phía kia
                if (($m['from'] ?? '') === $role) {
                    continue;
                }
                $msgs[] = $m;
            }
        }

        return response()->json([
            'success' => true,
            'side' => $side,
            'session' => $session,
            'messages' => $msgs,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function webrtcPath($side)
    {
        $uid = $this->currentUserScopeId();
        $dir = $this->ensureDir(storage_path('app/webrtc/' . $uid));
        $side = $side === 'exit' ? 'exit' : 'entry';
        return $dir . DIRECTORY_SEPARATOR . $side . '.json';
    }

    private function readWebrtcState($side)
    {
        $path = $this->webrtcPath($side);
        if (!is_file($path)) {
            return ['session' => null, 'device_id' => null, 'seq' => 0, 'updated_at' => 0, 'msgs' => []];
        }
        $raw = @file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['session' => null, 'device_id' => null, 'seq' => 0, 'updated_at' => 0, 'msgs' => []];
        }
        return $data;
    }

    private function writeWebrtcState($side, array $state)
    {
        $path = $this->webrtcPath($side);
        $tmp = $path . '.tmp';
        @file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
        if (!@rename($tmp, $path)) {
            @copy($tmp, $path);
            @unlink($tmp);
        }
    }

    /** TTL khóa LIVE: hết heartbeat → thiết bị khác được vào */
    private function livePublisherTtlSeconds()
    {
        return 30;
    }

    private function normalizeLiveDeviceId($raw)
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $raw);
        if ($id === '' || strlen($id) > 64) {
            return '';
        }
        return $id;
    }

    private function isLivePublisherActive(array $state)
    {
        $deviceId = (string) ($state['device_id'] ?? '');
        if ($deviceId === '') {
            return false;
        }
        return (time() - (int) ($state['updated_at'] ?? 0)) < $this->livePublisherTtlSeconds();
    }

    /**
     * @return array{ok:bool,message?:string,state?:array}
     */
    private function assertLivePublisherAllowed($side, $deviceId, ?array $state = null)
    {
        $state = $state ?? $this->readWebrtcState($side);
        if ($this->isLivePublisherActive($state) && ($state['device_id'] ?? '') !== $deviceId) {
            return [
                'ok' => false,
                'message' => 'Đang có thiết bị khác kết nối camera này',
                'state' => $state,
            ];
        }
        return ['ok' => true, 'state' => $state];
    }

    private function touchLivePublisher($side, $deviceId, ?array $state = null)
    {
        $state = $state ?? $this->readWebrtcState($side);
        $now = time();
        $sameDevice = ($state['device_id'] ?? '') === $deviceId;
        $needWrite = !$sameDevice || ($now - (int) ($state['updated_at'] ?? 0)) >= 8;
        if (!$needWrite) {
            return;
        }
        $state['device_id'] = $deviceId;
        $state['updated_at'] = $now;
        $state['user_id'] = $this->currentUserScopeId();
        $this->writeWebrtcState($side, $state);
    }
}
