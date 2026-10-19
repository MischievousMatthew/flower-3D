import { reactive } from "vue";

// "Your account was logged in on another device": shown when the server says
// this browser's session was replaced. Kept in sessionStorage too, so a reload
// still shows it until the person logs out.
const FLAG = "bc_session_replaced";

const read = () => {
  try {
    return sessionStorage.getItem(FLAG);
  } catch {
    return null;
  }
};

export const sessionReplaced = reactive({ open: !!read(), message: read() || "" });

export function markSessionReplaced(message) {
  sessionReplaced.message = message || "Your account was logged in on another device or browser.";
  sessionReplaced.open = true;
  try {
    sessionStorage.setItem(FLAG, sessionReplaced.message);
  } catch {
    // storage unavailable; the modal still shows for this page
  }
}

export function clearSessionReplaced() {
  sessionReplaced.open = false;
  try {
    sessionStorage.removeItem(FLAG);
  } catch {
    // nothing to clear
  }
}
