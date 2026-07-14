<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $keys = [
        'staff-recruitment-requests',
        'hr-recruitment-requests',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('frontend_menus')->insertOrIgnore([
            [
                'key' => 'staff-recruitment-requests',
                'label' => 'Pengajuan Rekrutmen',
                'path' => '/staff/recruitment/requests',
                'icon' => 'i-lucide-user-plus',
                'allowed_levels' => '3',
                'sort_order' => 45,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-recruitment-requests',
                'label' => 'Persetujuan Lowongan',
                'path' => '/hr/recruitment/requests',
                'icon' => 'i-lucide-clipboard-check',
                'allowed_levels' => '2',
                'sort_order' => 67,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->whereIn('key', $this->keys)->delete();
    }
};
