// Makes the empty bouquet bases from the shop's own models: removes the
// built-in flowers, leaves, baby's breath and sepals, keeping only the wrap,
// ribbon and stems, so customers fill the bouquet themselves.
//
//   public/Anniversary.glb → public/bouquets/anniversary.glb
//   public/Wedding.glb     → public/bouquets/wedding.glb
//
// The full model's frame (flowers included) is stored in the scene's extras
// as `bloomcraftFrame`, so the empty base is sized as the whole bouquet was.
// Run after changing a source model: node scripts/build-bouquet-bases.mjs
import { mkdirSync, statSync } from "node:fs";
import { NodeIO } from "@gltf-transform/core";
import { ALL_EXTENSIONS } from "@gltf-transform/extensions";
import { prune } from "@gltf-transform/functions";

const BASES = [
  { source: "Anniversary.glb", out: "bouquets/anniversary.glb", keep: /^(Parcel1|Parcel2|Ribbons1)$/ },
  { source: "Wedding.glb", out: "bouquets/wedding.glb", keep: /^(Ribbons1|Stems)$/ },
];

const publicDir = new URL("../public/", import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, "$1");
const io = new NodeIO().registerExtensions(ALL_EXTENSIONS);
mkdirSync(`${publicDir}bouquets`, { recursive: true });

// Like three.js Box3.setFromObject: each mesh's box corners through its world matrix
function frameOf(root) {
  const min = [Infinity, Infinity, Infinity];
  const max = [-Infinity, -Infinity, -Infinity];
  for (const node of root.listNodes()) {
    const mesh = node.getMesh();
    if (!mesh) continue;
    const m = node.getWorldMatrix();
    for (const prim of mesh.listPrimitives()) {
      const pos = prim.getAttribute("POSITION");
      const lo = pos.getMin([]), hi = pos.getMax([]);
      for (const x of [lo[0], hi[0]]) for (const y of [lo[1], hi[1]]) for (const z of [lo[2], hi[2]]) {
        const p = [m[0] * x + m[4] * y + m[8] * z + m[12], m[1] * x + m[5] * y + m[9] * z + m[13], m[2] * x + m[6] * y + m[10] * z + m[14]];
        for (let i = 0; i < 3; i++) {
          min[i] = Math.min(min[i], p[i]);
          max[i] = Math.max(max[i], p[i]);
        }
      }
    }
  }
  return { min, max };
}

for (const base of BASES) {
  const doc = await io.read(publicDir + base.source);
  const root = doc.getRoot();
  const frame = frameOf(root);

  const kept = new Set();
  let removed = 0;
  for (const node of root.listNodes()) {
    const mesh = node.getMesh();
    if (!mesh) continue;
    const names = mesh.listPrimitives().map((p) => p.getMaterial()?.getName() ?? "");
    if (names.some((n) => base.keep.test(n))) names.forEach((n) => kept.add(n));
    else {
      node.setMesh(null);
      removed++;
    }
  }
  await doc.transform(prune());
  const scene = root.listScenes()[0];
  scene.setExtras({ ...scene.getExtras(), bloomcraftFrame: frame });

  const out = publicDir + base.out;
  await io.write(out, doc);
  const before = statSync(publicDir + base.source).size / 1024 / 1024;
  const after = statSync(out).size / 1024 / 1024;
  console.log(`${base.source}: removed ${removed} parts, kept ${[...kept].join(", ")} → ${base.out} (${before.toFixed(1)} MB → ${after.toFixed(1)} MB)`);
}
