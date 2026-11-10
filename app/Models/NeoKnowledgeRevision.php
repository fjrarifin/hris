<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeoKnowledgeRevision extends Model
{
    public $timestamps = false;

    protected $table = 't_neo_knowledge_revisions';

    protected $fillable = [
        'neo_knowledge_id',
        'title',
        'content',
        'changed_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

