<template>
  <header class="eh">
    <button type="button" class="eh-icon-btn eh-menu" aria-label="Open menu" @click="toggleMobile" v-html="ICONS.menu"></button>

    <div class="eh-where">
      <span v-if="place" class="eh-crumb">{{ place.section.label }}</span>
      <h1 class="eh-title">{{ place?.item.label || "Workspace" }}</h1>
    </div>

    <div class="eh-actions">
      <button
        type="button"
        class="eh-icon-btn"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="setMode(isDark ? 'light' : 'dark')"
        v-html="isDark ? SUN : ICONS.moon"
      ></button>

      <router-link v-if="canChat" :to="chatRoute" class="eh-icon-btn" title="Customer chat" aria-label="Customer chat">
        <span v-html="ICONS.chat"></span>
        <span v-if="unreadChatCount > 0" class="eh-dot-badge">{{ unreadChatCount > 9 ? "9+" : unreadChatCount }}</span>
      </router-link>

      <span class="eh-divider" aria-hidden="true"></span>

      <div ref="menuRef" class="eh-user-wrap">
        <button type="button" class="eh-user" :class="{ open }" :aria-expanded="open" aria-haspopup="menu" @click="open = !open">
          <span class="eh-avatar">{{ initials }}</span>
          <span class="eh-user-text">
            <b>{{ shortName }}</b>
            <small>{{ roleLabel }}</small>
          </span>
          <span class="eh-chevron" v-html="ICONS.chevronDown"></span>
        </button>

        <Transition name="eh-drop">
          <div v-if="open" class="eh-card" role="menu">
            <div class="eh-id">
              <span class="eh-avatar lg">{{ initials }}</span>
              <div>
                <b>{{ displayName }}</b>
                <small>{{ user?.email || user?.username }}</small>
                <span class="eh-role">{{ roleLabel }}</span>
              </div>
            </div>
            <div class="eh-appearance">
              <span>Appearance</span>
              <ErpThemeToggle />
            </div>
            <nav v-if="isOwner" class="eh-links">
              <router-link :to="{ name: 'VendorFinanceDashboard' }" class="eh-item" role="menuitem" @click="open = false"><span v-html="ICONS.store"></span>Shop portal</router-link>
              <router-link :to="{ name: 'VendorStaffRoles' }" class="eh-item" role="menuitem" @click="open = false"><span v-html="ICONS.shield"></span>Roles & permissions</router-link>
            </nav>
            <button type="button" class="eh-item eh-logout" role="menuitem" :disabled="signingOut" @click="signOut">
              <span v-html="ICONS.logout"></span>{{ signingOut ? "Signing out…" : "Sign out" }}
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import { useAssignment } from "../../composables/useAssignment";
import { useChatNotifications } from "../../composables/useChatNotifications";
import { useErpTheme } from "../../composables/useErpTheme";
import { useSidebarState } from "../../composables/useSidebarState";
import { SHOP_ICONS as ICONS } from "../../components/vendor/shop/shopIcons";
import ErpThemeToggle from "../../components/hr/ErpThemeToggle.vue";
import { erpPlaceFor } from "./erpNav";

const SUN = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>`;

const route = useRoute();
const { user, logout } = useAuth();
const { canView } = useAssignment();
const { unreadChatCount, chatRoute } = useChatNotifications();
const { isDark, setMode } = useErpTheme();
const { toggleMobile } = useSidebarState();

const open = ref(false);
const menuRef = ref(null);
const signingOut = ref(false);

const place = computed(() => erpPlaceFor(route.meta.canonicalPath || route.path));
const isOwner = computed(() => user.value?.role === "vendor");
const canChat = computed(() => isOwner.value || canView("crm"));

const displayName = computed(() => {
  const u = user.value;
  if (u?.name && u?.surname) return `${u.name} ${u.surname}`;
  return u?.name || u?.username || "You";
});
const shortName = computed(() => (displayName.value.length > 18 ? `${displayName.value.slice(0, 17)}…` : displayName.value));
const initials = computed(() => displayName.value.split(/\s+/).map((w) => w[0] ?? "").slice(0, 2).join("").toUpperCase() || "U");
const roleLabel = computed(() => (isOwner.value ? "Shop owner" : user.value?.role_name || "Staff"));

async function signOut() {
  signingOut.value = true;
  try {
    await logout();
  } finally {
    signingOut.value = false;
    open.value = false;
  }
}

const onOutsideClick = (e) => {
  if (menuRef.value && !menuRef.value.contains(e.target)) open.value = false;
};
const onKey = (e) => {
  if (e.key === "Escape") open.value = false;
};
onMounted(() => {
  document.addEventListener("click", onOutsideClick);
  document.addEventListener("keydown", onKey);
});
onUnmounted(() => {
  document.removeEventListener("click", onOutsideClick);
  document.removeEventListener("keydown", onKey);
});
watch(() => route.path, () => (open.value = false));
</script>

<style scoped>
.eh {
  position: sticky;
  top: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 68px;
  padding: 10px 28px;
  background: var(--v-glass);
  backdrop-filter: saturate(180%) blur(14px);
  -webkit-backdrop-filter: saturate(180%) blur(14px);
  border-bottom: 1px solid var(--v-border);
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}
.eh *,
.eh *::before,
.eh *::after {
  box-sizing: border-box;
}
.eh-where {
  flex: 1;
  min-width: 0;
}
.eh-crumb {
  display: block;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.eh-title {
  margin: 0;
  overflow: hidden;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.eh-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.eh-icon-btn {
  position: relative;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-surface);
  color: var(--v-text-2);
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.eh-icon-btn:hover {
  background: var(--v-subtle);
  border-color: var(--v-border-strong);
  color: var(--v-text);
}
.eh-icon-btn :deep(svg) {
  width: 18px;
  height: 18px;
}
.eh-dot-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border: 2px solid var(--v-surface);
  border-radius: 999px;
  background: #e53e3e;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  line-height: 14px;
  text-align: center;
}
.eh-menu {
  display: none;
}
.eh-divider {
  width: 1px;
  height: 28px;
  margin: 0 4px;
  background: var(--v-subtle-2);
}

.eh-user-wrap {
  position: relative;
}
.eh-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 10px 4px 4px;
  border: 1px solid transparent;
  border-radius: 14px;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.eh-user:hover,
.eh-user.open {
  background: var(--v-subtle);
  border-color: var(--v-border);
}
.eh-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  font-size: 13px;
  font-weight: 600;
}
.eh-avatar.lg {
  width: 46px;
  height: 46px;
  border-radius: 14px;
  font-size: 16px;
}
.eh-user-text b,
.eh-user-text small {
  display: block;
  white-space: nowrap;
}
.eh-user-text b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.eh-user-text small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.eh-chevron {
  display: inline-flex;
  color: var(--v-faint);
  transition: transform 0.2s;
}
.eh-chevron :deep(svg) {
  width: 16px;
  height: 16px;
}
.eh-user.open .eh-chevron {
  transform: rotate(180deg);
}
.eh-card {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 300px;
  max-width: calc(100vw - 24px);
  padding: 8px;
  border: 1px solid var(--v-border);
  border-radius: 18px;
  background: var(--v-raised);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.2);
}
.eh-id {
  display: flex;
  gap: 12px;
  padding: 10px 10px 14px;
  border-bottom: 1px solid var(--v-border);
}
.eh-id > div {
  min-width: 0;
}
.eh-id b,
.eh-id small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.eh-id b {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.eh-id small {
  font-size: 12px;
  color: var(--v-faint);
}
.eh-role {
  display: inline-block;
  margin-top: 6px;
  padding: 2px 9px;
  border-radius: 999px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
  font-size: 11px;
  font-weight: 600;
}
.eh-appearance {
  display: grid;
  gap: 8px;
  padding: 12px 10px;
  border-bottom: 1px solid var(--v-border);
  font-size: 12px;
  font-weight: 600;
  color: var(--v-muted);
}
.eh-links {
  display: grid;
  padding: 6px 0;
  border-bottom: 1px solid var(--v-border);
}
.eh-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 9px 10px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--v-text-2);
  font: inherit;
  font-size: 13px;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}
.eh-item:hover {
  background: var(--v-subtle);
  color: var(--v-text);
}
.eh-item :deep(svg) {
  width: 16px;
  height: 16px;
  color: var(--v-faint);
}
.eh-logout {
  margin-top: 6px;
  color: var(--v-red-text);
}
.eh-logout :deep(svg) {
  color: currentColor;
}
.eh-logout:hover {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.eh-drop-enter-active,
.eh-drop-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.eh-drop-enter-from,
.eh-drop-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 968px) {
  .eh {
    padding: 10px 16px;
  }
  .eh-menu {
    display: grid;
  }
  .eh-user-text,
  .eh-chevron,
  .eh-divider {
    display: none;
  }
  .eh-user {
    padding: 2px;
  }
}
@media (max-width: 640px) {
  .eh-crumb {
    display: none;
  }
}
</style>
