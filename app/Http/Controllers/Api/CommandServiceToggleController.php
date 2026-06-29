<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandServiceToggle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandServiceToggleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CommandServiceToggle::query()->orderBy('key')->get(),
        ]);
    }

    public function update(Request $request, CommandServiceToggle $commandServiceToggle): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $commandServiceToggle->update($validated);

        return response()->json([
            'message' => 'Status layanan berhasil diperbarui.',
            'data' => $commandServiceToggle,
        ]);
    }
}
