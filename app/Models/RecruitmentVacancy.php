<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class RecruitmentVacancy extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'division',
        'department',
        'unit',
        'position',
        'supervisor_nik',
        'supervisor_name',
        'description',
        'employment_type',
        'workplace_type',
        'location',
        'responsibilities',
        'requirements',
        'benefits',
        'published_at',
        'expires_at',
        'application_deadline',
        'status',
        'hire_type',
        'replaced_employee_nik',
        'replaced_employee_name',
    ];


    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'application_deadline' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (RecruitmentVacancy $vacancy): void {
            if (Schema::hasColumn($vacancy->getTable(), 'slug') && ! $vacancy->slug) {
                $vacancy->forceFill(['slug' => Str::slug($vacancy->title).'-'.$vacancy->id])->saveQuietly();
            }
        });
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'open')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNull('application_deadline')->orWhereDate('application_deadline', '>=', today()));
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(RecruitmentCandidate::class, 'vacancy_id');
    }
}
