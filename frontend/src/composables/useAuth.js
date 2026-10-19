import { ref, computed } from "vue";
import api from "../plugins/axios";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useAssignment } from "./useAssignment";
import { buildLoginContext } from "../utils/loginContext";
import {
  clearStoredAuth,
  getPreferredAuthToken,
  getPreferredUserType,
  getStoredEmployeeToken,
  getStoredUserToken,
} from "../utils/authSession";
import { clearSubscriptionAccess } from "./useSubscriptionAccess";
import { clearVendorProfile } from "./useVendorProfile";
import { useAdminAccess } from "./useAdminAccess";
import { readPendingPlan } from "../constants/subscriptionPlans";
import { getDeviceId } from "../utils/deviceId";
import { clearSessionReplaced } from "../utils/sessionReplaced";

const user = ref(null);
const loading = ref(false);
const error = ref(null);
const initialized = ref(false);
const isLoggingOut = ref(false);

export function useAuth() {
  const router = useRouter();
  const isAuthenticated = computed(() => !!user.value);
  const isUnauthorizedError = (err) => err?.response?.status === 401;

  // ==================== Helpers ====================
  const setAuthHeader = (
    type = getPreferredUserType(window.location.pathname),
  ) => {
    const token =
      type === "employee" ? getStoredEmployeeToken() : getStoredUserToken();

    if (token) {
      api.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    } else {
      delete api.defaults.headers.common["Authorization"];
    }
  };

  const setAuthData = (token, userData, type = "user") => {
    clearSubscriptionAccess();
    clearVendorProfile();
    if (type === "employee") {
      localStorage.setItem("employee_token", token);
      localStorage.removeItem("auth_token");
      localStorage.removeItem("vendor_token");
    } else {
      localStorage.setItem("auth_token", token);
      localStorage.removeItem("employee_token");
      localStorage.removeItem("vendor_token");
    }

    localStorage.setItem("user_type", type);
    localStorage.setItem("user", JSON.stringify(userData));
    user.value = userData;
    // A fresh sign-in on this browser: any "signed in elsewhere" notice is over
    clearSessionReplaced();

    setAuthHeader(type);
  };

  const clearAuthData = () => {
    clearStoredAuth();
    clearSubscriptionAccess();
    clearVendorProfile();
    // Forget the previous admin's permissions
    useAdminAccess().reset();

    delete api.defaults.headers.common["Authorization"];

    user.value = null;
  };

  // A vendor who picked a plan on the landing page before logging in goes
  // straight to that plan's checkout on /pricing. Every other user keeps the
  // server-provided redirect.
  const resolveUserRedirect = (userData, serverRedirect) => {
    if (userData?.role === "vendor") {
      const planKey = readPendingPlan();
      if (planKey) return { path: "/pricing", query: { plan: planKey } };
    }
    return serverRedirect || "/shop";
  };

  // ================= EMPLOYEE LOGIN =================
  const employeeLogin = async (credentials) => {
    try {
      loading.value = true;
      error.value = null;
      const loginContext =
        credentials?.loginContext ?? (await buildLoginContext());

      const { data } = await api.post("/auth/employee-login", {
        username: credentials.username,
        password: credentials.password,
        login_context: loginContext,
      });

      if (!data?.success) {
        throw new Error(data?.message || "Employee login failed");
      }

      setAuthData(
        data.token,
        { ...data.employee, type: "employee" },
        "employee",
      );

      const { loadAssignments, getDefaultRoute } = useAssignment();
      await loadAssignments(data.employee);

      const redirectUrl = getDefaultRoute() || "/dashboard";
      await router.push(redirectUrl);

      return { success: true, data };
    } catch (err) {
      // console.error("❌ Employee login error:", {
      //   message: err?.message,
      //   response: err?.response?.data,
      //   status: err?.response?.status,
      //   url: err?.response?.config?.url,
      // });

      let errorMessage = "Employee login failed";
      if (err?.response?.status === 404) {
        errorMessage =
          "Employee login endpoint not found. Check your API routes.";
      } else if (err?.response?.status === 401) {
        errorMessage = "Invalid employee credentials or account inactive";
      } else if (err?.response?.data?.message) {
        errorMessage = err.response.data.message;
      } else if (err?.response?.data?.errors) {
        const firstError = Object.values(err.response.data.errors)[0];
        errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
      } else if (err?.message === "Network Error") {
        errorMessage =
          "Cannot connect to server. Please check your connection.";
      }

      error.value = errorMessage;
      return { success: false, error: errorMessage, isEmployeeError: true, status: err?.response?.status, code: err?.response?.data?.code ?? null };
    } finally {
      loading.value = false;
    }
  };

  // ================= REGULAR LOGIN =================
  const login = async (credentials) => {
    try {
      loading.value = true;
      error.value = null;
      const loginContext =
        credentials?.loginContext ?? (await buildLoginContext());

      const { data } = await api.post("/auth/login", {
        username: credentials.username,
        password: credentials.password,
        login_context: loginContext,
        device_id: getDeviceId(),
        // Set after the person chose "Continue logging in" in the "already logged in" modal
        continue_here: credentials.continueHere ? true : undefined,
      });

      if (data?.requires_two_factor) {
        return {
          success: false,
          requiresTwoFactor: true,
          challengeToken: data.challenge_token,
        };
      }

      const token = data?.token;
      const userData = data?.user || data;

      if (!token) throw new Error("No token received from server");

      setAuthData(
        token,
        { ...userData, type: userData?.role || "customer" },
        "user",
      );

      const redirectUrl = resolveUserRedirect(userData, data?.redirect_url);
      await router.push(redirectUrl);
      toast.success(`Welcome back, ${userData?.name || "User"}!`);

      return { success: true };
    } catch (err) {
      console.error("❌ Regular login error:", {
        message: err?.message,
        response: err?.response?.data,
        status: err?.response?.status,
        url: err?.response?.config?.url,
      });

      let errorMessage = "Login failed";
      if (err?.response?.status === 401) {
        errorMessage = "Invalid username or password";
      } else if (err?.response?.data?.errors) {
        const firstError = Object.values(err.response.data.errors)[0];
        errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
      } else if (err?.response?.data?.message) {
        errorMessage = err.response.data.message;
      }

      error.value = errorMessage;
      return { success: false, error: errorMessage, isEmployeeError: false, status: err?.response?.status, code: err?.response?.data?.code ?? null };
    } finally {
      loading.value = false;
    }
  };

  // ================= COMBINED LOGIN =================
  const combinedLogin = async (credentials) => {
    const loginContext = await buildLoginContext();

    const employeeResult = await employeeLogin({
      ...credentials,
      loginContext,
    });
    if (employeeResult.success) return employeeResult;
    // A staff account that exists but can't sign in (store suspended, too many tries)
    if (employeeResult.code || employeeResult.status === 403) {
      return { success: false, error: employeeResult.error, code: employeeResult.code };
    }

    const userResult = await login({
      ...credentials,
      loginContext,
    });
    if (userResult.success) return userResult;
    if (userResult.requiresTwoFactor) return userResult;
    // Suspended, locked or unverified: say so instead of "wrong password"
    if (userResult.code) {
      return { success: false, error: userResult.error, code: userResult.code };
    }
    if (!userResult.status || userResult.status >= 500) {
      return { success: false, error: "We couldn't reach BloomCraft. Check your connection and try again." };
    }

    return {
      success: false,
      error: "Invalid username or password. Please check your credentials.",
    };
  };

  const completeTwoFactorLogin = async (challengeToken, code, method) => {
    try {
      loading.value = true;
      error.value = null;
      const endpoint =
        method === "otp"
          ? "/auth/two-factor/verify-otp"
          : "/auth/two-factor/verify-passkey";
      const payload = {
        challenge_token: challengeToken,
        [method === "otp" ? "otp" : "passkey"]: code,
        login_context: await buildLoginContext(),
        device_id: getDeviceId(),
      };
      const { data } = await api.post(endpoint, payload);
      if (!data?.token) throw new Error("Login verification failed");

      const userData = data.user;
      setAuthData(
        data.token,
        { ...userData, type: userData?.role || "customer" },
        "user",
      );
      await router.push(resolveUserRedirect(userData, data.redirect_url));
      toast.success(`Welcome back, ${userData?.name || "User"}!`);
      return { success: true };
    } catch (err) {
      const errors = err?.response?.data?.errors;
      const firstError = errors ? Object.values(errors)[0] : null;
      return {
        success: false,
        error: Array.isArray(firstError)
          ? firstError[0]
          : err?.response?.data?.message ||
            err.message ||
            "Verification failed",
      };
    } finally {
      loading.value = false;
    }
  };

  // ================= REGISTER =================
  const register = async (formData) => {
    try {
      loading.value = true;
      error.value = null;

      const payload = {
        name: formData.name, // ← formData, not form
        surname: formData.surname,
        username: formData.username,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
        otp: formData.otp,
        device_id: getDeviceId(),
      };

      const { data } = await api.post("/auth/register", payload);
      setAuthData(data?.token, data?.user);
      await router.push("/shop");
      toast.success("Account created successfully!");

      return { success: true, data };
    } catch (err) {
      let errorMessage = "Registration failed";
      let fieldErrors = {};
      if (err?.response?.data?.errors) {
        fieldErrors = err.response.data.errors;
        const firstError = Object.values(err.response.data.errors)[0];
        errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
      } else if (err?.response?.data?.message) {
        errorMessage = err.response.data.message;
      }
      error.value = errorMessage;
      return { success: false, error: errorMessage, errors: fieldErrors };
    } finally {
      loading.value = false;
    }
  };

  // ================= LOGOUT =================
  const logout = async () => {
    if (isLoggingOut.value) return;

    try {
      isLoggingOut.value = true;

      const userType = localStorage.getItem("user_type");
      const token = userType === "employee" ? getStoredEmployeeToken() : getStoredUserToken();

      const endpoint =
        userType === "employee" ? "/auth/employee-logout" : "/auth/logout";

      // Sign out here straight away. The server is told in the background:
      // on a sleeping free-tier server that call can take half a minute, and
      // waiting for it made the Log out button look like it did nothing.
      clearAuthData();
      if (userType === "employee") {
        const { clearAssignment } = useAssignment();
        clearAssignment();
      }

      if (token) {
        api
          .post(endpoint, null, { headers: { Authorization: `Bearer ${token}` }, keepAuthorization: true })
          .catch(() => console.warn("Server logout failed; this browser is signed out anyway."));
      }

      await router.push("/guest/login");
      toast.success("Logged out successfully!");
    } finally {
      isLoggingOut.value = false;
    }
  };

  // ================= FETCH USER =================
  const fetchUser = async (path = window.location.pathname) => {
    try {
      const userType = getPreferredUserType(path);
      const endpoint =
        userType === "employee" ? "/auth/employee-me" : "/auth/me";

      const { data } = await api.get(endpoint);
      user.value =
        userType === "employee"
          ? { ...data?.employee, type: "employee" }
          : { ...data?.user, type: data?.user?.role };

      if (userType === "employee" && data?.employee) {
        const { loadAssignments } = useAssignment();
        loadAssignments(data.employee);
      }

      localStorage.setItem(
        "user_type",
        userType === "employee" ? "employee" : "user",
      );
      localStorage.setItem("user", JSON.stringify(user.value));
      setAuthHeader(userType);
    } catch (err) {
      console.warn("Fetch user error (ignored if transient):", err?.message);
      if (err?.response?.status === 401) {
        console.warn(
          "Unauthorized during fetchUser - clearing local auth state",
        );
        clearAuthData();
      }
      throw err;
    }
  };

  // ================= LOAD USER FROM STORAGE =================
  const loadUser = async (path = window.location.pathname) => {
    const userType = getPreferredUserType(path) || "user";
    const token = getPreferredAuthToken(path);
    const storedUser = localStorage.getItem("user");

    if (!token) throw new Error("No token found");

    // Set the auth header in axios so the fetch call below works
    setAuthHeader(userType);

    // CRITICAL: Hydrate in-memory state before any async calls to prevent
    // router guards from seeing a null user during navigation.
    if (storedUser && !user.value) {
      try {
        user.value = JSON.parse(storedUser);
      } catch (err) {
        console.warn("Failed to parse stored user during loadUser:", err);
      }
    }

    try {
      await fetchUser(path);
    } catch (err) {
      // If we already have a user in memory from localStorage, don't crash
      // the navigation just because the background fetch failed (unless it was a 401).
      if (user.value && err?.response?.status !== 401) {
        console.warn(
          "Fetch user failed, but continuing with stored data.",
          err?.message,
        );
        return;
      }
      throw err;
    }
  };

  // ================= INIT AUTH =================
  const initAuth = async (path = window.location.pathname) => {
    if (initialized.value) return;

    const userType = getPreferredUserType(path);
    const token = getPreferredAuthToken(path);
    const storedUser = localStorage.getItem("user");

    if (storedUser && !user.value) {
      try {
        user.value = JSON.parse(storedUser);
      } catch (err) {
        console.error("Failed to parse stored user during init:", err);
      }
    }

    if (token) {
      setAuthHeader(userType);

      try {
        await fetchUser(path);
      } catch (err) {
        if (isUnauthorizedError(err)) {
          clearAuthData();
        } else {
          console.warn("Auth bootstrap failed, preserving stored session.", {
            path,
            status: err?.response?.status,
            message: err?.message,
          });
        }
      }
    }

    initialized.value = true;
  };

  return {
    user,
    loading,
    error,
    isAuthenticated,
    isLoggingOut,
    register,
    login,
    employeeLogin,
    combinedLogin,
    completeTwoFactorLogin,
    logout,
    clearAuthData,
    fetchUser,
    loadUser,
    initAuth,
    initialized,
  };
}
