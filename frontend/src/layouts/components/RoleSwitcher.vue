<template>
  <!--
    RoleSwitcher.vue
    ─────────────────
    Drop this inside any sidebar, right below the <div class="sidebar-header">.

    It renders nothing when the employee has only one assignment.
    When they have multiple it shows a styled dropdown to switch context.

    Usage:
      <RoleSwitcher @switched="onSwitched" />

    The parent sidebar listens to @switched and calls router.push() if needed.
  -->
  <div class="role-switcher" v-if="assignments.length > 0" ref="rootEl">
    <!-- Single assignment — just show a static badge, no dropdown -->
    <div class="assignment-badge solo" v-if="!hasManyAssignments">
      <span class="badge-dot" :style="{ background: deptColor }"></span>
      <div class="badge-text">
        <span class="badge-dept">{{ activeAssignment?.department?.name }}</span>
        <span class="badge-role">{{ activeAssignment?.role?.name }}</span>
      </div>
    </div>

    <!-- Multiple assignments — interactive dropdown -->
    <div class="switcher-wrap" v-else>
      <button
        class="switcher-trigger"
        :class="{ open: dropdownOpen }"
        @click="dropdownOpen = !dropdownOpen"
        type="button"
      >
        <span class="badge-dot" :style="{ background: deptColor }"></span>
        <div class="badge-text">
          <span class="badge-dept">{{
            activeAssignment?.department?.name
          }}</span>
          <span class="badge-role">{{ activeAssignment?.role?.name }}</span>
        </div>
        <svg
          class="trigger-chevron"
          :class="{ rotated: dropdownOpen }"
          xmlns="http://www.w3.org/2000/svg"
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
        >
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>

      <Transition name="dropdown">
        <div class="dropdown-panel" v-if="dropdownOpen">
          <div class="dropdown-hint">Switch context</div>

          <button
            v-for="a in assignments"
            :key="a.id ?? a.label"
            class="dropdown-item"
            :class="{ active: isActive(a) }"
            type="button"
            @click="select(a)"
          >
            <span
              class="item-dot"
              :style="{ background: getDeptColor(a.department?.slug) }"
            ></span>
            <div class="item-text">
              <span class="item-dept">{{ a.department?.name }}</span>
              <span class="item-role">{{ a.role?.name }}</span>
            </div>
            <svg
              v-if="isActive(a)"
              class="item-check"
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </button>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useAssignment } from "../../composables/useAssignment";

const emit = defineEmits(["switched"]);

const router = useRouter();
const {
  assignments,
  activeAssignment,
  hasManyAssignments,
  switchAssignment,
  getDefaultRoute,
} = useAssignment();

const dropdownOpen = ref(false);
const rootEl = ref(null);

// ── Color map (dept slug → accent color) ─────────────────────────────────────
const DEPT_COLORS = {
  hr: "#10b981",
  finance: "#3b82f6",
  procurement: "#f59e0b",
  crm: "#8b5cf6",
};

function getDeptColor(slug) {
  return DEPT_COLORS[slug] ?? "#6b7280";
}

const deptColor = computed(() =>
  getDeptColor(activeAssignment.value?.department?.slug),
);

// ── Helpers ───────────────────────────────────────────────────────────────────
function isActive(a) {
  return a.id != null
    ? a.id === activeAssignment.value?.id
    : a.label === activeAssignment.value?.label;
}

function select(assignment) {
  if (isActive(assignment)) {
    dropdownOpen.value = false;
    return;
  }
  switchAssignment(assignment.id);
  dropdownOpen.value = false;
  emit("switched", assignment);
  // Navigate to the default landing page for the new role
  router.push(getDefaultRoute());
}

// ── Close on outside click ────────────────────────────────────────────────────
function onOutsideClick(e) {
  if (rootEl.value && !rootEl.value.contains(e.target)) {
    dropdownOpen.value = false;
  }
}

onMounted(() => document.addEventListener("mousedown", onOutsideClick));
onUnmounted(() => document.removeEventListener("mousedown", onOutsideClick));
</script>

<style scoped>
.role-switcher {
  padding: 0 12px 4px;
}

/* ── Shared badge layout ─────────────────────────────────────────────────── */
.assignment-badge,
.switcher-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 10px;
  font-family: "Poppins", sans-serif;
}

/* Static solo badge */
.assignment-badge.solo {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  cursor: default;
}

/* Interactive trigger */
.switcher-trigger {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}
.switcher-trigger:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}
.switcher-trigger.open {
  background: #f0f4ff;
  border-color: #c7d2fe;
}

/* Colored dot */
.badge-dot,
.item-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Text block */
.badge-text,
.item-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex: 1;
  min-width: 0;
  text-align: left;
}
.badge-dept,
.item-dept {
  font-size: 12px;
  font-weight: 600;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.badge-role,
.item-role {
  font-size: 11px;
  color: #6b7280;
  font-weight: 400;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Chevron */
.trigger-chevron {
  color: #9ca3af;
  flex-shrink: 0;
  transition: transform 0.25s;
}
.trigger-chevron.rotated {
  transform: rotate(180deg);
}

/* ── Dropdown panel ──────────────────────────────────────────────────────── */
.switcher-wrap {
  position: relative;
}

.dropdown-panel {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  z-index: 300;
  overflow: hidden;
}

.dropdown-hint {
  padding: 8px 14px 4px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  color: #9ca3af;
  font-family: "Poppins", sans-serif;
}

.dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: background 0.15s;
  font-family: "Poppins", sans-serif;
}
.dropdown-item:hover {
  background: #f9fafb;
}
.dropdown-item.active {
  background: #f0fdf4;
}

.item-check {
  color: #10b981;
  flex-shrink: 0;
}

/* ── Transition ──────────────────────────────────────────────────────────── */
.dropdown-enter-active,
.dropdown-leave-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
