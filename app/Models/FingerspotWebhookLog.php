<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerspotWebhookLog extends Model
{
    protected $fillable = [
        'type',
        'cloud_id',
        'pin',
        'scan',
        'verify',
        'status_scan',
        'raw_payload',
        'ip_address',
        'user_agent',
        'received_at',
    ];

    protected $casts = [
        'scan' => 'datetime',
        'raw_payload' => 'array',
        'received_at' => 'datetime',
    ];
}
