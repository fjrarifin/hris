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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
