<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveAccrual extends Model
{
    protected $fillable = [
        'user_id',
        'nik',
        'year',
        'month',
        'accrued_at',
        'days',
        'expired_at',
        'is_used',
    ];

    protected $casts = [
        'accrued_at' => 'date',
        'expired_at' => 'date',
        'days' => 'integer',
        'is_used' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
