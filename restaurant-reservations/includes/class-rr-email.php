<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rr_send_notifications', array( 'RREmail', 'send_notifications' ), 10, 1 );

class RREmail {
	private function replace( $template, $reservation_id ) {
		$values = array(
			'{customer_name}'   => get_the_title( $reservation_id ),
			'{date}'            => get_post_meta( $reservation_id, '_rr_date', true ),
			'{time}'            => get_post_meta( $reservation_id, '_rr_time', true ),
			'{guests}'          => get_post_meta( $reservation_id, '_rr_guests', true ),
			'{restaurant_name}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		return strtr( $template, $values );
	}

	public static function send_notifications( $reservation_id ) {
		if ( 'yes' !== get_option( 'rr_email_enabled', 'no' ) ) { return; }
		$templates = get_option( 'rr_email_templates', array() );
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$admin     = sanitize_email( get_option( 'rr_email_admin', get_option( 'admin_email' ) ) );
		if ( $admin ) {
			wp_mail( $admin, wp_strip_all_tags( $this->replace( $templates['admin_subject'] ?? __( 'New reservation', 'restaurant-reservations' ), $reservation_id ) ), wp_kses_post( $this->replace( $templates['admin_body'] ?? '', $reservation_id ) ), $headers );
		}
		$customer = sanitize_email( get_post_meta( $reservation_id, '_rr_email', true ) );
		if ( $customer ) {
			wp_mail( $customer, wp_strip_all_tags( $this->replace( $templates['customer_subject'] ?? __( 'Reservation received', 'restaurant-reservations' ), $reservation_id ) ), wp_kses_post( $this->replace( $templates['customer_body'] ?? '', $reservation_id ) ), $headers );
		}
	}
}