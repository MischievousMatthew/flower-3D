import { test } from "node:test";
import assert from "node:assert/strict";
import { passwordOk, passwordPercent, passwordRules } from "../src/utils/passwordRules.js";

test("the percentage is how much of the list is done", () => {
  assert.equal(passwordPercent(""), 0);
  assert.equal(passwordPercent("petals"), 25);
  assert.equal(passwordPercent("Petals"), 50);
  assert.equal(passwordPercent("Petals2"), 75);
  assert.equal(passwordPercent("Petals22"), 100);
});

test("administrators need 10 characters", () => {
  assert.equal(passwordRules("Petals22", 10)[0].label, "10 or more characters");
  assert.equal(passwordOk("Petals22", 10), false);
  assert.equal(passwordPercent("Petals22", 10), 75);
  assert.equal(passwordOk("Petals2222", 10), true);
});
