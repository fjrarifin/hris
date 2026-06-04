<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrd_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 100)->index();
            $table->string('action', 30)->index();
            $table->string('subject_type', 150)->nullable();
            $table->string('subject_id', 100)->nullable()->index();
            $table->string('subject_label')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_username')->nullable();
            $table->json('changes')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'audit-logs'],
            [
                'label' => 'Log Perubahan',
                'path' => '/it/audit-logs',
                'icon' => 'i-lucide-history',
                'allowed_levels' => '0',
                'sort_order' => 95,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'audit-logs')->delete();
        Schema::dropIfExists('hrd_audit_logs');
    }
};
