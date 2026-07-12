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
        'status',
        'entry_time',
        'exit_time',
        'entry_image',
        'exit_image',
        'exit_plate_number',
        'guard_in_id',
        'guard_out_id',
        'is_valid'
    ];

    public function guardIn()
    {
        return $this->belongsTo(User::class, 'guard_in_id');
    }

    public function guardOut()
    {
        return $this->belongsTo(User::class, 'guard_out_id');
    }
}
