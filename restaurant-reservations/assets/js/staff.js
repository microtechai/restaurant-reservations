(function ($) {
	'use strict';

	var state = {
		year: parseInt(rrStaff.currentYear, 10),
		month: parseInt(rrStaff.currentMonth, 10),
		details: {}
	};

	function pad(number) {
		return String(number).padStart(2, '0');
	}

	function statusLabel(status, suppliedLabel) {
		var labels = { pending: 'Pendiente', confirmed: 'Confirmada', completed: 'Completada', cancelled: 'Cancelada' };
		return suppliedLabel || labels[status] || status;
	}

	function formatDate(dateString) {
		var parts = dateString.split('-');
		var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		return date.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
	}

	function normalizeMonth(year, month) {
		var date = new Date(year, month - 1, 1);
		return { year: date.getFullYear(), month: date.getMonth() + 1 };
	}

	function renderCalendar(counts) {
		var $grid = $('.rr-calendar-grid').empty();
		var firstDow = new Date(state.year, state.month - 1, 1).getDay();
		var days = new Date(state.year, state.month, 0).getDate();
		var weekdays = rrStaff.i18n.weekdaysShort || ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
		var i;

		$.each(weekdays, function (_, weekday) {
			$('<div>', { 'class': 'rr-calendar-weekday', text: weekday }).appendTo($grid);
		});
		for (i = 0; i < firstDow; i += 1) {
			$('<span>', { 'class': 'rr-calendar-blank', 'aria-hidden': 'true' }).appendTo($grid);
		}
		for (i = 1; i <= days; i += 1) {
			var date = state.year + '-' + pad(state.month) + '-' + pad(i);
			var count = parseInt(counts[date] || 0, 10);
			var $day = $('<a>', { href: '#rr-day-detail', 'class': 'rr-calendar-day', 'data-date': date });
			$('<span>', { 'class': 'rr-calendar-number', text: i }).appendTo($day);
			if (count) {
				$('<span>', { 'class': 'rr-calendar-count', text: count }).appendTo($day);
			}
			if (date === rrStaff.today) {
				$day.addClass('is-today');
			}
			$day.attr('aria-label', formatDate(date) + ', ' + count + ' ' + (rrStaff.i18n.reservations || 'reservas'));
			$day.appendTo($grid);
		}
		$grid.attr({ 'data-year': state.year, 'data-month': state.month });
		$('.rr-calendar-month').text((rrStaff.i18n.months[state.month - 1] || '') + ' ' + state.year);
	}

	function loadCalendar(year, month) {
		var normalized = normalizeMonth(year, month);
		state.year = normalized.year;
		state.month = normalized.month;
		$('.rr-calendar-nav button').prop('disabled', true);
		return $.post(rrStaff.ajaxUrl, {
			action: 'rr_staff_calendar_data',
			nonce: rrStaff.nonce,
			year: state.year,
			month: state.month
		}).done(function (response) {
			if (!response.success) {
				window.alert((response.data && response.data.message) || rrStaff.i18n.error);
				return;
			}
			state.details = $.extend({}, state.details, response.data.details || {});
			renderCalendar(response.data.counts || {});
			closeDetail();
		}).fail(function (xhr) {
			var response = xhr.responseJSON;
			window.alert((response && response.data && response.data.message) || rrStaff.i18n.error);
		}).always(function () {
			$('.rr-calendar-nav button').prop('disabled', false);
		});
	}

	function reservationActions(reservation) {
		var $actions = $('<div>', { 'class': 'rr-actions' });
		if (reservation.status === 'pending' || reservation.status === 'confirmed') {
			$('<button>', { type: 'button', 'class': 'rr-btn rr-btn--complete', 'data-id': reservation.id, 'data-status': 'completed', text: 'Completar' }).appendTo($actions);
		}
		$('<button>', { type: 'button', 'class': 'rr-btn rr-btn--cancel', 'data-id': reservation.id, 'data-status': 'cancelled', text: 'Cancelar' }).appendTo($actions);
		return $actions;
	}

	function showDay(date) {
		var reservations = state.details[date] || [];
		var $panel = $('.rr-staff-day-detail');
		var $content = $panel.find('.rr-day-detail-content').empty();
		$panel.find('.rr-day-detail-title').text(formatDate(date));
		if (!reservations.length) {
			$('<p>', { 'class': 'rr-empty-state', text: rrStaff.i18n.noReservations }).appendTo($content);
		} else {
			var $list = $('<div>', { 'class': 'rr-day-reservations' }).appendTo($content);
			$.each(reservations, function (_, reservation) {
				var $item = $('<article>', { 'class': 'rr-day-reservation', 'data-reservation-id': reservation.id });
				var $identity = $('<div>').appendTo($item);
				$('<div>', { 'class': 'rr-day-reservation-name', text: reservation.name }).appendTo($identity);
				$('<div>', { 'class': 'rr-day-reservation-meta', text: reservation.time + ' · ' + reservation.guests + ' comensales' }).appendTo($identity);
				$('<span>', { 'class': 'rr-status rr-status--' + reservation.status, text: statusLabel(reservation.status, reservation.label) }).appendTo($item);
				reservationActions(reservation).appendTo($item);
				$item.appendTo($list);
			});
		}
		$panel.prop('hidden', false);
	}

	function closeDetail() {
		$('.rr-calendar-day').removeClass('is-active');
		$('.rr-staff-day-detail').prop('hidden', true);
	}

	function showFlash(message) {
		var $flash = $('.rr-staff-flash').stop(true, true).text(message).prop('hidden', false).hide().fadeIn(150);
		window.setTimeout(function () { $flash.fadeOut(250, function () { $flash.prop('hidden', true); }); }, 2200);
	}

	function updateCachedReservation(id, status, label) {
		$.each(state.details, function (_, reservations) {
			$.each(reservations, function (__, reservation) {
				if (parseInt(reservation.id, 10) === parseInt(id, 10)) {
					reservation.status = status;
					reservation.label = label;
				}
			});
		});
	}

	$(document).on('click', '.rr-calendar-prev, .rr-calendar-next', function () {
		var direction = $(this).hasClass('rr-calendar-prev') ? -1 : 1;
		loadCalendar(state.year, state.month + direction);
	});

	$(document).on('click', '.rr-calendar-day', function (event) {
		event.preventDefault();
		$('.rr-calendar-day').removeClass('is-active');
		$(this).addClass('is-active');
		showDay($(this).data('date'));
	});

	$(document).on('click', '.rr-day-detail-close', closeDetail);

	$(document).on('click', '.rr-btn--complete, .rr-btn--cancel', function () {
		var $button = $(this);
		var id = $button.data('id');
		var status = $button.data('status');
		var confirmation = status === 'completed' ? rrStaff.i18n.confirmComplete : rrStaff.i18n.confirmCancel;
		if (!window.confirm(confirmation)) {
			return;
		}
		$button.closest('.rr-actions').find('.rr-btn').prop('disabled', true);
		$.post(rrStaff.ajaxUrl, {
			action: 'rr_staff_update_status',
			nonce: rrStaff.nonce,
			post_id: id,
			status: status
		}).done(function (response) {
			if (!response.success) {
				window.alert((response.data && response.data.message) || rrStaff.i18n.error);
				return;
			}
			var label = response.data.label || statusLabel(status);
			var $rows = $('[data-reservation-id="' + id + '"]');
			$rows.find('.rr-status').attr('class', 'rr-status rr-status--' + status).text(label);
			$rows.find('.rr-actions').each(function () {
				$(this).empty().append(reservationActions({ id: id, status: status }));
			});
			$rows.addClass('is-updated');
			window.setTimeout(function () { $rows.removeClass('is-updated'); }, 1300);
			updateCachedReservation(id, status, label);
			showFlash(response.data.message || rrStaff.i18n.success);
		}).fail(function (xhr) {
			var response = xhr.responseJSON;
			window.alert((response && response.data && response.data.message) || rrStaff.i18n.error);
		}).always(function () {
			$button.closest('.rr-actions').find('.rr-btn').prop('disabled', false);
		});
	});

	$(function () {
		if ($('.rr-calendar-grid').length) {
			loadCalendar(state.year, state.month);
		}
	});
}(jQuery));

/* ===== Tables Management (Gestión de Mesas) ===== */
(function ($) {
	'use strict';

	var $modal = $('#rr-table-modal');
	var $form = $('#rr-table-form');
	var $list = $('#rr-tables-list');
	var $title = $('#rr-table-modal-title');
	var $flash = $('.rr-staff-flash');

	/**
	 * Show a flash message.
	 */
	function showFlash(message, type) {
		type = type || '';
		$flash.stop(true, true)
			.removeClass('rr-flash--error rr-flash--success')
			.addClass(type ? 'rr-flash--' + type : '')
			.text(message)
			.prop('hidden', false)
			.hide()
			.fadeIn(150);
		window.setTimeout(function () {
			$flash.fadeOut(250, function () { $flash.prop('hidden', true); });
		}, 2500);
	}

	/**
	 * Open modal for creating a new table.
	 */
	function openCreateModal() {
		$form[0].reset();
		$form.find('[name="table_id"]').val('');
		$form.find('[name="title"]').prop('readonly', false);
		$form.find('[name="capacity"]').val('4');
		$form.find('[name="min_guests"]').val('1');
		$form.find('[name="location"]').val('indoor');
		$form.find('[name="active"]').prop('checked', true);
		$title.text(rrStaff.i18n.addTable || 'Añadir mesa');
		$modal.addClass('is-visible');
	}

	/**
	 * Open modal for editing an existing table.
	 */
	function openEditModal(tableId) {
		$.get(rrStaff.ajaxUrl, {
			action: 'rr_get_tables',
			nonce: rrStaff.nonce
		}).done(function (response) {
			if (!response.success) {
				window.alert((response.data && response.data.message) || rrStaff.i18n.error);
				return;
			}
			var table = null;
			$.each(response.data, function (_, t) {
				if (parseInt(t.id, 10) === parseInt(tableId, 10)) {
					table = t;
					return false;
				}
			});
			if (!table) {
				window.alert(rrStaff.i18n.error);
				return;
			}
			$form.find('[name="table_id"]').val(table.id);
			$form.find('[name="title"]').val(table.title);
			$form.find('[name="capacity"]').val(table.capacity);
			$form.find('[name="min_guests"]').val(table.min_guests);
			$form.find('[name="location"]').val(table.location);
			$form.find('[name="active"]').prop('checked', table.active);
			$title.text(rrStaff.i18n.editTable || 'Editar mesa');
			$modal.addClass('is-visible');
		}).fail(function () {
			window.alert(rrStaff.i18n.error);
		});
	}

	/**
	 * Close the modal.
	 */
	function closeModal() {
		$modal.removeClass('is-visible');
	}

	/**
	 * Render a single table card from data object.
	 */
	function renderTableCard(table) {
		var locIcon = '🏠';
		var locLabel = 'Interior';
		if (table.location === 'outdoor') { locIcon = '🌿'; locLabel = 'Terraza'; }
		if (table.location === 'bar') { locIcon = '🍸'; locLabel = 'Barra'; }
		var activeClass = table.active ? '' : ' is-inactive';
		var statusClass = table.active ? 'active' : 'inactive';
		var statusLabel = table.active ? 'Activa' : 'Inactiva';
		return '<article class="rr-table-card' + activeClass + '" data-table-id="' + table.id + '">' +
			'<h4>' + $('<span>').text(table.title).html() + '</h4>' +
			'<div class="rr-table-details">' +
				'<span class="rr-table-capacity">👤 ' + table.capacity + ' pers (mín. ' + table.min_guests + ')</span>' +
				'<span class="rr-table-location">' + locIcon + ' ' + locLabel + '</span>' +
				'<span class="rr-table-status rr-table-status--' + statusClass + '">' + statusLabel + '</span>' +
			'</div>' +
			'<div class="rr-table-actions">' +
				'<button type="button" class="rr-table-edit" data-id="' + table.id + '">Editar</button>' +
				'<button type="button" class="rr-table-delete" data-id="' + table.id + '">Eliminar</button>' +
			'</div>' +
		'</article>';
	}

	/**
	 * Load and render all tables via AJAX.
	 */
	function loadTables() {
		$.get(rrStaff.ajaxUrl, {
			action: 'rr_get_tables',
			nonce: rrStaff.nonce
		}).done(function (response) {
			if (!response.success) {
				showFlash((response.data && response.data.message) || rrStaff.i18n.error, 'error');
				return;
			}
			if (!response.data || !response.data.length) {
				$list.html('<p class="rr-empty-state">No hay mesas configuradas.</p>');
				return;
			}
			var html = '';
			$.each(response.data, function (_, table) {
				html += renderTableCard(table);
			});
			$list.html(html);
		}).fail(function () {
			showFlash(rrStaff.i18n.error, 'error');
		});
	}

	// === Event handlers ===

	// Open modal for new table
	$(document).on('click', '#rr-add-table-btn', openCreateModal);

	// Open modal for edit
	$(document).on('click', '.rr-table-edit', function () {
		var id = $(this).data('id');
		openEditModal(id);
	});

	// Delete table
	$(document).on('click', '.rr-table-delete', function () {
		var $button = $(this);
		var id = $button.data('id');
		if (!window.confirm('¿Eliminar esta mesa?')) {
			return;
		}
		$button.prop('disabled', true);
		$.post(rrStaff.ajaxUrl, {
			action: 'rr_delete_table',
			nonce: rrStaff.nonce,
			table_id: id
		}).done(function (response) {
			if (!response.success) {
				window.alert((response.data && response.data.message) || rrStaff.i18n.error);
				return;
			}
			showFlash(response.data.message || 'Mesa eliminada.', 'success');
			loadTables();
		}).fail(function () {
			window.alert(rrStaff.i18n.error);
		}).always(function () {
			$button.prop('disabled', false);
		});
	});

	// Submit form (create / update)
	$form.on('submit', function (event) {
		event.preventDefault();
		var $submit = $form.find('[type="submit"]').prop('disabled', true);
		var data = {
			action: 'rr_save_table',
			nonce: rrStaff.nonce,
			table_id: $form.find('[name="table_id"]').val(),
			title: $form.find('[name="title"]').val(),
			capacity: $form.find('[name="capacity"]').val(),
			min_guests: $form.find('[name="min_guests"]').val(),
			location: $form.find('[name="location"]').val(),
			active: $form.find('[name="active"]').is(':checked') ? '1' : '0'
		};
		$.post(rrStaff.ajaxUrl, data).done(function (response) {
			if (!response.success) {
				window.alert((response.data && response.data.message) || rrStaff.i18n.error);
				return;
			}
			showFlash(response.data.title + ' guardada.', 'success');
			closeModal();
			loadTables();
		}).fail(function () {
			window.alert(rrStaff.i18n.error);
		}).always(function () {
			$submit.prop('disabled', false);
		});
	});

	// Close modal on backdrop click or close button
	$(document).on('click', '.rr-modal-backdrop, .rr-modal-close, .rr-modal-close-trigger', closeModal);

	// Close modal on Escape key
	$(document).on('keydown', function (event) {
		if (event.key === 'Escape' && !$modal.prop('hidden')) {
			closeModal();
		}
	});

	// Initial load of tables if the grid exists
	$(function () {
		if ($('#rr-tables-list').length) {
			loadTables();
		}
	});

})(jQuery);