<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItPushNotification;
use App\Models\MobileDeviceToken;
use App\Models\User;
use App\Notifications\ItBroadcastNotification;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class ItPushNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $records = ItPushNotification::query()
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 10), ['*'], 'page', (int) ($validated['page'] ?? 1));

        return response()->json([
            'records' => $records->through(fn (ItPushNotification $record): array => $this->serializeLog($record))->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
        ]);
    }

    public function recipients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));
        $users = $this->recipientQuery()
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('name', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhereHas('karyawan', function ($employeeQuery) use ($keyword): void {
                            $employeeQuery->where('nama_karyawan', 'like', "%{$keyword}%")
                                ->orWhere('nik', 'like', "%{$keyword}%")
                                ->orWhere('departement', 'like', "%{$keyword}%")
                                ->orWhere('divisi', 'like', "%{$keyword}%")
                                ->orWhere('unit', 'like', "%{$keyword}%");
                        });
                });
            })
            ->withCount('mobileDeviceTokens')
            ->paginate((int) ($validated['per_page'] ?? 30), ['*'], 'page', (int) ($validated['page'] ?? 1));

        return response()->json([
            'records' => $users->through(fn (User $user): array => $this->serializeRecipient($user))->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'summary' => $this->summary(),
        ]);
    }

    public function store(Request $request, FirebasePushService $pushService): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'audience' => ['required', Rule::in(['all_employees', 'selected'])],
            'user_ids' => ['required_if:audience,selected', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'mobile_path' => ['nullable', 'string', 'max:120'],
            'send_push' => ['nullable', 'boolean'],
        ]);

        $mobilePath = $this->normalizeMobilePath($validated['mobile_path'] ?? '/notifications');
        $recipients = $this->resolveRecipients($validated['audience'], $validated['user_ids'] ?? []);

        abort_if($recipients->isEmpty(), 422, 'Tidak ada penerima aktif untuk target yang dipilih.');

        $tokenCount = MobileDeviceToken::query()
            ->whereIn('user_id', $recipients->pluck('id'))
            ->count();

        $log = ItPushNotification::query()->create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'audience' => $validated['audience'],
            'target_user_ids' => $validated['audience'] === 'selected'
                ? $recipients->pluck('id')->values()->all()
                : null,
            'mobile_path' => $mobilePath,
            'sent_by' => $request->user()?->id,
            'sent_by_name' => $request->user()?->name,
            'recipient_count' => $recipients->count(),
            'token_count' => $tokenCount,
            'database_sent_count' => 0,
            'push_sent_count' => 0,
            'metadata' => [
                'requested_user_ids' => $validated['user_ids'] ?? [],
                'send_push' => (bool) ($validated['send_push'] ?? true),
            ],
        ]);

        Notification::send($recipients, new ItBroadcastNotification(
            $log->id,
            $validated['title'],
            $validated['message'],
            $mobilePath
        ));

        $pushSent = 0;
        if ((bool) ($validated['send_push'] ?? true)) {
            foreach ($recipients as $recipient) {
                $pushSent += $pushService->sendToUser((int) $recipient->id, $validated['title'], $validated['message'], [
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'type' => 'it_broadcast',
                    'broadcast_id' => $log->id,
                    'mobile_path' => $mobilePath,
                ]);
            }
        }

        $log->forceFill([
            'database_sent_count' => $recipients->count(),
            'push_sent_count' => $pushSent,
        ])->save();

        return response()->json([
            'message' => 'Notifikasi berhasil diproses.',
            'record' => $this->serializeLog($log->fresh()),
        ], 201);
    }

    private function recipientQuery()
    {
        return User::query()
            ->with('karyawan:nik,nama_karyawan,jabatan,posisi,departement,divisi,unit,status_karyawan')
            ->where('is_active', true)
            ->orderBy('name');
    }

    private function allEmployeeQuery()
    {
        return $this->recipientQuery()
            ->where('level', 3)
            ->whereHas('karyawan', fn ($query) => $query->where('status_karyawan', 'AKTIF'));
    }

    private function resolveRecipients(string $audience, array $userIds)
    {
        if ($audience === 'all_employees') {
            return $this->allEmployeeQuery()->get();
        }

        return $this->recipientQuery()
            ->whereIn('id', collect($userIds)->map(fn ($id) => (int) $id)->unique()->values())
            ->get();
    }

    private function summary(): array
    {
        $activeEmployeeIds = $this->allEmployeeQuery()->pluck('id');

        return [
            'active_employee_count' => $activeEmployeeIds->count(),
            'active_employee_token_count' => MobileDeviceToken::query()
                ->whereIn('user_id', $activeEmployeeIds)
                ->count(),
            'active_user_count' => $this->recipientQuery()->count(),
            'active_user_token_count' => MobileDeviceToken::query()
                ->whereIn('user_id', $this->recipientQuery()->pluck('id'))
                ->count(),
        ];
    }

    private function serializeRecipient(User $user): array
    {
        $employee = $user->karyawan;

        return [
            'id' => $user->id,
            'name' => $employee?->nama_karyawan ?: $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'level' => (int) $user->level,
            'level_label' => match ((int) $user->level) {
                0 => 'IT',
                1 => 'Admin',
                2 => 'HRD',
                default => 'Karyawan',
            },
            'position' => $employee?->jabatan ?: $employee?->posisi,
            'department' => $employee?->departement ?: $employee?->divisi,
            'unit' => $employee?->unit,
            'employee_status' => $employee?->status_karyawan,
            'mobile_token_count' => (int) ($user->mobile_device_tokens_count ?? 0),
        ];
    }

    private function serializeLog(ItPushNotification $record): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'message' => $record->message,
            'audience' => $record->audience,
            'audience_label' => $record->audience === 'all_employees' ? 'Semua karyawan aktif' : 'User terpilih',
            'target_user_ids' => $record->target_user_ids ?: [],
            'mobile_path' => $record->mobile_path,
            'sent_by_name' => $record->sent_by_name,
            'recipient_count' => $record->recipient_count,
            'token_count' => $record->token_count,
            'database_sent_count' => $record->database_sent_count,
            'push_sent_count' => $record->push_sent_count,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    private function normalizeMobilePath(string $path): string
    {
        $path = trim($path) ?: '/notifications';

        if (preg_match('/^https?:\/\//i', $path)) {
            $parts = parse_url($path);
            $path = ($parts['path'] ?? '/notifications')
                . (isset($parts['query']) ? '?'.$parts['query'] : '')
                . (isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }

        return str_starts_with($path, '/') ? $path : "/{$path}";
    }
}
