<?php

namespace App\Console\Commands;

use App\Http\Services\ApprovalNotificationService;
use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendApprovalReminderNotifications extends Command
{
    protected $signature = 'approval:send-reminders {--slot=1 : Reminder slot number, 1 or 2}';

    protected $description = 'Send reminder notifications to direct managers for pending staff approvals.';

    public function handle(ApprovalNotificationService $approvalNotification): int
    {
        $slot = max(1, min(2, (int) $this->option('slot')));
        $sent = 0;

        $this->pendingRequests()->each(function (array $item) use ($approvalNotification, $slot, &$sent): void {
            if ($approvalNotification->notifyManagerReminder($item['request'], $item['type'], $slot)) {
                $sent++;
            }
        });

        $this->info("Approval reminder slot {$slot} sent to {$sent} manager(s).");

        return self::SUCCESS;
    }

    private function pendingRequests(): Collection
    {
        return collect()
            ->concat(
                LeaveRequest::query()
                    ->with('user.karyawan')
                    ->where('status', 'pending')
                    ->whereNull('manager_approved_at')
                    ->whereNotNull('approval_token')
                    ->where('approval_token_expires_at', '>', now())
                    ->get()
                    ->map(fn (LeaveRequest $request): array => ['type' => 'CUTI', 'request' => $request])
            )
            ->concat(
                PublicHolidayRequest::query()
                    ->with(['user.karyawan', 'holiday'])
                    ->where('status', 'pending')
                    ->whereNull('manager_approved_at')
                    ->whereNotNull('approval_token')
                    ->where('approval_token_expires_at', '>', now())
                    ->get()
                    ->map(fn (PublicHolidayRequest $request): array => ['type' => 'PH', 'request' => $request])
            )
            ->concat(
                ExtraOffRequest::query()
                    ->with('user.karyawan')
                    ->where('status', 'pending')
                    ->whereNull('manager_approved_at')
                    ->whereNotNull('approval_token')
                    ->where('approval_token_expires_at', '>', now())
                    ->get()
                    ->map(fn (ExtraOffRequest $request): array => ['type' => 'EO', 'request' => $request])
            )
            ->concat(
                EmployeePermission::query()
                    ->with('user.karyawan')
                    ->where('status', 'pending')
                    ->whereNull('manager_approved_at')
                    ->whereNotNull('approval_token')
                    ->where('approval_token_expires_at', '>', now())
                    ->get()
                    ->map(fn (EmployeePermission $request): array => [
                        'type' => strtoupper($request->type),
                        'request' => $request,
                    ])
            )
            ->sortBy(fn (array $item) => $item['request']->created_at)
            ->values();
    }
}
