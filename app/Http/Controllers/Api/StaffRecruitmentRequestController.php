<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffRecruitmentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requests = RecruitmentRequest::query()
            ->with(['vacancy.candidates'])
            ->where('requester_nik', $request->user()->username)
            ->latest()
            ->get()
            ->map(function ($item) {
                $candidates = $item->vacancy ? $item->vacancy->candidates : collect();
                $item->stats = [
                    'applied' => $candidates->where('status', 'applied')->count(),
                    'screening' => $candidates->where('status', 'screening')->count(),
                    'interview' => $candidates->where('status', 'interview')->count(),
                    'offered' => $candidates->where('status', 'offered')->count(),
                    'hired' => $candidates->where('status', 'hired')->count(),
                    'rejected' => $candidates->where('status', 'rejected')->count(),
                    'total' => $candidates->count(),
                ];
                unset($item->vacancy); // To keep payload clean unless needed
                return $item;
            });

        return response()->json($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'exists:master_position_titles,name'],
            'department' => ['required', 'string', 'exists:master_departments,name'],
            'unit' => ['required', 'string', 'exists:master_units,name'],
            'quantity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $recruitmentRequest = RecruitmentRequest::query()->create($payload + [
            'requester_nik' => $request->user()->username,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pengajuan rekrutmen berhasil diajukan.',
            'data' => $recruitmentRequest,
        ], 201);
    }
}
