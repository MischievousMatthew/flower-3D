// src/composables/usePrice.js

export function usePrice() {
  const effectivePrice = (item) => {
    if (item.product?.discount_price)
      return parseFloat(item.product.discount_price);
    if (item.product?.selling_price)
      return parseFloat(item.product.selling_price);
    return parseFloat(item.unit_price || item.price || 0);
  };

  const originalPrice = (item) => {
    return parseFloat(
      item.original_price ||
        item.product?.selling_price ||
        item.unit_price ||
        item.price ||
        0,
    );
  };

  const hasDiscount = (item) => {
    const disc = parseFloat(
      item.discount_price || item.product?.discount_price || 0,
    );
    const orig = originalPrice(item);
    return disc > 0 && disc < orig;
  };

  const discountPercent = (item) => {
    if (!hasDiscount(item)) return 0;
    const orig = originalPrice(item);
    const disc = parseFloat(
      item.discount_price || item.product?.discount_price,
    );
    return Math.round(((orig - disc) / orig) * 100);
  };

  const lineTotal = (item) => {
    return effectivePrice(item) * (item.quantity || 1);
  };

  const lineSaving = (item) => {
    if (!hasDiscount(item)) return 0;
    return (originalPrice(item) - effectivePrice(item)) * (item.quantity || 1);
  };

  const formatPrice = (price) => {
    return parseFloat(price || 0).toFixed(2);
  };

  return {
    effectivePrice,
    originalPrice,
    hasDiscount,
    discountPercent,
    lineTotal,
    lineSaving,
    formatPrice,
  };
}
