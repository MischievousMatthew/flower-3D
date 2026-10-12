/**
 * Record IDs that admin pages pass to each other (which order, product,
 * customer, vendor or ticket to open). They travel in the browser's history
 * state instead of the address bar, so IDs never show in the URL.
 */
export const HIDDEN_ID_KEYS = ["order", "product", "customer", "vendor", "ticket"];

/** Moves any hidden-ID keys from `location.query` into `location.state` */
export const hideIdsInLocation = (location) => {
  if (!location || typeof location !== "object" || !location.query) return location;
  const query = { ...location.query };
  const state = { ...(location.state ?? {}) };
  let moved = false;
  for (const key of HIDDEN_ID_KEYS) {
    if (key in query) {
      // null/undefined clears it (e.g. when a drawer closes)
      state[key] = query[key] == null || query[key] === "" ? null : String(query[key]);
      delete query[key];
      moved = true;
    }
  }
  return moved ? { ...location, query, state } : location;
};

/** The ID another page asked this page to open, if any */
export const linkedId = (key) => {
  let value;
  try {
    value = window.history.state?.[key];
  } catch {
    return null;
  }
  const id = Number(value);
  return Number.isInteger(id) && id > 0 ? id : null;
};
