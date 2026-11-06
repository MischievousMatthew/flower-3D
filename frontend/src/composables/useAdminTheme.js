// The admin panel's appearance: a theme color and light / dark / match device.
// Both are saved in this browser. Dark mode applies on admin pages only
// (html[data-admin-theme="dark"] switches the --a-* tokens in
// styles/admin-theme.css); the accent shades are set on <html> as --a-accent*.
import { computed, ref, watchEffect } from "vue";
import {
  ADMIN_ACCENT_KEY,
  ADMIN_ACCENT_PRESETS,
  ADMIN_MODE_KEY,
  DEFAULT_ADMIN_ACCENT,
  adminShades,
  isHex,
  readAdminMode,
  resolveDark,
} from "./adminThemeCore";

export { ADMIN_ACCENT_PRESETS, DEFAULT_ADMIN_ACCENT };

const storage = () => {
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
    const saved = localStorage.getItem(ADMIN_ACCENT_KEY);
    return isHex(saved) ? saved.toLowerCase() : DEFAULT_ADMIN_ACCENT;
  } catch (_) {
    return DEFAULT_ADMIN_ACCENT;
  }
};

const accent = ref(readAccent());
const mode = ref(readAdminMode(storage()));
const media = typeof window !== "undefined" && window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
const systemDark = ref(media?.matches ?? false);
media?.addEventListener?.("change", (e) => (systemDark.value = e.matches));
const isDark = computed(() => resolveDark(mode.value, systemDark.value));

function setAccent(value) {
  if (!isHex(value)) return;
  accent.value = value.toLowerCase();
  save(ADMIN_ACCENT_KEY, accent.value);
}
function setMode(value) {
  if (!["system", "light", "dark"].includes(value)) return;
  mode.value = value;
  save(ADMIN_MODE_KEY, value);
}

const signedInAsAdmin = () => {
  try {
    return ["admin", "super_admin"].includes(JSON.parse(localStorage.getItem("user") || "null")?.role);
  } catch (_) {
    return false;
  }
};

/** Keeps <html> in step with the admin theme while an admin page is open */
export function applyAdminTheme(router) {
  // URLs are opaque tokens, so go by the route's admin flag. Until the first
  // navigation resolves, assume a signed-in admin is headed to an admin page
  // so a refresh doesn't flash white.
  const onAdminPage = ref(signedInAsAdmin());
  router.afterEach((to) => {
    // adminTheme: the hidden admin sign-in page, which wears the panel's colors too
    onAdminPage.value = to.matched.some((r) => r.meta?.requiresAdmin === true || r.meta?.adminTheme === true);
  });

  watchEffect(() => {
    const root = document.documentElement;
    const dark = isDark.value && onAdminPage.value;
    const shades = adminShades(accent.value, dark);
    root.style.setProperty("--a-accent", accent.value);
    for (const [name, value] of Object.entries(shades)) root.style.setProperty(`--a-accent-${name}`, value);
    if (dark) root.setAttribute("data-admin-theme", "dark");
    else root.removeAttribute("data-admin-theme");
  });
}

export const useAdminTheme = () => ({
  accent,
  setAccent,
  resetAccent: () => setAccent(DEFAULT_ADMIN_ACCENT),
  mode,
  setMode,
  isDark,
});
