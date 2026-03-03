@php
	$level = (int) auth()->user()->level;
	$isAdmin = $level === 0;

	$menuClass = function ($active) {
	    return $active ? 'bg-indigo-600 text-white shadow' : 'text-gray-700 hover:bg-indigo-50 hover:text-indigo-700';
	};

	$iconClass = function ($active) {
	    return $active ? 'bg-white/20' : 'bg-indigo-50 group-hover:bg-indigo-100';
	};
@endphp

{{-- ✅ MOBILE OVERLAY --}}
<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/40 md:hidden"
	@click="sidebarOpen = false" style="display:none;"></div>

{{-- ✅ MOBILE SIDEBAR DRAWER --}}
<aside x-show="sidebarOpen" x-transition:enter="transition ease-out duration-200"
	x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
	x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0"
	x-transition:leave-end="-translate-x-full"
	class="fixed left-0 top-0 z-50 h-full w-64 border-r border-gray-200/70 bg-white md:hidden" style="display:none;">
	{{-- Brand --}}
	<div class="flex h-[60px] items-center justify-between border-b border-gray-200/70 px-4">
		<div class="flex items-center gap-3">
			<div
				class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-600 font-bold text-white shadow">
				H
			</div>
			<div>
				<p class="font-extrabold leading-tight tracking-tight text-gray-900">
					{{ config('app.name', 'HRIS') }}</p>
				<p class="text-[11px] leading-tight text-gray-500">Human Resource System</p>
			</div>
		</div>

		<button @click="sidebarOpen = false" class="rounded-xl p-2 hover:bg-gray-100">
			✖
		</button>
	</div>

	{{-- Menu --}}
	<nav class="flex-1 space-y-2 px-3 py-4">
		@if ($isAdmin)
			<p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				Admin Menu
			</p>

			<a href="{{ route('admin.dashboard') }}"
				class="{{ $menuClass(request()->routeIs('admin.dashboard')) }} group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.dashboard')) }} flex h-7 w-7 items-center justify-center rounded-lg">🛡️</span>
				<span>Dashboard</span>
			</a>

			<p class="mb-2 mt-4 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				Master Data
			</p>

			<a href="{{ route('admin.karyawan.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.karyawan.*')) }} group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.karyawan.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">👥</span>
				<span>Karyawan</span>
			</a>

			<a href="{{ route('admin.users.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.users.*')) }} group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.users.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">🔑</span>
				<span>Users</span>
			</a>

			<a href="{{ route('admin.relasi-master.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.relasi.*')) }} group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.relasi.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">🔗</span>
				<span>Relasi</span>
			</a>

			<a href="{{ route('admin.faktor.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.faktor.*')) }} group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.faktor.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">🧩</span>
				<span>Faktor Penilaian</span>
			</a>

			<p class="mb-2 mt-4 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				Penilaian
			</p>

			<a href="{{ route('admin.monitoring.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.monitoring.*')) }} group flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.monitoring.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">📊</span>
				<span>Monitoring</span>
			</a>
		@else
			<a href="{{ route('user.dashboard') }}"
				class="{{ $menuClass(request()->routeIs('user.dashboard')) }} group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('user.dashboard')) }} flex h-8 w-8 items-center justify-center rounded-lg">🏠</span>
				<span>Dashboard</span>
			</a>

			{{-- <a href="#"
				class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
				<span>Profil</span>
			</a>

			<a href="#"
				class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
				<span>Absensi</span>
			</a>

			<a href="#"
				class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
				<span>Cuti / PH</span>
			</a> --}}

			<a href="{{ route('penilaian.index') }}"
				class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">📄</span>
				<span>Penilaian</span>
			</a>
		@endif
	</nav>

	<div class="px-3 pb-4">
		<div class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm">
			<div class="flex items-center gap-3">
				<div class="w-48 items-center justify-center overflow-hidden rounded-2xl bg-gray-50">
					<img src="{{ asset('img/hompimplay_logo.png') }}" alt="Logo Perusahaan" class="h-full w-full">
				</div>
			</div>
		</div>
	</div>
</aside>

{{-- ✅ DESKTOP SIDEBAR --}}
<aside
	class="sticky top-0 hidden h-screen w-60 flex-col border-r border-gray-200/70 bg-white/70 backdrop-blur-xl md:flex">

	{{-- Brand --}}
	<div class="flex h-[60px] items-center border-b border-gray-200/70 px-4">
		<div class="flex items-center gap-3">
			<div
				class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-600 font-bold text-white shadow">
				H
			</div>
			<div>
				<p class="font-extrabold leading-tight tracking-tight text-gray-900">
					{{ config('app.name', 'HRIS') }}
				</p>
				<p class="text-[11px] leading-tight text-gray-500">
					Human Resource System
				</p>
			</div>
		</div>
	</div>

	{{-- Menu --}}
	<nav class="flex-1 space-y-2 overflow-y-auto px-3 py-4">



		@if ($isAdmin)
			<p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				Admin Menu
			</p>

			<a href="{{ route('admin.dashboard') }}"
				class="{{ $menuClass(request()->routeIs('admin.dashboard')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.dashboard')) }} flex h-7 w-7 items-center justify-center rounded-lg">🛡️</span>
				<span>Dashboard</span>
			</a>

			<p class="mb-2 mt-4 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				Master Data
			</p>

			<a href="{{ route('admin.karyawan.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.karyawan.*')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.karyawan.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">👥</span>
				<span>Karyawan</span>
			</a>

			<a href="{{ route('admin.users.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.users.*')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.users.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">🔑</span>
				<span>Users</span>
			</a>

			<a href="{{ route('admin.relasi-master.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.relasi-master.*')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.relasi-master.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">🧠</span>
				<span>Relasi Master</span>
			</a>

			<a href="{{ route('admin.faktor-score.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.faktor-score.*')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.faktor-score.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">🔨</span>
				<span>Faktor Penilaian</span>
			</a>

			<p class="mb-2 mt-4 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				Penilaian
			</p>

			<a href="{{ route('admin.monitoring.index') }}"
				class="{{ $menuClass(request()->routeIs('admin.monitoring.*')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('admin.monitoring.*')) }} flex h-7 w-7 items-center justify-center rounded-lg">📊</span>
				<span>Monitoring</span>
			</a>

			{{-- ================= GA MENU ================= --}}
			<p class="mb-2 mt-6 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
				GA Management
			</p>

			@php
				$gaActive = request()->routeIs('ga.*');
			@endphp

			<div x-data="{ open: {{ $gaActive ? 'true' : 'false' }} }" class="space-y-1">

				{{-- PARENT --}}
				<button @click="open = !open"
					class="{{ $menuClass($gaActive) }} flex w-full items-center justify-between rounded-xl px-2 py-1 text-sm font-semibold transition-all">

					<div class="flex items-center gap-3">
						<span class="{{ $iconClass($gaActive) }} flex h-7 w-7 items-center justify-center rounded-lg">
							🏢
						</span>
						<span>General Affair</span>
					</div>

					<span class="text-xs transition-transform" :class="open && 'rotate-180'">⌄</span>
				</button>

				{{-- CHILD --}}
				<div x-show="open" x-collapse class="ml-4 space-y-1">

					<a href="{{ Route::has('admin.ga.dashboard.index') ? route('admin.ga.dashboard.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.dashboard.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-50">📊</span>
						<span>Dashboard</span>
					</a>
					<a href="{{ Route::has('admin.ga.asset.index') ? route('admin.ga.asset.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.asset.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50">🏢</span>
						<span>Aset</span>
					</a>
					<a href="{{ Route::has('admin.ga.budget.index') ? route('admin.ga.budget.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.budget.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-50">💰</span>
						<span>Budget & Cost</span>
					</a>
					<a href="{{ Route::has('admin.ga.compliance.index') ? route('admin.ga.compliance.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.compliance.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-sky-50">📄</span>
						<span>Compliance</span>
					</a>
					<a href="{{ Route::has('admin.ga.incident.index') ? route('admin.ga.incident.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.incident.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-rose-50">⚠️</span>
						<span>Incident</span>
					</a>
					<a href="{{ Route::has('admin.ga.maintenance.index') ? route('admin.ga.maintenance.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.maintenance.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-red-50">🛠</span>
						<span>Maintenance</span>
					</a>
					<a href="{{ Route::has('admin.ga.sla.index') ? route('admin.ga.sla.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.sla.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-green-50">📈</span>
						<span>SLA</span>
					</a>
					<a href="{{ Route::has('admin.ga.vendor.index') ? route('admin.ga.vendor.index') : '#' }}"
						class="{{ $menuClass(request()->routeIs('admin.ga.vendor.*')) }} flex items-center gap-3 rounded-xl px-2 py-1 text-sm">
						<span class="flex h-6 w-6 items-center justify-center rounded-md bg-yellow-50">🤝</span>
						<span>Vendor</span>
					</a>

				</div>
			</div>
			{{-- ================= END GA MENU ================= --}}
		@else
			<a href="{{ route('user.dashboard') }}"
				class="{{ $menuClass(request()->routeIs('user.dashboard')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('user.dashboard')) }} flex h-6 w-6 items-center justify-center rounded-lg">🏠</span>
				<span>Dashboard</span>
			</a>

			<a href="{{ route('atk.index') }}"
				class="{{ $menuClass(request()->routeIs('user.atk.*')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
				<span
					class="{{ $iconClass(request()->routeIs('user.atk.*')) }} flex h-6 w-6 items-center justify-center rounded-lg">📝</span>
				<span>Pengajuan ATK</span>
			</a>


			{{-- <a href="#"
				class="group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
				<span>Profil</span>
			</a>

			<a href="#"
				class="group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
				<span>Absensi</span>
			</a>

			<a href="#"
				class="group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
				<span>Cuti / PH</span>
			</a> --}}

			<a href="{{ route('penilaian.index') }}"
				class="{{ $menuClass(request()->routeIs('penilaian.index')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
				<span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">📄</span>
				<span>Penilaian</span>
			</a>
		@endif
	</nav>

	{{-- Logo --}}
	<div class="px-3">
		<div class="rounded-2xl p-4">
			<div class="flex items-center justify-center">
				<div class="w-30 flex items-center justify-center overflow-hidden rounded-2xl bg-gray-50">
					<img src="{{ asset('img/hompimplay_logo.png') }}" alt="Logo Perusahaan" class="h-full w-full object-contain">
				</div>
			</div>
		</div>
	</div>

</aside>
