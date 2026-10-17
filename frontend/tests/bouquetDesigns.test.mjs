// Run with: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import * as THREE from "three";
import { DESIGNS, DESIGN_FOR_OCCASION, createBouquetBase, designForOccasion } from "../src/utils/bouquetDesigns/index.js";
import { classicTransform } from "../src/utils/bouquetDesigns/classic.js";
import { exportGlb } from "../src/components/vendor/photo3d/matchedModel.js";

globalThis.FileReader ??= class {
  readAsArrayBuffer(blob) {
    blob.arrayBuffer().then((result) => {
      this.result = result;
      this.onloadend?.();
    });
  }
};

const GENERATED = ["korean", "hand_tied", "box", "basket"];
const colorOf = (layer) => [].concat(layer.meshes[0].material)[0].color.getHexString();

test("every occasion has a design, and unknown ones fall back to classic", () => {
  for (const design of Object.values(DESIGN_FOR_OCCASION)) assert.ok(DESIGNS[design], design);
  assert.equal(designForOccasion("wedding"), "hand_tied");
  assert.equal(designForOccasion("romance"), "korean");
  assert.equal(designForOccasion("birthday"), "box");
  assert.equal(designForOccasion("sympathy"), "basket");
  assert.equal(designForOccasion("graduation"), "classic");
  assert.equal(designForOccasion(undefined), "classic");
});

for (const design of GENERATED) {
  test(`${design}: stands on the floor, centred, at the classic bouquet's size`, async () => {
    const base = await createBouquetBase(design);
    const box = new THREE.Box3().setFromObject(base.root);
    assert.ok(Math.abs(box.min.y) < 0.02, `min y ${box.min.y}`);
    // A hat box is shorter than a wrap; flowers on its bed add the height
    assert.ok(box.max.y > 1.5 && box.max.y < 3.6, `height ${box.max.y}`);
    const c = box.getCenter(new THREE.Vector3());
    assert.ok(Math.abs(c.x) < 0.3 && Math.abs(c.z) < 0.3, `centre ${c.x},${c.z}`);
  });

  test(`${design}: has a flower bed to place flowers on`, async () => {
    const base = await createBouquetBase(design);
    assert.ok(base.targets.length >= 1);
    assert.equal(base.targets[0].name, "FlowerBed");
    // A ray straight down through the middle hits the bed
    const ray = new THREE.Raycaster(new THREE.Vector3(0, 10, 0), new THREE.Vector3(0, -1, 0));
    base.root.updateMatrixWorld(true);
    assert.ok(ray.intersectObjects(base.targets, true).length > 0);
  });

  test(`${design}: layers recolour and go back to the design's own colour`, async () => {
    const base = await createBouquetBase(design);
    const { outer, inner } = base.dressing;
    for (const layer of [outer, inner]) {
      if (!layer.meshes.length) continue;
      layer.apply("#123456");
      assert.equal(colorOf(layer), "123456");
      layer.apply(null);
      assert.equal(colorOf(layer), new THREE.Color(layer.defaultColor).getHexString());
    }
    base.dressing.applyRibbon(null);
    base.dressing.applyRibbon("#ff0000");
  });

  test(`${design}: exports as a small model`, async () => {
    const base = await createBouquetBase(design);
    const blob = await exportGlb(base.root);
    assert.ok(blob.size < 400 * 1024, `${(blob.size / 1024).toFixed(0)} KB`);
  });
}

test("the hand-tied wedding bouquet has no paper", async () => {
  const base = await createBouquetBase("hand_tied");
  assert.equal(base.dressing.outer.meshes.length, 0);
  assert.equal(base.dressing.wrapMeshes.length, 0);
});

test("wrapper size grows the base about the floor centre", async () => {
  const small = new THREE.Box3().setFromObject((await createBouquetBase("box")).root);
  const big = new THREE.Box3().setFromObject((await createBouquetBase("box", { wrapperScale: 1.35 })).root);
  assert.ok(Math.abs(big.min.y) < 0.03, "still on the floor");
  assert.ok(Math.abs(big.max.y / small.max.y - 1.35) < 0.01);
});

test("older bouquets keep the original model, fillers and all", async () => {
  const { LEGACY_DESIGN } = await import("../src/utils/bouquetDesigns/index.js");
  assert.equal(LEGACY_DESIGN, "classic_full");
  assert.ok(!(LEGACY_DESIGN in DESIGNS), "never offered for new bouquets");
});

test("classic keeps the original model's 3.5-unit frame", () => {
  const { scale, position } = classicTransform({ min: [0, 10, 0], max: [2, 17, 4] });
  assert.equal(scale, 0.5); // 3.5 / 7 (height is the largest side)
  assert.deepEqual(position, [-0.5, -5, -1]);
});
