<template>
  <div class="support" :class="`theme-${theme}`">
    <header class="support-head">
      <div>
        <p class="kicker">Help &amp; support</p>
        <h1>How can we help?</h1>
        <p class="lede">{{ categories.complaint ? "Ask BloomCraft, or tell a shop about a problem with your order. Replies come here, and you'll get a notification." : "Send BloomCraft a request and we'll reply here. You'll also get a notification." }}</p>
      </div>
      <button type="button" class="btn primary" @click="startNew">+ New request</button>
    </header>

    <div class="support-grid">
      <!-- My requests -->
      <section class="card list-card" aria-label="Your requests">
        <div class="list-head">
          <h2>Your requests</h2>
          <span class="muted">{{ tickets.length }}</span>
        </div>
        <div v-if="loading && !tickets.length" class="skeletons">
          <span v-for="n in 4" :key="n" class="sk"></span>
        </div>
        <p v-else-if="!tickets.length" class="empty">No requests yet.</p>
        <ul v-else class="ticket-list">
          <li v-for="t in tickets" :key="t.id">
            <button type="button" class="ticket" :class="{ active: active?.id === t.id }" @click="open(t.id)">
              <span class="ticket-top">
                <span class="ref">{{ t.reference }}</span>
                <span class="status" :class="`s-${t.status}`">{{ STATUS[t.status] }}</span>
              </span>
              <span class="ticket-subject">{{ t.subject }}</span>
              <span class="muted small">{{ t.category_label }}<template v-if="t.handled_by"> · {{ t.handled_by }}</template> · {{ timeAgo(t.last_activity_at) }}</span>
            </button>
          </li>
        </ul>
      </section>

      <!-- New request -->
      <section v-if="mode === 'new'" class="card">
        <h2>New request</h2>
        <form class="form" @submit.prevent="submit">
          <label class="field">
            <span>What's it about?</span>
            <select v-model="draft.category" required>
              <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <label class="field">
            <span>Subject</span>
            <input v-model="draft.subject" maxlength="160" required placeholder="A short summary" />
          </label>
          <p v-if="toShop" class="route-note">
            This goes straight to the shop that sold the order, and they'll reply here. BloomCraft Support can still step in if you need us.
          </p>
          <label class="field">
            <span>{{ toShop ? "Which order?" : "Order number (optional)" }}</span>
            <select v-model="draft.order_id" :required="toShop">
              <option value="">{{ toShop ? "Pick the order" : "Not about a specific order" }}</option>
              <option v-for="o in orders" :key="o.id" :value="o.id">{{ orderLabel(o) }}</option>
            </select>
            <small v-if="ordersLoaded && !orders.length" class="muted small">You have no orders yet.</small>
          </label>
          <label class="field">
            <span>Tell us what happened</span>
            <textarea v-model="draft.message" rows="7" maxlength="5000" required placeholder="Include anything that helps us look into it."></textarea>
          </label>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button v-if="tickets.length" type="button" class="btn" @click="mode = active ? 'view' : 'none'">Cancel</button>
            <button type="submit" class="btn primary" :disabled="busy">{{ busy ? "Sending…" : "Send request" }}</button>
          </div>
        </form>
      </section>

      <!-- Conversation -->
      <section v-else-if="mode === 'view'" class="card conversation">
        <template v-if="!active">
          <span v-for="n in 3" :key="n" class="sk tall"></span>
        </template>
        <template v-else>
          <div class="conv-head">
            <div>
              <span class="ref">{{ active.reference }} · {{ active.category_label }}</span>
              <h2>{{ active.subject }}</h2>
              <span v-if="active.order" class="muted small">About order {{ active.order.order_number }}</span>
              <span v-if="active.handled_by" class="muted small handled">Handled by {{ active.handled_by }}</span>
            </div>
            <span class="status" :class="`s-${active.status}`">{{ STATUS[active.status] }}</span>
          </div>
          <ol class="messages">
            <li v-for="m in active.messages" :key="m.id" :class="{ mine: !m.from_admin }">
              <span class="who">{{ m.author }} · {{ formatDateTime(m.created_at) }}</span>
              <p class="bubble">{{ m.body }}</p>
            </li>
          </ol>
          <form v-if="active.status !== 'closed'" class="reply" @submit.prevent="sendReply">
            <textarea v-model="reply" rows="3" maxlength="5000" :placeholder="active.status === 'resolved' ? 'Still need help? Writing here reopens the request.' : 'Write a reply…'"></textarea>
            <p v-if="error" class="error">{{ error }}</p>
            <div class="actions">
              <button v-if="['open', 'in_progress', 'waiting'].includes(active.status)" type="button" class="btn" :disabled="busy" @click="resolve">My issue is solved</button>
              <button type="submit" class="btn primary" :disabled="busy || reply.trim().length < 2">{{ busy ? "Sending…" : "Send" }}</button>
            </div>
          </form>
          <p v-else class="muted closed-note">This request is closed. Start a new one if you need more help.</p>
        </template>
      </section>

      <section v-else class="card hint-card">
        <h2>Pick a request</h2>
        <p class="muted">Choose one on the left to see the conversation, or start a new request.</p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import { linkedId } from "../../utils/hiddenIds";

/** Raise and follow support requests (used by vendors and customers) */
const props = defineProps({
  theme: { type: String, default: "vendor" }, // vendor | shop
  ordersEndpoint: { type: String, default: null },
});

const route = useRoute();
const router = useRouter();
const STATUS = { open: "Waiting for a reply", in_progress: "Being handled", waiting: "Waiting for you", resolved: "Solved", closed: "Closed" };

const tickets = ref([]);
const categories = ref({});
// Complaint topics go to the shop that sold the order, so they need one
const shopCategories = ref([]);
const toShop = computed(() => shopCategories.value.includes(draft.category));
const orders = ref([]);
const ordersLoaded = ref(false);
const active = ref(null);
const mode = ref("none");
const loading = ref(false);
const busy = ref(false);
const error = ref("");
const reply = ref("");
const draft = reactive({ category: "orders", subject: "", order_id: "", message: "" });

const loadList = async () => {
  loading.value = true;
  try {
    const { data } = await api.get("/support/tickets");
    tickets.value = data.data;
    categories.value = data.categories;
    shopCategories.value = data.shop_categories ?? [];
  } catch {
    toast.error("Your requests couldn't be loaded.");
  } finally {
    loading.value = false;
  }
};

const loadOrders = async () => {
  if (!props.ordersEndpoint) return;
  try {
    const { data } = await api.get(props.ordersEndpoint, { params: { per_page: 30 } });
    // Customer orders come as data[]; vendor orders as data.orders[] (paginated)
    const raw = data?.data;
    const list = Array.isArray(raw) ? raw : raw?.orders ?? raw?.data ?? [];
    orders.value = (Array.isArray(list) ? list : [])
      .filter((o) => o?.id && o?.order_number)
      .sort((a, b) => new Date(b.created_at ?? 0) - new Date(a.created_at ?? 0))
      .slice(0, 30);
  } catch {
    orders.value = [];
  } finally {
    ordersLoaded.value = true;
  }
};

/** "#BC-1024 · Oct 3 · ₱1,250.00" so the right order is easy to spot */
const orderLabel = (o) => {
  const when = o.created_at ? new Date(o.created_at).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "";
  const total = o.total_amount != null ? `₱${Number(o.total_amount).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : "";
  return [o.order_number, when, total].filter(Boolean).join(" · ");
};

const open = async (id) => {
  mode.value = "view";
  error.value = "";
  reply.value = "";
  if (active.value?.id !== id) active.value = null;
  router.replace({ query: { ...route.query, ticket: id } });
  try {
    const { data } = await api.get(`/support/tickets/${id}`);
    active.value = data.data;
  } catch {
    toast.error("That request couldn't be opened.");
    mode.value = "none";
  }
};

const startNew = () => {
  Object.assign(draft, { category: "orders", subject: "", order_id: "", message: "" });
  error.value = "";
  mode.value = "new";
  if (!orders.value.length) loadOrders();
};

const firstError = (e) => {
  const errors = e.response?.data?.errors;
  return errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "That didn't go through. Try again.";
};

const submit = async () => {
  error.value = "";
  if (toShop.value && !draft.order_id) {
    error.value = "Pick the order this is about, so the shop can help.";
    return;
  }
  busy.value = true;
  try {
    const { data } = await api.post("/support/tickets", { ...draft, order_id: draft.order_id || null });
    toast.success(data.message);
    await loadList();
    active.value = data.data;
    mode.value = "view";
    router.replace({ query: { ...route.query, ticket: data.data.id } });
  } catch (e) {
    error.value = firstError(e);
  } finally {
    busy.value = false;
  }
};

const sendReply = async () => {
  busy.value = true;
  error.value = "";
  try {
    const { data } = await api.post(`/support/tickets/${active.value.id}/messages`, { message: reply.value });
    active.value = data.data;
    reply.value = "";
    loadList();
  } catch (e) {
    error.value = firstError(e);
  } finally {
    busy.value = false;
  }
};

const resolve = async () => {
  busy.value = true;
  try {
    const { data } = await api.post(`/support/tickets/${active.value.id}/resolve`);
    active.value = data.data;
    toast.success(data.message);
    loadList();
  } catch (e) {
    error.value = firstError(e);
  } finally {
    busy.value = false;
  }
};

const timeAgo = (v) => {
  if (!v) return "";
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "just now" : `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? "" : "s"} ago`;
};
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "";

onMounted(async () => {
  await loadList();
  const fromLink = linkedId("ticket");
  if (fromLink) open(fromLink);
  else if (!tickets.value.length) startNew();
});
</script>

<style scoped>
.support {
  --accent: #7c6ff7;
  --accent-dark: #5b4fc9;
  --accent-soft: #f5f3ff;
  --text: #1e2739;
  --muted: #94a3b8;
  --line: #e9edf3;
  --card: #fff;
  --mine: #f5f3ff;
  --radius: 14px;
  color: var(--text);
}
/* Vendor pages follow the vendor light/dark tokens */
.theme-vendor {
  --accent-soft: var(--v-purple-soft);
  --text: var(--v-text);
  --muted: var(--v-muted);
  --line: var(--v-border);
  --card: var(--v-surface);
  --mine: var(--v-purple-soft);
}
.theme-shop {
  --accent: #1b1612;
  --accent-dark: #000;
  --accent-soft: #f7ede9;
  --text: #1e1b18;
  --muted: #6e6761;
  --line: rgba(30, 27, 24, 0.12);
  --card: #faf8f4;
  --mine: #f2e9e1;
  --radius: 20px;
}
.support-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.kicker {
  margin: 0;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--accent);
}
.theme-shop .kicker {
  color: var(--v-red-text);
}
.support-head h1 {
  margin: 6px 0 4px;
  font-size: 26px;
}
.theme-shop .support-head h1 {
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 46px;
  font-weight: 500;
}
.lede {
  margin: 0;
  color: var(--muted);
  font-size: 13.5px;
}
.support-grid {
  display: grid;
  grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 18px 20px;
  min-width: 0;
}
.card h2 {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 600;
}
.list-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.muted {
  color: var(--muted);
}
.small {
  font-size: 12px;
}
.empty {
  color: var(--muted);
  font-size: 13px;
  margin: 8px 0;
}
.ticket-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ticket {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
}
.ticket:hover {
  background: var(--accent-soft);
}
.ticket.active {
  background: var(--accent-soft);
  border-color: var(--accent);
}
.ticket-top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.ref {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 11.5px;
  color: var(--muted);
}
.ticket-subject {
  font-size: 13.5px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.status {
  font-size: 10.5px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
  align-self: flex-start;
}
.s-open { background: var(--v-amber-soft); color: var(--v-amber-text); }
.s-waiting { background: var(--v-blue-soft); color: var(--v-blue-text); }
.s-in_progress { background: var(--v-purple-soft); color: var(--v-purple-text); }
.route-note {
  margin: -4px 0 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.handled {
  display: block;
}
.s-resolved { background: var(--v-green-soft); color: var(--v-green-text); }
.s-closed { background: var(--v-subtle); color: var(--v-muted); }
.form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 500;
}
.field input,
.field select,
.field textarea,
.reply textarea {
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  font: inherit;
  font-size: 13.5px;
  color: var(--text);
  background: var(--v-input);
  resize: vertical;
  width: 100%;
  box-sizing: border-box;
}
.field input:focus,
.field select:focus,
.field textarea:focus,
.reply textarea:focus {
  outline: none;
  border-color: var(--accent);
}
.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}
.btn {
  padding: 9px 16px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: var(--v-surface);
  color: var(--text);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.theme-shop .btn {
  border-radius: 999px;
}
.btn.primary {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
.btn.primary:hover:not(:disabled) {
  background: var(--accent-dark);
}
.btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.error {
  margin: 0;
  color: var(--v-red-text);
  font-size: 12.5px;
}
.conv-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--line);
}
.conv-head h2 {
  margin: 4px 0;
}
.messages {
  list-style: none;
  margin: 16px 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 52vh;
  overflow-y: auto;
}
.messages li {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 85%;
}
.messages li.mine {
  align-self: flex-end;
  align-items: flex-end;
}
.who {
  font-size: 11.5px;
  color: var(--muted);
}
.bubble {
  margin: 0;
  padding: 10px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
  font-size: 13.5px;
  line-height: 1.55;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.mine .bubble {
  background: var(--mine);
}
.reply {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px solid var(--line);
  padding-top: 14px;
}
.closed-note {
  font-size: 13px;
  margin: 0;
}
.hint-card p {
  margin: 0;
  font-size: 13px;
}
.skeletons {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk {
  display: block;
  height: 56px;
  border-radius: 10px;
  background: linear-gradient(100deg, var(--v-subtle-2) 30%, var(--v-subtle) 50%, var(--v-subtle-2) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk.tall {
  height: 80px;
  margin-bottom: 10px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}
@media (max-width: 860px) {
  .support-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
</style>
