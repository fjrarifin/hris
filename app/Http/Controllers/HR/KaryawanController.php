<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class KaryawanController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->q;

        $karyawan = Karyawan::with('user')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('nik', 'like', "%{$q}%")
                        ->orWhere('nama_karyawan', 'like', "%{$q}%")
                        ->orWhere('jabatan', 'like', "%{$q}%")
                        ->orWhere('divisi', 'like', "%{$q}%");
                });
            })
            ->orderBy('nik')
            ->get();

        return view('hr.karyawan.index', compact('karyawan', 'q'));
    }

    public function create()
    {
        return view('hr.karyawan.form', [
            'mode' => 'create',
            'data' => new Karyawan,
            'kontrak' => collect(),
            'kontrakBerjalan' => collect(),
            'kontrakSelesai' => collect(),
            'kontrakAktif' => null,
            'nextKontrakKe' => 1,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->mergePositionPayload($request);

        $request->validate([
            'nik' => ['required', 'string', 'max:30', 'unique:m_karyawan,nik'],
            'pin' => ['nullable', 'string', 'max:50'],
            'nama_karyawan' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'posisi_level' => ['nullable', Rule::in($this->positionLevels())],
            'posisi_title' => ['nullable', Rule::in($this->positionTitles())],
            'divisi' => ['nullable', Rule::in($this->divisionOptions())],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'nama_atasan_langsung' => ['nullable', 'string', 'max:150'],
            'atasan_tidak_langsung' => ['nullable', 'string', 'max:150'],
            'status_kontrak' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'durasi_kontrak' => ['nullable', 'numeric'],
            'end_date' => ['nullable', 'date'],
            'total_masa_kerja' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'bank' => ['nullable', 'string', 'max:100'],
            'bpjs' => ['nullable', 'boolean'],
            'no_bpjs' => ['nullable', 'string', 'max:50'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'tanggal_lahir' => ['nullable', 'date'],
            'no_ktp' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'npwp' => ['nullable', 'boolean'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'status_pernikahan' => ['nullable', 'string', 'max:50'],
            'agama' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            'nama_institusi' => ['nullable', 'string', 'max:150'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'nama_pasangan' => ['nullable', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0'],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'nama_ibu' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat_no_hp' => ['nullable', 'string', 'max:30'],
        ]);

        $payload = $request->all();
        $payload['bpjs'] = $request->boolean('bpjs');
        $payload['npwp'] = $request->boolean('npwp');

        Karyawan::create($payload);

        return redirect()->route('hr.karyawan.index')->with('success', 'Karyawan berhasil ditambahkan');
    }

    public function edit($nik)
    {
        $data = Karyawan::with('user')->where('nik', $nik)->firstOrFail();

        $kontrak = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->orderByDesc('kontrak_ke')
            ->get();

        $today = now()->toDateString();
        $isKontrakSelesai = fn ($item) => in_array(strtoupper((string) $item->status_kontrak), ['SELESAI', 'HABIS', 'EXPIRED', 'NONAKTIF'], true)
            || ($item->end_date && $item->end_date < $today);
        $isKontrakBerjalan = fn ($item) => ! $isKontrakSelesai($item)
            && (
                strtoupper((string) $item->status_kontrak) === 'AKTIF'
                || (
                    $item->start_date
                    && $item->start_date <= $today
                    && (! $item->end_date || $item->end_date >= $today)
                )
            );

        $kontrakBerjalan = $kontrak->filter($isKontrakBerjalan)->values();
        $kontrakSelesai = $kontrak->filter($isKontrakSelesai)->values();

        return view('hr.karyawan.form', [
            'mode' => 'edit',
            'data' => $data,
            'kontrak' => $kontrak,
            'kontrakBerjalan' => $kontrakBerjalan,
            'kontrakSelesai' => $kontrakSelesai,
            'kontrakAktif' => $kontrakBerjalan->first(),
            'nextKontrakKe' => ((int) $kontrak->max('kontrak_ke')) + 1,
            ...$this->formOptions($nik),
        ]);
    }

    public function update(Request $request, $nik)
    {
        $data = Karyawan::with('user')->where('nik', $nik)->firstOrFail();
        $this->mergePositionPayload($request);

        $request->validate([
            'pin' => ['nullable', 'string', 'max:50'],
            'nama_karyawan' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'posisi' => ['nullable', 'string', 'max:100'],
            'posisi_level' => ['nullable', Rule::in($this->positionLevels())],
            'posisi_title' => ['nullable', Rule::in($this->positionTitles())],
            'divisi' => ['nullable', Rule::in($this->divisionOptions())],
            'departement' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'nama_atasan_langsung' => ['nullable', 'string', 'max:150'],
            'atasan_tidak_langsung' => ['nullable', 'string', 'max:150'],
            'status_kontrak' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore(optional($data->user)->id),
            ],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'bank' => ['nullable', 'string', 'max:100'],
            'bpjs' => ['nullable', 'boolean'],
            'no_bpjs' => ['nullable', 'string', 'max:50'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'tanggal_lahir' => ['nullable', 'date'],
            'no_ktp' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'npwp' => ['nullable', 'boolean'],
            'no_npwp' => ['nullable', 'string', 'max:30'],
            'status_pernikahan' => ['nullable', 'string', 'max:50'],
            'agama' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            'nama_institusi' => ['nullable', 'string', 'max:150'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'nama_pasangan' => ['nullable', 'string', 'max:150'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0'],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'nama_ibu' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_nama' => ['nullable', 'string', 'max:150'],
            'kontak_darurat_hubungan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat_no_hp' => ['nullable', 'string', 'max:30'],
        ]);

        $payload = $request->only([
            'pin',
            'nama_karyawan',
            'jabatan',
            'posisi',
            'posisi_level',
            'posisi_title',
            'divisi',
            'departement',
            'unit',
            'nama_atasan_langsung',
            'atasan_tidak_langsung',
            'status_kontrak',
            'join_date',
            'no_hp',
            'email',
            'no_rekening',
            'bank',
            'bpjs',
            'no_bpjs',
            'jenis_kelamin',
            'golongan_darah',
            'tanggal_lahir',
            'no_ktp',
            'tempat_lahir',
            'alamat',
            'npwp',
            'no_npwp',
            'status_pernikahan',
            'agama',
            'kewarganegaraan',
            'pendidikan_terakhir',
            'nama_institusi',
            'jurusan',
            'nama_pasangan',
            'jumlah_anak',
            'nama_ayah',
            'nama_ibu',
            'kontak_darurat_nama',
            'kontak_darurat_hubungan',
            'kontak_darurat_no_hp',
        ]);

        $payload['email'] = $payload['email'] ? strtolower(trim($payload['email'])) : null;
        $payload['bpjs'] = $request->boolean('bpjs');
        $payload['npwp'] = $request->boolean('npwp');

        $data->update($payload);

        if ($data->user) {
            $userPayload = ['name' => $data->nama_karyawan];

            if ($data->email) {
                $userPayload['email'] = $data->email;
            }

            $data->user->update($userPayload);
        }

        return back()->with('success', 'Karyawan berhasil diupdate');
    }

    public function storeKontrak(Request $request, $nik)
    {
        Karyawan::where('nik', $nik)->firstOrFail();

        $validated = $request->validate([
            'kontrak_ke' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'durasi_bulan' => ['nullable', 'integer', 'min:0'],
            'status_kontrak' => ['required', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:2048'],
        ]);

        DB::table('t_kontrak_karyawan')->insert([
            'nik' => $nik,
            'kontrak_ke' => $validated['kontrak_ke'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'durasi_bulan' => $validated['durasi_bulan'] ?? null,
            'status_kontrak' => strtoupper($validated['status_kontrak']),
            'catatan' => $validated['catatan'] ?? null,
            'document' => $request->file('document')->store('contract-documents', 'local'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Kontrak baru berhasil ditambahkan');
    }

    public function updateKontrak(Request $request, $nik, $kontrakId)
    {
        Karyawan::where('nik', $nik)->firstOrFail();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'durasi_bulan' => ['nullable', 'integer', 'min:0'],
            'status_kontrak' => ['required', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = DB::table('t_kontrak_karyawan')
            ->where('id', $kontrakId)
            ->where('nik', $nik)
            ->update([
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'durasi_bulan' => $validated['durasi_bulan'] ?? null,
                'status_kontrak' => strtoupper($validated['status_kontrak']),
                'catatan' => $validated['catatan'] ?? null,
                'updated_at' => now(),
            ]);

        abort_if(! $updated, 404);

        return back()->with('success', 'Kontrak berhasil diperbarui');
    }

    public function updatePhoto(Request $request, $nik)
    {
        $data = Karyawan::with('user')->where('nik', $nik)->firstOrFail();

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $path = $request->file('photo')->store('profile-photos', 'public');
        $user = $this->ensureUserForPhoto($data);

        $user->update([
            'photo' => $path,
        ]);

        return back()->with('success', 'Foto profil karyawan berhasil diperbarui');
    }

    public function destroy($nik)
    {
        $data = Karyawan::where('nik', $nik)->firstOrFail();
        $data->delete();

        return redirect()->route('hr.karyawan.index')->with('success', 'Karyawan berhasil dihapus');
    }

    private function formOptions(?string $currentNik = null): array
    {
        $baseOptions = fn (string $column) => Karyawan::query()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        return [
            'posisiOptions' => $baseOptions('posisi'),
            'divisiOptions' => $baseOptions('divisi'),
            'departementOptions' => $baseOptions('departement'),
            'atasanOptions' => Karyawan::query()
                ->when($currentNik, fn ($query) => $query->where('nik', '<>', $currentNik))
                ->whereNotNull('nama_karyawan')
                ->where('nama_karyawan', '<>', '')
                ->orderBy('nama_karyawan')
                ->get(['nik', 'nama_karyawan']),
        ];
    }

    private function ensureUserForPhoto(Karyawan $karyawan): User
    {
        if ($karyawan->user) {
            return $karyawan->user;
        }

        $email = $karyawan->email ?: $karyawan->nik.'@hris.local';

        if (User::where('email', $email)->exists()) {
            $email = $karyawan->nik.'@hris.local';
        }

        return User::create([
            'username' => $karyawan->nik,
            'name' => $karyawan->nama_karyawan,
            'email' => $email,
            'password' => Hash::make('12345678'),
            'level' => 3,
            'must_change_password' => true,
        ]);
    }

    private function mergePositionPayload(Request $request): void
    {
        $level = trim((string) $request->input('posisi_level'));
        $title = trim((string) $request->input('posisi_title'));

        $request->merge([
            'posisi_level' => $level ?: null,
            'posisi_title' => $title ?: null,
            'posisi' => trim($level.' '.$title) ?: null,
        ]);
    }

    private function positionLevels(): array
    {
        return ['Sr.', 'Md.', 'Jr.'];
    }

    private function positionTitles(): array
    {
        return ['Operator', 'Staff', 'Leader', 'Supervisor', 'Asst. Manager', 'Manager', 'GM'];
    }

    private function divisionOptions(): array
    {
        return ['Business Partner', 'Commercial Business'];
    }
}
