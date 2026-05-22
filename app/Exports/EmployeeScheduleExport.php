<?php

namespace App\Exports;

use App\Models\EmployeeDailySchedule;
use App\Models\Karyawan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeScheduleExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    private Collection $dates;

    public function __construct(
        private readonly Carbon $start,
        private readonly Carbon $end,
        private readonly ?string $q = null
    ) {
        $this->dates = collect(CarbonPeriod::create($this->start, $this->end))
            ->map(fn (Carbon $date) => $date->copy());
    }

    public function headings(): array
    {
        return collect(['NIK', 'Nama Karyawan'])
            ->merge($this->dates->map(fn (Carbon $date) => $date->format('Y-m-d')))
            ->all();
    }

    public function collection(): Collection
    {
        $employees = Karyawan::query()
            ->when($this->q, function ($query, $q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('nik', 'like', "%{$q}%")
                        ->orWhere('nama_karyawan', 'like', "%{$q}%")
                        ->orWhere('jabatan', 'like', "%{$q}%")
                        ->orWhere('departement', 'like', "%{$q}%")
                        ->orWhere('unit', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan']);

        $schedules = EmployeeDailySchedule::query()
            ->whereIn('karyawan_nik', $employees->pluck('nik'))
            ->whereBetween('schedule_date', [$this->start->toDateString(), $this->end->toDateString()])
            ->get()
            ->groupBy(fn (EmployeeDailySchedule $schedule) => $schedule->karyawan_nik . '|' . $schedule->schedule_date->format('Y-m-d'));

        return $employees->map(function (Karyawan $employee) use ($schedules) {
            $row = [$employee->nik, $employee->nama_karyawan];

            foreach ($this->dates as $date) {
                $key = $employee->nik . '|' . $date->format('Y-m-d');
                $row[] = optional($schedules->get($key)?->first())->schedule_code;
            }

            return $row;
        });
    }
}
