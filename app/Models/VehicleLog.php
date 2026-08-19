<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate_number',
        'code',
        'ticket_type',
        'monthly_ticket_id',
        'status',
        'entry_time',
        'exit_time',
        'entry_image',
        'exit_image',
        'exit_plate_number',
        'guard_in_id',
        'guard_out_id',
        'is_valid',
        'fee',
        'hourly_rate',
        'monthly_match',
        'monthly_confirmed',
    ];

    protected $casts = [
        'monthly_match' => 'boolean',
        'monthly_confirmed' => 'boolean',
        'fee' => 'integer',
        'hourly_rate' => 'integer',
    ];

    public function guardIn()
    {
        return $this->belongsTo(User::class, 'guard_in_id');
    }

    public function guardOut()
    {
        return $this->belongsTo(User::class, 'guard_out_id');
    }

    public function monthlyTicket()
    {
        return $this->belongsTo(MonthlyTicket::class, 'monthly_ticket_id');
    }

    public function isMonthly(): bool
    {
        return ($this->ticket_type ?? 'daily') === 'monthly';
    }

    public function isPendingMonthlyEntry(): bool
    {
        return $this->isMonthly()
            && $this->monthly_ticket_id
            && $this->monthly_confirmed === null;
    }
}
