<?php

namespace Database\Seeders;

use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Services\PayrollReviewService;
use App\Services\PayrollValidationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class May2026PayrollManualAdjustmentSeeder extends Seeder
{
    private const PERIOD_START = '2026-04-25';

    private const PERIOD_END = '2026-05-24';

    private const SHEET = 'PERHITUNGAN (2)';

    public function run(): void
    {
        $path = env('MAY_2026_PAYROLL_WORKBOOK', 'C:/Users/msi/Downloads/Data Gaji Mei Untuk HRIS.xlsx');
        if (! is_file($path)) {
            throw new RuntimeException("Workbook payroll Mei 2026 tidak ditemukan: {$path}");
        }

        $this->call(PayrollComponentSeeder::class);

        $sheet = IOFactory::load($path)->getSheetByName(self::SHEET);
        if (! $sheet) {
            throw new RuntimeException('Sheet payroll Mei 2026 tidak ditemukan.');
        }

        $components = PayrollComponent::query()->get()->keyBy('nama');
        $review = app(PayrollReviewService::class);
        $validation = app(PayrollValidationService::class);
        $summary = ['payrolls' => 0, 'items' => 0, 'missing_payrolls' => []];

        DB::transaction(function () use ($sheet, $components, $review, $validation, &$summary): void {
            for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
                $nik = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());
                if (! preg_match('/^[A-Z]{3}\d+$/', $nik)) {
                    continue;
                }

                $payroll = Payroll::query()
                    ->with('items')
                    ->where('karyawan_nik', $nik)
                    ->whereDate('periode_start', self::PERIOD_START)
                    ->whereDate('periode_end', self::PERIOD_END)
                    ->first();

                if (! $payroll) {
                    $summary['missing_payrolls'][] = $nik;
                    continue;
                }

                $items = $this->items($sheet, $row);
                $componentIds = collect(array_keys($items))
                    ->map(fn (string $name) => $components->get($name)?->id)
                    ->filter()
                    ->values();

                $payroll->items()->whereIn('component_id', $componentIds)->delete();

                foreach ($items as $name => $item) {
                    if ($item['amount'] <= 0) {
                        continue;
                    }

                    $component = $components->get($name);
                    if (! $component) {
                        throw new RuntimeException("Komponen payroll belum tersedia: {$name}");
                    }

                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'component_id' => $component->id,
                        'nama_item' => $name,
                        'type' => $item['type'],
                        'amount' => $item['amount'],
                    ]);
                    $summary['items']++;
                }

                $review->refreshTotals($payroll);
                $validation->validateAndStore($payroll->fresh(['karyawan', 'items.component']));
                $summary['payrolls']++;
            }
        });

        $this->command?->info('Seed adjustment manual payroll Mei 2026 selesai: '.json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    private function items($sheet, int $row): array
    {
        $mapping = [
            'Lembur' => ['earning', 'AM'],
            'Nominal PIKET' => ['earning', 'AN'],
            'Lain-lain' => ['earning', 'AO'],
            'Training' => ['earning', 'AP'],
            'THR' => ['earning', 'AQ'],
            'Kekurangan Bulan Sebelumnya' => ['earning', 'AR'],
            'Tunjangan PPh21' => ['earning', 'AY'],
            'Service' => ['earning', 'BB'],
            'Bonus' => ['earning', 'BD'],
            'Potongan Kasbon' => ['deduction', 'AV'],
            'Kelebihan Gaji' => ['deduction', 'AW'],
            'Pot. Denda Kehilangan Aset' => ['deduction', 'AX'],
            'PPh21' => ['deduction', 'AY'],
        ];

        return collect($mapping)
            ->map(fn (array $config) => [
                'type' => $config[0],
                'amount' => $this->cellAmount($sheet, "{$config[1]}{$row}"),
            ])
            ->filter(fn (array $item) => $item['amount'] > 0)
            ->all();
    }

    private function cellAmount($sheet, string $cell): int
    {
        return (int) round((float) ($sheet->getCell($cell)->getCalculatedValue() ?: 0));
    }
}
