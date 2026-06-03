=== CoreLabs Checkout Fields for WooCommerce ===
Contributors: 74h1r
Tags: woocommerce, checkout, checkout fields, conditional logic, custom fields
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add custom WooCommerce checkout fields with a drag-and-drop builder and full conditional logic — on classic and Block checkout.

== Description ==

CoreLabs Checkout Fields lets you add unlimited custom fields to the WooCommerce checkout and show, hide, or require them based on real checkout context — cart contents, product category, customer role, shipping method, payment method, and country — with no code.

Unlike other checkout-field plugins, the full conditional logic engine (including multi-condition AND/OR rules) is **free**, and it works identically on both the classic checkout and the new Block (Store API) checkout.

(Full description, FAQ, and screenshots are finalized before submission — see docs/superpowers/plans.)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install via Plugins → Add New.
2. Activate it (WooCommerce must be active).
3. Go to WooCommerce → Checkout Fields to build your fields.

== Changelog ==

= 1.0.0 =
* Initial release.

== Development ==

This plugin ships its human-readable source (`/src`) and build tooling (`composer.json`, `package.json`, `webpack.config.js`) alongside the compiled `build/` directory. Build with `npm install && npm run build`.
