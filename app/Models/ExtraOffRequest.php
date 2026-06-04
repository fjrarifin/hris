<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraOffRequest extends Model
{
    protected $fillable = [
        'user_id',
        'source_period_start',
        'source_period_end',
        'claim_date',
        'status',
        'manager_approved_at',
        'manager_approved_by',
        'hr_approved_at',
        'hr_approved_by',
        'reject_reason',
        'approval_token',
        'approval_token_expires_at',
    ];

    protected $casts = [
        'source_period_start' => 'date',
        'source_period_end' => 'date',
        'claim_date' => 'date',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'approval_token_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
