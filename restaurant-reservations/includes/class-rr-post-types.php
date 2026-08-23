<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRPostTypes {
	public function __construct() {
		if ( did_action( 'init' ) ) {
			self::register_post_type();
		} else {
			add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		}
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_rr_reservation', array( $this, 'save_meta' ) );
		add_filter( 'manage_rr_reservation_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_rr_reservation_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type( 'rr_reservation', array(
			'labels' => array( 'name' => __( 'Reservations', 'restaurant-reservations' ), 'singular_name' => __( 'Reservation', 'restaurant-reservations' ), 'add_new_item' => __( 'Add Reservation', 'restaurant-reservations' ), 'edit_item' => __( 'Edit Reservation', 'restaurant-reservations' ) ),
			'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'menu_icon' => 'dashicons-calendar-alt',
			'supports' => array( 'title', 'editor', 'custom-fields' ), 'map_meta_cap' => true,
			'capability_type' => array( 'rr_reservation', 'rr_reservations' ),
			'capabilities' => array( 'edit_post' => 'edit_post', 'read_post' => 'read_post', 'delete_post' => 'delete_post', 'edit_posts' => 'edit_posts', 'edit_others_posts' => 'edit_others_posts', 'publish_posts' => 'publish_posts', 'read_private_posts' => 'read_private_posts', 'delete_posts' => 'delete_posts', 'delete_private_posts' => 'delete_private_posts', 'delete_published_posts' => 'delete_published_posts', 'delete_others_posts' => 'delete_others_posts', 'edit_private_posts' => 'edit_private_posts', 'edit_published_posts' => 'edit_published_posts', 'create_posts' => 'edit_posts' ),
		) );
		$statuses = array( 'confirmed' => __( 'Confirmed', 'restaurant-reservations' ), 'cancelled' => __( 'Cancelled', 'restaurant-reservations' ), 'completed' => __( 'Completed', 'restaurant-reservations' ) );
		foreach ( $statuses as $status => $label ) {
			register_post_status( $status, array( 'label' => $label, 'public' => false, 'internal' => false, 'protected' => true, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'restaurant-reservations' ) ) );
		}
	}

	public function add_meta_boxes() {
		add_meta_box( 'rr_details', __( 'Reservation Details', 'restaurant-reservations' ), array( $this, 'render_meta_box' ), 'rr_reservation', 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'rr_save_meta', 'rr_meta_nonce' );
		$fields = array( 'date' => __( 'Date', 'restaurant-reservations' ), 'time' => __( 'Time', 'restaurant-reservations' ), 'guests' => __( 'Guests', 'restaurant-reservations' ), 'email' => __( 'Email', 'restaurant-reservations' ), 'phone' => __( 'Phone', 'restaurant-reservations' ), 'notes' => __( 'Notes', 'restaurant-reservations' ), 'table' => __( 'Table', 'restaurant-reservations' ) );
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, '_rr_' . $key, true );
			$type  = in_array( $key, array( 'date', 'time', 'email', 'number' ), true ) ? $key : ( 'guests' === $key ? 'number' : 'text' );
			echo '<p><label for="rr_' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
			if ( 'notes' === $key ) { echo '<textarea class="widefat" id="rr_notes" name="rr_notes">' . esc_textarea( $value ) . '</textarea>'; }
			else { echo '<input class="widefat" type="' . esc_attr( $type ) . '" id="rr_' . esc_attr( $key ) . '" name="rr_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
			echo '</p>';
		}
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['rr_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rr_meta_nonce'] ) ), 'rr_save_meta' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$sanitizers = array( 'date' => 'sanitize_text_field', 'time' => 'sanitize_text_field', 'guests' => 'absint', 'email' => 'sanitize_email', 'phone' => 'sanitize_text_field', 'notes' => 'sanitize_textarea_field', 'table' => 'sanitize_text_field' );
		foreach ( $sanitizers as $key => $callback ) {
			if ( isset( $_POST[ 'rr_' . $key ] ) ) { update_post_meta( $post_id, '_rr_' . $key, call_user_func( $callback, wp_unslash( $_POST[ 'rr_' . $key ] ) ) ); }
		}
	}

	public function columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Customer', 'restaurant-reservations' ), 'rr_date' => __( 'Date', 'restaurant-reservations' ), 'rr_time' => __( 'Time', 'restaurant-reservations' ), 'rr_guests' => __( 'Guests', 'restaurant-reservations' ), 'rr_status' => __( 'Status', 'restaurant-reservations' ), 'rr_email' => __( 'Email', 'restaurant-reservations' ), 'rr_phone' => __( 'Phone', 'restaurant-reservations' ) );
	}

	public function column_content( $column, $post_id ) {
		if ( 'rr_status' === $column ) { echo esc_html( self::status_label( get_post_status( $post_id ) ) ); return; }
		if ( 0 === strpos( $column, 'rr_' ) ) { echo esc_html( get_post_meta( $post_id, '_' . $column, true ) ); }
	}

	public static function status_label( $status ) {
		$labels = array( 'pending' => __( 'Pending', 'restaurant-reservations' ), 'confirmed' => __( 'Confirmed', 'restaurant-reservations' ), 'cancelled' => __( 'Cancelled', 'restaurant-reservations' ), 'completed' => __( 'Completed', 'restaurant-reservations' ) );
		return $labels[ $status ] ?? sanitize_text_field( $status );
	}
}
