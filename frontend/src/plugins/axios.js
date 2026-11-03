// frontend/src/plugins/axios.js
import axios from "axios";
import { toast } from "@/utils/snackbar";
import { clearStoredAuth, getPreferredAuthToken } from "../utils/authSession";
import { markSessionReplaced } from "../utils/sessionReplaced";
import { markSessionExpired } from "../utils/sessionExpired";
import { makeFriendly } from "../utils/friendlyError";

const baseURL = import.meta.env.VITE_API_BASE_URL?.trim();

if (!baseURL) {
  throw new Error("VITE_API_BASE_URL is not defined.");
}

const api = axios.create({
  baseURL,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
  withCredentials: true,
});

const shouldForceLogout = (error) => {
  if (error.response?.status !== 401) {
    return false;
  }
  // A working sign-in used on the other kind of page: nothing to sign out
  if (error.response?.data?.code === "wrong_account_type") {
    return false;
  }

  const url = error.config?.url ?? "";
  const normalizedUrl = url.startsWith("/") ? url : `/${url}`;

  // Only identity endpoints should trigger a full local data wipe.
  const isIdentityEndpoint = ["/auth/me", "/auth/employee-me"].some(
    (ep) => normalizedUrl === ep || normalizedUrl === `/api${ep}`,
  );

  if (!isIdentityEndpoint) {
    return false;
  }

  // If we are currently navigating and don't even have a user in memory yet,
  // we should be more cautious about wiping localStorage, as it might be
  // a transient context mismatch during page load.
  const hasToken = !!error.config?.headers?.Authorization;
  if (!hasToken) {
    return false;
  }

  return true;
};

// Server answers that mean "this sign-in has ended", with the modal's heading
const ENDED_CODES = {
  session_expired: "You were away for a while",
  session_ended: "You've been signed out",
};

const sentCurrentToken = (config) => {
  const sent = String(config?.headers?.Authorization ?? "").replace(/^Bearer\s+/i, "");
  if (!sent) return false;
  try {
    return [localStorage.getItem("auth_token"), localStorage.getItem("vendor_token"), localStorage.getItem("employee_token")].includes(sent);
  } catch {
    return false;
  }
};

// ================= REQUEST INTERCEPTOR =================
api.interceptors.request.use(
  (config) => {
    // Let the browser generate the multipart boundary for every FormData
    // request. Keeping the client's JSON default here causes Laravel to read
    // uploaded images/models as ordinary values instead of UploadedFile items.
    if (config.data instanceof FormData) {
      delete config.headers["Content-Type"];
      delete config.headers["content-type"];
    }

    const token = getPreferredAuthToken(window.location.pathname);

    // keepAuthorization: the caller passed a token on purpose (logging out
    // in the background after the stored one was already cleared)
    if (config.keepAuthorization && config.headers.Authorization) {
      // leave it as given
    } else if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    } else {
      delete config.headers.Authorization;
    }

    // Inject active assignment context for the backend CheckActiveAssignment middleware
    const assignmentId = localStorage.getItem("active_assignment_id");
    if (assignmentId) {
      config.headers["X-Active-Assignment"] = assignmentId;
    }

    // console.log(`[API Request] ${config.method?.toUpperCase()} ${config.url}`);

    return config;
  },
  (error) => {
    console.error("[Request Error]", error);
    return Promise.reject(error);
  },
);

// ================= RESPONSE INTERCEPTOR =================
api.interceptors.response.use(
  (response) => {
    // console.log(`[API Response] ${response.status} ${response.config.url}`);
    return response;
  },
  (error) => {
    // Requests that expect a "no" as a normal answer (e.g. the staff sign-in
    // tried before the customer/vendor one) stay out of the console
    const expected = error.config?.quietStatuses?.includes(error.response?.status);
    if (!expected) console.error("[Response Error]", {
      status: error.response?.status,
      url: error.config?.url,
      data: error.response?.data,
    });

    // Plain words for every screen that shows the error (no internet, server
    // waking up, or anything technical the server let through)
    makeFriendly(error);

    // ================= SUSPENDED STORE =================
    // The vendor (or the store they work for) was suspended: sign out and
    // explain why on the login page.
    const isLoginRequest = /login/.test(error.config?.url ?? "");
    if (error.response?.status === 403 && error.response?.data?.code === "account_suspended" && !isLoginRequest) {
      clearStoredAuth();
      try {
        sessionStorage.setItem("auth_notice", error.response.data.message);
      } catch {
        // storage unavailable; the login page simply won't show the notice
      }
      if (!window.location.pathname.includes("/guest/login")) {
        window.location.replace("/guest/login");
      }
      return Promise.reject(error);
    }

    // ================= ADMIN PERMISSION =================
    // Page loads handle their own empty states; actions say why they failed.
    if (
      error.response?.status === 403 &&
      error.response?.data?.code === "forbidden_permission" &&
      (error.config?.method ?? "get").toLowerCase() !== "get"
    ) {
      toast.error(error.response.data.message);
    }

    // ================= SIGNED IN ELSEWHERE =================
    // The account logged in on another browser. Explain it in a modal instead
    // of silently bouncing to the login page; the modal's Log out finishes up.
    if (error.response?.status === 401 && error.response?.data?.code === "session_replaced") {
      markSessionReplaced(error.response.data.message);
      return Promise.reject(error);
    }

    // ================= SIGN-IN ENDED =================
    // Any account (customer, vendor, admin, ERP staff): the server says this
    // sign-in no longer works and why. Sign out here and show the reason,
    // never a silent jump to the login page.
    const endedCode = error.response?.data?.code;
    if (error.response?.status === 401 && ENDED_CODES[endedCode]) {
      // Only when it's the sign-in this browser still uses (not a request
      // that was sent before signing in again)
      if (sentCurrentToken(error.config)) {
        clearStoredAuth();
        delete api.defaults.headers.common["Authorization"];
        markSessionExpired({
          kind: endedCode === "session_expired" ? "away" : "ended",
          title: ENDED_CODES[endedCode],
          message: error.response.data.message,
        });
      }
      return Promise.reject(error);
    }

    // ================= HANDLE 401 =================
    if (shouldForceLogout(error)) {
      console.log("401 Unauthorized - clearing auth");

      clearStoredAuth();

      // Use replace instead of href (prevents history loop)
      if (!window.location.pathname.includes("/guest/login")) {
        window.location.replace("/guest/login");
      }
    }

    return Promise.reject(error);
  },
);

export default api;
