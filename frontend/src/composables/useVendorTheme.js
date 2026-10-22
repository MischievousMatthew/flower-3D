// The vendor's appearance: theme color and dark mode. Both are saved in this
// browser and apply to the vendor pages only.
//
// Theme color: the --bc-accent* variables on <html> color the sidebar tabs and
// every button, tab, toggle and highlight. Status colors such as "paid" or
// "in stock" stay green on purpose.
// Dark mode: data-vendor-theme="dark" on <html> switches the --v-* tokens in
// styles/vendor-theme.css. "system" follows the device's setting.
import { computed, ref, watchEffect } from "vue";
import {
  ACCENT_KEY,
  ACCENT_PRESETS,
  DEFAULT_ACCENT,
  MODE_KEY,
  inkFor,
  isHex,
  readMode,
  resolveDark,
  shadesFor,
} from "./vendorThemeCore";
import { erpIsDark } from "./useErpTheme";

export { ACCENT_PRESETS, DEFAULT_ACCENT };

const safeStorage = () => {
  try {
    return window.localStorage;
  } catch (_) {
    return null;
  }
};

const save = (key, value) => {
  try {
    localStorage.setItem(key, value);
  } catch (_) {
    // Storage unavailable: the choice still applies until the page reloads
  }
};

const readAccent = () => {
  try {
    const saved = localStorage.getItem(ACCENT_KEY);
    return isHex(saved) ? saved.toLowerCase() : DEFAULT_ACCENT;
  } catch (_) {
    return DEFAULT_ACCENT;
  }
};

const accent = ref(readAccent());
const mode = ref(readMode(safeStorage()));

const media = typeof window !== "undefined" && window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
const systemDark = ref(media?.matches ?? false);
media?.addEventListener?.("change", (e) => (systemDark.value = e.matches));

const isDark = computed(() => resolveDark(mode.value, systemDark.value));
const matchDevice = computed(() => mode.value === "system");

/** CSS variables the sidebar uses for its tabs */
const accentVars = computed(() => {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(accent.value.slice(i, i + 2), 16));
  return {
    "--sb-accent": accent.value,
    "--sb-accent-ink": inkFor(accent.value),
    "--sb-accent-soft": `rgba(${r}, ${g}, ${b}, ${isDark.value ? 0.18 : 0.1})`,
    "--sb-accent-glow": `rgba(${r}, ${g}, ${b}, 0.32)`,
  };
});

function setAccent(value) {
  if (!isHex(value)) return;
  accent.value = value.toLowerCase();
  save(ACCENT_KEY, accent.value);
}

function setMode(value) {
  mode.value = value;
  save(MODE_KEY, value);
}

/** Flipping the switch is an explicit choice, so it stops following the device */
const setDark = (on) => setMode(on ? "dark" : "light");
const setMatchDevice = (on) => setMode(on ? "system" : isDark.value ? "dark" : "light");

const signedInAsVendor = () => {
  try {
    return JSON.parse(localStorage.getItem("user") || "null")?.role === "vendor";
  } catch (_) {
    return false;
  }
};

/**
 * Keeps <html> in step with the theme: accent variables everywhere, the dark
 * marker only on vendor pages and ERP pages marked meta.erpTheme (with the
 * ERP's own choice, see useErpTheme), so every other page stays light.
 */
export function applyVendorTheme(router) {
  // URLs are opaque tokens, so go by the route's vendor flag, not the path.
  // Until the first navigation resolves (it waits on the API), assume a
  // signed-in vendor is headed to a vendor page so a refresh doesn't flash white.
  const onVendorPage = ref(signedInAsVendor());
  const onErpThemedPage = ref(false);
  router.afterEach((to) => {
    onVendorPage.value = to.meta?.requiresVendor === true;
    onErpThemedPage.value = to.matched.some((r) => r.meta?.erpTheme === true);
  });

  watchEffect(() => {
    const root = document.documentElement;
    const dark = (isDark.value && onVendorPage.value) || (erpIsDark.value && onErpThemedPage.value);
    const shades = shadesFor(accent.value, dark);
    root.style.setProperty("--bc-accent", accent.value);
    root.style.setProperty("--bc-accent-ink", inkFor(accent.value));
    for (const [name, value] of Object.entries(shades)) root.style.setProperty(`--bc-accent-${name}`, value);
    if (dark) root.setAttribute("data-vendor-theme", "dark");
    else root.removeAttribute("data-vendor-theme");
  });
}

export const useVendorTheme = () => ({
  accent,
  accentVars,
  setAccent,
  resetAccent: () => setAccent(DEFAULT_ACCENT),
  mode,
  isDark,
  matchDevice,
  setDark,
  setMatchDevice,
});
