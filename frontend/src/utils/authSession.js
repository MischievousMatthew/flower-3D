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

  if (routeContext === "user") {
    return userToken;
  }

  if (routeContext === "employee") {
    return employeeToken;
  }

  const userType = localStorage.getItem("user_type");

  if (userType === "employee") {
    return employeeToken || userToken || null;
  }

  if (userType === "user") {
    return userToken || employeeToken || null;
  }

  return userToken || employeeToken || null;
};

export const getPreferredUserType = (path = window.location.pathname) => {
  const routeContext = getRouteAuthContext(path);

  if (routeContext === "user") {
    return getStoredUserToken() ? "user" : null;
  }

  if (routeContext === "employee") {
    return getStoredEmployeeToken() ? "employee" : null;
  }

  const userType = localStorage.getItem("user_type");

  if (userType === "employee" || userType === "user") {
    return userType;
  }

  if (getStoredUserToken()) {
    return "user";
  }

  if (getStoredEmployeeToken()) {
    return "employee";
  }

  return null;
};
