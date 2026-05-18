@once
	<style>
		.date-picker-wrap {
			position: relative;
		}

		.app-date-calendar {
			position: absolute;
			top: calc(100% + 6px);
			left: 0;
			width: 292px;
			z-index: 2050;
			border: 1px solid #e5e7eb;
			border-radius: 18px;
			background: #fff;
			box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
			padding: 12px;
		}

		.app-date-calendar-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 10px;
		}

		.app-date-calendar-title {
			font-size: 13px;
			font-weight: 800;
			color: #0f172a;
		}

		.app-date-calendar-nav {
			display: inline-flex;
			width: 28px;
			height: 28px;
			align-items: center;
			justify-content: center;
			border: 1px solid #e5e7eb;
			border-radius: 999px;
			background: #fff;
			color: #334155;
		}

		.app-date-calendar-grid {
			display: grid;
			grid-template-columns: repeat(7, 1fr);
			gap: 4px;
		}

		.app-date-calendar-weekday {
			padding: 5px 0;
			text-align: center;
			font-size: 10px;
			font-weight: 800;
			color: #64748b;
		}

		.app-date-calendar-day {
			width: 34px;
			height: 34px;
			border: 0;
			border-radius: 10px;
			background: #fff;
			color: #0f172a;
			font-size: 12px;
			font-weight: 700;
		}

		.app-date-calendar-day:not(:disabled):hover {
			background: #dbeafe;
			color: #1d4ed8;
		}

		.app-date-calendar-day.is-selected {
			background: #2563eb;
			color: #fff;
		}

		.app-date-calendar-day:disabled {
			cursor: not-allowed;
			background: #f1f5f9;
			color: #cbd5e1;
			text-decoration: line-through;
		}

		.app-date-calendar-empty {
			width: 34px;
			height: 34px;
		}

		.app-date-help {
			display: block;
			margin-top: 6px;
			font-size: 11px;
			font-weight: 600;
			color: #64748b;
		}
	</style>

	<script>
		(function() {
			if (window.createAppDatePicker) return;

			const monthNames = [
				'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
				'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
			];
			const weekdays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

			function pad(value) {
				return String(value).padStart(2, '0');
			}

			function formatDate(date) {
				return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
			}

			function parseDate(value) {
				if (!value) return null;
				const parts = String(value).split('-').map(Number);
				if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
				return new Date(parts[0], parts[1] - 1, parts[2]);
			}

			function addDays(value, days) {
				const date = parseDate(value);
				if (!date) return null;
				date.setDate(date.getDate() + days);
				return formatDate(date);
			}

			function getOptionValue(option, fallback = '') {
				return typeof option === 'function' ? option() : (option ?? fallback);
			}

			window.datePickerAddDays = addDays;

			window.createAppDatePicker = function(input, options = {}) {
				const disabledDates = new Set(options.disabledDates || []);
				let current = parseDate(input.value) || parseDate(getOptionValue(options.minDate)) || new Date();
				let calendar = null;

				input.setAttribute('readonly', 'readonly');
				input.setAttribute('autocomplete', 'off');
				input.placeholder = input.placeholder || 'YYYY-MM-DD';

				if (input.parentElement) {
					input.parentElement.classList.add('date-picker-wrap');
				}

				function closeCalendar() {
					if (calendar) {
						calendar.remove();
						calendar = null;
					}
				}

				function isDisabled(dateValue) {
					const minDate = getOptionValue(options.minDate);
					const maxDate = getOptionValue(options.maxDate);

					if (minDate && dateValue < minDate) return true;
					if (maxDate && dateValue > maxDate) return true;
					if (disabledDates.has(dateValue)) return true;
					if (typeof options.isDisabled === 'function' && options.isDisabled(dateValue)) return true;

					return false;
				}

				function disabledTitle(dateValue) {
					const minDate = getOptionValue(options.minDate);
					const maxDate = getOptionValue(options.maxDate);

					if (minDate && dateValue < minDate) return 'Tanggal sebelum hari ini tidak bisa dipilih';
					if (maxDate && dateValue > maxDate) return 'Tanggal melewati batas pengajuan';
					if (disabledDates.has(dateValue)) return options.disabledTitle || 'Tanggal ini sudah dipakai pengajuan lain';
					return '';
				}

				function renderCalendar() {
					closeCalendar();

					const wrapper = input.parentElement || input;
					calendar = document.createElement('div');
					calendar.className = 'app-date-calendar';

					const header = document.createElement('div');
					header.className = 'app-date-calendar-header';

					const prev = document.createElement('button');
					prev.type = 'button';
					prev.className = 'app-date-calendar-nav';
					prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
					prev.addEventListener('click', function(e) {
						e.stopPropagation();
						current.setMonth(current.getMonth() - 1);
						renderCalendar();
					});

					const title = document.createElement('div');
					title.className = 'app-date-calendar-title';
					title.textContent = `${monthNames[current.getMonth()]} ${current.getFullYear()}`;

					const next = document.createElement('button');
					next.type = 'button';
					next.className = 'app-date-calendar-nav';
					next.innerHTML = '<i class="fas fa-chevron-right"></i>';
					next.addEventListener('click', function(e) {
						e.stopPropagation();
						current.setMonth(current.getMonth() + 1);
						renderCalendar();
					});

					header.append(prev, title, next);

					const grid = document.createElement('div');
					grid.className = 'app-date-calendar-grid';

					weekdays.forEach(function(day) {
						const weekday = document.createElement('div');
						weekday.className = 'app-date-calendar-weekday';
						weekday.textContent = day;
						grid.appendChild(weekday);
					});

					const firstDay = new Date(current.getFullYear(), current.getMonth(), 1);
					const totalDays = new Date(current.getFullYear(), current.getMonth() + 1, 0).getDate();

					for (let i = 0; i < firstDay.getDay(); i++) {
						const empty = document.createElement('div');
						empty.className = 'app-date-calendar-empty';
						grid.appendChild(empty);
					}

					for (let day = 1; day <= totalDays; day++) {
						const date = new Date(current.getFullYear(), current.getMonth(), day);
						const dateValue = formatDate(date);
						const button = document.createElement('button');
						button.type = 'button';
						button.className = 'app-date-calendar-day';
						button.textContent = day;
						button.disabled = isDisabled(dateValue);
						button.title = disabledTitle(dateValue);

						if (input.value === dateValue) {
							button.classList.add('is-selected');
						}

						button.addEventListener('click', function(e) {
							e.stopPropagation();
							if (button.disabled) return;

							input.value = dateValue;
							input.dispatchEvent(new Event('change', {
								bubbles: true
							}));
							closeCalendar();
						});

						grid.appendChild(button);
					}

					calendar.append(header, grid);
					wrapper.appendChild(calendar);
				}

				input.addEventListener('focus', renderCalendar);
				input.addEventListener('click', function(e) {
					e.stopPropagation();
					renderCalendar();
				});

				document.addEventListener('click', function(e) {
					if (!calendar) return;
					if (calendar.contains(e.target) || e.target === input) return;
					closeCalendar();
				});
			};
		})();
	</script>
@endonce
