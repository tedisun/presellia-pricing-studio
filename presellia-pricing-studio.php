<?php
/**
 * Plugin Name:  Presellia Pricing Studio
 * Plugin URI:   https://presellia.com
 * Description:  Éditeur de prix et analyse de rentabilité — coûts sourcing USD/CFA, marges client/revendeur, paliers dégressifs, analytics WooCommerce.
 * Version:      1.1.0
 * Author:       Presellia
 * Text Domain:  presellia-pricing-studio
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PPS_VERSION',     '1.1.0' );
define( 'PPS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PPS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'PPS_PLUGIN_FILE', __FILE__ );

require_once PPS_PLUGIN_DIR . 'includes/class-pps-activator.php';
register_activation_hook( __FILE__, [ 'PPS_Activator', 'activate' ] );

add_action( 'plugins_loaded', static function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>Presellia Pricing Studio</strong> nécessite WooCommerce pour fonctionner.';
			echo '</p></div>';
		} );
		return;
	}

	require_once PPS_PLUGIN_DIR . 'includes/class-pps-plugin.php';
	PPS_Plugin::instance();
} );
