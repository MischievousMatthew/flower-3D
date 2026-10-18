import test from "node:test";
import assert from "node:assert/strict";
import { anchorCard, buildSteps, keyAction, receiptLines } from "../src/utils/customizerUi.js";

const label = (steps) => steps.map((s) => `${s.number} ${s.label}`);
const ready = { ready: true, modalOpen: false, hasTarget: true, bouquetSelected: false, hasSelectedFlower: true, placing: false };
const key = (k, extra = {}) => ({ key: k, ctrlKey: false, metaKey: false, altKey: false, inField: false, ...extra });

test("all steps when everything exists", () => {
  assert.deepEqual(label(buildSteps({ hasFillers: true, hasWrapper: true, hasRibbon: true, hasCards: true })), [
    "01 Flowers",
    "02 Fillers",
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
  assert.equal(lines[1].amount, 40);
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
  assert.equal(keyAction(key("g", { ctrlKey: true }), ready), null);
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
