import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default defineConfig(({ command }) => ({
  plugins: [vue()], // ✅ ONLY ONCE

  // The live site prints nothing to the browser console: logs there can show
  // order details, scanned badge codes or a request's sign-in token to anyone
  // who opens DevTools. `npm run dev` keeps them for debugging; real errors
  // are recorded by the server (admin → System health, BC- codes).
  esbuild: command === "build" ? { drop: ["console", "debugger"] } : {},

  server: {
    port: 5173,
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
        secure: false,
      },
      "/storage": {
        target: "http://localhost:8000",
        changeOrigin: true,
        secure: false,
      },
    },
  },

  resolve: {
    dedupe: ["three"],
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
}));
