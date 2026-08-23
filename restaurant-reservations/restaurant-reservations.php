<?php
/**
 * Plugin Name: Restaurant Reservations
 * Plugin URI: https://example.com/restaurant-reservations
 * Description: Complete restaurant table reservation system with calendar, statistics, and optional email notifications.
 * Version: 1.0.0
 * Author: Restaurant Reservations Team
 * License: GPLv2 or later
 * Text Domain: restaurant-reservations
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RR_VERSION', '1.0.0' );
define( 'RR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

foreach ( glob( RR_PLUGIN_PATH . 'includes/class-rr-*.php' ) as $rr_class_file ) {
	require_once $rr_class_file;
}

register_activation_hook( __FILE__, array( 'RRActivator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RRDeactivator', 'deactivate' ) );

function rr_initialize_plugin() {
	load_plugin_textdomain( 'restaurant-reservations', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	new RRPostTypes();
	new RRSettings();
	new RRAdmin();
	new RRFrontend();
	new RRAjax();
	new RRStats();
	new RRStaffDashboard();
}
add_action( 'init', 'rr_initialize_plugin', 5 );
