<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('frontend_menus')
            ->where('key', 'staff-recruitment-requests')
            ->update([
                'is_active' => true,
                'allowed_levels' => '3',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')
            ->where('key', 'staff-recruitment-requests')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
