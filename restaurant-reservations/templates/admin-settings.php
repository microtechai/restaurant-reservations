<?php if ( ! defined( 'ABSPATH' ) ) { exit; } $tab = sanitize_key( $_GET['tab'] ?? 'general' ); if ( ! in_array( $tab, array( 'general', 'hours', 'email' ), true ) ) { $tab = 'general'; } ?>
<div class="wrap rr-admin-wrap">
	<h1><?php esc_html_e( 'Reservation Settings', 'restaurant-reservations' ); ?></h1>
	<nav class="nav-tab-wrapper">
		<?php foreach ( array( 'general' => __( 'General', 'restaurant-reservations' ), 'hours' => __( 'Hours', 'restaurant-reservations' ), 'email' => __( 'Email', 'restaurant-reservations' ) ) as $key => $label ) : ?>
			<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rr-settings', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<form method="post" action="options.php">
		<?php settings_fields( 'rr_settings' ); do_settings_sections( 'rr-settings-' . $tab ); submit_button(); ?>
	</form>
	<?php if ( 'email' === $tab ) : ?><section class="rr-template-preview"><h2><?php esc_html_e( 'Template placeholders', 'restaurant-reservations' ); ?></h2><p><?php echo esc_html( '{customer_name}, {date}, {time}, {guests}, {restaurant_name}' ); ?></p></section><?php endif; ?>
</div>

