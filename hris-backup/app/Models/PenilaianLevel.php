<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenilaianLevel extends Model
{
    protected $table = 'm_penilaian_level';

    public function getTemplateLevelIdAttribute()
    {
        return $this->template_parent_id ?: $this->id;
    }
}
