<?php

namespace App\Http\Services;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Str;

class ApprovalRoutingService
{
    public function __construct(private readonly ApprovalNotificationService $approvalNotification) {}

    public function requiresDirectHrApproval(User $user): bool
    {
        $employee = Karyawan::query()
            ->where('nik', $user->username)
            ->first();

        if (! $employee) {
            return false;
        }

        $positionTitle = strtolower(trim((string) ($employee->posisi_title ?: $employee->jabatan ?: $employee->posisi)));

        return in_array($positionTitle, ['manager', 'gm', 'general manager'], true);
    }

    public function initialApprovalFields(bool $directToHr): array
    {
        return $directToHr
            ? [
                'manager_approved_at' => now(),
                'manager_approved_by' => null,
                'approval_token' => null,
                'approval_token_expires_at' => null,
            ]
            : [
                'approval_token' => (string) Str::uuid(),
                'approval_token_expires_at' => now()->addHours(config('services.public_approval.expires_hours')),
            ];
    }

    public function notifyInitialApprover(object $request, string $type, bool $directToHr): void
    {
        if ($directToHr) {
            $this->approvalNotification->notifyHrGroups($request, $type);

            return;
        }

        $this->approvalNotification->notifyManager($request, $type);
    }
}
