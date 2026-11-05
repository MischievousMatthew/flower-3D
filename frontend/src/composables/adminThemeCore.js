// Color math for the admin theme (no Vue, so it can be tested with plain Node:
// tests/adminTheme.test.mjs). The admin pages use --a-* tokens
// (styles/admin-theme.css); this file works out the accent shades from the
// theme color the admin picks, for light and dark mode.

export const DEFAULT_ADMIN_ACCENT = "#7c6ff7";
export const ADMIN_MODES = ["system", "light", "dark"];
export const ADMIN_MODE_KEY = "bc_admin_color_mode";
export const ADMIN_ACCENT_KEY = "bc_admin_accent";

export const ADMIN_ACCENT_PRESETS = [
  { name: "Violet", value: "#7c6ff7" },
  { name: "Indigo", value: "#4f46e5" },
  { name: "Ocean", value: "#2563eb" },
  { name: "Teal", value: "#0d9488" },
  { name: "Leaf", value: "#16a34a" },
  { name: "BloomCraft", value: "#4db173" },
  { name: "Rose", value: "#e11d6b" },
  { name: "Coral", value: "#f97316" },
  { name: "Charcoal", value: "#363f4e" },
];

// The dark card color the accent has to read on
export const DARK_SURFACE = "#161c25";

export const isHex = (v) => /^#[0-9a-f]{6}$/i.test(String(v));
export const resolveDark = (mode, systemDark) => (mode === "system" ? systemDark : mode === "dark");
export const readAdminMode = (storage) => {
  try {
    const saved = storage?.getItem(ADMIN_MODE_KEY);
    return ADMIN_MODES.includes(saved) ? saved : "system";
  } catch (_) {
    return "system";
  }
};

const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
const toHex = (parts) => `#${parts.map((c) => Math.round(Math.min(255, Math.max(0, c))).toString(16).padStart(2, "0")).join("")}`;
/** Blend `hex` toward `target` by `amount` (0–1) */
export const mix = (hex, target, amount) => {
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
/** White text on dark colors, near-black on light ones */
export const inkFor = (hex) => (luminance(hex) > 0.45 ? "#1e2739" : "#ffffff");

// Walk a color toward `target` until it reaches `ratio` against `against`
const until = (hex, target, against, ratio) => {
  for (let amount = 0; amount <= 1; amount += 0.04) {
    const c = mix(hex, target, amount);
    if (contrast(c, against) >= ratio) return c;
  }
  return target;
};

// The violet the admin pages were designed with, kept exactly
const DEFAULT_LIGHT = {
  hover: "#6d5ff0",
  deep: "#5b4fc9",
  strong: "#4c3fd0",
  light: "#8f84ff",
  "light-2": "#a79ffb",
  soft: "#f5f3ff",
  "soft-2": "#ede9fe",
  muted: "#c7c2fb",
  text: "#5b4fc9",
};

/** The accent's shades: --a-accent-<name> */
export function adminShades(hex, dark) {
  const base = isHex(hex) ? hex.toLowerCase() : DEFAULT_ADMIN_ACCENT;
  if (!dark && base === DEFAULT_ADMIN_ACCENT) return { ...DEFAULT_LIGHT, ink: "#ffffff" };
  if (!dark) {
    return {
      hover: mix(base, "#000000", 0.1),
      deep: until(base, "#000000", "#ffffff", 4.5),
      strong: mix(until(base, "#000000", "#ffffff", 4.5), "#000000", 0.15),
      light: mix(base, "#ffffff", 0.18),
      "light-2": mix(base, "#ffffff", 0.32),
      soft: mix(base, "#ffffff", 0.92),
      "soft-2": mix(base, "#ffffff", 0.85),
      muted: mix(base, "#ffffff", 0.6),
      text: until(base, "#000000", "#ffffff", 4.5),
      ink: inkFor(base),
    };
  }
  // Dark: the soft fills sink into the dark cards; text lightens until it reads
  return {
    hover: mix(base, "#ffffff", 0.12),
    deep: until(base, "#ffffff", DARK_SURFACE, 4.5),
    strong: mix(base, "#000000", 0.15),
    light: mix(base, "#ffffff", 0.2),
    "light-2": mix(base, "#ffffff", 0.34),
    soft: mix(base, DARK_SURFACE, 0.82),
    "soft-2": mix(base, DARK_SURFACE, 0.72),
    muted: mix(base, DARK_SURFACE, 0.5),
    text: until(base, "#ffffff", DARK_SURFACE, 5),
    ink: inkFor(base),
  };
}
