<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'staff-extra-off'],
            [
                'label' => 'Extra Off',
                'path' => '/staff/extra-off',
                'icon' => 'CalendarPlus',
                'allowed_levels' => '3',
                'sort_order' => 37,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'hr-approval-extra-off'],
            [
                'label' => 'Approval Extra Off',
                'path' => '/hr/approvals/extra-off',
                'icon' => 'CalendarPlus',
                'allowed_levels' => '2',
                'sort_order' => 76,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-extra-off')->delete();
        DB::table('frontend_menus')->where('key', 'hr-approval-extra-off')->delete();
    }
};
