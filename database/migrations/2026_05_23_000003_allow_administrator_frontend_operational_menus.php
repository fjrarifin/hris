<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateAdministratorAccess(true);
    }

    public function down(): void
    {
        $this->updateAdministratorAccess(false);
    }

    private function updateAdministratorAccess(bool $allow): void
    {
        DB::table('frontend_menus')
            ->whereIn('key', ['employees', 'payroll', 'attendance'])
            ->get(['id', 'allowed_levels'])
            ->each(function ($menu) use ($allow) {
                $levels = collect(explode(',', (string) $menu->allowed_levels))
                    ->filter(fn ($level) => $level !== '')
                    ->when(
                        $allow,
                        fn ($collection) => $collection->push('1'),
                        fn ($collection) => $collection->reject(fn ($level) => $level === '1')
                    )
                    ->unique()
                    ->sort()
                    ->values()
                    ->implode(',');

                DB::table('frontend_menus')
                    ->where('id', $menu->id)
                    ->update([
                        'allowed_levels' => $levels,
                        'updated_at' => now(),
                    ]);
            });
    }
};
