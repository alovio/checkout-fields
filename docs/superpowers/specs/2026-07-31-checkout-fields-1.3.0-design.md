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

Add `FeeCalculator::breakdown( $group, $field_values, $values, $base ): array`
returning `[{ field_id, label, amount }]` for engaged, active, priced fields.
`total()` becomes the sum of that breakdown. Display and billing then read the same
function — in Product Options, display and pricing each decided money separately and
that produced three defects where the page advertised an amount the cart did not
charge. We do not repeat that split.

`FeesIntegration::add_fee()` iterates the breakdown and adds one cart fee per row.
The `clcf_itemised_fees` filter (default `true`) restores the single-line behaviour.

**Fee-name collision (must-fix).** WooCommerce identifies a fee by
`sanitize_title( $name )`. Two fields labelled "Insurance" produce the same fee id
and WooCommerce **silently drops the second** — the shop loses that money. Fee names
are therefore made unique when labels repeat (the displayed name stays human; the id
disambiguates), and the builder warns about duplicate labels the way the Product
Options builder does.

Tax: `add_fee()` keeps WooCommerce's `taxable = true` default, so splitting one fee
into several does not change the tax total. Orders receive one fee item per row, so
the admin order screen and emails become itemised with no extra code.

### 2. Per-option fee amounts

Options keep the leanest shape that carries their data: a bare string until an option
needs a price, then `{ label, price }`. Groups saved by earlier versions normalize
byte-identically, so **no migration is required**.

Every reader goes through a new `FieldOptions` helper (`label()`, `price()`,
`labels()`, `price_for_value()`, `effective_price()`, `has_priced_options()`,
`price_range()`) rather than touching raw option values. An option's own price
replaces the field price and still runs through the field's price mode, so
"10% Express / 20% Overnight" works like flat amounts.

Three Checkout-Fields-specific consequences:

1. **Options are edited in a textarea today** (`General` tab, `options.join('\n')`),
   which has nowhere to put a price. Port the Product Options `Options` tab: one row
   per choice with a label and a price box, shown only for option-bearing types. The
   textarea moves there. Side benefit: both plugins' builders gain the same shape.
2. **Two render paths** — classic PHP (`ClassicRenderer`) and Block React
   (`block-checkout/index.js`) — both read options as strings today
   (`esc_html( $opt )`, `{ o }`). The new shape breaks both unless both move to
   `optionLabel()`. In Product Options this exact assumption crashed React.
3. **Block options are keyed by label** (`key={ o }`); duplicate labels then trigger
   React's duplicate-key warning. Key by position.

Prices render beside each choice automatically ("Express (+£10.00)"), and the field's
pill summarises the span. An option with no price of its own displays the field price
it falls back to, so what a shopper reads is always what the cart charges.

**Parity.** Fee logic will live in three places: PHP calculation, PHP classic render,
JS block render. Lock them with a shared `option-pricing-cases.json` fixture executed
by both test suites — the technique already used for the formula and conditional-logic
engines.

### 3. Uploads

File upload is implemented **twice** in this plugin: `ClassicRenderer` plus
`frontend.js`, and a separate React uploader inside `block-checkout/index.js`.

- **Server side is shared:** `FileUploads::parse_tokens()` (comma-joined token list),
  loops in `Sanitizer`/`Validator`, and one admin download link per file in
  `OrderPersistence`.
- **UI is built twice** with identical behaviour: a `maxFiles` setting (1–10), each
  uploaded file as a removable row, and a cap message.
- **WEBP** joins the default whitelist; `clcf_upload_extensions` still filters it.

### 4. Icons and brand mark

`FieldIcon` (one stroked 24×24 `currentColor` set, the Checkout Fields subset of the
Product Options icons) replaces the mix of typographic stand-ins, Unicode shapes and
colour emoji (`🔗 📅 🕐 📎`), which render differently on every OS. `FlameMark`
replaces the `▲` placeholder. Both are new files; existing code is untouched.

### Shared-by-copy files

`FieldOptions`, `FieldIcon`, `FlameMark`, `shared/options.js` and the pricing fixture
exist in both repositories on purpose — wp.org plugins must be self-contained and a
shared `vendor/` package has caused review friction before. The cost is drift: when
one copy is fixed, the other must be too. This list is the reminder.

## Testing

Current suite: 61 PHP + 5 JS. Additions:

- `FeeCalculatorTest` — breakdown rows, effective price per option, all three modes.
- `FieldOptionsTest` — both option shapes, fallback, duplicates, non-choice fields.
- Shared `option-pricing-cases.json` run by PHPUnit **and** Jest.
- **Render tests** — classic renderer output asserted *against* `FeeCalculator`.
  Product Options shipped display defects precisely because this layer had no tests.
- `FileUploads::parse_tokens`, WEBP in the default whitelist.
- A test proving a repeated fee label does not lose the second fee.

Live QA runs on **both** checkouts separately: field renders, prices beside options,
itemised totals, multi-file upload with per-file removal, an order with several fee
lines and several download links.

## Risks

| Risk | Mitigation |
| --- | --- |
| Two fields share a label → second fee silently dropped | unique fee ids + regression test + builder warning |
| Classic and Block renderers drift apart | shared fixture + QA on both |
| Existing shops see their totals change shape | `clcf_itemised_fees` filter + explicit changelog note |
| Block checkout's live fee refresh breaks | leave the existing cart-update flow untouched; verify in QA |
| Copied files drift from Product Options | the shared-by-copy list above |

## Out of scope for this release

New field types, `per_char`/`formula` modes, option groups, discounts.
