<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $hrKeys = [
        'hr-schedules',
        'hr-approval-leave',
        'hr-approval-overtime',
        'hr-approval-ph',
        'hr-approval-permission',
        'hr-guide',
    ];

    public function up(): void
    {
        DB::table('frontend_menus')->where('key', 'dashboard')->update(['icon' => 'i-lucide-layout-dashboard']);
        DB::table('frontend_menus')->where('key', 'employees')->update(['icon' => 'i-lucide-users-round']);
        DB::table('frontend_menus')->where('key', 'attendance')->update(['icon' => 'i-lucide-calendar-clock']);
        DB::table('frontend_menus')->where('key', 'payroll')->update(['allowed_levels' => '1']);

        $now = now();
        DB::table('frontend_menus')->insertOrIgnore([
            [
                'key' => 'hr-schedules',
                'label' => 'Jadwal Karyawan',
                'path' => '/hr/schedules',
                'icon' => 'i-lucide-calendar-range',
                'allowed_levels' => '2',
                'sort_order' => 45,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-approval-leave',
                'label' => 'Cuti',
                'path' => '/hr/approvals/leave',
                'icon' => 'i-lucide-calendar-check',
                'allowed_levels' => '2',
                'sort_order' => 50,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-approval-overtime',
                'label' => 'Lembur',
                'path' => '/hr/approvals/overtime',
                'icon' => 'i-lucide-clock-3',
                'allowed_levels' => '2',
                'sort_order' => 51,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-approval-ph',
                'label' => 'PH',
                'path' => '/hr/approvals/ph',
                'icon' => 'i-lucide-sun',
                'allowed_levels' => '2',
                'sort_order' => 52,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-approval-permission',
                'label' => 'Izin / Sakit',
                'path' => '/hr/approvals/permission',
                'icon' => 'i-lucide-file-heart',
                'allowed_levels' => '2',
                'sort_order' => 53,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-guide',
                'label' => 'Panduan Aplikasi',
                'path' => '/hr/guide',
                'icon' => 'i-lucide-book-open',
                'allowed_levels' => '2',
                'sort_order' => 60,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->whereIn('key', $this->hrKeys)->delete();
        DB::table('frontend_menus')->where('key', 'payroll')->update(['allowed_levels' => '1,2']);
    }
};
