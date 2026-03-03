<style>
	.nav-sidebar .nav-treeview {
		border-left: 2px solid #e5e7eb;
		margin-left: 5px;
	}
</style>

<aside class="main-sidebar sidebar-light-primary elevation-4 rounded-xl">

	<!-- Brand Logo -->
	<a href="{{ url('/dashboard') }}" class="brand-link flex items-center gap-2">
		<img src="{{ asset('hompimplay_icon.png') }}" alt="HRGA Logo" style="opacity: 1; width: 33px; margin-left: 12px;">
		<span class="brand-text font-weight-bold">HRGA Information System</span>
	</a>

	<!-- Sidebar -->
	<div class="sidebar">

		<!-- Sidebar Menu -->
		<nav class="mt-2">
			{{-- DEBUG --}}
			{{-- <pre>
            {{ sidebarMenus()->toJson(JSON_PRETTY_PRINT) }}
            </pre>
			<pre>
            AUTH: {{ auth()->check() ? 'YES' : 'NO' }}
            ROLE: {{ auth()->user()?->role_id }}
            </pre>
                        <pre>
            PERMISSIONS:
            {{ auth()->user()->role->permissions->pluck('key') }}
            </pre> --}}


			<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">

				@foreach (sidebarMenus() as $menu)
					@php
						$isActive = isMenuActive($menu);
					@endphp

					<li class="nav-item {{ $menu->children->count() ? 'has-treeview' : '' }} {{ $isActive ? 'menu-open' : '' }}">
						<a href="{{ $menu->route && Route::has($menu->route) ? route($menu->route) : '#' }}"
							class="nav-link {{ $isActive ? 'active' : '' }}">

							<i class="nav-icon {{ $menu->icon }}"></i>
							<p>
								{{ $menu->name }}
								@if ($menu->children->count())
									<i class="right fas fa-angle-left"></i>
								@endif
							</p>
						</a>

						@if ($menu->children->count())
							<ul class="nav nav-treeview">
								@foreach ($menu->children as $child)
									<li class="nav-item">
										<a href="{{ $child->route && Route::has($child->route) ? route($child->route) : '#' }}"
											class="nav-link {{ $child->route && request()->routeIs($child->route . '*') ? 'active' : '' }}">
											<i class="far fa-circle nav-icon"></i>
											<p>{{ $child->name }}</p>
										</a>
									</li>
								@endforeach
							</ul>
						@endif
					</li>
				@endforeach

				@if (punyaBawahan())
					<li class="nav-item">
						<a href="{{ route('staff.approval.leave.index') }}"
							class="nav-link {{ request()->routeIs('staff.approval.leave.*') ? 'active' : '' }}">
							<i class="nav-icon fas fa-check-circle"></i>
							<p>Approval Cuti / PH</p>
						</a>
					</li>
				@endif


			</ul>

		</nav>

		<!-- Logo di bagian bawah -->
		<div class="p-3 text-center" style="position: absolute; bottom: 0; left: 0; right: 0; z-index: 999;">
			<img src="{{ asset('hompimplay_icon.png') }}" alt="HRGA Logo" style="width: 150px; display: block; margin: 0 auto;">
		</div>

	</div>

</aside>
