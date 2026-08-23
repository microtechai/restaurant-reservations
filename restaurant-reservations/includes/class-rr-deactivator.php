<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRDeactivator {
	public static function deactivate() {
		remove_role( 'rr_manager' );
		flush_rewrite_rules();
	}
}

