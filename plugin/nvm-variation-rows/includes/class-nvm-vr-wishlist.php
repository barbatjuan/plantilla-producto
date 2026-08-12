<?php
/**
 * Wishlist control inside the buy row.
 *
 * The heart belongs between the total and the quantity, and that row is printed by
 * WooCommerce, not by the page builder — a widget dropped next to the form lands under it
 * instead. JetCompareWishlist owns the wishlist behaviour, so its own widget is rendered
 * through the native WooCommerce hook rather than reimplementing its markup or its storage.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the JetCompareWishlist button in the add-to-cart row when that plugin is active.
 */
class NVM_VR_Wishlist {

	const WIDGET = 'jet-wishlist-button';

	/**
	 * Register hooks.
	 *
	 * The provider check happens in the callbacks, not here: this runs on plugins_loaded,
	 * where Elementor has not fired `elementor/loaded` yet, so testing for the widget at
	 * registration time silently skips the feature on every request.
	 */
	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render' ), 7 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
	}

	/**
	 * The provider widget, or null when JetCompareWishlist / Elementor are absent.
	 *
	 * @return \Elementor\Widget_Base|null
	 */
	private static function widget() {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$manager = \Elementor\Plugin::instance()->widgets_manager;

		return $manager ? $manager->get_widget_types( self::WIDGET ) : null;
	}

	/**
	 * Rendering a widget outside the builder's own loop does not pull in its assets,
	 * so its declared dependencies are enqueued explicitly.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( ! NVM_VR_Renderer::is_supported( wc_get_product( get_queried_object_id() ) ) ) {
			return;
		}

		$widget = self::widget();

		if ( ! $widget ) {
			return;
		}

		foreach ( (array) $widget->get_style_depends() as $handle ) {
			wp_enqueue_style( $handle );
		}

		foreach ( (array) $widget->get_script_depends() as $handle ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Print the button between the total and the quantity field.
	 */
	public static function render() {
		global $product;

		if ( ! self::widget() || ! NVM_VR_Renderer::is_supported( $product ) ) {
			return;
		}

		try {
			$element = \Elementor\Plugin::instance()->elements_manager->create_element_instance(
				array(
					'elType'     => 'widget',
					'widgetType' => self::WIDGET,
					'id'         => 'nvmwish',
					'settings'   => array(),
					'elements'   => array(),
				)
			);

			if ( ! $element ) {
				return;
			}

			echo '<div class="nvm-wish">';
			$element->print_element();
			echo '</div>';
		} catch ( \Throwable $e ) {
			// A third-party widget that fails to render must not take the buy box down with it.
			return;
		}
	}
}
