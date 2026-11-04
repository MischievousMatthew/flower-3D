// The product a customer just clicked (from a tile), kept for a moment so its
// page can show the name, price and photo straight away while the full
// details load. In memory only: a reload starts fresh.

const previews = new Map();
const MAX = 30;

export function rememberProduct(product) {
  if (!product?.id) return;
  previews.delete(String(product.id));
  previews.set(String(product.id), product);
  if (previews.size > MAX) previews.delete(previews.keys().next().value);
}

/** The remembered product for this id, or null */
export function productPreview(id) {
  return previews.get(String(id)) || null;
}
