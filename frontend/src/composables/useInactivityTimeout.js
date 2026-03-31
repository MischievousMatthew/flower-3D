import { onMounted, onUnmounted, watch } from "vue";
import { useAuth } from "./useAuth";
import { toast } from "vue3-toastify";

export function useInactivityTimeout(timeoutMinutes = 15) {
  const { isAuthenticated, logout } = useAuth();

  const timeoutMs = timeoutMinutes * 60 * 1000;
  let activityTimer = null;
  let storageCheckTimer = null;

  const resetTimer = () => {
    if (!isAuthenticated.value) return;

    localStorage.setItem("last_activity", Date.now().toString());

    if (activityTimer) clearTimeout(activityTimer);
    activityTimer = setTimeout(handleInactivity, timeoutMs);
  };

  const handleInactivity = async () => {
    if (!isAuthenticated.value) return;

    // Before logging out, double check if another tab was active recently
    const lastActivity = parseInt(
      localStorage.getItem("last_activity") || "0",
      10,
    );
    const now = Date.now();

    if (now - lastActivity >= timeoutMs) {
      // It's definitely expired!
      console.log(
        "User inactive for",
        timeoutMinutes,
        "minutes. Logging out for security.",
      );

      // Clear activity
      localStorage.removeItem("last_activity");

      // We must clear the timer to avoid infinite loops
      if (activityTimer) clearTimeout(activityTimer);

      // Trigger logout universally (this handles tokens and redirection)
      await logout();

      // Show expiration alert
      toast.warning("Session expired due to inactivity. Please log in again.", {
        autoClose: 6000,
      });
    } else {
      // Another tab was active! Reset the timer relative to that tab's last activity
      const remainingTime = timeoutMs - (now - lastActivity);
      if (activityTimer) clearTimeout(activityTimer);
      activityTimer = setTimeout(handleInactivity, remainingTime);
    }
  };

  const checkOnVisible = () => {
    if (document.visibilityState === "visible") {
      // Tab became active, check if we expired while it was hidden
      const lastActivity = parseInt(
        localStorage.getItem("last_activity") || "0",
        10,
      );
      if (
        isAuthenticated.value &&
        lastActivity > 0 &&
        Date.now() - lastActivity >= timeoutMs
      ) {
        handleInactivity();
      }
    }
  };

  const setupListeners = () => {
    // Standard activity events
    const events = [
      "mousemove",
      "mousedown",
      "keydown",
      "touchstart",
      "scroll",
    ];
    events.forEach((event) => {
      // Add passive listener for better performance
      window.addEventListener(event, resetTimer, { passive: true });
    });

    // Handle tab visibility change (e.g., returning to tab after an hour)
    document.addEventListener("visibilitychange", checkOnVisible);

    // Check every minute just in case the browser throttles timeouts
    storageCheckTimer = setInterval(() => {
      if (!isAuthenticated.value) return;
      const lastActivity = parseInt(
        localStorage.getItem("last_activity") || "0",
        10,
      );
      if (lastActivity > 0 && Date.now() - lastActivity >= timeoutMs) {
        handleInactivity();
      }
    }, 60000);
  };

  const cleanupListeners = () => {
    const events = [
      "mousemove",
      "mousedown",
      "keydown",
      "touchstart",
      "scroll",
    ];
    events.forEach((event) => {
      window.removeEventListener(event, resetTimer);
    });
    document.removeEventListener("visibilitychange", checkOnVisible);

    if (activityTimer) clearTimeout(activityTimer);
    if (storageCheckTimer) clearInterval(storageCheckTimer);
  };

  // Watch for authentication state changes (e.g. user logs in)
  watch(isAuthenticated, (newVal) => {
    if (newVal) {
      resetTimer();
    } else {
      if (activityTimer) clearTimeout(activityTimer);
    }
  });

  onMounted(() => {
    setupListeners();
    // Initial check just in case we are landing on an authenticated page
    if (isAuthenticated.value) {
      checkOnVisible();
      resetTimer();
    }
  });

  onUnmounted(() => {
    cleanupListeners();
  });

  return { resetTimer };
}
