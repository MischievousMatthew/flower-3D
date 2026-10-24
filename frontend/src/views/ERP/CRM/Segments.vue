<template>
  <HrPage title="Segments & offers" subtitle="Your customers grouped by what they buy and the preferences they chose to share, so each offer goes to the people it suits.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="load"><span v-html="ICONS.restore"></span>Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Consent -->
    <section v-if="data" class="sg-consent" aria-label="Who can get offers">
      <span class="sg-consent-icon" v-html="ICONS.shield"></span>
      <p>
        <strong>{{ data.reachable }} of {{ data.customers }} {{ data.customers === 1 ? "customer" : "customers" }}</strong> said yes to offers from shops they buy from.
        Offers only go to them, at most one every {{ data.cooldown_days }} days, and they can stop your offers from their profile at any time.
      </p>
    </section>

    <!-- Groups -->
    <section class="sg-grid" aria-label="Customer groups">
      <template v-if="!data">
        <div v-for="n in 5" :key="n" class="sp-card sg-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
      </template>
      <template v-else>
        <article v-for="s in data.segments" :key="s.key" class="sp-card sg-card" :class="{ on: selected === s.key }">
          <header>
            <span class="sg-icon" :class="`tone-${META[s.key].tone}`" v-html="META[s.key].icon"></span>
            <div class="grow"><h3>{{ s.label }}</h3><small>{{ s.rule }}</small></div>
          </header>
          <strong class="sg-count">{{ s.count }}</strong>
          <dl class="sg-facts">
            <div><dt>Can get offers</dt><dd>{{ s.reachable }}</dd></div>
            <div><dt>Spent with you</dt><dd>{{ money(s.spent) }}</dd></div>
          </dl>
          <div class="sg-reach" role="img" :aria-label="`${s.reachable} of ${s.count} can get offers`"><span :style="{ width: `${s.count ? (s.reachable / s.count) * 100 : 0}%` }"></span></div>
          <footer>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :aria-pressed="selected === s.key" :disabled="!s.count" @click="pick(s.key)">{{ selected === s.key ? "Showing" : "See who" }}</button>
            <button v-if="canSend" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="!s.reachable" :title="s.reachable ? '' : 'No one in this group has said yes to offers yet'" @click="openSend(s)"><span v-html="ICONS.send"></span>Send offer</button>
          </footer>
        </article>
      </template>
    </section>

    <div class="sg-cols">
      <!-- Members -->
      <section class="sp-card sg-members" aria-label="Customers in this group">
        <div class="sp-card-head">
          <div>
            <h2>{{ members?.segment.label || "Customers in a group" }}</h2>
            <p>{{ members ? members.segment.rule : "Pick a group above to see who's in it." }}</p>
          </div>
          <label v-if="selected" class="sp-search sg-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Name or email" aria-label="Search this group" @input="onSearch" />
          </label>
        </div>
        <div v-if="!selected" class="sp-empty sg-empty">
          <span class="sp-empty-icon" v-html="ICONS.users"></span>
          <p>Choose "See who" on a group.</p>
        </div>
        <ul v-else-if="!members" class="sg-rows">
          <li v-for="n in 4" :key="n" class="sg-row"><div class="bc-skeleton sg-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <p v-else-if="!members.rows.length" class="sp-hint">No one matches.</p>
        <template v-else>
          <ul class="sg-rows" :class="{ dim: membersLoading }">
            <li v-for="m in members.rows" :key="m.id" class="sg-row">
              <span class="sg-avatar" aria-hidden="true">{{ initials(m.name) }}</span>
              <div class="grow">
                <strong>{{ m.name }}</strong>
                <small>{{ m.orders }} {{ m.orders === 1 ? "order" : "orders" }} · {{ money(m.spent) }} · last {{ ago(m.last_order_at) }}</small>
                <small v-if="m.why.length" class="sg-why">{{ m.why.join(" · ") }}</small>
              </div>
              <span class="sg-tags hide-sm">
                <span v-for="k in m.segments.filter((x) => x !== selected)" :key="k" class="sp-chip slate">{{ META[k].short }}</span>
              </span>
              <span class="sp-chip" :class="m.gets_offers ? 'green' : 'slate'">{{ m.gets_offers ? "Gets offers" : "No offers" }}</span>
            </li>
          </ul>
          <footer v-if="members.meta.last_page > 1" class="sg-pager">
            <span>{{ members.meta.total }} people · page {{ members.meta.page }} of {{ members.meta.last_page }}</span>
            <div>
              <button type="button" class="sp-icon-btn" :disabled="members.meta.page <= 1" aria-label="Previous page" @click="loadMembers(members.meta.page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
              <button type="button" class="sp-icon-btn" :disabled="members.meta.page >= members.meta.last_page" aria-label="Next page" @click="loadMembers(members.meta.page + 1)"><span v-html="ICONS.chevronRight"></span></button>
            </div>
          </footer>
        </template>
      </section>

      <!-- Recent offers -->
      <section class="sp-card" aria-label="Offers you sent">
        <div class="sp-card-head"><div><h2>Offers you sent</h2><p>Latest first</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <p v-else-if="!data.offers.length" class="sp-hint">No offers yet. Send one from a group above.</p>
        <ul v-else class="sg-offers">
          <li v-for="o in data.offers" :key="o.id">
            <strong>{{ o.title }}</strong>
            <p>{{ o.body }}</p>
            <small>{{ o.segment_label }} · sent to {{ o.recipients }} · seen by {{ o.seen }} · {{ o.sent_by }} · {{ ago(o.created_at) }}</small>
            <div class="sg-seen" role="img" :aria-label="`${o.seen} of ${o.recipients} have seen it`"><span :style="{ width: `${o.recipients ? (o.seen / o.recipients) * 100 : 0}%` }"></span></div>
          </li>
        </ul>
      </section>
    </div>

    <!-- Send an offer -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="form = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="so-title" novalidate @submit.prevent="send">
            <div class="sp-modal-head">
              <div><h3 id="so-title">Send an offer to {{ form.segment.label.toLowerCase() }}</h3><p>Goes to {{ form.segment.reachable }} {{ form.segment.reachable === 1 ? "customer" : "customers" }} who said yes to offers (anyone who got one from you in the last {{ data.cooldown_days }} days is skipped).</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="form = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sg-form">
              <div class="sg-fields">
                <label class="sp-field">
                  <span class="sp-label">Headline</span>
                  <input v-model="form.title" class="sp-input" maxlength="120" :placeholder="META[form.segment.key].title" />
                  <small v-if="form.errors.title" class="sp-error">{{ form.errors.title }}</small>
                </label>
                <label class="sp-field">
                  <span class="sp-label">Message</span>
                  <textarea v-model="form.body" class="sp-textarea" rows="4" maxlength="1000" :placeholder="META[form.segment.key].body"></textarea>
                  <small class="sp-hint">{{ 1000 - form.body.length }} characters left</small>
                  <small v-if="form.errors.body" class="sp-error">{{ form.errors.body }}</small>
                </label>
                <label class="sp-field">
                  <span class="sp-label">Attach a promotion <small>(optional)</small></span>
                  <select v-model="form.promotion_id" class="sp-select">
                    <option value="">None</option>
                    <option v-for="p in data.promotions" :key="p.id" :value="p.id">{{ p.name }} · {{ p.label }} · until {{ shortDate(p.ends_at) }}</option>
                  </select>
                  <small v-if="!data.promotions.length" class="sp-hint">You have no running promotions. Create one in the vendor portal under Promotions.</small>
                </label>
              </div>
              <!-- What the customer sees -->
              <div class="sg-preview" aria-label="Preview">
                <span class="sp-label">What they'll see</span>
                <div class="sg-preview-card">
                  <small>{{ shopName }} · just now</small>
                  <strong>{{ form.title || META[form.segment.key].title }}</strong>
                  <p>{{ form.body || META[form.segment.key].body }}</p>
                  <span v-if="previewPromo" class="sg-preview-promo">{{ previewPromo.label }} · {{ previewPromo.name }}</span>
                </div>
              </div>
            </div>
            <p v-if="form.error" class="sp-error">{{ form.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="form = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="form.busy"><span v-html="ICONS.send"></span>{{ form.busy ? "Sending…" : `Send to ${form.segment.reachable}` }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../components/vendor/finance/financeUi";
import { CRM_ICONS } from "../../../components/crm/customerPrefs";
import { useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";
import "../../../components/vendor/finance/finance.css";

// How each group looks, and a starting point for its offer
const META = {
  new: { short: "New", tone: "green", icon: ICONS.sparkle, title: "Welcome! A little thank-you", body: "Thanks for your first order. Here's something for your next bouquet." },
  repeat: { short: "Repeat", tone: "blue", icon: ICONS.restore, title: "Thanks for coming back", body: "You've ordered with us more than once. Here's a treat for your next order." },
  frequent: { short: "Frequent", tone: "violet", icon: CRM_ICONS.star, title: "For our favorite regulars", body: "You're one of our most loyal customers. Enjoy this offer before anyone else." },
  events: { short: "Events", tone: "pink", icon: CRM_ICONS.anniversary, title: "Planning a wedding or event?", body: "Our event flowers are ready for your big day. Message us to plan the arrangements." },
  inactive: { short: "Inactive", tone: "amber", icon: ICONS.clock, title: "We miss you!", body: "It's been a while. Fresh flowers are in, and here's an offer to welcome you back." },
};

const { can } = useAssignment();
const { user } = useAuth();
const canSend = computed(() => can("crm_segments", "create"));
const shopName = computed(() => user.value?.shop_name || user.value?.vendor_data?.store_name || "Your shop");

const data = ref(null);
const error = ref("");
const loading = ref(false);
const selected = ref("");
const members = ref(null);
const membersLoading = ref(false);
const search = ref("");
const form = ref(null);

const previewPromo = computed(() => data.value?.promotions.find((p) => p.id === form.value?.promotion_id) ?? null);

const initials = (name) => (name || "?").split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
const shortDate = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : "");
function ago(at) {
  if (!at) return "—";
  const d = Math.round((Date.now() - new Date(at).getTime()) / 86400000);
  if (d < 1) return "today";
  if (d === 1) return "yesterday";
  if (d < 30) return `${d} days ago`;
  return d < 365 ? `${Math.round(d / 30)} months ago` : `${Math.round(d / 365)} years ago`;
}

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get("crm/segments");
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your customer groups.");
  } finally {
    loading.value = false;
  }
}
async function loadMembers(p = 1) {
  const key = selected.value;
  membersLoading.value = true;
  try {
    const res = await api.get(`crm/segments/${key}`, { params: { page: p, search: search.value.trim() || undefined } });
    if (selected.value === key) members.value = res.data.data;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't load this group."));
  } finally {
    membersLoading.value = false;
  }
}
function pick(key) {
  selected.value = key;
  members.value = null;
  search.value = "";
  loadMembers(1);
}
let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadMembers(1), 300);
}

function openSend(segment) {
  form.value = { segment, title: "", body: "", promotion_id: "", busy: false, error: "", errors: {} };
}
async function send() {
  const f = form.value;
  f.errors = {};
  const title = f.title.trim() || META[f.segment.key].title;
  const body = f.body.trim() || META[f.segment.key].body;
  f.busy = true;
  f.error = "";
  try {
    const res = await api.post(`crm/segments/${f.segment.key}/offers`, { title, body, promotion_id: f.promotion_id || null });
    toast.success(res.data.message);
    form.value = null;
    load();
  } catch (e) {
    const errors = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(errors)) f.errors[k] = [].concat(v)[0];
    f.error = Object.keys(errors).length ? "" : apiMessage(e, "Couldn't send the offer.");
  } finally {
    if (form.value) form.value.busy = false;
  }
}

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sg-consent {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-surface);
}
.sg-consent p {
  margin: 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
}
.sg-consent strong {
  color: var(--v-text);
}
.sg-consent-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.sg-consent-icon :deep(svg) {
  width: 17px;
  height: 17px;
}

/* Groups */
.sg-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}
.sg-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.sg-card.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 15%, transparent);
}
.sg-card header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.sg-card h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.sg-card header small {
  font-size: 12px;
  line-height: 1.4;
  color: var(--v-muted);
}
.sg-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
}
.sg-icon :deep(svg) {
  width: 17px;
  height: 17px;
}
.tone-pink {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.sg-count {
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
  color: var(--v-text);
}
.sg-facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin: 0;
}
.sg-facts dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.sg-facts dd {
  margin: 0;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sg-reach,
.sg-seen {
  height: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.sg-reach span,
.sg-seen span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--bc-accent);
}
.sg-card footer {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: auto;
}
.sg-card footer .sp-btn {
  min-height: 36px;
}

/* Members and offers */
.sg-cols {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.sg-cols > section {
  min-width: 0;
}
.sg-search {
  flex: 0 1 240px;
}
.sg-empty p {
  margin: 0;
}
.sg-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.sg-rows.dim {
  opacity: 0.55;
}
.sg-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 2px;
  border-top: 1px solid var(--v-border);
}
.sg-row:first-child {
  border-top: 0;
}
.sg-row strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sg-row small {
  font-size: 12px;
  color: var(--v-muted);
}
.sg-why {
  color: var(--v-purple-text) !important;
}
.sg-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12.5px;
  font-weight: 600;
}
.sg-tags {
  display: flex;
  gap: 4px;
}
.sg-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.sg-pager div {
  display: flex;
  gap: 6px;
}
.sg-offers {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 12px;
}
.sg-offers li {
  display: grid;
  gap: 4px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.sg-offers strong {
  font-size: 13.5px;
  color: var(--v-text);
}
.sg-offers p {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.sg-offers small {
  font-size: 11.5px;
  color: var(--v-muted);
}

/* Send */
.sg-form {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 16px;
}
.sg-fields {
  display: grid;
  gap: 12px;
  align-content: start;
}
.sp-label small {
  font-weight: 400;
  color: var(--v-faint);
}
.sg-preview {
  display: grid;
  gap: 8px;
  align-content: start;
}
.sg-preview-card {
  display: grid;
  gap: 4px;
  padding: 14px 16px;
  border-radius: 16px;
  background: #fbfaf7;
  border: 1px solid #e7e2d8;
  color: #363f4e;
}
.sg-preview-card small {
  font-size: 11.5px;
  color: #6b7280;
}
.sg-preview-card strong {
  font-size: 15px;
}
.sg-preview-card p {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  white-space: pre-wrap;
}
.sg-preview-promo {
  justify-self: start;
  margin-top: 4px;
  padding: 3px 10px;
  border-radius: 999px;
  background: #e3f4ea;
  color: #2f855a;
  font-size: 12px;
  font-weight: 600;
}

@media (max-width: 1100px) {
  .sg-cols {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 720px) {
  .sg-form {
    grid-template-columns: minmax(0, 1fr);
  }
  .hide-sm {
    display: none;
  }
}
</style>
