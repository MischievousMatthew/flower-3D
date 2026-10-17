# Bouquet Occasion Presets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Occasion + size presets in the customizer, configurable per vendor, enforced on the server.

**Architecture:** `BouquetPresets` (PHP) owns defaults/validation; JSON column on `vendor_applications`; frontend mirror `constants/bouquetPresets.js` for display + pure helpers; customizer reads presets from the public options endpoint.

**Tech Stack:** Laravel 11 + PHPUnit, Vue 3, three.js, node:test.

**Spec:** `docs/superpowers/specs/2026-10-17-bouquet-presets-design.md`

## Global Constraints

- Occasion keys: `wedding, romance, birthday, graduation, sympathy, just_because`.
- Sizes 1–36, ≤4 per occasion; ≥1 occasion enabled; filler cap `max(5, ceil(size/2))`.
- Legacy bouquets (no `preset`) keep working: ≤3 flowers.
- Skeleton/text loading states only.

## Review Focus

- Legacy cart items without `preset` still check out (test).
- Vendor disables an occasion after a customer put it in the cart → checkout explains, doesn't crash (test: disabled occasion message).
- Size shrunk below placed flowers in the customizer → blocked in UI.
- Wedding bouquet with a wrapper id sent anyway → wrapper not charged (test).
- Vendor saves duplicates/out-of-range sizes → 422 with field message (test).

---

### Task 1: Backend presets + validation
Files: migration `2026_10_21_000000_add_bouquet_presets_to_vendor_applications.php`; `app/Support/BouquetPresets.php`; `VendorApplication` cast; `VendorBouquetPresetController`; routes; `VendorStoreFrontController@getCustomizationOptions`; `BouquetCustomizationOption::resolveSelections`; `CheckoutController` max 36; test `tests/Feature/BouquetPresetsTest.php`.
- [ ] Failing tests → implement → `php artisan test --filter=BouquetPresets` passes, full vendor/subscription suites unchanged.

### Task 2: Frontend helpers
Files: `src/constants/bouquetPresets.js` (`OCCASIONS`, `wrapperScaleFor(size)`, `fillerLimitFor(size)`, `spiralPoints(n, radius)`); test `tests/bouquetPresets.test.mjs`.
- [ ] Failing tests → implement → `npm test`.

### Task 3: Vendor Presets tab (`views/vendor/Customization.vue`)
- [ ] Tab, load/save, validation display; build passes.

### Task 4: Customizer (`views/authenticated/FlowerCustomizer.vue`)
- [ ] Start screen, dynamic limits, build visibility, wrapper scale, fill-the-rest, payload/restore; build passes; browser check.
