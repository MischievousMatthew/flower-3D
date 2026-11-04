import test from "node:test";
import assert from "node:assert/strict";
import * as THREE from "three";
import { createBouquetBase, isModelDesign } from "../src/utils/bouquetDesigns/index.js";
import { designParts } from "../src/constants/bouquetPresets.js";

// A paper cone standing upright, open at the top: what a shop would upload
function coneGltf() {
  const scene = new THREE.Group();
  const geometry = new THREE.CylinderGeometry(1.2, 0.25, 3, 48, 12, true);
  const cone = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({ color: "#d9c3a5", name: "ShopPaper" }));
  cone.position.y = 1.5;
  scene.add(cone);
  return { scene };
}

test("a shop's 3D wrapper style is model_<id>", () => {
  assert.equal(isModelDesign("model_12"), true);
  assert.equal(isModelDesign("model_"), false);
  assert.equal(isModelDesign("classic"), false);
  assert.deepEqual(designParts("model_12"), { wrapper: true, ribbon: true });
  assert.deepEqual(designParts("hand_tied"), { wrapper: false, ribbon: true });
});

test("an uploaded wrapper becomes a bouquet base: sized, recolourable, with a ribbon and a flower bed", async () => {
  const base = await createBouquetBase("model_12", { loadGltf: async () => coneGltf(), modelUrl: "https://example.com/cone.glb" });
  assert.equal(base.design, "model_12");

  const box = new THREE.Box3().setFromObject(base.root);
  const size = box.getSize(new THREE.Vector3());
  assert.ok(Math.abs(Math.max(size.x, size.y, size.z) - 3.5) < 0.6, "sized like the other bases");
  assert.ok(Math.abs(box.min.y) < 0.05, "stands on the floor");

  // The whole model is the wrapper; there's no inner layer
  assert.equal(base.dressing.wrapMeshes.length, 1);
  assert.equal(base.dressing.inner.meshes.length, 0);
  base.dressing.outer.apply("#ff0000");
  assert.equal(base.dressing.wrapMeshes[0].material.color.getHexString(), "ff0000");

  // A ribbon is tied on, and hides when the occasion has none
  assert.ok(base.dressing.ribbon, "ribbon tied");
  base.dressing.applyRibbon(null);
  assert.equal(base.dressing.ribbon.visible, false);

  // Flowers go on the bed first
  assert.equal(base.targets[0].name, "FlowerBed");
  assert.equal(base.defaults.ribbon, "#caa6d9");
});

test("a 3D wrapper that can't load falls back to the classic cone", async () => {
  const calls = [];
  const loadGltf = async (url) => {
    calls.push(url);
    if (url.includes("cone.glb")) throw new Error("gone");
    return coneGltf();
  };
  const base = await createBouquetBase("model_12", { loadGltf, modelUrl: "https://example.com/cone.glb" });
  assert.equal(base.design, "classic");
  assert.ok(calls.length >= 2);

  const noUrl = await createBouquetBase("model_12", { loadGltf: async () => coneGltf() });
  assert.equal(noUrl.design, "classic");
});
