<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $components = [
            ['nama' => 'Penyesuaian Pembulatan', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Potongan Pembulatan', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Tunjangan PPh21', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
        ];

        foreach ($components as $component) {
            $exists = DB::table('payroll_components')->where('nama', $component['nama'])->exists();
            $values = $component + [
                'updated_at' => $now,
            ];

            if (! $exists) {
                DB::table('payroll_components')->insert($values + [
                    'created_at' => $now,
                ]);
                continue;
            }

            DB::table('payroll_components')
                ->where('nama', $component['nama'])
                ->update($values);
        }

        // Set Potongan Alpha to inactive to match local environment configuration
        DB::table('payroll_components')
            ->where('nama', 'Potongan Alpha')
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payroll_components')
            ->whereIn('nama', ['Penyesuaian Pembulatan', 'Potongan Pembulatan', 'Tunjangan PPh21'])
            ->delete();

        DB::table('payroll_components')
            ->where('nama', 'Potongan Alpha')
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
