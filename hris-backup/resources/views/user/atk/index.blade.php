@extends('layouts.app')

@section('title', 'Pengajuan ATK')
@section('page_title', 'Pengajuan ATK')
@section('page_desc', 'Ajukan kebutuhan alat tulis kantor')

@section('content')
	<div class="space-y-6">

		{{-- ALERT --}}
		@if (session('success'))
			<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
				{{ session('success') }}
			</div>
		@endif
		@if (session('error'))
			<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
				{{ session('error') }}
			</div>
		@endif

		{{-- FORM INPUT --}}
		<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
			<div class="flex items-start justify-between gap-3">
				<div>
					<h3 class="text-base font-extrabold text-gray-900">Form Pengajuan ATK</h3>
					<p class="text-sm text-gray-500">Isi kebutuhan ATK kamu, lalu submit.</p>
				</div>
			</div>

			<form method="POST" action="{{ route('atk.store') }}" class="mt-5 grid gap-4 md:grid-cols-4">
				@csrf

				<div class="md:col-span-2">
					<label class="text-xs font-bold text-gray-600">Nama Barang</label>
					<input type="text" name="nama_barang" value="{{ old('nama_barang') }}" placeholder="Contoh: Pulpen Hitam"
						class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-200">
					@error('nama_barang')
						<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
					@enderror
				</div>

				<div>
					<label class="text-xs font-bold text-gray-600">Qty</label>
					<input type="number" name="qty" value="{{ old('qty', 1) }}"
						class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-200">
					@error('qty')
						<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
					@enderror
				</div>

				<div>
					<label class="text-xs font-bold text-gray-600">Satuan</label>
					<input type="text" name="satuan" value="{{ old('satuan', 'pcs') }}" placeholder="pcs / box / pack"
						class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-200">
					@error('satuan')
						<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
					@enderror
				</div>

				<div class="md:col-span-4">
					<label class="text-xs font-bold text-gray-600">Keterangan</label>
					<textarea name="keterangan" rows="2" placeholder="Opsional, contoh: untuk kebutuhan meeting / operasional..."
					 class="mt-1 w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-200">{{ old('keterangan') }}</textarea>
					@error('keterangan')
						<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
					@enderror
				</div>

				<div class="flex justify-end md:col-span-4">
					<button class="rounded-2xl bg-indigo-600 px-6 py-2 text-sm font-bold text-white hover:bg-indigo-700">
						+ Submit Pengajuan
					</button>
				</div>
			</form>
		</div>

		{{-- LIST PENGAJUAN --}}
		<div class="rounded-3xl border border-gray-200/70 bg-white p-6 shadow-sm">
			<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
				<div>
					<h3 class="text-base font-extrabold text-gray-900">Riwayat Pengajuan</h3>
					<p class="text-sm text-gray-500">Daftar pengajuan ATK yang pernah kamu kirim.</p>
				</div>

				<form method="GET" class="flex items-center gap-2">
					<input type="text" name="q" value="{{ $q }}" placeholder="Cari request no / barang..."
						class="rounded-2xl border border-gray-200 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-200">
					<button class="rounded-2xl bg-gray-900 px-4 py-2 text-sm font-bold text-white hover:bg-gray-800">
						Cari
					</button>
				</form>
			</div>

			<div class="mt-5 overflow-hidden rounded-2xl border border-gray-200/70">
				<table class="w-full text-sm">
					<thead class="bg-gray-50 text-gray-600">
						<tr>
							<th class="px-4 py-3 text-left">No</th>
							<th class="px-4 py-3 text-left">Barang</th>
							<th class="px-4 py-3 text-left">Qty</th>
							<th class="px-4 py-3 text-left">Tanggal</th>
							<th class="px-4 py-3 text-left">Status</th>
							<th class="px-4 py-3 text-right">Aksi</th>
						</tr>
					</thead>
					<tbody class="divide-y">
						@forelse($rows as $r)
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-3 font-semibold">{{ $r->request_no ?? '-' }}</td>
								<td class="px-4 py-3">
									<div class="font-bold text-gray-900">{{ $r->nama_barang ?? '-' }}</div>
									<div class="text-xs text-gray-500">{{ $r->keterangan ?? '-' }}</div>
								</td>
								<td class="px-4 py-3">{{ $r->qty ?? 0 }} {{ $r->satuan ?? '' }}</td>
								<td class="px-4 py-3 text-xs text-gray-600">
									{{ $r->tanggal_pengajuan ? \Carbon\Carbon::parse($r->tanggal_pengajuan)->format('d M Y') : '-' }}
								</td>
								<td class="px-4 py-3">
									<span
										class="@if ($r->status === 'SUBMIT') bg-indigo-50 text-indigo-700
                                    @elseif($r->status === 'APPROVED') bg-green-50 text-green-700
                                    @elseif($r->status === 'REJECTED') bg-red-50 text-red-700
                                    @else bg-gray-100 text-gray-700 @endif rounded-full px-3 py-1 text-xs font-bold">
										{{ $r->status }}
									</span>
								</td>
								<td class="px-4 py-3 text-right">
									@if (in_array($r->status, ['DRAFT', 'SUBMIT']))
										<form method="POST" action="{{ route('atk.destroy', $r->id) }}"
											onsubmit="return confirm('Yakin hapus pengajuan ini?')">
											@csrf
											@method('DELETE')
											<button class="rounded-xl bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100">
												Hapus
											</button>
										</form>
									@else
										<span class="text-xs text-gray-400">-</span>
									@endif
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="6" class="px-4 py-10 text-center text-gray-500">
									Belum ada pengajuan ATK.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<div class="mt-4">
				{{ $rows->links() }}
			</div>
		</div>

	</div>
@endsection
