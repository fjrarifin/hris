<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\PublicGaWorkOrderApprovalController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman publik approval Work Order/ RAB yang disajikan langsung oleh backend HRIS.
 *
 * Dipakai oleh link WhatsApp: {PUBLIC_APPROVAL_BASE_URL}/wo/{token}
 * sehingga domain produksi (api-hr.hompimplay.id) dan lokal (0.0.0.0:8000)
 * selalu membuka halaman yang valid tanpa bergantung pada host frontend.
 */
class GaWorkOrderPublicPageController extends Controller
{
    public function show(string $token): View
    {
        $response = app(PublicGaWorkOrderApprovalController::class)->show($token);
        $status = $response->getStatusCode();
        $data = json_decode($response->getContent());

        if ($status !== 200 || ! is_object($data) || empty($data->work_order)) {
            $message = is_object($data) ? ($data->message ?? null) : null;

            return view('ga.work-order-invalid', [
                'reason' => $status === 404
                    ? 'Tautan persetujuan tidak valid atau Work Order sudah dibatalkan.'
                    : ($message ?: 'Tautan persetujuan tidak dapat dibuka saat ini. Silakan coba beberapa saat lagi.'),
            ]);
        }

        return view('ga.work-order-approval', [
            'token' => $token,
            'workOrder' => $data->work_order,
            'requester' => $data->requester ?? null,
            'approver' => $data->approver ?? null,
            'approval' => $data->approval ?? null,
            'approvals' => $data->approvals ?? [],
            'rabItems' => $data->rab_items ?? [],
            'canAct' => (bool) ($data->can_act ?? false),
        ]);
    }

    public function decide(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'comment' => 'nullable|string|max:2000',
        ]);

        if ($data['decision'] === 'reject' && trim((string) ($data['comment'] ?? '')) === '') {
            return redirect()->route('ga.wo.approval.show', ['token' => $token])
                ->withErrors(['comment' => 'Alasan penolakan atau catatan revisi wajib diisi.'])
                ->withInput();
        }

        $response = app(PublicGaWorkOrderApprovalController::class)->decide($request, $token);
        $status = $response->getStatusCode();
        $payload = json_decode($response->getContent());
        $message = is_object($payload) ? ($payload->message ?? null) : null;

        if ($status !== 200) {
            return redirect()->route('ga.wo.approval.show', ['token' => $token])
                ->withErrors([
                    'decision' => $message ?: 'Keputusan persetujuan gagal disimpan.',
                ])
                ->withInput();
        }

        return redirect()->route('ga.wo.approval.show', ['token' => $token])
            ->with('status', $message ?: 'Keputusan persetujuan berhasil dicatat.');
    }
}
