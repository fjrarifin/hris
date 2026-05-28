<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'online_latitude')) {
                $table->decimal('online_latitude', 10, 7)->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('users', 'online_longitude')) {
                $table->decimal('online_longitude', 10, 7)->nullable()->after('online_latitude');
            }

            if (! Schema::hasColumn('users', 'online_city')) {
                $table->string('online_city')->nullable()->after('online_longitude');
            }

            if (! Schema::hasColumn('users', 'online_location_updated_at')) {
                $table->timestamp('online_location_updated_at')->nullable()->after('online_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'online_location_updated_at',
                'online_city',
                'online_longitude',
                'online_latitude',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
