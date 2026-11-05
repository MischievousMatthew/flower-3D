import test from "node:test";
import assert from "node:assert/strict";
import { ADMIN_ACCENT_PRESETS, DARK_SURFACE, DEFAULT_ADMIN_ACCENT, adminShades, contrast, readAdminMode, resolveDark } from "../src/composables/adminThemeCore.js";

test("the default violet keeps the shades the admin pages were designed with", () => {
  const s = adminShades(DEFAULT_ADMIN_ACCENT, false);
  assert.equal(s.deep, "#5b4fc9");
  assert.equal(s.soft, "#f5f3ff");
  assert.equal(s.muted, "#c7c2fb");
});

test("accent text reads on white in light mode and on dark cards in dark mode", () => {
  for (const { value } of ADMIN_ACCENT_PRESETS) {
    assert.ok(contrast(adminShades(value, false).text, "#ffffff") >= 4.5, `${value} light`);
    assert.ok(contrast(adminShades(value, true).text, DARK_SURFACE) >= 4.5, `${value} dark`);
  }
  // A very light custom color still gets readable text
  assert.ok(contrast(adminShades("#fde68a", false).text, "#ffffff") >= 4.5);
  assert.ok(contrast(adminShades("#1e1b4b", true).text, DARK_SURFACE) >= 4.5);
});

test("text on accent buttons is dark on light accents and white on dark ones", () => {
  assert.equal(adminShades("#fde68a", false).ink, "#1e2739");
  assert.equal(adminShades("#4f46e5", false).ink, "#ffffff");
});

test("mode: saved choice or follow the device", () => {
  assert.equal(readAdminMode({ getItem: () => "dark" }), "dark");
  assert.equal(readAdminMode({ getItem: () => "nonsense" }), "system");
  assert.equal(resolveDark("system", true), true);
  assert.equal(resolveDark("light", true), false);
});
