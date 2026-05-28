<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['username' => 'hrd0003', 'name' => 'HRD 0003', 'email' => 'hrd0003@hris.local'],
            ['username' => 'hrd0004', 'name' => 'HRD 0004', 'email' => 'hrd0004@hris.local'],
        ] as $user) {
            $existing = DB::table('users')->where('username', $user['username'])->first();

            if ($existing) {
                DB::table('users')
                    ->where('username', $user['username'])
                    ->update([
                        'name' => $existing->name ?: $user['name'],
                        'email' => $existing->email ?: $user['email'],
                        'level' => 2,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('users')->insert([
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make('12345678'),
                'level' => 2,
                'must_change_password' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->whereIn('username', ['hrd0003', 'hrd0004'])->delete();
    }
};
