<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Supprime uniquement l'option propre à PPS.
// Les metas produit (_wc_cog_cost, _ppb_*) appartiennent aux autres plugins
// et ne sont pas supprimées.
delete_option( 'pps_usd_cfa_rate' );
delete_option( 'pps_other_fees_pct' );
