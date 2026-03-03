<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="{{ asset('img/hompimplay_icon.png') }}" type="image/png">
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
	<link rel="stylesheet" href="{{ asset('adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">


	<title>@yield('title', 'Dashboard') - {{ config('app.name', 'HRIS') }}</title>

	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-50 text-gray-800">

	<div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">

		@include('layouts.partials.sidebar')

		<div class="flex h-screen flex-1 flex-col overflow-hidden">
			@include('layouts.partials.navbar')

			<main class="flex-1 overflow-y-auto px-4 py-5 md:px-6">
				@yield('content')
			</main>

			@include('layouts.partials.footer')
		</div>
	</div>

	@include('layouts.partials.toast')

	<script src="{{ asset('adminlte/plugins/datatables/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>

	<script src="{{ asset('adminlte/plugins/jszip/jszip.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/pdfmake/pdfmake.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/pdfmake/vfs_fonts.js') }}"></script>

	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
	<script src="{{ asset('adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>



</body>

</html>
