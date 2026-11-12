// The flowers in a bouquet ([{ flower, color, count }]), shared by the vendor
// and ERP product forms. Sent as `composition`; the server keeps it only for
// bouquets and procurement adds it up to know how many stems to buy.

const filled = (r) => String(r?.flower || "").trim() || String(r?.color || "").trim() || (r?.count !== "" && r?.count != null && r?.count !== 1);

/** The rows of an existing product, for its edit form */
export const compositionFromProduct = (p) =>
  (Array.isArray(p?.composition) ? p.composition : []).map((f) => ({
    flower: f.flower || "",
    color: f.color || "",
    count: Number(f.count) || 1,
  }));

/** The JSON the forms send (empty rows left out; [] clears it) */
export const compositionField = (rows) =>
  JSON.stringify(
    (rows || [])
      .filter((r) => String(r.flower || "").trim())
      .map((r) => ({ flower: String(r.flower).trim(), color: String(r.color || "").trim() || null, count: Math.floor(Number(r.count)) })),
  );

/** A form error for the rows, or "" when they're fine */
export function compositionProblem(rows) {
  for (const r of rows || []) {
    if (!filled(r)) continue;
    const name = String(r.flower || "").trim();
    if (!name) return "Name every flower in the bouquet, or remove the empty row.";
    const n = Number(r.count);
    if (!Number.isInteger(n) || n < 1 || n > 1000) return `Enter how many ${name} are in the bouquet (1 to 1000).`;
  }
  return "";
}
