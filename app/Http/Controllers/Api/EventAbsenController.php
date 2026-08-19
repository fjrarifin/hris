<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventAbsen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventAbsenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $query = EventAbsen::query()
            ->with(['creator:id,name,username'])
            ->withCount('absensiEvents')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('nama_event', 'like', "%{$search}%")
                        ->orWhere('deskripsi', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($q, $status) {
                if ($status === 'aktif') {
                    $q->where('status', 'aktif')->where('tanggal_selesai', '>=', now());
                } elseif ($status === 'kadaluarsa') {
                    $q->where('tanggal_selesai', '<', now());
                } elseif ($status === 'nonaktif') {
                    $q->where('status', 'nonaktif');
                }
            })
            ->orderByDesc('created_at');

        $events = $query->paginate($perPage);

        return response()->json([
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'summary' => [
                'total_events' => EventAbsen::count(),
                'active_events' => EventAbsen::where('status', 'aktif')->where('tanggal_selesai', '>=', now())->count(),
                'total_attendances' => \App\Models\AbsensiEvent::count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_event' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:event_absen,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'nama_event.required' => 'Nama event wajib diisi.',
            'slug.unique' => 'Slug / link event sudah digunakan. Pilih slug lain.',
            'slug.regex' => 'Format slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'tanggal_mulai.required' => 'Tanggal & jam mulai wajib ditentukan.',
            'tanggal_selesai.required' => 'Tanggal & jam selesai wajib ditentukan.',
            'tanggal_selesai.after' => 'Tanggal & jam selesai harus setelah tanggal mulai.',
        ]);

        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['nama_event']);
            $slug = $baseSlug ?: 'event';
            $counter = 1;
            while (EventAbsen::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $validated['status'] = $validated['status'] ?? 'aktif';
        $validated['created_by'] = $request->user()?->id;

        $event = EventAbsen::create($validated);
        $event->load(['creator:id,name,username'])->loadCount('absensiEvents');

        return response()->json([
            'message' => 'Event absensi berhasil dibuat.',
            'data' => $event,
        ], 201);
    }

    public function show(EventAbsen $eventAbsen): JsonResponse
    {
        $eventAbsen->load(['creator:id,name,username'])->loadCount('absensiEvents');

        $participants = $eventAbsen->absensiEvents()
            ->with(['karyawan:nik,nama_karyawan,jabatan,divisi,departement'])
            ->orderByDesc('jam_absen')
            ->get();

        return response()->json([
            'data' => $eventAbsen,
            'participants' => $participants,
        ]);
    }

    public function update(Request $request, EventAbsen $eventAbsen): JsonResponse
    {
        $validated = $request->validate([
            'nama_event' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('event_absen', 'slug')->ignore($eventAbsen->id),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'nama_event.required' => 'Nama event wajib diisi.',
            'slug.required' => 'Slug / link event wajib diisi.',
            'slug.unique' => 'Slug / link event sudah digunakan.',
            'slug.regex' => 'Format slug hanya boleh huruf kecil, angka, dan tanda hubung (-).',
            'tanggal_mulai.required' => 'Tanggal & jam mulai wajib ditentukan.',
            'tanggal_selesai.required' => 'Tanggal & jam selesai wajib ditentukan.',
            'tanggal_selesai.after' => 'Tanggal & jam selesai harus setelah tanggal mulai.',
        ]);

        $eventAbsen->update($validated);
        $eventAbsen->load(['creator:id,name,username'])->loadCount('absensiEvents');

        return response()->json([
            'message' => 'Event absensi berhasil diperbarui.',
            'data' => $eventAbsen,
        ]);
    }

    public function destroy(EventAbsen $eventAbsen): JsonResponse
    {
        $eventAbsen->delete();

        return response()->json([
            'message' => 'Event absensi berhasil dihapus.',
        ]);
    }

    public function export(EventAbsen $eventAbsen): StreamedResponse
    {
        $participants = $eventAbsen->absensiEvents()
            ->with(['karyawan:nik,nama_karyawan,jabatan,divisi,departement'])
            ->orderBy('jam_absen')
            ->get();

        $fileName = sprintf('rekap-absen-%s-%s.csv', $eventAbsen->slug, now()->format('Ymd_His'));

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($eventAbsen, $participants) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Event Metadata
            fputcsv($handle, ['NAMA EVENT', $eventAbsen->nama_event]);
            fputcsv($handle, ['SLUG / LINK', $eventAbsen->slug]);
            fputcsv($handle, ['PERIODE', $eventAbsen->tanggal_mulai?->format('d/m/Y H:i').' s/d '.$eventAbsen->tanggal_selesai?->format('d/m/Y H:i')]);
            fputcsv($handle, ['TOTAL PESERTA HADIR', $participants->count()]);
            fputcsv($handle, []);

            // Table Headers
            fputcsv($handle, [
                'No',
                'NIK Karyawan',
                'Nama Lengkap',
                'Jabatan',
                'Divisi',
                'Departemen',
                'Waktu Absen (WIB)',
                'URL Foto Selfie',
                'IP Address',
                'Device / User Agent',
            ]);

            $no = 1;
            foreach ($participants as $item) {
                $karyawan = $item->karyawan;
                fputcsv($handle, [
                    $no++,
                    $item->nik_karyawan,
                    $karyawan?->nama_karyawan ?? '-',
                    $karyawan?->jabatan ?? '-',
                    $karyawan?->divisi ?? '-',
                    $karyawan?->departement ?? '-',
                    $item->jam_absen ? $item->jam_absen->format('Y-m-d H:i:s') : '-',
                    $item->foto_url ?? '-',
                    $item->ip_address ?? '-',
                    $item->user_agent ?? '-',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function photo(string $filename)
    {
        $path = 'absensi-event/'.$filename;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, $filename, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
