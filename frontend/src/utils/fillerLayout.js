// Filler greenery laid out like the original bouquet. Every piece of greenery
// in public/Boquet.glb has a spot (src/constants/fillerSlots.json, made by
// scripts/build-filler-slots.mjs): 28 eucalyptus leaves, 22 small blooms,
// 6 lavender spikes and 3 sepals. The spots are shared out among the fillers
// the customer placed, each kind on its own spots first, so with every kind
// placed the bouquet carries the original's full greenery. A suggestion keeps
// the spots but swaps pieces between kinds of the same family and turns and
// sizes each piece a little.
//
// A filler saves its pieces ("sprigs"), each { i, a, r, k, p }:
//   i  the spot
//   a  turn of the spot about the bouquet's middle (radians)
//   r  turn of the piece about its own upright line (radians)
//   k  size (1 = as in the original)
//   p  how far out from the middle it stays (1 = as in the original; lower
//      when it had to come in to stay inside the paper)
// The filler object sits at the bed centre, scaled by the wrap's filler span.
import * as THREE from "three";
import SLOT_DATA from "../constants/fillerSlots.json";

const SLOTS = SLOT_DATA.slots.map((slot, index) => ({ ...slot, i: index }));
const UP = new THREE.Vector3(0, 1, 0);
// Families that can stand in for one another when a kind has no filler placed
const BORROW = { sepal: "small", spike: null, leaf: null, small: null };

/** The open front of the original wrap, in the opening frame (radians) */
export const ORIGINAL_FRONT = THREE.MathUtils.degToRad(SLOT_DATA.front);
/** On the classic cone, the filler span for a bed of radius 1 (it grows exactly with the wrapper) */
export const SPAN_PER_BED_RADIUS = SLOT_DATA.spanPerBedRadius;
// A piece may hang a little further than the original's did on that spot:
// a vendor's leaf standing in for a different leaf shape isn't an exact match
const SPILL_LEEWAY = 0.1;
/** How far the piece on a spot may hang past the paper (about as much as in the original), in the opening frame */
export const spillAt = (spot) => (SLOTS[spot]?.s ?? 0) + SPILL_LEEWAY;

/** The model's main mesh, its matrix within the model, and its own bounding box */
function mainMesh(template) {
  if (template.userData.fillerMesh) return template.userData.fillerMesh;
  template.updateMatrixWorld(true);
  let best = null;
  template.traverse((child) => {
    if (child.isMesh && (!best || child.geometry.attributes.position.count > best.geometry.attributes.position.count)) best = child;
  });
  if (!best) return null;
  if (!best.geometry.boundingBox) best.geometry.computeBoundingBox();
  const mesh = {
    inModel: template.matrixWorld.clone().invert().multiply(best.matrixWorld),
    box: best.geometry.boundingBox,
    material: [].concat(best.material).map((m) => m?.name || "").join("+"),
    vertices: best.geometry.attributes.position.count,
  };
  template.userData.fillerMesh = mesh;
  return mesh;
}

/** Which original pieces a filler model stands in for: { own: spot kind or null, family } */
export function fillerKind(template) {
  const mesh = mainMesh(template);
  if (!mesh) return { own: null, family: "leaf" };
  let own = SLOTS.find((slot) => slot.k === mesh.material);
  // A recoloured copy keeps the shape: match it by size instead of name
  own ??= SLOTS.find((slot) => Math.abs(slot.v - mesh.vertices) / slot.v < 0.05);
  if (own) return { own: own.k, family: own.f };
  return { own: null, family: /leaf|leaves|eucal|green/i.test(mesh.material) ? "leaf" : "small" };
}

/** A sprig's matrix in the opening frame */
function spotMatrix(sprig) {
  const slot = SLOTS[sprig.i];
  if (!slot) return new THREE.Matrix4();
  const m = new THREE.Matrix4().fromArray(slot.m);
  const at = new THREE.Vector3().setFromMatrixPosition(m);
  const k = sprig.k ?? 1;
  // Resize and turn the piece about where it stands, then bring it in, then turn the spot
  const inPlace = new THREE.Matrix4()
    .makeTranslation(at.x, at.y, at.z)
    .multiply(new THREE.Matrix4().makeRotationAxis(UP, sprig.r ?? 0))
    .multiply(new THREE.Matrix4().makeScale(k, k, k))
    .multiply(new THREE.Matrix4().makeTranslation(-at.x, -at.y, -at.z));
  const p = sprig.p ?? 1;
  const inward = new THREE.Matrix4().makeTranslation(at.x * (p - 1), 0, at.z * (p - 1));
  return new THREE.Matrix4().makeRotationAxis(UP, sprig.a ?? 0).multiply(inward).multiply(inPlace).multiply(m);
}

/**
 * The matrix that puts the vendor's model on a sprig's spot: the original
 * piece's box mapped onto the model's main mesh box (an exact fit when the
 * vendor uploaded that very piece), then the spot's matrix.
 */
function sprigMatrix(sprig, mesh) {
  const slot = SLOTS[sprig.i];
  const [x0, y0, z0, x1, y1, z1] = slot?.b ?? [-1, -1, -1, 1, 1, 1];
  const targetSize = Math.max(x1 - x0, y1 - y0, z1 - z0);
  const size = mesh.box.getSize(new THREE.Vector3());
  const k = targetSize / Math.max(size.x, size.y, size.z, 1e-6);
  const center = mesh.box.getCenter(new THREE.Vector3());
  const fit = new THREE.Matrix4()
    .makeTranslation((x0 + x1) / 2, (y0 + y1) / 2, (z0 + z1) / 2)
    .multiply(new THREE.Matrix4().makeScale(k, k, k))
    .multiply(new THREE.Matrix4().makeTranslation(-center.x, -center.y, -center.z));
  return spotMatrix(sprig).multiply(fit).multiply(mesh.inModel.clone().invert());
}

/** A filler's pieces as one group, in the opening frame. `makeCopy` returns a fresh copy of the model. */
export function buildFillerSprigs(template, sprigs, makeCopy) {
  const mesh = mainMesh(template);
  const group = new THREE.Group();
  if (!mesh) return group;
  for (const sprig of sprigs) {
    if (!SLOTS[sprig.i]) continue;
    const copy = makeCopy();
    sprigMatrix(sprig, mesh).decompose(copy.position, copy.quaternion, copy.scale);
    copy.userData.sprig = sprig;
    group.add(copy);
  }
  return group;
}

/** Puts a piece's object back on its (changed) spot */
export function placeSprig(copy, template) {
  const mesh = mainMesh(template);
  if (mesh) sprigMatrix(copy.userData.sprig, mesh).decompose(copy.position, copy.quaternion, copy.scale);
}

// `spin` and `size` are the most a piece turns about its own stem (share of a
// full turn) and grows or shrinks. Every look but the first moves each piece
// to a new side of the bouquet: a turn about the middle keeps the piece's
// height, reach and outward lean, so it still grows out of the wrap the way
// it did in the original. Taller pieces go toward the back.
export const FILLER_STYLES = [
  // As the original, each piece turned a little (used when fillers are added)
  { key: "original", label: "Like the original", spin: 0.05, size: 0.05, first: true },
  { key: "collar", label: "Even collar", spin: 0.08, size: 0.08 },
  { key: "fan", label: "Fan at the back", spin: 0.08, size: 0.08 },
  { key: "sweep", label: "Side sweep", spin: 0.08, size: 0.08 },
  { key: "clusters", label: "Grouped by kind", spin: 0.08, size: 0.08 },
  { key: "mirror", label: "Mirrored", spin: 0.08, size: 0.06 },
];

const TAU = Math.PI * 2;
const angleOf = (slot) => Math.atan2(slot.m[14], slot.m[12]);
const heightOf = (slot) => slot.m[13];

/**
 * Where each piece goes round the bouquet, as an angle from the open front
 * (0 = front, π = back), for the chosen look.
 */
function sidesFor(look, pieces, rand) {
  const jitter = (amount) => (rand() - 0.5) * 2 * amount;
  const fromFront = (slot) => angleOf(slot) - ORIGINAL_FRONT;
  if (look === "original") return pieces.map(({ slot }) => fromFront(slot) + jitter(0.04));
  if (look === "mirror") return pieces.map(({ slot }) => -fromFront(slot) + jitter(0.15));

  const n = pieces.length;
  // Even places across an arc centred on the back (or one side, for a sweep)
  const arc = { collar: TAU * 0.95, fan: 3.8, sweep: 3.3, clusters: 4.8 }[look];
  const centre = Math.PI + (look === "sweep" ? (rand() < 0.5 ? -1 : 1) * 0.9 : 0);
  const places = Array.from({ length: n }, (_, j) => centre + ((j + 0.5) / n - 0.5) * arc + jitter((arc / n) * 0.35));

  if (look === "clusters") {
    // Each kind keeps to its own stretch, the kinds in a new order each time
    const order = [...new Set(pieces.map(({ slot }) => slot.k))].sort(() => rand() - 0.5);
    const ranked = pieces.map((piece, index) => ({ index, key: order.indexOf(piece.slot.k) + rand() * 0.5 })).sort((a, b) => a.key - b.key);
    const sides = new Array(n);
    ranked.forEach(({ index }, j) => (sides[index] = places[j]));
    return sides;
  }
  // Tallest pieces to the places nearest the back, so the front stays open
  const byBack = places.map((side, j) => ({ side, j, back: -Math.cos(side) })).sort((a, b) => b.back - a.back);
  const byHeight = pieces.map((piece, index) => ({ index, h: heightOf(piece.slot) + jitter(0.08) })).sort((a, b) => b.h - a.h);
  const sides = new Array(n);
  byHeight.forEach(({ index }, j) => (sides[index] = byBack[j].side));
  return sides;
}

/**
 * Shares the original's spots among fillers and returns each one's sprigs.
 *   entries  [{ kind }] from fillerKind(), in the fillers' order
 *   align    turn that puts the original's open front at this wrap's (radians)
 *   keep     spots locked fillers hold
 */
export function planFillers(entries, { align = 0, style = "original", keep = [], rand = Math.random } = {}) {
  const look = FILLER_STYLES.find((s) => s.key === style) || FILLER_STYLES[0];
  const held = new Set(keep);
  const pieces = []; // { index: which filler, slot }

  for (const family of ["leaf", "small", "spike", "sepal"]) {
    // Fillers that take this family's spots (or borrow them when none are placed)
    let takers = entries.map((entry, index) => ({ entry, index })).filter(({ entry }) => entry.kind.family === family);
    if (!takers.length && BORROW[family]) takers = entries.map((entry, index) => ({ entry, index })).filter(({ entry }) => entry.kind.family === BORROW[family]);
    if (!takers.length) continue;
    // Each spot goes to a filler of its own kind when there is one (shared out
    // in turn among several of that kind), otherwise to whichever taker has the fewest
    const counts = new Map(takers.map(({ index }) => [index, 0]));
    for (const slot of SLOTS.filter((s) => s.f === family && !held.has(s.i)).sort(() => rand() - 0.5)) {
      const ownKind = takers.filter(({ entry }) => entry.kind.own === slot.k);
      const pool = ownKind.length ? ownKind : takers;
      const pick = pool.reduce((best, taker) => (counts.get(taker.index) < counts.get(best.index) ? taker : best), pool[Math.floor(rand() * pool.length)]);
      counts.set(pick.index, counts.get(pick.index) + 1);
      pieces.push({ index: pick.index, slot });
    }
  }

  const sides = sidesFor(look.key, pieces, rand);
  const result = entries.map(() => []);
  pieces.forEach(({ index, slot }, j) => {
    // A turn about the middle moves the piece from its own side to the new one
    const target = ORIGINAL_FRONT + sides[j];
    result[index].push({
      i: slot.i,
      a: round(Math.atan2(Math.sin(angleOf(slot) - target), Math.cos(angleOf(slot) - target)) + align),
      r: round((rand() - 0.5) * 2 * Math.PI * look.spin),
      k: round(1 + (rand() - 0.5) * 2 * look.size),
      p: 1,
    });
  });
  return result;
}

const round = (v) => Number(v.toFixed(4));
