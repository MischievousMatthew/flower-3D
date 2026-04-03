// src/composables/useAssignment.js
//
// Module-based RBAC composable.
// Replaces the old department/role assignment system with direct module permissions.
//
// Each employee has a flat array of { module, access } objects.
// The composable provides helpers to check access per module.

import { ref, computed } from "vue";
import { ERP_MODULES, findModule } from "../constants/erpModules";

// ─── Module-level singletons ──────────────────────────────────────────────────
// Shared across all components that call useAssignment().
const modulePermissions = ref([]); // Array of { module, access }
const defaultRoute = ref("/erp/dashboard");

export function useAssignment() {
  // ─── Bootstrap ─────────────────────────────────────────────────────────────
  // Called once after login, passing the user object from /auth/employee-me.
  // The user object must have a `module_permissions` array.
  function loadAssignments(userData) {
    if (!userData?.module_permissions?.length) {
      modulePermissions.value = [];
      defaultRoute.value = "/erp/dashboard";
      return;
    }

    modulePermissions.value = userData.module_permissions;
    defaultRoute.value = userData.default_route || "/erp/dashboard";
  }

  function clearAssignment() {
    modulePermissions.value = [];
    defaultRoute.value = "/erp/dashboard";
  }

  // ─── Permission helpers ──────────────────────────────────────────────────
  /**
   * Does the employee have any access (view or edit) to this module?
   */
  function hasAccess(moduleKey) {
    return modulePermissions.value.some((p) => p.module === moduleKey);
  }

  /**
   * Does the employee have at least view access?
   */
  function canView(moduleKey) {
    return modulePermissions.value.some(
      (p) => p.module === moduleKey && (p.access === "view" || p.access === "edit"),
    );
  }

  /**
   * Does the employee have edit access?
   */
  function canEdit(moduleKey) {
    return modulePermissions.value.some(
      (p) => p.module === moduleKey && p.access === "edit",
    );
  }

  /**
   * Is this module currently view-only for the employee?
   */
  function isReadOnly(moduleKey) {
    return canView(moduleKey) && !canEdit(moduleKey);
  }

  /**
   * Check if the employee has access to any module in a given group.
   * Useful for route guards that protect entire sections (e.g. /erp/hr/*).
   */
  function hasGroupAccess(group) {
    const groupModuleKeys = ERP_MODULES.filter((m) => m.group === group).map((m) => m.key);
    return modulePermissions.value.some((p) => groupModuleKeys.includes(p.module));
  }

  // ─── Computed helpers ────────────────────────────────────────────────────
  const accessibleModules = computed(() =>
    modulePermissions.value.map((p) => p.module),
  );

  const hasAnyPermission = computed(() => modulePermissions.value.length > 0);

  // For backwards compat — components that checked hasManyAssignments
  const hasManyAssignments = computed(() => false);

  // ─── Navigation ──────────────────────────────────────────────────────────
  function getDefaultRoute() {
    return defaultRoute.value;
  }

  // ─── Deprecated stubs (prevent runtime errors in any code not yet updated) ─
  const assignments = computed(() => []);
  const activeAssignment = computed(() => null);
  const activeRoleSlug = computed(() => null);
  const activeDeptSlug = computed(() => null);
  const activeModules = computed(() => accessibleModules.value);
  const activePermissions = computed(() => []);

  function switchAssignment() {}
  function isRole() { return false; }
  function isDept() { return false; }
  function can() { return false; }
  function hasModule(moduleKey) { return hasAccess(moduleKey); }

  return {
    // New API
    modulePermissions,
    accessibleModules,
    hasAnyPermission,
    hasAccess,
    canView,
    canEdit,
    isReadOnly,
    hasGroupAccess,
    loadAssignments,
    clearAssignment,
    getDefaultRoute,

    // Deprecated — kept so existing imports don't crash
    assignments,
    activeAssignment,
    hasManyAssignments,
    activeRoleSlug,
    activeDeptSlug,
    activeModules,
    activePermissions,
    switchAssignment,
    can,
    hasModule,
    isRole,
    isDept,
  };
}
