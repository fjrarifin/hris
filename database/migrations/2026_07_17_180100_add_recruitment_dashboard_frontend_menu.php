<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'hr-recruitment-dashboard'],
            [
                'label' => 'Dashboard Recruitment',
                'path' => '/hr/recruitment/dashboard',
                'icon' => 'i-lucide-chart-no-axes-combined',
                'allowed_levels' => '2',
                'sort_order' => 64,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'hr-recruitment-dashboard')->delete();
    }
};
