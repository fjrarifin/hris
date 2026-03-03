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
