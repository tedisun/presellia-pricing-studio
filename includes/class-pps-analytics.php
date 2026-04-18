<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stats de vente via la table wc_order_product_lookup (WC Analytics).
 * Retourne un tableau vide si la table n'existe pas (WC Analytics non activé).
 */
class PPS_Analytics {

	/**
	 * @param int[] $product_ids  IDs produits ou variations
	 * @return array<int, array{orders_week: int, orders_month: int, orders_year: int, revenue_month: float}>
	 */
	public static function get_stats_batch( array $product_ids ): array {
		global $wpdb;

		if ( empty( $product_ids ) ) {
			return [];
		}

		$table = $wpdb->prefix . 'wc_order_product_lookup';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return [];
		}

		$ids_in      = implode( ',', array_map( 'intval', $product_ids ) );
		$year_start  = gmdate( 'Y-01-01 00:00:00' );
		$month_start = gmdate( 'Y-m-01 00:00:00' );
		$ts_monday   = strtotime( 'monday this week', time() );
		$week_start  = gmdate( 'Y-m-d 00:00:00', $ts_monday !== false ? $ts_monday : time() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT product_id, variation_id, date_created, product_net_revenue
			 FROM {$table}
			 WHERE ( product_id IN ({$ids_in}) OR variation_id IN ({$ids_in}) )
			 AND date_created >= '{$year_start}'"
		);

		if ( ! $rows ) {
			return [];
		}

		$stats    = [];
		$ids_flip = array_flip( $product_ids );

		foreach ( $rows as $row ) {
			$vid = (int) $row->variation_id;
			$pid = (int) $row->product_id;
			$key = ( $vid > 0 && isset( $ids_flip[ $vid ] ) ) ? $vid : $pid;

			if ( ! isset( $stats[ $key ] ) ) {
				$stats[ $key ] = [
					'orders_week'   => 0,
					'orders_month'  => 0,
					'orders_year'   => 0,
					'revenue_month' => 0.0,
				];
			}

			$date = $row->date_created;
			$stats[ $key ]['orders_year']++;

			if ( $date >= $month_start ) {
				$stats[ $key ]['orders_month']++;
				$stats[ $key ]['revenue_month'] += (float) $row->product_net_revenue;
			}

			if ( $date >= $week_start ) {
				$stats[ $key ]['orders_week']++;
			}
		}

		return $stats;
	}
}
