<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaAgentUnresolvedQuery extends Model
{
    use HasFactory;

    protected $table = 't_wa_agent_unresolved_queries';

    protected $fillable = [
        'sender_phone',
        'karyawan_nik',
        'sender_name',
        'question',
        'bot_response',
        'category',
        'status',
        'admin_notes',
        'ask_count',
        'last_asked_at',
    ];

    protected $casts = [
        'ask_count' => 'integer',
        'last_asked_at' => 'datetime',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_nik', 'nik');
    }
}
