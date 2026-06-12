<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_push_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('message');
            $table->string('audience', 30)->index();
            $table->json('target_user_ids')->nullable();
            $table->string('mobile_path')->default('/notifications');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sent_by_name')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('database_sent_count')->default(0);
            $table->unsignedInteger('push_sent_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('frontend_menus')->updateOrInsert(
            ['key' => 'it-push-notifications'],
            [
                'label' => 'Push Notification',
                'path' => '/it/push-notifications',
                'icon' => 'i-lucide-send',
                'allowed_levels' => '0',
                'sort_order' => 93,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('frontend_menus')->where('key', 'it-push-notifications')->delete();
        Schema::dropIfExists('it_push_notifications');
    }
};
