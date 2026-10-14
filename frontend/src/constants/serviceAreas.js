// Where a vendor can deliver. Vendors pick from this list (never free text) so
// every shop names places the same way. Keep in sync with
// VendorApplication::SERVICE_AREAS on the backend.
//
// Stored as a comma-separated string, e.g. "Cavite, Makati" (the same shape
// vendor registration writes). A province name means the whole province.

export const SERVICE_AREA_GROUPS = [
  {
    province: "Cavite",
    places: [
      "Alfonso", "Amadeo", "Bacoor", "Carmona", "Cavite City", "Dasmariñas", "General Emilio Aguinaldo",
      "General Mariano Alvarez", "General Trias", "Imus", "Indang", "Kawit", "Magallanes", "Maragondon",
      "Mendez", "Naic", "Noveleta", "Rosario", "Silang", "Tagaytay", "Tanza", "Ternate", "Trece Martires",
    ],
  },
  {
    province: "Metro Manila",
    places: [
      "Caloocan", "Las Piñas", "Makati", "Malabon", "Mandaluyong", "Manila", "Marikina", "Muntinlupa",
      "Navotas", "Parañaque", "Pasay", "Pasig", "Quezon City", "San Juan", "Taguig", "Valenzuela",
    ],
  },
];

const KNOWN = new Set(SERVICE_AREA_GROUPS.flatMap((g) => [g.province, ...g.places]));

/** "Cavite, Makati" → ["Cavite", "Makati"], keeping only places on the list */
export function parseServiceAreas(value) {
  const list = Array.isArray(value) ? value : String(value ?? "").split(",");
  return [...new Set(list.map((s) => String(s).trim()).filter((s) => KNOWN.has(s)))];
}

/** Places stored before the list existed, which the picker can't show */
export function unknownServiceAreas(value) {
  const list = Array.isArray(value) ? value : String(value ?? "").split(",");
  return list.map((s) => String(s).trim()).filter((s) => s && !KNOWN.has(s));
}

/** Back to the stored string; a whole province replaces its individual places */
export function joinServiceAreas(selected) {
  const set = new Set(selected);
  const out = [];
  for (const g of SERVICE_AREA_GROUPS) {
    if (set.has(g.province)) out.push(g.province);
    else out.push(...g.places.filter((p) => set.has(p)));
  }
  return out.join(", ");
}
