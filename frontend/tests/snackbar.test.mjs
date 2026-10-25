import { test } from "node:test";
import assert from "node:assert/strict";
import { hold, release, snackbars, toast } from "../src/utils/snackbar.js";

const texts = () => snackbars.items.map((s) => s.text);

test("never more than two: a new one pushes out the oldest", () => {
  toast.dismiss();
  toast.success("one");
  toast.error("two");
  toast.info("three");
  assert.deepEqual(texts(), ["two", "three"]);
});

test("the one under the pointer stays; the other one makes room", () => {
  toast.dismiss();
  toast.success("kept");
  toast.info("goes");
  hold(snackbars.items[0].id);
  toast.warning("new");
  assert.deepEqual(texts(), ["kept", "new"]);
  release(snackbars.items[0].id);
  toast.dismiss();
});

test("the same message replaces itself instead of stacking", () => {
  toast.dismiss();
  toast.error("Couldn't save.", { toastId: "save" });
  toast.error("Still couldn't save.", { toastId: "save" });
  assert.deepEqual(texts(), ["Still couldn't save."]);
  toast.dismiss();
});
