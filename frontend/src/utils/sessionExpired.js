import { reactive } from "vue";

// "You were signed out for inactivity": shown when the 15-minute timeout ends
// a session while the page is open. Not shown when the site is reopened after
// the browser was closed; that sign-out happens quietly.
export const sessionExpired = reactive({ open: false });

export function markSessionExpired() {
  sessionExpired.open = true;
}

export function clearSessionExpired() {
  sessionExpired.open = false;
}
