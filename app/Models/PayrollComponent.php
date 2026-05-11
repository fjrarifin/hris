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
        'is_active'
    ];
}
