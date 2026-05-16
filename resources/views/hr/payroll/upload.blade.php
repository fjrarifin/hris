@extends('layouts.app')

@section('title', 'Unggah Payroll')
@section('page-title', 'Unggah Payroll')

@section('content')
	<!-- Modal Preview -->
	<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
		<div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-96 flex flex-col">
			<div class="p-6 border-b flex justify-between items-center">
				<h2 class="text-lg font-bold text-gray-900">👁️ Pratinjau File Excel</h2>
				<button onclick="closePreview()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
			</div>

			<div class="p-6 overflow-auto flex-1">
				<div class="overflow-x-auto">
					<table id="previewTable" class="w-full border-collapse text-sm">
						<thead>
							<tr class="bg-gray-100"></tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<p id="previewInfo" class="text-gray-600 text-sm mt-4"></p>
			</div>

			<div class="p-6 border-t flex gap-3 justify-end bg-gray-50">
				<button onclick="closePreview()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
					❌ Batal
				</button>
				<button onclick="confirmUpload()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
					✅ Lanjutkan Unggah
				</button>
			</div>
		</div>
	</div>

	<style>
		#uploadForm input[type="file"] {
			display: block;
		}

		.upload-area {
			border: 2px dashed #cbd5e1;
			border-radius: 10px;
			padding: 30px;
			text-align: center;
			transition: all 0.3s ease;
		}

		.upload-area:hover {
			border-color: #3b82f6;
			background-color: #f0f9ff;
		}

		.upload-area.dragover {
			border-color: #3b82f6;
			background-color: #eff6ff;
		}

		#message {
			margin-top: 20px;
			padding: 12px 16px;
			border-radius: 8px;
			display: none;
		}

		#message.show {
			display: block;
		}

		#message.success {
			background-color: #d1fae5;
			color: #065f46;
			border-left: 4px solid #10b981;
		}

		#message.error {
			background-color: #fee2e2;
			color: #991b1b;
			border-left: 4px solid #ef4444;
		}

		/* Preview Table Styles */
		#previewTable {
			border: 1px solid #e2e8f0;
		}

		#previewTable thead th {
			background-color: #f1f5f9;
			border: 1px solid #e2e8f0;
			padding: 10px;
			text-align: left;
			font-weight: 600;
			color: #334155;
		}

		#previewTable tbody td {
			border: 1px solid #e2e8f0;
			padding: 10px;
			color: #475569;
		}

		#previewTable tbody tr:nth-child(odd) {
			background-color: #f8fafc;
		}

		#previewTable tbody tr:hover {
			background-color: #f0f9ff;
		}
	</style>

	<div class="space-y-4">
		<div class="card card-primary card-outline rounded-2xl shadow-sm">
			<div class="card-header">
				<h3 class="card-title mb-0">📤 Unggah Payroll</h3>
			</div>

			<div class="card-body">
				<form id="uploadForm">
					@csrf
					
					<div class="upload-area">
						<div class="mb-4">
							<svg class="mx-auto mb-3 h-20 w-20 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
								<path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-12l-3.172-3.172a4 4 0 00-5.656 0L28 12m0 0l-3.172-3.172a4 4 0 00-5.656 0L12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</div>

						<label for="fileInput" class="cursor-pointer">
							<span class="inline-block bg-blue-50 px-4 py-2 rounded-lg text-blue-600 font-semibold hover:bg-blue-100 transition">
								Pilih File Excel
							</span>
						</label>

						<p class="mt-2 text-sm text-gray-500">
							atau drag and drop file Excel ke sini
						</p>

						<input 
							id="fileInput"
							type="file" 
							name="file" 
							required 
							accept=".xlsx,.xls,.csv"
							class="hidden"
						>

						<p id="fileName" class="mt-3 text-xs text-gray-600"></p>
					</div>

					<div class="mt-6 flex gap-3">
						<button 
							type="submit"
							class="inline-flex items-center px-4 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 transition duration-200"
						>
							<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
							</svg>
							Pratinjau & Unggah
						</button>

						<button 
							type="reset"
							class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-400 transition duration-200"
						>
							<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
							Reset
						</button>
					</div>
				</form>

				<div id="message"></div>
			</div>
		</div>
	</div>

	<script>
		// Cek apakah XLSX library sudah ter-load
		if (typeof XLSX === 'undefined') {
			console.error('XLSX library belum ter-load');
			// Fallback: load dari CDN alternate
			const script = document.createElement('script');
			script.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
			script.onload = function() {
				console.log('XLSX library berhasil di-load');
			};
			document.head.appendChild(script);
		}

		const uploadArea = document.querySelector('.upload-area');
		const fileInput = document.getElementById('fileInput');
		let selectedFile = null;
		let isUploadConfirmed = false;

		// Drag and drop
		uploadArea.addEventListener('dragover', (e) => {
			e.preventDefault();
			uploadArea.classList.add('dragover');
		});

		uploadArea.addEventListener('dragleave', () => {
			uploadArea.classList.remove('dragover');
		});

		uploadArea.addEventListener('drop', (e) => {
			e.preventDefault();
			uploadArea.classList.remove('dragover');
			fileInput.files = e.dataTransfer.files;
			document.getElementById('fileName').textContent = e.dataTransfer.files[0]?.name || 'File dipilih';
		});

		// File input change handler
		fileInput.addEventListener('change', function(e) {
			const fileName = this.files[0]?.name;
			document.getElementById('fileName').textContent = fileName || '';
			selectedFile = this.files[0];
		});

		// Preview Excel file
		function previewExcelFile() {
			if (!selectedFile) {
				alert('Pilih file terlebih dahulu!');
				return;
			}

			// Cek apakah XLSX library available
			if (typeof XLSX === 'undefined') {
				alert('⚠️ Library XLSX belum siap. Silakan refresh halaman dan coba lagi.');
				console.error('XLSX is not defined');
				return;
			}

			const reader = new FileReader();
			
			reader.onload = function(e) {
				try {
					const data = new Uint8Array(e.target.result);
					const workbook = XLSX.read(data, { type: 'array' });
					const firstSheet = workbook.SheetNames[0];
					const worksheet = workbook.Sheets[firstSheet];

					// Get all data
					const allRows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
					
					// Limit to first 10 rows
					const previewRows = allRows.slice(0, 10);
					
					if (previewRows.length === 0) {
						alert('File Excel kosong!');
						return;
					}

					// Get headers (first row)
					const headers = previewRows[0];
					
					// Build table
					const thead = document.querySelector('#previewTable thead tr');
					const tbody = document.querySelector('#previewTable tbody');
					
					// Clear existing content
					thead.innerHTML = '';
					tbody.innerHTML = '';

					// Add headers
					headers.forEach(header => {
						const th = document.createElement('th');
						th.textContent = header || '-';
						thead.appendChild(th);
					});

					// Add data rows
					for (let i = 1; i < previewRows.length; i++) {
						const row = previewRows[i];
						const tr = document.createElement('tr');
						
						headers.forEach((_, colIndex) => {
							const td = document.createElement('td');
							td.textContent = row[colIndex] ?? '-';
							tr.appendChild(td);
						});
						
						tbody.appendChild(tr);
					}

					// Show info
					const totalRows = allRows.length - 1; // exclude header
					document.getElementById('previewInfo').innerHTML = 
						`<strong>📊 Info File:</strong><br>
						Total kolom: ${headers.length} | Total baris data: ${totalRows} | 
						Menampilkan: ${Math.min(10, totalRows)} baris pertama`;

					// Show modal
					document.getElementById('previewModal').classList.remove('hidden');
					isUploadConfirmed = false;

				} catch (error) {
					console.error('Error membaca file:', error);
					
					let errorMsg = 'Error membaca file: ' + error.message;
					
					// Handle specific errors
					if (error.message.includes('XLSX') || typeof XLSX === 'undefined') {
						errorMsg = '⚠️ Library XLSX belum ter-load. Silakan refresh halaman dan coba lagi.';
					} else if (error.message.includes('not a valid') || error.message.includes('Cannot read')) {
						errorMsg = '❌ File tidak valid atau format tidak didukung. Gunakan format .xlsx, .xls, atau .csv';
					}
					
					alert(errorMsg);
				}
			};

			reader.readAsArrayBuffer(selectedFile);
		}

		// Close preview
		function closePreview() {
			document.getElementById('previewModal').classList.add('hidden');
			isUploadConfirmed = false;
		}

		// Confirm upload
		function confirmUpload() {
			isUploadConfirmed = true;
			closePreview();
			performUpload();
		}

		// Form submit - show preview instead of direct upload
		document.getElementById('uploadForm').addEventListener('submit', function(e) {
			e.preventDefault();

			if (isUploadConfirmed) {
				performUpload();
			} else {
				previewExcelFile();
			}
		});

		// Perform actual upload
		function performUpload() {
			let formData = new FormData(document.getElementById('uploadForm'));
			let messageEl = document.getElementById('message');
			let uploadBtn = document.querySelector('#uploadForm button[type="submit"]');

			// Show loading state
			messageEl.textContent = '⏳ Mengupload...';
			messageEl.className = 'show';
			messageEl.style.background = '#fef3c7';
			messageEl.style.borderLeftColor = '#f59e0b';
			messageEl.style.color = '#92400e';

			// Disable button
			uploadBtn.disabled = true;
			uploadBtn.style.opacity = '0.6';

			fetch("{{ url('/hr/payroll/upload') }}", {
					method: "POST",
					body: formData,
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
					}
				})
				.then(res => res.json())
				.then(data => {
					if (data.status) {
						messageEl.innerHTML = "✅ " + data.message;
						messageEl.className = "show success";
						document.getElementById('uploadForm').reset();
						document.getElementById('fileName').textContent = '';
						isUploadConfirmed = false;
						selectedFile = null;
					} else {
						messageEl.innerHTML = "❌ " + (data.error || data.message);
						messageEl.className = "show error";
					}
				})
				.catch(err => {
					messageEl.innerHTML = "❌ Unggah gagal. Silakan coba lagi.";
					messageEl.className = "show error";
				})
				.finally(() => {
					uploadBtn.disabled = false;
					uploadBtn.style.opacity = '1';
					isUploadConfirmed = false;
				});
		}
	</script>

@endsection
