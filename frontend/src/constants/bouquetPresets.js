// Occasion presets for the 3D customizer. The shop's own settings come from
// GET /stores/{id}/customization-options (`presets`); these defaults mirror
// backend/app/Support/BouquetPresets.php for shops that send none.
// (Birthday is left out until the shop has a model for it.)

export const DEFAULT_PRESETS = [
  { key: "wedding", label: "Wedding", design: "hand_tied", wrapper: false, ribbon: true, sizes: [6, 12, 24] },
  { key: "romance", label: "Anniversary / Valentine's", design: "korean", wrapper: true, ribbon: true, sizes: [6, 12, 24] },
  { key: "graduation", label: "Graduation", design: "classic", wrapper: true, ribbon: true, sizes: [3, 6, 9] },
  { key: "sympathy", label: "Sympathy", design: "basket", wrapper: true, ribbon: false, sizes: [6, 12] },
  { key: "just_because", label: "Just because", design: "classic", wrapper: true, ribbon: true, sizes: [3, 6, 12] },
];

// Words and icons for the occasion cards
export const OCCASION_INFO = {
  wedding: { icon: "rings", blurb: "A hand-tied bridal bouquet finished with a satin ribbon." },
  romance: { icon: "heart", blurb: "A Korean-style layered wrap, open and airy, for anniversaries and Valentine's Day." },
  graduation: { icon: "cap", blurb: "The classic paper cone, for the big day." },
  sympathy: { icon: "dove", blurb: "A woven basket of flowers to share your condolences." },
  just_because: { icon: "flower", blurb: "The classic paper cone. No reason needed." },
};

// Names for sizes, by their place in the occasion's list
const SIZE_NAMES = [["Classic"], ["Petite", "Grand"], ["Petite", "Classic", "Grand"], ["Petite", "Classic", "Grand", "Luxe"]];
export const sizeName = (sizes, index) => (SIZE_NAMES[sizes.length - 1] ?? [])[index] ?? `${sizes[index]} stems`;

/** Bouquets saved before presets existed open as this */
export const LEGACY_PRESET = Object.freeze({ key: "just_because", size: 3 });

export const presetsOrDefaults = (presets) => (Array.isArray(presets) && presets.length ? presets : DEFAULT_PRESETS);

// Wrapper size by number of stems, interpolated between these points
const WRAPPER_POINTS = [
  [6, 1],
  [12, 1.15],
  [24, 1.35],
  [36, 1.5],
];

export function wrapperScaleFor(size) {
  const n = Number(size) || 0;
  if (n <= WRAPPER_POINTS[0][0]) return WRAPPER_POINTS[0][1];
  for (let i = 1; i < WRAPPER_POINTS.length; i++) {
    const [x1, y1] = WRAPPER_POINTS[i];
    if (n <= x1) {
      const [x0, y0] = WRAPPER_POINTS[i - 1];
      return y0 + ((y1 - y0) * (n - x0)) / (x1 - x0);
    }
  }
  return WRAPPER_POINTS[WRAPPER_POINTS.length - 1][1];
}

/** Same rule as the server: max(5, half the stems, rounded up) */
export const fillerLimitFor = (size) => Math.max(5, Math.ceil((Number(size) || 0) / 2));

/**
 * `count` points spread evenly over a disc of `radius` (a sunflower spiral),
 * as { x, z } offsets from the centre. Used to place "fill the rest" flowers.
 */
export function spiralPoints(count, radius) {
  const golden = Math.PI * (3 - Math.sqrt(5));
  return Array.from({ length: Math.max(0, count) }, (_, i) => {
    const r = radius * Math.sqrt((i + 0.5) / count);
    const a = i * golden;
    return { x: r * Math.cos(a), z: r * Math.sin(a) };
  });
}
