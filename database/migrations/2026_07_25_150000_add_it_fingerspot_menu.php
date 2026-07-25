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
            ->where('key', 'it-fingerspot')
            ->exists();

        if (! $exists) {
            DB::table('frontend_menus')->insert([
                'key' => 'it-fingerspot',
                'label' => 'Mesin Fingerspot',
                'path' => '/it/fingerspot',
                'icon' => 'i-lucide-fingerprint',
                'allowed_levels' => '0',
                'sort_order' => 45,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('frontend_menus')
                ->where('key', 'it-fingerspot')
                ->update([
                    'allowed_levels' => '0',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'it-fingerspot')
                ->delete();
        }
    }
};
