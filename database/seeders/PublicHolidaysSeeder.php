<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PublicHoliday;

class PublicHolidaysSeeder extends Seeder
{
    public function run()
    {
        $holidays = [
            ['name' => 'Tahun Baru Masehi',                'holiday_date' => '2026-01-01'],
            ['name' => 'Isra Mi’raj Nabi Muhammad',         'holiday_date' => '2026-01-16'],
            ['name' => 'Tahun Baru Imlek',                  'holiday_date' => '2026-02-17'],
            ['name' => 'Hari Suci Nyepi',                   'holiday_date' => '2026-03-19'],
            ['name' => 'Hari Raya Idul Fitri',              'holiday_date' => '2026-03-20'],
            ['name' => 'Hari Raya Idul Fitri (Hari Kedua)', 'holiday_date' => '2026-03-21'],
            ['name' => 'Wafat Yesus Kristus',               'holiday_date' => '2026-04-03'],
            ['name' => 'Kebangkitan Yesus Kristus',         'holiday_date' => '2026-04-05'],
            ['name' => 'Hari Buruh Internasional',          'holiday_date' => '2026-05-01'],
            ['name' => 'Kenaikan Yesus Kristus',            'holiday_date' => '2026-05-14'],
            ['name' => 'Hari Raya Idul Adha',               'holiday_date' => '2026-05-27'],
            ['name' => 'Hari Raya Waisak',                  'holiday_date' => '2026-05-31'],
            ['name' => 'Hari Lahir Pancasila',              'holiday_date' => '2026-06-01'],
            ['name' => 'Tahun Baru Islam',                  'holiday_date' => '2026-06-16'],
            ['name' => 'Hari Kemerdekaan RI',               'holiday_date' => '2026-08-17'],
            ['name' => 'Maulid Nabi Muhammad',              'holiday_date' => '2026-08-25'],
            ['name' => 'Hari Raya Natal',                   'holiday_date' => '2026-12-25'],
        ];

        foreach ($holidays as $holiday) {
            PublicHoliday::create([
                'name' => $holiday['name'],
                'holiday_date' => $holiday['holiday_date'],
                'year' => date('Y', strtotime($holiday['holiday_date'])),
                'is_active' => true,
            ]);
        }
    }
}
