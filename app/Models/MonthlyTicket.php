<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MonthlyTicket extends Model
{
    protected $fillable = [
        'code',
        'plate_number',
        'duration_months',
        'price',
        'starts_on',
        'expires_on',
        'is_active',
        'deleted',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'expires_on' => 'date',
        'is_active' => 'boolean',
        'deleted' => 'boolean',
        'duration_months' => 'integer',
        'price' => 'integer',
    ];

    public function logs()
    {
        return $this->hasMany(VehicleLog::class, 'monthly_ticket_id');
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('deleted', 0);
    }

    public static function expiryDate($startOn, int $months): Carbon
    {
        $start = $startOn instanceof Carbon ? $startOn->copy()->startOfDay() : Carbon::parse($startOn)->startOfDay();
        $months = max(1, min(4, $months));
        return $start->addDays(30 * $months);
    }

    public static function nextCode(): string
    {
        $last = static::query()
            ->orderByRaw("LEFT(code, 1) DESC")
            ->orderByRaw("CAST(SUBSTRING(code, 2) AS UNSIGNED) DESC")
            ->first();

        if (!$last || !preg_match('/^[A-Z]\d{5}$/', (string) $last->code)) {
            return 'A00001';
        }

        $letter = substr($last->code, 0, 1);
        $num = (int) substr($last->code, 1) + 1;
        if ($num > 99999) {
            $letter = chr(ord($letter) + 1);
            $num = 1;
        }
        if ($letter > 'Z') {
            throw new \RuntimeException('Đã hết mã vé tháng (A00001–Z99999).');
        }

        return $letter . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }

    public function isExpired(?Carbon $at = null): bool
    {
        $at = ($at ?: now())->copy()->startOfDay();
        $expires = Carbon::parse($this->expires_on)->startOfDay();
        return $at->gt($expires);
    }

    public function isUsable(): bool
    {
        return !$this->deleted && $this->is_active && !$this->isExpired();
    }

    public function daysUsed(?Carbon $at = null): int
    {
        $at = ($at ?: now())->copy()->startOfDay();
        $start = Carbon::parse($this->starts_on)->startOfDay();
        if ($at->lt($start)) {
            return 0;
        }
        return (int) $start->diffInDays($at);
    }

    /**
     * Thời hạn còn chọn được khi sửa: không cho hạ xuống mốc đã quá hạn.
     * Ngày hết hạn 1 tháng vẫn còn hạn trong đúng ngày đó.
     */
    public function allowedDurations(?Carbon $at = null): array
    {
        $at = ($at ?: now())->copy()->startOfDay();
        $start = Carbon::parse($this->starts_on)->startOfDay();
        $allowed = [];
        foreach ([1, 2, 3, 4] as $months) {
            $expiry = static::expiryDate($start, $months);
            if (!$at->gt($expiry)) {
                $allowed[] = $months;
            }
        }
        return $allowed;
    }

    public static function findUsableByCode(string $code): ?self
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $ticket = static::notDeleted()->where('code', $code)->first();
        if (!$ticket || !$ticket->isUsable()) {
            return null;
        }
        return $ticket;
    }
}
