import { reactive } from "vue";

// "You were signed out": one modal for every kind of account (customers,
// vendors, admins, ERP staff). Shown when the 15-minute inactivity timeout
// ends a session while the page is open, or when the server says this
// browser's sign-in has ended and why (expired, password changed, closed,
// suspended…). Kept in sessionStorage so a page reload still explains it.
const FLAG = "bc_session_ended";

export const AWAY_TITLE = "You were away for a while";
export const AWAY_MESSAGE = "To keep your account safe, we sign you out after 15 minutes without any activity.";

const read = () => {
  try {
    return JSON.parse(sessionStorage.getItem(FLAG) || "null");
  } catch {
    return null;
  }
};

const saved = read();

export const sessionExpired = reactive({
  open: !!saved,
  kind: saved?.kind || "away",
  title: saved?.title || AWAY_TITLE,
  message: saved?.message || AWAY_MESSAGE,
});

/**
 * Opens the modal. With no details it's the inactivity sign-out; the server's
 * reason passes { kind: "ended", title, message }.
 */
export function markSessionExpired(details = {}) {
  sessionExpired.kind = details.kind || "away";
  sessionExpired.title = details.title || AWAY_TITLE;
  sessionExpired.message = details.message || AWAY_MESSAGE;
  sessionExpired.open = true;
  try {
    sessionStorage.setItem(FLAG, JSON.stringify({ kind: sessionExpired.kind, title: sessionExpired.title, message: sessionExpired.message }));
  } catch {
    // storage unavailable; the modal still shows for this page
  }
}

export function clearSessionExpired() {
  sessionExpired.open = false;
  try {
    sessionStorage.removeItem(FLAG);
  } catch {
    // nothing to clear
  }
}
