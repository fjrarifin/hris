<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="{{ asset('img/hompimplay_icon.png') }}" type="image/png">

	<title>{{ config('app.name', 'HRIS') }}</title>

	{{-- Tailwind via Vite --}}
	@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 text-gray-800">

	{{-- Content --}}
	<main class="mx-auto max-w-6xl px-4 py-4">
		{{-- SLOT CONTENT --}}
		<div class="mt-8">
			<div class="flex min-h-[75vh] items-center justify-center">
				<div class="w-full max-w-md">

					<div class="rounded-2xl border bg-white p-6 shadow md:p-8">
						<div class="px-1">
							<div class="rounded-2xl p-4">
								<div class="flex items-center justify-center">
									<div class="flex w-1/2 items-center justify-center overflow-hidden rounded-2xl bg-gray-50">
										<img src="{{ asset('img/hompimplay_logo.png') }}" alt="Logo Perusahaan" class="h-full w-full object-contain">
									</div>
								</div>
							</div>
						</div>
						{{-- Under Maintenance Message --}}
						<div class="space-y-4 text-center">
							{{-- Icon --}}
							<div class="flex justify-center">
								<div class="rounded-full bg-yellow-100 p-4">
									<svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
											d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
									</svg>
								</div>
							</div>

							{{-- Title --}}
							<h1 class="text-2xl font-bold text-gray-900">
								Sistem Sedang Dalam Pengembangan
							</h1>

							{{-- Description --}}
							<p class="leading-relaxed text-gray-600">
								Mohon maaf atas ketidaknyamanannya. Saat ini kami sedang melakukan pengembangan dan perbaikan sistem HRIS untuk
								memberikan pengalaman yang lebih baik.
							</p>

							{{-- Additional Info --}}
							<div class="mt-6 rounded-xl border border-indigo-100 bg-indigo-50 p-4">
								<p class="text-sm text-indigo-800">
									<i class="fas fa-info-circle mr-2"></i>
									Silakan coba kembali beberapa saat lagi atau hubungi tim IT untuk informasi lebih lanjut.
								</p>
							</div>

							{{-- Contact Info (Optional) --}}
							<div class="mt-6 border-t border-gray-200 pt-6">
								<p class="text-xs text-gray-500">
									Butuh bantuan? Hubungi
									<a href="mailto:it@company.com" class="font-medium text-indigo-600 hover:text-indigo-500">
										IT Support
									</a>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>

	<footer class="py-6 text-center text-sm text-gray-500">
		© {{ date('Y') }} {{ config('app.name', 'HRIS') }} — built by Fajar ✅
	</footer>

</body>

</html>
