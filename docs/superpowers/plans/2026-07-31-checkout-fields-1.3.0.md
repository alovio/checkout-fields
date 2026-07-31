# Checkout Fields 1.3.0 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make checkout fees transparent — a price per option, one fee line per field — and let one upload field take several files.

**Architecture:** `FeeCalculator` gains a `breakdown()` that returns per-field rows; `total()` becomes their sum, so display and billing read one function. Option values become polymorphic (a bare string until they carry a price), read everywhere through a new `FieldOptions` helper. `FeesIntegration` emits one WooCommerce fee per row using the fees API with explicit ids.

**Tech Stack:** PHP 7.4+, WordPress/WooCommerce, PHPUnit + Brain Monkey, React (`@wordpress/scripts`), Jest.

**Spec:** `docs/superpowers/specs/2026-07-31-checkout-fields-1.3.0-design.md`

---

## Conventions for every task

- Run PHP tests with `vendor/bin/phpunit --colors=never`. Baseline before you start: **OK (61 tests, 131 assertions)**.
- Run JS tests with `npm run test:js`. Baseline: **5 suites, 44 tests**. Never `npx jest` — it fails on ESM.
- Build JS with `npm run build` before any browser check.
- Commit with `git -c user.name='Tahir' -c user.email='ttaxiir@gmail.com' commit -m "…"`. Never `git add -A`; stage the exact files.
- Never add `Co-Authored-By` lines.
- Text domain is `corelabs-checkout-fields`; PHP prefix `clcf_`; CSS prefix `clcf-`.

## File Structure

**New files:**
| File | Responsibility |
| --- | --- |
| `includes/Fields/FieldOptions.php` | The only reader of an option's shape: label, price, effective price, range |
| `src/shared/options.js` | JS twin of the above (label, price, effectivePrice, hasPricedOptions) |
| `src/shared/FieldIcon.jsx` | One stroked icon per field type |
| `src/shared/FlameMark.jsx` | The Alovio flame |
| `src/builder/panels/Options.jsx` | Row-per-choice editor with a price box |
| `tests/fixtures/option-pricing-cases.json` | Shared PHP↔JS pricing fixture |
| `tests/php/Unit/FieldOptionsTest.php` | |
| `tests/php/Unit/ClassicRendererOptionsTest.php` | Renderer output asserted against FeeCalculator |
| `tests/js/options.test.js` | |
| `bin/svn-deploy.sh` | Ported from the sibling plugin |

**Modified:**
| File | Change |
| --- | --- |
| `includes/Fields/FieldSchema.php:202` | `normalize_options()` preserves prices; whitelist gains `maxFiles` |
| `includes/Checkout/FeeCalculator.php` | `breakdown()` added; `total()` sums it; per-option effective price |
| `includes/Checkout/FeesIntegration.php` | Itemized emission via `fees_api()`; mode selection |
| `includes/Checkout/Sanitizer.php:72` | Option match through `FieldOptions::labels()` |
| `includes/Checkout/Validator.php:94` | Same |
| `includes/Checkout/ClassicRenderer.php:169,176` | Option label + price suffix |
| `includes/Checkout/FileUploads.php` | `parse_tokens()`, WEBP, rate limit moved |
| `includes/Checkout/OrderPersistence.php` | One entry per file; legacy fallback kept |
| `src/block-checkout/index.js` | Option labels, fee gate, multi-file uploader |
| `src/frontend.js` | Fee gate, multi-file uploader |
| `src/builder/panels/General.jsx` | Textarea removed; default list via `optionLabel` |
| `src/builder/panels/Logic.jsx:92` | Rule values via `optionLabel` |
| `src/builder/FieldPreview.jsx` | Option labels + icons |
| `src/builder/Palette.jsx`, `SettingsPanel.jsx`, `AppShell.jsx` | Icons + flame |
| `assets/css/builder.css:74,134,279` | SVG-appropriate rules |
| `readme.txt`, `corelabs-checkout-fields.php` | Version, changelog, Upgrade Notice |

---

## Chunk 1: Option shape foundation

Nothing else works until options can carry a price through `FieldSchema`. Do this first.

### Task 1: `FieldOptions` helper

**Files:**
- Create: `includes/Fields/FieldOptions.php`
- Test: `tests/php/Unit/FieldOptionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Fields\FieldOptions;

final class FieldOptionsTest extends TestCase {

	private function speeds(): array {
		return array(
			'id'      => 'speed',
			'type'    => 'select',
			'price'   => 0,
			'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ),
		);
	}

	public function test_label_reads_both_shapes(): void {
		$this->assertSame( 'Standard', FieldOptions::label( 'Standard' ) );
		$this->assertSame( 'Express', FieldOptions::label( array( 'label' => 'Express', 'price' => 10 ) ) );
	}

	public function test_price_only_counts_positive_numbers(): void {
		$this->assertSame( 10.0, FieldOptions::price( array( 'label' => 'a', 'price' => 10 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( 'a' ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => -5 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => 'free' ) ) );
	}

	public function test_labels_lists_both_shapes(): void {
		$this->assertSame( array( 'Standard', 'Express' ), FieldOptions::labels( $this->speeds() ) );
	}

	public function test_effective_price_prefers_the_option_then_the_field(): void {
		$f = $this->speeds();
		$f['price'] = 4.0;
		$this->assertSame( 4.0, FieldOptions::effective_price( $f, 'Standard' ) );
		$this->assertSame( 10.0, FieldOptions::effective_price( $f, 'Express' ) );
		$this->assertSame( 4.0, FieldOptions::effective_price( $f, 'Unknown' ) );
	}

	public function test_options_never_price_a_non_choice_field(): void {
		$f = array( 'type' => 'text', 'price' => 5, 'options' => array( array( 'label' => 'gold', 'price' => 500 ) ) );
		$this->assertSame( 0.0, FieldOptions::price_for_value( $f, 'gold' ) );
		$this->assertSame( 5.0, FieldOptions::effective_price( $f, 'gold' ) );
	}

	public function test_duplicate_labels_resolve_to_the_first_match(): void {
		$f = array(
			'type'    => 'select',
			'options' => array( array( 'label' => 'A', 'price' => 3 ), array( 'label' => 'A', 'price' => 9 ) ),
		);
		$this->assertSame( 3.0, FieldOptions::effective_price( $f, 'A' ) );
	}

	public function test_non_scalar_value_never_matches(): void {
		$this->assertSame( 0.0, FieldOptions::price_for_value( $this->speeds(), array( 'Express' ) ) );
	}

	public function test_has_priced_options_and_range(): void {
		$this->assertTrue( FieldOptions::has_priced_options( $this->speeds() ) );
		$this->assertSame( array( 10.0, 10.0 ), FieldOptions::price_range( $this->speeds() ) );

		$plain = array( 'type' => 'select', 'options' => array( 'A', 'B' ) );
		$this->assertFalse( FieldOptions::has_priced_options( $plain ) );
		$this->assertSame( array( 0.0, 0.0 ), FieldOptions::price_range( $plain ) );
	}

	public function test_range_covers_the_field_price_fallback(): void {
		$f = $this->speeds();
		$f['price'] = 4.0;
		$this->assertSame( array( 4.0, 10.0 ), FieldOptions::price_range( $f ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter FieldOptionsTest`
Expected: FAIL — `Class "CoreLabs\CheckoutFields\Fields\FieldOptions" not found`

- [ ] **Step 3: Write the implementation**

Create `includes/Fields/FieldOptions.php`. Port from
`/Users/tahir/woo-product-options/includes/Fields/FieldOptions.php`, changing the
namespace to `CoreLabs\CheckoutFields\Fields` and `CHOICE_TYPES` to `array( 'select', 'radio' )`
(this plugin has no swatch/buttons/image_swatch types). Keep all seven methods:
`label()`, `price()`, `labels()`, `price_for_value()`, `effective_price()`,
`has_priced_options()`, `price_range()`.

Two behaviours the tests above pin, both present in the sibling:
- `price_for_value()` returns 0 unless `$field['type']` is in `CHOICE_TYPES`, and rejects non-scalar values via `is_scalar()`.
- `price_range()` uses `effective_price()` per option, so unpriced options contribute the field price.

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit --filter FieldOptionsTest`
Expected: PASS (10 tests)

- [ ] **Step 5: Commit**

```bash
git add includes/Fields/FieldOptions.php tests/php/Unit/FieldOptionsTest.php
git commit -m "feat: FieldOptions — the one reader of an option's shape"
```

### Task 2: `normalize_options()` preserves prices

This is the blocker: today every option is flattened to a bare string on both read and
write, so a price could never reach the database.

**Files:**
- Modify: `includes/Fields/FieldSchema.php:196-210`
- Test: `tests/php/Unit/FieldSchemaTest.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/php/Unit/FieldSchemaTest.php`:

```php
	public function test_option_prices_survive_normalization_and_shapes_stay_lean(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'      => 'speed',
						'type'    => 'select',
						'options' => array(
							'Standard',
							array( 'label' => 'Express', 'price' => 10 ),
							array( 'label' => 'Zero', 'price' => 0 ),
						),
					),
				),
			)
		);
		$opts = $out['fields'][0]['options'];
		$this->assertSame( 'Standard', $opts[0], 'an unpriced choice stays a bare string' );
		$this->assertSame( array( 'label' => 'Express', 'price' => 10.0 ), $opts[1] );
		$this->assertSame( 'Zero', $opts[2], 'a zero price collapses back to a string' );
	}

	public function test_negative_and_non_numeric_option_prices_are_dropped(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'      => 'speed',
						'type'    => 'radio',
						'options' => array(
							array( 'label' => 'A', 'price' => -5 ),
							array( 'label' => 'B', 'price' => 'free' ),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'A', 'B' ), $out['fields'][0]['options'] );
	}

	public function test_empty_label_options_are_dropped(): void {
		$out = FieldSchema::normalize(
			array( 'fields' => array( array( 'id' => 's', 'type' => 'select', 'options' => array( 'A', '', '  ' ) ) ) )
		);
		$this->assertSame( array( 'A' ), $out['fields'][0]['options'] );
	}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter FieldSchemaTest`
Expected: FAIL — the first assertion passes but `$opts[1]` is the string `'Express'`, not an array.

- [ ] **Step 3: Rewrite `normalize_options()`**

Replace `includes/Fields/FieldSchema.php:196-210` with:

```php
	/**
	 * Options keep the leanest shape that carries their data: a plain string
	 * when a choice is just a label, an object once it needs a price. Groups
	 * saved before per-option pricing therefore normalize to exactly what they
	 * already were — no migration.
	 *
	 * @param array<string,mixed> $f
	 * @return array<int,mixed>
	 */
	private static function normalize_options( array $f ): array {
		$raw = ( isset( $f['options'] ) && is_array( $f['options'] ) ) ? $f['options'] : array();
		$out = array();

		foreach ( $raw as $o ) {
			$label = trim( sanitize_text_field( FieldOptions::label( $o ) ) );
			if ( '' === $label ) {
				continue; // an unlabelled choice cannot be selected.
			}
			$price = FieldOptions::price( $o );
			$out[] = $price > 0 ? array( 'label' => $label, 'price' => $price ) : $label;
		}

		return $out;
	}
```

Add the import at the top of the file, after the namespace declaration:

```php
use CoreLabs\CheckoutFields\Fields\FieldOptions;
```

(`FieldSchema` is already in that namespace, so if PHP complains about importing from
the same namespace, drop the `use` line — the class resolves unqualified.)

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit --filter FieldSchemaTest`
Expected: PASS

- [ ] **Step 5: Run the whole PHP suite**

Run: `vendor/bin/phpunit --colors=never`
Expected: OK — 61 baseline + the new tests, no failures. If `SanitizerTest` or
`ValidatorTest` now fail, that is Chunk 3's work; note it and continue.

- [ ] **Step 6: Commit**

```bash
git add includes/Fields/FieldSchema.php tests/php/Unit/FieldSchemaTest.php
git commit -m "fix: keep per-option prices through normalization

normalize_options() flattened every option to a bare string on both read
and write, so a price could never reach the database. Options now stay
strings until they carry a price. Empty-label options are dropped — an
unlabelled choice is unselectable."
```

### Task 3: `maxFiles` survives the schema whitelist

**Files:**
- Modify: `includes/Fields/FieldSchema.php` (the `$entry` array at :60-78, and `normalize_constraints()`)
- Test: `tests/php/Unit/FieldSchemaTest.php` (append)

- [ ] **Step 1: Write the failing test**

```php
	public function test_file_max_files_clamped_to_1_10(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'f1', 'type' => 'file' ),
					array( 'id' => 'f2', 'type' => 'file', 'maxFiles' => 5 ),
					array( 'id' => 'f3', 'type' => 'file', 'maxFiles' => 99 ),
					array( 'id' => 'f4', 'type' => 'file', 'maxFiles' => 0 ),
				),
			)
		);
		$this->assertSame( 1, $out['fields'][0]['maxFiles'] );
		$this->assertSame( 5, $out['fields'][1]['maxFiles'] );
		$this->assertSame( 10, $out['fields'][2]['maxFiles'] );
		$this->assertSame( 1, $out['fields'][3]['maxFiles'] );
	}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter test_file_max_files`
Expected: FAIL — undefined index `maxFiles`.

- [ ] **Step 3: Add the constraint**

In `normalize_constraints()`, add before the final `return array();`:

```php
		if ( 'file' === $type ) {
			$max = ( isset( $f['maxFiles'] ) && is_numeric( $f['maxFiles'] ) ) ? (int) $f['maxFiles'] : 1;
			return array( 'maxFiles' => min( 10, max( 1, $max ) ) );
		}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit --filter test_file_max_files`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add includes/Fields/FieldSchema.php tests/php/Unit/FieldSchemaTest.php
git commit -m "feat: maxFiles field property (1-10)"
```

### Task 4: JS twin + shared fixture

**Files:**
- Create: `src/shared/options.js`, `tests/fixtures/option-pricing-cases.json`, `tests/js/options.test.js`

- [ ] **Step 1: Write the fixture**

Create `tests/fixtures/option-pricing-cases.json`. Each case is one field, a submitted
value, a base, and the amount that field must charge:

```json
{
  "_comment": "Shared PHP<->JS parity cases. PHP: FeeCalculatorTest::test_shared_option_pricing_fixtures. JS: options.test.js 'shared fixtures'.",
  "cases": [
    { "name": "option price wins over an absent field price",
      "field": { "id": "speed", "type": "select", "price": 0, "priceMode": "fixed",
                 "options": [ "Standard", { "label": "Express", "price": 10 } ] },
      "value": "Express", "base": 0, "expected": 10 },
    { "name": "unpriced option falls back to the field price",
      "field": { "id": "speed", "type": "select", "price": 4, "priceMode": "fixed",
                 "options": [ "Standard", { "label": "Express", "price": 10 } ] },
      "value": "Standard", "base": 0, "expected": 4 },
    { "name": "option price runs through percent mode",
      "field": { "id": "speed", "type": "radio", "price": 0, "priceMode": "percent",
                 "options": [ { "label": "Express", "price": 10 } ] },
      "value": "Express", "base": 200, "expected": 20 },
    { "name": "a value matching no option charges nothing",
      "field": { "id": "speed", "type": "select", "price": 0, "priceMode": "fixed",
                 "options": [ { "label": "Express", "price": 10 } ] },
      "value": "Express (+$10.00)", "base": 0, "expected": 0 },
    { "name": "duplicate labels both charge the first match",
      "field": { "id": "speed", "type": "select", "price": 0, "priceMode": "fixed",
                 "options": [ { "label": "A", "price": 3 }, { "label": "A", "price": 9 } ] },
      "value": "A", "base": 0, "expected": 3 },
    { "name": "a non-numeric option price is ignored, field price applies",
      "field": { "id": "speed", "type": "select", "price": 2, "priceMode": "fixed",
                 "options": [ { "label": "Odd", "price": "10abc" } ] },
      "value": "Odd", "base": 0, "expected": 2 },
    { "name": "options on a non-choice field never price it",
      "field": { "id": "note", "type": "text", "price": 5, "priceMode": "fixed",
                 "options": [ { "label": "gold", "price": 500 } ] },
      "value": "gold", "base": 0, "expected": 5 },
    { "name": "an empty selection charges nothing",
      "field": { "id": "speed", "type": "select", "price": 0, "priceMode": "fixed",
                 "options": [ { "label": "Express", "price": 10 } ] },
      "value": "", "base": 0, "expected": 0 }
  ]
}
```

- [ ] **Step 2: Write the failing JS test**

Create `tests/js/options.test.js`:

```js
import {
	optionLabel, optionPrice, optionLabels, optionPriceForValue,
	hasPricedOptions, effectivePrice, priceRange,
} from '../../src/shared/options';

const SPEEDS = { id: 'speed', type: 'select', price: 0, options: [ 'Standard', { label: 'Express', price: 10 } ] };

describe( 'option shape helpers', () => {
	it( 'reads labels from both shapes', () => {
		expect( optionLabel( 'Standard' ) ).toBe( 'Standard' );
		expect( optionLabel( { label: 'Express', price: 10 } ) ).toBe( 'Express' );
		expect( optionLabel( null ) ).toBe( '' );
	} );

	it( 'only counts positive numeric prices', () => {
		expect( optionPrice( { label: 'a', price: 10 } ) ).toBe( 10 );
		expect( optionPrice( 'a' ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: -5 } ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: '10abc' } ) ).toBe( 0 );
	} );

	it( 'lists labels', () => {
		expect( optionLabels( SPEEDS ) ).toEqual( [ 'Standard', 'Express' ] );
	} );

	it( 'prefers the option price over the field price', () => {
		const f = { ...SPEEDS, price: 4 };
		expect( effectivePrice( f, 'Standard' ) ).toBe( 4 );
		expect( effectivePrice( f, 'Express' ) ).toBe( 10 );
	} );

	it( 'never lets options price a non-choice field', () => {
		const f = { type: 'text', price: 5, options: [ { label: 'gold', price: 500 } ] };
		expect( optionPriceForValue( f, 'gold' ) ).toBe( 0 );
		expect( effectivePrice( f, 'gold' ) ).toBe( 5 );
	} );

	it( 'detects priced options and spans the fallback', () => {
		expect( hasPricedOptions( SPEEDS ) ).toBe( true );
		expect( hasPricedOptions( { type: 'select', options: [ 'A' ] } ) ).toBe( false );
		expect( priceRange( { ...SPEEDS, price: 4 } ) ).toEqual( [ 4, 10 ] );
	} );
} );
```

- [ ] **Step 3: Run it to verify it fails**

Run: `npm run test:js -- options`
Expected: FAIL — cannot resolve `../../src/shared/options`.

- [ ] **Step 4: Write `src/shared/options.js`**

Port from `/Users/tahir/woo-product-options/src/shared/options.js` unchanged except
`CHOICE_TYPES = [ 'select', 'radio' ]`. It exports `optionLabel`, `optionPrice`,
`optionLabels`, `optionPriceForValue`, `hasPricedOptions`, `effectivePrice`,
`priceRange`. `optionPrice()` must gate on a full numeric match (mirroring PHP's
`is_numeric`), not bare `parseFloat`, so `"10abc"` is 0 on both sides.

- [ ] **Step 5: Run it to verify it passes**

Run: `npm run test:js -- options`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/shared/options.js tests/js/options.test.js tests/fixtures/option-pricing-cases.json
git commit -m "feat: JS twin of FieldOptions + shared pricing fixture"
```

---

## Chunk 2: Fees

### Task 5: `FeeCalculator::breakdown()`

**Files:**
- Modify: `includes/Checkout/FeeCalculator.php`
- Test: `tests/php/Unit/FeeCalculatorTest.php` (append)

- [ ] **Step 1: Write the failing tests**

```php
	public function test_breakdown_returns_one_row_per_charging_field(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'wrap', 'type' => 'checkbox', 'label' => 'Gift wrapping', 'price' => 8, 'priceMode' => 'fixed' ),
			array( 'id' => 'note', 'type' => 'text', 'label' => 'Note', 'price' => 0 ),
		) );
		$rows = FeeCalculator::breakdown( $g, array( 'wrap' => 'yes', 'note' => 'hi' ), array(), 0.0, 2 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'wrap', $rows[0]['field_id'] );
		$this->assertSame( 'Gift wrapping', $rows[0]['label'] );
		$this->assertSame( 8.0, $rows[0]['amount'] );
	}

	public function test_total_is_the_sum_of_the_rounded_rows(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'a', 'type' => 'checkbox', 'label' => 'A', 'price' => 3.333, 'priceMode' => 'fixed' ),
			array( 'id' => 'b', 'type' => 'checkbox', 'label' => 'B', 'price' => 3.333, 'priceMode' => 'fixed' ),
		) );
		$vals = array( 'a' => 'yes', 'b' => 'yes' );
		$rows = FeeCalculator::breakdown( $g, $vals, array(), 0.0, 2 );
		$this->assertSame( 3.33, $rows[0]['amount'] );
		$this->assertSame( 6.66, FeeCalculator::total( $g, $vals, array(), 0.0 ) );
	}

	public function test_rows_rounding_to_zero_are_skipped(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'tiny', 'type' => 'checkbox', 'label' => 'Tiny', 'price' => 0.4, 'priceMode' => 'percent' ),
		) );
		$rows = FeeCalculator::breakdown( $g, array( 'tiny' => 'yes' ), array(), 1.0, 2 );
		$this->assertSame( array(), $rows );
	}

	public function test_a_priced_option_lifts_a_zero_priced_field(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'speed', 'type' => 'select', 'label' => 'Shipping speed', 'price' => 0, 'priceMode' => 'fixed',
				'options' => array( 'Standard', array( 'label' => 'Overnight', 'price' => 12 ) ) ),
		) );
		$rows = FeeCalculator::breakdown( $g, array( 'speed' => 'Overnight' ), array(), 0.0, 2 );
		$this->assertCount( 1, $rows, 'the field price is 0 but the option charges' );
		$this->assertSame( 12.0, $rows[0]['amount'] );
		$this->assertSame( 'Shipping speed: Overnight', $rows[0]['label'] );
	}

	public function test_row_label_omits_the_value_for_non_choice_fields(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'wrap', 'type' => 'checkbox', 'label' => 'Gift wrapping', 'price' => 8 ),
		) );
		$rows = FeeCalculator::breakdown( $g, array( 'wrap' => 'yes' ), array(), 0.0, 2 );
		$this->assertSame( 'Gift wrapping', $rows[0]['label'] );
	}

	/** @dataProvider optionPricingProvider */
	public function test_shared_option_pricing_fixtures( array $case ): void {
		$g = array( 'fields' => array( $case['field'] ) );
		$this->assertSame(
			(float) $case['expected'],
			FeeCalculator::total( $g, array( $case['field']['id'] => $case['value'] ), array(), (float) $case['base'] ),
			$case['name']
		);
	}

	/** @return array<string,array{0:array}> */
	public function optionPricingProvider(): array {
		$raw   = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/option-pricing-cases.json' ), true );
		$cases = array();
		foreach ( $raw['cases'] as $c ) {
			$cases[ $c['name'] ] = array( $c );
		}
		return $cases;
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter FeeCalculatorTest`
Expected: FAIL — `breakdown()` undefined.

- [ ] **Step 3: Implement**

In `includes/Checkout/FeeCalculator.php`, add the import
`use CoreLabs\CheckoutFields\Fields\FieldOptions;` and replace `total()` with:

```php
	/**
	 * Per-field fee rows — the single source of truth for what the checkout
	 * charges and what it displays. Rows are rounded here so the printed lines,
	 * the printed total and the charged total agree.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values field id => submitted value
	 * @param array<string,mixed> $values       full token map (for visibility)
	 * @param float               $base         subtotal used for percentage fees
	 * @param int|null            $decimals     store decimals; resolved when null
	 * @return array<int,array{field_id:string,label:string,amount:float}>
	 */
	public static function breakdown( array $group, array $field_values, array $values, float $base = 0.0, ?int $decimals = null ): array {
		if ( null === $decimals ) {
			$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		}
		$modes  = self::modes();
		$active = ConditionalLogic::active_map( $group, $values );
		$rows   = array();

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id || empty( $active[ $id ] ) ) {
				continue;
			}
			$type = (string) ( $f['type'] ?? '' );
			$val  = $field_values[ $id ] ?? null;
			// A priced option lifts a field that carries no price of its own.
			$price = FieldOptions::effective_price( $f, $val );
			if ( $price <= 0 || ! self::is_engaged( $type, $val ) ) {
				continue;
			}

			$mode = (string) ( $f['priceMode'] ?? 'fixed' );
			if ( ! in_array( $mode, $modes, true ) ) {
				$mode = 'fixed';
			}

			if ( 'per_unit' === $mode && 'number' === $type ) {
				$amount = $price * (float) $val;
			} elseif ( 'percent' === $mode ) {
				$amount = $base * $price / 100;
			} else {
				$amount = $price;
			}

			$amount = round( $amount, $decimals );
			if ( $amount <= 0 ) {
				continue; // never emit a 0.00 fee line.
			}

			$rows[] = array(
				'field_id' => $id,
				'label'    => self::row_label( $f, $val ),
				'amount'   => $amount,
			);
		}

		return $rows;
	}

	/**
	 * What an itemised fee line reads as. Choice fields carry the chosen value,
	 * because "Shipping speed £12.00" alone does not say which speed.
	 *
	 * @param array<string,mixed> $f
	 * @param mixed               $val
	 */
	private static function row_label( array $f, $val ): string {
		$label = (string) ( $f['label'] ?? '' );
		if ( '' === $label ) {
			$label = (string) ( $f['id'] ?? '' );
		}
		if ( in_array( (string) ( $f['type'] ?? '' ), array( 'select', 'radio' ), true ) && is_scalar( $val ) && '' !== $val ) {
			return $label . ': ' . (string) $val;
		}
		return $label;
	}

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values
	 * @param array<string,mixed> $values
	 */
	public static function total( array $group, array $field_values, array $values, float $base = 0.0 ): float {
		$total = 0.0;
		foreach ( self::breakdown( $group, $field_values, $values, $base ) as $row ) {
			$total += $row['amount'];
		}
		return (float) apply_filters( 'clcf_fee_total', $total, $group, $field_values, $base );
	}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter FeeCalculatorTest`
Expected: PASS, including the 8 fixture cases.

- [ ] **Step 5: Run the whole suite**

Run: `vendor/bin/phpunit --colors=never`
Expected: OK

- [ ] **Step 6: Commit**

```bash
git add includes/Checkout/FeeCalculator.php tests/php/Unit/FeeCalculatorTest.php
git commit -m "feat: FeeCalculator::breakdown() — per-field rows, one source of truth

total() is now their sum. A priced option lifts a field whose own price is
zero, rows are rounded so printed lines add up, and rows rounding to zero
are dropped."
```

### Task 6: Itemized fee emission

**Files:**
- Modify: `includes/Checkout/FeesIntegration.php:81-98`
- Test: `tests/php/Unit/FeesIntegrationModeTest.php` (create)

- [ ] **Step 1: Write the failing test**

The mode decision is pure, so extract it as a static and test that directly:

```php
<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\FeesIntegration;
use Brain\Monkey;

final class FeesIntegrationModeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'has_filter' )->justReturn( false );
	}

	public function test_itemized_by_default(): void {
		$this->assertTrue( FeesIntegration::is_itemized() );
	}

	public function test_single_line_when_the_filter_says_so(): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			static fn( $tag, $value = null ) => 'clcf_itemized_fees' === $tag ? false : $value
		);
		$this->assertFalse( FeesIntegration::is_itemized() );
	}

	public function test_single_line_when_a_site_filters_the_total(): void {
		// An external filter rewrites the charged total; itemised rows cannot
		// honestly represent it, so we fall back to one line.
		Monkey\Functions\when( 'has_filter' )->alias( static fn( $tag ) => 'clcf_fee_total' === $tag );
		$this->assertFalse( FeesIntegration::is_itemized() );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter FeesIntegrationModeTest`
Expected: FAIL — `is_itemized()` undefined.

- [ ] **Step 3: Implement**

Add to `includes/Checkout/FeesIntegration.php`:

```php
	/**
	 * Itemized (one fee per field) unless a shop opted out, or unless a site is
	 * rewriting the charged total — itemised rows cannot represent a total that
	 * was adjusted outside them, so clcf_fee_total wins.
	 */
	public static function is_itemized(): bool {
		if ( has_filter( 'clcf_fee_total' ) ) {
			return false;
		}
		return (bool) apply_filters( 'clcf_itemized_fees', true );
	}
```

and replace the body of `add_fee()` after `$base` is computed:

```php
		if ( self::is_itemized() ) {
			foreach ( FeeCalculator::breakdown( $group, $field_values, $values, $base ) as $row ) {
				$cart->fees_api()->add_fee(
					array(
						'id'        => 'clcf-' . $row['field_id'],
						'name'      => $row['label'],
						'amount'    => $row['amount'],
						'taxable'   => false,
						'tax_class' => '',
					)
				);
			}
			return;
		}

		$fee = FeeCalculator::total( $group, $field_values, $values, $base );
		if ( $fee > 0 ) {
			$cart->fees_api()->add_fee(
				array(
					'id'        => 'clcf-checkout-options',
					'name'      => apply_filters( 'clcf_fee_label', __( 'Checkout options', 'corelabs-checkout-fields' ) ),
					'amount'    => $fee,
					'taxable'   => false,
					'tax_class' => '',
				)
			);
		}
```

Why the explicit `id`: `WC_Cart::add_fee()` derives the id from the name via
`sanitize_title()`, and `WC_Cart_Fees::add_fee()` returns `WP_Error('fee_exists')` for a
repeat — a value `WC_Cart::add_fee()` throws away. Two fields labelled "Insurance"
would lose the second fee silently. `taxable => false` preserves today's behaviour
(WooCommerce's own default).

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter FeesIntegrationModeTest`
Expected: PASS

- [ ] **Step 5: Run the whole suite, then commit**

```bash
vendor/bin/phpunit --colors=never
git add includes/Checkout/FeesIntegration.php tests/php/Unit/FeesIntegrationModeTest.php
git commit -m "feat: one fee line per field

Fees are emitted through the fees API with explicit ids, so two fields
sharing a label no longer lose the second fee. clcf_itemized_fees returns
to the single line; a site filtering clcf_fee_total keeps the single line
automatically."
```

### Task 7: The three fee-bearing gates

A field priced only through its options must count as fee-bearing, or the checkout
never pushes the cart update that captures the values — and nothing is ever charged.

**Files:**
- Modify: `src/frontend.js:155`, `src/block-checkout/index.js:21`
- (The PHP gate was already replaced in Task 5.)

- [ ] **Step 1: Update both gates**

`src/frontend.js:155`:

```js
	const hasFees = fields.some( ( f ) => parseFloat( f.price ) > 0 || hasPricedOptions( f ) );
```

`src/block-checkout/index.js:21`:

```js
const HAS_FEES = ( CONFIG.fields || [] ).some( ( f ) => parseFloat( f.price ) > 0 || hasPricedOptions( f ) );
```

Import in both: `import { hasPricedOptions } from './shared/options';` (frontend.js) and
`import { hasPricedOptions } from '../shared/options';` (block-checkout/index.js) —
check the relative depth of each file before writing the path.

- [ ] **Step 2: Build and run JS tests**

```bash
npm run build
npm run test:js
```
Expected: build succeeds, tests pass.

- [ ] **Step 3: Commit**

```bash
git add src/frontend.js src/block-checkout/index.js
git commit -m "fix: count option-priced fields as fee-bearing

Both checkouts decided 'does this cart have fees?' from the field-level
price alone, so a field priced only through its options never triggered
the cart update that captures values — the fee was never charged."
```

---

## Chunk 3: Option readers and the Options panel

### Task 8: Server-side readers

**Files:**
- Modify: `includes/Checkout/Sanitizer.php:72`, `includes/Checkout/Validator.php:94`
- Test: `tests/php/Unit/SanitizerTest.php`, `tests/php/Unit/ValidatorTest.php` (append)

- [ ] **Step 1: Write the failing tests**

Append to `SanitizerTest`:

```php
	public function test_priced_options_are_accepted_by_their_label(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'speed', 'type' => 'select',
				'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ) ),
		) );
		$this->assertSame( array( 'speed' => 'Express' ), Sanitizer::sanitize( $g, array( 'speed' => 'Express' ) ) );
		$this->assertSame( array(), Sanitizer::sanitize( $g, array( 'speed' => 'Express (+$10.00)' ) ) );
	}
```

Append to `ValidatorTest`:

```php
	public function test_priced_options_validate_on_the_bare_label(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'speed', 'type' => 'select', 'label' => 'Speed', 'required' => true,
				'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ) ),
		) );
		$this->assertSame( array(), Validator::validate( $g, array( 'speed' => 'Express' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'speed' => 'Express (+$10.00)' ) ) );
	}
```

(Adjust the call signatures to match the existing tests in each file.)

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter "SanitizerTest|ValidatorTest"`
Expected: FAIL — "Array to string conversion" and/or the value is dropped.

- [ ] **Step 3: Implement**

`Sanitizer.php:72` — replace:
```php
					$opts = array_map( 'strval', (array) ( $f['options'] ?? array() ) );
```
with:
```php
					$opts = FieldOptions::labels( $f );
```

`Validator.php:94` — replace:
```php
				$valid = array_map( 'strval', (array) $f['options'] );
```
with:
```php
				$valid = FieldOptions::labels( $f );
```

Add `use CoreLabs\CheckoutFields\Fields\FieldOptions;` to both files.

- [ ] **Step 4: Run to verify they pass, then the whole suite**

```bash
vendor/bin/phpunit --filter "SanitizerTest|ValidatorTest"
vendor/bin/phpunit --colors=never
```

- [ ] **Step 5: Commit**

```bash
git add includes/Checkout/Sanitizer.php includes/Checkout/Validator.php tests/php/Unit/SanitizerTest.php tests/php/Unit/ValidatorTest.php
git commit -m "fix: accept object-shaped options in the sanitizer and validator

Both matched submitted values against array_map('strval', …), which turns
an option object into the string 'Array' — every priced choice would have
been rejected and no order could be placed."
```

### Task 9: Classic renderer prints option prices

**Files:**
- Modify: `includes/Checkout/ClassicRenderer.php:167-178`
- Test: `tests/php/Unit/ClassicRendererOptionsTest.php` (create)

- [ ] **Step 1: Write the failing test**

The renderer must never advertise an amount the calculator would not charge, so assert
it *against* `FeeCalculator`:

```php
<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\ClassicRenderer;
use CoreLabs\CheckoutFields\Checkout\FeeCalculator;
use Brain\Monkey;
use ReflectionMethod;

final class ClassicRendererOptionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'wc_price' )->alias( static fn( $a ) => '<span>&#36;' . number_format( (float) $a, 2 ) . '</span>' );
		Monkey\Functions\when( 'wp_strip_all_tags' )->alias( static fn( $s ) => strip_tags( (string) $s ) );
		Monkey\Functions\when( 'esc_html' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_attr' )->returnArg( 1 );
	}

	private function optionText( array $f, $option ): string {
		$m = new ReflectionMethod( ClassicRenderer::class, 'option_text' );
		$m->setAccessible( true );
		return (string) $m->invoke( null, $f, $option );
	}

	private function field(): array {
		return array(
			'id' => 'speed', 'type' => 'select', 'label' => 'Speed', 'price' => 4, 'priceMode' => 'fixed',
			'options' => array( 'Standard', array( 'label' => 'Express', 'price' => 10 ) ),
		);
	}

	public function test_every_option_advertises_what_it_charges(): void {
		$f = $this->field();
		foreach ( array( 'Standard' => 4.0, 'Express' => 10.0 ) as $value => $expected ) {
			$charged = FeeCalculator::total( array( 'fields' => array( $f ) ), array( 'speed' => $value ), array(), 0.0 );
			$this->assertSame( $expected, $charged );
			$option = 'Standard' === $value ? 'Standard' : array( 'label' => 'Express', 'price' => 10 );
			$this->assertStringContainsString(
				'$' . number_format( $charged, 2 ),
				$this->optionText( $f, $option ),
				"option “{$value}” must show the amount it is charged"
			);
		}
	}

	public function test_bare_labels_when_no_option_is_priced(): void {
		$f = array( 'id' => 's', 'type' => 'select', 'price' => 4, 'options' => array( 'A', 'B' ) );
		$this->assertSame( 'A', $this->optionText( $f, 'A' ) );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ClassicRendererOptionsTest`
Expected: FAIL — `option_text` does not exist.

- [ ] **Step 3: Implement**

Add to `ClassicRenderer`:

```php
	/**
	 * A choice reads as its label plus what picking it costs. The submitted
	 * value stays the bare label — only the display changes. Fields that do not
	 * price their options keep bare labels; the field's own price is stated once
	 * elsewhere.
	 *
	 * @param array<string,mixed> $f
	 * @param mixed               $option
	 */
	private static function option_text( array $f, $option ): string {
		$label = FieldOptions::label( $option );
		if ( ! FieldOptions::has_priced_options( $f ) ) {
			return $label;
		}
		$price = FieldOptions::effective_price( $f, $label );
		if ( $price <= 0 ) {
			return $label;
		}
		$suffix = 'percent' === (string) ( $f['priceMode'] ?? 'fixed' )
			/* translators: %s: percentage number */
			? sprintf( __( '+%s%%', 'corelabs-checkout-fields' ), (string) ( $price + 0 ) )
			: '+' . self::money( $price );

		/**
		 * Filter the price suffix appended to an option label. Return '' to hide it.
		 *
		 * @param string $suffix
		 * @param mixed  $option
		 * @param array  $f
		 */
		$suffix = (string) apply_filters( 'clcf_option_price_suffix', $suffix, $option, $f );
		if ( '' === $suffix ) {
			return $label;
		}
		/* translators: 1: option label, 2: price suffix */
		return sprintf( __( '%1$s (%2$s)', 'corelabs-checkout-fields' ), $label, $suffix );
	}

	/** Formatted money as plain text (wc_price returns markup with an entity symbol). */
	private static function money( float $amount ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format( $amount, 2 );
		}
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
```

Then in the radio branch (:167-172) and the select branch (:173-178), replace the loop
bodies so the **value** uses `FieldOptions::label( $opt )` and the **visible text** uses
`self::option_text( $f, $opt )`. Add `use CoreLabs\CheckoutFields\Fields\FieldOptions;`.

- [ ] **Step 4: Run to verify it passes, then the whole suite**

```bash
vendor/bin/phpunit --filter ClassicRendererOptionsTest
vendor/bin/phpunit --colors=never
```

- [ ] **Step 5: Commit**

```bash
git add includes/Checkout/ClassicRenderer.php tests/php/Unit/ClassicRendererOptionsTest.php
git commit -m "feat: classic checkout prints each option's price"
```

### Task 10: Block checkout readers

**Files:**
- Modify: `src/block-checkout/index.js:77,83`

- [ ] **Step 1: Route both option lists through the helper**

Import `optionLabel`, `optionPrice`, `hasPricedOptions`, `effectivePrice` from
`../shared/options`. Add a local `optionText( field, option )` mirroring the PHP one
(label, plus `+amount` when the field prices its options). Then:

```jsx
// select
{ ( field.options || [] ).map( ( o, i ) => (
    <option key={ i } value={ optionLabel( o ) }>{ optionText( field, o ) }</option>
) ) }

// radio
{ ( field.options || [] ).map( ( o, i ) => (
    <label key={ i }>
        <input type="radio" name={ id } value={ optionLabel( o ) }
               checked={ value === optionLabel( o ) }
               onChange={ () => onChange( optionLabel( o ) ) }
               required={ !! field.required } /> { optionText( field, o ) }
    </label>
) ) }
```

Key by index, not by label: duplicate labels are possible and React would warn.

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: compiles.

- [ ] **Step 3: Commit**

```bash
git add src/block-checkout/index.js
git commit -m "feat: Block checkout prints each option's price"
```

### Task 11: Builder readers + the Options panel

**Files:**
- Create: `src/builder/panels/Options.jsx`
- Modify: `src/builder/panels/General.jsx:20,75`, `src/builder/panels/Logic.jsx:92`, `src/builder/FieldPreview.jsx:19-25`, `src/builder/SettingsPanel.jsx`

- [ ] **Step 1: Port the Options panel**

Copy `/Users/tahir/woo-product-options/src/builder/panels/Options.jsx` and adapt:
- `PLAIN_OPTION_TYPES = [ 'radio', 'select' ]`, `OBJECT_OPTION_TYPES = []` (no swatches here).
- Drop the colour and image controls and the `pickImage` helper entirely.
- The help text points at the **Fee** tab (this plugin has no Pricing tab).
- Keep the duplicate-label warning and the price column.
- CSS classes `clpo-` → `clcf-`.

- [ ] **Step 2: Wire the tab**

In `SettingsPanel.jsx`, add an Options tab between General and Logic, rendered only when
`[ 'select', 'radio' ].includes( field.type )`.

- [ ] **Step 3: Remove the textarea and fix the other readers**

- `General.jsx:75` — delete the options `TextareaControl` (the panel replaces it).
- `General.jsx:20` — `{ label: optionLabel( o ), value: optionLabel( o ) }`.
- `Logic.jsx:92` — same; this one also *wrote* an object as a rule value.
- `FieldPreview.jsx:19-25` — labels via `optionLabel`, keys by index.

- [ ] **Step 4: Build, test, browser-check**

```bash
npm run build && npm run test:js
```
Then in wp-env: open the builder, add a Dropdown, give one option a price, save, reload,
confirm the price persisted, and confirm the browser console is clean.

- [ ] **Step 5: Commit**

```bash
git add src/builder/panels/Options.jsx src/builder/panels/General.jsx src/builder/panels/Logic.jsx src/builder/FieldPreview.jsx src/builder/SettingsPanel.jsx
git commit -m "feat: per-option price editor in the builder"
```

---

## Chunk 4: Uploads

### Task 12: Multi-token parsing and WEBP

**Files:**
- Modify: `includes/Checkout/FileUploads.php`
- Test: `tests/php/Unit/FileUploadsTest.php` (append)

- [ ] **Step 1: Write the failing tests**

```php
	public function test_webp_allowed_by_default(): void {
		$this->assertContains( 'webp', FileUploads::allowed_extensions() );
	}

	public function test_parse_tokens_handles_one_and_many(): void {
		$a = str_repeat( 'a1', 16 );
		$b = str_repeat( 'b2', 16 );
		$this->assertSame( array( $a ), FileUploads::parse_tokens( $a ) );
		$this->assertSame( array( $a, $b ), FileUploads::parse_tokens( "$a,$b" ) );
		$this->assertSame( array( $a, $b ), FileUploads::parse_tokens( " $a , $b " ) );
	}

	public function test_parse_tokens_drops_garbage_and_duplicates(): void {
		$a = str_repeat( 'a1', 16 );
		$this->assertSame( array( $a ), FileUploads::parse_tokens( "$a,$a,nope,," ) );
		$this->assertSame( array(), FileUploads::parse_tokens( '' ) );
		$this->assertSame( array(), FileUploads::parse_tokens( null ) );
		$this->assertSame( array(), FileUploads::parse_tokens( array( $a ) ) );
	}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter FileUploadsTest`

- [ ] **Step 3: Implement**

- Add `'webp'` to the `allowed_extensions()` default array.
- Add `parse_tokens()` (port from
  `/Users/tahir/woo-product-options/includes/Cart/FileUploads.php`).
- Update `validate_tokens()` to loop over `parse_tokens()` instead of testing one token.

- [ ] **Step 4: Run, then commit**

```bash
vendor/bin/phpunit --filter FileUploadsTest
git add includes/Checkout/FileUploads.php tests/php/Unit/FileUploadsTest.php
git commit -m "feat: multi-token file values + WEBP"
```

### Task 13: Rate limit moves out of the permission callback

**Files:**
- Modify: `includes/Checkout/FileUploads.php` (`can_upload()`, `handle_upload()`)

- [ ] **Step 1: Move the check**

`can_upload()` keeps only the nonce check and returns `bool`. Move the transient logic
to the top of `handle_upload()` and return a `WP_Error` when tripped:

```php
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'clcf_upl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= (int) apply_filters( 'clcf_upload_rate_limit', 60 ) ) {
			return new \WP_Error(
				'clcf_rate_limited',
				__( 'Too many uploads — please wait a few minutes and try again.', 'corelabs-checkout-fields' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
```

The default rises from 20 to 60: a shopper allowed 10 files across three upload fields
would otherwise exhaust the old limit legitimately, and because the transient is
re-set on every upload the window is rolling — they would wait a full hour.

- [ ] **Step 2: Run the suite and commit**

```bash
vendor/bin/phpunit --colors=never
git add includes/Checkout/FileUploads.php
git commit -m "fix: rate-limited uploads explain themselves

The check lived in the REST permission callback, so a shopper who hit it
saw 'Sorry, you are not allowed to do that.'"
```

### Task 14: Order persistence for several files

**Files:**
- Modify: `includes/Checkout/OrderPersistence.php:60-75,110-145`
- Test: `tests/php/Unit/OrderPersistenceTest.php` (append)

- [ ] **Step 1: Write the failing test**

```php
	public function test_one_dead_token_does_not_discard_the_other_files(): void {
		// Two tokens, the first unresolvable: the field must still record the second.
		// (Follow the mocking style already used in this test file for FileUploads::consume.)
	}
```

Write it concretely against the existing file's mocking style — read
`tests/php/Unit/OrderPersistenceTest.php` first and follow it.

- [ ] **Step 2: Implement**

- `save()`: loop `parse_tokens()`, `consume()` each, collect resolved files, and do not
  `continue` past the whole field when one token is dead.
- Store `files` (a list of `{ name, url }`) on the `_clcf_fields` row; keep `display` as
  a comma-joined list of **names**.
- `render_admin()`: if the row has `files`, print one link per file; otherwise fall back
  to the existing `0 === strpos( $display, 'http' )` path so pre-1.3.0 orders keep their
  links.
- `render_email()`: print names, never URLs.

- [ ] **Step 3: Run, then commit**

```bash
vendor/bin/phpunit --colors=never
git add includes/Checkout/OrderPersistence.php tests/php/Unit/OrderPersistenceTest.php
git commit -m "feat: orders record every uploaded file"
```

### Task 15: Both upload UIs

**Files:**
- Modify: `includes/Checkout/ClassicRenderer.php:196-209`, `src/frontend.js`, `src/block-checkout/index.js`

- [ ] **Step 1: Classic markup**

Add `multiple` and `data-clcf-max-files` when `maxFiles > 1`, plus a
`<span class="clcf-file-list"></span>` for the uploaded rows. Keep exactly **one**
hidden input per field — `frontend.js` finds it with
`wrap.querySelector( 'input[type="hidden"]' )`.

- [ ] **Step 2: Classic JS**

Port the uploader behaviour from
`/Users/tahir/woo-product-options/src/frontend/uploader.js`: append tokens up to the
cap, render a removable row per file, write the comma-joined list to the hidden input,
show a cap message.

- [ ] **Step 3: Block JS**

Mirror the same behaviour in the React uploader inside `block-checkout/index.js`.

- [ ] **Step 4: Build, then browser-check both checkouts**

Upload two files including a `.webp`, remove one, place an order, confirm two download
links on the admin order screen.

- [ ] **Step 5: Commit**

```bash
git add includes/Checkout/ClassicRenderer.php src/frontend.js src/block-checkout/index.js assets/css/frontend.css
git commit -m "feat: several files per upload field, on both checkouts"
```

---

## Chunk 5: Icons, brand mark, release

### Task 16: One icon set

**Files:**
- Create: `src/shared/FieldIcon.jsx`, `src/shared/FlameMark.jsx`
- Modify: `src/builder/Palette.jsx:9`, `src/builder/SettingsPanel.jsx:12`, `src/builder/FieldPreview.jsx:33,35,37`, `src/builder/AppShell.jsx:93`, `assets/css/builder.css:74,134,279`

- [ ] **Step 1: Port both components**

From `/Users/tahir/woo-product-options/src/shared/`. Keep only the 13 types this plugin
has; change the default `className` from `clpo-` to `clcf-`.

- [ ] **Step 2: Replace all five call sites**

- `Palette.jsx` — `META` becomes `LABELS`; render `<FieldIcon type={ type } />`.
- `SettingsPanel.jsx` — delete the duplicated `TYPE_IC` map; render `<FieldIcon …>`.
- `FieldPreview.jsx` — replace the inline 📅 🕐 📎 with `<FieldIcon …>`.
- `AppShell.jsx` — `▲` becomes `<FlameMark />`.
- `builder.css` — `.clcf-ic` loses `font-size`/`font-weight` and gains
  `color: var(--clcf-flame-deep)`; add `.clcf-logo .clcf-flame { display: block; }`.

- [ ] **Step 3: Build and check**

```bash
npm run build && npm run test:js
```
Then open the builder and confirm no emoji remain and the console is clean.

- [ ] **Step 4: Commit**

```bash
git add src/shared/FieldIcon.jsx src/shared/FlameMark.jsx src/builder/Palette.jsx src/builder/SettingsPanel.jsx src/builder/FieldPreview.jsx src/builder/AppShell.jsx assets/css/builder.css
git commit -m "feat: one drawn icon set + the Alovio flame"
```

### Task 17: Release

**Files:**
- Modify: `corelabs-checkout-fields.php`, `readme.txt`
- Create: `bin/svn-deploy.sh`

- [ ] **Step 1: Versions**

`Version: 1.3.0` in the plugin header, `CLCF_VERSION` if defined, `Stable tag: 1.3.0`
in `readme.txt`.

- [ ] **Step 2: Changelog + Upgrade Notice**

Add a `= 1.3.0 =` changelog block covering: a price per option; itemized fee lines
(and `clcf_itemized_fees` to return to one line); the single-line fee id change
(`checkout-options` → `clcf-checkout-options`, visible in the Store API); multi-file
uploads; WEBP; the drawn icon set; and the removal of empty-label options on save.
`readme.txt` has **no** `== Upgrade Notice ==` section — add one.

- [ ] **Step 3: POT + dist**

```bash
npx wp-env run cli -- wp i18n make-pot wp-content/plugins/corelabs-checkout-fields \
  wp-content/plugins/corelabs-checkout-fields/languages/corelabs-checkout-fields.pot \
  --exclude=node_modules,vendor,tests,dist,bin,docs,build
bash bin/build-dist.sh
```

- [ ] **Step 4: Port the deploy script**

Copy `/Users/tahir/woo-product-options/bin/svn-deploy.sh`, changing `SLUG` to
`corelabs-checkout-fields`. It prepares the SVN working copy and stops before
committing — the `svn ci` stays with the user.

- [ ] **Step 5: Full QA pass**

Both checkouts: fields render, prices sit beside options, totals itemize, multi-file
upload with per-file removal, an order with several fee lines and several download
links. Then run Plugin Check and confirm no new code-level findings.

- [ ] **Step 6: Commit**

```bash
git add corelabs-checkout-fields.php readme.txt languages/corelabs-checkout-fields.pot bin/svn-deploy.sh
git commit -m "release: 1.3.0"
```
