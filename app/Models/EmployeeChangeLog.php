<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeChangeLog extends Model
{
    protected $fillable = [
        'employee_nik',
        'changed_by_user_id',
        'changed_by_name',
        'source',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
