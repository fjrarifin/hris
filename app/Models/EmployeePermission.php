<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class EmployeePermission extends Model
{
    protected $table = 'employee_permissions';

    protected $fillable = [
        'user_id',
        'type',        // izin | sakit
        'date',
        'reason',
        'document',
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
}
