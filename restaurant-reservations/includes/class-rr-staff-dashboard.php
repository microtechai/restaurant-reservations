<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Staff dashboard accessible at /mesas/
 * Provides a front-end panel for restaurant staff to view
 * and close reservations without accessing wp-admin.
 */
class RRStaffDashboard {

	public function __construct() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'document_title_parts', array( $this, 'page_title' ) );
	}

	public function rewrite() {
		add_rewrite_rule( '^mesas/?$', 'index.php?rr_dashboard=1', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'rr_dashboard';
		return $vars;
	}

	public function page_title( $title ) {
		if ( get_query_var( 'rr_dashboard' ) ) {
			$title['title'] = __( 'Gestión de reservas', 'restaurant-reservations' );
		}
		return $title;
	}

	public function template( $template ) {
		if ( ! get_query_var( 'rr_dashboard' ) ) {
			return $template;
		}

		if ( ! is_user_logged_in() ) {
			return RR_PLUGIN_PATH . 'templates/staff-login.php';
		}

		// Staff dashboard — prepare data for template
		set_query_var( 'rr_today_reservations', $this->get_today_reservations() );
		set_query_var( 'rr_today_date', current_time( 'Y-m-d' ) );
		set_query_var( 'rr_calendar_year', (int) current_time( 'Y' ) );
		set_query_var( 'rr_calendar_month', (int) current_time( 'n' ) );
		set_query_var( 'rr_calendar_counts', $this->get_month_counts( (int) current_time( 'Y' ), (int) current_time( 'n' ) ) );

		return RR_PLUGIN_PATH . 'templates/staff-dashboard.php';
	}

	private function get_today_reservations() {
		$date  = current_time( 'Y-m-d' );
		$query = new WP_Query( array(
			'post_type'      => 'rr_reservation',
			'post_status'    => array( 'pending', 'confirmed', 'cancelled', 'completed' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_key'       => '_rr_time',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'   => '_rr_date',
				'value' => $date,
			) ),
		) );
		return $query->posts;
	}

	private function get_month_counts( $year, $month ) {
		$start = sprintf( '%04d-%02d-01', $year, $month );
		$end   = gmdate( 'Y-m-t', strtotime( $start ) );
		$query = new WP_Query( array(
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
		foreach ( $query->posts as $post ) {
			$d = get_post_meta( $post->ID, '_rr_date', true );
			$counts[ $d ] = ( $counts[ $d ] ?? 0 ) + 1;
		}
		return $counts;
	}

	public function assets() {
		if ( ! get_query_var( 'rr_dashboard' ) ) {
			return;
		}
		wp_enqueue_style( 'rr-staff', RR_PLUGIN_URL . 'assets/css/staff.css', array(), RR_VERSION );
		wp_enqueue_script( 'rr-staff', RR_PLUGIN_URL . 'assets/js/staff.js', array( 'jquery' ), RR_VERSION, true );
		wp_localize_script( 'rr-staff', 'rrStaff', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rr_staff_nonce' ),
			'today'   => current_time( 'Y-m-d' ),
			'currentYear'  => (int) current_time( 'Y' ),
			'currentMonth' => (int) current_time( 'n' ),
			'siteUrl' => site_url(),
			'i18n'    => array(
				'confirmComplete' => __( '¿Marcar esta reserva como completada?', 'restaurant-reservations' ),
				'confirmCancel'   => __( '¿Cancelar esta reserva?', 'restaurant-reservations' ),
				'error'           => __( 'Error al procesar la solicitud.', 'restaurant-reservations' ),
				'success'         => __( 'Reserva actualizada.', 'restaurant-reservations' ),
				'months'          => array_map( function( $n ) { return wp_date( 'F', mktime( 0, 0, 0, $n, 1 ) ); }, range( 1, 12 ) ),
				'weekdaysShort'   => array( 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb' ),
				'reservations'    => __( 'reservas', 'restaurant-reservations' ),
				'noReservations'  => __( 'No hay reservas para este día.', 'restaurant-reservations' ),
				'loading'         => __( 'Cargando...', 'restaurant-reservations' ),
				'logout'          => __( 'Cerrar sesión', 'restaurant-reservations' ),
			),
		) );
	}
}