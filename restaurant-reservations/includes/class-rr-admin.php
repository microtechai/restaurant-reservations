<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( is_admin() && ! class_exists( 'WP_List_Table' ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; }

class RRReservationsListTable extends WP_List_Table {
	public function get_columns() { return array( 'cb' => '<input type="checkbox">', 'title' => __( 'Customer', 'restaurant-reservations' ), 'date' => __( 'Date', 'restaurant-reservations' ), 'time' => __( 'Time', 'restaurant-reservations' ), 'guests' => __( 'Guests', 'restaurant-reservations' ), 'status' => __( 'Status', 'restaurant-reservations' ), 'email' => __( 'Email', 'restaurant-reservations' ), 'phone' => __( 'Phone', 'restaurant-reservations' ) ); }
	protected function get_sortable_columns() { return array( 'date' => array( 'date', false ), 'time' => array( 'time', false ), 'title' => array( 'title', false ) ); }
	protected function get_bulk_actions() { return array( 'confirmed' => __( 'Confirm', 'restaurant-reservations' ), 'cancelled' => __( 'Cancel', 'restaurant-reservations' ), 'completed' => __( 'Complete', 'restaurant-reservations' ) ); }
	public function column_cb( $item ) { return '<input type="checkbox" name="reservation_ids[]" value="' . absint( $item->ID ) . '">'; }
	public function column_default( $item, $column ) { if ( 'title' === $column ) { return '<strong><a href="' . esc_url( get_edit_post_link( $item->ID ) ) . '">' . esc_html( get_the_title( $item->ID ) ) . '</a></strong>'; } if ( 'status' === $column ) { return '<span class="rr-status rr-status-' . esc_attr( $item->post_status ) . '">' . esc_html( RRPostTypes::status_label( $item->post_status ) ) . '</span>'; } return esc_html( get_post_meta( $item->ID, '_rr_' . $column, true ) ); }
	public function column_title( $item ) { $actions = array(); foreach ( array( 'confirmed' => __( 'Confirm', 'restaurant-reservations' ), 'cancelled' => __( 'Cancel', 'restaurant-reservations' ), 'completed' => __( 'Complete', 'restaurant-reservations' ) ) as $status => $label ) { $actions[ $status ] = '<a class="rr-status-action" href="#" data-id="' . absint( $item->ID ) . '" data-status="' . esc_attr( $status ) . '">' . esc_html( $label ) . '</a>'; } return $this->column_default( $item, 'title' ) . $this->row_actions( $actions ); }

	public function prepare_items() {
		$this->process_bulk_action(); $per_page = 20; $page = $this->get_pagenum();
		$args = array( 'post_type' => 'rr_reservation', 'post_status' => array( 'pending', 'confirmed', 'cancelled', 'completed' ), 'posts_per_page' => $per_page, 'paged' => $page, 's' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) );
		$status = sanitize_key( $_GET['rr_status'] ?? '' ); if ( in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'completed' ), true ) ) { $args['post_status'] = $status; }
		$meta_query = array(); $start = sanitize_text_field( wp_unslash( $_GET['start_date'] ?? '' ) ); $end = sanitize_text_field( wp_unslash( $_GET['end_date'] ?? '' ) );
		if ( $start ) { $meta_query[] = array( 'key' => '_rr_date', 'value' => $start, 'compare' => '>=', 'type' => 'DATE' ); } if ( $end ) { $meta_query[] = array( 'key' => '_rr_date', 'value' => $end, 'compare' => '<=', 'type' => 'DATE' ); }
		if ( $meta_query ) { $args['meta_query'] = $meta_query; }
		$orderby = sanitize_key( $_GET['orderby'] ?? 'date' ); $order = 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ?? 'desc' ) ) ) ? 'ASC' : 'DESC';
		if ( in_array( $orderby, array( 'date', 'time' ), true ) ) { $args['meta_key'] = '_rr_' . $orderby; $args['orderby'] = 'meta_value'; } else { $args['orderby'] = 'title'; } $args['order'] = $order;
		$query = new WP_Query( $args ); $this->items = $query->posts; $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() ); $this->set_pagination_args( array( 'total_items' => $query->found_posts, 'per_page' => $per_page, 'total_pages' => $query->max_num_pages ) );
	}

	private function process_bulk_action() {
		$action = $this->current_action(); if ( ! in_array( $action, array( 'confirmed', 'cancelled', 'completed' ), true ) || empty( $_POST['reservation_ids'] ) ) { return; }
		check_admin_referer( 'bulk-' . $this->_args['plural'] ); foreach ( array_map( 'absint', (array) $_POST['reservation_ids'] ) as $post_id ) { if ( current_user_can( 'edit_post', $post_id ) ) { wp_update_post( array( 'ID' => $post_id, 'post_status' => $action ) ); } }
	}
}

class RRAdmin {
	public function __construct() { add_action( 'admin_menu', array( $this, 'menus' ) ); add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) ); }
	public function menus() {
		add_menu_page( __( 'Reservations', 'restaurant-reservations' ), __( 'Reservations', 'restaurant-reservations' ), 'edit_posts', 'rr-reservations', array( $this, 'reservations_page' ), 'dashicons-calendar-alt', 26 );
		add_submenu_page( 'rr-reservations', __( 'All Reservations', 'restaurant-reservations' ), __( 'All Reservations', 'restaurant-reservations' ), 'edit_posts', 'rr-reservations', array( $this, 'reservations_page' ) );
		add_submenu_page( 'rr-reservations', __( 'Reservation Calendar', 'restaurant-reservations' ), __( 'Calendar', 'restaurant-reservations' ), 'edit_posts', 'rr-calendar', array( $this, 'calendar_page' ) );
		add_submenu_page( 'rr-reservations', __( 'Reservation Statistics', 'restaurant-reservations' ), __( 'Statistics', 'restaurant-reservations' ), 'manage_options', 'rr-statistics', array( $this, 'stats_page' ) );
		add_submenu_page( 'rr-reservations', __( 'Reservation Settings', 'restaurant-reservations' ), __( 'Settings', 'restaurant-reservations' ), 'manage_options', 'rr-settings', array( $this, 'settings_page' ) );
	}
	public function assets( $hook ) { if ( false === strpos( $hook, 'rr-' ) && 'toplevel_page_rr-reservations' !== $hook ) { return; } wp_enqueue_style( 'rr-admin', RR_PLUGIN_URL . 'assets/css/admin.css', array(), RR_VERSION ); wp_enqueue_script( 'rr-admin', RR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), RR_VERSION, true ); wp_localize_script( 'rr-admin', 'rrAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'rr_admin_nonce' ), 'i18n' => array( 'confirmChange' => __( 'Change this reservation status?', 'restaurant-reservations' ), 'confirmCancel' => __( 'Cancel this reservation?', 'restaurant-reservations' ), 'error' => __( 'The request could not be completed.', 'restaurant-reservations' ), 'reservations' => __( 'reservations', 'restaurant-reservations' ), 'months' => array_map( function( $n ) { return wp_date( 'F', mktime( 0, 0, 0, $n, 1 ) ); }, range( 1, 12 ) ), 'weekdays' => array( __( 'Sun', 'restaurant-reservations' ), __( 'Mon', 'restaurant-reservations' ), __( 'Tue', 'restaurant-reservations' ), __( 'Wed', 'restaurant-reservations' ), __( 'Thu', 'restaurant-reservations' ), __( 'Fri', 'restaurant-reservations' ), __( 'Sat', 'restaurant-reservations' ) ) ) ) ); }
	public function reservations_page() { $table = new RRReservationsListTable( array( 'singular' => 'reservation', 'plural' => 'reservations' ) ); $table->prepare_items(); include RR_PLUGIN_PATH . 'templates/admin-reservations-list.php'; }
	public function settings_page() { include RR_PLUGIN_PATH . 'templates/admin-settings.php'; }
	public function stats_page() { $date = sanitize_text_field( wp_unslash( $_GET['date'] ?? current_time( 'Y-m-d' ) ) ); $timestamp = strtotime( $date ); $daily = RRStats::calculate_daily( $date ); $weekly = RRStats::calculate_weekly( (int) gmdate( 'o', $timestamp ), (int) gmdate( 'W', $timestamp ) ); $monthly = RRStats::calculate_monthly( (int) gmdate( 'Y', $timestamp ), (int) gmdate( 'n', $timestamp ) ); include RR_PLUGIN_PATH . 'templates/admin-stats.php'; }
	public function calendar_page() { $year = absint( $_GET['rr_year'] ?? current_time( 'Y' ) ); $month = absint( $_GET['rr_month'] ?? current_time( 'n' ) ); if ( $month < 1 || $month > 12 ) { $month = (int) current_time( 'n' ); } $first = sprintf( '%04d-%02d-01', $year, $month ); $calendar = new RRCalendar(); $counts = array(); for ( $day = 1; $day <= (int) gmdate( 't', strtotime( $first ) ); $day++ ) { $date = sprintf( '%04d-%02d-%02d', $year, $month, $day ); $counts[ $date ] = count( $calendar->get_reservations_for_date( $date ) ); } include RR_PLUGIN_PATH . 'templates/admin-calendar.php'; }
}
