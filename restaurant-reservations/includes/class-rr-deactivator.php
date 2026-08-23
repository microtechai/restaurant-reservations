<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRDeactivator {
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

