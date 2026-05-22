<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $data = [
            'name' => 'Kategori Jadwal',
            'route' => 'hr.schedule-categories.index',
            'icon' => 'fas fa-calendar-alt',
            'order' => 14,
            'is_active' => 1,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menus', 'allowed_levels')) {
            $data['allowed_levels'] = '2';
        }

        if (Schema::hasColumn('menus', 'permission_key')) {
            $data['permission_key'] = 'hr.schedule-categories';
        }

        $menu = DB::table('menus')->where('route', 'hr.schedule-categories.index')->first();

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
        DB::table('menus')->where('route', 'hr.schedule-categories.index')->delete();
    }
};
