// frontend/src/plugins/axios.js
import axios from "axios";

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

const getStoredAuthToken = () => {
  const userType = localStorage.getItem("user_type");

  if (userType === "employee") {
    return localStorage.getItem("employee_token");
  }

  if (userType === "user") {
    return localStorage.getItem("auth_token");
  }

  // Fallback for older sessions where user_type may be missing.
  return (
    localStorage.getItem("employee_token") ||
    localStorage.getItem("auth_token")
  );
};

// ================= REQUEST INTERCEPTOR =================
api.interceptors.request.use(
  (config) => {
    const token = getStoredAuthToken();

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
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
    if (error.response?.status === 401) {
      console.log("401 Unauthorized - clearing auth");

      localStorage.removeItem("auth_token");
      localStorage.removeItem("employee_token");
      localStorage.removeItem("vendor_token");
      localStorage.removeItem("user");
      localStorage.removeItem("user_type");

      // Use replace instead of href (prevents history loop)
      if (!window.location.pathname.includes("/guest/login")) {
        window.location.replace("/guest/login");
      }
    }

    return Promise.reject(error);
  },
);

export default api;
