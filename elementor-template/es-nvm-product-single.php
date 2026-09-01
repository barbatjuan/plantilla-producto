<?php
/**
 * NovaMira — approved product-page mockup as an Elementor Pro Theme Builder template.
 *
 * Native Elementor / Elementor Pro / WooCommerce widgets only. The variation rows, the total
 * and the unit price come from the nvm-variation-rows plugin, which rewrites the markup the
 * native add-to-cart widget prints — nothing here is pasted HTML.
 *
 * @package NovaMira\VariationRows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Dependencies. Any .php dropped in novamira-sandbox/ executes on upload, so a bare
 * require_once on a missing file fatals before execute-php is ever reached.
 */
foreach ( array( 'es-builder.php', 'es-theme-parts.php' ) as $nvm_dep ) {
	$nvm_dep_path = WP_CONTENT_DIR . '/novamira-sandbox/' . $nvm_dep;
	if ( ! file_exists( $nvm_dep_path ) ) {
		$nvm_msg = 'NovaMira: ' . basename( __FILE__ ) . ' requires ' . $nvm_dep . ' in novamira-sandbox/. NOTHING WAS BUILT.';
		error_log( $nvm_msg );
		echo $nvm_msg . "\n";
		return;
	}
	require_once $nvm_dep_path;
}

/*
 * Local helpers. The es-builder.php already on this server predates es_wide() and the
 * container audit, and overwriting it would touch a live build that loads on every request.
 * These carry their own prefix so nothing can collide.
 */

if ( ! function_exists( 'nvm_wide' ) ) {
	/**
	 * Width on the element itself, so no wrapper container is spent on a percentage.
	 *
	 * Containers and widgets do NOT share this control: a container sizes through `width`,
	 * a widget through `_element_width` + `_element_custom_width`. Feeding a container the
	 * widget keys writes no rule at all, it silently takes the full row and the layout
	 * collapses into one column.
	 */
	function nvm_wide( array $el, $pct, $stacked = 100 ) {
		if ( isset( $el['elType'] ) && 'container' === $el['elType'] ) {
			$el['settings']['width']        = es_size( $pct, '%' );
			$el['settings']['width_tablet'] = es_size( $stacked, '%' );
			$el['settings']['width_mobile'] = es_size( $stacked, '%' );
			return $el;
		}

		$el['settings']['_element_width']               = 'initial';
		$el['settings']['_element_custom_width']        = es_size( $pct, '%' );
		$el['settings']['_element_custom_width_tablet'] = es_size( $stacked, '%' );
		$el['settings']['_element_custom_width_mobile'] = es_size( $stacked, '%' );
		return $el;
	}
}

if ( ! function_exists( 'nvm_dynamic' ) ) {
	/** Bind a control to an Elementor dynamic tag. */
	function nvm_dynamic( array $el, $control, $tag ) {
		$el['settings'][ $control ]                 = '';
		$el['settings']['__dynamic__'][ $control ] = '[elementor-tag id="' . es_uid() . '" name="' . $tag . '" settings="%7B%7D"]';
		return $el;
	}
}

if ( ! function_exists( 'nvm_audit' ) ) {
	/** Container/widget/depth count, echoed to stdout: an unread audit is not an audit. */
	function nvm_audit( array $elements, $label = 'plantilla' ) {
		$stats = array(
			'containers' => 0,
			'widgets'    => 0,
			'max_depth'  => 0,
		);

		$walk = function ( array $nodes, $depth ) use ( &$walk, &$stats ) {
			foreach ( $nodes as $node ) {
				if ( isset( $node['elType'] ) && 'container' === $node['elType'] ) {
					$stats['containers']++;
					$stats['max_depth'] = max( $stats['max_depth'], $depth );
				} else {
					$stats['widgets']++;
				}
				if ( ! empty( $node['elements'] ) ) {
					$walk( $node['elements'], $depth + 1 );
				}
			}
		};

		$walk( $elements, 1 );

		echo sprintf(
			"AUDIT %s: %d contenedores, %d widgets, profundidad max %d\n",
			$label,
			$stats['containers'],
			$stats['widgets'],
			$stats['max_depth']
		);

		return $stats;
	}
}

/**
 * Colour from the site kit palette, so the block follows the brand instead of carrying its own.
 *
 * @param string $id       Global colour id (system: primary/secondary/text/accent, or a custom id).
 * @param string $fallback Used only when the kit has no such entry.
 * @return string
 */
function nvm_color( $id, $fallback ) {
	static $map = null;

	if ( null === $map ) {
		$map      = array();
		$settings = get_post_meta( get_option( 'elementor_active_kit' ), '_elementor_page_settings', true );

		foreach ( array( 'system_colors', 'custom_colors' ) as $group ) {
			if ( empty( $settings[ $group ] ) ) {
				continue;
			}
			foreach ( $settings[ $group ] as $entry ) {
				if ( isset( $entry['_id'], $entry['color'] ) ) {
					$map[ $entry['_id'] ] = $entry['color'];
				}
			}
		}
	}

	return isset( $map[ $id ] ) ? $map[ $id ] : $fallback;
}

/** Bind a colour control to an Elementor global, so kit edits keep flowing through. */
function nvm_global( array $el, $control, $global_id ) {
	$el['settings']['__globals__'][ $control ] = 'globals/colors?id=' . $global_id;
	return $el;
}

/** Zero padding: nested containers otherwise inherit the kit default and lose the card alignment. */
function nvm_flat( array $settings ) {
	$settings['padding'] = es_box( 0, 0, 0, 0 );
	return $settings;
}

/**
 * Build and save the template.
 *
 * @return int|WP_Error Template post id.
 */
function nvm_build_product_single() {
	es_uid_reset( 'nvmpdp' );

	/* ══════════ Left: gallery ══════════ */

	$gallery = nvm_wide(
		es_w(
			'woocommerce-product-images',
			array(
				'sale_flash_show'      => '',
				'image_border_radius'  => es_box( 12, 12, 12, 12 ),
				'thumbs_border_radius' => es_box( 8, 8, 8, 8 ),
				'spacing'              => es_size( 10 ),
				/* The reference carries no sale roundel, and clearing sale_flash_show
				   does not remove the one WooCommerce prints into the gallery. */
				'custom_css'           => 'selector .onsale{display:none!important;}',
			)
		),
		40
	);

	/* ══════════ Right: buy box card ══════════ */

	$buybox = es_c(
		array(
			'content_width'         => 'full',
			'flex_direction'        => 'column',
			'flex_gap'              => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ),
			'background_background' => 'classic',
			'background_color'      => nvm_color( 'gris', '#F4F5F3' ),
			'border_radius'         => es_box( 12, 12, 12, 12 ),
			'padding'               => es_box( 20, 20, 20, 20 ),
			'padding_mobile'        => es_box( 16, 16, 16, 16 ),
		),
		array(
			/* Head: identity left, reputation right. */
			es_c(
				nvm_flat(
					array(
						'content_width'        => 'full',
						'flex_direction'       => 'row',
						'flex_justify_content' => 'space-between',
						'flex_align_items'     => 'flex-start',
						'flex_gap'             => array( 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16' ),
					)
				),
				array(
					es_c(
						nvm_flat(
							array(
								'content_width'  => 'full',
								'flex_direction' => 'column',
								'flex_gap'       => array( 'unit' => 'px', 'size' => 2, 'column' => '2', 'row' => '2' ),
							)
						),
						array(
							/*
							 * The brand, through the plugin's own shortcode and the widget built to run
							 * one - the same path the archive card takes. It prints the taxonomy term,
							 * or nothing at all when the product has no brand. Never a literal name:
							 * this page is installed on sites that have never heard of the one it was
							 * authored against.
							 */
							es_w(
								'shortcode',
								array(
									'shortcode'  => '[nvm_brand]',
									/* The span, not the wrapper: nvm-archive.css also loads on the product page
									   and sizes .nvm-brand at the 13px the card wants. */
									'custom_css' => 'selector .nvm-brand{display:block;font-size:12px;line-height:1.4;color:' . nvm_color( 'text', '#6A6F6C' ) . ';}',
								)
							),
							/* The widget renders its own `title` control, which defaults to
							   Elementor's placeholder copy — it does not pull the product name
							   on its own. The dynamic tag is what binds it. */
							nvm_dynamic(
								es_w(
									'woocommerce-product-title',
									array(
										'header_size'                 => 'h1',
										'title_color'                 => nvm_color( 'primary', '#15181A' ),
										'typography_typography'       => 'custom',
										'typography_font_size'        => es_size( 17 ),
										'typography_font_size_mobile' => es_size( 16 ),
										'typography_font_weight'      => '700',
										'typography_text_transform'   => 'uppercase',
										'typography_line_height'      => es_size( 1.15, 'em' ),
										'_margin'                     => es_box( 0, 0, 0, 0 ),
									)
								),
								'title',
								'post-title'
							),
							/* The product's short description. Same binding as the title above: the
							   widget has no idea which post it renders on unless a tag tells it. */
							nvm_dynamic(
								es_w(
									'text-editor',
									array(
										'text_color'            => nvm_color( 'text', '#6A6F6C' ),
										'typography_typography' => 'custom',
										'typography_font_size'  => es_size( 12 ),
										'custom_css'            => 'selector p{margin:0;}',
									)
								),
								'editor',
								'post-excerpt'
							),
						),
						true
					),
					es_c(
						nvm_flat(
							array(
								'content_width'    => 'full',
								'flex_direction'   => 'column',
								'flex_align_items' => 'flex-end',
								'flex_gap'         => array( 'unit' => 'px', 'size' => 4, 'column' => '4', 'row' => '4' ),
								'_flex_grow'       => 0,
								'_flex_shrink'     => 0,
							)
						),
						array(
							/*
							 * No brand mark here. This page is shipped inside the plugin and installed
							 * on whichever site runs the installer, while an image widget carries the
							 * attachment id AND the absolute URL of the site it was authored on. The
							 * export then hotlinks that site's logo on every product of the next one.
							 */
							es_w(
								'woocommerce-product-rating',
								array(
									'star_color' => nvm_color( 'primary', '#15181A' ),
									'text_color' => nvm_color( 'text', '#6A6F6C' ),
									'_margin'    => es_box( 0, 0, 0, 0 ),
									'custom_css' => 'selector .woocommerce-product-rating{margin:0;font-size:12px;line-height:1.2;}'
										. 'selector .woocommerce-review-link{color:' . nvm_color( 'text', '#6A6F6C' ) . ';}'
										. 'selector .star-rating{color:' . nvm_color( 'primary', '#15181A' ) . ';}',
								)
							),
							es_w(
								'button',
								array(
									/* The count was mockup copy - a button widget cannot compute one.
									   The anchor is real: the tabs widget at the bottom prints it. */
									'text'                          => 'OPINIONES',
									'link'                          => array( 'url' => '#tab-title-reviews' ),
									'size'                          => 'xs',
									'background_color'              => nvm_color( 'primary', '#15181A' ),
									'button_background_hover_color' => nvm_color( 'primary', '#15181A' ),
									'button_text_color'             => '#FFFFFF',
									'hover_color'                   => '#FFFFFF',
									'border_radius'                 => es_box( 4, 4, 4, 4 ),
									'text_padding'                  => es_box( 4, 8, 4, 8 ),
									'typography_typography'         => 'custom',
									'typography_font_size'          => es_size( 11 ),
									'typography_font_weight'        => '700',
								)
							),
						),
						true
					),
				),
				true
			),

			/*
			 * Price. NVM_VR_Renderer::is_supported() only accepts a variable product with exactly
			 * one attribute, and on those the row list is what carries the price. Everything else -
			 * every simple product, every variable one with two attributes - reached this page with
			 * no price anywhere at all. This widget covers them, and nvm-variation-rows.css hides
			 * it again wherever the rows do render: that stylesheet loads nowhere else.
			 */
			es_w(
				'woocommerce-product-price',
				array(
					'_css_classes' => 'nvm-pdp-price',
					'_margin'      => es_box( 0, 0, 0, 0 ),
					'custom_css'   => 'selector .price{margin:0;display:flex;align-items:baseline;flex-wrap:wrap;gap:8px;font-size:22px;font-weight:700;line-height:1.2;color:' . nvm_color( 'primary', '#15181A' ) . ';}'
						. 'selector .price del{font-size:13px;font-weight:400;color:' . nvm_color( 'text', '#6A6F6C' ) . ';opacity:1;}'
						. 'selector .price ins{text-decoration:none;background:none;color:' . nvm_color( 'primary', '#15181A' ) . ';}',
				)
			),

			/* The plugin turns this native widget into the row selector + total + CTA. */
			es_w(
				'woocommerce-product-add-to-cart',
				array(
					'button_background_color'          => nvm_color( 'secondary', '#0FA968' ),
					'button_hover_background_color'    => nvm_color( 'secondary', '#0FA968' ),
					'button_text_color'                => '#FFFFFF',
					'button_hover_text_color'          => '#FFFFFF',
					'button_border_radius'             => es_box( 8, 8, 8, 8 ),
					'quantity_border_color'            => nvm_color( 'borde', '#E5E7E5' ),
					'button_typography_typography'     => 'custom',
					'button_typography_font_size'      => es_size( 13 ),
					'button_typography_font_weight'    => '700',
					'button_typography_text_transform' => 'uppercase',
					'button_typography_letter_spacing' => es_size( 0.06, 'em' ),
					'_margin'                          => es_box( 0, 0, 0, 0 ),
					/* The theme button colour otherwise wins the specificity war. */
					'custom_css'                       => 'selector .single_add_to_cart_button{background-color:' . nvm_color( 'secondary', '#0FA968' ) . '!important;border-color:' . nvm_color( 'secondary', '#0FA968' ) . '!important;color:#fff!important;border-radius:8px!important;min-height:48px;transition:filter .25s cubic-bezier(.22,1,.36,1),transform .25s cubic-bezier(.22,1,.36,1)!important;}'
						. 'selector .single_add_to_cart_button:hover{filter:brightness(1.08);transform:translateY(-1px);}'
						. 'selector .quantity input{height:44px;}',
					/*
					 * The variation price and the reset link are hidden from
					 * nvm-variation-rows.css rather than from here. That stylesheet loads only on
					 * pages where the plugin actually renders rows, while these two rules baked
					 * into the template also stripped the price and the reset link from every
					 * variable product the renderer does not support - which has neither a row
					 * list nor a total to put in their place.
					 */
				)
			),
		),
		true
	);

	/* ══════════ Right: chat banner ══════════ */

	$chatbar = es_c(
		array(
			'content_width'             => 'full',
			'flex_direction'            => 'row',
			'flex_align_items'          => 'center',
			'flex_gap'                  => array( 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '12' ),
			'background_background'     => 'gradient',
			'background_color'          => '#E4F3EC',
			'background_color_b'        => nvm_color( 'gris', '#F4F5F3' ),
			'background_gradient_angle' => es_size( 90, 'deg' ),
			'border_radius'             => es_box( 12, 12, 12, 12 ),
			'padding'                   => es_box( 12, 20, 12, 20 ),
		),
		array(
			es_w(
				'icon',
				array(
					'selected_icon'   => array( 'value' => 'far fa-comment-dots', 'library' => 'fa-regular' ),
					'view'            => 'stacked',
					'shape'           => 'circle',
					'primary_color'   => '#FFFFFF',
					'secondary_color' => nvm_color( 'secondary', '#0FA968' ),
					'size'            => es_size( 18 ),
					'_flex_grow'      => 0,
					'_flex_shrink'    => 0,
				)
			),
			nvm_wide(
				es_w(
					'text-editor',
					array(
						'editor'                => '<p>¿Quieres más información de este producto? <strong>Nuestro chat te responde</strong></p>',
						'text_color'            => nvm_color( 'primary', '#15181A' ),
						'typography_typography' => 'custom',
						'typography_font_size'  => es_size( 12 ),
						'custom_css'            => 'selector p{margin:0;}',
					)
				),
				80
			),
			/* Round affordance at the far right of the banner, as in the reference. */
			es_w(
				'button',
				array(
					'text'                          => '',
					'selected_icon'                 => array( 'value' => 'fas fa-arrow-down', 'library' => 'fa-solid' ),
					'link'                          => array( 'url' => '#' ),
					'size'                          => 'xs',
					'background_color'              => '#FFFFFF',
					'button_background_hover_color' => '#FFFFFF',
					'button_text_color'             => nvm_color( 'primary', '#15181A' ),
					'hover_color'                   => nvm_color( 'secondary', '#0FA968' ),
					'border_radius'                 => es_box( 50, 50, 50, 50, '%' ),
					'text_padding'                  => es_box( 0, 0, 0, 0 ),
					'_flex_grow'                    => 0,
					'_flex_shrink'                  => 0,
					'custom_css'                    => 'selector .elementor-button{width:32px;height:32px;display:grid;place-items:center;border-radius:50%;}'
						. 'selector .elementor-button-content-wrapper{margin:0;}'
						. 'selector .elementor-button-icon{margin:0;font-size:11px;transform:rotate(-45deg);}',
				)
			),
		),
		true
	);

	/* ══════════ Right: info cards ══════════ */

	/*
	 * One widget per card. The round affordance on the right is a pseudo-element rather than a
	 * second widget: a card that is two widgets becomes a container, and this template is served
	 * on every product of the catalogue, so each extra level is paid site-wide.
	 *
	 * $lead decides which line carries the weight: "PACKS OFERTA" leads with its label, while the
	 * delivery card leads with the small "Entrega prevista" and gives the estimate the emphasis.
	 */
	$card = function ( $title, $description, $icon, $symbol, $lead = 'title' ) {
		$strong = 'font-size:12px;font-weight:700;color:' . nvm_color( 'primary', '#15181A' ) . ';';
		$quiet  = 'font-size:11px;font-weight:400;color:' . nvm_color( 'text', '#6A6F6C' ) . ';';

		$css = 'selector .elementor-icon-box-wrapper{display:flex;align-items:center;gap:14px;text-align:left;}'
			. 'selector .elementor-icon-box-icon{margin:0;flex:0 0 auto;}'
			. 'selector .elementor-icon-box-content{flex:1 1 auto;}'
			. 'selector .elementor-icon-box-title{margin:0;' . ( 'title' === $lead ? $strong : $quiet ) . '}'
			. 'selector .elementor-icon-box-description{margin:0;' . ( 'title' === $lead ? $quiet : $strong ) . '}'
			/* Round affordance, mirroring the reference without spending a widget on it. */
			. 'selector .elementor-icon-box-wrapper::after{content:"' . $symbol . '";flex:0 0 auto;'
			. 'display:grid;place-items:center;width:28px;height:28px;border-radius:50%;'
			. 'background:#fff;color:' . nvm_color( 'text', '#6A6F6C' ) . ';font-size:12px;line-height:1;}';

		return es_w(
			'icon-box',
			array(
				'title_text'             => $title,
				'description_text'       => $description,
				'selected_icon'          => array( 'value' => $icon, 'library' => 'fa-solid' ),
				'position'               => 'left',
				'view'                   => 'stacked',
				'shape'                  => 'circle',
				'primary_color'          => '#FFFFFF',
				'secondary_color'        => nvm_color( 'primary', '#15181A' ),
				'icon_size'              => es_size( 13 ),
				'_background_background' => 'classic',
				'_background_color'      => nvm_color( 'gris', '#F4F5F3' ),
				'_border_radius'         => es_box( 12, 12, 12, 12 ),
				'_padding'               => es_box( 12, 16, 12, 16 ),
				'custom_css'             => $css,
			)
		);
	};

	$infocards = es_grid(
		2,
		array(
			$card( 'PACKS OFERTA', 'Ver productos comprados juntos habitualmente', 'fas fa-link', '↓' ),
			/* No baked date. The export is installed months after it is authored, on sites with
			   their own carriers, so a fixed date is wrong on arrival and worse every day after. */
			$card( 'Entrega prevista', 'Calculada en el carrito', 'fas fa-truck', '+', 'description' ),
		),
		16,
		nvm_flat(
			array(
				'content_width'             => 'full',
				'grid_columns_grid_mobile'  => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
			)
		)
	);

	/*
	 * The banner and the cards belong to the buy column, not to the page: in the reference
	 * they hang under the buy box and stop at its width, leaving the gallery column empty.
	 */
	$buy_column = nvm_wide(
		es_c(
			nvm_flat(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array( 'unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12' ),
				)
			),
			array( $buybox, $chatbar, $infocards ),
			true
		),
		56
	);

	$el = array();

	/*
	 * 40% + 56% + a 32px gap fits inside the row; percentages that sum near 100 alongside a
	 * pixel gap overflow and wrap, which reads as a broken one-column layout. Wrapping is
	 * therefore enabled only from tablet down, where both halves are 100% by design.
	 */
	$el[] = es_c(
		array(
			'content_width'    => 'boxed',
			'flex_direction'   => 'row',
			'flex_wrap'        => 'nowrap',
			'flex_wrap_tablet' => 'wrap',
			'flex_wrap_mobile' => 'wrap',
			'flex_align_items' => 'flex-start',
			'flex_gap'         => array( 'unit' => 'px', 'size' => 32, 'column' => '32', 'row' => '32' ),
			'padding'          => es_box( 32, 24, 16, 24 ),
			'padding_mobile'   => es_box( 20, 16, 12, 16 ),
		),
		array( $gallery, $buy_column )
	);

	/* ══════════ Product tabs (full width, centred) ══════════ */

	$el[] = es_c(
		array(
			'content_width'  => 'boxed',
			'flex_direction' => 'column',
			'padding'        => es_box( 40, 24, 40, 24 ),
			'padding_mobile' => es_box( 28, 16, 28, 16 ),
		),
		array(
			es_w(
				'woocommerce-product-data-tabs',
				array(
					'tabs_title_color'                     => nvm_color( 'text', '#6A6F6C' ),
					'tabs_title_color_active'              => nvm_color( 'primary', '#15181A' ),
					'tabs_title_typography_typography'     => 'custom',
					'tabs_title_typography_font_size'      => es_size( 13 ),
					'tabs_title_typography_font_weight'    => '600',
					'tabs_title_typography_text_transform' => 'uppercase',
					'tabs_title_typography_letter_spacing' => es_size( 0.06, 'em' ),
					'border_color'                         => nvm_color( 'borde', '#E5E7E5' ),
					'content_color'                        => nvm_color( 'primary', '#15181A' ),
					'content_typography_typography'        => 'custom',
					'content_typography_font_size'         => es_size( 14 ),
					'content_typography_line_height'       => es_size( 1.7, 'em' ),
					/* Centre the tab row and mark the active one with the accent, per the mockup. */
					'custom_css'                           => 'selector .woocommerce-tabs ul.tabs{display:flex;justify-content:center;flex-wrap:wrap;gap:32px;padding:0;margin:0 0 24px;border-bottom:1px solid ' . nvm_color( 'borde', '#E5E7E5' ) . ';}'
						. 'selector .woocommerce-tabs ul.tabs li{margin:0;padding:0;background:none;border:0;border-bottom:2px solid transparent;}'
						. 'selector .woocommerce-tabs ul.tabs li::before,selector .woocommerce-tabs ul.tabs li::after{display:none;}'
						. 'selector .woocommerce-tabs ul.tabs li.active{border-bottom-color:' . nvm_color( 'secondary', '#0FA968' ) . ';}'
						. 'selector .woocommerce-tabs ul.tabs li a{padding:0 0 8px;display:block;}',
				)
			),
		)
	);

	nvm_audit( $el, 'ficha producto' );

	return es_save_theme_part(
		'nvm-ficha-producto',
		'NovaMira - Ficha producto (maqueta)',
		'product',
		$el,
		array( 'include/product' )
	);
}
