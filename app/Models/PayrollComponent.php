<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model
{
    protected $table = 'payroll_components';

    protected $fillable = [
        'id',
        'header',
        'nama',
        'type',
        'input_mode',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
