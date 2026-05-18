<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $data = [
            'name' => 'Panduan Aplikasi',
            'route' => 'guide.index',
            'icon' => 'fas fa-book-open',
            'order' => 98,
            'is_active' => 1,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menus', 'allowed_levels')) {
            $data['allowed_levels'] = '0,1,2,3';
        }

        if (Schema::hasColumn('menus', 'permission_key')) {
            $data['permission_key'] = 'guide.view';
        }

        $menu = DB::table('menus')->where('route', 'guide.index')->first();

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
        DB::table('menus')->where('route', 'guide.index')->delete();
    }
};
