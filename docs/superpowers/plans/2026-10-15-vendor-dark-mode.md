# Vendor Dark Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Vendors can switch every `/vendor` page to a dark appearance from the sidebar, the navbar dropdown, or Shop Settings.

**Architecture:** Pure theme logic (`vendorThemeCore.js`) is wrapped by a Vue composable (`useVendorTheme.js`) that sets `--bc-accent*` variables and `data-vendor-theme="dark"` on `<html>` for `/vendor` routes. A global `vendor-theme.css` defines `--v-*` tokens with light values on `:root` and dark values under the attribute. A one-off codemod swaps hardcoded colors in vendor files for tokens.

**Tech Stack:** Vue 3 (`<script setup>`), vue-router 4, Vite 7, plain CSS, Node 20+ `node:test` for logic tests.

**Spec:** `docs/superpowers/specs/2026-10-15-vendor-dark-mode-design.md`

## Global Constraints

- Storage keys: theme color `bc_vendor_sidebar_accent` (unchanged), mode `bc_vendor_color_mode` with values `"system" | "light" | "dark"`; default `"system"`.
- Dark attribute only when effective dark AND route path starts with `/vendor`.
- Light token values equal today's colors; dark values as in the spec table.
- `color: #fff` / `white` text is never converted.
- Never convert: photos/logos, 3D viewer canvas, map tiles, JS palettes (`staffUi.js`, `crmUi.js`, category palette), storefront cover gradient, status greens kept in the theme-color pass.
- All dark text/background token pairs pass WCAG AA 4.5:1.

## Review Focus

- `localStorage` blocked or holding junk → falls back to `"system"` without throwing (test in Task 1).
- OS switches dark↔light while Match my device is on → page follows live (test in Task 1 via `resolveDark`; listener verified in Task 4 browser pass).
- Navigating from a vendor page to `/shop` while dark → attribute removed, customer page light (Task 4 browser pass).
- Shared components (NotificationBell, SupportCenter, ReturnCasePanel) on customer/admin pages → unchanged light look (tokens' `:root` values equal old colors; Task 4 browser pass on `/shop` support).
- Custom theme color that is very light (e.g. `#f6d743`) in dark mode → accent text shades still readable (contrast test in Task 1 for presets plus `#f6d743`).

---

### Task 1: Theme logic and tokens

**Files:**
- Create: `frontend/src/composables/vendorThemeCore.js` (pure, no Vue)
- Create: `frontend/src/composables/useVendorTheme.js` (replaces `useSidebarTheme.js`; delete old file, update importers `VendorSidebar.vue`, `main.js`)
- Create: `frontend/src/styles/vendor-theme.css`, imported in `frontend/src/main.js`
- Test: `frontend/tests/vendorTheme.test.mjs`

**Interfaces:**
- Produces (core): `readMode(storage) -> "system"|"light"|"dark"`, `resolveDark(mode, systemDark: boolean) -> boolean`, `shadesFor(hex, dark: boolean) -> {hover, deep, strong, soft, muted, border}`, `contrast(hexA, hexB) -> number`, `inkFor(hex) -> string`
- Produces (composable): `useVendorTheme() -> { accent, accentVars, setAccent, resetAccent, mode, isDark, matchDevice, setDark(bool), setMatchDevice(bool) }`, `applyVendorTheme(router)`

- [ ] **Step 1: Write failing tests** — `readMode` returns `"system"` for `null`, `"junk"`, and a storage whose `getItem` throws; `resolveDark("system", true) === true`, `resolveDark("light", true) === false`, `resolveDark("dark", false) === true`; `shadesFor("#48bb78", false)` equals the existing default shade set; for every `ACCENT_PRESETS` value plus `#f6d743`, `contrast(shadesFor(hex, true).deep, "#161c25") >= 4.5`; for every dark token pair from the spec (`--v-text`, `--v-text-2`, `--v-muted` on `--v-surface`, `--v-bg`, `--v-raised`; each `--v-<hue>-text` on its `--v-<hue>-soft`) contrast `>= 4.5`. Token values are imported from an exported `DARK_TOKENS` object in the core.
- [ ] **Step 2: Run** `node --test frontend/tests/vendorTheme.test.mjs` — expect FAIL (module missing).
- [ ] **Step 3: Implement core + composable + CSS.** Dark accent shades mix toward `#161c25` for soft/muted/border (0.82/0.65/0.45) and toward white for deep/strong (0.25/0.4); tune until tests pass. `vendor-theme.css` is generated to match `DARK_TOKENS`/light tokens and includes `color-scheme: dark`, `.bc-skeleton` dark colors, and toastify variables under the attribute.
- [ ] **Step 4: Run tests** — PASS. Run `npx vite build` in `frontend` — "built in".

### Task 2: The three switches

**Files:**
- Modify: `frontend/src/layouts/Sidebar/VendorSidebar.vue` (Dark mode row in footer)
- Modify: `frontend/src/layouts/vendorHeader.vue` (row in `.vh-menu-card` before Log out; click does not close the menu)
- Modify: `frontend/src/views/vendor/account/ShopSettings.vue` (Appearance card in `.ss-side`: Dark mode, Match my device, theme swatches + any-color input)

**Interfaces:** Consumes `useVendorTheme()` from Task 1.

- [ ] **Step 1:** Each uses `<span class="sp-switch"><input type="checkbox" :checked="isDark" @change="setDark($event.target.checked)" /><span></span></span>`; Match my device uses `matchDevice` / `setMatchDevice`.
- [ ] **Step 2:** `npx vite build` passes.

### Task 3: Convert colors to tokens

**Files:**
- Create (scratchpad, not committed): `vendor-dark-codemod.mjs`
- Modify: every file in the spec's Scope list, in the spec's order (shared → shop → orders → crm → finance → staff → account → dashboard/profile/customization/add product/support/force password)

- [ ] **Step 1:** Codemod maps by property per spec rules; prints unclassified colors per file.
- [ ] **Step 2:** Run per section, review diff, hand-fix the unclassified list.
- [ ] **Step 3:** `npx vite build` passes after each section; `grep` shows no remaining neutral hex (`#fff` backgrounds, `#1a202c`, `#718096`, `#a0aec0`, `#4a5568`, `#e2e8f0`, `#edf2f7`) outside the never-convert list.

### Task 4: Verify in the browser

- [ ] **Step 1:** With the dev server and a vendor login, open each vendor page in light and dark; screenshot; fix anything unreadable.
- [ ] **Step 2:** Check Review Focus items: OS toggle with Match my device on, navigating to `/shop` removes dark, refresh has no flash.
