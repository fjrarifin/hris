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
            ->where('key', 'it-event-absen')
            ->exists();

        if (! $exists) {
            DB::table('frontend_menus')->insert([
                'key' => 'it-event-absen',
                'label' => 'Absen Event',
                'path' => '/it/event-absen',
                'icon' => 'i-lucide-calendar-check',
                'allowed_levels' => '0,1,2',
                'sort_order' => 48,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('frontend_menus')
                ->where('key', 'it-event-absen')
                ->update([
                    'label' => 'Absen Event',
                    'path' => '/it/event-absen',
                    'icon' => 'i-lucide-calendar-check',
                    'allowed_levels' => '0,1,2',
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'it-event-absen')
                ->delete();
        }
    }
};
