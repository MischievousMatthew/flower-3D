const USER_ROUTE_PREFIXES = ["/admin", "/authenticated", "/customer", "/shop", "/store", "/vendor"];
const EMPLOYEE_ROUTE_PREFIXES = ["/erp"];

const normalizePath = (path = "/") => {
  if (!path) {
    return "/";
  }

  return path.startsWith("/") ? path : `/${path}`;
};

const pathMatchesPrefix = (path, prefix) =>
  path === prefix || path.startsWith(`${prefix}/`);

export const getStoredUserToken = () =>
  localStorage.getItem("auth_token") || localStorage.getItem("vendor_token");

export const getStoredEmployeeToken = () =>
  localStorage.getItem("employee_token");

export const clearStoredAuth = () => {
  localStorage.removeItem("auth_token");
  localStorage.removeItem("employee_token");
  localStorage.removeItem("vendor_token");
  localStorage.removeItem("user");
  localStorage.removeItem("user_type");
};

export const hasStoredAuthSession = (path = window.location.pathname) =>
  !!getPreferredAuthToken(path);

export const getRouteAuthContext = (path = window.location.pathname) => {
  const normalizedPath = normalizePath(path);

  if (EMPLOYEE_ROUTE_PREFIXES.some((prefix) => pathMatchesPrefix(normalizedPath, prefix))) {
    return "employee";
  }

  if (USER_ROUTE_PREFIXES.some((prefix) => pathMatchesPrefix(normalizedPath, prefix))) {
    return "user";
  }

  return null;
};

export const getPreferredAuthToken = (path = window.location.pathname) => {
  const routeContext = getRouteAuthContext(path);
  const userToken = getStoredUserToken();
  const employeeToken = getStoredEmployeeToken();
  const storedUserType = localStorage.getItem("user_type");

  // During a direct refresh on a specific route, strictly follow that route's context.
  if (routeContext === "employee" && employeeToken) {
    return employeeToken;
  }

  if (routeContext === "user" && userToken) {
    return userToken;
  }

  // Fallback to the stored user type if path context is neutral (e.g. public pages or root)
  if (storedUserType === "employee") {
    return employeeToken || userToken || null;
  }

  return userToken || employeeToken || null;
};

export const getPreferredUserType = (path = window.location.pathname) => {
  const routeContext = getRouteAuthContext(path);
  const storedUserType = localStorage.getItem("user_type");

  if (routeContext === "employee" && getStoredEmployeeToken()) {
    return "employee";
  }

  if (routeContext === "user" && getStoredUserToken()) {
    return "user";
  }

  if (storedUserType === "employee" || storedUserType === "user") {
    return storedUserType;
  }

  if (getStoredEmployeeToken()) {
    return "employee";
  }

  if (getStoredUserToken()) {
    return "user";
  }

  return null;
};
