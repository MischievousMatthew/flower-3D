import { createApp } from "vue";
import "./style.css";
import App from "./App.vue";
import router from "./router/index.js";
import "vue3-toastify/dist/index.css";
import { configurePhilippineDateTimeDefaults } from "./utils/philippineDateTime";
import { applyVendorTheme } from "./composables/useSidebarTheme";

configurePhilippineDateTimeDefaults();
// The vendor's theme color, set before the first page renders
applyVendorTheme();

// After a new deploy, an open tab still runs the old code and its page files
// no longer exist. Reload to pick up the new version — at most once every
// 10 seconds, so a file that is truly missing can't cause a reload loop.
window.addEventListener("vite:preloadError", (event) => {
  event.preventDefault();
  try {
    const last = Number(sessionStorage.getItem("reloaded_for_new_version") || 0);
    if (Date.now() - last < 10_000) return;
    sessionStorage.setItem("reloaded_for_new_version", String(Date.now()));
  } catch {
    // storage unavailable; reload anyway
  }
  window.location.reload();
});

const app = createApp(App);

app.use(router);

app.mount("#app");
