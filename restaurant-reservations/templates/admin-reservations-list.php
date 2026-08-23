<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap rr-admin-wrap">
	<h1><?php esc_html_e( 'Reservations', 'restaurant-reservations' ); ?></h1>
	<form method="get" class="rr-filters">
		<input type="hidden" name="page" value="rr-reservations">
		<label><?php esc_html_e( 'From', 'restaurant-reservations' ); ?> <input type="date" name="start_date" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['start_date'] ?? '' ) ) ); ?>"></label>
		<label><?php esc_html_e( 'To', 'restaurant-reservations' ); ?> <input type="date" name="end_date" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['end_date'] ?? '' ) ) ); ?>"></label>
		<label><span class="screen-reader-text"><?php esc_html_e( 'Status', 'restaurant-reservations' ); ?></span><select name="rr_status"><option value=""><?php esc_html_e( 'All statuses', 'restaurant-reservations' ); ?></option><?php foreach ( array( 'pending', 'confirmed', 'cancelled', 'completed' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( sanitize_key( $_GET['rr_status'] ?? '' ), $status ); ?>><?php echo esc_html( RRPostTypes::status_label( $status ) ); ?></option><?php endforeach; ?></select></label>
		<?php submit_button( __( 'Filter', 'restaurant-reservations' ), 'secondary', 'filter_action', false ); ?>
	</form>
	<form method="post"><?php wp_nonce_field( 'bulk-reservations' ); $table->search_box( __( 'Search reservations', 'restaurant-reservations' ), 'rr-search' ); $table->display(); ?></form>
</div>
