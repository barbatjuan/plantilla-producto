<?php
/**
 * The pieces the product card needs and WooCommerce does not print: brand, highlight labels
 * and the discount pill.
 *
 * They are shortcodes rather than widgets on purpose. The card is built with Elementor and
 * JetWooBuilder widgets; a shortcode drops into the native Shortcode widget, is editable by
 * hand, and survives a switch of page builder — a custom widget would tie the card's contents
 * to Elementor being installed.
 *
 * Every shortcode reads the global $product, which is what the WooCommerce loop sets for each
 * card, so the same markup renders different data per product without any parameter passing.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Card shortcodes and the stylesheet they need.
 */
class NVM_VR_Archive {

	const BRAND_TAXONOMY = 'product_brand';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_shortcode( 'nvm_badges', array( __CLASS__, 'badges' ) );
		add_shortcode( 'nvm_discount', array( __CLASS__, 'discount' ) );
		add_shortcode( 'nvm_brand', array( __CLASS__, 'brand' ) );
		add_shortcode( 'nvm_rating_count', array( __CLASS__, 'rating_count' ) );

		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_from' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Show a variable product's cheapest price as "Desde 15,60 €" instead of a range.
	 *
	 * A range needs two figures and a dash to say what one figure and one word say, and in a
	 * card three prices deep — struck, current, range — the shopper cannot tell which number
	 * is the one being charged. Only in the loop: on the product page the full range is the
	 * honest answer, because the variation selector is right underneath it.
	 *
	 * @param string     $html    Price markup built by WooCommerce.
	 * @param WC_Product $product Product being priced.
	 * @return string
	 */
	public static function price_from( $html, $product ) {
		if ( ! $product instanceof WC_Product_Variable || ( function_exists( 'is_product' ) && is_product() ) ) {
			return $html;
		}

		$min = $product->get_variation_price( 'min', true );
		$max = $product->get_variation_price( 'max', true );

		if ( '' === $min || $min === $max ) {
			return $html;
		}

		return sprintf(
			'<span class="nvm-price-from">%1$s</span> %2$s',
			esc_html__( 'Desde', 'nvm-variation-rows' ),
			wc_price( $min ) . $product->get_price_suffix( $min )
		);
	}

	/**
	 * Stack of highlight labels, optionally led by the discount.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Empty when the product carries nothing to show.
	 */
	public static function badges( $atts ) {
		$atts = shortcode_atts(
			array(
				'discount' => 'yes',
				'limit'    => 4,
			),
			$atts,
			'nvm_badges'
		);

		$items = array();

		if ( 'yes' === $atts['discount'] ) {
			$data = NVM_VR_Discount::get();

			if ( $data['has_discount'] ) {
				$items[] = array(
					'name' => $data['label'],
					'slug' => 'descuento',
					'bg'   => 'var(--nvm-accent, #e2007a)',
					'fg'   => '#ffffff',
				);
			}
		}

		$items = array_merge( $items, NVM_VR_Badges::get_for() );
		$limit = max( 1, (int) $atts['limit'] );
		$items = array_slice( $items, 0, $limit );

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<ul class="nvm-badges">';

		foreach ( $items as $item ) {
			$html .= sprintf(
				'<li class="nvm-badges__item nvm-badges__item--%1$s" style="--nvm-badge-bg:%2$s;--nvm-badge-fg:%3$s">%4$s</li>',
				esc_attr( $item['slug'] ),
				esc_attr( $item['bg'] ),
				esc_attr( $item['fg'] ),
				esc_html( $item['name'] )
			);
		}

		return $html . '</ul>';
	}

	/**
	 * The discount pill on its own, for cards that place it next to the price.
	 *
	 * @return string Empty when the product is not discounted.
	 */
	public static function discount() {
		$data = NVM_VR_Discount::get();

		if ( ! $data['has_discount'] ) {
			return '';
		}

		return sprintf( '<span class="nvm-pill">%s</span>', esc_html( $data['label'] ) );
	}

	/**
	 * Number of reviews, in the "(35)" form the card prints beside the stars.
	 *
	 * The JetWooBuilder rating widget draws stars and stops there, so five grey stars and
	 * five grey stars backed by two hundred reviews look identical on the card. The count is
	 * the part a shopper actually weighs.
	 *
	 * @return string Empty when the product has no reviews.
	 */
	public static function rating_count() {
		$product = $GLOBALS['product'] ?? null;

		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$count = (int) $product->get_review_count();

		if ( $count < 1 ) {
			return '';
		}

		return sprintf( '<span class="nvm-rating-count">(%d)</span>', $count );
	}

	/**
	 * Brand name, from the WooCommerce brands taxonomy.
	 *
	 * Printed as an empty string rather than a placeholder when the product has no brand: a
	 * card that shows "Sin marca" reads as missing data, while a card without the line simply
	 * reads as a product that does not need one.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function brand( $atts ) {
		$atts = shortcode_atts(
			array(
				'link' => 'no',
			),
			$atts,
			'nvm_brand'
		);

		$product = $GLOBALS['product'] ?? null;

		if ( ! $product instanceof WC_Product || ! taxonomy_exists( self::BRAND_TAXONOMY ) ) {
			return '';
		}

		$terms = get_the_terms( $product->get_id(), self::BRAND_TAXONOMY );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$term = reset( $terms );

		if ( 'yes' !== $atts['link'] ) {
			return sprintf( '<span class="nvm-brand">%s</span>', esc_html( $term->name ) );
		}

		$url = get_term_link( $term );

		if ( is_wp_error( $url ) ) {
			return sprintf( '<span class="nvm-brand">%s</span>', esc_html( $term->name ) );
		}

		return sprintf(
			'<a class="nvm-brand" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $term->name )
		);
	}

	/**
	 * Load the card styles wherever a product loop can appear.
	 *
	 * The check is deliberately wide — shop, category, tag, brand and search all render cards,
	 * and so does any page holding a products widget. Gating it on is_shop() alone leaves the
	 * cards unstyled on exactly the pages a shop links to most.
	 */
	public static function enqueue() {
		if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}

		if ( ! is_woocommerce() && ! is_search() && ! is_page() ) {
			return;
		}

		NVM_VR_Settings::enqueue_tokens();

		wp_enqueue_style(
			'nvm-archive',
			NVM_VR_URL . 'assets/css/nvm-archive.css',
			array( 'nvm-tokens' ),
			NVM_VR_VERSION
		);
	}
}
