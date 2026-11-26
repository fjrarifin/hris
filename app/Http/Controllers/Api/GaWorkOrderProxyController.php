<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GaWorkOrderWhatsAppNotifier;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Authenticated BFF proxy between HRIS employees and GA EMS Work Order API.
 * Enforces allow_work_order toggle, master_units area allowlist, manager-level approver filter,
 * and mandatory photo + document attachments.
 */
class GaWorkOrderProxyController extends Controller
{
    private const ALLOWED_APPROVER_TITLES = [
        'supervisor',
        'spv',
        'asst. manager',
        'asst manager',
        'assistant manager',
        'manager',
        'general manager',
        'gm',
    ];

    public function units(Request $request): JsonResponse
    {
        $this->assertCanAccess($request, true);

        $units = Cache::remember('hris:ga_wo_master_units', 300, function () {
            return DB::table('master_units')
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();
        });

        return response()->json(['data' => $units]);
    }

    public function approvers(Request $request): JsonResponse
    {
        $this->assertCanAccess($request, true);

        $meId = (int) $request->user()->id;
        $search = trim((string) $request->query('search', ''));

        $query = DB::table('users as u')
            ->join('m_karyawan as k', 'k.nik', '=', 'u.username')
            ->where('u.is_active', true)
            ->where('u.id', '!=', $meId)
            ->whereIn(DB::raw('LOWER(TRIM(k.posisi_title))'), self::ALLOWED_APPROVER_TITLES)
            ->select('u.id', 'u.username', 'u.name', 'u.email', 'k.jabatan', 'k.posisi_title')
            ->orderBy('u.name');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('u.name', 'like', $like)
                    ->orWhere('u.username', 'like', $like)
                    ->orWhere('k.jabatan', 'like', $like);
            });
        }

        $rows = $query->limit(150)->get()->map(fn ($u) => [
            'hris_user_id' => (int) $u->id,
            'nik' => strtoupper(trim((string) $u->username)),
            'name' => trim((string) $u->name),
            'email' => $u->email ? strtolower(trim((string) $u->email)) : null,
            'jabatan' => $u->jabatan ?: $u->posisi_title,
            'posisi_title' => $u->posisi_title,
        ])->values();

        return response()->json(['data' => $rows]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertCanAccess($request);
        $query = $request->only(['page', 'per_page', 'status', 'search', 'scope']);

        return $this->forward($request, 'GET', '/api/internal/hris/work-orders', $query);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertCanAccess($request);

        return $this->forward($request, 'GET', "/api/internal/hris/work-orders/{$id}", [
            'division' => (string) $request->query('division', 'MEP'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanAccess($request, true);

        $data = $request->validate([
            'division' => 'required|string|in:MEP,IT',
            'job_type' => 'required|string|in:WORK ORDER,PROJECT,LAIN - LAIN',
            'area_name' => ['required', 'string', 'max:255', Rule::exists('master_units', 'name')->where('is_active', true)],
            'project_description' => 'required|string|max:5000',
            'target_date' => 'nullable|date_format:Y-m-d',
            'notes' => 'nullable|string|max:5000',
            'photos' => 'required|array|min:1|max:3',
            'photos.*' => 'required|string|max:3000000',
            'approver_ids' => 'required|array|min:1|max:20',
            'approver_ids.*' => 'integer|distinct|exists:users,id',
        ], [
            'area_name.exists' => 'Area / Lokasi harus dipilih dari daftar Master Units yang aktif.',
            'photos.required' => 'Lampiran foto minimal 1 wajib diunggah.',
            'photos.min' => 'Lampiran foto minimal 1 wajib diunggah.',
            'photos.max' => 'Lampiran foto maksimal 3 foto.',
        ]);

        $meId = (int) $request->user()->id;
        $approvers = DB::table('users as u')
            ->join('m_karyawan as k', 'k.nik', '=', 'u.username')
            ->whereIn('u.id', $data['approver_ids'])
            ->where('u.id', '!=', $meId)
            ->where('u.is_active', true)
            ->whereIn(DB::raw('LOWER(TRIM(k.posisi_title))'), self::ALLOWED_APPROVER_TITLES)
            ->get(['u.id', 'u.username', 'u.name', 'u.email'])
            ->map(fn ($u) => [
                'hris_user_id' => (int) $u->id,
                'nik' => strtoupper(trim((string) ($u->username ?: 'EMP'.$u->id))),
                'name' => trim((string) $u->name),
                'email' => $u->email ? strtolower(trim((string) $u->email)) : null,
            ])
            ->values()
            ->all();

        if (count($approvers) === 0) {
            return response()->json([
                'message' => 'Pilih minimal 1 approver aktif dengan level Supervisor, Asst. Manager, Manager, atau General Manager.',
            ], 422);
        }

        $response = $this->forward($request, 'POST', '/api/internal/hris/work-orders', [
            'division' => $data['division'],
            'job_type' => $data['job_type'],
            'area_name' => $data['area_name'],
            'project_description' => $data['project_description'],
            'target_date' => $data['target_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'photos' => array_values($data['photos']),
            'approvers' => $approvers,
        ]);

        if ($response->getStatusCode() === 201) {
            $payload = $response->getData(true);
            app(GaWorkOrderWhatsAppNotifier::class)->notifyApproversOnCreated(
                $approvers,
                [
                    'id' => $payload['id'] ?? '-',
                    'division' => $data['division'],
                    'area_name' => $data['area_name'],
                    'job_type' => $data['job_type'],
                    'project_description' => $data['project_description'],
                    'target_date' => $data['target_date'] ?? null,
                ],
                $request->user(),
                is_array($payload['approval_tokens'] ?? null) ? $payload['approval_tokens'] : []
            );
        }

        return $response;
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertCanAccess($request);

        return $this->forward($request, 'DELETE', "/api/internal/hris/work-orders/{$id}", [
            'division' => (string) $request->query('division', 'MEP'),
        ]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $this->assertCanAccess($request);

        $data = $request->validate([
            'decision' => 'required|in:done,revision',
            'notes' => 'nullable|string|max:5000',
            'photos' => 'nullable|array|min:1|max:3',
            'photos.*' => 'required|string|max:3000000',
        ], [
            'photos.min' => 'Lampiran foto revisi minimal 1 wajib diunggah.',
            'photos.max' => 'Lampiran foto revisi maksimal 3 foto.',
        ]);

        $data['division'] = (string) $request->query('division', 'MEP');

        return $this->forward($request, 'POST', "/api/internal/hris/work-orders/{$id}/review", $data);
    }

    public function notifyReview(Request $request): JsonResponse
    {
        $expected = (string) config('services.ga_ems.secret', '');
        $provided = (string) $request->header('X-GA-Service-Secret', '');
        abort_unless($expected !== '' && hash_equals($expected, $provided), 403, 'Kredensial layanan internal tidak sah.');

        $data = $request->validate([
            'work_order_id' => 'required|integer|min:1',
            'work_order_no' => 'nullable',
            'area_name' => 'required|string|max:255',
            'project_description' => 'nullable|string|max:5000',
            'requester_nik' => 'required|string|max:100',
            'requester_name' => 'nullable|string|max:255',
            'technician_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        app(GaWorkOrderWhatsAppNotifier::class)->notifyRequesterForReview($data);

        return response()->json(['message' => 'Notifikasi review dikirim.']);
    }

    public function notifyRab(Request $request): JsonResponse
    {
        $expected = (string) config('services.ga_ems.secret', '');
        $provided = (string) $request->header('X-GA-Service-Secret', '');
        abort_unless($expected !== '' && hash_equals($expected, $provided), 403, 'Kredensial layanan internal tidak sah.');

        $data = $request->validate([
            'work_order_id' => 'required|integer|min:1',
            'work_order_no' => 'nullable',
            'area_name' => 'required|string|max:255',
            'project_description' => 'nullable|string|max:5000',
            'rab_total' => 'required|numeric|min:0',
            'item_count' => 'required|integer|min:1',
            'rab_notes' => 'nullable|string|max:5000',
            'submitted_by' => 'nullable|string|max:255',
            'approvers' => 'required|array|min:1|max:30',
            'approvers.*.nik' => 'required|string|max:100',
            'approvers.*.name' => 'required|string|max:255',
            'approvers.*.token' => 'required|string|min:10|max:64',
            'approvers.*.is_requester' => 'nullable|boolean',
        ]);

        app(GaWorkOrderWhatsAppNotifier::class)->notifyApproversOnRabSubmitted($data);

        return response()->json(['message' => 'Notifikasi persetujuan RAB dikirim.']);
    }

    public function resendApproval(Request $request): JsonResponse
    {
        $expected = (string) config('services.ga_ems.secret', '');
        $provided = (string) $request->header('X-GA-Service-Secret', '');
        abort_unless($expected !== '' && hash_equals($expected, $provided), 403, 'Kredensial layanan internal tidak sah.');

        $data = $request->validate([
            'work_order_id' => 'required|integer|min:1',
            'work_order_no' => 'nullable',
            'stage' => 'required|string|max:30',
            'area_name' => 'required|string|max:255',
            'job_type' => 'nullable|string|max:100',
            'project_description' => 'nullable|string|max:5000',
            'target_date' => 'nullable|string|max:40',
            'requester_name' => 'nullable|string|max:255',
            'requester_nik' => 'nullable|string|max:100',
            'rab_total' => 'nullable|numeric|min:0',
            'item_count' => 'nullable|integer|min:0',
            'rab_notes' => 'nullable|string|max:5000',
            'vendor_name' => 'nullable|string|max:255',
            'sph_amount' => 'nullable|numeric|min:0',
            'approver_nik' => 'required|string|max:100',
            'approver_name' => 'required|string|max:255',
            'token' => 'required|string|min:10|max:64',
        ]);

        $result = app(GaWorkOrderWhatsAppNotifier::class)->resendApprovalToApprover($data);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Gagal mengirim ulang notifikasi approval.',
            ], 422);
        }

        return response()->json([
            'message' => $result['message'] ?? 'Notifikasi approval berhasil dikirim ulang.',
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $this->assertCanAccess($request);

        $data = $request->validate([
            'stage' => ['required', 'string', 'regex:/^(initial|rab|rab_[1-5]|sph_[1-5])$/'],
            'decision' => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:2000',
        ]);
        $data['division'] = (string) $request->query('division', 'MEP');

        $response = $this->forward($request, 'POST', "/api/internal/hris/work-orders/{$id}/approve", $data);

        if ($response->getStatusCode() === 200) {
            $detailResp = $this->forward($request, 'GET', "/api/internal/hris/work-orders/{$id}", [
                'division' => $data['division'],
            ]);
            if ($detailResp->getStatusCode() === 200) {
                $detailData = $detailResp->getData(true);
                $approveData = $response->getData(true);
                app(GaWorkOrderWhatsAppNotifier::class)->notifyRequesterOnDecision(
                    is_array($detailData) ? $detailData : [],
                    $request->user(),
                    $data['stage'],
                    $data['decision'],
                    $data['comment'] ?? null,
                    $approveData['status'] ?? null
                );
            }
        }

        return $response;
    }

    public function attachment(Request $request, int $id, string $type): SymfonyResponse
    {
        $this->assertCanAccess($request);
        abort_unless(in_array($type, ['photo', 'document', 'spl', 'completion', 'revision', 'sph'], true), 404, 'Tipe lampiran tidak valid.');

        $baseUrl = rtrim((string) config('services.ga_ems.url', ''), '/');
        $secret = (string) config('services.ga_ems.secret', '');
        $timeout = max(4, (int) config('services.ga_ems.timeout', 8));

        if ($baseUrl === '' || $secret === '') {
            return response()->json(['message' => 'Integrasi Work Order GA belum dikonfigurasi.'], 503);
        }

        $user = $request->user();
        $nik = strtoupper(trim((string) ($user->username ?: $user->nik ?: 'EMP'.$user->id)));

        $index = max(0, (int) $request->query('index', 0));
        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)->withHeaders([
                'X-GA-Service-Secret' => $secret,
                'X-HRIS-User-Id' => (string) $user->id,
                'X-HRIS-User-Nik' => $nik,
                'X-HRIS-User-Name' => trim((string) $user->name),
                'X-HRIS-User-Email' => strtolower(trim((string) ($user->email ?? ''))),
            ])->get("{$baseUrl}/api/internal/hris/work-orders/{$id}/attachments/{$type}", [
            'index' => $index,
            'division' => (string) $request->query('division', 'MEP'),
        ]);
        } catch (\Throwable) {
            return response()->json(['message' => 'Gagal mengambil lampiran dari layanan GA.'], 503);
        }

        if (! $response->successful()) {
            $json = $response->json();

            return response()->json(
                is_array($json) ? $json : ['message' => 'Lampiran tidak ditemukan.'],
                $response->status()
            );
        }

        $contentType = $response->header('Content-Type') ?: 'application/octet-stream';
        $disposition = $response->header('Content-Disposition') ?: 'inline';

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function assertCanAccess(Request $request, bool $forCreate = false): void
    {
        $user = $request->user();
        $hasDirectAccess = $user && ((bool) $user->allow_work_order || (int) $user->level === 0);
        if ($hasDirectAccess) {
            return;
        }

        if (! $forCreate && $user && $this->isWorkOrderApprover($user)) {
            return;
        }

        abort(403, 'Akses menu Work Order belum diaktifkan oleh IT Admin.');
    }

    private function isWorkOrderApprover(object $user): bool
    {
        $nik = trim((string) ($user->username ?? ''));
        if ($nik === '') {
            return false;
        }

        $title = DB::table('m_karyawan')->where('nik', $nik)->value('posisi_title');
        if (! $title) {
            return false;
        }

        return in_array(strtolower(trim((string) $title)), self::ALLOWED_APPROVER_TITLES, true);
    }

    private function forward(Request $request, string $method, string $path, array $payload = []): JsonResponse
    {
        $baseUrl = rtrim((string) config('services.ga_ems.url', ''), '/');
        $secret = (string) config('services.ga_ems.secret', '');
        $timeout = max(2, (int) config('services.ga_ems.timeout', 8));

        if ($baseUrl === '' || $secret === '') {
            return response()->json(['message' => 'Integrasi Work Order GA belum dikonfigurasi.'], 503);
        }

        $user = $request->user();
        $nik = strtoupper(trim((string) ($user->username ?: $user->nik ?: 'EMP'.$user->id)));
        $headers = [
            'Accept' => 'application/json',
            'X-GA-Service-Secret' => $secret,
            'X-HRIS-User-Id' => (string) $user->id,
            'X-HRIS-User-Nik' => $nik,
            'X-HRIS-User-Name' => trim((string) $user->name),
            'X-HRIS-User-Email' => strtolower(trim((string) ($user->email ?? ''))),
        ];

        try {
            $client = Http::timeout($timeout)->withHeaders($headers);
            $upper = strtoupper($method);
            /** @var Response $response */
            $response = match ($upper) {
                'GET' => $client->get($baseUrl.$path, $payload),
                'DELETE' => $client->delete($baseUrl.$path, $payload),
                default => $client->post($baseUrl.$path, $payload),
            };
        } catch (\Throwable) {
            return response()->json(['message' => 'Layanan Work Order GA sedang tidak tersedia. Coba kembali beberapa saat lagi.'], 503);
        }

        $json = $response->json();

        return response()->json(is_array($json) ? $json : ['message' => 'Respons layanan GA tidak valid.'], $response->status());
    }
}
