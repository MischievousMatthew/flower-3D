// Run with: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { extractPalette } from "../src/components/vendor/photo3d/photoPalette.js";
import { classifyAiError, convertWithAI } from "../src/components/vendor/photo3d/aiProviders.js";

// GLTFExporter reads blobs with FileReader, which Node lacks
globalThis.FileReader ??= class {
  readAsArrayBuffer(blob) {
    blob.arrayBuffer().then((result) => {
      this.result = result;
      this.onloadend?.();
    });
  }
  readAsDataURL(blob) {
    blob.arrayBuffer().then((buf) => {
      this.result = `data:${blob.type};base64,${Buffer.from(buf).toString("base64")}`;
      this.onloadend?.();
    });
  }
};

// 40×40 photo: white background, a red disc (the rose), a green band (leaves)
function rosePhoto() {
  const w = 40, h = 40;
  const data = new Uint8ClampedArray(w * h * 4);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = (y * w + x) * 4;
      let rgb = [250, 250, 250];
      if ((x - 20) ** 2 + (y - 14) ** 2 < 100) rgb = [200 + (x % 3) * 5, 20, 35];
      else if (y > 28 && x > 8 && x < 32) rgb = [70, 140, 60];
      data.set([...rgb, 255], i);
    }
  }
  return { data, width: w, height: h };
}

const hexToRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

test("extractPalette finds the petal color and the leaf color, not the background", () => {
  const { flowers, leaf } = extractPalette(rosePhoto());
  assert.ok(flowers.length >= 1, "at least one flower color");
  const [r, g, b] = hexToRgb(flowers[0]);
  assert.ok(r > 150 && g < 80 && b < 90, `main flower color is red, got ${flowers[0]}`);
  const [lr, lg, lb] = hexToRgb(leaf);
  assert.ok(lg > lr && lg > lb, `leaf is green, got ${leaf}`);
});

test("extractPalette falls back to a default when the photo is all background", () => {
  const w = 10, h = 10;
  const data = new Uint8ClampedArray(w * h * 4).fill(255);
  const { flowers, leaf } = extractPalette({ data, width: w, height: h });
  assert.equal(flowers.length, 1);
  assert.match(flowers[0], /^#[0-9a-f]{6}$/);
  assert.match(leaf, /^#[0-9a-f]{6}$/);
});

test("classifyAiError reads the ZeroGPU quota message and its reset time", () => {
  const e = classifyAiError(
    "You have exceeded your ZeroGPU quota (135s requested vs. 130s left). Try again in 23:58:03. Authenticate with a Hugging Face token",
  );
  assert.equal(e.code, "quota");
  assert.equal(e.retryInSeconds, 23 * 3600 + 58 * 60 + 3);
});

test("classifyAiError sorts other failures", () => {
  assert.equal(classifyAiError("Space is sleeping").code, "down");
  assert.equal(classifyAiError("Failed to fetch").code, "down");
  assert.equal(classifyAiError("Queue is full").code, "busy");
  assert.equal(classifyAiError("something odd").code, "down");
});

const okGlb = new Blob([new Uint8Array([0x67, 0x6c, 0x54, 0x46])], { type: "model/gltf-binary" });

test("convertWithAI tries the next provider when one is down", async () => {
  const tried = [];
  const providers = [
    { id: "a", label: "A", run: async () => { tried.push("a"); throw new Error("Space is sleeping"); } },
    { id: "b", label: "B", run: async () => { tried.push("b"); return okGlb; } },
  ];
  const result = await convertWithAI(new Blob(), { providers });
  assert.deepEqual(tried, ["a", "b"]);
  assert.equal(result.provider.id, "b");
  assert.equal(result.blob, okGlb);
});

// In-memory stand-in for localStorage
const memoryStorage = () => {
  const data = new Map();
  return { getItem: (k) => (data.has(k) ? data.get(k) : null), setItem: (k, v) => data.set(k, String(v)), removeItem: (k) => data.delete(k) };
};
const QUOTA = "You have exceeded your ZeroGPU quota (130s left). Try again in 0:10:00.";

test("a GPU quota error skips the other GPU models and uses the free CPU model", async () => {
  const tried = [];
  const providers = [
    { id: "gpu1", label: "GPU 1", pool: "zerogpu", run: async () => { tried.push("gpu1"); throw new Error(QUOTA); } },
    { id: "gpu2", label: "GPU 2", pool: "zerogpu", run: async () => { tried.push("gpu2"); return okGlb; } },
    { id: "cpu", label: "CPU", pool: "cpu", run: async () => { tried.push("cpu"); return okGlb; } },
  ];
  const result = await convertWithAI(new Blob(), { providers, storage: memoryStorage(), now: () => 1000 });
  assert.deepEqual(tried, ["gpu1", "cpu"]);
  assert.equal(result.provider.id, "cpu");
});

test("a remembered quota skips GPU models until it resets", async () => {
  const storage = memoryStorage();
  const tried = [];
  const providers = [
    { id: "gpu", label: "GPU", pool: "zerogpu", run: async () => { tried.push("gpu"); throw new Error(QUOTA); } },
    { id: "cpu", label: "CPU", pool: "cpu", run: async () => { tried.push("cpu"); return okGlb; } },
  ];
  await convertWithAI(new Blob(), { providers, storage, now: () => 0 });
  // 10 minutes later minus a second: still skipped
  await convertWithAI(new Blob(), { providers, storage, now: () => 599_000 });
  // After the reset: tried again
  await convertWithAI(new Blob(), { providers, storage, now: () => 601_000 });
  assert.deepEqual(tried, ["gpu", "cpu", "cpu", "gpu", "cpu"]);
});

test("when only GPU models exist and the quota is used up, the quota error is reported", async () => {
  const providers = [{ id: "gpu", label: "GPU", pool: "zerogpu", run: async () => { throw new Error(QUOTA); } }];
  await assert.rejects(
    convertWithAI(new Blob(), { providers, storage: memoryStorage(), now: () => 0 }),
    (err) => err.code === "quota" && err.retryInSeconds === 600,
  );
});

test("a bad photo stops at once instead of trying more models", async () => {
  const tried = [];
  const providers = [
    { id: "gpu", label: "GPU", pool: "zerogpu", run: async () => { tried.push("gpu"); throw new Error("No object found in the image"); } },
    { id: "cpu", label: "CPU", pool: "cpu", run: async () => { tried.push("cpu"); return okGlb; } },
  ];
  await assert.rejects(convertWithAI(new Blob(), { providers, storage: memoryStorage() }), (err) => err.code === "bad-photo");
  assert.deepEqual(tried, ["gpu"]);
});

test("convertWithAI reports the last failure when every provider fails", async () => {
  const providers = [
    { id: "a", label: "A", run: async () => { throw new Error("Failed to fetch"); } },
    { id: "b", label: "B", run: async () => { throw new Error("Queue is full"); } },
  ];
  await assert.rejects(convertWithAI(new Blob(), { providers }), (err) => err.code === "busy");
});

test("matched build exports a valid, small GLB for a single flower and a bouquet", async () => {
  const { buildMatchedModel, exportGlb } = await import("../src/components/vendor/photo3d/matchedModel.js");
  for (const options of [
    { kind: "single", head: "rose", colors: ["#c81e2d"], leaf: "#4c8a3c" },
    { kind: "bouquet", counts: { rose: 5, tulip: 2, daisy: 2 }, colors: ["#c81e2d", "#f2a7b8"], leaf: "#4c8a3c", paper: "#d3b18f" },
    // Worst case: the most heads, every type, three colors
    { kind: "bouquet", counts: { rose: 12, tulip: 4, daisy: 3, peony: 5 }, colors: ["#c81e2d", "#f2a7b8", "#ffffff"], leaf: "#4c8a3c" },
  ]) {
    const model = buildMatchedModel(options);
    const blob = await exportGlb(model);
    const bytes = new Uint8Array(await blob.arrayBuffer());
    assert.equal(String.fromCharCode(...bytes.slice(0, 4)), "glTF", `${options.kind} is a GLB`);
    assert.ok(blob.size < 2 * 1024 * 1024, `${options.kind} is ${blob.size} bytes`);
  }
});
