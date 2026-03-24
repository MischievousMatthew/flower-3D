<template>
  <div class="hr-layout">
    <div class="sidebar-container" :style="{ width: sidebarWidth }">
      <DynamicSidebar />
    </div>

    <div class="main-content" :style="{ marginLeft: sidebarWidth }">
      <div class="top-section">
        <div class="breadcrumbs">
          <router-link to="/erp/hr">
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            </svg>
            HR Portal
          </router-link>
          <span>/ {{ currentPage }}</span>
        </div>
      </div>
      <div class="page-container">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import DynamicSidebar from "../../../layouts/Sidebar/DynamicSidebar.vue";
import { useSidebarState } from "../../../composables/useSidebarState";

const route = useRoute();
const { isCollapsed } = useSidebarState();

// +2px accounts for the toggle button that pokes out of sidebar-outer
const sidebarWidth = computed(() => (isCollapsed.value ? "66px" : "250px"));

const currentPage = computed(() => {
  const path = route.path.split("/").pop() || "Dashboard";
  return (
    path.charAt(0).toUpperCase() + path.slice(1).replace(/([A-Z])/g, " $1")
  );
});
</script>

<style scoped>
.hr-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}

.sidebar-container {
  flex-shrink: 0;
  position: fixed;
  left: 0;
  top: 0;
  height: 100vh;
  /* z-index below the toggle button (z-index 300) but above page content */
  z-index: 100;
  overflow: visible;
  transition: width 0.25s ease;
  /* Extra right padding so the toggle circle (right:-12px on sidebar-outer)
     falls within the container's interactive hit area */
}

.main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: #f8fafc;
  transition: margin-left 0.25s ease;
}

.top-section {
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  padding: 18px 32px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: #64748b;
}

.breadcrumbs a {
  color: #475569;
  text-decoration: none;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}
.breadcrumbs a:hover {
  color: #48bb78;
}

.page-container {
  padding: 24px 32px;
  flex: 1;
  overflow-y: auto;
}
</style>
