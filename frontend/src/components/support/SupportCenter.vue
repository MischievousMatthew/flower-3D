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
              <span class="muted small">{{ t.issue_label || t.category_label }}<template v-if="t.handled_by"> · {{ t.handled_by }}</template> · {{ timeAgo(t.last_activity_at) }}</span>
            </button>
          </li>
        </ul>
      </section>

      <!-- New request -->
      <section v-if="mode === 'new'" class="card">
        <h2>New request</h2>
        <form class="form" novalidate @submit.prevent="submit">
          <!-- 1. The kind of problem -->
          <fieldset class="step">
            <legend><span class="step-no">1</span> What's it about?</legend>
            <div v-for="group in topicGroups" :key="group.title" class="topic-group">
              <p v-if="topicGroups.length > 1" class="group-title">
                {{ group.title }}<small>{{ group.note }}</small>
              </p>
              <div class="topics" role="radiogroup" :aria-label="group.title">
                <label v-for="t in group.topics" :key="t.key" class="topic" :class="{ on: draft.category === t.key }">
                  <input v-model="draft.category" type="radio" name="topic" :value="t.key" class="sr" @change="pickTopic" />
                  <svg class="topic-icon" viewBox="0 0 24 24" aria-hidden="true"><path :d="TOPIC_ICONS[t.key] || TOPIC_ICONS.other" /></svg>
                  <span class="topic-text">
                    <strong>{{ t.label }}</strong>
                    <small>{{ t.hint }}</small>
                  </span>
                </label>
              </div>
            </div>
            <p v-if="fieldErrors.category" class="error">{{ fieldErrors.category }}</p>
          </fieldset>

          <!-- 2. The exact problem -->
          <fieldset v-if="topic && topic.issues.length" class="step">
            <legend><span class="step-no">2</span> What happened?</legend>
            <div class="chips" role="radiogroup" aria-label="What happened">
              <label v-for="i in topic.issues" :key="i.key" class="chip" :class="{ on: draft.issue === i.key }">
                <input v-model="draft.issue" type="radio" name="issue" :value="i.key" class="sr" @change="pickIssue" />
                {{ i.label }}
              </label>
            </div>
            <p v-if="fieldErrors.issue" class="error">{{ fieldErrors.issue }}</p>
          </fieldset>

          <!-- 3. Which order -->
          <fieldset v-if="topic && (toShop || ordersEndpoint)" class="step">
            <legend><span class="step-no">3</span> {{ toShop ? "Which order?" : "Is it about an order?" }}<small v-if="!toShop" class="optional">Optional</small></legend>
            <p v-if="toShop" class="route-note">
              This goes straight to the shop that sold the order, and they'll reply here. BloomCraft Support can still step in if you need us.
            </p>
            <div v-if="!ordersLoaded" class="skeletons">
              <span v-for="n in 2" :key="n" class="sk short"></span>
            </div>
            <p v-else-if="!orders.length" class="muted small">You have no orders yet.</p>
            <div v-else class="orders" role="radiogroup" aria-label="Order">
              <label v-if="!toShop" class="order" :class="{ on: !draft.order_id }">
                <input v-model="draft.order_id" type="radio" name="order" value="" class="sr" />
                <span class="order-main"><strong>Not about a specific order</strong></span>
              </label>
              <label v-for="o in shownOrders" :key="o.id" class="order" :class="{ on: draft.order_id === o.id }">
                <input v-model="draft.order_id" type="radio" name="order" :value="o.id" class="sr" />
                <span class="order-main">
                  <strong>{{ o.order_number }}</strong>
                  <small>{{ orderMeta(o) }}</small>
                </span>
                <span class="order-total">{{ money(o.total_amount) }}</span>
              </label>
              <button v-if="orders.length > shownOrders.length" type="button" class="link-btn" @click="allOrders = true">
                Show older orders ({{ orders.length - shownOrders.length }})
              </button>
            </div>
            <p v-if="fieldErrors.order_id" class="error">{{ fieldErrors.order_id }}</p>
          </fieldset>

          <!-- 4. What they'd like done (complaints to a shop) -->
          <fieldset v-if="toShop && outcomes.length" class="step">
            <legend><span class="step-no">4</span> What would you like the shop to do?<small class="optional">Optional</small></legend>
            <div class="chips" role="radiogroup" aria-label="What you'd like done">
              <label v-for="o in outcomes" :key="o.key" class="chip" :class="{ on: draft.desired_outcome === o.key }">
                <input v-model="draft.desired_outcome" type="radio" name="outcome" :value="o.key" class="sr" @click="toggleOutcome(o.key)" />
                {{ o.label }}
              </label>
            </div>
          </fieldset>

          <!-- Last: in their own words -->
          <fieldset v-if="topic" class="step">
            <legend><span class="step-no">{{ lastStep }}</span> Tell us more</legend>
            <label class="field">
              <span>Subject</span>
              <input v-model="draft.subject" maxlength="160" placeholder="A short summary" @input="subjectEdited = true" />
              <small v-if="fieldErrors.subject" class="error">{{ fieldErrors.subject }}</small>
            </label>
            <label class="field">
              <span>Details</span>
              <textarea v-model="draft.message" rows="6" maxlength="5000" :placeholder="messageHint"></textarea>
              <span class="field-foot">
                <small v-if="fieldErrors.message" class="error">{{ fieldErrors.message }}</small>
                <small class="muted count">{{ draft.message.length }}/5000</small>
              </span>
            </label>
          </fieldset>

          <p v-if="error" class="error" role="alert">{{ error }}</p>
          <div class="actions">
            <span v-if="topic" class="goes-to muted small">Goes to {{ goesTo }}</span>
            <button v-if="tickets.length" type="button" class="btn" @click="mode = active ? 'view' : 'none'">Cancel</button>
            <button type="submit" class="btn primary" :disabled="busy || !topic">{{ busy ? "Sending…" : "Send request" }}</button>
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
              <span v-if="active.issue_label" class="issue-tag">{{ active.issue_label }}</span>
              <span v-if="active.desired_outcome_label" class="muted small handled">You asked for: {{ active.desired_outcome_label.toLowerCase() }}</span>
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

// Simple line icons for each topic (24px grid)
const TOPIC_ICONS = {
  complaint: "M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2",
  quality: "M12 21c-4-3-7-6-7-10a7 7 0 0 1 14 0c0 4-3 7-7 10Zm0-13v6m0 3h.01",
  delivery: "M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z",
  service: "M4 5h16v11H8l-4 4V5Zm5 5h.01M12 10h.01M15 10h.01",
  orders: "M5 4h14v16H5V4Zm4 5h6m-6 4h6m-6 4h4",
  payments: "M3 6h18v12H3V6Zm0 4h18M7 15h3",
  account: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0",
  products: "M12 3 4 7v10l8 4 8-4V7l-8-4Zm0 8 8-4M12 11 4 7m8 4v10",
  subscription: "M4 7h16v10H4V7Zm4-3h8M8 20h8",
  technical: "M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z",
  other: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-2.5-11.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.4V14m0 3h.01",
};
// A prompt in the details box that fits the topic
const MESSAGE_HINTS = {
  complaint: "What did you expect, and what arrived? Mention the items involved.",
  quality: "Which flowers, and what's wrong with them? When did you notice?",
  delivery: "When was it due, and when (or whether) it came? Any message from the rider?",
  service: "What happened, and when? Who did you talk to?",
  payments: "When did you pay, how much, and with which method? Don't share card numbers.",
  account: "What happens when you try? Include any message you see. Never share your password.",
  technical: "What were you doing, which page, and what did you see? Phone or computer?",
};

const tickets = ref([]);
const categories = ref({});
// Complaint topics go to the shop that sold the order, so they need one
const shopCategories = ref([]);
const topics = ref([]);
const outcomes = ref([]);
const toShop = computed(() => shopCategories.value.includes(draft.category));
const orders = ref([]);
const ordersLoaded = ref(false);
const allOrders = ref(false);
const active = ref(null);
const mode = ref("none");
const loading = ref(false);
const busy = ref(false);
const error = ref("");
const fieldErrors = reactive({});
const reply = ref("");
const subjectEdited = ref(false);
const blankDraft = () => ({ category: "", issue: "", desired_outcome: "", subject: "", order_id: "", message: "" });
const draft = reactive(blankDraft());

const topic = computed(() => topics.value.find((t) => t.key === draft.category) ?? null);
// Customers see complaints to a shop apart from questions for BloomCraft
const topicGroups = computed(() => {
  const shop = topics.value.filter((t) => t.to_shop);
  const rest = topics.value.filter((t) => !t.to_shop);
  if (!shop.length) return [{ title: "Topics", topics: rest }];
  return [
    { title: "A problem with an order", note: "Goes to the shop that sold it", topics: shop },
    { title: "BloomCraft and your account", note: "Goes to BloomCraft Support", topics: rest },
  ];
});
const shownOrders = computed(() => (allOrders.value ? orders.value : orders.value.slice(0, 5)));
const lastStep = computed(() => 1 + [topic.value?.issues.length, toShop.value || props.ordersEndpoint, toShop.value && outcomes.value.length].filter(Boolean).length + 1);
const messageHint = computed(() => MESSAGE_HINTS[draft.category] ?? "Include anything that helps us look into it.");
const goesTo = computed(() => {
  if (!toShop.value) return "BloomCraft Support";
  const shop = orders.value.find((o) => o.id === draft.order_id)?.store_name;
  return shop || "the shop that sold the order";
});

const pickTopic = () => {
  draft.issue = "";
  draft.desired_outcome = "";
  if (!subjectEdited.value) draft.subject = "";
  if (!toShop.value) return;
  // A complaint needs an order; preselect the only one
  if (orders.value.length === 1) draft.order_id = orders.value[0].id;
};
const pickIssue = () => {
  fieldErrors.issue = "";
  const label = topic.value?.issues.find((i) => i.key === draft.issue)?.label;
  // The issue makes a good subject until they write their own
  if (label && !subjectEdited.value) draft.subject = label;
};
const toggleOutcome = (key) => {
  if (draft.desired_outcome === key) setTimeout(() => (draft.desired_outcome = ""));
};

const loadList = async () => {
  loading.value = true;
  try {
    const { data } = await api.get("/support/tickets");
    tickets.value = data.data;
    categories.value = data.categories;
    shopCategories.value = data.shop_categories ?? [];
    topics.value = data.topics ?? Object.entries(data.categories ?? {}).map(([key, label]) => ({ key, label, hint: "", issues: [] }));
    outcomes.value = data.outcomes ?? [];
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

/** "Petal & Stem · Oct 3, 2026 · 3 items" so the right order is easy to spot */
const orderMeta = (o) => {
  const when = o.created_at ? new Date(o.created_at).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "";
  const items = o.items_count ? `${o.items_count} item${o.items_count === 1 ? "" : "s"}` : "";
  return [o.store_name, when, items].filter(Boolean).join(" · ");
};
const money = (v) => (v != null ? `₱${Number(v).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : "");

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

const clearFieldErrors = () => Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

const startNew = () => {
  Object.assign(draft, blankDraft());
  subjectEdited.value = false;
  allOrders.value = false;
  error.value = "";
  clearFieldErrors();
  mode.value = "new";
  if (!orders.value.length) loadOrders();
  else ordersLoaded.value = true;
};

const firstError = (e) => {
  const errors = e.response?.data?.errors;
  return errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "That didn't go through. Try again.";
};

/** Checks every step at once, so each problem shows beside its own field */
const validate = () => {
  clearFieldErrors();
  if (!topic.value) fieldErrors.category = "Pick what your request is about.";
  else if (topic.value.issues.length && !draft.issue) fieldErrors.issue = "Pick what happened.";
  if (toShop.value && !draft.order_id) fieldErrors.order_id = "Pick the order this is about, so the shop can help.";
  if (draft.subject.trim().length < 4) fieldErrors.subject = "Add a short summary (at least 4 characters).";
  if (draft.message.trim().length < 10) fieldErrors.message = "Tell us a little more (at least 10 characters).";
  return !Object.keys(fieldErrors).length;
};

const submit = async () => {
  error.value = "";
  if (!validate()) return;
  busy.value = true;
  try {
    const { data } = await api.post("/support/tickets", {
      ...draft,
      issue: draft.issue || null,
      desired_outcome: (toShop.value && draft.desired_outcome) || null,
      order_id: draft.order_id || null,
    });
    toast.success(data.message);
    await loadList();
    active.value = data.data;
    mode.value = "view";
    router.replace({ query: { ...route.query, ticket: data.data.id } });
  } catch (e) {
    // The server's field problems go beside their fields
    const errors = e.response?.data?.errors;
    if (errors) Object.entries(errors).forEach(([k, v]) => (fieldErrors[k] = v?.[0]));
    if (!errors || Object.keys(errors).some((k) => !["category", "issue", "order_id", "subject", "message"].includes(k))) error.value = firstError(e);
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
/* Customer pages: the logo's navy and green */
.theme-shop {
  --accent: #363f4e;
  --accent-dark: #262d39;
  --accent-soft: #eef6f1;
  --text: #363f4e;
  --muted: #6a7282;
  --line: rgba(54, 63, 78, 0.14);
  --card: #fff;
  --mine: #e6f4eb;
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
  color: #2e7a4f;
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
/* ── New request: step by step ── */
.support {
  --pick: var(--accent);
}
.theme-vendor {
  --pick: var(--v-purple-text, #5b4fc9);
}
.theme-shop {
  --pick: #2e7a4f;
}
.step {
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.step + .step {
  padding-top: 16px;
  border-top: 1px solid var(--line);
}
.step legend {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  margin-bottom: 10px;
  font-size: 14px;
  font-weight: 600;
}
.step-no {
  display: inline-grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--accent);
  color: #fff;
  font-size: 11.5px;
  font-weight: 600;
}
.optional {
  font-size: 11.5px;
  font-weight: 500;
  color: var(--muted);
}
.sr {
  position: absolute;
  opacity: 0;
  width: 1px;
  height: 1px;
  pointer-events: none;
}
.topic-group + .topic-group {
  margin-top: 6px;
}
.group-title {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--muted);
}
.group-title small {
  font-size: 12px;
  font-weight: 400;
  letter-spacing: 0;
  text-transform: none;
}
.topics {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 8px;
}
.topic,
.order,
.chip {
  position: relative;
  border: 1px solid var(--line);
  background: var(--card);
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
.topic {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-height: 64px;
  padding: 12px;
  border-radius: 12px;
}
.topic:hover,
.order:hover,
.chip:hover {
  border-color: var(--pick);
}
.topic.on,
.order.on,
.chip.on {
  border-color: var(--pick);
  background: var(--accent-soft);
  box-shadow: inset 0 0 0 1px var(--pick);
}
.topic:focus-within,
.order:focus-within,
.chip:focus-within {
  outline: 2px solid var(--pick);
  outline-offset: 2px;
}
.topic-icon {
  flex: none;
  width: 22px;
  height: 22px;
  margin-top: 1px;
  fill: none;
  stroke: var(--pick);
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.topic-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.topic-text strong {
  font-size: 13.5px;
  font-weight: 600;
}
.topic-text small {
  font-size: 12px;
  line-height: 1.4;
  color: var(--muted);
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
}
.chip.on {
  color: var(--pick);
}
.orders {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.order {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 52px;
  padding: 10px 14px;
  border-radius: 12px;
}
.order-main {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.order-main strong {
  font-size: 13.5px;
  font-weight: 600;
}
.order-main small {
  font-size: 12px;
  color: var(--muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.order-total {
  flex: none;
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.link-btn {
  align-self: flex-start;
  min-height: 36px;
  padding: 0 4px;
  border: 0;
  background: none;
  color: var(--pick);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.link-btn:hover {
  text-decoration: underline;
}
.field-foot {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.count {
  margin-left: auto;
  font-size: 11.5px;
}
.goes-to {
  margin-right: auto;
  align-self: center;
}
.issue-tag {
  display: inline-block;
  margin: 2px 0 4px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--accent-soft);
  color: var(--pick);
  font-size: 12px;
  font-weight: 600;
}
.sk.short {
  height: 52px;
}
@media (prefers-reduced-motion: reduce) {
  .topic,
  .order,
  .chip {
    transition: none;
  }
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
