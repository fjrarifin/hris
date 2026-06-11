<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileAppReleaseController extends Controller
{
    public function latest(): JsonResponse
    {
        $release = MobileAppRelease::query()
            ->where('is_active', true)
            ->latest('version_code')
            ->first();

        return response()->json([
            'release' => $release ? $this->serialize($release) : null,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $releases = MobileAppRelease::query()
            ->with('uploader:id,name,username')
            ->latest('version_code')
            ->paginate(10, ['*'], 'page', (int) ($validated['page'] ?? 1));

        return response()->json([
            'records' => $releases->through(fn (MobileAppRelease $release): array => $this->serialize($release, true))->items(),
            'pagination' => [
                'current_page' => $releases->currentPage(),
                'last_page' => $releases->lastPage(),
                'per_page' => $releases->perPage(),
                'total' => $releases->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version_code' => ['required', 'integer', 'min:1', Rule::unique('mobile_app_releases', 'version_code')],
            'version_name' => ['required', 'string', 'max:50'],
            'apk' => ['required', 'file', 'max:204800'],
            'mandatory' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('apk');
        if (! $file || strtolower($file->getClientOriginalExtension()) !== 'apk') {
            throw ValidationException::withMessages([
                'apk' => ['File harus berformat APK.'],
            ]);
        }

        $fileName = sprintf(
            'hris-mobile-%s-%s.apk',
            preg_replace('/[^A-Za-z0-9._-]/', '-', $validated['version_name']),
            $validated['version_code']
        );
        $path = $file->storeAs('mobile-app-releases', $fileName, 'local');
        $absolutePath = Storage::disk('local')->path($path);

        $release = MobileAppRelease::query()->create([
            'version_code' => (int) $validated['version_code'],
            'version_name' => $validated['version_name'],
            'file_path' => $path,
            'file_name' => $fileName,
            'file_size' => filesize($absolutePath),
            'sha256' => hash_file('sha256', $absolutePath),
            'mandatory' => (bool) ($validated['mandatory'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'notes' => $validated['notes'] ?? null,
            'uploaded_by' => $request->user()?->id,
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Release aplikasi mobile berhasil diupload.',
            'data' => $this->serialize($release, true),
        ], 201);
    }

    public function download(MobileAppRelease $release): StreamedResponse
    {
        abort_unless($release->is_active && Storage::disk('local')->exists($release->file_path), 404);

        return Storage::disk('local')->download($release->file_path, $release->file_name, [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    private function serialize(MobileAppRelease $release, bool $includeAdmin = false): array
    {
        return [
            'id' => $release->id,
            'version_code' => $release->version_code,
            'version_name' => $release->version_name,
            'apk_url' => $this->apkUrl($release),
            'sha256' => $release->sha256,
            'file_size' => $release->file_size,
            'mandatory' => $release->mandatory,
            'notes' => $release->notes,
            'published_at' => $release->published_at?->toIso8601String(),
            ...($includeAdmin ? [
                'is_active' => $release->is_active,
                'uploaded_by' => $release->uploader?->name,
            ] : []),
        ];
    }

    private function apkUrl(MobileAppRelease $release): string
    {
        return request()->getSchemeAndHttpHost() . '/mobile-app-releases/' . rawurlencode($release->file_name);
    }
}
