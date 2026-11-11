// "Explore 3D Designer" pressed before signing in: remembered for this tab so
// that, once the visitor signs up or signs in, they land on the shop picker
// and can open the 3D designer (see useAuth's resolveUserRedirect).
const KEY = "bc.pendingDesigner";
const TTL_MS = 60 * 60 * 1000; // an hour: long enough to sign up with an emailed code

export function savePendingDesigner() {
  try {
    sessionStorage.setItem(KEY, String(Date.now()));
  } catch {
    // Storage unavailable: they land on the Shop page and can open the designer from there
  }
}

export function readPendingDesigner() {
  try {
    const savedAt = Number(sessionStorage.getItem(KEY));
    if (!savedAt) return false;
    if (Date.now() - savedAt > TTL_MS) {
      clearPendingDesigner();
      return false;
    }
    return true;
  } catch {
    return false;
  }
}

export function clearPendingDesigner() {
  try {
    sessionStorage.removeItem(KEY);
  } catch {
    // Nothing to clear
  }
}

// Where the shop picker opens: the Shop page with ?design=1
export const DESIGNER_PICKER_ROUTE = Object.freeze({ name: "Shop", query: { design: "1" } });
