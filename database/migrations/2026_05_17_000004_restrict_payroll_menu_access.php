<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('route', 'hr.payroll.index')
            ->where('name', 'Payroll')
            ->update([
                'allowed_levels' => '1,2',
                'is_active' => 1,
            ]);

        DB::table('menus')
            ->where('route', 'hr.payroll.index')
            ->where('name', 'Data Payroll')
            ->update([
                'is_active' => 0,
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('route', 'hr.payroll.index')
            ->where('name', 'Payroll')
            ->update([
                'allowed_levels' => '2',
            ]);

        DB::table('menus')
            ->where('route', 'hr.payroll.index')
            ->where('name', 'Data Payroll')
            ->update([
                'is_active' => 1,
            ]);
    }
};
