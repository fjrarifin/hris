<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $superAdmin = Role::where('level', 0)->first();
        $admin = Role::where('level', 1)->first();

        $superAdmin->permissions()->sync(
            Permission::pluck('id')
        );

        $admin->permissions()->sync(
            Permission::whereIn('key', [
                'dashboard.view',
                'users.view',
                'users.create',
                'users.edit',
            ])->pluck('id')
        );
    }
}
