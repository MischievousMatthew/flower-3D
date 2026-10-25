<template>
  <!-- Same bar as the landing page (src/styles/bloomcraft.css) -->
  <nav
    class="navbar bc-theme bc-navbar"
    :class="{
      'bc-navbar--scrolled': navScrolled,
      'bc-navbar--menu-open': mobileMenuOpen,
    }"
    aria-label="Main navigation"
  >
    <!-- Ordering paused, or a planned pause coming up (Admin → System settings) -->
    <CheckoutStatusBanner />
    <router-link to="/" class="bc-logo" @click="closeMobileMenu">
      <img
        src="/bloomcraft-blankBg.png"
        alt="Bloomcraft Logo"
        width="44"
        height="44"
      />
      <span class="bc-logo-text">BloomCraft</span>
    </router-link>

    <div id="customer-nav" class="bc-nav-links">
      <template v-if="!isAuthenticated">
        <router-link to="/" @click="closeMobileMenu">Home</router-link>
      </template>
      <router-link to="/shop" @click="closeMobileMenu">Shop</router-link>
      <a href="#occasions" @click.prevent="navigateTo('occasions')"
        >Occasions</a
      >
      <a href="#sale" @click.prevent="navigateTo('sale')">Sales</a>
      <router-link
        v-if="!isAuthenticated"
        to="/guest/login"
        class="bc-nav-menu-only"
        @click="closeMobileMenu"
        >Login</router-link
      >
    </div>

    <div class="nav-actions">
      <template v-if="!isAuthenticated">
        <router-link to="/guest/login" class="bc-nav-login">Login</router-link>
        <router-link to="/guest/register" class="bc-nav-register"
          >Register</router-link
        >
      </template>
      <template v-else>
        <router-link
          v-if="user?.role === 'customer'"
          to="/customer/cart"
          id="cart-icon"
          class="btn-cart nav-icon-btn"
          :class="{ 'cart-pulse': cartPulse }"
          :aria-label="`Cart, ${cartCount} item${cartCount === 1 ? '' : 's'}`"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 7h12l-1 13H7L6 7Z" />
            <path d="M9 7a3 3 0 0 1 6 0" />
          </svg>
          <span class="nav-icon-label">Cart</span>
          <span class="nav-count">{{ cartCount }}</span>
        </router-link>

        <div v-if="user?.role === 'customer'" class="notification-wrapper">
          <button
            type="button"
            class="btn-notification nav-icon-btn"
            :aria-expanded="showNotificationDropdown"
            aria-label="Order notifications"
            @click="toggleNotificationDropdown"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z" />
              <path d="M10 20a2 2 0 0 0 4 0" />
            </svg>
            <span v-if="notificationCount + noticeUnread > 0" class="nav-badge">
              {{ notificationCount + noticeUnread > 9 ? "9+" : notificationCount + noticeUnread }}
            </span>
          </button>

          <div v-if="showNotificationDropdown" class="nav-menu notification-menu">
            <template v-if="notices.length">
              <div class="nav-menu-head">
                <span class="bc-label">From BloomCraft</span>
                <button v-if="noticeUnread" type="button" class="nav-menu-action" @click="readAllNotices">Mark read</button>
              </div>
              <div class="notification-list">
                <button
                  v-for="notice in notices"
                  :key="`notice-${notice.id}`"
                  type="button"
                  class="notification-entry"
                  :class="{ important: notice.important && !notice.read }"
                  @click="openNotice(notice)"
                >
                  <span class="notification-status-dot" :class="notice.read ? '' : notice.important ? 'alert' : 'info'"></span>
                  <span class="notification-entry-content">
                    <span class="notification-entry-title">{{ notice.title }} <span v-if="notice.important" class="notification-tag">Important</span></span>
                    <span v-if="notice.body" class="notification-entry-message">{{ notice.body }}</span>
                    <span class="notification-entry-time">{{ formatNoticeTime(notice.created_at) }}</span>
                  </span>
                </button>
              </div>
            </template>
            <div class="nav-menu-head">
              <span class="bc-label">Order updates</span>
              <button
                type="button"
                class="nav-menu-action"
                @click="loadOrderNotifications"
              >
                Refresh
              </button>
            </div>

            <div v-if="loadingNotifications" class="notification-skeletons">
              <div v-for="n in 3" :key="n" class="notification-skeleton">
                <span class="bc-skeleton notification-skeleton-dot"></span>
                <span class="notification-skeleton-lines">
                  <span class="bc-skeleton notification-skeleton-line"></span>
                  <span
                    class="bc-skeleton notification-skeleton-line notification-skeleton-line--short"
                  ></span>
                </span>
              </div>
            </div>

            <div
              v-else-if="orderNotifications.length === 0"
              class="notification-empty"
            >
              No order updates yet.
            </div>

            <div v-else class="notification-list">
              <button
                v-for="notification in orderNotifications"
                :key="notification.id"
                type="button"
                class="notification-entry"
                @click="goToOrders(notification)"
              >
                <span
                  class="notification-status-dot"
                  :class="notification.variant"
                ></span>
                <span class="notification-entry-content">
                  <span class="notification-entry-title">
                    {{ notification.title }}
                  </span>
                  <span class="notification-entry-message">
                    {{ notification.message }}
                  </span>
                  <span class="notification-entry-time">
                    {{ notification.time }}
                  </span>
                </span>
              </button>
            </div>
          </div>
        </div>

        <div class="user-dropdown-wrapper">
          <button
            type="button"
            class="btn-user"
            :aria-expanded="showUserDropdown"
            @click="toggleUserDropdown"
          >
            <span class="user-avatar" :class="{ loading: loadingProfile }">
              <img
                v-if="hasProfilePicture"
                :src="profilePictureUrl"
                :alt="userName"
                @error="handleAvatarError"
              />
              <span v-else>{{ avatarInitial }}</span>
            </span>
            <span class="user-name">{{ userName }}</span>
            <svg
              class="dropdown-icon"
              :class="{ open: showUserDropdown }"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path d="M6 9l6 6 6-6" />
            </svg>
          </button>
          <div v-if="showUserDropdown" class="nav-menu dropdown-menu">
            <div class="dropdown-header">
              <span class="user-avatar-large" :class="{ loading: loadingProfile }">
                <img
                  v-if="hasProfilePicture"
                  :src="profilePictureUrl"
                  :alt="userName"
                  @error="handleAvatarError"
                />
                <span v-else>{{ avatarInitial }}</span>
              </span>
              <span class="user-details">
                <span class="user-display-name">{{ userName }}</span>
                <span class="user-role">{{ userRoleDisplay }}</span>
                <span v-if="userProfile?.plan" class="user-plan">{{
                  userProfile.plan
                }}</span>
              </span>
            </div>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/profile"
              class="dropdown-item"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="3.5" />
                <path d="M5 20a7 7 0 0 1 14 0" />
              </svg>
              <span>Profile</span>
            </router-link>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/orders"
              class="dropdown-item"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 8l8-4 8 4v8l-8 4-8-4V8Z" />
                <path d="M4 8l8 4 8-4M12 12v8" />
              </svg>
              <span>Orders</span>
            </router-link>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/support"
              class="dropdown-item"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01" />
              </svg>
              <span>Help &amp; support</span>
            </router-link>

            <router-link
              v-if="user?.role === 'customer'"
              to="/customer/chat"
              class="dropdown-item"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 5h14v10H9l-4 4V5Z" />
              </svg>
              <span>Chat</span>
              <span v-if="unreadChatCount > 0" class="dropdown-badge">
                {{ unreadChatCount > 9 ? "9+" : unreadChatCount }}
              </span>
            </router-link>

            <a
              href="#"
              class="dropdown-item dropdown-item--logout"
              @click.prevent="handleLogout"
              :class="{ 'logout-loading': isLoading }"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9" />
              </svg>
              <span aria-live="polite">{{
                isLoading ? "Logging out..." : "Logout"
              }}</span>
            </a>
          </div>
        </div>
      </template>

      <button
        type="button"
        class="bc-nav-toggle"
        :aria-expanded="mobileMenuOpen"
        aria-controls="customer-nav"
        :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
        @click="mobileMenuOpen = !mobileMenuOpen"
      >
        <span class="bc-nav-toggle-bar" aria-hidden="true"></span>
        <span class="bc-nav-toggle-bar" aria-hidden="true"></span>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { useCart } from "../composables/useCart";
import { useChatNotifications } from "../composables/useChatNotifications";
import api from "../plugins/axios";
import { toast } from "@/utils/snackbar";
import "../styles/bloomcraft.css";
import CheckoutStatusBanner from "../components/site/CheckoutStatusBanner.vue";

const router = useRouter();
const { isAuthenticated, logout, user } = useAuth();
const { unreadChatCount, chatRoute } = useChatNotifications();

const showUserDropdown = ref(false);
const showNotificationDropdown = ref(false);
const cartPulse = ref(false);
const userProfile = ref(null);
const loadingProfile = ref(false);
const loadingNotifications = ref(false);
const isLoading = ref(null);
const orderNotifications = ref([]);
const seenOrderNotificationIds = ref(new Set());
let notificationInterval = null;

const cartStore = useCart();

isLoading.value = false;

const props = defineProps({
  cartCount: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(["scroll-to-section"]);

const cartCount = computed(() => cartStore.count.value);
const isChatRoute = computed(() =>
  router.currentRoute.value.path.startsWith(chatRoute.value),
);

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

const notificationCount = computed(
  () =>
    orderNotifications.value.filter(
      (notification) => !seenOrderNotificationIds.value.has(notification.id),
    ).length,
);

const orderNotificationStorageKey = () =>
  `seen_order_notifications:${user.value?.id ?? "guest"}`;

const loadSeenOrderNotifications = () => {
  try {
    const stored = JSON.parse(
      localStorage.getItem(orderNotificationStorageKey()) || "[]",
    );
    seenOrderNotificationIds.value = new Set(
      Array.isArray(stored) ? stored : [],
    );
  } catch {
    seenOrderNotificationIds.value = new Set();
  }
};

const markOrderNotificationsAsSeen = (notifications) => {
  const nextSeenIds = new Set(seenOrderNotificationIds.value);
  notifications.forEach((notification) => nextSeenIds.add(notification.id));
  seenOrderNotificationIds.value = nextSeenIds;
  localStorage.setItem(
    orderNotificationStorageKey(),
    JSON.stringify([...nextSeenIds]),
  );
};

const scrollToSection = (sectionId) => {
  emit("scroll-to-section", sectionId);
};

// Mobile menu (same pattern as the landing page navbar).
const mobileMenuOpen = ref(false);
const closeMobileMenu = () => {
  mobileMenuOpen.value = false;
};
const navigateTo = (sectionId) => {
  closeMobileMenu();
  scrollToSection(sectionId);
};

const toggleUserDropdown = () => {
  showUserDropdown.value = !showUserDropdown.value;
  showNotificationDropdown.value = false;
  if (showUserDropdown.value && isAuthenticated.value) {
    loadUserProfile();
  }
};

// ── Notices from BloomCraft (announcements, support replies) ──────────────
const notices = ref([]);
const noticeUnread = ref(0);
const loadNotices = async (withItems = false) => {
  if (!isAuthenticated.value || user.value?.role !== "customer") return;
  try {
    if (withItems) {
      const { data } = await api.get("/notifications", { params: { limit: 6 } });
      notices.value = data.data ?? [];
      noticeUnread.value = data.unread ?? 0;
    } else {
      noticeUnread.value = (await api.get("/notifications/unread-count")).data.unread ?? 0;
    }
  } catch {
    // notices are optional
  }
};
const openNotice = async (notice) => {
  if (!notice.read) {
    notice.read = true;
    try {
      noticeUnread.value = (await api.post(`/notifications/${notice.id}/read`)).data.unread ?? 0;
    } catch {
      // stays unread on the server; harmless
    }
  }
  if (notice.link_url) {
    showNotificationDropdown.value = false;
    if (/^https?:\/\//i.test(notice.link_url)) window.open(notice.link_url, "_blank", "noopener");
    else router.push(notice.link_url);
  }
};
const readAllNotices = async () => {
  notices.value.forEach((n) => (n.read = true));
  noticeUnread.value = 0;
  try {
    await api.post("/notifications/read-all");
  } catch {
    loadNotices();
  }
};
const formatNoticeTime = (value) =>
  value ? new Date(value).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "";

const toggleNotificationDropdown = async () => {
  showNotificationDropdown.value = !showNotificationDropdown.value;
  showUserDropdown.value = false;

  if (showNotificationDropdown.value) {
    await Promise.all([loadOrderNotifications(), loadNotices(true)]);
    markOrderNotificationsAsSeen(orderNotifications.value);
  }
};

const loadUserProfile = async () => {
  if (!isAuthenticated.value || loadingProfile.value) return;

  try {
    loadingProfile.value = true;
    const token =
      localStorage.getItem("token") || localStorage.getItem("auth_token");
    if (token) {
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
    isLoading.value = true;
    showUserDropdown.value = false;
    // Signs out here at once, tells the server, and opens the login page
    await logout();
  } catch {
    toast.error("Logout failed");
  } finally {
    isLoading.value = false;
  }
};

const goToOrders = (notification) => {
  if (notification) markOrderNotificationsAsSeen([notification]);
  showNotificationDropdown.value = false;
  router.push("/customer/orders");
};

const loadOrderNotifications = async () => {
  if (!isAuthenticated.value || user.value?.role !== "customer") return;

  try {
    loadingNotifications.value = true;
    const response = await api.get("/customer/orders", {
      params: { per_page: 10 },
    });

    if (response.data.success) {
      const orders = Array.isArray(response.data.data) ? response.data.data : [];
      orderNotifications.value = orders.map(mapOrderToNotification).filter(Boolean);
    }
  } catch (error) {
    console.error("Error loading order notifications:", error);
  } finally {
    loadingNotifications.value = false;
  }
};

const mapOrderToNotification = (order) => {
  const status = order?.delivery?.status || order?.status;

  const statusMap = {
    pending: {
      title: "To Process",
      message: `Order ${order.order_number} is waiting for vendor processing.`,
      variant: "warning",
    },
    to_processed: {
      title: "To Process",
      message: `Order ${order.order_number} is queued for processing.`,
      variant: "warning",
    },
    processing: {
      title: "To Process",
      message: `Order ${order.order_number} is now being prepared.`,
      variant: "warning",
    },
    to_ship: {
      title: "To Ship",
      message: `Order ${order.order_number} is ready to be shipped.`,
      variant: "info",
    },
    shipped: {
      title: "To Ship",
      message: `Order ${order.order_number} is being dispatched.`,
      variant: "info",
    },
    to_received: {
      title: "To Receive",
      message: `Order ${order.order_number} is out for delivery.`,
      variant: "success",
    },
    delivered: {
      title: "To Receive",
      message: `Order ${order.order_number} is arriving for delivery.`,
      variant: "success",
    },
    completed: {
      title: "Completed",
      message: `Order ${order.order_number} has been completed.`,
      variant: "completed",
    },
    returned: {
      title: "Returned",
      message: `Order ${order.order_number} has a return request in progress.`,
      variant: "warning",
    },
    refunded: {
      title: "Refunded",
      message: `Order ${order.order_number} has been refunded.`,
      variant: "completed",
    },
    cancelled: {
      title: "Cancelled",
      message: `Order ${order.order_number} was cancelled.`,
      variant: "warning",
    },
  };

  const config = statusMap[status];
  if (!config) return null;

  const timestamp =
    order?.delivery?.last_scanned_at || order?.delivered_at || order?.created_at;

  return {
    id: `${order.id}-${status}`,
    ...config,
    time: formatNotificationTime(timestamp),
  };
};

const formatNotificationTime = (value) => {
  if (!value) return "Just now";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Just now";

  return date.toLocaleString("en-PH", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
};

const handleClickOutside = (event) => {
  const dropdown = document.querySelector(".user-dropdown-wrapper");
  const notificationDropdown = document.querySelector(".notification-wrapper");
  if (dropdown && !dropdown.contains(event.target)) {
    showUserDropdown.value = false;
  }
  if (notificationDropdown && !notificationDropdown.contains(event.target)) {
    showNotificationDropdown.value = false;
  }
  if (!event.target.closest?.(".bc-navbar")) {
    mobileMenuOpen.value = false;
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
      loadSeenOrderNotifications();
      if (user.value.role === "customer") {
        cartStore.initialize();
      }
      userProfile.value = {
        name: user.value.name,
        surname: user.value.surname,
        profile_picture: getDefaultAvatar(),
        plan: user.value.plan || "Free Plan",
      };
    } else {
      userProfile.value = null;
      seenOrderNotificationIds.value = new Set();
      cartStore.resetCart();
    }
  },
  { immediate: true },
);

defineExpose({
  triggerCartPulse,
});

// ── Scroll-driven floating pill ────────────────────────────────
const navScrolled = ref(false);

const handleNavScroll = () => {
  navScrolled.value = window.scrollY > 24;
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
  window.addEventListener("scroll", handleNavScroll, { passive: true });
  handleNavScroll(); // set correct state on initial render

  if (isAuthenticated.value) {
    if (user.value?.role === "customer") {
      cartStore.initialize();
    }
    loadUserProfile();
    loadOrderNotifications();

    if (user.value?.role === "customer") {
      loadNotices();
      notificationInterval = window.setInterval(() => {
        loadOrderNotifications();
        loadNotices();
      }, 60000);
    }
  }
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  window.removeEventListener("scroll", handleNavScroll);
  if (notificationInterval) {
    window.clearInterval(notificationInterval);
  }
});
</script>

<style scoped>
/* Bar, logo, links, login/register and the mobile menu come from
   src/styles/bloomcraft.css (same as the landing page). Only the customer
   controls (cart, notifications, account menu) are styled here. */

.nav-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

/* ---- Cart + notifications ---- */
.nav-icon-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  border: none;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: color 0.3s;
}

.nav-icon-btn:hover {
  color: var(--leaf-deep);
}

.nav-icon-btn svg,
.dropdown-item svg,
.dropdown-icon {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.nav-count {
  display: inline-grid;
  place-items: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--ink);
  color: var(--ivory);
  font-size: 11px;
  font-weight: 500;
}

.nav-badge {
  position: absolute;
  top: -6px;
  right: -8px;
  display: grid;
  place-items: center;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--leaf);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
}

.cart-pulse {
  animation: cartPulse 0.6s var(--ease-out);
}

@keyframes cartPulse {
  40% {
    transform: scale(1.12);
  }
}

.nav-icon-btn:focus-visible,
.btn-user:focus-visible,
.dropdown-item:focus-visible,
.notification-entry:focus-visible,
.nav-menu-action:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 4px;
  border-radius: 6px;
}

/* ---- Dropdown panels ---- */
.notification-wrapper,
.user-dropdown-wrapper {
  position: relative;
}

.nav-menu {
  position: absolute;
  top: calc(100% + 18px);
  right: 0;
  z-index: 1001;
  width: 300px;
  padding: 8px;
  border: 1px solid var(--line-soft);
  border-radius: 18px;
  background: var(--paper);
  box-shadow: 0 24px 60px rgba(54, 63, 78, 0.14);
  animation: menuIn 0.3s var(--ease-out);
}

@keyframes menuIn {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
}

.notification-menu {
  width: 340px;
}

.nav-menu-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px 12px;
  border-bottom: 1px solid var(--line-soft);
}

.nav-menu-action {
  border: none;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 12.5px;
  font-weight: 500;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.notification-empty {
  padding: 22px 12px;
  color: var(--ink-soft);
  font-size: 13.5px;
  text-align: center;
}

.notification-list {
  max-height: 340px;
  overflow-y: auto;
}

/* Important notices from BloomCraft: tagged, with a red edge, and listed first by the server */
.notification-entry.important {
  box-shadow: inset 3px 0 0 #c2410c;
}
.notification-tag {
  display: inline-block;
  margin-left: 4px;
  padding: 0 6px;
  border-radius: 5px;
  background: #fdecea;
  color: #b42318;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  vertical-align: 1px;
}
.notification-status-dot.alert {
  background: #c2410c;
}
.notification-entry {
  display: flex;
  gap: 12px;
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 12px;
  background: none;
  font-family: var(--font-sans);
  text-align: left;
  cursor: pointer;
  transition: background 0.25s;
}

.notification-entry:hover {
  background: var(--ivory);
}

.notification-status-dot {
  flex-shrink: 0;
  width: 8px;
  height: 8px;
  margin-top: 7px;
  border-radius: 50%;
  background: var(--gold);
}

.notification-status-dot.info {
  background: var(--leaf);
}

.notification-status-dot.success {
  background: #7d8f69;
}

.notification-status-dot.completed {
  background: var(--ink-soft);
}

.notification-entry-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.notification-entry-title {
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
}

.notification-entry-message {
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.5;
}

.notification-entry-time {
  color: var(--ink-soft);
  font-size: 11.5px;
  opacity: 0.8;
}

.notification-skeletons {
  padding: 6px 4px;
}

.notification-skeleton {
  display: flex;
  gap: 12px;
  padding: 10px 8px;
}

.notification-skeleton-dot {
  width: 8px;
  height: 8px;
  margin-top: 6px;
  border-radius: 50%;
}

.notification-skeleton-lines {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 8px;
}

.notification-skeleton-line {
  height: 10px;
}

.notification-skeleton-line--short {
  width: 60%;
}

/* ---- Account ---- */
.btn-user {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 4px 6px 4px 4px;
  border: none;
  border-radius: 999px;
  background: none;
  color: var(--ink);
  font-family: var(--font-sans);
  cursor: pointer;
}

.user-avatar,
.user-avatar-large {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 50%;
  background: var(--ivory-deep);
  color: var(--ink);
  font-family: var(--font-display);
  font-weight: 600;
}

.user-avatar {
  width: 34px;
  height: 34px;
  font-size: 17px;
}

.user-avatar-large {
  width: 46px;
  height: 46px;
  font-size: 22px;
}

.user-avatar img,
.user-avatar-large img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar.loading,
.user-avatar-large.loading {
  opacity: 0.6;
}

.user-name {
  max-width: 140px;
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-icon {
  width: 16px;
  height: 16px;
  transition: transform 0.3s var(--ease-out);
}

.dropdown-icon.open {
  transform: rotate(180deg);
}

.dropdown-menu {
  width: 260px;
}

.dropdown-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 12px 16px;
  margin-bottom: 6px;
  border-bottom: 1px solid var(--line-soft);
}

.user-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-display-name {
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 500;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role,
.user-plan {
  color: var(--ink-soft);
  font-size: 12px;
}

.user-plan {
  color: var(--leaf-deep);
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border-radius: 12px;
  color: var(--ink);
  font-size: 14px;
  font-weight: 400;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.25s;
}

.dropdown-item:hover {
  background: var(--ivory);
}

.dropdown-item--logout {
  margin-top: 6px;
  border-top: 1px solid var(--line-soft);
  border-radius: 0 0 12px 12px;
}

.dropdown-badge {
  margin-left: auto;
  min-width: 20px;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--leaf);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  text-align: center;
}

.logout-loading {
  pointer-events: none;
  opacity: 0.6;
}

/* ---- Responsive ---- */
@media (max-width: 968px) {
  .user-name {
    display: none;
  }

  .nav-actions {
    gap: 16px;
  }

  .nav-menu {
    position: fixed;
    top: 84px;
    right: 16px;
    left: 16px;
    width: auto;
  }
}

@media (max-width: 640px) {
  .nav-actions {
    gap: 12px;
  }

  .nav-icon-label,
  .dropdown-icon {
    display: none;
  }

  .btn-user {
    padding: 0;
  }

  /* Cart count becomes a corner badge, like notifications */
  .nav-count {
    position: absolute;
    top: -6px;
    right: -9px;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    font-size: 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .nav-menu,
  .cart-pulse {
    animation: none;
  }
}
</style>
