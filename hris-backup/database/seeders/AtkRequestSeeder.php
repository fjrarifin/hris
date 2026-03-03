<?php

namespace Database\Seeders;

use App\Models\AtkRequest;
use Illuminate\Database\Seeder;

class AtkRequestSeeder extends Seeder
{
    public function run(): void
    {
        AtkRequest::factory()->count(25)->create();
    }
}
