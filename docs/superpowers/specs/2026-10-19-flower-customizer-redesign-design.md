# Flower Customizer redesign

**Date:** 2026-10-19
**Page:** `frontend/src/views/authenticated/FlowerCustomizer.vue`
**Approved mockup:** `.superpowers/brainstorm/392-1792342233/content/design-v5.html`

## Goal

The customer bouquet builder looks crowded. Rebuild its layout and visual design so it feels calm, modern, and on-brand, while keeping every feature it has today. Desktop is the primary target (most customers build on computers). Phones must still work.

### What the user decided

- Layout direction: **Studio**, meaning the 3D bouquet is the hero and only one set of choices shows at a time.
- Look: **Soft editorial**, the current BloomCraft brand (ivory, Cormorant Garamond serif headings, Poppins body, rosewood accent) but calmer.
- Second-pass refinements, approved after the first mockup was judged "vibe coded": no emoji, line icons only, one sheet with hairline dividers instead of stacked boxes, serif step numerals, list-style flower picker, a modest button radius instead of pills, and the receipt inside the side column.
- **The Blender-style movement must not be removed.** No new movement features are added (no X/Y/Z axis lock, numpad views, or typed values).

## Layout

Two columns, filling the viewport below the site navbar.

### Left: the sheet (about 340px, `--paper` background, right hairline border)

1. **Header.** A back link with the shop name, the "Build your bouquet" serif title, and an occasion line (`Wedding · 12 stems · hand-tied`) with a rosewood underlined **Change** link. Change reopens the existing occasion/size start screen. The current header with the row of stem slots is removed; its information moves to the receipt and the Bouquet list.
2. **Step tabs**, across the sheet: `01 Flowers · 02 Fillers · 03 Wrap · 04 Ribbon · 05 Card · 06 Review`. They use serif numerals, the active step gets a 2px rosewood underline, and completed steps get ink-colored numerals. Steps whose part doesn't exist for the current bouquet are hidden: no Wrap when there's no wrapper, no Ribbon when there's no ribbon, no Fillers or Card when the shop has none. The remaining steps are renumbered. Steps are free navigation, not a forced wizard.
3. **Step content**, scrolling inside the sheet, with a serif heading and a one-line hint:
   - **Flowers / Fillers:** search field (underline style), then a list of rows (thumbnail, name, price · stock left, then "Add" or the placed count in rosewood serif). "Fill the rest" stays. Selecting a row starts placing, as today.
   - **Wrap:** the existing wrapper layer switch (outer/inner), color options, and original-paper option.
   - **Ribbon:** the existing ribbon options.
   - **Card:** the existing card choices and the message editor modal.
   - **Review:** the "On the bouquet" list (each placed stem, click to select, remove), plus a summary of wrap, ribbon and card.
4. **Receipt**, pinned to the bottom of the sheet: stems `n of max` with a 2px progress line, one line per extra (filler, wrap, ribbon, card) with its price or "Included", a `TOTAL` caps label, a large serif total, then **Add to cart** (outline) and **Checkout** (ink, the wider of the two). Buttons are 42px tall with a 10px radius. Disabled, "Processing…" and validation states behave as today.

### Right: the stage (fills the rest, edge to edge)

- The 3D canvas fills the column on a soft ivory radial background. There are no extra borders around it.
- **Tool stack**, top left: a vertical group of icon buttons for **Move (G), Rotate (R), Size (S)**, a divider, then **Focus (F)**. The active mode is ink-filled. Each has a tooltip with its key. "Select whole bouquet" stays in this group.
- **View tools**, top right: bare icons for Start over, Save as image, and Fullscreen.
- **Selection card:** appears only when a flower or the bouquet is selected, anchored next to the selection, with a small pointer toward it. If anchoring is impractical it sits at the stage's upper right, and the pointer is dropped. It contains:
  - Name, plus `n of m` for that flower type.
  - **Turn**: − / degrees / +, and **Size**: slider, both as today.
  - **Fine-tune ›**: expands in place to show the per-axis rotate controls and the up/down/left/right direction pad (today's Adjust section).
  - **Lock** and **Remove** (Remove in rosewood). For the whole bouquet: Turn only, with no Size or Remove, matching today's rules.
- **Hint line**, bottom center: `Drag to look around · G move · R rotate · S size` with keycaps. It changes to the placing hint ("Click the bouquet to place …", with Cancel) while a flower is being placed.
- The load-error state ("The bouquet couldn't load") and the skeleton loading state stay, restyled. Per the project rule there are no spinners.

### Unchanged behavior (must keep working)

- All keyboard controls: G / R / S modes, F focus, arrow keys plus E / Q nudges, Delete / Backspace remove, Esc to deselect or cancel placing. The same ignore rules apply (inputs, modal open, modifier keys).
- TransformControls gizmo dragging, the upright-spin rule (`showX`), and lock behavior.
- Occasion and size start screen (restyled to the editorial look only), presets, max flowers and fillers, wrapper scale.
- Designs and model loading (`createBouquetBase`), save payload, add to cart, checkout, card editor, screenshot, fullscreen, toasts.

### Phones (under about 900px)

The stage goes on top (about 55vh) and the sheet becomes the area below it. Step tabs scroll horizontally if needed. The receipt stays as a bottom bar showing the total and both buttons. The selection card docks to the bottom of the stage at full width.

## Code structure

The page is a single 4,554-line file. While rebuilding the template, split the presentational pieces into components under `frontend/src/components/customizer/`. State, three.js and the order logic stay in the page:

- `CustomizerSteps.vue`: the step tabs (props: steps, active; emits select).
- `StemPicker.vue`: the search and list for flowers or fillers.
- `SelectionCard.vue`: Turn, Size, Fine-tune, Lock, Remove.
- `OrderReceipt.vue`: the receipt and both buttons.
- `StageTools.vue`: the tool stack and view tools.

Each component receives data through props and reports through events. They don't reach into three.js. Styling uses the existing tokens in `src/styles/bloomcraft.css` (`--ink`, `--paper`, `--ivory`, `--line`, `--rosewood`, fonts) and icons use the existing `BcIcon`. The scoped `.fc-page button { color: inherit }` rule that caused the blank Checkout button is not carried over.

## Testing

- The existing frontend tests (`npm test`) and `npx vite build` pass.
- Headless-Edge screenshots of the real page at 1440×900 and 390×844 are compared against the mockup, with the start screen, the empty bouquet, a selected flower, and the Review step.
- A manual check in the dev server: place a flower, then G/R/S, F, arrows, E/Q, Delete and Esc; change the occasion; switch wrap and ribbon; add a card message; add to cart; reach checkout. This needs the production API, so nothing is attached to real products without asking.

## Out of scope

- New movement features (axis lock, numpad views, typed values) and a Duplicate action. The mockup showed Duplicate, but the page doesn't have it today.
- Dark mode for the customizer, which is customer-facing. Dark mode stays vendor-only.
- Backend changes.
