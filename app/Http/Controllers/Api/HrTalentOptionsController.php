<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\MasterJabatan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class HrTalentOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'jabatans' => MasterJabatan::query()->where('is_active', true)->orderBy('nama_jabatan')->get(),
            'employees' => Karyawan::query()->orderBy('nama_karyawan')->get(['nik', 'nama_karyawan', 'jabatan', 'departement']),
            'reviewers' => User::query()->whereNotNull('username')->orderBy('name')->get(['id', 'name', 'username']),
        ]);
    }
}
