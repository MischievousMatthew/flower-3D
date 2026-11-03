import { ref } from "vue";
import api from "../plugins/axios";

// How many of my Help & support requests have a reply I haven't opened.
// Shown as a red dot on my name in the header and on "Help & support" in
// its menu. Shared by the header and the support page, checked once a minute.
const unreadSupport = ref(0);
const CHECK_EVERY_MS = 60_000;
let timer = null;
let users = 0;

export async function refreshSupportUnread() {
  try {
    const { data } = await api.get("/support/unread", { quietStatuses: [401, 403] });
    unreadSupport.value = Number(data?.unread) || 0;
  } catch {
    // Keep the last known count; the next check tries again
  }
}

function check() {
  if (document.visibilityState === "visible") refreshSupportUnread();
}

/** Start checking while a header is on screen; stop() when it leaves */
export function useSupportUnread() {
  function start() {
    users += 1;
    if (users === 1) {
      refreshSupportUnread();
      timer = setInterval(check, CHECK_EVERY_MS);
      document.addEventListener("visibilitychange", check);
    }
  }
  function stop() {
    users = Math.max(0, users - 1);
    if (users === 0) {
      clearInterval(timer);
      document.removeEventListener("visibilitychange", check);
    }
  }
  return { unreadSupport, start, stop, refresh: refreshSupportUnread };
}
