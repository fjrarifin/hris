<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FrontendMenu;
use App\Models\FrontendMenuUserAccess;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ItUserController extends Controller
{
    private const DEFAULT_PASSWORD = '12345678';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'integer', 'in:0,1,2,3'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));
        $users = User::query()
            ->with('karyawan:nik,nama_karyawan,jabatan,posisi,departement,divisi')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('name', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->when(isset($validated['level']), fn ($query) => $query->where('level', $validated['level']))
            ->when(($validated['status'] ?? '') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? '') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('level')
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            'records' => $users->through(fn (User $user): array => $this->serializeUser($user))->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => [
                'q' => $keyword,
                'level' => $validated['level'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'level' => ['required', 'integer', 'in:0,1,2,3'],
            'is_active' => ['required', 'boolean'],
            'allow_mobile_attendance' => ['required', 'boolean'],
            'menu_ids' => ['nullable', 'array'],
            'menu_ids.*' => ['integer', 'exists:frontend_menus,id'],
        ]);

        $menuIds = collect($validated['menu_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        unset($validated['menu_ids']);

        $user = DB::transaction(function () use ($validated, $menuIds): User {
            $user = User::query()->create([
                ...$validated,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'must_change_password' => true,
                'password_changed_at' => null,
            ]);

            $menus = FrontendMenu::query()
                ->where('key', '!=', 'dashboard')
                ->get(['id']);

            foreach ($menus as $menu) {
                FrontendMenuUserAccess::query()->create([
                    'frontend_menu_id' => $menu->id,
                    'user_id' => $user->id,
                    'is_allowed' => $menuIds->contains((int) $menu->id),
                ]);
            }

            return $user;
        });

        return response()->json([
            'message' => 'User berhasil dibuat. Password default: 12345678.',
            'record' => $this->serializeUser($user->fresh('karyawan')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'level' => ['required', 'integer', 'in:0,1,2,3'],
            'is_active' => ['required', 'boolean'],
            'allow_mobile_attendance' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill($validated)->save();

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'record' => $this->serializeUser($user->fresh('karyawan')),
        ]);
    }

    public function resetPassword(User $user): JsonResponse
    {
        $user->forceFill([
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password' => true,
            'password_changed_at' => null,
        ])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password user direset ke default 12345678.']);
    }

    public function resetPhotoLimit(User $user): JsonResponse
    {
        $user->forceFill(['photo_changed_at' => null])->save();

        return response()->json(['message' => 'Batas ganti foto profil sudah direset.']);
    }

    public function resetEmailLimit(User $user): JsonResponse
    {
        $user->forceFill(['email_updated_at' => null])->save();

        return response()->json(['message' => 'Batas ubah email sudah direset.']);
    }

    public function resetPasswordLimit(User $user): JsonResponse
    {
        $user->forceFill(['password_changed_at' => null])->save();

        return response()->json(['message' => 'Batas ubah password sudah direset.']);
    }

    private function serializeUser(User $user): array
    {
        $employee = $user->karyawan;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'level' => (int) $user->level,
            'level_label' => $this->levelLabel((int) $user->level),
            'is_active' => (bool) $user->is_active,
            'allow_mobile_attendance' => (bool) $user->allow_mobile_attendance,
            'employee_name' => $employee?->nama_karyawan,
            'position' => $employee?->jabatan ?: $employee?->posisi,
            'department' => $employee?->departement ?: $employee?->divisi,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'photo_changed_at' => $user->photo_changed_at?->toIso8601String(),
            'email_updated_at' => $user->email_updated_at?->toIso8601String(),
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'must_change_password' => (bool) $user->must_change_password,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function levelLabel(int $level): string
    {
        return match ($level) {
            0 => 'IT',
            1 => 'Admin',
            2 => 'HRD',
            3 => 'Karyawan',
            default => 'User',
        };
    }
}
