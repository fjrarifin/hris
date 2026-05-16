<nav class="main-header navbar navbar-expand navbar-white navbar-light sticky-top shadow-sm">
	<!-- Left navbar -->
	<ul class="navbar-nav">

		<!-- Hamburger -->
		<li class="nav-item">
			<a class="nav-link" data-widget="pushmenu" href="#">
				<i class="fas fa-bars"></i>
			</a>
		</li>

		<!-- Home Icon -->
		<li class="nav-item">
			<a href="{{ route('dashboard') }}" class="nav-link">
				<i class="fas fa-home"></i>
			</a>
		</li>

	</ul>

	<!-- Right navbar -->
	<ul class="navbar-nav ml-auto flex items-center gap-2">

		{{-- 🔔 NOTIFICATION --}}
		@php
			$user = auth()->user();
			$notifications = $user->notifications()->latest()->limit(5)->get();
			$unreadCount = $user->unreadNotifications()->count();
		@endphp

		<li class="nav-item dropdown">
			<a
				class="nav-link position-relative d-flex align-items-center justify-center rounded-2xl border border-gray-200/70 bg-white px-3 py-2 shadow-sm transition hover:bg-gray-50"
				data-toggle="dropdown" href="#" role="button">

				<i class="far fa-bell text-lg text-gray-700"></i>

				@if ($unreadCount > 0)
					<span
						class="position-absolute translate-middle badge right-0 top-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] text-white">
						{{ $unreadCount }}
					</span>
				@endif
			</a>

			<div class="dropdown-menu dropdown-menu-right overflow-hidden rounded-xl p-0 shadow-lg" style="width: 350px">

				{{-- HEADER --}}
				<div class="border-bottom d-flex justify-content-between align-items-center bg-gray-50 px-3 py-2">
					<div>
						<p class="mb-0 text-sm font-bold">Notifikasi</p>
						<p class="mb-0 text-xs text-gray-500">
							{{ $unreadCount }} belum dibaca
						</p>
					</div>

					@if ($unreadCount > 0)
						<button id="markAllRead"
							class="border-0 bg-transparent text-xs font-semibold text-indigo-600 hover:text-indigo-800">
							Tandai semua
						</button>
					@endif
				</div>

				{{-- LIST --}}
				<div style="max-height: 400px; overflow-y: auto;">
					@forelse($notifications as $notif)
						<a href="#"
							class="dropdown-item notification-item {{ $notif->read_at ? '' : 'bg-indigo-50' }} flex gap-3 py-3"
							data-id="{{ $notif->id }}">

							<div class="text-lg">
								🔔
							</div>

							<div>
								<p class="mb-0 text-sm font-semibold">
									{{ $notif->data['title'] ?? 'Notifikasi' }}
								</p>

								<p class="mb-0 text-xs text-gray-500">
									{{ $notif->data['message'] ?? '-' }}
								</p>

								<p class="mt-1 text-[11px] text-gray-400">
									{{ $notif->created_at->diffForHumans() }}
								</p>
							</div>
						</a>

						<div class="dropdown-divider m-0"></div>
					@empty
						<div class="p-4 text-center text-sm text-gray-400">
							Tidak ada notifikasi
						</div>
					@endforelse
				</div>

				{{-- FOOTER --}}
				<a href="#" class="dropdown-item border-top py-2 text-center text-sm font-semibold text-indigo-600">
					Lihat Semua Notifikasi
				</a>
			</div>
		</li>


		{{-- 👤 USER --}}
		<li class="nav-item dropdown">
			<a
				class="nav-link d-flex align-items-center gap-2 rounded-2xl border border-gray-200 bg-white px-3 py-2 shadow-sm transition hover:bg-gray-50"
				data-toggle="dropdown" href="#" role="button" aria-expanded="false">

				{{-- FOTO / INITIAL --}}
				@if (Auth::user()->photo)
					<img src="{{ asset('storage/' . Auth::user()->photo) }}" class="rounded-circle" width="24" height="24"
						style="object-fit:cover;">
				@else
					<div class="bg-primary d-flex align-items-center justify-content-center rounded-circle text-white"
						style="width:24px;height:24px;font-size:14px;font-weight:600;">
						{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
					</div>
				@endif

				{{-- Nama desktop only --}}
				<span class="d-none d-md-inline font-weight-semibold ml-2">
					{{ Auth::user()->name }}
				</span>

				<span class="d-none d-md-inline text-muted ml-1">▾</span>
			</a>

			<div class="dropdown-menu dropdown-menu-right rounded-xl border-0 p-2 shadow">

				{{-- MOBILE HEADER --}}
				<div class="dropdown-header d-md-none mb-2 text-center">

					@if (Auth::user()->photo)
						<img src="{{ asset('storage/' . Auth::user()->photo) }}" class="rounded-circle d-block mx-auto mb-2"
							width="60" height="60" style="object-fit:cover;">
					@else
						<div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 text-white"
							style="width:60px;height:60px;font-size:22px;">
							{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
						</div>
					@endif

					<div class="font-weight-bold">
						{{ Auth::user()->name }}
					</div>

					<div class="text-muted small">
						{{ Auth::user()->email }}
					</div>

				</div>

				{{-- MENU --}}
				<a href="{{ route('staff.profile.index') }}" class="dropdown-item rounded-lg">
					<i class="fas fa-user mr-2"></i>
					Profil
				</a>

				<div class="dropdown-divider"></div>

				<form action="/logout" method="POST" class="mb-0">
					@csrf
					<button type="submit" class="dropdown-item text-danger rounded-lg">
						<i class="fas fa-sign-out-alt mr-2"></i>
						Keluar
					</button>
				</form>
			</div>
		</li>

	</ul>


</nav>
<script>
	document.addEventListener('DOMContentLoaded', function() {

		// Klik notifikasi → tandai sudah dibaca
		document.querySelectorAll('.notification-item').forEach(item => {
			item.addEventListener('click', function(e) {
				e.preventDefault();

				const id = this.dataset.id;

				fetch(`/notifications/${id}/read`, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
						'Content-Type': 'application/json'
					}
				}).then(() => {
					this.classList.remove('bg-indigo-50');
					location.reload(); // supaya badge update
				});
			});
		});

		// Tandai semua
		const markAllBtn = document.getElementById('markAllRead');
		if (markAllBtn) {
			markAllBtn.addEventListener('click', function() {
				fetch(`/notifications/read-all`, {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
						'Content-Type': 'application/json'
					}
				}).then(() => {
					location.reload();
				});
			});
		}

	});
</script>
