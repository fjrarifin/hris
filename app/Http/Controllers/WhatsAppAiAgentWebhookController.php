<?php

namespace App\Http\Controllers;

use App\Services\HrisWhatsAppAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppAiAgentWebhookController extends Controller
{
    public function __invoke(Request $request, HrisWhatsAppAgent $agent): JsonResponse
    {
        $token = trim((string) config('services.hris_agent.webhook_token', ''));

        if ($token !== '' && ! hash_equals($token, (string) ($request->header('X-HRIS-Agent-Token') ?: $request->input('token')))) {
            return response()->json([
                'status' => 'rejected',
                'reason' => 'invalid_token',
            ], 403);
        }

        Log::info('WHATSAPP AI AGENT WEBHOOK HIT', [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        return response()->json($agent->handleWebhook($request->all()));
    }
}
