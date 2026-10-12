import { reactive } from "vue";
import api from "../plugins/axios";

// Used until the admin-managed lists load (or if they can't be loaded)
const FALLBACK = {
  categories: [
    ["roses", "Roses"], ["tulips", "Tulips"], ["lilies", "Lilies"], ["orchids", "Orchids"],
    ["sunflowers", "Sunflowers"], ["mixed-bouquets", "Mixed Bouquets"], ["arrangements", "Arrangements"],
    ["plants", "Plants"], ["gifts", "Gifts & Add-ons"], ["seasonal", "Seasonal Flowers"],
  ].map(([value, label]) => ({ value, label, description: null })),
  flower_types: [
    ["focal", "Focal Flowers", "Main attraction"], ["secondary", "Secondary Flowers", "Support & volume"],
    ["filler", "Filler Flowers", "Small texture"], ["line", "Line Flowers", "Height & direction"],
    ["greenery", "Greenery", "Foliage"],
  ].map(([value, label, description]) => ({ value, label, description })),
  occasions: [
    "Wedding", "Birthday", "Anniversary", "Funeral", "Get Well", "Congratulations", "Sympathy",
    "Valentine", "Mother's Day", "Christmas", "Corporate", "Just Because",
  ].map((value) => ({ value, label: value, description: null })),
};

const taxonomies = reactive({ ...FALLBACK, loaded: false });
let request = null;

const load = () => {
  request ??= api
    .get("/product-taxonomies")
    .then(({ data }) => {
      const lists = data?.data ?? {};
      for (const key of ["categories", "flower_types", "occasions"]) {
        if (Array.isArray(lists[key]) && lists[key].length) taxonomies[key] = lists[key];
      }
      taxonomies.loaded = true;
    })
    .catch(() => {
      request = null; // try again next time a form opens
    });
  return request;
};

/** Keep a product's current value selectable even if it's no longer offered */
const withCurrent = (list, current) =>
  !current || list.some((item) => item.value === current) ? list : [...list, { value: current, label: current, description: null }];

/**
 * The categories, flower types and occasions vendors can choose from,
 * managed by BloomCraft admins. Loaded once per page load.
 */
export function useProductTaxonomies() {
  load();
  return { taxonomies, withCurrent, reload: () => ((request = null), load()) };
}
