<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HrisWhatsAppAgent;
use App\Services\NeoPromptContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NovaChatController extends Controller
{
    private function ensureLevelThreeUser(Request $request): void
    {
        // CheckLevel selalu meloloskan level 0, sedangkan chat web Nova khusus level 3 saja.
        if ((int) ($request->user()?->level ?? -1) !== 3) {
            abort(403, 'Akses chat web Nova hanya tersedia untuk karyawan (level 3).');
        }
    }

    public function index(Request $request, HrisWhatsAppAgent $agent): JsonResponse
    {
        $this->ensureLevelThreeUser($request);
        $user = $request->user();

        return response()->json([
            'persona' => NeoPromptContract::personaName(),
            'history' => $agent->getWebChatHistory($user),
        ]);
    }

    public function store(Request $request, HrisWhatsAppAgent $agent): JsonResponse
    {
        $this->ensureLevelThreeUser($request);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $agent->handleWebChat($request->user(), $validated['message']);
        $statusCode = ($result['status'] ?? 'sent') === 'rate_limited' ? 429 : 200;

        return response()->json($result, $statusCode);
    }

    public function destroy(Request $request, HrisWhatsAppAgent $agent): JsonResponse
    {
        $this->ensureLevelThreeUser($request);
        $agent->clearWebChatHistory($request->user());

        return response()->json([
            'status' => 'cleared',
            'persona' => NeoPromptContract::personaName(),
            'history' => [],
        ]);
    }
}
