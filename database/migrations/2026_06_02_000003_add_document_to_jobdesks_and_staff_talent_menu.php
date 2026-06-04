<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobdesks', function (Blueprint $table): void {
            $table->string('document')->nullable()->after('is_active');
        });

        $now = now();
        DB::table('frontend_menus')->insertOrIgnore([
            'key' => 'staff-talent',
            'label' => 'Jobdesk & KPI Saya',
            'path' => '/staff/talent',
            'icon' => 'i-lucide-clipboard-list',
            'allowed_levels' => '3',
            'sort_order' => 47,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'staff-talent')->delete();

        Schema::table('jobdesks', function (Blueprint $table): void {
            $table->dropColumn('document');
        });
    }
};
