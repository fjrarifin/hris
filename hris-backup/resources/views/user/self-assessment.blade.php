<form method="POST" action="{{ route('self.store') }}" class="mt-6 space-y-5">

	@csrf

	<div class="rounded-3xl border bg-white p-5 shadow-sm">
		<h3 class="text-lg font-extrabold text-gray-900">
			Self Assessment
		</h3>

		<div class="mt-4 space-y-4">

			{{-- Kesulitan --}}
			<div>
				<label class="text-sm font-bold text-gray-700">
					Kesulitan yang dialami selama bekerja
				</label>
				<textarea name="kesulitan" rows="4" required class="mt-2 w-full rounded-xl border">{{ old('kesulitan', $self->kesulitan ?? '') }}</textarea>
			</div>

			{{-- Improvement Diri --}}
			<div>
				<label class="text-sm font-bold text-gray-700">
					Harapan improvement diri
				</label>
				<textarea name="improvement" rows="4" required class="mt-2 w-full rounded-xl border" placeholder="Contoh: meningkatkan kemampuan komunikasi dengan customer, memperdalam pemahaman SOP operasional, atau mengikuti training tertentu untuk menunjang pekerjaan.">{{ old('improvement', $self->improvement ?? '') }}</textarea>

			</div>

		</div>
	</div>

	<div class="rounded-3xl border bg-white p-5 shadow-sm">
		<div class="mt-4 space-y-4">

			{{-- Perbaikan Hompimplay --}}
			<div>
				<label class="text-sm font-bold text-gray-700">
					Harapan perbikan untuk Hompimplay
				</label>
				<textarea name="perbaikan_hompimplay" rows="4" required class="mt-2 w-full rounded-xl border" placeholder="Contoh: perbaikan alur kerja, pembagian jadwal yang lebih jelas, peningkatan fasilitas kerja, atau komunikasi antar tim yang lebih efektif.">{{ old('perbaikan_hompimplay', $self->perbaikan_hompimplay ?? '') }}</textarea>

			</div>

			{{-- Catatan Rekan --}}
			<div>
				<label class="text-sm font-bold text-gray-700">
					Evaluasi rekan kerja (STAR)
				</label>
				<textarea name="catatan_rekan" rows="4" class="mt-2 w-full rounded-xl border" placeholder="Tuliskan nama lengkap rekan kerja & catatan evaluasinya menggunakan metode STAR (Bisa lebih dari 1 karyawan)">{{ old('catatan_rekan', $self->catatan_rekan ?? '') }}</textarea>
			</div>


			<div>
				<label class="text-sm font-bold text-gray-700">
					Keterangan :
				</label>
				<p>
					Metode STAR
				<ul>
					<li>- Situation: konteks kejadian</li>
					<li>- Task: peran/tanggung jawab individu</li>
					<li>- Action: tindakan nyata yang dilakukan</li>
					<li>- Result: dampak/hasil (ke tim, customer, target)</li>
				</ul>
				</p>
				<p class="mt-2">
					Contoh Pengisian :
					Saat jam ramai akhir pekan di area playground. Emil seharusnya bertanggung jawab untuk memberikan arahan dan
					bantuan kepada pengunjung. Namun Emil menunggu hingga customer bertanya terlebih dahulu. Sehingga beberapa customer
					terlihat kebingungan dan harus bolak-balik bertanya ke staff lain.
				</p>
			</div>

		</div>

		<div class="mt-5 flex justify-end">
			<button type="submit"
				class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
				Simpan Self Assessment 💾
			</button>
		</div>
	</div>
</form>
