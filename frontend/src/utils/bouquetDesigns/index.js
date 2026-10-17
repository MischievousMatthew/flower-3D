// Bouquet base designs, one per occasion. Used by the customer's customizer
// and by the 3D viewer (orders, cart), so a saved bouquet rebuilds exactly.
//
// createBouquetBase(design, { loadGltf, wrapperScale }) returns
//   root      the base, standing on the floor, centred, wrapper size applied
//   dressing  { outer, inner: { apply(hex|null) }, applyRibbon(hex|null), wrapMeshes }
//   targets   meshes flowers can be placed on (the flower bed first)
//   defaults  the design's own colours
import { buildClassic } from "./classic.js";
import { buildBasket, buildBox, buildHandTied, buildKorean } from "./generated.js";

export const DESIGNS = {
  classic: { label: "Classic cone" },
  korean: { label: "Korean layered wrap" },
  hand_tied: { label: "Round hand-tied" },
  box: { label: "Flower box" },
  basket: { label: "Basket" },
};

export const DESIGN_FOR_OCCASION = {
  wedding: "hand_tied",
  romance: "korean",
  birthday: "box",
  graduation: "classic",
  sympathy: "basket",
  just_because: "classic",
};

// Bouquets saved before designs existed keep the original model, fillers and
// all ("classic_full"). It's never offered for new bouquets.
export const LEGACY_DESIGN = "classic_full";

export const designForOccasion = (occasion) => DESIGN_FOR_OCCASION[occasion] ?? "classic";

const BUILDERS = { korean: buildKorean, hand_tied: buildHandTied, box: buildBox, basket: buildBasket };

/**
 * Wrapper size grows the whole base about the floor centre; placed flowers
 * are moved by the caller with the same factor.
 */
export function applyWrapperScale(root, factor) {
  root.scale.multiplyScalar(factor);
  root.position.multiplyScalar(factor);
}

export async function createBouquetBase(design, { loadGltf, wrapperScale = 1 } = {}) {
  const key = design === LEGACY_DESIGN || DESIGNS[design] ? design : "classic";
  const base =
    key === LEGACY_DESIGN
      ? await buildClassic(loadGltf, { withFillers: true })
      : key === "classic"
        ? await buildClassic(loadGltf)
        : BUILDERS[key]();
  base.design = key;
  if (wrapperScale !== 1) applyWrapperScale(base.root, wrapperScale);
  return base;
}
