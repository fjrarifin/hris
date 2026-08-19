<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AbsensiEvent extends Model
{
    use HasFactory;

    protected $table = 'absensi_event';

    protected $fillable = [
        'id_event_absen',
        'nik_karyawan',
        'foto_absen',
        'jam_absen',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'jam_absen' => 'datetime',
    ];

    protected $appends = [
        'foto_url',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventAbsen::class, 'id_event_absen');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'nik_karyawan', 'nik');
    }

    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->foto_absen) {
            return null;
        }

        if (str_starts_with($this->foto_absen, 'http://') || str_starts_with($this->foto_absen, 'https://')) {
            return $this->foto_absen;
        }

        $filename = basename($this->foto_absen);
        return url('/api/event-absen/photos/'.$filename);
    }
}
