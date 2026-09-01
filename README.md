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
| `plugin/nvm-variation-rows` | Variation rows, unit price, discount percentages, the extra discount as a real cart rule, the quantity stepper, the wishlist control — and the card's brand, badges, rating count and discount pill |
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
   Plugins → Add New → Upload, or drop `plugin/nvm-variation-rows` straight into
   `wp-content/plugins/`. The zip is a build artifact, not a tracked file: see **The zip at the
   root** below for what keeps it current.
2. **Template** — Templates → Theme Builder → Import, and feed it
   `elementor-template/nvm-ficha-producto-template.json`.
3. **Assign the condition** — set the template to `include/product`, or to the categories that
   should use it. Without a condition the template exists and renders nowhere; that is the most
   common reason a correct import appears to do nothing.

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
nvm-variation-rows-*.zip       the installable build, rebuilt by the hooks (gitignored)
plugin/nvm-variation-rows/     the installable plugin
elementor-template/
  ├── *.json                   importable Theme Builder template
  └── es-nvm-product-single.php  build script (NovaMira / raw PHP), reads the kit palette
mockup/                        approved static mockup, the visual contract
tools/build-plugin.ps1         packages the plugin into the zip at the root
.githooks/                     rebuild the zip on commit, merge and branch checkout
```

## Known gaps

- The stars under the reviews tab label are not native: WooCommerce prints the tab title as plain
  text, so showing a rating there needs a filter on the tab title.
- Container depth in the template is 5, above the 3 this codebase aims for. It comes from the
  buy-box header splitting into two columns.
