<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRDatabase {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rr_stats_daily';
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			total_reservations int(11) NOT NULL DEFAULT 0,
			confirmed_reservations int(11) NOT NULL DEFAULT 0,
			cancelled_reservations int(11) NOT NULL DEFAULT 0,
			total_guests int(11) NOT NULL DEFAULT 0,
			max_guests int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY date (date)
		) {$charset};";
		dbDelta( $sql );
	}

	public static function drop_table() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	public static function record_stats( $date, $total, $confirmed, $cancelled, $guests ) {
		global $wpdb;
		$table = self::table_name();
		$sql = "INSERT INTO {$table} (date,total_reservations,confirmed_reservations,cancelled_reservations,total_guests,max_guests,created_at)
			VALUES (%s,%d,%d,%d,%d,%d,%s) ON DUPLICATE KEY UPDATE total_reservations=VALUES(total_reservations), confirmed_reservations=VALUES(confirmed_reservations), cancelled_reservations=VALUES(cancelled_reservations), total_guests=VALUES(total_guests), max_guests=VALUES(max_guests), created_at=VALUES(created_at)";
		return $wpdb->query( $wpdb->prepare( $sql, $date, $total, $confirmed, $cancelled, $guests, $guests, current_time( 'mysql' ) ) );
	}

	private static function normalize( $row ) {
		return array(
			'total_reservations' => (int) ( $row['total_reservations'] ?? 0 ),
			'confirmed'          => (int) ( $row['confirmed'] ?? $row['confirmed_reservations'] ?? 0 ),
			'cancelled'          => (int) ( $row['cancelled'] ?? $row['cancelled_reservations'] ?? 0 ),
			'total_guests'       => (int) ( $row['total_guests'] ?? 0 ),
		);
	}

	public static function get_daily_stats( $date ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT total_reservations, confirmed_reservations, cancelled_reservations, total_guests FROM %i WHERE date = %s', self::table_name(), $date ), ARRAY_A );
		return self::normalize( $row ?: array() );
	}

	public static function get_weekly_stats( $year, $week ) {
		$date = new DateTime();
		$date->setISODate( (int) $year, (int) $week );
		$start = $date->format( 'Y-m-d' );
		$date->modify( '+6 days' );
		return self::get_stats_range( $start, $date->format( 'Y-m-d' ) );
	}

	public static function get_monthly_stats( $year, $month ) {
		$start = sprintf( '%04d-%02d-01', (int) $year, (int) $month );
		$end   = gmdate( 'Y-m-t', strtotime( $start ) );
		return self::get_stats_range( $start, $end );
	}

	public static function get_stats_range( $start_date, $end_date ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(total_reservations),0) total_reservations, COALESCE(SUM(confirmed_reservations),0) confirmed, COALESCE(SUM(cancelled_reservations),0) cancelled, COALESCE(SUM(total_guests),0) total_guests FROM %i WHERE date BETWEEN %s AND %s', self::table_name(), $start_date, $end_date ), ARRAY_A );
		return self::normalize( $row ?: array() );
	}
}

