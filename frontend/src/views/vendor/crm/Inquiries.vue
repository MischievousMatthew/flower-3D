<template>
  <ShopLayout section="crm" title="Customer Inquiries" subtitle="Questions customers ask about your products and their orders. Answered product questions can appear on the product page for other shoppers.">
    <div v-if="settingUp" class="sp-banner info"><span v-html="ICONS.clock"></span>{{ settingUp }}</div>
    <div v-else-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load()">Try again</button>
    </div>

    <section v-if="!settingUp" class="sp-stats" aria-label="Inquiry summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button type="button" class="sp-stat" :class="{ on: status === 'open' }" @click="setStatus('open')">
          <span class="sp-stat-icon tone-amber" v-html="ICONS.help"></span>
          <div><strong>{{ data.counts.open }}</strong><span>Waiting for you</span></div>
        </button>
        <button type="button" class="sp-stat" :class="{ on: status === 'answered' }" @click="setStatus('answered')">
          <span class="sp-stat-icon tone-green" v-html="ICONS.check"></span>
          <div><strong>{{ data.counts.answered }}</strong><span>Answered</span></div>
        </button>
        <button type="button" class="sp-stat" :class="{ on: status === 'closed' }" @click="setStatus('closed')">
          <span class="sp-stat-icon tone-slate" v-html="ICONS.archive"></span>
          <div><strong>{{ data.counts.closed }}</strong><span>Done</span></div>
        </button>
        <div class="sp-stat">
          <span class="sp-stat-icon tone-blue" v-html="ICONS.clock"></span>
          <div><strong>{{ replyTime }}</strong><span>Average time to answer</span></div>
        </div>
      </template>
    </section>

    <section v-if="!settingUp" class="sp-card">
      <div class="sp-toolbar iq-toolbar">
        <div class="sp-seg" role="tablist" aria-label="Status">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" :class="{ on: status === t.key }" :aria-selected="status === t.key" @click="setStatus(t.key)">
            {{ t.label }}<span v-if="t.key === 'open' && data?.counts.open" class="count">{{ data.counts.open }}</span>
          </button>
        </div>
        <div class="iq-filters">
          <select v-model="about" class="sp-select" aria-label="About" @change="load()">
            <option value="">Products &amp; orders</option>
            <option value="product">Products</option>
            <option value="order">Orders</option>
          </select>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Question, customer, product or order" aria-label="Search inquiries" @input="debouncedLoad" />
          </label>
        </div>
      </div>

      <ul v-if="!data" class="iq-list">
        <li v-for="n in 4" :key="n" class="iq-item"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></li>
      </ul>
      <div v-else-if="!data.inquiries.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.help"></span>
        <h3>{{ search || about ? "Nothing matches" : status === "open" ? "No questions waiting" : "Nothing here yet" }}</h3>
        <p>{{ search || about ? "Try another search or filter." : status === "open" ? "When a customer asks about a product or one of their orders, it shows up here, and you'll get a notification." : "Answered and finished questions are kept here." }}</p>
      </div>
      <ul v-else class="iq-list">
        <li v-for="q in data.inquiries" :key="q.id" :ref="(el) => setFocusEl(q.id, el)" class="iq-item" :class="{ open: q.status === 'open', focus: q.id === focusId }">
          <span class="st-avatar" :style="{ background: colorFor(q.customer.name) }">
            <img v-if="q.customer.avatar" :src="q.customer.avatar" alt="" />
            <template v-else>{{ initialsOf(q.customer.name) }}</template>
          </span>
          <div class="iq-main">
            <div class="iq-top">
              <strong>{{ q.customer.name }}</strong>
              <span class="st-sub">asked {{ timeAgo(q.created_at) }}</span>
              <span class="sp-chip" :class="STATUS[q.status][1]">{{ STATUS[q.status][0] }}</span>
            </div>

            <button v-if="q.product" type="button" class="iq-about" @click="openProduct(q)">
              <img v-if="q.product.image" :src="q.product.image" alt="" />
              <span v-else class="iq-about-icon" v-html="ICONS.flower"></span>
              <span><small>About a product</small>{{ q.product.name }}</span>
            </button>
            <button v-else-if="q.order" type="button" class="iq-about" @click="openOrder(q)">
              <span class="iq-about-icon" v-html="ICONS.receipt"></span>
              <span><small>About their order · {{ money(q.order.total) }}</small>{{ q.order.order_number }}</span>
            </button>

            <p class="iq-question">{{ q.question }}</p>

            <div v-if="q.answer && editing !== q.id" class="iq-answer">
              <div class="iq-answer-head">
                <b>Your answer</b>
                <span class="st-sub">{{ q.answered_by }} · {{ timeAgo(q.answered_at) }}{{ q.seen_by_customer ? " · Seen" : "" }}</span>
              </div>
              <p>{{ q.answer }}</p>
            </div>

            <form v-if="!q.answer || editing === q.id" class="iq-reply" @submit.prevent="submit(q)">
              <textarea
                v-model="drafts[q.id]"
                class="sp-textarea"
                rows="3"
                maxlength="2000"
                :placeholder="q.product ? 'e.g. Yes, we can make it in pink at the same price.' : 'e.g. Yes, we can deliver before 10 AM.'"
                :aria-label="`Answer ${q.customer.name}`"
              ></textarea>
              <div class="iq-reply-foot">
                <label v-if="q.product" class="iq-public">
                  <input v-model="publicFlags[q.id]" type="checkbox" />
                  <span>Show this question and answer on the product page</span>
                </label>
                <span v-else class="st-sub">Only {{ q.customer.name.split(" ")[0] }} sees answers about their order.</span>
                <div class="iq-reply-actions">
                  <button v-if="editing === q.id" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="editing = null">Cancel</button>
                  <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === q.id || !(drafts[q.id] || '').trim()">
                    <span v-html="ICONS.send"></span>{{ busy === q.id ? "Sending…" : editing === q.id ? "Update answer" : "Send answer" }}
                  </button>
                </div>
              </div>
            </form>

            <div class="iq-actions">
              <button v-if="q.answer && editing !== q.id" type="button" class="st-link" @click="startEdit(q)">Edit answer</button>
              <button v-if="q.product && q.answer" type="button" class="st-link" :disabled="busy === q.id" @click="setPublic(q, !q.is_public)">
                {{ q.is_public ? "Hide from product page" : "Show on product page" }}
              </button>
              <button type="button" class="st-link" @click="messageCustomer(q)">Message {{ q.customer.name.split(" ")[0] }}</button>
              <button v-if="q.status !== 'closed'" type="button" class="st-link muted" :disabled="busy === q.id" @click="setDone(q, true)">Mark as done</button>
              <button v-else type="button" class="st-link muted" :disabled="busy === q.id" @click="setDone(q, false)">Reopen</button>
              <span v-if="q.product && q.answer && q.is_public" class="sp-chip teal iq-public-chip"><span v-html="ICONS.eye"></span>On product page</span>
            </div>
          </div>
        </li>
      </ul>

      <nav v-if="data && data.meta.last_page > 1" class="iq-pages" aria-label="Pages">
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="data.meta.page <= 1" @click="load(data.meta.page - 1)"><span v-html="ICONS.left"></span>Previous</button>
        <span class="st-sub">Page {{ data.meta.page }} of {{ data.meta.last_page }}</span>
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="data.meta.page >= data.meta.last_page" @click="load(data.meta.page + 1)">Next<span v-html="ICONS.right"></span></button>
      </nav>
    </section>
  </ShopLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../components/vendor/finance/financeUi";
import { colorFor, initialsOf, timeAgo } from "../../../components/vendor/crm/crmUi";
import { linkedId } from "../../../utils/hiddenIds";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const TABS = [
  { key: "open", label: "Waiting" },
  { key: "answered", label: "Answered" },
  { key: "closed", label: "Done" },
  { key: "", label: "All" },
];
const STATUS = { open: ["Waiting for you", "amber"], answered: ["Answered", "green"], closed: ["Done", "slate"] };

const router = useRouter();
const data = ref(null);
const error = ref("");
const settingUp = ref("");
const status = ref("open");
const about = ref("");
const search = ref("");
const drafts = reactive({});
const publicFlags = reactive({});
const busy = ref(null);
const editing = ref(null);
const focusId = ref(linkedId("inquiry"));
let focusEl = null;
const setFocusEl = (id, el) => {
  if (id === focusId.value && el) focusEl = el;
};
let timer = null;

const replyTime = computed(() => {
  const h = data.value?.avg_reply_hours;
  if (h === null || h === undefined) return "—";
  if (h < 1) return `${Math.max(1, Math.round(h * 60))} min`;
  if (h < 48) return `${Math.round(h)} h`;
  return `${Math.round(h / 24)} days`;
});

async function load(page = 1) {
  error.value = "";
  try {
    const res = await api.get("vendor/crm/inquiries", { params: { page, status: status.value || undefined, about: about.value || undefined, search: search.value.trim() || undefined } });
    data.value = res.data.data;
    for (const q of data.value.inquiries) {
      if (!(q.id in publicFlags)) publicFlags[q.id] = q.is_public;
    }
  } catch (e) {
    if (e?.response?.data?.code === "crm_setting_up") settingUp.value = e.response.data.message;
    else error.value = apiMessage(e, "Couldn't load questions.");
  }
}
function debouncedLoad() {
  clearTimeout(timer);
  timer = setTimeout(() => load(), 300);
}
function setStatus(s) {
  status.value = status.value === s && s ? "" : s;
  load();
}

function replace(updated) {
  const i = data.value.inquiries.findIndex((q) => q.id === updated.id);
  if (i >= 0) data.value.inquiries.splice(i, 1, updated);
}
async function refreshCounts() {
  try {
    const res = await api.get("vendor/crm/inquiries", { params: { status: "open", page: 1 } });
    data.value.counts = res.data.data.counts;
    data.value.avg_reply_hours = res.data.data.avg_reply_hours;
  } catch {
    /* counts update next load */
  }
}

async function submit(q) {
  const answer = (drafts[q.id] || "").trim();
  if (!answer) return;
  busy.value = q.id;
  try {
    const { data: res } = await api.post(`vendor/crm/inquiries/${q.id}/answer`, { answer, is_public: q.product ? !!publicFlags[q.id] : false });
    toast.success(res.message);
    replace(res.data);
    drafts[q.id] = "";
    editing.value = null;
    refreshCounts();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't send your answer."));
  } finally {
    busy.value = null;
  }
}
function startEdit(q) {
  drafts[q.id] = q.answer;
  publicFlags[q.id] = q.is_public;
  editing.value = q.id;
}
async function setDone(q, done) {
  busy.value = q.id;
  try {
    const { data: res } = await api.post(`vendor/crm/inquiries/${q.id}/status`, { status: done ? "closed" : "open" });
    toast.success(res.message);
    replace(res.data);
    refreshCounts();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't change that."));
  } finally {
    busy.value = null;
  }
}
async function setPublic(q, on) {
  busy.value = q.id;
  try {
    const { data: res } = await api.post(`vendor/crm/inquiries/${q.id}/status`, { is_public: on });
    toast.success(res.message);
    replace(res.data);
    publicFlags[q.id] = on;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't change that."));
  } finally {
    busy.value = null;
  }
}

function openOrder(q) {
  router.push({ name: "VendorOrderDetails", query: { order: q.order.id } });
}
function openProduct() {
  router.push({ name: "VendorProducts" });
}
async function messageCustomer(q) {
  try {
    const { data: res } = await api.get("/chat/conversations");
    const existing = (res.conversations ?? []).find((c) => c.customer?.id === q.customer.id);
    if (existing) {
      router.push({ name: "VendorChat", query: { conversation: existing.id } });
      return;
    }
    const about = q.product ? q.product.name : `order ${q.order?.order_number ?? ""}`.trim();
    const { data: started } = await api.post("/chat/conversations/start", {
      customer_id: q.customer.id,
      message: `Hi ${q.customer.name.split(" ")[0]}! Following up on your question about ${about}.`,
    });
    router.push({ name: "VendorChat", query: { conversation: started.conversation?.id } });
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't open the chat."));
  }
}

onMounted(async () => {
  // Opened from a notification: show everything so that question is in the list
  if (focusId.value) status.value = "";
  await load();
  if (focusId.value) {
    await nextTick();
    focusEl?.scrollIntoView({ behavior: "smooth", block: "center" });
  }
});
</script>

<style scoped>
.iq-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 6px;
}
.iq-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  flex: 1 1 340px;
  justify-content: flex-end;
}
.iq-filters .sp-select {
  width: auto;
  min-width: 160px;
}
.iq-filters .sp-search {
  flex: 1 1 220px;
  max-width: 320px;
}
.iq-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.iq-item {
  display: flex;
  gap: 14px;
  padding: 18px 4px;
  border-top: 1px solid #f1f5f9;
}
.iq-item:first-child {
  border-top: 0;
}
.iq-item .grow {
  flex: 1;
}
.iq-item.focus {
  margin: 0 -10px;
  padding: 18px 14px;
  border-radius: 14px;
  background: #f8fdf9;
  box-shadow: inset 0 0 0 1px #c6f6d5;
}
.iq-main {
  flex: 1;
  min-width: 0;
}
.iq-top {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 10px;
}
.iq-top strong {
  font-size: 14px;
  font-weight: 600;
}
.iq-top .sp-chip {
  margin-left: auto;
}
.iq-about {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  max-width: 100%;
  margin-top: 10px;
  padding: 6px 12px 6px 6px;
  border: 1px solid #edf2f7;
  border-radius: 12px;
  background: #fff;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
  text-align: left;
  cursor: pointer;
}
.iq-about:hover {
  border-color: #9ae6b4;
}
.iq-about img,
.iq-about-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 9px;
  object-fit: cover;
  background: #f0fff4;
  color: #2f855a;
}
.iq-about-icon :deep(svg) {
  width: 18px;
  height: 18px;
}
.iq-about > span:last-child {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.iq-about small {
  display: block;
  font-size: 11px;
  font-weight: 400;
  color: #a0aec0;
}
.iq-question {
  margin: 12px 0 0;
  font-size: 14px;
  line-height: 1.55;
  color: #1a202c;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.iq-answer {
  margin-top: 12px;
  padding: 12px 14px;
  border-left: 3px solid #48bb78;
  border-radius: 0 12px 12px 0;
  background: #f7fafc;
}
.iq-answer-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 10px;
  margin-bottom: 4px;
}
.iq-answer-head b {
  font-size: 12.5px;
  font-weight: 600;
  color: #2f855a;
}
.iq-answer p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.55;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.iq-reply {
  margin-top: 12px;
}
.iq-reply-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 8px;
}
.iq-reply-actions {
  display: flex;
  gap: 8px;
}
.iq-public {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: #4a5568;
  cursor: pointer;
}
.iq-public input {
  accent-color: #48bb78;
}
.iq-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 16px;
  margin-top: 12px;
}
.st-link.muted {
  color: #718096;
}
.st-link:disabled {
  opacity: 0.5;
  cursor: default;
}
.iq-public-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.iq-public-chip :deep(svg) {
  width: 13px;
  height: 13px;
}
.iq-pages {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #edf2f7;
}
@media (max-width: 640px) {
  .iq-item > .st-avatar {
    display: none;
  }
  .iq-top .sp-chip {
    margin-left: 0;
  }
}
</style>
