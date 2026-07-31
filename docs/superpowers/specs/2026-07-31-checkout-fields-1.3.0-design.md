# Checkout Fields 1.3.0 — money transparency, multi-file uploads, one icon set

**Status:** approved (design)
**Date:** 2026-07-31
**Version:** 1.3.0 (new features, backward compatible)

## Why

Alovio Product Options shipped three releases this month driven by one real user's
emails. Three of the four gaps they reported exist in Checkout Fields too, and the
fixes are already written and proven in the sibling plugin:

| Gap | Product Options | Checkout Fields today |
| --- | --- | --- |
| A different fee per choice | shipped (2.3.0) | options are plain strings; one fee for the whole field |
| Shopper sees what each option costs | shipped (2.2.0) | every fee collapses into one "Checkout options" line |
| Several files in one upload field | shipped (2.1.0) | one file only |
| WEBP uploads | shipped (2.1.0) | `jpg, jpeg, png, pdf` |

The through-line is money transparency: a shopper adding £8 gift wrapping and a 10%
rush fee currently sees a single opaque "Checkout options £20.00".

## Scope

**In:** per-option fee amounts, itemized fee display (form + totals), multi-file
uploads, WEBP, the drawn icon set, the Alovio flame mark.

**Deliberately out** (candidates for later releases): new field types (swatch, image
swatch, buttons, quantity, surcharge), the `per_char` and `formula` pricing modes,
option groups (a checkout is singular), negative fees/discounts, configurable fee
taxability.

## Architecture

### 1. Fees: one source of truth

Today `FeeCalculator::total()` returns a `float` and `FeesIntegration::add_fee()`
posts it as a single `$cart->add_fee( 'Checkout options', $fee )`. The per-field
split never exists, so nothing downstream can show it.

Add:

```php
FeeCalculator::breakdown( array $group, array $field_values, array $values,
                          float $base = 0.0, ?int $decimals = null ): array
// [ [ 'field_id' => string, 'label' => string, 'amount' => float ], … ]
```

for engaged, active, priced fields. `$decimals` resolves inside the method —
`function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2` — because
`FeeCalculator` is pure logic unit-tested without WooCommerce loaded, and
`wc_get_price_decimals()` is not stubbed in `tests/php/Unit/TestCase.php`. (A function
call also cannot be a PHP parameter default.) Rows whose rounded amount is ≤ 0 are
skipped, so a percent row that rounds to 0.00 never becomes a £0.00 fee line.

`total()` keeps its signature and becomes the sum of the rounded rows. Display and
billing then read the same PHP function — in Product Options display and pricing each
decided money separately and that produced three defects where the page advertised an
amount the cart did not charge. We do not repeat that split. (The JS side mirrors the
logic rather than calling it; §2 explains how the two are kept in step.)

**Why round per row.** Splitting one fee into N does **not** change what WooCommerce
charges: `WC_Cart_Totals::get_fees_from_cart()` stores each fee at 4 decimals beyond
the cent and the grand total is rounded once. The problem is display: the classic checkout renders each fee through
`wc_cart_totals_fee_html()` → `wc_price()` and the Block checkout through the Store
API's `prepare_money_response()`, both rounding each line to 2 dp — so unrounded rows
produce visible lines that don't add up to the total.
Rounding each row and summing the rounded rows makes the printed lines, the printed
total and the charged total agree by construction.

**Emitting the fees.** `FeesIntegration::add_fee()` iterates the breakdown and uses the
fees API rather than the `WC_Cart::add_fee()` shortcut:

```php
$cart->fees_api()->add_fee( [
    'id'        => 'clcf-' . $field_id,  // unique per field, never derived from the label
    'name'      => $row['label'],        // human text, may legitimately repeat
    'amount'    => $row['amount'],
    'taxable'   => false,                // preserves today's behaviour — see below
    'tax_class' => '',
] );
```

This is the **fee-name collision fix**. `WC_Cart::add_fee()` derives the id from the
name (`sanitize_title`) and discards the return value, while
`WC_Cart_Fees::add_fee()` returns `WP_Error( 'fee_exists' )` for a repeated id — so two
fields labelled "Insurance" lose the second fee **silently** and the shop loses that
money. `WC_Cart_Fees::add_fee()` only generates an id when the caller supplies none, so
an explicit id removes the collision while labels stay human. The builder also warns about two fields sharing a
label. (Product Options warns about duplicate *option* labels inside one field, so this
is new work rather than a port.)

**Tax:** WooCommerce's fee default is `taxable = false`
(`WC_Cart_Fees::$default_fee_props`), so Checkout Fields fees are non-taxable today.
The new code passes `'taxable' => false` **explicitly** to preserve that exactly.
Changing it would start charging tax on fees on every live store.

**Two emission modes, chosen deterministically.**

- **Itemized (default):** one fee per breakdown row.
- **Single line:** the pre-1.3.0 behaviour — one fee named by `clcf_fee_label`,
  carrying the `total()` value.

Single line is used when either is true:
1. `apply_filters( 'clcf_itemized_fees', true )` returns `false` — the documented way
   back for a shop that prefers a compact totals block; or
2. `has_filter( 'clcf_fee_total' )` is true — a site is rewriting the charged total,
   and itemized rows cannot honestly represent a total that was adjusted outside them.

The `has_filter()` test is deliberate: comparing the filtered total against the raw sum
would need a float epsilon and would misfire on benign filters that merely re-cast the
value. `clcf_fee_total` therefore keeps its current meaning — it wraps the charged
total — and a site using it keeps being charged exactly what it asks for. In itemized
mode the filter is not consulted, because it is not reachable: the mode only runs when
no filter is attached.

The single-line fee keeps the id `clcf-checkout-options`.

**Row labels.** A breakdown row's `label` is the field label, plus the chosen value for
choice fields — "Shipping speed: Overnight", "Gift wrapping". The value carries the
information the shopper needs at the moment they are reading the totals; the bare field
label would leave "Shipping speed £12.00" ambiguous.

**Orders and emails** need no extra work: each fee becomes its own order fee item on
both checkouts — classic via `WC_Checkout::create_order_fee_lines()`, Store API via
`OrderController` — so the admin order screen, the customer email and the
order-received page itemize automatically.

### 2. Per-option fee amounts

Options keep the leanest shape that carries their data: a bare string until an option
needs a price, then `{ label, price }`. Groups saved by earlier versions normalize
byte-identically, so **no migration is required**.

Every reader goes through a new `FieldOptions` helper — `label()`, `price()`,
`labels()`, `price_for_value()`, `effective_price()`, `has_priced_options()`,
`price_range()` — rather than touching raw option values. An option's own price
replaces the field price and still runs through the field's price mode, so
"10% Express / 20% Overnight" works like flat amounts.

**Nine places read options today and every one assumes a string.** Missing any of them
ships a broken store, so this list is exhaustive and each row is part of the work:

| File | Today | Breaks as |
| --- | --- | --- |
| `Fields/FieldSchema.php:202` | `normalize_options()` returns `sanitize_text_field( … $o['label'] … )` | **prices are flattened away before they are ever stored — the whole feature becomes a silent no-op** |
| `Checkout/Sanitizer.php:72` | `array_map( 'strval', … )` | value silently dropped — **order cannot be placed** |
| `Checkout/Validator.php:94` | `array_map( 'strval', … )` | "has an invalid selection" on every priced choice |
| `Checkout/ClassicRenderer.php:169,175` | `esc_html( $opt )` | prints `Array` |
| `src/block-checkout/index.js:77,83` | `{ o }`, `key={ o }` | React "Objects are not valid as a child" |
| `src/builder/FieldPreview.jsx:20,25` | `{ o }`, `key={ o }`, `options[0]` | canvas crash |
| `src/builder/panels/Logic.jsx:92` | `{ label: o, value: o }` | **writes an object as a rule value** — conditional logic breaks |
| `src/builder/panels/General.jsx:20` | `DefaultControl` map | default-selection list renders `[object Object]` |
| `src/builder/panels/General.jsx:75` | the options textarea (`join('\n')` / newline split) | rewrites every option back to a bare string, erasing prices — replaced by the Options panel below |

The first row is the critical one. `FieldRepository` normalizes on **both** read and
write, so `normalize_options()` is the gate every option passes through; today it
returns bare strings unconditionally. It is rewritten the way the sibling's was
(`woo-product-options/includes/Fields/FieldSchema.php:218-255`): keep a bare string
when a choice carries nothing extra, emit `{ label, price }` once it has a positive
numeric price, and drop non-numeric or ≤ 0 prices. The sibling's `swatch` and
`image_swatch` branches are dropped on port — those types are out of scope here. That
rewrite also drops
empty-label options, which Checkout Fields currently keeps — a deliberate behaviour
change (an unlabelled choice is unselectable) that the changelog calls out.

The REST layer needs no change: the `fields` arg is declared as a bare
`'type' => 'array'` (`Admin/RestController.php:41-44`) with no per-item schema, so
nested option objects survive the round-trip.

Options are keyed by position wherever React lists them, so a duplicate label cannot
produce a duplicate key.

**Three gates must learn about priced options — otherwise the feature is a no-op even
after the above.** On the server, `FeeCalculator.php:42` skips a field with
`if ( $price <= 0 ) { continue; }`; `breakdown()` replaces that with the field's
effective price so a priced option lifts a zero-priced field. On the client,
`src/frontend.js:155` and `src/block-checkout/index.js:21`
both decide "does this checkout have fees?" with
`fields.some( f => parseFloat( f.price ) > 0 )`. A field whose own price is 0 but whose
options are priced fails that test, so the checkout never pushes the cart update that
writes `clcf_fee_fields` into the session — and `FeesIntegration::add_fee()` reads that
session at order creation. Result: the fee is never captured and never charged. Both
gates become `parseFloat( f.price ) > 0 || hasPricedOptions( f )`.

**Editing.** Options live in a `General`-tab textarea today (`options.join('\n')`),
which has nowhere to put a price. Port the Product Options `Options` panel — one row
per choice with a label and a price box — as a new **Options** tab shown only for
option-bearing types, sitting between General and Logic. Checkout Fields' tabs are
General / Logic / Layout / Fee; there is no Pricing tab, so the ported panel's help
text must point at **Fee** instead. The textarea moves out of General.

**Display.** Prices render beside each choice ("Express (+£10.00)") on both checkouts,
and the field's pill summarises the span. An option with no price of its own displays
the field price it falls back to, so what a shopper reads is always what the cart
charges.

**Parity.** Fee logic lives in three places: PHP calculation, PHP classic render, JS
block render. Lock them with a shared `option-pricing-cases.json` fixture executed by
both test suites — the technique already used for the conditional-logic engine
(`tests/fixtures/conditional-cases.json`, consumed by `ConditionalLogicTest.php` and
`tests/js/conditional-logic.test.js`).

### 3. Uploads

File upload is implemented **twice**: `ClassicRenderer` markup plus `frontend.js`, and
a separate React uploader inside `block-checkout/index.js`.

**Value format (cross-cutting decision).** A file field's value becomes a
comma-joined token list (`"<32hex>,<32hex>"`) instead of a single token. Every consumer
is handled explicitly: `Sanitizer`'s `^[a-f0-9]{32}$` check becomes a per-token check
via `FileUploads::parse_tokens()`; `FileUploads::validate_tokens()` — called from
`ClassicRenderer::validate_submit()` and `BlockIntegration::update_order_from_request()`,
not from `Validator`, which only does the required/empty check — validates each token;
`ConditionalLogic` keeps treating the value as an opaque string (non-empty = a file is
attached), which stays correct.

**Server side:**
- `FileUploads::parse_tokens( $value ): string[]` — parses, de-duplicates, drops
  malformed entries.
- `FileUploads::consume()` stays single-token; the caller loops. A dead token must not
  discard the field's other files — today `OrderPersistence::save():66` `continue`s and
  loses the whole field when `consume()` returns null.
- `OrderPersistence` stores one entry per file. `render_admin():115` currently decides
  "this is a file" with `0 === strpos( $display, 'http' )` and prints one "View file"
  link; it renders one link per file instead. `render_email():136,142` prints
  `$r['display']` as escaped text, so it must print **file names**, not a URL blob.
  The `_clcf_fields` row gains an explicit `files` list rather than overloading
  `display`. Orders placed before 1.3.0 have no `files` key, so the existing
  `strpos( $display, 'http' )` path stays as the fallback for them — otherwise every
  historical order silently loses its download link.
- `maxFiles` (1–10, clamped) is a new field property and must be added to
  `FieldSchema::normalize()`'s key whitelist (`:60-78`) — that method rebuilds each
  field from a fixed key set on both read and write, so an unlisted key is silently
  discarded.
- **WEBP** joins the default whitelist; `clcf_upload_extensions` still filters it.

**UI (built twice, identical behaviour):** a `maxFiles` setting, each uploaded file as
a removable row, a cap message. Two details the current single-file code assumes:
- `frontend.js:105` finds its target with `wrap.querySelector( 'input[type="hidden"]' )`
  — the new markup keeps exactly one hidden input per field (the token list), so this
  lookup stays valid.
- `can_upload()`'s per-IP rate limit (`clcf_upload_rate_limit`, default 20/hour) is now
  reachable by a legitimate shopper: 10 files plus one re-pick exhausts half of it, and
  `FileUploads.php:70` re-sets the transient on every upload, so the window is
  **rolling** — a shopper who trips it waits a full hour from their last attempt.
  Because `can_upload()` is the REST `permission_callback`, the failure currently
  surfaces as WordPress's "Sorry, you are not allowed to do that." Two changes: the default rises
  from 20 to **60** per hour (10 files × 3 fields, plus headroom for re-picks), and the
  rate-limit check moves out of the permission callback into `handle_upload()`, which
  can return a `WP_Error` carrying "Too many uploads — please wait a few minutes."
  `can_upload()` keeps only the nonce check, so its `bool` return stays correct.

### 4. Icons and brand mark

`FieldIcon` — one stroked 24×24 `currentColor` set covering Checkout Fields' 13 types
(text, textarea, number, checkbox, radio, select, heading, email, phone, url, date,
time, file) — replaces the mix of typographic stand-ins, Unicode shapes and colour
emoji, which render differently on every OS. `FlameMark` replaces the `▲` placeholder.
The sibling's `FieldIcon` already covers all 13 types, so no new artwork is needed.

Both are new files, and **five existing files call them**:

- `src/builder/Palette.jsx:9` — the `META` icon map,
- `src/builder/SettingsPanel.jsx:12` — a **second, duplicated** `TYPE_IC` emoji map,
- `src/builder/FieldPreview.jsx:33,35,37` — inline 📅 🕐 📎,
- `src/builder/AppShell.jsx:93` — the `▲` mark,
- `assets/css/builder.css:74,134,279` — `.clcf-ic` styles a text glyph
  (`font-size`/`font-weight`) and `.clcf-logo .clcf-mark` paints a gradient behind the
  triangle; both need SVG-appropriate rules.

The ported components default to Product Options' `clpo-` class names; they are
re-prefixed to `clcf-`.

### Shared-by-copy files

`FieldOptions`, `FieldIcon`, `FlameMark`, `shared/options.js` and
`option-pricing-cases.json` exist in both repositories on purpose — wp.org plugins must
be self-contained and a shared `vendor/` package has caused review friction before. The
cost is drift: when one copy is fixed, the other must be too. This list is the reminder.

## Testing

Current suite: 61 PHP tests + 5 JS suites (31 `it()` blocks). JS runs via
`npm run test:js` (bare `npx jest` fails — the config is `wp-scripts`').

Additions:

- `FieldSchemaTest` — `normalize_options()` preserves a positive price, collapses an
  unpriced choice back to a bare string, and drops non-numeric/≤ 0 prices. This is the
  regression guard for the silent no-op.
- `FeeCalculatorTest` — breakdown rows, per-row rounding, zero-amount rows skipped,
  effective price per option, all three modes, and `total()` equal to the sum of the
  rounded rows.
- `FieldOptionsTest` — both option shapes, field-price fallback, duplicate labels,
  non-choice fields, malformed prices.
- Shared `option-pricing-cases.json` executed by PHPUnit **and** Jest.
- **Render tests** — classic renderer output asserted *against* `FeeCalculator`.
  Product Options shipped display defects precisely because this layer had no tests.
- A test proving two fields with the same label produce **two** fees.
- `FileUploads::parse_tokens`, WEBP in the default whitelist, and `OrderPersistence`
  keeping the remaining files when one token is dead.
- `FieldSchema` retaining and clamping `maxFiles`.

Live QA runs on **both** checkouts separately: fields render, prices sit beside
options, totals itemize, multi-file upload with per-file removal, a `.webp` uploaded
end-to-end (`wp_handle_upload()` gates on `get_allowed_mime_types()` independently of
our whitelist), and an order carrying several fee lines and several download links.

## Release mechanics

Version bump in `corelabs-checkout-fields.php` and `readme.txt` (`Stable tag: 1.3.0`);
changelog covering the totals-shape change and the empty-option removal; an
`== Upgrade Notice ==` section, which this readme does not yet have (the sibling's
does); `.pot` regeneration; `bin/build-dist.sh`. There is no `bin/svn-deploy.sh` in
this repository — it exists only in Product Options and is ported across as part of
this release. The `svn ci` itself stays with the user, as always.

## Risks

| Risk | Mitigation |
| --- | --- |
| `normalize_options()` flattens prices → feature silently does nothing | rewritten first; `FieldSchemaTest` guards it |
| Priced options never charge because the fee gate ignores them | both gates test `has_priced_options()` too; QA on both checkouts |
| Two fields share a label → second fee silently dropped | explicit fee ids via `fees_api()` + regression test + builder warning |
| Fee lines print amounts that don't sum to the total | round per row, sum the rounded rows |
| An option reader is missed → broken checkout or crash | the exhaustive eight-file table in §2 |
| Classic and Block renderers drift apart | shared fixture + QA on both |
| Existing shops see their totals change shape | `clcf_itemized_fees` filter + explicit changelog note |
| Copied files drift from Product Options | the shared-by-copy list above |

## Out of scope for this release

New field types, `per_char`/`formula` modes, option groups, discounts, configurable fee
taxability.
