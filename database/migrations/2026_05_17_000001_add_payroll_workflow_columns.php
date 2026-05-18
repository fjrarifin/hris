<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('approval_status')->default('draft')->after('total_dibayarkan');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('approval_status');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->boolean('is_locked')->default(false)->after('approval_notes');
            $table->unsignedBigInteger('locked_by')->nullable()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->string('validation_status')->default('unchecked')->after('locked_at');
            $table->json('validation_warnings')->nullable()->after('validation_status');
            $table->unsignedBigInteger('validated_by')->nullable()->after('validation_warnings');
            $table->timestamp('validated_at')->nullable()->after('validated_by');

            $table->index(['periode_start', 'periode_end']);
            $table->index('approval_status');
            $table->index('is_locked');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex(['periode_start', 'periode_end']);
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['is_locked']);

            $table->dropColumn([
                'approval_status',
                'submitted_by',
                'submitted_at',
                'approved_by',
                'approved_at',
                'approval_notes',
                'is_locked',
                'locked_by',
                'locked_at',
                'validation_status',
                'validation_warnings',
                'validated_by',
                'validated_at',
            ]);
        });
    }
};
