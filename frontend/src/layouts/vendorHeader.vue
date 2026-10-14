<template>
  <header class="vh">
    <button type="button" class="vh-icon-btn vh-menu" aria-label="Open menu" @click="toggleMobile" v-html="ICONS.menu"></button>

    <!-- Where you are -->
    <div class="vh-where">
      <span v-if="place" class="vh-crumb">{{ place.section.label }}</span>
      <h1 class="vh-title">{{ title || place?.item.label || "Vendor" }}</h1>
    </div>

    <div class="vh-actions">
      <label v-if="showSearch" class="vh-search">
        <span v-html="ICONS.search"></span>
        <input
          type="search"
          :placeholder="searchPlaceholder"
          :value="modelValue"
          :aria-label="searchPlaceholder"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </label>

      <!-- Parent-injected buttons -->
      <slot name="actions" />

      <router-link :to="{ name: 'VendorSupport' }" class="vh-icon-btn" title="Help & support" aria-label="Help & support" v-html="ICONS.help"></router-link>

      <!-- Announcements and support replies (store owners only) -->
      <NotificationBell v-if="user?.role === 'vendor'" support-path="/vendor/support" :all-path="{ name: 'VendorNotifications' }" />

      <router-link :to="chatRoute" class="vh-icon-btn" :class="{ on: isChatRoute }" title="Customer chat" aria-label="Customer chat">
        <span v-html="ICONS.chat"></span>
        <span v-if="unreadChatCount > 0" class="vh-dot-badge">{{ unreadChatCount > 9 ? "9+" : unreadChatCount }}</span>
      </router-link>

      <span class="vh-divider" aria-hidden="true"></span>

      <!-- Account menu -->
      <div ref="dropdownRef" class="vh-user-wrap">
        <button type="button" class="vh-user" :class="{ open: showDropdown }" :aria-expanded="showDropdown" aria-haspopup="menu" @click="toggleDropdown">
          <span class="vh-avatar">
            <img v-if="resolvedAvatar" :src="resolvedAvatar" :alt="displayName" @error="avatarFailed = true" />
            <span v-else>{{ initials }}</span>
          </span>
          <span class="vh-user-text">
            <b>{{ shortName }}</b>
            <small>{{ planLabel }}</small>
          </span>
          <span class="vh-chevron" v-html="ICONS.chevronDown"></span>
        </button>

        <Transition name="drop">
          <div v-if="showDropdown" class="vh-menu-card" role="menu">
            <div class="vh-menu-id">
              <span class="vh-avatar lg">
                <img v-if="resolvedAvatar" :src="resolvedAvatar" :alt="displayName" @error="avatarFailed = true" />
                <span v-else>{{ initials }}</span>
              </span>
              <div>
                <b>{{ displayName }}</b>
                <small>{{ userEmail }}</small>
                <span class="vh-plan" :class="statusClass">{{ planLabel }}<template v-if="subscriptionStatus"> · {{ subscriptionStatus }}</template></span>
              </div>
            </div>
            <nav class="vh-menu-links">
              <router-link v-for="l in MENU" :key="l.name" :to="{ name: l.name }" class="vh-menu-item" role="menuitem" @click="showDropdown = false">
                <span v-html="ICONS[l.icon]"></span>{{ l.label }}
              </router-link>
            </nav>
            <button type="button" class="vh-menu-item vh-logout" role="menuitem" :disabled="isLoggingOut" @click="handleLogout">
              <span v-html="ICONS.logout"></span>{{ isLoggingOut ? "Signing out…" : "Sign out" }}
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { useChatNotifications } from "../composables/useChatNotifications";
import { useSidebarState } from "../composables/useSidebarState";
import { useSubscriptionAccess } from "../composables/useSubscriptionAccess";
import { SHOP_ICONS as ICONS } from "../components/vendor/shop/shopIcons";
import { navPlaceFor } from "./Sidebar/vendorNav";
import NotificationBell from "../components/support/NotificationBell.vue";
import api from "../plugins/axios";

defineProps({
  title: { type: String, default: "" },
  showSearch: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: "Search name or SKU..." },
  modelValue: { type: String, default: "" },
});
defineEmits(["update:modelValue"]);

const MENU = [
  { name: "VendorProfile", label: "Profile", icon: "user" },
  { name: "VendorSubscription", label: "Subscription & Billing", icon: "sparkle" },
  { name: "VendorShopSettings", label: "Shop settings", icon: "gear" },
  { name: "VendorSecurity", label: "Security", icon: "shield" },
  { name: "VendorSupport", label: "Help & support", icon: "help" },
];

const route = useRoute();
const router = useRouter();
const { user, logout } = useAuth();
const { unreadChatCount, chatRoute } = useChatNotifications();
const { toggleMobile } = useSidebarState();
const { subscriptionAccess, loadSubscriptionAccess } = useSubscriptionAccess();

const showDropdown = ref(false);
const dropdownRef = ref(null);
const isLoggingOut = ref(false);
const avatarFailed = ref(false);
const vendorProfile = ref(null);

const place = computed(() => navPlaceFor(route.name));

const loadVendorProfile = async () => {
  try {
    const res = await api.get("/vendor/profile");
    if (res.data?.success || res.data?.data) vendorProfile.value = res.data.data ?? res.data;
  } catch (_) {
    // Falls back to the signed-in user's details
  }
};

const displayName = computed(() => {
  const vp = vendorProfile.value;
  if (vp?.owner_name) return vp.owner_name;
  if (vp?.name) return vp.name;
  const u = user.value;
  if (u?.name && u?.surname) return `${u.name} ${u.surname}`;
  return u?.name || u?.email?.split("@")[0] || "Vendor";
});
const shortName = computed(() => (displayName.value.length > 18 ? `${displayName.value.slice(0, 17)}…` : displayName.value));
const initials = computed(
  () => displayName.value.split(" ").map((w) => w[0] ?? "").slice(0, 2).join("").toUpperCase() || "V",
);
const userEmail = computed(() => vendorProfile.value?.email || user.value?.email || "");
const resolvedAvatar = computed(() => {
  if (avatarFailed.value) return "";
  const vp = vendorProfile.value;
  if (vp?.store_logo_url) return vp.store_logo_url;
  if (vp?.profile_photo_url) return vp.profile_photo_url;
  const pic = user.value?.profile_picture;
  if (pic) return pic.startsWith("http") ? pic : `/storage/${pic}`;
  return "";
});

const planLabel = computed(() => {
  const a = subscriptionAccess.value;
  if (a?.plan_name) return `${a.plan_name} plan`;
  return a ? "No plan" : "Vendor";
});
const subscriptionStatus = computed(() => {
  const a = subscriptionAccess.value;
  if (!a?.status) return "";
  if (a.status === "trialing") return "Trial";
  return a.status.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
});
const statusClass = computed(() => {
  const a = subscriptionAccess.value;
  if (!a) return "";
  if (!a.subscription_active) return "off";
  return a.status === "trialing" ? "trial" : "on";
});

const isChatRoute = computed(() => router.currentRoute.value.path.startsWith(chatRoute.value));

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
  if (showDropdown.value) loadSubscriptionAccess().catch(() => {});
};
const onOutsideClick = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) showDropdown.value = false;
};
const onKey = (e) => {
  if (e.key === "Escape") showDropdown.value = false;
};

onMounted(() => {
  document.addEventListener("click", onOutsideClick);
  document.addEventListener("keydown", onKey);
  loadSubscriptionAccess().catch(() => {});
});
onUnmounted(() => {
  document.removeEventListener("click", onOutsideClick);
  document.removeEventListener("keydown", onKey);
});
watch(user, (u) => u && loadVendorProfile(), { immediate: true });

const handleLogout = async () => {
  if (isLoggingOut.value) return;
  isLoggingOut.value = true;
  try {
    await logout();
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    isLoggingOut.value = false;
    showDropdown.value = false;
  }
};
</script>

<style scoped>
.vh {
  position: sticky;
  top: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 68px;
  padding: 10px 28px;
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: saturate(180%) blur(14px);
  -webkit-backdrop-filter: saturate(180%) blur(14px);
  border-bottom: 1px solid #edf0f3;
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
}
.vh *,
.vh *::before,
.vh *::after {
  box-sizing: border-box;
}
.vh-where {
  flex: 1;
  min-width: 0;
}
.vh-crumb {
  display: block;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #a0aab8;
}
.vh-title {
  margin: 0;
  overflow: hidden;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: #1a202c;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vh-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Round icon buttons */
.vh-icon-btn {
  position: relative;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 1px solid #edf0f3;
  border-radius: 12px;
  background: #fff;
  color: #5b6474;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.vh-icon-btn:hover,
.vh-icon-btn.on {
  background: #f5f7f9;
  border-color: #e2e8f0;
  color: #1a202c;
}
.vh-icon-btn :deep(svg) {
  width: 18px;
  height: 18px;
}
.vh-dot-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border: 2px solid #fff;
  border-radius: 999px;
  background: #e53e3e;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  line-height: 14px;
  text-align: center;
}
.vh-menu {
  display: none;
}

/* Search */
.vh-search {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #edf0f3;
  border-radius: 12px;
  background: #f7f9fa;
  color: #a0aab8;
}
.vh-search:focus-within {
  border-color: #cbd5e0;
  background: #fff;
}
.vh-search :deep(svg) {
  width: 16px;
  height: 16px;
}
.vh-search input {
  width: 220px;
  border: 0;
  outline: 0;
  background: none;
  color: #1a202c;
  font: inherit;
  font-size: 13px;
}

.vh-divider {
  width: 1px;
  height: 28px;
  margin: 0 4px;
  background: #edf0f3;
}

/* Account */
.vh-user-wrap {
  position: relative;
}
.vh-user {
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
.vh-user:hover,
.vh-user.open {
  background: #f7f9fa;
  border-color: #edf0f3;
}
.vh-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  overflow: hidden;
  border-radius: 11px;
  background: #2d3748;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
}
.vh-avatar.lg {
  width: 46px;
  height: 46px;
  border-radius: 14px;
  font-size: 16px;
}
.vh-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.vh-user-text b,
.vh-user-text small {
  display: block;
  white-space: nowrap;
}
.vh-user-text b {
  font-size: 13px;
  font-weight: 600;
  color: #1a202c;
}
.vh-user-text small {
  font-size: 11.5px;
  color: #8a94a6;
}
.vh-chevron {
  display: inline-flex;
  color: #a0aab8;
  transition: transform 0.2s;
}
.vh-chevron :deep(svg) {
  width: 16px;
  height: 16px;
}
.vh-user.open .vh-chevron {
  transform: rotate(180deg);
}

.vh-menu-card {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 290px;
  padding: 8px;
  border: 1px solid #edf0f3;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.16);
}
.vh-menu-id {
  display: flex;
  gap: 12px;
  padding: 10px 10px 14px;
  border-bottom: 1px solid #f1f4f6;
}
.vh-menu-id > div {
  min-width: 0;
}
.vh-menu-id b,
.vh-menu-id small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vh-menu-id b {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
}
.vh-menu-id small {
  font-size: 12px;
  color: #8a94a6;
}
.vh-plan {
  display: inline-block;
  background: #f1f5f9;
  color: #64748b;
  margin-top: 6px;
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}
.vh-plan.on { background: #e6f9ee; color: #22753f; }
.vh-plan.trial { background: #f3f0ff; color: #6b46c1; }
.vh-plan.off { background: #fff1f1; color: #c53030; }
.vh-menu-links {
  display: grid;
  padding: 6px 0;
  border-bottom: 1px solid #f1f4f6;
}
.vh-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 9px 10px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: #4a5568;
  font: inherit;
  font-size: 13px;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}
.vh-menu-item:hover {
  background: #f7f9fa;
  color: #1a202c;
}
.vh-menu-item :deep(svg) {
  width: 16px;
  height: 16px;
  color: #8a94a6;
}
.vh-logout {
  margin-top: 6px;
  color: #c53030;
}
.vh-logout :deep(svg) {
  color: currentColor;
}
.vh-logout:hover {
  background: #fff5f5;
  color: #c53030;
}
.drop-enter-active,
.drop-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.drop-enter-from,
.drop-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 968px) {
  .vh {
    padding: 10px 16px;
  }
  .vh-menu {
    display: grid;
  }
  .vh-user-text,
  .vh-chevron,
  .vh-divider {
    display: none;
  }
  .vh-user {
    padding: 2px;
  }
}
@media (max-width: 640px) {
  .vh-search {
    display: none;
  }
  .vh-crumb {
    display: none;
  }
}
</style>
