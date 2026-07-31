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

**In:** per-option fee amounts, itemised fee display (form + totals), multi-file
uploads, WEBP, the drawn icon set, the Alovio flame mark.

**Deliberately out** (candidates for later releases): new field types (swatch, image
swatch, buttons, quantity, surcharge), the `per_char` and `formula` pricing modes,
option groups (a checkout is singular), negative fees/discounts.

## Architecture

### 1. Fees: one source of truth

Today `FeeCalculator::total()` returns a `float` and `FeesIntegration::add_fee()`
posts it as a single `$cart->add_fee( 'Checkout options', $fee )`. The per-field
split never exists, so nothing downstream can show it.

Add:

```php
FeeCalculator::breakdown( array $group, array $field_values, array $values,
                          int $decimals, float $base ): array
// [ [ 'field_id' => string, 'label' => string, 'amount' => float ], … ]
```

for engaged, active, priced fields, each row rounded to `$decimals`. `total()` becomes
the sum of that breakdown, keeping its current signature by defaulting
`$decimals = wc_get_price_decimals()`. Display and billing then read the same
function — in Product Options display and pricing each decided money separately and
that produced three defects where the page advertised an amount the cart did not
charge. We do not repeat that split.

**Rounding matters here.** `WC_Cart_Totals` rounds every fee independently, so N split
fees can differ from one summed fee by up to N−1 minor units on percent-mode rows.
Rounding per row inside `breakdown()` — and summing the rounded rows — makes the
advertised total and the charged total agree by construction.

**Emitting the fees.** `FeesIntegration::add_fee()` iterates the breakdown. It must
use the fees API rather than the `WC_Cart::add_fee()` shortcut:

```php
$cart->fees_api()->add_fee( [
    'id'       => 'clcf-' . $field_id,   // unique per field, never derived from the label
    'name'     => $row['label'],         // human text, may legitimately repeat
    'amount'   => $row['amount'],
    'taxable'  => false,                 // preserves today's behaviour — see below
    'tax_class'=> '',
] );
```

This is the **fee-name collision fix**. `WC_Cart::add_fee()` derives the id from the
name (`sanitize_title`), and `WC_Cart_Fees::add_fee()` returns
`WP_Error( 'fee_exists' )` for a repeated id — a return value `WC_Cart::add_fee()`
discards. Two fields labelled "Insurance" would therefore lose the second fee
**silently**, and the shop would lose that money. Passing an explicit id keyed on the
field id removes the collision while letting labels stay human. The builder also warns
about duplicate labels, as the Product Options builder does.

**Tax:** WooCommerce's `add_fee()` defaults to `taxable = false`
(`WC_Cart_Fees::$default_fee_props`), so Checkout Fields fees are non-taxable today.
The new code passes `'taxable' => false` **explicitly** to preserve that exactly.
Changing it would start charging tax on fees on every live store; making fee taxability
configurable is out of scope for this release.

**The two shipped filters keep defined meanings:**
- `clcf_fee_total` still wraps the charged total. `total()` applies it as it does
  today, and `add_fee()` compares the filtered total against the raw breakdown sum: if
  a site has filtered it, the fees are emitted as a **single** line (the itemised split
  cannot honestly represent an externally rewritten total). This keeps existing
  integrations correct instead of silently ignoring them.
- `clcf_fee_label` names the single line in that fallback and in the non-itemised mode.
- New `clcf_itemised_fees` (default `true`) emits one fee per field; returning `false`
  restores the pre-1.3.0 single "Checkout options" line.

**Orders and emails** need no extra work: each fee becomes its own order fee item, so
the admin order screen, the customer email and the order-received page itemise
automatically.

### 2. Per-option fee amounts

Options keep the leanest shape that carries their data: a bare string until an option
needs a price, then `{ label, price }`. Groups saved by earlier versions normalize
byte-identically, so **no migration is required**. (`FieldRepository` normalizes on both
read and write; the REST arg is a bare `array`, so nested objects survive.)

Every reader goes through a new `FieldOptions` helper — `label()`, `price()`,
`labels()`, `price_for_value()`, `effective_price()`, `has_priced_options()`,
`price_range()` — rather than touching raw option values. An option's own price
replaces the field price and still runs through the field's price mode, so
"10% Express / 20% Overnight" works like flat amounts.

**Every place that reads options must move to `FieldOptions`/`optionLabel()`.** The
current code assumes strings in eight places; missing any one of them ships a broken
store, so the list is exhaustive:

| File | Today | Breaks as |
| --- | --- | --- |
| `Checkout/Sanitizer.php:72` | `array_map( 'strval', … )` | value silently dropped — **order cannot be placed** |
| `Checkout/Validator.php:94` | `array_map( 'strval', … )` | "has an invalid selection" on every priced choice |
| `Checkout/ClassicRenderer.php:167,174` | `esc_html( $opt )` | prints `Array` |
| `src/block-checkout/index.js:77,83` | `{ o }`, `key={ o }` | React "Objects are not valid as a child" |
| `src/builder/FieldPreview.jsx:19,25` | `{ o }`, `key={ o }`, `options[0]` | canvas crash |
| `src/builder/panels/Logic.jsx:92` | `{ label: o, value: o }` | **writes an object as a rule value** — conditional logic breaks |
| `src/builder/panels/General.jsx:20` | `DefaultControl` map | default-selection list renders `[object Object]` |

Options are keyed by position wherever React lists them, so a duplicate label cannot
produce a duplicate key.

**The fee-bearing gate must learn about priced options — this is the failure that
would make the whole feature a no-op.** `src/frontend.js:155` and
`src/block-checkout/index.js:21` both decide "does this checkout have fees?" with
`fields.some( f => parseFloat( f.price ) > 0 )`. A field whose own price is 0 but whose
options are priced fails that test, so the checkout never pushes the cart update that
writes `clcf_fee_fields` into the session — and `FeesIntegration::add_fee()` reads that
session at order creation. Result: the fee is never captured and never charged. Both
gates become `parseFloat( f.price ) > 0 || hasPricedOptions( f )`.

**Editing.** Options live in a `General`-tab textarea today (`options.join('\n')`),
which has nowhere to put a price. Port the Product Options `Options` panel — one row
per choice with a label and a price box — as a new **Options** tab shown only for
option-bearing types, sitting between General and Logic (Checkout Fields' tabs are
General / Logic / Layout / Fee; there is no Pricing tab, so the panel's help text must
point at **Fee** instead). The textarea moves out of General.

The port also changes one existing behaviour: Product Options' `normalize_options()`
drops empty-label options, while Checkout Fields currently keeps them. Adopting the
port therefore removes empty options on the next save. That is the desired behaviour
(an unlabelled choice is unselectable), and it is called out in the changelog.

**Display.** Prices render beside each choice ("Express (+£10.00)") on both checkouts,
and the field's pill summarises the span. An option with no price of its own displays
the field price it falls back to, so what a shopper reads is always what the cart
charges.

**Parity.** Fee logic will live in three places: PHP calculation, PHP classic render,
JS block render. Lock them with a shared `option-pricing-cases.json` fixture executed
by both test suites — the technique already used for the conditional-logic engine.

### 3. Uploads

File upload is implemented **twice**: `ClassicRenderer` markup plus `frontend.js`, and
a separate React uploader inside `block-checkout/index.js`.

**Value format (cross-cutting decision).** A file field's value becomes a
comma-joined token list (`"<32hex>,<32hex>"`) instead of a single token. Every consumer
of that value is affected and each is handled explicitly: `Sanitizer`'s
`^[a-f0-9]{32}$` check becomes a per-token check via `FileUploads::parse_tokens()`;
`FileUploads::validate_tokens()` (called from `ClassicRenderer::validate_submit()` and
`BlockIntegration::update_order_from_request()`) validates each token; `ConditionalLogic`
comparisons continue to treat the value as an opaque string (non-empty = a file is
attached), which stays correct.

**Server side:**
- `FileUploads::parse_tokens( $value ): string[]` — parses, de-duplicates, drops
  malformed entries.
- `FileUploads::consume()` stays single-token; the caller loops. A dead token must not
  discard the field's other files — today `OrderPersistence::save()` `continue`s (and
  loses the whole field) when `consume()` returns null.
- `OrderPersistence` stores one entry per file. `render_admin()` currently decides
  "this is a file" with `0 === strpos( $display, 'http' )` and prints one "View file"
  link; it renders one link per file instead. `render_email()` prints
  `$r['display']` as escaped text, so it must print **file names**, not a URL blob.
  The `_clcf_fields` row shape gains an explicit `files` list rather than overloading
  `display`.
- `maxFiles` (1–10, clamped) is a new field property and must be added to
  `FieldSchema::normalize()`'s key whitelist — that method rebuilds each field from a
  fixed set of keys on both read and write, so an unlisted key is silently discarded.
- **WEBP** joins the default whitelist; `clcf_upload_extensions` still filters it.

**UI (built twice, identical behaviour):** a `maxFiles` setting, each uploaded file as
a removable row, a cap message. Two details the current single-file code assumes:
- `frontend.js:105` finds its target with `wrap.querySelector( 'input[type="hidden"]' )`
  — the new markup must keep exactly one hidden input per field (the token list), or
  that lookup has to become explicit.
- `can_upload()`'s per-IP rate limit (`clcf_upload_rate_limit`, default 20/hour) is now
  reachable by a legitimate shopper: 10 files plus one re-pick exhausts half of it, and
  the failure currently surfaces as the generic "Upload failed — please try again."
  The limit is raised to a per-field-aware default and the rate-limited response gets
  its own message so the shopper knows to wait rather than retry.

### 4. Icons and brand mark

`FieldIcon` — one stroked 24×24 `currentColor` set covering Checkout Fields' 13 types
(text, textarea, number, checkbox, radio, select, heading, email, phone, url, date,
time, file) — replaces the mix of typographic stand-ins, Unicode shapes and colour
emoji, which render differently on every OS. `FlameMark` replaces the `▲` placeholder.

Both are new files, but four existing files call them (the spec's earlier claim that
"existing code is untouched" was wrong):

- `src/builder/Palette.jsx` — the `META` icon map,
- `src/builder/SettingsPanel.jsx:12` — a **second, duplicated** `TYPE_IC` emoji map,
- `src/builder/FieldPreview.jsx:33,35,37` — inline 📅 🕐 📎,
- `src/builder/AppShell.jsx:93` — the `▲` mark.

The ported components default to Product Options' `clpo-` CSS class names; they are
re-prefixed to `clcf-` to match this plugin's stylesheet.

### Shared-by-copy files

`FieldOptions`, `FieldIcon`, `FlameMark`, `shared/options.js` and
`option-pricing-cases.json` exist in both repositories on purpose — wp.org plugins must
be self-contained and a shared `vendor/` package has caused review friction before. The
cost is drift: when one copy is fixed, the other must be too. This list is the reminder.

## Testing

Current suite: 61 PHP tests + 5 JS suites (31 `it()` blocks). JS runs via
`npm run test:js` (bare `npx jest` fails — the config is `wp-scripts`').

Additions:

- `FeeCalculatorTest` — breakdown rows, per-row rounding, effective price per option,
  all three modes, and `total()` equals the sum of the rounded rows.
- `FieldOptionsTest` — both option shapes, field-price fallback, duplicate labels,
  non-choice fields, malformed prices.
- Shared `option-pricing-cases.json` executed by PHPUnit **and** Jest.
- **Render tests** — classic renderer output asserted *against* `FeeCalculator`.
  Product Options shipped display defects precisely because this layer had no tests.
- A test proving two fields with the same label produce **two** fees.
- A test proving a filtered `clcf_fee_total` still charges the filtered amount.
- `FileUploads::parse_tokens`, WEBP in the default whitelist, and `OrderPersistence`
  keeping the remaining files when one token is dead.
- `FieldSchema` retaining and clamping `maxFiles`.

Live QA runs on **both** checkouts separately: fields render, prices sit beside
options, totals itemise, multi-file upload with per-file removal, a `.webp` uploaded
end-to-end (`wp_handle_upload()` gates on `get_allowed_mime_types()` independently of
our whitelist), and an order carrying several fee lines and several download links.

## Release mechanics

Version bump in `corelabs-checkout-fields.php` and `readme.txt` (`Stable tag: 1.3.0`),
changelog + upgrade notice covering the totals-shape change and the empty-option
removal, `.pot` regeneration, `bin/build-dist.sh`, then the SVN deploy script — commit
left to the user, as always.

## Risks

| Risk | Mitigation |
| --- | --- |
| Priced options never charge because the fee gate ignores them | both gates test `hasPricedOptions()` too; covered by QA on both checkouts |
| Two fields share a label → second fee silently dropped | explicit fee ids via `fees_api()` + regression test + builder warning |
| Split fees round differently from one summed fee | round per row in `breakdown()`, sum the rounded rows |
| A site filtering `clcf_fee_total` silently loses its filter | itemisation falls back to a single line when the filter changed the total |
| An option reader is missed → broken checkout or crash | the exhaustive eight-file table in §2 |
| Classic and Block renderers drift apart | shared fixture + QA on both |
| Existing shops see their totals change shape | `clcf_itemised_fees` filter + explicit changelog note |
| Copied files drift from Product Options | the shared-by-copy list above |

## Out of scope for this release

New field types, `per_char`/`formula` modes, option groups, discounts, configurable fee
taxability.
