<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollNew extends Model
{
    protected $table = 'payroll_new';

    protected $fillable = [
        'nik',
        'periode_start',
        'periode_end',
        'raw_data'
    ];

    protected $casts = [
        'raw_data' => 'array'
    ];
}
