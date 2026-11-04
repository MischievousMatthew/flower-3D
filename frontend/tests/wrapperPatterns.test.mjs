import test from "node:test";
import assert from "node:assert/strict";
import { cleanPatternWord, normalizePattern, PATTERN_DESIGNS } from "../src/utils/wrapperPatterns.js";
import { wrapperPatternLabel } from "../src/components/vendor/orders/orderUi.js";

test("a print word is trimmed, cleaned and kept short", () => {
  assert.equal(cleanPatternWord("  love  "), "love");
  assert.equal(cleanPatternWord("<b>hi</b>"), "bhib");
  assert.equal(cleanPatternWord("happy   birthday to you!!"), "happy birthday t");
  assert.equal(cleanPatternWord("Mahal kita ♥"), "Mahal kita ♥");
});

test("only newspaper and words with a word are prints; anything else is plain", () => {
  assert.deepEqual(normalizePattern({ type: "newspaper", text: "x" }), { type: "newspaper" });
  assert.deepEqual(normalizePattern({ type: "words", text: " love " }), { type: "words", text: "love" });
  assert.equal(normalizePattern({ type: "words", text: "  " }), null);
  assert.equal(normalizePattern({ type: "plain" }), null);
  assert.equal(normalizePattern(null), null);
  assert.deepEqual(PATTERN_DESIGNS, ["classic", "korean"]);
});

test("order screens name the print", () => {
  assert.equal(wrapperPatternLabel({ type: "newspaper" }), "Newspaper print");
  assert.equal(wrapperPatternLabel({ type: "words", text: "love" }), "Words: “love”");
  assert.equal(wrapperPatternLabel(null), "");
});

test("a word print keeps its size, kept between 50% and 200%", async () => {
  const { cleanPatternSize } = await import("../src/utils/wrapperPatterns.js");
  assert.deepEqual(normalizePattern({ type: "words", text: "love", size: 150 }), { type: "words", text: "love", size: 150 });
  assert.deepEqual(normalizePattern({ type: "words", text: "love", size: 100 }), { type: "words", text: "love" });
  assert.equal(cleanPatternSize(10), 50);
  assert.equal(cleanPatternSize(999), 200);
  assert.equal(cleanPatternSize(123), 125);
  assert.equal(cleanPatternSize(null), 100);
  assert.equal(cleanPatternSize("abc"), 100);
});
