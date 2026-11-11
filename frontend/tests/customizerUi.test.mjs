import test from "node:test";
import assert from "node:assert/strict";
import { anchorCard, bouquetShortfall, buildSteps, fillFlowerChoice, keyAction, placeTip, receiptLines } from "../src/utils/customizerUi.js";

const label = (steps) => steps.map((s) => `${s.number} ${s.label}`);
const ready = { ready: true, modalOpen: false, hasTarget: true, bouquetSelected: false, hasSelectedFlower: true, placing: false };
const key = (k, extra = {}) => ({ key: k, ctrlKey: false, metaKey: false, altKey: false, inField: false, ...extra });

test("all steps when everything exists", () => {
  assert.deepEqual(label(buildSteps({ hasFillers: true, hasWrapper: true, hasRibbon: true, hasCards: true })), [
    "01 Fillers",
    "02 Flowers",
    "03 Wrap",
    "04 Ribbon",
    "05 Card",
    "06 Review",
  ]);
});

test("hides absent steps and renumbers", () => {
  assert.deepEqual(label(buildSteps({ hasFillers: false, hasWrapper: false, hasRibbon: true, hasCards: false })), [
    "01 Flowers",
    "02 Ribbon",
    "03 Review",
  ]);
});

test("receipt lists present items in order", () => {
  const lines = receiptLines({
    flowers: [{ price: 150 }, { price: 150 }],
    fillers: [],
    outerWrapper: null,
    innerWrapper: null,
    ribbon: { name: "Ivory satin", price: 0 },
    card: null,
  });
  assert.deepEqual(lines, [
    { label: "Stems (2)", amount: 300 },
    { label: "Ivory satin", amount: 0 },
  ]);
});

test("receipt names fillers, wrappers and the card", () => {
  const lines = receiptLines({
    flowers: [{ price: 100 }],
    fillers: [{ price: 20 }, { price: 20 }],
    outerWrapper: { name: "Kraft", price: 50 },
    innerWrapper: { name: "Blush", price: 0 },
    ribbon: null,
    card: { product_name: "Note", size: "Small", price: 30 },
  });
  assert.deepEqual(lines.map((l) => l.label), ["Stems (1)", "Fillers (2)", "Outer: Kraft", "Inner: Blush", "Card: Note (Small)"]);
  // Included in the occasion's base price
  assert.equal(lines[1].amount, 0);
  assert.equal(lines[2].amount, 0);
  assert.equal(lines[4].amount, 30);
});

test("receipt shows the occasion's base price after the stems", () => {
  const lines = receiptLines({ flowers: [{ price: 100 }], base: { label: "Wedding", amount: 500 } });
  assert.deepEqual(lines, [
    { label: "Stems (1)", amount: 100 },
    { label: "Base price: Wedding", amount: 500 },
  ]);
  assert.equal(receiptLines({ flowers: [], base: { label: "Wedding", amount: 0 } }).length, 1);
});

test("G R S F map to modes and focus", () => {
  assert.equal(keyAction(key("g"), ready), "mode:translate");
  assert.equal(keyAction(key("R"), ready), "mode:rotate");
  assert.equal(keyAction(key("s"), ready), "mode:scale");
  assert.equal(keyAction(key("f"), ready), "focus");
});

test("arrows and E Q nudge", () => {
  assert.equal(keyAction(key("ArrowLeft"), ready), "nudge:left");
  assert.equal(keyAction(key("ArrowRight"), ready), "nudge:right");
  assert.equal(keyAction(key("ArrowUp"), ready), "nudge:up");
  assert.equal(keyAction(key("ArrowDown"), ready), "nudge:down");
  assert.equal(keyAction(key("e"), ready), "nudge:forward");
  assert.equal(keyAction(key("q"), ready), "nudge:backward");
});

test("Delete and Backspace remove only a flower", () => {
  assert.equal(keyAction(key("Delete"), ready), "remove");
  assert.equal(keyAction(key("Backspace"), ready), "remove");
  const bouquet = { ...ready, bouquetSelected: true, hasSelectedFlower: false };
  assert.equal(keyAction(key("Delete"), bouquet), null);
});

test("S is ignored when the bouquet is selected", () => {
  assert.equal(keyAction(key("s"), { ...ready, bouquetSelected: true, hasSelectedFlower: false }), null);
});

test("Esc cancels placing first, then deselects", () => {
  assert.equal(keyAction(key("Escape"), { ...ready, placing: true }), "cancel-placing");
  assert.equal(keyAction(key("Escape"), ready), "deselect");
  assert.equal(keyAction(key("Escape"), { ...ready, hasTarget: false }), "deselect");
});

test("ignores keys typed into fields", () => {
  assert.equal(keyAction(key("g", { inField: true }), ready), null);
  assert.equal(keyAction(key("k", { ctrlKey: true }), ready), null);
  assert.equal(keyAction(key("z", { ctrlKey: true, inField: true }), ready), null);
  assert.equal(keyAction(key("g"), { ...ready, modalOpen: true }), null);
  assert.equal(keyAction(key("g"), { ...ready, ready: false }), null);
});

test("keys need a target", () => {
  const none = { ...ready, hasTarget: false, hasSelectedFlower: false };
  assert.equal(keyAction(key("g"), none), null);
  assert.equal(keyAction(key("ArrowLeft"), none), null);
});

test("anchorCard keeps the card inside the stage", () => {
  const size = { cardWidth: 220, cardHeight: 180 };
  const nearRight = anchorCard({ x: 900, y: 40 }, { width: 1000, height: 700 }, size);
  assert.equal(nearRight.side, "left");
  assert.ok(nearRight.left + 220 <= 984);
  assert.ok(nearRight.top >= 16);

  const nearBottom = anchorCard({ x: 300, y: 690 }, { width: 1000, height: 700 }, size);
  assert.equal(nearBottom.side, "right");
  assert.equal(nearBottom.left, 324);
  assert.ok(nearBottom.top + 180 <= 684);
});

test("Ctrl+Z undoes, Ctrl+Y and Ctrl+Shift+Z redo, also with nothing selected", () => {
  const none = { ...ready, hasTarget: false, hasSelectedFlower: false };
  assert.equal(keyAction(key("z", { ctrlKey: true }), none), "undo");
  assert.equal(keyAction(key("y", { ctrlKey: true }), none), "redo");
  assert.equal(keyAction(key("Z", { ctrlKey: true, shiftKey: true }), none), "redo");
  assert.equal(keyAction(key("z", { metaKey: true }), none), "undo");
  assert.equal(keyAction(key("z", { ctrlKey: true }), { ...none, modalOpen: true }), null);
});

test("Ctrl+G groups, Ctrl+Shift+G ungroups, Esc stops picking", () => {
  assert.equal(keyAction(key("g", { ctrlKey: true }), ready), "group");
  assert.equal(keyAction(key("g", { ctrlKey: true }), { ...ready, hasSelectedFlower: false }), null);
  assert.equal(keyAction(key("g", { ctrlKey: true, shiftKey: true }), ready), null);
  assert.equal(keyAction(key("g", { ctrlKey: true, shiftKey: true }), { ...ready, grouped: true }), "ungroup");
  assert.equal(keyAction(key("Escape"), { ...ready, grouping: true, placing: true }), "cancel-grouping");
});

const view = { width: 1400, height: 900 };
const card = { width: 320, height: 200 };

test("guide card sits right of a part with room, arrow pointing left at it", () => {
  const tip = placeTip({ left: 100, top: 300, width: 300, height: 200 }, view, card);
  assert.equal(tip.side, "right");
  assert.equal(tip.left, 430);
  assert.equal(tip.arrow.dir, "left");
});

test("guide card flips left near the right edge", () => {
  const tip = placeTip({ left: 1150, top: 300, width: 200, height: 100 }, view, card);
  assert.equal(tip.side, "left");
  assert.equal(tip.arrow.dir, "right");
});

test("guide card docks away from a part filling the screen", () => {
  const tip = placeTip({ left: 0, top: 0, width: 1400, height: 400 }, view, card);
  assert.equal(tip.side, "bottom");
  const big = placeTip({ left: 0, top: 0, width: 1400, height: 800 }, view, card);
  assert.equal(big.side, "dock-bottom");
  assert.equal(big.arrow.dir, "up");
});

test("phones stack the card above or below, never beside", () => {
  const tip = placeTip({ left: 20, top: 100, width: 100, height: 60 }, { width: 390, height: 800 }, card);
  assert.equal(tip.side, "bottom");
  assert.ok(tip.left >= 16 && tip.left + card.width <= 390 - 16 + 1);
});

test("no part to point at centres the card", () => {
  assert.equal(placeTip(null, view, card).side, "center");
});

test("a bouquet with fewer flowers than its size is short", () => {
  const bouquet = { type: "custom_flower_bouquet", preset: { key: "birthday", size: 3 }, flowers: [{ id: 1 }] };
  assert.deepEqual(bouquetShortfall(bouquet), { placed: 1, size: 3, missing: 2 });
  assert.equal(bouquetShortfall({ ...bouquet, flowers: [{}, {}, {}] }), null);
  assert.equal(bouquetShortfall({ ...bouquet, preset: null }), null);
  assert.equal(bouquetShortfall({ type: "other", preset: { size: 3 }, flowers: [] }), null);
});

test("fills with the most used flower that still has stock", () => {
  const flowers = [
    { id: 1, model: "a.glb", quantity_in_stock: 5 },
    { id: 2, model: "b.glb", quantity_in_stock: 2 },
    { id: 3, model: null, quantity_in_stock: 9 },
  ];
  assert.equal(fillFlowerChoice(flowers, { 1: 1, 2: 2 }).id, 1); // rose 2 is used up
  assert.equal(fillFlowerChoice(flowers, { 1: 3 }).id, 1);
  assert.equal(fillFlowerChoice(flowers, {}).id, 1);
  assert.equal(fillFlowerChoice([{ id: 3, model: null, quantity_in_stock: 9 }], {}), null);
});
