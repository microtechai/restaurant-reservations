<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRAjax {
	public function __construct() {
		foreach ( array( 'check_availability', 'submit_reservation' ) as $action ) {
			add_action( 'wp_ajax_rr_' . $action, array( $this, $action ) );
			add_action( 'wp_ajax_nopriv_rr_' . $action, array( $this, $action ) );
		}
		add_action( 'wp_ajax_rr_calendar_data', array( $this, 'calendar_data' ) );
		add_action( 'wp_ajax_rr_update_status', array( $this, 'update_status' ) );
		add_action( 'wp_ajax_rr_staff_update_status', array( $this, 'staff_update_status' ) );
		add_action( 'wp_ajax_rr_staff_calendar_data', array( $this, 'staff_calendar_data' ) );
		// Table AJAX handlers
		add_action( 'wp_ajax_rr_get_tables', array( $this, 'get_tables' ) );
		add_action( 'wp_ajax_rr_save_table', array( $this, 'save_table' ) );
		add_action( 'wp_ajax_rr_delete_table', array( $this, 'delete_table' ) );
		add_action( 'wp_ajax_rr_get_available_tables', array( $this, 'get_available_tables' ) );
	}

	private function verify_public_request() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! RRFrontend::verify_timed_nonce( $nonce ) ) { wp_send_json_error( array( 'message' => __( 'Your session expired. Please refresh the page.', 'restaurant-reservations' ) ), 403 ); }
	}

	public function check_availability() {
		$this->verify_public_request();
		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$time = isset( $_GET['time'] ) ? sanitize_text_field( wp_unslash( $_GET['time'] ) ) : '';
		$guests = isset( $_GET['guests'] ) ? absint( $_GET['guests'] ) : 1;
		$calendar = new RRCalendar();
		wp_send_json_success( array( 'available' => $time ? $calendar->is_slot_available( $date, $time, $guests ) : false, 'slots' => $calendar->get_available_slots( $date, $guests ) ) );
	}

	public function submit_reservation() {
		$this->verify_public_request();
		if ( ! empty( $_POST['website'] ) ) { wp_send_json_error( array( 'message' => __( 'Unable to process this reservation.', 'restaurant-reservations' ) ), 400 ); }
		$data = array(
			'date' => sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) ), 'time' => sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) ),
			'guests' => absint( $_POST['guests'] ?? 0 ), 'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), 'phone' => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);
		if ( ! $data['date'] || ! $data['time'] || ! $data['guests'] || ! $data['name'] || ! is_email( $data['email'] ) ) {
			$missing = array();
			if ( ! $data['date'] ) { $missing[] = 'date'; }
			if ( ! $data['time'] ) { $missing[] = 'time'; }
			if ( ! $data['guests'] ) { $missing[] = 'guests'; }
			if ( ! $data['name'] ) { $missing[] = 'name'; }
			if ( ! is_email( $data['email'] ) ) { $missing[] = 'email'; }
			wp_send_json_error( array( 'message' => __( 'Please complete all required fields.', 'restaurant-reservations' ), 'missing' => $missing ), 400 );
		}
		$calendar = new RRCalendar();
		if ( ! $calendar->is_slot_available( $data['date'], $data['time'], $data['guests'] ) ) { wp_send_json_error( array( 'message' => __( 'That time is no longer available.', 'restaurant-reservations' ) ), 409 ); }
		$post_id = wp_insert_post( array( 'post_type' => 'rr_reservation', 'post_status' => 'pending', 'post_title' => $data['name'], 'post_content' => $data['notes'] ), true );
		if ( is_wp_error( $post_id ) ) { wp_send_json_error( array( 'message' => __( 'The reservation could not be saved.', 'restaurant-reservations' ) ), 500 ); }
		foreach ( array( 'date', 'time', 'guests', 'email', 'phone', 'notes' ) as $key ) { update_post_meta( $post_id, '_rr_' . $key, $data[ $key ] ); }
		( new RREmail() )->send_notifications( $post_id );
		RRStats::calculate_daily( $data['date'] );
		wp_send_json_success( array( 'message' => __( 'Your reservation has been received.', 'restaurant-reservations' ), 'reservation_id' => $post_id ) );
	}

	public function calendar_data() {
		check_ajax_referer( 'rr_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => __( 'You are not allowed to view this data.', 'restaurant-reservations' ) ), 403 ); }
		$year = absint( $_POST['year'] ?? gmdate( 'Y' ) ); $month = absint( $_POST['month'] ?? gmdate( 'n' ) );
		$start = sprintf( '%04d-%02d-01', $year, $month ); $end = gmdate( 'Y-m-t', strtotime( $start ) );
		$query = new WP_Query( array( 'post_type' => 'rr_reservation', 'post_status' => array( 'pending', 'confirmed', 'cancelled', 'completed' ), 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_rr_date', 'value' => array( $start, $end ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) ) ) );
		$counts = array(); foreach ( $query->posts as $post ) { $date = get_post_meta( $post->ID, '_rr_date', true ); $counts[ $date ] = ( $counts[ $date ] ?? 0 ) + 1; }
		wp_send_json_success( array( 'counts' => $counts ) );
	}

	public function update_status() {
		check_ajax_referer( 'rr_admin_nonce', 'nonce' );
		$post_id = absint( $_POST['post_id'] ?? 0 ); $status = sanitize_key( $_POST['status'] ?? '' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'completed' ), true ) ) { wp_send_json_error( array( 'message' => __( 'Invalid status request.', 'restaurant-reservations' ) ), 403 ); }
		$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => __( 'The status could not be updated.', 'restaurant-reservations' ) ), 500 ); }
		wp_send_json_success( array( 'message' => __( 'Reservation status updated.', 'restaurant-reservations' ), 'status' => $status, 'label' => RRPostTypes::status_label( $status ) ) );
	}

	public function staff_update_status() {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'rr_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please refresh the page.', 'restaurant-reservations' ) ), 403 );
		}
		$post_id = absint( $_POST['post_id'] ?? 0 );
		$status  = sanitize_key( $_POST['status'] ?? '' );
		if ( ! $post_id || ! current_user_can( 'edit_rr_reservation', $post_id ) || ! in_array( $status, array( 'completed', 'cancelled', 'confirmed', 'pending' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'restaurant-reservations' ) ), 403 );
		}
		$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not update the reservation.', 'restaurant-reservations' ) ), 500 );
		}
		wp_send_json_success( array(
			'message' => __( 'Reservation updated.', 'restaurant-reservations' ),
			'status'  => $status,
			'label'   => RRPostTypes::status_label( $status ),
		) );
	}

	public function staff_calendar_data() {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'rr_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired.', 'restaurant-reservations' ) ), 403 );
		}
		$year  = absint( $_POST['year'] ?? current_time( 'Y' ) );
		$month = absint( $_POST['month'] ?? current_time( 'n' ) );
		if ( $month < 1 || $month > 12 ) { $month = 1; }
		$start   = sprintf( '%04d-%02d-01', $year, $month );
		$end     = gmdate( 'Y-m-t', strtotime( $start ) );
		$query   = new WP_Query( array(
			'post_type'      => 'rr_reservation',
			'post_status'    => array( 'pending', 'confirmed', 'cancelled', 'completed' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array( array(
				'key'     => '_rr_date',
				'value'   => array( $start, $end ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			) ),
		) );
		$counts = array();
		$details = array();
		foreach ( $query->posts as $post ) {
			$date = get_post_meta( $post->ID, '_rr_date', true );
			$counts[ $date ] = ( $counts[ $date ] ?? 0 ) + 1;
			if ( ! isset( $details[ $date ] ) ) { $details[ $date ] = array(); }
			$details[ $date ][] = array(
				'id'     => $post->ID,
				'name'   => get_the_title( $post->ID ),
				'time'   => get_post_meta( $post->ID, '_rr_time', true ),
				'guests' => get_post_meta( $post->ID, '_rr_guests', true ),
				'status' => $post->post_status,
				'label'  => RRPostTypes::status_label( $post->post_status ),
			);
		}
		wp_send_json_success( array( 'counts' => $counts, 'details' => $details ) );
	}

	// === TABLE AJAX HANDLERS ===

	/**
	 * AJAX: Get all tables with their meta.
	 */
	public function get_tables() {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'rr_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired.', 'restaurant-reservations' ) ), 403 );
		}
		$tables = get_posts( array(
			'post_type' => 'rr_table',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'orderby' => 'meta_value_num',
			'meta_key' => '_rr_capacity',
			'order' => 'ASC',
		) );
		$data = array();
		foreach ( $tables as $table ) {
			$data[] = array(
				'id' => $table->ID,
				'title' => get_the_title( $table->ID ),
				'capacity' => (int) get_post_meta( $table->ID, '_rr_capacity', true ),
				'min_guests' => (int) get_post_meta( $table->ID, '_rr_min_guests', true ),
				'location' => get_post_meta( $table->ID, '_rr_location', true ) ?: 'indoor',
				'active' => get_post_meta( $table->ID, '_rr_active', true ) === '1',
			);
		}
		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Create or update a table.
	 */
	public function save_table() {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'rr_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired.', 'restaurant-reservations' ) ), 403 );
		}
		$table_id = absint( $_POST['table_id'] ?? 0 );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$capacity = absint( $_POST['capacity'] ?? 4 );
		$min_guests = absint( $_POST['min_guests'] ?? 1 );
		$location = sanitize_text_field( wp_unslash( $_POST['location'] ?? 'indoor' ) );
		$active = isset( $_POST['active'] ) && '1' === $_POST['active'] ? '1' : '0';

		if ( ! $title || $capacity < 1 || $capacity > 20 || $min_guests < 1 || $min_guests > $capacity ) {
			wp_send_json_error( array( 'message' => __( 'Invalid table data.', 'restaurant-reservations' ) ), 400 );
		}
		if ( ! in_array( $location, array( 'indoor', 'outdoor', 'bar' ), true ) ) {
			$location = 'indoor';
		}

		if ( $table_id > 0 ) {
			$post_id = wp_update_post( array(
				'ID' => $table_id,
				'post_title' => $title,
			), true );
		} else {
			$post_id = wp_insert_post( array(
				'post_type' => 'rr_table',
				'post_status' => 'publish',
				'post_title' => $title,
			), true );
		}

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the table.', 'restaurant-reservations' ) ), 500 );
		}

		update_post_meta( $post_id, '_rr_capacity', $capacity );
		update_post_meta( $post_id, '_rr_min_guests', $min_guests );
		update_post_meta( $post_id, '_rr_location', $location );
		update_post_meta( $post_id, '_rr_active', $active );

		wp_send_json_success( array(
			'id' => $post_id,
			'title' => $title,
			'capacity' => $capacity,
			'min_guests' => $min_guests,
			'location' => $location,
			'active' => '1' === $active,
		) );
	}

	/**
	 * AJAX: Delete a table.
	 */
	public function delete_table() {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'rr_staff_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired.', 'restaurant-reservations' ) ), 403 );
		}
		$table_id = absint( $_POST['table_id'] ?? 0 );
		if ( ! $table_id || 'rr_table' !== get_post_type( $table_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid table.', 'restaurant-reservations' ) ), 400 );
		}
		$result = wp_delete_post( $table_id, true );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete the table.', 'restaurant-reservations' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'Table deleted.', 'restaurant-reservations' ) ) );
	}

	/**
	 * AJAX: Get available tables for a given date, time, and guest count.
	 */
	public function get_available_tables() {
		if ( ! is_user_logged_in() && ! isset( $_GET['nonce_public'] ) ) {
			// Allow public requests for frontend form
		}
		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$time = isset( $_GET['time'] ) ? sanitize_text_field( wp_unslash( $_GET['time'] ) ) : '';
		$guests = isset( $_GET['guests'] ) ? absint( $_GET['guests'] ) : 1;

		$calendar = new RRCalendar();
		$tables = $calendar->get_available_tables( $date, $time, $guests );
		wp_send_json_success( array( 'tables' => $tables ) );
	}
}