<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_accruals', function (Blueprint $table) {
            $table->string('nik')->after('user_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('leave_accruals', function (Blueprint $table) {
            $table->dropColumn('nik');
        });
    }
};
