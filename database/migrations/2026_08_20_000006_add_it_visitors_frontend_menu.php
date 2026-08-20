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
            ->where('key', 'it-visitors')
            ->exists();

        if (! $exists) {
            DB::table('frontend_menus')->insert([
                'key' => 'it-visitors',
                'label' => 'Buku Tamu / Visitor',
                'path' => '/it/visitors',
                'icon' => 'i-lucide-id-card',
                'allowed_levels' => '0,1,2',
                'sort_order' => 49,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('frontend_menus')
                ->where('key', 'it-visitors')
                ->update([
                    'label' => 'Buku Tamu / Visitor',
                    'path' => '/it/visitors',
                    'icon' => 'i-lucide-id-card',
                    'allowed_levels' => '0,1,2',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'it-visitors')
                ->delete();
        }
    }
};
