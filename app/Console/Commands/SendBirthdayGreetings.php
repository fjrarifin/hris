<?php

namespace App\Console\Commands;

use App\Models\Karyawan;
use App\Notifications\BirthdayGreetingNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBirthdayGreetings extends Command
{
    protected $signature = 'birthdays:send-greetings {--date= : Tanggal acuan, default hari ini (Y-m-d)}';

    protected $description = 'Kirim ucapan ulang tahun in-app dan push notification kepada karyawan yang berulang tahun.';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $employees = Karyawan::query()
            ->with('user')
            ->whereMonth('tanggal_lahir', $today->month)
            ->whereDay('tanggal_lahir', $today->day)
            ->whereRaw("UPPER(TRIM(COALESCE(status_karyawan, ''))) = ?", ['AKTIF'])
            ->whereHas('user', fn ($query) => $query
                ->where(fn ($activeQuery) => $activeQuery
                    ->whereNull('is_active')
                    ->orWhere('is_active', true)))
            ->orderBy('nama_karyawan')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $user = $employee->user;

            if (! $user || $this->greetingExists($user, $today->toDateString())) {
                $skipped++;

                continue;
            }

            $user->notify(new BirthdayGreetingNotification(
                $employee->nama_karyawan ?: $user->name,
                $today->toDateString()
            ));
            $sent++;
        }

        $this->info(
            "Ucapan ulang tahun {$today->toDateString()}: {$sent} dikirim, {$skipped} dilewati."
        );

        return self::SUCCESS;
    }

    private function greetingExists(object $user, string $date): bool
    {
        return $user->notifications()
            ->where('type', BirthdayGreetingNotification::class)
            ->where('data->date', $date)
            ->exists();
    }
}
