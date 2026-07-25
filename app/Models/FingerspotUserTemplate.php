<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FingerspotUserTemplate extends Model
{
    use HasFactory;

    protected $table = 'fingerspot_user_templates';

    protected $fillable = [
        'pin',
        'name',
        'cloud_id',
        'privilege',
        'password',
        'card',
        'template',
        'raw_data',
        'last_pulled_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_pulled_at' => 'datetime',
    ];
}
