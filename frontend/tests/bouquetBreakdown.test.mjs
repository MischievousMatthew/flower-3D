import test from "node:test";
import assert from "node:assert/strict";
import { bouquetBreakdowns } from "../src/components/vendor/orders/orderUi.js";

const tulip = (n) => ({ scene_id: `f${n}`, product_id: 7, product_name: "Tulips", price: 150 });
const bouquet = (card = null, flowers = [1, 2, 3].map(tulip)) => ({
  type: "custom_flower_bouquet",
  preset: { key: "graduation", size: flowers.length },
  design: "classic",
  wrapper: { id: 1, name: "Lilac", color_hex: "#ccccff", price: 0 },
  inner_wrapper: { id: 2, name: "Pink", color_hex: "#ffccff", price: 0 },
  ribbon: { id: 3, name: "Red", color_hex: "#ff0000", price: 0 },
  inner_wrapper_pattern: { type: "words", text: "Love" },
  fillers: [{ id: 9, name: "Eucalyptus", price: 0 }, { id: 9, name: "Eucalyptus", price: 0 }],
  card,
  // The occasion's ₱40 base price plus the card
  extras_total: 40 + (card?.price || 0),
  flowers,
});

test("one item per flower: flowers × price, then the base price and card, adding up to the total", () => {
  const card = { id: 5, name: "Kraft card", size: "Small", price: 20, message: "Congrats, grad!" };
  // Checkout adds the base price and card (₱60) to the first flower
  const items = [210, 150, 150].map((price, i) => ({ id: i + 1, product_id: 7, image: "t.jpg", quantity: 1, unit_price: price, subtotal: price, customizations: bouquet(card) }));
  const [b] = bouquetBreakdowns(items);

  assert.equal(b.total, 510);
  assert.deepEqual([b.flowers[0].count, b.flowers[0].unit_price, b.flowers[0].subtotal], [3, 150, 450]);
  assert.equal(b.base, 40);
  assert.deepEqual(b.card, { name: "Kraft card", size: "Small", price: 20, message: "Congrats, grad!" });
  assert.deepEqual(b.parts.map((p) => p.label), ["Wrapper", "Inner wrapper", "Ribbon"]);
  assert.equal(b.parts[1].pattern, "Words: “Love”");
  assert.deepEqual(b.fillers, [{ name: "Eucalyptus", count: 2, price: 0 }]);
  assert.deepEqual(b.item_ids, [1, 2, 3]);
});

test("one item carrying the whole bouquet (added from the cart)", () => {
  const [b] = bouquetBreakdowns([{ id: 9, product_id: 7, quantity: 1, unit_price: 490, subtotal: 490, customizations: bouquet() }]);
  assert.deepEqual([b.total, b.flowers[0].subtotal, b.base, b.card], [490, 450, 40, null]);
});

test("different flowers are priced by kind", () => {
  const custom = bouquet(null, [tulip(1), { scene_id: "r", product_id: 8, product_name: "Roses", price: 100 }]);
  const [b] = bouquetBreakdowns([
    { id: 1, product_id: 7, quantity: 1, subtotal: 190, customizations: custom },
    { id: 2, product_id: 8, quantity: 1, subtotal: 100, customizations: custom },
  ]);
  assert.deepEqual(b.flowers.map((f) => [f.name, f.subtotal]), [["Tulips", 150], ["Roses", 100]]);
  assert.equal(b.base, 40);
});

test("ordinary items are not bouquets", () => {
  assert.deepEqual(bouquetBreakdowns([{ id: 1, product_id: 3, subtotal: 300, customizations: {} }]), []);
});
