<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->insertOrIgnore([
            [
                'key' => 'staff-leave',
                'label' => 'Pengajuan Cuti',
                'path' => '/staff/leave',
                'icon' => 'i-lucide-calendar-check',
                'allowed_levels' => '3',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'staff-public-holiday',
                'label' => 'Public Holiday',
                'path' => '/staff/public-holiday',
                'icon' => 'i-lucide-calendar-days',
                'allowed_levels' => '3',
                'sort_order' => 21,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'staff-permission',
                'label' => 'Izin / Sakit',
                'path' => '/staff/permission',
                'icon' => 'i-lucide-file-text',
                'allowed_levels' => '3',
                'sort_order' => 22,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'staff-approvals',
                'label' => 'Approval Pengajuan',
                'path' => '/staff/approvals',
                'icon' => 'i-lucide-badge-check',
                'allowed_levels' => '3',
                'sort_order' => 23,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'staff-overtime',
                'label' => 'Pengajuan Lembur',
                'path' => '/staff/overtime',
                'icon' => 'i-lucide-clock-3',
                'allowed_levels' => '3',
                'sort_order' => 24,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'staff-guide',
                'label' => 'Panduan Aplikasi',
                'path' => '/staff/guide',
                'icon' => 'i-lucide-book-open',
                'allowed_levels' => '3',
                'sort_order' => 25,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')
            ->whereIn('key', [
                'staff-leave',
                'staff-public-holiday',
                'staff-permission',
                'staff-approvals',
                'staff-overtime',
                'staff-guide',
            ])
            ->delete();
    }
};
