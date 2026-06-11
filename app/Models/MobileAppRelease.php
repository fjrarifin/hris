<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAppRelease extends Model
{
    protected $fillable = [
        'version_code',
        'version_name',
        'file_path',
        'file_name',
        'file_size',
        'sha256',
        'mandatory',
        'is_active',
        'notes',
        'uploaded_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'file_size' => 'integer',
            'mandatory' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
