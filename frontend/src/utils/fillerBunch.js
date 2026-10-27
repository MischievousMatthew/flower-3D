// One filler shows as a spray of sprigs fanned out from a shared base, like the
// greenery in a hand-tied bouquet, instead of a single sprig. The customizer
// and the 3D viewer (cart, orders) both build it here, seeded by the filler's
// scene id, so a saved bouquet shows the same spray everywhere.
//
// The spray grows up its local +Y and fans out sideways along local X, so it
// reads as an open fan when its face (local +Z) looks out of the bouquet.
import * as THREE from "three";
import { seeded } from "./bouquetDesigns/common.js";

export const BUNCH_SPRIGS = 4;
const FAN = 0.5; // radians from the middle to the outermost sprig
const BASE = -0.35; // where the sprigs meet, below the spray's centre

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
  for (let i = 0; i < BUNCH_SPRIGS; i++) {
    // Spread across the fan, a little uneven so no two sprays match
    const across = BUNCH_SPRIGS === 1 ? 0 : (i / (BUNCH_SPRIGS - 1)) * 2 - 1;
    const middle = 1 - Math.abs(across); // 1 in the middle, 0 at the edges
    const pivot = new THREE.Group();
    pivot.position.set(across * 0.06, BASE, 0);
    pivot.rotation.set((rand() - 0.5) * 0.35, rand() * Math.PI * 2, 0, "ZXY");
    pivot.rotation.z = -across * FAN + (rand() - 0.5) * 0.2;

    // Middle sprigs stand taller; outer ones are a little smaller
    const size = 0.7 + middle * 0.25 + rand() * 0.1;
    const holder = new THREE.Group();
    holder.scale.setScalar(size);
    holder.position.y = (0.35 + middle * 0.3) * size;
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
