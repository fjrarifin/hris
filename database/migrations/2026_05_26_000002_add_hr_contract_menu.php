<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'hr-contracts',
            'label' => 'Kontrak Karyawan',
            'path' => '/hr/contracts',
            'icon' => 'i-lucide-file-clock',
            'allowed_levels' => '2',
            'sort_order' => 25,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'hr-contracts')->delete();
    }
};
