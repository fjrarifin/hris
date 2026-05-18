<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEmailLog extends Model
{
    protected $fillable = [
        'payroll_id',
        'karyawan_nik',
        'recipient_email',
        'subject',
        'action',
        'status',
        'attempt_no',
        'created_by',
        'sent_at',
        'notes',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
