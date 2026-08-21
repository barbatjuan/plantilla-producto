<?php
/**
 * Builds the JetWooBuilder product card and the Elementor Pro archive page from code, and
 * exposes the one-click "Reconstruir plantilla del listado" action in
 * Ajustes > Productos > Filas de variación.
 *
 * Adapted from elementor-template/es-nvm-product-archive.php (the standalone script run through
 * the NovaMira connector) so any site that installs this plugin gets the same card without a
 * manual execute-php step — JetWooBuilder and Elementor Pro still have to be installed
 * separately, this only wires the template once they are.
 *
 * Colours are not baked in as literal hex: they are the same `var(--nvm-x)` custom properties
 * the buy-box already uses (see NVM_VR_Settings), so a colour change in Ajustes takes effect on
 * the card immediately, with no rebuild. Only a structural change to the card needs this action
 * run again.
 *
 * Reads nothing from a previous run: calling it twice rewrites the same two templates rather
 * than creating a second pair.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Archive/card template builder and its admin action.
 */
class NVM_VR_Archive_Builder {

	const ACTION = 'nvm_vr_rebuild_archive';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_rebuild' ) );
		add_action( 'woocommerce_admin_field_nvm_rebuild_button', array( __CLASS__, 'render_button' ) );
	}

	/**
	 * Deterministic element ids, so a rebuild produces the same JSON and Elementor's caches can
	 * tell a real change from a rerun.
	 *
	 * @param string $seed  Namespace for this template.
	 * @param bool   $reset Start the counter over.
	 * @return string
	 */
	private static function uid( $seed, $reset = false ) {
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
	private static function box( $t, $r, $b, $l ) {
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
	private static function gap( $v ) {
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
	private static function size( $v, $unit = 'px' ) {
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
	private static function c( $seed, array $settings, array $children ) {
		return array(
			'id'       => self::uid( $seed ),
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
	private static function w( $seed, $type, array $settings = array() ) {
		return array(
			'id'         => self::uid( $seed ),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * The product card, as a JetWooBuilder "Archive Item" template.
	 *
	 * Type, colour and star sizing are set as widget controls rather than in the stylesheet.
	 * Elementor compiles a control to a rule keyed on the element id, which outranks any plain
	 * class selector — writing them in CSS means losing. The colour values themselves are the
	 * plugin's own `var(--nvm-x)` custom properties, not literals: Elementor writes a control's
	 * value straight into the compiled CSS with no colour-format validation, so the card follows
	 * the same live-editable tokens as the buy-box instead of a second, disconnected palette.
	 *
	 * @return int Template post id.
	 */
	public static function build_card() {
		$seed = 'nvm-card';
		self::uid( $seed, true );

		$ink   = 'var(--nvm-ink)';
		$muted = 'var(--nvm-ink-muted)';
		$green = 'var(--nvm-accent)';
		$faint = '#C9CCC9';

		$shortcode = function ( $code ) use ( $seed ) {
			return self::w( $seed, 'shortcode', array( 'shortcode' => $code ) );
		};

		$elements = array(
			self::c(
				$seed,
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => self::gap( 0 ),
					'padding'        => self::box( 0, 0, 0, 0 ),
					'margin'         => self::box( 0, 0, 0, 0 ),
					'css_classes'    => 'nvm-card',
				),
				array(
					self::c(
						$seed,
						array(
							'content_width'  => 'full',
							'flex_direction' => 'column',
							'flex_gap'       => self::gap( 0 ),
							'padding'        => self::box( 0, 0, 0, 0 ),
							'margin'         => self::box( 0, 0, 0, 0 ),
							'css_classes'    => 'nvm-card__media',
						),
						array(
							$shortcode( '[nvm_badges discount="no"]' ),
							self::w(
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
					self::c(
						$seed,
						array(
							'content_width'    => 'full',
							'flex_direction'   => 'column',
							'flex_gap'         => self::gap( 2 ),
							'flex_align_items' => 'center',
							'padding'          => self::box( 2, 10, 12, 10 ),
							'margin'           => self::box( 0, 0, 0, 0 ),
							'css_classes'      => 'nvm-card__body',
						),
						array(
							$shortcode( '[nvm_brand]' ),
							self::w(
								$seed,
								'jet-woo-builder-archive-product-title',
								array(
									'is_linked'                           => 'yes',
									'title_html_tag'                      => 'h3',
									'title_trim_type'                     => 'word',
									'title_length'                        => -1,
									'archive_title_typography_typography' => 'custom',
									'archive_title_typography_font_size'  => self::size( 14 ),
									'archive_title_typography_font_weight' => '700',
									'archive_title_typography_line_height' => self::size( 1.35, 'em' ),
									'archive_title_color_normal'           => $ink,
									'archive_title_color_hover'            => $green,
									'archive_title_alignment'              => 'center',
								)
							),
							self::w(
								$seed,
								'jet-woo-builder-archive-cats',
								array(
									'categories_count'         => 1,
									'archive_cats_color'       => $muted,
									'archive_cats_color_hover' => $muted,
								)
							),
							self::c(
								$seed,
								array(
									'content_width'        => 'full',
									'flex_direction'       => 'row',
									'flex_gap'             => self::gap( 5 ),
									'flex_align_items'     => 'center',
									'flex_justify_content' => 'center',
									'padding'              => self::box( 2, 0, 0, 0 ),
									'margin'               => self::box( 0, 0, 0, 0 ),
									'css_classes'          => 'nvm-card__rating',
								),
								array(
									// Empty stars are printed on purpose: an unrated card without the
									// row is one line shorter and knocks its whole row out of line.
									//
									// Jet ships fourteen star sets under names that say nothing —
									// "Rating 1" to "Rating 14". They are the same shapes drawn hollow,
									// half and solid: 1/2/3 are one star, 4/5/6 the next, and so on.
									// Rating 1, the default, is the hollow one, and the widget draws
									// the rated overlay with the SAME glyph — so a four-star product
									// gets four hollow stars in a darker grey, which reads as nothing
									// at card size. Rating 3 is that star filled in, and the rating
									// then reads the way every shopper expects: solid accent up to the
									// score, solid pale grey after it.
									self::w(
										$seed,
										'jet-woo-builder-archive-product-rating',
										array(
											'show_empty_rating'           => 'yes',
											'archive_rating_icon'         => 'jetwoo-front-icon-rating-3',
											'archive_stars_font_size'     => self::size( 13 ),
											'archive_stars_space_between' => self::size( 2 ),
											'stars_archive_color_all'     => $faint,
											'archive_stars_color_rated'   => $green,
											'archive_stars_color_empty'   => $faint,
											'archive_stars_alignment'     => 'center',
										)
									),
									$shortcode( '[nvm_rating_count]' ),
								)
							),
							self::c(
								$seed,
								array(
									'content_width'        => 'full',
									'flex_direction'       => 'row',
									'flex_gap'             => self::gap( 7 ),
									'flex_align_items'     => 'baseline',
									'flex_justify_content' => 'center',
									'flex_wrap'            => 'wrap',
									'padding'              => self::box( 6, 0, 0, 0 ),
									'margin'               => self::box( 0, 0, 0, 0 ),
									'css_classes'          => 'nvm-card__price',
								),
								array(
									self::w(
										$seed,
										'jet-woo-builder-archive-product-price',
										array(
											'archive_price_sale_display_type'      => 'inline-block',
											'archive_price_typography_typography'  => 'custom',
											'archive_price_typography_font_size'   => self::size( 17 ),
											'archive_price_typography_font_weight' => '800',
											'archive_price_color'                  => $ink,
											'archive_price_sale_color'             => $ink,
											'archive_price_sale_size'              => self::size( 17 ),
											'archive_price_sale_weight'            => '800',
											'archive_price_sale_decoration'        => 'none',
											'archive_price_regular_color'          => $muted,
											'archive_price_regular_size'           => self::size( 13 ),
											'archive_price_regular_weight'         => '400',
											'archive_price_regular_decoration'     => 'line-through',
											'archive_price_space_between'          => self::size( 7 ),
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
		self::flush( $id );

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
	public static function build_page() {
		$seed = 'nvm-archive-page';
		self::uid( $seed, true );

		$elements = array(
			self::c(
				$seed,
				array(
					'content_width'  => 'boxed',
					'boxed_width'    => self::size( 1300 ),
					'flex_direction' => 'column',
					'flex_gap'       => self::gap( 18 ),
					'padding'        => self::box( 22, 20, 56, 20 ),
				),
				array(
					self::w( $seed, 'woocommerce-breadcrumb' ),
					// "Archivos:" is a setting on the dynamic tag, not a WordPress filter — Elementor
					// Pro assembles the string itself in Utils::get_page_title( $include_context ).
					self::w(
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
					self::w( $seed, 'woocommerce-archive-description' ),
					self::w(
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
		self::flush( $id );

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
	public static function wire_jet( $card_id ) {
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
	public static function retire_others( $keep_id ) {
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
	public static function flush( $post_id ) {
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
	public static function build_all() {
		$card = self::build_card();
		$page = self::build_page();

		self::wire_jet( $card );
		$retired = self::retire_others( $page );

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

	/**
	 * Whether the dependencies this action needs are active. JetWooBuilder registers the
	 * `jet-woo-builder` post type; there is no public class/constant of its own worth trusting
	 * across versions.
	 *
	 * @return bool
	 */
	private static function dependencies_ready() {
		return class_exists( '\Elementor\Plugin' ) && post_type_exists( 'jet-woo-builder' );
	}

	/**
	 * Handle the button's POST: verify, build, flash a result, redirect back.
	 */
	public static function handle_rebuild() {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'nvm-variation-rows' ) );
		}

		if ( self::dependencies_ready() ) {
			$result = self::build_all();
			set_transient( 'nvm_vr_rebuild_result_' . get_current_user_id(), $result, 60 );
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=wc-settings&tab=products&section=' . NVM_VR_Settings::SECTION )
		);
		exit;
	}

	/**
	 * Render the settings-screen field: a result notice from the last run, if any, then the
	 * button — or a disabled state with what is missing.
	 */
	public static function render_button() {
		$transient_key = 'nvm_vr_rebuild_result_' . get_current_user_id();
		$result        = get_transient( $transient_key );

		if ( $result ) {
			delete_transient( $transient_key );

			echo '<tr><td colspan="2"><div class="notice notice-success inline"><p>';
			printf(
				/* translators: 1: card template id, 2: archive page template id. */
				esc_html__( 'Listo. Tarjeta #%1$d y archivo #%2$d actualizados.', 'nvm-variation-rows' ),
				(int) $result['card'],
				(int) $result['page']
			);

			if ( ! empty( $result['retired'] ) ) {
				$titles = wp_list_pluck( $result['retired'], 'title' );
				echo ' ' . esc_html(
					sprintf(
						/* translators: %s: comma-separated list of template titles. */
						__( 'Se retiró la condición de archivo de: %s (queda guardada, se puede reactivar desde Elementor).', 'nvm-variation-rows' ),
						implode( ', ', $titles )
					)
				);
			}

			echo '</p></div></td></tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( 'Plantilla', 'nvm-variation-rows' ) . '</th><td>';

		if ( ! self::dependencies_ready() ) {
			echo '<p class="description">' . esc_html__( 'Necesita Elementor y JetWooBuilder activos para reconstruir la plantilla.', 'nvm-variation-rows' ) . '</p>';
			submit_button( __( 'Reconstruir tarjeta y archivo', 'nvm-variation-rows' ), 'secondary', 'submit', false, array( 'disabled' => 'disabled' ) );
			echo '</td></tr>';
			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		submit_button( __( 'Reconstruir tarjeta y archivo', 'nvm-variation-rows' ), 'secondary', 'submit', false );
		echo '</form></td></tr>';
	}
}
