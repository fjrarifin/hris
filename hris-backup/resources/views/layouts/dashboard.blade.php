<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="{{ asset('img/hompimplay_icon.png') }}" type="image/png">

	<title>@yield('title', 'Dashboard') - {{ config('app.name', 'HRIS') }}</title>

	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-50 text-gray-800">

	<div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">

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
					<p class="mb-1 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
						Admin Menu
					</p>

					<a href="{{ route('admin.dashboard') }}"
						class="{{ $menuClass(request()->routeIs('admin.dashboard')) }} group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold transition-all">
						<span
							class="{{ $iconClass(request()->routeIs('admin.dashboard')) }} flex h-8 w-8 items-center justify-center rounded-lg">🛡️</span>
						<span>Admin Dashboard</span>
					</a>
				@else
					<p class="mb-1 px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
						User Menu
					</p>

					<a href="{{ route('user.dashboard') }}"
						class="{{ $menuClass(request()->routeIs('user.dashboard')) }} group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold transition-all">
						<span
							class="{{ $iconClass(request()->routeIs('user.dashboard')) }} flex h-8 w-8 items-center justify-center rounded-lg">🏠</span>
						<span>Dashboard</span>
					</a>
				@endif

				<a href="#"
					class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
					<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
					<span>Profil</span>
				</a>

				<a href="#"
					class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
					<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">🔗</span>
					<span>Relasi</span>
				</a>

				<a href="{{ route('penilaian.index') }}"
					class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
					<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">📄</span>
					<span>Penilaian</span>
				</a>

				<a href="#"
					class="group flex items-center gap-3 rounded-xl px-3 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
					<span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">📊</span>
					<span>Laporan</span>
				</a>
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
					<a href="{{ route('admin.dashboard') }}"
						class="{{ $menuClass(request()->routeIs('admin.dashboard')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
						<span
							class="{{ $iconClass(request()->routeIs('admin.dashboard')) }} flex h-6 w-6 items-center justify-center rounded-lg">🛡️</span>
						<span>Admin Dashboard</span>
					</a>
				@else
					<a href="{{ route('user.dashboard') }}"
						class="{{ $menuClass(request()->routeIs('user.dashboard')) }} group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold transition-all">
						<span
							class="{{ $iconClass(request()->routeIs('user.dashboard')) }} flex h-6 w-6 items-center justify-center rounded-lg">🏠</span>
						<span>Dashboard</span>
					</a>

					<a href="#"
						class="group flex items-center gap-3 rounded-xl px-2 py-1 text-sm font-semibold text-gray-700 transition-all hover:bg-indigo-50 hover:text-indigo-700">
						<span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 group-hover:bg-indigo-100">👤</span>
						<span>Profil</span>
					</a>

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

		{{-- MAIN --}}
		<div class="flex h-screen flex-1 flex-col overflow-hidden">

			{{-- NAVBAR --}}
			<header class="sticky top-0 z-20 border-b border-gray-200/70 bg-white/70 backdrop-blur-xl">
				<div class="flex h-[60px] items-center justify-between gap-3 px-4 md:px-6">

					{{-- Mobile hamburger --}}
					<div class="flex items-center gap-3 md:hidden">
						<button @click="sidebarOpen = true" class="rounded-xl p-2 hover:bg-gray-100">
							☰
						</button>

						<div>
							<p class="text-sm font-extrabold leading-tight text-gray-900">@yield('page_title', 'Dashboard')</p>
							<p class="text-[11px] leading-tight text-gray-500">@yield('page_desc', 'HRIS')</p>
						</div>
					</div>

					{{-- Desktop title --}}
					<div class="hidden min-w-[200px] md:block">
						<h1 class="text-lg font-extrabold leading-tight tracking-tight text-gray-900 md:text-xl">
							@yield('page_title', 'Dashboard')
						</h1>
						<p class="text-xs leading-tight text-gray-500">
							@yield('page_desc', 'Ringkasan informasi sistem HRIS')
						</p>
					</div>

					{{-- Profile Dropdown --}}
					<div class="relative" x-data="{ open: false }" @click.outside="open = false">
						<button type="button" @click="open = !open"
							class="flex items-center gap-2 rounded-2xl border border-gray-200/70 bg-white px-3 py-2 shadow-sm transition hover:bg-gray-50">
							<div
								class="flex h-7 w-7 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 text-xs font-bold text-white">
								{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
							</div>

							<div class="hidden text-left leading-tight sm:block">
								<p class="text-sm font-bold">{{ auth()->user()->name }}</p>
							</div>

							<span class="text-xs text-gray-400">▾</span>
						</button>

						{{-- Dropdown Card --}}
						<div x-show="open" x-transition style="display:none;"
							class="absolute right-0 z-50 mt-2 w-56 rounded-2xl border border-gray-200 bg-white p-3 shadow-lg">
							<div class="flex items-center gap-3 border-b pb-3">
								<div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-indigo-50 font-bold text-indigo-700">
									{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
								</div>
								<div class="leading-tight">
									<p class="text-sm font-bold text-gray-900">{{ auth()->user()->name }}</p>
									<p class="text-xs text-gray-500">NIK: {{ auth()->user()->nik }}</p>
								</div>
							</div>

							<div class="space-y-2 pt-3">
								{{-- Optional: tombol profil --}}
								<a href="#"
									class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">
									👤 <span>Profil</span>
								</a>

								{{-- Logout --}}
								<form method="POST" action="{{ route('logout') }}">
									@csrf
									<button type="submit"
										class="flex w-full items-center justify-center gap-2 rounded-xl bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100">
										🚪 Logout
									</button>
								</form>
							</div>
						</div>
					</div>

				</div>
			</header>

			{{-- CONTENT --}}
			<main class="flex-1 overflow-y-auto px-4 py-5 md:px-6">

				@yield('content')
			</main>

			{{-- FOOTER --}}
			<footer class="border-t border-gray-200/70 bg-white/70 backdrop-blur-xl">
				<div class="flex items-center justify-between px-4 py-3 text-xs text-gray-500 md:px-6">
					<p>© {{ date('Y') }} {{ config('app.name', 'HRIS') }} HomPim Play</p>
					<p class="flex items-center gap-1">Built by Fajar<span>✅</span></p>
				</div>
			</footer>

		</div>

	</div>

	{{-- ✅ Toast Notification --}}
	@if (session('success'))
		<div x-data="{ show: true }" x-init="setTimeout(() => show = false, 2500)" x-show="show" x-transition style="display: none;"
			class="fixed right-5 top-5 z-[9999]">
			<div class="flex min-w-[260px] items-start gap-3 rounded-2xl border border-green-200 bg-white px-4 py-3 shadow-lg">
				<div class="flex h-9 w-9 items-center justify-center rounded-xl bg-green-100">
					✅
				</div>

				<div class="flex-1">
					<p class="text-sm font-bold text-gray-900">Berhasil</p>
					<p class="text-sm text-gray-600">{{ session('success') }}</p>
				</div>

				<button @click="show = false" class="px-2 font-bold text-gray-400 hover:text-gray-600" type="button">
					✖
				</button>
			</div>
		</div>
	@endif


</body>

</html>
