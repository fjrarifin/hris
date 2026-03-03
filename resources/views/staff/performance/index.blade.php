@extends('layouts.app')

@section('title', 'Performance')
@section('page-title', 'Performance')

@section('content')

	<div class="space-y-5">

		@if ($relasi->count() == 0)


			{{-- EMPTY STATE --}}
			<div class="rounded-3xl border border-gray-200/70 bg-white p-8 text-center shadow-sm">
				<div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-50 text-2xl">
					👥
				</div>

				<h2 class="mt-4 text-xl font-extrabold text-gray-900">
					Belum Ada Relasi Penilaian
				</h2>

				<p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-gray-600">
					Saat ini kamu belum memiliki daftar relasi (atasan / peer / bawahan) untuk dinilai pada periode ini.
					Silakan hubungi Admin / HR untuk menambahkan relasi kamu terlebih dahulu.
				</p>
			</div>
		@else
			<div class="row">
				<div class="col-12">
					<div class="card card-primary card-outline rounded-xl">
						<div class="card-body">
							<div class="flex items-center justify-between gap-4">
								<div>
									<h2 class="text-lg font-extrabold text-gray-900">Form Penilaian</h2>
								</div>

								<span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
									Faktor dinamis per level • Skala 1 - 5
								</span>

							</div>
						</div>
					</div>
				</div>
			</div>
			{{-- Form --}}
			<form x-data="penilaianForm({{ Js::from($relasi) }}, {{ Js::from($faktorByRelasi) }})" method="POST" action="{{ route('staff.performance.store') }}">
				@csrf

				<div x-data="relasiAccordion()" x-init="init()" class="space-y-4">
					@foreach ($relasi as $rIndex => $relasiItem)
						<div
							class="card card-outline card-primary overflow-hidden rounded-xl border border-gray-200/70 bg-white shadow-sm">

							{{-- HEADER CARD --}}
							<button type="button" @click="toggle({{ $rIndex }})"
								class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left transition hover:bg-gray-50">
								<div class="min-w-0">
									<p class="text-md truncate font-bold text-gray-900">
										{{ $relasiItem->nama_karyawan }}
									</p>
									<p class="mt-1 text-xs text-gray-500">
										{{ $relasiItem->jabatan }}
									</p>
								</div>

								<div class="flex shrink-0 items-center gap-2">

									{{-- Badge Relasi --}}
									{{-- <span class="px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold">
                                    Relasi #{{ $rIndex + 1 }}
                                </span> --}}

									{{-- ✅ Progress Badge --}}
									<span class="rounded-full border px-3 py-1 text-xs font-bold"
										:class="progress[{{ $rIndex }}]?.isDone ?
										    'bg-green-50 text-green-700 border-green-200' :
										    'bg-gray-50 text-gray-700 border-gray-200'"
										x-text="progress[{{ $rIndex }}]?.text ?? '0/0'"></span>

									{{-- Arrow --}}
									<svg class="h-5 w-5 text-gray-400 transition" :class="openRelasi === {{ $rIndex }} ? 'rotate-180' : ''"
										fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
									</svg>
								</div>
							</button>

							{{-- BODY CARD --}}
							<div x-show="openRelasi === {{ $rIndex }}" x-transition style="display:none;" class="px-3 pb-5">
								<div class="mt-2 space-y-4">

									@php
										$faktors = $faktorByRelasi[$relasiItem->nik_relasi] ?? collect();
									@endphp

									@if ($faktors->count() == 0)
										<div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-4">
											<p class="text-sm font-bold text-yellow-900">Template faktor belum tersedia</p>
											<p class="mt-1 text-xs text-yellow-700">
												Level: <b>{{ $relasiItem->level_penilaian ?? '-' }}</b> belum punya template faktor penilaian.
												Hubungi Admin/HR untuk setup template.
											</p>
										</div>
									@else
										@foreach ($faktors as $i => $itemFaktor)
											<div class="rounded-2xl border border-gray-200/70 p-4">
												<div class="flex items-start justify-between gap-3">
													<div>
														<p class="text-sm font-bold text-gray-900">
															{{ $i + 1 }}. {{ $itemFaktor->nama_faktor }}
														</p>

														@if ($itemFaktor->deskripsi)
															<p class="mt-1 text-xs text-gray-500">
																{{ $itemFaktor->deskripsi }}
															</p>
														@endif
													</div>
												</div>

												{{-- Skala 1-5 --}}
												<div class="mt-3 grid grid-cols-5 gap-2">
													@foreach ($itemFaktor->scores as $rowScore)
														<label class="cursor-pointer select-none">
															<input type="radio" class="peer hidden"
																name="penilaian[{{ $relasiItem->nik_relasi }}][{{ $itemFaktor->id }}]" value="{{ $rowScore->score }}"
																@change="updateProgress()">
															<div
																class="rounded-xl border px-2 py-2 text-center transition peer-checked:border-indigo-600 peer-checked:bg-indigo-100 peer-checked:ring-2 peer-checked:ring-indigo-500">
																<p class="text-sm font-extrabold text-gray-900">{{ $rowScore->score }}</p>
																<p class="text-[11px] leading-tight text-gray-500">
																	{{ $skala[$rowScore->score] ?? '' }}
																</p>
															</div>
														</label>
													@endforeach
												</div>

												<div x-data="{ openScoreDesc: false }" class="mt-3">
													<button type="button" @click="openScoreDesc = !openScoreDesc"
														class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
														<span x-text="openScoreDesc ? '▼ Sembunyikan deskripsi score' : '▶ Lihat deskripsi score'"></span>
													</button>

													<div x-show="openScoreDesc" x-transition style="display:none;"
														class="mt-3 space-y-2 rounded-2xl border border-gray-200/70 bg-gray-50 p-4">
														@foreach ($itemFaktor->scores as $rowScore)
															<div class="flex gap-3">
																<div class="w-8 shrink-0 font-extrabold text-gray-900">
																	{{ $rowScore->score }}
																</div>
																<div>
																	<p class="text-xs font-bold text-gray-800">
																		{{ $skala[$rowScore->score] ?? '' }}
																	</p>
																	<p class="text-xs leading-relaxed text-gray-600">
																		{{ $rowScore->deskripsi }}
																	</p>
																</div>
															</div>
														@endforeach
													</div>
												</div>


											</div>
										@endforeach
									@endif

									{{-- Catatan --}}
									<div class="rounded-2xl border border-gray-200/70 p-4">
										<p class="text-sm font-bold text-gray-900">Catatan (Opsional)</p>
										<textarea name="penilaian[{{ $relasiItem->nik_relasi }}][catatan]" rows="3"
										 class="mt-2 w-full rounded-xl border border-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500"
										 placeholder="Tambahkan catatan jika diperlukan..."></textarea>
									</div>

								</div>
							</div>

						</div>
					@endforeach
				</div>

				{{-- Footer Submit --}}
				<div x-data="penilaianForm()" x-init="init()"
					class="mt-3 flex flex-col gap-4 rounded-2xl border border-gray-200/70 bg-white p-3 shadow-sm md:flex-row md:items-center md:justify-between">
					<div class="flex-1">
						{{-- <p class="text-sm font-semibold text-gray-700">
							Status Penilaian:
							<span class="font-bold text-gray-900" x-text="doneText"></span>
							<span class="text-gray-500" x-text="percentText"></span>
						</p> --}}

						{{-- Progress bar kecil --}}
						<div class="mt-2 h-2 max-w-md overflow-hidden rounded-full bg-gray-100">
							<div class="h-2 rounded-full transition-all duration-300" :style="`width:${percent}%`"
								:class="percent === 100 ? 'bg-green-500' : 'bg-red-500'"></div>
						</div>

						<p class="mt-2 text-xs text-gray-500">
							Tombol submit akan aktif jika semua relasi sudah terisi lengkap.
						</p>

						<label class="mt-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
							<input type="checkbox" x-model="agree" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
							Saya setuju dan memastikan penilaian sudah benar.
						</label>
					</div>

					<div class="flex items-center gap-2">
						<button type="button" @click="handleSubmitClick()" :disabled="!canSubmit"
							class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600">
							Submit Penilaian 🔥
						</button>
					</div>

					{{-- ✅ Modal Konfirmasi Submit --}}
					<div x-show="confirmOpen" x-transition.opacity
						class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 px-4" style="display:none;">
						<div x-transition class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-4 shadow-xl"
							@click.outside="confirmOpen = false">
							<div class="flex items-start gap-3">
								<div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-50 text-lg">
									⚠️
								</div>

								<div class="flex-1">
									<h3 class="text-lg font-extrabold text-gray-900">
										Konfirmasi Submit Penilaian
									</h3>

									<p class="mt-2 text-sm leading-relaxed text-gray-600">
										Penilaian ini hanya dapat diisi dan tidak dapat dilihat kembali setelah di submit,
										mohon pastikan kembali sebelum ada submit. Terima kasih
									</p>
								</div>
							</div>

							<div class="mt-5 flex items-center justify-end gap-2">
								<button type="button" @click="confirmOpen = false"
									class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold transition hover:bg-gray-200">
									Cek Lagi
								</button>

								<button type="submit" :disabled="!canSubmit"
									class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600">
									Ya, Submit
								</button>
							</div>
						</div>
					</div>
				</div>

			</form>

		@endif

	</div>

	{{-- script untuk progress dan submit --}}
	<script>
		function penilaianForm() {
			return {
				agree: false,
				confirmOpen: false,

				total: 0,
				filled: 0,
				percent: 0,

				doneText: '0/0',
				percentText: '',

				canSubmit: false,

				init() {
					this.checkAll();

					document.addEventListener('change', () => this.checkAll());
					document.addEventListener('input', () => this.checkAll());

					this.$watch('agree', () => this.checkAll()); // 🔥 WAJIB
				},

				checkAll() {
					const relasiMap = {}

					document.querySelectorAll('input[type="radio"][name^="penilaian["]')
						.forEach(el => {
							const m = el.name.match(/^penilaian\[(.*?)\]\[(.*?)\]$/)
							if (!m || m[2] === 'catatan') return

							const nik = m[1]
							const faktor = m[2]

							relasiMap[nik] = relasiMap[nik] || {}
							relasiMap[nik][faktor] = relasiMap[nik][faktor] || false

							if (el.checked) {
								relasiMap[nik][faktor] = true
							}
						})

					const relasiKeys = Object.keys(relasiMap)
					this.total = relasiKeys.length
					this.filled = 0

					relasiKeys.forEach(nik => {
						const faktorDone = Object.values(relasiMap[nik]).every(v => v === true)
						if (faktorDone) this.filled++
					})

					this.percent = this.total > 0 ?
						Math.round((this.filled / this.total) * 100) :
						100

					this.doneText = `${this.filled}/${this.total}`
					this.percentText = `(${this.percent}%)`

					this.canSubmit = this.percent === 100 && this.agree
				},

				handleSubmitClick() {
					if (!this.canSubmit) {
						// kalau belum lengkap, scroll ke yg kosong pertama
						this.scrollToFirstEmpty();
						return;
					}

					this.confirmOpen = true;
				},

				scrollToFirstEmpty() {
					const groups = {};

					document.querySelectorAll('input[type="radio"][name^="penilaian["]').forEach((el) => {
						groups[el.name] = groups[el.name] || [];
						groups[el.name].push(el);
					});

					for (const name in groups) {
						const radios = groups[name];
						const checked = radios.some(r => r.checked);
						if (!checked) {
							radios[0].scrollIntoView({
								behavior: 'smooth',
								block: 'center'
							});
							break;
						}
					}
				}
			}
		}
	</script>

	{{-- script for relasi accordion and progress tracking --}}
	<script>
		function relasiAccordion() {
			return {
				openRelasi: null,
				progress: [],

				init() {
					this.updateProgress();
				},

				toggle(index) {
					this.openRelasi = (this.openRelasi === index) ? null : index;
				},

				updateProgress() {
					const cards = document.querySelectorAll('[data-relasi-card]');
					// kita gak pakai cards selector, jadi kita hitung progress berdasarkan nik_relasi di name

					// ambil semua group input radio per relasi
					// contoh name: penilaian[3201010101010004][5]
					const groups = {};
					document.querySelectorAll('input[type="radio"][name^="penilaian["]').forEach((el) => {
						groups[el.name] = groups[el.name] || [];
						groups[el.name].push(el);
					});

					// hitung per relasi
					// kunci relasi = nik_relasi dari name "penilaian[nik][faktor]"
					const relasiMap = {};

					Object.keys(groups).forEach((groupName) => {
						const match = groupName.match(/^penilaian\[(.*?)\]\[(.*?)\]$/);
						if (!match) return;

						const nikRelasi = match[1];
						const faktorId = match[2];

						// skip catatan
						if (faktorId === 'catatan') return;

						relasiMap[nikRelasi] = relasiMap[nikRelasi] || {
							total: 0,
							filled: 0
						};

						relasiMap[nikRelasi].total++;
						if (groups[groupName].some(r => r.checked)) {
							relasiMap[nikRelasi].filled++;
						}
					});

					// urutan progress sesuai urutan relasi muncul di halaman
					// kita ambil nik_relasi dari tombol header (cara aman: scan dari DOM dengan regex dari input)
					const nikOrder = [];
					document.querySelectorAll('input[type="radio"][name^="penilaian["]').forEach((el) => {
						const m = el.name.match(/^penilaian\[(.*?)\]\[(.*?)\]$/);
						if (m && !nikOrder.includes(m[1])) nikOrder.push(m[1]);
					});

					this.progress = nikOrder.map((nik) => {
						const row = relasiMap[nik] || {
							total: 0,
							filled: 0
						};
						const percent = row.total > 0 ? Math.round((row.filled / row.total) * 100) : 0;

						const isDone = row.total > 0 && row.filled === row.total;

						return {
							nik,
							total: row.total,
							filled: row.filled,
							percent,
							isDone,
							text: isDone ? `Lengkap ✅` : `${row.filled}/${row.total} (${percent}%)`,
						};
					});

					// ✅ BONUS: kalau relasi yang lagi dibuka sudah lengkap, auto pindah ke relasi berikutnya
					this.autoNextIfDone();
				},

				autoNextIfDone() {
					if (this.openRelasi === null) return;

					const current = this.progress[this.openRelasi];
					if (!current) return;

					if (current.isDone) {
						// cari next yg belum done
						for (let i = this.openRelasi + 1; i < this.progress.length; i++) {
							if (!this.progress[i].isDone) {
								this.openRelasi = i;
								return;
							}
						}
					}
				}
			}
		}
	</script>
@endsection
