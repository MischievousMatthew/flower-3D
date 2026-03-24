// src/composables/useAssignment.js
//
// Drop this file in your composables folder.
// Import it in any sidebar to get the active assignment state.
//
// The composable reads the assignments array that your /auth/me endpoint
// already returns (from the multi-assignment work we did earlier).
// If you haven't wired that up yet it also reads from localStorage as a
// fallback so the sidebar dropdown still works.

import { ref, computed } from "vue";

// ─── Module-level singletons ──────────────────────────────────────────────────
// Defined outside the function so every component that calls useAssignment()
// shares the same reactive state — no prop-drilling needed.
const assignments = ref([]); // All department-role assignments for this employee
const activeAssignment = ref(null); // The currently selected one

const STORAGE_KEY = "active_assignment_id";

// ─── Route map ────────────────────────────────────────────────────────────────
// Maps a role slug to the default landing path when the user switches context.
const ROLE_ROUTE_MAP = {
  "hr-manager": "/erp/hr",
  "finance-manager": "/erp/finance/dashboard",
  "inventory-manager": "/erp/procurement/inventory/funding-request",
  "supply-chain-coordinator": "/erp/procurement/supply-chain/dashboard",
  "crm-specialist": "/erp/crm/dashboard",
};

export function useAssignment() {
  // ─── Bootstrap ─────────────────────────────────────────────────────────────
  // Call this once after login, passing the user object from /auth/me.
  // The user object must have an `assignments` array shaped like:
  // [{ id, label, is_primary, department: { name, slug }, role: { name, slug } }]
  function loadAssignments(userData) {
    if (!userData?.assignments?.length) {
      // Fallback: build a single synthetic assignment from flat dept/role fields
      // (supports employees created before the multi-assignment system)
      if (userData?.department && userData?.role) {
        const synthetic = {
          id: null,
          label: `${userData.department} — ${userData.role}`,
          is_primary: true,
          department: {
            name: userData.department,
            slug: userData.department?.toLowerCase(),
          },
          role: { name: userData.role, slug: slugify(userData.role) },
        };
        assignments.value = [synthetic];
        activeAssignment.value = synthetic;
      }
      return;
    }

    assignments.value = userData.assignments;

    // Restore the last-used assignment from localStorage, or fall back to
    // the one marked is_primary, or the very first one.
    const storedId = parseInt(localStorage.getItem(STORAGE_KEY));
    const restored =
      storedId && assignments.value.find((a) => a.id === storedId);
    const primary = assignments.value.find((a) => a.is_primary);

    activeAssignment.value = restored || primary || assignments.value[0];
    _persist();
  }

  // ─── Switch ─────────────────────────────────────────────────────────────────
  // Call this when the user picks a different assignment from the dropdown.
  function switchAssignment(assignmentId) {
    const found = assignments.value.find((a) => a.id === assignmentId);
    if (!found) return;
    activeAssignment.value = found;
    _persist();
  }

  function clearAssignment() {
    assignments.value = [];
    activeAssignment.value = null;
    localStorage.removeItem(STORAGE_KEY);
  }

  function _persist() {
    if (activeAssignment.value?.id) {
      localStorage.setItem(STORAGE_KEY, activeAssignment.value.id);
    }
  }

  // ─── Computed helpers ────────────────────────────────────────────────────────
  const hasManyAssignments = computed(() => assignments.value.length > 1);
  const activeRoleSlug = computed(
    () => activeAssignment.value?.role?.slug ?? null,
  );
  const activeDeptSlug = computed(
    () => activeAssignment.value?.department?.slug ?? null,
  );

  const activeModules = computed(() => activeAssignment.value?.role?.modules ?? []);
  const activePermissions = computed(() => activeAssignment.value?.permissions ?? []);

  // ─── Permission helpers ──────────────────────────────────────────────────────
  function can(permissionSlug) {
    return activePermissions.value.includes(permissionSlug);
  }

  function hasModule(moduleSlug) {
    return activeModules.value.includes(moduleSlug);
  }

  function isRole(...slugs) {
    return slugs.includes(activeRoleSlug.value);
  }

  function isDept(...slugs) {
    return slugs.includes(activeDeptSlug.value);
  }

  function getDefaultRoute() {
    return ROLE_ROUTE_MAP[activeRoleSlug.value] ?? "/erp/dashboard";
  }

  return {
    assignments,
    activeAssignment,
    hasManyAssignments,
    activeRoleSlug,
    activeDeptSlug,
    activeModules,
    activePermissions,
    loadAssignments,
    switchAssignment,
    clearAssignment,
    can,
    hasModule,
    isRole,
    isDept,
    getDefaultRoute,
  };
}

// ─── Helper ───────────────────────────────────────────────────────────────────
function slugify(str) {
  return str?.toLowerCase().replace(/\s+/g, "-") ?? "";
}
