<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        if (! Schema::hasTable('mobile_device_tokens')) {
            return response()->json([
                'message' => 'Tabel token push mobile belum tersedia. Jalankan migration backend terlebih dahulu.',
            ], 503);
        }

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
        if (! Schema::hasTable('mobile_device_tokens')) {
            return response()->json([
                'message' => 'Tabel token push mobile belum tersedia. Jalankan migration backend terlebih dahulu.',
            ], 503);
        }

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

    public function testMobilePush(Request $request, FirebasePushService $pushService): JsonResponse
    {
        if (! Schema::hasTable('mobile_device_tokens')) {
            return response()->json([
                'message' => 'Tabel token push mobile belum tersedia. Jalankan php artisan migrate di backend production.',
            ], 503);
        }

        $tokenCount = MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->count();

        if ($tokenCount < 1) {
            return response()->json([
                'message' => 'Token push mobile belum terdaftar. Buka aplikasi Android dan izinkan notifikasi terlebih dahulu.',
            ], 422);
        }

        $sent = $pushService->sendToUser(
            (int) $request->user()->id,
            'Test Notifikasi HRIS',
            'Push notification Android berhasil terhubung.',
            [
                'title' => 'Test Notifikasi HRIS',
                'message' => 'Push notification Android berhasil terhubung.',
                'mobile_path' => '/notifications',
                'type' => 'test_push',
            ]
        );

        return response()->json([
            'message' => $sent > 0
                ? 'Test push notification sudah dikirim.'
                : 'Token ditemukan, tetapi push belum berhasil dikirim. Cek log Firebase di backend.',
            'sent' => $sent,
            'registered_tokens' => $tokenCount,
        ]);
    }
}
