// Removes stray fragments from AI-made meshes: small pieces that aren't
// connected to the main shape (TripoSR often leaves a sliver or two).
import * as THREE from "three";

// A piece smaller than this share of the mesh's triangles is a fragment.
// Small on purpose: real slivers are tiny (TripoSR's was 0.007%), while a
// leaf or petal in a bouquet can be 1–2% and must stay.
const MIN_SHARE = 0.005;

function unionFind(n) {
  const parent = new Int32Array(n);
  for (let i = 0; i < n; i++) parent[i] = i;
  const find = (a) => {
    while (parent[a] !== a) {
      parent[a] = parent[parent[a]];
      a = parent[a];
    }
    return a;
  };
  const union = (a, b) => {
    const ra = find(a), rb = find(b);
    if (ra !== rb) parent[ra] = rb;
  };
  return { find, union };
}

/**
 * @param {ArrayLike<number>} index triangle vertex ids (3 per triangle); ids
 *   that are the same point in space must already be the same number
 * @param {number} vertexCount
 * @returns {{ index: number[], keep: Uint8Array, removedTriangles: number }}
 */
export function removeSmallPieces(index, vertexCount, { minShare = MIN_SHARE } = {}) {
  const tris = Math.floor(index.length / 3);
  const { find, union } = unionFind(vertexCount);
  for (let t = 0; t < tris; t++) {
    union(index[t * 3], index[t * 3 + 1]);
    union(index[t * 3], index[t * 3 + 2]);
  }
  const sizes = new Map();
  for (let t = 0; t < tris; t++) {
    const root = find(index[t * 3]);
    sizes.set(root, (sizes.get(root) || 0) + 1);
  }
  let largest = 0;
  for (const s of sizes.values()) largest = Math.max(largest, s);
  const threshold = Math.min(largest, Math.max(1, Math.ceil(tris * minShare)));

  const keep = new Uint8Array(tris);
  const out = [];
  for (let t = 0; t < tris; t++) {
    if (sizes.get(find(index[t * 3])) >= threshold) {
      keep[t] = 1;
      out.push(index[t * 3], index[t * 3 + 1], index[t * 3 + 2]);
    }
  }
  return { index: out, keep, removedTriangles: tris - out.length / 3 };
}

// Same point in space -> same id, so seams with duplicated vertices still connect
function canonicalIds(position) {
  const ids = new Uint32Array(position.count);
  const seen = new Map();
  const q = (v) => Math.round(v * 1e5);
  for (let i = 0; i < position.count; i++) {
    const key = `${q(position.getX(i))},${q(position.getY(i))},${q(position.getZ(i))}`;
    let id = seen.get(key);
    if (id === undefined) {
      id = seen.size;
      seen.set(key, id);
    }
    ids[i] = id;
  }
  return { ids, count: seen.size };
}

// A copy holding only the vertices the kept triangles use (smaller file)
function compact(g, index) {
  const remap = new Map();
  const order = [];
  const newIndex = index.map((v) => {
    let n = remap.get(v);
    if (n === undefined) {
      n = order.length;
      remap.set(v, n);
      order.push(v);
    }
    return n;
  });
  const out = new THREE.BufferGeometry();
  for (const [name, attr] of Object.entries(g.attributes)) {
    const size = attr.itemSize;
    const array = new attr.array.constructor(order.length * size);
    order.forEach((v, i) => {
      for (let c = 0; c < size; c++) array[i * size + c] = attr.array[v * size + c];
    });
    out.setAttribute(name, new THREE.BufferAttribute(array, size, attr.normalized));
  }
  out.setIndex(newIndex);
  out.groups = g.groups.slice();
  out.computeBoundingBox();
  out.computeBoundingSphere();
  return out;
}

/** Cleans every mesh under `root` in place. Returns how many triangles were removed. */
export function cleanObject(root) {
  let removed = 0;
  root.traverse((child) => {
    if (!child.isMesh || !child.geometry?.attributes?.position) return;
    const g = child.geometry;
    const position = g.attributes.position;
    const original = g.index ? Array.from(g.index.array) : Array.from({ length: position.count }, (_, i) => i);
    if (original.length < 9) return;
    const { ids, count } = canonicalIds(position);
    const result = removeSmallPieces(original.map((v) => ids[v]), count);
    if (!result.removedTriangles) return;
    const kept = [];
    for (let t = 0; t < result.keep.length; t++) {
      if (result.keep[t]) kept.push(original[t * 3], original[t * 3 + 1], original[t * 3 + 2]);
    }
    child.geometry = compact(g, kept);
    g.dispose();
    removed += result.removedTriangles;
  });
  return removed;
}

