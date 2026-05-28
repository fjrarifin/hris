<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeChangeLog;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function frontendIndex(): JsonResponse
    {
        $employees = Karyawan::query()
            ->with('user')
            ->orderBy('nik')
            ->get()
            ->map(fn (Karyawan $employee) => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'email' => $employee->email,
                'position' => $employee->jabatan ?: $employee->posisi,
                'department' => $employee->departement ?: $employee->divisi,
                'status' => $this->employeeStatus($employee->nik),
                'photo_url' => $this->publicFileUrl($employee->user?->photo),
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
                'Golongan Darah', 'Status Pajak', 'Status Pernikahan', 'Nama Pasangan',
                'Daftar Anak',
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
                    $this->employeeStatus($employee->nik),
                    $employee->join_date?->toDateString(),
                    $employee->golongan_darah,
                    $employee->status_pajak,
                    $employee->status_pernikahan,
                    $employee->nama_pasangan,
                    implode('; ', $this->employeeChildren($employee)),
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
            $this->syncEmployeeStatus($employee);

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

        DB::transaction(function () use ($request, $employee, $payload): void {
            $profilePayload = $this->profilePayload($payload);
            $auditPayload = $this->employeeAuditPayload($profilePayload);
            $before = $this->snapshotEmployee($employee, array_keys($auditPayload));
            $employee->update($profilePayload);
            $this->syncExistingUser($employee, $profilePayload);
            $this->recordEmployeeChanges($employee, $before, $auditPayload, $request->user(), 'hrd');
            $this->saveContract($employee, $payload);
            $this->syncEmployeeStatus($employee);
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
            'jabatan' => [$requiredOnCreate, 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'posisi_level' => ['nullable', Rule::in($this->positionLevels())],
            'posisi_title' => ['nullable', Rule::in($this->positionTitles())],
            'divisi' => ['nullable', Rule::in($this->divisionOptions())],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'nama_atasan_langsung' => ['nullable', 'string', 'max:150', 'exists:m_karyawan,nama_karyawan'],
            'atasan_tidak_langsung' => ['nullable', 'string', 'max:150', 'exists:m_karyawan,nama_karyawan'],
            'join_date' => ['nullable', 'date'],
            'jenis_kontrak' => ['nullable', Rule::in(['PKWT', 'PKWTT']), 'required_with:start_date,end_date,status_kontrak'],
            'status_kontrak' => ['nullable', Rule::in(['AKTIF', 'NONAKTIF']), 'required_with:start_date,end_date,jenis_kontrak'],
            'start_date' => ['nullable', 'date', 'required_with:end_date,status_kontrak,jenis_kontrak'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_with:start_date,status_kontrak,jenis_kontrak'],
            'keterangan_kontrak' => ['nullable', 'string', 'max:1000'],
            'document' => [$this->requiresNewContractDocument($request, $employee) ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:2048'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', $emailRule],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'golongan_darah' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'no_ktp' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'npwp' => ['nullable', 'boolean'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'status_pajak' => ['nullable', Rule::in($this->taxStatuses())],
            'status_pernikahan' => ['nullable', Rule::in($this->maritalStatuses())],
            'agama' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            'nama_institusi' => ['nullable', 'string', 'max:150'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'nama_pasangan' => ['nullable', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'array'],
            'children.*' => ['nullable', 'string', 'max:150'],
            'nama_anak_1' => ['nullable', 'string', 'max:150'],
            'nama_anak_2' => ['nullable', 'string', 'max:150'],
            'nama_anak_3' => ['nullable', 'string', 'max:150'],
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

        if ($request->has('children')) {
            $payload = $this->syncChildrenPayload($payload);
        }

        if ($request->hasFile('document')) {
            $payload['document'] = $request->file('document')->store('contract-documents', 'local');
        }

        return $payload;
    }

    private function profilePayload(array $payload): array
    {
        return Arr::except($payload, [
            'jenis_kontrak',
            'status_kontrak',
            'start_date',
            'end_date',
            'keterangan_kontrak',
            'document',
            'status_karyawan',
        ]);
    }

    private function employeeData(Karyawan $employee): array
    {
        $employee->loadMissing('user');
        $photoUrl = $this->publicFileUrl($employee->user?->photo);
        $employee->unsetRelation('user');
        $contracts = DB::table('t_kontrak_karyawan')
            ->where('nik', $employee->nik)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $today = now()->startOfDay();
        $activeContract = $contracts->first(
            fn (object $contract): bool => $this->isActiveContract($contract, $today)
        );
        $formContract = $activeContract ?? $contracts->first();

        return [
            ...$employee->toArray(),
            'children' => $this->employeeChildren($employee),
            'status_karyawan' => $activeContract ? 'AKTIF' : 'NONAKTIF',
            'photo_url' => $photoUrl,
            'join_date' => $employee->join_date?->toDateString(),
            'tanggal_lahir' => $employee->tanggal_lahir?->toDateString(),
            'jenis_kontrak' => $formContract?->jenis_kontrak,
            'status_kontrak' => $formContract?->status_kontrak,
            'start_date' => $formContract?->start_date,
            'end_date' => $formContract?->end_date,
            'keterangan_kontrak' => $formContract?->keterangan,
            'active_contract_id' => $activeContract?->id,
            'contracts' => $contracts->map(fn (object $contract) => [
                'id' => $contract->id,
                'contract_number' => $contract->kontrak_ke,
                'contract_type' => $contract->jenis_kontrak ?? 'PKWT',
                'start_date' => $contract->start_date,
                'end_date' => $contract->end_date,
                'duration_months' => $this->durationMonths($contract->start_date, $contract->end_date),
                'duration_label' => $this->durationLabel($this->durationMonths($contract->start_date, $contract->end_date)),
                'status' => $contract->status_kontrak,
                'description' => $contract->keterangan,
                'has_document' => filled($contract->document),
            ])->values(),
            'change_logs' => $this->employeeChangeLogs($employee),
        ];
    }

    private function snapshotEmployee(Karyawan $employee, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field): array => [$field => $this->normalizeAuditValue($employee->{$field})])
            ->all();
    }

    private function employeeAuditPayload(array $payload): array
    {
        if (array_key_exists('children', $payload)) {
            return Arr::except($payload, ['nama_anak_1', 'nama_anak_2', 'nama_anak_3']);
        }

        return $payload;
    }

    private function recordEmployeeChanges(
        Karyawan $employee,
        array $before,
        array $payload,
        ?\App\Models\User $actor,
        string $source
    ): void {
        $changes = collect($payload)
            ->map(function ($newValue, string $field) use ($before): ?array {
                $old = $before[$field] ?? null;
                $new = $this->normalizeAuditValue($newValue);

                if ($old === $new) {
                    return null;
                }

                return [
                    'field' => $field,
                    'label' => $this->employeeFieldLabel($field),
                    'old' => $old,
                    'new' => $new,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($changes === []) {
            return;
        }

        EmployeeChangeLog::create([
            'employee_nik' => $employee->nik,
            'changed_by_user_id' => $actor?->id,
            'changed_by_name' => $actor?->name,
            'source' => $source,
            'changes' => $changes,
        ]);
    }

    private function employeeChangeLogs(Karyawan $employee): array
    {
        return EmployeeChangeLog::query()
            ->where('employee_nik', $employee->nik)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (EmployeeChangeLog $log): array => [
                'id' => $log->id,
                'changed_by_name' => $log->changed_by_name ?: 'User tidak diketahui',
                'source' => $log->source,
                'source_label' => $log->source === 'self_service' ? 'Karyawan' : 'HRD',
                'changes' => $log->changes ?? [],
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function normalizeAuditValue(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        return $value === null ? null : trim((string) $value);
    }

    private function employeeFieldLabel(string $field): string
    {
        return [
            'nama_karyawan' => 'Nama Karyawan',
            'jabatan' => 'Jabatan',
            'posisi' => 'Posisi',
            'posisi_level' => 'Level Posisi',
            'posisi_title' => 'Title Posisi',
            'divisi' => 'Divisi',
            'departement' => 'Departemen',
            'unit' => 'Unit',
            'nama_atasan_langsung' => 'Atasan Langsung',
            'atasan_tidak_langsung' => 'Atasan Tidak Langsung',
            'join_date' => 'Tanggal Bergabung',
            'no_hp' => 'Nomor HP',
            'email' => 'Email',
            'tanggal_lahir' => 'Tanggal Lahir',
            'jenis_kelamin' => 'Jenis Kelamin',
            'golongan_darah' => 'Golongan Darah',
            'no_ktp' => 'Nomor KTP',
            'tempat_lahir' => 'Tempat Lahir',
            'alamat' => 'Alamat',
            'npwp' => 'NPWP',
            'no_npwp' => 'Nomor NPWP',
            'status_pajak' => 'Status Pajak',
            'status_pernikahan' => 'Status Pernikahan',
            'agama' => 'Agama',
            'kewarganegaraan' => 'Kewarganegaraan',
            'pendidikan_terakhir' => 'Pendidikan Terakhir',
            'nama_institusi' => 'Institusi / Sekolah',
            'jurusan' => 'Jurusan',
            'nama_pasangan' => 'Nama Pasangan',
            'jumlah_anak' => 'Jumlah Anak',
            'children' => 'Daftar Anak',
            'nama_anak_1' => 'Nama Anak 1',
            'nama_anak_2' => 'Nama Anak 2',
            'nama_anak_3' => 'Nama Anak 3',
            'nama_ayah' => 'Nama Ayah',
            'nama_ibu' => 'Nama Ibu',
            'kontak_darurat_nama' => 'Kontak Darurat',
            'kontak_darurat_hubungan' => 'Hubungan Kontak Darurat',
            'kontak_darurat_no_hp' => 'Nomor Kontak Darurat',
            'bank' => 'Bank',
            'no_rekening' => 'Nomor Rekening',
            'bpjs' => 'BPJS',
            'no_bpjs' => 'Nomor BPJS',
        ][$field] ?? str($field)->replace('_', ' ')->title()->toString();
    }

    private function publicFileUrl(?string $path): ?string
    {
        return $path
            ? route('profile-photos.show', ['filename' => basename($path)])
            : null;
    }

    private function syncChildrenPayload(array $payload): array
    {
        $children = $this->normalizeChildren($payload['children'] ?? []);
        $payload['children'] = $children;
        $payload['jumlah_anak'] = count($children);

        foreach ([1, 2, 3] as $index) {
            $payload['nama_anak_'.$index] = $children[$index - 1] ?? null;
        }

        return $payload;
    }

    private function employeeChildren(Karyawan $employee): array
    {
        $children = $this->normalizeChildren($employee->children ?? []);

        if ($children !== []) {
            return $children;
        }

        return $this->normalizeChildren([
            $employee->nama_anak_1,
            $employee->nama_anak_2,
            $employee->nama_anak_3,
        ]);
    }

    private function normalizeChildren(mixed $children): array
    {
        if (! is_array($children)) {
            return [];
        }

        return collect($children)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }

    private function saveContract(Karyawan $employee, array $payload): void
    {
        $fields = Arr::only($payload, ['jenis_kontrak', 'status_kontrak', 'start_date', 'end_date', 'keterangan_kontrak']);
        if (! collect($fields)->contains(fn ($value) => filled($value))) {
            return;
        }

        $fields = [...$fields, ...Arr::only($payload, ['document'])];
        $contractPayload = [
            'jenis_kontrak' => strtoupper((string) $fields['jenis_kontrak']),
            'status_kontrak' => strtoupper((string) $fields['status_kontrak']),
            'start_date' => $fields['start_date'],
            'end_date' => $fields['end_date'],
            'durasi_bulan' => $this->durationMonths($fields['start_date'], $fields['end_date']),
            'keterangan' => $fields['keterangan_kontrak'] ?? null,
            'updated_at' => now(),
            ...array_key_exists('document', $fields) ? ['document' => $fields['document']] : [],
        ];

        $contract = DB::table('t_kontrak_karyawan')
            ->where('nik', $employee->nik)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($contractPayload['status_kontrak'] === 'AKTIF') {
            $hasOtherActiveContract = DB::table('t_kontrak_karyawan')
                ->where('nik', $employee->nik)
                ->where('status_kontrak', 'AKTIF')
                ->when($contract, fn ($query) => $query->where('id', '!=', $contract->id))
                ->exists();

            if ($hasOtherActiveContract) {
                throw ValidationException::withMessages([
                    'status_kontrak' => ['Kontrak AKTIF lain harus diubah menjadi NONAKTIF terlebih dahulu.'],
                ]);
            }
        }

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

    private function syncEmployeeStatus(Karyawan $employee): void
    {
        $employee->update([
            'status_karyawan' => $this->employeeStatus($employee->nik),
        ]);
    }

    private function requiresNewContractDocument(Request $request, ?Karyawan $employee): bool
    {
        $hasContractInput = collect(['jenis_kontrak', 'status_kontrak', 'start_date', 'end_date'])
            ->contains(fn (string $field): bool => $request->filled($field));

        if (! $hasContractInput) {
            return false;
        }

        return ! $employee || ! DB::table('t_kontrak_karyawan')->where('nik', $employee->nik)->exists();
    }

    private function employeeStatus(string $nik): string
    {
        $today = now()->startOfDay();
        $hasActiveContract = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->get()
            ->contains(fn (object $contract): bool => $this->isActiveContract($contract, $today));

        return $hasActiveContract ? 'AKTIF' : 'NONAKTIF';
    }

    private function isActiveContract(object $contract, Carbon $today): bool
    {
        return strtoupper((string) $contract->status_kontrak) === 'AKTIF'
            && Carbon::parse($contract->start_date)->lte($today)
            && Carbon::parse($contract->end_date)->gte($today);
    }

    private function durationMonths(?string $startDate, ?string $endDate): ?int
    {
        if (! $startDate || ! $endDate) {
            return null;
        }

        return (int) round(Carbon::parse($startDate)->diffInMonths(Carbon::parse($endDate)->addDay()));
    }

    private function durationLabel(?int $months): string
    {
        if (! $months) {
            return '-';
        }

        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;
        $parts = [];

        if ($years) {
            $parts[] = $years.' tahun';
        }

        if ($remainingMonths) {
            $parts[] = $remainingMonths.' bulan';
        }

        return implode(' ', $parts);
    }

    private function taxStatuses(): array
    {
        return ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3', 'K/I/0', 'K/I/1', 'K/I/2', 'K/I/3'];
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

    private function maritalStatuses(): array
    {
        return ['Menikah', 'Tidak Kawin', 'Cerai Hidup', 'Cerai Mati'];
    }
}
