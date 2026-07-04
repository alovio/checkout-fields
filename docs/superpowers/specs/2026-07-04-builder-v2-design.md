# Builder v2 + Frontend Polish — Design (Phase 1 of "MVP → real product")

- **Status:** Approved by user (2026-07-04, from rendered mockup `docs/mockups/builder-v2.html`)
- **Plugin:** Alovio Checkout Fields for WooCommerce (`~/woo-checkout-fields`, live on wp.org as `corelabs-checkout-fields` v1.0.0)
- **Goal:** Take the admin builder and the checkout-facing rendering from MVP quality to a distinctive, SaaS-grade product experience. UI is the emphasis; architecture (option storage, REST, ConditionalLogic engine, PHP renderers, Store API integration) stays.

---

## 1. Scope

### In (Phase 1)
1. **Alovio app shell** for the builder page: dark coal header bar (flame logo mark, breadcrumb, dirty-state indicator, Undo, primary "Save & publish" button), full-height three-region workspace on a warm-gray canvas. Distinct product identity inside wp-admin (hybrid: custom shell + CSS design tokens; `@wordpress/components` kept where invisible/low-level).
2. **WYSIWYG preview canvas** (replaces the row-list canvas): the center column renders the configured fields as they will look on the checkout — realistic inputs, labels, required marks, descriptions — inside a "checkout sheet" with placeholder WooCommerce context rows. Click selects a field (flame ring + floating duplicate/delete ops + drag grip); drag reorders. A region selector tag on the sheet switches which render-region is being edited.
3. **Simulation bar** above the canvas: "PREVIEW AS" pickers — Role, Payment method, Shipping method, Cart (qty/subtotal presets), Country. Drives the existing JS `buildValues` + `activeMap` engine live: conditionally-shown fields appear with an `IF … → SHOW` flag; hidden ones collapse into a ghost "hidden by current preview" section listing why. No new logic — reuses the shipped parity-tested engine.
4. **Settings panel redesign** (right column), three tabs:
   - **General** — label, description, placeholder, default, required, options editor (existing controls, restyled).
   - **Logic** — sentence-style condition editor: token chips `[source] [operator] [value]`, AND/OR segmented toggle, "+ Add condition", THEN segmented `Show / Hide / Require`. Same rule data model as today (source tokens, `conditions[]`).
   - **Layout** — NEW `width` property: `full | half` (checkout renders half-width fields side by side in a 2-col grid), plus region (moved from General).
5. **Onboarding / empty state** — when no fields exist: hero empty state + "One-click recipes": pre-built field sets with wired conditional logic (COD confirmation, Gift message, VAT / EU B2B, Delivery notes). Free — templates are just data.
6. **Undo** — single-level undo (snapshot stack in the builder store) surfaced in the header; `⌘S`/`Ctrl+S` saves.
7. **Frontend polish (checkout-facing)**:
   - New `assets/css/frontend.css` (classic) + styles for the Block integration: consistent spacing/typography, focus-visible states, required asterisk, description text, validation-error styling, and the `width: half` 2-col grid (stacks on mobile).
   - Block checkout fields restyled to match (same class vocabulary where possible).

### Out (later phases)
- New field types (email/phone/url/multiselect/html) — Phase 2 (the mockup shows Email in the palette as a Phase-2 hint; not built now).
- Settings page, export/import, style-options UI — Phase 3.
- Pro add-on (fees etc., off-site) — separate product track.
- Multi-region simultaneous editing (v1: one region at a time via the region selector).

## 2. Architecture

No server-architecture change. The work is a **UI-layer rebuild plus two small schema/render additions**:

```
src/builder/            REBUILT
  AppShell.jsx          header bar + 3-region layout
  Palette.jsx           type grid + recipes (templates.js data)
  PreviewCanvas.jsx     checkout sheet; maps fields → <FieldPreview>, DnD, selection, ghost section
  FieldPreview.jsx      renders one field like the checkout (shared visual vocabulary w/ frontend CSS)
  SimulationBar.jsx     sim state → ctx → buildValues → activeMap (engine reuse)
  SettingsPanel.jsx     tabs shell
  panels/General.jsx, panels/Logic.jsx (token sentence editor), panels/Layout.jsx
  templates.js          recipe definitions (plain data)
  store/reducer.js      EXTEND: selection, simulation state, undo snapshot stack, width patches
assets/css/builder.css  REBUILT: Alovio design tokens (CSS vars: flame/coal palette, radii, shadow, spacing)
assets/css/frontend.css NEW: classic-checkout field styles + half-width grid
includes/Fields/FieldSchema.php   ADD `width` (full|half, default full)
includes/Checkout/ClassicRenderer.php  wrap fields in a grid container; width classes; enqueue frontend.css
src/block-checkout/     width classes + matching styles
```

- **Engine reuse:** the simulation uses `buildValues(fieldValues, ctx)` + `activeMap(fields, values)` from the shipped code — the preview is *provably* the same logic as the live checkout.
- **Sim data sources:** roles/payment/shipping/country lists already arrive via `CLCF_BUILDER` (SourceCatalog); cart presets are static options (e.g. "Empty", "2 items · $48", "5 items · $180").
- **Templates:** each recipe = `{ title, fields: [...] }` inserted through the normal `addField`/`updateField` path, then normalized server-side by FieldSchema on save — no new persistence.
- **Undo:** reducer keeps a bounded snapshot stack (e.g. last 25 states) — pure JS, unit-testable.
- **Text domain stays `corelabs-checkout-fields`** (wp.org slug is immutable); display strings say "Alovio".

## 3. Error handling & edge cases
- Save failure → header status turns red with retry; snackbar preserved.
- Empty region + fields in other regions → sheet shows that region's empty hint, ghost section still lists fields from the *selected* region only.
- A condition referencing a deleted sibling field already gets stripped by reducer/FieldSchema (unchanged).
- `width: half` fields at odd counts → last one spans full (CSS `:last-child:nth-child(odd)` grid rule); mobile always stacks.
- Old saved groups (no `width`) normalize to `full` — backward compatible; no migration needed.

## 4. Testing
- **JS (Jest):** reducer additions (selection, undo push/pop bounds, width patch), simulation ctx→activeMap integration (fixture-driven), templates insert→normalized shape.
- **PHP (PHPUnit):** FieldSchema `width` normalization (invalid → full), ClassicRenderer grid/width classes (output-buffer assertions).
- **Live QA (wp-env + Playwright):** build fields via new UI, simulate COD→field appears in canvas, save→reload, then place classic + Block orders verifying width layout, styling, and unchanged persistence/validation.
- Existing suites must stay green (PHP 48+, JS 25+).

## 5. Release
- Version bump to **1.1.0** on completion (real release per user's versioning rule), `readme.txt` changelog + refreshed screenshots (new builder is the hero screenshot), SVN trunk update + tag.
