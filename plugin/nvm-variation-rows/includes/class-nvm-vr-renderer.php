<?php
/**
 * Front-end rendering of the variation rows.
 *
 * The native WooCommerce <select> stays in the form and keeps driving price, stock and
 * add-to-cart validation. The rows are a presentation layer that sets the select value.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Appends the row list after the attribute dropdown and prints the total block.
 */
class NVM_VR_Renderer {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'append_rows' ), 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_total' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Rows only make sense when one attribute drives the variations: with two or more,
	 * an option no longer maps to a single price and the native dropdowns are kept.
	 *
	 * @param mixed $product Product to test.
	 * @return bool
	 */
	public static function is_supported( $product ) {
		if ( ! $product instanceof WC_Product_Variable ) {
			return false;
		}

		$supported = 1 === count( $product->get_variation_attributes() );

		return (bool) apply_filters( 'nvm_vr_is_supported', $supported, $product );
	}

	/**
	 * Append the row list to the dropdown markup.
	 *
	 * @param string $html Dropdown HTML built by WooCommerce.
	 * @param array  $args Dropdown arguments.
	 * @return string
	 */
	public static function append_rows( $html, $args ) {
		$product = isset( $args['product'] ) ? $args['product'] : null;

		if ( ! self::is_supported( $product ) ) {
			return $html;
		}

		$attribute = isset( $args['attribute'] ) ? $args['attribute'] : '';
		$options   = isset( $args['options'] ) ? (array) $args['options'] : array();
		$variations = self::map_options_to_variations( $product, $attribute );

		if ( empty( $options ) || empty( $variations ) ) {
			return $html;
		}

		$selected = isset( $args['selected'] ) ? (string) $args['selected'] : '';
		$name     = 'attribute_' . sanitize_title( $attribute );

		ob_start();
		?>
		<ul class="nvm-vr" role="radiogroup" data-nvm-rows data-attribute="<?php echo esc_attr( $name ); ?>"
			aria-label="<?php esc_attr_e( 'Formatos disponibles', 'nvm-variation-rows' ); ?>">
			<?php
			$index = 0;
			foreach ( $options as $option ) {
				$option = (string) $option;

				if ( ! isset( $variations[ $option ] ) ) {
					continue;
				}

				self::render_row(
					$variations[ $option ],
					self::option_label( $product, $attribute, $option ),
					$option,
					$option === $selected,
					0 === $index
				);

				$index++;
			}
			?>
		</ul>
		<?php

		return $html . ob_get_clean();
	}

	/**
	 * Print one row.
	 *
	 * @param WC_Product_Variation $variation  Variation behind the row.
	 * @param string               $label      Human readable option name.
	 * @param string               $value      Option value fed to the select.
	 * @param bool                 $is_checked Whether the option is the pre-selected one.
	 * @param bool                 $is_first   Whether it is the first row, used for roving tabindex.
	 */
	private static function render_row( $variation, $label, $value, $is_checked, $is_first ) {
		$data      = NVM_VR_Variation_Data::build( $variation );
		$classes   = array( 'nvm-vr__row' );
		$classes[] = $is_checked ? 'is-selected' : '';
		$classes[] = $data['in_stock'] ? '' : 'is-unavailable';
		?>
		<li class="<?php echo esc_attr( trim( implode( ' ', array_filter( $classes ) ) ) ); ?>"
			role="radio"
			aria-checked="<?php echo $is_checked ? 'true' : 'false'; ?>"
			<?php echo $data['in_stock'] ? '' : 'aria-disabled="true"'; ?>
			tabindex="<?php echo ( $is_checked || $is_first ) ? '0' : '-1'; ?>"
			data-value="<?php echo esc_attr( $value ); ?>">

			<span class="nvm-vr__radio" aria-hidden="true"></span>

			<span class="nvm-vr__labels">
				<span class="nvm-vr__name"><?php echo esc_html( $label ); ?></span>
				<?php if ( '' !== $data['badge'] ) : ?>
					<span class="nvm-vr__badge"><?php echo esc_html( $data['badge'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! $data['in_stock'] ) : ?>
					<span class="nvm-vr__stock"><?php esc_html_e( 'Sin stock', 'nvm-variation-rows' ); ?></span>
				<?php endif; ?>
			</span>

			<?php if ( $data['has_discount'] ) : ?>
				<span class="nvm-vr__sale"><?php echo esc_html( $data['discount_label'] ); ?></span>
			<?php endif; ?>

			<span class="nvm-vr__prices">
				<?php if ( '' !== $data['regular_html'] ) : ?>
					<span class="nvm-vr__old">
						<s><?php echo wp_kses_post( $data['regular_html'] ); ?></s>
						<?php if ( '' !== $data['reference_note'] ) : ?>
							<i class="nvm-vr__info" tabindex="0"
								title="<?php echo esc_attr( $data['reference_note'] ); ?>"
								aria-label="<?php echo esc_attr( $data['reference_note'] ); ?>">i</i>
						<?php endif; ?>
					</span>
				<?php endif; ?>

				<span class="nvm-vr__now"><?php echo wp_kses_post( $data['price_html'] ); ?></span>

				<?php if ( '' !== $data['unit_html'] ) : ?>
					<span class="nvm-vr__unit"><?php echo wp_kses_post( $data['unit_html'] ); ?></span>
				<?php endif; ?>
			</span>
		</li>
		<?php
	}

	/**
	 * Total block, printed next to the quantity field and the add-to-cart button.
	 * It stays hidden until a variation is selected.
	 */
	public static function render_total() {
		global $product;

		if ( ! self::is_supported( $product ) ) {
			return;
		}
		?>
		<div class="nvm-total" data-nvm-total hidden>
			<span class="nvm-total__label"><?php esc_html_e( 'Total:', 'nvm-variation-rows' ); ?></span>
			<span class="nvm-total__value" data-nvm-total-value></span>
			<small class="nvm-total__note" data-nvm-total-note></small>
		</div>
		<?php
	}

	/**
	 * Load assets only on single product pages that actually render rows.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! self::is_supported( $product ) ) {
			return;
		}

		NVM_VR_Settings::enqueue_tokens();

		wp_enqueue_style(
			'nvm-variation-rows',
			NVM_VR_URL . 'assets/css/nvm-variation-rows.css',
			array( 'nvm-tokens' ),
			NVM_VR_VERSION
		);

		wp_enqueue_script(
			'nvm-variation-rows',
			NVM_VR_URL . 'assets/js/nvm-variation-rows.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			NVM_VR_VERSION,
			true
		);

		wp_localize_script(
			'nvm-variation-rows',
			'nvmVrStrings',
			array(
				'extraNote' => __( '*Dto. extra aplicado', 'nvm-variation-rows' ),
			)
		);
	}

	/**
	 * Map every attribute option to its variation. Returns an empty array when the mapping
	 * is not one to one, which is the signal to keep the native dropdown untouched.
	 *
	 * @param WC_Product_Variable $product   Parent product.
	 * @param string              $attribute Attribute name.
	 * @return array<string,WC_Product_Variation>
	 */
	private static function map_options_to_variations( $product, $attribute ) {
		$key  = 'attribute_' . sanitize_title( $attribute );
		$rows = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( ! $variation instanceof WC_Product_Variation || ! $variation->variation_is_visible() ) {
				continue;
			}

			$attributes = $variation->get_variation_attributes();
			$value      = isset( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';

			// An "any" variation carries no value, so it cannot own a priced row.
			if ( '' === $value ) {
				return array();
			}

			$rows[ $value ] = $variation;
		}

		return $rows;
	}

	/**
	 * Readable name of an attribute option.
	 *
	 * @param WC_Product_Variable $product   Parent product.
	 * @param string              $attribute Attribute name.
	 * @param string              $option    Option value.
	 * @return string
	 */
	private static function option_label( $product, $attribute, $option ) {
		if ( taxonomy_exists( $attribute ) ) {
			$term = get_term_by( 'slug', $option, $attribute );

			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		return apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product );
	}
}
