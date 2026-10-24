import { onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "./useAuth";
import { markSessionExpired } from "../utils/sessionExpired";

export function useInactivityTimeout(timeoutMinutes = 15) {
  const { isAuthenticated, logout } = useAuth();
  const router = useRouter();

  const timeoutMs = timeoutMinutes * 60 * 1000;
  let activityTimer = null;
  let storageCheckTimer = null;

  // Away too long since the last recorded activity (e.g. the browser was closed)
  const hasExpired = () => {
    const lastActivity = parseInt(localStorage.getItem("last_activity") || "0", 10);
    return lastActivity > 0 && Date.now() - lastActivity >= timeoutMs;
  };

  // A signed-in session just became known: expire it if the gap was too long,
  // otherwise start counting. Resetting first would erase the gap.
  const startTracking = () => {
    if (!isAuthenticated.value) return;
    if (hasExpired()) {
      handleInactivity({ reopened: true });
    } else {
      resetTimer();
    }
  };

  const resetTimer = () => {
    if (!isAuthenticated.value) return;

    localStorage.setItem("last_activity", Date.now().toString());

    if (activityTimer) clearTimeout(activityTimer);
    activityTimer = setTimeout(handleInactivity, timeoutMs);
  };

  // reopened: the gap was found on page load (browser closed and reopened), so
  // sign out quietly. Otherwise the page was open the whole time: explain why.
  const handleInactivity = async ({ reopened = false } = {}) => {
    if (!isAuthenticated.value) return;

    const lastActivity = parseInt(
      localStorage.getItem("last_activity") || "0",
      10,
    );
    const now = Date.now();

    if (now - lastActivity >= timeoutMs) {
      console.log(
        "User inactive for",
        timeoutMinutes,
        "minutes. Logging out for security.",
      );

      localStorage.removeItem("last_activity");

      if (activityTimer) clearTimeout(activityTimer);

      const onPublicPage = router.currentRoute.value.matched.some((r) => r.meta?.public);

      await logout({ quiet: true });

      if (!reopened) {
        markSessionExpired();
      } else if (!onPublicPage) {
        router.replace("/guest/login");
      }
    } else {
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
      startTracking();
    } else {
      if (activityTimer) clearTimeout(activityTimer);
    }
  });

  onMounted(() => {
    setupListeners();
    // Landing on a page with a stored session, whether or not the tab is visible yet
    startTracking();
  });

  onUnmounted(() => {
    cleanupListeners();
  });

  return { resetTimer };
}
