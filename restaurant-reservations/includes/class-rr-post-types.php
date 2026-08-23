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
		add_action( 'save_post_rr_table', array( $this, 'save_table_meta' ) );
		add_filter( 'manage_rr_reservation_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_rr_reservation_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_rr_table_posts_columns', array( $this, 'table_columns' ) );
		add_action( 'manage_rr_table_posts_custom_column', array( $this, 'table_column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type( 'rr_reservation', array(
			'labels' => array( 'name' => __( 'Reservations', 'restaurant-reservations' ), 'singular_name' => __( 'Reservation', 'restaurant-reservations' ), 'add_new_item' => __( 'Add Reservation', 'restaurant-reservations' ), 'edit_item' => __( 'Edit Reservation', 'restaurant-reservations' ) ),
			'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'menu_icon' => 'dashicons-calendar-alt',
			'supports' => array( 'title', 'editor', 'custom-fields' ), 'map_meta_cap' => true,
			'capability_type' => array( 'rr_reservation', 'rr_reservations' ),
			'capabilities' => array(
							'edit_post'              => 'edit_rr_reservation',
							'read_post'              => 'read_rr_reservation',
							'delete_post'            => 'delete_rr_reservation',
							'edit_posts'             => 'edit_rr_reservations',
							'edit_others_posts'      => 'edit_others_rr_reservations',
							'publish_posts'          => 'publish_posts',
							'read_private_posts'     => 'read_private_posts',
							'delete_posts'           => 'delete_rr_reservations',
							'delete_private_posts'   => 'delete_private_posts',
							'delete_published_posts' => 'delete_published_posts',
							'delete_others_posts'    => 'delete_others_posts',
							'edit_private_posts'     => 'edit_private_posts',
							'edit_published_posts'   => 'edit_published_rr_reservations',
							'create_posts'           => 'edit_rr_reservations',
						),
		) );
		register_post_type( 'rr_table', array(
			'labels' => array(
				'name' => __( 'Tables', 'restaurant-reservations' ),
				'singular_name' => __( 'Table', 'restaurant-reservations' ),
				'add_new_item' => __( 'Add Table', 'restaurant-reservations' ),
				'edit_item' => __( 'Edit Table', 'restaurant-reservations' ),
			),
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'supports' => array( 'title', 'custom-fields' ),
			'map_meta_cap' => true,
			'capability_type' => 'post',
		) );
		$statuses = array( 'confirmed' => __( 'Confirmed', 'restaurant-reservations' ), 'cancelled' => __( 'Cancelled', 'restaurant-reservations' ), 'completed' => __( 'Completed', 'restaurant-reservations' ) );
		foreach ( $statuses as $status => $label ) {
			register_post_status( $status, array( 'label' => $label, 'public' => false, 'internal' => false, 'protected' => true, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'restaurant-reservations' ) ) );
		}
	}

	public function add_meta_boxes() {
		add_meta_box( 'rr_details', __( 'Reservation Details', 'restaurant-reservations' ), array( $this, 'render_meta_box' ), 'rr_reservation', 'normal', 'high' );
		add_meta_box( 'rr_table_details', __( 'Table Details', 'restaurant-reservations' ), array( $this, 'render_table_meta_box' ), 'rr_table', 'normal', 'high' );
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'rr_save_meta', 'rr_meta_nonce' );
		$fields = array( 'date' => __( 'Date', 'restaurant-reservations' ), 'time' => __( 'Time', 'restaurant-reservations' ), 'guests' => __( 'Guests', 'restaurant-reservations' ), 'email' => __( 'Email', 'restaurant-reservations' ), 'phone' => __( 'Phone', 'restaurant-reservations' ), 'notes' => __( 'Notes', 'restaurant-reservations' ) );
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, '_rr_' . $key, true );
			$type  = in_array( $key, array( 'date', 'time', 'email', 'number' ), true ) ? $key : ( 'guests' === $key ? 'number' : 'text' );
			echo '<p><label for="rr_' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
			if ( 'notes' === $key ) { echo '<textarea class="widefat" id="rr_notes" name="rr_notes">' . esc_textarea( $value ) . '</textarea>'; }
			else { echo '<input class="widefat" type="' . esc_attr( $type ) . '" id="rr_' . esc_attr( $key ) . '" name="rr_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
			echo '</p>';
		}
		// Table dropdown
		$current_table = get_post_meta( $post->ID, '_rr_table', true );
		$tables = get_posts( array(
			'post_type' => 'rr_table',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'orderby' => 'title',
			'order' => 'ASC',
			'meta_query' => array( array(
				'key' => '_rr_active',
				'value' => '1',
			) ),
		) );
		echo '<p><label for="rr_table"><strong>' . esc_html__( 'Table', 'restaurant-reservations' ) . '</strong></label><br>';
		echo '<select class="widefat" id="rr_table" name="rr_table">';
		echo '<option value="">' . esc_html__( 'Sin asignar', 'restaurant-reservations' ) . '</option>';
		foreach ( $tables as $table ) {
			$capacity = get_post_meta( $table->ID, '_rr_capacity', true );
			$selected = selected( $current_table, $table->post_title, false );
			echo '<option value="' . esc_attr( $table->post_title ) . '"' . $selected . '>' . esc_html( $table->post_title ) . ' (' . esc_html( $capacity ) . ' pers)</option>';
		}
		echo '</select></p>';
	}

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['rr_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rr_meta_nonce'] ) ), 'rr_save_meta' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$sanitizers = array( 'date' => 'sanitize_text_field', 'time' => 'sanitize_text_field', 'guests' => 'absint', 'email' => 'sanitize_email', 'phone' => 'sanitize_text_field', 'notes' => 'sanitize_textarea_field', 'table' => 'sanitize_text_field' );
		foreach ( $sanitizers as $key => $callback ) {
			if ( isset( $_POST[ 'rr_' . $key ] ) ) { update_post_meta( $post_id, '_rr_' . $key, call_user_func( $callback, wp_unslash( $_POST[ 'rr_' . $key ] ) ) ); }
		}
	}

	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'rr_guests' === $key ) {
				$new['rr_table'] = __( 'Table', 'restaurant-reservations' );
			}
		}
		return $new;
	}

	public function column_content( $column, $post_id ) {
		if ( 'rr_status' === $column ) { echo esc_html( self::status_label( get_post_status( $post_id ) ) ); return; }
		if ( 'rr_table' === $column ) {
			$table = get_post_meta( $post_id, '_rr_table', true );
			echo $table ? esc_html( $table ) : '<span style="color:#9CA3AF;">—</span>';
			return;
		}
		if ( 0 === strpos( $column, 'rr_' ) ) { echo esc_html( get_post_meta( $post_id, '_' . $column, true ) ); }
	}

	public static function status_label( $status ) {
		$labels = array( 'pending' => __( 'Pending', 'restaurant-reservations' ), 'confirmed' => __( 'Confirmed', 'restaurant-reservations' ), 'cancelled' => __( 'Cancelled', 'restaurant-reservations' ), 'completed' => __( 'Completed', 'restaurant-reservations' ) );
		return $labels[ $status ] ?? sanitize_text_field( $status );
	}

	// === TABLE META BOX ===
	public function render_table_meta_box( $post ) {
		wp_nonce_field( 'rr_save_table_meta', 'rr_table_meta_nonce' );
		$capacity = get_post_meta( $post->ID, '_rr_capacity', true ) ?: 4;
		$min_guests = get_post_meta( $post->ID, '_rr_min_guests', true ) ?: 1;
		$location = get_post_meta( $post->ID, '_rr_location', true ) ?: 'indoor';
		$active = get_post_meta( $post->ID, '_rr_active', true );
		?>
		<p><label for="rr_capacity"><strong><?php esc_html_e( 'Capacity', 'restaurant-reservations' ); ?></strong></label><br>
		<select class="widefat" id="rr_capacity" name="rr_capacity">
			<?php for ( $i = 1; $i <= 20; $i++ ) : ?>
				<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $capacity, $i ); ?>><?php echo esc_html( $i ); ?> <?php esc_html_e( 'personas', 'restaurant-reservations' ); ?></option>
			<?php endfor; ?>
		</select></p>
		<p><label for="rr_min_guests"><strong><?php esc_html_e( 'Minimum guests', 'restaurant-reservations' ); ?></strong></label><br>
		<select class="widefat" id="rr_min_guests" name="rr_min_guests">
			<?php for ( $i = 1; $i <= 20; $i++ ) : ?>
				<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $min_guests, $i ); ?>><?php echo esc_html( $i ); ?></option>
			<?php endfor; ?>
		</select></p>
		<p><label for="rr_location"><strong><?php esc_html_e( 'Location', 'restaurant-reservations' ); ?></strong></label><br>
		<select class="widefat" id="rr_location" name="rr_location">
			<option value="indoor" <?php selected( $location, 'indoor' ); ?>><?php esc_html_e( 'Interior', 'restaurant-reservations' ); ?></option>
			<option value="outdoor" <?php selected( $location, 'outdoor' ); ?>><?php esc_html_e( 'Terraza', 'restaurant-reservations' ); ?></option>
			<option value="bar" <?php selected( $location, 'bar' ); ?>><?php esc_html_e( 'Barra', 'restaurant-reservations' ); ?></option>
		</select></p>
		<p><label><input type="checkbox" name="rr_active" value="1" <?php checked( $active, '1' ); ?>> <?php esc_html_e( 'Active table', 'restaurant-reservations' ); ?></label></p>
		<?php
	}

	public function save_table_meta( $post_id ) {
		if ( ! isset( $_POST['rr_table_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rr_table_meta_nonce'] ) ), 'rr_save_table_meta' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( isset( $_POST['rr_capacity'] ) ) { update_post_meta( $post_id, '_rr_capacity', absint( $_POST['rr_capacity'] ) ); }
		if ( isset( $_POST['rr_min_guests'] ) ) { update_post_meta( $post_id, '_rr_min_guests', absint( $_POST['rr_min_guests'] ) ); }
		if ( isset( $_POST['rr_location'] ) ) { update_post_meta( $post_id, '_rr_location', sanitize_text_field( wp_unslash( $_POST['rr_location'] ) ) ); }
		update_post_meta( $post_id, '_rr_active', isset( $_POST['rr_active'] ) ? '1' : '0' );
	}

	public function table_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['title'] = __( 'Table', 'restaurant-reservations' );
			} elseif ( 'cb' === $key ) {
				$new['cb'] = $columns['cb'];
			} else {
				continue;
			}
		}
		$new['rr_capacity'] = __( 'Capacity', 'restaurant-reservations' );
		$new['rr_location'] = __( 'Location', 'restaurant-reservations' );
		$new['rr_active'] = __( 'Active', 'restaurant-reservations' );
		return $new;
	}

	public function table_column_content( $column, $post_id ) {
		if ( 'rr_capacity' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_rr_capacity', true ) ?: '—' );
		} elseif ( 'rr_location' === $column ) {
			$labels = array( 'indoor' => __( 'Interior', 'restaurant-reservations' ), 'outdoor' => __( 'Terraza', 'restaurant-reservations' ), 'bar' => __( 'Barra', 'restaurant-reservations' ) );
			$loc = get_post_meta( $post_id, '_rr_location', true );
			echo esc_html( $labels[ $loc ] ?? $loc );
		} elseif ( 'rr_active' === $column ) {
			$active = get_post_meta( $post_id, '_rr_active', true );
			echo $active ? '<span style="color:#10B981;">✓</span>' : '<span style="color:#9CA3AF;">✗</span>';
		}
	}
}