<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplateAssignment extends Model
{
    protected $table = 'kpi_template_assignments';

    protected $fillable = [
        'kpi_template_id',
        'employee_nik',
        'performance_period_id',
        'assigned_by',
        'assigned_at',
        'status',
        'weight_percentage',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'weight_percentage' => 'decimal:2',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'employee_nik', 'nik');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PerformancePeriod::class, 'performance_period_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
