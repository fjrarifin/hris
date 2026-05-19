<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $data = [
            'name' => 'Approval HR',
            'route' => 'hr.approval.all',
            'icon' => 'fas fa-clipboard-check',
            'order' => 12,
            'is_active' => 1,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menus', 'allowed_levels')) {
            $data['allowed_levels'] = '2';
        }

        if (Schema::hasColumn('menus', 'permission_key')) {
            $data['permission_key'] = 'hr.approval';
        }

        $menu = DB::table('menus')->where('route', 'hr.approval.all')->first();

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
        DB::table('menus')->where('route', 'hr.approval.all')->delete();
    }
};
