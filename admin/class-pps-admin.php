<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface admin Pricing Studio.
 *
 * - Page "⚡ Pricing Studio" sous WooCommerce
 * - AJAX pps_bulk_save : sauvegarde coûts + prix partenaires + taux
 * - AJAX pps_load_analytics : stats commandes/CA via WC Analytics
 */
class PPS_Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_action( 'wp_ajax_pps_bulk_save',      [ $this, 'ajax_bulk_save' ] );
		add_action( 'wp_ajax_pps_load_analytics', [ $this, 'ajax_load_analytics' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Pricing Studio', 'presellia-pricing-studio' ),
			__( '⚡ Pricing Studio', 'presellia-pricing-studio' ),
			'manage_woocommerce',
			'pps-studio',
			[ $this, 'render_studio' ]
		);
	}

	// -------------------------------------------------------------------------
	// Scripts
	// -------------------------------------------------------------------------

	public function enqueue_scripts( string $hook ): void {
		if ( 'woocommerce_page_pps-studio' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pps-studio',
			PPS_PLUGIN_URL . 'assets/css/pps-studio.css',
			[],
			PPS_VERSION
		);

		wp_enqueue_script(
			'pps-studio',
			PPS_PLUGIN_URL . 'assets/js/pps-studio.js',
			[],
			PPS_VERSION,
			true
		);

		wp_localize_script( 'pps-studio', 'ppsStudio', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'pps_nonce' ),
			'usdCfaRate' => PPS_Data::get_usd_cfa_rate(),
			'feesPct'    => PPS_Data::get_fees_pct(),
			'hasPpb'     => PPS_Data::has_ppb(),
			'i18n'       => [
				'saving'  => __( 'Enregistrement...', 'presellia-pricing-studio' ),
				'saved'   => __( 'Enregistré !', 'presellia-pricing-studio' ),
				'error'   => __( 'Erreur lors de la sauvegarde.', 'presellia-pricing-studio' ),
				'confirm' => __( 'Enregistrer toutes les modifications ?', 'presellia-pricing-studio' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Page principale
	// -------------------------------------------------------------------------

	public function render_studio(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'presellia-pricing-studio' ) );
		}

		$catalog  = PPS_Data::get_catalog();
		$usd_rate = PPS_Data::get_usd_cfa_rate();
		$has_ppb  = PPS_Data::has_ppb();

		$tiers_map   = [];
		$product_ids = [];

		foreach ( $catalog as $product ) {
			$product_ids[] = $product['id'];
			if ( ! empty( $product['partner_tiers'] ) ) {
				$tiers_map[ $product['id'] ] = $product['partner_tiers'];
			}
			foreach ( $product['variations'] ?? [] as $var ) {
				$product_ids[] = $var['id'];
				if ( ! empty( $var['partner_tiers'] ) ) {
					$tiers_map[ $var['id'] ] = $var['partner_tiers'];
				}
			}
		}

		$grouped    = [];
		foreach ( $catalog as $product ) {
			$grouped[ $product['category'] ][] = $product;
		}
		$categories = array_keys( $grouped );

		require PPS_PLUGIN_DIR . 'admin/page-pps-studio.php';
	}

	// -------------------------------------------------------------------------
	// Render helpers
	// -------------------------------------------------------------------------

	public function render_product_row( array $product, bool $is_variation = false, int $parent_id = 0 ): void {
		$id        = $product['id'];
		$has_vars  = ! empty( $product['variations'] );

		if ( $is_variation ) {
			$row_class = 'pps-row-variation';
		} elseif ( $has_vars ) {
			$row_class = 'pps-row-parent';
		} else {
			$row_class = 'pps-row-product';
		}

		$price_regular = $product['regular_price'] ?? null;
		$price_sale    = $product['sale_price'] ?? null;

		$status_map = [
			'publish' => [ 'label' => '●', 'class' => 'pps-status-publish', 'title' => 'Publié' ],
			'draft'   => [ 'label' => '○', 'class' => 'pps-status-draft',   'title' => 'Brouillon' ],
			'private' => [ 'label' => '◐', 'class' => 'pps-status-private', 'title' => 'Privé' ],
		];
		$s = $status_map[ $product['status'] ] ?? $status_map['draft'];

		$stock_in    = 'instock' === $product['stock_status'];
		$stock_class = $stock_in ? 'pps-stock-in' : 'pps-stock-out';
		$stock_label = $stock_in ? 'En stock' : 'Rupture';
		?>
		<tr class="<?php echo esc_attr( $row_class ); ?>"
			data-id="<?php echo esc_attr( $id ); ?>"
			data-sku="<?php echo esc_attr( $product['sku'] ); ?>"
			data-has-vars="<?php echo $has_vars ? '1' : '0'; ?>"
			data-parent="<?php echo esc_attr( $parent_id ); ?>"
			data-price-regular="<?php echo esc_attr( $price_regular ?? '' ); ?>"
			data-price-sale="<?php echo esc_attr( $price_sale ?? '' ); ?>"
		>
			<td class="pps-col-name">
				<?php if ( $has_vars ) : ?>
					<button type="button" class="pps-toggle-vars button-link" data-id="<?php echo esc_attr( $id ); ?>">▾</button>
				<?php elseif ( $is_variation ) : ?>
					<span class="pps-var-indent">↳</span>
				<?php endif; ?>
				<span class="pps-product-name">
					<?php echo esc_html( $is_variation ? ( $product['attributes'] ?: $product['name'] ) : $product['name'] ); ?>
				</span>
				<span class="<?php echo esc_attr( $s['class'] ); ?>" title="<?php echo esc_attr( $s['title'] ); ?>"><?php echo esc_html( $s['label'] ); ?></span>
				<span class="pps-stock <?php echo esc_attr( $stock_class ); ?>"><?php echo esc_html( $stock_label ); ?></span>
			</td>
			<td class="pps-col-sku"><?php echo esc_html( $product['sku'] ); ?></td>

			<?php if ( $has_vars ) : ?>
				<td colspan="3" class="pps-col-costs pps-parent-placeholder">
					<em><?php esc_html_e( 'Voir variations ↓', 'presellia-pricing-studio' ); ?></em>
				</td>
			<?php else : ?>
				<td class="pps-col-cost-usd pps-group-costs">
					<input type="number" class="pps-input pps-cost-usd" min="0" step="0.01"
						value="<?php echo esc_attr( $product['cost_usd'] ); ?>" placeholder="0">
				</td>
				<td class="pps-col-cost-cfa pps-group-costs">
					<div class="pps-cfa-wrap">
						<input type="number" class="pps-input pps-cost-cfa" min="0" step="1"
							value="<?php echo esc_attr( $product['cost_cfa'] ); ?>" placeholder="0"
							data-manual="<?php echo $product['cost_manual'] ? '1' : '0'; ?>"
							data-was-manual="<?php echo $product['cost_manual'] ? '1' : '0'; ?>">
						<span class="pps-manual-badge" title="Valeur manuelle"
							<?php echo ! $product['cost_manual'] ? 'style="display:none"' : ''; ?>>✎</span>
						<button type="button" class="pps-reset-manual button-link" title="Recalculer depuis USD"
							<?php echo ! $product['cost_manual'] ? 'style="display:none"' : ''; ?>>↺</button>
					</div>
				</td>
				<td class="pps-col-cost-total pps-group-costs pps-readonly" title="Coût CFA de référence (sans frais paiement)">
					<span class="pps-cost-total-val">—</span>
				</td>
			<?php endif; ?>

			<td class="pps-col-price-regular pps-group-client">
				<?php echo $price_regular !== null ? wp_kses_post( wc_price( $price_regular ) ) : '—'; ?>
			</td>
			<td class="pps-col-price-sale pps-group-client">
				<?php echo $price_sale !== null ? wp_kses_post( wc_price( $price_sale ) ) : '—'; ?>
			</td>

			<?php if ( $has_vars ) : ?>
				<td colspan="2" class="pps-group-partner pps-parent-placeholder">
					<em><?php esc_html_e( 'Voir variations ↓', 'presellia-pricing-studio' ); ?></em>
				</td>
			<?php else : ?>
				<td class="pps-col-partner-price pps-group-partner">
					<input type="number" class="pps-input pps-partner-price" min="0" step="1"
						value="<?php echo esc_attr( $product['partner_price'] ?? '' ); ?>" placeholder="—">
				</td>
				<td class="pps-col-partner-tiers pps-group-partner">
					<?php $tiers = $product['partner_tiers']; ?>
					<button type="button" class="pps-tiers-btn button-link" data-id="<?php echo esc_attr( $id ); ?>">
						<?php echo count( $tiers ) > 0
							? esc_html( sprintf( '▾ Paliers (%d)', count( $tiers ) ) )
							: esc_html__( '▾ Paliers', 'presellia-pricing-studio' ); ?>
					</button>
				</td>
			<?php endif; ?>

			<?php if ( $has_vars ) : ?>
				<td colspan="4" class="pps-group-margins pps-parent-placeholder">—</td>
			<?php else : ?>
				<td class="pps-col-margin-client pps-group-margins pps-margin-cell">
					<span class="pps-margin-client-val">—</span>
				</td>
				<td class="pps-col-profit-client pps-group-margins pps-readonly">
					<span class="pps-profit-client-val">—</span>
				</td>
				<td class="pps-col-margin-partner pps-group-margins pps-margin-cell">
					<span class="pps-margin-partner-val">—</span>
				</td>
				<td class="pps-col-profit-partner pps-group-margins pps-readonly">
					<span class="pps-profit-partner-val">—</span>
				</td>
			<?php endif; ?>

			<td class="pps-col-analytics pps-group-analytics pps-analytics-cell"
				data-id="<?php echo esc_attr( $id ); ?>" data-stat="orders_week">—</td>
			<td class="pps-col-analytics pps-group-analytics pps-analytics-cell"
				data-id="<?php echo esc_attr( $id ); ?>" data-stat="orders_month">—</td>
			<td class="pps-col-analytics pps-group-analytics pps-analytics-cell"
				data-id="<?php echo esc_attr( $id ); ?>" data-stat="revenue_month">—</td>

			<td class="pps-col-save">
				<?php if ( ! $has_vars ) : ?>
					<button type="button" class="pps-save-row button button-small"
						data-id="<?php echo esc_attr( $id ); ?>" title="Enregistrer cette ligne">✓</button>
					<span class="pps-row-status"></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php

		if ( ! $has_vars ) {
			$this->render_tiers_row( $id );
		}

		if ( $has_vars ) {
			foreach ( $product['variations'] as $variation ) {
				$this->render_product_row( $variation, true, $id );
			}
		}
	}

	private function render_tiers_row( int $id ): void {
		?>
		<tr class="pps-tiers-row" data-for="<?php echo esc_attr( $id ); ?>" style="display:none">
			<td colspan="17" class="pps-tiers-cell">
				<div class="pps-tiers-editor">
					<table class="pps-tiers-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Qté min', 'presellia-pricing-studio' ); ?></th>
								<th><?php esc_html_e( 'Prix CFA', 'presellia-pricing-studio' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody class="pps-tiers-body"></tbody>
					</table>
					<div class="pps-tiers-actions">
						<button type="button" class="pps-tier-add button button-secondary">
							<?php esc_html_e( '+ Palier', 'presellia-pricing-studio' ); ?>
						</button>
						<span class="pps-tiers-hint">
							<?php esc_html_e( 'Le 1er palier remplace le prix plat.', 'presellia-pricing-studio' ); ?>
						</span>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX : bulk save
	// -------------------------------------------------------------------------

	public function ajax_bulk_save(): void {
		check_ajax_referer( 'pps_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
		}

		if ( ! empty( $_POST['rate'] ) ) {
			$rate = (float) sanitize_text_field( wp_unslash( (string) $_POST['rate'] ) );
			if ( $rate > 0 ) {
				PPS_Data::save_usd_cfa_rate( $rate );
			}
		}

		if ( isset( $_POST['fees_pct'] ) ) {
			$fees = (float) sanitize_text_field( wp_unslash( (string) $_POST['fees_pct'] ) );
			PPS_Data::save_fees_pct( $fees );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw           = isset( $_POST['products'] ) ? wp_unslash( $_POST['products'] ) : '{}';
		$products_data = json_decode( (string) $raw, true );

		if ( ! is_array( $products_data ) ) {
			wp_send_json_error( [ 'message' => 'Données invalides.' ] );
			return;
		}

		$saved = 0;

		foreach ( $products_data as $id_str => $fields ) {
			$id = (int) $id_str;
			if ( $id <= 0 || ! wc_get_product( $id ) ) {
				continue;
			}

			$data = [];

			$text_fields = [ 'cost_cfa', 'cost_usd', 'partner_price' ];
			foreach ( $text_fields as $key ) {
				if ( isset( $fields[ $key ] ) ) {
					$data[ $key ] = sanitize_text_field( (string) $fields[ $key ] );
				}
			}

			if ( isset( $fields['cost_cfa_is_manual'] ) ) {
				$data['cost_cfa_is_manual'] = '1' === (string) $fields['cost_cfa_is_manual'];
			}

			if ( isset( $fields['partner_tiers'] ) ) {
				$tiers_decoded = json_decode( (string) $fields['partner_tiers'], true );
				$data['partner_tiers'] = is_array( $tiers_decoded ) ? $tiers_decoded : [];
			}

			PPS_Data::save_product_data( $id, $data );
			$saved++;
		}

		wp_send_json_success( [
			'saved'   => $saved,
			'message' => sprintf( '%d produit(s) mis à jour.', $saved ),
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX : analytics
	// -------------------------------------------------------------------------

	public function ajax_load_analytics(): void {
		check_ajax_referer( 'pps_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [], 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : '[]';
		$ids = json_decode( (string) $raw, true );

		if ( ! is_array( $ids ) ) {
			wp_send_json_success( [] );
			return;
		}

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		wp_send_json_success( PPS_Analytics::get_stats_batch( $ids ) );
	}
}
