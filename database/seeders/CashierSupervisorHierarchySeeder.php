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
            if ($leader->nama_atasan_langsung !== $supervisor->nama_karyawan) {
                $leader->update(['nama_atasan_langsung' => $supervisor->nama_karyawan]);
            }
        }

        $cashiers = Karyawan::query()
            ->where('departement', 'Sales')
            ->where('jabatan', 'Cashier')
            ->orderBy('nik')
            ->get();

        foreach ($cashiers as $index => $cashier) {
            $updates = [];
            if (blank($cashier->nama_atasan_langsung) || $cashier->nama_atasan_langsung === '0') {
                $updates['nama_atasan_langsung'] = $leaders[$index % $leaders->count()]->nama_karyawan;
            }
            if (blank($cashier->atasan_tidak_langsung) || $cashier->atasan_tidak_langsung === '0') {
                $updates['atasan_tidak_langsung'] = $supervisor->nama_karyawan;
            }
            if ($updates) {
                $cashier->update($updates);
            }
        }

        $this->command?->info("Pemetaan jadwal Kasir selesai untuk {$cashiers->count()} karyawan.");
    }
}
