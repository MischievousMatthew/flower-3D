import { reactive } from "vue";
import api from "../plugins/axios";

/**
 * Website content managed in Admin → Website (banners, announcements,
 * promotions, journal teasers, categories/occasions, contact details).
 * Loaded once per page load and shared by the shop, landing page and journal.
 */
const site = reactive({
  loaded: false,
  failed: false,
  banners: [],
  announcements: [],
  promotions: [],
  articles: [],
  categories: [],
  occasions: [],
  settings: {},
});

let request = null;

const load = () => {
  request ??= api
    .get("/site/home")
    .then(({ data }) => {
      Object.assign(site, data?.data ?? {}, { loaded: true, failed: false });
    })
    .catch(() => {
      site.failed = true;
      site.loaded = true;
      request = null;
    });
  return request;
};

const isExternal = (value) => /^https?:\/\//i.test(value || "");

/**
 * Where a banner / announcement / promotion button goes, as something
 * router.push understands (or an external address).
 */
const linkTarget = (item) => {
  const value = item?.link_value ?? "";
  switch (item?.link_type) {
    case "category":
      return { name: "Shop", query: { category: value } };
    case "occasion":
      return { name: "Shop", query: { occasion: value } };
    case "store":
      return { name: "VendorStorefront", state: { storeId: String(value) } };
    case "article":
      return { path: `/journal/${encodeURIComponent(value)}` };
    case "url":
      return isExternal(value) ? value : { path: value || "/shop" };
    case "products":
      return { name: "Shop", query: { section: "products" } };
    default:
      return null;
  }
};

/** Follow a content link (opens external addresses in a new tab) */
const followLink = (router, item) => {
  const target = linkTarget(item);
  if (!target) return;
  if (typeof target === "string") {
    window.open(target, "_blank", "noopener");
    return;
  }
  router.push(target);
};

export function useSiteContent() {
  load();
  return { site, linkTarget, followLink, reload: () => ((request = null), load()) };
}
