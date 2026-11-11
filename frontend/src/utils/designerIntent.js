// "Explore 3D Designer" pressed before signing in: remembered in memory only,
// so it lasts while the visitor goes from the landing page to sign-up or
// sign-in and on to the shop picker (see useAuth's resolveUserRedirect), and
// a page refresh forgets it.
let pendingAt = 0;
const TTL_MS = 60 * 60 * 1000; // an hour: long enough to sign up with an emailed code

// Earlier versions kept it in sessionStorage, where it outlived refreshes
try {
  sessionStorage.removeItem("bc.pendingDesigner");
} catch {
  // Storage unavailable: nothing to clear
}

export function savePendingDesigner() {
  pendingAt = Date.now();
}

export function readPendingDesigner() {
  if (!pendingAt) return false;
  if (Date.now() - pendingAt > TTL_MS) {
    pendingAt = 0;
    return false;
  }
  return true;
}

export function clearPendingDesigner() {
  pendingAt = 0;
}

// Where the shop picker opens: the Shop page with ?design=1
export const DESIGNER_PICKER_ROUTE = Object.freeze({ name: "Shop", query: { design: "1" } });
