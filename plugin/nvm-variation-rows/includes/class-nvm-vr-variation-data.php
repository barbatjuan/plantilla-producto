<?php
/**
 * Computed values for each variation: discount percentage, unit price, extra discount, total.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the nvm payload and injects it into the variation JSON WooCommerce prints in the form,
 * so PHP rendering and the front-end script read exactly the same numbers.
 */
class NVM_VR_Variation_Data {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_data' ), 10, 3 );
	}

	/**
	 * Attach the payload to the variation array.
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product_Variable  $parent    Parent product.
	 * @param WC_Product_Variation $variation Variation object.
	 * @return array
	 */
	public static function add_data( $data, $parent, $variation ) {
		$data['nvm'] = self::build( $variation );

		return $data;
	}

	/**
	 * Compute everything the row and the total need.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @return array<string,mixed>
	 */
	public static function build( $variation ) {
		$price   = (float) wc_get_price_to_display( $variation );
		$regular = (float) wc_get_price_to_display( $variation, array( 'price' => $variation->get_regular_price() ) );

		$has_discount = $regular > 0 && $regular > $price;
		$discount_pct = $has_discount ? (int) round( ( ( $regular - $price ) / $regular ) * 100 ) : 0;

		$extra_pct = NVM_VR_Variation_Meta::get_extra_pct( $variation->get_id() );
		$has_extra = $extra_pct > 0;
		$total     = $has_extra ? $price * ( 1 - $extra_pct / 100 ) : $price;

		$payload = array(
			'price'          => $price,
			'price_html'     => wc_price( $price ),
			'regular_html'   => $has_discount ? wc_price( $regular ) : '',
			'has_discount'   => $has_discount,
			'discount_pct'   => $discount_pct,
			'discount_label' => $has_discount ? sprintf( '-%d%%', $discount_pct ) : '',
			'unit_html'      => self::unit_price_html( $variation, $price ),
			'badge'          => NVM_VR_Variation_Meta::get_badge( $variation->get_id() ),
			'extra_pct'      => $extra_pct,
			'has_extra'      => $has_extra,
			'total'          => $total,
			'total_html'     => wc_price( $total ),
			'reference_note' => self::reference_note( $variation, $price ),
			'in_stock'       => $variation->is_in_stock() && $variation->is_purchasable(),
		);

		return apply_filters( 'nvm_vr_variation_payload', $payload, $variation );
	}

	/**
	 * Price per reference amount, e.g. "100 ml / 7,80 €".
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @param float                $price     Display price.
	 * @return string Empty when the variation has no content defined.
	 */
	private static function unit_price_html( $variation, $price ) {
		$content = NVM_VR_Variation_Meta::get_content( $variation->get_id() );

		if ( $content['qty'] <= 0 || '' === $content['unit'] ) {
			return '';
		}

		$reference  = NVM_VR_Variation_Meta::get_reference_amount( $content['unit'] );
		$unit_price = ( $price / $content['qty'] ) * $reference;

		return sprintf(
			/* translators: 1: reference amount, 2: unit of measurement, 3: formatted price. */
			_x( '%1$s %2$s / %3$s', 'unit price', 'nvm-variation-rows' ),
			$reference,
			esc_html( $content['unit'] ),
			wc_price( $unit_price )
		);
	}

	/**
	 * Plain-text reference-price note for the info icon.
	 *
	 * @param WC_Product_Variation $variation Variation object.
	 * @param float                $price     Price charged today.
	 * @return string Empty when the note would carry no information.
	 */
	private static function reference_note( $variation, $price ) {
		$lowest = NVM_VR_Price_History::get_lowest( $variation->get_id() );

		// A recorded low that matches what is charged today says nothing, and printed next to a
		// crossed-out price it reads as if the old price were the current one.
		if ( null === $lowest || $lowest >= $price ) {
			return '';
		}

		return sprintf(
			/* translators: %s: formatted price. */
			__( 'Precio más bajo de los últimos 30 días: %s', 'nvm-variation-rows' ),
			html_entity_decode( wp_strip_all_tags( wc_price( $lowest ) ), ENT_QUOTES, get_bloginfo( 'charset' ) )
		);
	}
}
