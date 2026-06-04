<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jobdesk extends Model
{
    protected $fillable = ['master_jabatan_id', 'kategori', 'deskripsi', 'tipe_tugas', 'is_active', 'document', 'created_by', 'updated_by'];

    protected $hidden = ['document'];

    protected $appends = ['has_document'];

    protected $casts = ['is_active' => 'boolean'];

    public function jabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'master_jabatan_id');
    }

    public function getHasDocumentAttribute(): bool
    {
        return filled($this->document);
    }
}
