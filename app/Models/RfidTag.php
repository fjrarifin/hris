<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfidTag extends Model
{
    use HasFactory;

    protected $fillable = ['tag', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
