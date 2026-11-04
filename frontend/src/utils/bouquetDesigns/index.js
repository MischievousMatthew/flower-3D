// Bouquet base designs, one per occasion. Used by the customer's customizer
// and by the 3D viewer (orders, cart), so a saved bouquet rebuilds exactly.
//
// createBouquetBase(design, { loadGltf, wrapperScale }) returns
//   root      the base, standing on the floor, centred, wrapper size applied
//   dressing  { outer, inner: { apply(hex|null) }, applyRibbon(hex|null), wrapMeshes }
//   targets   meshes flowers can be placed on (the flower bed first)
//   defaults  the design's own colours
import { buildClassic } from "./classic.js";
import { MODEL_BASES, buildFromModel, buildFromUpload } from "./modelBases.js";

export const DESIGNS = {
  classic: { label: "Classic cone" },
  korean: { label: "Korean layered wrap" },
  hand_tied: { label: "Round hand-tied" },
  basket: { label: "Basket" },
};

export const DESIGN_FOR_OCCASION = {
  wedding: "hand_tied",
  romance: "korean",
  graduation: "classic",
  sympathy: "basket",
  just_because: "classic",
};

// Bouquets saved before designs existed keep the original model, fillers and
// all ("classic_full"). It's never offered for new bouquets.
export const LEGACY_DESIGN = "classic_full";

export const designForOccasion = (occasion) => DESIGN_FOR_OCCASION[occasion] ?? "classic";

/** A shop's own 3D wrapper as a style ("model_<id>", see BouquetPresets::MODEL_DESIGN) */
export const isModelDesign = (design) => typeof design === "string" && /^model_\d{1,10}$/.test(design);

/**
 * Wrapper size grows the whole base about the floor centre; placed flowers
 * are moved by the caller with the same factor.
 */
export function applyWrapperScale(root, factor) {
  root.scale.multiplyScalar(factor);
  root.position.multiplyScalar(factor);
}

// modelUrl: where a shop's own 3D wrapper is (only for "model_<id>" styles).
// If it can't be loaded the bouquet falls back to the classic cone.
export async function createBouquetBase(design, { loadGltf, wrapperScale = 1, modelUrl = null } = {}) {
  let key = design === LEGACY_DESIGN || DESIGNS[design] || (isModelDesign(design) && modelUrl) ? design : "classic";
  let base = null;
  if (isModelDesign(key)) {
    try {
      base = await buildFromUpload(modelUrl, loadGltf);
    } catch (error) {
      console.error("The shop's 3D wrapper couldn't load:", error);
      key = "classic";
    }
  }
  base ??=
    key === LEGACY_DESIGN
      ? await buildClassic(loadGltf, { withFillers: true })
      : key === "classic"
        ? await buildClassic(loadGltf)
        : await buildFromModel(key, loadGltf);
  base.design = key;
  if (wrapperScale !== 1) applyWrapperScale(base.root, wrapperScale);
  return base;
}
