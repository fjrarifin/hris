<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $data = [
            'name' => 'Jadwal Karyawan',
            'route' => 'hr.employee-schedules.index',
            'icon' => 'fas fa-calendar-check',
            'order' => 15,
            'is_active' => 1,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menus', 'allowed_levels')) {
            $data['allowed_levels'] = '2';
        }

        if (Schema::hasColumn('menus', 'permission_key')) {
            $data['permission_key'] = 'hr.employee-schedules';
        }

        $menu = DB::table('menus')->where('route', 'hr.employee-schedules.index')->first();

        if ($menu) {
            DB::table('menus')->where('id', $menu->id)->update($data);
            return;
        }

        DB::table('menus')->insert($data + [
            'parent_id' => null,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('route', 'hr.employee-schedules.index')->delete();
    }
};
