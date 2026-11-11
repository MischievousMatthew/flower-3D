import { test } from "node:test";
import assert from "node:assert/strict";
import { generatePassword, passwordOk } from "../src/utils/passwordRules.js";

test("a suggested password always meets the rules", () => {
  for (let i = 0; i < 500; i++) {
    const pw = generatePassword();
    assert.equal(pw.length, 16);
    assert.ok(passwordOk(pw), pw);
    assert.ok(passwordOk(pw, 10), "long enough for administrators too");
  }
});

test("no look-alike characters", () => {
  for (let i = 0; i < 200; i++) assert.doesNotMatch(generatePassword(), /[0O1lI]/);
});

test("never shorter than the minimum, and different each time", () => {
  assert.equal(generatePassword(6, 10).length, 10);
  const seen = new Set(Array.from({ length: 50 }, () => generatePassword()));
  assert.equal(seen.size, 50);
});
