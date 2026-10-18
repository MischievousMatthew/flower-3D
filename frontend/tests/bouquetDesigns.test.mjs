// Run with: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import * as THREE from "three";
import { DESIGNS, DESIGN_FOR_OCCASION, createBouquetBase, designForOccasion } from "../src/utils/bouquetDesigns/index.js";
import { classicTransform } from "../src/utils/bouquetDesigns/classic.js";
import { NodeIO } from "@gltf-transform/core";
import { ALL_EXTENSIONS } from "@gltf-transform/extensions";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";

// Loads a model from public/ in Node: textures are dropped (no browser here),
// geometry and materials stay, which is all these checks need
const io = new NodeIO().registerExtensions(ALL_EXTENSIONS);
async function loadGltf(url) {
  const doc = await io.read(new URL(`../public${url}`, import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, "$1"));
  doc.getRoot().listTextures().forEach((t) => t.dispose());
  const glb = await io.writeBinary(doc);
  const buffer = glb.buffer.slice(glb.byteOffset, glb.byteOffset + glb.byteLength);
  return new Promise((resolve, reject) => new GLTFLoader().parse(buffer, "", resolve, reject));
}
const build = (design, options = {}) => createBouquetBase(design, { loadGltf, ...options });


// The shop's own models
const DESIGN_KEYS = ["korean", "hand_tied", "basket"];
const colorOf = (layer) => [].concat(layer.meshes[0].material)[0].color.getHexString();

test("every occasion has a design, and unknown ones fall back to classic", () => {
  for (const design of Object.values(DESIGN_FOR_OCCASION)) assert.ok(DESIGNS[design], design);
  assert.equal(designForOccasion("wedding"), "hand_tied");
  assert.equal(designForOccasion("romance"), "korean");
  assert.equal(designForOccasion("birthday"), "classic"); // not offered for now
  assert.equal(designForOccasion("sympathy"), "basket");
  assert.equal(designForOccasion("graduation"), "classic");
  assert.equal(designForOccasion(undefined), "classic");
});

for (const design of DESIGN_KEYS) {
  test(`${design}: stands on the floor, centred, at the classic bouquet's size`, async () => {
    const base = await build(design);
    const box = new THREE.Box3().setFromObject(base.root);
    assert.ok(Math.abs(box.min.y) < 0.02, `min y ${box.min.y}`);
    // A hat box is shorter than a wrap; flowers on its bed add the height
    assert.ok(box.max.y > 1.5 && box.max.y < 3.6, `height ${box.max.y}`);
    const c = box.getCenter(new THREE.Vector3());
    assert.ok(Math.abs(c.x) < 0.3 && Math.abs(c.z) < 0.3, `centre ${c.x},${c.z}`);
  });

  test(`${design}: has a flower bed to place flowers on`, async () => {
    const base = await build(design);
    assert.ok(base.targets.length >= 1);
    assert.equal(base.targets[0].name, "FlowerBed");
    // A ray straight down through the middle hits the bed
    const ray = new THREE.Raycaster(new THREE.Vector3(0, 10, 0), new THREE.Vector3(0, -1, 0));
    base.root.updateMatrixWorld(true);
    assert.ok(ray.intersectObjects(base.targets, true).length > 0);
  });

  test(`${design}: layers recolour and go back to the design's own colour`, async () => {
    const base = await build(design);
    const { outer, inner } = base.dressing;
    for (const layer of [outer, inner]) {
      if (!layer.meshes.length) continue;
      const own = colorOf(layer);
      layer.apply("#123456");
      assert.equal(colorOf(layer), "123456");
      layer.apply(null);
      assert.equal(colorOf(layer), own);
    }
    base.dressing.applyRibbon(null);
    base.dressing.applyRibbon("#ff0000");
  });

}

test("the hand-tied wedding bouquet has no paper", async () => {
  const base = await build("hand_tied");
  assert.equal(base.dressing.outer.meshes.length, 0);
  assert.equal(base.dressing.wrapMeshes.length, 0);
});

test("wrapper size grows the base about the floor centre", async () => {
  const small = new THREE.Box3().setFromObject((await build("basket")).root);
  const big = new THREE.Box3().setFromObject((await build("basket", { wrapperScale: 1.35 })).root);
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

test("the shop's models arrive without flowers or fillers", async () => {
  const filler = /SmallPlants|Leaves|Petals|Sepals/;
  for (const design of ["korean", "hand_tied", "basket"]) {
    const base = await build(design);
    const names = [];
    base.root.traverse((c) => c.isMesh && names.push(...[].concat(c.material).map((m) => m.name)));
    assert.ok(!names.some((n) => filler.test(n)), `${design}: ${names.join(", ")}`);
  }
});

test("the anniversary wrap keeps its two papers and ribbon, the wedding bouquet its ribbon", async () => {
  const anniversary = await build("korean");
  assert.ok(anniversary.dressing.outer.meshes.length > 0, "outer wrap");
  assert.ok(anniversary.dressing.inner.meshes.length > 0, "inner wrap");
  const wedding = await build("hand_tied");
  let ribbon = 0;
  wedding.root.traverse((c) => c.isMesh && /Ribbons/.test([].concat(c.material)[0]?.name) && ribbon++);
  assert.ok(ribbon > 0);
  // No ribbon for an occasion that has none
  wedding.dressing.applyRibbon(null);
  wedding.root.traverse((c) => c.isMesh && /Ribbons/.test([].concat(c.material)[0]?.name) && assert.equal(c.visible, false));
});
