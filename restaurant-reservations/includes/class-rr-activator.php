<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRActivator {
	public static function activate() {
		RRPostTypes::register_post_type();
		RRDatabase::create_table();
		$hours = array();
		foreach ( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ) as $day ) {
			$hours[ $day ] = array( 'open' => '12:00', 'close' => '22:00' );
		}
		$defaults   = array(
			'max_guests_per_slot' => 20,
			'time_slot_interval'   => 30,
			'business_hours'       => $hours,
			'blocked_dates'        => array(),
			'email_admin'          => '',
			'email_enabled'        => 'no',
			'email_templates'      => array(
				'admin_subject'    => __( 'New reservation at {restaurant_name}', 'restaurant-reservations' ),
				'admin_body'       => __( '<p>A new reservation was made by {customer_name} for {guests} guests on {date} at {time}.</p>', 'restaurant-reservations' ),
				'customer_subject' => __( 'Your reservation at {restaurant_name}', 'restaurant-reservations' ),
				'customer_body'    => __( '<p>Hello {customer_name},</p><p>We received your reservation for {guests} guests on {date} at {time}.</p>', 'restaurant-reservations' ),
			),
		);
		foreach ( $defaults as $key => $value ) {
			add_option( 'rr_' . $key, $value );
		}
		self::create_roles();
		flush_rewrite_rules();
	}

	public static function create_roles() {
		remove_role( 'rr_manager' );
		add_role(
			'rr_manager',
			__( 'Restaurant Manager', 'restaurant-reservations' ),
			array(
				'read'                      => true,
				'edit_posts'                => true,
				'edit_rr_reservations'      => true,
				'read_rr_reservation'       => true,
				'edit_rr_reservation'       => true,
				'edit_published_rr_reservations' => true,
			)
		);
	}
}
