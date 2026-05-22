<?php

namespace App\Http\Controllers\HR;

use App\Exports\EmployeeScheduleExport;
use App\Http\Controllers\Controller;
use App\Imports\RawRowsImport;
use App\Models\AttendanceScheduleCategory;
use App\Models\EmployeeDailySchedule;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeScheduleController extends Controller
{
    public function index(Request $request)
    {
        [$startDate, $endDate, $q] = $this->filters($request);
        [$start, $end] = $this->dateRange($startDate, $endDate);
        $employees = $this->employeeQuery($q)->get();
        $categories = AttendanceScheduleCategory::query()
            ->where('is_active', true)
            ->orderByRaw("CASE type WHEN 'work' THEN 1 WHEN 'off' THEN 2 WHEN 'leave' THEN 3 WHEN 'public_holiday' THEN 4 ELSE 5 END")
            ->orderBy('start_time')
            ->orderBy('code')
            ->get();

        $employeeNiks = $employees->pluck('nik');
        $scheduleCounts = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employeeNiks)
            ->whereBetween('schedule_date', [$start->toDateString(), $end->toDateString()])
            ->select('karyawan_nik', DB::raw('count(*) as total'))
            ->groupBy('karyawan_nik')
            ->pluck('total', 'karyawan_nik');

        return view('hr.schedules.employees', compact(
            'startDate',
            'endDate',
            'q',
            'employees',
            'categories',
            'scheduleCounts'
        ));
    }

    public function show(Request $request, string $nik)
    {
        [$startDate, $endDate, $q] = $this->filters($request);
        [$start, $end] = $this->dateRange($startDate, $endDate);
        $dates = $this->dates($start, $end);
        $employee = Karyawan::query()
            ->where('nik', $nik)
            ->firstOrFail(['nik', 'nama_karyawan', 'jabatan', 'departement', 'unit']);
        $categories = AttendanceScheduleCategory::query()
            ->where('is_active', true)
            ->orderByRaw("CASE type WHEN 'work' THEN 1 WHEN 'off' THEN 2 WHEN 'leave' THEN 3 WHEN 'public_holiday' THEN 4 ELSE 5 END")
            ->orderBy('start_time')
            ->orderBy('code')
            ->get();
        $schedules = EmployeeDailySchedule::with('category')
            ->where('karyawan_nik', $employee->nik)
            ->whereBetween('schedule_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (EmployeeDailySchedule $schedule) => $schedule->schedule_date->format('Y-m-d'));

        return view('hr.schedules.employee-detail', compact(
            'startDate',
            'endDate',
            'q',
            'dates',
            'employee',
            'categories',
            'schedules'
        ));
    }

    public function store(Request $request)
    {
        [$startDate, $endDate] = $this->validatedPeriod($request);
        [$start, $end] = $this->dateRange($startDate, $endDate);
        $categoryMap = $this->categoryMap();
        $payload = $request->input('schedules', []);
        $saved = 0;
        $deleted = 0;

        DB::transaction(function () use ($payload, $start, $end, $categoryMap, &$saved, &$deleted) {
            foreach ($payload as $nik => $dateItems) {
                foreach ((array) $dateItems as $date => $code) {
                    if (! $this->validDateInRange($date, $start, $end)) {
                        continue;
                    }

                    $code = strtoupper(trim((string) $code));

                    if ($code === '') {
                        $deleted += EmployeeDailySchedule::query()
                            ->where('karyawan_nik', $nik)
                            ->whereDate('schedule_date', $date)
                            ->delete();
                        continue;
                    }

                    $category = $categoryMap->get($code);

                    if (! $category) {
                        continue;
                    }

                    EmployeeDailySchedule::updateOrCreate(
                        [
                            'karyawan_nik' => $nik,
                            'schedule_date' => $date,
                        ],
                        [
                            'schedule_category_id' => $category->id,
                            'schedule_code' => $category->code,
                            'source' => 'manual',
                            'updated_by' => Auth::id(),
                            'created_by' => Auth::id(),
                        ]
                    );

                    $saved++;
                }
            }
        });

        return back()->with('success', "Jadwal berhasil disimpan. Tersimpan: {$saved}, dikosongkan: {$deleted}.");
    }

    public function upload(Request $request)
    {
        [$startDate, $endDate] = $this->validatedPeriod($request);
        [$start, $end] = $this->dateRange($startDate, $endDate);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $rows = collect(Excel::toArray(new RawRowsImport(), $request->file('file'))[0] ?? []);
        $header = collect($rows->shift() ?? [])->map(fn ($value) => trim((string) $value));
        $dateColumns = $this->dateColumns($header, $start, $end);
        $categoryMap = $this->categoryMap();
        $employeeNiks = Karyawan::pluck('nik')->map(fn ($nik) => (string) $nik)->flip();
        $summary = ['saved' => 0, 'skipped' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, $dateColumns, $categoryMap, $employeeNiks, &$summary) {
            foreach ($rows as $rowIndex => $row) {
                $nik = trim((string) ($row[0] ?? ''));

                if ($nik === '') {
                    continue;
                }

                if (! $employeeNiks->has($nik)) {
                    $summary['skipped']++;
                    $summary['errors'][] = 'Baris ' . ($rowIndex + 2) . ": NIK {$nik} tidak ditemukan.";
                    continue;
                }

                foreach ($dateColumns as $columnIndex => $date) {
                    $code = strtoupper(trim((string) ($row[$columnIndex] ?? '')));

                    if ($code === '') {
                        continue;
                    }

                    $category = $categoryMap->get($code);

                    if (! $category) {
                        $summary['skipped']++;
                        $summary['errors'][] = 'Baris ' . ($rowIndex + 2) . ", tanggal {$date}: kode {$code} tidak terdaftar.";
                        continue;
                    }

                    EmployeeDailySchedule::updateOrCreate(
                        [
                            'karyawan_nik' => $nik,
                            'schedule_date' => $date,
                        ],
                        [
                            'schedule_category_id' => $category->id,
                            'schedule_code' => $category->code,
                            'source' => 'upload',
                            'updated_by' => Auth::id(),
                            'created_by' => Auth::id(),
                        ]
                    );

                    $summary['saved']++;
                }
            }
        });

        $message = "Upload jadwal selesai. Tersimpan: {$summary['saved']}, dilewati: {$summary['skipped']}.";

        return back()->with('success', $message)->with('upload_errors', array_slice($summary['errors'], 0, 10));
    }

    public function template(Request $request)
    {
        [$startDate, $endDate, $q] = $this->filters($request);
        [$start, $end] = $this->dateRange($startDate, $endDate);

        return Excel::download(
            new EmployeeScheduleExport($start, $end, $q),
            'Template_Jadwal_Karyawan_' . $start->format('Ymd') . '_' . $end->format('Ymd') . '.xlsx'
        );
    }

    public function export(Request $request)
    {
        return $this->template($request);
    }

    private function filters(Request $request): array
    {
        $defaultStart = $this->defaultPeriodStart();
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            $data['start_date'] ?? $defaultStart->toDateString(),
            $data['end_date'] ?? $defaultStart->copy()->addMonthNoOverflow()->subDay()->toDateString(),
            trim((string) ($data['q'] ?? '')),
        ];
    }

    private function validatedPeriod(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return [$data['start_date'], $data['end_date']];
    }

    private function dateRange(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($start->diffInDays($end) > 45) {
            throw ValidationException::withMessages([
                'end_date' => 'Periode jadwal maksimal 46 hari.',
            ]);
        }

        return [$start, $end];
    }

    private function dates(Carbon $start, Carbon $end)
    {
        return collect(CarbonPeriod::create($start, $end))->map(fn (Carbon $date) => $date->copy());
    }

    private function defaultPeriodStart(): Carbon
    {
        $today = now();

        return $today->day >= 25
            ? $today->copy()->day(25)
            : $today->copy()->subMonthNoOverflow()->day(25);
    }

    private function employeeQuery(string $q): Builder
    {
        return Karyawan::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('nik', 'like', "%{$q}%")
                        ->orWhere('nama_karyawan', 'like', "%{$q}%")
                        ->orWhere('jabatan', 'like', "%{$q}%")
                        ->orWhere('departement', 'like', "%{$q}%")
                        ->orWhere('unit', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama_karyawan')
            ->select(['nik', 'nama_karyawan', 'jabatan', 'departement', 'unit']);
    }

    private function categoryMap()
    {
        return AttendanceScheduleCategory::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('code');
    }

    private function validDateInRange(string $date, Carbon $start, Carbon $end): bool
    {
        try {
            $parsed = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $parsed->betweenIncluded($start, $end);
    }

    private function dateColumns(Collection $header, Carbon $start, Carbon $end): array
    {
        $columns = [];

        foreach ($header as $index => $value) {
            if ($index < 2) {
                continue;
            }

            $date = $this->parseDateHeader($value);

            if ($date && Carbon::parse($date)->betweenIncluded($start, $end)) {
                $columns[$index] = $date;
            }
        }

        return $columns;
    }

    private function parseDateHeader($value): ?string
    {
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
