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

/**
 * Filler layouts. `minBack` keeps sprays to where the paper is at least that
 * tall (0 = all round, higher = only the back), `edge` is the share of
 * sprays against the paper rather than between the flowers.
 */
const FILLER_STYLES = [
  { key: "fan", label: "Fan behind", minBack: 0.15, edge: 0.85 },
  { key: "frame", label: "Framed all round", minBack: 0, edge: 1 },
  { key: "woven", label: "Woven through", minBack: 0, edge: 0.35 },
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
 * @param backness (angle) => 0..1, how tall the paper is that way (1 = the back);
 *                 sprays gather and stand taller where the paper is tall
 * @returns spots: { x, z, rise (0 low .. 1 tall), lean (0..1 extra outward), twist, scale }
 */
export function suggestFillers(fillers, flowers, { avoid = null, rand = Math.random, backness = () => 0.5 } = {}) {
  const choices = FILLER_STYLES.filter((style) => style.key !== avoid);
  const style = choices[Math.floor(rand() * choices.length)];

  // Candidates: a ring against the paper (the caller pulls each spray in until
  // its leaves clear the wall) and a spiral between the flowers
  const spin = rand() * TAU;
  const ring = Array.from({ length: 30 }, (_, i) => ({ ...polar(0.92 - rand() * 0.12, spin + (i / 30) * TAU), edge: true }));
  const inner = spiral(40, 0.6, spin).map((p) => ({ ...p, edge: false }));
  for (const c of [...ring, ...inner]) c.back = backness(Math.atan2(c.z, c.x));

  // Each pick: away from flowers and other sprays, toward the back as the style wants
  const taken = flowers.map((p) => ({ ...p }));
  const spots = [];
  for (let n = 0; n < fillers.length; n++) {
    const pool = (rand() < style.edge ? ring : inner).filter((c) => c.back >= style.minBack);
    let best = null;
    let bestScore = -Infinity;
    for (const c of pool) {
      if (spots.includes(c)) continue;
      const near = taken.reduce((min, t) => Math.min(min, Math.hypot(t.x - c.x, t.z - c.z)), 2);
      // Spacing always counts, so sprays spread across the back instead of stacking up
      const score = Math.min(near, 0.9) + c.back * 0.25 + rand() * 0.15;
      if (score > bestScore) [best, bestScore] = [c, score];
    }
    if (!best) break;
    spots.push(best);
    taken.push(best);
  }

  return {
    style: style.key,
    label: style.label,
    // The flowers stay the front of the bouquet: at the back a spray stands
    // as tall as the paper lets it (rise 1) and is the biggest; toward the
    // open front it's lower and smaller; between the flowers it sits deep so
    // only its tips show. Sprays at the paper lean out a little past the fan.
    spots: spots.map((p, i) => ({
      sceneId: fillers[i].sceneId,
      x: p.x,
      z: p.z,
      rise: p.edge ? 0.35 + p.back * 0.55 + rand() * 0.1 : rand() * 0.15,
      lean: p.edge ? 0.6 + rand() * 0.4 : rand() * 0.3,
      twist: (rand() - 0.5) * 50,
      scale: p.edge ? 0.8 + p.back * 0.3 + rand() * 0.1 : 0.75 + rand() * 0.1,
    })),
  };
}
