<?php
/**
 * Stepper controls around the native quantity field.
 *
 * WooCommerce prints a bare number input whose only controls are the browser spinners, which
 * are tiny and look different in every browser. These buttons wrap that same input through the
 * native hooks — the field, its min/max/step and the form submission stay WooCommerce's.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minus / plus controls around the quantity input.
 */
class NVM_VR_Quantity {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_quantity', array( __CLASS__, 'open' ) );
		add_action( 'woocommerce_after_add_to_cart_quantity', array( __CLASS__, 'close' ) );
	}

	/**
	 * Only where the rows render, so the stepper never appears without its stylesheet.
	 *
	 * Both callbacks read this same check: one printing without the other would leave an
	 * unbalanced wrapper in the form.
	 *
	 * @return bool
	 */
	private static function applies() {
		global $product;

		return class_exists( 'NVM_VR_Renderer' ) && NVM_VR_Renderer::is_supported( $product );
	}

	/**
	 * Open the wrapper and print the decrement control.
	 */
	public static function open() {
		if ( ! self::applies() ) {
			return;
		}

		echo '<div class="nvm-qty">';
		printf(
			'<button type="button" class="nvm-qty__btn nvm-qty__btn--minus" data-nvm-step="-1" aria-label="%s">&minus;</button>',
			esc_attr__( 'Quitar una unidad', 'nvm-variation-rows' )
		);
	}

	/**
	 * Print the increment control and close the wrapper.
	 */
	public static function close() {
		if ( ! self::applies() ) {
			return;
		}

		printf(
			'<button type="button" class="nvm-qty__btn nvm-qty__btn--plus" data-nvm-step="1" aria-label="%s">+</button>',
			esc_attr__( 'Añadir una unidad', 'nvm-variation-rows' )
		);
		echo '</div>';
	}
}
