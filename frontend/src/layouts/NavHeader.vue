<template>
  <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
  <nav class="navbar">
    <router-link to="/" class="logo">
      <span
        ><img
          src="../../public/bloomcraft-blankBg.png"
          alt="Bloomcraft Logo"
          width="50"
          height="50"
      /></span>
      <span>BloomCraft</span>
    </router-link>

    <div class="nav-links">
      <template v-if="!isAuthenticated">
        <router-link to="/">Home</router-link>
      </template>
      <router-link to="/shop">Shop</router-link>
      <a href="#occasions" @click.prevent="scrollToSection('occasions')"
        >Occasions</a
      >
      <a href="#sale" @click.prevent="scrollToSection('sale')">Sales</a>
    </div>

    <div class="nav-buttons">
      <template v-if="!isAuthenticated">
        <router-link to="/guest/login" class="btn-login">Login</router-link>
        <router-link to="/guest/register" class="btn-register"
          >Register</router-link
        >
      </template>
      <template v-else>
        <router-link
          v-if="user?.role === 'customer'"
          to="/customer/cart"
          class="btn-cart"
          :class="{ 'cart-pulse': cartPulse }"
        >
          🛒 Cart ({{ cartCount }})
        </router-link>

        <div class="user-dropdown-wrapper">
          <button class="btn-user" @click="toggleUserDropdown">
            <div class="user-avatar" :class="{ loading: loadingProfile }">
              <img
                v-if="hasProfilePicture"
                :src="profilePictureUrl"
                :alt="userName"
                @error="handleAvatarError"
              />
              <span v-else>{{ avatarInitial }}</span>
            </div>
            <span class="user-name">{{ userName }}</span>
            <span class="dropdown-icon" :class="{ open: showUserDropdown }">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 1024 1024"
              >
                <path
                  fill="currentColor"
                  d="M831.872 340.864L512 652.672L192.128 340.864a30.59 30.59 0 0 0-42.752 0a29.12 29.12 0 0 0 0 41.6L489.664 714.24a32 32 0 0 0 44.672 0l340.288-331.712a29.12 29.12 0 0 0 0-41.728a30.59 30.59 0 0 0-42.752 0z"
                  stroke-width="25.5"
                  stroke="currentColor"
                />
              </svg>
            </span>
          </button>
          <div v-if="showUserDropdown" class="dropdown-menu">
            <div class="dropdown-header">
              <div class="user-info">
                <div
                  class="user-avatar-large"
                  :class="{ loading: loadingProfile }"
                >
                  <img
                    v-if="hasProfilePicture"
                    :src="profilePictureUrl"
                    :alt="userName"
                    @error="handleAvatarError"
                  />
                  <span v-else>{{ avatarInitial }}</span>
                </div>
                <div class="user-details">
                  <span class="user-display-name">{{ userName }}</span>
                  <span class="user-role">{{ userRoleDisplay }}</span>
                  <span v-if="userProfile?.plan" class="user-plan">{{
                    userProfile.plan
                  }}</span>
                </div>
              </div>
            </div>

            <div class="dropdown-divider"></div>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/profile"
              class="dropdown-item"
            >
              <span class="icons"> 👤 </span>
              <span>Profile</span>
            </router-link>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/orders"
              class="dropdown-item"
            >
              <span class="icons"> 📦 </span>
              <span>Orders</span>
            </router-link>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/chat"
              class="dropdown-item"
            >
              <span class="icons"> 💬 </span>
              <span>Chat</span>
            </router-link>

            <div class="dropdown-divider"></div>

            <a
              class="dropdown-item"
              @click.prevent="handleLogout"
              :class="{ 'logout-loading': isLoading }"
            >
              <span>
                <svg
                  v-if="!isLoading"
                  xmlns="http://www.w3.org/2000/svg"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M5.616 20q-.691 0-1.153-.462T4 18.384V5.616q0-.691.463-1.153T5.616 4h5.903q.214 0 .357.143t.143.357t-.143.357t-.357.143H5.616q-.231 0-.424.192T5 5.616v12.769q0 .23.192.423t.423.192h5.904q.214 0 .357.143t.143.357t-.143.357t-.357.143zm12.444-7.5H9.692q-.213 0-.356-.143T9.192 12t.143-.357t.357-.143h8.368l-1.971-1.971q-.141-.14-.15-.338q-.01-.199.15-.364q.159-.165.353-.168q.195-.003.36.162l2.614 2.613q.242.243.242.566t-.243.566l-2.613 2.613q-.146.146-.347.153t-.366-.159q-.16-.165-.157-.357t.162-.35z"
                  />
                </svg>
                <span v-else class="logout-spinner"></span>
              </span>
              <span>{{ isLoading ? "Logging out..." : "Logout" }}</span>
            </a>
          </div>
        </div>
      </template>
    </div>
  </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../composables/useAuth";
import api from "../plugins/axios";
import { toast } from "vue3-toastify";
import LoadingOverlay from "../layouts/components/LoadingOverlay.vue";
import cartService from "../services/cartService.js";

const router = useRouter();
const { isAuthenticated, logout, isLoggingOut, user } = useAuth();

const showUserDropdown = ref(false);
const cartPulse = ref(false);
const userProfile = ref(null);
const loadingProfile = ref(false);
const isLoadingMessage = ref("Loading...");
const isLoading = ref(null);
const cartItems = ref([]);

isLoading.value = false;

const props = defineProps({
  cartCount: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(["scroll-to-section"]);

const cartCount = computed(() => {
  return cartItems.value.length;
});

const loadCart = async () => {
  if (!isAuthenticated.value) return;

  try {
    const response = await cartService.getCart();
    if (response.success) {
      cartItems.value = response.data.items || [];
    }
  } catch (error) {
    console.error("Error loading cart:", error);
  }
};

const userName = computed(() => {
  if (userProfile.value?.name && userProfile.value?.surname) {
    return `${userProfile.value.name} ${userProfile.value.surname}`;
  }
  if (user.value?.name && user.value?.surname) {
    return `${user.value.name} ${user.value.surname}`;
  }
  return user.value?.name || user.value?.email?.split("@")[0] || "User";
});

const avatarInitial = computed(() => {
  return userName.value.charAt(0).toUpperCase();
});

const hasProfilePicture = computed(() => {
  return (
    userProfile.value?.profile_picture &&
    userProfile.value.profile_picture !== "" &&
    !userProfile.value.profile_picture.includes("ui-avatars.com")
  );
});

const profilePictureUrl = computed(() => {
  if (userProfile.value?.profile_picture) {
    if (!userProfile.value.profile_picture.startsWith("http")) {
      return `/storage/${userProfile.value.profile_picture}`;
    }
    return userProfile.value.profile_picture;
  }
  if (user.value?.profile_picture) {
    if (!user.value.profile_picture.startsWith("http")) {
      return `/storage/${user.value.profile_picture}`;
    }
    return user.value.profile_picture;
  }
  return "";
});

const userRoleDisplay = computed(() => {
  if (!user.value?.role) return "Guest";

  const roleMap = {
    admin: "Administrator",
    vendor: "Vendor",
    customer: "Customer",
  };

  return roleMap[user.value.role] || user.value.role;
});

const scrollToSection = (sectionId) => {
  emit("scroll-to-section", sectionId);
};

const toggleUserDropdown = () => {
  showUserDropdown.value = !showUserDropdown.value;
  if (showUserDropdown.value && isAuthenticated.value) {
    loadUserProfile();
  }
};

const loadUserProfile = async () => {
  if (!isAuthenticated.value || loadingProfile.value) return;

  try {
    loadingProfile.value = true;
    const token =
      localStorage.getItem("token") || localStorage.getItem("auth_token");
    if (token) {
      // #region agent log
      fetch(
        "http://127.0.0.1:7867/ingest/e7384a0f-ecb0-4584-9186-0923dc335670",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Debug-Session-Id": "0e8f77",
          },
          body: JSON.stringify({
            sessionId: "0e8f77",
            runId: "pre-fix",
            hypothesisId: "H5_TOKEN_SOURCE",
            location: "frontend/src/layouts/NavHeader.vue:loadUserProfile",
            message: "loadUserProfile token state",
            data: {
              has_local_token_key: !!localStorage.getItem("token"),
              has_auth_token_key: !!localStorage.getItem("auth_token"),
              has_employee_token_key: !!localStorage.getItem("employee_token"),
              token_source: localStorage.getItem("token")
                ? "token"
                : localStorage.getItem("auth_token")
                  ? "auth_token"
                  : null,
              token_looks_like_bearer: typeof token === "string"
                ? token.startsWith("Bearer ")
                : null,
              token_length: typeof token === "string" ? token.length : null,
            },
            timestamp: Date.now(),
          }),
        },
      ).catch(() => {});
      // #endregion

      const response = await api.get("profile/user-profile", {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      });

      if (response.data.success) {
        userProfile.value = response.data.user;
      }
    }
  } catch (error) {
    console.error("Error loading user profile:", error);
    // #region agent log
    fetch(
      "http://127.0.0.1:7867/ingest/e7384a0f-ecb0-4584-9186-0923dc335670",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Debug-Session-Id": "0e8f77",
        },
        body: JSON.stringify({
          sessionId: "0e8f77",
          runId: "pre-fix",
          hypothesisId: "H6_PROFILE_500_DETAILS",
          location: "frontend/src/layouts/NavHeader.vue:loadUserProfile",
          message: "loadUserProfile request failed",
          data: {
            status: error?.response?.status ?? null,
            backend_success_flag: error?.response?.data?.success ?? null,
            backend_message:
              error?.response?.data?.message ?? null,
            backend_path: error?.response?.config?.url ?? null,
          },
          timestamp: Date.now(),
        }),
      },
    ).catch(() => {});
    // #endregion

    if (user.value) {
      userProfile.value = {
        name: user.value.name,
        surname: user.value.surname,
        profile_picture: getDefaultAvatar(),
        plan: user.value.plan || "Free Plan",
      };
    }
  } finally {
    loadingProfile.value = false;
  }
};

const getDefaultAvatar = () => {
  if (user.value?.name && user.value?.surname) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(
      user.value.name + " " + user.value.surname,
    )}&background=random&color=fff`;
  }
  if (user.value?.name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(
      user.value.name,
    )}&background=random&color=fff`;
  }
  if (user.value?.email) {
    const emailName = user.value.email.split("@")[0];
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(
      emailName,
    )}&background=random&color=fff`;
  }
  return `https://ui-avatars.com/api/?name=User&background=random&color=fff`;
};

const handleAvatarError = (event) => {
  const target = event.target;
  target.style.display = "none";

  const parent = target.parentElement;
  const existingSpan = parent.querySelector("span:not(img + span)");
  if (!existingSpan) {
    const initialSpan = document.createElement("span");
    initialSpan.textContent = avatarInitial.value;
    parent.appendChild(initialSpan);
  }
};

const handleLogout = async () => {
  if (isLoading.value) return;

  try {
    isLoadingMessage.value = "Logging out...";
    isLoading.value = isLoggingOut.value = true;
    showUserDropdown.value = false;

    await logout();

    await new Promise((r) => setTimeout(r, 600));

    localStorage.removeItem("auth_token");
    localStorage.removeItem("user");

    user.value = null;

    await router.push("/guest/login");

    toast.success("Logged out successfully!");
  } catch {
    toast.error("Logout failed");
  } finally {
    isLoading.value = false;
  }
};

const handleClickOutside = (event) => {
  const dropdown = document.querySelector(".user-dropdown-wrapper");
  if (dropdown && !dropdown.contains(event.target)) {
    showUserDropdown.value = false;
  }
};

const triggerCartPulse = () => {
  cartPulse.value = true;
  setTimeout(() => {
    cartPulse.value = false;
  }, 1000);
};

watch(
  isAuthenticated,
  (newVal) => {
    if (newVal && user.value) {
      userProfile.value = {
        name: user.value.name,
        surname: user.value.surname,
        profile_picture: getDefaultAvatar(),
        plan: user.value.plan || "Free Plan",
      };
    } else {
      userProfile.value = null;
    }
  },
  { immediate: true },
);

defineExpose({
  triggerCartPulse,
});

onMounted(() => {
  document.addEventListener("click", handleClickOutside);

  loadCart();

  if (isAuthenticated.value) {
    loadUserProfile();
  }
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
* {
  font-family: "Poppins", "sans-serif";
}

/* Navbar */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 1rem 5%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
  text-decoration: none;
}

.nav-links {
  display: flex;
  gap: 24px;
  align-items: center;
}

.nav-links a {
  color: #4a5568;
  text-decoration: none;
  font-size: 15px;
  transition: color 0.3s;
  cursor: pointer;
  white-space: nowrap;
}

.nav-links a:hover,
.nav-links a.router-link-active {
  color: #2d3748;
  font-weight: 500;
}

.nav-buttons {
  display: flex;
  gap: 12px;
  align-items: center;
}

.btn-login {
  padding: 10px 24px;
  background: transparent;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  display: inline-block;
  white-space: nowrap;
}

.btn-login:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.btn-register,
.btn-cart {
  padding: 10px 24px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  display: inline-block;
  white-space: nowrap;
}

.btn-register:hover,
.btn-cart:hover {
  background: #1a202c;
  transform: translateY(-1px);
}

.btn-cart.cart-pulse {
  animation: cartPulse 0.6s ease-in-out;
}

@keyframes cartPulse {
  0%,
  100% {
    transform: scale(1);
  }
  25% {
    transform: scale(1.15);
  }
  50% {
    transform: scale(1.05);
  }
  75% {
    transform: scale(1.1);
  }
}

.icons {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
}

.user-dropdown-wrapper {
  position: relative;
}

.btn-user {
  padding: 8px 16px;
  background: transparent;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-user:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.user-avatar {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, #48bb78, #7c3aed);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  overflow: hidden;
  position: relative;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0;
  left: 0;
}

.user-avatar span {
  position: relative;
  z-index: 1;
}

.user-avatar-large {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #48bb78, #7c3aed);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 20px;
  overflow: hidden;
  position: relative;
}

.user-avatar-large img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0;
  left: 0;
}

.user-avatar-large span {
  position: relative;
  z-index: 1;
}

.user-name {
  font-weight: 500;
  max-width: 100px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-icon {
  font-size: 10px;
  transition: transform 0.3s;
  display: flex;
  align-items: center;
}

.dropdown-icon.open {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  min-width: 280px;
  z-index: 1001;
  animation: dropdownSlide 0.2s ease;
  overflow: hidden;
}

@keyframes dropdownSlide {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-header {
  padding: 20px;
  background: linear-gradient(135deg, #f7fafc, #f0fff4);
  border-bottom: 1px solid #e2e8f0;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-details {
  display: flex;
  flex-direction: column;
}

.user-display-name {
  font-weight: 600;
  font-size: 16px;
  color: #2d3748;
}

.user-role {
  font-size: 12px;
  color: #718096;
  background: white;
  padding: 2px 8px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  margin-top: 4px;
  display: inline-block;
  width: fit-content;
}

.user-plan {
  font-size: 12px;
  color: #48bb78;
  background: #f0fff4;
  padding: 2px 8px;
  border-radius: 12px;
  border: 1px solid #c6f6d5;
  margin-top: 4px;
  display: inline-block;
  width: fit-content;
  font-weight: 500;
}

.dropdown-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 0;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  color: #2d3748;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
  cursor: pointer;
  position: relative;
}

.dropdown-item:hover {
  background: #f7fafc;
}

.dropdown-item.logout-loading {
  background-color: #f8f9fa;
  cursor: not-allowed;
  opacity: 0.6;
}

.dropdown-item.logout-loading:hover {
  background-color: #f8f9fa;
}

.dropdown-item span:first-child {
  display: flex;
  align-items: center;
  width: 24px;
}

.logout-spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid #2d3748;
  border-radius: 50%;
  border-top-color: transparent;
  animation: spin 0.8s linear infinite;
}

.dropdown-badge {
  position: absolute;
  right: 20px;
  background: #e53e3e;
  color: white;
  font-size: 11px;
  font-weight: 600;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Loading state for avatar */
.user-avatar.loading::after,
.user-avatar-large.loading::after {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.4),
    transparent
  );
  animation: loadingShimmer 1.5s infinite;
  z-index: 2;
}

@keyframes loadingShimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

/* Responsive Design */
@media (max-width: 968px) {
  .nav-links {
    display: none;
  }

  .user-name {
    max-width: 80px;
  }

  .dropdown-menu {
    position: fixed;
    top: 70px;
    right: 20px;
    left: 20px;
    width: auto;
    max-width: 320px;
  }
}

@media (max-width: 640px) {
  .nav-buttons {
    gap: 8px;
  }

  .btn-login,
  .btn-register,
  .btn-cart,
  .btn-user {
    padding: 8px 12px;
    font-size: 13px;
  }

  .user-name {
    display: none;
  }

  .user-avatar {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }

  .user-avatar-large {
    width: 40px;
    height: 40px;
    font-size: 16px;
  }

  .dropdown-menu {
    right: 10px;
    left: 10px;
    min-width: auto;
  }
}

@media (max-width: 480px) {
  .btn-cart {
    padding: 8px;
    min-width: 40px;
    justify-content: center;
  }

  .btn-cart::before {
    content: "🛒";
  }
}
</style>
