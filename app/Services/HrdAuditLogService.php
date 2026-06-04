<?php

namespace App\Services;

use App\Models\HrdAuditLog;
use App\Models\User;
use App\Notifications\HrdDataChangedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HrdAuditLogService
{
    private const HIDDEN_FIELDS = [
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    public function record(
        Request $request,
        string $module,
        string $action,
        string $subjectLabel,
        array|object|null $before = null,
        array|object|null $after = null,
        ?string $subjectType = null,
        string|int|null $subjectId = null,
        array $metadata = []
    ): ?HrdAuditLog {
        $beforeSnapshot = $this->snapshot($before);
        $afterSnapshot = $this->snapshot($after);
        $changes = $this->changes($beforeSnapshot, $afterSnapshot);

        if ($action === 'updated' && $changes === []) {
            return null;
        }

        $actor = $request->user();
        $log = HrdAuditLog::query()->create([
            'module' => $module,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'subject_label' => $subjectLabel,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_username' => $actor?->username,
            'changes' => $changes,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'metadata' => [
                ...$metadata,
                'ip_address' => $request->ip(),
            ],
            'occurred_at' => now(),
        ]);

        $this->notifyItUsers($log);

        return $log;
    }

    public function snapshot(array|object|null $value, array $except = []): array
    {
        if ($value === null) {
            return [];
        }

        if ($value instanceof Model) {
            $value = $value->getAttributes();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        return collect(Arr::except($value, [...$except, ...self::HIDDEN_FIELDS]))
            ->map(fn ($item) => $this->normalize($item))
            ->all();
    }

    private function changes(array $before, array $after): array
    {
        return collect(array_unique([...array_keys($before), ...array_keys($after)]))
            ->map(function (string $field) use ($before, $after): ?array {
                $old = $before[$field] ?? null;
                $new = $after[$field] ?? null;

                if ($old === $new) {
                    return null;
                }

                return [
                    'field' => $field,
                    'label' => str($field)->replace('_', ' ')->title()->toString(),
                    'old' => $old,
                    'new' => $new,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($item) => $this->normalize($item))->all();
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : get_object_vars($value);
        }

        return $value;
    }

    private function notifyItUsers(HrdAuditLog $log): void
    {
        User::query()
            ->where('level', 0)
            ->get()
            ->each(fn (User $user) => $user->notify(new HrdDataChangedNotification($log)));
    }
}
