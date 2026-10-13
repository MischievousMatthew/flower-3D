import { ref } from "vue";
import api from "../../../plugins/axios";
import { useProductTaxonomies } from "../../../composables/useProductTaxonomies";

// Shared between the Products, Inventory, Categories and Promotions pages so
// switching tabs shows the last list straight away while it refreshes.
const products = ref(null);
const reasons = ref([]);
const error = ref("");
let inflight = null;

export function useVendorCatalog() {
  const { taxonomies } = useProductTaxonomies();

  const load = () => {
    inflight ??= api
      .get("vendor/catalog/products")
      .then(({ data }) => {
        products.value = data.data ?? [];
        reasons.value = data.reasons ?? [];
        error.value = "";
      })
      .catch((e) => {
        error.value = e?.response?.data?.message || "Couldn't load your products.";
      })
      .finally(() => {
        inflight = null;
      });
    return inflight;
  };

  /** Put a product returned by the API back into the list. */
  const replace = (product) => {
    if (!products.value) return;
    const i = products.value.findIndex((p) => p.id === product.id);
    if (i >= 0) products.value.splice(i, 1, product);
    else products.value.unshift(product);
  };
  const remove = (id) => {
    if (products.value) products.value = products.value.filter((p) => p.id !== id);
  };

  const categoryLabel = (value) =>
    taxonomies.categories.find((c) => c.value === value)?.label ?? (value ? titleCase(value) : "Uncategorised");

  return { products, reasons, error, load, replace, remove, taxonomies, categoryLabel };
}

const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
export const money = (v) => peso.format(Number(v || 0));
export const titleCase = (s) => String(s ?? "").replace(/[_-]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
export const formatDate = (iso, opts = { month: "short", day: "numeric", year: "numeric" }) =>
  iso ? new Date(iso).toLocaleString("en-PH", opts) : "";
export const apiMessage = (e, fallback) => {
  const errors = e?.response?.data?.errors;
  if (errors) {
    const first = Object.values(errors).flat()[0];
    if (first) return first;
  }
  return e?.response?.data?.message || fallback;
};
