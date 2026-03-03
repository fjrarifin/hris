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
            ->get();

        foreach ($expiredRequests as $request) {

            $request->update([
                'status' => 'rejected',
                'reject_reason' => 'PH expired otomatis (60 hari terlewati)'
            ]);

            $request->user->notify(
                new PublicHolidayStatusNotification($request, 'expired')
            );
        }

        $this->info('Expired PH processed successfully.');
    }
}
