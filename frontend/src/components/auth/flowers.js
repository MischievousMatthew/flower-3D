// Line-art flowers for the sign-in pages' side panel (viewBox 0 0 320 420).
// Each is a list of shapes drawn in order: { d } for a path, { cx, cy, r } for a circle.

const turn = (shape, deg) => ({ ...shape, transform: `rotate(${deg} 160 150)` });

// A petal pointing up from the flower's centre (160,150)
const petal = (from, to, w) =>
  `M160 ${150 - from} C ${160 + w} ${150 - from - (to - from) * 0.3}, ${160 + w * 0.8} ${150 - to + (to - from) * 0.15}, 160 ${150 - to} C ${160 - w * 0.8} ${150 - to + (to - from) * 0.15}, ${160 - w} ${150 - from - (to - from) * 0.3}, 160 ${150 - from} Z`;
const ring = (count, from, to, w, offset = 0) =>
  Array.from({ length: count }, (_, i) => turn({ d: petal(from, to, w) }, offset + (360 / count) * i));

const stem = (top, sway = 0) => ({ d: `M160 ${top} C ${158 + sway} ${top + 60}, ${166 - sway} ${top + 120}, 156 410` });
const leaf = (x, y, dir, len = 80, w = 18) => {
  const tipX = x + dir * len;
  const tipY = y - len * 0.25;
  return [
    { d: `M${x} ${y} C ${x + dir * len * 0.35} ${y - w * 1.6}, ${tipX - dir * len * 0.2} ${tipY - w}, ${tipX} ${tipY} C ${tipX - dir * len * 0.3} ${tipY + w}, ${x + dir * len * 0.3} ${y + w * 0.6}, ${x} ${y} Z` },
    { d: `M${x} ${y} L ${tipX - dir * 10} ${tipY + 1}` },
  ];
};

export const FLOWERS = [
  {
    key: "tulip",
    latin: "Tulipa",
    name: "Spring Blush",
    line: "picked at dawn, wrapped by hand, at your door by noon.",
    shapes: [
      { d: "M160 236 C 132 214, 128 140, 160 92 C 192 140, 188 214, 160 236 Z" },
      { d: "M158 234 C 116 232, 96 190, 102 108 C 124 118, 142 140, 152 172" },
      { d: "M162 234 C 204 232, 224 190, 218 108 C 196 118, 178 140, 168 172" },
      { d: "M160 132 L 160 222" },
      { d: "M160 236 C 158 292, 165 346, 158 410" },
      { d: "M159 404 C 118 362, 102 312, 110 258 C 140 300, 154 350, 159 404 Z" },
      { d: "M161 382 C 198 346, 214 302, 208 266 C 184 300, 167 340, 161 382 Z" },
    ],
  },
  {
    key: "sunflower",
    latin: "Helianthus",
    name: "Golden Hour",
    line: "a handful of sunshine from your neighbourhood florist.",
    shapes: [
      ...ring(16, 36, 96, 11),
      ...ring(16, 34, 80, 9, 11.25),
      { cx: 160, cy: 150, r: 34 },
      { cx: 160, cy: 150, r: 22 },
      ...Array.from({ length: 10 }, (_, i) => {
        const a = i * 2.39996; // golden angle: seeds spiral like the real thing
        const rr = 5 + i * 1.7;
        return { cx: +(160 + Math.cos(a) * rr).toFixed(1), cy: +(150 + Math.sin(a) * rr).toFixed(1), r: 1.6 };
      }),
      stem(184, 4),
      { d: "M160 300 C 196 268, 236 280, 244 314 C 214 330, 180 326, 160 300 Z" },
      { d: "M160 300 L 228 308" },
      { d: "M158 350 C 124 322, 86 332, 78 364 C 108 378, 140 372, 158 350 Z" },
      { d: "M158 350 L 94 360" },
    ],
  },
  {
    key: "rose",
    latin: "Rosa",
    name: "Petal & Stem",
    line: "hand-tied by a local florist, delivered the morning it's made.",
    shapes: [
      ...ring(5, 0, 96, 46),
      ...ring(5, 0, 70, 34, 36),
      ...ring(3, 0, 40, 20, 18),
      { cx: 160, cy: 150, r: 7 },
      { cx: 160, cy: 150, r: 2.5 },
      stem(214),
      ...leaf(160, 300, 1),
      ...leaf(158, 350, -1),
    ],
  },
  {
    key: "daisy",
    latin: "Bellis",
    name: "Little Joy",
    line: "simple, cheerful, and designed by you in 3D.",
    shapes: [
      ...ring(18, 18, 92, 9),
      { cx: 160, cy: 150, r: 18 },
      { cx: 160, cy: 150, r: 10 },
      stem(168, -6),
      ...leaf(160, 290, -1, 70, 14),
      ...leaf(158, 340, 1, 64, 13),
    ],
  },
];

const LAST_KEY = "bc_auth_flower";

/** A random flower, never the same one twice in a row */
export function pickFlower() {
  let last = null;
  try {
    last = sessionStorage.getItem(LAST_KEY);
  } catch (_) {
    // storage unavailable: any flower will do
  }
  const choices = FLOWERS.filter((f) => f.key !== last);
  const flower = choices[Math.floor(Math.random() * choices.length)];
  try {
    sessionStorage.setItem(LAST_KEY, flower.key);
  } catch (_) {
    // ignore
  }
  return flower;
}
