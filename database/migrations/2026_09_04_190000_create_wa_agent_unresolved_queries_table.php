<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('t_wa_agent_unresolved_queries')) {
            Schema::create('t_wa_agent_unresolved_queries', function (Blueprint $table) {
                $table->id();
                $table->string('sender_phone', 50)->index();
                $table->string('karyawan_nik', 50)->nullable()->index();
                $table->string('sender_name', 255)->nullable();
                $table->text('question');
                $table->text('bot_response')->nullable();
                $table->string('category', 100)->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->text('admin_notes')->nullable();
                $table->unsignedInteger('ask_count')->default(1);
                $table->timestamp('last_asked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_wa_agent_unresolved_queries');
    }
};
