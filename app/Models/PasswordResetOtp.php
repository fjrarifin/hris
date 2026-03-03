<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'otp',
        'expired_at',
        'is_used',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * Cek apakah OTP masih valid
     */
    public function isValid()
    {
        return !$this->is_used && now()->lessThanOrEqualTo($this->expired_at);
    }

    /**
     * Mark OTP sebagai digunakan
     */
    public function markAsUsed()
    {
        $this->update(['is_used' => true]);
    }

    /**
     * Get OTP terakhir untuk email
     */
    public static function getLatestOtp($email)
    {
        return self::where('email', $email)
            ->latest()
            ->first();
    }
}