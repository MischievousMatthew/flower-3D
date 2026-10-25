import { reactive } from "vue";
import api from "../plugins/axios.js";

// Whether sign-ups and checkout are open, as set by admins in Security → Platform settings.
// The server enforces these too; this only lets pages say so up front.
const OPEN = {
  registrations_open: true,
  vendor_applications_open: true,
  checkout_paused: false,
  checkout_pause_message: "Ordering is paused for a moment. Please try again later.",
  // A planned pause (shown to customers before it starts)
  checkout_pause_starts_at: null,
  checkout_pause_ends_at: null,
};

// The "checkout is paused" modal, shared by every page (mounted once in App.vue)
export const checkoutPausedNotice = reactive({ open: false, message: "" });

export function showCheckoutPaused(message) {
  checkoutPausedNotice.message = message || OPEN.checkout_pause_message;
  checkoutPausedNotice.open = true;
}

export function usePlatformStatus() {
  const status = reactive({ ...OPEN, loaded: false });

  // Always asks the server, so a switch an admin just flipped applies straight away.
  // If the check itself fails the page stays usable; the server still refuses.
  async function refresh() {
    try {
      const { data } = await api.get("/site/status");
      Object.assign(status, OPEN, data?.data || {});
    } catch {
      Object.assign(status, OPEN);
    } finally {
      status.loaded = true;
    }
    return status;
  }

  return { status, refresh };
}
