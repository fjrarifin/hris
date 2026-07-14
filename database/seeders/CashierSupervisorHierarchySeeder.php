<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use Illuminate\Database\Seeder;

class CashierSupervisorHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $supervisor = Karyawan::query()
            ->where('nama_karyawan', 'SYIFA HAPIPAH')
            ->where('jabatan', 'like', '%SPV%Kasir%')
            ->first();

        if (! $supervisor) {
            $this->command?->warn('SPV Kasir SYIFA HAPIPAH tidak ditemukan; pemetaan Kasir dilewati.');

            return;
        }

        $leaders = Karyawan::query()
            ->where('departement', 'Sales')
            ->where('jabatan', 'like', '%Leader%Cashier%')
            ->orderBy('nik')
            ->get();

        if ($leaders->isEmpty()) {
            $this->command?->warn('Leader Cashier Sales tidak ditemukan; pemetaan Kasir dilewati.');

            return;
        }

        foreach ($leaders as $leader) {
            if ($leader->atasan_langsung_nik !== $supervisor->nik) {
                $leader->update([
                    'nama_atasan_langsung' => $supervisor->nama_karyawan,
                    'atasan_langsung_nik' => $supervisor->nik,
                ]);
            }
        }

        $cashiers = Karyawan::query()
            ->where('departement', 'Sales')
            ->where('jabatan', 'Cashier')
            ->orderBy('nik')
            ->get();

        foreach ($cashiers as $index => $cashier) {
            $updates = [];
            $leader = $leaders[$index % $leaders->count()];
            if (blank($cashier->atasan_langsung_nik) || $cashier->atasan_langsung_nik === '0') {
                $updates['nama_atasan_langsung'] = $leader->nama_karyawan;
                $updates['atasan_langsung_nik'] = $leader->nik;
            }
            if (blank($cashier->atasan_tidak_langsung_nik) || $cashier->atasan_tidak_langsung_nik === '0') {
                $updates['atasan_tidak_langsung'] = $supervisor->nama_karyawan;
                $updates['atasan_tidak_langsung_nik'] = $supervisor->nik;
            }
            if ($updates) {
                $cashier->update($updates);
            }
        }

        $this->command?->info("Pemetaan jadwal Kasir selesai untuk {$cashiers->count()} karyawan.");
    }
}
