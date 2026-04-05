// frontend/src/plugins/axios.js
import axios from "axios";
import { clearStoredAuth, getPreferredAuthToken } from "../utils/authSession";

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

  const url = error.config?.url ?? "";
  const normalizedUrl = url.startsWith("/") ? url : `/${url}`;

  return ["/auth/me", "/auth/employee-me"].includes(normalizedUrl);
};

// ================= REQUEST INTERCEPTOR =================
api.interceptors.request.use(
  (config) => {
    const token = getPreferredAuthToken(window.location.pathname);

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    } else {
      delete config.headers.Authorization;
    }

    // Inject active assignment context for the backend CheckActiveAssignment middleware
    const assignmentId = localStorage.getItem("active_assignment_id");
    if (assignmentId) {
      config.headers["X-Active-Assignment"] = assignmentId;
    }

    console.log(`[API Request] ${config.method?.toUpperCase()} ${config.url}`);

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
    console.log(`[API Response] ${response.status} ${response.config.url}`);
    return response;
  },
  (error) => {
    console.error("[Response Error]", {
      status: error.response?.status,
      url: error.config?.url,
      data: error.response?.data,
    });

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
