<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('leave_type', [
                'cuti_tahunan',
                'cuti_hamil_melahirkan',
                'cuti_menikah',
                'cuti_menikahkan_anak',
                'cuti_mengkhitankan_anak',
                'cuti_membaptiskan_anak',
                'cuti_istri_melahirkan',
                'public_holiday',
                'lainnya'
            ])->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('leave_type');
        });
    }
};