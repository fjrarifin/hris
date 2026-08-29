<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
        'phone',
        'photo',
        'photo_changed_at',
        'last_seen_at',
        'online_latitude',
        'online_longitude',
        'online_city',
        'online_location_updated_at',
        'level',
        'is_active',
        'allow_mobile_attendance',
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
            'is_active' => 'boolean',
            'allow_mobile_attendance' => 'boolean',
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

    public function mobileDeviceTokens()
    {
        return $this->hasMany(MobileDeviceToken::class);
    }

    /**
     * Check if user has at least one active contract.
     */
    public function hasActiveContract(?Carbon $date = null): bool
    {
        $today = $date ?? now()->startOfDay();

        return DB::table('t_kontrak_karyawan')
            ->where('nik', $this->username)
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $today->copy()->addMonthNoOverflow())
            ->whereDate('end_date', '>=', $today)
            ->exists();
    }

    /**
     * Synchronize `is_active` attribute based on active contracts.
     */
    public function syncIsActiveFromContracts(?Carbon $date = null): bool
    {
        $hasActive = $this->hasActiveContract($date);

        if ((bool) $this->is_active !== $hasActive) {
            $this->forceFill(['is_active' => $hasActive])->save();
        }

        return $hasActive;
    }

    /**
     * Static helper to sync `is_active` for a given NIK/username.
     */
    public static function syncIsActiveForNik(string $nik, ?Carbon $date = null): void
    {
        $today = $date ?? now()->startOfDay();
        $hasActive = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $today->copy()->addMonthNoOverflow())
            ->whereDate('end_date', '>=', $today)
            ->exists();

        static::query()->where('username', $nik)->update([
            'is_active' => $hasActive,
        ]);
    }
}
