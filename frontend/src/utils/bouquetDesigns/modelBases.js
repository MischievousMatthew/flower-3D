// Bouquet bases made from the shop's own models (public/bouquets/*.glb, built
// by scripts/build-bouquet-bases.mjs without their flowers and fillers).
import * as THREE from "three";
import { createLayer } from "../bouquetDressing.js";
import { emptyLayer, finish, makeBed } from "./common.js";

const TARGET_SIZE = 3.5;

/**
 * Each base: which parts are the outer/inner wrap and the ribbon (by material
 * name), how big it should be, and where its (invisible) flower bed sits, as
 * fractions of the kept parts' bounding box.
 */
export const MODEL_BASES = {
  korean: {
    url: "/bouquets/anniversary.glb",
    outer: /^Parcel2$/,
    inner: /^Parcel1$/,
    ribbon: /^Ribbons1$/,
    bed: { y: 0.82, radius: 0.27 }, // just inside the opening, so flowers rise above the paper
    defaults: { ribbon: "#8e1b33" },
  },
  hand_tied: {
    url: "/bouquets/wedding.glb",
    ribbon: /^Ribbons1$/,
    bed: { y: 0.86, radius: 0.5, height: 0.22 }, // a shallow dome resting on the stem tops
    defaults: { ribbon: "#f4efe6" },
  },
  basket: {
    url: "/basket.glb",
    outer: /Wicker/,
    size: 3.1, // a basket reads best a little smaller than a full bouquet
    bed: { y: 0.44, radius: 0.4 },
    defaults: {},
  },
};

const meshesMatching = (root, pattern) => {
  const found = [];
  if (!pattern) return found;
  root.traverse((child) => {
    if (!child.isMesh) return;
    const names = [].concat(child.material).map((m) => m?.name || "");
    if (names.some((n) => pattern.test(n))) found.push(child);
  });
  return found;
};

const colourOf = (meshes) => {
  const material = meshes.length ? [].concat(meshes[0].material)[0] : null;
  return material?.color ? `#${material.color.getHexString()}` : null;
};

/** A ribbon that tints like paper and hides when the occasion has none */
function ribbonLayer(meshes) {
  if (!meshes.length) return () => {};
  const layer = createLayer(meshes);
  return (hex) => {
    layer.apply(hex);
    meshes.forEach((m) => (m.visible = !!hex));
  };
}

export async function buildFromModel(key, loadGltf) {
  const spec = MODEL_BASES[key];
  const gltf = await loadGltf(spec.url);
  const model = gltf.scene || gltf.scenes?.[0];
  if (!model) throw new Error(`The ${key} bouquet model is unavailable.`);

  // Size by the whole original bouquet (saved in the file), or by what's there
  const saved = model.userData?.bloomcraftFrame;
  const box = saved
    ? new THREE.Box3(new THREE.Vector3(...saved.min), new THREE.Vector3(...saved.max))
    : new THREE.Box3().setFromObject(model);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const scale = (spec.size ?? TARGET_SIZE) / Math.max(size.x, size.y, size.z, 1e-6);
  model.scale.multiplyScalar(scale);
  model.position.set(-center.x * scale, -box.min.y * scale, -center.z * scale);

  const root = new THREE.Group();
  root.name = `${key}Model`;
  root.add(model);
  root.updateMatrixWorld(true);

  const outerMeshes = meshesMatching(model, spec.outer);
  const innerMeshes = meshesMatching(model, spec.inner);
  const ribbonMeshes = meshesMatching(model, spec.ribbon);
  const outer = outerMeshes.length ? createLayer(outerMeshes) : emptyLayer();
  const inner = innerMeshes.length ? createLayer(innerMeshes) : emptyLayer();

  // Flower bed (invisible) inside the opening, from the kept parts' extent
  const parts = new THREE.Box3().setFromObject(model);
  const partSize = parts.getSize(new THREE.Vector3());
  const partCenter = parts.getCenter(new THREE.Vector3());
  const bed = makeBed({
    y: parts.min.y + partSize.y * spec.bed.y,
    radius: Math.min(partSize.x, partSize.z) * spec.bed.radius,
    height: spec.bed.height ?? 0.42,
    seed: 27,
  });
  bed.position.x = partCenter.x;
  bed.position.z = partCenter.z;
  root.add(bed);
  finish(root);

  const wrapMeshes = [...outerMeshes, ...innerMeshes];
  return {
    root,
    dressing: { outer, inner, wrapMeshes, applyRibbon: ribbonLayer(ribbonMeshes) },
    targets: [bed, ...wrapMeshes],
    // The model's own paper colours, for the customizer's "Original" swatch
    defaults: { outer: colourOf(outerMeshes), inner: colourOf(innerMeshes), ribbon: spec.defaults.ribbon ?? null },
  };
}
