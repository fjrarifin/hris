<?php

namespace App\Exports;

use App\Models\Karyawan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class EmployeeExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, WithCustomValueBinder, ShouldAutoSize
{
    private array $columns;

    public function __construct()
    {
        $this->columns = Schema::getColumnListing((new Karyawan)->getTable());
    }

    public function collection(): Collection
    {
        return Karyawan::query()->orderBy('nik')->get();
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function map($employee): array
    {
        $attributes = $employee->getRawOriginal();

        return array_map(fn (string $column) => $attributes[$column] ?? null, $this->columns);
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
