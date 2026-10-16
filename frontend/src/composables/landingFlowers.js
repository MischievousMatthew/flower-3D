// Sculpted flower heads for the landing-page bouquet.
//
// Boquet.glb is the customizer's base: wrapper, fillers and stems, but no
// flower heads (vendors' flowers are placed into it in the 3D customizer).
// For the landing page we sculpt stylized heads from curved petal geometry
// and arrange them in a dome inside the real bouquet's wrapper. Each variety
// is one merged geometry, so a full bouquet is ~14 draw calls.
import * as THREE from "three";
import { mergeGeometries } from "three/examples/jsm/utils/BufferGeometryUtils.js";
import { gsap } from "gsap";

export const BLOOM_VARIETIES = [
  { key: "signature", label: "Signature mix" },
  { key: "roses", label: "Roses" },
  { key: "tulips", label: "Tulips" },
  { key: "peonies", label: "Peonies" },
  { key: "daisies", label: "Daisies" },
];

// Small deterministic PRNG so the arrangement looks the same on every visit.
function seeded(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));

// One curved petal standing on +Y with its inner face toward +Z.
function petalGeometry({
  length,
  width,
  cup = 0.4, // sideways cupping toward the centre
  curl = 0, // tip bends inward (+) or outward (-)
  ruffle = 0, // wavy edge
  pointed = false,
  base,
  tip,
}) {
  const geometry = new THREE.PlaneGeometry(1, 1, 10, 16);
  const pos = geometry.attributes.position;
  const colors = new Float32Array(pos.count * 3);
  const cBase = new THREE.Color(base);
  const cTip = new THREE.Color(tip);
  const color = new THREE.Color();

  for (let i = 0; i < pos.count; i++) {
    const u = pos.getX(i) * 2; // -1 .. 1 across
    const v = pos.getY(i) + 0.5; // 0 .. 1 along
    // Outline: pointed (tulip) or a rounded superellipse, narrowing to a
    // claw at the base.
    const claw = 0.3 + 0.7 * THREE.MathUtils.smoothstep(v, 0, 0.45);
    const envelope = pointed
      ? Math.pow(Math.sin(Math.PI * (0.06 + 0.94 * v)), 0.7) * claw
      : Math.pow(Math.max(1 - Math.pow(Math.abs(2 * v - 1), 2.4), 0), 0.42) * claw;
    const w = width * envelope;
    const x = (u * w) / 2;
    const y = v * length;
    let z = cup * w * u * u;
    z += curl * length * v * v;
    z += ruffle * Math.sin(u * 7 + v * 5) * v * v;
    pos.setXYZ(i, x, y, z);

    color.lerpColors(cBase, cTip, Math.pow(v, 0.85));
    color.multiplyScalar(1 - 0.1 * Math.abs(u));
    colors.set([color.r, color.g, color.b], i * 3);
  }
  geometry.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  geometry.computeVertexNormals();
  return geometry;
}

// Places a petal around the head: offset from the centre, leaned outward by
// `tilt`, then turned to `angle` around the stem.
function placePetal(geometry, { angle, tilt, offset = 0, y = 0 }) {
  const m = new THREE.Matrix4()
    .makeRotationY(angle)
    .multiply(new THREE.Matrix4().makeTranslation(0, y, -offset))
    .multiply(new THREE.Matrix4().makeRotationX(-tilt));
  geometry.applyMatrix4(m);
  return geometry;
}

function ring(petals, count, spec, place, rand, startAngle = 0) {
  for (let i = 0; i < count; i++) {
    const g = petalGeometry(spec);
    petals.push(
      placePetal(g, {
        angle: startAngle + (i / count) * Math.PI * 2 + (rand() - 0.5) * 0.25,
        tilt: place.tilt + (rand() - 0.5) * 0.06,
        offset: place.offset,
        y: place.y || 0,
      }),
    );
  }
}

function discGeometry(radius, height, base, top) {
  const geometry = new THREE.SphereGeometry(radius, 20, 10);
  geometry.scale(1, height / radius, 1);
  const pos = geometry.attributes.position;
  const colors = new Float32Array(pos.count * 3);
  const cBase = new THREE.Color(base);
  const cTop = new THREE.Color(top);
  const color = new THREE.Color();
  for (let i = 0; i < pos.count; i++) {
    color.lerpColors(cBase, cTop, THREE.MathUtils.clamp(pos.getY(i) / height + 0.5, 0, 1));
    colors.set([color.r, color.g, color.b], i * 3);
  }
  geometry.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  return geometry;
}

// A petal wrapped around the stem axis: it follows a circle of `radius`,
// spans `span` radians, flares outward as it rises and its tip reflexes back.
// This gives roses, tulips and peonies a true cupped silhouette.
function wrappedPetal({
  radius,
  length,
  span, // radians of the circle covered by the petal's widest point
  flare = 0, // outward lean (added radius at the tip, in units of length)
  reflex = 0, // extra outward roll of the top third
  bulge = 0, // swelling at mid-height (tulip / peony bowls), fraction of radius
  pointed = false,
  ruffle = 0,
  base,
  tip,
}) {
  const geometry = new THREE.PlaneGeometry(1, 1, 14, 16);
  const pos = geometry.attributes.position;
  const colors = new Float32Array(pos.count * 3);
  const cBase = new THREE.Color(base);
  const cTip = new THREE.Color(tip);
  const color = new THREE.Color();

  for (let i = 0; i < pos.count; i++) {
    const u = pos.getX(i) * 2; // -1 .. 1 across
    const v = pos.getY(i) + 0.5; // 0 .. 1 along
    const claw = 0.7 + 0.3 * THREE.MathUtils.smoothstep(v, 0, 0.35);
    const envelope = pointed
      ? Math.pow(Math.sin(Math.PI * (0.05 + 0.6 * v)), 0.6) * (1 - Math.pow(v, 3) * 0.85) * claw
      : Math.pow(Math.max(1 - Math.pow(Math.abs(2 * v - 1), 2.6), 0), 0.35) * claw;
    const theta = (u * span * envelope) / 2;
    const roll = Math.max(v - 0.62, 0) / 0.38;
    const r =
      radius * (1 + bulge * Math.sin(Math.PI * Math.min(v * 1.15, 1))) +
      flare * length * Math.pow(v, 1.6) +
      reflex * length * roll * roll * 0.5 +
      ruffle * Math.sin(u * 9 + v * 6) * v;
    const y = v * length * (1 - 0.25 * Math.min(flare, 1)) - reflex * length * roll * roll * 0.15;
    pos.setXYZ(i, r * Math.sin(theta), y, -r * Math.cos(theta));

    color.lerpColors(cBase, cTip, Math.pow(v, 0.8));
    color.multiplyScalar(1 - 0.08 * Math.abs(u));
    colors.set([color.r, color.g, color.b], i * 3);
  }
  geometry.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  geometry.computeVertexNormals();
  return geometry;
}

function wrappedRing(petals, count, spec, rand, { startAngle = 0, y = 0 } = {}) {
  for (let i = 0; i < count; i++) {
    const jitter = spec.lengthJitter || 0;
    const g = wrappedPetal({
      ...spec,
      length: spec.length * (1 - jitter / 2 + rand() * jitter),
      flare: (spec.flare || 0) * (0.9 + rand() * 0.2),
    });
    g.applyMatrix4(
      new THREE.Matrix4()
        .makeTranslation(0, y, 0)
        .multiply(new THREE.Matrix4().makeRotationY(startAngle + (i / count) * Math.PI * 2 + (rand() - 0.5) * 0.3)),
    );
    petals.push(g);
  }
}

// ---- Varieties ----
const BUILDERS = {
  rose(rand) {
    const petals = [];
    const c = (base, tip) => ({ base, tip });
    wrappedRing(petals, 3, { radius: 0.03, length: 0.34, span: 3.4, flare: 0.02, ...c("#4a0b16", "#7f1729") }, rand, { y: 0.07 });
    wrappedRing(petals, 4, { radius: 0.055, length: 0.4, span: 2.8, flare: 0.06, reflex: 0.06, ...c("#520d1a", "#8f1c31") }, rand, { startAngle: 0.5, y: 0.05 });
    wrappedRing(petals, 5, { radius: 0.085, length: 0.44, span: 2.3, flare: 0.13, reflex: 0.16, bulge: 0.15, ...c("#5a0f1e", "#9c2337") }, rand, { startAngle: 0.2, y: 0.025 });
    wrappedRing(petals, 6, { radius: 0.115, length: 0.46, span: 1.9, flare: 0.22, reflex: 0.3, bulge: 0.2, ...c("#62121f", "#a92c41") }, rand, { startAngle: 0.8 });
    wrappedRing(petals, 6, { radius: 0.14, length: 0.44, span: 1.7, flare: 0.36, reflex: 0.42, bulge: 0.2, ...c("#6a1524", "#b5374b") }, rand, { startAngle: 0.3, y: -0.025 });
    return mergeGeometries(petals);
  },

  tulip(rand) {
    const petals = [];
    const spec = { length: 0.62, span: 2.5, flare: -0.02, reflex: 0.05, bulge: 0.75, pointed: true, base: "#bf5462", tip: "#f6c4be" };
    wrappedRing(petals, 3, { ...spec, radius: 0.12 }, rand, { y: 0.02 });
    wrappedRing(petals, 3, { ...spec, radius: 0.14, base: "#c9626f", tip: "#f9d0ca" }, rand, { startAngle: Math.PI / 3 });
    return mergeGeometries(petals);
  },

  peony(rand) {
    // A fluffy dome: many short, ruffled, strongly bulging petals.
    const petals = [];
    const rings = 6;
    for (let k = 0; k < rings; k++) {
      const t = k / (rings - 1); // 0 = centre, 1 = outer
      wrappedRing(
        petals,
        6 + Math.round(t * 4),
        {
          radius: 0.035 + t * 0.14,
          length: 0.3 + t * 0.14,
          span: 2.7 - t * 0.8,
          // Inner petals curl inward over the centre; outer ones open out.
          flare: -0.06 + t * 0.4,
          reflex: -0.35 + t * 0.55,
          bulge: 0.55,
          ruffle: 0.03,
          lengthJitter: 0.35,
          base: k % 2 ? "#dc8d9e" : "#e39aaa",
          tip: k % 2 ? "#f7d3da" : "#fbe3e8",
        },
        rand,
        { startAngle: k * 0.9, y: (1 - t) * 0.07 },
      );
    }
    return mergeGeometries(petals);
  },

  daisy(rand) {
    const petals = [];
    ring(
      petals,
      22,
      { length: 0.46, width: 0.14, cup: 0.2, curl: -0.04, base: "#e6e0d2", tip: "#fffdf8" },
      { tilt: 1.36, offset: 0.08 },
      rand,
      0,
    );
    const disc = discGeometry(0.11, 0.06, "#b9840f", "#e2ad2c");
    disc.translate(0, 0.02, 0);
    petals.push(disc);
    // Only position/normal/uv/color may differ between parts; strip extras.
    return mergeGeometries(petals.map((g) => (g.index ? g.toNonIndexed() : g)));
  },
};

export const HEAD_TYPES = Object.keys(BUILDERS);

/**
 * One sculpted head (rose, tulip, peony or daisy), scaled so its largest side
 * is 1, standing on +Y with its base at the origin. Colors are the variety's
 * own; callers may recolor the `color` attribute.
 */
export function createHeadGeometry(head, seed = head.length * 977) {
  const g = BUILDERS[head](seeded(seed));
  g.computeBoundingBox();
  const size = g.boundingBox.getSize(new THREE.Vector3());
  const k = 1 / Math.max(size.x, size.y, size.z);
  g.scale(k, k, k);
  g.computeBoundingBox();
  g.translate(0, -g.boundingBox.min.y, 0);
  return g;
}

// Which variety goes in each slot for the signature mix.
const SIGNATURE_PATTERN = ["peony", "rose", "daisy", "rose", "peony", "tulip", "rose", "daisy", "peony", "rose", "tulip", "daisy", "rose", "peony"];
const VARIETY_HEAD = { roses: "rose", tulips: "tulip", peonies: "peony", daisies: "daisy" };
const HEAD_SCALE = { rose: 0.95, tulip: 0.9, peony: 1.05, daisy: 1 };

/**
 * Creates the bloom layer.
 * @param {object} opts
 * @param {THREE.Vector3} opts.anchor  top-centre of the bouquet opening
 * @param {number} opts.radius         dome radius
 * @param {number} opts.headSize       target head diameter
 * @param {boolean} opts.reducedMotion swap instantly
 */
export function createBloomLayer({ anchor, radius, headSize, reducedMotion = false }) {
  const group = new THREE.Group();
  const material = new THREE.MeshPhysicalMaterial({
    vertexColors: true,
    roughness: 0.62,
    metalness: 0,
    side: THREE.DoubleSide,
    sheen: 0.6,
    sheenRoughness: 0.45,
    sheenColor: new THREE.Color("#fff1ec"),
  });

  // Geometries are normalised so each head's largest dimension is 1.
  const geometries = {};
  const getGeometry = (head) => {
    if (!geometries[head]) {
      const g = BUILDERS[head](seeded(head.length * 977));
      g.computeBoundingBox();
      const size = g.boundingBox.getSize(new THREE.Vector3());
      const k = 1 / Math.max(size.x, size.y, size.z);
      g.scale(k, k, k);
      geometries[head] = g;
    }
    return geometries[head];
  };

  // Dome of slots: one centre head, then two rings, each leaning outward.
  const rand = seeded(42);
  const slots = [];
  const rings = [
    { count: 1, r: 0, lift: 0.22 },
    { count: 5, r: 0.48, lift: 0.1 },
    { count: 8, r: 0.95, lift: -0.08 },
  ];
  rings.forEach(({ count, r, lift }, ringIndex) => {
    for (let i = 0; i < count; i++) {
      const a = (i / count) * Math.PI * 2 + ringIndex * 0.4 + (rand() - 0.5) * 0.3;
      const dir = new THREE.Vector3(Math.cos(a), 0, Math.sin(a));
      const position = new THREE.Vector3(
        anchor.x + dir.x * r * radius,
        anchor.y + lift * radius + (rand() - 0.5) * 0.05 * radius,
        anchor.z + dir.z * r * radius,
      );
      // Lean the head outward from the centre of the dome.
      const axis = new THREE.Vector3(0, 1, 0).cross(dir).normalize();
      const quaternion = new THREE.Quaternion();
      if (r > 0) quaternion.setFromAxisAngle(axis, r * 0.95);
      // A gentle forward tilt so faces read from the front.
      quaternion.premultiply(new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(1, 0, 0), 0.22));
      quaternion.multiply(new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), rand() * Math.PI * 2));
      slots.push({ position, quaternion, scale: 0.9 + rand() * 0.2 });
    }
  });

  let meshes = [];
  let current = null;

  function headFor(variety, index) {
    if (variety === "signature") return SIGNATURE_PATTERN[index % SIGNATURE_PATTERN.length];
    return VARIETY_HEAD[variety] || "rose";
  }

  function build(variety) {
    return slots.map((slot, i) => {
      const head = headFor(variety, i);
      const mesh = new THREE.Mesh(getGeometry(head), material);
      mesh.position.copy(slot.position);
      mesh.quaternion.copy(slot.quaternion);
      mesh.userData.targetScale = headSize * slot.scale * HEAD_SCALE[head];
      mesh.scale.setScalar(mesh.userData.targetScale);
      group.add(mesh);
      return mesh;
    });
  }

  function setVariety(variety, { animate = true } = {}) {
    if (variety === current) return;
    current = variety;
    const outgoing = meshes;
    meshes = build(variety);

    if (!animate || reducedMotion) {
      outgoing.forEach((m) => group.remove(m));
      return;
    }

    // Old heads close and sink, new ones unfurl with a slight twist.
    outgoing.forEach((m, i) => {
      gsap.killTweensOf(m.scale);
      gsap.to(m.scale, {
        x: 0,
        y: 0,
        z: 0,
        duration: 0.35,
        delay: i * 0.015,
        ease: "power2.in",
        onComplete: () => group.remove(m),
      });
    });
    meshes.forEach((m, i) => {
      const s = m.userData.targetScale;
      m.scale.setScalar(0.0001);
      gsap.to(m.scale, { x: s, y: s, z: s, duration: 0.75, delay: 0.22 + i * 0.03, ease: "back.out(1.7)" });
      gsap.from(m.rotation, { y: m.rotation.y - 0.8, duration: 0.9, delay: 0.22 + i * 0.03, ease: "power3.out" });
    });
  }

  function dispose() {
    gsap.killTweensOf(meshes.map((m) => m.scale));
    Object.values(geometries).forEach((g) => g.dispose());
    material.dispose();
  }

  setVariety("signature", { animate: false });

  return { group, material, setVariety, getVariety: () => current, dispose };
}
