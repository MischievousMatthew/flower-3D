// Pieces every bouquet base shares: the (invisible) flower bed that flowers
// are placed on, and a few helpers. Units match the classic bouquet after
// normalizing (about 3.5 tall, opening about 2.4 across).
import * as THREE from "three";

// Small deterministic noise so designs look the same every time
export function seeded(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export const emptyLayer = (defaultColor = "#ffffff") => ({ meshes: [], apply() {}, defaultColor });

/**
 * The flower bed: a soft, slightly lumpy moss dome filling an opening of
 * `radius` at height `y`, rising by `height`. Flowers are placed on it.
 */
export function makeBed({ y, radius, height = 0.35, color = "#5f7a4a", seed = 3 }) {
  // A closed pillow: domed on top, a shallow curve underneath, so it never
  // shows an open edge from the side
  const g = new THREE.SphereGeometry(radius, 48, 24);
  const pos = g.attributes.position;
  const rand = seeded(seed);
  const bumps = Array.from({ length: 6 }, () => ({ a: rand() * 6.28, f: 5 + rand() * 7, w: 0.01 + rand() * 0.012 }));
  const colors = new Float32Array(pos.count * 3);
  const base = new THREE.Color(color);
  const c = new THREE.Color();
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i), yy = pos.getY(i), z = pos.getZ(i);
    const a = Math.atan2(z, x);
    const ring = Math.hypot(x, z) / radius;
    let bump = 0;
    // Small mossy lumps on the top only
    if (yy > 0) for (const b of bumps) bump += Math.sin(a * b.f + b.a + ring * 9) * b.w;
    const lift = yy > 0 ? (yy / radius) * height + bump * Math.min(1, ring * 3) : (yy / radius) * height * 0.25;
    pos.setXYZ(i, x * (1 + bump * 0.3), lift, z * (1 + bump * 0.3));
    c.copy(base).multiplyScalar(0.8 + 0.35 * rand() + bump * 2);
    colors.set([c.r, c.g, c.b], i * 3);
  }
  g.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  g.computeVertexNormals();
  // Not drawn: it's only the surface flowers are placed on. (A hidden
  // material keeps it clickable; a hidden object would not be.)
  const bed = new THREE.Mesh(g, new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 1, metalness: 0, visible: false }));
  bed.name = "FlowerBed";
  bed.position.y = y;
  return bed;
}

/** Shadows on, and every mesh named for debugging */
export function finish(root) {
  root.traverse((child) => {
    if (!child.isMesh) return;
    const hidden = child.name === "FlowerBed"; // placement surface only
    child.castShadow = !hidden;
    child.receiveShadow = !hidden;
  });
  return root;
}
