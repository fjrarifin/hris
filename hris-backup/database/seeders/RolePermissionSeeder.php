<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // admin core
            'admin.dashboard.view',

            // master data
            'karyawan.view',
            'users.view',
            'relasi.view',

            // faktor & score
            'faktor.view',
            'faktor.manage',
            'faktor_score.manage',

            // monitoring penilaian
            'monitoring_penilaian.view',

            // user penilaian
            'penilaian.fill',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $hrdAdmin = Role::firstOrCreate(['name' => 'hrd_admin']);
        $hrPenilaian = Role::firstOrCreate(['name' => 'hr_penilaian']);
        $user = Role::firstOrCreate(['name' => 'user']);

        // Permissions assignment
        $superAdmin->syncPermissions($permissions);

        $hrdAdmin->syncPermissions([
            'admin.dashboard.view',
            'karyawan.view',
            'users.view',
            'relasi.view',
            'faktor.view',
            'faktor.manage',
            'faktor_score.manage',
            'monitoring_penilaian.view',
        ]);

        $hrPenilaian->syncPermissions([
            'admin.dashboard.view',
            'relasi.view',
            'faktor.view',
            'faktor_score.manage',
            'monitoring_penilaian.view',
        ]);

        $user->syncPermissions([
            'penilaian.fill',
        ]);
    }
}
