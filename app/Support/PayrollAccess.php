<?php

namespace App\Support;

use App\Models\User;

class PayrollAccess
{
    public static function can(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array((int) $user->level, config('payroll.admin_levels', [0, 1]), true)) {
            return true;
        }

        if (self::usernameAllowed($user)) {
            return true;
        }

        if (!in_array((int) $user->level, config('payroll.hr_manager_levels', [2]), true)) {
            return false;
        }

        return self::looksLikeHrManager($user);
    }

    private static function usernameAllowed(User $user): bool
    {
        $allowed = collect(config('payroll.allowed_usernames', []))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values();

        return $allowed->contains(strtolower((string) $user->username));
    }

    private static function looksLikeHrManager(User $user): bool
    {
        $karyawan = $user->karyawan;

        if (!$karyawan) {
            return false;
        }

        $departmentText = strtolower(implode(' ', array_filter([
            $karyawan->departement,
            $karyawan->divisi,
            $karyawan->unit,
        ])));

        $positionText = strtolower(implode(' ', array_filter([
            $karyawan->jabatan,
            $karyawan->posisi,
        ])));

        $isHr = str_contains($departmentText, 'hr')
            || str_contains($departmentText, 'human resource')
            || str_contains($departmentText, 'hrbp');

        $isManager = str_contains($positionText, 'manager')
            && !str_contains($positionText, 'asst')
            && !str_contains($positionText, 'assistant')
            && !str_contains($positionText, 'supervisor')
            && !str_contains($positionText, 'spv')
            && !str_contains($positionText, 'staff');

        return $isHr && $isManager;
    }
}
