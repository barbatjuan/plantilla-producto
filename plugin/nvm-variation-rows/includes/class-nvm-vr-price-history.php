<?php
/**
 * Rolling 30-day price log, used for the reference-price note next to the crossed-out price.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Records each price change of a purchasable product and returns the lowest price of the window.
 */
class NVM_VR_Price_History {

	const META_LOG    = '_nvm_price_log';
	const WINDOW_DAYS = 30;

	/**
	 * Register hooks. Disable the whole feature with the nvm_vr_enable_price_history filter.
	 */
	public static function init() {
		if ( ! apply_filters( 'nvm_vr_enable_price_history', true ) ) {
			return;
		}

		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'log' ), 10, 1 );
	}

	/**
	 * Append the current price to the log when it actually changed.
	 *
	 * @param mixed $product Product object handed over by WooCommerce.
	 */
	public static function log( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// A variable parent has no price of its own; its children are logged individually.
		if ( $product->is_type( 'variable' ) ) {
			return;
		}

		$price = (float) $product->get_price( 'edit' );

		if ( $price <= 0 ) {
			return;
		}

		$log  = self::get_log( $product->get_id() );
		$last = empty( $log ) ? null : (float) end( $log );

		if ( null !== $last && abs( $last - $price ) < 0.001 ) {
			return;
		}

		$log[ (string) time() ] = $price;

		update_post_meta( $product->get_id(), self::META_LOG, self::prune( $log ) );
	}

	/**
	 * Lowest price recorded inside the window.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return float|null Null when there is nothing logged yet.
	 */
	public static function get_lowest( $product_id ) {
		$log = self::prune( self::get_log( $product_id ) );

		if ( empty( $log ) ) {
			return null;
		}

		return (float) min( $log );
	}

	/**
	 * Read the stored log.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return array<string,float>
	 */
	private static function get_log( $product_id ) {
		$log = get_post_meta( $product_id, self::META_LOG, true );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Drop entries older than the window.
	 *
	 * @param array<string,float> $log Price log.
	 * @return array<string,float>
	 */
	private static function prune( array $log ) {
		$cutoff = time() - ( self::WINDOW_DAYS * DAY_IN_SECONDS );

		foreach ( array_keys( $log ) as $timestamp ) {
			if ( (int) $timestamp < $cutoff ) {
				unset( $log[ $timestamp ] );
			}
		}

		return $log;
	}
}
