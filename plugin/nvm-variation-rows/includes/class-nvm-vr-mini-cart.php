<?php
/**
 * Editable quantity inside the mini cart.
 *
 * WooCommerce prints the mini cart line as dead text — "2 × 25,20 €" — because the mini cart was
 * designed as a receipt, not as a control. Making it editable is not a styling job: changing a
 * quantity has to re-run the cart, because the line total, the subtotal, the coupons and this
 * plugin's own extra-discount rule are all server-side calculations. Nothing in the browser can
 * be trusted to arrive at the same number.
 *
 * That is why this is the one place in the storefront that ships script. It lives in the plugin,
 * behind a nonce and a cart lookup, rather than as a snippet pasted into a widget: the storefront
 * templates must stay reproducible, and code that touches what a customer is charged does not
 * belong in a text field in an admin screen.
 *
 * The line also shows the REAL line total rather than WooCommerce's "quantity × unit price". The
 * unit price there is read before this plugin applies the extra discount, so a line that was
 * charged at 20,16 € advertised itself at 25,20 €. One number, taken from the same cart the
 * customer pays.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Quantity controls on each mini cart line.
 */
class NVM_VR_Mini_Cart {

	/**
	 * Shared by the AJAX action name and its nonce, so the two cannot drift apart.
	 */
	const ACTION = 'nvm_mini_cart_qty';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_widget_cart_item_quantity', array( __CLASS__, 'render' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );

		// Both variants: a shop's cart belongs to logged-out visitors as much as to members, and
		// registering only the privileged one makes the control silently dead for most shoppers.
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Replace the static "2 × 25,20 €" with a stepper and the line total.
	 *
	 * The filter output lands inside Elementor's own `.elementor-menu-cart__product-price`
	 * wrapper, which carries the grid placement — so replacing the inner markup is safe and the
	 * card layout is untouched.
	 *
	 * @param string $html          Default markup.
	 * @param array  $cart_item     Cart line.
	 * @param string $cart_item_key Cart line key.
	 * @return string
	 */
	public static function render( $html, $cart_item, $cart_item_key ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $html;
		}

		// A product sold one at a time has nothing to step through, and offering the control
		// would only promise something the cart is going to refuse.
		if ( $product->is_sold_individually() ) {
			return $html;
		}

		$quantity = (int) $cart_item['quantity'];
		$max      = (int) $product->get_max_purchase_quantity(); // -1 when there is no ceiling.
		$total    = self::line_total( $cart_item, $product, $quantity );
		$at_max   = ( $max > 0 && $quantity >= $max );

		ob_start();
		?>
		<span class="nvm-mc">
			<span
				class="nvm-mc__stepper"
				data-nvm-mc-key="<?php echo esc_attr( $cart_item_key ); ?>"
				data-nvm-mc-max="<?php echo esc_attr( (string) $max ); ?>">
				<button
					type="button"
					class="nvm-mc__btn nvm-mc__btn--minus"
					data-nvm-mc-step="-1"
					aria-label="<?php esc_attr_e( 'Quitar una unidad', 'nvm-variation-rows' ); ?>">&minus;</button>
				<span class="nvm-mc__value" data-nvm-mc-value><?php echo esc_html( (string) $quantity ); ?></span>
				<button
					type="button"
					class="nvm-mc__btn nvm-mc__btn--plus"
					data-nvm-mc-step="1"
					<?php disabled( $at_max ); ?>
					aria-label="<?php esc_attr_e( 'Añadir una unidad', 'nvm-variation-rows' ); ?>">+</button>
			</span>
			<span class="nvm-mc__total"><?php echo wp_kses_post( $total ); ?></span>
		</span>
		<?php
		return ob_get_clean();
	}

	/**
	 * What this line actually costs.
	 *
	 * NOT `get_product_subtotal()`. That multiplies the product's own price, and this plugin's
	 * extra discount is a CART rule — it never touches the catalogue price the product carries.
	 * Reading the product therefore printed 25,20 € on a line the customer was charged 20,16 €
	 * for, with the panel's own subtotal below disagreeing with it.
	 *
	 * `line_total` is the figure WooCommerce adds up to reach that subtotal, so the two cannot
	 * drift apart whatever rule is applied — a coupon, a fee, a future promotion.
	 *
	 * @param array      $cart_item Cart line.
	 * @param WC_Product $product   Line product.
	 * @param int        $quantity  Line quantity.
	 * @return string
	 */
	private static function line_total( $cart_item, $product, $quantity ) {
		// Before calculate_totals() has run there is no line to read, and the product price is
		// the only number available. It is the right one whenever no cart rule applies.
		if ( ! isset( $cart_item['line_total'] ) ) {
			return WC()->cart->get_product_subtotal( $product, $quantity );
		}

		$amount = (float) $cart_item['line_total'];

		// `line_total` is always net. Whether the shopper should see net or gross is the cart's
		// decision, and WC_Cart::display_prices_including_tax() is the one that survives: the
		// wc_display_cart_prices_including_tax() helper is gone in WooCommerce 11, and guarding
		// it with function_exists() failed the worst way available — silently taking the
		// ex-tax branch, so a line charged at 20,16 € displayed 16,66 € next to a 20,16 €
		// subtotal. A missing API should not be able to quietly change a price.
		if ( WC()->cart->display_prices_including_tax() ) {
			$amount += (float) ( isset( $cart_item['line_tax'] ) ? $cart_item['line_tax'] : 0 );
		}

		return wc_price( $amount );
	}

	/**
	 * Apply a new quantity and let WooCommerce recalculate.
	 */
	public static function handle() {
		check_ajax_referer( self::ACTION, 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'El carrito no está disponible.', 'nvm-variation-rows' ) ), 500 );
		}

		$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$quantity = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : -1;
		$item     = '' === $key ? false : WC()->cart->get_cart_item( $key );

		// The key is the authority on what may be changed: it is generated per cart, so a request
		// carrying one that is not in THIS cart cannot reach another shopper's line.
		if ( ! $item || $quantity < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Esa línea ya no está en el carrito.', 'nvm-variation-rows' ) ), 400 );
		}

		// Clamp rather than reject. Stock can fall between the moment the panel was rendered and
		// the moment the button was pressed, and handing the shopper the most they can actually
		// have is more useful than an error about a number they did not choose.
		$product = isset( $item['data'] ) ? $item['data'] : null;
		$max     = $product instanceof WC_Product ? (int) $product->get_max_purchase_quantity() : -1;

		if ( $max > 0 && $quantity > $max ) {
			$quantity = $max;
		}

		// Zero is a removal in WooCommerce's own vocabulary, which is what the minus control on a
		// single unit should do — no separate delete path to keep in step with this one.
		WC()->cart->set_quantity( $key, $quantity, true );

		wp_send_json_success(
			array(
				'quantity' => $quantity,
				'removed'  => 0 === $quantity,
			)
		);
	}

	/**
	 * The mini cart lives in the header, so this loads on the whole front end.
	 */
	public static function enqueue() {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		NVM_VR_Settings::enqueue_tokens();

		wp_enqueue_style(
			'nvm-mini-cart',
			NVM_VR_URL . 'assets/css/nvm-mini-cart.css',
			array( 'nvm-tokens' ),
			NVM_VR_VERSION
		);

		// wc-cart-fragments is a hard dependency, not a convenience: the script asks it to
		// repaint the panel, and without it the quantity would change on the server while the
		// panel kept showing the old one.
		wp_enqueue_script(
			'nvm-mini-cart',
			NVM_VR_URL . 'assets/js/nvm-mini-cart.js',
			array( 'jquery', 'wc-cart-fragments' ),
			NVM_VR_VERSION,
			true
		);

		wp_localize_script(
			'nvm-mini-cart',
			'nvmMiniCart',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::ACTION,
				'nonce'   => wp_create_nonce( self::ACTION ),
			)
		);
	}
}
