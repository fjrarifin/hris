<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterBusinessUnit extends Model
{
    protected $table = 'master_business_units';

    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
