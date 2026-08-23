<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRFrontend {
	public function __construct() { add_shortcode( 'rr_reservation_form', array( $this, 'shortcode' ) ); }

	public static function create_timed_nonce() {
		$timestamp = time();
		return $timestamp . '.' . hash_hmac( 'sha256', 'rr_reservation|' . $timestamp, wp_salt( 'nonce' ) );
	}

	public static function verify_timed_nonce( $nonce ) {
		$parts = explode( '.', (string) $nonce, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || abs( time() - (int) $parts[0] ) > HOUR_IN_SECONDS ) { return false; }
		return hash_equals( hash_hmac( 'sha256', 'rr_reservation|' . $parts[0], wp_salt( 'nonce' ) ), $parts[1] );
	}

	public function shortcode() {
		wp_enqueue_style( 'rr-frontend', RR_PLUGIN_URL . 'assets/css/frontend.css', array(), RR_VERSION );
		wp_enqueue_script( 'rr-frontend', RR_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), RR_VERSION, true );
		wp_localize_script( 'rr-frontend', 'rrFrontend', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => self::create_timed_nonce(), 'today' => current_time( 'Y-m-d' ), 'maxDate' => gmdate( 'Y-m-d', current_time( 'timestamp' ) + 90 * DAY_IN_SECONDS ),
			'i18n' => array( 'months' => array_map( function( $number ) { return wp_date( 'F', mktime( 0, 0, 0, $number, 1 ) ); }, range( 1, 12 ) ), 'weekdays' => array( __( 'Sun', 'restaurant-reservations' ), __( 'Mon', 'restaurant-reservations' ), __( 'Tue', 'restaurant-reservations' ), __( 'Wed', 'restaurant-reservations' ), __( 'Thu', 'restaurant-reservations' ), __( 'Fri', 'restaurant-reservations' ), __( 'Sat', 'restaurant-reservations' ) ), 'selectTime' => __( 'Select a time', 'restaurant-reservations' ), 'noSlots' => __( 'No times are available for this date.', 'restaurant-reservations' ), 'checking' => __( 'Checking availability…', 'restaurant-reservations' ), 'unavailable' => __( 'This time is unavailable.', 'restaurant-reservations' ), 'error' => __( 'Something went wrong. Please try again.', 'restaurant-reservations' ), 'confirm' => __( 'Reservation available.', 'restaurant-reservations' ) )
		) );
		ob_start(); include RR_PLUGIN_PATH . 'templates/reservation-form.php'; return ob_get_clean();
	}
}

