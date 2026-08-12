<?php
/**
 * Per-variation admin fields and their accessors.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the content / badge / extra discount fields to each variation.
 */
class NVM_VR_Variation_Meta {

	const META_CONTENT_QTY  = '_nvm_content_qty';
	const META_CONTENT_UNIT = '_nvm_content_unit';
	const META_BADGE        = '_nvm_badge';
	const META_EXTRA_PCT    = '_nvm_extra_discount_pct';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_fields' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_fields' ), 20, 2 );
	}

	/**
	 * Measurement units offered in the variation form.
	 *
	 * @return array<string,string>
	 */
	public static function get_units() {
		return apply_filters(
			'nvm_vr_content_units',
			array(
				''   => __( '— sin definir —', 'nvm-variation-rows' ),
				'ml' => 'ml',
				'l'  => 'l',
				'g'  => 'g',
				'kg' => 'kg',
				'ud' => __( 'unidades', 'nvm-variation-rows' ),
			)
		);
	}

	/**
	 * How much product the unit price refers to: 100 ml, 100 g, 1 l, 1 kg, 1 ud.
	 *
	 * @param string $unit Measurement unit.
	 * @return int
	 */
	public static function get_reference_amount( $unit ) {
		$reference = in_array( $unit, array( 'ml', 'g' ), true ) ? 100 : 1;

		return (int) apply_filters( 'nvm_vr_reference_amount', $reference, $unit );
	}

	/**
	 * Print the fields inside the variation pricing row.
	 *
	 * @param int     $loop           Variation index in the admin list.
	 * @param array   $variation_data Legacy variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public static function render_fields( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input(
			array(
				'id'                => self::META_CONTENT_QTY . $loop,
				'name'              => self::META_CONTENT_QTY . '[' . $loop . ']',
				'value'             => get_post_meta( $variation->ID, self::META_CONTENT_QTY, true ),
				'label'             => __( 'Contenido del envase', 'nvm-variation-rows' ),
				'description'       => __( 'Cantidad que contiene esta variación. Se usa para calcular el precio por unidad de medida.', 'nvm-variation-rows' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
				'wrapper_class'     => 'form-row form-row-first',
			)
		);

		woocommerce_wp_select(
			array(
				'id'            => self::META_CONTENT_UNIT . $loop,
				'name'          => self::META_CONTENT_UNIT . '[' . $loop . ']',
				'value'         => get_post_meta( $variation->ID, self::META_CONTENT_UNIT, true ),
				'label'         => __( 'Unidad de medida', 'nvm-variation-rows' ),
				'options'       => self::get_units(),
				'wrapper_class' => 'form-row form-row-last',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'            => self::META_BADGE . $loop,
				'name'          => self::META_BADGE . '[' . $loop . ']',
				'value'         => get_post_meta( $variation->ID, self::META_BADGE, true ),
				'label'         => __( 'Etiqueta destacada', 'nvm-variation-rows' ),
				'description'   => __( 'Texto corto que aparece junto al nombre de la variación. Por ejemplo: 20% extra.', 'nvm-variation-rows' ),
				'desc_tip'      => true,
				'wrapper_class' => 'form-row form-row-first',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_EXTRA_PCT . $loop,
				'name'              => self::META_EXTRA_PCT . '[' . $loop . ']',
				'value'             => get_post_meta( $variation->ID, self::META_EXTRA_PCT, true ),
				'label'             => __( 'Descuento extra en cesta (%)', 'nvm-variation-rows' ),
				'description'       => __( 'Se descuenta del precio al añadir el producto a la cesta. El total que ve el cliente en la ficha es el que paga.', 'nvm-variation-rows' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
					'max'  => '100',
				),
				'wrapper_class'     => 'form-row form-row-last',
			)
		);
	}

	/**
	 * Persist the fields. WooCommerce verifies its own nonce before firing this action.
	 *
	 * @param int $variation_id Variation post ID.
	 * @param int $i            Variation index in the submitted arrays.
	 */
	public static function save_fields( $variation_id, $i ) {
		if ( ! current_user_can( 'edit_product', $variation_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$qty   = isset( $_POST[ self::META_CONTENT_QTY ][ $i ] ) ? wc_clean( wp_unslash( $_POST[ self::META_CONTENT_QTY ][ $i ] ) ) : '';
		$unit  = isset( $_POST[ self::META_CONTENT_UNIT ][ $i ] ) ? wc_clean( wp_unslash( $_POST[ self::META_CONTENT_UNIT ][ $i ] ) ) : '';
		$badge = isset( $_POST[ self::META_BADGE ][ $i ] ) ? wc_clean( wp_unslash( $_POST[ self::META_BADGE ][ $i ] ) ) : '';
		$extra = isset( $_POST[ self::META_EXTRA_PCT ][ $i ] ) ? wc_clean( wp_unslash( $_POST[ self::META_EXTRA_PCT ][ $i ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_post_meta( $variation_id, self::META_CONTENT_QTY, '' === $qty ? '' : wc_format_decimal( $qty ) );

		$units = self::get_units();
		update_post_meta( $variation_id, self::META_CONTENT_UNIT, isset( $units[ $unit ] ) ? $unit : '' );

		update_post_meta( $variation_id, self::META_BADGE, sanitize_text_field( $badge ) );

		$extra = '' === $extra ? '' : (string) max( 0, min( 100, (float) wc_format_decimal( $extra ) ) );
		update_post_meta( $variation_id, self::META_EXTRA_PCT, $extra );
	}

	/**
	 * Package content of a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array{qty:float,unit:string}
	 */
	public static function get_content( $variation_id ) {
		return array(
			'qty'  => (float) get_post_meta( $variation_id, self::META_CONTENT_QTY, true ),
			'unit' => (string) get_post_meta( $variation_id, self::META_CONTENT_UNIT, true ),
		);
	}

	/**
	 * Highlight label of a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return string
	 */
	public static function get_badge( $variation_id ) {
		return (string) get_post_meta( $variation_id, self::META_BADGE, true );
	}

	/**
	 * Extra cart discount of a variation, as a percentage.
	 *
	 * @param int $variation_id Variation ID.
	 * @return float
	 */
	public static function get_extra_pct( $variation_id ) {
		$pct = (float) get_post_meta( $variation_id, self::META_EXTRA_PCT, true );

		return max( 0, min( 100, $pct ) );
	}
}
