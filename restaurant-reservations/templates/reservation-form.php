<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="rr-reservation-widget">
	<form class="rr-reservation-form" novalidate>
		<div class="rr-progress" aria-label="<?php esc_attr_e( 'Reservation progress', 'restaurant-reservations' ); ?>"><span class="is-active">1</span><span>2</span><span>3</span></div>
		<section class="rr-form-step is-active" data-step="1">
			<h2><?php esc_html_e( 'Choose a date and time', 'restaurant-reservations' ); ?></h2>
			<input type="hidden" name="date" required><input type="hidden" name="time" required>
			<div class="rr-datepicker"><div class="rr-datepicker-header"><button type="button" class="rr-month-prev" aria-label="<?php esc_attr_e( 'Previous month', 'restaurant-reservations' ); ?>">&lsaquo;</button><strong class="rr-month-title"></strong><button type="button" class="rr-month-next" aria-label="<?php esc_attr_e( 'Next month', 'restaurant-reservations' ); ?>">&rsaquo;</button></div><div class="rr-weekdays"></div><div class="rr-days"></div></div>
			<div class="rr-time-slots" aria-live="polite"></div>
			<button type="button" class="rr-next button" disabled><?php esc_html_e( 'Continue', 'restaurant-reservations' ); ?></button>
		</section>
		<section class="rr-form-step" data-step="2">
			<h2><?php esc_html_e( 'How many guests?', 'restaurant-reservations' ); ?></h2>
			<label for="rr-guests"><?php esc_html_e( 'Guests', 'restaurant-reservations' ); ?></label><select id="rr-guests" name="guests" required><?php for ( $guest = 1; $guest <= 20; $guest++ ) : ?><option value="<?php echo absint( $guest ); ?>"><?php echo absint( $guest ); ?></option><?php endfor; ?></select>
			<div class="rr-step-buttons"><button type="button" class="rr-back button"><?php esc_html_e( 'Back', 'restaurant-reservations' ); ?></button><button type="button" class="rr-next button"><?php esc_html_e( 'Continue', 'restaurant-reservations' ); ?></button></div>
		</section>
		<section class="rr-form-step" data-step="3">
			<h2><?php esc_html_e( 'Your details', 'restaurant-reservations' ); ?></h2>
			<label><?php esc_html_e( 'Name', 'restaurant-reservations' ); ?> <span aria-hidden="true">*</span><input type="text" name="name" required autocomplete="name"></label>
			<label><?php esc_html_e( 'Email', 'restaurant-reservations' ); ?><input type="email" name="email" autocomplete="email"></label>
			<label><?php esc_html_e( 'Phone', 'restaurant-reservations' ); ?> <span aria-hidden="true">*</span><input type="tel" name="phone" required autocomplete="tel"></label>
			<label><?php esc_html_e( 'Special requests', 'restaurant-reservations' ); ?><textarea name="notes" rows="4"></textarea></label>
			<div class="rr-honeypot" aria-hidden="true"><label><?php esc_html_e( 'Website', 'restaurant-reservations' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
			<div class="rr-step-buttons"><button type="button" class="rr-back button"><?php esc_html_e( 'Back', 'restaurant-reservations' ); ?></button><button type="submit" class="rr-submit button"><span><?php esc_html_e( 'Request reservation', 'restaurant-reservations' ); ?></span><i class="rr-spinner" aria-hidden="true"></i></button></div>
		</section>
		<div class="rr-message" role="status" aria-live="polite"></div>
	</form>
</div>
