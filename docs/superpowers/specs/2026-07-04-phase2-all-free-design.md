# Phase 2 — "All Free" + New Field Types + Fees + File Upload (Design)

- **Status:** Scope approved by user (2026-07-04). Strategy: NO Pro version — this plugin is 100% free forever;
  it exists to build reputation/installs and convert users to the Alovio Calculator flagship (link activates when
  Calculator ships).
- **Version target:** 1.2.0

## 1. Scope

1. **De-Pro** — remove the Pro gate entirely: `Pro/ProModule`, the `corelabs-checkout-fields-pro` plugin,
   `clcf_is_pro` / gating filters, Pro mentions in readme/dist tooling. Reposition readme: "100% free — no Pro
   version, no locked features, everything included."
2. **Fees, free** — restore field-driven fees from git history (`b70e258`), ungated: a field can add a cart fee
   (fixed / per-unit [number × value] / percent of subtotal), applied via `woocommerce_cart_calculate_fees` on both
   checkouts, live total updates (classic `update_checkout` trigger; Block `extensionCartUpdate`). Builder: a new
   **Fee tab** in the settings panel (amount + mode). The 1.1.x draft-update validation guard stays.
3. **New field types (free):** `email`, `phone`, `url`, `date`, `time` — native inputs, type-correct
   sanitization (`sanitize_email`, `esc_url_raw`, …) and server validation (`is_email`, URL filter, date/time
   format + date min/max bounds). Builder: palette entries, General-tab constraints (date min/max), checkout-style
   previews.
4. **File upload field:** because the classic checkout submits via AJAX-serialized form (files cannot ride along),
   files upload **immediately on selection** through a dedicated REST endpoint (`clcf/v1/upload`, public for
   guests) and the field carries an opaque **file token** in a hidden input — identical flow on Block checkout
   (token via extensionData). Server hardening: extension whitelist (default jpg/jpeg/png/pdf), size cap (default
   5 MB), `wp_check_filetype_and_ext` MIME verification, randomized filename in `uploads/clcf/`, `index.html`
   guard file; a **daily cron** deletes orphan uploads older than 48 h (token never attached to an order). Order
   meta stores the file URL; the admin order screen shows a download link.
5. **Alovio conversion slot:** builder header gets a subtle "More from Alovio ↗" ghost link (→ alovio.org for
   now); readme gets an "Also by Alovio" section. The Calculator link/promo card is added when the Calculator is
   live — the slot exists so that release is a one-line change.

## 2. Out of scope (Phase 3+)
Repeatable field groups, My-Account editing, import/export, per-rule analytics, per-product overrides.

## 3. Key decisions
- **No gating filters left:** price modes and field types are plain constants/filters defaulting to the full set
  (filters kept only as extensibility points, never as locks).
- **Fee tab** (4th tab) rather than crowding General; hidden for `heading`/`file` (no sensible fee) — actually
  fees allowed on file too? No: fee applies to any *engaged* field; keep Fee tab for all input types, engaged =
  non-empty (checkbox = checked, number ≠ 0, file = uploaded).
- **File tokens** are random 32-hex ids mapped to uploads via a `clcf_upload_<token>` option row (value = relative
  path + timestamp); attaching to an order clears the orphan marker. Simpler than media attachments and keeps the
  media library clean.
- Tests follow the existing stack (PHPUnit+Brain Monkey, Jest, shared fixtures); live QA on both checkouts before
  release.
