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
        Schema::create('t_whatsapp_bot_faq', function (Blueprint $table) {
            $table->id();
            $table->string('topic')->nullable()->comment('Topik atau judul pertanyaan');
            $table->text('keywords')->comment('Kata kunci pencocokan, pisahkan dengan koma');
            $table->text('answer')->comment('Jawaban bot');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_whatsapp_bot_faq');
    }
};
