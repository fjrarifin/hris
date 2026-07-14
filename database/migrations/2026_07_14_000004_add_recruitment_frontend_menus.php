<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $keys = [
        'hr-recruitment-vacancies',
        'hr-recruitment-candidates',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('frontend_menus')->insertOrIgnore([
            [
                'key' => 'hr-recruitment-vacancies',
                'label' => 'Lowongan Kerja',
                'path' => '/hr/recruitment/vacancies',
                'icon' => 'i-lucide-briefcase',
                'allowed_levels' => '2',
                'sort_order' => 65,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'hr-recruitment-candidates',
                'label' => 'Pipeline Pelamar',
                'path' => '/hr/recruitment/candidates',
                'icon' => 'i-lucide-users-round',
                'allowed_levels' => '2',
                'sort_order' => 66,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->whereIn('key', $this->keys)->delete();
    }
};
