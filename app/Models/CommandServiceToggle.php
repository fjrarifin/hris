<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandServiceToggle extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public static function isEnabled(string $key): bool
    {
        return static::query()
            ->where('key', $key)
            ->value('is_enabled') ?? true;
    }
}
