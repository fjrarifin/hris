<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $keys = [
        'hr-talent-jobdesks',
        'hr-talent-kpis',
        'hr-talent-periods',
        'hr-talent-reviews',
        'staff-performance-reviews',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('frontend_menus')->insertOrIgnore([
            ['key' => 'hr-talent-jobdesks', 'label' => 'Jobdesk', 'path' => '/hr/talent/jobdesks', 'icon' => 'i-lucide-list-checks', 'allowed_levels' => '2', 'sort_order' => 55, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hr-talent-kpis', 'label' => 'Template KPI', 'path' => '/hr/talent/kpis', 'icon' => 'i-lucide-target', 'allowed_levels' => '2', 'sort_order' => 56, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hr-talent-periods', 'label' => 'Periode Review', 'path' => '/hr/talent/periods', 'icon' => 'i-lucide-calendar-days', 'allowed_levels' => '2', 'sort_order' => 57, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hr-talent-reviews', 'label' => 'Performance Review', 'path' => '/hr/talent/reviews', 'icon' => 'i-lucide-chart-no-axes-combined', 'allowed_levels' => '2', 'sort_order' => 58, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'staff-performance-reviews', 'label' => 'Performance Review', 'path' => '/staff/performance-reviews', 'icon' => 'i-lucide-chart-no-axes-combined', 'allowed_levels' => '3', 'sort_order' => 48, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->whereIn('key', $this->keys)->delete();
    }
};
