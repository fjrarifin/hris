<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtkRequest extends Model
{
    protected $fillable = [
        'user_id',
        'item_name',
        'quantity',
        'note',
        'status',
    ];
}
