<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WP_List_Table for managing reservations in admin.
 * Loaded on demand from RRAdmin::reservations_page()
 * to avoid fatal errors when WP_List_Table is not available (CLI, etc).
 */
class RRReservationsListTable extends WP_List_Table {

	public function get_columns() {
		return array(
			'cb'     => '<input type="checkbox">',
			'title'  => __( 'Customer', 'restaurant-reservations' ),
			'date'   => __( 'Date', 'restaurant-reservations' ),
			'time'   => __( 'Time', 'restaurant-reservations' ),
			'guests' => __( 'Guests', 'restaurant-reservations' ),
			'status' => __( 'Status', 'restaurant-reservations' ),
			'email'  => __( 'Email', 'restaurant-reservations' ),
			'phone'  => __( 'Phone', 'restaurant-reservations' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'date'  => array( 'date', false ),
			'time'  => array( 'time', false ),
			'title' => array( 'title', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'confirmed' => __( 'Confirm', 'restaurant-reservations' ),
			'cancelled' => __( 'Cancel', 'restaurant-reservations' ),
			'completed' => __( 'Complete', 'restaurant-reservations' ),
		);
	}

	public function column_cb( $item ) {
		return '<input type="checkbox" name="reservation_ids[]" value="' . absint( $item->ID ) . '">';
	}

	public function column_default( $item, $column ) {
		if ( 'title' === $column ) {
			return '<strong><a href="' . esc_url( get_edit_post_link( $item->ID ) ) . '">' . esc_html( get_the_title( $item->ID ) ) . '</a></strong>';
		}
		if ( 'status' === $column ) {
			return '<span class="rr-status rr-status-' . esc_attr( $item->post_status ) . '">' . esc_html( RRPostTypes::status_label( $item->post_status ) ) . '</span>';
		}
		return esc_html( get_post_meta( $item->ID, '_rr_' . $column, true ) );
	}

	public function column_title( $item ) {
		$actions = array();
		foreach ( array(
			'confirmed' => __( 'Confirm', 'restaurant-reservations' ),
			'cancelled' => __( 'Cancel', 'restaurant-reservations' ),
			'completed' => __( 'Complete', 'restaurant-reservations' ),
		) as $status => $label ) {
			$actions[ $status ] = '<a class="rr-status-action" href="#" data-id="' . absint( $item->ID ) . '" data-status="' . esc_attr( $status ) . '">' . esc_html( $label ) . '</a>';
		}
		return $this->column_default( $item, 'title' ) . $this->row_actions( $actions );
	}

	public function prepare_items() {
		$this->process_bulk_action();
		$per_page = 20;
		$page     = $this->get_pagenum();

		$args = array(
			'post_type'      => 'rr_reservation',
			'post_status'    => array( 'pending', 'confirmed', 'cancelled', 'completed' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			's'              => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
		);

		$status = sanitize_key( $_GET['rr_status'] ?? '' );
		if ( in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'completed' ), true ) ) {
			$args['post_status'] = $status;
		}

		$meta_query = array();
		$start      = sanitize_text_field( wp_unslash( $_GET['start_date'] ?? '' ) );
		$end        = sanitize_text_field( wp_unslash( $_GET['end_date'] ?? '' ) );

		if ( $start ) {
			$meta_query[] = array( 'key' => '_rr_date', 'value' => $start, 'compare' => '>=', 'type' => 'DATE' );
		}
		if ( $end ) {
			$meta_query[] = array( 'key' => '_rr_date', 'value' => $end, 'compare' => '<=', 'type' => 'DATE' );
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}

		$orderby = sanitize_key( $_GET['orderby'] ?? 'date' );
		$order   = 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ?? 'desc' ) ) ) ? 'ASC' : 'DESC';

		if ( in_array( $orderby, array( 'date', 'time' ), true ) ) {
			$args['meta_key'] = '_rr_' . $orderby;
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = 'title';
		}
		$args['order'] = $order;

		$query                          = new WP_Query( $args );
		$this->items                    = $query->posts;
		$this->_column_headers          = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args( array(
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		) );
	}

	private function process_bulk_action() {
		$action = $this->current_action();
		if ( ! in_array( $action, array( 'confirmed', 'cancelled', 'completed' ), true ) || empty( $_POST['reservation_ids'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		foreach ( array_map( 'absint', (array) $_POST['reservation_ids'] ) as $post_id ) {
			if ( current_user_can( 'edit_post', $post_id ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => $action ) );
			}
		}
	}
}