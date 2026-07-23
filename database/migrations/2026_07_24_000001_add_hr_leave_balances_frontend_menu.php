<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->insertOrIgnore([
                'key' => 'hr-leave-balances',
                'label' => 'Sisa Jatah Cuti / PH / EO',
                'path' => '/hr/leave-balances',
                'icon' => 'i-lucide-badge-percent',
                'sort_order' => 15,
                'allowed_levels' => '0,1,2',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->where('key', 'hr-leave-balances')->delete();
        }
    }
};
