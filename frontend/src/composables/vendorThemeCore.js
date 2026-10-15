// Color math and token values for the vendor theme. No Vue here, so the
// rules can be tested with plain Node (tests/vendorTheme.test.mjs).

export const DEFAULT_ACCENT = "#48bb78";
export const MODES = ["system", "light", "dark"];
export const MODE_KEY = "bc_vendor_color_mode";
export const ACCENT_KEY = "bc_vendor_sidebar_accent";

export const ACCENT_PRESETS = [
  { name: "Leaf", value: "#48bb78" },
  { name: "Teal", value: "#0d9488" },
  { name: "Ocean", value: "#2563eb" },
  { name: "Indigo", value: "#4f46e5" },
  { name: "Lavender", value: "#8b5cf6" },
  { name: "Rose", value: "#e11d6b" },
  { name: "Coral", value: "#f97316" },
  { name: "Gold", value: "#ca8a04" },
  { name: "Charcoal", value: "#334155" },
];

// Status colors: each has a soft background and a text color
export const HUES = ["red", "amber", "blue", "green", "purple", "pink", "teal"];

// Light values are the colors the vendor pages already use
export const LIGHT_TOKENS = {
  bg: "#f4f6f8",
  surface: "#ffffff",
  raised: "#ffffff",
  subtle: "#f7fafc",
  "subtle-2": "#edf2f7",
  input: "#ffffff",
  border: "#e2e8f0",
  "border-strong": "#cbd5e0",
  text: "#1a202c",
  "text-2": "#4a5568",
  muted: "#718096",
  faint: "#a0aec0",
  glass: "rgba(255, 255, 255, 0.92)",
  shadow: "rgba(15, 23, 42, 0.08)",
  "red-soft": "#fff5f5", "red-text": "#c53030",
  "amber-soft": "#fffaf0", "amber-text": "#b45309",
  "blue-soft": "#ebf4ff", "blue-text": "#2b6cb0",
  "green-soft": "#e6f9ee", "green-text": "#22753f",
  "purple-soft": "#f3f0ff", "purple-text": "#6b46c1",
  "pink-soft": "#fff0f6", "pink-text": "#b83280",
  "teal-soft": "#e6fffa", "teal-text": "#2c7a7b",
};

export const DARK_TOKENS = {
  bg: "#0f141b",
  surface: "#161c25",
  raised: "#1d2430",
  subtle: "#1b222c",
  "subtle-2": "#222a36",
  input: "#121821",
  border: "#2a3340",
  "border-strong": "#3a4555",
  text: "#e6eaf0",
  "text-2": "#c3cad5",
  muted: "#95a0b0",
  faint: "#6b7686",
  glass: "rgba(22, 28, 37, 0.88)",
  shadow: "rgba(0, 0, 0, 0.45)",
  "red-soft": "#3a1d22", "red-text": "#feb2b2",
  "amber-soft": "#3a2a12", "amber-text": "#fbd38d",
  "blue-soft": "#172a45", "blue-text": "#90cdf4",
  "green-soft": "#163323", "green-text": "#9ae6b4",
  "purple-soft": "#2a2245", "purple-text": "#d6bcfa",
  "pink-soft": "#3a1a2c", "pink-text": "#fbb6ce",
  "teal-soft": "#133332", "teal-text": "#81e6d9",
};

export const isHex = (v) => /^#[0-9a-f]{6}$/i.test(String(v));

export function readMode(storage) {
  try {
    const saved = storage?.getItem(MODE_KEY);
    return MODES.includes(saved) ? saved : "system";
  } catch (_) {
    return "system";
  }
}

export const resolveDark = (mode, systemDark) => (mode === "system" ? systemDark : mode === "dark");

const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
const toHex = (parts) => `#${parts.map((c) => Math.round(c).toString(16).padStart(2, "0")).join("")}`;
// Blend `hex` toward `target` by `amount` (0–1)
const mix = (hex, target, amount) => {
  const t = rgb(target);
  return toHex(rgb(hex).map((c, i) => c + (t[i] - c) * amount));
};

const luminance = (hex) => {
  const [r, g, b] = rgb(hex).map((c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export const contrast = (a, b) => {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
};

// White text on dark colors, near-black on light ones
export const inkFor = (hex) => (luminance(hex) > 0.45 ? "#1a202c" : "#ffffff");

// Lighten toward white until the color reads on dark cards
const readableOnDark = (hex, start) => {
  for (let amount = start; amount < 1; amount += 0.05) {
    const c = mix(hex, "#ffffff", amount);
    if (contrast(c, DARK_TOKENS.surface) >= 5) return c;
  }
  return "#ffffff";
};

// Darken toward black until white text on it passes WCAG AA (solid buttons)
const solidFrom = (hex, start) => {
  for (let amount = start; amount < 1; amount += 0.05) {
    const c = mix(hex, "#000000", amount);
    if (contrast("#ffffff", c) >= 4.5) return c;
  }
  return "#000000";
};

// The default light set keeps the exact greens the pages were designed with
const DEFAULT_LIGHT = {
  hover: "#38a169",
  deep: "#2f855a",
  strong: "#22543d",
  soft: "#f0fff4",
  muted: "#c6f6d5",
  border: "#9ae6b4",
  solid: "#22543d",
  "solid-hover": "#2f855a",
};

export function shadesFor(hex, dark) {
  if (!dark) {
    if (hex === DEFAULT_ACCENT) return { ...DEFAULT_LIGHT };
    return {
      hover: mix(hex, "#000000", 0.15),
      deep: mix(hex, "#000000", 0.35),
      strong: mix(hex, "#000000", 0.55),
      soft: mix(hex, "#ffffff", 0.93),
      muted: mix(hex, "#ffffff", 0.75),
      border: mix(hex, "#ffffff", 0.5),
      solid: solidFrom(hex, 0.55),
      "solid-hover": solidFrom(hex, 0.35),
    };
  }
  return {
    hover: mix(hex, "#000000", 0.15),
    deep: readableOnDark(hex, 0.25),
    strong: readableOnDark(hex, 0.45),
    soft: mix(hex, DARK_TOKENS.surface, 0.82),
    muted: mix(hex, DARK_TOKENS.surface, 0.65),
    border: mix(hex, DARK_TOKENS.surface, 0.45),
    // Button backgrounds stay dark in both modes so white text reads
    solid: solidFrom(hex, 0.35),
    "solid-hover": solidFrom(hex, 0.2),
  };
}
