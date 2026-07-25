<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FingerspotUserTemplate;
use App\Models\FingerspotWebhookLog;
use App\Models\Karyawan;
use App\Services\FingerspotAttendanceService;
use App\Services\FingerspotUserinfoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItFingerspotController extends Controller
{
    public function __construct(
        private readonly FingerspotUserinfoService $userinfoService,
        private readonly FingerspotAttendanceService $attendanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $department = trim((string) $request->query('department', ''));
        $hasTemplate = $request->query('has_template', 'all');

        $clouds = collect($this->userinfoService->clouds());
        $storedTemplates = FingerspotUserTemplate::all()->keyBy('pin');

        $employeesQuery = Karyawan::query()
            ->whereNotNull('pin')
            ->where('pin', '!=', '')
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where('nama_karyawan', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            })
            ->when($department !== '', function (Builder $q) use ($department) {
                $q->where('departement', $department)
                  ->orWhere('divisi', $department);
            });

        $employees = $employeesQuery->get()->map(function (Karyawan $emp) use ($storedTemplates) {
            $pin = (string) $emp->pin;
            $tpl = $storedTemplates->get($pin);

            return [
                'nik' => $emp->nik,
                'pin' => $pin,
                'name' => $emp->nama_karyawan,
                'department' => $emp->departement ?? $emp->divisi ?? '-',
                'position' => $emp->jabatan ?? '-',
                'has_template' => $tpl && ! empty($tpl->template),
                'card' => $tpl?->card,
                'source_cloud_id' => $tpl?->cloud_id ?? '-',
                'last_pulled_at' => $tpl?->last_pulled_at?->format('d/m/Y H:i') ?? '-',
            ];
        });

        if ($hasTemplate === 'yes') {
            $employees = $employees->where('has_template', true)->values();
        } elseif ($hasTemplate === 'no') {
            $employees = $employees->where('has_template', false)->values();
        }

        $allKaryawanCount = Karyawan::whereNotNull('pin')->where('pin', '!=', '')->count();
        $totalSavedTemplates = FingerspotUserTemplate::whereNotNull('template')->where('template', '!=', '')->count();
        $totalCards = FingerspotUserTemplate::whereNotNull('card')->where('card', '!=', '')->count();

        $recentWebhooks = FingerspotWebhookLog::query()
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($log) {
                $raw = $log->raw_payload;
                $payload = is_array($raw) ? $raw : json_decode((string) $raw, true);

                return [
                    'id' => $log->id,
                    'ip_address' => $log->ip_address,
                    'type' => $payload['type'] ?? 'webhook',
                    'cloud_id' => $payload['cloud_id'] ?? '-',
                    'received_at' => $log->created_at?->format('d/m/Y H:i:s') ?? '-',
                    'summary' => isset($payload['data']['name'])
                        ? "{$payload['data']['name']} (PIN: {$payload['data']['pin']})"
                        : (isset($payload['data']['scan']) ? "Attlog PIN: {$payload['data']['pin']} @ {$payload['data']['scan']}" : 'Callback Data Received'),
                ];
            });

        return response()->json([
            'clouds' => $clouds,
            'summary' => [
                'total_machines' => $clouds->count(),
                'total_employees' => $allKaryawanCount,
                'total_templates_saved' => $totalSavedTemplates,
                'total_cards_saved' => $totalCards,
            ],
            'employees' => $employees->values()->all(),
            'webhook_logs' => $recentWebhooks,
        ]);
    }

    public function pullAll(Request $request): JsonResponse
    {
        $cloudId = $request->input('cloud_id');
        $result = $this->userinfoService->pullAllBiometrics($cloudId ?: null);

        return response()->json([
            'message' => "Berhasil mengirim {$result['success_count']} perintah tarik biometrik ke mesin.",
            'result' => $result,
        ]);
    }

    public function sendAll(Request $request): JsonResponse
    {
        $cloudId = $request->input('cloud_id');
        $result = $this->userinfoService->sendAllEmployees($cloudId ?: null);

        return response()->json([
            'message' => "Berhasil mengirim data profile & biometrik ke {$result['success_count']} target mesin.",
            'result' => $result,
        ]);
    }

    public function pullEmployee(Request $request, string $nik): JsonResponse
    {
        $employee = Karyawan::where('nik', $nik)->firstOrFail();
        $cloudId = $request->input('cloud_id');
        $clouds = $this->userinfoService->clouds();
        $targetCloudId = $cloudId ?: ($clouds[0]['id'] ?? null);

        if (! $targetCloudId) {
            return response()->json(['message' => 'Tidak ada mesin absensi yang terkonfigurasi.'], 422);
        }

        $result = $this->userinfoService->pullEmployeeUserinfo($employee, $targetCloudId);

        return response()->json([
            'message' => "Perintah tarik data/biometrik untuk {$employee->nama_karyawan} berhasil dikirim ke mesin.",
            'data' => $result,
        ]);
    }

    public function sendEmployee(Request $request, string $nik): JsonResponse
    {
        $employee = Karyawan::where('nik', $nik)->firstOrFail();
        $cloudId = $request->input('cloud_id');
        $clouds = $this->userinfoService->clouds();

        $targetClouds = $cloudId
            ? collect($clouds)->where('id', $cloudId)->values()
            : collect($clouds);

        $results = [];
        foreach ($targetClouds as $c) {
            $results[] = $this->userinfoService->sendEmployee($employee, $c['id']);
        }

        return response()->json([
            'message' => "Data profil & biometrik {$employee->nama_karyawan} berhasil dikirim ke mesin.",
            'data' => $results,
        ]);
    }

    public function pullAttlog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cloud_id' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $cloudId = $validated['cloud_id'] ?? null;
        $result = $this->attendanceService->syncFromFingerspot(
            $validated['start_date'],
            $validated['end_date'],
            $cloudId
        );

        return response()->json([
            'message' => 'Perintah tarik log absensi berhasil dikirim ke mesin.',
            'result' => $result,
        ]);
    }
}
