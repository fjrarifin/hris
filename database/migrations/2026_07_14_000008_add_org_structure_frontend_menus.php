<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $keys = [
        'hr-master-positions',
        'hr-master-divisions',
        'hr-master-departments',
        'hr-master-units',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('frontend_menus')->insertOrIgnore([
            [
                'key' => 'hr-master-positions',
                'label' => 'Master Posisi',
                'path' => '/hr/master/positions',
                'icon' => 'i-lucide-award',
                'allowed_levels' => '2',
                'sort_order' => 24,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-master-divisions',
                'label' => 'Master Divisi',
                'path' => '/hr/master/divisions',
                'icon' => 'i-lucide-network',
                'allowed_levels' => '2',
                'sort_order' => 25,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-master-departments',
                'label' => 'Master Departemen',
                'path' => '/hr/master/departments',
                'icon' => 'i-lucide-building',
                'allowed_levels' => '2',
                'sort_order' => 26,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-master-units',
                'label' => 'Master Unit',
                'path' => '/hr/master/units',
                'icon' => 'i-lucide-layers',
                'allowed_levels' => '2',
                'sort_order' => 27,
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
