=== Alovio Checkout Fields for WooCommerce ===
Contributors: 74h1r
Tags: woocommerce, checkout, checkout fields, conditional logic, custom fields
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add custom WooCommerce checkout fields with a drag-and-drop builder and full conditional logic — working on both classic and Block checkout.

== Description ==

**Alovio Checkout Fields** lets you add unlimited custom fields to the WooCommerce checkout and **show, hide, or require** them based on real checkout context — cart contents, product category, customer role, shipping method, payment method, and country — all without writing code.

Most checkout-field plugins lock conditional logic behind a paid upgrade. This one gives you the **full conditional engine for free**, including multi-condition AND/OR rules, and it behaves **identically on the classic checkout and the new Block (Store API) checkout**.

= Free features =

* Drag-and-drop field builder on a single admin page (WooCommerce → Checkout Fields).
* Field types: text, text area, number, checkbox, multiple choice (radio), dropdown (select), and section heading.
* **Full conditional logic — free:** show / hide / require, single or multi-condition (AND/OR).
* Condition sources: cart contents, product category, customer role, shipping method, payment method, billing/shipping country, and other checkout fields.
* Works on **both** the classic checkout and the Block checkout, with live show/hide as the shopper changes payment, shipping, or address.
* Choose where each field appears (after order notes, before order notes, after customer details, before payment).
* Validation (required and conditionally-required), then the values are saved to the order and shown on the admin order screen and in order emails. HPOS-compatible.

= Pro =

A separate Pro add-on unlocks field-driven pricing/fees, file-upload and date/time field types, repeatable field groups, My-Account-editable fields, import/export, and per-rule analytics. The free plugin is fully functional on its own.

== Installation ==

1. Install and activate the plugin (WooCommerce must be active).
2. Go to **WooCommerce → Checkout Fields**.
3. Add fields, set conditional rules, and save. Your fields appear on the checkout immediately.

== Frequently Asked Questions ==

= Does it work with the Block (Cart & Checkout) checkout? =

Yes. Fields render and conditionally show/hide on both the classic shortcode checkout and the WooCommerce Block checkout, and submitted values are validated server-side and saved to the order on both.

= Is conditional logic really free? =

Yes — the full engine (multi-condition AND/OR, all operators, all condition sources) is in the free plugin. That is the whole point.

= Where are the field values stored? =

On the order (HPOS-safe order meta), shown on the admin order screen and in order emails.

= Will it slow down my checkout? =

No. Conditions are evaluated by a small shared engine on the page and re-checked server-side on submit; there is no extra page load.

== Screenshots ==

1. The drag-and-drop checkout-field builder with the conditional-logic editor.
2. A conditional field showing on the Block checkout based on the selected payment method.

== Changelog ==

= 1.0.0 =
* Initial release: custom checkout fields with a drag-drop builder and full conditional logic, on classic and Block checkout.

== Development ==

This plugin ships its human-readable source (`/src`) and build tooling (`composer.json`, `package.json`, `webpack.config.js`) alongside the compiled `build/` directory. Build with `npm install && npm run build`.
