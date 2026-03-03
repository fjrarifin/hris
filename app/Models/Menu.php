<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'name',
        'route',
        'icon',
        'parent_id',
        'order',
        'allowed_levels',
        'is_active',
    ];

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('is_active', 1)
            ->orderBy('order');
    }
}