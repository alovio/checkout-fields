=== Alovio Checkout Fields – Custom WooCommerce Checkout Fields + Conditional Logic ===
Contributors: 74h1r
Tags: woocommerce, checkout, checkout fields, conditional logic, custom fields
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add custom WooCommerce checkout fields with a drag-and-drop builder and full conditional logic — working on both classic and Block checkout.

== Description ==

**Alovio Checkout Fields** lets you add unlimited custom fields to the WooCommerce checkout and **show, hide, or require** them based on real checkout context — cart contents, product category, customer role, shipping method, payment method, and country — all without writing code.

Most checkout-field plugins lock conditional logic behind a paid upgrade. This one gives you the **full conditional engine for free**, including multi-condition AND/OR rules, and it behaves **identically on the classic checkout and the new Block (Store API) checkout**.

= Free features =

* **Visual builder with live preview** — fields render exactly as they will look on the checkout, and a "Preview as" bar simulates role, payment method, shipping, cart and country so you can watch conditional fields show and hide in real time.
* **One-click recipes** — pre-built field sets (COD confirmation, gift message, VAT/EU B2B, delivery notes) with conditional logic already wired.
* Drag-and-drop field builder on a single admin page (WooCommerce → Checkout Fields).
* 13 field types: text, text area, number, checkbox, radio, dropdown, heading, email, phone, URL, date, time, and secure file upload.
* **Field-driven fees** — any field can add a fee to the order (fixed, per-unit, or percentage of subtotal), shown live in the totals on both checkouts.
* **Full conditional logic — free:** show / hide / require, single or multi-condition (AND/OR).
* Condition sources: cart contents, product category, customer role, shipping method, payment method, billing/shipping country, and other checkout fields.
* Works on **both** the classic checkout and the Block checkout, with live show/hide as the shopper changes payment, shipping, or address.
* Choose where each field appears (after order notes, before order notes, after customer details, before payment) and its width — full or half, so two fields can sit side by side.
* Validation (required and conditionally-required), then the values are saved to the order and shown on the admin order screen and in order emails. HPOS-compatible.

= 100% free — no Pro version =

There is no paid tier and no locked features. Everything this plugin does — conditional logic, field-driven fees, file uploads, every field type — is included, free.

= Also by Alovio =

* [Alovio Calculator](https://wordpress.org/plugins/alovio-calculator/) — build cost, price and quote calculators with formulas, conditional logic and lead capture. 11 templates, 6 themes, free.
* See [alovio.org](https://alovio.org) for the full family.

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

= Is this a free alternative to Checkout Field Editor for WooCommerce? =

Yes. If you are comparing against Checkout Field Editor, Checkout Field Manager or a similar plugin, Alovio Checkout Fields gives you the full conditional-logic engine — show / hide / require with multi-condition AND/OR rules — free, and identical on both the classic and Block (Store API) checkout. Most alternatives paywall conditional logic; here it is in the free plugin.

== Screenshots ==

1. The drag-and-drop checkout-field builder with the conditional-logic editor.
2. A conditional field showing on the Block checkout based on the selected payment method.

== Changelog ==

= 1.2.2 =
* Builder header now links to Alovio Calculator, our free calculator/quote builder; readme lists it under "Also by Alovio".

= 1.2.1 =
* Redesigned checkout-field styling on both checkouts: one consistent look for every field type (the new email/date/time/phone/URL inputs were unstyled), tappable boxed checkbox/radio rows with a highlighted checked state, a drop-zone style file picker, unified heights, and clear focus states.

= 1.2.0 =
* This plugin is now 100% free, forever — the Pro tier is gone and everything it would have held is included.
* Field-driven fees: any field can add a fee to the order (fixed / per-unit / percentage of subtotal), shown live in the totals on both checkouts.
* Six new field types: email, phone, URL, date (with min/max), time, and secure file upload.
* File uploads: files upload on selection through a hardened endpoint (type whitelist, real MIME verification, size cap, randomized names); the admin order screen links to the file; unattached uploads are purged automatically after 48 hours.
* "More from Alovio" link in the builder header.

= 1.1.2 =
* Fix: on the Block checkout, the fields section had no horizontal inset (fields touched the panel edge) — a WooCommerce Blocks slot rule was overriding the section padding. Fields now align with the order-summary panel's own content.
* Improvement: dropdown fields on the Block checkout show "Choose an option" instead of a bare dash as the empty choice.

= 1.1.1 =
* Fix: dropdowns in the builder (simulation bar, region selector, condition tokens) opened visibly offset from their chip — wp-admin's global select min-height made the invisible overlay selects taller than their chips.
* Fix: admin notices no longer push the full-height builder layout down.

= 1.1.0 =
* New visual builder: fields render checkout-style with click-to-edit, drag reorder, and a live "Preview as" simulation bar (role / payment / shipping / cart / country) that shows conditional logic working in real time.
* New sentence-style condition editor with AND/OR groups and Show / Hide / Require actions.
* One-click recipes: COD confirmation, gift message, VAT/EU B2B, delivery notes.
* Field width option (full / half) — half-width fields sit side by side on the checkout, on both classic and Block.
* Polished checkout styling for fields on both classic and Block checkout.
* Undo support and Cmd/Ctrl+S save in the builder.
* Fix: switching payment method on the Block checkout no longer surfaces a premature "required" notice.

= 1.0.0 =
* Initial release: custom checkout fields with a drag-drop builder and full conditional logic, on classic and Block checkout.

== Development ==

This plugin ships its human-readable source (`/src`) and build tooling (`composer.json`, `package.json`, `webpack.config.js`) alongside the compiled `build/` directory. Build with `npm install && npm run build`.
