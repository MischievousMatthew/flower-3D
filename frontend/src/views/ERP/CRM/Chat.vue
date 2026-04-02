<template>
  <div class="crm-layout">
    <div class="sidebar-container" :style="{ width: sidebarWidth }">
      <DynamicSidebar />
    </div>

    <div
      class="main-content"
      :style="{ marginLeft: isMobile ? '0' : sidebarWidth }"
    >
      <div class="top-section">
        <div class="header-left">
          <button
            class="mobile-toggle"
            @click="toggleMobile"
            aria-label="Toggle Menu"
          >
            <svg
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <div class="breadcrumbs">
            <router-link to="/erp/crm/chat">
              <svg
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
              CRM Chat
            </router-link>
            <span>/ Customer Conversations</span>
          </div>
        </div>
      </div>

      <div class="page-container">
        <ChatPanel
          chat-role="vendor"
          layout-mode="embedded"
          counterpart-label="customers"
          :can-send-messages="canEditCRM"
          :allow-new-chat="canEditCRM"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import ChatPanel from "../../../layouts/Chat/Chat.vue";
import DynamicSidebar from "../../../layouts/Sidebar/DynamicSidebar.vue";
import { useAssignment } from "../../../composables/useAssignment";
import { useSidebarState } from "../../../composables/useSidebarState";

const { canEdit } = useAssignment();
const { isCollapsed, toggleMobile } = useSidebarState();

const width = ref(window.innerWidth);
const updateWidth = () => (width.value = window.innerWidth);

onMounted(() => window.addEventListener("resize", updateWidth));
onUnmounted(() => window.removeEventListener("resize", updateWidth));

const isMobile = computed(() => width.value <= 968);
const sidebarWidth = computed(() => (isCollapsed.value ? "66px" : "250px"));
const canEditCRM = computed(() => canEdit("crm"));
</script>

<style scoped>
.crm-layout {
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
  z-index: 100;
  overflow: visible;
  transition: width 0.25s ease;
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

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
}

.mobile-toggle:hover {
  background: #f1f5f9;
  color: #1e293b;
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
  overflow: hidden;
}

@media (max-width: 968px) {
  .sidebar-container {
    width: 0 !important;
  }

  .mobile-toggle {
    display: flex;
  }

  .top-section {
    padding: 14px 20px;
  }

  .page-container {
    padding: 20px;
  }
}
</style>
