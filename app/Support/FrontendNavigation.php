<?php

namespace App\Support;

use App\Models\FrontendMenu;
use App\Models\User;
use Illuminate\Support\Collection;

class FrontendNavigation
{
    public function menusFor(User $user): Collection
    {
        $menus = FrontendMenu::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('key', 'dashboard'))
            ->with(['userAccess' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (FrontendMenu $menu) => $this->isAllowed($user, $menu))
            ->map(fn (FrontendMenu $menu) => $this->serializeMenu($menu, $user))
            ->values();

        if ((int) $user->level !== 2) {
            return $menus;
        }

        $employeeKeys = ['employees', 'hr-contracts'];
        $employeeAnchor = $menus
            ->first(fn (array $menu) => in_array($menu['key'], $employeeKeys, true))['key'] ?? null;
        $employeeChildren = $menus
            ->whereIn('key', $employeeKeys)
            ->sortBy(fn (array $menu) => array_search($menu['key'], $employeeKeys, true))
            ->map(function (array $menu): array {
                if ($menu['key'] === 'employees') {
                    $menu['label'] = 'Data Karyawan';
                }

                return $menu;
            })
            ->values()
            ->all();

        $attendanceKeys = ['attendance', 'hr-attendance-corrections', 'hr-schedules'];
        $attendanceAnchor = $menus
            ->first(fn (array $menu) => in_array($menu['key'], $attendanceKeys, true))['key'] ?? null;
        $attendanceChildren = $menus
            ->whereIn('key', $attendanceKeys)
            ->sortBy(fn (array $menu) => array_search($menu['key'], $attendanceKeys, true))
            ->map(function (array $menu): array {
                if ($menu['key'] === 'attendance') {
                    $menu['label'] = 'Rekap Absensi';
                }

                return $menu;
            })
            ->values()
            ->all();

        $approvalKeys = ['hr-approval-leave', 'hr-approval-overtime', 'hr-approval-ph', 'hr-approval-permission'];
        $children = $menus->whereIn('key', $approvalKeys)->values()->all();
        $approvalAnchor = $children[0]['key'] ?? null;

        return $menus->flatMap(function (array $menu) use (
            $employeeKeys,
            $employeeChildren,
            $employeeAnchor,
            $attendanceKeys,
            $attendanceChildren,
            $attendanceAnchor,
            $approvalKeys,
            $children,
            $approvalAnchor
        ): array {
            if ($menu['key'] === $employeeAnchor && $employeeChildren) {
                return [[
                    'key' => 'hr-employees',
                    'label' => 'Karyawan',
                    'icon' => 'i-lucide-users-round',
                    'children' => $employeeChildren,
                ]];
            }

            if (in_array($menu['key'], $employeeKeys, true)) {
                return [];
            }

            if ($menu['key'] === $attendanceAnchor && $attendanceChildren) {
                return [[
                    'key' => 'hr-attendance',
                    'label' => 'Absensi',
                    'icon' => 'i-lucide-calendar-clock',
                    'children' => $attendanceChildren,
                ]];
            }

            if (in_array($menu['key'], $attendanceKeys, true)) {
                return [];
            }

            if ($menu['key'] === $approvalAnchor && $children) {
                return [[
                    'key' => 'hr-approvals',
                    'label' => 'Pengajuan',
                    'icon' => 'i-lucide-clipboard-check',
                    'children' => $children,
                ]];
            }

            return in_array($menu['key'], $approvalKeys, true) ? [] : [$menu];
        })->values();
    }

    public function canAccess(User $user, string $key): bool
    {
        $menu = FrontendMenu::query()
            ->where('key', $key)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('key', 'dashboard'))
            ->with(['userAccess' => fn ($query) => $query->where('user_id', $user->id)])
            ->first();

        return $menu ? $this->isAllowed($user, $menu) : false;
    }

    public function dashboardPath(User $user): string
    {
        return match ((int) $user->level) {
            0 => '/it/dashboard',
            1 => '/admin/dashboard',
            2 => '/hr/dashboard',
            default => '/staff/dashboard',
        };
    }

    public function levelLabel(User $user): string
    {
        return match ((int) $user->level) {
            0 => 'IT Administrator',
            1 => 'Administrator',
            2 => 'HRD',
            default => 'Karyawan',
        };
    }

    private function isAllowed(User $user, FrontendMenu $menu): bool
    {
        if ($menu->key === 'dashboard') {
            return true;
        }

        if ((int) $user->level === 0 && $menu->key === 'menu-access') {
            return true;
        }

        if (in_array($menu->key, ['staff-approvals', 'staff-overtime'], true) && ! $this->hasDirectSubordinates($user)) {
            return false;
        }

        if ($menu->key === 'staff-team-schedules' && ! $this->hasScheduleSubordinates($user)) {
            return false;
        }

        $override = $menu->userAccess->first();

        if ($override) {
            return $override->is_allowed;
        }

        $levels = array_filter(
            array_map('trim', explode(',', (string) $menu->allowed_levels)),
            fn (string $level) => $level !== ''
        );

        return in_array((string) $user->level, $levels, true);
    }

    private function hasDirectSubordinates(User $user): bool
    {
        $employee = $user->karyawan;

        return $employee
            ? \App\Models\Karyawan::query()->where('nama_atasan_langsung', $employee->nama_karyawan)->exists()
            : false;
    }

    private function hasScheduleSubordinates(User $user): bool
    {
        $employee = $user->karyawan;

        return $employee
            ? \App\Models\Karyawan::query()
                ->where('nama_atasan_langsung', $employee->nama_karyawan)
                ->orWhere('atasan_tidak_langsung', $employee->nama_karyawan)
                ->exists()
            : false;
    }

    private function serializeMenu(FrontendMenu $menu, User $user): array
    {
        return [
            'id' => $menu->id,
            'key' => $menu->key,
            'label' => $menu->label,
            'to' => $menu->key === 'dashboard' ? $this->dashboardPath($user) : $menu->path,
            'icon' => $menu->icon,
        ];
    }
}
