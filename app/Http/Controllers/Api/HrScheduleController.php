<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\RawRowsImport;
use App\Models\AttendanceScheduleCategory;
use App\Models\EmployeeDailySchedule;
use App\Models\Karyawan;
use App\Services\HrdAuditLogService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrScheduleController extends Controller
{
    public function options(): JsonResponse
    {
        $employees = Karyawan::query()
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi', 'unit']);

        return response()->json([
            'departments' => $employees
                ->map(fn (Karyawan $employee) => $this->employeeDepartment($employee))
                ->unique()
                ->sort()
                ->values(),
            'employees' => $employees->map(fn (Karyawan $employee) => $this->serializeEmployee($employee)),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $validated = $request->validate([
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', 'max:100'],
            'employee_niks' => ['nullable', 'array'],
            'employee_niks.*' => ['string', 'max:30'],
        ]);
        $departments = $validated['departments'] ?? [];
        $employeeNiks = $validated['employee_niks'] ?? [];
        $employees = Karyawan::query()
            ->when($departments !== [], fn (Builder $query) => $this->filterDepartments($query, $departments))
            ->when($employeeNiks !== [], fn (Builder $query) => $query->whereIn('nik', $employeeNiks))
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi', 'unit']);
        $counts = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employees->pluck('nik'))
            ->whereBetween('schedule_date', [$start, $end])
            ->select('karyawan_nik', DB::raw('count(*) as total'))
            ->groupBy('karyawan_nik')
            ->pluck('total', 'karyawan_nik');
        $totalPeriodDays = $start->diffInDays($end) + 1;

        return response()->json([
            'filters' => [
                ...$this->serializedPeriod($start, $end),
                'departments' => $departments,
                'employee_niks' => $employeeNiks,
            ],
            'employees' => $employees->map(fn (Karyawan $employee) => [
                ...$this->serializeEmployee($employee),
                'total_period_days' => $totalPeriodDays,
                'scheduled_days' => (int) ($counts[$employee->nik] ?? 0),
            ]),
            'categories' => $this->categories(),
        ]);
    }

    public function department(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
        ]);
        $department = $validated['department'];
        $employees = Karyawan::query()
            ->where(function ($query) use ($department) {
                if ($department === 'Tanpa Departemen') {
                    $query->where(function ($inner) {
                        $inner->whereNull('departement')->orWhere('departement', '');
                    })->where(function ($inner) {
                        $inner->whereNull('divisi')->orWhere('divisi', '');
                    });
                } else {
                    $query->where('departement', $department)
                        ->orWhere(function ($inner) use ($department) {
                            $inner->where(function ($empty) {
                                $empty->whereNull('departement')->orWhere('departement', '');
                            })->where('divisi', $department);
                        });
                }
            })
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi', 'unit']);
        $counts = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employees->pluck('nik'))
            ->whereBetween('schedule_date', [$start, $end])
            ->select('karyawan_nik', DB::raw('count(*) as total'))
            ->groupBy('karyawan_nik')
            ->pluck('total', 'karyawan_nik');

        return response()->json([
            'department' => $department,
            'employees' => $employees->map(fn (Karyawan $employee) => [
                ...$this->serializeEmployee($employee),
                'scheduled_days' => (int) ($counts[$employee->nik] ?? 0),
            ]),
        ]);
    }

    public function template(Request $request): StreamedResponse
    {
        [$start, $end] = $this->period($request);
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
        ]);
        $department = $validated['department'];
        $employees = $this->departmentEmployees($department);
        $filename = 'Template_Jadwal_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $department)
            .'_'.$start->format('Ymd').'_'.$end->format('Ymd').'.csv';

        return $this->templateResponse($employees, $start, $end, $filename);
    }

    public function employee(Request $request, string $nik): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();
        $schedules = EmployeeDailySchedule::query()
            ->where('karyawan_nik', $employee->nik)
            ->whereBetween('schedule_date', [$start, $end])
            ->pluck('schedule_code', 'schedule_date');

        return response()->json([
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'department' => $employee->departement ?: ($employee->divisi ?: 'Tanpa Departemen'),
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
        Karyawan::query()->where('nik', $nik)->firstOrFail();
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
                        'source' => 'manual',
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]
                );
                $saved++;
            }
        });
        app(HrdAuditLogService::class)->record(
            $request,
            'Jadwal Karyawan',
            'updated',
            "{$nik} {$start->toDateString()} - {$end->toDateString()}",
            ['saved' => 0, 'cleared' => 0],
            ['saved' => $saved, 'cleared' => $cleared],
            EmployeeDailySchedule::class,
            $nik
        );

        return response()->json([
            'message' => "Jadwal tersimpan: {$saved}; dikosongkan: {$cleared}.",
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
            'file' => [
                'required',
                'file',
                function (string $attribute, mixed $file, \Closure $fail): void {
                    if (! in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) {
                        $fail('File jadwal harus berformat XLSX, XLS, atau CSV.');
                    }
                },
            ],
        ]);
        $employeeNiks = $this->departmentEmployeeNiks($validated['department'])->flip();
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
                            'source' => 'upload',
                            'created_by' => $request->user()->id,
                            'updated_by' => $request->user()->id,
                        ]
                    );
                    $saved++;
                }
            }
        });
        app(HrdAuditLogService::class)->record(
            $request,
            'Jadwal Karyawan',
            'updated',
            "Upload {$validated['department']} {$start->toDateString()} - {$end->toDateString()}",
            ['saved' => 0, 'skipped' => 0],
            ['saved' => $saved, 'skipped' => $skipped],
            EmployeeDailySchedule::class,
            $validated['department']
        );

        return response()->json([
            'message' => "Upload jadwal selesai. Tersimpan: {$saved}; dilewati: {$skipped}.",
        ]);
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

    private function departmentEmployeeNiks(string $department): Collection
    {
        return $this->departmentEmployees($department)->pluck('nik');
    }

    private function departmentEmployees(string $department): Collection
    {
        return Karyawan::query()
            ->where(function ($query) use ($department) {
                if ($department === 'Tanpa Departemen') {
                    $query->where(function ($inner) {
                        $inner->whereNull('departement')->orWhere('departement', '');
                    })->where(function ($inner) {
                        $inner->whereNull('divisi')->orWhere('divisi', '');
                    });
                } else {
                    $query->where('departement', $department)
                        ->orWhere(function ($inner) use ($department) {
                            $inner->where(function ($empty) {
                                $empty->whereNull('departement')->orWhere('departement', '');
                            })->where('divisi', $department);
                        });
                }
            })
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan']);
    }

    private function filterDepartments(Builder $query, array $departments): Builder
    {
        $withoutDepartment = in_array('Tanpa Departemen', $departments, true);
        $namedDepartments = array_values(array_diff($departments, ['Tanpa Departemen']));

        return $query->where(function (Builder $filter) use ($withoutDepartment, $namedDepartments): void {
            if ($namedDepartments !== []) {
                $filter->whereIn('departement', $namedDepartments)
                    ->orWhere(function (Builder $fallback) use ($namedDepartments): void {
                        $fallback->where(function (Builder $empty): void {
                            $empty->whereNull('departement')->orWhere('departement', '');
                        })->whereIn('divisi', $namedDepartments);
                    });
            }

            if ($withoutDepartment) {
                $method = $namedDepartments === [] ? 'where' : 'orWhere';
                $filter->{$method}(function (Builder $empty): void {
                    $empty->where(function (Builder $department): void {
                        $department->whereNull('departement')->orWhere('departement', '');
                    })->where(function (Builder $division): void {
                        $division->whereNull('divisi')->orWhere('divisi', '');
                    });
                });
            }
        });
    }

    private function serializeEmployee(Karyawan $employee): array
    {
        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'department' => $this->employeeDepartment($employee),
            'unit' => $employee->unit ?: '-',
        ];
    }

    private function employeeDepartment(Karyawan $employee): string
    {
        return $employee->departement ?: ($employee->divisi ?: 'Tanpa Departemen');
    }

    private function templateResponse(Collection $employees, Carbon $start, Carbon $end, string $filename): StreamedResponse
    {
        $dates = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->toDateString());
        $schedules = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employees->pluck('nik'))
            ->whereBetween('schedule_date', [$start, $end])
            ->get(['karyawan_nik', 'schedule_date', 'schedule_code'])
            ->keyBy(fn (EmployeeDailySchedule $schedule) => $schedule->karyawan_nik.'|'.$schedule->schedule_date->toDateString());

        return response()->streamDownload(function () use ($employees, $dates, $schedules): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['NIK', 'Nama Karyawan', ...$dates->all()]);

            foreach ($employees as $employee) {
                $row = [$employee->nik, $employee->nama_karyawan];
                foreach ($dates as $date) {
                    $row[] = $schedules->get($employee->nik.'|'.$date)?->schedule_code ?? '';
                }
                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
