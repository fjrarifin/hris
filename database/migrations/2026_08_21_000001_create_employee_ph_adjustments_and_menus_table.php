<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create employee_ph_adjustments table
        if (! Schema::hasTable('employee_ph_adjustments')) {
            Schema::create('employee_ph_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('karyawan_nik', 50)->index();
                $table->integer('days'); // Positive for grant (+N), Negative for deduction (-N)
                $table->date('adjustment_date')->useCurrent();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 2. Ensure leave_accruals has notes / description if not present
        if (Schema::hasTable('leave_accruals') && ! Schema::hasColumn('leave_accruals', 'notes')) {
            Schema::table('leave_accruals', function (Blueprint $table): void {
                $table->text('notes')->nullable()->after('is_used');
            });
        }

        // 3. Register frontend menus for the 3 adjustment types
        if (Schema::hasTable('frontend_menus')) {
            $now = now();
            $menus = [
                [
                    'key' => 'hr-leave-adjustments',
                    'label' => 'Adjustment Saldo Cuti',
                    'path' => '/hr/adjustments/leave',
                    'icon' => 'i-lucide-calendar-plus',
                    'allowed_levels' => '0,1,2',
                    'sort_order' => 31,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'hr-ph-adjustments',
                    'label' => 'Adjustment Saldo PH',
                    'path' => '/hr/adjustments/ph',
                    'icon' => 'i-lucide-calendar-check-2',
                    'allowed_levels' => '0,1,2',
                    'sort_order' => 32,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'hr-extra-off-adjustments',
                    'label' => 'Adjustment Saldo Extra Off',
                    'path' => '/hr/adjustments/extra-off',
                    'icon' => 'i-lucide-clock-plus',
                    'allowed_levels' => '0,1,2',
                    'sort_order' => 33,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            foreach ($menus as $menu) {
                $exists = DB::table('frontend_menus')->where('key', $menu['key'])->exists();
                if (! $exists) {
                    DB::table('frontend_menus')->insert($menu);
                } else {
                    DB::table('frontend_menus')->where('key', $menu['key'])->update([
                        'label' => $menu['label'],
                        'path' => $menu['path'],
                        'icon' => $menu['icon'],
                        'allowed_levels' => $menu['allowed_levels'],
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ph_adjustments');

        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->whereIn('key', ['hr-leave-adjustments', 'hr-ph-adjustments', 'hr-extra-off-adjustments'])
                ->delete();
        }
    }
};
