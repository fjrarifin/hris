<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('frontend_menus')) {
            return;
        }
        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'hr-permission-recap',
            'label' => 'Rekap Izin / Sakit',
            'path' => '/hr/permission-recap',
            'icon' => 'i-lucide-file-spreadsheet',
            'allowed_levels' => '0,2',
            'sort_order' => 56,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->where('key', 'hr-permission-recap')->delete();
        }
    }
};
