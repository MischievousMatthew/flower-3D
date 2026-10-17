// Pieces every bouquet design shares: tintable layers, the satin ribbon and
// bow, and the flower bed that flowers are placed on. Units match the classic
// bouquet after normalizing (about 3.5 tall, opening about 2.4 across).
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

/** Light vertex-colour variation, so plain paper/box surfaces read as material */
export function grain(geometry, { amount = 0.06, seed = 7 } = {}) {
  const rand = seeded(seed);
  const n = geometry.attributes.position.count;
  const colors = new Float32Array(n * 3);
  for (let i = 0; i < n; i++) {
    const k = 1 - amount + rand() * amount;
    colors.set([k, k, k], i * 3);
  }
  geometry.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  return geometry;
}

export const surface = (options = {}) =>
  new THREE.MeshStandardMaterial({ roughness: 0.82, metalness: 0, side: THREE.DoubleSide, vertexColors: true, ...options });

/**
 * A recolourable layer (outer paper, liner, box, basket…). `apply(null)`
 * returns to the design's own colour, like the classic model's "original paper".
 */
export function makeLayer(meshes, defaultColor) {
  const materials = [...new Set(meshes.flatMap((m) => [].concat(m.material)))];
  const apply = (hex) => materials.forEach((m) => m.color.set(hex || defaultColor));
  apply(null);
  return { meshes, apply, defaultColor };
}

export const emptyLayer = (defaultColor = "#ffffff") => ({ meshes: [], apply() {}, defaultColor });

function satinMaterial() {
  return new THREE.MeshPhysicalMaterial({
    color: "#caa6d9",
    roughness: 0.34,
    metalness: 0,
    sheen: 0.7,
    sheenRoughness: 0.32,
    clearcoat: 0.2,
    clearcoatRoughness: 0.45,
    side: THREE.DoubleSide,
  });
}

/** A ribbon loop of the bow: a flattened teardrop band */
function bowLoop(length, width, side) {
  const curve = new THREE.CatmullRomCurve3(
    [
      new THREE.Vector3(0, 0, 0),
      new THREE.Vector3(side * length * 0.45, width * 0.9, 0.04),
      new THREE.Vector3(side * length, width * 0.35, 0.02),
      new THREE.Vector3(side * length * 0.8, -width * 0.45, 0.03),
      new THREE.Vector3(side * length * 0.3, -width * 0.35, 0.05),
      new THREE.Vector3(0, 0, 0),
    ],
    true,
  );
  return ribbonStrip(curve, width * 0.55, 64);
}

/** A flat strip of `width` following a curve (ribbon cross-section is flat) */
function ribbonStrip(curve, width, segments) {
  const frames = curve.computeFrenetFrames(segments, curve.closed);
  const positions = [];
  const indices = [];
  for (let i = 0; i <= segments; i++) {
    const p = curve.getPointAt((i / segments) % 1);
    const b = frames.binormals[Math.min(i, segments - 1)];
    positions.push(...p.clone().addScaledVector(b, width / 2).toArray(), ...p.clone().addScaledVector(b, -width / 2).toArray());
    if (i < segments) {
      const a = i * 2;
      indices.push(a, a + 1, a + 2, a + 1, a + 3, a + 2);
    }
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
  g.setIndex(indices);
  g.computeVertexNormals();
  return g;
}

/**
 * Ribbon tied around something round at height `y`: a band of `radius`, and
 * a bow facing the front (+z). Returns { group, apply(hex|null) }.
 */
export function makeRibbon({ y, radius, band = 0.12, bowSize = 0.32, front = new THREE.Vector3(0, 0, 1) }) {
  const material = satinMaterial();
  const group = new THREE.Group();
  group.name = "Ribbon";

  const ring = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius * 1.02, band, 48, 1, true), material);
  ring.position.y = y;
  group.add(ring);

  const bow = new THREE.Group();
  bow.add(new THREE.Mesh(bowLoop(bowSize, bowSize * 0.55, 1), material));
  bow.add(new THREE.Mesh(bowLoop(bowSize, bowSize * 0.55, -1), material));
  // The knot follows the bow's size, not the band (a tall band would make a ball)
  const knot = new THREE.Mesh(new THREE.SphereGeometry(bowSize * 0.16, 16, 12), material);
  knot.scale.set(1, 0.85, 0.6);
  bow.add(knot);
  for (const side of [1, -1]) {
    const tail = new THREE.CatmullRomCurve3([
      new THREE.Vector3(0, 0, 0.02),
      new THREE.Vector3(side * bowSize * 0.18, -bowSize * 0.6, 0.06),
      new THREE.Vector3(side * bowSize * 0.3, -bowSize * 1.3, 0.03),
    ]);
    bow.add(new THREE.Mesh(ribbonStrip(tail, band * 0.9, 24), material));
  }
  const dir = front.clone().setY(0).normalize();
  bow.position.set(dir.x * (radius + 0.02), y, dir.z * (radius + 0.02));
  bow.lookAt(bow.position.clone().add(dir));
  group.add(bow);

  const tintSheen = () => material.sheenColor.copy(material.color).lerp(new THREE.Color("#ffffff"), 0.55);
  return {
    group,
    apply(hex) {
      if (hex) {
        material.color.set(hex);
        tintSheen();
      }
      group.visible = !!hex;
    },
  };
}

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
  const bed = new THREE.Mesh(g, new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 1, metalness: 0 }));
  bed.name = "FlowerBed";
  bed.position.y = y;
  return bed;
}

/** Shadows on, and every mesh named for debugging */
export function finish(root) {
  root.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
  });
  return root;
}
