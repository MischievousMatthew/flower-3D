<template>
  <ShopLayout section="crm" title="Customer Chat" subtitle="Talk with your customers. Their orders with you are on the right, so you can answer without switching pages.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-primary" @click="openNew"><span v-html="ICONS.plus"></span>New message</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="loadConversations()">Try again</button>
    </div>

    <section class="sp-card ch-shell" :class="{ 'has-thread': !!active, 'show-info': showInfo }">
      <!-- Conversations -->
      <aside class="ch-list" aria-label="Conversations">
        <div class="ch-list-head">
          <label class="sp-search ch-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Search customers" aria-label="Search conversations" />
          </label>
          <div class="sp-seg ch-seg" role="tablist" aria-label="Show">
            <button type="button" role="tab" :class="{ on: view === 'inbox' }" :aria-selected="view === 'inbox'" @click="view = 'inbox'">Inbox</button>
            <button type="button" role="tab" :class="{ on: view === 'unread' }" :aria-selected="view === 'unread'" @click="view = 'unread'">
              Unread <span v-if="unreadTotal" class="count">{{ unreadTotal }}</span>
            </button>
            <button type="button" role="tab" :class="{ on: view === 'archived' }" :aria-selected="view === 'archived'" @click="view = 'archived'">Archived</button>
          </div>
        </div>

        <ul v-if="!loaded" class="ch-items">
          <li v-for="n in 6" :key="n" class="ch-item sk"><div class="bc-skeleton ch-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></li>
        </ul>
        <div v-else-if="!visible.length" class="ch-list-empty">
          <span class="sp-empty-icon" v-html="ICONS.chat"></span>
          <p>{{ search ? "No one matches that search." : view === "unread" ? "You're all caught up." : view === "archived" ? "No archived conversations." : "No conversations yet. Customers who message your shop show up here." }}</p>
        </div>
        <ul v-else class="ch-items">
          <li v-for="c in visible" :key="c.id">
            <button type="button" class="ch-item" :class="{ on: active?.id === c.id, unread: c.unread_count > 0 }" @click="select(c)">
              <span class="ch-avatar" :style="{ background: colorFor(c.customer.name) }">
                <img v-if="c.customer.avatar" :src="c.customer.avatar" alt="" />
                <template v-else>{{ initialsOf(c.customer.name) }}</template>
                <i v-if="c.customer.online" class="ch-online" aria-label="Online"></i>
              </span>
              <span class="ch-item-main">
                <span class="ch-item-top">
                  <strong>{{ c.customer.name }}</strong>
                  <small>{{ c.last_message_time }}</small>
                </span>
                <span class="ch-item-bottom">
                  <span class="ch-preview">{{ c.last_message || "No messages yet" }}</span>
                  <b v-if="c.unread_count" class="ch-badge">{{ c.unread_count > 9 ? "9+" : c.unread_count }}</b>
                </span>
              </span>
            </button>
          </li>
        </ul>
      </aside>

      <!-- Thread -->
      <div class="ch-thread">
        <div v-if="!active" class="ch-placeholder">
          <span class="sp-empty-icon" v-html="ICONS.chat"></span>
          <h3>Pick a conversation</h3>
          <p>Or start one with a customer who has ordered from you.</p>
        </div>
        <template v-else>
          <header class="ch-thread-head">
            <button type="button" class="sp-icon-btn ch-back" aria-label="Back to conversations" @click="active = null"><span v-html="ICONS.left"></span></button>
            <span class="ch-avatar" :style="{ background: colorFor(active.customer.name) }">
              <img v-if="active.customer.avatar" :src="active.customer.avatar" alt="" />
              <template v-else>{{ initialsOf(active.customer.name) }}</template>
            </span>
            <div class="ch-who">
              <strong>{{ active.customer.name }}</strong>
              <small :class="{ online: active.customer.online }">{{ active.customer.online ? "Online now" : "Offline" }}</small>
            </div>
            <div class="ch-head-actions">
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="toggleArchive(active)">{{ active.is_archived ? "Move to inbox" : "Archive" }}</button>
              <button type="button" class="sp-icon-btn danger" aria-label="Delete conversation" @click="confirmDelete = true"><span v-html="ICONS.trash"></span></button>
              <button type="button" class="sp-icon-btn ch-info-btn" :class="{ on: showInfo }" aria-label="Customer details" :aria-pressed="showInfo" @click="showInfo = !showInfo"><span v-html="ICONS.user"></span></button>
            </div>
          </header>

          <div ref="scroller" class="ch-messages" aria-live="polite">
            <div v-if="loadingThread" class="ch-msg-sk">
              <div v-for="n in 4" :key="n" class="bc-skeleton" :class="n % 2 ? 'l' : 'r'"></div>
            </div>
            <template v-else>
              <p v-if="!messages.length" class="ch-day"><span>No messages yet. Say hello!</span></p>
              <template v-for="g in grouped" :key="g.day">
                <p class="ch-day"><span>{{ g.day }}</span></p>
                <div v-for="m in g.items" :key="m.id" class="ch-msg" :class="{ own: m.is_own, pending: m.pending }">
                  <div class="ch-bubble">
                    <div v-if="images(m).length" class="ch-images" :class="{ many: images(m).length > 1 }">
                      <a v-for="a in images(m)" :key="a.url" :href="a.url" target="_blank" rel="noopener"><img :src="a.url" :alt="a.name || 'Photo'" loading="lazy" /></a>
                    </div>
                    <a v-for="a in files(m)" :key="a.url" :href="a.url" target="_blank" rel="noopener" class="ch-file">
                      <span v-html="ICONS.note"></span><span>{{ a.name || "File" }}</span>
                    </a>
                    <p v-if="m.text">{{ m.text }}</p>
                  </div>
                  <small class="ch-meta">{{ m.pending ? "Sending…" : clock(m.created_at) }}<template v-if="m.is_own && !m.pending"> · {{ m.read ? "Seen" : "Sent" }}</template></small>
                </div>
              </template>
            </template>
          </div>

          <form class="ch-composer" @submit.prevent="send">
            <div v-if="pending.length" class="ch-attach-list">
              <span v-for="(f, i) in pending" :key="i" class="ch-attach">
                <img v-if="f.preview" :src="f.preview" alt="" />
                <span v-else v-html="ICONS.note"></span>
                <span class="name">{{ f.file.name }}</span>
                <button type="button" :aria-label="`Remove ${f.file.name}`" @click="dropFile(i)"><span v-html="ICONS.close"></span></button>
              </span>
            </div>
            <p v-if="sendError" class="sp-error ch-send-error">{{ sendError }}</p>
            <div class="ch-compose-row">
              <label class="sp-icon-btn ch-clip" aria-label="Attach photos or files">
                <input type="file" multiple accept="image/*,.pdf,.doc,.docx,.txt" @change="pickFiles" />
                <span v-html="ICONS.clip"></span>
              </label>
              <div class="ch-quick">
                <button type="button" class="sp-icon-btn" aria-label="Quick replies" :aria-expanded="showQuick" @click="showQuick = !showQuick"><span v-html="ICONS.sparkle"></span></button>
                <div v-if="showQuick" class="ch-quick-menu" role="menu">
                  <p>Quick replies</p>
                  <button v-for="q in QUICK_REPLIES" :key="q" type="button" role="menuitem" @click="useQuick(q)">{{ q }}</button>
                </div>
              </div>
              <textarea
                ref="box"
                v-model="draft"
                class="sp-textarea ch-input"
                rows="1"
                maxlength="1000"
                placeholder="Write a message…"
                aria-label="Message"
                @keydown.enter.exact.prevent="send"
                @input="grow"
              ></textarea>
              <button type="submit" class="sp-btn sp-btn-primary ch-send" :disabled="sending || (!draft.trim() && !pending.length)" aria-label="Send">
                <span v-html="ICONS.send"></span>
              </button>
            </div>
            <small class="ch-hint">Enter to send · Shift + Enter for a new line<template v-if="draft.length > 800"> · {{ 1000 - draft.length }} left</template></small>
          </form>
        </template>
      </div>

      <!-- Customer -->
      <aside v-if="active" class="ch-info" aria-label="Customer details">
        <div class="ch-info-head">
          <span class="ch-avatar lg" :style="{ background: colorFor(active.customer.name) }">
            <img v-if="active.customer.avatar" :src="active.customer.avatar" alt="" />
            <template v-else>{{ initialsOf(active.customer.name) }}</template>
          </span>
          <strong>{{ active.customer.name }}</strong>
          <small v-if="info?.member_since">Customer since {{ shortDate(info.member_since) }}</small>
          <button type="button" class="sp-icon-btn ch-info-close" aria-label="Close details" @click="showInfo = false"><span v-html="ICONS.close"></span></button>
        </div>

        <div v-if="!info" class="ch-info-sk"><div class="bc-skeleton sp-sk-block"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
        <template v-else>
          <div class="ch-stats">
            <div><strong>{{ info.stats.orders }}</strong><span>{{ info.stats.orders === 1 ? "Order" : "Orders" }}</span></div>
            <div><strong>{{ money(info.stats.spent) }}</strong><span>Spent with you</span></div>
          </div>
          <dl class="ch-contact">
            <template v-if="info.email"><dt>Email</dt><dd>{{ info.email }}</dd></template>
            <template v-if="info.phone"><dt>Phone</dt><dd>{{ info.phone }}</dd></template>
            <template v-if="info.stats.last_order"><dt>Last order</dt><dd>{{ shortDate(info.stats.last_order) }}</dd></template>
          </dl>

          <h4>Recent orders</h4>
          <p v-if="!info.orders.length" class="sp-hint">They haven't ordered from you yet.</p>
          <ul v-else class="ch-orders">
            <li v-for="o in info.orders" :key="o.id">
              <button type="button" @click="openOrder(o)">
                <span><b>{{ o.order_number }}</b><small>{{ shortDate(o.created_at) }} · {{ money(o.total) }}</small></span>
                <span class="sp-chip" :class="(ORDER_STATUS[o.status] ?? [o.status, 'slate'])[1]">{{ (ORDER_STATUS[o.status] ?? [o.status])[0] }}</span>
              </button>
            </li>
          </ul>

          <template v-if="info.inquiries.length">
            <h4>Their questions</h4>
            <ul class="ch-orders">
              <li v-for="q in info.inquiries" :key="q.id">
                <button type="button" @click="router.push({ name: 'VendorInquiries', query: { inquiry: q.id } })">
                  <span><b>{{ q.about }}</b><small>{{ timeAgo(q.created_at) }}</small></span>
                  <span class="sp-chip" :class="q.status === 'open' ? 'amber' : 'green'">{{ q.status === "open" ? "Waiting" : q.status === "answered" ? "Answered" : "Done" }}</span>
                </button>
              </li>
            </ul>
          </template>
        </template>
      </aside>
    </section>

    <!-- New conversation -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="newChat" class="sp-backdrop" @click="newChat = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="newChat" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="nc-title" @submit.prevent="startChat">
            <div class="sp-modal-head">
              <div><h3 id="nc-title">New message</h3><p>Find a customer by name, username or email.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="newChat = null"><span v-html="ICONS.close"></span></button>
            </div>
            <template v-if="!newChat.to">
              <label class="sp-search">
                <span v-html="ICONS.search"></span>
                <input v-model="newChat.query" class="sp-input" type="search" placeholder="Search customers" aria-label="Search customers" @input="searchCustomers" />
              </label>
              <ul class="nc-results">
                <li v-if="newChat.searching" class="sp-hint">Searching…</li>
                <li v-else-if="newChat.query.trim().length >= 2 && !newChat.results.length" class="sp-hint">No customers found.</li>
                <li v-for="u in newChat.results" :key="u.id">
                  <button type="button" @click="newChat.to = u">
                    <span class="ch-avatar sm" :style="{ background: colorFor(u.name) }"><img v-if="u.avatar" :src="u.avatar" alt="" /><template v-else>{{ initialsOf(u.name) }}</template></span>
                    <span><b>{{ u.name }}</b><small>{{ u.username ? `@${u.username}` : u.email }}</small></span>
                  </button>
                </li>
              </ul>
            </template>
            <template v-else>
              <div class="nc-to">
                <span class="ch-avatar sm" :style="{ background: colorFor(newChat.to.name) }">{{ initialsOf(newChat.to.name) }}</span>
                <b>{{ newChat.to.name }}</b>
                <button type="button" class="st-link" @click="newChat.to = null">Change</button>
              </div>
              <label class="sp-field">
                <span>Message</span>
                <textarea v-model="newChat.message" class="sp-textarea" rows="4" maxlength="1000" placeholder="Hi! …"></textarea>
              </label>
              <p v-if="newChat.error" class="sp-error">{{ newChat.error }}</p>
              <div class="sp-modal-foot">
                <button type="button" class="sp-btn sp-btn-ghost" @click="newChat = null">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-primary" :disabled="newChat.busy || !newChat.message.trim()">{{ newChat.busy ? "Sending…" : "Send" }}</button>
              </div>
            </template>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="confirmDelete" class="sp-backdrop" @click="confirmDelete = false"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="confirmDelete && active" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="cd-title">
            <div class="sp-modal-head"><div><h3 id="cd-title">Delete this conversation?</h3><p>With {{ active.customer.name }}</p></div></div>
            <p class="sp-hint">It's removed from your inbox only; {{ active.customer.name.split(" ")[0] }} keeps their copy. If they message again, it comes back with just the new messages.</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="confirmDelete = false">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" @click="remove">Delete</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../components/vendor/finance/financeUi";
import { ORDER_STATUS, QUICK_REPLIES, clock, colorFor, dayHeading, initialsOf, timeAgo } from "../../../components/vendor/crm/crmUi";
import { linkedId } from "../../../utils/hiddenIds";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const MAX_FILES = 5;
const MAX_BYTES = 5 * 1024 * 1024;

const router = useRouter();
const conversations = ref([]);
const loaded = ref(false);
const error = ref("");
const search = ref("");
const view = ref("inbox");
const active = ref(null);
const messages = ref([]);
const loadingThread = ref(false);
const lastId = ref(0);
const draft = ref("");
const pending = ref([]);
const sending = ref(false);
const sendError = ref("");
const showQuick = ref(false);
const showInfo = ref(window.innerWidth > 1180);
const info = ref(null);
const newChat = ref(null);
const confirmDelete = ref(false);
const scroller = ref(null);
const box = ref(null);
let pollTimer = null;
let listTimer = null;
let searchTimer = null;

const unreadTotal = computed(() => conversations.value.filter((c) => !c.is_archived).reduce((n, c) => n + (c.unread_count || 0), 0));
const visible = computed(() => {
  const q = search.value.trim().toLowerCase();
  return conversations.value.filter((c) => {
    if (view.value === "archived" ? !c.is_archived : c.is_archived) return false;
    if (view.value === "unread" && !c.unread_count) return false;
    return !q || [c.customer.name, c.customer.username, c.customer.email].some((v) => v && v.toLowerCase().includes(q));
  });
});
const grouped = computed(() => {
  const out = [];
  for (const m of messages.value) {
    const day = dayHeading(m.created_at);
    if (!out.length || out[out.length - 1].day !== day) out.push({ day, items: [] });
    out[out.length - 1].items.push(m);
  }
  return out;
});

const isImage = (a) => String(a?.type ?? "").startsWith("image/");
const images = (m) => (m.attachments ?? []).filter(isImage);
const files = (m) => (m.attachments ?? []).filter((a) => !isImage(a));
const shortDate = (d) => new Date(String(d).length === 10 ? `${d}T00:00:00` : d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });

async function loadConversations({ quiet = false } = {}) {
  if (!quiet) error.value = "";
  try {
    const { data } = await api.get("/chat/conversations");
    conversations.value = (data.conversations ?? []).filter((c) => c.customer);
    if (active.value) {
      const fresh = conversations.value.find((c) => c.id === active.value.id);
      if (fresh) Object.assign(active.value, { ...fresh, unread_count: 0 });
    }
  } catch (e) {
    if (!quiet) error.value = apiMessage(e, "Couldn't load your conversations.");
  } finally {
    loaded.value = true;
  }
}

async function select(c) {
  if (active.value?.id === c.id) return;
  active.value = c;
  messages.value = [];
  info.value = null;
  sendError.value = "";
  loadingThread.value = true;
  c.unread_count = 0;
  try {
    const { data } = await api.get(`/chat/conversations/${c.id}/messages`);
    if (active.value?.id !== c.id) return;
    messages.value = data.messages ?? [];
    lastId.value = Math.max(0, ...messages.value.map((m) => m.id));
  } catch (e) {
    sendError.value = apiMessage(e, "Couldn't load the messages.");
  } finally {
    loadingThread.value = false;
    scrollDown();
  }
  loadInfo(c);
}

async function loadInfo(c) {
  try {
    const { data } = await api.get(`vendor/crm/customers/${c.customer.id}`);
    if (active.value?.id === c.id) info.value = data.data;
  } catch {
    if (active.value?.id === c.id) info.value = { stats: { orders: 0, spent: 0 }, orders: [], inquiries: [] };
  }
}

async function poll() {
  if (document.hidden || !active.value || sending.value) return;
  const id = active.value.id;
  try {
    const { data } = await api.get("/chat/poll", { params: { conversation_id: id, last_message_id: lastId.value } });
    if (active.value?.id !== id) return;
    const known = new Set(messages.value.map((m) => m.id));
    const fresh = (data.new_messages ?? []).filter((m) => !known.has(m.id));
    if (fresh.length) {
      const atBottom = nearBottom();
      messages.value.push(...fresh);
      lastId.value = Math.max(lastId.value, ...fresh.map((m) => m.id));
      if (atBottom) scrollDown();
      // Seeing them here marks them read
      if (fresh.some((m) => !m.is_own)) api.get(`/chat/conversations/${id}/messages`).catch(() => {});
    }
  } catch {
    /* try again next tick */
  }
}

function nearBottom() {
  const el = scroller.value;
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}
function scrollDown() {
  nextTick(() => {
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
  });
}
function grow() {
  const el = box.value;
  if (!el) return;
  el.style.height = "auto";
  el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
}
function useQuick(text) {
  draft.value = draft.value.trim() ? `${draft.value.trim()} ${text}` : text;
  showQuick.value = false;
  nextTick(() => {
    grow();
    box.value?.focus();
  });
}

function pickFiles(e) {
  sendError.value = "";
  for (const file of Array.from(e.target.files ?? [])) {
    if (pending.value.length >= MAX_FILES) {
      sendError.value = `You can send up to ${MAX_FILES} files at a time.`;
      break;
    }
    if (file.size > MAX_BYTES) {
      sendError.value = `${file.name} is bigger than 5 MB.`;
      continue;
    }
    pending.value.push({ file, preview: file.type.startsWith("image/") ? URL.createObjectURL(file) : null });
  }
  e.target.value = "";
}
function dropFile(i) {
  const [f] = pending.value.splice(i, 1);
  if (f?.preview) URL.revokeObjectURL(f.preview);
}

async function send() {
  const c = active.value;
  const text = draft.value.trim();
  if (!c || sending.value || (!text && !pending.value.length)) return;
  sending.value = true;
  sendError.value = "";
  const files = pending.value;
  const temp = { id: `tmp-${Date.now()}`, text, is_own: true, pending: true, created_at: new Date().toISOString(), attachments: files.filter((f) => f.preview).map((f) => ({ url: f.preview, type: f.file.type, name: f.file.name })) };
  messages.value.push(temp);
  scrollDown();
  draft.value = "";
  pending.value = [];
  nextTick(grow);
  try {
    const fd = new FormData();
    fd.append("conversation_id", c.id);
    if (text) fd.append("message", text);
    files.forEach((f) => fd.append("attachments[]", f.file));
    const { data } = await api.post("/chat/messages/send", fd);
    const i = messages.value.indexOf(temp);
    if (i >= 0) messages.value.splice(i, 1, { ...data.message, created_at: data.message.created_at ?? temp.created_at });
    lastId.value = Math.max(lastId.value, data.message.id);
    files.forEach((f) => f.preview && URL.revokeObjectURL(f.preview));
    c.is_archived = false;
    c.last_message = text || "You sent an attachment";
    c.last_message_time = "Just now";
    conversations.value = [c, ...conversations.value.filter((x) => x.id !== c.id)];
  } catch (e) {
    messages.value = messages.value.filter((m) => m !== temp);
    draft.value = text;
    pending.value = files;
    sendError.value = apiMessage(e, "Couldn't send. Check your connection and try again.");
  } finally {
    sending.value = false;
  }
}

async function toggleArchive(c) {
  const archive = !c.is_archived;
  try {
    await api.post(`/chat/conversations/${c.id}/${archive ? "archive" : "unarchive"}`);
    c.is_archived = archive;
    toast.success(archive ? "Archived. It comes back if they message again." : "Moved back to your inbox.");
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't change that."));
  }
}

async function remove() {
  const c = active.value;
  confirmDelete.value = false;
  try {
    await api.delete(`/chat/conversations/${c.id}`);
    conversations.value = conversations.value.filter((x) => x.id !== c.id);
    active.value = null;
    toast.success("Conversation deleted.");
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete the conversation."));
  }
}

function openOrder(o) {
  router.push({ name: "VendorOrderDetails", query: { order: o.id } });
}

function openNew() {
  newChat.value = { query: "", results: [], searching: false, to: null, message: "", busy: false, error: "" };
}
function searchCustomers() {
  clearTimeout(searchTimer);
  const nc = newChat.value;
  const q = nc.query.trim();
  if (q.length < 2) {
    nc.results = [];
    return;
  }
  searchTimer = setTimeout(async () => {
    nc.searching = true;
    try {
      const { data } = await api.get("/chat/search-customers", { params: { search: q } });
      if (newChat.value === nc && nc.query.trim() === q) nc.results = data.customers ?? [];
    } catch {
      nc.results = [];
    } finally {
      nc.searching = false;
    }
  }, 300);
}
async function startChat() {
  const nc = newChat.value;
  if (!nc?.to || !nc.message.trim()) return;
  // Already talking? Just open it.
  const existing = conversations.value.find((c) => c.customer.id === nc.to.id);
  nc.busy = true;
  nc.error = "";
  try {
    if (existing) {
      newChat.value = null;
      await select(existing);
      draft.value = nc.message.trim();
      await send();
      return;
    }
    const { data } = await api.post("/chat/conversations/start", { customer_id: nc.to.id, message: nc.message.trim() });
    newChat.value = null;
    await loadConversations({ quiet: true });
    const made = conversations.value.find((c) => c.id === data.conversation?.id);
    if (made) {
      view.value = "inbox";
      await select(made);
    }
  } catch (e) {
    nc.error = apiMessage(e, "Couldn't start the conversation.");
  } finally {
    nc.busy = false;
  }
}

function onKey(e) {
  if (e.key === "Escape") showQuick.value = false;
}


onMounted(async () => {
  await loadConversations();
  const wanted = linkedId("conversation");
  const target = wanted && conversations.value.find((c) => c.id === wanted);
  if (target) {
    view.value = target.is_archived ? "archived" : "inbox";
    select(target);
  }
  pollTimer = setInterval(poll, 4000);
  listTimer = setInterval(() => !document.hidden && loadConversations({ quiet: true }), 15000);
  window.addEventListener("keydown", onKey);
});
onBeforeUnmount(() => {
  clearInterval(pollTimer);
  clearInterval(listTimer);
  clearTimeout(searchTimer);
  window.removeEventListener("keydown", onKey);
  pending.value.forEach((f) => f.preview && URL.revokeObjectURL(f.preview));
});
</script>

<style scoped>
.ch-shell {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  height: max(540px, calc(100vh - 250px));
  padding: 0;
  overflow: hidden;
}
.ch-shell.has-thread.show-info {
  grid-template-columns: 320px minmax(0, 1fr) 290px;
}

/* List */
.ch-list {
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-right: 1px solid var(--v-border);
}
.ch-list-head {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-bottom: 1px solid var(--v-border);
}
.ch-search {
  width: 100%;
}
.ch-seg {
  width: 100%;
}
.ch-seg button {
  flex: 1;
  justify-content: center;
}
.ch-items {
  flex: 1;
  margin: 0;
  padding: 6px;
  list-style: none;
  overflow-y: auto;
}
.ch-item {
  display: flex;
  align-items: center;
  gap: 11px;
  width: 100%;
  padding: 10px;
  border: 0;
  border-radius: 12px;
  background: none;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}
.ch-item:hover {
  background: var(--v-subtle);
}
.ch-item.on {
  background: var(--bc-accent-soft);
}
.ch-item.sk {
  cursor: default;
}
.ch-item .grow,
.ch-item-main {
  flex: 1;
  min-width: 0;
}
.ch-item-top,
.ch-item-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.ch-item-top strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--v-text);
}
.ch-item.unread .ch-item-top strong {
  font-weight: 700;
}
.ch-item-top small {
  flex: none;
  font-size: 11px;
  color: var(--v-faint);
}
.ch-preview {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 12.5px;
  color: var(--v-muted);
}
.ch-item.unread .ch-preview {
  color: var(--v-text);
  font-weight: 500;
}
.ch-badge {
  flex: none;
  min-width: 19px;
  height: 19px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  font-size: 11px;
  line-height: 19px;
  text-align: center;
}
.ch-list-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 40px 24px;
  text-align: center;
  font-size: 13px;
  color: var(--v-muted);
}

.ch-avatar {
  position: relative;
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  color: #fff;
  font-size: 13.5px;
  font-weight: 600;
}
.ch-avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}
.ch-avatar.sm {
  width: 32px;
  height: 32px;
  font-size: 11.5px;
}
.ch-avatar.lg {
  width: 64px;
  height: 64px;
  font-size: 20px;
}
.ch-online {
  position: absolute;
  z-index: 1;
  right: 0;
  bottom: 0;
  width: 11px;
  height: 11px;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: #48bb78;
}

/* Thread */
.ch-thread {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  background: var(--v-subtle);
}
.ch-placeholder {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 24px;
  text-align: center;
}
.ch-placeholder h3 {
  margin: 8px 0 0;
  font-size: 16px;
  font-weight: 600;
}
.ch-placeholder p {
  margin: 0;
  font-size: 13px;
  color: var(--v-muted);
}
.ch-thread-head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--v-border);
  background: var(--v-surface);
}
.ch-back {
  display: none;
}
.ch-who {
  flex: 1;
  min-width: 0;
}
.ch-who strong {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 14.5px;
  font-weight: 600;
}
.ch-who small {
  font-size: 12px;
  color: var(--v-faint);
}
.ch-who small.online {
  color: var(--v-green-text);
}
.ch-head-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ch-info-btn.on {
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.ch-messages {
  flex: 1;
  min-height: 0;
  padding: 16px 20px;
  overflow-y: auto;
}
.ch-msg-sk {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ch-msg-sk .bc-skeleton {
  height: 40px;
  width: 45%;
  border-radius: 16px;
}
.ch-msg-sk .r {
  align-self: flex-end;
}
.ch-day {
  display: flex;
  justify-content: center;
  margin: 14px 0 10px;
}
.ch-day span {
  padding: 3px 12px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  font-size: 11.5px;
  color: var(--v-muted);
}
.ch-msg {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: 72%;
  margin-bottom: 8px;
}
.ch-msg.own {
  align-items: flex-end;
  margin-left: auto;
}
.ch-bubble {
  max-width: 100%;
  padding: 9px 13px;
  border-radius: 16px 16px 16px 4px;
  background: var(--v-surface);
  border: 1px solid var(--v-border);
  color: var(--v-text);
}
.ch-msg.own .ch-bubble {
  border-color: var(--bc-accent);
  border-radius: 16px 16px 4px 16px;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.ch-msg.pending .ch-bubble {
  opacity: 0.65;
}
.ch-bubble p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.5;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.ch-images {
  display: grid;
  gap: 4px;
  margin: -3px -7px 6px;
}
.ch-images.many {
  grid-template-columns: 1fr 1fr;
}
.ch-images img {
  display: block;
  width: 100%;
  max-height: 240px;
  border-radius: 12px;
  object-fit: cover;
}
.ch-file {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  padding: 7px 10px;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.05);
  color: inherit;
  font-size: 12.5px;
  text-decoration: none;
  overflow-wrap: anywhere;
}
.ch-file :deep(svg) {
  flex: none;
  width: 16px;
  height: 16px;
}
.ch-meta {
  margin-top: 3px;
  font-size: 11px;
  color: var(--v-faint);
}

/* Composer */
.ch-composer {
  padding: 10px 14px 8px;
  border-top: 1px solid var(--v-border);
  background: var(--v-surface);
}
.ch-compose-row {
  display: flex;
  align-items: flex-end;
  gap: 6px;
}
.ch-clip {
  position: relative;
  cursor: pointer;
}
.ch-clip input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.ch-input {
  flex: 1;
  min-height: 40px;
  max-height: 140px;
  resize: none;
  line-height: 1.45;
}
.ch-send {
  width: 42px;
  height: 40px;
  padding: 0;
  justify-content: center;
}
.ch-hint {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: var(--v-faint);
}
.ch-send-error {
  margin: 0 0 6px;
}
.ch-quick {
  position: relative;
}
.ch-quick-menu {
  position: absolute;
  bottom: calc(100% + 8px);
  left: 0;
  z-index: 5;
  width: min(320px, 80vw);
  padding: 8px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-raised);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
}
.ch-quick-menu p {
  margin: 4px 8px 6px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--v-faint);
}
.ch-quick-menu button {
  display: block;
  width: 100%;
  padding: 8px 10px;
  border: 0;
  border-radius: 10px;
  background: none;
  font-family: inherit;
  font-size: 12.5px;
  text-align: left;
  color: var(--v-text);
  cursor: pointer;
}
.ch-quick-menu button:hover {
  background: var(--bc-accent-soft);
}
.ch-attach-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.ch-attach {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 220px;
  padding: 4px 6px 4px 4px;
  border: 1px solid var(--v-border-strong);
  border-radius: 10px;
  font-size: 12px;
}
.ch-attach img,
.ch-attach > span:first-child {
  flex: none;
  width: 28px;
  height: 28px;
  border-radius: 7px;
  object-fit: cover;
  display: grid;
  place-items: center;
}
.ch-attach .name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ch-attach button {
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--v-faint);
  cursor: pointer;
}
.ch-attach :deep(svg) {
  width: 14px;
  height: 14px;
}

/* Customer panel */
.ch-info {
  display: none;
  min-height: 0;
  padding: 18px;
  border-left: 1px solid var(--v-border);
  overflow-y: auto;
}
.show-info .ch-info {
  display: block;
}
.ch-info-head {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding-bottom: 16px;
  text-align: center;
}
.ch-info-head strong {
  font-size: 15px;
  font-weight: 600;
  overflow-wrap: anywhere;
}
.ch-info-head small {
  font-size: 12px;
  color: var(--v-muted);
}
.ch-info-close {
  display: none;
  position: absolute;
  top: 0;
  right: 0;
}
.ch-info-sk {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ch-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.ch-stats div {
  padding: 12px;
  border-radius: 12px;
  background: var(--v-subtle);
  text-align: center;
  min-width: 0;
}
.ch-stats strong {
  display: block;
  font-size: 16px;
  font-weight: 600;
  overflow-wrap: anywhere;
}
.ch-stats span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ch-contact {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 6px 12px;
  margin: 16px 0 0;
  font-size: 12.5px;
}
.ch-contact dt {
  color: var(--v-faint);
}
.ch-contact dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.ch-info h4 {
  margin: 20px 0 8px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.ch-orders {
  margin: 0;
  padding: 0;
  list-style: none;
}
.ch-orders button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  padding: 9px 10px;
  border: 0;
  border-radius: 10px;
  background: none;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}
.ch-orders button:hover {
  background: var(--v-subtle);
}
.ch-orders button > span:first-child {
  min-width: 0;
}
.ch-orders b {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 12.5px;
  font-weight: 600;
}
.ch-orders small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ch-orders .sp-chip {
  flex: none;
}

/* New conversation */
.nc-results {
  max-height: 300px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  overflow-y: auto;
}
.nc-results li.sp-hint {
  padding: 10px 4px;
}
.nc-results button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px;
  border: 0;
  border-radius: 10px;
  background: none;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}
.nc-results button:hover {
  background: var(--bc-accent-soft);
}
.nc-results b {
  display: block;
  font-size: 13px;
}
.nc-results small {
  font-size: 12px;
  color: var(--v-muted);
}
.nc-to {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  font-size: 13.5px;
}

@media (max-width: 1180px) {
  .ch-shell.has-thread.show-info {
    grid-template-columns: 300px minmax(0, 1fr);
  }
  .show-info .ch-info {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 1100;
    width: min(320px, 92vw);
    background: var(--v-surface);
    box-shadow: -12px 0 30px rgba(0, 0, 0, 0.12);
  }
  .ch-info-close {
    display: grid;
  }
}
@media (max-width: 760px) {
  .ch-shell,
  .ch-shell.has-thread.show-info {
    grid-template-columns: minmax(0, 1fr);
    height: calc(100vh - 210px);
  }
  .ch-shell.has-thread .ch-list {
    display: none;
  }
  .ch-shell:not(.has-thread) .ch-thread {
    display: none;
  }
  .ch-back {
    display: grid;
  }
  .ch-head-actions .sp-btn {
    display: none;
  }
  .ch-msg {
    max-width: 86%;
  }
}
</style>
