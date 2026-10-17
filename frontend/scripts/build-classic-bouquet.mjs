// Makes public/Boquet.classic.glb: the classic bouquet with only its wrapper
// (kraft paper + pink inner sheet). The built-in eucalyptus, baby's breath,
// sepals, red blooms and bare stems are removed, which is most of the file.
// Run after changing Boquet.glb: node scripts/build-classic-bouquet.mjs
import { NodeIO } from "@gltf-transform/core";
import { getBounds, prune } from "@gltf-transform/functions";

const KEEP = /^(paper04_05_06|Pink)$/; // wrapper materials

const io = new NodeIO();
const source = new URL("../public/Boquet.glb", import.meta.url);
const target = new URL("../public/Boquet.classic.glb", import.meta.url);
const doc = await io.read(source.pathname.replace(/^\/([A-Za-z]:)/, "$1"));
const root = doc.getRoot();

// The FULL model's frame, measured like three.js Box3.setFromObject (each
// mesh's bounding-box corners through its world matrix). Saved bouquets
// placed flowers relative to this frame, so the lighter copy must keep it.
function multiply(m, [x, y, z]) {
  return [m[0] * x + m[4] * y + m[8] * z + m[12], m[1] * x + m[5] * y + m[9] * z + m[13], m[2] * x + m[6] * y + m[10] * z + m[14]];
}
const frameMin = [Infinity, Infinity, Infinity];
const frameMax = [-Infinity, -Infinity, -Infinity];
for (const node of root.listNodes()) {
  const mesh = node.getMesh();
  if (!mesh) continue;
  const world = node.getWorldMatrix();
  for (const prim of mesh.listPrimitives()) {
    const pos = prim.getAttribute("POSITION");
    const lo = pos.getMin([]), hi = pos.getMax([]);
    for (const x of [lo[0], hi[0]]) for (const y of [lo[1], hi[1]]) for (const z of [lo[2], hi[2]]) {
      const p = multiply(world, [x, y, z]);
      for (let i = 0; i < 3; i++) {
        frameMin[i] = Math.min(frameMin[i], p[i]);
        frameMax[i] = Math.max(frameMax[i], p[i]);
      }
    }
  }
}

let removed = 0;
for (const node of root.listNodes()) {
  const mesh = node.getMesh();
  if (!mesh) continue;
  const keep = mesh.listPrimitives().some((p) => KEEP.test(p.getMaterial()?.getName() ?? ""));
  if (!keep) {
    node.setMesh(null);
    removed++;
  }
}
await doc.transform(prune());

const out = target.pathname.replace(/^\/([A-Za-z]:)/, "$1");
await io.write(out, doc);

const scene = root.listScenes()[0];
const { min, max } = getBounds(scene);
const size = max.map((v, i) => v - min[i]);
const { statSync } = await import("node:fs");
console.log(`Removed ${removed} filler meshes → ${out} (${(statSync(out).size / 1024).toFixed(0)} KB)`);
console.log(`Wrapper bounds: min ${min.map((v) => v.toFixed(3))} max ${max.map((v) => v.toFixed(3))} size ${size.map((v) => v.toFixed(3))}`);
console.log(`Full model frame (paste into utils/bouquetDesigns/classic.js): min [${frameMin.join(", ")}] max [${frameMax.join(", ")}]`);
