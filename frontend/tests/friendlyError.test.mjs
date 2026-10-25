import { test } from "node:test";
import assert from "node:assert/strict";
import { friendlyErrorText, isTechnical, makeFriendly, MESSAGES } from "../src/utils/friendlyError.js";

const fail = (status, data, method = "get") => ({ message: `Request failed with status code ${status}`, config: { method }, response: { status, data } });

test("readable server messages are kept as they are", () => {
  assert.equal(friendlyErrorText(fail(422, { message: "Only 3 roses are left in stock." }, "post")), "Only 3 roses are left in stock.");
  assert.equal(
    friendlyErrorText(fail(500, { message: "We couldn't load this right now. Please try again. If it keeps happening, contact support and mention code BC-7K2Q9F.", reference: "BC-7K2Q9F" })),
    "We couldn't load this right now. Please try again. If it keeps happening, contact support and mention code BC-7K2Q9F.",
  );
});

test("programmer errors never reach the screen", () => {
  const sql = 'SQLSTATE[42703]: Undefined column: 7 ERROR: column "tax_amount" does not exist';
  assert.equal(friendlyErrorText(fail(500, { message: sql }, "post")), MESSAGES.save);
  assert.equal(friendlyErrorText(fail(500, { message: sql, reference: "BC-AB12CD" })), `${MESSAGES.load} If it keeps happening, contact support and mention code BC-AB12CD.`);
  assert.ok(isTechnical("Call to undefined method App\\Models\\Order::taxes()"));
  assert.ok(!isTechnical("Your order was placed."));
});

test("no internet, timeouts and a waking server get their own words", () => {
  assert.equal(friendlyErrorText({ message: "Network Error", config: {} }), MESSAGES.offline);
  assert.equal(friendlyErrorText({ code: "ECONNABORTED", message: "timeout of 30000ms exceeded", config: {} }), MESSAGES.timeout);
  assert.equal(friendlyErrorText(fail(502, "<html><body>Bad gateway</body></html>")), MESSAGES.waking);
});

test("the error is rewritten in place for every screen", () => {
  const error = fail(500, { success: false, message: "Server Error", error: "SQLSTATE[HY000]: General error", code: "server_error" }, "delete");
  makeFriendly(error);
  assert.equal(error.message, MESSAGES.save);
  assert.equal(error.response.data.message, MESSAGES.save);
  assert.equal(error.response.data.error, undefined);
  // Codes other screens rely on stay
  assert.equal(error.response.data.code, "server_error");

  const html = fail(503, "<html>Service Unavailable</html>");
  makeFriendly(html);
  assert.deepEqual(html.response.data, { success: false, message: MESSAGES.waking });
});
