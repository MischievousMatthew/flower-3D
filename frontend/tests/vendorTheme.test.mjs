// Run with: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import {
  ACCENT_PRESETS,
  DARK_TOKENS,
  HUES,
  LIGHT_TOKENS,
  contrast,
  readMode,
  resolveDark,
  shadesFor,
} from "../src/composables/vendorThemeCore.js";

const storage = (value) => ({ getItem: () => value });

test("readMode falls back to system for missing, junk or blocked storage", () => {
  assert.equal(readMode(storage(null)), "system");
  assert.equal(readMode(storage("junk")), "system");
  assert.equal(readMode({ getItem: () => { throw new Error("blocked"); } }), "system");
  assert.equal(readMode(storage("dark")), "dark");
  assert.equal(readMode(storage("light")), "light");
});

test("resolveDark follows the device only in system mode", () => {
  assert.equal(resolveDark("system", true), true);
  assert.equal(resolveDark("system", false), false);
  assert.equal(resolveDark("light", true), false);
  assert.equal(resolveDark("dark", false), true);
});

test("the default light shades are the original greens", () => {
  assert.deepEqual(shadesFor("#48bb78", false), {
    hover: "#38a169",
    deep: "#2f855a",
    strong: "#22543d",
    soft: "#f0fff4",
    muted: "#c6f6d5",
    border: "#9ae6b4",
    solid: "#22543d",
    "solid-hover": "#2f855a",
  });
});

test("accent text shades stay readable on dark cards", () => {
  for (const hex of [...ACCENT_PRESETS.map((p) => p.value), "#f6d743"]) {
    const { deep, strong } = shadesFor(hex, true);
    assert.ok(contrast(deep, DARK_TOKENS.surface) >= 4.5, `${hex} deep ${deep}`);
    assert.ok(contrast(strong, DARK_TOKENS.surface) >= 4.5, `${hex} strong ${strong}`);
    assert.ok(contrast(strong, shadesFor(hex, true).soft) >= 4.5, `${hex} strong on soft`);
  }
});

test("dark text tokens pass WCAG AA on every dark background", () => {
  for (const text of ["text", "text-2", "muted"]) {
    for (const bg of ["surface", "bg", "raised", "subtle", "input"]) {
      const ratio = contrast(DARK_TOKENS[text], DARK_TOKENS[bg]);
      assert.ok(ratio >= 4.5, `${text} on ${bg}: ${ratio.toFixed(2)}`);
    }
  }
});

test("dark status text passes WCAG AA on its soft background and on cards", () => {
  for (const hue of HUES) {
    const fg = DARK_TOKENS[`${hue}-text`];
    for (const bg of [DARK_TOKENS[`${hue}-soft`], DARK_TOKENS.surface]) {
      const ratio = contrast(fg, bg);
      assert.ok(ratio >= 4.5, `${hue} ${fg} on ${bg}: ${ratio.toFixed(2)}`);
    }
  }
});

test("vendor-theme.css is in sync with the token values", async () => {
  const fs = await import("node:fs");
  const css = fs.readFileSync(new URL("../src/styles/vendor-theme.css", import.meta.url), "utf8");
  for (const tokens of [LIGHT_TOKENS, DARK_TOKENS]) {
    for (const [name, value] of Object.entries(tokens)) {
      assert.ok(css.includes(`--v-${name}: ${value};`), `--v-${name}: ${value} (run node scripts/build-vendor-theme-css.mjs)`);
    }
  }
});

test("solid accent buttons keep white text readable in both modes", () => {
  for (const hex of [...ACCENT_PRESETS.map((p) => p.value), "#f6d743"]) {
    for (const dark of [false, true]) {
      const { solid, "solid-hover": solidHover } = shadesFor(hex, dark);
      assert.ok(contrast("#ffffff", solid) >= 4.5, `${hex} dark=${dark} solid ${solid}`);
      assert.ok(contrast("#ffffff", solidHover) >= 4.5, `${hex} dark=${dark} solid-hover ${solidHover}`);
    }
  }
});
