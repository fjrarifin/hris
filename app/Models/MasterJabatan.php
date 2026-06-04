<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterJabatan extends Model
{
    protected $fillable = ['nama_jabatan', 'departemen', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function jobdesks()
    {
        return $this->hasMany(Jobdesk::class);
    }

    public function kpiTemplates()
    {
        return $this->hasMany(KpiTemplate::class);
    }
}
