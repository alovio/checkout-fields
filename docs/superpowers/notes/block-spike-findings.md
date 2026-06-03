# Block Checkout Spike — Findings (Task 5.0)

**Date:** 2026-06-03 · **Env:** wp-env, WordPress 7.0, WooCommerce 10.8.1, HPOS on, Checkout = `woocommerce/checkout` block.

**Verdict: Approach (A) — plugin-owned React fields in the Block checkout via the Store API — is VALIDATED end to end.** A virtual-product order (#13) placed through the Block checkout carried a value typed in our React field all the way to order meta.

## Proven mechanisms

1. **Script enqueue on Block checkout — `IntegrationInterface`.**
   Register via `add_action('woocommerce_blocks_checkout_block_registration', fn($registry) => $registry->register(new Integration()))`. Implement `IntegrationInterface` (`get_name`, `initialize`, `get_script_handles`, `get_editor_script_handles`, `get_script_data`). In `initialize()`, `wp_register_script()` the built bundle.
   - **Dep gotcha:** our bundle accesses `window.wc.blocksCheckout` as a global, so `build/block-checkout.asset.php` does NOT list `wc-blocks-checkout`. Manually merge `'wc-blocks-checkout'` into the registered deps. `@wordpress/*` imports (`@wordpress/element`, `@wordpress/data`, `@wordpress/plugins`) are externalised by the default `@wordpress/scripts` config to `wp-element`/`wp-data`/`wp-plugins` automatically.

2. **Rendering a React component into the Block checkout — `ExperimentalOrderMeta` slot.**
   `import { registerPlugin } from '@wordpress/plugins'`, `const { ExperimentalOrderMeta } = window.wc.blocksCheckout`, then `registerPlugin('clcf', { render: () => <ExperimentalOrderMeta>…</ExperimentalOrderMeta>, scope: 'woocommerce-checkout' })`. Renders in the order-summary/Totals region.
   - For v1 production we may want fields nearer the address form; revisit region in Chunk 5. The data path is region-independent.

3. **Reading cart/context client-side.**
   `select('wc/store/cart').getCartData()` → `.itemsCount`, items, etc. Worked live (showed "cart items: 1"). This is how `data-sources.js` will assemble the client token map (cart categories/products/qty/subtotal); shipping/payment come from `wc/store/checkout` + the payment store.

4. **Submitting values to the server — `setExtensionData` + Store API.**
   - **CRITICAL gotcha:** the action signature is **`setExtensionData(namespace, dataObject)`** — TWO args. Calling it 3-arg (`(ns, key, value)`) sets the namespace value to the *key string*, and the Store API rejects the checkout with HTTP 400: `extensions > clcf is not of type object,null`. Correct: `dispatch('wc/store/checkout').setExtensionData('clcf', { note: v })`.
   - The data is posted under `extensions[namespace]` to `POST /wc/store/v1/checkout`.

5. **Accepting posted data — `woocommerce_store_api_register_endpoint_data`.**
   On `woocommerce_blocks_loaded`, register for `endpoint => 'checkout'`, `namespace => 'clcf'`, a `schema_callback` returning the property schemas, `schema_type => ARRAY_A`. Without a matching schema the value is rejected by Store API validation.

6. **Persisting to the order — `woocommerce_store_api_checkout_update_order_from_request`.**
   Fires with `($order, $request)` during the checkout POST. Read `$request['extensions']['clcf']`, then `$order->update_meta_data('_clcf_…', …)` (+ `$order->save()` if outside the normal save flow). HPOS-safe — the value landed in the HPOS order-meta (not legacy postmeta), confirmed via `wp wc shop_order get 13 --field=meta_data`.

## Implications for Chunk 5 (real BlockIntegration)
- Server: one `IntegrationInterface` (enqueue + `get_script_data` carrying the field group + sources + i18n), endpoint-data registration whose schema accepts `fields` (object keyed by field id), and the `update_order_from_request` hook that builds the token map, runs the shared `Validator` (throw `RouteException` on error), then delegates to `OrderPersistence`.
- Client: render fields from `getScriptData()`; build the live token map via `buildValues(ctx)` from `wc/store/cart` + `wc/store/checkout` + payment store; compute `activeMap` for show/hide; on change `setExtensionData('clcf', { fields: values })` (2-arg!).
- Validation errors surface as Store API notices (the 400 path we already saw renders a checkout notice).

## Cleanup
Spike code (`includes/Checkout/BlockSpike.php`, the spike `src/block-checkout/index.js`, the `Plugin::boot` wiring line) was reverted after these findings were recorded. Test order #13 left in the dev DB (harmless).
