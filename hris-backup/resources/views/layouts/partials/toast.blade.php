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
