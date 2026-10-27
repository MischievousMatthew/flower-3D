// Arrangement suggestions for the bouquet builder. Each call gives a fresh
// layout in the flower bed's own frame: offsets on a disc of radius 1 from the
// bed centre, so the caller scales them to the real opening. Nothing leaves
// the disc, which keeps every item inside the wrapper.

const TAU = Math.PI * 2;
const GOLDEN = Math.PI * (3 - Math.sqrt(5));

const shuffle = (list, rand) => {
  const out = [...list];
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(rand() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
};

const polar = (r, a) => ({ x: r * Math.cos(a), z: r * Math.sin(a) });

// Points on a sunflower spiral, the most even way to cover a disc
function spiral(count, reach, spin) {
  return Array.from({ length: count }, (_, i) => polar(reach * Math.sqrt((i + 0.5) / count), i * GOLDEN + spin));
}

/**
 * Flower layouts. `lift` raises a spot above the bed (0..1 of the dome
 * height) and `tilt` leans it outward (0..1 of the widest lean).
 */
const FLOWER_STYLES = [
  {
    key: "dome",
    label: "Round dome",
    spots(count, rand) {
      return spiral(count, 0.78, rand() * TAU).map((p) => {
        const t = Math.hypot(p.x, p.z);
        return { ...p, lift: 1 - t * t, tilt: t };
      });
    },
  },
  {
    key: "focal",
    label: "Centrepiece",
    spots(count, rand) {
      // One flower raised in the middle, the rest in a ring around it
      if (count === 1) return [{ x: 0, z: 0, lift: 1, tilt: 0 }];
      const ring = count - 1;
      const spin = rand() * TAU;
      return [
        { x: 0, z: 0, lift: 1, tilt: 0 },
        ...Array.from({ length: ring }, (_, i) => ({ ...polar(ring > 6 ? 0.55 + (i % 2) * 0.2 : 0.6, spin + (i / ring) * TAU), lift: 0.35, tilt: 0.8 })),
      ];
    },
  },
  {
    key: "cluster",
    label: "Tight cluster",
    spots(count, rand) {
      return spiral(count, Math.min(0.62, 0.3 + count * 0.03), rand() * TAU).map((p) => {
        const t = Math.hypot(p.x, p.z) / 0.62;
        return { ...p, lift: 0.8 - t * 0.5, tilt: t * 0.5 };
      });
    },
  },
  {
    key: "cascade",
    label: "Side sweep",
    spots(count, rand) {
      // Weighted to one side and stepping down, like a hand-tied posy
      const side = rand() * TAU;
      return spiral(count, 0.7, rand() * TAU).map((p) => {
        const along = (p.x * Math.cos(side) + p.z * Math.sin(side) + 0.7) / 1.4; // 0 back .. 1 front
        const x = p.x + Math.cos(side) * 0.1;
        const z = p.z + Math.sin(side) * 0.1;
        return { x, z, lift: 1 - along * 0.85, tilt: 0.25 + along * 0.6 };
      });
    },
  },
  {
    key: "loose",
    label: "Garden loose",
    spots(count, rand) {
      return spiral(count, 0.8, rand() * TAU).map((p) => ({
        x: p.x + (rand() - 0.5) * 0.14,
        z: p.z + (rand() - 0.5) * 0.14,
        lift: 0.3 + rand() * 0.7,
        tilt: Math.hypot(p.x, p.z) * (0.5 + rand() * 0.5),
      }));
    },
  },
];

/** Filler layouts: around the edge, tucked between the flowers, or both */
const FILLER_STYLES = [
  { key: "collar", label: "Green collar", edge: 1 },
  { key: "tucked", label: "Tucked between", edge: 0 },
  { key: "mixed", label: "Edge and gaps", edge: 0.5 },
];

const keepInside = (p, reach) => {
  const r = Math.hypot(p.x, p.z);
  if (r <= reach) return p;
  return { ...p, x: (p.x / r) * reach, z: (p.z / r) * reach };
};

/**
 * @param items   the flowers to arrange, [{ id, sceneId }]
 * @param avoid   style key used last time, so a retry looks different
 * @returns { style, label, spots: [{ sceneId, x, z, lift, tilt, turn }] }
 */
export function suggestFlowers(items, { avoid = null, rand = Math.random } = {}) {
  const choices = FLOWER_STYLES.filter((style) => style.key !== avoid);
  const style = choices[Math.floor(rand() * choices.length)];
  const spots = style.spots(items.length, rand).map((p) => keepInside(p, 0.85));

  // Like flowers sit together, or spread evenly, chosen per suggestion
  const groups = new Map();
  items.forEach((item) => groups.set(item.id, [...(groups.get(item.id) || []), item]));
  const grouped = rand() < 0.5;
  const order = grouped
    ? shuffle([...groups.values()], rand).flat()
    : interleave(shuffle([...groups.values()].map((group) => shuffle(group, rand)), rand));
  // Spots run centre-out, so in grouped mode a kind fills one patch
  const placed = grouped ? spots : shuffle(spots, rand);

  return {
    style: style.key,
    label: style.label,
    spots: order.map((item, i) => ({ sceneId: item.sceneId, ...placed[i], turn: (rand() - 0.5) * 40 })),
  };
}

function interleave(groups) {
  const out = [];
  for (let i = 0; groups.some((group) => i < group.length); i++) groups.forEach((group) => group[i] && out.push(group[i]));
  return out;
}

/**
 * @param fillers  [{ sceneId }]
 * @param flowers  where the flowers are now, as disc offsets [{ x, z }]
 */
export function suggestFillers(fillers, flowers, { avoid = null, rand = Math.random } = {}) {
  const choices = FILLER_STYLES.filter((style) => style.key !== avoid);
  const style = choices[Math.floor(rand() * choices.length)];
  const reach = 0.8; // fillers are wider than a bloom, so they stay well inside the rim

  // Candidates: a ring near the rim and a spiral over the middle
  const spin = rand() * TAU;
  const ring = Array.from({ length: 24 }, (_, i) => ({ ...polar(reach - rand() * 0.08, spin + (i / 24) * TAU), edge: true }));
  const inner = spiral(40, reach * 0.85, spin).map((p) => ({ ...p, edge: false }));

  // Prefer spots away from the flowers and from each other
  const taken = flowers.map((p) => ({ ...p }));
  const spots = [];
  for (let n = 0; n < fillers.length; n++) {
    const wantEdge = style.edge === 1 || (style.edge > 0 && n % 2 === 0);
    const pool = style.edge === 0 ? inner : wantEdge ? ring : inner;
    let best = null;
    let bestScore = -Infinity;
    for (const c of pool) {
      if (spots.includes(c)) continue;
      const near = taken.reduce((min, t) => Math.min(min, Math.hypot(t.x - c.x, t.z - c.z)), 2);
      const score = near + rand() * 0.15;
      if (score > bestScore) [best, bestScore] = [c, score];
    }
    if (!best) break;
    spots.push(best);
    taken.push(best);
  }

  return {
    style: style.key,
    label: style.label,
    spots: spots.map((p, i) => ({
      sceneId: fillers[i].sceneId,
      x: p.x,
      z: p.z,
      lift: p.edge ? 0 : 0.2,
      tilt: p.edge ? 0.9 : 0.4,
      turn: rand() * 360 - 180,
      scale: 0.85 + rand() * 0.2,
    })),
  };
}
