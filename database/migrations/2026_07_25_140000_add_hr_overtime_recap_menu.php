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
            ->where('key', 'hr-overtime-recap')
            ->exists();

        if (! $exists) {
            DB::table('frontend_menus')->insert([
                'key' => 'hr-overtime-recap',
                'label' => 'Rekap Lembur',
                'path' => '/hr/overtime-recap',
                'icon' => 'i-lucide-file-spreadsheet',
                'allowed_levels' => '0,2',
                'sort_order' => 55,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('frontend_menus')
                ->where('key', 'hr-overtime-recap')
                ->update([
                    'allowed_levels' => '0,2',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'hr-overtime-recap')
                ->delete();
        }
    }
};
