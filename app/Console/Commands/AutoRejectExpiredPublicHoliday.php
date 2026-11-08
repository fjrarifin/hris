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
        $expiredRequests = PublicHolidayRequest::with('user')
            ->where('status', 'pending')
            ->where('expired_at', '<', now())
            // Pengajuan yang masuk sebelum expired boleh punya tanggal claim setelah expired,
            // jadi baru di-reject otomatis jika tanggal claim-nya juga sudah lewat.
            ->whereDate('claim_date', '<', now()->toDateString())
            ->get();

        foreach ($expiredRequests as $request) {

            $request->update([
                'status' => 'rejected',
                'reject_reason' => 'PH expired otomatis (90 hari terlewati)'
            ]);

            $request->user->notify(
                new PublicHolidayStatusNotification($request, 'expired')
            );
        }

        $this->info('Expired PH processed successfully.');
    }
}
