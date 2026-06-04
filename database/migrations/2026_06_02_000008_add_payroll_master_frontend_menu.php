<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'hr-payroll-master'],
            [
                'label' => 'Master Payroll',
                'path' => '/payroll/master',
                'icon' => 'i-lucide-wallet-cards',
                'allowed_levels' => '1,2',
                'sort_order' => 31,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('frontend_menus')
            ->where('key', 'payroll')
            ->update([
                'allowed_levels' => '1,2',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'hr-payroll-master')->delete();
    }
};
