# NovaMira Variation Rows for WooCommerce

Renders the variations of a variable product as selectable rows — each with its own price,
crossed-out price, discount pill, highlight badge and unit price — and applies the extra
discount as a real cart rule.

The native WooCommerce variation form stays in charge. The rows do not compute prices, do not
add to cart and do not validate anything: they set the value of the native `<select>` and let
WooCommerce recalculate. That is what makes the plugin small and upgrade-safe.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- WooCommerce 8.0+
- Elementor is optional. It is used only to inherit global colours.

## Install

Copy the `nvm-variation-rows` folder into `wp-content/plugins/` and activate it.

## Setting up a product

The product must be **variable** with **one** attribute driving the variations (format, size,
capacity). Each variation then gets four extra fields in its pricing row:

| Field | What it does |
| --- | --- |
| Contenido del envase | Amount the package contains, e.g. `200` |
| Unidad de medida | `ml`, `l`, `g`, `kg` or units. Drives the unit price: `100 ml / 7,80 €` for ml and g, `1 l / …` for l and kg |
| Etiqueta destacada | Short label next to the variation name, e.g. `20% extra` |
| Descuento extra en cesta (%) | Discounted off the price when the product reaches the cart |

The `-40%` pill needs no field: it is computed from the regular price against the sale price.

## Colours

No colour is hardcoded into a rule. Each one is a CSS custom property resolved in three steps:

1. The override in **WooCommerce → Ajustes → Productos → Filas de variación**.
2. The **Elementor global colour** of the site, so the design system stays the single source
   of truth.
3. A literal fallback, for sites without Elementor.

Leave the settings empty on a site whose palette is already right — the component follows the
brand on its own, and keeps following it when the brand changes.

The form also carries a `nvm-vr-active` class once the script boots, as a styling hook for the
theme or for Elementor custom CSS.

## The extra discount is real

`Descuento extra en cesta` rewrites the cart price through `woocommerce_before_calculate_totals`,
and the cart and checkout show the applied percentage under the item name. The total printed on
the product page is the amount the customer pays.

Never replace this with a number painted on the product page. A total that the cart does not
honour reads as a pricing error and costs the sale.

## Reference price note

The `i` icon next to the crossed-out price shows the lowest price recorded in the last 30 days.
The log starts the day the plugin is installed — there is no history before that. If the store
needs full price-history compliance from day one, that is a dedicated plugin, not this one.

Disable the whole feature with `add_filter( 'nvm_vr_enable_price_history', '__return_false' );`.

## In Elementor

Keep using the native widget on the Single Product template:
`woocommerce-product-add-to-cart`, or JetWooBuilder's Single Add To Cart. The plugin changes the
markup that widget prints — no pasted HTML, no broken template, the client keeps editing the
layout in the editor.

## Filters

| Filter | Purpose |
| --- | --- |
| `nvm_vr_is_supported` | Force rows on or off for a given product |
| `nvm_vr_content_units` | Change the measurement units offered |
| `nvm_vr_reference_amount` | Change the unit-price reference, e.g. per 50 ml instead of 100 |
| `nvm_vr_variation_payload` | Add or rewrite computed values of a variation |
| `nvm_vr_css_tokens` | Add, remove or redefine colour tokens |
| `nvm_vr_enable_price_history` | Turn the 30-day price log off |

## Limitation worth knowing

With **two or more** variation attributes an option no longer maps to a single price, so a priced
row would be a lie. In that case the plugin steps aside and WooCommerce renders its native
dropdowns. Same behaviour when a variation uses "any" as its attribute value.

If a product genuinely needs two attributes and priced rows, the modelling answer is to split the
second attribute into separate products, not to force it here.
