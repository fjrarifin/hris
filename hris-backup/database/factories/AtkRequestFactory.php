<?php

namespace Database\Factories;

use App\Models\AtkRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class AtkRequestFactory extends Factory
{
    protected $model = AtkRequest::class;

    public function definition(): array
    {
        $barang = [
            'Pulpen Hitam',
            'Pulpen Biru',
            'Spidol Board Marker',
            'Kertas A4 80gsm',
            'Map Plastik',
            'Staples',
            'Penghapus',
            'Pensil 2B',
            'Lakban Bening',
            'Paper Clip',
        ];

        $status = $this->faker->randomElement(['SUBMIT', 'APPROVED', 'REJECTED']);

        return [
            'request_no' => 'ATK-' . now()->format('Ymd') . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'nik' => $this->faker->randomElement(['3201010101010001', '3201010101010002', '3201010101010003']),
            'nama_barang' => $this->faker->randomElement($barang),
            'qty' => $this->faker->numberBetween(1, 5),
            'satuan' => $this->faker->randomElement(['pcs', 'box', 'pack']),
            'keterangan' => $this->faker->sentence(6),
            'tanggal_pengajuan' => now()->subDays(rand(0, 10))->toDateString(),
            'status' => $status,
        ];
    }
}
