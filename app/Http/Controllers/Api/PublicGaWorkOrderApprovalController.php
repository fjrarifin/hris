<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GaWorkOrderWhatsAppNotifier;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Public (token-authenticated) Work Order Approval proxy for managers approving directly via WhatsApp link.
 */
class PublicGaWorkOrderApprovalController extends Controller
{
    public function show(string $token): JsonResponse
    {
        return $this->forwardJson('GET', "/api/internal/hris/work-orders/public/{$token}");
    }

    public function decide(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:2000',
        ]);

        if ($data['decision'] === 'reject' && trim((string) ($data['comment'] ?? '')) === '') {
            return response()->json(['message' => 'Alasan penolakan atau catatan revisi wajib diisi.'], 422);
        }

        $response = $this->forwardJson('POST', "/api/internal/hris/work-orders/public/{$token}/decide", $data);

        if ($response->getStatusCode() === 200) {
            $payload = $response->getData(true);
            if (is_array($payload) && ! empty($payload['requester'])) {
                $actorObj = (object) [
                    'name' => $payload['approver_name'] ?? 'Approver',
                    'username' => $payload['approver_nik'] ?? '',
                ];
                app(GaWorkOrderWhatsAppNotifier::class)->notifyRequesterOnDecision(
                    [
                        'requester' => $payload['requester'],
                        'work_order' => $payload['work_order'] ?? [],
                    ],
                    $actorObj,
                    (string) ($payload['stage'] ?? 'initial'),
                    $data['decision'],
                    $data['comment'] ?? null,
                    $payload['status'] ?? null
                );
            }
        }

        return $response;
    }

    public function attachment(Request $request, string $token, string $type): SymfonyResponse
    {
        abort_unless(in_array($type, ['photo', 'document', 'spl', 'sph'], true), 404, 'Tipe lampiran tidak valid.');

        $baseUrl = rtrim((string) config('services.ga_ems.url', ''), '/');
        $secret = (string) config('services.ga_ems.secret', '');
        $timeout = max(4, (int) config('services.ga_ems.timeout', 8));

        if ($baseUrl === '' || $secret === '') {
            return response()->json(['message' => 'Layanan Work Order GA belum dikonfigurasi.'], 503);
        }

        $index = max(0, (int) $request->query('index', 0));
        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)->withHeaders([
                'X-GA-Service-Secret' => $secret,
            ])->get("{$baseUrl}/api/internal/hris/work-orders/public/{$token}/attachments/{$type}", ['index' => $index]);
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

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => $response->header('Content-Disposition') ?: 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function forwardJson(string $method, string $path, array $payload = []): JsonResponse
    {
        $baseUrl = rtrim((string) config('services.ga_ems.url', ''), '/');
        $secret = (string) config('services.ga_ems.secret', '');
        $timeout = max(4, (int) config('services.ga_ems.timeout', 8));

        if ($baseUrl === '' || $secret === '') {
            return response()->json(['message' => 'Layanan Work Order GA belum dikonfigurasi.'], 503);
        }

        try {
            $client = Http::timeout($timeout)->withHeaders([
                'Accept' => 'application/json',
                'X-GA-Service-Secret' => $secret,
            ]);
            /** @var Response $response */
            $response = strtoupper($method) === 'GET'
                ? $client->get($baseUrl.$path, $payload)
                : $client->post($baseUrl.$path, $payload);
        } catch (\Throwable) {
            return response()->json(['message' => 'Layanan Work Order GA sedang tidak tersedia. Coba kembali beberapa saat lagi.'], 503);
        }

        $json = $response->json();

        return response()->json(
            is_array($json) ? $json : ['message' => 'Respons layanan GA tidak valid.'],
            $response->status()
        );
    }
}
