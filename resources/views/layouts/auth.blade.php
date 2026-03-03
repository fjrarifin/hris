<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>@yield('title', 'Login')</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- AdminLTE -->
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
	<link rel="stylesheet" href="{{ asset('adminlte/dist/css/adminlte.min.css') }}">

	<link rel="icon" href="{{ asset('hompimplay_icon.png') }}" type="image/png">
	<!-- Tailwind -->
	@vite(['resources/css/app.css'])
</head>

<body class="hold-transition login-page">
	@yield('content')

	<script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
	<script src="{{ asset('adminlte/dist/js/adminlte.min.js') }}"></script>
</body>

</html>
