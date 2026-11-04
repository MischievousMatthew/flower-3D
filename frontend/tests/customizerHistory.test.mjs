import test from "node:test";
import assert from "node:assert/strict";
import { createHistory, pickUnit, tidyGroups, togglePick } from "../src/utils/customizerHistory.js";

test("undo and redo walk the recorded changes", () => {
  const history = createHistory();
  history.reset("a");
  assert.equal(history.canUndo(), false);
  assert.equal(history.record("b"), true);
  assert.equal(history.record("b"), false); // no change, no entry
  history.record("c");
  assert.equal(history.undo(), "b");
  assert.equal(history.undo(), "a");
  assert.equal(history.undo(), null);
  assert.equal(history.redo(), "b");
  assert.equal(history.canRedo(), true);
});

test("a new change after undo drops the undone ones", () => {
  const history = createHistory();
  history.reset("a");
  history.record("b");
  history.record("c");
  history.undo();
  history.record("d");
  assert.equal(history.canRedo(), false);
  assert.equal(history.undo(), "b");
});

test("history keeps only the newest entries", () => {
  const history = createHistory(3);
  history.reset("a");
  ["b", "c", "d"].forEach((s) => history.record(s));
  assert.equal(history.undo(), "c");
  assert.equal(history.undo(), "b");
  assert.equal(history.undo(), null);
});

const items = [
  { sceneId: "1", group: "g" },
  { sceneId: "2", group: "g" },
  { sceneId: "3", group: null },
  { sceneId: "4", group: "h" },
];

test("clicking a grouped item picks its whole group", () => {
  assert.deepEqual(pickUnit(items, "2"), ["1", "2"]);
  assert.deepEqual(pickUnit(items, "3"), ["3"]);
  assert.deepEqual(togglePick(items, ["3"], "1"), ["3", "1", "2"]);
  assert.deepEqual(togglePick(items, ["3", "1", "2"], "2"), ["3"]);
});

test("a group of one is dissolved", () => {
  assert.deepEqual(tidyGroups(items).map((i) => i.group), ["g", "g", null, null]);
});
