import { test } from "node:test";
import assert from "node:assert/strict";
import { availability, cells, coverage, rangeFor, time12, weekStart, weekdayName } from "../src/components/hr/schedules/scheduleMath.js";

test("weeks start on Monday; a month view covers whole weeks", () => {
  assert.equal(weekStart("2026-10-22"), "2026-10-19");
  assert.equal(weekStart("2026-10-19"), "2026-10-19");
  assert.equal(weekStart("2026-10-25"), "2026-10-19");
  assert.deepEqual(rangeFor("week", "2026-10-22"), { from: "2026-10-19", to: "2026-10-25" });
  assert.deepEqual(rangeFor("month", "2026-10-22"), { from: "2026-09-28", to: "2026-11-01" });
  assert.equal(weekdayName("2026-10-25"), "Sunday");
});

test("assignments land in their shift row, covered people last", () => {
  const map = cells([
    { kind: "shift", template_id: 1, date: "2026-10-19", employee: "John", status: "replaced" },
    { kind: "shift", template_id: 1, date: "2026-10-19", employee: "Jane", status: "scheduled" },
    { kind: "off", date: "2026-10-19", employee: "Ana", status: "scheduled" },
  ]);
  assert.deepEqual(map["t1|2026-10-19"].map((a) => a.employee), ["Jane", "John"]);
  assert.equal(map["off|2026-10-19"].length, 1);
});

test("coverage counts the people actually on a shift", () => {
  const morning = { headcount: 2, days: ["Monday"] };
  const list = [{ status: "scheduled" }, { status: "replaced" }];
  assert.deepEqual(coverage(morning, list, "2026-10-19"), { on: 1, need: 2, runs: true, short: 1, state: "short" });
  assert.equal(coverage(morning, [], "2026-10-20").state, "idle");
});

test("availability groups people for the chosen day and searches", () => {
  const staff = [
    { name: "John", availability: { "2026-10-19": { status: "shift" } }, week_hours: { "2026-10-19": 9 } },
    { name: "Jane", availability: { "2026-10-19": { status: "free" } }, week_hours: {} },
    { name: "Ana", availability: { "2026-10-19": { status: "leave" } }, week_hours: {} },
  ];
  const groups = availability(staff, "2026-10-19");
  assert.deepEqual(groups.map((g) => g.key), ["free", "working", "leave"]);
  assert.equal(groups[1].people[0].weekHours, 9);
  assert.deepEqual(availability(staff, "2026-10-19", "jan").map((g) => g.key), ["free"]);
});

test("times read naturally", () => {
  assert.equal(time12("08:00"), "8 AM");
  assert.equal(time12("17:30"), "5:30 PM");
  assert.equal(time12("00:15"), "12:15 AM");
});
