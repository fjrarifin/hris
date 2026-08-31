<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create master_business_units Table
        if (! Schema::hasTable('master_business_units')) {
            Schema::create('master_business_units', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Seed Default Business Units
        $defaultBusinessUnits = ['Holding', 'HomPimPlay', 'Playground', 'Store'];
        $now = now();
        foreach ($defaultBusinessUnits as $bu) {
            DB::table('master_business_units')->insertOrIgnore([
                'name' => $bu,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Add Bisnis Unit Frontend Menu
        DB::table('frontend_menus')->insertOrIgnore([
            [
                'key' => 'hr-master-business-units',
                'label' => 'Master Bisnis Unit',
                'path' => '/hr/master/business-units',
                'icon' => 'i-lucide-briefcase',
                'allowed_levels' => '2',
                'sort_order' => 23,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 4. Update Sort Orders in frontend_menus for the clean hierarchy
        DB::table('frontend_menus')->where('key', 'hr-master-business-units')->update(['sort_order' => 23]);
        DB::table('frontend_menus')->where('key', 'hr-master-divisions')->update(['sort_order' => 24]);
        DB::table('frontend_menus')->where('key', 'hr-master-departments')->update(['sort_order' => 25]);
        DB::table('frontend_menus')->where('key', 'hr-master-units')->update(['sort_order' => 26]);
        DB::table('frontend_menus')->where('key', 'hr-master-positions')->update(['sort_order' => 27]);
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'hr-master-business-units')->delete();
        Schema::dropIfExists('master_business_units');
    }
};
