# CoreLabs Checkout Fields for WooCommerce — Implementation Plan (v1 Free)

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the v1 free MVP of a WooCommerce plugin that adds custom checkout fields with full conditional show/hide/require logic, working identically on classic and Block checkout.

**Architecture:** Reuse the sibling plugin's context-agnostic conditional-logic engine verbatim. Feed it a flat `values` map built at checkout from cart/role/shipping/payment/address ("source tokens") plus submitted field values. Render fields in a plugin-owned region on both classic checkout (PHP hooks) and Block checkout (Store API + a registered checkout block). Store the field definition in one global option, edited from a central admin builder adapted from the flagship.

**Tech Stack:** PHP 7.4+ (PSR-4, `CoreLabs\CheckoutFields\`), WordPress/WooCommerce (HPOS-safe, Store API, Blocks), React via `@wordpress/scripts` + `@wordpress/data`, PHPUnit 9 + Brain Monkey, Jest, wp-env (Docker).

**Spec:** `docs/superpowers/specs/2026-06-03-checkout-fields-design.md`

**Sibling source (reuse from):** `/Users/tahir/woo-product-options`

---

## Key design decisions locked here (refining the spec)

1. **Engine stays verbatim.** `ConditionalLogic` (PHP + JS) and `conditional-cases.json` are copied unchanged except namespace/text-domain renames. The rule shape is unchanged: `{field, operator, value, action}` and `{conditions:[…], conditionMatch, conditionAction}`. **The `field` key holds a "source token"**, not only a sibling field id. This supersedes the spec §5 `source` sub-key — no engine change needed.
2. **Source tokens** (the value of a rule's `field`):
   - `field:<id>` — another checkout field's submitted value
   - `user_role` — current user's primary role (or `guest`)
   - `cart_has_category:<term_id>` — `yes`/`""`
   - `cart_has_product:<product_id>` — `yes`/`""`
   - `cart_qty` — integer as string
   - `cart_subtotal` — float as string
   - `shipping_method` — chosen rate id (e.g. `flat_rate:3`)
   - `payment_method` — gateway id (e.g. `cod`)
   - `billing_country` / `shipping_country` — ISO code
3. **`CheckoutConditionData::build()` (PHP)** and **`buildValues()` (JS mirror)** produce the same token-keyed map; the engine + `active_map` then resolve visibility identically server- and client-side.
4. **Storage:** one autoloaded option `clcf_field_group` (global). No post meta. No per-product overrides in v1.
5. **Order persistence:** order-level meta `_clcf_fields` (JSON) + one human-readable meta per active field for admin/email display. HPOS-safe (CRUD only).
6. **Free = all conditional logic** (multi-condition AND/OR + all operators). Pro gate `clcf_is_pro` reserved for: extra field types (`file`, `date`), pricing modes, repeatable groups, My-Account editing, import/export, analytics.

### Rename mapping (applied to every copied file)

| Sibling | This plugin |
|---|---|
| `CoreLabs\ProductOptions\` | `CoreLabs\CheckoutFields\` |
| `CLPO_` | `CLCF_` |
| `clpo_` (hooks) | `clcf_` |
| `corelabs-product-options` (text domain/slug) | `corelabs-checkout-fields` |
| `CLPO_BUILDER` / `CLPO_FE` | `CLCF_BUILDER` / `CLCF_FE` |
| store id `apo/builder` | `clcf/builder` |
| REST ns `clpo/v1` | `clcf/v1` |
| markup classes `apo-*` / `data-apo-*` | `clcf-*` / `data-clcf-*` |

---

## File structure (what gets created)

```
corelabs-checkout-fields.php             [new]  bootstrap, constants, HPOS+Blocks declare, Plugin::boot
composer.json package.json webpack.config.js phpunit.xml.dist .wp-env.json .distignore .gitignore  [new, adapted]
bin/build-dist.sh                        [new, adapted]
readme.txt                               [new]
includes/
  Plugin.php                             [new]  wiring
  Logic/ConditionalLogic.php             [copy] verbatim+rename
  Fields/FieldSchema.php                 [adapt] normalize the checkout field group (checkout types)
  Fields/FieldTypes.php                  [adapt] free type list + clcf_field_types filter
  Fields/FieldRepository.php             [new]  get()/save() against the clcf_field_group OPTION
  Admin/SettingsPage.php                 [new]  WooCommerce submenu mounting the builder
  Admin/BuilderAssets.php                [adapt] enqueue builder bundle + CLCF_BUILDER global
  Admin/RestController.php               [adapt] clcf/v1/fields GET/POST (manage_woocommerce)
  Checkout/CheckoutConditionData.php     [new]  build token→value map from cart/customer/session/posted
  Checkout/Sanitizer.php                 [copy] from Cart/OptionSanitizer, rename
  Checkout/Validator.php                 [copy] from Cart/Validator, rename + drop pricing bits
  Checkout/ClassicRenderer.php           [new]  render + collect on classic checkout
  Checkout/BlockIntegration.php          [new]  Store API endpoint data + order write + block asset reg
  Checkout/OrderPersistence.php          [new]  write _clcf_fields, admin + email display
  Frontend/FrontendAssets.php            [adapt] enqueue checkout runtime + CLCF_FE global
  Pro/ProModule.php                      [copy] rename gate clcf_is_pro (excluded from free dist)
src/
  index.js                               [adapt] builder bootstrap (mount on settings page)
  builder/{App,Canvas,FieldPalette,FieldSettings,ConditionEditor,reducer,store,selectors}.{jsx,js}  [adapt]
  frontend/conditional-logic.js          [copy] verbatim+rename
  checkout-conditions/data-sources.js    [new]  JS mirror of CheckoutConditionData
  frontend.js                            [new]  classic-checkout runtime bootstrap
  block-checkout/index.js                [new]  registerCheckoutBlock + conditional wiring
  block-checkout/block.json              [new]  block metadata
tests/
  fixtures/conditional-cases.json        [copy] verbatim
  fixtures/condition-data-cases.json     [new]  token-map fixtures
  php/Unit/*.php                          [copy+new]
  js/*.test.js                            [copy+new]
  integration-smoke.php                  [new]  wp-cli money/data path smoke
corelabs-checkout-fields-pro/corelabs-checkout-fields-pro.php  [new] flips clcf_is_pro
```

---

## Chunk 0: Project scaffold & toolchain

### Task 0.1: Bootstrap file + constants

**Files:**
- Create: `corelabs-checkout-fields.php`

- [ ] **Step 1: Write the bootstrap** (adapted from sibling `corelabs-product-options.php`)

```php
<?php
/**
 * Plugin Name: CoreLabs Checkout Fields for WooCommerce
 * Plugin URI: https://addons.itahir.com/corelabs-checkout-fields
 * Description: Add custom WooCommerce checkout fields with a drag-and-drop builder and full conditional logic — on classic and Block checkout.
 * Version: 1.0.0
 * Author: CoreLabs
 * Author URI: https://addons.itahir.com
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: corelabs-checkout-fields
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
defined( 'ABSPATH' ) || exit;

define( 'CLCF_VERSION', '1.0.0' );
define( 'CLCF_FILE', __FILE__ );
define( 'CLCF_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLCF_URL', plugin_dir_url( __FILE__ ) );

require_once CLCF_PATH . 'vendor/autoload.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CLCF_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CLCF_FILE, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	\CoreLabs\CheckoutFields\Plugin::instance()->boot();
} );
```

- [ ] **Step 2: Commit**

```bash
git add corelabs-checkout-fields.php
git commit -m "feat: plugin bootstrap, HPOS + cart_checkout_blocks compat"
```

### Task 0.2: Composer + autoload

**Files:**
- Create: `composer.json`, `.gitignore`

- [ ] **Step 1: composer.json** (adapt sibling; new package name + namespace)

```json
{
  "name": "corelabs/checkout-fields",
  "description": "Custom WooCommerce checkout fields with a drag-drop builder and conditional logic.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "version": "1.0.0",
  "require": { "php": ">=7.4" },
  "require-dev": {
    "phpunit/phpunit": "^9",
    "brain/monkey": "^2",
    "yoast/phpunit-polyfills": "^2"
  },
  "autoload": { "psr-4": { "CoreLabs\\CheckoutFields\\": "includes/" } },
  "autoload-dev": { "psr-4": { "CoreLabs\\CheckoutFields\\Tests\\": "tests/php/" } },
  "scripts": { "test": "phpunit -c phpunit.xml.dist" },
  "config": { "allow-plugins": { "*": true } }
}
```

- [ ] **Step 2: .gitignore** — `vendor/`, `node_modules/`, `build/`, `dist/`, `.phpunit.result.cache`, `.DS_Store`, `package-lock.json` is committed? (sibling excludes from dist but commits lock — keep `composer.lock` + `package-lock.json` committed for reproducible builds; ignore `vendor/ node_modules/ build/ dist/`).

- [ ] **Step 3: Generate autoloader & verify**

Run: `composer install`
Expected: `vendor/autoload.php` created, no errors.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock .gitignore
git commit -m "chore: composer PSR-4 autoload (CoreLabs\\CheckoutFields)"
```

### Task 0.3: JS toolchain (wp-scripts, webpack 3 entries)

**Files:**
- Create: `package.json`, `webpack.config.js`

- [ ] **Step 1: package.json** (sibling + a `dist` script)

```json
{
  "name": "corelabs-checkout-fields",
  "private": true,
  "scripts": {
    "start": "wp-scripts start",
    "build": "wp-scripts build",
    "test:js": "wp-scripts test-unit-js",
    "env:start": "wp-env start",
    "env:stop": "wp-env stop",
    "dist": "bash bin/build-dist.sh"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27",
    "@wordpress/env": "^9"
  }
}
```

- [ ] **Step 2: webpack.config.js** — three entries: `index` (builder), `frontend` (classic checkout), `block-checkout` (Block):

```js
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( process.cwd(), 'src', 'index.js' ),
		frontend: path.resolve( process.cwd(), 'src', 'frontend.js' ),
		'block-checkout': path.resolve( process.cwd(), 'src', 'block-checkout', 'index.js' ),
	},
};
```

- [ ] **Step 3: `npm install`**, then commit `package.json package-lock.json webpack.config.js`.

### Task 0.4: Test harness (PHPUnit + Brain Monkey, Jest) & wp-env

**Files:**
- Create: `phpunit.xml.dist`, `tests/php/Unit/TestCase.php`, `tests/php/bootstrap.php`, `.wp-env.json`

- [ ] **Step 1:** Copy `phpunit.xml.dist`, `tests/php/Unit/TestCase.php`, `tests/php/bootstrap.php` from the sibling, renaming namespace `CoreLabs\ProductOptions\Tests` → `CoreLabs\CheckoutFields\Tests`. (Read sibling files first.)

- [ ] **Step 2: `.wp-env.json`** mapping both plugins:

```json
{
	"plugins": [ "https://downloads.wordpress.org/plugin/woocommerce.zip" ],
	"mappings": {
		"wp-content/plugins/corelabs-checkout-fields": ".",
		"wp-content/plugins/corelabs-checkout-fields-pro": "./corelabs-checkout-fields-pro"
	},
	"config": { "WP_DEBUG": true, "WP_DEBUG_LOG": true }
}
```

- [ ] **Step 3: A smoke test** `tests/php/Unit/PluginSmokeTest.php` asserting `CLCF_VERSION` is defined when the bootstrap constants file is loaded (copy sibling pattern).

- [ ] **Step 4: Run** `composer test` and `npm run test:js` → expect green (only the smoke test so far).

- [ ] **Step 5: Commit** test harness + `.wp-env.json`.

### Task 0.5: Packaging skeleton

**Files:**
- Create: `bin/build-dist.sh`, `.distignore`, `readme.txt` (stub)

- [ ] **Step 1:** Copy `bin/build-dist.sh` and `.distignore` from the sibling; change `SLUG="corelabs-checkout-fields"`; in `.distignore` and `build-dist.sh` set the Pro excludes to `/corelabs-checkout-fields-pro` and `/includes/Pro`; add `/src/block-checkout/block.json`? (no — block.json must ship; keep it). Add `--exclude='src/block-checkout/*.test.js'` is unnecessary (tests/ already excluded).
- [ ] **Step 2:** Write a `readme.txt` stub (header, short description, == Description ==, == Installation ==, == Changelog == placeholders to be filled in Chunk 7).
- [ ] **Step 3:** Commit.

---

## Chunk 1: Reused conditional-logic core

### Task 1.1: Copy the engine (PHP) + parity fixtures

**Files:**
- Create: `includes/Logic/ConditionalLogic.php`, `tests/fixtures/conditional-cases.json`, `tests/php/Unit/ConditionalLogicTest.php`

- [ ] **Step 1:** Copy sibling `includes/Logic/ConditionalLogic.php` verbatim; change only the namespace to `CoreLabs\CheckoutFields\Logic`. (No logic changes — the `field` key will carry source tokens, transparent to the engine.)
- [ ] **Step 2:** Copy `tests/fixtures/conditional-cases.json` verbatim.
- [ ] **Step 3:** Copy `tests/php/Unit/ConditionalLogicTest.php`, rename namespace + `use`.
- [ ] **Step 4: Run** `composer test -- --filter ConditionalLogic` → expect all fixture cases PASS.
- [ ] **Step 5: Commit** `feat: conditional-logic engine (reused) + parity fixtures`.

### Task 1.2: Copy the engine (JS) + Jest parity

**Files:**
- Create: `src/frontend/conditional-logic.js`, `tests/js/conditional-logic.test.js`

- [ ] **Step 1:** Copy sibling `src/frontend/conditional-logic.js` verbatim (it is pure; no WP deps). Keep exports `evaluate`, `fieldActive`, `activeMap`, `matchRule`, `readValues`, `wire`. NOTE: `readValues`/`wire` read sibling field values from the DOM; in Chunk 6 we extend value-reading via `data-sources.js`, but the pure functions stay unchanged here.
- [ ] **Step 2:** Copy `tests/js/conditional-logic.test.js` (loads the same fixture).
- [ ] **Step 3: Run** `npm run test:js -- conditional-logic` → expect PASS, asserting PHP↔JS parity against the shared fixture.
- [ ] **Step 4: Commit.**

### Task 1.3: Pro gate (reserved, excluded from free build)

**Files:**
- Create: `includes/Pro/ProModule.php`, `corelabs-checkout-fields-pro/corelabs-checkout-fields-pro.php`

- [ ] **Step 1:** Copy `includes/Pro/ProModule.php`; rename namespace + filters to `clcf_*`. For v1 the gate lifts: `clcf_field_types` += `['file','date']`, `clcf_price_modes` += `['fixed','per_unit','percent']`. (Operators/actions are already full in free — leave `clcf_allowed_operators`/`clcf_allowed_actions` returning the full set regardless; the gate does not restrict them.)
- [ ] **Step 2:** Pro unlocker plugin:

```php
<?php
/**
 * Plugin Name: CoreLabs Checkout Fields — Pro
 * Description: Unlocks Pro features for CoreLabs Checkout Fields.
 * Version: 1.0.0
 * Author: CoreLabs
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
add_filter( 'clcf_is_pro', '__return_true' );
```

- [ ] **Step 3:** `tests/php/Unit/ProGateTest.php` — assert `ProModule::is_pro()` reflects the `clcf_is_pro` filter, and `register()` adds the field-type/price-mode filters only when pro. (Copy sibling ProConditionalTest patterns; trim to gate behavior.)
- [ ] **Step 4: Run** tests → PASS. **Commit.**

---

## Chunk 2: Field model, storage, REST & admin builder

### Task 2.1: Field types & schema

**Files:**
- Create: `includes/Fields/FieldTypes.php`, `includes/Fields/FieldSchema.php`, `tests/php/Unit/FieldSchemaTest.php`

- [ ] **Step 1: Write FieldSchemaTest** first (TDD). Assert `FieldSchema::normalize()`:
  - coerces a raw array into `{version:1, fields:[…]}`;
  - drops unknown field types (keeps only those in `FieldTypes::all()` = filtered by `clcf_field_types`);
  - assigns/sanitizes a unique `id` (slug) per field; sanitizes `label` with `sanitize_text_field`;
  - normalizes `options` for radio/select to `[{label,value}]`;
  - preserves `conditions[]`/`condition`, `conditionMatch`, `conditionAction`;
  - normalizes `location` to one of the allowed regions (default `after_order_notes`).

```php
public function test_normalize_keeps_known_types_and_assigns_ids(): void {
    $out = FieldSchema::normalize( [ 'fields' => [
        [ 'type' => 'text', 'label' => 'VAT <b>id</b>' ],
        [ 'type' => 'rocket', 'label' => 'nope' ],
    ] ] );
    $this->assertSame( 1, $out['version'] );
    $this->assertCount( 1, $out['fields'] );
    $this->assertSame( 'VAT id', $out['fields'][0]['label'] );
    $this->assertNotEmpty( $out['fields'][0]['id'] );
}
```

- [ ] **Step 2:** Implement `FieldTypes::all()` returning `apply_filters('clcf_field_types', ['text','textarea','number','checkbox','radio','select','heading'])`. Implement `FieldSchema::normalize()` (adapt the sibling `FieldSchema` — strip product-only `price`/`priceMode`/`swatch` handling from the FREE path; keep `location` and the condition normalization).
- [ ] **Step 3: Run** `composer test -- --filter FieldSchema` → PASS.
- [ ] **Step 4: Commit.**

### Task 2.2: Repository (option storage)

**Files:**
- Create: `includes/Fields/FieldRepository.php`, `tests/php/Unit/FieldRepositoryTest.php`

- [ ] **Step 1: Test** (Brain Monkey mock `get_option`/`update_option`): `get()` returns a normalized empty group when the option is absent; `save()` normalizes then `update_option('clcf_field_group', wp_json_encode(...))`; `get()` decodes + normalizes round-trip.
- [ ] **Step 2: Implement**

```php
<?php
declare( strict_types=1 );
namespace CoreLabs\CheckoutFields\Fields;
defined( 'ABSPATH' ) || exit;

final class FieldRepository {
	private const OPTION = 'clcf_field_group';

	public function get(): array {
		$raw = get_option( self::OPTION, '' );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		return FieldSchema::normalize( is_array( $decoded ) ? $decoded : array() );
	}

	public function save( array $group ): array {
		$normalized = FieldSchema::normalize( $group );
		update_option( self::OPTION, wp_json_encode( $normalized ) );
		return $normalized;
	}
}
```

- [ ] **Step 3: Run** → PASS. **Commit.**

### Task 2.3: REST controller

**Files:**
- Create: `includes/Admin/RestController.php`, `tests/php/Unit/RestControllerTest.php`

- [ ] **Step 1: Test** the route shape + permission: registers `clcf/v1` route `/fields` with GET + POST; permission callback requires `current_user_can('manage_woocommerce')`; POST body `{fields:[…]}` is passed to `FieldRepository::save` and the normalized group returned.
- [ ] **Step 2: Implement** (adapt sibling `Admin/RestController`; route is `/fields` not `/product/{id}/fields`):

```php
register_rest_route( 'clcf/v1', '/fields', [
	[ 'methods' => 'GET',  'callback' => [ $this, 'get_fields' ],  'permission_callback' => [ $this, 'can_manage' ] ],
	[ 'methods' => 'POST', 'callback' => [ $this, 'save_fields' ], 'permission_callback' => [ $this, 'can_manage' ],
	  'args' => [ 'fields' => [ 'required' => true, 'type' => 'array' ] ] ],
] );
```
`can_manage()` → `current_user_can('manage_woocommerce')`. `save_fields()` sanitizes via `FieldSchema::normalize` (already does input sanitizing) and returns the saved group.

- [ ] **Step 3: Run** → PASS. **Commit.**

### Task 2.4: Settings page + builder assets

**Files:**
- Create: `includes/Admin/SettingsPage.php`, `includes/Admin/BuilderAssets.php`

- [ ] **Step 1:** `SettingsPage::register()` adds `add_submenu_page` under `woocommerce` ("Checkout Fields", cap `manage_woocommerce`, slug `clcf-checkout-fields`) rendering `<div id="clcf-builder-root"></div>`.
- [ ] **Step 2:** `BuilderAssets::register()` enqueues `build/index.js` + `index.asset.php` deps **only on our settings page** (`$hook === 'woocommerce_page_clcf-checkout-fields'`), enqueues `build/index.css`, and injects `CLCF_BUILDER` via `wp_localize_script`:

```php
wp_localize_script( 'clcf-builder', 'CLCF_BUILDER', [
	'root'           => esc_url_raw( rest_url( '/' ) ),
	'nonce'          => wp_create_nonce( 'wp_rest' ),
	'fieldTypes'     => \CoreLabs\CheckoutFields\Fields\FieldTypes::all(),
	'isPro'          => \CoreLabs\CheckoutFields\Pro\ProModule::is_pro(),
	'operators'      => apply_filters( 'clcf_allowed_operators', [ 'is', 'is_not', 'contains', 'gt', 'lt' ] ),
	'actions'        => apply_filters( 'clcf_allowed_actions', [ 'show', 'hide', 'require' ] ),
	'sources'        => \CoreLabs\CheckoutFields\Admin\SourceCatalog::all(), // categories, roles, shipping methods, gateways, countries
] );
wp_set_script_translations( 'clcf-builder', 'corelabs-checkout-fields', CLCF_PATH . 'languages' );
```
NOTE: `ProModule::is_pro()` is called via fully-qualified name guarded by `class_exists` in `Plugin::boot` consumers — but here it's safe because `is_pro()` only reads a filter; if Pro class is absent, call `(bool) apply_filters('clcf_is_pro', false)` directly to avoid a hard dependency. Prefer the direct filter call in free code.

- [ ] **Step 3:** `includes/Admin/SourceCatalog.php` (new) builds the dropdown data for the builder: product categories (`get_terms('product_cat')` → id+name), editable roles (`wp_roles()`), shipping methods/zones (`WC_Shipping_Zones`), payment gateways (`WC()->payment_gateways`), countries (`WC()->countries`). Each as `[{token, label}]` or grouped for the source dropdown. Add a unit test mocking these.
- [ ] **Step 4: Commit.** (UI mounts in Task 2.5; verify enqueue path manually in Chunk 6 live QA.)

### Task 2.5: Builder React app (adapt)

**Files:**
- Create: `src/index.js`, `src/builder/{App.jsx,Canvas.jsx,FieldPalette.jsx,FieldSettings.jsx,ConditionEditor.jsx}`, `src/builder/{reducer.js,store.js,selectors.js}`, `tests/js/store.test.js`

- [ ] **Step 1:** Copy `reducer.js`, `store.js`, `selectors.js` from the sibling verbatim; rename `STORE = 'clcf/builder'`. Copy `tests/js/store.test.js`; run → PASS.
- [ ] **Step 2:** `src/index.js` mounts `<App/>` on `#clcf-builder-root`, hydrating from `GET clcf/v1/fields` and saving via `POST clcf/v1/fields` (adapt sibling `App.jsx` save flow; the endpoint no longer needs a product id).
- [ ] **Step 3:** Adapt `App.jsx`, `Canvas.jsx`, `FieldPalette.jsx`, `FieldSettings.jsx`: remove product-page price UI from the FREE build; add a per-field **Location** selector (the 4 regions). Keep field add/reorder/duplicate/dirty-save/beforeunload behaviors.
- [ ] **Step 4: Adapt `ConditionEditor.jsx`** — the key UI change. Each condition row becomes **[source dropdown] [operator] [value]**:
  - Source dropdown lists: "Another field" (→ then a field picker that writes `field:<id>`), plus the `CLCF_BUILDER.sources` groups (category/role/shipping/payment/country) writing the matching token into the rule's `field` key.
  - When source is a field of type radio/select/checkbox, the value input becomes a dropdown of that field's options/yes (reuse the sibling's "condition value-as-dropdown" behavior).
  - When source is category/role/shipping/payment/country, the value input is a dropdown populated from `CLCF_BUILDER.sources`.
  - Multi-condition AND/OR (`conditions[]` + `conditionMatch` + `conditionAction`) is shown **for everyone** (free), unlike the flagship which gated it behind `isPro`.
- [ ] **Step 5: Commit.** (Live render verified in Chunk 6.)

---

## Chunk 3: Checkout condition data (the new engine input)

### Task 3.1: PHP — CheckoutConditionData

**Files:**
- Create: `includes/Checkout/CheckoutConditionData.php`, `tests/fixtures/condition-data-cases.json`, `tests/php/Unit/CheckoutConditionDataTest.php`

- [ ] **Step 1: Write the test first.** Using Brain Monkey, stub `WC()` cart/customer/session and posted data, assert `build($posted)` returns the expected token map. Cases (also written to `condition-data-cases.json` for JS parity):
  - guest + empty cart → `user_role=guest`, `cart_qty=0`, `cart_subtotal=0`.
  - logged-in wholesale + cart has category 12 + product 99 + qty 3 + subtotal 150 + COD + ship flat_rate:1 + billing DE → exact tokens.
  - submitted field `vat_id=DE123` → `field:vat_id => 'DE123'`.

```php
public function test_build_maps_sources_to_values(): void {
    // ...stub WC() cart/customer/session...
    $values = CheckoutConditionData::build( [ 'payment_method' => 'cod', 'clcf' => [ 'vat_id' => 'DE123' ] ] );
    $this->assertSame( 'wholesale', $values['user_role'] );
    $this->assertSame( 'yes', $values['cart_has_category:12'] );
    $this->assertSame( 'cod', $values['payment_method'] );
    $this->assertSame( 'DE123', $values['field:vat_id'] );
}
```

- [ ] **Step 2: Implement.** Public API:

```php
final class CheckoutConditionData {
	/** @param array<string,mixed> $posted raw posted checkout data (classic $_POST or Store API request) */
	public static function build( array $posted = array(), ?array $field_group = null ): array {
		$values = array();
		// submitted field values (namespaced under 'clcf')
		$fields = isset( $posted['clcf'] ) && is_array( $posted['clcf'] ) ? $posted['clcf'] : array();
		foreach ( $fields as $id => $val ) {
			$values[ 'field:' . sanitize_key( (string) $id ) ] = is_scalar( $val ) ? (string) $val : '';
		}
		// context sources
		$user            = wp_get_current_user();
		$values['user_role'] = ( $user && ! empty( $user->roles ) ) ? (string) $user->roles[0] : 'guest';
		$cart            = function_exists( 'WC' ) ? WC()->cart : null;
		$qty = 0; $subtotal = 0.0; $cat_ids = array(); $prod_ids = array();
		if ( $cart ) {
			foreach ( $cart->get_cart() as $item ) {
				$pid = (int) ( $item['product_id'] ?? 0 );
				$prod_ids[ $pid ] = true;
				foreach ( wc_get_product_term_ids( $pid, 'product_cat' ) as $tid ) { $cat_ids[ (int) $tid ] = true; }
			}
			$qty      = (int) $cart->get_cart_contents_count();
			$subtotal = (float) $cart->get_subtotal();
		}
		foreach ( array_keys( $cat_ids ) as $tid )  { $values[ 'cart_has_category:' . $tid ] = 'yes'; }
		foreach ( array_keys( $prod_ids ) as $pid ) { $values[ 'cart_has_product:' . $pid ] = 'yes'; }
		$values['cart_qty']      = (string) $qty;
		$values['cart_subtotal'] = (string) $subtotal;
		$chosen = function_exists( 'WC' ) && WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$values['shipping_method'] = isset( $chosen[0] ) ? (string) $chosen[0] : '';
		$values['payment_method']  = isset( $posted['payment_method'] ) ? sanitize_text_field( (string) $posted['payment_method'] ) : '';
		$values['billing_country']  = isset( $posted['billing_country'] )  ? sanitize_text_field( (string) $posted['billing_country'] )  : ( $cart ? (string) WC()->customer->get_billing_country() : '' );
		$values['shipping_country'] = isset( $posted['shipping_country'] ) ? sanitize_text_field( (string) $posted['shipping_country'] ) : ( $cart ? (string) WC()->customer->get_shipping_country() : '' );
		return apply_filters( 'clcf_condition_values', $values, $posted, $field_group );
	}
}
```
NOTE: `cart_has_category:<id>`/`cart_has_product:<id>` are only set to `yes` when present; absent ⇒ engine reads `''` ⇒ `is yes` is false (correct). The `clcf_condition_values` filter is the Pro/extensibility hook.

- [ ] **Step 3: Run** → PASS. **Commit.**

### Task 3.2: JS mirror — data-sources.js

**Files:**
- Create: `src/checkout-conditions/data-sources.js`, `tests/js/data-sources.test.js`

- [ ] **Step 1: Test** (Jest) `buildValues(ctx)` against `condition-data-cases.json`, where `ctx` is a plain object simulating the two environments:
  - classic: `{ fields:{vat_id:'DE123'}, role, cart:{categories:[12], products:[99], qty:3, subtotal:150}, shippingMethod, paymentMethod, billingCountry, shippingCountry }`.
  - The function returns the **same token map** as the PHP fixture expects.
- [ ] **Step 2: Implement** `buildValues(ctx)` producing identical tokens (`field:<id>`, `user_role`, `cart_has_category:<id>`, `cart_qty`, `payment_method`, …). Keep it environment-agnostic; the classic/block adapters (Chunks 5–6) gather `ctx` from the DOM or `wc/store/*` and call `buildValues`.
- [ ] **Step 3: Add a cross-language parity test** note: `condition-data-cases.json` is consumed by both `CheckoutConditionDataTest.php` and `data-sources.test.js`. Run both → PASS. **Commit.**

---

## Chunk 4: Classic checkout — render, validate, persist, display

### Task 4.1: Sanitizer & Validator (reuse)

**Files:**
- Create: `includes/Checkout/Sanitizer.php`, `includes/Checkout/Validator.php`, `tests/php/Unit/SanitizerTest.php`, `tests/php/Unit/ValidatorTest.php`

- [ ] **Step 1:** Copy sibling `Cart/OptionSanitizer.php` → `Checkout/Sanitizer.php` (rename ns). Keep type cases for text/textarea/number/checkbox/radio/select; drop swatch/date (Pro). Copy its test, adapt.
- [ ] **Step 2:** Copy `Cart/Validator.php` → `Checkout/Validator.php`. Keep required + conditional-required (`ConditionalLogic::active_map` + `requires`) + type/constraint checks. **Remove** price-related validation. The `values` passed to `active_map` must be the **token map** from `CheckoutConditionData::build($posted)` merged with submitted field values — write the validator to accept `(array $group, array $submitted, array $values)` where `$submitted` is the raw field id→value and `$values` is the token map (so a field is validated only when active per the full context).
- [ ] **Step 3:** Adapt `ValidatorTest` for the token-map signature; run → PASS. **Commit.**

### Task 4.2: ClassicRenderer

**Files:**
- Create: `includes/Checkout/ClassicRenderer.php`, `tests/php/Unit/ClassicRendererTest.php`

- [ ] **Step 1: Test** (Brain Monkey): given a field group with one always-shown text field and one role-gated field, `render()` (captured via output buffering) outputs the visible field's markup, marks the role-gated field with a `hidden` attribute + `data-clcf-id`, and emits the `<script type="application/json" class="clcf-rules">` payload (field group) and a `<script class="clcf-values">` seed (initial token map) for the runtime. Field inputs are named `clcf[<id>]`.
- [ ] **Step 2: Implement** `register()` hooking the configured region (default `woocommerce_after_order_notes`; map the 4 region options to: `woocommerce_checkout_before_customer_details`/`woocommerce_after_checkout_billing_form`/`woocommerce_before_order_notes`/`woocommerce_after_order_notes`). `render()`:
  1. `$group = (new FieldRepository())->get()`. If no fields, return.
  2. `$values = CheckoutConditionData::build( $_POST ?? [] )` (initial server-side state; on first GET render `$_POST` is empty → context still has cart/role).
  3. `$active = ConditionalLogic::active_map( $group, $values )`.
  4. Output a `<div class="clcf-fields">`; per field render label + input (escaped), add `hidden` + `aria-hidden` when `! $active[id]`, add `required`/`aria-required` when required-or-conditionally-required.
  5. Emit `clcf-rules` (group JSON, `JSON_HEX_*`) and `clcf-values` (token map) scripts for the runtime.
- [ ] **Step 3:** `register()` also hooks `woocommerce_checkout_process` → run `Sanitizer` + `Validator`; on errors call `wc_add_notice( $msg, 'error' )`. (Validation uses freshly-built token map from `$_POST` so conditional-required reflects the submitted context.)
- [ ] **Step 4: Run** → PASS. **Commit.**

### Task 4.3: OrderPersistence + admin/email display

**Files:**
- Create: `includes/Checkout/OrderPersistence.php`, `tests/php/Unit/OrderPersistenceTest.php`

- [ ] **Step 1: Test** (mock `WC_Order`): `save($order, $posted)` computes the active field values and calls `$order->add_meta_data('_clcf_fields', wp_json_encode($activeValues), true)` plus one human-readable `$order->add_meta_data($label, $display, true)` per active non-empty field. Hidden fields are excluded.
- [ ] **Step 2: Implement** `register()`:
  - classic: `add_action('woocommerce_checkout_create_order', [$this,'save_classic'], 10, 2)` reading `$data` (posted) → `save($order, $posted)`.
  - display admin: `woocommerce_admin_order_data_after_billing_address` → render the stored human-readable pairs.
  - display email: `woocommerce_email_order_meta` → same.
  - `format_value()` (reuse the sibling's friendly checkbox→"Yes"/trimmed number logic).
- [ ] **Step 3: Run** → PASS. **Commit.**

### Task 4.4: Wire into Plugin::boot (classic path)

**Files:**
- Create: `includes/Plugin.php`

- [ ] **Step 1:** Implement `Plugin` singleton (copy sibling pattern) wiring, in `boot()`: `class_exists` guard for `Pro\ProModule`; then `RestController`, `BuilderAssets`, `SettingsPage`, `ClassicRenderer`, `OrderPersistence`, `FrontendAssets`, and `BlockIntegration` (added in Chunk 5).
- [ ] **Step 2:** `tests/php/Unit/PluginBootTest.php` — assert `boot()` is idempotent and registers without fatal (Brain Monkey, stub `add_action`/`add_filter`). Run → PASS. **Commit.**

---

## Chunk 5: Block checkout integration (primary risk — spike first)

### Task 5.0: SPIKE — validate the Block extensibility approach in wp-env

> Do this before building 5.1–5.3. The exact Store API surface varies by WooCommerce version; confirm against the installed version.

- [ ] **Step 1:** `npm run env:start`; activate WooCommerce + this plugin; switch the Checkout page to the **Checkout block**.
- [ ] **Step 2:** Prove each of these works in the running env (throwaway code, then revert):
  1. Register an `IntegrationInterface` via `add_action('woocommerce_blocks_checkout_block_registration', fn($r) => $r->register(new SpikeIntegration()))` and confirm its script enqueues on the block checkout.
  2. From the client, render a trivial component into the checkout using `registerCheckoutBlock` (with a `block.json` + a forced/inner block) **or** the `ExperimentalOrderMeta` slot — pick whichever renders in an acceptable region; record the choice.
  3. Push a value with `useCheckoutExtensionData().setExtensionData('clcf', 'fields', {...})` and confirm it arrives server-side in `woocommerce_store_api_checkout_update_order_from_request` under `$request['extensions']['clcf']`.
  4. Expose cart context to the client via `woocommerce_store_api_register_endpoint_data()` (namespace `clcf`) and read it with `select('wc/store/cart').getCartData().extensions.clcf`.
  5. Throw `\Automattic\WooCommerce\StoreApi\Exceptions\RouteException` from the update hook and confirm it surfaces as a checkout error.
- [ ] **Step 2b:** If any mechanism is unavailable in the target WC version, record the working alternative in this plan before proceeding (update Task 5.1–5.3 accordingly). **This is the one place the plan may need revision against reality.**
- [ ] **Step 3:** Revert spike code. Commit a short note to `docs/superpowers/notes/block-spike-findings.md`.

### Task 5.1: BlockIntegration — server (Store API)

**Files:**
- Create: `includes/Checkout/BlockIntegration.php`, `tests/php/Unit/BlockIntegrationTest.php`

- [ ] **Step 1: Test** (Brain Monkey, light): `register()` hooks `woocommerce_blocks_checkout_block_registration` (asset/integration) and `woocommerce_store_api_checkout_update_order_from_request`; `update_order_from_request($order, $request)` extracts `$request['extensions']['clcf']['fields']`, builds the token map, validates (reusing `Validator`), throws `RouteException` on error, else delegates to `OrderPersistence::save($order, $posted)`.
- [ ] **Step 2: Implement** server side:
  - `woocommerce_store_api_register_endpoint_data()` with namespace `clcf`, `schema_callback` exposing the field-group + computed context tokens (so the client can resolve conditions), `data_callback` returning current values, `schema_type = ARRAY_A`.
  - `IntegrationInterface` (`Checkout/BlockFieldsIntegration.php`) declaring name `clcf`, enqueuing `build/block-checkout.js` + style, and passing script data (field group, sources, i18n) via `get_script_data()`.
  - register it on `woocommerce_blocks_checkout_block_registration`.
  - `update_order_from_request`: validate + persist as above.
- [ ] **Step 3: Run** unit tests → PASS. **Commit.**

### Task 5.2: Block client component

**Files:**
- Create: `src/block-checkout/index.js`, `src/block-checkout/block.json`, `src/block-checkout/Fields.jsx`

- [ ] **Step 1:** `block.json` metadata + `index.js` registering the checkout block (per the spike's chosen mechanism) rendering `<Fields/>` in the dedicated region.
- [ ] **Step 2:** `<Fields/>`:
  - reads the field group + context from `getScriptData()` / Store API extensions;
  - builds the live token map via `buildValues(ctx)` where `ctx` is assembled from `select('wc/store/cart')`, `select('wc/store/checkout')`, the chosen `payment_method` (payment store), and current field inputs;
  - computes visibility with `activeMap(fields, values)`; renders only active fields with `@wordpress/components` inputs;
  - on change, updates local state and calls `setExtensionData('clcf','fields', values)` so data posts to the Store API;
  - subscribes to store changes (cart/shipping/payment) so conditions re-resolve live.
- [ ] **Step 3:** `npm run build` → confirm `build/block-checkout.js` + `.asset.php` emitted. **Commit.**

### Task 5.3: Classic⇄Block parity test

**Files:**
- Create: `tests/js/parity.test.js`

- [ ] **Step 1:** A Jest test that, for each case in `condition-data-cases.json`, runs `buildValues(ctx)` then `activeMap(fields, values)` and asserts the active set equals a hand-specified expected set — the **same** `fields` + `values` the PHP `active_map` would produce (cross-checked by a sibling PHP fixture test). This guards that both checkout surfaces resolve visibility identically.
- [ ] **Step 2: Run** → PASS. **Commit.**

---

## Chunk 6: Front-end runtime (classic) & assets

### Task 6.1: Classic runtime (live show/hide)

**Files:**
- Create: `src/frontend.js`

- [ ] **Step 1:** Bootstrap on `form.checkout`: parse `script.clcf-rules` (group) + `script.clcf-values` (seed token map). Build a `ctx` from the classic DOM + the seed; compute `activeMap`; toggle field visibility/required. Re-run on `change` of our inputs and on WooCommerce's `updated_checkout` event (fires when shipping/payment/address change) so context-source conditions update live. Reuse `activeMap`/`fieldActive` from `frontend/conditional-logic.js` and `buildValues` from `checkout-conditions/data-sources.js`.
- [ ] **Step 2:** No new unit test beyond the parity test (DOM wiring verified in live QA 6.3). **Commit.**

### Task 6.2: FrontendAssets

**Files:**
- Create: `includes/Frontend/FrontendAssets.php`

- [ ] **Step 1:** Adapt the sibling: enqueue `build/frontend.js` on `is_checkout()` (classic) only; inject `CLCF_FE` (currency/decimals/i18n + the source-token context the runtime needs that isn't in the DOM, e.g. cart categories/products/role, so the client can resolve cart/role conditions without an AJAX call). Localize script translations.
- [ ] **Step 2: Commit.**

### Task 6.3: LIVE QA — both checkouts (wp-env)

- [ ] **Step 1:** `npm run build && npm run env:start`.
- [ ] **Step 2: Classic checkout:** build a group in admin (e.g. text "VAT ID" shown when `user_role is wholesale`; "Delivery note" textarea shown when `shipping_method is local_pickup`; required checkbox "Age 18+"). Place an order on the **classic** checkout: verify live show/hide as role/shipping/payment change, required validation blocks submit, values land on the order + admin + email.
- [ ] **Step 3: Block checkout:** switch Checkout page to the block; repeat. Verify identical behavior, Store API submit, validation errors surface, order meta written.
- [ ] **Step 4:** `tests/integration-smoke.php` (wp-cli): seed a group, simulate both order paths, assert order meta `_clcf_fields` present and a conditional-required failure path. Run via `wp eval-file`.
- [ ] **Step 5:** Fix any bugs found (expect at least one — the flagship's live QA always caught a real bug). Re-run. **Commit** fixes. `npm run env:stop`.

---

## Chunk 7: Pro plugin, packaging & wp.org prep

### Task 7.1: Pro plugin sanity

- [ ] **Step 1:** With the Pro unlocker active in wp-env, confirm `clcf_field_types` exposes `file`/`date` in the builder (types appear but their full impl is a later Pro release — ensure the free build degrades gracefully: unknown types are dropped by `FieldSchema::normalize`, so a free site never renders them). Document that v1 Pro ships only the gate + type/price-mode unlock; full Pro field impl is a follow-up.
- [ ] **Step 2:** Verify deactivating Pro flips the gate off. **Commit** any guard fixes.

### Task 7.2: Plugin Check + i18n + readme + assets

- [ ] **Step 1:** Install & run the official **Plugin Check** in wp-env; fix all code-level findings (escaping, sanitization via `map_deep`/`sanitize_*`, text-domain match, `tested up to`). Dev/test files are excluded by `.distignore`.
- [ ] **Step 2:** Generate `.pot` → `languages/corelabs-checkout-fields.pot` (`wp i18n make-pot`). Add `wp_set_script_translations` for both `clcf-builder` and `clcf-frontend` (+ block script).
- [ ] **Step 3:** Finalize `readme.txt`: full free feature set, honest Pro mention, FAQ, changelog, "Tested up to" = the wp-env WP version. Add `== Development ==` build section (ships `/src` + `composer.json`).
- [ ] **Step 4:** Listing assets in `.wordpress-org/` (icon, banner, 2 screenshots — builder + a conditional field on block checkout). **Commit.**

### Task 7.3: Build the distributable

- [ ] **Step 1:** `npm run build` then `npm run dist` (`bin/build-dist.sh`). Verify `dist/corelabs-checkout-fields.zip`:
  - ships `build/`, `src/`, `composer.json`, vendor autoloader (no dev deps);
  - **excludes** `includes/Pro`, the Pro plugin, `tests/`, `docs/`, `bin/`, dotfiles;
  - separate `dist/corelabs-checkout-fields-pro.zip` built.
- [ ] **Step 2:** Unzip to a clean WP+WC site (or fresh wp-env) and smoke-activate to confirm the free zip runs without the Pro code present (`class_exists` guard holds).
- [ ] **Step 3:** Final `composer test` + `npm run test:js` green. **Commit** + tag `v1.0.0` (do NOT push unless the user asks — per the user's git rules, branch/commit only; no `git add -A`, no Co-Authored-By).

---

## Definition of done (v1 free)
- Admin builds checkout fields with full conditional logic (multi-condition AND/OR, all operators, all sources) on a central page.
- Fields render + conditionally show/hide/require **live** on **both** classic and Block checkout.
- Values validate (incl. conditional-required) server-side on both paths and persist to the order (HPOS-safe), visible in admin + emails.
- PHP + JS test suites green (incl. PHP↔JS engine parity and classic⇄block visibility parity).
- Clean Plugin-Check; free `dist` zip excludes Pro; Pro unlocker flips the gate.

## Open items deferred to planning-during-execution
1. Exact Block render region (decided in the 5.0 spike).
2. Whether `file`/`date` Pro field types ship in the first Pro release or later (not v1-free regardless).
3. wp.org submission timing vs own-channel-first (mirrors the flagship's open decision).
