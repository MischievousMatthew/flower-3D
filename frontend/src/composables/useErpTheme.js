// The ERP's light/dark choice, saved in this browser. It is separate from the
// vendor's choice and starts light. It only darkens ERP pages whose route has
// meta.erpTheme, the pages already built on the --v-* tokens; applyVendorTheme
// (useVendorTheme.js) sets the marker on <html>.
import { computed, ref } from "vue";
import { MODES, resolveDark } from "./vendorThemeCore";

export const ERP_MODE_KEY = "bc_erp_color_mode";

const readErpMode = () => {
  try {
    const saved = localStorage.getItem(ERP_MODE_KEY);
    return MODES.includes(saved) ? saved : "light";
  } catch (_) {
    return "light";
  }
};

const mode = ref(readErpMode());
const media = typeof window !== "undefined" && window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
const systemDark = ref(media?.matches ?? false);
media?.addEventListener?.("change", (e) => (systemDark.value = e.matches));

export const erpIsDark = computed(() => resolveDark(mode.value, systemDark.value));

function setMode(value) {
  if (!MODES.includes(value)) return;
  mode.value = value;
  try {
    localStorage.setItem(ERP_MODE_KEY, value);
  } catch (_) {
    // Storage unavailable: the choice still applies until the page reloads
  }
}

export const useErpTheme = () => ({ mode, isDark: erpIsDark, setMode });
