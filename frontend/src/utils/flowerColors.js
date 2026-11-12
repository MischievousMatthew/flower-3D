/** A paint for each flower color name (swatches on the shop page) */
const SWATCHES = {
  red: "#c8102e", pink: "#f4a6c0", white: "#ffffff", cream: "#f5ecd7", ivory: "#f7f3e8", yellow: "#f5c518",
  orange: "#f08a24", peach: "#f7b997", coral: "#f27b6b", purple: "#7d4bb3", lavender: "#c3a8e3", lilac: "#c8a2c8",
  blue: "#4a7fd0", green: "#5aa469", burgundy: "#7a1f3d", magenta: "#c2185b",
  mixed: "conic-gradient(#c8102e, #f5c518, #5aa469, #4a7fd0, #7d4bb3, #c8102e)",
};

export function flowerSwatch(name) {
  const key = String(name ?? "").toLowerCase().trim();
  if (SWATCHES[key]) return SWATCHES[key];
  if (typeof CSS !== "undefined" && CSS.supports?.("color", key)) return key;
  return SWATCHES.mixed;
}
