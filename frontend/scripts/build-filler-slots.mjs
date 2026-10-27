// Makes src/constants/fillerSlots.json: where every piece of greenery sits in
// the original bouquet (public/Boquet.glb). Filler suggestions start from
// these spots, so a suggested bouquet looks like the original.
// Run after changing Boquet.glb: node scripts/build-filler-slots.mjs
//
// Each spot is the piece's matrix in the "opening frame": the classic
// bouquet's flower-bed centre moved to the origin and its filler span scaled
// to 1, so it can be fitted to any wrap. `b` is the piece's own bounding box,
// used to line a vendor's filler model up with it. `s` is how far the piece
// already hangs past the paper in the original (in the same units), so a
// moved piece may spill that much and no more. Pieces the model has twice on
// the very same spot are kept once.
import { NodeIO } from "@gltf-transform/core";
import { writeFileSync } from "node:fs";
import { classicTransform } from "../src/utils/bouquetDesigns/classic.js";

// The classic_full base at wrapper size 1, measured in the customizer
// (bedFrame().center and fillerSpan()). Re-measure if the bed or wrap changes.
const OPENING = { center: [0.11814, 2.62647, 0.11588], span: 1.06664, bedRadius: 0.951633 };
// The open front of the original wrap (degrees, atan2(z, x)): where its paper is lowest
const FRONT_DEG = 55;

const FAMILIES = [
  [/^Leaves/, "leaf"],
  [/^SmallPlants/, "small"],
  [/^Sepals/, "sepal"],
  [/^Material$/, "spike"], // the lavender heads (their bare stems come with the vendor's model)
];

const path = (url) => url.pathname.replace(/^\/([A-Za-z]:)/, "$1");
const io = new NodeIO();
const doc = await io.read(path(new URL("../public/Boquet.glb", import.meta.url)));

function multiply(a, b) {
  const out = new Array(16).fill(0);
  for (let c = 0; c < 4; c++) for (let r = 0; r < 4; r++) for (let k = 0; k < 4; k++) out[c * 4 + r] += a[k * 4 + r] * b[c * 4 + k];
  return out;
}
const { scale, position } = classicTransform();
const model = [scale, 0, 0, 0, 0, scale, 0, 0, 0, 0, scale, 0, ...position, 1];
const k = 1 / OPENING.span;
const opening = [k, 0, 0, 0, 0, k, 0, 0, 0, 0, k, 0, ...OPENING.center.map((v) => -v * k), 1];
const round = (v, places) => Number(v.toFixed(places));

// How far each piece hangs past the paper in the original, in model order,
// measured in the customizer with wallOvershoot() on the classic_full base
const SPILL = [0.198, 0, 0.098, 0.022, 0.083, 0.173, 0.037, 0.105, 0.155, 0.128, 0.155, 0, 0.036, 0, 0.025, 0.313, 0.322, 0.313, 0.322, 0.173, 0.098, 0.081, 0.02, 0.098, 0.02, 0.031, 0, 0.023, 0, 0, 0, 0, 0, 0.045, 0, 0, 0, 0, 0, 0, 0, 0, 0.008, 0, 0.047, 0, 0, 0, 0, 0.017, 0.11, 0, 0.019, 0, 0, 0, 0.002, 0, 0];

const slots = [];
let measured = 0;
for (const node of doc.getRoot().listNodes()) {
  const mesh = node.getMesh();
  if (!mesh) continue;
  for (const prim of mesh.listPrimitives()) {
    const material = prim.getMaterial()?.getName() ?? "";
    const family = FAMILIES.find(([pattern]) => pattern.test(material))?.[1];
    const pos = prim.getAttribute("POSITION");
    if (!family || pos.getCount() < 100) continue; // tiny pieces are stem caps
    const m = multiply(opening, multiply(model, node.getWorldMatrix()));
    const spot = {
      k: material,
      v: pos.getCount(),
      f: family,
      m: m.map((v) => round(v, 6)),
      b: [...pos.getMin([]), ...pos.getMax([])].map((v) => round(v, 4)),
      s: SPILL[measured++] ?? 0,
    };
    const twin = slots.find((other) => other.k === spot.k && other.m.every((v, i) => Math.abs(v - spot.m[i]) < 1e-5));
    if (twin) twin.s = Math.max(twin.s, spot.s);
    else slots.push(spot);
  }
}

const out = path(new URL("../src/constants/fillerSlots.json", import.meta.url));
// On the classic cone the span is taken from the bed radius, which grows exactly with the wrapper
writeFileSync(out, JSON.stringify({ front: FRONT_DEG, spanPerBedRadius: round(OPENING.span / OPENING.bedRadius, 6), slots }));
if (measured !== SPILL.length) throw new Error(`Expected ${SPILL.length} pieces, found ${measured}: re-measure SPILL`);
const count = slots.reduce((all, slot) => ({ ...all, [slot.f]: (all[slot.f] || 0) + 1 }), {});
console.log(`${slots.length} filler spots → ${out}`, count);
