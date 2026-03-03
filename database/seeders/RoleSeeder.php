<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        Role::insert([
            ['name' => 'Super Admin', 'level' => 0],
            ['name' => 'Admin', 'level' => 1],
            ['name' => 'Manager', 'level' => 2],
            ['name' => 'Staff', 'level' => 3],
        ]);
    }
}
