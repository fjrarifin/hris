<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function frontendIndex(): JsonResponse
    {
        $employees = Karyawan::query()
            ->orderBy('nik')
            ->get()
            ->map(fn (Karyawan $employee) => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'email' => $employee->email,
                'position' => $employee->jabatan ?: $employee->posisi,
                'department' => $employee->departement ?: $employee->divisi,
            ]);

        return response()->json([
            'data' => $employees,
        ]);
    }

    public function export(): StreamedResponse
    {
        $employees = Karyawan::query()->orderBy('nik')->get();

        return response()->streamDownload(function () use ($employees): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'NIK', 'Nama', 'Jabatan', 'Posisi', 'Divisi', 'Departemen', 'Unit',
                'Email', 'No HP', 'Status Karyawan', 'Tanggal Bergabung',
            ]);

            foreach ($employees as $employee) {
                fputcsv($file, [
                    $employee->nik,
                    $employee->nama_karyawan,
                    $employee->jabatan,
                    $employee->posisi,
                    $employee->divisi,
                    $employee->departement,
                    $employee->unit,
                    $employee->email,
                    $employee->no_hp,
                    $employee->status_karyawan,
                    $employee->join_date?->toDateString(),
                ]);
            }

            fclose($file);
        }, 'Data_Karyawan_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $employees = Karyawan::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($employeeQuery) use ($search) {
                    $employeeQuery->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_karyawan', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%")
                        ->orWhere('posisi', 'like', "%{$search}%")
                        ->orWhere('divisi', 'like', "%{$search}%")
                        ->orWhere('departement', 'like', "%{$search}%");
                });
            })
            ->orderBy('nik')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);
        $payload = $this->preparePayload($request, $payload);

        $employee = DB::transaction(function () use ($payload): Karyawan {
            $employee = Karyawan::create($this->profilePayload($payload));
            $this->saveContract($employee, $payload);

            return $employee;
        });

        return response()->json([
            'message' => 'Data karyawan berhasil ditambahkan.',
            'data' => $this->employeeData($employee->fresh()),
        ], 201);
    }

    public function show(Karyawan $employee): JsonResponse
    {
        return response()->json([
            'data' => $this->employeeData($employee),
        ]);
    }

    public function update(Request $request, Karyawan $employee): JsonResponse
    {
        $payload = $this->validatedPayload($request, $employee);
        $payload = $this->preparePayload($request, $payload, $employee);

        DB::transaction(function () use ($employee, $payload): void {
            $profilePayload = $this->profilePayload($payload);
            $employee->update($profilePayload);
            $this->syncExistingUser($employee, $profilePayload);
            $this->saveContract($employee, $payload);
        });

        return response()->json([
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => $this->employeeData($employee->fresh()),
        ]);
    }

    public function destroy(Karyawan $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(null, 204);
    }

    private function validatedPayload(Request $request, ?Karyawan $employee = null): array
    {
        $isUpdate = $employee !== null;
        $requiredOnCreate = $isUpdate ? 'sometimes' : 'required';
        $emailRule = Rule::unique('users', 'email');

        if ($employee?->user) {
            $emailRule->ignore($employee->user->id);
        }

        return $request->validate([
            'nik' => $isUpdate
                ? ['prohibited']
                : ['required', 'string', 'max:30', 'unique:m_karyawan,nik'],
            'pin' => $isUpdate
                ? ['prohibited']
                : ['nullable', 'string', 'max:50'],
            'nama_karyawan' => [$requiredOnCreate, 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'posisi_level' => ['nullable', Rule::in($this->positionLevels())],
            'posisi_title' => ['nullable', Rule::in($this->positionTitles())],
            'divisi' => ['nullable', Rule::in($this->divisionOptions())],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'nama_atasan_langsung' => ['nullable', 'string', 'max:150'],
            'atasan_tidak_langsung' => ['nullable', 'string', 'max:150'],
            'status_karyawan' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'status_kontrak' => ['nullable', 'string', 'max:50', 'required_with:start_date,end_date,durasi_kontrak'],
            'start_date' => ['nullable', 'date', 'required_with:end_date,status_kontrak,durasi_kontrak'],
            'durasi_kontrak' => ['nullable', 'integer', 'min:0'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_with:start_date,status_kontrak,durasi_kontrak'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', $emailRule],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'no_ktp' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'npwp' => ['nullable', 'boolean'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'status_pernikahan' => ['nullable', 'string', 'max:50'],
            'agama' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            'nama_institusi' => ['nullable', 'string', 'max:150'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'nama_pasangan' => ['nullable', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0'],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'nama_ibu' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat_no_hp' => ['nullable', 'string', 'max:30'],
            'bank' => ['nullable', 'string', 'max:100'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'bpjs' => ['nullable', 'boolean'],
            'no_bpjs' => ['nullable', 'string', 'max:50'],
        ]);
    }

    private function preparePayload(Request $request, array $payload, ?Karyawan $employee = null): array
    {
        if (array_key_exists('email', $payload)) {
            $payload['email'] = $payload['email']
                ? strtolower(trim($payload['email']))
                : null;
        }

        if (! $employee || $request->has('bpjs')) {
            $payload['bpjs'] = $request->boolean('bpjs');
        }

        if (! $employee || $request->has('npwp')) {
            $payload['npwp'] = $request->boolean('npwp');
        }

        if ($request->hasAny(['posisi_level', 'posisi_title'])) {
            $level = trim((string) ($payload['posisi_level'] ?? $employee?->posisi_level));
            $title = trim((string) ($payload['posisi_title'] ?? $employee?->posisi_title));

            $payload['posisi'] = trim($level.' '.$title) ?: null;
        }

        return $payload;
    }

    private function profilePayload(array $payload): array
    {
        return Arr::except($payload, ['status_kontrak', 'start_date', 'durasi_kontrak', 'end_date']);
    }

    private function employeeData(Karyawan $employee): array
    {
        $contract = DB::table('t_kontrak_karyawan')
            ->where('nik', $employee->nik)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        return [
            ...$employee->toArray(),
            'status_kontrak' => $contract?->status_kontrak,
            'start_date' => $contract?->start_date,
            'durasi_kontrak' => $contract?->durasi_bulan,
            'end_date' => $contract?->end_date,
        ];
    }

    private function saveContract(Karyawan $employee, array $payload): void
    {
        $fields = Arr::only($payload, ['status_kontrak', 'start_date', 'durasi_kontrak', 'end_date']);
        if (! collect($fields)->contains(fn ($value) => filled($value))) {
            return;
        }

        $contractPayload = [
            'status_kontrak' => strtoupper((string) $fields['status_kontrak']),
            'start_date' => $fields['start_date'],
            'end_date' => $fields['end_date'],
            'durasi_bulan' => $fields['durasi_kontrak'] ?? null,
            'updated_at' => now(),
        ];

        $contract = DB::table('t_kontrak_karyawan')
            ->where('nik', $employee->nik)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($contract) {
            DB::table('t_kontrak_karyawan')
                ->where('id', $contract->id)
                ->update($contractPayload);

            return;
        }

        DB::table('t_kontrak_karyawan')->insert([
            'nik' => $employee->nik,
            'kontrak_ke' => 1,
            ...$contractPayload,
            'created_at' => now(),
        ]);
    }

    private function syncExistingUser(Karyawan $employee, array $payload): void
    {
        $user = $employee->user;

        if (! $user) {
            return;
        }

        $userPayload = [];

        if (array_key_exists('nama_karyawan', $payload)) {
            $userPayload['name'] = $payload['nama_karyawan'];
        }

        if (! empty($payload['email'])) {
            $userPayload['email'] = $payload['email'];
        }

        if ($userPayload) {
            $user->update($userPayload);
        }
    }

    private function positionLevels(): array
    {
        return ['Sr.', 'Md.', 'Jr.'];
    }

    private function positionTitles(): array
    {
        return ['Operator', 'Staff', 'Leader', 'Supervisor', 'Asst. Manager', 'Manager', 'GM'];
    }

    private function divisionOptions(): array
    {
        return ['Business Partner', 'Commercial Business'];
    }
}
