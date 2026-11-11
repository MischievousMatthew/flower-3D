import { test } from "node:test";
import assert from "node:assert/strict";
import { explainLoginProblem, formatWait, loginProblem } from "../src/utils/loginProblems.js";

test("every wrong password says the tries left and how long the lock lasts", () => {
  const n = explainLoginProblem({ code: "invalid_credentials", attemptsLeft: 4, lockoutMinutes: 15 });
  assert.equal(n.tone, "info");
  assert.equal(n.field, "password");
  assert.equal(n.inline, true);
  assert.deepEqual(n.actions, ["forgot"]);
  assert.equal(n.body, "Wrong username or password: 4 tries left before sign-in locks for 15 minutes.");
});

test("the last try gets more attention, in one sentence", () => {
  const n = explainLoginProblem({ code: "invalid_credentials", attemptsLeft: 1, lockoutMinutes: 1 });
  assert.equal(n.tone, "warn");
  assert.equal(n.body, "Wrong username or password: 1 try left, then sign-in locks for 1 minute.");
  assert.equal(n.body.split(". ").length, 1, "one sentence");
});

test("without the count (admin setting off) it still says what went wrong", () => {
  const n = explainLoginProblem({ code: "invalid_credentials" });
  assert.doesNotMatch(n.body, /tries? left/);
  assert.equal(n.inline, true);
});

test("a lock shows a countdown and a way out", () => {
  const n = explainLoginProblem({ code: "account_locked", retryAfter: 840 });
  assert.equal(n.tone, "lock");
  assert.equal(n.countdown, 840);
  assert.deepEqual(n.actions, ["forgot"]);
});

test("no answer from the server is not called a wrong password", () => {
  assert.deepEqual(loginProblem({ message: "Network Error" }).code, typeof navigator !== "undefined" && navigator.onLine === false ? "offline" : "unreachable");
  assert.equal(loginProblem({ response: { status: 502, data: {} } }).code, "unreachable");
  assert.equal(explainLoginProblem({ code: "unreachable" }).tone, "offline");
});

test("server details are passed through", () => {
  const p = loginProblem({ response: { status: 422, data: { code: "account_locked", retry_after: 90 } } });
  assert.deepEqual(p, { status: 422, code: "account_locked", attemptsLeft: null, lockoutMinutes: null, retryAfter: 90 });
  assert.equal(loginProblem({ response: { status: 429, data: {}, headers: { "retry-after": "30" } } }).retryAfter, 30);
});

test("waits read naturally", () => {
  assert.equal(formatWait(45), "45s");
  assert.equal(formatWait(245), "4:05");
});
