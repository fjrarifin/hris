<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<title>@yield('title', 'HRGA - Approval')</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Font Awesome -->
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
	<!-- AdminLTE CSS -->
	<link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">
	<link rel="icon" href="{{ asset('hompimplay_icon.png') }}">

	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	@vite(['resources/css/app.css'])

	<style>
		body {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1rem;
			margin: 0;
		}

		.approval-card {
			background: white;
			border-radius: 16px;
			box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
			overflow: hidden;
			max-width: 500px;
			width: 100%;
			margin: auto;
		}

		.header-gradient {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			padding: 1.5rem;
			text-align: center;
			color: white;
		}

		.success-animation {
			animation: scaleIn 0.4s ease-out;
		}

		@keyframes scaleIn {
			from {
				transform: scale(0.9);
				opacity: 0;
			}
			to {
				transform: scale(1);
				opacity: 1;
			}
		}

		@media (max-width: 640px) {
			.approval-card {
				border-radius: 12px;
				max-height: 95vh;
				overflow-y: auto;
			}
			
			.header-gradient {
				padding: 1rem;
			}
		}
	</style>

	@stack('styles')
</head>

<body>
	<div class="w-full max-w-lg mx-auto">
		@yield('content')
	</div>

	<!-- jQuery -->
	<script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>
	<!-- Bootstrap -->
	<script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

	@stack('scripts')
</body>

</html>