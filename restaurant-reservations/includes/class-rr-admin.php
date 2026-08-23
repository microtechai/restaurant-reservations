<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRAdmin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menus() {
		add_menu_page(
			__( 'Reservations', 'restaurant-reservations' ),
			__( 'Reservations', 'restaurant-reservations' ),
			'edit_posts',
			'rr-reservations',
			array( $this, 'reservations_page' ),
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page(
			'rr-reservations',
			__( 'All Reservations', 'restaurant-reservations' ),
			__( 'All Reservations', 'restaurant-reservations' ),
			'edit_posts',
			'rr-reservations',
			array( $this, 'reservations_page' )
		);
		add_submenu_page(
			'rr-reservations',
			__( 'Reservation Calendar', 'restaurant-reservations' ),
			__( 'Calendar', 'restaurant-reservations' ),
			'edit_posts',
			'rr-calendar',
			array( $this, 'calendar_page' )
		);
		add_submenu_page(
			'rr-reservations',
			__( 'Reservation Statistics', 'restaurant-reservations' ),
			__( 'Statistics', 'restaurant-reservations' ),
			'manage_options',
			'rr-statistics',
			array( $this, 'stats_page' )
		);
		add_submenu_page(
			'rr-reservations',
			__( 'Reservation Settings', 'restaurant-reservations' ),
			__( 'Settings', 'restaurant-reservations' ),
			'manage_options',
			'rr-settings',
			array( $this, 'settings_page' )
		);
		add_submenu_page(
			'rr-reservations',
			__( 'Tables', 'restaurant-reservations' ),
			__( 'Tables', 'restaurant-reservations' ),
			'manage_options',
			'edit.php?post_type=rr_table',
			''
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, 'rr-' ) && 'toplevel_page_rr-reservations' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rr-admin', RR_PLUGIN_URL . 'assets/css/admin.css', array(), RR_VERSION );
		wp_enqueue_script( 'rr-admin', RR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), RR_VERSION, true );
		wp_localize_script( 'rr-admin', 'rrAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rr_admin_nonce' ),
			'i18n'    => array(
				'confirmChange' => __( 'Change this reservation status?', 'restaurant-reservations' ),
				'confirmCancel' => __( 'Cancel this reservation?', 'restaurant-reservations' ),
				'error'         => __( 'The request could not be completed.', 'restaurant-reservations' ),
				'reservations'  => __( 'reservations', 'restaurant-reservations' ),
				'months'        => array_map( function( $n ) { return wp_date( 'F', mktime( 0, 0, 0, $n, 1 ) ); }, range( 1, 12 ) ),
				'weekdays'      => array(
					__( 'Sun', 'restaurant-reservations' ),
					__( 'Mon', 'restaurant-reservations' ),
					__( 'Tue', 'restaurant-reservations' ),
					__( 'Wed', 'restaurant-reservations' ),
					__( 'Thu', 'restaurant-reservations' ),
					__( 'Fri', 'restaurant-reservations' ),
					__( 'Sat', 'restaurant-reservations' ),
				),
			),
		) );
	}

	public function reservations_page() {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		if ( ! class_exists( 'RRReservationsListTable' ) ) {
			require_once RR_PLUGIN_PATH . 'includes/rr-list-table.php';
		}
		$table = new RRReservationsListTable( array( 'singular' => 'reservation', 'plural' => 'reservations' ) );
		$table->prepare_items();
		include RR_PLUGIN_PATH . 'templates/admin-reservations-list.php';
	}

	public function settings_page() {
		include RR_PLUGIN_PATH . 'templates/admin-settings.php';
	}

	public function stats_page() {
		$date      = sanitize_text_field( wp_unslash( $_GET['date'] ?? current_time( 'Y-m-d' ) ) );
		$timestamp = strtotime( $date );
		$daily     = RRStats::calculate_daily( $date );
		$weekly    = RRStats::calculate_weekly( (int) gmdate( 'o', $timestamp ), (int) gmdate( 'W', $timestamp ) );
		$monthly   = RRStats::calculate_monthly( (int) gmdate( 'Y', $timestamp ), (int) gmdate( 'n', $timestamp ) );
		include RR_PLUGIN_PATH . 'templates/admin-stats.php';
	}

	public function calendar_page() {
		$year  = absint( $_GET['rr_year'] ?? current_time( 'Y' ) );
		$month = absint( $_GET['rr_month'] ?? current_time( 'n' ) );
		if ( $month < 1 || $month > 12 ) {
			$month = (int) current_time( 'n' );
		}
		$first    = sprintf( '%04d-%02d-01', $year, $month );
		$calendar = new RRCalendar();
		$counts   = array();
		for ( $day = 1; $day <= (int) gmdate( 't', strtotime( $first ) ); $day++ ) {
			$date               = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			$counts[ $date ]    = count( $calendar->get_reservations_for_date( $date ) );
		}
		include RR_PLUGIN_PATH . 'templates/admin-calendar.php';
	}
}