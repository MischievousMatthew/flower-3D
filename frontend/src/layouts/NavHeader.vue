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
      <!-- Guests keep the landing page's navbar on every shop page -->
      <template v-if="!isAuthenticated">
        <router-link to="/shop" @click="closeMobileMenu">Shop</router-link>
        <router-link v-for="s in LANDING_SECTIONS" :key="s.id" :to="{ name: 'Home', hash: `#${s.id}` }" @click="closeMobileMenu">{{ s.label }}</router-link>
        <router-link to="/journal" @click="closeMobileMenu">Journal</router-link>
        <router-link to="/guest/login" class="bc-nav-menu-only" @click="closeMobileMenu">Sign in</router-link>
      </template>
      <!-- Signed in: the shop's own navbar -->
      <template v-else>
        <router-link to="/shop" @click="closeMobileMenu">Shop</router-link>
        <a href="#occasions" @click.prevent="navigateTo('occasions')"
          >Occasions</a
        >
        <a href="#sale" @click.prevent="navigateTo('sale')">Sales</a>
      </template>
    </div>

    <div class="nav-actions">
      <template v-if="!isAuthenticated">
        <router-link to="/guest/login" class="bc-nav-login">Sign in</router-link>
        <router-link to="/guest/register" class="bc-nav-register"
          >Get started</router-link
        >
      </template>
      <template v-else>
        <router-link
          v-if="user?.role === 'customer'"
          to="/customer/cart"
          id="cart-icon"
          class="btn-cart nav-icon-btn"
          :class="{ 'cart-pulse': cartPulse }"
          :aria-label="cartCount ? `Cart, ${cartCount} item${cartCount === 1 ? '' : 's'}` : 'Cart, empty'"
          title="Cart"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 7h12l-1 13H7L6 7Z" />
            <path d="M9 7a3 3 0 0 1 6 0" />
          </svg>
          <span v-if="cartCount > 0" class="nav-badge nav-badge--cart" aria-hidden="true">{{ cartCount > 99 ? "99+" : cartCount }}</span>
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

          <div v-if="showNotificationDropdown" class="nav-menu notification-menu" role="dialog" aria-label="Notifications">
            <header class="nt-head">
              <div>
                <h2 class="nt-title">Notifications</h2>
                <p class="nt-sub">{{ freshCount ? `${freshCount} new` : "You're all caught up" }}</p>
              </div>
              <button type="button" class="nt-action" :disabled="!freshCount" @click="readAll">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m2.5 12.5 4 4L13 10M10.5 16.5 17 10M14.5 16.5 21 10" /></svg>
                {{ freshCount ? "Mark all as read" : "All read" }}
              </button>
            </header>

            <div class="nt-scroll">
              <!-- From BloomCraft: announcements and support replies -->
              <section v-if="notices.length" class="nt-section" :class="{ open: ntOpen === 'bloomcraft' }">
                <div class="nt-section-head">
                  <button
                    type="button"
                    class="nt-toggle"
                    :aria-expanded="ntOpen === 'bloomcraft'"
                    aria-controls="nt-panel-bloomcraft"
                    @click="toggleNtSection('bloomcraft')"
                  >
                    <span class="nt-section-label">From BloomCraft</span>
                    <span v-if="noticeUnread" class="nt-count" :aria-label="`${noticeUnread} unread`">{{ noticeUnread }}</span>
                    <svg class="nt-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                  </button>
                </div>
                <div v-show="ntOpen === 'bloomcraft'" id="nt-panel-bloomcraft" class="nt-panel">
                <button
                  v-for="notice in notices"
                  :key="`notice-${notice.id}`"
                  type="button"
                  class="nt-item"
                  :class="{ fresh: !notice.read, important: notice.important && !notice.read }"
                  @click="openNotice(notice)"
                >
                  <span class="nt-icon" :class="notice.important ? 'red' : 'leaf'" aria-hidden="true" v-html="NOTICE_ICON"></span>
                  <span class="nt-body">
                    <span class="nt-row">
                      <span class="nt-item-title">{{ notice.title }}</span>
                      <span class="nt-time">{{ relativeTime(notice.created_at) }}</span>
                    </span>
                    <span v-if="notice.body" class="nt-text">{{ notice.body }}</span>
                    <span v-if="notice.important" class="nt-tag">Important</span>
                  </span>
                  <span v-if="!notice.read" class="nt-unread" aria-label="Unread"></span>
                </button>
                </div>
              </section>

              <!-- Order updates -->
              <section class="nt-section" :class="{ open: ntOpen === 'orders' }">
                <div class="nt-section-head">
                  <button
                    type="button"
                    class="nt-toggle"
                    :aria-expanded="ntOpen === 'orders'"
                    aria-controls="nt-panel-orders"
                    @click="toggleNtSection('orders')"
                  >
                    <span class="nt-section-label">Your orders</span>
                    <span v-if="freshIds.size" class="nt-count" :aria-label="`${freshIds.size} new`">{{ freshIds.size }}</span>
                    <svg class="nt-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                  </button>
                  <button v-show="ntOpen === 'orders'" type="button" class="nt-refresh" aria-label="Refresh order updates" title="Refresh" :disabled="loadingNotifications" @click="loadOrderNotifications">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.4-5.7L20 8.5" /><path d="M20 4v4.5h-4.5" /></svg>
                  </button>
                </div>

                <div v-show="ntOpen === 'orders'" id="nt-panel-orders" class="nt-panel">
                <div v-if="loadingNotifications && !orderNotifications.length" class="notification-skeletons">
                  <div v-for="n in 3" :key="n" class="notification-skeleton">
                    <span class="bc-skeleton nt-icon-skel"></span>
                    <span class="notification-skeleton-lines">
                      <span class="bc-skeleton notification-skeleton-line"></span>
                      <span class="bc-skeleton notification-skeleton-line notification-skeleton-line--short"></span>
                    </span>
                  </div>
                </div>

                <div v-else-if="orderNotifications.length === 0" class="nt-empty">
                  <span class="nt-icon leaf" aria-hidden="true" v-html="ORDER_ICONS.box"></span>
                  <strong>No order updates yet</strong>
                  <span>When you order flowers, every step shows up here.</span>
                </div>

                <template v-else>
                  <button
                    v-for="notification in orderNotifications"
                    :key="notification.id"
                    type="button"
                    class="nt-item"
                    :class="{ fresh: freshIds.has(notification.id) }"
                    @click="goToOrders(notification)"
                  >
                    <span class="nt-icon" :class="notification.tone" aria-hidden="true" v-html="ORDER_ICONS[notification.icon]"></span>
                    <span class="nt-body">
                      <span class="nt-row">
                        <span class="nt-item-title">{{ notification.title }}</span>
                        <span class="nt-time">{{ notification.time }}</span>
                      </span>
                      <span class="nt-text">Order <b>{{ notification.orderNumber }}</b> {{ notification.text }}</span>
                    </span>
                    <span v-if="freshIds.has(notification.id)" class="nt-unread" aria-label="New"></span>
                  </button>
                </template>
                </div>
              </section>
            </div>

            <footer class="nt-foot">
              <button type="button" class="nt-all" @click="goToOrders()">
                View all orders
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
              </button>
            </footer>
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
            <!-- A support reply waiting: the dot moves to "Help & support" while the menu is open -->
            <span v-if="unreadSupport > 0 && !showUserDropdown" class="nav-red-dot" role="status" aria-label="New reply in Help & support"></span>
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
              <span v-if="unreadSupport > 0" class="nav-red-dot inline" :aria-label="`${unreadSupport} new ${unreadSupport === 1 ? 'reply' : 'replies'}`"></span>
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
    <NotificationDetail :notice="openedNotice" @close="openedNotice = null" />
  </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { useCart } from "../composables/useCart";
import { useChatNotifications } from "../composables/useChatNotifications";
import { useSupportUnread } from "../composables/useSupportUnread";

const { unreadSupport, start: watchSupport, stop: unwatchSupport } = useSupportUnread();
import api from "../plugins/axios";
import { toast } from "@/utils/snackbar";
import "../styles/bloomcraft.css";
import CheckoutStatusBanner from "../components/site/CheckoutStatusBanner.vue";
import NotificationDetail from "../components/support/NotificationDetail.vue";

const router = useRouter();
const { isAuthenticated, logout, user } = useAuth();

// The landing page's sections, linked from the guest navbar (same as the landing page's own)
const LANDING_SECTIONS = [
  { id: "features", label: "Features" },
  { id: "how-it-works", label: "How It Works" },
  { id: "plans", label: "For Vendors" },
  { id: "blog", label: "Blog" },
  { id: "about", label: "About" },
];
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
// Updates that were new when the panel opened (they stay highlighted while it's open)
const freshIds = ref(new Set());
const freshCount = computed(() => freshIds.value.size + noticeUnread.value);

// Line icons for each kind of update (24px grid, stroke = currentColor)
const lineIcon = (d) =>
  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
const ORDER_ICONS = {
  receipt: lineIcon('<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>'),
  flower: lineIcon('<circle cx="12" cy="9" r="2.5"/><path d="M12 6.5a3 3 0 1 1 3 3 3 3 0 1 1-3 3 3 3 0 1 1-3-3 3 3 0 1 1 3-3Z"/><path d="M12 13v8M12 18c2 0 3.5-1 4-3"/>'),
  box: lineIcon('<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>'),
  truck: lineIcon('<path d="M3 6h11v10H3zM14 9h4l3 3.5V16h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>'),
  home: lineIcon('<path d="M4 11 12 4l8 7v9H4z"/><path d="m9 15 2 2 4-4"/>'),
  check: lineIcon('<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/>'),
  undo: lineIcon('<path d="M9 7 4 12l5 5"/><path d="M4 12h11a5 5 0 0 1 0 10h-2"/>'),
  cross: lineIcon('<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>'),
};
const NOTICE_ICON = lineIcon('<path d="M4 10v4h3l6 4V6L7 10z"/><path d="M16.5 9a4 4 0 0 1 0 6M19 6.5a7.5 7.5 0 0 1 0 11"/>');

// "Just now", "5m ago", "3h ago", "Yesterday", then the date
function relativeTime(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const minutes = Math.round((Date.now() - date.getTime()) / 60000);
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  if (hours < 48) return "Yesterday";
  return date.toLocaleDateString("en-PH", { month: "short", day: "numeric" });
}
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
  if (router.currentRoute.value.name !== "Shop") {
    router.push({ name: "Shop", query: { section: sectionId } });
    return;
  }
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
  // The whole message, with a button to its page when it has one
  showNotificationDropdown.value = false;
  openedNotice.value = notice;
};
const openedNotice = ref(null);
// Everything new in the panel: BloomCraft notices on the server, order updates on this device
const readAll = async () => {
  markOrderNotificationsAsSeen(orderNotifications.value);
  freshIds.value = new Set();
  if (noticeUnread.value || notices.value.some((n) => !n.read)) await readAllNotices();
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

// Notification categories: one open at a time. The panel opens on Your
// orders; opening From BloomCraft closes it, and closing the open one leaves both closed.
const ntOpen = ref("orders"); // "orders" | "bloomcraft" | null
const toggleNtSection = (key) => {
  ntOpen.value = ntOpen.value === key ? null : key;
};

const toggleNotificationDropdown = async () => {
  showNotificationDropdown.value = !showNotificationDropdown.value;
  showUserDropdown.value = false;
  if (showNotificationDropdown.value) ntOpen.value = "orders";

  if (showNotificationDropdown.value) {
    await Promise.all([loadOrderNotifications(), loadNotices(true)]);
    freshIds.value = new Set(orderNotifications.value.filter((n) => !seenOrderNotificationIds.value.has(n.id)).map((n) => n.id));
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
  showNotificationDropdown.value = false;
  if (!notification) {
    router.push("/customer/orders");
    return;
  }
  markOrderNotificationsAsSeen([notification]);
  const order = String(notification.orderNumber || "").replace(/^#/, "");
  router.push({ path: "/customer/orders", query: order ? { order } : {} });
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

  // What the customer sees for each step (the shop's own wording stays on its side)
  const statusMap = {
    pending: { title: "Order placed", text: "is waiting for the shop to accept it.", icon: "receipt", tone: "amber" },
    to_processed: { title: "Order accepted", text: "is in the shop's queue.", icon: "receipt", tone: "amber" },
    processing: { title: "Being prepared", text: "is being arranged by the florist.", icon: "flower", tone: "amber" },
    to_ship: { title: "Ready to ship", text: "is packed and waiting for the rider.", icon: "box", tone: "blue" },
    shipped: { title: "On the way", text: "has left the shop.", icon: "truck", tone: "blue" },
    to_received: { title: "Out for delivery", text: "is on its way to you.", icon: "truck", tone: "leaf" },
    delivered: { title: "Delivered", text: "has arrived. Enjoy your flowers!", icon: "home", tone: "leaf" },
    completed: { title: "Completed", text: "is complete. Thank you for ordering!", icon: "check", tone: "slate" },
    returned: { title: "Return in progress", text: "has a return being handled.", icon: "undo", tone: "amber" },
    refunded: { title: "Refunded", text: "has been refunded.", icon: "undo", tone: "slate" },
    cancelled: { title: "Cancelled", text: "was cancelled.", icon: "cross", tone: "red" },
  };

  const config = statusMap[status];
  if (!config) return null;

  const timestamp =
    order?.delivery?.last_scanned_at || order?.delivered_at || order?.created_at;

  return {
    id: `${order.id}-${status}`,
    ...config,
    orderNumber: order.order_number ? `#${String(order.order_number).replace(/^#/, "")}` : "",
    time: relativeTime(timestamp) || "Just now",
  };
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
      watchSupport();
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
  if (user.value?.role === "customer") unwatchSupport();
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
  justify-content: center;
  gap: 8px;
  min-width: 40px;
  height: 40px;
  padding: 0;
  border-radius: 999px;
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
  background: rgba(54, 63, 78, 0.06);
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

/* A count on the icon's top-right corner (cart and bell) */
.nav-badge {
  position: absolute;
  top: 2px;
  right: 0;
  display: grid;
  place-items: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border: 2px solid var(--paper, #fafbfa);
  border-radius: 999px;
  background: var(--leaf);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  box-sizing: content-box;
  transform: translate(25%, -25%);
  pointer-events: none;
}

.nav-badge--cart {
  background: var(--ink);
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
  width: 380px;
  padding: 0;
  overflow: hidden;
}

.nt-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--line-soft);
}

.nt-title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1.1;
  color: var(--ink);
}

.nt-sub {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--ink-soft);
}

.nt-action,
.nt-refresh,
.nt-all {
  border: none;
  background: none;
  font-family: var(--font-sans);
  cursor: pointer;
}

.nt-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 14px;
  border-radius: 999px;
  background: #e9f5ed;
  color: var(--leaf-deep);
  font-size: 12.5px;
  font-weight: 500;
  white-space: nowrap;
}

.nt-action:hover:not(:disabled) {
  background: #dcefe2;
}

.nt-action:disabled {
  background: var(--ivory);
  color: var(--ink-soft);
  cursor: default;
}

.nt-action svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.nt-scroll {
  max-height: min(440px, 62vh);
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
}

.nt-section {
  padding: 8px;
}

.nt-section + .nt-section {
  border-top: 1px solid var(--line-soft);
}

.nt-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nt-section-label {
  margin: 0;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink);
}

/* A category header: the whole row opens or closes it */
.nt-toggle {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 12px;
  border: none;
  border-radius: 12px;
  background: none;
  font-family: var(--font-sans);
  text-align: left;
  cursor: pointer;
  transition: background 0.2s ease;
}

.nt-toggle:hover {
  background: var(--ivory);
}

.nt-toggle:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: -2px;
}

.nt-count {
  display: inline-grid;
  place-items: center;
  min-width: 18px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--leaf);
  color: #ffffff;
  font-size: 10.5px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.nt-chevron {
  width: 18px;
  height: 18px;
  margin-left: auto;
  fill: none;
  stroke: var(--ink-soft);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.25s ease;
}

.nt-section.open .nt-chevron {
  transform: rotate(180deg);
}

.nt-panel {
  padding-top: 4px;
}

@media (prefers-reduced-motion: reduce) {
  .nt-chevron {
    transition: none;
  }
}

.nt-refresh {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  margin-right: 4px;
  border-radius: 50%;
  color: var(--ink-soft);
}

.nt-refresh:hover:not(:disabled) {
  background: var(--ivory);
  color: var(--ink);
}

.nt-refresh:disabled {
  opacity: 0.5;
  cursor: progress;
}

.nt-refresh svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.nt-item {
  position: relative;
  display: flex;
  gap: 12px;
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 14px;
  background: none;
  font-family: var(--font-sans);
  text-align: left;
  cursor: pointer;
  transition: background 0.2s ease;
}

.nt-item:hover {
  background: var(--ivory);
}

/* New since the last look: a soft green wash and a dot */
.nt-item.fresh {
  background: #f1f8f3;
}

.nt-item.fresh:hover {
  background: #e7f3eb;
}

.nt-item.important {
  background: #fdf3f1;
}

.nt-item:focus-visible,
.nt-action:focus-visible,
.nt-refresh:focus-visible,
.nt-all:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.nt-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
}

.nt-icon :deep(svg) {
  width: 20px;
  height: 20px;
}

.nt-icon.leaf { background: #e9f5ed; color: var(--leaf-deep); }
.nt-icon.amber { background: #fbf0dd; color: #8a5a12; }
.nt-icon.blue { background: #e8f0fb; color: #2b5d9b; }
.nt-icon.red { background: #fbe9e7; color: #b42318; }
.nt-icon.slate { background: #eef0f3; color: var(--ink-soft); }

.nt-icon-skel {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 12px;
}

.nt-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  padding-right: 10px;
}

.nt-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}

.nt-item-title {
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nt-time {
  flex-shrink: 0;
  font-size: 12px;
  color: var(--ink-soft);
  font-variant-numeric: tabular-nums;
}

.nt-text {
  display: -webkit-box;
  overflow: hidden;
  font-size: 13px;
  line-height: 1.5;
  color: var(--ink-soft);
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.nt-text b {
  font-weight: 600;
  color: var(--ink);
}

.nt-tag {
  align-self: flex-start;
  margin-top: 3px;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fbe9e7;
  color: #b42318;
  font-size: 12px;
  font-weight: 600;
}

.nt-unread {
  position: absolute;
  top: 18px;
  right: 12px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--leaf);
}

.nt-empty {
  display: grid;
  justify-items: center;
  gap: 6px;
  padding: 22px 16px 26px;
  text-align: center;
  font-size: 13px;
  color: var(--ink-soft);
}

.nt-empty strong {
  margin-top: 4px;
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
}

.nt-foot {
  padding: 8px;
  border-top: 1px solid var(--line-soft);
}

.nt-all {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 44px;
  border-radius: 12px;
  color: var(--leaf-deep);
  font-size: 13.5px;
  font-weight: 500;
}

.nt-all:hover {
  background: #e9f5ed;
}

.nt-all svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.notification-skeletons {
  padding: 0 4px 6px;
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
/* A support reply waiting: on the name, then on "Help & support" in the menu */
.nav-red-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 10px;
  height: 10px;
  border: 2px solid #fff;
  border-radius: 50%;
  background: #e53e3e;
}
.nav-red-dot.inline {
  position: static;
  margin-left: auto;
  border: 0;
  width: 8px;
  height: 8px;
}
.btn-user {
  position: relative;
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
}

@media (prefers-reduced-motion: reduce) {
  .nav-menu,
  .cart-pulse {
    animation: none;
  }
}
</style>
