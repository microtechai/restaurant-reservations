<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRSettings {
	public function __construct() { add_action( 'admin_init', array( $this, 'register_settings' ) ); }

	public function register_settings() {
		register_setting( 'rr_settings', 'rr_max_guests_per_slot', array( 'sanitize_callback' => 'absint', 'default' => 20 ) );
		register_setting( 'rr_settings', 'rr_time_slot_interval', array( 'sanitize_callback' => array( $this, 'sanitize_interval' ), 'default' => 30 ) );
		register_setting( 'rr_settings', 'rr_business_hours', array( 'sanitize_callback' => array( $this, 'sanitize_hours' ) ) );
		register_setting( 'rr_settings', 'rr_blocked_dates', array( 'sanitize_callback' => array( $this, 'sanitize_dates' ) ) );
		register_setting( 'rr_settings', 'rr_email_admin', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'rr_settings', 'rr_email_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_toggle' ) ) );
		register_setting( 'rr_settings', 'rr_email_templates', array( 'sanitize_callback' => array( $this, 'sanitize_templates' ) ) );
		add_settings_section( 'rr_general', __( 'General', 'restaurant-reservations' ), '__return_false', 'rr-settings-general' );
		add_settings_field( 'rr_max_guests', __( 'Maximum guests per slot', 'restaurant-reservations' ), array( $this, 'number_field' ), 'rr-settings-general', 'rr_general', array( 'option' => 'rr_max_guests_per_slot', 'min' => 1 ) );
		add_settings_field( 'rr_interval', __( 'Time slot interval', 'restaurant-reservations' ), array( $this, 'interval_field' ), 'rr-settings-general', 'rr_general' );
		add_settings_field( 'rr_blocked', __( 'Blocked dates', 'restaurant-reservations' ), array( $this, 'blocked_field' ), 'rr-settings-general', 'rr_general' );
		add_settings_section( 'rr_hours', __( 'Business Hours', 'restaurant-reservations' ), '__return_false', 'rr-settings-hours' );
		add_settings_field( 'rr_hours_field', __( 'Weekly hours', 'restaurant-reservations' ), array( $this, 'hours_field' ), 'rr-settings-hours', 'rr_hours' );
		add_settings_section( 'rr_email', __( 'Email', 'restaurant-reservations' ), '__return_false', 'rr-settings-email' );
		add_settings_field( 'rr_email_enabled', __( 'Enable notifications', 'restaurant-reservations' ), array( $this, 'toggle_field' ), 'rr-settings-email', 'rr_email' );
		add_settings_field( 'rr_email_admin', __( 'Administrator email', 'restaurant-reservations' ), array( $this, 'email_field' ), 'rr-settings-email', 'rr_email' );
		add_settings_field( 'rr_templates', __( 'Email templates', 'restaurant-reservations' ), array( $this, 'templates_field' ), 'rr-settings-email', 'rr_email' );
	}

	public function sanitize_interval( $value ) { return in_array( absint( $value ), array( 30, 60 ), true ) ? absint( $value ) : 30; }
	public function sanitize_toggle( $value ) { return 'yes' === $value ? 'yes' : 'no'; }
	public function sanitize_dates( $value ) { $items = is_array( $value ) ? $value : explode( ',', (string) $value ); return array_values( array_filter( array_map( 'sanitize_text_field', $items ) ) ); }
	public function sanitize_hours( $value ) { $clean = array(); foreach ( (array) $value as $day => $times ) { $clean[ sanitize_key( $day ) ] = array( 'open' => sanitize_text_field( $times['open'] ?? '' ), 'close' => sanitize_text_field( $times['close'] ?? '' ) ); } return $clean; }
	public function sanitize_templates( $value ) { $clean = array(); foreach ( (array) $value as $key => $content ) { $clean[ sanitize_key( $key ) ] = false !== strpos( $key, 'body' ) ? wp_kses_post( $content ) : sanitize_text_field( $content ); } return $clean; }
	public function number_field( $args ) { printf( '<input type="number" min="%d" name="%s" value="%d">', absint( $args['min'] ), esc_attr( $args['option'] ), absint( get_option( $args['option'], 20 ) ) ); }
	public function interval_field() { $value = absint( get_option( 'rr_time_slot_interval', 30 ) ); echo '<select name="rr_time_slot_interval"><option value="30" ' . selected( $value, 30, false ) . '>' . esc_html__( '30 minutes', 'restaurant-reservations' ) . '</option><option value="60" ' . selected( $value, 60, false ) . '>' . esc_html__( '60 minutes', 'restaurant-reservations' ) . '</option></select>'; }
	public function blocked_field() { echo '<input class="regular-text" name="rr_blocked_dates" value="' . esc_attr( implode( ',', get_option( 'rr_blocked_dates', array() ) ) ) . '"><p class="description">' . esc_html__( 'Comma-separated dates in YYYY-MM-DD format.', 'restaurant-reservations' ) . '</p>'; }
	public function toggle_field() { echo '<label><input type="checkbox" name="rr_email_enabled" value="yes" ' . checked( get_option( 'rr_email_enabled' ), 'yes', false ) . '> ' . esc_html__( 'Send reservation emails', 'restaurant-reservations' ) . '</label>'; }
	public function email_field() { echo '<input type="email" class="regular-text" name="rr_email_admin" value="' . esc_attr( get_option( 'rr_email_admin' ) ) . '">'; }

	public function hours_field() {
		$hours = get_option( 'rr_business_hours', array() );
		$days = array( 'monday' => __( 'Monday', 'restaurant-reservations' ), 'tuesday' => __( 'Tuesday', 'restaurant-reservations' ), 'wednesday' => __( 'Wednesday', 'restaurant-reservations' ), 'thursday' => __( 'Thursday', 'restaurant-reservations' ), 'friday' => __( 'Friday', 'restaurant-reservations' ), 'saturday' => __( 'Saturday', 'restaurant-reservations' ), 'sunday' => __( 'Sunday', 'restaurant-reservations' ) );
		foreach ( $days as $day => $label ) {
			printf( '<p><label class="rr-day-label">%s</label> <input type="time" name="rr_business_hours[%s][open]" value="%s"> <span>%s</span> <input type="time" name="rr_business_hours[%s][close]" value="%s"></p>', esc_html( $label ), esc_attr( $day ), esc_attr( $hours[ $day ]['open'] ?? '' ), esc_html__( 'to', 'restaurant-reservations' ), esc_attr( $day ), esc_attr( $hours[ $day ]['close'] ?? '' ) );
		}
	}

	public function templates_field() {
		$templates = get_option( 'rr_email_templates', array() );
		$labels = array( 'admin_subject' => __( 'Admin subject', 'restaurant-reservations' ), 'admin_body' => __( 'Admin body', 'restaurant-reservations' ), 'customer_subject' => __( 'Customer subject', 'restaurant-reservations' ), 'customer_body' => __( 'Customer body', 'restaurant-reservations' ) );
		foreach ( $labels as $key => $label ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong></label><br>'; if ( false !== strpos( $key, 'body' ) ) { echo '<textarea class="large-text" rows="5" name="rr_email_templates[' . esc_attr( $key ) . ']">' . esc_textarea( $templates[ $key ] ?? '' ) . '</textarea>'; } else { echo '<input class="large-text" name="rr_email_templates[' . esc_attr( $key ) . ']" value="' . esc_attr( $templates[ $key ] ?? '' ) . '">'; } echo '</p>'; }
	}
}
