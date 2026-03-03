<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('public_holiday_requests', function (Blueprint $table) {

            // Drop foreign keys dulu
            $table->dropForeign(['user_id']);
            $table->dropForeign(['public_holiday_id']);

            // Drop unique index
            $table->dropUnique('public_holiday_requests_user_id_public_holiday_id_unique');
        });

        Schema::table('public_holiday_requests', function (Blueprint $table) {

            // Recreate foreign keys TANPA unique
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('public_holiday_id')
                ->references('id')
                ->on('public_holidays')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('public_holiday_requests', function (Blueprint $table) {

            $table->dropForeign(['user_id']);
            $table->dropForeign(['public_holiday_id']);

            $table->unique(['user_id', 'public_holiday_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->foreign('public_holiday_id')
                ->references('id')
                ->on('public_holidays');
        });
    }
};
