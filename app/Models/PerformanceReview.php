<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    protected $fillable = ['employee_nik', 'performance_period_id', 'jabatan_snapshot', 'departemen_snapshot', 'reviewer_id', 'total_score', 'status', 'notes'];

    protected $casts = ['total_score' => 'decimal:2'];

    public function period()
    {
        return $this->belongsTo(PerformancePeriod::class, 'performance_period_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function items()
    {
        return $this->hasMany(PerformanceReviewItem::class);
    }

    public function employee()
    {
        return $this->belongsTo(Karyawan::class, 'employee_nik', 'nik');
    }
}
