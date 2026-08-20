<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class EventAbsen extends Model
{
    use HasFactory;

    protected $table = 'event_absen';

    protected $fillable = [
        'nama_event',
        'deskripsi',
        'slug',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    protected $appends = [
        'is_expired',
        'effective_status',
        'effective_status_label',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function absensiEvents(): HasMany
    {
        return $this->hasMany(AbsensiEvent::class, 'id_event_absen');
    }

    public function attendances(): HasMany
    {
        return $this->absensiEvents();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->tanggal_selesai ? now()->greaterThan($this->tanggal_selesai) : false;
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'nonaktif') {
            return 'nonaktif';
        }

        if ($this->getIsExpiredAttribute()) {
            return 'kadaluarsa';
        }

        if ($this->tanggal_mulai && now()->lessThan($this->tanggal_mulai)) {
            return 'mendatang';
        }

        return 'aktif';
    }

    public function getEffectiveStatusLabelAttribute(): string
    {
        return match ($this->effective_status) {
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
            'kadaluarsa' => 'Selesai / Kadaluarsa',
            'mendatang' => 'Belum Dimulai',
            default => 'Unknown',
        };
    }
}
