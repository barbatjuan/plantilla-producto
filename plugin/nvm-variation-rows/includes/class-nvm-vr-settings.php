<?php
/**
 * Editable colour tokens.
 *
 * Every colour of the rows is a CSS custom property. Left untouched it inherits the Elementor
 * global colour of the site, so the design system stays the single source of truth. Overriding
 * one in WooCommerce > Ajustes > Productos > Filas de variación pins it for this component only.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings section and the inline CSS it produces.
 */
class NVM_VR_Settings {

	const SECTION       = 'nvm_variation_rows';
	const OPTION_PREFIX = 'nvm_vr_token_';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_get_sections_products', array( __CLASS__, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( __CLASS__, 'add_settings' ), 10, 2 );
	}

	/**
	 * Token definitions: CSS custom property, label, and the value used when nothing is set.
	 *
	 * The defaults point at Elementor global colours where a global genuinely maps to the role,
	 * with a literal fallback so the plugin also works on a site without Elementor.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_tokens() {
		$tokens = array(
			'accent'      => array(
				'var'     => '--nvm-accent',
				'label'   => __( 'Acento', 'nvm-variation-rows' ),
				'desc'    => __( 'Radio seleccionado, pill de descuento y nota del descuento extra.', 'nvm-variation-rows' ),
				'default' => 'var(--e-global-color-accent, #e2007a)',
			),
			'ink'         => array(
				'var'     => '--nvm-ink',
				'label'   => __( 'Texto principal', 'nvm-variation-rows' ),
				'desc'    => __( 'Nombre y precio de la variación seleccionada.', 'nvm-variation-rows' ),
				'default' => 'var(--e-global-color-text, #26262b)',
			),
			'ink_muted'   => array(
				'var'     => '--nvm-ink-muted',
				'label'   => __( 'Texto secundario', 'nvm-variation-rows' ),
				'desc'    => __( 'Variaciones no seleccionadas, precio tachado y precio por unidad.', 'nvm-variation-rows' ),
				'default' => '#84848d',
			),
			'line'        => array(
				'var'     => '--nvm-line',
				'label'   => __( 'Separadores', 'nvm-variation-rows' ),
				'desc'    => __( 'Línea entre filas.', 'nvm-variation-rows' ),
				'default' => '#dcdcdf',
			),
			'line_strong' => array(
				'var'     => '--nvm-line-strong',
				'label'   => __( 'Borde del radio', 'nvm-variation-rows' ),
				'desc'    => __( 'Contorno del selector sin marcar.', 'nvm-variation-rows' ),
				'default' => '#b6b6bb',
			),
			'chip'        => array(
				'var'     => '--nvm-chip',
				'label'   => __( 'Fondo de etiqueta', 'nvm-variation-rows' ),
				'desc'    => __( 'Fondo de la etiqueta destacada, por ejemplo 20% extra.', 'nvm-variation-rows' ),
				'default' => 'var(--e-global-color-secondary, #2c2d31)',
			),
			'chip_ink'    => array(
				'var'     => '--nvm-chip-ink',
				'label'   => __( 'Texto de etiqueta', 'nvm-variation-rows' ),
				'desc'    => __( 'Texto sobre la etiqueta destacada y check del radio.', 'nvm-variation-rows' ),
				'default' => '#ffffff',
			),
			'surface'     => array(
				'var'     => '--nvm-surface',
				'label'   => __( 'Fondo de tarjeta', 'nvm-variation-rows' ),
				'desc'    => __( 'Fondo de la tarjeta de producto en el listado, imagen incluida.', 'nvm-variation-rows' ),
				'default' => '#f4f5f3',
			),
			'button_bg'   => array(
				'var'     => '--nvm-button-bg',
				'label'   => __( 'Fondo del botón', 'nvm-variation-rows' ),
				'desc'    => __( 'Botón Añadir al carrito. Vacío hereda el color del tema.', 'nvm-variation-rows' ),
				'default' => '',
			),
			'button_ink'  => array(
				'var'     => '--nvm-button-ink',
				'label'   => __( 'Texto del botón', 'nvm-variation-rows' ),
				'desc'    => __( 'Texto del botón Añadir al carrito.', 'nvm-variation-rows' ),
				'default' => '',
			),
		);

		return apply_filters( 'nvm_vr_css_tokens', $tokens );
	}

	/**
	 * Add the section to the Products settings tab.
	 *
	 * @param array $sections Existing sections.
	 * @return array
	 */
	public static function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'Filas de variación', 'nvm-variation-rows' );

		return $sections;
	}

	/**
	 * Build the settings fields for the section.
	 *
	 * @param array  $settings       Settings of the current section.
	 * @param string $current_section Section being rendered.
	 * @return array
	 */
	public static function add_settings( $settings, $current_section ) {
		if ( self::SECTION !== $current_section ) {
			return $settings;
		}

		$fields = array(
			array(
				'title' => __( 'Colores de las filas de variación', 'nvm-variation-rows' ),
				'type'  => 'title',
				'desc'  => __( 'Deja un campo vacío para heredar el color global del sitio. Rellénalo solo cuando este componente deba salirse del sistema de diseño.', 'nvm-variation-rows' ),
				'id'    => self::OPTION_PREFIX . 'section',
			),
		);

		foreach ( self::get_tokens() as $key => $token ) {
			$fields[] = array(
				'title'    => $token['label'],
				'desc'     => $token['desc'],
				'desc_tip' => true,
				'id'       => self::OPTION_PREFIX . $key,
				'type'     => 'color',
				'default'  => '',
				'css'      => 'width:6em;',
			);
		}

		$fields[] = array(
			'title' => __( 'Reconstruir plantilla del listado', 'nvm-variation-rows' ),
			'type'  => 'title',
			'desc'  => __( 'Regenera la tarjeta y la página de archivo de JetWooBuilder. Solo hace falta después de un cambio de estructura del card, no por cambiar un color: los colores de arriba se leen en vivo.', 'nvm-variation-rows' ),
			'id'    => self::OPTION_PREFIX . 'rebuild_section',
		);

		$fields[] = array(
			'type' => 'nvm_rebuild_button',
			'id'   => self::OPTION_PREFIX . 'rebuild',
		);

		$fields[] = array(
			'type' => 'sectionend',
			'id'   => self::OPTION_PREFIX . 'section',
		);

		return $fields;
	}

	/**
	 * Enqueue the token sheet and the overrides that pin it.
	 *
	 * Both the buy box and the product card depend on this handle, and a single product page
	 * asks for it twice. Adding the inline block on each call would print the overrides twice;
	 * the flag makes the second call the no-op it should be.
	 */
	public static function enqueue_tokens() {
		static $done = false;

		wp_enqueue_style( 'nvm-tokens', NVM_VR_URL . 'assets/css/nvm-tokens.css', array(), NVM_VR_VERSION );

		if ( $done ) {
			return;
		}

		$done   = true;
		$inline = self::get_inline_css();

		if ( '' !== $inline ) {
			wp_add_inline_style( 'nvm-tokens', $inline );
		}
	}

	/**
	 * Inline CSS with the resolved tokens, appended after the token stylesheet.
	 *
	 * @return string Empty when nothing is overridden.
	 */
	public static function get_inline_css() {
		$declarations = array();

		foreach ( self::get_tokens() as $key => $token ) {
			$value = get_option( self::OPTION_PREFIX . $key, '' );

			if ( '' === $value ) {
				continue;
			}

			$declarations[] = sprintf( '%s:%s;', $token['var'], sanitize_text_field( $value ) );
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return sprintf( ':root{%s}', implode( '', $declarations ) );
	}
}
