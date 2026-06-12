<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItPushNotification extends Model
{
    protected $fillable = [
        'title',
        'message',
        'audience',
        'target_user_ids',
        'mobile_path',
        'sent_by',
        'sent_by_name',
        'recipient_count',
        'token_count',
        'database_sent_count',
        'push_sent_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_user_ids' => 'array',
            'metadata' => 'array',
            'recipient_count' => 'integer',
            'token_count' => 'integer',
            'database_sent_count' => 'integer',
            'push_sent_count' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
