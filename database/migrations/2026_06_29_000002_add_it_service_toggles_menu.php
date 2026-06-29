<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'it-service-toggles'],
            [
                'label' => 'Layanan Terjadwal',
                'path' => '/it/service-toggles',
                'icon' => 'i-lucide-toggle-left',
                'allowed_levels' => '0',
                'sort_order' => 95,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'it-service-toggles')->delete();
    }
};
