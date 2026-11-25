<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAbsenParticipant extends Model
{
    use HasFactory;

    protected $table = 'event_absen_participants';

    protected $fillable = [
        'id_event_absen',
        'nik_karyawan',
        'initial_notified_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'initial_notified_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventAbsen::class, 'id_event_absen');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'nik_karyawan', 'nik');
    }
}
