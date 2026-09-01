<?php
/**
 * Bring Elementor Pro's side cart in line with the buy box, on whatever header the site has.
 *
 * This deliberately does NOT build a header. A header carries the client's brand — the logo, the
 * menu, the phone number, their own call to action — and a plugin that overwrote it in order to
 * ship a cart panel would be trading something they own for something we own. Instead this finds
 * the `woocommerce-menu-cart` widgets already in their templates and restyles them in place,
 * leaving every other widget alone.
 *
 * Every value is one of the plugin's own `var(--nvm-x)` tokens rather than a literal hex.
 * Elementor writes a control's value straight into the compiled CSS without validating the colour
 * format, so the panel follows the palette in Ajustes exactly as the buy box does — on any site,
 * with no second copy of the brand to keep in step.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Restyles the menu-cart widgets a site already has.
 */
class NVM_VR_Side_Cart {

	/**
	 * The only widget this touches. Nothing else in a template is read or written.
	 */
	const WIDGET = 'woocommerce-menu-cart';

	/**
	 * Marks our block inside a widget's custom CSS, so a rerun replaces it instead of stacking
	 * another copy underneath the last one.
	 */
	const CSS_OPEN = '/* nvm-side-cart:start */';

	/**
	 * Closing marker for the block above.
	 */
	const CSS_CLOSE = '/* nvm-side-cart:end */';

	/**
	 * Slider control value.
	 *
	 * @param int|float $v    Size.
	 * @param string    $unit Unit.
	 * @return array<string,mixed>
	 */
	private static function size( $v, $unit = 'px' ) {
		return array(
			'unit'  => $unit,
			'size'  => $v,
			'sizes' => array(),
		);
	}

	/**
	 * Dimensions control value.
	 *
	 * @param int|float $t      Top.
	 * @param int|float $r      Right.
	 * @param int|float $b      Bottom.
	 * @param int|float $l      Left.
	 * @param bool      $linked Whether Elementor shows the sides as linked.
	 * @return array<string,mixed>
	 */
	private static function box( $t, $r, $b, $l, $linked = false ) {
		return array(
			'unit'     => 'px',
			'top'      => (string) $t,
			'right'    => (string) $r,
			'bottom'   => (string) $b,
			'left'     => (string) $l,
			'isLinked' => $linked,
		);
	}

	/**
	 * The scoped CSS the widget controls cannot express.
	 *
	 * @return string
	 */
	private static function css() {
		$rules = array(
			// The theme paints the toggle like one of its own buttons.
			'selector .elementor-menu-cart__toggle .elementor-button{border:0!important;background:transparent!important;box-shadow:none!important;}',

			// `__main` is the panel. `__container` is the full-viewport scrim and is ALREADY
			// 100vw, which is the no-op every "make it full screen on mobile" snippet falls into.
			// Elementor hard-codes the panel to 350px with no control, narrow enough to wrap a
			// two-word checkout label onto two lines.
			'selector .elementor-menu-cart__main{width:400px;box-shadow:0 0 40px rgba(0,0,0,.18);}',

			// Subtotal and buttons belong together at the foot of the panel. The native
			// buttons-position control moves only the buttons and strands the subtotal under the
			// last product. ONE auto margin sinks the whole block; two would split the free space
			// between them and reopen the gap.
			'selector .elementor-menu-cart__subtotal{margin-top:auto;}',

			// Darkened from the accent instead of introducing a second green to keep in step.
			'selector .elementor-menu-cart__footer-buttons .elementor-button--checkout:hover{filter:brightness(.92);}',

			'selector .elementor-menu-cart__product-image img{background:var(--nvm-surface,#f4f5f3);border-radius:6px;}',
			'selector .elementor-menu-cart__product-name a{text-decoration:none;}',

			'@media(max-width:767px){selector .elementor-menu-cart__main{width:100%;--cart-padding:22px 18px;}}',
		);

		return self::CSS_OPEN . implode( '', $rules ) . self::CSS_CLOSE;
	}

	/**
	 * Controls merged onto every menu-cart widget found.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings() {
		$ink     = 'var(--nvm-ink)';
		$muted   = 'var(--nvm-ink-muted)';
		$accent  = 'var(--nvm-accent)';
		$line    = 'var(--nvm-line)';
		$surface = 'var(--nvm-surface)';

		return array(
			// Panel.
			'cart_type'                 => 'side-cart',
			'side_cart_alignment'       => 'right',
			'automatically_open_cart'   => 'yes',
			'automatically_update_cart' => 'yes',
			'background_color'          => '#FFFFFF',
			'cart_padding'              => self::box( 26, 24, 26, 24 ),
			'border_type'               => 'none',

			// Close control.
			'close_cart_button_show'      => 'yes',
			'close_cart_icon_size'        => self::size( 18 ),
			'close_cart_icon_color'       => $muted,
			'close_cart_icon_hover_color' => $ink,

			// The line: title, the meta under it, price, quantity.
			'product_title_typography_typography'  => 'custom',
			'product_title_typography_font_size'   => self::size( 14 ),
			'product_title_typography_font_weight' => '600',
			'product_title_typography_line_height' => self::size( 1.35, 'em' ),
			'product_title_color'                  => $ink,
			'product_title_hover_color'            => $accent,

			'product_variations_color'                  => $muted,
			'product_variations_typography_typography'  => 'custom',
			'product_variations_typography_font_size'   => self::size( 12 ),
			'product_variations_typography_font_weight' => '500',
			'product_variations_typography_line_height' => self::size( 1.5, 'em' ),

			'product_price_color'                  => $ink,
			'product_price_typography_typography'  => 'custom',
			'product_price_typography_font_size'   => self::size( 14 ),
			'product_price_typography_font_weight' => '700',

			'product_quantity_color'                  => $muted,
			'product_quantity_typography_typography'  => 'custom',
			'product_quantity_typography_font_size'   => self::size( 13 ),
			'product_quantity_typography_font_weight' => '500',

			// Separators.
			'show_divider'  => 'yes',
			'divider_style' => 'solid',
			'divider_color' => $line,
			'divider_width' => self::size( 1 ),
			'divider_gap'   => self::size( 18 ),

			// Remove control.
			'show_remove_icon'               => 'yes',
			'remove_item_button_position'    => 'middle',
			'remove_item_button_size'        => self::size( 16 ),
			'remove_item_button_color'       => $line,
			'remove_item_button_hover_color' => $ink,

			// Subtotal.
			'subtotal_color'                  => $ink,
			'subtotal_alignment'              => 'left',
			'subtotal_typography_typography'  => 'custom',
			'subtotal_typography_font_size'   => self::size( 18 ),
			'subtotal_typography_font_weight' => '700',
			'subtotal_divider_style'          => 'solid',
			'subtotal_divider_color'          => $line,
			'subtotal_divider_width'          => self::box( 1, 0, 0, 0 ),

			// Buttons. Stacked because the panel is too narrow for two labels side by side, and
			// because one full-width action reads as the thing to press.
			'buttons_layout'                            => 'stacked',
			'space_between_buttons'                     => self::size( 10 ),
			'view_cart_button_show'                     => 'yes',
			'checkout_button_show'                      => 'yes',
			'product_buttons_typography_typography'     => 'custom',
			'product_buttons_typography_font_size'      => self::size( 13 ),
			'product_buttons_typography_font_weight'    => '700',
			'product_buttons_typography_text_transform' => 'uppercase',
			'product_buttons_typography_letter_spacing' => self::size( 0.6 ),

			// Checkout is the one solid action.
			'checkout_button_background_color'   => $accent,
			'checkout_button_text_color'         => '#FFFFFF',
			'checkout_button_hover_background'   => $accent,
			'checkout_button_hover_text_color'   => '#FFFFFF',
			'checkout_border_border'             => 'none',
			'view_checkout_button_border_radius' => self::box( 8, 8, 8, 8, true ),
			'view_checkout_button_padding'       => self::box( 15, 20, 15, 20 ),

			// "View cart" is the quiet one beside it.
			'view_cart_button_background_color'   => 'rgba(0,0,0,0)',
			'view_cart_button_text_color'         => $ink,
			'view_cart_button_hover_text_color'   => $ink,
			'view_cart_button_hover_background'   => $surface,
			'view_cart_border_border'             => 'solid',
			'view_cart_border_width'              => self::box( 1, 1, 1, 1, true ),
			'view_cart_border_color'              => $line,
			'view_cart_button_border_hover_color' => $ink,
			'view_cart_button_border_radius'      => self::box( 8, 8, 8, 8, true ),
			'view_cart_button_padding'            => self::box( 15, 20, 15, 20 ),

			// Empty state.
			'empty_message_color'                      => $muted,
			'empty_message_alignment'                  => 'center',
			'cart_empty_message_typography_typography' => 'custom',
			'cart_empty_message_typography_font_size'  => self::size( 14 ),
		);
	}

	/**
	 * Append our CSS block, replacing a previous one rather than stacking on top of it.
	 *
	 * Whatever the site wrote itself is kept: the markers delimit only what belongs to us.
	 *
	 * @param string $existing Current custom CSS.
	 * @return string
	 */
	private static function merge_css( $existing ) {
		$existing = is_string( $existing ) ? $existing : '';
		$start    = strpos( $existing, self::CSS_OPEN );

		if ( false !== $start ) {
			$end = strpos( $existing, self::CSS_CLOSE, $start );

			if ( false !== $end ) {
				$existing = substr( $existing, 0, $start ) . substr( $existing, $end + strlen( self::CSS_CLOSE ) );
			}
		}

		return trim( $existing ) . self::css();
	}

	/**
	 * Restyle every menu-cart widget in the site's Elementor templates.
	 *
	 * Scoped to `elementor_library` on purpose: that is where a header lives, and it keeps the
	 * pass from walking every page on the site hunting for a widget that does not belong there.
	 *
	 * @return array<int,array<string,mixed>> One entry per template touched.
	 */
	public static function apply_to_all() {
		$touched   = array();
		$settings  = self::settings();
		$templates = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $templates as $template_id ) {
			$raw = get_post_meta( $template_id, '_elementor_data', true );

			if ( ! is_string( $raw ) || false === strpos( $raw, self::WIDGET ) ) {
				continue;
			}

			$data = json_decode( $raw, true );

			if ( ! is_array( $data ) ) {
				continue;
			}

			$count = 0;
			$walk  = function ( &$elements ) use ( &$walk, &$count, $settings ) {
				foreach ( $elements as &$element ) {
					if ( isset( $element['widgetType'] ) && self::WIDGET === $element['widgetType'] ) {
						$current = ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) ? $element['settings'] : array();
						$css     = isset( $current['custom_css'] ) ? $current['custom_css'] : '';

						$element['settings']               = array_merge( $current, $settings );
						$element['settings']['custom_css'] = self::merge_css( $css );

						$count++;
					}

					if ( ! empty( $element['elements'] ) ) {
						$walk( $element['elements'] );
					}
				}
			};
			$walk( $data );

			if ( 0 === $count ) {
				continue;
			}

			// Kept from the FIRST run only, so a second pass cannot overwrite the original with
			// our own output and make the change irreversible.
			if ( ! get_post_meta( $template_id, '_nvm_backup_menu_cart', true ) ) {
				update_post_meta( $template_id, '_nvm_backup_menu_cart', wp_slash( $raw ) );
			}

			update_post_meta( $template_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			NVM_VR_Archive_Builder::flush( $template_id );

			$touched[] = array(
				'id'      => $template_id,
				'title'   => get_the_title( $template_id ),
				'widgets' => $count,
			);
		}

		return $touched;
	}
}
