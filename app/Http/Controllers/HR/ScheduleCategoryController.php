<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceScheduleCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleCategoryController extends Controller
{
    public function index()
    {
        $categories = AttendanceScheduleCategory::query()
            ->orderByRaw("CASE type WHEN 'work' THEN 1 WHEN 'off' THEN 2 WHEN 'leave' THEN 3 WHEN 'public_holiday' THEN 4 ELSE 5 END")
            ->orderBy('start_time')
            ->orderBy('code')
            ->get();

        return view('hr.schedules.categories', [
            'categories' => $categories,
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);
        AttendanceScheduleCategory::create($payload);

        return back()->with('success', 'Kategori jadwal berhasil ditambahkan.');
    }

    public function update(Request $request, AttendanceScheduleCategory $scheduleCategory)
    {
        $payload = $this->validatedPayload($request, $scheduleCategory);
        $scheduleCategory->update($payload);

        return back()->with('success', 'Kategori jadwal berhasil diperbarui.');
    }

    public function destroy(AttendanceScheduleCategory $scheduleCategory)
    {
        $scheduleCategory->delete();

        return back()->with('success', 'Kategori jadwal berhasil dihapus.');
    }

    private function validatedPayload(Request $request, ?AttendanceScheduleCategory $category = null): array
    {
        $payload = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('attendance_schedule_categories', 'code')->ignore($category?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys($this->typeOptions()))],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $payload['code'] = strtoupper(trim($payload['code']));
        $payload['is_active'] = $request->boolean('is_active', true);
        $payload['is_workday'] = $payload['type'] === 'work';

        if ($payload['type'] !== 'work') {
            $payload['start_time'] = null;
            $payload['end_time'] = null;
        }

        return $payload;
    }

    private function typeOptions(): array
    {
        return [
            'work' => 'Jadwal Kerja',
            'off' => 'Libur',
            'leave' => 'Cuti',
            'public_holiday' => 'Public Holiday',
        ];
    }
}
