<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PengajuanAtkSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $data = [
            [
                'request_no' => 'ATK-2026-001',
                'nik' => 'HPP25120147', // sesuaikan dengan NIK yang ada di m_karyawan
                'nama_barang' => 'Pulpen Hitam Standard',
                'qty' => 10,
                'satuan' => 'pcs',
                'keterangan' => 'Untuk kebutuhan administrasi harian',
                'tanggal_pengajuan' => $now->copy()->subDays(5)->format('Y-m-d'),
                'status' => 'APPROVED',
                'approved_by' => 'HR Manager',
                'approved_at' => $now->copy()->subDays(3)->format('Y-m-d H:i:s'),
                'rejected_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'request_no' => 'ATK-2026-002',
                'nik' => 'HPP25120147',
                'nama_barang' => 'Kertas A4 80 gram',
                'qty' => 5,
                'satuan' => 'rim',
                'keterangan' => 'Untuk kebutuhan printing dokumen',
                'tanggal_pengajuan' => $now->copy()->subDays(3)->format('Y-m-d'),
                'status' => 'SUBMIT',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'request_no' => 'ATK-2026-003',
                'nik' => 'HPP25120147',
                'nama_barang' => 'Spidol Whiteboard',
                'qty' => 6,
                'satuan' => 'pcs',
                'keterangan' => 'Untuk kebutuhan meeting rutin',
                'tanggal_pengajuan' => $now->copy()->subDays(1)->format('Y-m-d'),
                'status' => 'SUBMIT',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'request_no' => 'ATK-2026-004',
                'nik' => 'HPP25120147',
                'nama_barang' => 'Stabilo Warna',
                'qty' => 2,
                'satuan' => 'pack',
                'keterangan' => 'Untuk marking dokumen penting',
                'tanggal_pengajuan' => $now->copy()->subDays(7)->format('Y-m-d'),
                'status' => 'REJECTED',
                'approved_by' => 'HR Manager',
                'approved_at' => $now->copy()->subDays(6)->format('Y-m-d H:i:s'),
                'rejected_reason' => 'Stok masih tersedia di gudang',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'request_no' => 'ATK-2026-005',
                'nik' => 'HPP25120147',
                'nama_barang' => 'Binder Clip Besar',
                'qty' => 3,
                'satuan' => 'box',
                'keterangan' => 'Untuk arsip dokumen bulanan',
                'tanggal_pengajuan' => $now->format('Y-m-d'),
                'status' => 'SUBMIT',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('pengajuan_atk')->insert($data);
    }
}
