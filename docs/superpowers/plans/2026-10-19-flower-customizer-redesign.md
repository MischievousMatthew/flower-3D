# Flower Customizer Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the customer bouquet builder's layout and look as the approved "Studio + Soft editorial" design (a sheet on the left, the 3D stage edge to edge on the right), without losing any feature or any of the Blender-style controls.

**Architecture:** All state, three.js and order logic stay in `FlowerCustomizer.vue`. Two pure modules (step list and receipt lines, keyboard actions) move out so they can be unit-tested with `node:test`. Five presentational components under `src/components/customizer/` render the new UI from props and emit events. The page's template and scoped CSS are rewritten to compose them.

**Tech Stack:** Vue 3 `<script setup>`, Vite 7, three.js 0.182 (TransformControls, OrbitControls), node:test, headless Edge for screenshots.

**Spec:** `docs/superpowers/specs/2026-10-19-flower-customizer-redesign-design.md`. The approved mockup is `.superpowers/brainstorm/392-1792342233/content/design-v5.html`, so open it for exact spacing and colors.

## Global Constraints

- Brand tokens only, from `src/styles/bloomcraft.css`: `--ink`, `--paper`, `--ivory`, `--line`, `--rosewood`, `--rosewood-dark`, `--font-serif` (Cormorant Garamond), `--font-sans` (Poppins). Use no new hex colors except the stage background gradient `radial-gradient(120% 90% at 55% 38%, #fbf8f3 0%, #efe8de 55%, #e6ddd0 100%)`.
- No emoji. Icons are line SVGs on a 24px grid with a 1.5 stroke (the existing `ICONS` set).
- Buttons are 42px tall with a 10px radius, not pills. Checkout is ink with `--paper` text; Add to cart is the outline style.
- No spinners: skeleton (`.bc-skeleton`) and inline text states only (project memory rule).
- Don't carry over `.fc-page button { color: inherit }`. Only `font-family` may be inherited.
- Sheet width is 340px on desktop. Below 900px the stage stacks on top at `55vh`.
- Step labels and order are `Flowers, Fillers, Wrap, Ribbon, Card, Review`, numbered `01…` after hiding absent steps.
- Keep every existing keyboard control with identical behavior: G, R, S, F, arrows, E, Q, Shift for fine steps, Delete/Backspace, Esc, and the same ignore rules.
- No backend changes. No Duplicate action. No new movement features.

## Review Focus

- **Typing in search while a flower is selected:** pressing "g", "r", "s" or "f" in the search field must type the letter, not switch the gizmo mode. Test `ignores keys typed into fields` in Task 1.
- **Occasion without a wrapper, ribbon, fillers or cards (e.g. Wedding with no fillers):** that step disappears and numbering stays continuous (`01 02 03…`). If the active step disappears after a change of occasion, the view falls back to Flowers. Tests `hides absent steps and renumbers` (Task 1) and Task 6 step 3.
- **Whole bouquet selected:** S does nothing, the Size and Remove controls don't show, and Turn works. Test `S is ignored when the bouquet is selected` (Task 1) and `SelectionCard` props in Task 3.
- **Selection near the stage edge:** the selection card must stay fully inside the stage, flipping to the left of the selection when there's no room on the right. Test `anchorCard keeps the card inside the stage` in Task 1.
- **Window resize, fullscreen, or the phone breakpoint:** the 3D canvas must keep its correct aspect (no stretching). `handleResize` is driven by a `ResizeObserver` on the stage instead of the removed sidebar toggles. Checked in Task 6 step 4.

---

### Task 1: Pure customizer logic (steps, receipt, keys, card anchor)

**Files:**
- Create: `frontend/src/utils/customizerUi.js`
- Test: `frontend/tests/customizerUi.test.mjs`

**Interfaces:**
- Produces:
  - `buildSteps({ hasFillers, hasWrapper, hasRibbon, hasCards }) -> Array<{ key, label, number }>`. Keys are `flowers | fillers | wrap | ribbon | card | review`, and `number` is a zero-padded string (`"01"`).
  - `receiptLines({ flowers, fillers, outerWrapper, innerWrapper, ribbon, card }) -> Array<{ label, amount }>`. `amount` is a number, with 0 meaning "Included". There is one line per present item, in that order. Stems is labeled `Stems (n)`, then `Fillers (n)`, `Outer: name`, `Inner: name`, the ribbon name, then `Card: name (size)`.
  - `keyAction(event, state) -> string | null`. `event` is `{ key, ctrlKey, metaKey, altKey, inField }` and `state` is `{ ready, modalOpen, hasTarget, bouquetSelected, hasSelectedFlower, placing }`. The return value is one of `"cancel-placing" | "deselect" | "mode:translate" | "mode:rotate" | "mode:scale" | "focus" | "remove" | "nudge:left|right|up|down|forward|backward"`, or `null`.
  - `anchorCard({ x, y }, { width, height }, { cardWidth, cardHeight, gap = 24, margin = 16 }) -> { left, top, side: "right" | "left" }`.

- [ ] **Step 1: Write the failing tests** in `tests/customizerUi.test.mjs` (`node:test` + `node:assert/strict`, importing from `../src/utils/customizerUi.js`):
  - `all steps when everything exists`: `buildSteps({hasFillers:true,hasWrapper:true,hasRibbon:true,hasCards:true}).map(s => s.number + " " + s.label)` deep-equals `["01 Flowers","02 Fillers","03 Wrap","04 Ribbon","05 Card","06 Review"]`.
  - `hides absent steps and renumbers`: with only `hasRibbon:true`, it gives `["01 Flowers","02 Ribbon","03 Review"]`.
  - `receipt lists present items in order`: two flowers (₱150 each), one ribbon `{name:"Ivory satin",price:0}`, and no card produce `[{label:"Stems (2)",amount:300},{label:"Ivory satin",amount:0}]`.
  - `G R S F map to modes and focus`: with `state = {ready:true,modalOpen:false,hasTarget:true,bouquetSelected:false,hasSelectedFlower:true,placing:false}`, the keys `g`/`R`/`s`/`f` give `mode:translate`/`mode:rotate`/`mode:scale`/`focus`.
  - `arrows and E Q nudge`: `ArrowLeft → nudge:left`, `ArrowUp → nudge:up`, `e → nudge:forward`, `q → nudge:backward`.
  - `Delete and Backspace remove only a flower`: with `hasSelectedFlower:true` they give `remove`; with `bouquetSelected:true, hasSelectedFlower:false` they give `null`.
  - `S is ignored when the bouquet is selected`: `bouquetSelected:true` gives `null`.
  - `Esc cancels placing first, then deselects`: `placing:true` gives `cancel-placing`, otherwise `deselect`. This works even when `hasTarget:false`.
  - `ignores keys typed into fields`: `inField:true` gives `null` for `g`. `ctrlKey:true` gives `null`. `modalOpen:true` gives `null`. `ready:false` gives `null`.
  - `keys need a target`: `hasTarget:false` gives `null` for `g` and `ArrowLeft`.
  - `anchorCard keeps the card inside the stage`: a point at `{x:900,y:40}` in a `1000×700` stage with a `220×180` card gives `side:"left"`, `left + 220 <= 984`, `top >= 16`. A point at `{x:300,y:690}` gives `side:"right"`, `left === 324`, and `top + 180 <= 684`.

- [ ] **Step 2: Run the tests and confirm they fail**
  Run: `cd frontend && node --test tests/customizerUi.test.mjs`
  Expected: FAIL with `Cannot find module …customizerUi.js`.

- [ ] **Step 3: Implement the four functions** in `src/utils/customizerUi.js`. `keyAction` lowercases `key` and uses the existing `MOVE_KEYS` map (move it from the page into this module and export it). The card is vertically centered on `y`, then clamped.

- [ ] **Step 4: Run the tests and confirm they pass**
  Run: `cd frontend && node --test tests/customizerUi.test.mjs`. Expected: all pass. Then run `npm test`; expected: all 45 existing tests still pass, plus the new ones.

- [ ] **Step 5: Wire `keyAction` into the page.** In `FlowerCustomizer.vue`, `onKeydown` builds `event`/`state`, calls `keyAction`, and dispatches each action to the existing functions (`transformMode.value = …`, `focusSelection()`, `removeFlower(...)`, `nudgeSelectedPosition(dir, event)`, `clearSelection()`, `pendingFlower.value = null`). It calls `preventDefault()` when the action isn't null. Run `npx vite build`; expected: `built in`.

- [ ] **Step 6: Commit**
  `git add frontend/src/utils/customizerUi.js frontend/tests/customizerUi.test.mjs frontend/src/views/authenticated/FlowerCustomizer.vue && git commit -m "Extract customizer steps, receipt and key handling"`

### Task 2: Shared icon component

**Files:**
- Create: `frontend/src/components/customizer/CustomizerIcon.js`
- Modify: `frontend/src/views/authenticated/FlowerCustomizer.vue` (remove the `ICONS` and `BcIcon` definitions)

**Interfaces:**
- Produces: the default export, a functional component with prop `name` that renders `<svg class="fc-icon" viewBox="0 0 24 24" aria-hidden="true">`. It covers every current `ICONS` key plus `chevronLeft` (`M15 6l-6 6 6 6`) and `chevronRight` (`M9 6l6 6-6 6`). The page imports it as `BcIcon`, so the template stays unchanged.

- [ ] **Step 1:** Move `ICONS` and `BcIcon` into the new file, add the two chevrons, and import it in the page as `BcIcon`.
- [ ] **Step 2:** Run `npx vite build`. Expected: `built in` with no errors. Run `npm test`; expected: pass.
- [ ] **Step 3:** Commit: `git commit -am "Move customizer icons into a shared component"` (after `git add` of the new file).

### Task 3: Presentational components

**Files:**
- Create: `frontend/src/components/customizer/CustomizerSteps.vue`, `StemPicker.vue`, `SelectionCard.vue`, `OrderReceipt.vue`, `StageTools.vue`

**Interfaces (props → events). Each component uses `CustomizerIcon`, scoped CSS and brand tokens:**
- `CustomizerSteps`: props `steps` (from `buildSteps`), `active`, `done` (array of keys); emits `select(key)`. Renders a `<nav>` of buttons laid out as a 6-column grid, with the serif numeral above the label, a 2px `--rosewood` underline on `active`, and ink numerals for `done`. Uses `aria-current="step"` on the active one.
- `StemPicker`: props `items`, `kind` (`"flower" | "filler" | "card"`), `loading`, `search`, `emptyText`, `countOf` (function `(id) => number`), `canUse` (function), `pendingId`, `showFill` (boolean); emits `update:search`, `pick(item)`, `fill(item)`, `dragstart(event, item)`, `dragend`. Each row is a 46px thumbnail (image or a `flower`/`leaf`/`card` icon), the name, `₱price · n left` (or "Sold out" / "Full"), and on the right either "Add" or the placed count in rosewood serif. Loading shows four skeleton rows. Rows keep `role="button"`, `tabindex="0"`, Enter/Space, and `draggable`. The search field is shown only for `kind === "flower" | "filler"`.
- `SelectionCard`: props `title`, `subtitle` (e.g. `"1 of 4"`), `mode` (`"flower" | "bouquet"`), `locked`, `scale`, `rotation` (`{xDeg,yDeg,zDeg}`), `turn`, `moveLocked`, `style` (absolute position object); emits `turn(delta)` / `set-turn(deg)`, `scale(value)`, `rotate(axis, deg)`, `nudge-rotate(axis, delta)`, `nudge(dir, event)`, `lock`, `remove`, `reset`. The default view shows Turn (− value° +), Size slider (0.25–2, step 0.01; hidden for `bouquet`), a "Fine-tune ›" toggle, then Lock and Remove (Remove in `--rosewood`, hidden for `bouquet`). Fine-tune expands in place to the Tilt/Turn/Lean axis rows (labels from the page's `ROTATION_AXES`, passed as prop `axes`), the four-way pad with the same aria-labels and titles as today ("Move up", "Up (↑)"…), the Shift/E/Q note, and the reset text button ("Reset size and angle" or "Put the bouquet back in the centre"). For a flower, Turn maps to the y axis.
- `OrderReceipt`: props `stems`, `maxStems`, `lines` (from `receiptLines`), `total`, `busy`, `disabled`, `editing`; emits `add-to-cart`, `checkout`. Renders "Stems  n of max", a 2px progress line, the lines (amount `0` shows "Included"), `TOTAL` caps + serif total, then two buttons in a `1fr 1.35fr` grid: outline "Add to cart" (or "Update cart" when `editing`) and ink "Checkout" ("Processing…" when `busy`). Shows "Add at least one stem to continue." when `stems === 0`.
- `StageTools`: props `mode`, `hasTarget`, `bouquetSelected`, `ready`, `fullscreen`, `canReset`; emits `mode(value)`, `toggle-bouquet`, `focus`, `reset`, `screenshot`, `fullscreen`. The top-left vertical stack holds the bouquet select, a divider, Move/Rotate/Size (Size disabled when `bouquetSelected`), a divider, then Focus. The top right holds bare icons for Start over, Save as image, and Full screen. Tooltips are the existing `data-tip` text with the key (`"Move (G)"`). The active mode is ink-filled.

- [ ] **Step 1:** Create the five components as above.
- [ ] **Step 2:** Run `npx vite build`. Expected: `built in`. The components aren't used yet, so the page is unchanged.
- [ ] **Step 3:** Commit: `git add frontend/src/components/customizer && git commit -m "Add customizer redesign components"`

### Task 4: New page layout and template

**Files:**
- Modify: `frontend/src/views/authenticated/FlowerCustomizer.vue` (template lines 1–765, the layout refs, and the scoped `<style>` blocks)

**Interfaces:**
- Consumes: the Task 1 functions and the Task 3 components.
- Produces, in the page: `activeStep` (ref, default `"flowers"`), `steps` (computed `buildSteps({ hasFillers: fillers.length > 0, hasWrapper, hasRibbon, hasCards: cards.length > 0 })`), `doneSteps` (computed: `flowers` when stems > 0, `fillers` when fillers are placed, `wrap` when a wrap is chosen, `ribbon` when a ribbon is chosen, `card` when a card is placed), and `selectionCardStyle` (ref `{left, top}` in px).

- [ ] **Step 1: Replace the template structure** with: `NavHeader` → `.fc-shell` → `.fc-layout` (ref `layoutContainer`, a grid `340px 1fr`, height `calc(100dvh - var(--nav-h, 72px))`) containing:
  - `aside.fc-sheet`: the header (back link to the shop using the existing store route and `storeName`, the `h1` "Build your bouquet", the occasion line from `presetLabel` + design name, and a **Change** link button calling `openStart`), then `CustomizerSteps`, then `.fc-sheet-body` (scrolls), switching on `activeStep`:
    - `flowers` → `StemPicker kind="flower"` bound to `filteredFlowers`, `search`, `selectFlower`, `fillWithFlower`, `onDragStart`, `onDragEnd`, `countOfFlower`, `canUseFlower`. The "no store" note stays.
    - `fillers` → `StemPicker kind="filler"` with `filteredFillers`, plus the "n of max used" line.
    - `wrap` → the existing wrapper markup (layer segment, options or swatches), unchanged in behavior and restyled.
    - `ribbon` → the existing ribbon markup.
    - `card` → `StemPicker kind="card"`, then the existing placed-card settings (size segment, open switch, message, edit button).
    - `review` → the "On the bouquet" list (existing markup: select, lock, remove) and the "hand-tied" note when there's no wrapper.
  - The sheet ends with `OrderReceipt`.
  - `main.fc-stage` keeps all its current drag/drop handlers and the `viewerContainer` div. It contains `StageTools`, the load-error and skeleton states, `SelectionCard` (when `hasTransformTarget`), and the hint line. The hint shows `dragFeedback || stageHint`; when idle it reads `Drag to look around · G move · R rotate · S size` with `<kbd>`. Cancel placing stays.
  - The start screen and card editor modal stay as they are (markup unchanged).
- [ ] **Step 2: Remove the dead UI state.** Remove the stem-slot header, `isLeftSidebarCollapsed`/`isRightSidebarCollapsed`, `toggleLeftSidebar`/`toggleRightSidebar`, and the `stemSlots` computed if nothing else uses it (check with grep). Add a `ResizeObserver` on the stage element that calls `queueResize()`. Connect it in `onMounted` after the scene is built and disconnect it in `onUnmounted`.
- [ ] **Step 3: Anchor the selection card.** In `animateScene`, when `hasTransformTarget` is true, project the selection's world bounding-box center (the selected flower object, or the bouquet root) with `vector.project(camera)`. Convert it to stage pixels and call `anchorCard` with the card's measured size (default `220×200`). Write the result to `selectionCardStyle` only when it moves by more than 1px. When the card is wider than the stage minus 32px (phones), skip anchoring and let CSS dock it to the bottom.
- [ ] **Step 4: When the steps change,** a `watch(steps)` sets `activeStep` to `"flowers"` if the current key is no longer in the list. Selecting a stem from the Review list sets `selectedSceneId` as today. Picking a card in the Card step keeps the existing place-then-click flow.
- [ ] **Step 5: Rewrite the scoped CSS** for the new structure: the sheet (`--paper` with a right hairline), header, steps, body, the receipt pinned with `position: sticky; bottom: 0`, the stage with the gradient, the hint with keycaps, and the restyled start screen (same editorial tokens, cards with hairlines, no heavy shadows). Delete every rule for removed classes (`.fc-head*`, `.fc-stem*`, `.fc-catalog`, `.fc-inspector*`, `.fc-toolbar*`, `.fc-grid`, `.fc-tile*`, `.fc-order*`). Keep `.fc-page button { font-family: var(--font-sans) }` only. Add the `@media (max-width: 900px)` rules: stage first at `55vh`, sheet below, steps scrolling horizontally, the receipt as a sticky bottom bar, and the selection card docked to the stage's bottom at full width.
- [ ] **Step 6: Verify.** Run `npx vite build` (expected `built in`) and `npm test` (expected all pass). Run `grep -n "fc-toolbar\|fc-inspector\|isLeftSidebarCollapsed" src/views/authenticated/FlowerCustomizer.vue`; expected: no output.
- [ ] **Step 7: Commit:** `git commit -am "Rebuild the customizer layout as the Studio design"`

### Task 5: Screenshots against the mockup

**Files:**
- Create: `frontend/scripts/shoot-customizer.mjs`, a dev-only helper that is not shipped

**Interfaces:**
- The helper takes `--url`, `--width`, `--height` and `--out`, and runs headless Edge (`--headless=new --use-angle=swiftshader --enable-unsafe-swiftshader --virtual-time-budget=15000 --screenshot`).

- [ ] **Step 1:** With the dev server on 5173, open the customizer for a store that has stems. Use the store id already used in earlier checks; nothing is saved or added to cart. Capture these at 1440×900: (a) the start screen, (b) an empty bouquet after picking Wedding · 12, (c) a placed and selected flower, (d) the Review step. Capture (b) and (c) again at 390×844. The interactive states (c, d) are reached with the Chrome extension if it's connected; otherwise the dev server is temporarily seeded with a URL query `?debugSelect=1`. If that seeding is needed, it must be removed before the commit.
- [ ] **Step 2:** Compare each shot to `design-v5.html`: one sheet with hairlines, serif step numerals with the rosewood underline, list rows, the receipt at the sheet bottom, the tool stack top left, bare icons top right, the selection card beside the flower and inside the stage, and keycap hints. Fix any mismatches in Task 4's CSS and re-shoot.
- [ ] **Step 3:** Commit the helper: `git add frontend/scripts/shoot-customizer.mjs && git commit -m "Add customizer screenshot helper"`

### Task 6: Behavior check of the controls

- [ ] **Step 1:** In the dev server: place a flower, then press G, R, S. The gizmo mode switches and the tool stack's active icon follows. F focuses. The arrows move the flower and E/Q move it forward and back, with Shift giving smaller steps. Delete removes it. Esc deselects; while placing, Esc cancels.
- [ ] **Step 2:** Type "grsf" into the flower search while a flower is selected. The text appears in the field and the mode doesn't change.
- [ ] **Step 3:** Switch the occasion from Romance (wrapper) to Wedding (no wrapper) while on the Wrap step. The Wrap step disappears, the view falls back to Flowers, and the numbers stay continuous.
- [ ] **Step 4:** Resize the window from 1440 down to 390 wide and toggle fullscreen. The bouquet never stretches, and the selection card stays inside the stage.
- [ ] **Step 5:** Select the whole bouquet. Turn works, Size and Remove are hidden, and S does nothing.
- [ ] **Step 6:** Add a card and write a message. Click Checkout and Add to cart only up to the point where a request would go to the production API. Don't submit an order or attach anything to real products; stop at the confirmation and report.
- [ ] **Step 7:** Run `npm test` and `npx vite build` one final time. Expected: all pass and `built in`.
