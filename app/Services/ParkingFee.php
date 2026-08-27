<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\VehicleLog;
use Carbon\Carbon;

class ParkingFee
{
    /**
     * Vé ngày: mỗi giây sang giờ mới đã tính 1 giờ.
     * 13:15:30 → 13:15:31 = 1 giờ; 13:15:30 → 14:15:31 = 2 giờ.
     */
    public static function billedHours($entryTime, $exitTime): int
    {
        $entry = $entryTime instanceof Carbon ? $entryTime : Carbon::parse($entryTime);
        $exit = $exitTime instanceof Carbon ? $exitTime : Carbon::parse($exitTime);
        $seconds = max(0, $exit->diffInSeconds($entry));
        return max(1, (int) ceil($seconds / 3600));
    }

    public static function calcDailyFee($entryTime, $exitTime, ?int $hourlyRate = null): array
    {
        $rate = $hourlyRate !== null ? (int) $hourlyRate : Setting::dailyPricePerHour();
        $hours = static::billedHours($entryTime, $exitTime);
        return [
            'hours' => $hours,
            'hourly_rate' => $rate,
            'fee' => $hours * $rate,
        ];
    }

    public static function applyOnCheckout(VehicleLog $log, $exitTime = null): array
    {
        $exitTime = $exitTime ?: now();
        if (($log->ticket_type ?? 'daily') === 'monthly') {
            $log->fee = 0;
            $log->hourly_rate = null;
            return [
                'hours' => 0,
                'hourly_rate' => 0,
                'fee' => 0,
                'ticket_type' => 'monthly',
            ];
        }

        $rate = $log->hourly_rate !== null
            ? (int) $log->hourly_rate
            : Setting::dailyPricePerHour();
        $calc = static::calcDailyFee($log->entry_time, $exitTime, $rate);
        $log->fee = $calc['fee'];
        $log->hourly_rate = $calc['hourly_rate'];
        $calc['ticket_type'] = 'daily';
        return $calc;
    }

    public static function formatVnd($amount): string
    {
        return number_format((int) $amount, 0, ',', '.') . ' VNĐ';
    }
}
