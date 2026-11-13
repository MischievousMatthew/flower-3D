// The flowers in a bouquet ([{ flower, color, count, cost }]), shared by the
// vendor and ERP product forms. cost is what the shop paid for one piece (not
// its selling price; never shown to customers). Sent as `composition`; the
// server keeps it only for bouquets and procurement adds it up to know how many
// stems to buy and what they'll cost.

const hasCost = (r) => r?.cost !== "" && r?.cost !== null && r?.cost !== undefined;
const filled = (r) =>
  String(r?.flower || "").trim() || String(r?.color || "").trim() || hasCost(r) || (r?.count !== "" && r?.count != null && r?.count !== 1);

/** Rows that take from the shop's materials instead of a flower: a wrapper's sheets, how many of a filler or ribbon */
export const MATERIAL_KINDS = ["wrapper", "filler", "ribbon"];

/** The rows of an existing product, for its edit form */
export const compositionFromProduct = (p) =>
  (Array.isArray(p?.composition) ? p.composition : []).map((f) => ({
    flower: f.flower || "",
    color: f.color || "",
    count: Number(f.count) || 1,
    cost: f.cost ?? "",
    product_id: f.product_id ?? null, // linked single flower (shares its stock)
    ...(MATERIAL_KINDS.includes(f.kind) ? { kind: f.kind } : {}), // sheets of a wrapper, or bouquets' worth of a filler
  }));

/** How many of a linked flower a row can use: its colour's own stock when it counts colours */
export const flowerStockFor = (flower, color) => {
  if (!flower) return 0;
  const c = (flower.colors || []).find((x) => String(x.name).toLowerCase() === String(color || "").trim().toLowerCase());
  return c ? Number(c.stock) || 0 : Number(flower.stock) || 0;
};

/**
 * The bouquets the linked flowers make (the lowest stock ÷ pieces), or null
 * when no row is linked. `flowers` is the linkable-flowers list.
 */
export function stockFromLinks(rows, flowers) {
  let make = null;
  for (const r of rows || []) {
    if (!r.product_id || !(Number(r.count) > 0)) continue;
    const flower = (flowers || []).find((f) => f.id === Number(r.product_id));
    if (!flower) continue;
    const can = Math.floor(flowerStockFor(flower, r.color) / Number(r.count));
    make = make === null ? can : Math.min(make, can);
  }
  return make;
}

export const hasLinks = (rows) => (rows || []).some((r) => r.product_id);

/** What one row's flowers cost to buy (null when it has no cost) */
export const rowCost = (r) => (hasCost(r) && Number(r.count) > 0 ? Number(r.cost) * Number(r.count) : null);

/** What all the flowers in one bouquet cost to buy (only rows with a cost) */
export const flowersCost = (rows) => (rows || []).reduce((sum, r) => sum + (rowCost(r) ?? 0), 0);

/** The JSON the forms send (empty rows left out; [] clears it) */
export const compositionField = (rows) =>
  JSON.stringify(
    (rows || [])
      .filter((r) => String(r.flower || "").trim())
      .map((r) => ({
        flower: String(r.flower).trim(),
        color: String(r.color || "").trim() || null,
        count: Math.floor(Number(r.count)),
        cost: hasCost(r) ? Math.round(Number(r.cost) * 100) / 100 : null,
        product_id: r.product_id ? Number(r.product_id) : null,
        ...(MATERIAL_KINDS.includes(r.kind) ? { kind: r.kind } : {}),
      })),
  );

/** A form error for the rows, or "" when they're fine */
export function compositionProblem(rows) {
  for (const r of rows || []) {
    if (r.kind === "wrapper") {
      if (!r.product_id) return "Pick which wrapper the bouquet uses, or remove that row.";
      const n = Number(r.count);
      if (!Number.isInteger(n) || n < 1 || n > 1000) return `Enter how many sheets of ${r.flower || "the wrapper"} one bouquet uses (1 or more).`;
      continue;
    }
    if (r.kind === "filler" || r.kind === "ribbon") {
      if (!r.product_id) return `Pick which ${r.kind} the bouquet uses, or remove that row.`;
      const n = Number(r.count);
      if (!Number.isInteger(n) || n < 1 || n > 1000) return `Enter how many ${r.flower || `of the ${r.kind}`} one bouquet uses (1 or more).`;
      continue;
    }
    if (!filled(r)) continue;
    const name = String(r.flower || "").trim();
    if (!name) return "Name every flower in the bouquet, or remove the empty row.";
    const n = Number(r.count);
    if (!Number.isInteger(n) || n < 1 || n > 1000) return `Enter how many ${name} are in the bouquet (1 to 1000).`;
    if (hasCost(r) && !(Number(r.cost) >= 0)) return `Enter what one ${name} cost you (₱0 or more).`;
  }
  return "";
}
