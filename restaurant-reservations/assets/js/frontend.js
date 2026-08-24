(function ($) {
	'use strict';

	$('.rr-reservation-form').each(function () {
		var $form = $(this), $days = $form.find('.rr-days'), $slots = $form.find('.rr-time-slots');
		var today = new Date(rrFrontend.today + 'T00:00:00'), maximum = new Date(rrFrontend.maxDate + 'T00:00:00');
		var shown = new Date(today.getFullYear(), today.getMonth(), 1);

		function pad(number) { return String(number).padStart(2, '0'); }
		function dateValue(date) { return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()); }
		function message(text, type) { $form.find('.rr-message').removeClass('is-error is-success').addClass(type ? 'is-' + type : '').text(text); }
		function loading(active) { $form.toggleClass('is-loading', active).find(':submit').prop('disabled', active); }
		function step(number) {
			$form.find('.rr-form-step').removeClass('is-active').filter('[data-step="' + number + '"]').addClass('is-active');
			$form.find('.rr-progress-step').removeClass('is-active is-completed');
			$form.find('.rr-progress-step').slice(0, number - 1).addClass('is-completed');
			$form.find('.rr-progress-step').eq(number - 1).addClass('is-active');
			message('');
		}

		function renderCalendar() {
			var year = shown.getFullYear(), month = shown.getMonth(), firstDay = new Date(year, month, 1).getDay(), count = new Date(year, month + 1, 0).getDate();
			$form.find('.rr-month-title').text(rrFrontend.i18n.months[month] + ' ' + year);
			$form.find('.rr-weekdays').empty(); $.each(rrFrontend.i18n.weekdays, function (_, day) { $('<span>').text(day).appendTo($form.find('.rr-weekdays')); });
			$days.empty(); for (var blank = 0; blank < firstDay; blank += 1) { $('<span>').addClass('rr-day-empty').appendTo($days); }
			for (var day = 1; day <= count; day += 1) {
				var date = new Date(year, month, day), value = dateValue(date), disabled = date < today || date > maximum;
				$('<button>', {type: 'button', text: day, disabled: disabled, 'data-date': value, 'aria-label': value}).toggleClass('is-selected', value === $form.find('[name="date"]').val()).appendTo($days);
			}
			$form.find('.rr-month-prev').prop('disabled', shown <= new Date(today.getFullYear(), today.getMonth(), 1));
			$form.find('.rr-month-next').prop('disabled', shown >= new Date(maximum.getFullYear(), maximum.getMonth(), 1));
		}

		function loadSlots() {
			var date = $form.find('[name="date"]').val(), guests = $form.find('[name="guests"]').val(); if (!date) { return; }
			$slots.addClass('is-loading').text(rrFrontend.i18n.checking); $form.find('[name="time"]').val(''); $form.find('[data-step="1"] .rr-next').prop('disabled', true);
			$.get(rrFrontend.ajaxUrl, {action: 'rr_check_availability', nonce: rrFrontend.nonce, date: date, guests: guests}).done(function (response) {
				$slots.empty(); if (!response.success || !response.data.slots.length) { $slots.text(rrFrontend.i18n.noSlots); return; }
				$.each(response.data.slots, function (_, time) { $('<button>', {type: 'button', text: time, 'data-time': time}).appendTo($slots); });
			}).fail(function (xhr) { try { var m = JSON.parse(xhr.responseText); message(m.data && m.data.message ? m.data.message : rrFrontend.i18n.error, 'error'); } catch(e) { message(rrFrontend.i18n.error, 'error'); } }).always(function () { $slots.removeClass('is-loading'); });
		}

		$form.on('click', '.rr-days button', function () { $form.find('[name="date"]').val($(this).data('date')); $days.find('button').removeClass('is-selected'); $(this).addClass('is-selected'); loadSlots(); });
		$form.on('click', '.rr-time-slots button', function () { $slots.find('button').removeClass('is-selected'); $(this).addClass('is-selected'); $form.find('[name="time"]').val($(this).data('time')); $form.find('[data-step="1"] .rr-next').prop('disabled', false); message(rrFrontend.i18n.confirm, 'success'); });
		$form.on('click', '.rr-month-prev, .rr-month-next', function () { shown.setMonth(shown.getMonth() + ($(this).hasClass('rr-month-next') ? 1 : -1)); renderCalendar(); });
		$form.on('click', '.rr-next', function () { step(Number($(this).closest('.rr-form-step').data('step')) + 1); });
		$form.on('click', '.rr-back', function () { step(Number($(this).closest('.rr-form-step').data('step')) - 1); });
		$form.on('change', '[name="guests"]', loadSlots);
		$form.on('submit', function (event) {
			event.preventDefault(); loading(true); message('');
			// Explicitly validate required fields (checkValidity skips hidden inputs)
			var missing = [];
			if (!$form.find('[name="date"]').val()) { missing.push('date'); }
			if (!$form.find('[name="time"]').val()) { missing.push('time'); }
			if (!$form.find('[name="guests"]').val() || $form.find('[name="guests"]').val() < 1) { missing.push('guests'); }
			if (!$form.find('[name="name"]').val()) { missing.push('name'); }
			if (!$form.find('[name="phone"]').val()) { missing.push('phone'); }
			if (missing.length) { message(rrFrontend.i18n.error, 'error'); loading(false); return; }
			var data = $form.serializeArray(); data.push({name: 'action', value: 'rr_submit_reservation'}, {name: 'nonce', value: rrFrontend.nonce});
			$.post(rrFrontend.ajaxUrl, data).done(function (response) { console.log('RR submit done:', response); if (response && response.success) { message(response.data && response.data.message ? response.data.message : 'Reserva confirmada', 'success'); $form[0].reset(); shown = new Date(today.getFullYear(), today.getMonth(), 1); renderCalendar(); step(1); $form.find('.rr-datepicker, .rr-time-slots, .rr-progress').show(); } else { var errMsg = 'Error al procesar la reserva'; if (response && response.data && response.data.message) { errMsg = response.data.message; } message(errMsg, 'error'); } }).fail(function (xhr) { console.log('RR submit fail:', xhr.status, xhr.responseText); try { var msg = JSON.parse(xhr.responseText); message(msg.data && msg.data.message ? msg.data.message : rrFrontend.i18n.error, 'error'); } catch(e) { message(rrFrontend.i18n.error, 'error'); } }).always(function () { loading(false); });
		});
		renderCalendar();
	});
}(jQuery));