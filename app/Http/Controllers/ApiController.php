<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleLog;
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
                'plate_number' => strtoupper($aiResult['plate']),
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
                    'message' => $aiResult['error']
                ]);
            }

            $plate = strtoupper($aiResult['plate']);
            $cleanPlate = preg_replace('/[^A-Z0-9]/i', '', $plate);

            // Xe vẫn còn trong bãi (chưa ra) → không cho nhận diện vào lần nữa
            $stillInside = null;
            if ($cleanPlate !== '') {
                $stillInside = VehicleLog::where('status', 'in')
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

                return response()->json(array_merge([
                    'success' => false,
                    'already_inside' => true,
                ], $alert));
            }

            // Generate a 6-character code (2 letters, 4 numbers)
            do {
                $code = chr(rand(65, 90)) . chr(rand(65, 90)) . rand(1000, 9999);
            } while (VehicleLog::where('code', $code)->exists());

            // Save log
            $log = VehicleLog::create([
                'plate_number' => $plate,
                'code' => $code,
                'status' => 'in',
                'entry_time' => now(),
                'entry_image' => $relativePath,
                'guard_in_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'log_id' => $log->id,
                'plate_number' => $plate,
                'code' => $code,
                'image_url' => asset($relativePath),
                'message' => 'Nhận diện thành công. Xe đã vào.'
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

            $log = VehicleLog::where('code', $code)
                ->where(function ($q) {
                    $q->where('status', 'in')->orWhere('is_valid', false);
                })
                ->latest()
                ->first();

            if (!$log) {
                // Sai mã → tắt kích hoạt để PC/ĐT nhập lại từ đầu
                $this->clearArmedExitCodeStorage();
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy xe chưa ra với mã code: ' . $code . '. Hãy nhập lại mã.',
                    'retryable' => true,
                ]);
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
                    'message' => 'Lỗi nhận diện ảnh ra: ' . $aiResult['error'] . '. Có thể quét lại.',
                    'retryable' => true,
                    'keep_armed' => true,
                ]);
            }

            $exitPlate = strtoupper($aiResult['plate']);

            // Compare Plates
            $cleanEntry = preg_replace('/[^A-Z0-9]/i', '', $log->plate_number);
            $cleanExit = preg_replace('/[^A-Z0-9]/i', '', $exitPlate);

            $isMatch = ($cleanEntry === $cleanExit);

            // Chỉ lưu ảnh/biển số xe ra để đối chiếu — chưa checkout.
            // Bảo vệ phải bấm Hợp lệ / Không hợp lệ mới quyết định cho ra.
            $log->update([
                'status' => 'in',
                'exit_time' => null,
                'exit_image' => $relativePath,
                'exit_plate_number' => $exitPlate,
                'guard_out_id' => auth()->id(),
                'is_valid' => null,
            ]);

            // Đã dùng mã → tắt kích hoạt quét ra trên ĐT
            $this->clearArmedExitCodeStorage();

            return response()->json([
                'success' => true,
                'match' => $isMatch,
                'log_id' => $log->id,
                'code' => $log->code,
                'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
                'entry_plate' => $log->plate_number,
                'exit_image' => asset($relativePath),
                'exit_plate' => $exitPlate,
                'message' => $isMatch
                    ? ('Biển số khớp: ' . $exitPlate . '. Vui lòng xác nhận Hợp lệ / Không hợp lệ.')
                    : ('Biển số xe ra (' . $exitPlate . ') không khớp với xe vào (' . $log->plate_number . '). Vui lòng xác nhận.')
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

        $isValid = filter_var($request->is_valid, FILTER_VALIDATE_BOOLEAN);

        if ($isValid) {
            // Hợp lệ → chuyển sang danh sách "Xe vào đã ra"
            $log->update([
                'status' => 'out',
                'exit_time' => now(),
                'guard_out_id' => auth()->id(),
                'is_valid' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xác nhận Hợp lệ! Xe đã chuyển sang danh sách xe vào đã ra.'
            ]);
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
        $this->clearArmedExitCodeStorage();

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận Không hợp lệ! Có thể nhập lại mã để quét xe ra lại.'
        ]);
    }

    /**
     * Hủy lượt đối chiếu xe ra (sai mã / biển lỗi) → cho phép nhập mã và quét lại.
     */
    public function retryExitAttempt(Request $request)
    {
        $logId = $request->input('log_id');
        if ($logId) {
            $log = VehicleLog::find($logId);
            if ($log && $log->status === 'in' && $log->is_valid === null && $log->exit_image) {
                $log->update([
                    'exit_time' => null,
                    'exit_image' => null,
                    'exit_plate_number' => null,
                    'is_valid' => null,
                    'guard_out_id' => null,
                ]);
            }
        }

        $this->clearArmedExitCodeStorage();

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy lượt ra. Nhập lại mã code để quét biển lại.',
        ]);
    }

    public function getRecentLogs()
    {
        $pendingLogs = VehicleLog::with('guardIn')
            ->where(function ($query) {
                $query->where('status', 'in')->orWhere('is_valid', false);
            })
            ->orderBy('entry_time', 'desc')
            ->take(50)
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
                ];
            });

        $completedLogs = VehicleLog::with(['guardIn', 'guardOut'])
            ->where('status', 'out')
            ->where(function ($query) {
                $query->whereNull('is_valid')->orWhere('is_valid', true);
            })
            ->orderBy('exit_time', 'desc')
            ->take(50)
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
                ];
            });

        return response()->json([
            'success' => true,
            'pending' => $pendingLogs,
            'completed' => $completedLogs
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Máy tính poll trạng thái: xe vào mới + lượt đang chờ xác nhận Hợp lệ.
     */
    public function guardMonitorState()
    {
        $this->releaseSessionLock();

        $lastEntry = VehicleLog::query()
            ->where('entry_time', '>=', now()->subMinutes(5))
            ->orderByDesc('id')
            ->first();

        $pendingValidation = VehicleLog::query()
            ->whereNotNull('exit_image')
            ->whereNull('is_valid')
            ->where('status', 'in')
            ->orderByDesc('id')
            ->first();

        $entryPayload = null;
        if ($lastEntry) {
            $entryPayload = [
                'id' => $lastEntry->id,
                'plate_number' => $lastEntry->plate_number,
                'code' => $lastEntry->code,
                'entry_image' => $lastEntry->entry_image ? asset($lastEntry->entry_image) : null,
                'entry_time' => optional($lastEntry->entry_time)->toDateTimeString(),
            ];
        }

        $pendingPayload = null;
        if ($pendingValidation) {
            $cleanEntry = preg_replace('/[^A-Z0-9]/i', '', (string) $pendingValidation->plate_number);
            $cleanExit = preg_replace('/[^A-Z0-9]/i', '', (string) $pendingValidation->exit_plate_number);
            $pendingPayload = [
                'log_id' => $pendingValidation->id,
                'code' => $pendingValidation->code,
                'entry_plate' => $pendingValidation->plate_number,
                'exit_plate' => $pendingValidation->exit_plate_number,
                'entry_image' => $pendingValidation->entry_image ? asset($pendingValidation->entry_image) : null,
                'exit_image' => $pendingValidation->exit_image ? asset($pendingValidation->exit_image) : null,
                'match' => ($cleanEntry !== '' && $cleanEntry === $cleanExit),
                'message' => ($cleanEntry !== '' && $cleanEntry === $cleanExit)
                    ? ('Biển số khớp: ' . $pendingValidation->exit_plate_number . '. Vui lòng xác nhận.')
                    : ('Biển số xe ra (' . $pendingValidation->exit_plate_number . ') không khớp với xe vào (' . $pendingValidation->plate_number . ').'),
            ];
        }

        return response()->json([
            'success' => true,
            'last_entry' => $entryPayload,
            'pending_validation' => $pendingPayload,
            'armed_exit_code' => $this->readArmedExitCode(),
            'entry_alert' => $this->readEntryAlert(),
            'live_preview' => [
                'entry' => $this->readLivePreview('entry'),
                'exit' => $this->readLivePreview('exit'),
            ],
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * ĐT đẩy frame LIVE → máy tính giám sát hiện lại.
     */
    public function uploadLivePreview(Request $request)
    {
        $side = $request->input('side', 'entry') === 'exit' ? 'exit' : 'entry';
        if (!$request->hasFile('image')) {
            return response()->json(['success' => false, 'message' => 'Thiếu ảnh'], 422);
        }

        $this->releaseSessionLock();

        $dir = public_path('uploads/live');
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        $filename = $side . '.jpg';
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
        $request->file('image')->move($dir, $filename);
        @touch($fullPath);

        $ts = time();
        return response()->json([
            'success' => true,
            'side' => $side,
            'url' => asset('public/uploads/live/' . $filename) . '?t=' . $ts,
            'ts' => $ts,
        ]);
    }

    private function readLivePreview($side)
    {
        $side = $side === 'exit' ? 'exit' : 'entry';
        $fullPath = public_path('uploads/live') . DIRECTORY_SEPARATOR . $side . '.jpg';
        if (!is_file($fullPath)) {
            return ['active' => false, 'url' => null, 'ts' => null];
        }
        $ts = (int) @filemtime($fullPath);
        $age = time() - $ts;
        // Frame cũ hơn 4s → ĐT đã tắt / mất mạng
        $active = $age <= 4;
        return [
            'active' => $active,
            'url' => $active ? (asset('public/uploads/live/' . $side . '.jpg') . '?t=' . $ts) : null,
            'ts' => $ts,
        ];
    }

    public function armedExitCodeStatus()
    {
        $this->releaseSessionLock();

        return response()->json([
            'success' => true,
            'armed_exit_code' => $this->readArmedExitCode(),
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function entryAlertPath()
    {
        $dir = storage_path('app');
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'entry_alert.json';
    }

    private function writeEntryAlert(array $payload)
    {
        $payload['id'] = $payload['id'] ?? ('ea_' . time() . '_' . uniqid());
        $payload['ts'] = time();
        file_put_contents($this->entryAlertPath(), json_encode($payload, JSON_UNESCAPED_UNICODE));
        try {
            \Illuminate\Support\Facades\Cache::put('entry_alert', $payload, 90);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function readEntryAlert()
    {
        $data = null;
        try {
            $cached = \Illuminate\Support\Facades\Cache::get('entry_alert');
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
        // Chỉ hiện trên PC trong ~60s sau khi phát sinh
        if ($age > 60) {
            $path = $this->entryAlertPath();
            if (is_file($path)) {
                @unlink($path);
            }
            try {
                \Illuminate\Support\Facades\Cache::forget('entry_alert');
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
        $dir = storage_path('app');
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'armed_exit_code.json';
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
            \Illuminate\Support\Facades\Cache::put('armed_exit_code', $payload, 300);
        } catch (\Throwable $e) {
            // ignore cache errors — file vẫn là nguồn chính
        }
    }

    private function clearArmedExitCodeStorage()
    {
        $path = $this->armedExitCodePath();
        if (is_file($path)) {
            @unlink($path);
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('armed_exit_code');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function readArmedExitCode()
    {
        // Ưu tiên cache (nhanh, ít race), fallback file
        try {
            $cached = \Illuminate\Support\Facades\Cache::get('armed_exit_code');
            if (is_array($cached) && !empty($cached['code'])) {
                $age = time() - (int) ($cached['ts'] ?? 0);
                if ($age <= 300) {
                    return strtoupper((string) $cached['code']);
                }
                \Illuminate\Support\Facades\Cache::forget('armed_exit_code');
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
            $this->clearArmedExitCodeStorage();
            return null;
        }
        // Đồng bộ lại cache nếu file còn hạn
        try {
            \Illuminate\Support\Facades\Cache::put('armed_exit_code', $data, 300 - $age);
        } catch (\Throwable $e) {
            // ignore
        }
        return strtoupper((string) $data['code']);
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

        $log = VehicleLog::where('code', $code)
            ->where(function ($q) {
                $q->where('status', 'in')->orWhere('is_valid', false);
            })
            ->latest()
            ->first();

        if (!$log) {
            $this->clearArmedExitCodeStorage();
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy xe chưa ra với mã: ' . $code . '. Hãy nhập lại.',
                'retryable' => true,
            ], 404);
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
}
