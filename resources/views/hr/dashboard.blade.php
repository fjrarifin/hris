@extends('layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor HR')

@section('content')

	<div class="space-y-4">

		{{-- ================= HEADER ================= --}}
		<div class="rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 p-6 text-white shadow-sm">
			<h2 class="text-lg font-bold">
				Selamat Datang, {{ Auth::user()->name }} 👋
			</h2>
			<p class="mt-1 text-sm text-blue-100">
				{{ now()->isoFormat('dddd, D MMMM YYYY') }}
			</p>
		</div>

		{{-- ================= QUICK APPROVAL TABLE ================= --}}
		<div class="card card-primary card-outline mt-4 rounded-xl shadow-sm">

			<div class="card-header d-flex justify-content-between align-items-center">

				<h3 class="card-title mb-0">
					🔴 Menunggu Persetujuan (Aksi Cepat)
				</h3>

				<div class="d-flex ml-auto gap-2">
					<a href="{{ route('hr.approval.index', 'leave') }}" class="btn btn-sm btn-outline-primary mr-2">
						📅 Semua Cuti
					</a>

					<a href="{{ route('hr.approval.index', 'ph') }}" class="btn btn-sm btn-outline-info">
						🎉 Semua PH
					</a>
				</div>

			</div>


			<div class="card-body table-responsive">

				<table class="table-hover table text-sm">
					<thead class="bg-light">
						<tr>
							<th width="120">Jenis</th>
							<th>Nama</th>
							<th>Tanggal</th>
							<th width="120" class="text-center">Aksi</th>
						</tr>
					</thead>

					<tbody>

						{{-- LEAVE --}}
						@foreach ($pendingLeave as $r)
							<tr>
								<td>
									<span class="badge badge-primary">
										📅 Cuti
									</span>
								</td>

								<td>{{ $r->user->name }}</td>

								<td>
									{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
									-
									{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
								</td>

								<td class="text-center">

									<form method="POST" action="{{ route('hr.approval.approve', ['leave', $r->id]) }}" class="d-inline">
										@csrf
										<button class="btn btn-xs btn-success px-2 py-1" title="Setujui">
											<i class="fas fa-check"></i>
										</button>
									</form>

									<form method="POST" action="{{ route('hr.approval.reject', ['leave', $r->id]) }}" class="d-inline">
										@csrf
										<input type="hidden" name="reason" value="Ditolak HR">
										<button class="btn btn-xs btn-danger px-2 py-1" title="Tolak">
											<i class="fas fa-times"></i>
										</button>
									</form>

								</td>

							</tr>
						@endforeach


						{{-- PH --}}
						@foreach ($pendingPH as $r)
							<tr>
								<td>
									<span class="badge badge-info">
										🎉 Hari Libur
									</span>
								</td>

								<td>{{ $r->user->name }}</td>

								<td>
									{{ $r->claim_date->format('d M Y') }}
								</td>

								<td class="text-center">

									<form method="POST" action="{{ route('hr.approval.approve', ['ph', $r->id]) }}" class="d-inline">
										@csrf
										<button class="btn btn-xs btn-success px-2 py-1" title="Setujui">
											<i class="fas fa-check"></i>
										</button>
									</form>

									<form method="POST" action="{{ route('hr.approval.reject', ['ph', $r->id]) }}" class="d-inline">
										@csrf
										<input type="hidden" name="reason" value="Ditolak HR">
										<button class="btn btn-xs btn-danger px-2 py-1" title="Tolak">
											<i class="fas fa-times"></i>
										</button>
									</form>

								</td>

							</tr>
						@endforeach


						@if ($pendingLeave->isEmpty() && $pendingPH->isEmpty())
							<tr>
								<td colspan="4" class="text-muted py-4 text-center">
									✅ Tidak ada pending approval
								</td>
							</tr>
						@endif

					</tbody>
				</table>

			</div>
		</div>

	</div>

@endsection
