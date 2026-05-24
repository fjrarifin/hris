<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontendMenuUserAccess extends Model
{
    protected $table = 'frontend_menu_user_access';

    protected $fillable = [
        'frontend_menu_id',
        'user_id',
        'is_allowed',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    public function menu()
    {
        return $this->belongsTo(FrontendMenu::class, 'frontend_menu_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
