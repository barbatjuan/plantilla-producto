<?php
/**
 * Highlight labels shown on the product card: NOVEDAD, OCASIÓN, REGALO…
 *
 * Modelled as a taxonomy rather than a per-product meta field so a product can carry several
 * at once, so the shop owner edits the wording and the colour in one place instead of on every
 * product, and so the archive can be filtered by them later without a new data model.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the taxonomy and its two colour fields.
 */
class NVM_VR_Badges {

	const TAXONOMY = 'nvm_badge';
	const META_BG  = 'nvm_badge_bg';
	const META_FG  = 'nvm_badge_fg';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );

		add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_form_fields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_form_fields' ) );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_fields' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_fields' ) );

		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( __CLASS__, 'add_column' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
	}

	/**
	 * Register the taxonomy on products.
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			'product',
			array(
				'labels'            => array(
					'name'          => __( 'Etiquetas destacadas', 'nvm-variation-rows' ),
					'singular_name' => __( 'Etiqueta destacada', 'nvm-variation-rows' ),
					'menu_name'     => __( 'Etiquetas destacadas', 'nvm-variation-rows' ),
					'add_new_item'  => __( 'Añadir etiqueta destacada', 'nvm-variation-rows' ),
					'edit_item'     => __( 'Editar etiqueta destacada', 'nvm-variation-rows' ),
					'search_items'  => __( 'Buscar etiquetas destacadas', 'nvm-variation-rows' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'show_in_quick_edit' => true,
				'hierarchical'      => false,
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Every badge a product carries, ordered as WordPress returns them.
	 *
	 * @param WC_Product|int|null $product Product, product ID, or null for the global product.
	 * @return array<int,array{name:string,bg:string,fg:string,slug:string}>
	 */
	public static function get_for( $product = null ) {
		$product_id = self::resolve_id( $product );

		if ( ! $product_id ) {
			return array();
		}

		$terms = get_the_terms( $product_id, self::TAXONOMY );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$badges = array();

		foreach ( $terms as $term ) {
			$badges[] = array(
				'name' => $term->name,
				'slug' => $term->slug,
				'bg'   => self::color( $term->term_id, self::META_BG, 'var(--nvm-chip, #2c2d31)' ),
				'fg'   => self::color( $term->term_id, self::META_FG, 'var(--nvm-chip-ink, #ffffff)' ),
			);
		}

		return apply_filters( 'nvm_vr_product_badges', $badges, $product_id );
	}

	/**
	 * Colour stored on a term, or the token that stands in for it.
	 *
	 * @param int    $term_id Term to read.
	 * @param string $key     Meta key.
	 * @param string $default Value used when the term has no colour of its own.
	 * @return string
	 */
	private static function color( $term_id, $key, $default ) {
		$value = get_term_meta( $term_id, $key, true );

		return '' === $value ? $default : $value;
	}

	/**
	 * Fields on the "add term" screen.
	 */
	public static function add_form_fields() {
		?>
		<div class="form-field">
			<label for="<?php echo esc_attr( self::META_BG ); ?>"><?php esc_html_e( 'Color de fondo', 'nvm-variation-rows' ); ?></label>
			<input type="color" name="<?php echo esc_attr( self::META_BG ); ?>" id="<?php echo esc_attr( self::META_BG ); ?>" value="#2c2d31">
			<p><?php esc_html_e( 'Déjalo sin tocar para usar el color de etiqueta del sistema de diseño.', 'nvm-variation-rows' ); ?></p>
		</div>
		<div class="form-field">
			<label for="<?php echo esc_attr( self::META_FG ); ?>"><?php esc_html_e( 'Color del texto', 'nvm-variation-rows' ); ?></label>
			<input type="color" name="<?php echo esc_attr( self::META_FG ); ?>" id="<?php echo esc_attr( self::META_FG ); ?>" value="#ffffff">
		</div>
		<?php
	}

	/**
	 * Fields on the "edit term" screen.
	 *
	 * @param WP_Term $term Term being edited.
	 */
	public static function edit_form_fields( $term ) {
		$bg = get_term_meta( $term->term_id, self::META_BG, true );
		$fg = get_term_meta( $term->term_id, self::META_FG, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( self::META_BG ); ?>"><?php esc_html_e( 'Color de fondo', 'nvm-variation-rows' ); ?></label></th>
			<td><input type="color" name="<?php echo esc_attr( self::META_BG ); ?>" id="<?php echo esc_attr( self::META_BG ); ?>" value="<?php echo esc_attr( '' === $bg ? '#2c2d31' : $bg ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( self::META_FG ); ?>"><?php esc_html_e( 'Color del texto', 'nvm-variation-rows' ); ?></label></th>
			<td><input type="color" name="<?php echo esc_attr( self::META_FG ); ?>" id="<?php echo esc_attr( self::META_FG ); ?>" value="<?php echo esc_attr( '' === $fg ? '#ffffff' : $fg ); ?>"></td>
		</tr>
		<?php
	}

	/**
	 * Persist both colours.
	 *
	 * @param int $term_id Term being saved.
	 */
	public static function save_fields( $term_id ) {
		// The nonce belongs to the term screen WordPress already verified before firing this.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		foreach ( array( self::META_BG, self::META_FG ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}

			$value = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( null === $value ) {
				continue;
			}

			update_term_meta( $term_id, $key, $value );
		}
	}

	/**
	 * Add a colour preview column to the term list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$columns['nvm_badge_preview'] = __( 'Vista previa', 'nvm-variation-rows' );

		return $columns;
	}

	/**
	 * Render the preview column.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column being rendered.
	 * @param int    $term_id Term shown in the row.
	 * @return string
	 */
	public static function render_column( $content, $column, $term_id ) {
		if ( 'nvm_badge_preview' !== $column ) {
			return $content;
		}

		$term = get_term( $term_id, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return $content;
		}

		return sprintf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:%1$s;color:%2$s">%3$s</span>',
			esc_attr( self::color( $term_id, self::META_BG, '#2c2d31' ) ),
			esc_attr( self::color( $term_id, self::META_FG, '#ffffff' ) ),
			esc_html( $term->name )
		);
	}

	/**
	 * Accept a product, an ID, or nothing at all.
	 *
	 * @param WC_Product|int|null $product Candidate.
	 * @return int 0 when nothing resolves.
	 */
	private static function resolve_id( $product ) {
		if ( $product instanceof WC_Product ) {
			return $product->get_id();
		}

		if ( is_numeric( $product ) ) {
			return (int) $product;
		}

		$current = $GLOBALS['product'] ?? null;

		return $current instanceof WC_Product ? $current->get_id() : 0;
	}
}
