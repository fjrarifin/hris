<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        Permission::insert([
            ['key' => 'dashboard.view', 'label' => 'Lihat Dashboard'],
            ['key' => 'users.view', 'label' => 'Lihat User'],
            ['key' => 'users.create', 'label' => 'Tambah User'],
            ['key' => 'users.edit', 'label' => 'Edit User'],
            ['key' => 'users.delete', 'label' => 'Hapus User'],
            ['key' => 'menus.manage', 'label' => 'Kelola Menu'],
        ]);
    }
}
