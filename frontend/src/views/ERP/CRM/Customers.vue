<template>
  <HrPage title="Customers" subtitle="Everyone who has ordered, chatted or asked the shop a question: how to reach them, what they bought, what they like, and notes for the team.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="refresh()"><span v-html="ICONS.restore"></span>Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="refresh()">Try again</button>
    </div>

    <!-- Coming up -->
    <section v-if="data?.upcoming.length" class="sp-card cu-soon" aria-label="Coming up">
      <div class="sp-card-head"><div><h2>Coming up</h2><p>Special days in the next 30 days, a good time to reach out</p></div></div>
      <div class="cu-soon-row">
        <button v-for="d in data.upcoming" :key="`${d.id}-${d.label}`" type="button" class="cu-soon-card" :title="soonTitle(d)" @click="open(d.id)">
          <span class="cu-soon-icon" :class="d.kind" v-html="kindOf(d.kind).icon"></span>
          <span class="grow">
            <strong>{{ d.name }}</strong>
            <small>{{ d.label }}<template v-if="d.turning"> · turning {{ d.turning }}</template></small>
          </span>
          <span class="sp-chip" :class="d.days_away <= 7 ? 'amber' : 'slate'">{{ whenText(d.days_away) }}</span>
        </button>
      </div>
    </section>

    <section class="sp-card cu-list" aria-label="Customers">
      <div class="cu-list-head">
        <div class="sp-seg" role="tablist" aria-label="Show">
          <button v-for="f in FILTERS" :key="f.value" type="button" role="tab" :aria-selected="filter === f.value" :class="{ on: filter === f.value }" @click="setFilter(f.value)">
            {{ f.label }}<span v-if="data" class="count">{{ data.counts[f.value] }}</span>
          </button>
        </div>
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Name, email or phone" aria-label="Search customers" @input="onSearch" />
        </label>
      </div>

      <ul v-if="!data" class="cu-rows">
        <li v-for="n in 6" :key="n" class="cu-row"><div class="bc-skeleton cu-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!data.rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.users"></span>
        <h3>{{ search || filter !== "all" ? "No one matches" : "No customers yet" }}</h3>
        <p>{{ search || filter !== "all" ? "Try another search or filter." : "People appear here once they order, message the shop or ask about a product." }}</p>
      </div>
      <template v-else>
        <ul class="cu-rows" :class="{ dim: loading }">
          <li v-for="c in list.rows.value" :key="c.id">
            <button type="button" class="cu-row" :class="{ on: selectedId === c.id }" @click="open(c.id)">
              <Avatar :name="c.name" :src="c.avatar" />
              <span class="grow">
                <strong>{{ c.name }}</strong>
                <small>{{ [c.email, c.phone].filter(Boolean).join(" · ") }}</small>
              </span>
              <span class="cu-tags hide-sm">
                <span v-if="c.next_date && c.next_date.days_away <= 30" class="sp-chip amber cu-ichip"><span v-html="kindOf(c.next_date.kind).icon"></span>{{ kindOf(c.next_date.kind).label }} {{ whenText(c.next_date.days_away) }}</span>
                <span v-if="c.notes" class="sp-chip slate" :title="notesLabel(c.notes)" :aria-label="notesLabel(c.notes)"><span v-html="ICONS.note"></span>{{ c.notes }}</span>
              </span>
              <span class="cu-money">
                <strong>{{ money(c.spent) }}</strong>
                <small>{{ c.orders }} {{ c.orders === 1 ? "order" : "orders" }}<template v-if="c.last_order_at"> · {{ shortDate(c.last_order_at) }}</template></small>
              </span>
              <span class="cu-go" v-html="ICONS.chevronRight"></span>
            </button>
          </li>
        </ul>
        <ShowMore v-if="data && data.rows.length" tone="vendor" :has-more="list.hasMore.value" :loading="list.loadingMore.value" :shown="list.rows.value.length" :total="list.total.value" @more="list.showMore()" />
      </template>
    </section>

    <!-- Profile -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="selectedId" class="sp-backdrop" @click="close"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="selectedId" class="sp-drawer cu-drawer" role="dialog" aria-modal="true" aria-labelledby="cu-title">
          <div class="sp-drawer-head">
            <div class="cu-who">
              <Avatar :name="profile?.name || '…'" :src="profile?.avatar" big />
              <div>
                <h3 id="cu-title">{{ profile?.name || "Loading…" }}</h3>
                <p v-if="profile">Customer since {{ shortDate(profile.member_since) }}<template v-if="profile.stats.orders"> · {{ profile.stats.orders }} {{ profile.stats.orders === 1 ? "order" : "orders" }}</template></p>
              </div>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="close"><span v-html="ICONS.close"></span></button>
          </div>

          <div class="sp-drawer-body">
            <div class="sp-seg cu-tabs" role="tablist" aria-label="Sections">
              <button v-for="t in TABS" :key="t.value" type="button" role="tab" :aria-selected="tab === t.value" :class="{ on: tab === t.value }" @click="tab = t.value">
                {{ t.label }}<span v-if="t.value === 'notes' && profile?.notes.length" class="count">{{ profile.notes.length }}</span>
              </button>
            </div>

            <div v-if="profileError" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ profileError }}</div>
            <div v-else-if="!profile" class="cu-sk"><div v-for="n in 5" :key="n" class="bc-skeleton sp-sk-line"></div></div>

            <!-- Overview -->
            <template v-else-if="tab === 'overview'">
              <div class="cu-stats">
                <div><strong>{{ money(profile.stats.spent) }}</strong><span>Spent</span></div>
                <div><strong>{{ profile.stats.orders }}</strong><span>Orders</span></div>
                <div><strong>{{ money(profile.stats.average) }}</strong><span>Average order</span></div>
              </div>

              <h4 class="st-section-title">Contact</h4>
              <dl class="st-dl">
                <dt>Email</dt><dd><a :href="`mailto:${profile.contact.email}`" class="st-link">{{ profile.contact.email }}</a></dd>
                <dt>Phone</dt><dd><a v-if="profile.contact.phone" :href="`tel:${profile.contact.phone}`" class="st-link">{{ profile.contact.phone }}</a><template v-else>—</template></dd>
                <dt>Address</dt><dd>{{ profile.contact.address || "—" }}</dd>
                <template v-if="profile.contact.last_delivery">
                  <dt>Last delivered to</dt><dd>{{ profile.contact.last_delivery.address }}<small v-if="profile.contact.last_delivery.name" class="cu-sub">{{ profile.contact.last_delivery.name }}<template v-if="profile.contact.last_delivery.phone"> · {{ profile.contact.last_delivery.phone }}</template></small></dd>
                </template>
                <dt>First order</dt><dd>{{ profile.stats.first_order_at ? shortDate(profile.stats.first_order_at) : "—" }}</dd>
                <dt>Last order</dt><dd>{{ profile.stats.last_order_at ? shortDate(profile.stats.last_order_at) : "—" }}</dd>
              </dl>

              <h4 class="st-section-title">Special days</h4>
              <p v-if="!allDates.length" class="sp-hint">No special days yet. Add one under Preferences when they mention it.</p>
              <ul v-else class="cu-dates">
                <li v-for="d in allDates" :key="`${d.source}-${d.id}`">
                  <span class="cu-date-icon" :class="d.kind" v-html="kindOf(d.kind).icon"></span>
                  <span class="grow"><strong>{{ d.label }}</strong><small>{{ monthDay(d) }}<template v-if="d.turning"> · turning {{ d.turning }}</template> · {{ d.source === "shared" ? "shared by them" : `noted by ${d.added_by || "the shop"}` }}</small></span>
                  <span class="sp-chip" :class="d.days_away <= 7 ? 'amber' : 'slate'">{{ whenText(d.days_away) }}</span>
                </li>
              </ul>

              <h4 class="st-section-title">Most bought</h4>
              <p v-if="!profile.top_products.length" class="sp-hint">They haven't bought anything yet.</p>
              <ul v-else class="cu-top">
                <li v-for="p in profile.top_products" :key="p.name">
                  <span class="grow"><strong>{{ p.name }}</strong><small>{{ p.times }} {{ p.times === 1 ? "order" : "orders" }}</small></span>
                  <b>×{{ p.qty }}</b>
                </li>
              </ul>
            </template>

            <!-- Orders -->
            <template v-else-if="tab === 'orders'">
              <p v-if="!profile.orders.length" class="sp-hint">No orders yet.</p>
              <ul v-else class="cu-orders">
                <li v-for="o in profile.orders" :key="o.id">
                  <span class="grow"><strong>{{ o.order_number }}</strong><small>{{ shortDate(o.created_at) }} · {{ PAY[o.payment_method] ?? o.payment_method }}<template v-if="o.payment_status"> · {{ o.payment_status }}</template></small></span>
                  <span class="sp-chip" :class="(ORDER_STATUS[o.status] ?? [o.status, 'slate'])[1]">{{ (ORDER_STATUS[o.status] ?? [o.status])[0] }}</span>
                  <b class="cu-order-total">{{ money(o.total) }}</b>
                </li>
              </ul>
            </template>

            <!-- Preferences -->
            <template v-else-if="tab === 'prefs'">
              <section class="cu-box shared">
                <header><span v-html="ICONS.user"></span><div><strong>Shared by {{ firstName }}</strong><small>From their own profile. Only they can change it.</small></div></header>
                <p v-if="!profile.shared" class="sp-hint">They haven't shared their preferences with shops. It shows here if they turn sharing on in their profile.</p>
                <template v-else>
                  <dl class="cu-pref-list">
                    <template v-for="f in PREF_FIELDS" :key="f.key">
                      <dt>{{ f.label }}</dt>
                      <dd><span v-for="t in profile.shared[f.key]" :key="t" class="cu-chip" :class="{ warn: f.key === 'avoid' }">{{ t }}</span><template v-if="!profile.shared[f.key].length">—</template></dd>
                    </template>
                    <template v-if="profile.hearted.length">
                      <dt>Hearted products</dt>
                      <dd><span v-for="p in profile.hearted" :key="p.id" class="cu-chip cu-ichip heart"><span v-html="CRM_ICONS.heart"></span>{{ p.name }}</span></dd>
                    </template>
                  </dl>
                  <p v-if="profile.shared.note" class="cu-quote">“{{ profile.shared.note }}”</p>
                </template>
              </section>

              <section class="cu-box">
                <header><span v-html="ICONS.store"></span><div><strong>Your shop's record</strong><small>What {{ firstName }} told the shop. They can see this and remove it.</small></div></header>
                <div class="cu-form">
                  <label v-for="f in PREF_FIELDS" :key="f.key" class="sp-field">
                    <span class="sp-label">{{ f.label }}</span>
                    <TagInput v-model="prefs[f.key]" :suggestions="canEdit ? SUGGEST[f.key] : []" :disabled="!canEdit" :label="f.label" />
                    <small v-if="!canEdit && !prefs[f.key].length" class="sp-hint">—</small>
                  </label>
                  <div class="sp-field">
                    <span class="sp-label">Favorite products</span>
                    <div class="cu-picks">
                      <button
                        v-for="p in favoriteChoices"
                        :key="p.id"
                        type="button"
                        class="sp-pick"
                        :class="{ on: prefs.favorite_product_ids.includes(p.id) }"
                        :disabled="!canEdit"
                        @click="toggleFavorite(p.id)"
                       :aria-pressed="prefs.favorite_product_ids.includes(p.id)"><span class="cu-star" v-html="prefs.favorite_product_ids.includes(p.id) ? CRM_ICONS.star : CRM_ICONS.starOutline"></span>{{ p.name }}</button>
                      <small v-if="!favoriteChoices.length" class="sp-hint">Pick from products they've bought or hearted, once they have.</small>
                    </div>
                  </div>
                  <div v-if="canEdit" class="cu-form-foot">
                    <small v-if="profile.shop.updated_by" class="sp-hint">Last saved by {{ profile.shop.updated_by }} · {{ shortDate(profile.shop.updated_at) }}</small>
                    <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === 'prefs' || !prefsChanged" @click="savePrefs">{{ busy === "prefs" ? "Saving…" : "Save preferences" }}</button>
                  </div>
                </div>

                <h4 class="st-section-title">Special days the shop knows</h4>
                <p v-if="!profile.shop.dates.length" class="sp-hint">None yet.</p>
                <ul v-else class="cu-dates">
                  <li v-for="d in profile.shop.dates" :key="d.id">
                    <span class="cu-date-icon" :class="d.kind" v-html="kindOf(d.kind).icon"></span>
                    <span class="grow"><strong>{{ d.label }}</strong><small>{{ monthDay(d) }}<template v-if="d.year">, {{ d.year }}</template> · {{ whenText(d.days_away) }}<template v-if="d.note"> · {{ d.note }}</template></small></span>
                    <button v-if="canDelete" type="button" class="sp-icon-btn danger" :aria-label="`Remove ${d.label}`" :disabled="busy === `date${d.id}`" @click="removeDate(d)"><span v-html="ICONS.trash"></span></button>
                  </li>
                </ul>
                <form v-if="canCreate" class="cu-date-form" @submit.prevent="addDate">
                  <label class="sp-field">
                    <span class="sp-label">Kind</span>
                    <select v-model="dateForm.kind" class="sp-select">
                      <option v-for="k in DATE_KINDS" :key="k.value" :value="k.value">{{ k.label }}</option>
                    </select>
                  </label>
                  <label class="sp-field">
                    <span class="sp-label">What is it?</span>
                    <input v-model="dateForm.label" class="sp-input" maxlength="80" :placeholder="dateForm.kind === 'other' ? 'e.g. Shop opening' : `e.g. ${dateForm.kind === 'birthday' ? 'Her mom’s birthday' : 'Wedding anniversary'}`" />
                  </label>
                  <label class="sp-field">
                    <span class="sp-label">Month</span>
                    <select v-model="dateForm.month" class="sp-select">
                      <option value="" disabled>Choose…</option>
                      <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
                    </select>
                  </label>
                  <label class="sp-field">
                    <span class="sp-label">Day</span>
                    <select v-model="dateForm.day" class="sp-select">
                      <option value="" disabled>Choose…</option>
                      <option v-for="d in daysIn(dateForm.month)" :key="d" :value="d">{{ d }}</option>
                    </select>
                  </label>
                  <label class="sp-field">
                    <span class="sp-label">Year <small>(optional)</small></span>
                    <input v-model="dateForm.year" class="sp-input" type="number" min="1900" :max="thisYear" />
                  </label>
                  <button type="submit" class="sp-btn sp-btn-ghost" :disabled="busy === 'date' || !dateForm.month || !dateForm.day"><span v-html="ICONS.plus"></span>Add day</button>
                </form>
              </section>
            </template>

            <!-- Notes -->
            <template v-else-if="tab === 'notes'">
              <p class="cu-private"><span v-html="ICONS.lock"></span>Only your team sees these. The customer never does.</p>
              <form v-if="canCreate" class="cu-note-form" @submit.prevent="addNote">
                <textarea v-model="noteDraft" class="sp-textarea" rows="3" maxlength="2000" placeholder="e.g. Asked for no plastic wrapping. Prefers delivery after 5pm."></textarea>
                <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === 'note' || !noteDraft.trim()">{{ busy === "note" ? "Adding…" : "Add note" }}</button>
              </form>
              <p v-if="!profile.notes.length" class="sp-hint">No notes yet.</p>
              <ul v-else class="cu-notes">
                <li v-for="n in profile.notes" :key="n.id" :class="{ pinned: n.pinned }">
                  <p>{{ n.body }}</p>
                  <footer>
                    <small><span v-if="n.pinned" class="cu-pinned"><span v-html="CRM_ICONS.pin"></span>Pinned · </span>{{ n.author }} · {{ shortDate(n.at, true) }}</small>
                    <span>
                      <button v-if="canEdit" type="button" class="cu-link" @click="pin(n)">{{ n.pinned ? "Unpin" : "Pin" }}</button>
                      <button v-if="canDelete" type="button" class="cu-link danger" @click="removeNote(n)">Delete</button>
                    </span>
                  </footer>
                </li>
              </ul>
            </template>
          </div>
        </aside>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShowMore from "../../../components/common/ShowMore.vue";
import { useShowMoreInto } from "../../../composables/useShowMore";
import HrPage from "../../../components/hr/HrPage.vue";
import TagInput from "../../../components/crm/TagInput.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { money, PAYMENT_LABEL as PAY } from "../../../components/vendor/finance/financeUi";
import { ORDER_STATUS } from "../../../components/vendor/crm/crmUi";
import { CRM_ICONS, DATE_KINDS, MONTHS, SUGGEST, blankDate, datePayload, daysIn, kindOf, monthDay, whenText } from "../../../components/crm/customerPrefs";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const FILTERS = [
  { value: "all", label: "All" },
  { value: "repeat", label: "Repeat" },
  { value: "upcoming", label: "Special days soon" },
  { value: "notes", label: "With notes" },
];
const TABS = [
  { value: "overview", label: "Overview" },
  { value: "orders", label: "Orders" },
  { value: "prefs", label: "Preferences" },
  { value: "notes", label: "Notes" },
];
const PREF_FIELDS = [
  { key: "flowers", label: "Favorite flowers" },
  { key: "colors", label: "Colors" },
  { key: "avoid", label: "Avoid" },
  { key: "occasions", label: "Occasions they shop for" },
];

const { can } = useAssignment();
const canCreate = computed(() => can("crm_customers", "create"));
const canEdit = computed(() => can("crm_customers", "edit"));
const canDelete = computed(() => can("crm_customers", "delete"));
const thisYear = new Date().getFullYear();

const data = ref(null);
const error = ref("");
const loading = ref(false);
const page = ref(1);
const filter = ref("all");
const search = ref("");

const selectedId = ref(null);
const profile = ref(null);
const profileError = ref("");
const tab = ref("overview");
const busy = ref("");
const noteDraft = ref("");
const dateForm = ref(blankDate());
const prefs = reactive({ flowers: [], colors: [], avoid: [], occasions: [], favorite_product_ids: [] });
let savedPrefs = "";

const Avatar = defineComponent({
  props: { name: String, src: String, big: Boolean },
  setup(p) {
    const failed = ref(false);
    return () => {
      const cls = ["cu-avatar", { big: p.big }];
      return p.src && !failed.value
        ? h("img", { class: cls, src: p.src, alt: "", onError: () => (failed.value = true) })
        : h("span", { class: cls, "aria-hidden": "true" }, (p.name || "?").split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join("").toUpperCase());
    };
  },
});

const firstName = computed(() => profile.value?.name.split(/\s+/)[0] || "they");
const allDates = computed(() => {
  const p = profile.value;
  if (!p) return [];
  return [...(p.shared?.dates ?? []).map((d) => ({ ...d, source: "shared" })), ...p.shop.dates.map((d) => ({ ...d, source: "shop" }))].sort((a, b) => a.days_away - b.days_away);
});
const favoriteChoices = computed(() => {
  const p = profile.value;
  if (!p) return [];
  const seen = new Map();
  for (const x of p.shop.favorite_products) seen.set(x.id, x.name);
  for (const x of p.top_products) if (x.product_id) seen.set(x.product_id, x.name);
  for (const x of p.hearted) seen.set(x.id, x.name);
  return [...seen].map(([id, name]) => ({ id, name }));
});
const prefsChanged = computed(() => JSON.stringify(prefs) !== savedPrefs);

// Full text for labels that get cut short on narrow cards
const soonTitle = (d) => `${d.name}: ${d.label}${d.turning ? `, turning ${d.turning}` : ""}, ${whenText(d.days_away)}`;
const notesLabel = (n) => `${n} internal ${n === 1 ? "note" : "notes"}`;

const shortDate = (d, time = false) =>
  d ? new Date(d).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", ...(time ? { hour: "numeric", minute: "2-digit" } : {}) }) : "—";

// 10 customers first, then 10 more, then 20 at a time
const list = useShowMoreInto(data, "rows", async (p) =>
  (await api.get("crm/customers", { params: { page: p, filter: filter.value, search: search.value.trim() || undefined } })).data.data,
);
/** Reloads after a change, keeping as many customers showing */
const refresh = () => list.refresh().catch((e) => (error.value = apiMessage(e, "Couldn't load customers.")));
async function load() {
  error.value = "";
  loading.value = true;
  try {
    await list.load();
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load customers.");
  } finally {
    loading.value = false;
  }
}
let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 300);
}
function setFilter(f) {
  filter.value = f;
  load(1);
}

function useProfile(p) {
  profile.value = p;
  const s = p.shop;
  Object.assign(prefs, { flowers: [...s.flowers], colors: [...s.colors], avoid: [...s.avoid], occasions: [...s.occasions], favorite_product_ids: s.favorite_products.map((x) => x.id) });
  savedPrefs = JSON.stringify(prefs);
}

async function open(id) {
  selectedId.value = id;
  profile.value = null;
  profileError.value = "";
  tab.value = "overview";
  noteDraft.value = "";
  dateForm.value = blankDate();
  try {
    const res = await api.get(`crm/customers/${id}/profile`);
    if (selectedId.value === id) useProfile(res.data.data);
  } catch (e) {
    if (selectedId.value === id) profileError.value = apiMessage(e, "Couldn't load this customer.");
  }
}
function close() {
  selectedId.value = null;
}

/** Runs a change and takes the fresh profile it returns */
async function change(key, request, done) {
  busy.value = key;
  try {
    const res = await request();
    useProfile(res.data.data);
    if (done) toast.success(done);
    return true;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't save that."));
    return false;
  } finally {
    busy.value = "";
  }
}

const toggleFavorite = (id) => {
  const list = prefs.favorite_product_ids;
  prefs.favorite_product_ids = list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
};
const savePrefs = () => change("prefs", () => api.put(`crm/customers/${selectedId.value}/preferences`, { ...prefs }), "Preferences saved.");

async function addDate() {
  const ok = await change("date", () => api.post(`crm/customers/${selectedId.value}/dates`, datePayload(dateForm.value)), "Day added.");
  if (ok) {
    dateForm.value = blankDate();
    refresh();
  }
}
const removeDate = (d) => change(`date${d.id}`, () => api.delete(`crm/customers/${selectedId.value}/dates/${d.id}`), "Day removed.");

async function addNote() {
  const ok = await change("note", () => api.post(`crm/customers/${selectedId.value}/notes`, { body: noteDraft.value.trim() }), "Note added.");
  if (ok) {
    noteDraft.value = "";
    refresh();
  }
}
const pin = (n) => change("pin", () => api.patch(`crm/customers/${selectedId.value}/notes/${n.id}`, { pinned: !n.pinned }));
function removeNote(n) {
  if (!confirm("Delete this note?")) return;
  change("note", () => api.delete(`crm/customers/${selectedId.value}/notes/${n.id}`), "Note deleted.").then((ok) => ok && refresh());
}

onMounted(() => load(1));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.grow strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.grow small {
  overflow: hidden;
  font-size: 12px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Coming up */
.cu-soon {
  margin-bottom: 16px;
}
.cu-soon-row {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 10px;
}
.cu-soon-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-subtle);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s;
}
.cu-soon-card:hover {
  border-color: var(--bc-accent);
}
/* Kind-of-day icons on a tinted tile, so the kind reads at a glance */
.cu-soon-icon,
.cu-date-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: var(--v-purple-soft);
  color: var(--v-purple-text);
}
.cu-soon-icon.birthday,
.cu-date-icon.birthday {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.cu-soon-icon.anniversary,
.cu-date-icon.anniversary {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.cu-ichip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.cu-ichip :deep(svg) {
  width: 13px;
  height: 13px;
}
.cu-chip.heart :deep(svg) {
  color: #e0475a;
}
.cu-star {
  display: inline-flex;
  color: #f5b301;
}
.cu-star :deep(svg) {
  width: 14px;
  height: 14px;
}
.cu-pinned {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  vertical-align: -1px;
}
.cu-pinned :deep(svg) {
  width: 12px;
  height: 12px;
}

/* List */
.cu-list {
  min-width: 0;
}
.cu-list-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.cu-list-head .sp-search {
  flex: 0 1 300px;
}
.sp-seg .count {
  margin-left: 6px;
  padding: 0 7px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  font-size: 11px;
}
.cu-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.cu-rows.dim {
  opacity: 0.55;
}
.cu-rows li + li {
  border-top: 1px solid var(--v-border);
}
.cu-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px 8px;
  border: 0;
  border-radius: 12px;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}
li > .cu-row:hover,
.cu-row.on {
  background: var(--v-subtle);
}
div.cu-row,
li.cu-row {
  cursor: default;
}
.cu-tags {
  display: flex;
  gap: 6px;
}
.cu-tags .sp-chip :deep(svg) {
  width: 12px;
  height: 12px;
  margin-right: 3px;
  vertical-align: -2px;
}
.cu-money {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 110px;
}
.cu-money strong {
  font-size: 13.5px;
  color: var(--v-text);
}
.cu-money small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.cu-go {
  display: inline-flex;
  color: var(--v-faint);
}
.cu-go :deep(svg) {
  width: 16px;
  height: 16px;
}
:deep(.cu-avatar) {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 12px;
  object-fit: cover;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 13px;
  font-weight: 600;
}
:deep(.cu-avatar.big) {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  font-size: 17px;
}

.fx-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.fx-pager div {
  display: flex;
  gap: 6px;
}

/* Drawer */
.cu-drawer {
  width: min(600px, 100vw);
}
.cu-who {
  display: flex;
  align-items: center;
  gap: 12px;
}
.cu-tabs {
  margin-bottom: 16px;
}
.cu-sk {
  display: grid;
  gap: 12px;
}
.cu-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.cu-stats div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.cu-stats strong {
  font-size: 16px;
  color: var(--v-text);
}
.cu-stats span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.cu-sub {
  display: block;
  font-size: 12px;
  color: var(--v-muted);
}
.cu-dates,
.cu-top,
.cu-orders,
.cu-notes {
  list-style: none;
  margin: 0;
  padding: 0;
}
.cu-dates li,
.cu-top li,
.cu-orders li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid var(--v-border);
}
.cu-dates li:first-child,
.cu-top li:first-child,
.cu-orders li:first-child {
  border-top: 0;
}
.cu-top b,
.cu-order-total {
  font-size: 13px;
  color: var(--v-text);
  white-space: nowrap;
}
.cu-order-total {
  min-width: 90px;
  text-align: right;
}

/* Preferences */
.cu-box {
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
}
.cu-box.shared {
  background: var(--v-subtle);
}
.cu-box > header {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
}
.cu-box > header > span {
  display: grid;
  place-items: center;
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.cu-box > header :deep(svg) {
  width: 16px;
  height: 16px;
}
.cu-box > header strong {
  display: block;
  font-size: 14px;
  color: var(--v-text);
}
.cu-box > header small {
  font-size: 12px;
  color: var(--v-muted);
}
.cu-pref-list {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr);
  gap: 8px 12px;
  margin: 0;
}
.cu-pref-list dt {
  font-size: 12px;
  color: var(--v-muted);
}
.cu-pref-list dd {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin: 0;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.cu-chip {
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--v-surface);
  border: 1px solid var(--v-border);
  font-size: 12px;
  color: var(--v-text);
}
.cu-chip.warn {
  border-color: color-mix(in srgb, var(--v-red-text) 40%, transparent);
  color: var(--v-red-text);
}
.cu-quote {
  margin: 12px 0 0;
  font-size: 13px;
  font-style: italic;
  color: var(--v-text-2);
}
.cu-form {
  display: grid;
  gap: 12px;
}
.cu-picks {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.cu-form-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.cu-date-form {
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: 10px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px dashed var(--v-border-strong);
}
.cu-date-form .sp-label small {
  font-weight: 400;
  color: var(--v-faint);
}
.cu-date-form > :last-child {
  grid-column: 1 / -1;
  justify-self: start;
}

/* Notes */
.cu-private {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-muted);
}
.cu-private :deep(svg) {
  width: 15px;
  height: 15px;
}
.cu-note-form {
  display: grid;
  gap: 8px;
  justify-items: end;
  margin-bottom: 16px;
}
.cu-note-form textarea {
  width: 100%;
}
.cu-notes {
  display: grid;
  gap: 10px;
}
.cu-notes li {
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.cu-notes li.pinned {
  border-color: var(--bc-accent-border, var(--bc-accent));
  background: var(--bc-accent-soft);
}
.cu-notes p {
  margin: 0 0 8px;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text);
  white-space: pre-wrap;
}
.cu-notes footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: -10px;
}
.cu-notes small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.cu-link {
  min-height: 44px;
  padding: 0 6px;
  margin-left: 6px;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.cu-link.danger {
  color: var(--v-red-text);
}

@media (max-width: 720px) {
  .hide-sm {
    display: none;
  }
  .cu-money {
    min-width: 0;
  }
  .cu-list-head .sp-search {
    flex-basis: 100%;
  }
  .cu-pref-list {
    grid-template-columns: minmax(0, 1fr);
  }
  .cu-date-form {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
