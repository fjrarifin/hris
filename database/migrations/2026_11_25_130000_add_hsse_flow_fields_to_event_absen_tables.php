<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_absen')) {
            Schema::table('event_absen', function (Blueprint $table) {
                if (! Schema::hasColumn('event_absen', 'lokasi')) {
                    $table->string('lokasi', 255)->nullable()->after('deskripsi');
                }
                if (! Schema::hasColumn('event_absen', 'reminder_enabled')) {
                    $table->boolean('reminder_enabled')->default(false)->after('status');
                }
                if (! Schema::hasColumn('event_absen', 'reminder_minutes_before')) {
                    $table->unsignedInteger('reminder_minutes_before')->nullable()->default(60)->after('reminder_enabled');
                }
                if (! Schema::hasColumn('event_absen', 'reminder_sent_at')) {
                    $table->timestamp('reminder_sent_at')->nullable()->after('reminder_minutes_before');
                }
            });
        }

        if (! Schema::hasTable('event_absen_participants')) {
            Schema::create('event_absen_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_event_absen')->constrained('event_absen')->cascadeOnDelete();
                $table->string('nik_karyawan');
                $table->timestamp('initial_notified_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->timestamps();

                $table->unique(['id_event_absen', 'nik_karyawan'], 'event_absen_participants_unique');
                $table->index('nik_karyawan');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_absen_participants');

        if (Schema::hasTable('event_absen')) {
            Schema::table('event_absen', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn('event_absen', 'lokasi') ? 'lokasi' : null,
                    Schema::hasColumn('event_absen', 'reminder_enabled') ? 'reminder_enabled' : null,
                    Schema::hasColumn('event_absen', 'reminder_minutes_before') ? 'reminder_minutes_before' : null,
                    Schema::hasColumn('event_absen', 'reminder_sent_at') ? 'reminder_sent_at' : null,
                ]));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
