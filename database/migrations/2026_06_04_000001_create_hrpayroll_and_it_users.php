<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->upsertUser([
            'username' => 'hrpayroll',
            'name' => 'HR Payroll',
            'email' => 'hrpayroll@hris.local',
            'level' => 2,
        ]);

        $this->upsertUser([
            'username' => 'it',
            'name' => 'IT Administrator',
            'email' => 'it@hris.local',
            'level' => 0,
        ]);

        $payrollUser = DB::table('users')->where('username', 'hrpayroll')->first();

        if ($payrollUser) {
            $payrollMenuKeys = ['dashboard', 'payroll', 'hr-payroll-master', 'hr-payroll-process'];

            DB::table('frontend_menus')
                ->orderBy('id')
                ->get(['id', 'key'])
                ->each(function ($menu) use ($payrollUser, $payrollMenuKeys, $now): void {
                    if ($menu->key === 'dashboard') {
                        return;
                    }

                    DB::table('frontend_menu_user_access')->updateOrInsert(
                        [
                            'frontend_menu_id' => $menu->id,
                            'user_id' => $payrollUser->id,
                        ],
                        [
                            'is_allowed' => in_array($menu->key, $payrollMenuKeys, true),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                });
        }
    }

    public function down(): void
    {
        $users = DB::table('users')
            ->whereIn('username', ['hrpayroll', 'it'])
            ->get(['id', 'username']);

        DB::table('frontend_menu_user_access')
            ->whereIn('user_id', $users->pluck('id')->all())
            ->delete();

        DB::table('users')->whereIn('username', ['hrpayroll', 'it'])->delete();
    }

    private function upsertUser(array $user): void
    {
        $existing = DB::table('users')->where('username', $user['username'])->first();

        if ($existing) {
            DB::table('users')
                ->where('username', $user['username'])
                ->update([
                    'name' => $existing->name ?: $user['name'],
                    'email' => $existing->email ?: $user['email'],
                    'level' => $user['level'],
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('users')->insert([
            'username' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => Hash::make('12345678'),
            'level' => $user['level'],
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
