<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>@yield('title', 'HRGA')</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Font Awesome -->
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
	<!-- AdminLTE CSS -->
	<link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">
	<link rel="icon" href="{{ asset('hompimplay_icon.png') }}">

	<!-- DataTables CSS -->
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">

	<!-- SweetAlert2 Theme -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css">

	<!-- SweetAlert2 JS (WAJIB DI SINI) -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<!-- AlpineJS -->
	<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

	@vite(['resources/css/app.css'])

	<style>
		.swal2-toast {
			border-radius: 16px !important;
			padding: 14px 18px !important;
			box-shadow: 0 10px 25px rgba(0, 0, 0, .15);
		}

		.swal2-toast .swal2-title {
			margin: 0 !important;
			font-size: 14px !important;
			font-weight: 500;
			text-align: left !important;
		}

		/* =========================
			SIDEBAR MODERN STYLE
			========================= */

		.main-sidebar {
			background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
			border-right: 1px solid #e5e7eb;
		}

		/* Parent menu */
		.nav-sidebar .nav-link {
			border-radius: 12px;
			margin: 2px 0px;
			padding: 6px 15px;
			color: #374151;
			transition: all .25s ease;
			display: flex;
			align-items: center;
			gap: 5px;
		}

		/* Icon */
		.nav-sidebar .nav-link .nav-icon {
			font-size: 16px;
			opacity: .85;
		}

		/* Hover effect */
		.nav-sidebar .nav-link:hover {
			background: #eef2ff;
			color: #4338ca;
			transform: translateX(3px);
		}

		/* ACTIVE parent */
		.nav-sidebar .nav-link.active {
			background: linear-gradient(135deg, #2563eb, #4f46e5);
			color: #fff !important;
			box-shadow: 0 6px 14px rgba(37, 99, 235, .35);
		}

		/* Active icon */
		.nav-sidebar .nav-link.active .nav-icon {
			color: #fff;
		}

		/* Treeview container */
		.nav-sidebar .nav-treeview {
			margin-left: 5px;
			padding-left: 5px;
			border-left: 2px dashed #c7d2fe;
		}

		/* Child menu */
		.nav-sidebar .nav-treeview .nav-link {
			font-size: 13px;
			padding: 8px;
			margin: 3px;
			border-radius: 10px;
			color: #4b5563;
		}

		/* Child hover */
		.nav-sidebar .nav-treeview .nav-link:hover {
			background: #e0e7ff;
			color: #3730a3;
		}

		/* Child active */
		.nav-sidebar .nav-treeview .nav-link.active {
			background: #c7d2fe;
			color: #312e81;
			font-weight: 600;
		}

		/* Arrow animation */
		.nav-sidebar .right {
			transition: transform .3s ease;
		}

		.nav-item.menu-open>.nav-link .right {
			transform: rotate(-90deg);
		}

		/* =========================
			CHILD MENU ACTIVE FIX
			========================= */

		/* child active ketika parent menu-open */
		.nav-sidebar .menu-open>.nav-treeview .nav-link.active {
			background: linear-gradient(135deg, #6366f1, #4f46e5);
			color: #fff !important;
			font-weight: 600;
			box-shadow: 0 4px 10px rgba(79, 70, 229, 0.35);
		}

		/* icon child active */
		.nav-sidebar .menu-open>.nav-treeview .nav-link.active .nav-icon {
			color: #fff;
		}

		/* biar ga ketiban parent hover */
		.nav-sidebar .menu-open>.nav-treeview .nav-link {
			background: transparent;
		}
	</style>

	<style>
		/* =========================
			DATATABLES CUSTOM STYLE
			========================= */

		.dt-buttons {
			margin-bottom: 1rem;
		}

		.dt-buttons .btn {
			margin-right: 0.5rem;
			margin-bottom: 0.5rem;
			border-radius: 0.5rem;
			font-size: 0.875rem;
		}

		.dataTables_wrapper .dataTables_filter {
			float: right;
		}

		.dataTables_wrapper .dataTables_filter input {
			border-radius: 0.5rem;
			border: 1px solid #d1d5db;
			padding: 0.375rem 0.75rem;
			margin-left: 0.5rem;
		}

		.dataTables_wrapper .dataTables_length select {
			border-radius: 0.5rem;
			border: 1px solid #d1d5db;
			padding: 0.375rem 0.75rem;
			margin: 0 0.5rem;
		}

		.dataTables_wrapper .dataTables_info {
			padding-top: 1rem;
		}

		.dataTables_wrapper .dataTables_paginate {
			padding-top: 1rem;
		}

		.page-item.active .page-link {
			background-color: #4f46e5;
			border-color: #4f46e5;
		}

		.page-link {
			border-radius: 0.375rem;
			color: #4f46e5;
			margin: 0 2px;
		}

		.page-link:hover {
			background-color: #eef2ff;
			color: #4338ca;
		}

		table.dataTable thead th {
			border-bottom: 2px solid #e5e7eb;
		}

		table.dataTable tbody tr:hover {
			background-color: #f9fafb;
		}
	</style>

</head>

<body class="hold-transition sidebar-mini layout-fixed sidebar-collapse si text-sm">

	{{-- TOAST ERROR --}}
	@if ($errors->any())
		<script>
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'error',
				title: @json($errors->first()),
				showConfirmButton: false,
				timer: 7000
			});
		</script>
	@endif

	{{-- TOAST SUCCESS --}}
	@if (session('success'))
		<script>
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'success',
				title: @json(session('success')),
				showConfirmButton: false,
				timer: 7000
			});
		</script>
	@endif

	<div class="wrapper">
		@include('layouts.partials.navbar')
		@include('layouts.partials.sidebar')

		<div class="content-wrapper">
			<section class="content">

				{{-- BACK BUTTON --}}
				@if (!request()->routeIs('*.dashboard'))
					<div class="px-2 pt-3">
						<button onclick="goBack()" class="btn btn-outline-secondary rounded-pill px-2 shadow-sm">
							<i class="fas fa-arrow-left"></i>
							<span>Kembali</span>
						</button>
					</div>
				@endif

				<div class="container-fluid py-3">
					@yield('content')
				</div>

			</section>
		</div>

		@include('layouts.partials.footer')
	</div>

	<!-- jQuery -->
	<script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>
	<!-- Bootstrap -->
	<script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
	<!-- AdminLTE -->
	<script src="{{ asset('adminlte/dist/js/adminlte.min.js') }}"></script>

	<!-- DataTables & Plugins -->
	<script src="{{ asset('adminlte/plugins/datatables/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>

	<!-- Export Buttons -->
	<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
	<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

	<script>
		function goBack() {
			if (document.referrer !== "") {
				window.history.back();
			} else {
				window.location.href = "{{ route('dashboard') }}";
			}
		}
	</script>
	@stack('scripts')
</body>

</html>
