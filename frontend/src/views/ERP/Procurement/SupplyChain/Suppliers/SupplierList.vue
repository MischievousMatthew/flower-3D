<template>
  <HrPage title="Suppliers" subtitle="The farms, wholesalers, packaging and supply vendors the shop buys from: who to call, what they sell, when they deliver and how they've done.">
    <template #actions>
      <router-link v-if="can('suppliers', 'create')" :to="{ name: 'SupplierCreate' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>Add supplier</router-link>
    </template>

    <!-- Kinds of supplier, with counts; also the type filter -->
    <section class="sl-types" aria-label="Kinds of supplier">
      <template v-if="!counts">
        <div v-for="n in 5" :key="n" class="sp-card sl-type"><div class="bc-skeleton sl-type-ic"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button v-for="t in typeTiles" :key="t.value" type="button" class="sp-card sl-type" :class="{ on: type === t.value }" :aria-pressed="type === t.value" @click="setType(t.value)">
          <span class="sl-type-ic" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
          <span class="grow">
            <span class="sl-type-label">{{ t.label }}</span>
            <strong>{{ t.count }}</strong>
          </span>
        </button>
      </template>
    </section>

    <div class="sp-card sl-bar">
      <div class="sp-seg" role="group" aria-label="Status">
        <button v-for="s in STATUS_TABS" :key="s.value" type="button" :class="{ on: status === s.value }" :aria-pressed="status === s.value" @click="setStatus(s.value)">
          {{ s.label }}<span v-if="counts" class="count">{{ statusCount(s.value) }}</span>
        </button>
      </div>
      <label class="sp-search sl-search">
        <span v-html="ICONS.search"></span>
        <input v-model="search" class="sp-input" type="search" placeholder="Search name, contact person or email" aria-label="Search suppliers" @input="debouncedLoad" />
      </label>
    </div>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Suppliers -->
    <div v-if="suppliers === null" class="sl-grid">
      <div v-for="n in 6" :key="n" class="sp-card sl-card">
        <div class="sl-top"><div class="bc-skeleton sl-logo"></div><div class="grow"><div class="bc-skeleton sp-sk-line w70"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
        <div class="bc-skeleton sp-sk-line w50"></div>
        <div class="bc-skeleton sl-sk-facts"></div>
      </div>
    </div>
    <div v-else-if="!suppliers.length" class="sp-card sp-empty">
      <span class="sp-empty-icon" v-html="ICONS.store"></span>
      <h3>{{ filtered ? "No suppliers match" : "No suppliers yet" }}</h3>
      <p>{{ filtered ? "Try another kind, status or search." : "Add the farms, wholesalers and packaging vendors you buy from, so ordering and comparing prices is one click away." }}</p>
      <button v-if="filtered" type="button" class="sp-btn sp-btn-ghost" @click="clearFilters">Clear filters</button>
      <router-link v-else-if="can('suppliers', 'create')" :to="{ name: 'SupplierCreate' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>Add supplier</router-link>
    </div>
    <template v-else>
      <ul class="sl-grid" :class="{ dim: pager.loading.value }">
        <li v-for="s in suppliers" :key="s.id" class="sp-card sl-card" :class="{ muted: s.status !== 'active' }">
          <div class="sl-top">
            <Logo :name="s.company_name" :src="s.logo_url" />
            <div class="grow">
              <router-link :to="{ name: 'SupplierProfile', params: { id: s.id } }" class="sl-name">{{ s.company_name }}</router-link>
              <span class="sl-chips">
                <span v-if="typeOf(s.supplier_type)" class="sp-chip" :class="typeOf(s.supplier_type).tone">{{ typeOf(s.supplier_type).label }}</span>
                <span v-else class="sp-chip slate">Kind not set</span>
                <span v-if="s.status !== 'active'" class="sp-chip" :class="STATUS[s.status]?.tone">{{ STATUS[s.status]?.label ?? s.status }}</span>
              </span>
            </div>
            <div class="sl-menu-wrap">
              <button type="button" class="sp-icon-btn" :aria-label="`More for ${s.company_name}`" :aria-expanded="menuFor === s.id" @click.stop="menuFor = menuFor === s.id ? null : s.id"><span v-html="ICONS.more"></span></button>
              <div v-if="menuFor === s.id" class="sl-menu" role="menu" @click.stop>
                <router-link role="menuitem" :to="{ name: 'SupplierProfile', params: { id: s.id } }"><span v-html="ICONS.eye"></span>View profile</router-link>
                <router-link v-if="canEdit('suppliers')" role="menuitem" :to="{ name: 'SupplierEdit', params: { id: s.id } }"><span v-html="ICONS.edit"></span>Edit details</router-link>
                <template v-if="canEdit('suppliers')">
                  <button v-if="s.status !== 'active'" type="button" role="menuitem" @click="setSupplierStatus(s, 'active')"><span v-html="ICONS.check"></span>Mark active</button>
                  <button v-if="s.status === 'active'" type="button" role="menuitem" @click="setSupplierStatus(s, 'inactive')"><span v-html="ICONS.pause"></span>Mark inactive</button>
                  <button v-if="s.status !== 'blacklisted'" type="button" role="menuitem" class="danger" @click="ask('blacklist', s)"><span v-html="ICONS.lock"></span>Blacklist</button>
                </template>
                <button v-if="can('suppliers', 'delete')" type="button" role="menuitem" class="danger" @click="ask('delete', s)"><span v-html="ICONS.trash"></span>Delete</button>
              </div>
            </div>
          </div>

          <div class="sl-contact">
            <span class="sl-person"><span v-html="ICONS.user"></span>{{ s.contact_person }}</span>
            <a v-if="s.phone" :href="`tel:${s.phone}`" class="sl-link"><span v-html="ICONS.phone"></span>{{ s.phone }}</a>
            <a v-if="s.email" :href="`mailto:${s.email}`" class="sl-link"><span v-html="ICONS.send"></span>{{ s.email }}</a>
          </div>

          <dl class="sl-facts">
            <div><dt>Lead time</dt><dd>{{ leadText(s.lead_time_days) || "—" }}</dd></div>
            <div><dt>Delivers</dt><dd>{{ daysText(s.delivery_days) || "—" }}</dd></div>
            <div>
              <dt>Rating</dt>
              <dd v-if="s.rating !== null && s.rating !== undefined" class="sl-rating" :aria-label="`${s.rating} out of 5 from ${s.ratings_count} ratings`"><span v-html="ICONS.star"></span>{{ Number(s.rating).toFixed(1) }}<small>({{ s.ratings_count }})</small></dd>
              <dd v-else>—</dd>
            </div>
            <div><dt>Orders</dt><dd>{{ s.purchase_orders_count ?? 0 }}</dd></div>
          </dl>

          <div class="sl-foot">
            <span class="sl-meta">{{ s.products_count ? `${s.products_count} ${s.products_count === 1 ? "item" : "items"} in catalog` : "No catalog yet" }} · {{ s.last_order_at ? `last order ${shortDate(s.last_order_at)}` : "no orders yet" }}</span>
            <router-link :to="{ name: 'SupplierProfile', params: { id: s.id } }" class="sp-btn sp-btn-ghost sp-btn-sm">View profile</router-link>
          </div>
        </li>
      </ul>
      <ShowMore tone="vendor" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="suppliers.length" :total="pager.total.value" @more="pager.showMore()" />
    </template>

    <!-- Blacklist / delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="confirm" class="sp-backdrop" @click="confirm = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="confirm" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="sl-confirm-title" aria-describedby="sl-confirm-text">
            <div class="sp-modal-head">
              <div>
                <h3 id="sl-confirm-title">{{ confirm.kind === "delete" ? `Delete ${confirm.supplier.company_name}?` : `Blacklist ${confirm.supplier.company_name}?` }}</h3>
                <p id="sl-confirm-text">{{ confirm.kind === "delete"
                  ? "Its details, contacts, catalog and ratings are removed for good. A supplier with purchase orders can't be deleted; blacklist it instead to keep the history."
                  : "It stays in your records with its orders and ratings, marked so no one orders from it again. You can mark it active later." }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="confirm = null"><span v-html="ICONS.close"></span></button>
            </div>
            <p v-if="confirm.error" class="sp-error">{{ confirm.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="confirm = null">Keep it</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="confirm.busy" @click="runConfirm">{{ confirm.busy ? "Working…" : confirm.kind === "delete" ? "Delete supplier" : "Blacklist" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from "vue";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ShowMore from "../../../../../components/common/ShowMore.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { SUPPLIER_TYPES, STATUS, typeOf, daysText, leadText, initials } from "../../../../../components/procurement/supplierUi";
import { useShowMorePaged } from "../../../../../composables/useShowMore";
import { useAssignment } from "../../../../../composables/useAssignment";
import { supplierService } from "../../../../../services/supplierService";
import { toast } from "@/utils/snackbar";

const STATUS_TABS = [
  { value: "", label: "All" },
  { value: "active", label: "Active" },
  { value: "inactive", label: "Inactive" },
  { value: "blacklisted", label: "Blacklisted" },
];

const { can, canEdit } = useAssignment();
const suppliers = ref(null);
const counts = ref(null);
const loadError = ref("");
const type = ref("");
const status = ref("");
const search = ref("");
const menuFor = ref(null);
const confirm = ref(null);

const Logo = defineComponent({
  props: { name: String, src: String },
  setup(p) {
    const failed = ref(false);
    return () =>
      p.src && !failed.value
        ? h("img", { class: "sl-logo", src: p.src, alt: "", onError: () => (failed.value = true) })
        : h("span", { class: "sl-logo", "aria-hidden": "true" }, initials(p.name));
  },
});

const filtered = computed(() => !!(type.value || status.value || search.value.trim()));
const typeTiles = computed(() => [
  { value: "", label: "All suppliers", icon: "store", tone: "slate", count: counts.value.total },
  ...SUPPLIER_TYPES.map((t) => ({ value: t.value, label: t.plural, icon: t.icon, tone: t.tone, count: counts.value.by_type?.[t.value] ?? 0 })),
]);
const statusCount = (s) => (s ? counts.value.by_status?.[s] ?? 0 : counts.value.total);

async function fetchPage(page) {
  const res = await supplierService.list({
    page,
    per_page: 20,
    type: type.value || undefined,
    status: status.value || undefined,
    search: search.value.trim() || undefined,
  });
  const body = res.data ?? {};
  counts.value = body.counts ?? counts.value;
  return { items: (body.data ?? []).filter((s) => s?.id), lastPage: body.last_page ?? 1, total: body.total };
}
const pager = useShowMorePaged(fetchPage);
watch(() => pager.visible.value, (rows) => {
  if (suppliers.value !== null) suppliers.value = rows;
});

async function load() {
  loadError.value = "";
  try {
    await pager.load();
    if (pager.failure.value) throw pager.failure.value;
    suppliers.value = pager.visible.value;
  } catch (e) {
    suppliers.value = suppliers.value ?? [];
    counts.value = counts.value ?? { total: 0, by_status: {}, by_type: {} };
    loadError.value = apiMessage(e, "Couldn't load the suppliers.");
  }
}

let timer = null;
function debouncedLoad() {
  clearTimeout(timer);
  timer = setTimeout(load, 350);
}
function setType(v) {
  if (type.value === v) return;
  type.value = v;
  load();
}
function setStatus(v) {
  if (status.value === v) return;
  status.value = v;
  load();
}
function clearFilters() {
  type.value = "";
  status.value = "";
  search.value = "";
  load();
}

const shortDate = (d) => new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });

async function setSupplierStatus(s, next) {
  menuFor.value = null;
  const call = { active: "activate", inactive: "deactivate", blacklisted: "blacklist" }[next];
  try {
    await supplierService[call](s.id);
    const before = s.status;
    s.status = next;
    if (counts.value?.by_status) {
      counts.value.by_status[before] = Math.max(0, (counts.value.by_status[before] ?? 1) - 1);
      counts.value.by_status[next] = (counts.value.by_status[next] ?? 0) + 1;
    }
    toast.success(next === "active" ? `${s.company_name} is active again.` : next === "inactive" ? `${s.company_name} is marked inactive.` : `${s.company_name} is blacklisted.`);
    return true;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't change the supplier's status."));
    return false;
  }
}

function ask(kind, supplier) {
  menuFor.value = null;
  confirm.value = { kind, supplier, busy: false, error: "" };
}
async function runConfirm() {
  const c = confirm.value;
  c.busy = true;
  c.error = "";
  if (c.kind === "blacklist") {
    if (await setSupplierStatus(c.supplier, "blacklisted")) confirm.value = null;
    else c.busy = false;
    return;
  }
  try {
    await supplierService.remove(c.supplier.id);
    toast.success(`${c.supplier.company_name} was deleted.`);
    confirm.value = null;
    load();
  } catch (e) {
    c.busy = false;
    c.error = apiMessage(e, "Couldn't delete the supplier.");
  }
}

const closeMenu = () => (menuFor.value = null);
const onKey = (e) => {
  if (e.key === "Escape") menuFor.value = null;
};
onMounted(() => {
  load();
  document.addEventListener("click", closeMenu);
  document.addEventListener("keydown", onKey);
});
onBeforeUnmount(() => {
  clearTimeout(timer);
  document.removeEventListener("click", closeMenu);
  document.removeEventListener("keydown", onKey);
});
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* Kinds */
.sl-types {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}
.sl-type {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.sl-type:hover {
  border-color: var(--v-border-strong);
}
.sl-type.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.sl-type:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.sl-type-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 11px;
}
.sl-type-ic :deep(svg) {
  width: 18px;
  height: 18px;
}
.sl-type-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.sl-type strong {
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
}

/* Filter bar */
.sl-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  margin-bottom: 16px;
}
.sl-search {
  flex: 1;
  max-width: 360px;
  min-width: 220px;
}

/* Cards */
.sl-grid {
  list-style: none;
  margin: 0 0 6px;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 14px;
  transition: opacity 0.2s;
}
.sl-grid.dim {
  opacity: 0.55;
}
.sl-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.sl-card:hover {
  border-color: var(--v-border-strong);
  box-shadow: 0 10px 24px -16px rgba(15, 23, 42, 0.35);
}
.sl-card.muted .sl-logo {
  filter: grayscale(0.7);
  opacity: 0.75;
}
.sl-top {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
:deep(.sl-logo) {
  flex: none;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 13px;
  object-fit: cover;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 14px;
  font-weight: 600;
}
.sl-name {
  overflow: hidden;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sl-name:hover {
  color: var(--bc-accent-deep);
  text-decoration: underline;
}
.sl-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}
.sl-menu-wrap {
  position: relative;
  flex: none;
}
.sl-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 20;
  display: flex;
  flex-direction: column;
  min-width: 190px;
  padding: 6px;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-raised);
  box-shadow: 0 14px 30px -12px rgba(15, 23, 42, 0.35);
}
.sl-menu a,
.sl-menu button {
  display: flex;
  align-items: center;
  gap: 9px;
  min-height: 38px;
  padding: 0 10px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-text);
  font: inherit;
  font-size: 13px;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}
.sl-menu a:hover,
.sl-menu button:hover,
.sl-menu a:focus-visible,
.sl-menu button:focus-visible {
  background: var(--v-subtle);
  outline: none;
}
.sl-menu .danger {
  color: var(--v-red-text);
}
.sl-menu :deep(svg) {
  width: 15px;
  height: 15px;
}
.sl-contact {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 12.5px;
}
.sl-person,
.sl-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  overflow: hidden;
  color: var(--v-text-2);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sl-link {
  width: fit-content;
  max-width: 100%;
  text-decoration: none;
}
.sl-link:hover {
  color: var(--bc-accent-deep);
  text-decoration: underline;
}
.sl-person :deep(svg),
.sl-link :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
  color: var(--v-faint);
}
.sl-facts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin: 0;
  padding: 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.sl-facts dt {
  font-size: 11px;
  color: var(--v-muted);
}
.sl-facts dd {
  margin: 2px 0 0;
  overflow: hidden;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sl-rating {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.sl-rating :deep(svg) {
  width: 13px;
  height: 13px;
  color: #f5b301;
  fill: #f5b301;
}
.sl-rating small {
  font-size: 11px;
  font-weight: 500;
  color: var(--v-faint);
}
.sl-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: auto;
}
.sl-meta {
  font-size: 11.5px;
  color: var(--v-faint);
}
.sl-sk-facts {
  height: 54px;
  border-radius: 12px;
}

@media (max-width: 1100px) {
  .sl-types {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 640px) {
  .sl-types {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sl-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .sl-search {
    max-width: none;
  }
  .sl-facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .sl-foot {
    flex-wrap: wrap;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sl-type,
  .sl-card,
  .sl-grid {
    transition: none;
  }
}
</style>
