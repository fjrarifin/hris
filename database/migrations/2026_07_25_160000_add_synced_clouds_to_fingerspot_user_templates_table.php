<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fingerspot_user_templates') && ! Schema::hasColumn('fingerspot_user_templates', 'synced_clouds')) {
            Schema::table('fingerspot_user_templates', function (Blueprint $table) {
                $table->json('synced_clouds')->nullable()->after('cloud_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fingerspot_user_templates') && Schema::hasColumn('fingerspot_user_templates', 'synced_clouds')) {
            Schema::table('fingerspot_user_templates', function (Blueprint $table) {
                $table->dropColumn('synced_clouds');
            });
        }
    }
};
