// "Matched build": when the free AI can't take a photo, build a 3D flower or
// bouquet from BloomCraft's own sculpted heads in the photo's colors.
// Everything is generated in code, so the GLB stays small (Cloudinary's free
// tier caps files at 10 MB) and needs no network.
import * as THREE from "three";
import { GLTFExporter } from "three/examples/jsm/exporters/GLTFExporter.js";
import { mergeVertices } from "three/examples/jsm/utils/BufferGeometryUtils.js";
import { createHeadGeometry, HEAD_TYPES } from "../../../composables/landingFlowers.js";

export { HEAD_TYPES };
export const HEAD_LABELS = { rose: "Rose", tulip: "Tulip", peony: "Peony", daisy: "Daisy" };
export const HEAD_PLURALS = { rose: "Roses", tulip: "Tulips", peony: "Peonies", daisy: "Daisies" };
export const PAPER_PRESETS = ["#d3b18f", "#f3e9dc", "#d7d2c8", "#e2c7cf", "#c8b6a6"];
export const MAX_BOUQUET_HEADS = 24;

const SHADE = 0.8;
const luminance = (c) => 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b;

/**
 * A head's shading as gray vertex colors: its light-to-dark pattern, averaging
 * SHADE so highlights stay inside glTF's 0–1 range. The petal color comes from
 * the material (glTF multiplies it with the vertex colors), so one geometry per
 * head type serves every color. A daisy's yellow centre is split off into its
 * own part that keeps its colors.
 * @returns {{ petals: THREE.BufferGeometry, disc: THREE.BufferGeometry | null }}
 */
function shadeHead(head) {
  let g = createHeadGeometry(head);
  g.deleteAttribute("uv"); // not textured; keeps the file small
  if (!g.index) g = mergeVertices(g);
  const c = new THREE.Color();
  const hsl = { h: 0, s: 0, l: 0 };
  const colors = g.attributes.color;
  const discVertex = new Uint8Array(colors.count);
  if (head === "daisy") {
    for (let i = 0; i < colors.count; i++) {
      c.fromBufferAttribute(colors, i).getHSL(hsl);
      discVertex[i] = hsl.s > 0.5 ? 1 : 0;
    }
  }

  // Split triangles into petals and disc
  const index = g.index.array;
  const petalIdx = [], discIdx = [];
  for (let t = 0; t < index.length; t += 3) {
    const tri = [index[t], index[t + 1], index[t + 2]];
    (tri.every((v) => discVertex[v]) ? discIdx : petalIdx).push(...tri);
  }
  const part = (idx) => {
    const p = g.clone();
    p.setIndex(idx);
    return mergeVertices(p.toNonIndexed());
  };
  const petals = discIdx.length ? part(petalIdx) : g;
  const disc = discIdx.length ? part(discIdx) : null;

  const pc = petals.attributes.color;
  let sum = 0;
  for (let i = 0; i < pc.count; i++) sum += luminance(c.fromBufferAttribute(pc, i));
  const avg = sum / Math.max(pc.count, 1) || 1;
  for (let i = 0; i < pc.count; i++) {
    const k = Math.min(THREE.MathUtils.clamp(luminance(c.fromBufferAttribute(pc, i)) / avg, 0.55, 1.45) * SHADE, 1);
    pc.setXYZ(i, k, k, k);
  }
  pc.needsUpdate = true;
  petals.normalizeNormals();
  disc?.normalizeNormals();
  return { petals, disc };
}

// Shading averages SHADE, so the material is brightened to match the chosen
// color (capped at 1, so very light colors come out slightly softer)
const petalMaterial = (hex) => {
  const c = new THREE.Color(hex);
  c.setRGB(Math.min(c.r / SHADE, 1), Math.min(c.g / SHADE, 1), Math.min(c.b / SHADE, 1));
  return material({ vertexColors: true, color: c, name: "Petals" });
};

function leafGeometry(length, width, hex) {
  const g = new THREE.PlaneGeometry(1, 1, 6, 10);
  const pos = g.attributes.position;
  const colors = new Float32Array(pos.count * 3);
  const base = new THREE.Color(hex).multiplyScalar(0.75);
  const tip = new THREE.Color(hex);
  const c = new THREE.Color();
  for (let i = 0; i < pos.count; i++) {
    const u = pos.getX(i) * 2;
    const v = pos.getY(i) + 0.5;
    const w = width * Math.sin(Math.PI * Math.min(v * 1.05, 1)) ** 0.8;
    pos.setXYZ(i, (u * w) / 2, v * length, 0.25 * w * u * u + 0.12 * length * v * v);
    c.lerpColors(base, tip, v);
    colors.set([c.r, c.g, c.b], i * 3);
  }
  g.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  g.deleteAttribute("uv");
  g.computeVertexNormals();
  return g;
}

function stemGeometry(points, radius) {
  const curve = new THREE.CatmullRomCurve3(points);
  const g = new THREE.TubeGeometry(curve, 24, radius, 6, false);
  g.deleteAttribute("uv");
  return g;
}

// Open paper cone, wide at the top, with a softly scalloped rim
function paperGeometry(height, topRadius, bottomRadius) {
  const profile = [];
  for (let i = 0; i <= 12; i++) {
    const t = i / 12;
    profile.push(new THREE.Vector2(bottomRadius + (topRadius - bottomRadius) * t ** 1.3, height * t));
  }
  const g = new THREE.LatheGeometry(profile, 48);
  const pos = g.attributes.position;
  for (let i = 0; i < pos.count; i++) {
    const y = pos.getY(i);
    if (y > height * 0.8) {
      const a = Math.atan2(pos.getZ(i), pos.getX(i));
      pos.setY(i, y + Math.sin(a * 7) * height * 0.06 * ((y / height - 0.8) / 0.2));
    }
  }
  g.deleteAttribute("uv");
  g.computeVertexNormals();
  return g;
}

const material = (options) => new THREE.MeshStandardMaterial({ roughness: 0.65, metalness: 0, side: THREE.DoubleSide, ...options });

/** One head in one color, sharing geometry and materials through `cache` */
function headMesh(head, color, cache) {
  if (!cache.geometries.has(head)) cache.geometries.set(head, shadeHead(head));
  if (!cache.materials.has(color)) cache.materials.set(color, petalMaterial(color));
  const { petals, disc } = cache.geometries.get(head);
  const group = new THREE.Group();
  group.name = head;
  group.add(new THREE.Mesh(petals, cache.materials.get(color)));
  if (disc) {
    if (!cache.materials.has("disc")) cache.materials.set("disc", material({ vertexColors: true, name: "FlowerCentre" }));
    group.add(new THREE.Mesh(disc, cache.materials.get("disc")));
  }
  return group;
}

const newCache = () => ({ geometries: new Map(), materials: new Map() });

function buildSingle({ head = "rose", colors, leaf }) {
  const root = new THREE.Group();
  root.name = "MatchedFlower";
  const flower = headMesh(head, colors[0], newCache());
  flower.scale.setScalar(0.34);
  flower.position.set(0.015, 0.5, 0);
  flower.rotation.z = -0.08;
  root.add(flower);

  const stemMat = material({ color: new THREE.Color(leaf).multiplyScalar(0.8), name: "Stem" });
  root.add(new THREE.Mesh(stemGeometry([new THREE.Vector3(0, 0, 0), new THREE.Vector3(-0.015, 0.26, 0.01), new THREE.Vector3(0.015, 0.52, 0)], 0.012), stemMat));

  const leafMat = material({ vertexColors: true, name: "Leaves" });
  [
    { y: 0.18, rz: 0.9, ry: 0.3 },
    { y: 0.3, rz: -0.85, ry: 2.6 },
  ].forEach(({ y, rz, ry }) => {
    const l = new THREE.Mesh(leafGeometry(0.2, 0.08, leaf), leafMat);
    l.position.set(0, y, 0);
    l.rotation.set(0, ry, rz);
    root.add(l);
  });
  return root;
}

// Dome slots: one centre head, then rings that lean outward
function domeSlots(count) {
  const slots = [{ r: 0, a: 0, ring: 0 }];
  const ringSizes = [5, 8, 11];
  let ring = 1;
  while (slots.length < count && ring <= ringSizes.length) {
    const size = Math.min(ringSizes[ring - 1], count - slots.length);
    for (let i = 0; i < size; i++) slots.push({ r: ring / ringSizes.length, a: (i / size) * Math.PI * 2 + ring * 0.4, ring });
    ring++;
  }
  return slots;
}

function buildBouquet({ counts = { rose: 7 }, colors, leaf, paper = PAPER_PRESETS[0] }) {
  const root = new THREE.Group();
  root.name = "MatchedBouquet";
  const order = [];
  for (const head of HEAD_TYPES) for (let i = 0; i < (counts[head] || 0); i++) order.push(head);
  const heads = order.slice(0, MAX_BOUQUET_HEADS);
  if (!heads.length) heads.push("rose");
  // Spread varieties around the dome (round-robin by type) instead of clumping them
  const queues = HEAD_TYPES.map((t) => heads.filter((h) => h === t)).filter((q) => q.length);
  const spread = [];
  while (spread.length < heads.length) for (const q of queues) if (q.length) spread.push(q.pop());
  const slots = domeSlots(heads.length);

  const cache = newCache(); // one geometry per head type, one material per color
  const domeRadius = 0.18 + 0.11 * Math.ceil(Math.sqrt(heads.length));
  const headSize = 0.25;
  const top = 0.9; // centre head height; the paper rim sits around 0.75

  slots.forEach((slot, i) => {
    const head = spread[i];
    const color = colors[i % colors.length];
    const mesh = headMesh(head, color, cache);
    const dir = new THREE.Vector3(Math.cos(slot.a), 0, Math.sin(slot.a));
    mesh.position.set(dir.x * slot.r * domeRadius, top + (1 - slot.r ** 2) * 0.1 - slot.ring * 0.02, dir.z * slot.r * domeRadius);
    const axis = new THREE.Vector3(0, 1, 0).cross(dir).normalize();
    if (slot.r > 0) mesh.quaternion.setFromAxisAngle(axis, slot.r * 0.85); // lean outward
    mesh.rotateY(i * 2.1);
    mesh.scale.setScalar(headSize * (head === "peony" ? 1.08 : 1));
    root.add(mesh);
  });

  // Stems gather into a bundle under the wrap
  const stemMat = material({ color: new THREE.Color(leaf).multiplyScalar(0.8), name: "Stems" });
  const stemCount = Math.min(heads.length, 10);
  for (let i = 0; i < stemCount; i++) {
    const a = (i / stemCount) * Math.PI * 2;
    root.add(new THREE.Mesh(stemGeometry([
      new THREE.Vector3(Math.cos(a) * 0.025, -0.05, Math.sin(a) * 0.025),
      new THREE.Vector3(Math.cos(a) * 0.04, 0.35, Math.sin(a) * 0.04),
      new THREE.Vector3(Math.cos(a) * domeRadius * 0.6, top, Math.sin(a) * domeRadius * 0.6),
    ], 0.01), stemMat));
  }

  // Leaves peeking out between the flowers and the paper
  const leafMat = material({ vertexColors: true, name: "Leaves" });
  const leafGeo = leafGeometry(0.24, 0.09, leaf);
  for (let i = 0; i < 8; i++) {
    const a = (i / 8) * Math.PI * 2 + 0.2;
    const l = new THREE.Mesh(leafGeo, leafMat);
    l.position.set(Math.cos(a) * domeRadius * 0.85, top - 0.16, Math.sin(a) * domeRadius * 0.85);
    l.rotation.set(0, -a + Math.PI / 2, 0.9);
    root.add(l);
  }

  const wrap = new THREE.Mesh(paperGeometry(0.62, domeRadius * 1.15, 0.05), material({ color: paper, roughness: 0.85, name: "Paper" }));
  wrap.position.y = 0.12;
  root.add(wrap);

  const ribbon = new THREE.Mesh(new THREE.TorusGeometry(0.075, 0.016, 8, 32), material({ color: "#d95f76", name: "Ribbon" }));
  ribbon.rotation.x = Math.PI / 2;
  ribbon.position.y = 0.3;
  ribbon.geometry.deleteAttribute("uv");
  root.add(ribbon);
  return root;
}

/**
 * @param {object} options
 * @param {"single"|"bouquet"} options.kind
 * @param {string[]} options.colors   petal colors, most common first
 * @param {string} options.leaf       leaf / stem green
 * @param {string} [options.head]     single flower type
 * @param {Record<string, number>} [options.counts] bouquet heads per type
 * @param {string} [options.paper]    bouquet wrap color
 */
export function buildMatchedModel(options) {
  const colors = options.colors?.length ? options.colors : ["#c81e2d"];
  const opts = { ...options, colors, leaf: options.leaf || "#4c8a3c" };
  return opts.kind === "bouquet" ? buildBouquet(opts) : buildSingle(opts);
}

/** Binary glTF (.glb) of the model, as a Blob */
export async function exportGlb(object) {
  const exporter = new GLTFExporter();
  const result = await exporter.parseAsync(object, { binary: true });
  return new Blob([result], { type: "model/gltf-binary" });
}
