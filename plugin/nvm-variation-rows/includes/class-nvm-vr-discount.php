<?php
/**
 * The one place that decides what "on sale" and "-20%" mean.
 *
 * The buy box and the product card show the same discount for the same product, so they read
 * it from here rather than each recomputing it. Two implementations would drift the day the
 * rule changes — rounding, tax mode, what a variable product's headline price is — and the
 * archive would keep advertising a percentage the product page no longer charges.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discount figures for any product: simple, variable or a single variation.
 */
class NVM_VR_Discount {

	/**
	 * Resolve the prices and the percentage off.
	 *
	 * Prices come out of wc_get_price_to_display so they follow the store's tax display
	 * setting; comparing a net price against a gross one is what produces the phantom
	 * discounts that only appear on tax-inclusive stores.
	 *
	 * @param WC_Product|int|null $product Product, product ID, or null for the global product.
	 * @return array{price:float,regular:float,has_discount:bool,pct:int,label:string}
	 */
	public static function get( $product = null ) {
		$product = self::resolve( $product );
		$empty   = array(
			'price'        => 0.0,
			'regular'      => 0.0,
			'has_discount' => false,
			'pct'          => 0,
			'label'        => '',
		);

		if ( ! $product instanceof WC_Product ) {
			return $empty;
		}

		list( $price, $regular ) = self::raw_prices( $product );

		if ( $price <= 0 ) {
			return $empty;
		}

		// A product with no regular price is not a product discounted to zero: it is a product
		// whose list price was never filled in, and the price it charges is still the price.
		$price   = (float) wc_get_price_to_display( $product, array( 'price' => $price ) );
		$regular = $regular > 0 ? (float) wc_get_price_to_display( $product, array( 'price' => $regular ) ) : $price;

		if ( $regular <= $price ) {
			return array_merge(
				$empty,
				array(
					'price'   => $price,
					'regular' => $regular,
				)
			);
		}

		$pct = (int) round( ( ( $regular - $price ) / $regular ) * 100 );

		return apply_filters(
			'nvm_vr_discount',
			array(
				'price'        => $price,
				'regular'      => $regular,
				'has_discount' => true,
				'pct'          => $pct,
				/* translators: %d: discount percentage. */
				'label'        => sprintf( __( '-%d%%', 'nvm-variation-rows' ), $pct ),
			),
			$product
		);
	}

	/**
	 * Percentage off, or 0 when the product is not discounted.
	 *
	 * @param WC_Product|int|null $product Product, product ID, or null for the global product.
	 * @return int
	 */
	public static function pct( $product = null ) {
		$data = self::get( $product );

		return $data['pct'];
	}

	/**
	 * Net prices before tax display is applied.
	 *
	 * A variable product has no price of its own, so the headline is the cheapest variation —
	 * the same one WooCommerce prints as "Desde …". Reading get_regular_price() on the parent
	 * returns an empty string and would silently report every variable product as undiscounted.
	 *
	 * @param WC_Product $product Product to read.
	 * @return array{0:float,1:float} Current price, regular price.
	 */
	private static function raw_prices( $product ) {
		if ( $product instanceof WC_Product_Variable ) {
			return array(
				(float) $product->get_variation_price( 'min', true ),
				(float) $product->get_variation_regular_price( 'min', true ),
			);
		}

		return array(
			(float) $product->get_price(),
			(float) $product->get_regular_price(),
		);
	}

	/**
	 * Accept a product, an ID, or nothing at all — inside a loop the global is the product.
	 *
	 * @param WC_Product|int|null $product Candidate.
	 * @return WC_Product|null
	 */
	private static function resolve( $product ) {
		if ( $product instanceof WC_Product ) {
			return $product;
		}

		if ( is_numeric( $product ) ) {
			$resolved = wc_get_product( (int) $product );

			return $resolved instanceof WC_Product ? $resolved : null;
		}

		$current = $GLOBALS['product'] ?? null;

		return $current instanceof WC_Product ? $current : null;
	}
}
