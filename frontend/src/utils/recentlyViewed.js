// Products this browser opened lately, newest first, for "Recently viewed"
// on the product page. Kept in localStorage (a per-viewer convenience): it
// may be empty in a private window or if storage is blocked, which is fine.

const KEY = "bc_recently_viewed";
const MAX = 12;

/** Just what a product tile needs to show it */
function slim(p) {
  const image = p.primary_image?.image_url || p.images?.[0]?.image_url || null;
  return {
    id: p.id,
    product_name: p.product_name,
    selling_price: Number(p.selling_price || 0),
    discount_price: p.discount_price != null ? Number(p.discount_price) : null,
    has_discount: !!p.has_discount,
    quantity_in_stock: Number(p.quantity_in_stock || 0),
    average_rating: p.average_rating ?? null,
    total_reviews: Number(p.total_reviews || 0),
    primary_image: image ? { image_url: image } : null,
  };
}

export function recentlyViewed() {
  try {
    const list = JSON.parse(localStorage.getItem(KEY) || "[]");
    return Array.isArray(list) ? list.filter((p) => p && p.id) : [];
  } catch {
    return [];
  }
}

/** Puts this product at the front of the list */
export function rememberViewed(product) {
  if (!product?.id) return;
  try {
    const list = recentlyViewed().filter((p) => String(p.id) !== String(product.id));
    localStorage.setItem(KEY, JSON.stringify([slim(product), ...list].slice(0, MAX)));
  } catch {
    // Storage blocked or full: the list just isn't kept
  }
}
