<?php
/**
 * Installs the product page as an Elementor Pro Theme Builder template.
 *
 * The card and the archive are built element by element in PHP; this one is imported from the
 * JSON export bundled beside it, on purpose. The page has roughly a hundred controls across two
 * dozen elements, and hand-transcribing them into a second copy would create exactly the drift
 * this codebase avoids everywhere else: `elementor-template/es-nvm-product-single.php` builds the
 * page, its export is the artefact, and this reads that artefact. One source, one shape.
 *
 * What the export cannot carry is the palette. Elementor bakes the literal hex of whatever kit
 * the template was built against, so importing it into a site with a different brand brings this
 * one's colours along — the limitation the README has always documented. So the colours are
 * rewritten to the plugin's own `var(--nvm-x)` tokens as the JSON is read, which makes the page
 * follow the palette in Ajustes on any site, exactly like the card and the side cart.
 *
 * @package NovaMira\VariationRows
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product page template installer.
 */
class NVM_VR_Product_Builder {

	/**
	 * Slug of the template post, so a rerun rewrites it instead of creating a second one.
	 */
	const SLUG = 'nvm-ficha-producto';

	/**
	 * Bundled export, relative to the plugin root.
	 */
	const SOURCE = 'assets/templates/nvm-ficha-producto.json';

	/**
	 * Baked colour to token.
	 *
	 * Only the values that HAVE a token are listed. White is white on every brand, and the two
	 * one-off tints (the chat banner's pale green, the divider grey) have no equivalent — mapping
	 * them to an approximate token would change the design to gain nothing.
	 *
	 * @return array<string,string>
	 */
	private static function palette() {
		return array(
			'#15181A' => 'var(--nvm-ink)',
			'#6A6F6C' => 'var(--nvm-ink-muted)',
			'#0FA968' => 'var(--nvm-accent)',
			'#F4F5F3' => 'var(--nvm-surface)',
			'#E5E7E5' => 'var(--nvm-line)',
		);
	}

	/**
	 * Rewrite every baked colour in the tree to its token.
	 *
	 * Substring, not whole-value. A colour control holds the hex on its own, but roughly a third
	 * of this template's colours live inside `custom_css` strings — the add-to-cart green, the
	 * tab rule, the review link — and matching only whole values left those behind, still carrying
	 * this site's brand into the next one. `var()` is valid everywhere a hex was, so the same
	 * substitution serves both.
	 *
	 * Values only: a key is a control name and never a colour. Case-insensitive because Elementor
	 * writes `#15181A` while a hand edit in the panel can leave `#15181a`.
	 *
	 * @param mixed $node Element tree or leaf.
	 * @return mixed
	 */
	private static function tokenise( $node ) {
		if ( is_array( $node ) ) {
			foreach ( $node as $key => $value ) {
				$node[ $key ] = self::tokenise( $value );
			}

			return $node;
		}

		if ( ! is_string( $node ) || false === strpos( $node, '#' ) ) {
			return $node;
		}

		$palette = self::palette();

		return str_ireplace( array_keys( $palette ), array_values( $palette ), $node );
	}

	/**
	 * Build the template and return its id.
	 *
	 * @return int|WP_Error Template post id, or an error when the export is unreadable.
	 */
	public static function build() {
		$file = NVM_VR_PATH . self::SOURCE;

		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'nvm_missing_export', __( 'Falta el JSON de la ficha en el plugin.', 'nvm-variation-rows' ) );
		}

		$export = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Plugin's own bundled asset, not a remote read.

		if ( ! is_array( $export ) || empty( $export['content'] ) ) {
			return new WP_Error( 'nvm_bad_export', __( 'El JSON de la ficha no se pudo leer.', 'nvm-variation-rows' ) );
		}

		$elements = self::tokenise( $export['content'] );

		$existing = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'name'           => self::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => 1,
			)
		);

		if ( $existing ) {
			$id = $existing[0]->ID;
			wp_update_post(
				array(
					'ID'          => $id,
					'post_title'  => 'NovaMira - Ficha de producto',
					'post_status' => 'publish',
				)
			);
		} else {
			$id = wp_insert_post(
				array(
					'post_type'   => 'elementor_library',
					'post_title'  => 'NovaMira - Ficha de producto',
					'post_name'   => self::SLUG,
					'post_status' => 'publish',
				)
			);
		}

		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'nvm_insert_failed', __( 'No se pudo crear la plantilla de ficha.', 'nvm-variation-rows' ) );
		}

		// The taxonomy term and the meta have to agree. Elementor lists the template from the
		// term and resolves it from the meta, so setting only one leaves a template that either
		// cannot be found in the library or renders as the wrong type.
		wp_set_object_terms( $id, 'product', 'elementor_library_type' );
		update_post_meta( $id, '_elementor_template_type', 'product' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );

		if ( ! empty( $export['page_settings'] ) ) {
			update_post_meta( $id, '_elementor_page_settings', $export['page_settings'] );
		}

		update_post_meta( $id, '_elementor_conditions', array( 'include/product' ) );

		NVM_VR_Archive_Builder::flush( $id );

		return $id;
	}
}
