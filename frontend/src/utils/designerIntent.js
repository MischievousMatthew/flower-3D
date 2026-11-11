// "Explore 3D Designer" pressed before signing in: remembered for 5 minutes
// in this tab (sessionStorage), so it lasts through sign-up or sign-in, even
// a refresh or two, and then lands them on the shop picker (see useAuth's
// resolveUserRedirect). After 5 minutes a refresh no longer shows it.
const KEY = "bc.pendingDesigner";
const TTL_MS = 5 * 60 * 1000;

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
