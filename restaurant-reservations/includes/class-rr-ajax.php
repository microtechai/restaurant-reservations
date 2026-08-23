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
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! in_array( $status, array( 'completed', 'cancelled', 'confirmed', 'pending' ), true ) ) {
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
}
