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
            ->when((int) $user->level !== 0, fn ($query) => $query
                ->where(fn ($inner) => $inner->where('is_active', true)->orWhere('key', 'dashboard')))
            ->with(['userAccess' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (FrontendMenu $menu) => $this->isAllowed($user, $menu))
            ->map(fn (FrontendMenu $menu) => $this->serializeMenu($menu, $user))
            ->values();

        if ((int) $user->level === 0) {
            return $this->groupItMenus($menus);
        }

        if ((int) $user->level === 2) {
            return $this->groupHrMenus($menus);
        }

        if ((int) $user->level === 3) {
            return $this->groupStaffMenus($menus, $user);
        }

        return $menus;
    }

    private function groupItMenus(Collection $menus): Collection
    {
        $itKeys = ['it-users', 'it-push-notifications', 'it-active-sessions', 'menu-access', 'audit-logs'];
        $itChildren = $menus->whereIn('key', $itKeys)->values()->all();
        $itAnchor = $itChildren[0]['key'] ?? null;

        $menus = $menus->flatMap(function (array $menu) use ($itKeys, $itChildren, $itAnchor): array {
            if ($menu['key'] === $itAnchor && $itChildren) {
                return [[
                    'key' => 'it-admin',
                    'label' => 'IT Admin',
                    'icon' => 'i-lucide-settings',
                    'children' => $itChildren,
                ]];
            }

            if (in_array($menu['key'], $itKeys, true)) {
                return [];
            }

            return [$menu];
        })->values();

        return $this->groupHrMenus(
            $menus
                ->reject(fn (array $menu) => $this->isStaffMenuKey($menu['key']))
                ->values()
        );
    }

    private function groupHrMenus(Collection $menus): Collection
    {
        $payrollKeys = ['payroll', 'hr-payroll-master', 'hr-payroll-process'];
        $payrollChildren = $menus->whereIn('key', $payrollKeys)->values()->all();
        $payrollAnchor = $payrollChildren[0]['key'] ?? null;
        $employeeKeys = ['employees', 'hr-contracts', 'hr-master-positions', 'hr-master-divisions', 'hr-master-departments', 'hr-master-units'];
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

        $attendanceKeys = ['attendance', 'hr-attendance-minimum', 'hr-attendance-corrections', 'hr-schedules'];
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

        $approvalKeys = [
            'hr-approval-leave',
            'hr-approval-overtime',
            'hr-approval-ph',
            'hr-approval-extra-off',
            'hr-approval-permission',
        ];
        $children = $menus->whereIn('key', $approvalKeys)->values()->all();
        $approvalAnchor = $children[0]['key'] ?? null;
        $talentKeys = ['hr-talent-jobdesks', 'hr-talent-kpis', 'hr-talent-periods', 'hr-talent-reviews'];
        $talentChildren = $menus->whereIn('key', $talentKeys)->values()->all();
        $talentAnchor = $talentChildren[0]['key'] ?? null;

        $recruitmentKeys = ['hr-recruitment-dashboard', 'hr-recruitment-vacancies', 'hr-recruitment-candidates', 'hr-recruitment-requests'];
        $recruitmentChildren = $menus->whereIn('key', $recruitmentKeys)->values()->all();
        $recruitmentAnchor = $recruitmentChildren[0]['key'] ?? null;

        return $menus->flatMap(function (array $menu) use (
            $employeeKeys,
            $employeeChildren,
            $employeeAnchor,
            $attendanceKeys,
            $attendanceChildren,
            $attendanceAnchor,
            $approvalKeys,
            $children,
            $approvalAnchor,
            $talentKeys,
            $talentChildren,
            $talentAnchor,
            $payrollKeys,
            $payrollChildren,
            $payrollAnchor,
            $recruitmentKeys,
            $recruitmentChildren,
            $recruitmentAnchor
        ): array {
            if ($menu['key'] === $payrollAnchor && $payrollChildren) {
                return [[
                    'key' => 'hr-payroll',
                    'label' => 'Payroll',
                    'icon' => 'i-lucide-wallet-cards',
                    'children' => $payrollChildren,
                ]];
            }

            if (in_array($menu['key'], $payrollKeys, true)) {
                return [];
            }

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

            if ($menu['key'] === $talentAnchor && $talentChildren) {
                return [[
                    'key' => 'hr-talent',
                    'label' => 'Talent',
                    'icon' => 'i-lucide-chart-no-axes-combined',
                    'children' => $talentChildren,
                ]];
            }

            if (in_array($menu['key'], $talentKeys, true)) {
                return [];
            }

            if ($menu['key'] === $recruitmentAnchor && $recruitmentChildren) {
                return [[
                    'key' => 'hr-recruitment',
                    'label' => 'Recruitment',
                    'icon' => 'i-lucide-briefcase',
                    'children' => $recruitmentChildren,
                ]];
            }

            if (in_array($menu['key'], $recruitmentKeys, true)) {
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

            return in_array($menu['key'], array_merge($approvalKeys, $recruitmentKeys), true) ? [] : [$menu];
        })->values();
    }

    private function groupStaffMenus(Collection $menus, User $user): Collection
    {
        if ($this->hasVacancySupervision($user)) {
            $menus->push([
                'id' => 9999,
                'key' => 'staff-subordinate-candidates',
                'label' => 'Kandidat Bawahan',
                'to' => '/staff/subordinate-candidates',
                'icon' => 'i-lucide-user-search',
            ]);
        }

        $requestKeys = ['staff-leave', 'staff-public-holiday', 'staff-extra-off', 'staff-permission'];
        $requestAnchor = $menus
            ->first(fn (array $menu) => in_array($menu['key'], $requestKeys, true))['key'] ?? null;
        $requestChildren = $menus
            ->whereIn('key', $requestKeys)
            ->sortBy(fn (array $menu) => array_search($menu['key'], $requestKeys, true))
            ->values()
            ->all();

        $supervisorKeys = ['staff-approvals', 'staff-overtime', 'staff-team-schedules', 'staff-subordinate-candidates'];
        $supervisorAnchor = $menus
            ->first(fn (array $menu) => in_array($menu['key'], $supervisorKeys, true))['key'] ?? null;
        $supervisorChildren = $menus
            ->whereIn('key', $supervisorKeys)
            ->sortBy(fn (array $menu) => array_search($menu['key'], $supervisorKeys, true))
            ->map(function (array $menu): array {
                if ($menu['key'] === 'staff-approvals') {
                    $menu['label'] = 'Persetujuan Pengajuan';
                }

                return $menu;
            })
            ->values()
            ->all();

        return $menus->flatMap(function (array $menu) use (
            $requestKeys,
            $requestAnchor,
            $requestChildren,
            $supervisorKeys,
            $supervisorAnchor,
            $supervisorChildren
        ): array {
            if ($menu['key'] === $requestAnchor && $requestChildren) {
                return [[
                    'key' => 'staff-requests',
                    'label' => 'Pengajuan',
                    'icon' => 'i-lucide-clipboard-list',
                    'children' => $requestChildren,
                ]];
            }

            if (in_array($menu['key'], $requestKeys, true)) {
                return [];
            }

            if ($menu['key'] === $supervisorAnchor && $supervisorChildren) {
                return [[
                    'key' => 'staff-supervisor',
                    'label' => 'Menu Atasan',
                    'icon' => 'i-lucide-users-round',
                    'children' => $supervisorChildren,
                ]];
            }

            return in_array($menu['key'], $supervisorKeys, true) ? [] : [$menu];
        })->values();
    }

    private function isStaffMenuKey(string $key): bool
    {
        return str_starts_with($key, 'staff-');
    }

    public function canAccess(User $user, string $key): bool
    {
        if ($key === 'staff-subordinate-candidates') {
            return $this->hasVacancySupervision($user);
        }

        $menu = FrontendMenu::query()
            ->where('key', $key)
            ->when((int) $user->level !== 0, fn ($query) => $query
                ->where(fn ($inner) => $inner->where('is_active', true)->orWhere('key', 'dashboard')))
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

        if (in_array($menu->key, ['staff-approvals', 'staff-overtime', 'staff-recruitment-requests'], true) && ! $this->hasDirectSubordinates($user)) {
            return false;
        }

        if ($menu->key === 'staff-team-schedules' && ! $this->hasScheduleSubordinates($user)) {
            return false;
        }

        if ($menu->key === 'staff-performance-reviews' && ! \App\Models\PerformanceReview::query()->where('reviewer_id', $user->id)->exists()) {
            return false;
        }

        $override = $menu->userAccess->first();

        if ($override) {
            return $override->is_allowed;
        }

        if ((int) $user->level === 0) {
            return true;
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
            ? \App\Models\Karyawan::query()->where('atasan_langsung_nik', $employee->nik)->exists()
            : false;
    }

    private function hasScheduleSubordinates(User $user): bool
    {
        $employee = $user->karyawan;

        return $employee
            ? \App\Models\Karyawan::query()
                ->where('atasan_langsung_nik', $employee->nik)
                ->orWhere('atasan_tidak_langsung_nik', $employee->nik)
                ->exists()
            : false;
    }

    private function hasVacancySupervision(User $user): bool
    {
        $employee = $user->karyawan;

        return $employee
            ? \App\Models\RecruitmentVacancy::query()->where('supervisor_nik', $employee->nik)->exists()
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
