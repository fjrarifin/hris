<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('frontend_menus')) {
            return;
        }

        $now = now();

        $exists = DB::table('frontend_menus')
            ->where('key', 'hr-holding-employees')
            ->exists();

        if (! $exists) {
            DB::table('frontend_menus')->insert([
                'key' => 'hr-holding-employees',
                'label' => 'Karyawan Holding',
                'path' => '/hr/holding-employees',
                'icon' => 'i-lucide-building-2',
                'allowed_levels' => '0,1,2',
                'sort_order' => 12,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('frontend_menus')
                ->where('key', 'hr-holding-employees')
                ->update([
                    'label' => 'Karyawan Holding',
                    'path' => '/hr/holding-employees',
                    'icon' => 'i-lucide-building-2',
                    'allowed_levels' => '0,1,2',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'hr-holding-employees')
                ->delete();
        }
    }
};
