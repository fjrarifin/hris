<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $visibleNotifications = $request->user()
            ->notifications()
            ->where(function ($query): void {
                $query->whereNull('data->type')
                    ->orWhere('data->type', '!=', 'attendance_minimum');
            });

        $notifications = (clone $visibleNotifications)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notifikasi',
                'message' => $notification->data['message'] ?? '',
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'unread_count' => (clone $visibleNotifications)->whereNull('read_at')->count(),
            'records' => $notifications,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Notifikasi sudah dibaca.']);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return response()->json(['message' => 'Notifikasi sudah dibaca.']);
    }

    public function registerMobileToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:30'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        MobileDeviceToken::query()
            ->where('token', $data['token'])
            ->where('platform', $data['platform'] ?? 'android')
            ->where('user_id', '!=', $request->user()->id)
            ->delete();

        MobileDeviceToken::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'token' => $data['token'],
            ],
            [
                'device_name' => $data['device_name'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token notifikasi berhasil disimpan.']);
    }

    public function unregisterMobileToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:30'],
        ]);

        MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('platform', $data['platform'] ?? 'android')
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Token notifikasi berhasil dihapus.']);
    }
}
