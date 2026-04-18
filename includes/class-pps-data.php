<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Couche données de Pricing Studio.
 *
 * Coût CFA  → meta _wc_cog_cost  (partagée avec SkyVerge Cost of Goods)
 * Prix/paliers partenaire → _ppb_partner_price / _ppb_partner_tiers (PPB)
 * Si PPB actif : délègue à PPB_Pricing pour rester en sync.
 */
class PPS_Data {

	const META_COST_CFA      = '_wc_cog_cost';
	const META_COST_MANUAL   = '_pps_cost_cfa_is_manual';
	const META_COST_USD      = '_pps_cost_usd';
	const META_FEES_PCT      = '_pps_other_fees_pct';
	const META_PARTNER_PRICE = '_ppb_partner_price';
	const META_PARTNER_TIERS = '_ppb_partner_tiers';

	public static function has_ppb(): bool {
		return class_exists( 'PPB_Pricing' );
	}

	public static function get_usd_cfa_rate(): float {
		return max( 1.0, (float) get_option( 'pps_usd_cfa_rate', 655 ) );
	}

	public static function save_usd_cfa_rate( float $rate ): void {
		update_option( 'pps_usd_cfa_rate', (string) max( 1.0, $rate ) );
	}

	// -------------------------------------------------------------------------
	// Getters
	// -------------------------------------------------------------------------

	public static function get_cost_cfa( int $id ): string {
		$val = get_post_meta( $id, self::META_COST_CFA, true );
		return ( is_numeric( $val ) && (float) $val >= 0 ) ? (string) (float) $val : '';
	}

	public static function get_cost_usd( int $id ): string {
		$val = get_post_meta( $id, self::META_COST_USD, true );
		return ( is_numeric( $val ) && (float) $val >= 0 ) ? (string) (float) $val : '';
	}

	public static function get_fees_pct( int $id ): string {
		$val = get_post_meta( $id, self::META_FEES_PCT, true );
		return ( is_numeric( $val ) && (float) $val >= 0 ) ? (string) (float) $val : '';
	}

	public static function is_cost_manual( int $id ): bool {
		return '1' === get_post_meta( $id, self::META_COST_MANUAL, true );
	}

	public static function get_partner_price( int $id ): string {
		if ( self::has_ppb() ) {
			return PPB_Pricing::get_partner_price( $id );
		}
		$val = get_post_meta( $id, self::META_PARTNER_PRICE, true );
		return ( is_numeric( $val ) && (float) $val > 0 ) ? (string) (float) $val : '';
	}

	public static function get_partner_tiers( int $id ): array {
		if ( self::has_ppb() ) {
			return PPB_Pricing::get_partner_tiers( $id );
		}
		$raw     = get_post_meta( $id, self::META_PARTNER_TIERS, true );
		$decoded = $raw ? json_decode( $raw, true ) : null;
		return is_array( $decoded ) ? $decoded : [];
	}

	// -------------------------------------------------------------------------
	// Save (un produit)
	// -------------------------------------------------------------------------

	public static function save_product_data( int $id, array $data ): void {
		if ( array_key_exists( 'cost_cfa', $data ) ) {
			$val = (string) $data['cost_cfa'];
			if ( '' === $val || ! is_numeric( $val ) ) {
				delete_post_meta( $id, self::META_COST_CFA );
			} else {
				update_post_meta( $id, self::META_COST_CFA, wc_format_decimal( $val ) );
			}
		}

		if ( array_key_exists( 'cost_cfa_is_manual', $data ) ) {
			if ( $data['cost_cfa_is_manual'] ) {
				update_post_meta( $id, self::META_COST_MANUAL, '1' );
			} else {
				delete_post_meta( $id, self::META_COST_MANUAL );
			}
		}

		if ( array_key_exists( 'cost_usd', $data ) ) {
			$val = (string) $data['cost_usd'];
			if ( '' === $val || ! is_numeric( $val ) ) {
				delete_post_meta( $id, self::META_COST_USD );
			} else {
				update_post_meta( $id, self::META_COST_USD, (string) (float) $val );
			}
		}

		if ( array_key_exists( 'fees_pct', $data ) ) {
			$val = (string) $data['fees_pct'];
			if ( '' === $val || ! is_numeric( $val ) ) {
				delete_post_meta( $id, self::META_FEES_PCT );
			} else {
				update_post_meta( $id, self::META_FEES_PCT, (string) max( 0.0, (float) $val ) );
			}
		}

		if ( array_key_exists( 'partner_price', $data ) ) {
			$val = (string) $data['partner_price'];
			if ( self::has_ppb() ) {
				PPB_Pricing::set_partner_price( $id, $val );
			} elseif ( '' === $val || ! is_numeric( $val ) || (float) $val <= 0 ) {
				delete_post_meta( $id, self::META_PARTNER_PRICE );
			} else {
				update_post_meta( $id, self::META_PARTNER_PRICE, wc_format_decimal( $val ) );
			}
		}

		if ( array_key_exists( 'partner_tiers', $data ) ) {
			$tiers = is_array( $data['partner_tiers'] ) ? $data['partner_tiers'] : [];
			if ( self::has_ppb() ) {
				PPB_Pricing::set_partner_tiers( $id, $tiers );
			} elseif ( empty( $tiers ) ) {
				delete_post_meta( $id, self::META_PARTNER_TIERS );
			} else {
				update_post_meta( $id, self::META_PARTNER_TIERS, wp_json_encode( $tiers ) );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Catalogue
	// -------------------------------------------------------------------------

	public static function get_catalog(): array {
		$products_wc = wc_get_products( [
			'status'  => [ 'publish', 'draft', 'private' ],
			'limit'   => -1,
			'orderby' => 'title',
			'order'   => 'ASC',
		] );

		$catalog = [];

		foreach ( $products_wc as $product ) {
			$item                    = self::format_product( $product );
			$cat                     = self::get_root_category( $product->get_id() );
			$item['category']        = $cat['name'];
			$item['category_order']  = $cat['order'];

			if ( $product->is_type( 'variable' ) ) {
				$item['variations'] = [];
				foreach ( $product->get_available_variations() as $var_data ) {
					$variation = wc_get_product( $var_data['variation_id'] );
					if ( $variation instanceof WC_Product_Variation ) {
						$item['variations'][] = self::format_product( $variation );
					}
				}
				if ( ! empty( $item['variations'] ) ) {
					$catalog[] = $item;
				}
			} elseif ( $product->is_type( 'simple' ) ) {
				$catalog[] = $item;
			}
		}

		usort( $catalog, static fn( $a, $b ) =>
			$a['category_order'] <=> $b['category_order']
			?: strcmp( $a['category'], $b['category'] )
			?: strcmp( $a['name'], $b['name'] )
		);

		return $catalog;
	}

	private static function format_product( WC_Product $product ): array {
		$id    = $product->get_id();
		$tiers = self::get_partner_tiers( $id );
		$ptnr  = self::get_partner_price( $id );

		if ( ! empty( $tiers ) ) {
			$ptnr = (string) $tiers[0]['price'];
		}

		return [
			'id'            => $id,
			'name'          => $product->get_name(),
			'sku'           => $product->get_sku(),
			'type'          => $product->get_type(),
			'status'        => $product->get_status(),
			'stock_status'  => $product->get_stock_status(),
			'regular_price' => $product->get_regular_price() !== '' ? (float) $product->get_regular_price() : null,
			'sale_price'    => $product->get_sale_price()    !== '' ? (float) $product->get_sale_price()    : null,
			'partner_price' => $ptnr !== '' ? (float) $ptnr : null,
			'partner_tiers' => $tiers,
			'cost_cfa'      => self::get_cost_cfa( $id ),
			'cost_usd'      => self::get_cost_usd( $id ),
			'cost_manual'   => self::is_cost_manual( $id ),
			'fees_pct'      => self::get_fees_pct( $id ),
			'attributes'    => $product instanceof WC_Product_Variation
			                   ? wc_get_formatted_variation( $product, true, false )
			                   : '',
		];
	}

	private static function get_root_category( int $product_id ): array {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return [ 'name' => __( 'Sans catégorie', 'presellia-pricing-studio' ), 'order' => 9999 ];
		}

		$cat_name  = '';
		$cat_order = 9999;

		foreach ( $terms as $term ) {
			if ( 0 === (int) $term->parent ) {
				$order = (int) get_term_meta( $term->term_id, 'order', true );
				if ( '' === $cat_name || $order < $cat_order ) {
					$cat_name  = $term->name;
					$cat_order = $order;
				}
			}
		}

		if ( '' === $cat_name ) {
			$root = $terms[0];
			while ( 0 !== (int) $root->parent ) {
				$parent = get_term( (int) $root->parent, 'product_cat' );
				if ( ! $parent || is_wp_error( $parent ) ) {
					break;
				}
				$root = $parent;
			}
			$cat_name  = $root->name;
			$cat_order = (int) get_term_meta( $root->term_id, 'order', true );
		}

		return [
			'name'  => $cat_name ?: __( 'Sans catégorie', 'presellia-pricing-studio' ),
			'order' => $cat_order,
		];
	}
}
