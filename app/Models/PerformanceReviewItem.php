<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReviewItem extends Model
{
    protected $fillable = ['performance_review_id', 'kpi_template_id', 'nama_kpi_snapshot', 'target_snapshot', 'satuan_snapshot', 'bobot_snapshot', 'realisasi', 'score', 'weighted_score', 'notes'];

    protected $casts = ['bobot_snapshot' => 'decimal:2', 'realisasi' => 'decimal:2', 'score' => 'decimal:2', 'weighted_score' => 'decimal:2'];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }
}
