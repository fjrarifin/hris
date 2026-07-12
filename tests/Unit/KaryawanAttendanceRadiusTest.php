<?php

namespace Tests\Unit;

use App\Models\Karyawan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KaryawanAttendanceRadiusTest extends TestCase
{
    #[DataProvider('positions')]
    public function test_attendance_radius_requirement_by_position(array $attributes, bool $expected): void
    {
        $employee = new Karyawan($attributes);

        $this->assertSame($expected, $employee->requiresAttendanceRadius());
    }

    public static function positions(): array
    {
        return [
            'staff' => [['posisi_title' => 'Staff'], true],
            'supervisor' => [['posisi_title' => 'Supervisor'], true],
            'assistant manager' => [['posisi_title' => 'Asst. Manager'], true],
            'manager' => [['posisi_title' => 'Manager'], false],
            'manager case and whitespace' => [['posisi_title' => '  MANAGER  '], false],
            'general manager abbreviation' => [['posisi_title' => 'GM'], false],
            'general manager full title' => [['posisi_title' => 'General Manager'], false],
            'legacy jabatan fallback' => [['jabatan' => 'Manager'], false],
            'legacy posisi fallback' => [['posisi' => 'GM'], false],
            'structured title takes precedence' => [[
                'posisi_title' => 'Supervisor',
                'jabatan' => 'Manager',
            ], true],
            'missing position' => [[], true],
        ];
    }
}
