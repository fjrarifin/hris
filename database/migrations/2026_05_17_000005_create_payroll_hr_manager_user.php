<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('users')->where('username', 'hrd0001')->first();

        if ($existing) {
            DB::table('users')
                ->where('username', 'hrd0001')
                ->update([
                    'name' => 'HR Manager',
                    'level' => 2,
                    'email' => $existing->email ?: 'hrd0001@hris.local',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('users')->insert([
            'name' => 'HR Manager',
            'username' => 'hrd0001',
            'level' => 2,
            'email' => 'hrd0001@hris.local',
            'password' => Hash::make('password'),
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'hrd0001')->delete();
    }
};
