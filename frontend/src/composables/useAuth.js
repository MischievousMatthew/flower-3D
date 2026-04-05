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

const user = ref(null);
const loading = ref(false);
const error = ref(null);
const initialized = ref(false);
const isLoggingOut = ref(false);

export function useAuth() {
  const router = useRouter();
  const isAuthenticated = computed(() => !!user.value);

  // ==================== Helpers ====================
  const setAuthHeader = (type = getPreferredUserType(window.location.pathname)) => {
    const token =
      type === "employee" ? getStoredEmployeeToken() : getStoredUserToken();

    if (token) {
      api.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    } else {
      delete api.defaults.headers.common["Authorization"];
    }
  };

  const setAuthData = (token, userData, type = "user") => {
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

    setAuthHeader(type);
  };

  const clearAuthData = () => {
    clearStoredAuth();

    delete api.defaults.headers.common["Authorization"];

    user.value = null;
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

      // Bootstrap assignments
      const { loadAssignments, getDefaultRoute } = useAssignment();
      await loadAssignments(data.employee);

      // Redirect to the default route for their active assignment
      const redirectUrl = getDefaultRoute() || "/dashboard";
      await router.push(redirectUrl);
      toast.success(`Welcome ${data.employee.name}!`);

      return { success: true, data };
    } catch (err) {
      console.error("❌ Employee login error:", {
        message: err?.message,
        response: err?.response?.data,
        status: err?.response?.status,
        url: err?.response?.config?.url,
      });

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
      return { success: false, error: errorMessage, isEmployeeError: true };
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
      });

      const token = data?.token;
      const userData = data?.user || data;

      if (!token) throw new Error("No token received from server");

      setAuthData(
        token,
        { ...userData, type: userData?.role || "customer" },
        "user",
      );

      const redirectUrl = data?.redirect_url || "/shop";
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
      return { success: false, error: errorMessage, isEmployeeError: false };
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

    const userResult = await login({
      ...credentials,
      loginContext,
    });
    if (userResult.success) return userResult;

    return {
      success: false,
      error: "Invalid username or password. Please check your credentials.",
    };
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

      const endpoint =
        userType === "employee" ? "/auth/employee-logout" : "/auth/logout";

      try {
        await api.post(endpoint);
      } catch (error) {
        console.warn("Server logout failed, clearing locally...");
      }

      clearAuthData();
      if (userType === "employee") {
        const { clearAssignment } = useAssignment();
        clearAssignment();
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

      localStorage.setItem("user_type", userType === "employee" ? "employee" : "user");
      localStorage.setItem("user", JSON.stringify(user.value));
      setAuthHeader(userType);
    } catch (err) {
      console.error("Fetch user error:", err);
      if (err?.response?.status === 401) {
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

    // Important: Set the auth header in axios before fetching from API!
    setAuthHeader(userType);

    if (storedUser) {
      try {
        user.value = JSON.parse(storedUser);
      } catch (err) {
        console.error("Failed to parse stored user:", err);
      }
    }

    await fetchUser(path);
  };

  // ================= INIT AUTH =================
  const initAuth = async (path = window.location.pathname) => {
    if (initialized.value) return;

    const userType = getPreferredUserType(path);
    const token = getPreferredAuthToken(path);
    const storedUser = localStorage.getItem("user");

    if (token && storedUser) {
      try {
        user.value = JSON.parse(storedUser);
        setAuthHeader(userType);
        await fetchUser(path);
      } catch {
        clearAuthData();
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
    logout,
    fetchUser,
    loadUser,
    initAuth,
    initialized,
  };
}
