<?php

if (! function_exists('normalize_plate')) {
    function normalize_plate(?string $plate): string
    {
        $raw = trim((string) $plate);
        if ($raw === '') {
            return '';
        }
        if (mb_strtolower($raw) === 'không thể nhận diện') {
            return $raw;
        }

        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $raw));
    }
}

if (! function_exists('remove_protocol')) {
    function remove_protocol($url) {
        return str_replace(['http://', 'https://'], '', $url);
    }
}

if (! function_exists('getVideoDuration')) {
    function getVideoDuration($filePath)
    {
        $getID3 = new getID3();
        $fileInfo = $getID3->analyze($filePath);

        return isset($fileInfo['playtime_seconds']) ? (int)$fileInfo['playtime_seconds'] : 0;
    }
}

if (! function_exists('formatDuration')) {
    function formatDuration($seconds)
    {
        $years = floor($seconds / (365 * 24 * 3600));
        $remainingSeconds = $seconds % (365 * 24 * 3600);

        $months = floor($remainingSeconds / (30 * 24 * 3600));
        $remainingSeconds %= (30 * 24 * 3600);

        $days = floor($remainingSeconds / (24 * 3600));
        $remainingSeconds %= (24 * 3600);

        $hours = floor($remainingSeconds / 3600);
        $remainingSeconds %= 3600;

        $minutes = floor($remainingSeconds / 60);

        $timeParts = [];
        if ($years > 0) $timeParts[] = "{$years} năm";
        if ($months > 0) $timeParts[] = "{$months} tháng";
        if ($days > 0) $timeParts[] = "{$days} ngày";
        if ($hours > 0) $timeParts[] = "{$hours} giờ";
        if ($minutes > 0) $timeParts[] = "{$minutes} phút";

        return !empty($timeParts) ? implode(' ', array_slice($timeParts, 0, 1)) : "Dưới 1 phút";
    }
}

if (! function_exists('convertNumberToWords')) {
    function convertNumberToWords($number, $currency = 'VND') {
        $hyphen      = ' ';
        $conjunction = ' và ';
        $separator   = ', ';
        $negative    = 'âm ';

        $dictionary  = [
            0  => 'không',
            1  => 'một',
            2  => 'hai',
            3  => 'ba',
            4  => 'bốn',
            5  => 'năm',
            6  => 'sáu',
            7  => 'bảy',
            8  => 'tám',
            9  => 'chín',
            10 => 'mười',
            11 => 'mười một',
            12 => 'mười hai',
            13 => 'mười ba',
            14 => 'mười bốn',
            15 => 'mười lăm',
            16 => 'mười sáu',
            17 => 'mười bảy',
            18 => 'mười tám',
            19 => 'mười chín',
            20 => 'hai mươi',
            30 => 'ba mươi',
            40 => 'bốn mươi',
            50 => 'năm mươi',
            60 => 'sáu mươi',
            70 => 'bảy mươi',
            80 => 'tám mươi',
            90 => 'chín mươi'
        ];

        if (!is_numeric($number)) {
            return false;
        }

        if ($number < 0) {
            return $negative . convertNumberToWords(abs($number), $currency);
        }

        $string = '';

        // Xử lý phần nguyên và phần thập phân
        $integerPart = floor($number); // Lấy phần nguyên
        $decimalPart = round(($number - $integerPart) * 100); // Lấy phần thập phân (cents)

        if ($integerPart > 0) {
            $string .= convertIntegerToWords($integerPart, $dictionary);
            $string .= ($currency === 'USD') ? ' đô la Mỹ' : ' đồng';
        }

        if ($decimalPart > 0) {
            $string .= $conjunction . convertIntegerToWords($decimalPart, $dictionary) . ' xu';
        }

        return ucfirst($string);
    }

    // Hàm riêng để xử lý phần nguyên
    function convertIntegerToWords($number, $dictionary) {
        if ($number < 21) {
            return $dictionary[$number];
        } elseif ($number < 100) {
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            return $dictionary[$tens] . ($units ? ' ' . $dictionary[$units] : '');
        } else {
            $string = '';
            foreach ([1000000000 => 'tỷ', 1000000 => 'triệu', 1000 => 'nghìn', 100 => 'trăm'] as $value => $word) {
                if ($number >= $value) {
                    $numUnits = (int) ($number / $value);
                    $remainder = $number % $value;
                    $string .= convertIntegerToWords($numUnits, $dictionary) . ' ' . $word;
                    if ($remainder) {
                        $string .= ' ' . convertIntegerToWords($remainder, $dictionary);
                    }
                    break;
                }
            }
            return $string;
        }
    }
}

if (! function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;

        $units = [
            31536000 => 'năm',
            2592000  => 'tháng',
            604800   => 'tuần',
            86400    => 'ngày',
            3600     => 'giờ',
            60       => 'phút',
            1        => 'giây'
        ];

        foreach ($units as $unit => $text) {
            if ($diff >= $unit) {
                $value = floor($diff / $unit);
                return $value . " " . $text . " trước";
            }
        }

        return "Vừa xong";
    }
}

if (! function_exists('format_vnd')) {
    function format_vnd($amount)
    {
        return number_format((int) $amount, 0, ',', '.') . ' VNĐ';
    }
}

if(! function_exists('getLastTwoPartsOfName')) {
    function getLastTwoPartsOfName($fullName) {
        $parts = explode(' ', trim($fullName));

        if (count($parts) < 3) {
            return implode(' ', $parts);
        }

        return implode(' ', array_slice($parts, -2));
    }
}
