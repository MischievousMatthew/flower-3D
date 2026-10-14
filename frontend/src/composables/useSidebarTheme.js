// The vendor's chosen color for the sidebar tabs (active and hover states).
// Saved in this browser; every page's sidebar shares the same ref.
import { computed, ref } from "vue";

const STORAGE_KEY = "bc_vendor_sidebar_accent";
export const DEFAULT_ACCENT = "#48bb78";

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

const isHex = (v) => /^#[0-9a-f]{6}$/i.test(String(v));

const read = () => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    return isHex(saved) ? saved.toLowerCase() : DEFAULT_ACCENT;
  } catch (_) {
    return DEFAULT_ACCENT;
  }
};

const accent = ref(read());

const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));

// White text on dark colors, near-black on light ones (WCAG relative luminance)
const inkFor = (hex) => {
  const [r, g, b] = rgb(hex).map((c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.45 ? "#1a202c" : "#ffffff";
};

/** CSS variables the sidebar uses for its tabs */
const accentVars = computed(() => {
  const [r, g, b] = rgb(accent.value);
  return {
    "--sb-accent": accent.value,
    "--sb-accent-ink": inkFor(accent.value),
    "--sb-accent-soft": `rgba(${r}, ${g}, ${b}, 0.1)`,
    "--sb-accent-glow": `rgba(${r}, ${g}, ${b}, 0.32)`,
  };
});

function setAccent(value) {
  if (!isHex(value)) return;
  accent.value = value.toLowerCase();
  try {
    localStorage.setItem(STORAGE_KEY, accent.value);
  } catch (_) {
    // Storage unavailable: the color still applies until the page reloads
  }
}

export const useSidebarTheme = () => ({
  accent,
  accentVars,
  setAccent,
  resetAccent: () => setAccent(DEFAULT_ACCENT),
});
