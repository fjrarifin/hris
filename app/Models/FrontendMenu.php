<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontendMenu extends Model
{
    protected $fillable = [
        'key',
        'label',
        'path',
        'icon',
        'allowed_levels',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function userAccess()
    {
        return $this->hasMany(FrontendMenuUserAccess::class);
    }
}
