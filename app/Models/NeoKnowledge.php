<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NeoKnowledge extends Model
{
    protected $table = 't_neo_knowledge';

    protected $fillable = [
        'section_key',
        'title',
        'content',
        'sort_order',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(NeoKnowledgeRevision::class, 'neo_knowledge_id')->latest('id');
    }
}

