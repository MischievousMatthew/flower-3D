<template>
  <ShopLayout section="crm" title="Reviews & Ratings" subtitle="What customers say about your products. Reply to thank them or sort out a problem; your reply shows under their review.">
    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load()">Try again</button>
    </div>

    <div class="rv-top">
      <!-- Overall -->
      <section class="sp-card rv-overall" aria-label="Overall rating">
        <template v-if="!data">
          <div class="bc-skeleton rv-sk-big"></div>
          <div class="bc-skeleton sp-sk-block"></div>
        </template>
        <template v-else>
          <div class="rv-score">
            <strong>{{ s.average !== null ? s.average.toFixed(1) : "—" }}</strong>
            <div>
              <span class="rv-stars" :aria-label="`${s.average ?? 0} out of 5`">
                <i v-for="n in 5" :key="n" :style="{ '--fill': `${starFill(n)}%` }"></i>
              </span>
              <small>{{ s.count }} {{ s.count === 1 ? "review" : "reviews" }}</small>
              <small v-if="trend" class="rv-trend" :class="trend.dir">{{ trend.text }}</small>
            </div>
          </div>
          <ul class="rv-dist">
            <li v-for="n in [5, 4, 3, 2, 1]" :key="n">
              <button type="button" :class="{ on: rating === n }" :aria-pressed="rating === n" @click="toggleRating(n)">
                <span>{{ n }}★</span>
                <span class="rv-bar"><i :style="{ width: `${pct(n)}%` }" :class="{ low: n <= 2 }"></i></span>
                <b>{{ s.distribution[n] ?? 0 }}</b>
              </button>
            </li>
          </ul>
        </template>
      </section>

      <!-- To do + by product -->
      <section class="sp-card rv-side">
        <template v-if="!data">
          <div class="bc-skeleton sp-sk-line w50"></div>
          <div class="bc-skeleton sp-sk-block"></div>
        </template>
        <template v-else>
          <div v-if="data.can_reply" class="rv-todo">
            <button type="button" class="sp-stat" :class="{ on: replied === 'no' && !rating }" @click="showUnreplied">
              <span class="sp-stat-icon tone-amber" v-html="ICONS.chat"></span>
              <div><strong>{{ s.unreplied }}</strong><span>Without a reply</span></div>
            </button>
            <button type="button" class="sp-stat" :class="{ on: replied === 'no' && rating === 'low' }" @click="showLow">
              <span class="sp-stat-icon tone-red" v-html="ICONS.alert"></span>
              <div><strong>{{ s.low_unreplied }}</strong><span>Low ratings to answer</span></div>
            </button>
          </div>
          <h3 class="rv-h">By product</h3>
          <p v-if="!data.products.length" class="sp-hint">No ratings yet.</p>
          <ul v-else class="rv-products">
            <li v-for="p in data.products.slice(0, 6)" :key="p.id">
              <button type="button" :class="{ on: product === p.id }" @click="toggleProduct(p.id)">
                <span class="rv-pname">{{ p.name }}</span>
                <span class="rv-pscore"><b>{{ p.average.toFixed(1) }}</b>★ <small>({{ p.count }})</small></span>
              </button>
            </li>
          </ul>
        </template>
      </section>
    </div>

    <section class="sp-card">
      <div class="sp-toolbar rv-toolbar">
        <div class="rv-filters">
          <select v-model="product" class="sp-select" aria-label="Product" @change="load()">
            <option :value="null">All products</option>
            <option v-for="p in data?.products ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <select v-if="data?.can_reply" v-model="replied" class="sp-select" aria-label="Replies" @change="load()">
            <option value="">Replied or not</option>
            <option value="no">Without a reply</option>
            <option value="yes">Replied</option>
          </select>
          <select v-model="sort" class="sp-select" aria-label="Sort" @change="load()">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="lowest">Lowest rating</option>
            <option value="highest">Highest rating</option>
          </select>
        </div>
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Search what they wrote" aria-label="Search reviews" @input="debounced" />
        </label>
      </div>
      <p v-if="filtered" class="st-sub rv-filtered">
        Showing {{ data?.meta.total ?? 0 }} {{ (data?.meta.total ?? 0) === 1 ? "review" : "reviews" }}
        <button type="button" class="st-link" @click="clear">Show all</button>
      </p>

      <ul v-if="!data" class="rv-list">
        <li v-for="n in 3" :key="n" class="rv-item"><div class="bc-skeleton rv-thumb"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div></li>
      </ul>
      <div v-else-if="!data.reviews.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.star"></span>
        <h3>{{ filtered ? "No reviews match" : "No reviews yet" }}</h3>
        <p>{{ filtered ? "Try another filter." : "Customers can rate a product after their order is delivered. Reviews show up here." }}</p>
      </div>
      <ul v-else class="rv-list">
        <li v-for="r in data.reviews" :key="r.id" :ref="(el) => setFocusEl(r.id, el)" class="rv-item" :class="{ focus: r.id === focusId, hidden: r.hidden }">
          <img v-if="r.product.image" :src="r.product.image" alt="" class="rv-thumb" />
          <span v-else class="rv-thumb rv-thumb-icon" v-html="ICONS.flower"></span>
          <div class="rv-main">
            <div class="rv-head">
              <span class="rv-stars sm" :aria-label="`${r.rating} out of 5`"><i v-for="n in 5" :key="n" :style="{ '--fill': n <= r.rating ? '100%' : '0%' }"></i></span>
              <strong>{{ r.product.name }}</strong>
              <span class="st-sub">{{ timeAgo(r.created_at) }}</span>
            </div>
            <p class="rv-by">
              {{ r.reviewer }}
              <span v-if="r.verified" class="sp-chip green">Bought from you</span>
              <span v-if="r.hidden" class="sp-chip slate" :title="r.hidden_reason || ''">Hidden by BloomCraft</span>
            </p>
            <p v-if="r.comment" class="rv-comment">{{ r.comment }}</p>
            <p v-else class="rv-comment empty">No written comment, just a rating.</p>
            <a v-if="r.image_url" :href="r.image_url" target="_blank" rel="noopener" class="rv-photo"><img :src="r.image_url" alt="Photo from the review" loading="lazy" /></a>

            <div v-if="r.reply && editing !== r.id" class="rv-reply">
              <div class="rv-reply-head"><b>Your reply</b><span class="st-sub">{{ timeAgo(r.replied_at) }}</span></div>
              <p>{{ r.reply }}</p>
              <div class="rv-reply-actions">
                <button type="button" class="st-link" @click="startEdit(r)">Edit</button>
                <button type="button" class="st-link muted" :disabled="busy === r.id" @click="save(r, '')">Remove</button>
              </div>
            </div>
            <form v-else-if="data.can_reply && (editing === r.id || !r.reply)" class="rv-form" @submit.prevent="save(r, drafts[r.id])">
              <template v-if="open[r.id] || editing === r.id">
                <textarea v-model="drafts[r.id]" class="sp-textarea" rows="3" maxlength="1000" :placeholder="r.rating <= 2 ? 'Sorry to hear that. Tell them how you\'ll make it right…' : 'Thank them for their order…'" :aria-label="`Reply to ${r.reviewer}`"></textarea>
                <div class="rv-form-foot">
                  <span class="st-sub">Everyone who views this product can see your reply.</span>
                  <div>
                    <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="cancel(r)">Cancel</button>
                    <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === r.id || !(drafts[r.id] || '').trim()">{{ busy === r.id ? "Posting…" : "Post reply" }}</button>
                  </div>
                </div>
              </template>
              <button v-else type="button" class="st-link" @click="open[r.id] = true"><span class="rv-reply-icon" v-html="ICONS.chat"></span>Reply</button>
            </form>
          </div>
        </li>
      </ul>

      <nav v-if="data && data.meta.last_page > 1" class="rv-pages" aria-label="Pages">
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="data.meta.page <= 1" @click="load(data.meta.page - 1)"><span v-html="ICONS.left"></span>Previous</button>
        <span class="st-sub">Page {{ data.meta.page }} of {{ data.meta.last_page }}</span>
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="data.meta.page >= data.meta.last_page" @click="load(data.meta.page + 1)">Next<span v-html="ICONS.right"></span></button>
      </nav>
    </section>
  </ShopLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { timeAgo } from "../../../components/vendor/crm/crmUi";
import { linkedId } from "../../../utils/hiddenIds";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

const data = ref(null);
const error = ref("");
const rating = ref(null); // 1–5, "low" (1–2) or null
const product = ref(null);
const replied = ref("");
const sort = ref("newest");
const search = ref("");
const drafts = reactive({});
const open = reactive({});
const editing = ref(null);
const busy = ref(null);
const focusId = ref(linkedId("review"));
let focusEl = null;
let timer = null;
const setFocusEl = (id, el) => {
  if (id === focusId.value && el) focusEl = el;
};

const s = computed(() => data.value?.summary ?? {});
const filtered = computed(() => !!(rating.value || product.value || replied.value || search.value.trim()));
const trend = computed(() => {
  const { recent_average: now, previous_average: before, recent_count: n } = s.value;
  if (!n) return null;
  if (before === null) return { dir: "flat", text: `${n} in the last 30 days` };
  const diff = Math.round((now - before) * 10) / 10;
  if (Math.abs(diff) < 0.1) return { dir: "flat", text: `Steady over the last 30 days` };
  return { dir: diff > 0 ? "up" : "down", text: `${diff > 0 ? "▲" : "▼"} ${Math.abs(diff).toFixed(1)} in the last 30 days` };
});

const pct = (n) => (s.value.count ? ((s.value.distribution?.[n] ?? 0) / s.value.count) * 100 : 0);
const starFill = (n) => Math.max(0, Math.min(100, ((s.value.average ?? 0) - (n - 1)) * 100));

async function load(page = 1) {
  error.value = "";
  const params = {
    page,
    rating: rating.value || undefined,
    product: product.value || undefined,
    replied: replied.value || undefined,
    sort: sort.value,
    search: search.value.trim() || undefined,
  };
  try {
    const res = await api.get("vendor/crm/reviews", { params });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load reviews.");
  }
}
function debounced() {
  clearTimeout(timer);
  timer = setTimeout(() => load(), 300);
}
function toggleRating(n) {
  rating.value = rating.value === n ? null : n;
  load();
}
function toggleProduct(id) {
  product.value = product.value === id ? null : id;
  load();
}
function showUnreplied() {
  rating.value = null;
  replied.value = replied.value === "no" ? "" : "no";
  load();
}
function showLow() {
  const on = !(replied.value === "no" && rating.value === "low");
  replied.value = on ? "no" : "";
  rating.value = on ? "low" : null;
  load();
}
function clear() {
  rating.value = null;
  product.value = null;
  replied.value = "";
  search.value = "";
  load();
}

function startEdit(r) {
  drafts[r.id] = r.reply;
  editing.value = r.id;
}
function cancel(r) {
  open[r.id] = false;
  if (editing.value === r.id) editing.value = null;
  drafts[r.id] = "";
}
async function save(r, text) {
  busy.value = r.id;
  try {
    const { data: res } = await api.post(`vendor/crm/reviews/${r.id}/reply`, { reply: (text || "").trim() });
    toast.success(res.message);
    r.reply = (text || "").trim() || null;
    r.replied_at = r.reply ? new Date().toISOString() : null;
    editing.value = null;
    open[r.id] = false;
    drafts[r.id] = "";
    if (data.value.summary.unreplied !== null) {
      const res2 = await api.get("vendor/crm/reviews", { params: { page: 1 } });
      data.value.summary = res2.data.data.summary;
    }
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't save your reply."));
  } finally {
    busy.value = null;
  }
}

onMounted(async () => {
  await load();
  if (focusId.value) {
    await nextTick();
    focusEl?.scrollIntoView({ behavior: "smooth", block: "center" });
    open[focusId.value] = true;
  }
});
</script>

<style scoped>
.rv-top {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  gap: 16px;
  margin-bottom: 18px;
}
.rv-sk-big {
  height: 60px;
  width: 50%;
  margin-bottom: 14px;
  border-radius: 12px;
}
.rv-score {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}
.rv-score > strong {
  font-size: 46px;
  font-weight: 600;
  line-height: 1;
  letter-spacing: -0.02em;
}
.rv-score small {
  display: block;
  margin-top: 3px;
  font-size: 12.5px;
  color: #718096;
}
.rv-trend.up {
  color: #2f855a;
}
.rv-trend.down {
  color: #c53030;
}
.rv-stars {
  display: inline-flex;
  gap: 3px;
}
.rv-stars i {
  --fill: 0%;
  width: 20px;
  height: 20px;
  background: linear-gradient(90deg, #f6ad55 var(--fill), #e2e8f0 var(--fill));
  clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
}
.rv-stars.sm i {
  width: 15px;
  height: 15px;
}
.rv-dist {
  margin: 0;
  padding: 0;
  list-style: none;
}
.rv-dist button {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) 34px;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 5px 8px;
  border: 0;
  border-radius: 8px;
  background: none;
  font-family: inherit;
  font-size: 12.5px;
  color: #4a5568;
  cursor: pointer;
}
.rv-dist button:hover,
.rv-dist button.on {
  background: #f7fafc;
}
.rv-dist button.on {
  box-shadow: inset 0 0 0 1px var(--bc-accent-border);
}
.rv-dist b {
  text-align: right;
  font-weight: 600;
}
.rv-bar {
  height: 8px;
  border-radius: 999px;
  background: #edf2f7;
  overflow: hidden;
}
.rv-bar i {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: #f6ad55;
}
.rv-bar i.low {
  background: #fc8181;
}
.rv-todo {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 14px;
}
.rv-todo .sp-stat {
  padding: 12px;
}
.rv-h {
  margin: 0 0 6px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #a0aec0;
}
.rv-products {
  margin: 0;
  padding: 0;
  list-style: none;
}
.rv-products button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  padding: 7px 8px;
  border: 0;
  border-radius: 8px;
  background: none;
  font-family: inherit;
  font-size: 13px;
  color: #2d3748;
  cursor: pointer;
}
.rv-products button:hover,
.rv-products button.on {
  background: var(--bc-accent-soft);
}
.rv-pname {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rv-pscore {
  flex: none;
  color: #dd6b20;
}
.rv-pscore b {
  color: #1a202c;
}
.rv-pscore small {
  color: #a0aec0;
}
.rv-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.rv-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rv-filters .sp-select {
  width: auto;
  min-width: 150px;
}
.rv-toolbar .sp-search {
  flex: 1 1 220px;
  max-width: 300px;
}
.rv-filtered {
  display: flex;
  gap: 10px;
  margin: 10px 0 0;
}
.rv-list {
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
}
.rv-item {
  display: flex;
  gap: 14px;
  padding: 18px 4px;
  border-top: 1px solid #f1f5f9;
}
.rv-item:first-child {
  border-top: 0;
}
.rv-item .grow {
  flex: 1;
}
.rv-item.focus {
  margin: 0 -10px;
  padding: 18px 14px;
  border-radius: 14px;
  background: #fffaf0;
  box-shadow: inset 0 0 0 1px #fbd38d;
}
.rv-item.hidden {
  opacity: 0.7;
}
.rv-thumb {
  flex: none;
  width: 52px;
  height: 52px;
  border-radius: 12px;
  object-fit: cover;
}
.rv-thumb-icon {
  display: grid;
  place-items: center;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.rv-thumb-icon :deep(svg) {
  width: 22px;
  height: 22px;
}
.rv-main {
  flex: 1;
  min-width: 0;
}
.rv-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 10px;
}
.rv-head strong {
  font-size: 14px;
  font-weight: 600;
  overflow-wrap: anywhere;
}
.rv-head .st-sub {
  margin-left: auto;
}
.rv-by {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 4px 0 0;
  font-size: 12.5px;
  color: #4a5568;
}
.rv-comment {
  margin: 10px 0 0;
  font-size: 14px;
  line-height: 1.55;
  color: #1a202c;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.rv-comment.empty {
  font-size: 13px;
  font-style: italic;
  color: #a0aec0;
}
.rv-photo img {
  display: block;
  width: 96px;
  height: 96px;
  margin-top: 10px;
  border-radius: 12px;
  object-fit: cover;
}
.rv-reply {
  margin-top: 12px;
  padding: 12px 14px;
  border-left: 3px solid var(--bc-accent);
  border-radius: 0 12px 12px 0;
  background: #f7fafc;
}
.rv-reply-head {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 4px;
}
.rv-reply-head b {
  font-size: 12.5px;
  color: var(--bc-accent-deep);
}
.rv-reply p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.55;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.rv-reply-actions {
  display: flex;
  gap: 14px;
  margin-top: 8px;
}
.st-link.muted {
  color: #718096;
}
.rv-form {
  margin-top: 12px;
}
.rv-form > .st-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.rv-reply-icon :deep(svg) {
  width: 15px;
  height: 15px;
}
.rv-form-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}
.rv-form-foot > div {
  display: flex;
  gap: 8px;
}
.rv-pages {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #edf2f7;
}
@media (max-width: 900px) {
  .rv-top {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .rv-todo {
    grid-template-columns: minmax(0, 1fr);
  }
  .rv-head .st-sub {
    margin-left: 0;
  }
}
</style>
