<template>
  <ShopLayout section="crm" title="Notifications" subtitle="New orders, payments, low stock, customer messages and more, in one place. Choose what you're told about on the right.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="!data?.unread || marking" @click="readAll"><span v-html="ICONS.check"></span>Mark all as read</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load()">Try again</button>
    </div>

    <div class="nt-layout">
      <section class="sp-card nt-feed">
        <div class="nt-filters" role="tablist" aria-label="Show">
          <button type="button" role="tab" class="sp-pick" :class="{ on: !type && !unreadOnly }" :aria-selected="!type && !unreadOnly" @click="setFilter(null, false)">All</button>
          <button type="button" role="tab" class="sp-pick" :class="{ on: unreadOnly }" :aria-selected="unreadOnly" @click="setFilter(null, true)">
            Unread<b v-if="data?.unread">{{ data.unread }}</b>
          </button>
          <button v-for="k in FILTERS" :key="k" type="button" role="tab" class="sp-pick" :class="{ on: type === k }" :aria-selected="type === k" @click="setFilter(k, false)">
            {{ NOTIFICATION_KINDS[k].label }}<b v-if="data?.unread_by_type?.[k]">{{ data.unread_by_type[k] }}</b>
          </button>
        </div>

        <ul v-if="!data" class="nt-list">
          <li v-for="n in 6" :key="n" class="nt-item sk"><div class="bc-skeleton nt-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></li>
        </ul>
        <div v-else-if="!data.items.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.bell"></span>
          <h3>{{ unreadOnly ? "You're all caught up" : "Nothing here yet" }}</h3>
          <p>{{ unreadOnly ? "No unread notifications." : "When something happens in your shop, you'll see it here and on the bell at the top." }}</p>
        </div>
        <template v-else>
          <section v-for="g in grouped" :key="g.day" class="nt-day">
            <h3>{{ g.day }}</h3>
            <ul class="nt-list">
              <li v-for="n in g.items" :key="n.id">
                <button type="button" class="nt-item" :class="{ unread: !n.read, important: n.important && !n.read }" @click="open(n)">
                  <span class="nt-icon" :class="`tone-${kind(n).tone}`" v-html="ICONS[kind(n).icon]"></span>
                  <span class="nt-main">
                    <strong>{{ n.title }}</strong>
                    <span v-if="n.body" class="nt-body">{{ n.body }}</span>
                    <small>{{ kind(n).label }} · {{ timeAgo(n.created_at) }}</small>
                  </span>
                  <span v-if="!n.read" class="nt-dot" aria-label="Unread"></span>
                  <span v-if="n.link_url" class="nt-go" v-html="ICONS.right"></span>
                </button>
              </li>
            </ul>
          </section>
          <nav v-if="data.meta.last_page > 1" class="nt-pages" aria-label="Pages">
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="data.meta.page <= 1" @click="load(data.meta.page - 1)"><span v-html="ICONS.left"></span>Newer</button>
            <span class="st-sub">Page {{ data.meta.page }} of {{ data.meta.last_page }}</span>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="data.meta.page >= data.meta.last_page" @click="load(data.meta.page + 1)">Older<span v-html="ICONS.right"></span></button>
          </nav>
        </template>
      </section>

      <aside class="sp-card nt-prefs" aria-label="Notification settings">
        <div class="sp-card-head"><div><h2>Tell me about</h2><p>Turned-off events stop arriving. Ones you already have stay.</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <ul v-else class="nt-pref-list">
          <li v-for="(t, key) in data.types" :key="key">
            <label>
              <span class="nt-pref-icon" :class="`tone-${KIND_FOR[key].tone}`" v-html="ICONS[KIND_FOR[key].icon]"></span>
              <span class="nt-pref-text"><b>{{ t.label }}</b><small>{{ t.hint }}</small></span>
              <span class="sp-switch"><input v-model="prefs[key]" type="checkbox" :aria-label="t.label" @change="savePrefs" /><span></span></span>
            </label>
          </li>
        </ul>
        <p class="sp-hint nt-note">Announcements from BloomCraft and replies to your support requests always come through.</p>
      </aside>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { NOTIFICATION_KINDS, dayHeading, notificationTarget, timeAgo } from "../../../components/vendor/crm/crmUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
import "../../../components/vendor/finance/finance.css";

const FILTERS = ["order", "payment", "stock", "message", "inquiry", "review", "return", "announcement"];
// Settings keys → how they look
const KIND_FOR = {
  orders: NOTIFICATION_KINDS.order,
  payments: NOTIFICATION_KINDS.payment,
  stock: NOTIFICATION_KINDS.stock,
  messages: NOTIFICATION_KINDS.message,
  inquiries: NOTIFICATION_KINDS.inquiry,
  reviews: NOTIFICATION_KINDS.review,
  returns: NOTIFICATION_KINDS.return,
};

const router = useRouter();
const data = ref(null);
const error = ref("");
const type = ref(null);
const unreadOnly = ref(false);
const marking = ref(false);
const prefs = reactive({});

const kind = (n) => NOTIFICATION_KINDS[n.type] ?? NOTIFICATION_KINDS.support;
const grouped = computed(() => {
  const out = [];
  for (const n of data.value?.items ?? []) {
    const day = dayHeading(n.created_at);
    if (!out.length || out[out.length - 1].day !== day) out.push({ day, items: [] });
    out[out.length - 1].items.push(n);
  }
  return out;
});

async function load(page = 1) {
  error.value = "";
  try {
    const res = await api.get("vendor/crm/notifications", { params: { page, type: type.value || undefined, unread: unreadOnly.value ? 1 : undefined } });
    data.value = res.data.data;
    Object.assign(prefs, data.value.preferences);
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your notifications.");
  }
}
function setFilter(t, unread) {
  type.value = t;
  unreadOnly.value = unread;
  load();
}

async function open(n) {
  if (!n.read) {
    n.read = true;
    data.value.unread = Math.max(0, data.value.unread - 1);
    if (data.value.unread_by_type?.[n.type]) data.value.unread_by_type[n.type] -= 1;
    api.post(`/notifications/${n.id}/read`).catch(() => {});
  }
  const target = notificationTarget(n.link_url);
  if (!target) return;
  if (typeof target === "string" && /^https?:\/\//i.test(target)) window.open(target, "_blank", "noopener");
  else router.push(target);
}

async function readAll() {
  marking.value = true;
  try {
    await api.post("/notifications/read-all");
    data.value.items.forEach((n) => (n.read = true));
    data.value.unread = 0;
    data.value.unread_by_type = {};
    toast.success("All caught up.");
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't mark them as read."));
  } finally {
    marking.value = false;
  }
}

async function savePrefs() {
  try {
    const { data: res } = await api.put("vendor/crm/notifications/preferences", { settings: { ...prefs } });
    Object.assign(prefs, res.data);
    toast.success(res.message);
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't save your settings."));
    if (data.value) Object.assign(prefs, data.value.preferences);
  }
}

onMounted(() => load());
</script>

<style scoped>
.nt-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 16px;
  align-items: start;
}
.nt-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.nt-filters b {
  margin-left: 6px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  font-size: 11px;
  font-weight: 600;
}
.nt-day h3 {
  margin: 16px 0 4px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.nt-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.nt-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  padding: 12px 10px;
  border: 0;
  border-radius: 12px;
  background: none;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}
.nt-item.sk {
  cursor: default;
}
.nt-item .grow {
  flex: 1;
}
.nt-item:not(.sk):hover {
  background: var(--v-subtle);
}
.nt-item.unread {
  background: var(--v-subtle);
}
.nt-item.important {
  box-shadow: inset 3px 0 0 var(--bc-accent);
}
.nt-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
}
.nt-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.nt-main {
  flex: 1;
  min-width: 0;
}
.nt-main strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.nt-item.unread .nt-main strong {
  font-weight: 700;
}
.nt-body {
  display: block;
  margin-top: 2px;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--v-text-2);
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow-wrap: anywhere;
}
.nt-main small {
  display: block;
  margin-top: 4px;
  font-size: 11.5px;
  color: var(--v-faint);
}
.nt-dot {
  flex: none;
  width: 9px;
  height: 9px;
  margin-top: 6px;
  border-radius: 50%;
  background: var(--bc-accent);
}
.nt-go {
  flex: none;
  align-self: center;
  color: var(--v-faint);
}
.nt-go :deep(svg) {
  width: 16px;
  height: 16px;
}
.nt-pages {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
.nt-prefs {
  position: sticky;
  top: 16px;
}
.nt-pref-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.nt-pref-list li + li {
  border-top: 1px solid var(--v-border);
}
.nt-pref-list label {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 0;
  cursor: pointer;
}
.nt-pref-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
}
.nt-pref-icon :deep(svg) {
  width: 16px;
  height: 16px;
}
.nt-pref-text {
  flex: 1;
  min-width: 0;
}
.nt-pref-text b {
  display: block;
  font-size: 13px;
  font-weight: 600;
}
.nt-pref-text small {
  font-size: 12px;
  color: var(--v-muted);
}
.nt-note {
  margin: 10px 0 0;
}
@media (max-width: 960px) {
  .nt-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .nt-prefs {
    position: static;
    order: -1;
  }
}
</style>
