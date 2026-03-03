<?php

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Karyawan;

function sidebarMenus()
{
    if (!Auth::check()) {
        return collect();
    }

    $level = Auth::user()->level;

    // ambil SEMUA parent aktif
    $parents = Menu::whereNull('parent_id')
        ->where('is_active', 1)
        ->with(['children' => function ($q) use ($level) {
            $q->where('is_active', 1)
                ->whereRaw('FIND_IN_SET(?, allowed_levels)', [$level]);
        }])
        ->orderBy('order')
        ->get();

    // filter parent:
    // - punya child yg lolos
    // - ATAU parent sendiri boleh
    return $parents->filter(function ($menu) use ($level) {
        $parentAllowed = $menu->allowed_levels
            && str_contains(',' . $menu->allowed_levels . ',', ',' . $level . ',');

        return $parentAllowed || $menu->children->count() > 0;
    });
}


function isMenuActive($menu): bool
{
    // cek parent route (pakai wildcard)
    if ($menu->route && request()->routeIs($menu->route . '*')) {
        return true;
    }

    // cek child route
    foreach ($menu->children as $child) {
        if ($child->route && request()->routeIs($child->route . '*')) {
            return true;
        }
    }

    return false;
}


function isOpenTree($menu)
{
    return isMenuActive($menu) ? 'menu-open' : '';
}

function normalizePhone(string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (str_starts_with($phone, '0')) {
        return '62' . substr($phone, 1);
    }

    return $phone;
}

function currentQuarterPeriod(): string
{
    $month = now()->month;
    $year  = now()->year;

    if ($month <= 3) {
        $quarterMonth = 1;
    } elseif ($month <= 6) {
        $quarterMonth = 4;
    } elseif ($month <= 9) {
        $quarterMonth = 7;
    } else {
        $quarterMonth = 10;
    }

    return sprintf('%d-%02d', $year, $quarterMonth);
}

function quarterDateRange(string $periode): array
{
    [$year, $month] = explode('-', $periode);

    $start = \Carbon\Carbon::create((int)$year, (int)$month, 1)->startOfMonth();
    $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

    return [$start, $end];
}

if (!function_exists('punyaBawahan')) {
    function punyaBawahan(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $nikLogin = Auth::user()->username ?? null;

        if (!$nikLogin) {
            return false;
        }

        $karyawan = Karyawan::where('nik', $nikLogin)->first();

        if (!$karyawan || !$karyawan->nama_karyawan) {
            return false;
        }

        return Karyawan::where('nama_atasan_langsung', $karyawan->nama_karyawan)
            ->exists();
    }
}
