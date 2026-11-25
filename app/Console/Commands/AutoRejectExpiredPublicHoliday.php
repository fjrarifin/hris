<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PublicHolidayRequest;
use App\Notifications\PublicHolidayStatusNotification;

class AutoRejectExpiredPublicHoliday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-reject-expired-public-holiday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredRequests = PublicHolidayRequest::with(['user', 'holiday'])
            ->where('status', 'pending')
            ->get()
            ->filter(function ($request): bool {
                if (! $request->holiday?->holiday_date) {
                    return false;
                }
                $expiry = \App\Support\PublicHolidayValidity::expiresAt($request->holiday->holiday_date);

                return now()->gt($expiry) || $request->claim_date->gt($expiry);
            });

        foreach ($expiredRequests as $request) {

            $request->update([
                'status' => 'rejected',
                'reject_reason' => 'PH ditolak otomatis: masa berlaku 3 bulan berakhir atau tanggal pemakaian melewati kedaluwarsa.'
            ]);

            $request->user->notify(
                new PublicHolidayStatusNotification($request, 'expired')
            );
        }

        $this->info('Expired PH processed successfully.');
    }
}
