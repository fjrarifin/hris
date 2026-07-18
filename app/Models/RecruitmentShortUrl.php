<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentShortUrl extends Model
{
    protected $table = 'recruitment_short_urls';

    protected $fillable = [
        'code',
        'destination_url',
        'clicks_count',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'clicks_count' => 'integer',
    ];
}
