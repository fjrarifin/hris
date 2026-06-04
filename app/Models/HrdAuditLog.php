<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrdAuditLog extends Model
{
    protected $fillable = [
        'module',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'actor_user_id',
        'actor_name',
        'actor_username',
        'changes',
        'before_snapshot',
        'after_snapshot',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
