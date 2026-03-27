<template>
  <!-- Full-screen overlay while logging out -->
  <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

  <header class="vendor-header">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <!-- Left: page title -->
    <h1 class="page-title">{{ title }}</h1>

    <!-- Right: actions + user pill -->
    <div class="header-actions">
      <!-- Optional search -->
      <div v-if="showSearch" class="search-box">
        <svg
          class="search-icon"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
        >
          <circle
            cx="11"
            cy="11"
            r="8"
            stroke="currentColor"
            stroke-width="1.8"
          />
          <path
            d="m21 21-4.35-4.35"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
          />
        </svg>
        <input
          type="text"
          :placeholder="searchPlaceholder"
          :value="modelValue"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </div>

      <!-- Parent-injected icon buttons -->
      <slot name="actions" />

      <!-- Slim divider -->
      <div class="header-divider" />

      <!-- ── User pill ──────────────────────────────── -->
      <div class="user-pill-wrap" ref="dropdownRef">
        <button
          class="user-pill"
          :class="{ active: showDropdown }"
          @click="toggleDropdown"
        >
          <!-- Avatar -->
          <div class="pill-avatar-wrap">
            <img
              v-if="resolvedAvatar"
              :src="resolvedAvatar"
              :alt="displayName"
              class="pill-avatar-img"
              @error="avatarFailed = true"
            />
            <div v-else class="pill-avatar-fallback">{{ initials }}</div>
            <span class="pill-online-dot" />
          </div>

          <!-- Name + role -->
          <div class="pill-info">
            <span class="pill-name">{{ shortName }}</span>
            <span class="pill-role">VENDOR</span>
          </div>

          <!-- Chevron -->
          <svg
            class="pill-chevron"
            :class="{ open: showDropdown }"
            width="14"
            height="14"
            viewBox="0 0 14 14"
            fill="none"
          >
            <path
              d="M2.5 5L7 9.5L11.5 5"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>

        <!-- ── Dropdown ───────────────────────────────── -->
        <transition name="drop">
          <div v-if="showDropdown" class="drop-card">
            <!-- Identity header -->
            <div class="drop-identity">
              <div class="drop-avatar-ring">
                <img
                  v-if="resolvedAvatar"
                  :src="resolvedAvatar"
                  :alt="displayName"
                  class="drop-avatar-img"
                  @error="avatarFailed = true"
                />
                <div v-else class="drop-avatar-fallback">{{ initials }}</div>
              </div>
              <div class="drop-meta">
                <p class="drop-name">{{ displayName }}</p>
                <p class="drop-email">{{ userEmail }}</p>
                <div class="drop-badges">
                  <span class="badge-role">Vendor</span>
                  <span class="badge-plan" :class="planClass">{{
                    planLabel
                  }}</span>
                </div>
              </div>
            </div>

            <!-- Profile link -->
            <div class="drop-body">
              <router-link
                to="/vendor/profile"
                class="drop-item"
                @click="showDropdown = false"
              >
                <span class="drop-item-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                    <path
                      d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <circle
                      cx="12"
                      cy="7"
                      r="4"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </span>
                <span>Profile</span>
                <svg
                  class="item-arrow"
                  width="12"
                  height="12"
                  viewBox="0 0 12 12"
                  fill="none"
                >
                  <path
                    d="M4.5 2.5L8 6L4.5 9.5"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </router-link>
            </div>

            <!-- Logout -->
            <div class="drop-footer">
              <button class="drop-logout-btn" @click="handleLogout">
                <span class="drop-item-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                    <path
                      d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <polyline
                      points="16 17 21 12 16 7"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <line
                      x1="21"
                      y1="12"
                      x2="9"
                      y2="12"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </span>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { useSidebarState } from "../composables/useSidebarState";
import api from "../plugins/axios";
import { toast } from "vue3-toastify";
import LoadingOverlay from "../layouts/components/LoadingOverlay.vue";

const props = defineProps({
  title: { type: String, default: "" },
  showSearch: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: "Search name or SKU..." },
  modelValue: { type: String, default: "" },
});
defineEmits(["update:modelValue"]);

const router = useRouter();
const { user, logout } = useAuth();
const { toggleMobile } = useSidebarState();

const showDropdown = ref(false);
const dropdownRef = ref(null);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const avatarFailed = ref(false);
const vendorProfile = ref(null);

// ── Load enriched vendor profile ─────────────────────────────────────────
const loadVendorProfile = async () => {
  try {
    const res = await api.get("/vendor/profile");
    if (res.data?.success || res.data?.data) {
      vendorProfile.value = res.data.data ?? res.data;
    }
  } catch {}
};

// ── Computed ──────────────────────────────────────────────────────────────
const displayName = computed(() => {
  const vp = vendorProfile.value;
  if (vp?.owner_name) return vp.owner_name;
  if (vp?.name) return vp.name;
  const u = user.value;
  if (u?.name && u?.surname) return `${u.name} ${u.surname}`;
  return u?.name || u?.email?.split("@")[0] || "Vendor";
});

const shortName = computed(() => {
  const n = displayName.value;
  return n.length > 17 ? n.slice(0, 16) + "…" : n;
});

const initials = computed(
  () =>
    displayName.value
      .split(" ")
      .map((w) => w[0] ?? "")
      .slice(0, 2)
      .join("")
      .toUpperCase() || "V",
);

const userEmail = computed(
  () => vendorProfile.value?.email || user.value?.email || "",
);

const resolvedAvatar = computed(() => {
  if (avatarFailed.value) return "";
  const vp = vendorProfile.value;
  if (vp?.store_logo_url) return vp.store_logo_url;
  if (vp?.profile_photo_url) return vp.profile_photo_url;
  const pic = user.value?.profile_picture;
  if (pic) return pic.startsWith("http") ? pic : `/storage/${pic}`;
  return "";
});

const planLabel = computed(
  () =>
    vendorProfile.value?.plan ||
    user.value?.plan ||
    user.value?.subscription_plan ||
    "Free Plan",
);

const planClass = computed(() => {
  const p = planLabel.value.toLowerCase();
  if (p.includes("pro")) return "plan-pro";
  if (p.includes("biz") || p.includes("business")) return "plan-biz";
  return "plan-free";
});

// ── Dropdown ──────────────────────────────────────────────────────────────
const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
  if (showDropdown.value && !vendorProfile.value) loadVendorProfile();
};

const onOutsideClick = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target))
    showDropdown.value = false;
};
onMounted(() => {
  document.addEventListener("click", onOutsideClick);
  loadVendorProfile();
});
onUnmounted(() => document.removeEventListener("click", onOutsideClick));
watch(
  user,
  (u) => {
    if (u) loadVendorProfile();
  },
  { immediate: true },
);

// ── Logout — LoadingOverlay handles all visual feedback ───────────────────
const handleLogout = async () => {
  if (isLoading.value) return;

  isLoading.value = true;
  isLoadingMessage.value = "Logging out...";
  showDropdown.value = false;

  try {
    await logout();
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    isLoading.value = false;
  }
};
</script>

<style scoped>
* {
  font-family:
    "Poppins",
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
  box-sizing: border-box;
}

/* ── Sticky header ───────────────────────────────────── */
.vendor-header {
  position: sticky;
  top: 0;
  z-index: 200;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 28px;
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(226, 232, 240, 0.8);
  box-shadow:
    0 1px 3px rgba(0, 0, 0, 0.04),
    0 4px 16px rgba(0, 0, 0, 0.06),
    0 1px 0 rgba(255, 255, 255, 0.9) inset;
}

.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #4a5568;
  cursor: pointer;
  padding: 8px;
  margin-right: 12px;
  border-radius: 8px;
  transition: background-color 0.2s;
}

.mobile-toggle:hover {
  background-color: #f7fafc;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
  letter-spacing: -0.3px;
}

/* ── Right section ───────────────────────────────────── */
.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-divider {
  width: 1px;
  height: 28px;
  background: #e2e8f0;
  border-radius: 1px;
  margin: 0 2px;
}

/* ── Search ──────────────────────────────────────────── */
.search-box {
  position: relative;
  display: flex;
  align-items: center;
}
.search-icon {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}
.search-box input {
  width: 260px;
  padding: 9px 14px 9px 36px;
  border: 1.5px solid #e8edf5;
  border-radius: 9px;
  font-size: 13.5px;
  background: #f8fafd;
  color: #1a202c;
  font-family: inherit;
  transition: all 0.2s;
}
.search-box input:focus {
  outline: none;
  border-color: #48bb78;
  background: white;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.12);
}
.search-box input::placeholder {
  color: #b0bec5;
}

/* ── User pill ───────────────────────────────────────── */
.user-pill-wrap {
  position: relative;
}

.user-pill {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 6px 12px 6px 5px;
  background: white;
  border: 1.5px solid #e8edf5;
  border-radius: 40px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.user-pill:hover,
.user-pill.active {
  border-color: #48bb78;
  background: #f5fdf8;
  box-shadow: 0 2px 10px rgba(72, 187, 120, 0.15);
}

/* Avatar */
.pill-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}
.pill-avatar-img {
  width: 33px;
  height: 33px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  border: 2px solid #e8f5e9;
}
.pill-avatar-fallback {
  width: 33px;
  height: 33px;
  border-radius: 50%;
  background: linear-gradient(135deg, #48bb78, #38a169);
  color: white;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #e8f5e9;
}
.pill-online-dot {
  position: absolute;
  bottom: 1px;
  right: 0;
  width: 9px;
  height: 9px;
  background: #48bb78;
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 0 0 1px rgba(72, 187, 120, 0.3);
}

/* Name + role */
.pill-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
.pill-name {
  font-size: 13px;
  font-weight: 600;
  color: #1a202c;
  line-height: 1.25;
  max-width: 120px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pill-role {
  font-size: 9.5px;
  font-weight: 600;
  color: #48bb78;
  letter-spacing: 0.6px;
}

/* Chevron */
.pill-chevron {
  color: #94a3b8;
  transition: transform 0.25s;
  flex-shrink: 0;
}
.pill-chevron.open {
  transform: rotate(180deg);
  color: #48bb78;
}

/* ── Dropdown card ─────────────────────────────────────── */
.drop-card {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 296px;
  background: white;
  border-radius: 16px;
  box-shadow:
    0 0 0 1px rgba(0, 0, 0, 0.05),
    0 4px 8px rgba(0, 0, 0, 0.04),
    0 16px 40px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  z-index: 9999;
}

/* Identity */
.drop-identity {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 20px 18px 18px;
  background: linear-gradient(140deg, #f0fff5 0%, #f9fef9 60%, #fff 100%);
  border-bottom: 1px solid #edf5ed;
}

.drop-avatar-ring {
  flex-shrink: 0;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  overflow: hidden;
  box-shadow:
    0 0 0 2px white,
    0 0 0 4px #a7f3d0;
}
.drop-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.drop-avatar-fallback {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #48bb78, #38a169);
  color: white;
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.drop-meta {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.drop-name {
  font-size: 15px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.drop-email {
  font-size: 11.5px;
  color: #94a3b8;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.drop-badges {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.badge-role {
  display: inline-block;
  padding: 2px 9px;
  background: white;
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
}
.badge-plan {
  display: inline-block;
  padding: 2px 9px;
  font-size: 11px;
  font-weight: 700;
  border-radius: 20px;
}
.plan-free {
  background: #f0fff4;
  color: #38a169;
  border: 1.5px solid #c6f6d5;
}
.plan-pro {
  background: #fffbeb;
  color: #d97706;
  border: 1.5px solid #fde68a;
}
.plan-biz {
  background: #eff6ff;
  color: #2563eb;
  border: 1.5px solid #bfdbfe;
}

/* Profile link */
.drop-body {
  padding: 6px 8px;
}

.drop-item {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  color: #374151;
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
  border-radius: 10px;
  transition:
    background 0.15s,
    color 0.15s;
  cursor: pointer;
}
.drop-item:hover {
  background: #f0faf4;
  color: #1a2e1a;
}
.drop-item:hover .item-arrow {
  opacity: 1;
  transform: translateX(2px);
}

.drop-item-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #4b5563;
  transition: background 0.15s;
}
.drop-item:hover .drop-item-icon {
  background: #d1fae5;
  color: #16a34a;
}

.item-arrow {
  margin-left: auto;
  color: #cbd5e0;
  opacity: 0;
  transition: all 0.2s;
  flex-shrink: 0;
}

/* Logout */
.drop-footer {
  border-top: 1px solid #f1f5f1;
  padding: 6px 8px 8px;
}

.drop-logout-btn {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  width: 100%;
  color: #e53e3e;
  font-size: 13.5px;
  font-weight: 500;
  border-radius: 10px;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
  font-family: inherit;
  text-align: left;
}
.drop-logout-btn:hover {
  background: #fff5f5;
}
.drop-logout-btn .drop-item-icon {
  color: #e53e3e;
  background: #fee2e2;
}
.drop-logout-btn:hover .drop-item-icon {
  background: #fecaca;
}

/* ── Dropdown transition ─────────────────────────────── */
.drop-enter-active {
  transition:
    opacity 0.18s ease,
    transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.drop-leave-active {
  transition:
    opacity 0.12s ease,
    transform 0.12s ease;
}
.drop-enter-from,
.drop-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.97);
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 768px) {
  .vendor-header {
    padding: 12px 16px;
  }
  .mobile-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .pill-info {
    display: none;
  }
  .user-pill {
    padding: 5px;
    border-radius: 50%;
    gap: 0;
  }
  .pill-chevron {
    display: none;
  }
  .header-divider {
    display: none;
  }
  .drop-card {
    width: 272px;
    right: -4px;
  }
}
</style>
