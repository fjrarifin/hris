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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
