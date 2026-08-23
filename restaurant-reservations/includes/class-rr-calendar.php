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
		if ( ! $this->valid_date( $date ) || $date < $today || $date > $last_date || ! preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time ) || $guests < 1 || in_array( $date, $this->get_blocked_dates(), true ) ) { return false; }
		$hours = $this->get_business_hours( strtolower( gmdate( 'l', strtotime( $date ) ) ) );
		$interval = max( 1, absint( get_option( 'rr_time_slot_interval', 30 ) ) );
		if ( empty( $hours['open'] ) || empty( $hours['close'] ) || $time < $hours['open'] || strtotime( $date . ' ' . $time ) + $interval * MINUTE_IN_SECONDS > strtotime( $date . ' ' . $hours['close'] ) ) { return false; }
		// Check global capacity
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

	/**
	 * Get available (unbooked) tables for a given date, time, and guest count.
	 *
	 * @param string $date  Y-m-d
	 * @param string $time  H:i
	 * @param int    $guests
	 * @return array List of table objects with id, title, capacity, location
	 */
	public function get_available_tables( $date, $time, $guests ) {
		$date = sanitize_text_field( $date );
		$time = sanitize_text_field( $time );
		$guests = absint( $guests );

		// Get all active tables with capacity >= guests and min_guests <= guests
		$tables = get_posts( array(
			'post_type' => 'rr_table',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'orderby' => 'title',
			'order' => 'ASC',
		) );

		$available = array();

		// Get reservations for this date+time (excluding cancelled)
		$booked_table_names = array();
		$reservations = $this->get_reservations_for_date( $date );
		foreach ( $reservations as $reservation ) {
			$res_time = get_post_meta( $reservation->ID, '_rr_time', true );
			$res_table = get_post_meta( $reservation->ID, '_rr_table', true );
			if ( $res_time === $time && $res_table && 'cancelled' !== $reservation->post_status ) {
				$booked_table_names[] = $res_table;
			}
		}

		foreach ( $tables as $table ) {
			$active = get_post_meta( $table->ID, '_rr_active', true );
			$capacity = (int) get_post_meta( $table->ID, '_rr_capacity', true );
			$min_guests = (int) get_post_meta( $table->ID, '_rr_min_guests', true ) ?: 1;
			$title = get_the_title( $table->ID );

			if ( '1' !== $active ) { continue; }
			if ( $capacity < $guests ) { continue; }
			if ( $guests < $min_guests ) { continue; }
			if ( in_array( $title, $booked_table_names, true ) ) { continue; }

			$available[] = array(
				'id' => $table->ID,
				'title' => $title,
				'capacity' => $capacity,
				'location' => get_post_meta( $table->ID, '_rr_location', true ) ?: 'indoor',
			);
		}

		return $available;
	}

	private function valid_date( $date ) {
		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}