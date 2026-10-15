<template>
  <div ref="root" class="bell-wrap">
    <button type="button" class="bell" :aria-expanded="openMenu" aria-label="Notifications" @click="toggle">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
      <span v-if="unread" class="dot">{{ unread > 9 ? "9+" : unread }}</span>
    </button>

    <div v-if="openMenu" class="menu" role="dialog" aria-label="Notifications">
      <div class="menu-head">
        <strong>Notifications</strong>
        <button v-if="unread" type="button" class="link" @click="readAll">Mark all read</button>
      </div>
      <div v-if="loading && !items.length" class="menu-body">
        <span v-for="n in 3" :key="n" class="sk"></span>
      </div>
      <p v-else-if="!items.length" class="empty">You're all caught up.</p>
      <ul v-else class="menu-body list">
        <li v-for="n in items" :key="n.id">
          <button type="button" class="item" :class="{ unread: !n.read, important: n.important }" @click="openItem(n)">
            <span class="item-icon">{{ ICON[n.type] ?? "💬" }}</span>
            <span class="item-text">
              <span class="item-title">{{ n.title }}</span>
              <span v-if="n.body" class="item-body">{{ n.body }}</span>
              <span class="item-time">{{ timeAgo(n.created_at) }}</span>
            </span>
          </button>
        </li>
      </ul>
      <router-link v-if="allPath" :to="allPath" class="menu-foot" @click="openMenu = false">See all notifications →</router-link>
      <router-link v-else-if="supportPath" :to="supportPath" class="menu-foot" @click="openMenu = false">Help &amp; support →</router-link>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";

/** Inbox for announcements from BloomCraft and support replies */
const props = defineProps({
  supportPath: { type: String, default: "/vendor/support" },
  // Where "See all" goes (vendors: Customer relationships → Notifications)
  allPath: { type: [String, Object], default: null },
});

const ICON = { announcement: "📣", order: "🧾", payment: "💳", stock: "📦", message: "💬", inquiry: "❓", review: "⭐", return: "↩️", inquiry_answer: "💡", review_reply: "💬" };

/** "route:VendorOrderDetails?order=12" → a named route; the order ID travels hidden */
const target = (link) => {
  if (!link.startsWith("route:")) return link;
  const [name, qs] = link.slice(6).split("?");
  return { name, query: Object.fromEntries(new URLSearchParams(qs ?? "")) };
};
const router = useRouter();

const root = ref(null);
const openMenu = ref(false);
const items = ref([]);
const unread = ref(0);
const loading = ref(false);

const fetchCount = async () => {
  try {
    unread.value = (await api.get("/notifications/unread-count")).data.unread ?? 0;
  } catch {
    // keep the last count
  }
};
const fetchItems = async () => {
  loading.value = true;
  try {
    const { data } = await api.get("/notifications");
    items.value = data.data;
    unread.value = data.unread;
  } catch {
    items.value = [];
  } finally {
    loading.value = false;
  }
};

const toggle = () => {
  openMenu.value = !openMenu.value;
  if (openMenu.value) fetchItems();
};

const openItem = async (n) => {
  if (!n.read) {
    n.read = true;
    try {
      unread.value = (await api.post(`/notifications/${n.id}/read`)).data.unread;
    } catch {
      // stays unread on the server; harmless
    }
  }
  if (n.link_url) {
    openMenu.value = false;
    if (/^https?:\/\//i.test(n.link_url)) window.open(n.link_url, "_blank", "noopener");
    else router.push(target(n.link_url));
  }
};

const readAll = async () => {
  items.value.forEach((n) => (n.read = true));
  unread.value = 0;
  try {
    await api.post("/notifications/read-all");
  } catch {
    fetchCount();
  }
};

const timeAgo = (v) => {
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "just now" : `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? "" : "s"} ago`;
};

const onClickAway = (e) => {
  if (openMenu.value && root.value && !root.value.contains(e.target)) openMenu.value = false;
};

let timer = null;
onMounted(() => {
  fetchCount();
  timer = setInterval(fetchCount, 60_000);
  document.addEventListener("click", onClickAway);
});
onBeforeUnmount(() => {
  clearInterval(timer);
  document.removeEventListener("click", onClickAway);
});
defineExpose({ refresh: fetchCount });
</script>

<style scoped>
.bell-wrap {
  position: relative;
}
.bell {
  position: relative;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  border: 1px solid var(--v-border-strong);
  background: var(--v-surface);
  color: var(--v-text-2);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.bell:hover {
  color: var(--v-blue-text);
  border-color: #c7c2fb;
}
.dot {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: #e11d48;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.menu {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: min(360px, calc(100vw - 24px));
  background: var(--v-raised);
  border: 1px solid var(--v-border);
  border-radius: 14px;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
  z-index: 500;
  overflow: hidden;
  font-family: "Poppins", "Segoe UI", sans-serif;
}
.menu-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  border-bottom: 1px solid var(--v-border);
  font-size: 13.5px;
  color: var(--v-text);
}
.link {
  border: none;
  background: none;
  color: #6d5ff0;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}
.menu-body {
  max-height: 360px;
  overflow-y: auto;
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.list {
  list-style: none;
  margin: 0;
}
.item {
  width: 100%;
  display: flex;
  gap: 10px;
  padding: 10px;
  border: none;
  border-radius: 10px;
  background: transparent;
  text-align: left;
  font: inherit;
  cursor: pointer;
}
.item:hover {
  background: var(--v-subtle);
}
.item.unread {
  background: var(--v-purple-soft);
}
.item.important .item-title::after {
  content: " · Important";
  color: var(--v-red-text);
  font-weight: 600;
}
.item-icon {
  font-size: 16px;
  line-height: 1.4;
}
.item-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.item-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.item-body {
  font-size: 12.5px;
  color: var(--v-text-2);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.item-time {
  font-size: 11px;
  color: var(--v-faint);
}
.empty {
  margin: 0;
  padding: 24px 14px;
  text-align: center;
  color: var(--v-faint);
  font-size: 13px;
}
.menu-foot {
  display: block;
  padding: 10px 14px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: #6d5ff0;
  text-decoration: none;
}
.sk {
  display: block;
  height: 52px;
  border-radius: 10px;
  background: linear-gradient(100deg, var(--v-subtle-2) 30%, var(--v-subtle) 50%, var(--v-subtle-2) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}
</style>
