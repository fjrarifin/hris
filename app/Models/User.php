<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'email',
        'photo',
        'photo_changed_at',
        'last_seen_at',
        'online_latitude',
        'online_longitude',
        'online_city',
        'online_location_updated_at',
        'level',
        'must_change_password',
        'password_changed_at',
        'email_updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_updated_at' => 'datetime',
            'photo_changed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'online_latitude' => 'decimal:7',
            'online_longitude' => 'decimal:7',
            'online_location_updated_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission($key)
    {
        return $this->role
            ->permissions()
            ->where('key', $key)
            ->exists();
    }

    public function karyawan()
    {
        return $this->hasOne(Karyawan::class, 'nik', 'username');
    }

    public function accruals()
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    /**
     * RFID tags that have been assigned to this user (nullable, a tag may be scanned before assignment).
     */
    public function rfidTags()
    {
        return $this->hasMany(RfidTag::class);
    }
}
