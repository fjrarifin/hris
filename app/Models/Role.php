<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'level'];

    public function menus()
    {
        return $this->belongsToMany(Menu::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
