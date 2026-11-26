<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recruitment_candidate_pkb_signers', 'public_token')) {
            Schema::table('recruitment_candidate_pkb_signers', function (Blueprint $table) {
                $table->string('public_token', 64)->nullable()->unique()->after('employee_nik');
            });
        }

        DB::table('recruitment_candidate_pkb_signers')
            ->whereNull('public_token')
            ->orderBy('id')
            ->eachById(function ($row): void {
                DB::table('recruitment_candidate_pkb_signers')
                    ->where('id', $row->id)
                    ->update(['public_token' => Str::random(48)]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('recruitment_candidate_pkb_signers', 'public_token')) {
            Schema::table('recruitment_candidate_pkb_signers', function (Blueprint $table) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            });
        }
    }
};
