<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePermission extends Model
{
    protected $table = 'employee_permissions';

    protected $fillable = [
        'user_id',
        'type',        // izin | sakit
        'date',
        'reason',
        'document',
        'status'
    ];
}
