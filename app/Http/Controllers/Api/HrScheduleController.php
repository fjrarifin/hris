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

class HrScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $employees = Karyawan::query()
            ->orderBy('departement')
            ->orderBy('nama_karyawan')
            ->get(['nik', 'departement', 'divisi']);
        $counts = EmployeeDailySchedule::query()
            ->whereBetween('schedule_date', [$start, $end])
            ->select('karyawan_nik', DB::raw('count(*) as total'))
            ->groupBy('karyawan_nik')
            ->pluck('total', 'karyawan_nik');

        $departments = $employees
            ->groupBy(fn (Karyawan $employee) => $employee->departement ?: ($employee->divisi ?: 'Tanpa Departemen'))
            ->map(fn (Collection $items, string $department) => [
                'department' => $department,
                'employee_count' => $items->count(),
                'scheduled_days' => $items->sum(fn (Karyawan $employee) => (int) ($counts[$employee->nik] ?? 0)),
            ])
            ->values();

        return response()->json([
            'filters' => $this->serializedPeriod($start, $end),
            'departments' => $departments,
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
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'unit']);
        $counts = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employees->pluck('nik'))
            ->whereBetween('schedule_date', [$start, $end])
            ->select('karyawan_nik', DB::raw('count(*) as total'))
            ->groupBy('karyawan_nik')
            ->pluck('total', 'karyawan_nik');

        return response()->json([
            'department' => $department,
            'employees' => $employees->map(fn (Karyawan $employee) => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                'unit' => $employee->unit ?: '-',
                'scheduled_days' => (int) ($counts[$employee->nik] ?? 0),
            ]),
        ]);
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

        return response()->json([
            'message' => "Jadwal tersimpan: {$saved}; dikosongkan: {$cleared}.",
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        [$start, $end] = $this->period($request);
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
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
            ->pluck('nik');
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
