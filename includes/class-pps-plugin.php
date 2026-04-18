<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PPS_Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->load_dependencies();
			self::$instance->init_modules();
		}
		return self::$instance;
	}

	private function load_dependencies(): void {
		require_once PPS_PLUGIN_DIR . 'includes/class-pps-data.php';
		require_once PPS_PLUGIN_DIR . 'includes/class-pps-analytics.php';
		require_once PPS_PLUGIN_DIR . 'includes/class-pps-updater.php';

		if ( is_admin() ) {
			require_once PPS_PLUGIN_DIR . 'admin/class-pps-admin.php';
		}
	}

	private function init_modules(): void {
		PPS_Updater::get_instance();

		if ( is_admin() ) {
			new PPS_Admin();
		}
	}
}
