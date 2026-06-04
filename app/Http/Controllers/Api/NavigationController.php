<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FrontendMenu;
use App\Models\FrontendMenuUserAccess;
use App\Models\User;
use App\Services\HrdAuditLogService;
use App\Support\FrontendNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NavigationController extends Controller
{
    public function __construct(private readonly FrontendNavigation $navigation) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->navigation->menusFor($request->user()),
        ]);
    }

    public function access(): JsonResponse
    {
        return response()->json([
            'menus' => FrontendMenu::query()
                ->with('userAccess')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (FrontendMenu $menu) => [
                    'id' => $menu->id,
                    'key' => $menu->key,
                    'label' => $menu->label,
                    'path' => $menu->path,
                    'allowed_levels' => array_map(
                        'intval',
                        array_filter(
                            array_map('trim', explode(',', (string) $menu->allowed_levels)),
                            fn (string $level) => $level !== ''
                        )
                    ),
                    'is_active' => $menu->is_active,
                    'user_access' => $menu->userAccess->map(fn (FrontendMenuUserAccess $access) => [
                        'user_id' => $access->user_id,
                        'is_allowed' => $access->is_allowed,
                    ])->values(),
                ]),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'username', 'name', 'level']),
            'levels' => [
                ['value' => 0, 'label' => 'IT Administrator'],
                ['value' => 1, 'label' => 'Administrator'],
                ['value' => 2, 'label' => 'HR'],
                ['value' => 3, 'label' => 'Karyawan'],
            ],
        ]);
    }

    public function update(Request $request, FrontendMenu $frontendMenu): JsonResponse
    {
        $validated = $request->validate([
            'allowed_levels' => ['required', 'array'],
            'allowed_levels.*' => ['integer', Rule::in([0, 1, 2, 3])],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($frontendMenu->key === 'dashboard') {
            $validated['allowed_levels'] = [0, 1, 2, 3];
            $validated['is_active'] = true;
        }

        if ($frontendMenu->key === 'menu-access') {
            $validated['allowed_levels'] = [0];
            $validated['is_active'] = true;
        }

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($frontendMenu);
        $frontendMenu->update([
            'allowed_levels' => implode(',', array_unique($validated['allowed_levels'])),
            'is_active' => $validated['is_active'],
        ]);
        app(HrdAuditLogService::class)->record(
            $request,
            'Akses Menu',
            'updated',
            $frontendMenu->label,
            $beforeAudit,
            $frontendMenu->fresh(),
            FrontendMenu::class,
            $frontendMenu->id
        );

        return response()->json([
            'message' => 'Akses menu berhasil diperbarui.',
        ]);
    }

    public function updateUserAccess(Request $request, FrontendMenu $frontendMenu, User $user): JsonResponse
    {
        abort_if(in_array($frontendMenu->key, ['dashboard', 'menu-access'], true), 422, 'Menu wajib tidak dapat dioverride.');

        $validated = $request->validate([
            'is_allowed' => ['nullable', 'boolean'],
        ]);

        $beforeAccess = FrontendMenuUserAccess::query()
            ->where('frontend_menu_id', $frontendMenu->id)
            ->where('user_id', $user->id)
            ->first();

        if (! array_key_exists('is_allowed', $validated) || $validated['is_allowed'] === null) {
            FrontendMenuUserAccess::query()
                ->where('frontend_menu_id', $frontendMenu->id)
                ->where('user_id', $user->id)
                ->delete();
        } else {
            FrontendMenuUserAccess::updateOrCreate(
                [
                    'frontend_menu_id' => $frontendMenu->id,
                    'user_id' => $user->id,
                ],
                ['is_allowed' => $validated['is_allowed']]
            );
        }
        $afterAccess = FrontendMenuUserAccess::query()
            ->where('frontend_menu_id', $frontendMenu->id)
            ->where('user_id', $user->id)
            ->first();
        app(HrdAuditLogService::class)->record(
            $request,
            'Akses Menu User',
            $afterAccess ? ($beforeAccess ? 'updated' : 'created') : 'deleted',
            "{$user->username} - {$frontendMenu->label}",
            $beforeAccess,
            $afterAccess,
            FrontendMenuUserAccess::class,
            $user->id
        );

        return response()->json([
            'message' => 'Akses khusus user berhasil diperbarui.',
        ]);
    }
}
