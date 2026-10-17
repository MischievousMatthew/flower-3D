// The classic cone: the original bouquet model, wrapper only (no built-in
// eucalyptus, baby's breath, red blooms or stems) plus a flower bed.
import * as THREE from "three";
import { dressBouquet } from "../bouquetDressing.js";
import { finish, makeBed } from "./common.js";

export const CLASSIC_URL = "/Boquet.classic.glb";
export const FULL_URL = "/Boquet.glb";

// The FULL original model's bounds (fillers included), printed by
// scripts/build-classic-bouquet.mjs. Saved bouquets placed their flowers
// relative to this frame, so the lighter file is sized and placed by it.
const FULL_FRAME = {
  min: [-63.43073948143262, 14.332248836745357, 0.5035515858193862],
  max: [-47.23040882668326, 33.77212653079686, 17.399553440907425],
};
const TARGET_SIZE = 3.5;

const WRAPPER = /^(paper04_05_06|Pink)$/;

/** Scale and offset that put the full model on the floor, 3.5 units at its largest */
export function classicTransform(frame = FULL_FRAME) {
  const size = frame.max.map((v, i) => v - frame.min[i]);
  const scale = TARGET_SIZE / Math.max(...size, 1);
  const center = frame.max.map((v, i) => (v + frame.min[i]) / 2);
  return { scale, position: [-center[0] * scale, -frame.min[1] * scale, -center[2] * scale] };
}

/**
 * @param {{ withFillers?: boolean }} options withFillers keeps the original
 *   eucalyptus, baby's breath and blooms, for bouquets saved before designs
 *   existed: their flowers were placed on those fillers and would float without.
 */
export async function buildClassic(loadGltf, { withFillers = false } = {}) {
  let gltf;
  let lightweight = !withFillers;
  if (withFillers) {
    gltf = await loadGltf(FULL_URL);
  } else {
    try {
      gltf = await loadGltf(CLASSIC_URL);
    } catch (_) {
      // The lighter file is missing: use the full one and hide its fillers
      gltf = await loadGltf(FULL_URL);
      lightweight = false;
    }
  }
  const model = gltf.scene || gltf.scenes?.[0];
  if (!model) throw new Error("The classic bouquet model is unavailable.");

  if (!lightweight && !withFillers) {
    model.traverse((child) => {
      if (!child.isMesh) return;
      const names = [].concat(child.material).map((m) => m?.name || "").join(" ");
      if (!WRAPPER.test(names.trim())) child.visible = false;
    });
  }

  const { scale, position } = classicTransform();
  model.scale.setScalar(scale);
  model.position.set(...position);

  const root = new THREE.Group();
  root.name = withFillers ? "ClassicConeOriginal" : "ClassicCone";
  root.add(model);
  const dressing = dressBouquet(model);

  // Flower bed just below the paper's opening
  root.updateMatrixWorld(true);
  const box = new THREE.Box3();
  dressing.wrapMeshes.forEach((mesh) => box.expandByObject(mesh));
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const bed = makeBed({ y: box.max.y - 0.75, radius: Math.min(size.x, size.z) * 0.36, height: 0.45, seed: 21 });
  bed.position.x = center.x;
  bed.position.z = center.z;
  root.add(bed);
  finish(root);

  // With fillers, flowers can also be placed on the greenery, as before
  const targets = [bed, ...dressing.wrapMeshes];
  if (withFillers) model.traverse((child) => child.isMesh && !targets.includes(child) && targets.push(child));

  return {
    root,
    dressing,
    targets,
    defaults: { outer: null, inner: null, ribbon: "#caa6d9" },
  };
}
