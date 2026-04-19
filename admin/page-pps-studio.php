<?php
/**
 * Template page Pricing Studio.
 * Inclus depuis PPS_Admin::render_studio() — $this, $catalog, $usd_rate,
 * $has_ppb, $tiers_map, $product_ids, $grouped, $categories sont disponibles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap pps-wrap">
	<h1><?php esc_html_e( 'Pricing Studio', 'presellia-pricing-studio' ); ?></h1>

	<?php if ( ! $has_ppb ) : ?>
		<div class="notice notice-info inline" style="margin:0 0 12px">
			<p><?php esc_html_e( 'Presellia Partner Bridge non détecté — les colonnes prix/paliers revendeur sont désactivées.', 'presellia-pricing-studio' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Barre de contrôles -->
	<div class="pps-controls-bar">
		<div class="pps-controls-left">
			<label class="pps-rate-label">
				<?php esc_html_e( 'Taux USD → CFA', 'presellia-pricing-studio' ); ?>
				<input type="number" id="pps-global-rate" class="pps-rate-input"
					value="<?php echo esc_attr( $usd_rate ); ?>" min="1" step="1">
			</label>

			<label class="pps-rate-label">
				<?php esc_html_e( 'Frais paiement %', 'presellia-pricing-studio' ); ?>
				<input type="number" id="pps-global-fees" class="pps-rate-input"
					value="<?php echo esc_attr( PPS_Data::get_fees_pct() ); ?>" min="0" max="100" step="0.1">
			</label>

			<select id="pps-filter-cat" class="pps-select">
				<option value=""><?php esc_html_e( 'Toutes catégories', 'presellia-pricing-studio' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
				<?php endforeach; ?>
			</select>

			<input type="search" id="pps-search" class="pps-search"
				placeholder="<?php esc_attr_e( 'Rechercher un produit…', 'presellia-pricing-studio' ); ?>">
		</div>

		<div class="pps-controls-right">
			<span id="pps-save-status"></span>
			<button id="pps-save-all" class="button button-primary button-large">
				<?php esc_html_e( 'Tout enregistrer', 'presellia-pricing-studio' ); ?>
			</button>
		</div>
	</div>

	<!-- Légende -->
	<div class="pps-legend">
		<span class="pps-leg-good">■</span> <?php esc_html_e( 'Marge > 60%', 'presellia-pricing-studio' ); ?>
		&nbsp;
		<span class="pps-leg-ok">■</span> 40–60%
		&nbsp;
		<span class="pps-leg-bad">■</span> &lt; 40%
		&nbsp;|&nbsp;
		<span class="pps-manual-badge">✎</span> <?php esc_html_e( 'Coût CFA saisi manuellement', 'presellia-pricing-studio' ); ?>
		&nbsp;|&nbsp;
		<span style="color:#999">—</span> <?php esc_html_e( 'Analytics : chargement après affichage', 'presellia-pricing-studio' ); ?>
	</div>

	<?php if ( empty( $catalog ) ) : ?>
		<p><?php esc_html_e( 'Aucun produit trouvé.', 'presellia-pricing-studio' ); ?></p>
	<?php else : ?>

	<div class="pps-table-wrap">
		<table id="pps-studio-table" class="widefat pps-table">
			<thead>
				<tr class="pps-thead-groups">
					<th colspan="2"  class="pps-group-header pps-gh-product"><?php esc_html_e( 'Produit', 'presellia-pricing-studio' ); ?></th>
					<th colspan="3"  class="pps-group-header pps-gh-costs"><?php esc_html_e( 'Coûts sourcing', 'presellia-pricing-studio' ); ?></th>
					<th colspan="2"  class="pps-group-header pps-gh-client"><?php esc_html_e( 'Prix client', 'presellia-pricing-studio' ); ?></th>
					<th colspan="2"  class="pps-group-header pps-gh-partner"><?php esc_html_e( 'Prix revendeur', 'presellia-pricing-studio' ); ?></th>
					<th colspan="4"  class="pps-group-header pps-gh-margins"><?php esc_html_e( 'Rentabilité', 'presellia-pricing-studio' ); ?></th>
					<th colspan="3"  class="pps-group-header pps-gh-analytics"><?php esc_html_e( 'Analytics', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-header"></th>
				</tr>
				<tr class="pps-thead-cols">
					<th><?php esc_html_e( 'Produit / Variation', 'presellia-pricing-studio' ); ?></th>
					<th>SKU</th>
					<th class="pps-group-costs">USD</th>
					<th class="pps-group-costs">CFA</th>
					<th class="pps-group-costs"><?php esc_html_e( 'Base coût', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-client"><?php esc_html_e( 'Régulier', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-client">Promo</th>
					<th class="pps-group-partner">Prix</th>
					<th class="pps-group-partner">Paliers</th>
					<th class="pps-group-margins"><?php esc_html_e( 'Marge cli%', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-margins"><?php esc_html_e( 'Profit cli', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-margins"><?php esc_html_e( 'Marge rev%', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-margins"><?php esc_html_e( 'Profit rev', 'presellia-pricing-studio' ); ?></th>
					<th class="pps-group-analytics">Cmd/sem</th>
					<th class="pps-group-analytics">Cmd/mois</th>
					<th class="pps-group-analytics">CA/mois</th>
					<th></th>
				</tr>
			</thead>

			<?php foreach ( $grouped as $cat_name => $products ) : ?>
				<tbody class="pps-cat-group" data-cat="<?php echo esc_attr( $cat_name ); ?>">
					<tr class="pps-cat-header">
						<td colspan="17"><strong><?php echo esc_html( strtoupper( $cat_name ) ); ?></strong></td>
					</tr>
					<?php foreach ( $products as $product ) : ?>
						<?php $this->render_product_row( $product ); ?>
					<?php endforeach; ?>
				</tbody>
			<?php endforeach; ?>
		</table>
	</div>

	<div class="pps-save-bar-bottom">
		<button class="button button-primary button-large pps-save-all-bottom">
			<?php esc_html_e( 'Tout enregistrer', 'presellia-pricing-studio' ); ?>
		</button>
		<span class="pps-save-status-bottom"></span>
	</div>

	<?php endif; ?>
</div>

<script>
var ppsStudioTiers      = <?php echo wp_json_encode( $tiers_map ); ?>;
var ppsStudioProductIds = <?php echo wp_json_encode( array_values( $product_ids ) ); ?>;
</script>
