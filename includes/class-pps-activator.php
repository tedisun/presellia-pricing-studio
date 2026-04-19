<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PPS_Activator {

	public static function activate(): void {
		if ( ! get_option( 'pps_usd_cfa_rate' ) ) {
			add_option( 'pps_usd_cfa_rate', '655' );
		}
		if ( false === get_option( 'pps_other_fees_pct', false ) ) {
			add_option( 'pps_other_fees_pct', '0' );
		}
	}
}
