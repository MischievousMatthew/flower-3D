import { test } from "node:test";
import assert from "node:assert/strict";
import { ref } from "vue";
import { rowsAfter, stepAfter, useShowMoreLocal, useShowMorePaged } from "../src/composables/useShowMore.js";

test("10 rows first, then 10 more, then 20 at a time", () => {
  assert.deepEqual([0, 1, 2, 3, 4].map(rowsAfter), [10, 20, 40, 60, 80]);
  assert.deepEqual([0, 1, 2, 5].map(stepAfter), [10, 20, 20, 20]);
});

test("a list already on the page shows more until it's all there", () => {
  const list = ref(Array.from({ length: 33 }, (_, i) => i));
  const s = useShowMoreLocal(list);
  assert.equal(s.visible.value.length, 10);
  s.showMore();
  assert.equal(s.visible.value.length, 20);
  s.showMore();
  assert.equal(s.visible.value.length, 33);
  assert.equal(s.hasMore.value, false);
});

test("server pages of any size are fetched only as rows are needed", async () => {
  const rows = Array.from({ length: 57 }, (_, i) => i);
  const asked = [];
  const s = useShowMorePaged(async (page) => {
    asked.push(page);
    return { items: rows.slice((page - 1) * 25, page * 25), lastPage: 3, total: 57 };
  });
  await s.load();
  assert.equal(s.visible.value.length, 10);
  assert.deepEqual(asked, [1]);
  await s.showMore();
  assert.equal(s.visible.value.length, 20);
  await s.showMore();
  assert.equal(s.visible.value.length, 40);
  assert.deepEqual(asked, [1, 2]);
  await s.showMore();
  await s.showMore();
  assert.equal(s.visible.value.length, 57);
  assert.equal(s.hasMore.value, false);
  assert.deepEqual(asked, [1, 2, 3]);
});

test("a page's own data object grows as rows are needed and keeps edits made in place", async () => {
  const { useShowMoreInto } = await import("../src/composables/useShowMore.js");
  const all = Array.from({ length: 30 }, (_, i) => ({ id: i, read: false }));
  const data = ref(null);
  const s = useShowMoreInto(data, "items", async (page) => ({ items: all.slice((page - 1) * 25, page * 25).map((x) => ({ ...x })), meta: { page, last_page: 2, total: 30 }, summary: "x" }));
  await s.load();
  assert.equal(s.rows.value.length, 10);
  assert.equal(data.value.summary, "x");
  data.value.items[0].read = true;
  await s.showMore();
  await s.showMore();
  assert.equal(s.rows.value.length, 30);
  assert.equal(s.rows.value[0].read, true);
  assert.equal(s.hasMore.value, false);
});
