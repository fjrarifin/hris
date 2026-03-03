@extends('layouts.app')

@section('title', 'Self Assessment')
@section('page-title', 'Self Assessment')

@section('content')
	<form method="POST" action="{{ route('staff.self-assessment.store') }}">
		@csrf
		<div class="card card-primary card-outline rounded-xl text-xs">
			<div class="card-header">
				<h3 class="card-title">Self Assessment</h3>
			</div>

			<div class="card-body">

				<div class="form-group">
					<label>Kesulitan yang dihadapi</label>
					<textarea name="kesulitan" class="form-control text-sm" rows="4" required
					 placeholder="Ceritakan tantangan pekerjaan Anda">{{ $self->kesulitan ?? '' }}</textarea>
				</div>

				<div class="form-group">
					<label>Harapan improvement diri</label>
					<textarea name="improvement" class="form-control text-sm" rows="4" required
					 placeholder="Contoh: meningkatkan kemampuan komunikasi dengan customer, memperdalam pemahaman SOP operasional, atau mengikuti training tertentu untuk menunjang pekerjaan.">{{ $self->improvement ?? '' }}</textarea>
				</div>
			</div>
		</div>

		<div class="card card-primary card-outline rounded-xl text-xs">
			<div class="card-body">
				<div class="form-group">
					<label>Perbaikan pada Hompimplay</label>
					<textarea name="perbaikan_hompimplay" class="form-control text-sm" rows="4" required
					 placeholder="Contoh: perbaikan alur kerja, pembagian jadwal yang lebih jelas, peningkatan fasilitas kerja, atau komunikasi antar tim yang lebih efektif.">{{ $self->perbaikan_hompimplay ?? '' }}</textarea>

				</div>

				<div class="form-group">
					<label>Masukan untuk Rekan / Atasan</label>
					<textarea name="catatan_rekan" class="form-control text-sm" rows="4" required
					 placeholder="Tuliskan nama lengkap rekan kerja & catatan evaluasinya menggunakan metode STAR (Bisa lebih dari 1 karyawan)">{{ $self->catatan_rekan ?? '' }}</textarea>
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

			<div class="card-footer text-right">
				<button type="button" class="btn btn-primary btn-sm rounded-xl" id="btnSubmitSelf">
					<i class="fas fa-save"></i> Simpan
				</button>

			</div>
		</div>
	</form>

	<script>
		document.getElementById('btnSubmitSelf').addEventListener('click', function() {

			Swal.fire({
				title: 'Simpan Self Assessment?',
				text: 'Pastikan isian sudah benar sebelum disimpan.',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Ya, Simpan',
				cancelButtonText: 'Batal',
				confirmButtonColor: '#007bff',
				cancelButtonColor: '#6c757d'
			}).then((result) => {
				if (result.isConfirmed) {
					this.closest('form').submit();
				}
			});

		});
	</script>

@endsection
