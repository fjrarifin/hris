@extends('layouts.app')

@section('title', 'Dasbor')
@section('page-title', 'Dasbor HR')

@section('content')
	<div class="space-y-4">
		<div class="rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 p-6 text-white shadow-sm">
			<h2 class="text-lg font-bold">
				Selamat Datang, {{ Auth::user()->name }}
			</h2>
			<p class="mt-1 text-sm text-blue-100">
				{{ now()->isoFormat('dddd, D MMMM YYYY') }}
			</p>
		</div>

		<div class="card card-primary card-outline mt-4 rounded-xl shadow-sm">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h3 class="card-title mb-0">Menunggu Persetujuan HR</h3>

				<div class="d-flex ml-auto flex-wrap gap-2">
					<a href="{{ route('hr.approval.all') }}" class="btn btn-sm btn-primary mr-2">
						Semua Approval
					</a>
					<a href="{{ route('hr.approval.index', 'leave') }}" class="btn btn-sm btn-outline-primary mr-2">
						Cuti ({{ $leavePendingCount }})
					</a>
					<a href="{{ route('hr.approval.index', 'ph') }}" class="btn btn-sm btn-outline-info mr-2">
						PH ({{ $phPendingCount }})
					</a>
					<a href="{{ route('hr.approval.index', 'permission') }}" class="btn btn-sm btn-outline-secondary mr-2">
						Izin ({{ $permissionPendingCount }})
					</a>
					<a href="{{ route('hr.approval.index', 'overtime') }}" class="btn btn-sm btn-outline-dark">
						Lembur ({{ $overtimePendingCount }})
					</a>
				</div>
			</div>

			<div class="card-body table-responsive">
				<table class="table-hover table text-sm">
					<thead class="bg-light">
						<tr>
							<th width="120">Jenis</th>
							<th>Nama</th>
							<th>Detail</th>
							<th width="120" class="text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($pendingApprovals as $item)
							@php
								$r = $item->request;
								$type = $item->type;
								$typeLabel = match ($type) {
								    'leave' => 'Cuti',
								    'ph' => 'PH',
								    'permission' => 'Izin',
								    'overtime' => 'Lembur',
								    default => strtoupper($type),
								};
								$badge = match ($type) {
								    'leave' => 'primary',
								    'ph' => 'info',
								    'permission' => 'secondary',
								    'overtime' => 'dark',
								    default => 'light',
								};
							@endphp
							<tr>
								<td>
									<span class="badge badge-{{ $badge }}">{{ $typeLabel }}</span>
								</td>
								<td>{{ $r->user->name }}</td>
								<td>
									@if ($type === 'leave')
										{{ \Carbon\Carbon::parse($r->start_date)->format('d M Y') }}
										-
										{{ \Carbon\Carbon::parse($r->end_date)->format('d M Y') }}
									@elseif ($type === 'ph')
										{{ $r->holiday->name ?? 'Hari Libur' }}
										<br>Claim: {{ \Carbon\Carbon::parse($r->claim_date)->format('d M Y') }}
									@elseif ($type === 'permission')
										{{ $r->type === 'sakit' ? 'Sakit' : 'Izin Tidak Masuk' }}
										<br>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}
									@elseif ($type === 'overtime')
										{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}
										<br>{{ $r->start_time }} - {{ $r->end_time }}
									@endif
								</td>
								<td class="text-center">
									<form method="POST" action="{{ route('hr.approval.approve', [$type, $r->id]) }}" class="d-inline">
										@csrf
										<button class="btn btn-xs btn-success px-2 py-1" title="Setujui">
											<i class="fas fa-check"></i>
										</button>
									</form>

									<form method="POST" action="{{ route('hr.approval.reject', [$type, $r->id]) }}" class="d-inline">
										@csrf
										<input type="hidden" name="reason" value="Ditolak HR">
										<button class="btn btn-xs btn-danger px-2 py-1" title="Tolak">
											<i class="fas fa-times"></i>
										</button>
									</form>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="4" class="text-muted py-4 text-center">
									Tidak ada pending approval.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
@endsection
