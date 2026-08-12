# Plantilla producto — WooCommerce + Elementor

A pharmacy-style product page: gallery on the left, and a buy box whose variations are
selectable **rows** — each with its own price, discount pill, highlight badge and unit price —
followed by the chat banner, the info cards and the product tabs.

Two pieces, and both are needed. The plugin owns what happens *inside* the buy box; the
Elementor template owns the page around it.

| Piece | What it delivers |
| --- | --- |
| `plugin/nvm-variation-rows` | Variation rows, unit price, discount percentages, the extra discount as a real cart rule, the quantity stepper, the wishlist control |
| `elementor-template/` | The Single Product template: two columns, buy-box card, chat banner, info cards, tabs |
| `mockup/` | The approved static mockup — the visual contract the build is checked against |

## Requirements

- WordPress 6.4+, PHP 7.4+
- WooCommerce 8.0+
- **Elementor Pro** — the Theme Builder and the WooCommerce widgets are Pro-only
- JetCompareWishlist *(optional)* — only for the wishlist heart. Without it the heart is not
  printed and nothing else changes.

## Install

1. **Plugin** — zip the `plugin/nvm-variation-rows` folder and upload it under
   Plugins → Add New → Upload, or drop the folder straight into `wp-content/plugins/`.
2. **Template** — Templates → Theme Builder → Import, and feed it
   `elementor-template/nvm-ficha-producto-template.json`.
3. **Assign the condition** — set the template to `include/product`, or to the categories that
   should use it. Without a condition the template exists and renders nowhere; that is the most
   common reason a correct import appears to do nothing.

## Setting up a product

The rows only make sense on a product they can describe, so:

- The product must be **variable** with **one** variation attribute. With two or more, an option
  no longer maps to a single price, so the plugin steps aside and WooCommerce renders its native
  dropdowns rather than printing a row whose price would be a guess.
- Fill the four per-variation fields the plugin adds under Pricing: package content, unit of
  measurement, highlight label and extra cart discount.
- **Set a default attribute.** Without one WooCommerce loads with no variation selected, the
  total stays hidden and the add-to-cart button stretches to fill the row.

## Colours

The plugin follows the site: every colour is a CSS custom property that falls back to the
Elementor global colour, and each one can be pinned in
WooCommerce → Settings → Products → Variation rows.

**The exported template does not.** Its JSON carries the hex values resolved from the kit it was
built against, so importing it into a site with a different palette brings the old colours along.
Either retune them after importing, or rebuild the template from
`elementor-template/es-nvm-product-single.php`, which reads the destination kit's palette at
build time.

## Repository layout

```
plugin/nvm-variation-rows/     the installable plugin
elementor-template/
  ├── *.json                   importable Theme Builder template
  └── es-nvm-product-single.php  build script (NovaMira / raw PHP), reads the kit palette
mockup/                        approved static mockup, the visual contract
```

## Known gaps

- The stars under the reviews tab label are not native: WooCommerce prints the tab title as plain
  text, so showing a rating there needs a filter on the tab title.
- Container depth in the template is 5, above the 3 this codebase aims for. It comes from the
  buy-box header splitting into two columns.
