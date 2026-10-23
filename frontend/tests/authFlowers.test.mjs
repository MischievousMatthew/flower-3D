import { test } from "node:test";
import assert from "node:assert/strict";

const store = {};
globalThis.sessionStorage = { getItem: (k) => store[k] ?? null, setItem: (k, v) => (store[k] = String(v)) };
const { FLOWERS, pickFlower } = await import("../src/components/auth/flowers.js");

test("tulip, sunflower, rose and daisy are all drawn", () => {
  assert.deepEqual(FLOWERS.map((f) => f.key).sort(), ["daisy", "rose", "sunflower", "tulip"]);
  for (const f of FLOWERS) assert.ok(f.shapes.length > 4 && f.shapes.every((s) => s.d || s.r), f.key);
});

test("the same flower never shows twice in a row, and every flower turns up", () => {
  const seen = new Set();
  let last = null;
  for (let i = 0; i < 200; i++) {
    const f = pickFlower();
    assert.notEqual(f.key, last);
    last = f.key;
    seen.add(f.key);
  }
  assert.equal(seen.size, FLOWERS.length);
});
