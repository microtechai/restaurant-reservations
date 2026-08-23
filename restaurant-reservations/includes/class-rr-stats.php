<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRStats {
	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'admin_post_rr_export_stats', array( $this, 'export_csv' ) );
	}

	public static function calculate_daily( $date ) {
		$query = new WP_Query( array( 'post_type' => 'rr_reservation', 'post_status' => array( 'pending', 'confirmed', 'cancelled', 'completed' ), 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_rr_date', 'value' => sanitize_text_field( $date ) ) ) ) );
		$total = count( $query->posts ); $confirmed = 0; $cancelled = 0; $guests = 0;
		foreach ( $query->posts as $post ) { if ( 'confirmed' === $post->post_status ) { ++$confirmed; } if ( 'cancelled' === $post->post_status ) { ++$cancelled; } if ( 'cancelled' !== $post->post_status ) { $guests += absint( get_post_meta( $post->ID, '_rr_guests', true ) ); } }
		RRDatabase::record_stats( $date, $total, $confirmed, $cancelled, $guests );
		return RRDatabase::get_daily_stats( $date );
	}

	public static function calculate_weekly( $year, $week ) { $date = new DateTime(); $date->setISODate( (int) $year, (int) $week ); for ( $i = 0; $i < 7; $i++ ) { self::calculate_daily( $date->format( 'Y-m-d' ) ); $date->modify( '+1 day' ); } return RRDatabase::get_weekly_stats( $year, $week ); }
	public static function calculate_monthly( $year, $month ) { $date = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) ); $days = (int) $date->format( 't' ); for ( $i = 0; $i < $days; $i++ ) { self::calculate_daily( $date->format( 'Y-m-d' ) ); $date->modify( '+1 day' ); } return RRDatabase::get_monthly_stats( $year, $month ); }

	public function status_changed( $new_status, $old_status, $post ) {
		if ( 'rr_reservation' !== $post->post_type || $new_status === $old_status ) { return; }
		$date = get_post_meta( $post->ID, '_rr_date', true ); if ( $date ) { self::calculate_daily( $date ); }
	}

	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to export statistics.', 'restaurant-reservations' ) ); }
		check_admin_referer( 'rr_export_stats' );
		$start = sanitize_text_field( wp_unslash( $_GET['start'] ?? current_time( 'Y-m-01' ) ) ); $end = sanitize_text_field( wp_unslash( $_GET['end'] ?? current_time( 'Y-m-t' ) ) );
		header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename=reservation-stats-' . $start . '-' . $end . '.csv' );
		$output = fopen( 'php://output', 'w' ); fputcsv( $output, array( __( 'Date', 'restaurant-reservations' ), __( 'Reservations', 'restaurant-reservations' ), __( 'Confirmed', 'restaurant-reservations' ), __( 'Cancelled', 'restaurant-reservations' ), __( 'Guests', 'restaurant-reservations' ) ) );
		$date = new DateTime( $start ); $last = new DateTime( $end ); while ( $date <= $last ) { $day = $date->format( 'Y-m-d' ); $stats = self::calculate_daily( $day ); fputcsv( $output, array( $day, $stats['total_reservations'], $stats['confirmed'], $stats['cancelled'], $stats['total_guests'] ) ); $date->modify( '+1 day' ); } fclose( $output ); exit;
	}
}
