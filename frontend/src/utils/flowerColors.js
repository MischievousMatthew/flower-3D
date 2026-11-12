/** A paint for each flower color name (swatches on the shop page) */
const SWATCHES = {
  red: "#c8102e", pink: "#f4a6c0", white: "#ffffff", cream: "#f5ecd7", ivory: "#f7f3e8", yellow: "#f5c518",
  orange: "#f08a24", peach: "#f7b997", coral: "#f27b6b", purple: "#7d4bb3", lavender: "#c3a8e3", lilac: "#c8a2c8",
  blue: "#4a7fd0", green: "#5aa469", burgundy: "#7a1f3d", magenta: "#c2185b",
  mixed: "conic-gradient(#c8102e, #f5c518, #5aa469, #4a7fd0, #7d4bb3, #c8102e)",
};

export function flowerSwatch(name) {
  const key = String(name ?? "").toLowerCase().trim();
  if (SWATCHES[key]) return SWATCHES[key];
  if (typeof CSS !== "undefined" && CSS.supports?.("color", key)) return key;
  return SWATCHES.mixed;
}

// Flower colours a product comes in, shared by the vendor and ERP product forms.
// A form keeps a list of picked colours: [{ value, name, quantity }]
// - value: one of FLOWER_COLORS (name is only used for "other")
// - quantity: how many of that colour are in stock (used when 2+ colours are picked)

export const FLOWER_COLORS = [
  { value: "white", label: "White", hex: "#ffffff" },
  { value: "cream", label: "Cream", hex: "#f5ecd7" },
  { value: "yellow", label: "Yellow", hex: "#f6d743" },
  { value: "orange", label: "Orange", hex: "#f6943b" },
  { value: "red", label: "Red", hex: "#d63031" },
  { value: "pink", label: "Pink", hex: "#f78fb3" },
  { value: "purple", label: "Purple", hex: "#8e5bd6" },
  { value: "blue", label: "Blue", hex: "#4a90e2" },
  { value: "green", label: "Green", hex: "#4caf50" },
  { value: "other", label: "Other", hex: "conic-gradient(#f78fb3, #f6d743, #4a90e2, #8e5bd6, #f78fb3)" },
];

export const colorMeta = (value) => FLOWER_COLORS.find((c) => c.value === value) || null;

/** The name customers see: the list's label, or the shop's own name for "other" */
export const colorName = (entry) =>
  entry?.value === "other" ? String(entry.name || "").trim() : colorMeta(entry?.value)?.label || String(entry?.value || "");

/** A real hex for a swatch (the "other" rainbow isn't one) */
const solidHex = (value) => {
  const hex = colorMeta(value)?.hex || "";
  return /^#[0-9a-f]{3,8}$/i.test(hex) ? hex : null;
};

const valueForName = (name) => {
  const lower = String(name || "").trim().toLowerCase();
  return FLOWER_COLORS.find((c) => c.value !== "other" && c.label.toLowerCase() === lower)?.value || "other";
};

/** The picked colours of an existing product (for its edit form) */
export function colorsFromProduct(p) {
  const options = Array.isArray(p?.color_options) ? p.color_options.filter((c) => c && c.name) : [];
  if (options.length >= 2) {
    return options.map((c) => {
      const value = c.value && colorMeta(c.value) ? c.value : valueForName(c.name);
      return { value, name: value === "other" ? c.name : "", quantity: Number(c.quantity) || 0 };
    });
  }
  if (p?.color) {
    return [{ value: p.color, name: p.color === "other" ? p.color_other || "" : "", quantity: Number(p.quantity_in_stock) || 0 }];
  }
  return [];
}

/** True when each colour has its own stock count (2 or more colours) */
export const countsPerColor = (colors) => (colors?.length || 0) >= 2;

export const totalOf = (colors) => (colors || []).reduce((sum, c) => sum + (Number(c.quantity) || 0), 0);

/**
 * The colour fields a product form sends: the main colour (the first picked)
 * as color / color_other, and color_options with a count per colour when 2+
 * are picked (an empty list otherwise, which clears it on the server).
 */
export function colorFields(colors) {
  const list = colors || [];
  const first = list[0];
  const multi = countsPerColor(list);
  return {
    color: first?.value || "",
    color_other: first?.value === "other" ? String(first.name || "").trim() : "",
    color_options: JSON.stringify(
      multi
        ? list.map((c) => ({ name: colorName(c), value: c.value, hex: solidHex(c.value), quantity: Math.max(0, Math.floor(Number(c.quantity) || 0)) }))
        : [],
    ),
    ...(multi ? { quantity_in_stock: totalOf(list) } : {}),
  };
}

/** A form error for the colours, or "" when they're fine */
export function colorProblem(colors) {
  const list = colors || [];
  if (!list.length) return "Pick at least one flower colour.";
  if (list.some((c) => c.value === "other" && !String(c.name || "").trim())) return "Name the colour you picked as Other.";
  if (countsPerColor(list)) {
    const bad = list.find((c) => !Number.isInteger(Number(c.quantity)) || Number(c.quantity) < 0 || c.quantity === "" || c.quantity === null);
    if (bad) return `Enter how many ${colorName(bad) || "flowers"} you have (0 or more).`;
  }
  return "";
}
