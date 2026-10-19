import api from "../plugins/axios";
import { sessionReplaced } from "../utils/sessionReplaced";

// One sign-in per account: open tabs quietly ask whether their session is
// still the current one, so a sign-in elsewhere shows up within a minute.
const CHECK_EVERY_MS = 60_000;

// Customer, vendor and admin sessions only; staff accounts work as before
const signedInAsUser = () => {
  try {
    return !!(localStorage.getItem("auth_token") || localStorage.getItem("vendor_token")) && localStorage.getItem("user_type") !== "employee";
  } catch {
    return false;
  }
};

let timer = null;
let started = false;

async function check() {
  if (sessionReplaced.open || document.visibilityState !== "visible" || !signedInAsUser()) return;
  try {
    await api.get("/auth/session");
  } catch {
    // A "replaced" answer opens the modal from the axios interceptor
  }
}

/** Checks once a minute while the tab is visible, and whenever it becomes visible again */
export function startSessionWatch() {
  if (started) return;
  started = true;
  timer = setInterval(check, CHECK_EVERY_MS);
  document.addEventListener("visibilitychange", check);
  window.addEventListener("focus", check);
}

export function stopSessionWatch() {
  clearInterval(timer);
  document.removeEventListener("visibilitychange", check);
  window.removeEventListener("focus", check);
  started = false;
}
