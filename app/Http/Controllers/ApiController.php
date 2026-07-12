<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleLog;
use Illuminate\Support\Str;

class ApiController extends Controller
{
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

    private function runRecognition($imageFullPath)
    {
        if (!function_exists('exec')) {
            return ['success' => false, 'error' => 'Hàm exec() trong PHP đã bị khóa bởi Hostinger (disable_functions). Bạn hãy vào hPanel -> PHP Configuration -> Disable Functions để xóa chữ exec rồi lưu lại.'];
        }

        $pythonScript = base_path('recognize.py');
        $pythonBin = $this->getPythonExecutable();
        $command = escapeshellcmd($pythonBin) . " " . escapeshellarg($pythonScript) . " " . escapeshellarg($imageFullPath) . " 2>&1";
        
        exec($command, $output, $returnCode);
        
        if (!empty($output)) {
            $output = array_map(function($line) {
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
            } else {
                return ['success' => false, 'error' => $data['error'] ?? 'Không nhận diện được ký tự biển số.'];
            }
        }

        $errorMsg = 'Lỗi chạy script AI (Mã lỗi ' . $returnCode . '): ' . implode("\n", $output ?? []);
        return ['success' => false, 'error' => iconv('UTF-8', 'UTF-8//IGNORE', $errorMsg)];
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

            // Run AI Recognition
            $aiResult = $this->runRecognition($fullPath);
            if (!$aiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $aiResult['error']
                ]);
            }

            $plate = strtoupper($aiResult['plate']);

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
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy xe chưa ra với mã code: ' . $code
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

            // Run AI Recognition on Exit Image
            $aiResult = $this->runRecognition($fullPath);
            if (!$aiResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi nhận diện ảnh ra: ' . $aiResult['error']
                ]);
            }

            $exitPlate = strtoupper($aiResult['plate']);

            // Compare Plates
            $cleanEntry = preg_replace('/[^A-Z0-9]/i', '', $log->plate_number);
            $cleanExit = preg_replace('/[^A-Z0-9]/i', '', $exitPlate);

            $isMatch = ($cleanEntry === $cleanExit);

            if ($isMatch) {
                $log->update([
                    'status' => 'out',
                    'exit_time' => now(),
                    'exit_image' => $relativePath,
                    'exit_plate_number' => $exitPlate,
                    'guard_out_id' => auth()->id(),
                    'is_valid' => true
                ]);

                return response()->json([
                    'success' => true,
                    'match' => true,
                    'log_id' => $log->id,
                    'code' => $log->code,
                    'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
                    'entry_plate' => $log->plate_number,
                    'exit_image' => asset($relativePath),
                    'exit_plate' => $exitPlate,
                    'message' => 'Checkout thành công! Biển số khớp: ' . $exitPlate
                ]);
            } else {
                // Not match -> keep status as 'in', save exit image & plate for comparison
                $log->update([
                    'exit_image' => $relativePath,
                    'exit_plate_number' => $exitPlate,
                    'guard_out_id' => auth()->id()
                ]);

                return response()->json([
                    'success' => true,
                    'match' => false,
                    'log_id' => $log->id,
                    'code' => $log->code,
                    'entry_image' => $log->entry_image ? asset($log->entry_image) : null,
                    'entry_plate' => $log->plate_number,
                    'exit_image' => asset($relativePath),
                    'exit_plate' => $exitPlate,
                    'message' => 'Checkout không thành công! Biển số xe ra (' . $exitPlate . ') không khớp với xe vào (' . $log->plate_number . ')'
                ]);
            }
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
            $log->update([
                'status' => 'out',
                'exit_time' => now(),
                'guard_out_id' => auth()->id(),
                'is_valid' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xác nhận Hợp lệ! Xe đã được checkout.'
            ]);
        } else {
            $log->update([
                'is_valid' => false,
                'guard_out_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xác nhận Không hợp lệ! Phương tiện chưa được checkout.'
            ]);
        }
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
}
