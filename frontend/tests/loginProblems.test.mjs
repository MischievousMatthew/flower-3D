import { test } from "node:test";
import assert from "node:assert/strict";
import { explainLoginProblem, formatWait, loginProblem } from "../src/utils/loginProblems.js";

test("a wrong password points at the password and offers a reset", () => {
  const n = explainLoginProblem({ code: "invalid_credentials", attemptsLeft: 4 });
  assert.equal(n.tone, "info");
  assert.equal(n.field, "password");
  assert.deepEqual(n.actions, ["forgot"]);
  assert.doesNotMatch(n.body, /tries left/);
});

test("the last tries are counted and get more attention", () => {
  const n = explainLoginProblem({ code: "invalid_credentials", attemptsLeft: 1 });
  assert.equal(n.tone, "warn");
  assert.match(n.body, /1 try left/);
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
  assert.deepEqual(p, { status: 422, code: "account_locked", attemptsLeft: null, retryAfter: 90 });
  assert.equal(loginProblem({ response: { status: 429, data: {}, headers: { "retry-after": "30" } } }).retryAfter, 30);
});

test("waits read naturally", () => {
  assert.equal(formatWait(45), "45s");
  assert.equal(formatWait(245), "4:05");
});
