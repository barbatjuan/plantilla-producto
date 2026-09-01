# Plantilla producto — WooCommerce + Elementor

A pharmacy-style product page: gallery on the left, and a buy box whose variations are
selectable **rows** — each with its own price, discount pill, highlight badge and unit price —
followed by the chat banner, the info cards and the product tabs.

Two pieces, and both are needed. The plugin owns what happens *inside* the buy box; the
Elementor template owns the page around it.

And a matching shop archive: a grid of cards with the brand, the highlight labels, the rating
and the struck price, all reading the same numbers as the product page.

| Piece | What it delivers |
| --- | --- |
| `plugin/nvm-variation-rows` | Variation rows, unit price, discount percentages, the extra discount as a real cart rule, the quantity steppers on the product page and in the side cart, the wishlist control — and the card's brand, badges, rating count and discount pill |
| `elementor-template/` | The Single Product template and the Product Archive template |
| `mockup/` | The approved static mockup — the visual contract the build is checked against |

## Requirements

- WordPress 6.4+, PHP 7.4+
- WooCommerce 8.0+
- **Elementor Pro** — the Theme Builder and the WooCommerce widgets are Pro-only
- **JetWooBuilder** — only for the archive card. The product page does not need it.
- JetCompareWishlist *(optional)* — only for the wishlist heart. Without it the heart is not
  printed and nothing else changes.

## Install

1. **Plugin** — upload `nvm-variation-rows-<version>.zip` from the repository root under
   Plugins → Add New → Upload. The zip is a build artifact, not a tracked file: see **The zip at
   the root** below for what keeps it current.
2. **Press the button** — WooCommerce → Ajustes → Productos → Filas de variación →
   **Instalar plantillas NovaMira**.

That is the whole install. The button writes the product page, the archive page and the
JetWooBuilder card, registers their Theme Builder conditions, points JetWooBuilder at the card in
a render mode a scripted template survives, retires any older template still claiming those slots
(keeping its conditions so the swap is reversible), and restyles the side cart in whatever header
the site already has.

Rerunning it is safe: every piece is looked up by slug and rewritten, never duplicated.

**Two things it deliberately does not do.** It never builds or overwrites a header — that carries
the client's logo, menu and calls to action, and replacing all of it to deliver a cart panel would
trade something they own for something we own; only the menu-cart widgets already there are
restyled. And it never touches the palette: every colour it writes is a `var(--nvm-x)` token, so
the storefront follows WooCommerce → Ajustes → Productos → Filas de variación on the new site
rather than arriving in this one's brand.

JetWooBuilder is needed only for the listing card. Without it the button still installs everything
else and says so, instead of refusing to run.

## The zip at the root

`nvm-variation-rows-<version>.zip` sits at the top of the repository, ready to upload. It is
gitignored — a zip is a rebuildable artifact, and committing one adds a binary that changes in
full on every edit and can silently disagree with the source beside it.

```bash
powershell -ExecutionPolicy Bypass -File tools/build-plugin.ps1
```

Three git hooks in `.githooks/` run that for you, at each moment the working tree can start
disagreeing with the zip beside it: `pre-commit`, `post-merge` and `post-checkout`. They are
registered per clone, so **after cloning, run this once**:

```bash
git config core.hooksPath .githooks
```

Three things the build does that a plain `zip -r` does not:

- **It refuses a version mismatch.** The version is read from the plugin header, and the build
  aborts if `NVM_VR_VERSION` disagrees with it. Both are edited by hand, and bumping only one
  ships a plugin whose stylesheets keep the old `?ver=` — the browser then serves the cached CSS
  and a deployed change looks like it never landed.
- **It packages from git, not from the folder.** `git ls-files --cached --others
  --exclude-standard` is the file list, so uncommitted work is included but editor leftovers and
  anything gitignored cannot reach a release.
- **It names the entries itself.** Both `Compress-Archive` and `ZipFile::CreateFromDirectory`
  write them with backslashes on Windows, which the zip format does not permit. Windows unpacks
  such an archive anyway, so it looks fine locally — but PHP reads the names literally and
  WordPress lands eighteen loose files called `nvm-variation-rows\includes\class-….php`, with the
  plugin nowhere in the list. The build checks its own output for this before reporting success.

Only one zip survives a build; the previous one is deleted. "The latest version" is not a claim
worth making next to three older ones.

## Setting up a product

The rows only make sense on a product they can describe, so:

- The product must be **variable** with **one** variation attribute. With two or more, an option
  no longer maps to a single price, so the plugin steps aside and WooCommerce renders its native
  dropdowns rather than printing a row whose price would be a guess.
- Fill the four per-variation fields the plugin adds under Pricing: package content, unit of
  measurement, highlight label and extra cart discount.
- **Set a default attribute.** Without one WooCommerce loads with no variation selected, the
  total stays hidden and the add-to-cart button stretches to fill the row.

## The shop archive

The card is **not** placed by a widget. JetWooBuilder filters the WooCommerce template part
`content-product.php`, so an "Archive Item" template replaces the card in *every* product loop —
the shop, the categories, the search results, and the Elementor archive widget alike. That is why
the archive page template can stay an ordinary `wc-archive-products` widget.

Three things have to line up, and the card renders blank if any of them is missing:

1. An **Archive Item** template — post type `jet-woo-builder`, `_elementor_template_type` set to
   `jet-woo-builder-archive`.
2. WooCommerce → Settings → JetWooBuilder: **Custom archive page** on, pointing at that template.
3. **Widgets render method: `Elementor Default`.** The default, `Macros`, reads a pre-baked
   `_jet_woo_builder_content` meta that only exists once the template has been saved from the
   Elementor editor. A template written by script has no such meta, and Jet renders nothing at
   all — an empty `<li>` per product, with no error anywhere.

### What the plugin adds to the card

| Shortcode | Prints |
| --- | --- |
| `[nvm_badges]` | The highlight labels, each in its own colour. `discount="no"` leaves the percentage out; `limit="4"` caps the stack. |
| `[nvm_discount]` | The discount pill, e.g. `-44%`. Empty when the product is not on sale. |
| `[nvm_brand]` | The brand, from the WooCommerce `product_brand` taxonomy. `link="yes"` links it. |
| `[nvm_rating_count]` | The review count, e.g. `(35)`. The Jet rating widget draws stars and no number. |

All four read the global `$product`, which the loop sets per card — the same markup renders
different data for every product.

**Badges are a taxonomy**, `nvm_badge` ("Etiquetas destacadas"), with a background and a text
colour per term. A product can carry several, and the wording and colours are edited once on the
term rather than on every product.

**The image frame is a catalogue decision.** `--nvm-card-media-ratio` (default `1 / 1`) fixes the
ratio of every card's photo area; the photo is scaled to fit inside it and never cropped. Fixing it
is what keeps a row of cards aligned, but it also means any part of the frame the photo does not
reach becomes empty card. Match it to the catalogue — `4 / 3` for landscape packshots, `3 / 4` for
bottles and boxes — or a portrait frame over landscape photos leaves a band of dead space under
every one of them.

**Stars: use a filled glyph.** JetWooBuilder's rating widget draws the score by stacking a second
row of stars clipped to a percentage, and it draws that row with the *same* glyph as the empty one.
Left on the default `Rating 1`, which is a hollow star, a four-star product shows four hollow stars
in a slightly darker grey — invisible at card size. The sets come in threes (hollow, half, solid) of
the same shape, so `Rating 3` is the filled version of the default star. The card uses it, with the
score in the accent colour and the remainder in pale grey.

**The percentage has one source.** `NVM_VR_Discount` computes it, and both the variation rows and
the card call it. Two implementations would drift the first time the rule changed — rounding, tax
mode, what a variable product's headline price is — and the archive would keep advertising a
discount the product page no longer charged.

## The side cart

The panel is Elementor Pro's `woocommerce-menu-cart` widget in the header, not a template of ours.
Three things about it are worth knowing before touching it again.

**It is styled entirely through CSS custom properties.** Elementor emits every control as a
variable on the widget element — `--checkout-button-background-color`, `--cart-padding`,
`--divider-color` — and its own stylesheet consumes them. So a control left unset does not fall
back to the theme: it falls back to *Elementor's* default. The checkout button was grey because
`--checkout-button-background-color` was never defined and the shipped default is `#69727d`.
Setting the control fixes it; no `!important` is needed, because nothing is competing.

**`__main` is the panel; `__container` is the scrim.** `.elementor-menu-cart__container` is the
full-viewport dark overlay and is already `100vw`, so sizing it does nothing at all — a "make it
full-screen on mobile" rule written against it is a silent no-op. The panel is
`.elementor-menu-cart__main`, and Elementor hard-codes it to `width: 350px` with no control, which
is what made "Finalizar compra" wrap onto two lines.

**Buttons at the foot.** The native *buttons position: bottom* control sets `margin-top: auto` on
the buttons only, which strands the subtotal up under the last product with the gap in between.
The scoped CSS puts that one auto margin on the subtotal instead, so the total and the action sink
together. Two auto margins would split the free space and reopen the gap.

### The quantity stepper

The mini cart is a receipt in WooCommerce: it prints `2 × 25,20 €` as text. Making it editable is
not styling — a new quantity has to re-run the cart, because the line total, the subtotal, the
coupons and this plugin's extra discount are all server-side. So this is the one part of the
storefront that ships JavaScript, and it lives in the plugin (`NVM_VR_Mini_Cart`) behind a nonce
and a cart-key lookup rather than in a widget's custom-code field.

The script is deliberately tiny: it posts the new quantity and asks WooCommerce to repaint via
`wc_fragment_refresh`. It never writes the new figure itself — that would be a second, private
copy of a number only the cart can compute.

**The line shows `line_total`, not `quantity × price`.** The extra discount is a *cart* rule, so
the product still carries its catalogue price; multiplying it printed `25,20 €` on a line the
customer was charged `20,16 €` for, directly above a subtotal that said `20,16 €`.
`line_total` is the figure WooCommerce adds up to reach that subtotal, so the two cannot drift.

**Two traps when verifying a change here.** `wc-cart-fragments` caches the rendered panel in
`sessionStorage` and will keep serving the pre-deploy HTML until the cart changes — trigger
`wc_fragment_refresh` or the fix looks like it never landed. And
`wc_display_cart_prices_including_tax()` **no longer exists in WooCommerce 11**; guarding it with
`function_exists()` fails the worst way available, silently taking the ex-tax branch and showing
`16,66 €` for a `20,16 €` line. Use `WC()->cart->display_prices_including_tax()`.

## Colours

The plugin follows the site: every colour is a CSS custom property that falls back to the
Elementor global colour, and each one can be pinned in
WooCommerce → Settings → Products → Variation rows.

**The raw export does not** — which is why the installer does not use it raw. Elementor bakes the
literal hex of the kit a template was built against, so importing
`elementor-template/nvm-ficha-producto-template.json` by hand through Theme Builder brings this
site's colours along. `NVM_VR_Product_Builder` rewrites them to tokens as it reads the bundled copy
of that same export, which is what makes the product page palette-agnostic.

The substitution is a substring, not a whole value. About a third of the page's colours live inside
`custom_css` strings — the add-to-cart green, the tab rule, the review link — and matching only
whole values left exactly those behind, still carrying one site's brand into the next.

## Repository layout

```
nvm-variation-rows-*.zip       the installable build, rebuilt by the hooks (gitignored)
plugin/nvm-variation-rows/     the installable plugin — and the whole storefront installer
  └── assets/templates/*.json  the product page export the installer reads and tokenises
elementor-template/
  ├── *.json                   the same export, as authored (source for the bundled copy)
  ├── es-nvm-product-single.php  build script for the product page (NovaMira / raw PHP)
  └── es-nvm-product-archive.php build script for the card and archive
mockup/                        approved static mockup, the visual contract
tools/build-plugin.ps1         packages the plugin into the zip at the root
.githooks/                     rebuild the zip on commit, merge and branch checkout
```

The build scripts under `elementor-template/` remain the place the templates are *authored*,
through the NovaMira connector, against a live site. The plugin is how they are *delivered*: the
card and archive are transcribed into `NVM_VR_Archive_Builder`, and the product page is shipped as
the script's own export so there is no second copy of a hundred controls to drift.

## Known gaps

- The stars under the reviews tab label are not native: WooCommerce prints the tab title as plain
  text, so showing a rating there needs a filter on the tab title.
- Container depth in the template is 5, above the 3 this codebase aims for. It comes from the
  buy-box header splitting into two columns.
