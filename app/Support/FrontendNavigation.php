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

        $approvalKeys = ['hr-approval-leave', 'hr-approval-overtime', 'hr-approval-ph', 'hr-approval-permission'];
        $children = $menus->whereIn('key', $approvalKeys)->values()->all();

        return $menus->flatMap(function (array $menu) use ($approvalKeys, $children): array {
            if ($menu['key'] === $approvalKeys[0] && $children) {
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

        if ($menu->key === 'staff-team-schedules' && ! $this->isSupervisor($user)) {
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

    private function isSupervisor(User $user): bool
    {
        $employee = $user->karyawan;
        if (! $employee) {
            return false;
        }

        $role = strtolower(implode(' ', array_filter([
            $employee->jabatan,
            $employee->posisi,
            $employee->posisi_title,
        ])));

        return str_contains($role, 'supervisor') || preg_match('/\bspv\b/i', $role) === 1;
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
