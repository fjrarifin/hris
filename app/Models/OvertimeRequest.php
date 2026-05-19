<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requested_by_user_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'status',
        'reject_reason',
        'manager_approved_at',
        'manager_approved_by',
        'hr_approved_at',
        'hr_approved_by',
        'approval_token',
        'approval_token_expires_at',
    ];

    protected $casts = [
        'date' => 'date',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'approval_token_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
