<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappBotFaq extends Model
{
    use HasFactory;

    protected $table = 't_whatsapp_bot_faq';

    protected $fillable = [
        'topic',
        'keywords',
        'answer',
        'priority',
        'max_user_level',
        'match_type',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'max_user_level' => 'integer',
    ];
}
