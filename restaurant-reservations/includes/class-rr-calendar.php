<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRCalendar {
	public function get_available_slots( $date, $guests ) {
		$date = sanitize_text_field( $date );
		if ( ! $this->valid_date( $date ) || in_array( $date, $this->get_blocked_dates(), true ) ) { return array(); }
		$hours = $this->get_business_hours( strtolower( gmdate( 'l', strtotime( $date ) ) ) );
		if ( empty( $hours['open'] ) || empty( $hours['close'] ) ) { return array(); }
		$interval = max( 1, absint( get_option( 'rr_time_slot_interval', 30 ) ) );
		$start = strtotime( $date . ' ' . $hours['open'] );
		$end   = strtotime( $date . ' ' . $hours['close'] );
		$slots = array();
		for ( $time = $start; $time < $end; $time += $interval * MINUTE_IN_SECONDS ) {
			$slot = gmdate( 'H:i', $time );
			if ( $this->is_slot_available( $date, $slot, $guests ) ) { $slots[] = $slot; }
		}
		return $slots;
	}

	public function is_slot_available( $date, $time, $guests ) {
		$date   = sanitize_text_field( $date );
		$time   = sanitize_text_field( $time );
		$guests = absint( $guests );
		$today = current_time( 'Y-m-d' );
		$last_date = gmdate( 'Y-m-d', current_time( 'timestamp' ) + 90 * DAY_IN_SECONDS );
		if ( ! $this->valid_date( $date ) || $date < $today || $date > $last_date || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) || $guests < 1 || in_array( $date, $this->get_blocked_dates(), true ) ) { return false; }
		$hours = $this->get_business_hours( strtolower( gmdate( 'l', strtotime( $date ) ) ) );
		$interval = max( 1, absint( get_option( 'rr_time_slot_interval', 30 ) ) );
		if ( empty( $hours['open'] ) || empty( $hours['close'] ) || $time < $hours['open'] || strtotime( $date . ' ' . $time ) + $interval * MINUTE_IN_SECONDS > strtotime( $date . ' ' . $hours['close'] ) ) { return false; }
		$total = 0;
		foreach ( $this->get_reservations_for_date( $date ) as $reservation ) {
			if ( $time === get_post_meta( $reservation->ID, '_rr_time', true ) && 'cancelled' !== $reservation->post_status ) { $total += absint( get_post_meta( $reservation->ID, '_rr_guests', true ) ); }
		}
		return $total + $guests <= absint( get_option( 'rr_max_guests_per_slot', 20 ) );
	}

	public function get_blocked_dates() {
		$dates = get_option( 'rr_blocked_dates', array() );
		return is_array( $dates ) ? array_values( array_filter( array_map( 'sanitize_text_field', $dates ) ) ) : array();
	}

	public function get_business_hours( $day_of_week ) {
		$hours = get_option( 'rr_business_hours', array() );
		$key   = strtolower( sanitize_key( $day_of_week ) );
		return isset( $hours[ $key ] ) ? $hours[ $key ] : array( 'open' => '', 'close' => '' );
	}

	public function get_reservations_for_date( $date ) {
		$query = new WP_Query( array( 'post_type' => 'rr_reservation', 'post_status' => array( 'pending', 'confirmed', 'cancelled', 'completed' ), 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_rr_date', 'value' => sanitize_text_field( $date ), 'compare' => '=' ) ) ) );
		return $query->posts;
	}

	private function valid_date( $date ) {
		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
