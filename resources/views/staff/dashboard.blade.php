@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

	<div class="w-full max-w-none px-0">

		{{-- SAMBUTAN --}}
		<div class="rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 p-3 text-white shadow-sm">
			<h5 class="text-md font-bold">Selamat Datang, {{ Auth::user()->name }}! 👋</h5>
			@php
				$hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][now()->dayOfWeek];
				$bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][now()->month - 1];
				$tanggal = now()->day;
				$tahun = now()->year;
			@endphp
			<p class="mt-1 text-xs text-blue-100">{{ $hari }}, {{ $tanggal }} {{ $bulan }} {{ $tahun }}</p>
		</div>

		{{-- QUICK ACTION MENU --}}
		<div>
			<div class="row mt-3">

				{{-- SALDO CUTI --}}
				<div class="col-6 col-md-6">

					<div class="h-100 rounded-2xl bg-white p-3 shadow-sm">

						{{-- ================= MOBILE ================= --}}
						<div class="d-md-none text-center">

							<div
								class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 bg-green-100 text-green-600"
								style="width:50px; height:50px;">
								<i class="fas fa-umbrella-beach"></i>
							</div>

							<div class="text-muted small">Sisa Saldo Cuti</div>
							<div class="font-weight-bold text-success" style="font-size:18px;">
								{{ $leaveBalance }} Hari
							</div>

							<a href="{{ route('staff.leave.index') }}" class="btn btn-outline-success btn-sm btn-block rounded-pill mt-3">
								Detail
							</a>

						</div>


						{{-- ================= DESKTOP ================= --}}
						<div class="d-none d-md-flex align-items-center justify-content-between">

							<div class="d-flex align-items-center">

								<div class="rounded-circle d-flex align-items-center justify-content-center mr-3 bg-green-100 text-green-600"
									style="width:45px; height:45px;">
									<i class="fas fa-umbrella-beach"></i>
								</div>

								<div>
									<div class="text-muted small">Sisa Saldo Cuti</div>
									<div class="font-weight-bold text-success" style="font-size:18px;">
										{{ $leaveBalance }} Hari
									</div>
								</div>

							</div>

							<a href="{{ route('staff.leave.index') }}" class="btn btn-outline-success btn-sm rounded-pill px-3">
								Detail
							</a>

						</div>

					</div>
				</div>

				{{-- SALDO PUBLIC HOLIDAY --}}
				<div class="col-6 col-md-6">

					<div class="h-100 rounded-2xl bg-white p-3 shadow-sm">

						{{-- ================= MOBILE ================= --}}
						<div class="d-md-none text-center">

							<div
								class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 bg-orange-100 text-orange-500"
								style="width:50px; height:50px;">
								<i class="fas fa-calendar-day"></i>
							</div>

							<div class="text-muted small">Sisa Saldo PH</div>
							<div class="font-weight-bold text-primary" style="font-size:18px;">
								{{ $publicHolidayBalance }} Hari
							</div>

							<a href="{{ route('staff.public-holiday.index') }}"
								class="btn btn-outline-primary btn-sm btn-block rounded-pill mt-3">
								Detail
							</a>

						</div>


						{{-- ================= DESKTOP ================= --}}
						<div class="d-none d-md-flex align-items-center justify-content-between">

							<div class="d-flex align-items-center">

								<div class="rounded-circle d-flex align-items-center justify-content-center mr-3 bg-orange-100 text-orange-500"
									style="width:45px; height:45px;">
									<i class="fas fa-calendar-day"></i>
								</div>

								<div>
									<div class="text-muted small">Sisa Saldo Public Holiday</div>
									<div class="font-weight-bold text-primary" style="font-size:18px;">
										{{ $publicHolidayBalance }} Hari
									</div>
								</div>

							</div>

							<a href="{{ route('staff.public-holiday.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
								Detail
							</a>

						</div>

					</div>
				</div>

			</div>

			<div class="row mt-3">
				{{-- DATA DIRI --}}
				<div class="col-4 col-md-3 mt-md-0 mt-3">
					<a href="{{ route('staff.profile.index') }}"
						class="group flex flex-col items-center gap-1 rounded-2xl border border-gray-100 bg-white px-2 py-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-95">
						<span
							class="flex h-11 w-11 items-center justify-center rounded-2xl bg-purple-100 text-xl text-purple-600 transition-transform duration-200 group-hover:scale-110">
							<i class="fas fa-id-card"></i>
						</span>
						<span class="text-center text-[10px] font-semibold leading-tight text-gray-600 sm:text-[11px]">Data Diri</span>
					</a>
				</div>
				{{-- SELF ASSESSMENT --}}
				<div class="col-4 col-md-3 mt-md-0 mt-3">
					<a href="#" onclick="showMaintenanceToast('Self Assessment'); return false;"
						class="group flex flex-col items-center gap-1 rounded-2xl border border-gray-100 bg-white px-2 py-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-95">
						<span
							class="flex h-11 w-11 items-center justify-center rounded-2xl bg-yellow-100 text-xl text-yellow-600 transition-transform duration-200 group-hover:scale-110">
							<i class="fas fa-clipboard-check"></i>
						</span>
						<span class="text-center text-[10px] font-semibold leading-tight text-gray-600 sm:text-[11px]">Self
							Assessment</span>
					</a>
				</div>

				{{-- 360 ASSESSMENT --}}
				<div class="col-4 col-md-3 mt-md-0 mt-3">
					<a href="#" onclick="showMaintenanceToast('360 Assessment'); return false;"
						class="group flex flex-col items-center gap-1 rounded-2xl border border-gray-100 bg-white px-2 py-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-95">
						<span
							class="flex h-11 w-11 items-center justify-center rounded-2xl bg-pink-100 text-xl text-pink-600 transition-transform duration-200 group-hover:scale-110">
							<i class="fas fa-star"></i>
						</span>
						<span class="text-center text-[10px] font-semibold leading-tight text-gray-600 sm:text-[11px]">360
							Assessment</span>
					</a>
				</div>

				{{-- APPROVAL CUTI / PH (tampil hanya jika punya bawahan) --}}
				@if (punyaBawahan())
					<div class="col-4 col-md-3 mt-md-0 mt-3">
						<a href="{{ route('staff.approval.leave.index') }}"
							class="group flex flex-col items-center gap-1 rounded-2xl border border-gray-100 bg-white px-2 py-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-95">
							<span
								class="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-xl text-red-500 transition-transform duration-200 group-hover:scale-110">
								<i class="fas fa-check-circle"></i>
							</span>
							<span class="text-center text-[10px] font-semibold leading-tight text-gray-600 sm:text-[11px]">Approval
								Cuti</span>
						</a>
					</div>
				@endif

			</div>


		</div>
	</div>

	<script>
		function showMaintenanceToast(feature) {
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'info',
				title: `Fitur ${feature} sedang dalam maintenance. Silakan coba lagi nanti.`,
				showConfirmButton: false,
				timer: 5000
			});
		}
	</script>

@endsection
