<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        if (!$row || $row->value === null || $row->value === '') {
            return $default;
        }
        return $row->value;
    }

    public static function setValue(string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    public static function dailyPricePerHour(): int
    {
        return max(1, (int) static::getValue('daily_price_per_hour', 1000));
    }

    public static function monthlyPricePerMonth(): int
    {
        return max(1, (int) static::getValue('monthly_price_per_month', 100000));
    }
}
