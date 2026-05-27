<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\RawRowsImport;
use App\Models\AttendanceScheduleCategory;
use App\Models\EmployeeDailySchedule;
use App\Models\Karyawan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class StaffTeamScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $supervisor = $this->supervisorFor($request);
        $employees = $this->manageableEmployees($supervisor);
        $counts = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employees->pluck('nik'))
            ->whereBetween('schedule_date', [$start, $end])
            ->select('karyawan_nik', DB::raw('count(*) as total'))
            ->groupBy('karyawan_nik')
            ->pluck('total', 'karyawan_nik');

        return response()->json([
            'filters' => $this->serializedPeriod($start, $end),
            'supervisor' => [
                'nik' => $supervisor->nik,
                'name' => $supervisor->nama_karyawan,
                'position' => $supervisor->jabatan ?: ($supervisor->posisi ?: '-'),
            ],
            'employees' => $employees->map(fn (Karyawan $employee) => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                'department' => $employee->departement ?: ($employee->divisi ?: '-'),
                'unit' => $employee->unit ?: '-',
                'relationship' => $employee->nama_atasan_langsung === $supervisor->nama_karyawan
                    ? 'Bawahan langsung'
                    : 'Bawahan 2 level',
                'scheduled_days' => (int) ($counts[$employee->nik] ?? 0),
            ])->values(),
            'categories' => $this->categories(),
        ]);
    }

    public function employee(Request $request, string $nik): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $supervisor = $this->supervisorFor($request);
        $employee = $this->manageableEmployeeOrFail($supervisor, $nik);
        $schedules = EmployeeDailySchedule::query()
            ->where('karyawan_nik', $employee->nik)
            ->whereBetween('schedule_date', [$start, $end])
            ->pluck('schedule_code', 'schedule_date');

        return response()->json([
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                'department' => $employee->departement ?: ($employee->divisi ?: '-'),
            ],
            'dates' => collect(CarbonPeriod::create($start, $end))->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'code' => $schedules[$date->toDateString()] ?? '',
            ]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request, string $nik): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $supervisor = $this->supervisorFor($request);
        $this->manageableEmployeeOrFail($supervisor, $nik);
        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.date' => ['required', 'date'],
            'schedules.*.code' => ['nullable', 'string', 'max:20'],
        ]);
        $categories = $this->categoryMap();
        $saved = 0;
        $cleared = 0;

        DB::transaction(function () use ($validated, $categories, $start, $end, $nik, $request, &$saved, &$cleared): void {
            foreach ($validated['schedules'] as $row) {
                $date = Carbon::parse($row['date'])->startOfDay();

                if (! $date->betweenIncluded($start, $end)) {
                    continue;
                }

                $code = strtoupper(trim((string) ($row['code'] ?? '')));

                if ($code === '') {
                    $cleared += EmployeeDailySchedule::query()
                        ->where('karyawan_nik', $nik)
                        ->whereDate('schedule_date', $date)
                        ->delete();

                    continue;
                }

                $category = $categories->get($code);
                if (! $category) {
                    throw ValidationException::withMessages(['schedules' => "Kode jadwal {$code} tidak tersedia."]);
                }

                EmployeeDailySchedule::updateOrCreate(
                    ['karyawan_nik' => $nik, 'schedule_date' => $date->toDateString()],
                    [
                        'schedule_category_id' => $category->id,
                        'schedule_code' => $category->code,
                        'source' => 'supervisor_manual',
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]
                );
                $saved++;
            }
        });

        return response()->json([
            'message' => "Jadwal tersimpan: {$saved}; dikosongkan: {$cleared}.",
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $supervisor = $this->supervisorFor($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);
        $employeeNiks = $this->manageableEmployees($supervisor)->pluck('nik')->flip();
        $rows = collect(Excel::toArray(new RawRowsImport, $request->file('file'))[0] ?? []);
        $header = collect($rows->shift() ?? []);
        $dateColumns = $this->dateColumns($header, $start, $end);
        $categories = $this->categoryMap();
        $saved = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $dateColumns, $categories, $employeeNiks, $request, &$saved, &$skipped): void {
            foreach ($rows as $row) {
                $nik = trim((string) ($row[0] ?? ''));
                if ($nik === '' || ! $employeeNiks->has($nik)) {
                    $skipped++;

                    continue;
                }

                foreach ($dateColumns as $column => $date) {
                    $category = $categories->get(strtoupper(trim((string) ($row[$column] ?? ''))));
                    if (! $category) {
                        $skipped++;

                        continue;
                    }

                    EmployeeDailySchedule::updateOrCreate(
                        ['karyawan_nik' => $nik, 'schedule_date' => $date],
                        [
                            'schedule_category_id' => $category->id,
                            'schedule_code' => $category->code,
                            'source' => 'supervisor_upload',
                            'created_by' => $request->user()->id,
                            'updated_by' => $request->user()->id,
                        ]
                    );
                    $saved++;
                }
            }
        });

        return response()->json([
            'message' => "Upload jadwal selesai. Tersimpan: {$saved}; dilewati: {$skipped}.",
        ]);
    }

    private function supervisorFor(Request $request): Karyawan
    {
        $employee = Karyawan::query()->where('nik', $request->user()->username)->firstOrFail();

        abort_unless($this->isSupervisor($employee), 403, 'Menu Jadwal Tim hanya dapat digunakan oleh Supervisor.');

        return $employee;
    }

    private function manageableEmployeeOrFail(Karyawan $supervisor, string $nik): Karyawan
    {
        $employee = $this->manageableEmployees($supervisor)->firstWhere('nik', $nik);

        abort_unless($employee, 403, 'Jadwal hanya dapat diatur untuk bawahan Supervisor.');

        return $employee;
    }

    private function manageableEmployees(Karyawan $supervisor): Collection
    {
        return Karyawan::query()
            ->where('nik', '!=', $supervisor->nik)
            ->where(function ($query) use ($supervisor) {
                $query->where('nama_atasan_langsung', $supervisor->nama_karyawan)
                    ->orWhere('atasan_tidak_langsung', $supervisor->nama_karyawan);
            })
            ->orderBy('nama_karyawan')
            ->get([
                'nik',
                'nama_karyawan',
                'jabatan',
                'posisi',
                'posisi_title',
                'departement',
                'divisi',
                'unit',
                'nama_atasan_langsung',
                'atasan_tidak_langsung',
            ])
            ->filter(fn (Karyawan $employee) => $this->isManageableRole($employee))
            ->values();
    }

    private function isSupervisor(Karyawan $employee): bool
    {
        $role = strtolower(implode(' ', array_filter([
            $employee->jabatan,
            $employee->posisi,
            $employee->posisi_title,
        ])));

        return str_contains($role, 'supervisor') || preg_match('/\bspv\b/i', $role) === 1;
    }

    private function isManageableRole(Karyawan $employee): bool
    {
        $role = strtolower(implode(' ', array_filter([
            $employee->jabatan,
            $employee->posisi,
            $employee->posisi_title,
        ])));

        foreach (['leader', 'staff', 'operator', 'cashier', 'kasir'] as $allowedRole) {
            if (str_contains($role, $allowedRole)) {
                return true;
            }
        }

        return false;
    }

    private function period(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();

        if ($start->diffInDays($end) > 45) {
            throw ValidationException::withMessages(['end_date' => 'Periode jadwal maksimal 46 hari.']);
        }

        return [$start, $end];
    }

    private function serializedPeriod(Carbon $start, Carbon $end): array
    {
        return ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()];
    }

    private function categories(): Collection
    {
        return AttendanceScheduleCategory::query()
            ->where('is_active', true)
            ->orderBy('is_workday', 'desc')
            ->orderBy('start_time')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'start_time', 'end_time', 'type']);
    }

    private function categoryMap(): Collection
    {
        return $this->categories()->keyBy('code');
    }

    private function dateColumns(Collection $header, Carbon $start, Carbon $end): array
    {
        $columns = [];
        foreach ($header as $index => $value) {
            if ($index === 0) {
                continue;
            }
            $date = $this->parseDate($value);
            if ($date && Carbon::parse($date)->betweenIncluded($start, $end)) {
                $columns[$index] = $date;
            }
        }

        return $columns;
    }

    private function parseDate(mixed $value): ?string
    {
        try {
            return is_numeric($value)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString()
                : Carbon::parse(str_replace('/', '-', trim((string) $value)))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
