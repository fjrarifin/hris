<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    public function run()
    {
        $dashboard = Menu::create([
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'order' => 1,
            'permission_key' => 'dashboard.view'
        ]);

        $users = Menu::create([
            'name' => 'Users',
            'icon' => 'fas fa-users',
            'order' => 2,
            'permission_key' => 'users.view'
        ]);

        Menu::create([
            'parent_id' => $users->id,
            'name' => 'List User',
            'route' => 'users.index',
            'permission_key' => 'users.view'
        ]);

        Menu::create([
            'parent_id' => $users->id,
            'name' => 'Tambah User',
            'route' => 'users.create',
            'permission_key' => 'users.create'
        ]);
    }
}
