import { test } from "node:test";
import assert from "node:assert/strict";
import {
  averageDescriptor, colorDelta, createBlinkCounter, createChallengeJudge, descriptorDistance, eyeRatio, flashMatches, yaw,
} from "../src/components/hr/attendance/attendanceMath.js";

const eye = (open) => [
  { x: 0, y: 0 }, { x: 1, y: -open }, { x: 2, y: -open }, { x: 3, y: 0 }, { x: 2, y: open }, { x: 1, y: open },
];

test("eye ratio is high when open and low when closed", () => {
  assert.ok(eyeRatio(eye(0.5)) > 0.3);
  assert.ok(eyeRatio(eye(0.1)) < 0.1);
});

test("a blink is a dip and a recovery; two are needed", () => {
  const counter = createBlinkCounter();
  const open = 0.32;
  const closed = 0.12;
  [open, open, open, closed, open, open].forEach((r) => counter.push(r));
  assert.equal(counter.count, 1);
  [closed, closed, open].forEach((r) => counter.push(r));
  assert.equal(counter.count, 2);
  const judge = createChallengeJudge("blink", { yaw: 0, width: 100 });
  const results = [open, open, closed, open, closed, open].map((ratio) => judge({ ratio }));
  assert.equal(results.at(-1), true);
});

test("turning left moves the nose to the image's right", () => {
  const left = [{ x: 0, y: 0 }];
  const right = [{ x: 10, y: 0 }];
  assert.ok(yaw(left, right, { x: 8, y: 5 }) > 0.2);
  const judge = createChallengeJudge("turn_left", { yaw: 0, width: 100 });
  const frames = Array.from({ length: 4 }, () => judge({ yaw: 0.3 }));
  assert.equal(frames.at(-1), true);
  const wrongWay = createChallengeJudge("turn_left", { yaw: 0, width: 100 });
  assert.equal([1, 2, 3, 4, 5].map(() => wrongWay({ yaw: -0.3 })).some(Boolean), false);
});

test("moving closer must be held, not a single jumpy frame", () => {
  const judge = createChallengeJudge("move_closer", { yaw: 0, width: 100 });
  assert.equal(judge({ width: 130 }), false);
  assert.equal(judge({ width: 100 }), false);
  [130, 130].forEach((w) => judge({ width: w }));
  assert.equal(judge({ width: 131 }), true);
});

test("flash matching agrees with the server's rule", () => {
  const before = [120, 100, 90];
  const samples = [
    { color: "red", delta: colorDelta(before, [128, 101, 90]) },
    { color: "blue", delta: colorDelta(before, [121, 101, 97]) },
    { color: "green", delta: colorDelta(before, [120, 100, 90]) }, // no reflection
    { color: "red", delta: colorDelta(before, [121, 108, 90]) }, // wrong channel
  ];
  assert.equal(flashMatches(samples), 2);
});

test("averaging descriptors and their distance", () => {
  const a = new Array(128).fill(0.1);
  const b = new Array(128).fill(0.3);
  const avg = averageDescriptor([a, b]);
  assert.equal(avg[0], 0.2);
  assert.ok(Math.abs(descriptorDistance(a, b) - Math.sqrt(128 * 0.04)) < 1e-9);
});
