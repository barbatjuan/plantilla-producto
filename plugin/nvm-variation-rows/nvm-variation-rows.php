<?php
/**
 * Plugin Name:          NovaMira Variation Rows for WooCommerce
 * Description:          Renders variable product variations as selectable rows with per-variation price, discount badge and unit price, and applies the extra discount as a real cart rule. Also supplies the product card on the archive with its brand, highlight labels and discount pill. The native WooCommerce variation form stays in charge.
 * Version:              1.1.6
 * Author:               Juan Barbat
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          nvm-variation-rows
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * WC requires at least: 8.0
 * WC tested up to:      9.9
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

define( 'NVM_VR_VERSION', '1.1.6' );
define( 'NVM_VR_FILE', __FILE__ );
define( 'NVM_VR_PATH', plugin_dir_path( __FILE__ ) );
define( 'NVM_VR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce High Performance Order Storage.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NVM_VR_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', NVM_VR_FILE, true );
		}
	}
);

/**
 * Load the plugin once WooCommerce is available.
 */
function nvm_vr_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'nvm_vr_missing_woocommerce_notice' );
		return;
	}

	require_once NVM_VR_PATH . 'includes/class-nvm-vr-settings.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-discount.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-badges.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-variation-meta.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-price-history.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-variation-data.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-renderer.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-archive.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-quantity.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-wishlist.php';
	require_once NVM_VR_PATH . 'includes/class-nvm-vr-extra-discount.php';

	NVM_VR_Settings::init();
	NVM_VR_Badges::init();
	NVM_VR_Variation_Meta::init();
	NVM_VR_Price_History::init();
	NVM_VR_Variation_Data::init();
	NVM_VR_Renderer::init();
	NVM_VR_Archive::init();
	NVM_VR_Quantity::init();
	NVM_VR_Wishlist::init();
	NVM_VR_Extra_Discount::init();
}
add_action( 'plugins_loaded', 'nvm_vr_bootstrap' );

/**
 * Admin notice shown when WooCommerce is not active.
 */
function nvm_vr_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'NovaMira Variation Rows necesita WooCommerce activo para funcionar.', 'nvm-variation-rows' );
	echo '</p></div>';
}
