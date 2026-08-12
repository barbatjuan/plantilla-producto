<?php
/**
 * Builds the shop archive: the JetWooBuilder product card and the Elementor Pro archive page.
 *
 * Run through the NovaMira connector (execute-php, or dropped in wp-content/novamira-sandbox
 * where the sandbox auto-runs it). Everything is wrapped in functions on purpose — top-level
 * logic in a sandbox file executes on upload, before anything has been checked.
 *
 * Reads nothing from a previous run: calling it twice rewrites the same two templates rather
 * than creating a second pair.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic element ids, so a rebuild produces the same JSON and Elementor's caches can
 * tell a real change from a rerun.
 *
 * @param string $seed  Namespace for this template.
 * @param bool   $reset Start the counter over.
 * @return string
 */
function nvm_arch_uid( $seed, $reset = false ) {
	static $counters = array();

	if ( $reset || ! isset( $counters[ $seed ] ) ) {
		$counters[ $seed ] = 0;
	}

	$counters[ $seed ]++;

	return substr( md5( $seed . '#' . $counters[ $seed ] ), 0, 7 );
}

/**
 * Dimensions control value.
 *
 * @param int|float $t Top.
 * @param int|float $r Right.
 * @param int|float $b Bottom.
 * @param int|float $l Left.
 * @return array<string,mixed>
 */
function nvm_arch_box( $t, $r, $b, $l ) {
	return array(
		'unit'     => 'px',
		'top'      => (string) $t,
		'right'    => (string) $r,
		'bottom'   => (string) $b,
		'left'     => (string) $l,
		'isLinked' => false,
	);
}

/**
 * Container gap control value.
 *
 * @param int $v Gap in px.
 * @return array<string,mixed>
 */
function nvm_arch_gap( $v ) {
	return array(
		'unit'     => 'px',
		'size'     => $v,
		'column'   => (string) $v,
		'row'      => (string) $v,
		'isLinked' => true,
	);
}

/**
 * Slider control value.
 *
 * @param int|float $v    Size.
 * @param string    $unit Unit.
 * @return array<string,mixed>
 */
function nvm_arch_size( $v, $unit = 'px' ) {
	return array(
		'unit' => $unit,
		'size' => $v,
	);
}

/**
 * Container element.
 *
 * @param string $seed     Id namespace.
 * @param array  $settings Container settings.
 * @param array  $children Child elements.
 * @return array<string,mixed>
 */
function nvm_arch_c( $seed, array $settings, array $children ) {
	return array(
		'id'       => nvm_arch_uid( $seed ),
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => $settings,
		'elements' => $children,
	);
}

/**
 * Widget element.
 *
 * @param string $seed     Id namespace.
 * @param string $type     Widget type.
 * @param array  $settings Widget settings.
 * @return array<string,mixed>
 */
function nvm_arch_w( $seed, $type, array $settings = array() ) {
	return array(
		'id'         => nvm_arch_uid( $seed ),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
}

/**
 * A colour from the active Elementor kit, so the template follows the destination palette
 * instead of the one it happened to be built against.
 *
 * @param string $id       System colour id: primary, secondary, text, accent.
 * @param string $fallback Value used when the kit has no such colour.
 * @return string
 */
function nvm_arch_color( $id, $fallback ) {
	$settings = get_post_meta( (int) get_option( 'elementor_active_kit' ), '_elementor_page_settings', true );

	if ( ! is_array( $settings ) || empty( $settings['system_colors'] ) ) {
		return $fallback;
	}

	foreach ( $settings['system_colors'] as $color ) {
		if ( isset( $color['_id'] ) && $id === $color['_id'] && ! empty( $color['color'] ) ) {
			return $color['color'];
		}
	}

	return $fallback;
}

/**
 * The product card, as a JetWooBuilder "Archive Item" template.
 *
 * Type, colour and star sizing are set as widget controls rather than in the stylesheet.
 * Elementor compiles a control to a rule keyed on the element id, which outranks any plain
 * class selector — writing them in CSS means losing, and it also means the shop owner cannot
 * retune them from the editor.
 *
 * @return int Template post id.
 */
function nvm_arch_build_card() {
	$seed = 'nvm-card';
	nvm_arch_uid( $seed, true );

	$ink   = nvm_arch_color( 'primary', '#15181A' );
	$muted = nvm_arch_color( 'text', '#6A6F6C' );
	$green = nvm_arch_color( 'secondary', '#0FA968' );
	$faint = '#C9CCC9';

	$shortcode = function ( $code ) use ( $seed ) {
		return nvm_arch_w( $seed, 'shortcode', array( 'shortcode' => $code ) );
	};

	$elements = array(
		nvm_arch_c(
			$seed,
			array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_gap'       => nvm_arch_gap( 0 ),
				'padding'        => nvm_arch_box( 0, 0, 0, 0 ),
				'margin'         => nvm_arch_box( 0, 0, 0, 0 ),
				'css_classes'    => 'nvm-card',
			),
			array(
				nvm_arch_c(
					$seed,
					array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
						'flex_gap'       => nvm_arch_gap( 0 ),
						'padding'        => nvm_arch_box( 0, 0, 0, 0 ),
						'margin'         => nvm_arch_box( 0, 0, 0, 0 ),
						'css_classes'    => 'nvm-card__media',
					),
					array(
						$shortcode( '[nvm_badges discount="no"]' ),
						nvm_arch_w(
							$seed,
							'jet-woo-builder-archive-product-thumbnail',
							array(
								'is_linked'               => 'yes',
								'enable_thumbnail_effect' => '',
								'archive_thumbnail_size'  => 'woocommerce_single',
							)
						),
					)
				),
				nvm_arch_c(
					$seed,
					array(
						'content_width'    => 'full',
						'flex_direction'   => 'column',
						'flex_gap'         => nvm_arch_gap( 2 ),
						'flex_align_items' => 'center',
						'padding'          => nvm_arch_box( 2, 10, 12, 10 ),
						'margin'           => nvm_arch_box( 0, 0, 0, 0 ),
						'css_classes'      => 'nvm-card__body',
					),
					array(
						$shortcode( '[nvm_brand]' ),
						nvm_arch_w(
							$seed,
							'jet-woo-builder-archive-product-title',
							array(
								'is_linked'                            => 'yes',
								'title_html_tag'                       => 'h3',
								'title_trim_type'                      => 'word',
								'title_length'                         => -1,
								'archive_title_typography_typography'   => 'custom',
								'archive_title_typography_font_size'    => nvm_arch_size( 14 ),
								'archive_title_typography_font_weight'  => '700',
								'archive_title_typography_line_height'  => nvm_arch_size( 1.35, 'em' ),
								'archive_title_color_normal'            => $ink,
								'archive_title_color_hover'             => $green,
								'archive_title_alignment'               => 'center',
							)
						),
						nvm_arch_w(
							$seed,
							'jet-woo-builder-archive-cats',
							array(
								'categories_count'        => 1,
								'archive_cats_color'      => $muted,
								'archive_cats_color_hover' => $muted,
							)
						),
						nvm_arch_c(
							$seed,
							array(
								'content_width'        => 'full',
								'flex_direction'       => 'row',
								'flex_gap'             => nvm_arch_gap( 5 ),
								'flex_align_items'     => 'center',
								'flex_justify_content' => 'center',
								'padding'              => nvm_arch_box( 2, 0, 0, 0 ),
								'margin'               => nvm_arch_box( 0, 0, 0, 0 ),
								'css_classes'          => 'nvm-card__rating',
							),
							array(
								// Empty stars are printed on purpose: an unrated card without the
								// row is one line shorter and knocks its whole row out of line.
								nvm_arch_w(
									$seed,
									'jet-woo-builder-archive-product-rating',
									array(
										'show_empty_rating'           => 'yes',
										'archive_stars_font_size'     => nvm_arch_size( 13 ),
										'archive_stars_space_between' => nvm_arch_size( 2 ),
										'stars_archive_color_all'     => $faint,
										'archive_stars_color_rated'   => $muted,
										'archive_stars_color_empty'   => $faint,
										'archive_stars_alignment'     => 'center',
									)
								),
								$shortcode( '[nvm_rating_count]' ),
							)
						),
						nvm_arch_c(
							$seed,
							array(
								'content_width'        => 'full',
								'flex_direction'       => 'row',
								'flex_gap'             => nvm_arch_gap( 7 ),
								'flex_align_items'     => 'baseline',
								'flex_justify_content' => 'center',
								'flex_wrap'            => 'wrap',
								'padding'              => nvm_arch_box( 6, 0, 0, 0 ),
								'margin'               => nvm_arch_box( 0, 0, 0, 0 ),
								'css_classes'          => 'nvm-card__price',
							),
							array(
								nvm_arch_w(
									$seed,
									'jet-woo-builder-archive-product-price',
									array(
										'archive_price_sale_display_type'      => 'inline-block',
										'archive_price_typography_typography'  => 'custom',
										'archive_price_typography_font_size'   => nvm_arch_size( 17 ),
										'archive_price_typography_font_weight' => '800',
										'archive_price_color'                  => $ink,
										'archive_price_sale_color'             => $ink,
										'archive_price_sale_size'              => nvm_arch_size( 17 ),
										'archive_price_sale_weight'            => '800',
										'archive_price_sale_decoration'        => 'none',
										'archive_price_regular_color'          => $muted,
										'archive_price_regular_size'           => nvm_arch_size( 13 ),
										'archive_price_regular_weight'         => '400',
										'archive_price_regular_decoration'     => 'line-through',
										'archive_price_space_between'          => nvm_arch_size( 7 ),
										'archive_price_item_alignment'         => 'center',
									)
								),
								$shortcode( '[nvm_discount]' ),
							)
						),
					)
				),
			)
		),
	);

	$existing = get_posts(
		array(
			'post_type'      => 'jet-woo-builder',
			'name'           => 'nvm-tarjeta-producto',
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	);

	if ( $existing ) {
		$id = $existing[0]->ID;
		wp_update_post(
			array(
				'ID'          => $id,
				'post_title'  => 'NovaMira - Tarjeta de producto',
				'post_status' => 'publish',
			)
		);
	} else {
		$id = wp_insert_post(
			array(
				'post_type'   => 'jet-woo-builder',
				'post_title'  => 'NovaMira - Tarjeta de producto',
				'post_name'   => 'nvm-tarjeta-producto',
				'post_status' => 'publish',
			)
		);
	}

	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'jet-woo-builder-archive' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );

	// Written by script, so the macros cache Jet builds on an editor save does not exist.
	delete_post_meta( $id, '_jet_woo_builder_content' );
	nvm_arch_flush( $id );

	return $id;
}

/**
 * The archive page itself.
 *
 * Ordinary Elementor Pro widgets: the card comes from the JetWooBuilder template part filter,
 * not from this template, so nothing here needs to know how a product is drawn.
 *
 * @return int Template post id.
 */
function nvm_arch_build_page() {
	$seed = 'nvm-archive-page';
	nvm_arch_uid( $seed, true );

	$elements = array(
		nvm_arch_c(
			$seed,
			array(
				'content_width'  => 'boxed',
				'boxed_width'    => nvm_arch_size( 1300 ),
				'flex_direction' => 'column',
				'flex_gap'       => nvm_arch_gap( 18 ),
				'padding'        => nvm_arch_box( 22, 20, 56, 20 ),
			),
			array(
				nvm_arch_w( $seed, 'woocommerce-breadcrumb' ),
				// "Archivos:" is a setting on the dynamic tag, not a WordPress filter — Elementor
				// Pro assembles the string itself in Utils::get_page_title( $include_context ).
				nvm_arch_w(
					$seed,
					'theme-archive-title',
					array(
						'header_size' => 'h1',
						'title'       => '',
						'__dynamic__' => array(
							'title' => '[elementor-tag id="nvmarch" name="archive-title" settings="'
								. rawurlencode( wp_json_encode( array( 'include_context' => '' ) ) ) . '"]',
						),
					)
				),
				nvm_arch_w( $seed, 'woocommerce-archive-description' ),
				nvm_arch_w(
					$seed,
					'wc-archive-products',
					array(
						'allow_order'       => 'yes',
						'show_result_count' => 'yes',
						'paginate'          => 'yes',
					)
				),
			)
		),
	);

	$existing = get_posts(
		array(
			'post_type'      => 'elementor_library',
			'name'           => 'nvm-archivo-productos',
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	);

	if ( $existing ) {
		$id = $existing[0]->ID;
		wp_update_post(
			array(
				'ID'          => $id,
				'post_title'  => 'NovaMira - Archivo de productos',
				'post_status' => 'publish',
			)
		);
	} else {
		$id = wp_insert_post(
			array(
				'post_type'   => 'elementor_library',
				'post_title'  => 'NovaMira - Archivo de productos',
				'post_name'   => 'nvm-archivo-productos',
				'post_status' => 'publish',
			)
		);
	}

	wp_set_object_terms( $id, 'product-archive', 'elementor_library_type' );
	update_post_meta( $id, '_elementor_template_type', 'product-archive' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );

	// `include/general` does not cover archives; the shop, the categories and the search results
	// all fall under product_archive.
	update_post_meta( $id, '_elementor_conditions', array( 'include/product_archive' ) );
	nvm_arch_flush( $id );

	return $id;
}

/**
 * Point JetWooBuilder at the card and put it in a render mode a scripted template survives.
 *
 * The `macros` default reads a `_jet_woo_builder_content` meta that only an editor save writes,
 * so a template built this way renders as an empty <li> per product with nothing logged.
 *
 * @param int $card_id Archive item template id.
 */
function nvm_arch_wire_jet( $card_id ) {
	$options = get_option( 'jet_woo_builder', array() );

	$options['custom_archive_page']   = 'yes';
	$options['archive_template']      = (string) $card_id;
	$options['search_template']       = (string) $card_id;
	$options['widgets_render_method'] = 'elementor';

	update_option( 'jet_woo_builder', $options );

	// The archive widget takes its column count from the catalogue setting, not from a control,
	// so the shop, the categories and the search results cannot disagree about it.
	update_option( 'woocommerce_catalog_columns', 5 );
	update_option( 'woocommerce_catalog_rows', 4 );
}

/**
 * Retire another template that claims the archive, keeping its conditions so the swap is
 * reversible without rebuilding anything.
 *
 * @param int $keep_id The template that should own the archive.
 * @return array<int,array<string,mixed>> What was retired.
 */
function nvm_arch_retire_others( $keep_id ) {
	$retired   = array();
	$templates = get_posts(
		array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_elementor_template_type',
			'meta_value'     => 'product-archive',
		)
	);

	foreach ( $templates as $template ) {
		if ( $template->ID === $keep_id ) {
			continue;
		}

		$conditions = get_post_meta( $template->ID, '_elementor_conditions', true );

		if ( empty( $conditions ) ) {
			continue;
		}

		update_post_meta( $template->ID, '_nvm_conditions_backup', $conditions );
		update_post_meta( $template->ID, '_elementor_conditions', array() );

		$retired[] = array(
			'id'         => $template->ID,
			'title'      => $template->post_title,
			'conditions' => $conditions,
		);
	}

	return $retired;
}

/**
 * Drop every cache that would otherwise serve the previous version of a template.
 *
 * @param int $post_id Template id.
 */
function nvm_arch_flush( $post_id ) {
	delete_post_meta( $post_id, '_elementor_css' );
	delete_post_meta( $post_id, '_elementor_element_cache' );

	$file = wp_upload_dir()['basedir'] . '/elementor/css/post-' . $post_id . '.css';

	if ( file_exists( $file ) ) {
		wp_delete_file( $file );
	}
}

/**
 * Build everything and report what landed.
 *
 * @return array<string,mixed>
 */
function nvm_arch_build_all() {
	$card = nvm_arch_build_card();
	$page = nvm_arch_build_page();

	nvm_arch_wire_jet( $card );
	$retired = nvm_arch_retire_others( $page );

	if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();

		// A condition written to post meta is invisible until this cache is rebuilt: the
		// template exists in the library and renders nowhere.
		if ( method_exists( $manager, 'get_cache' ) ) {
			$manager->get_cache()->regenerate();
		} else {
			delete_option( 'elementor_pro_theme_builder_conditions' );
		}
	}

	\Elementor\Plugin::$instance->files_manager->clear_cache();

	return array(
		'card'       => $card,
		'page'       => $page,
		'retired'    => $retired,
		'conditions' => get_option( 'elementor_pro_theme_builder_conditions' )['archive'] ?? array(),
	);
}
