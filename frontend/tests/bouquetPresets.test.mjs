// Run with: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import {
  DEFAULT_PRESETS,
  LEGACY_PRESET,
  fillerLimitFor,
  presetsOrDefaults,
  spiralPoints,
  wrapperScaleFor,
} from "../src/constants/bouquetPresets.js";

test("the wrapper grows with the bouquet size", () => {
  assert.equal(wrapperScaleFor(3), 1);
  assert.equal(wrapperScaleFor(6), 1);
  assert.equal(wrapperScaleFor(12), 1.15);
  assert.equal(wrapperScaleFor(24), 1.35);
  assert.equal(wrapperScaleFor(36), 1.5);
  assert.equal(wrapperScaleFor(99), 1.5);
  // In between, it scales smoothly
  assert.ok(wrapperScaleFor(9) > 1 && wrapperScaleFor(9) < 1.15);
  assert.ok(Math.abs(wrapperScaleFor(18) - 1.25) < 1e-9);
});

test("fillers allowed follow the size, never below 5", () => {
  assert.equal(fillerLimitFor(3), 5);
  assert.equal(fillerLimitFor(12), 6);
  assert.equal(fillerLimitFor(24), 12);
  assert.equal(fillerLimitFor(25), 13);
});

test("defaults mirror the server and are used when the shop sends none", () => {
  assert.deepEqual(DEFAULT_PRESETS.map((p) => p.key), ["wedding", "romance", "graduation", "sympathy", "just_because"]);
  const wedding = DEFAULT_PRESETS[0];
  assert.equal(wedding.wrapper, false);
  assert.equal(wedding.ribbon, true);
  assert.deepEqual(presetsOrDefaults([]), DEFAULT_PRESETS);
  assert.deepEqual(presetsOrDefaults(null), DEFAULT_PRESETS);
  const own = [{ key: "graduation", label: "Graduation", wrapper: true, ribbon: true, sizes: [5] }];
  assert.deepEqual(presetsOrDefaults(own), own);
});

test("bouquets from before presets open as Just because with 3 stems", () => {
  assert.deepEqual(LEGACY_PRESET, { key: "just_because", size: 3 });
});

test("spiralPoints spreads points evenly inside the circle, none twice", () => {
  const pts = spiralPoints(24, 1);
  assert.equal(pts.length, 24);
  for (const p of pts) assert.ok(Math.hypot(p.x, p.z) <= 1 + 1e-9);
  const keys = new Set(pts.map((p) => `${p.x.toFixed(3)},${p.z.toFixed(3)}`));
  assert.equal(keys.size, 24);
  // The nearest neighbours aren't crammed together
  let minGap = Infinity;
  for (let i = 0; i < pts.length; i++)
    for (let j = i + 1; j < pts.length; j++) minGap = Math.min(minGap, Math.hypot(pts[i].x - pts[j].x, pts[i].z - pts[j].z));
  assert.ok(minGap > 0.15, `min gap ${minGap}`);
  assert.deepEqual(spiralPoints(0, 1), []);
});
