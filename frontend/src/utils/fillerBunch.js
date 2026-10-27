// One filler shows as a bunch of sprigs fanned out from a shared base, like the
// greenery in the original bouquet model, instead of a single sprig. The
// customizer and the 3D viewer (cart, orders) both build it here, seeded by the
// filler's scene id, so a saved bouquet shows the same bunch everywhere.
import * as THREE from "three";
import { seeded } from "./bouquetDesigns/common.js";

export const BUNCH_SPRIGS = 5;

function hash(text) {
  let h = 2166136261;
  for (let i = 0; i < text.length; i++) h = Math.imul(h ^ text.charCodeAt(i), 16777619);
  return h >>> 0;
}

/**
 * @param makeSprig  returns a new copy of the filler model, fitted to 0.9 across
 *                   and centred on the origin
 * @param seedKey    the filler's scene id
 * @returns a group centred on the origin, like a single fitted model
 */
export function buildFillerBunch(makeSprig, seedKey) {
  const rand = seeded(hash(String(seedKey || "filler")));
  const bunch = new THREE.Group();
  const spin = rand() * Math.PI * 2;
  for (let i = 0; i < BUNCH_SPRIGS; i++) {
    // The first sprig stands in the middle; the rest fan out around it
    const middle = i === 0;
    const angle = spin + ((i - 1) / (BUNCH_SPRIGS - 1)) * Math.PI * 2 + (rand() - 0.5) * 0.6;
    const dx = Math.cos(angle);
    const dz = Math.sin(angle);
    const lean = middle ? rand() * 0.2 : 0.55 + rand() * 0.3; // radians outward

    // Each sprig turns about its own base, low in the bunch, so they all grow from one point
    const pivot = new THREE.Group();
    pivot.position.set(middle ? 0 : dx * 0.15, -0.3, middle ? 0 : dz * 0.15);
    pivot.quaternion
      .setFromAxisAngle(new THREE.Vector3(dz, 0, -dx), lean)
      .multiply(new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), rand() * Math.PI * 2));
    const size = middle ? 1 : 0.75 + rand() * 0.2;
    const holder = new THREE.Group();
    holder.scale.setScalar(size);
    holder.position.y = 0.3 * size;
    holder.add(makeSprig());
    pivot.add(holder);
    bunch.add(pivot);
  }

  // Centre it like a single fitted model, so positions mean the same thing
  bunch.updateMatrixWorld(true);
  const center = new THREE.Box3().setFromObject(bunch).getCenter(new THREE.Vector3());
  bunch.children.forEach((child) => child.position.sub(center));
  return bunch;
}
