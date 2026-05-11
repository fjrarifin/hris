<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'type',
        'nama_item',
        'amount',
        'component_id'
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function component()
    {
        return $this->belongsTo(PayrollComponent::class, 'component_id');
    }
}
