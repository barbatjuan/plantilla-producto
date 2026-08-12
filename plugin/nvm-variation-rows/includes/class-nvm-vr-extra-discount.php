<?php
/**
 * Applies the per-variation extra discount as a real cart price.
 *
 * The total shown on the product page is the price the customer pays, which is the only
 * acceptable behaviour: a display-only discount that the cart does not honour destroys trust
 * and reads as a pricing error.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart-level extra discount.
 */
class NVM_VR_Extra_Discount {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
	}

	/**
	 * Rewrite the price of every cart item carrying an extra discount.
	 *
	 * WooCommerce recalculates the cart several times per request, and an AJAX add-to-cart
	 * already arrives with recalculations behind it. Counting invocations to avoid compounding
	 * therefore skips the discount exactly when it matters; instead every run recomputes from
	 * the catalogue price, which makes the operation idempotent however often it fires.
	 *
	 * @param WC_Cart $cart Cart instance.
	 */
	public static function apply( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}

			$product = $item['data'];
			$pct     = NVM_VR_Variation_Meta::get_extra_pct( $product->get_id() );

			if ( $pct <= 0 ) {
				continue;
			}

			$base = self::get_catalog_price( $product->get_id() );

			if ( $base <= 0 ) {
				continue;
			}

			$product->set_price( round( $base * ( 1 - $pct / 100 ), wc_get_price_decimals() ) );
		}
	}

	/**
	 * Price the catalogue advertises, read from a product instance the cart has not touched.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return float
	 */
	private static function get_catalog_price( $product_id ) {
		$product = wc_get_product( $product_id );

		return $product instanceof WC_Product ? (float) $product->get_price( 'edit' ) : 0.0;
	}

	/**
	 * Show the applied discount under the item name in cart and checkout.
	 *
	 * @param array $data Item data rows.
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function item_data( $data, $item ) {
		if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
			return $data;
		}

		$pct = NVM_VR_Variation_Meta::get_extra_pct( $item['data']->get_id() );

		if ( $pct <= 0 ) {
			return $data;
		}

		$data[] = array(
			'key'     => __( 'Descuento extra', 'nvm-variation-rows' ),
			'value'   => sprintf( '-%s%%', wc_format_localized_decimal( $pct ) ),
			'display' => '',
		);

		return $data;
	}
}
