(function ($) {
	'use strict';
	$(document).on('click', '.rr-status-action', function (event) {
		event.preventDefault(); var $link = $(this), status = $link.data('status');
		if (!window.confirm(status === 'cancelled' ? rrAdmin.i18n.confirmCancel : rrAdmin.i18n.confirmChange)) { return; }
		$link.addClass('is-loading'); $.post(rrAdmin.ajaxUrl, {action: 'rr_update_status', nonce: rrAdmin.nonce, post_id: $link.data('id'), status: status}).done(function (response) {
			if (response.success) { var $badge = $link.closest('tr').find('.rr-status'); $badge.attr('class', 'rr-status rr-status-' + status).text(response.data.label); } else { window.alert(response.data.message); }
		}).fail(function (xhr) { window.alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : rrAdmin.i18n.error); }).always(function () { $link.removeClass('is-loading'); });
	});
	$('.rr-stats-filter input[type="date"]').on('change', function () { $(this).closest('form').trigger('submit'); });
	$('.rr-stats-tabs a').on('click', function () { $('.rr-stat-card').removeClass('is-highlighted'); $($(this).attr('href')).addClass('is-highlighted'); });
	$('#rr-admin-calendar').on('click', '.rr-calendar-previous, .rr-calendar-next', function (event) {
		event.preventDefault(); var $calendar = $('#rr-admin-calendar'), direction = $(this).hasClass('rr-calendar-next') ? 1 : -1;
		var year = Number($calendar.attr('data-year')), month = Number($calendar.attr('data-month')) + direction;
		if (month === 0) { month = 12; year -= 1; } if (month === 13) { month = 1; year += 1; }
		$calendar.addClass('is-loading'); $.post(rrAdmin.ajaxUrl, {action: 'rr_calendar_data', nonce: rrAdmin.nonce, year: year, month: month}).done(function (response) {
			if (!response.success) { window.alert(response.data.message); return; }
			var $grid = $calendar.find('.rr-admin-calendar-grid').empty(), first = new Date(year, month - 1, 1), days = new Date(year, month, 0).getDate();
			$.each(rrAdmin.i18n.weekdays, function (_, day) { $('<strong>').text(day).appendTo($grid); });
			for (var blank = 0; blank < first.getDay(); blank += 1) { $('<span>').addClass('rr-calendar-empty').appendTo($grid); }
			for (var day = 1; day <= days; day += 1) { var date = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0'), count = response.data.counts[date] || 0; $('<a>', {class: 'rr-calendar-day', href: 'admin.php?page=rr-reservations&start_date=' + date + '&end_date=' + date}).append($('<b>').text(day), $('<span>').text(count + ' ' + rrAdmin.i18n.reservations)).appendTo($grid); }
			$calendar.attr({'data-year': year, 'data-month': month}).find('.rr-calendar-nav h2').text(rrAdmin.i18n.months[month - 1] + ' ' + year);
		}).fail(function () { window.alert(rrAdmin.i18n.error); }).always(function () { $calendar.removeClass('is-loading'); });
	});
}(jQuery));
