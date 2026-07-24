<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_components')
            ->where('nama', 'Tunjangan Tidak Tetap')
            ->update([
                'input_mode' => 'manual',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('payroll_components')
            ->where('nama', 'Tunjangan Tidak Tetap')
            ->update([
                'input_mode' => 'calculated',
                'updated_at' => now(),
            ]);
    }
};
