# CoreLabs Checkout Fields for WooCommerce — Design (v1)

- **Status:** Approved design (brainstorming phase) — pending spec review
- **Date:** 2026-06-03
- **Author:** Tahir (CoreLabs)
- **Sibling plugin:** [CoreLabs Product Options for WooCommerce](/Users/tahir/woo-product-options) — the conditional-logic engine and build pipeline are reused from it.

---

## 1. Overview & Strategy

### Problem / opportunity
WooCommerce store owners regularly need **extra checkout fields** (delivery instructions, gift messages, tax/VAT IDs, COD notes, age confirmation, etc.) and, critically, need to **show or hide those fields conditionally** — e.g. "show the company VAT field only for the Wholesale role", "show the delivery-time field only when shipping is Local Pickup", "show a surcharge notice only when paying Cash on Delivery".

Every meaningful paid competitor **gates conditional logic behind Pro**:
- ThemeHigh *Checkout Field Editor* — 400k+ installs, conditional logic is Pro.
- WP Desk *Flexible Checkout Fields* — 90k+ installs; **moved Block-checkout conditional logic behind a paywall in Sept 2025** (verbatim user review: "Free but now need Pro?").
- Acowebs *Checkout Field Editor* — conditional logic is Pro.
- QuadLayers — gives *basic* conditional logic away free **but is block-incompatible**.

Two of the most valuable conditions — **product category** and **user role** — are things WooCommerce's own native Additional Checkout Fields API (WC 8.9+) **cannot** express. And **nobody** ships full conditional logic working identically across **both classic and Block checkout** from a single editor.

### The wedge
Ship **full conditional logic (including AND/OR multi-condition groups), unified across classic + Block checkout, in the FREE tier.** This is the exact capability incumbents paywall. It also reuses CoreLabs' single most valuable existing asset — the React conditional-logic rule builder from Product Options — almost verbatim.

### Strategic fit
- **Demand & willingness-to-pay are web-verified:** $39–99/yr across three independent vendors plus an official WooCommerce extension.
- **Complements, does not cannibalize** the flagship: product-page *options* vs checkout-page *fields* are adjacent jobs for the identical store-owner buyer. This is the start of a **"CoreLabs Checkout Suite"** (fields → conditional fees → shipping rules).
- **Binding constraint = saturation** against ThemeHigh (cannot out-rank organically). Therefore the free plugin is a **lead magnet** for owned/paid channels and the existing Product Options install base, not the organic growth engine. The feature is matchable in one release, so the durable moat is **UX + the unified single-editor + suite bundling** — ship fast.

### Business model
Freemium. Free plugin on wordpress.org; Pro sold via own channels (Gumroad / code-heaven), unlocked by a separate Pro add-on plugin that flips an `apply_filters` gate — identical mechanism to the flagship (no license server required for v1).

Suggested Pro pricing: **$59/yr** (1 site), $99/yr (5 sites), $179/yr (25 sites), $199 lifetime single-site, $7/mo. Anchored just under ThemeHigh ($69/yr).

---

## 2. Goals & Non-Goals

### v1 Free goals
1. Unlimited custom checkout fields of types: **text, textarea, number, checkbox, radio, select, heading**.
2. A **drag-drop builder** (adapted from the flagship) on a central admin page.
3. **Full conditional logic** — `show` / `hide` / `require` actions, single **and** AND/OR multi-condition groups, operators `is` / `is_not` / `contains` / `gt` / `lt`.
4. Condition data sources: **cart contents** (has product, has category, total quantity, subtotal), **user role**, **shipping method**, **payment method**, **billing/shipping country**, and **sibling field values**.
5. Works **identically on classic checkout and Block checkout**.
6. Field values **saved to the order** (HPOS-safe) and **displayed** in admin order screen + order emails.
7. Server-side **validation** (required, type, conditional-required) and **sanitization**, mirrored client-side for live UX.

### v1 Pro goals (built but gated; shipped separately)
- Field-driven **pricing/fees** (charge or discount based on a selected field value).
- **Repeatable** field groups.
- **My-Account-editable** fields and field data on invoices.
- **Advanced validation** (regex, min/max, cross-field).
- **File-upload** and **date/time** field types.
- **Import/export** of field sets; per-rule **analytics**.

### Non-Goals (explicitly OUT of v1, free or Pro)
- Per-product field overrides (global store-level fields only in v1).
- Editing/conditioning WooCommerce's *native core* address fields (we render our own fields in a dedicated region; we do not rearrange first/last/address core fields).
- Multi-step / checkout-wizard flows.
- Payment-gateway or shipping-method *creation* (only conditioning on them).
- Multi-currency, subscriptions, or tax-engine logic.

---

## 3. Plugin Identity & Conventions

| Item | Value |
|---|---|
| Display name | **CoreLabs Checkout Fields for WooCommerce** |
| wp.org slug / text-domain | `corelabs-checkout-fields` |
| PHP namespace | `CoreLabs\CheckoutFields\` |
| Constant prefix | `CLCF_` (≥4 chars — avoids the 3-char `APO` issue the flagship hit) |
| Hook/filter prefix | `clcf_` |
| Option key (field group) | `clcf_field_group` |
| Order-meta key | `_clcf_fields` |
| JS globals | `window.CLCF_BUILDER` (admin), `window.CLCF_FE` (front-end) |
| REST namespace | `clcf/v1` |
| Local dev folder | `/Users/tahir/woo-checkout-fields/` |

wp.org safety: brand term ("CoreLabs") leads the name; slug carries no trademark; `/src` + `composer.json` shipped; Pro code excluded from the free build. (Applies the lessons from the flagship's pre-review.)

---

## 4. Architecture

### Reuse map (from Product Options)

| Verbatim copy (rename `clpo`→`clcf`) | Copy with adaptation | Net-new |
|---|---|---|
| `Logic/ConditionalLogic.php` | `Fields/FieldSchema.php` (drop product-page-only field types; keep checkout types) | `Checkout/CheckoutConditionData.php` |
| `src/frontend/conditional-logic.js` | `Admin/RestController.php` (route → settings, not per-product) | `Checkout/ClassicRenderer.php` |
| `tests/fixtures/conditional-cases.json` | `src/builder/App.jsx` (REST endpoint + central page) | `Checkout/BlockIntegration.php` (+ Store API) |
| `Cart/Validator.php`, `Cart/OptionSanitizer.php` | `src/builder/ConditionEditor.jsx` (add checkout data-source dropdown) | `Checkout/OrderPersistence.php` |
| `src/builder/reducer.js`, `src/builder/store.js` | `Admin/BuilderAssets.php` (settings page hook, not meta box) | `Admin/SettingsPage.php` |
| `Pro/ProModule.php` (rename gate `clcf_is_pro`) | `src/frontend.js` (bootstrap on checkout DOM) | `src/checkout-conditions/data-sources.js` |

### Module layout
```
corelabs-checkout-fields.php          (bootstrap, constants, autoload, Plugin::boot)
includes/
  Plugin.php                          (wiring; guards ProModule via class_exists)
  Logic/ConditionalLogic.php          [verbatim]
  Fields/FieldSchema.php              [adapt]   — normalize/validate field group JSON
  Fields/FieldRepository.php          [adapt]   — get()/save() against the clcf_field_group OPTION
  Admin/SettingsPage.php              [new]     — WooCommerce submenu page mounting the builder
  Admin/BuilderAssets.php             [adapt]   — enqueue builder bundle + CLCF_BUILDER global
  Admin/RestController.php            [adapt]   — clcf/v1/fields (GET/POST), manage_woocommerce cap
  Checkout/CheckoutConditionData.php  [new]     — build the values[] object from cart/customer/shipping/payment/address
  Checkout/ClassicRenderer.php        [new]     — render + collect fields on classic checkout
  Checkout/BlockIntegration.php       [new]     — register block-checkout integration + Store API schema
  Checkout/Validator.php              [adapt]   — reuse Cart/Validator logic for checkout submit
  Checkout/Sanitizer.php              [adapt]   — reuse OptionSanitizer
  Checkout/OrderPersistence.php       [new]     — write _clcf_fields to order; display in admin/email
  Frontend/FrontendAssets.php         [adapt]   — enqueue checkout runtime + CLCF_FE global
  Pro/ProModule.php                   [verbatim, rename] (excluded from free dist)
src/
  builder/ (App, Canvas, FieldSettings, ConditionEditor, FieldPalette, reducer, store)
  frontend/conditional-logic.js       [verbatim]
  checkout-conditions/data-sources.js [new]     — client mirror of CheckoutConditionData
  frontend.js                         [adapt]   — classic-checkout bootstrap
  block-checkout/index.js             [new]     — registerCheckoutBlock / slot fill + conditional wiring
tests/ (php Unit + js + fixtures)     [reuse conditional fixtures; new checkout tests]
bin/build-dist.sh, .distignore, readme.txt, composer.json, package.json, webpack.config.js, .wp-env.json
corelabs-checkout-fields-pro/         [separate Pro plugin: flips clcf_is_pro]
```

---

## 5. Data Model

### Field group (stored as JSON in the `clcf_field_group` option)
```jsonc
{
  "version": 1,
  "fields": [
    {
      "id": "vat_id",                 // stable slug, unique
      "type": "text",                 // text|textarea|number|checkbox|radio|select|heading
      "label": "VAT / Company ID",
      "placeholder": "",
      "description": "",
      "default": "",
      "required": false,
      "location": "after_order_notes",// rendering region (see §7)
      "options": [ ... ],             // for radio/select: [{label,value}]
      "constraints": { "min": null, "max": null, "step": null, "maxLength": null },
      // Conditional logic — FREE includes the multi-condition form:
      "conditions": [
        { "source": "user_role", "operator": "is", "value": "wholesale" },
        { "source": "field:ship_to_business", "operator": "is", "value": "yes" }
      ],
      "conditionMatch": "all",        // all (AND) | any (OR)
      "conditionAction": "show"       // show | hide | require
    }
  ]
}
```
- Backward-compatible single-condition form (`"condition": {…}`) is accepted by the engine (inherited from the flagship), but the builder authors the `conditions[]` form by default since multi-condition is free here.
- **`source`** is the new checkout extension to the rule shape: `field:<id>` (sibling field) | `user_role` | `cart_has_category:<slug>` | `cart_has_product:<id>` | `cart_qty` | `cart_subtotal` | `shipping_method` | `payment_method` | `billing_country` | `shipping_country`. When `source` is omitted it defaults to `field:<value-of-legacy-"field"-key>` for flagship parity.

### Storage
- **Global, store-level**, not per-product → a single `clcf_field_group` option (autoloaded JSON). No post meta.
- Per-product overrides are a deliberate non-goal for v1.

---

## 6. Conditional Logic

### Engine (reused verbatim)
`ConditionalLogic` provides `matches()`, `is_active()`, `requires()`, `active_map()` over a `(field, values)` pair. It is **context-agnostic**: it does not care whether `values` came from a product form or a checkout. The transitive, cycle-safe `active_map()` (a field whose controller is hidden is itself inactive) is reused as-is. JS mirror (`fieldActive`, `activeMap`, `matchRule`) and the shared `conditional-cases.json` fixtures are copied to keep PHP↔JS parity under test.

### New: building the `values` object at checkout (`CheckoutConditionData`)
The one genuinely new logic piece. Given the current request state it produces the flat `values` map the engine consumes, resolving each non-`field:` source:

| source | resolved from |
|---|---|
| `field:<id>` | submitted checkout field value |
| `user_role` | `wp_get_current_user()->roles[0]` (or `guest`) |
| `cart_has_category:<slug>` | iterate `WC()->cart` items → product term check |
| `cart_has_product:<id>` | cart item product/variation id match |
| `cart_qty` | `WC()->cart->get_cart_contents_count()` |
| `cart_subtotal` | `WC()->cart->get_subtotal()` |
| `shipping_method` | chosen shipping rate id (`WC()->session` chosen_shipping_methods) |
| `payment_method` | posted `payment_method` |
| `billing_country` / `shipping_country` | posted/customer address |

A **client mirror** (`src/checkout-conditions/data-sources.js`) computes the same map from the `wc/store/cart`, `wc/store/checkout`, and `wc/store/payment` data stores (Block) or from the classic checkout DOM + AJAX `update_order_review` payload (classic), so show/hide is live without a round-trip. Server remains source of truth for validation.

---

## 7. Classic Checkout Rendering (`ClassicRenderer`)

- Render fields in a configurable **region** via the matching core hook: `after_customer_details`, `before_order_notes`, `after_order_notes` (default), or `before_payment`. (We render our own block; we do not reorder native address fields.)
- Initial server-side visibility computed with `active_map(group, CheckoutConditionData::build())` so no-JS users get correct required/visible state.
- Embed the field-group JSON in a `<script type="application/json" class="clcf-rules">` tag; the front-end runtime (`frontend.js`) wires change/`updated_checkout` listeners to toggle visibility/required live.
- Collect on submit via `woocommerce_checkout_process` (validation) and persist via `woocommerce_checkout_create_order` / `OrderPersistence`.

---

## 8. Block Checkout Integration (`BlockIntegration`) — primary technical risk

**Chosen approach (A):** render our fields in a **dedicated, plugin-owned region** of the Block checkout and drive conditions from the block data stores — full control, guaranteeing the conditional-logic wedge behaves identically to classic. (Approach B — extending WC's native Additional Checkout Fields API — was rejected for v1: its supported field types/locations are too limited and reliable conditional show/hide on top of it is hard. This is precisely where WP Desk split into two plugins.)

Mechanism:
- **Server:** register an `IntegrationInterface` via `woocommerce_blocks_checkout_block_registration`; expose the field-group schema and accept submitted values through the **Store API** using `woocommerce_store_api_register_endpoint_data()` (schema + persistence callbacks) and `woocommerce_store_api_checkout_update_order_from_request` for order writing. Server-side validation runs through the same `Validator` as classic.
- **Client (`src/block-checkout/index.js`):** register a checkout block (slot fill in the dedicated region) that renders the fields with `@wordpress/components`, reads cart/checkout/payment state from `wc/store/*` for conditions (mirroring the classic data sources), and submits values through `extensionCartUpdate` / the checkout `additionalFields` payload. Reuses the same `fieldActive` / `activeMap` for visibility.
- **Parity:** both renderers consume the identical field-group JSON and the identical condition engine; a shared fixture-driven test asserts classic and block resolve the same visibility for the same `values`.

**Risk & mitigation:** dual rendering (server validation/price + client React show/hide) is the main build risk. Mitigation: keep v1 field types small, drive both paths from one engine + one fixture set, and live-QA both checkouts in wp-env before release.

---

## 9. Validation, Sanitization & Order Persistence

- **Sanitize** submitted values type-by-type (reuse `OptionSanitizer`: `sanitize_text_field`, `sanitize_textarea_field`, float cast, checkbox→`yes`, option-membership for radio/select). Output escaped with `JSON_HEX_*` where embedded in HTML (carries the flagship's stored-XSS fix).
- **Validate** only **active** fields (`active_map` drops hidden fields), enforcing required / conditional-required / type / constraints. Errors surface via `wc_add_notice` (classic) and Store API validation errors (block).
- **Persist** the active field values to the **order** (not line items — checkout fields are order-global) via `$order->add_meta_data('_clcf_fields', …)` + a human-readable per-field meta for admin display. HPOS-safe (CRUD only, no direct SQL). Display in the admin order screen (`woocommerce_admin_order_data_after_billing_address`) and in emails (`woocommerce_email_order_meta`).

---

## 10. Admin Builder (`SettingsPage` + adapted React app)

- A **WooCommerce submenu** page ("Checkout Fields") mounts the React builder. The builder is the flagship's `App.jsx`/`reducer`/`store` adapted to: (a) load/save the global option via `clcf/v1/fields` instead of per-product, (b) the `ConditionEditor` gains a **source dropdown** (field / cart category / role / shipping method / payment method / country) ahead of operator + value, with the value input switching to a select populated from the store's categories/roles/shipping zones/gateways where applicable.
- Capability: `manage_woocommerce`. Nonce: `wp_rest`. Globals injected via `wp_localize_script` into `CLCF_BUILDER` (REST root, nonce, field types, operators, `isPro`, and the lists needed for source value dropdowns).

---

## 11. Free / Pro Split & Gate

Identical mechanism to the flagship: `ProModule::is_pro()` returns `apply_filters('clcf_is_pro', false)`; the separate `corelabs-checkout-fields-pro` plugin flips it to true. Free build **excludes** `includes/Pro` and the Pro plugin via `.distignore` + `build-dist.sh`; `Plugin::boot()` guards `ProModule` with `class_exists`.

Filter extension points (default = free):
- `clcf_field_types` — free: text/textarea/number/checkbox/radio/select/heading; Pro adds: `file`, `date`.
- `clcf_price_modes` — free: none (no pricing in free); Pro: `fixed`, `per_unit`, `percent` (field-driven fees).
- `clcf_allowed_operators`, `clcf_allowed_actions` — full set free (the wedge); reserved for future tuning.
- `clcf_repeatable` — Pro: repeatable groups.
- `clcf_field_value` / `clcf_order_meta` — Pro hooks for My-Account editing, import/export, analytics.

**Note:** unlike the flagship, **conditional logic (multi-condition AND/OR + all operators) is FREE here** — that is the entire wedge. Pro monetizes *adjacent power* (pricing, file/date types, repeatable, My-Account), not the logic.

---

## 12. Testing Strategy

- **PHP unit** (PHPUnit 9 + Brain Monkey): `ConditionalLogic` (fixture parity), `FieldSchema` normalize, `Sanitizer`, `Validator`, **`CheckoutConditionData`** (new — mock cart/customer/session), `OrderPersistence` (CRUD meta), Pro-gate behavior.
- **JS unit** (Jest): `conditional-logic` (shared fixtures), `data-sources` (new — mock `wc/store/*`), builder `store`/`reducer`.
- **Parity test:** one fixture set asserts classic-path and block-path resolve identical visibility for identical `values`.
- **Live QA** (wp-env, Docker; WooCommerce + the Blocks checkout): build group in admin → place an order through **both** classic and Block checkout → assert correct show/hide, validation, and that values land on the order + emails. Target a money-/data-path smoke like the flagship's `integration-smoke.php`.

---

## 13. Packaging & wp.org

- Clone the flagship pipeline: `bin/build-dist.sh` (composer `--no-dev`, `.distignore`), ship `/src` + `composer.json` (Guideline 4), generate `.pot`, polished `readme.txt`, listing assets in `.wordpress-org/`.
- Run official Plugin Check in wp-env until clean at code level before any submission.
- Decision deferred (as with the flagship): whether to submit the free plugin to wp.org now or sell free+Pro via own channels first. Not blocking.

---

## 14. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Saturation vs ThemeHigh (400k installs) — can't win organic .org | Treat free as a lead magnet for owned channels + the Product Options base; lead GTM on the Block-checkout conditional-logic gap. |
| Wedge is matchable in one release | Moat = UX + unified single editor + suite bundling; ship fast, own the "works on Block checkout" reputation. |
| Dual classic+Block rendering is hard (where WP Desk split) | One engine + one fixture drives both paths; small v1 field set; live-QA both checkouts. |
| WC core encroachment (native Additional Checkout Fields API) | Our category/role/payment-method conditions are beyond the native API today; revisit if core expands. |
| Basic conditional logic already free (QuadLayers, block-incompatible) | Differentiate on advanced sources (category + role) + true Block parity. |

---

## 15. Open Questions (to resolve during planning)
1. Exact set of render regions to expose in v1 (proposed: after-order-notes default + 3 others) — keep minimal.
2. Whether file-upload/date land in the *first* Pro release or a later one (not v1-free either way).
3. wp.org submission timing vs own-channel-first (mirrors the flagship's open decision).

---

## Appendix A — Decision log
- **2026-06-02/03:** Demand-first research (43-agent workflow) ranked 18 candidates; **Conditional Checkout Fields** was the only idea to survive the adversarial kill-test (composite 7.8). Chosen over Conditional Fees (#2, better as a later suite module) and Request-a-Quote (#3, wedge already free).
- **Block rendering:** Approach **A** (own region) chosen over B (extend native API) for v1.
- **Free/Pro line:** full conditional logic is **free** (the wedge); Pro = pricing/fees, file/date types, repeatable, My-Account, import/export, analytics.
- **Storage:** global store-level option; per-product overrides are a non-goal for v1.
