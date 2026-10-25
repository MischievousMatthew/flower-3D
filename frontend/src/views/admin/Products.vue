<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
          </button>
          <div>
            <h1 class="page-title">{{ view === "lists" ? "Category Management" : "Product Management" }}</h1>
            <p class="page-subtitle">
              {{
                view === "lists"
                  ? "The categories, flower types and occasions vendors pick from and shoppers filter by."
                  : "Every listing on BloomCraft. Vendors manage their own details, prices and stock."
              }}
            </p>
          </div>
        </div>
        <div class="header-right">
          <button type="button" class="ghost-btn" :disabled="loading" @click="refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            <span>{{ loading ? "Refreshing" : "Refresh" }}</span>
          </button>
        </div>
      </header>

      <div class="page-body">
        <!-- ═══ Listings ═══ -->
        <template v-if="view === 'listings'">
          <section class="tab-strip" role="tablist" aria-label="Listing status">
            <button
              v-for="t in TABS"
              :key="t.key"
              type="button"
              role="tab"
              class="tab-chip"
              :class="[`tone-${t.tone}`, { active: filters.tab === t.key }]"
              :aria-selected="filters.tab === t.key"
              @click="filters.tab = t.key"
            >
              <span class="tab-dot"></span>
              {{ t.label }}
              <b>
                <template v-if="summary">{{ summary[t.key] }}</template>
                <span v-else class="sk sk-inline"></span>
              </b>
            </button>
          </section>

          <section class="panel">
            <div class="toolbar">
              <div class="search-wrap">
                <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                <input v-model="filters.search" type="search" class="search-input" placeholder="Product, SKU or store" aria-label="Search products" />
              </div>
              <div class="toolbar-filters">
                <select v-model="filters.category" class="select" aria-label="Category">
                  <option value="">All categories</option>
                  <option v-for="c in taxonomies.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <select v-model="filters.flower_type" class="select" aria-label="Flower type">
                  <option value="">All flower types</option>
                  <option v-for="t in taxonomies.flower_types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <select v-model="filters.sort" class="select" aria-label="Sort">
                  <option value="newest">Newest first</option>
                  <option value="oldest">Oldest first</option>
                  <option value="name">Name A–Z</option>
                  <option value="price_high">Highest price</option>
                  <option value="stock_low">Lowest stock</option>
                  <option value="reports">Most reported</option>
                </select>
                <button v-if="hasFilters" type="button" class="link-btn" @click="clearFilters">Clear</button>
              </div>
            </div>

            <p v-if="tabNote" class="tab-note">{{ tabNote }}</p>

            <!-- Grid -->
            <div v-if="loading && !products.length" class="product-grid">
              <div v-for="n in 8" :key="n" class="card">
                <div class="sk card-img"></div>
                <div class="card-body"><div class="sk sk-line" style="width: 70%"></div><div class="sk sk-line" style="width: 45%"></div></div>
              </div>
            </div>
            <p v-else-if="loadError && !products.length" class="empty-msg">
              The listings couldn't be loaded. <button type="button" class="link-btn" @click="productList.refresh()">Try again</button>
            </p>
            <p v-else-if="!products.length" class="empty-msg">{{ hasFilters ? "No listings match these filters." : EMPTY[filters.tab] ?? "No listings yet." }}</p>
            <div v-else class="product-grid" :class="{ dim: loading }">
              <article
                v-for="p in productList.rows.value"
                :key="p.id"
                class="card"
                :class="{ selected: drawer.id === p.id }"
                tabindex="0"
                @click="openProduct(p.id)"
                @keydown.enter="openProduct(p.id)"
              >
                <div class="card-img">
                  <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy" />
                  <span v-else class="img-fallback">🌸</span>
                  <span class="pill vis" :class="`vis-${p.visibility}`">{{ VISIBILITY[p.visibility] }}</span>
                  <span v-if="p.open_reports" class="report-tag" :title="`${p.open_reports} open report(s)`">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" /><line x1="4" y1="22" x2="4" y2="15" /></svg>
                    {{ p.open_reports }}
                  </span>
                  <span v-if="p.moderation_status === 'pending'" class="new-tag">New</span>
                </div>
                <div class="card-body">
                  <span class="card-name">{{ p.name }}</span>
                  <div class="card-vendor">
                    <VendorAvatar :vendor="p.vendor" :size="18" />
                    <span>{{ p.vendor.store_name }}</span>
                  </div>
                  <div class="card-row">
                    <span class="price">
                      {{ money(p.sale_price ?? p.price) }}
                      <s v-if="p.sale_price">{{ money(p.price) }}</s>
                    </span>
                    <span class="stock" :class="`stock-${p.stock_state}`">{{ p.stock_state === "out" ? "Out of stock" : `${p.stock} in stock` }}</span>
                  </div>
                  <div class="card-row sub">
                    <span>{{ p.category_label }}</span>
                    <span>{{ p.sold }} sold</span>
                  </div>
                </div>
                <div v-if="quickActions(p).length" class="card-actions" @click.stop>
                  <button
                    v-for="a in quickActions(p)"
                    :key="a"
                    type="button"
                    class="mini-btn"
                    :class="ACTION_TONE[a]"
                    @click="askModerate(a, p)"
                  >
                    {{ ACTION_LABEL[a] }}
                  </button>
                </div>
              </article>
            </div>

            <ShowMore v-if="products.length" tone="admin" :has-more="productList.hasMore.value" :loading="productList.loadingMore.value" :shown="productList.rows.value.length" :total="productList.total.value" @more="productList.showMore()" />
          </section>
        </template>

        <!-- ═══ Categories & tags ═══ -->
        <template v-else>
          <p class="lists-intro">
            These are the choices vendors see when they add or edit a product. Switching one off hides it from the form;
            products that already use it keep it. Only unused entries can be deleted.
          </p>
          <section class="lists-grid">
            <div v-for="list in LISTS" :key="list.type" class="panel list-panel">
              <div class="list-head">
                <div>
                  <h2 class="panel-title">{{ list.title }}</h2>
                  <p class="panel-sub">{{ list.hint }}</p>
                </div>
                <span class="count-badge">{{ (lists.items[list.type] ?? []).filter((i) => i.is_active).length }} offered</span>
              </div>

              <template v-if="lists.loading && !lists.loaded">
                <div v-for="n in 5" :key="n" class="sk sk-line" style="height: 34px; margin-bottom: 8px"></div>
              </template>
              <ul v-else class="tax-list">
                <li v-for="(item, index) in lists.items[list.type] ?? []" :key="item.id" :class="{ off: !item.is_active }">
                  <div class="reorder">
                    <button type="button" :disabled="index === 0" aria-label="Move up" @click="move(list.type, index, -1)">▲</button>
                    <button type="button" :disabled="index === (lists.items[list.type] ?? []).length - 1" aria-label="Move down" @click="move(list.type, index, 1)">▼</button>
                  </div>
                  <div class="tax-main">
                    <input
                      class="tax-label"
                      :value="item.label"
                      :aria-label="`${list.title} name`"
                      maxlength="120"
                      @change="renameItem(item, $event.target.value)"
                      @keydown.enter="$event.target.blur()"
                    />
                    <span class="tax-meta">
                      <code>{{ item.value }}</code> · {{ plural(item.products, "product") }}
                    </span>
                  </div>
                  <label class="switch" :title="item.is_active ? 'Offered to vendors' : 'Switched off'">
                    <input type="checkbox" :checked="item.is_active" @change="toggleItem(item, $event.target.checked)" />
                    <span></span>
                  </label>
                  <button
                    type="button"
                    class="icon-x"
                    :disabled="item.products > 0"
                    :title="item.products > 0 ? 'In use. Switch it off instead.' : 'Delete'"
                    aria-label="Delete"
                    @click="deleteItem(item)"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                  </button>
                </li>
              </ul>

              <div v-if="(lists.unlisted[list.type] ?? []).length" class="unlisted">
                <span class="sub">Used by products but not on the list:</span>
                <button v-for="u in lists.unlisted[list.type]" :key="u.value" type="button" class="unlisted-chip" @click="addItem(list.type, u.value, u.value)">
                  + {{ u.value }} <span>({{ u.products }})</span>
                </button>
              </div>

              <form class="add-row" @submit.prevent="addItem(list.type, drafts[list.type])">
                <input v-model="drafts[list.type]" type="text" class="select" :placeholder="`Add a ${list.single}`" maxlength="120" />
                <button type="submit" class="primary-btn" :disabled="!drafts[list.type]?.trim() || lists.saving">Add</button>
              </form>
            </div>
          </section>
        </template>
      </div>
    </main>

    <!-- ═══ Product drawer ═══ -->
    <Transition name="fade"><div v-if="drawer.id" class="drawer-backdrop" @click="closeProduct"></div></Transition>
    <Transition name="slide">
      <aside v-if="drawer.id" ref="drawerEl" class="drawer" role="dialog" aria-modal="true" tabindex="-1">
        <header class="drawer-head">
          <div class="drawer-title">
            <template v-if="detail">
              <h2>{{ detail.name }}</h2>
              <div class="drawer-meta">
                <span class="pill" :class="`vis-${detail.visibility}`">{{ VISIBILITY[detail.visibility] }}</span>
                <span class="sub">SKU {{ detail.sku || "—" }} · added {{ formatDate(detail.created_at) }}</span>
              </div>
            </template>
            <template v-else>
              <div class="sk sk-line" style="width: 220px; height: 18px"></div>
              <div class="sk sk-line" style="width: 150px"></div>
            </template>
          </div>
          <button type="button" class="icon-btn" aria-label="Close" @click="closeProduct"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
        </header>

        <div class="drawer-body">
          <p v-if="drawer.error" class="empty-msg">{{ drawer.error }}</p>
          <template v-else-if="!detail">
            <div class="sk" style="height: 220px; border-radius: 12px"></div>
            <div v-for="n in 3" :key="n" class="box"><div class="sk sk-line" style="width: 40%"></div><div class="sk sk-line"></div></div>
          </template>
          <template v-else>
            <!-- Gallery -->
            <div class="gallery">
              <div class="gallery-main">
                <img v-if="activeImage" :src="activeImage" :alt="detail.name" />
                <span v-else class="img-fallback big">🌸</span>
              </div>
              <div v-if="detail.images.length > 1" class="thumbs">
                <button v-for="(img, i) in detail.images" :key="i" type="button" :class="{ active: drawer.image === i }" @click="drawer.image = i">
                  <img :src="img" :alt="`Photo ${i + 1}`" />
                </button>
              </div>
            </div>

            <!-- Why it is / isn't visible -->
            <div class="box status-box" :class="`vis-${detail.visibility}`">
              <strong>{{ VISIBILITY_HELP[detail.visibility].title }}</strong>
              <span>{{ VISIBILITY_HELP[detail.visibility].body }}</span>
              <span v-if="detail.moderation_reason" class="reason">Reason given: “{{ detail.moderation_reason }}”</span>
            </div>

            <div class="box">
              <div class="vendor-row">
                <VendorAvatar :vendor="detail.vendor" :size="36" />
                <div>
                  <span class="strong">{{ detail.vendor.store_name }}</span>
                  <span class="sub">{{ detail.vendor.email }}</span>
                </div>
                <router-link :to="{ path: '/admin/vendor-requests', query: { tab: 'active', vendor: detail.vendor.id } }" class="link-btn">View vendor →</router-link>
              </div>
            </div>

            <div class="box">
              <span class="box-label">Listing</span>
              <dl class="facts">
                <dt>Price</dt>
                <dd>
                  <span class="strong">{{ money(detail.sale_price ?? detail.price) }}</span>
                  <s v-if="detail.sale_price" class="sub-inline">{{ money(detail.price) }}</s>
                </dd>
                <dt>Stock</dt>
                <dd><span :class="`stock-${detail.stock_state}`">{{ detail.stock }}</span> <span class="sub-inline">(vendor's minimum {{ detail.min_stock_level }})</span></dd>
                <dt>Sold</dt><dd>{{ detail.sold }}</dd>
                <dt>Category</dt><dd>{{ detail.category_label }}</dd>
                <dt>Flower type</dt><dd>{{ detail.flower_type_label }}</dd>
                <dt>Sold as</dt><dd>{{ SELLING_TYPES[detail.selling_type] ?? humanize(detail.selling_type) }}</dd>
                <dt>Colour</dt><dd>{{ humanize(detail.color) }}</dd>
                <dt>Season</dt><dd>{{ humanize(detail.season) }}</dd>
                <dt>Occasions</dt><dd>{{ detail.occasions.length ? detail.occasions.join(", ") : "—" }}</dd>
                <dt>Preparation</dt><dd>{{ detail.preparation_days ? plural(detail.preparation_days, "day") : "—" }}</dd>
                <dt>Handling</dt><dd>{{ [detail.is_fragile && "Fragile", detail.requires_refrigeration && "Keep refrigerated"].filter(Boolean).join(", ") || "—" }}</dd>
                <dt>3D model</dt><dd>{{ detail.has_3d_model ? "Yes" : "No" }}</dd>
              </dl>
              <p v-if="detail.description" class="text-block">{{ detail.description }}</p>
            </div>

            <div class="box">
              <div class="box-head">
                <span class="box-label">Reports</span>
                <router-link v-if="detail.reports.length" :to="{ path: '/admin/reviews', query: { tab: 'reports' } }" class="link-btn">Review in Review Management →</router-link>
              </div>
              <p v-if="!detail.reports.length" class="sub">No customer has reported this listing.</p>
              <ul v-else class="mini-list">
                <li v-for="r in detail.reports" :key="r.id">
                  <span class="pill" :class="`rp-${r.status}`">{{ REPORT_STATUS[r.status] ?? r.status }}</span>
                  <div>
                    <span class="strong">{{ humanize(r.reason) }}</span>
                    <span v-if="r.description" class="sub">“{{ r.description }}”</span>
                    <span class="sub">{{ r.reporter || "Customer" }} · {{ formatDate(r.created_at) }}</span>
                  </div>
                </li>
              </ul>
            </div>

            <div class="box">
              <span class="box-label">Reviews</span>
              <p v-if="!detail.reviews.count" class="sub">No reviews yet.</p>
              <template v-else>
                <span class="strong">★ {{ detail.reviews.average }} · {{ plural(detail.reviews.count, "review") }}</span>
                <ul class="mini-list">
                  <li v-for="(r, i) in detail.reviews.recent" :key="i">
                    <span class="rating">{{ "★".repeat(r.rating) }}</span>
                    <span class="sub">{{ r.comment || "No comment" }}</span>
                  </li>
                </ul>
              </template>
            </div>

            <div class="box">
              <span class="box-label">Moderation history</span>
              <p v-if="!detail.history.length" class="sub">No moderation yet.</p>
              <ol v-else class="timeline">
                <li v-for="e in detail.history" :key="e.id" :class="`ev-${e.action}`">
                  <span class="ev-dot"></span>
                  <div>
                    <span class="strong">{{ EVENT_LABEL[e.action] ?? e.action }} · {{ e.by }}</span>
                    <span v-if="e.reason" class="sub">“{{ e.reason }}”</span>
                    <span class="sub">{{ formatDateTime(e.at) }}</span>
                  </div>
                </li>
              </ol>
            </div>
          </template>
        </div>

        <footer v-if="detail" class="drawer-foot">
          <span class="sub foot-note">Vendors edit details and stock themselves.</span>
          <button
            v-for="a in drawerActions"
            :key="a"
            type="button"
            :class="a === 'approve' || a === 'restore' ? 'primary-btn ok' : 'ghost-btn ' + (a === 'remove' ? 'danger-text' : '')"
            @click="askModerate(a, detail)"
          >
            {{ ACTION_LABEL_LONG[a] }}
          </button>
        </footer>
      </aside>
    </Transition>

    <!-- ═══ Moderation dialog ═══ -->
    <Transition name="fade">
      <div v-if="dialog.action" class="dialog-backdrop" @click.self="closeDialog">
        <div class="dialog" role="dialog" aria-modal="true">
          <h3>{{ DIALOG[dialog.action].title(dialog.product.name) }}</h3>
          <p>{{ DIALOG[dialog.action].body }}</p>
          <textarea
            v-model="dialog.reason"
            rows="3"
            :placeholder="DIALOG[dialog.action].placeholder"
            :class="{ invalid: dialog.error }"
          ></textarea>
          <p v-if="dialog.error" class="dialog-error">{{ dialog.error }}</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="dialog.busy" @click="closeDialog">Cancel</button>
            <button type="button" class="primary-btn" :class="ACTION_TONE[dialog.action]" :disabled="dialog.busy" @click="confirmModerate">
              {{ dialog.busy ? "Working…" : ACTION_LABEL_LONG[dialog.action] }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import { useProductTaxonomies } from "../../composables/useProductTaxonomies";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMoreRows } from "../../composables/useShowMore";
import { linkedId } from "../../utils/hiddenIds";

const { toggleMobile } = useSidebarState();
const { taxonomies, reload: reloadTaxonomies } = useProductTaxonomies();
const route = useRoute();
const router = useRouter();

const TABS = [
  { key: "all", label: "All", tone: "slate" },
  { key: "review", label: "Needs review", tone: "violet" },
  { key: "live", label: "Live", tone: "green" },
  { key: "hidden", label: "Hidden", tone: "amber" },
  { key: "removed", label: "Removed", tone: "red" },
  { key: "reported", label: "Reported", tone: "rose" },
  { key: "out_of_stock", label: "Out of stock", tone: "gray" },
  { key: "low_stock", label: "Low stock", tone: "orange" },
];
const TAB_NOTES = {
  review: "New listings go live straight away; approve them once you've checked them. Listings a vendor fixed after being hidden stay hidden until you approve them.",
  hidden: "Hidden listings come back once the vendor fixes them and you approve the change.",
  removed: "Removed listings stay off the shop even if the vendor edits them. Restore one to bring it back.",
  out_of_stock: "Live listings with no stock left. Only the vendor can restock them.",
  low_stock: "Live listings at or below the vendor's own minimum stock level.",
};
const EMPTY = { review: "Nothing to review. You're all caught up.", reported: "No listing has an open report.", hidden: "No hidden listings.", removed: "No removed listings." };
const VISIBILITY = {
  live: "Live",
  vendor_off: "Off by vendor",
  store_suspended: "Store suspended",
  hidden: "Hidden",
  resubmitted: "Fixed · awaiting review",
  removed: "Removed",
};
const VISIBILITY_HELP = {
  live: { title: "Customers can see and buy this", body: "It's live on the vendor's store and in the shop." },
  vendor_off: { title: "Turned off by the vendor", body: "The vendor set it to draft or inactive. That's their choice, so there's nothing to do here." },
  store_suspended: { title: "Hidden because the store is suspended", body: "It comes back when the vendor is reactivated." },
  hidden: { title: "Hidden by BloomCraft", body: "Customers can't see it. The vendor has been told why and can fix it." },
  resubmitted: { title: "The vendor fixed it and it's waiting for you", body: "It stays hidden until you approve it." },
  removed: { title: "Removed by BloomCraft", body: "Customers can't see or buy it, and vendor edits won't bring it back." },
};
const SELLING_TYPES = { per_piece: "Per piece", per_piece_customizable: "Per piece (3D customizable)", bouquet: "Bouquet" };
const REPORT_STATUS = { pending: "Open", approved: "Upheld", rejected: "Dismissed" };
const EVENT_LABEL = { approved: "Approved", hidden: "Hidden", removed: "Removed", restored: "Restored", resubmitted: "Fixed and resubmitted" };
const ACTION_LABEL = { approve: "Approve", hide: "Hide", remove: "Remove", restore: "Restore" };
const ACTION_LABEL_LONG = { approve: "Approve listing", hide: "Hide listing", remove: "Remove listing", restore: "Restore listing" };
const ACTION_TONE = { approve: "ok", restore: "ok", hide: "warn", remove: "danger" };
const DIALOG = {
  approve: { title: (n) => `Approve ${n}?`, body: "It stays live and leaves the review queue.", placeholder: "Note for the history (optional)" },
  restore: { title: (n) => `Restore ${n}?`, body: "It goes back on the shop if the vendor has it set to active.", placeholder: "Note for the history (optional)" },
  hide: { title: (n) => `Hide ${n}?`, body: "Customers stop seeing it. The vendor sees your reason and can fix the listing and send it back for review.", placeholder: "What needs fixing? The vendor will see this." },
  remove: { title: (n) => `Remove ${n}?`, body: "It comes off the shop for good. Vendor edits won't bring it back; only an admin can restore it.", placeholder: "Why is it being removed? The vendor will see this." },
};
const LISTS = [
  { type: "category", title: "Categories", single: "category", hint: "What the product is" },
  { type: "flower_type", title: "Flower types", single: "flower type", hint: "Its role in an arrangement" },
  { type: "occasion", title: "Occasions", single: "occasion", hint: "Vendors pick up to two" },
];

// ── Listings ─────────────────────────────────────────────────────
const props = defineProps({ section: { type: String, default: "listings" } });
// Product Management and Category Management share this page
const view = computed(() => (props.section === "lists" ? "lists" : "listings"));
const summary = ref(null);
const products = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 });
const loading = ref(false);
const loadError = ref(false);
const blankFilters = () => ({ tab: "all", search: "", category: "", flower_type: "", sort: "newest" });
const filters = reactive({ ...blankFilters(), tab: TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "review" });

const hasFilters = computed(() => Boolean(filters.search.trim() || filters.category || filters.flower_type));
const tabNote = computed(() => TAB_NOTES[filters.tab] ?? "");

const fetchSummary = async () => {
  try {
    const { data } = await api.get("/admin/products/summary");
    summary.value = data.data;
  } catch {
    // counts are optional
  }
};

let listRequest = 0;
const fetchProducts = async (page = 1) => {
  const id = ++listRequest;
  loading.value = true;
  loadError.value = false;
  try {
    const params = Object.fromEntries(Object.entries({ ...filters, search: filters.search.trim(), page, per_page: 12 }).filter(([, v]) => v !== ""));
    const { data } = await api.get("/admin/products", { params });
    if (id !== listRequest) return;
    products.value = page > 1 ? [...products.value, ...(data.data ?? [])] : data.data;
    meta.value = data.meta;
  } catch {
    if (id !== listRequest) return;
    loadError.value = true;
    if (products.value.length) toast.error("The listings couldn't be refreshed.");
  } finally {
    if (id === listRequest) loading.value = false;
  }
};
// 10 rows first, then 10 more, then 20 at a time
const productList = useShowMoreRows(products, meta, fetchProducts);

let searchTimer = null;
watch(
  () => ({ ...filters }),
  (next, prev) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => productList.load(), next.search !== prev.search ? 300 : 0);
    if (next.tab !== prev.tab) router.replace({ query: { ...route.query, tab: next.tab } });
  },
);

const clearFilters = () => Object.assign(filters, { search: "", category: "", flower_type: "" });

const refresh = () => {
  fetchSummary();
  if (view.value === "listings") productList.refresh();
  else fetchLists();
  if (drawer.id) loadProduct(drawer.id);
};

const quickActions = (p) => {
  if (p.moderation_status === "removed") return ["restore"];
  if (p.moderation_status === "resubmitted") return ["approve", "remove"];
  if (p.moderation_status === "hidden") return ["restore", "remove"];
  if (p.moderation_status === "pending") return ["approve", "hide"];
  return ["hide"];
};
const drawerActions = computed(() => {
  const s = detail.value?.moderation_status;
  if (s === "removed") return ["restore"];
  if (s === "resubmitted") return ["remove", "approve"];
  if (s === "hidden") return ["remove", "restore"];
  if (s === "pending") return ["remove", "hide", "approve"];
  return ["remove", "hide"];
});

// ── Drawer ───────────────────────────────────────────────────────
const drawer = reactive({ id: null, error: "", image: 0 });
const drawerEl = ref(null);
const detail = ref(null);
const activeImage = computed(() => detail.value?.images[drawer.image] ?? detail.value?.image ?? null);
let lastFocus = null;

let detailRequest = 0;
const loadProduct = async (id) => {
  const requestId = ++detailRequest;
  drawer.error = "";
  try {
    const { data } = await api.get(`/admin/products/${id}`);
    if (requestId === detailRequest) detail.value = data.data;
  } catch (error) {
    if (requestId === detailRequest) drawer.error = error.response?.status === 404 ? "This product no longer exists." : "This product couldn't be loaded.";
  }
};

const openProduct = async (id) => {
  if (!drawer.id) lastFocus = document.activeElement;
  if (drawer.id !== id) detail.value = null;
  Object.assign(drawer, { id, image: 0 });
  router.replace({ query: { ...route.query, product: id } });
  loadProduct(id);
  await nextTick();
  drawerEl.value?.focus();
};

const closeProduct = () => {
  drawer.id = null;
  detailRequest++;
  router.replace({ query: { ...route.query, product: null } });
  lastFocus?.focus?.();
};

// ── Moderation ───────────────────────────────────────────────────
const dialog = reactive({ action: null, product: null, reason: "", error: "", busy: false });
const askModerate = (action, product) => Object.assign(dialog, { action, product, reason: "", error: "", busy: false });
const closeDialog = () => {
  if (!dialog.busy) dialog.action = null;
};

const confirmModerate = async () => {
  const reason = dialog.reason.trim();
  if (["hide", "remove"].includes(dialog.action) && reason.length < 5) {
    dialog.error = "Give the vendor a reason of at least 5 characters.";
    return;
  }
  dialog.busy = true;
  dialog.error = "";
  try {
    const { data } = await api.post(`/admin/products/${dialog.product.id}/${dialog.action}`, { reason: reason || null });
    toast.success(data.message || "Done.");
    const id = dialog.product.id;
    dialog.busy = false;
    dialog.action = null;
    await Promise.all([productList.refresh(), fetchSummary(), drawer.id === id ? loadProduct(id) : null]);
  } catch (error) {
    dialog.busy = false;
    dialog.error = error.response?.data?.message || "That didn't work. Try again.";
  }
};

// ── Categories & tags ────────────────────────────────────────────
const lists = reactive({ items: {}, unlisted: {}, loading: false, loaded: false, saving: false });
const drafts = reactive({ category: "", flower_type: "", occasion: "" });

const fetchLists = async () => {
  lists.loading = true;
  try {
    const { data } = await api.get("/admin/taxonomies");
    lists.items = data.data.items;
    lists.unlisted = data.data.unlisted;
    lists.loaded = true;
  } catch {
    toast.error("The lists couldn't be loaded.");
  } finally {
    lists.loading = false;
  }
};

let listingsLoaded = false;
const loadSection = () => {
  if (view.value === "lists") {
    if (!lists.loaded) fetchLists();
    return;
  }
  if (!listingsLoaded) {
    listingsLoaded = true;
    fetchSummary();
    productList.load();
  }
};
watch(view, loadSection);

const afterListChange = () => {
  reloadTaxonomies(); // keep this page's filters in step
};

const addItem = async (type, label, value) => {
  const name = (label ?? "").trim();
  if (!name) return;
  lists.saving = true;
  try {
    await api.post("/admin/taxonomies", { type, label: name, value: value ?? undefined });
    drafts[type] = "";
    toast.success("Added.");
    await fetchLists();
    afterListChange();
  } catch (error) {
    toast.error(error.response?.data?.errors?.label?.[0] || error.response?.data?.message || "It couldn't be added.");
  } finally {
    lists.saving = false;
  }
};

const renameItem = async (item, label) => {
  const name = label.trim();
  if (!name || name === item.label) return;
  const previous = item.label;
  item.label = name;
  try {
    await api.put(`/admin/taxonomies/${item.id}`, { label: name });
    toast.success("Renamed.");
    afterListChange();
  } catch {
    item.label = previous;
    toast.error("The new name couldn't be saved.");
  }
};

const toggleItem = async (item, active) => {
  item.is_active = active;
  try {
    await api.put(`/admin/taxonomies/${item.id}`, { is_active: active });
    afterListChange();
  } catch {
    item.is_active = !active;
    toast.error("That change couldn't be saved.");
  }
};

const move = async (type, index, delta) => {
  const list = lists.items[type];
  const target = index + delta;
  if (!list || target < 0 || target >= list.length) return;
  [list[index], list[target]] = [list[target], list[index]];
  try {
    await api.post("/admin/taxonomies/reorder", { type, ids: list.map((i) => i.id) });
    afterListChange();
  } catch {
    toast.error("The new order couldn't be saved.");
    fetchLists();
  }
};

const deleteItem = async (item) => {
  if (item.products > 0) return;
  try {
    await api.delete(`/admin/taxonomies/${item.id}`);
    toast.success("Deleted.");
    await fetchLists();
    afterListChange();
  } catch (error) {
    toast.error(error.response?.data?.message || "It couldn't be deleted.");
  }
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => (v === null || v === undefined ? "—" : peso.format(Number(v)));
const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const humanize = (s) => (s ? String(s).replace(/[_-]/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "—");
const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";

// ── Lifecycle ────────────────────────────────────────────────────
const onKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (dialog.action) closeDialog();
  else if (drawer.id) closeProduct();
};

onMounted(() => {
  // Old links to the lists used ?view=lists on this page
  if (view.value === "listings" && route.query.view === "lists") {
    router.replace("/admin/categories");
    return;
  }
  loadSection();
  const productId = linkedId("product");
  if (productId) openProduct(productId);
  window.addEventListener("keydown", onKeydown);
});
onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  window.removeEventListener("keydown", onKeydown);
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  transition: margin-left 0.3s;
}
.page-body {
  padding: 0 28px 40px;
}
.page-body > section,
.page-body > p {
  margin-top: 18px;
}

/* Header */
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left,
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.view-switch {
  display: inline-flex;
  padding: 3px;
  background: #f1f5f9;
  border-radius: 10px;
  gap: 2px;
}
.view-switch button {
  border: none;
  background: transparent;
  padding: 7px 13px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.view-switch button.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

/* Buttons */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.ghost-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
}
.ghost-btn:hover:not(:disabled) {
  border-color: #c7c2fb;
  color: #5b4fc9;
}
.ghost-btn.danger-text {
  color: #b91c1c;
}
.ghost-btn.danger-text:hover:not(:disabled) {
  background: #fef2f2;
  border-color: #fecaca;
  color: #b91c1c;
}
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.primary-btn:hover:not(:disabled) {
  background: #5b4fc9;
}
.primary-btn.ok { background: #16a34a; }
.primary-btn.ok:hover:not(:disabled) { background: #15803d; }
.primary-btn.warn { background: #d97706; }
.primary-btn.warn:hover:not(:disabled) { background: #b45309; }
.primary-btn.danger { background: #dc2626; }
.primary-btn.danger:hover:not(:disabled) { background: #b91c1c; }
.ghost-btn:disabled,
.primary-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.link-btn {
  border: none;
  background: none;
  color: #6d5ff0;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  padding: 2px 4px;
  text-decoration: none;
  white-space: nowrap;
}
.link-btn:hover {
  text-decoration: underline;
}
.icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.mini-btn {
  flex: 1;
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 8px;
  padding: 6px 8px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
}
.mini-btn.ok { color: #15803d; }
.mini-btn.ok:hover { background: #16a34a; border-color: #16a34a; color: #fff; }
.mini-btn.warn { color: #b45309; }
.mini-btn.warn:hover { background: #fffbeb; border-color: #fde68a; }
.mini-btn.danger { color: #b91c1c; }
.mini-btn.danger:hover { background: #fef2f2; border-color: #fecaca; }

/* Skeleton */
.sk {
  border-radius: 6px;
  background: linear-gradient(100deg, #eef1f6 30%, #f8fafc 50%, #eef1f6 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-line {
  height: 12px;
  margin: 6px 0;
}
.sk-inline {
  display: inline-block;
  width: 16px;
  height: 12px;
  vertical-align: middle;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Tabs */
.tab-strip {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.tab-chip {
  --c: #475569;
  --bg: #f8fafc;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 22px;
  border: 1px solid #e9edf3;
  background: #fff;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.tab-chip b {
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.tab-chip.active {
  border-color: var(--c);
  background: var(--bg);
  color: #1e2739;
}
.tab-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--c);
}
.tone-violet { --c: #7c6ff7; --bg: #f5f3ff; }
.tone-green { --c: #16a34a; --bg: #f0fdf4; }
.tone-amber { --c: #d97706; --bg: #fffbeb; }
.tone-red { --c: #dc2626; --bg: #fef2f2; }
.tone-rose { --c: #e11d48; --bg: #fff1f2; }
.tone-gray { --c: #94a3b8; --bg: #f8fafc; }
.tone-orange { --c: #ea580c; --bg: #fff7ed; }

/* Panel */
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
}
.panel-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.toolbar-filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1 1 240px;
  max-width: 340px;
}
.search-ico {
  position: absolute;
  left: 11px;
  color: #94a3b8;
  pointer-events: none;
}
.search-input,
.select {
  padding: 8px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.search-input {
  width: 100%;
  padding-left: 32px;
  background: #f8fafc;
}
.search-input:focus,
.select:focus,
textarea:focus,
.tax-label:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.tab-note {
  margin: 0 0 14px;
  padding: 9px 12px;
  border-radius: 10px;
  background: #eef2ff;
  color: #475569;
  font-size: 12.5px;
}
.empty-msg {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 40px 0;
  margin: 0;
}

/* Product grid */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
}
.product-grid.dim {
  opacity: 0.55;
}
.card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
  min-width: 0;
}
.card:hover,
.card:focus-visible {
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
  transform: translateY(-2px);
  outline: none;
}
.card.selected {
  border-color: #7c6ff7;
}
.card-img {
  position: relative;
  aspect-ratio: 4 / 3;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
}
.card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.img-fallback {
  font-size: 30px;
}
.img-fallback.big {
  font-size: 48px;
}
.pill.vis {
  position: absolute;
  top: 10px;
  left: 10px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
}
.report-tag,
.new-tag {
  position: absolute;
  top: 10px;
  right: 10px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  background: #e11d48;
  color: #fff;
}
.new-tag {
  background: #7c6ff7;
}
.report-tag + .new-tag {
  top: 36px;
}
.card-body {
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex: 1;
}
.card-name {
  font-size: 14px;
  font-weight: 600;
  color: #1e2739;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.card-vendor {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
  min-width: 0;
}
.card-vendor span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.card-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
  font-size: 12.5px;
}
.card-row.sub {
  font-size: 11.5px;
  color: #94a3b8;
}
.price {
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.price s {
  font-weight: 400;
  color: #94a3b8;
  font-size: 11.5px;
  margin-left: 4px;
}
.stock {
  font-size: 11.5px;
  font-weight: 500;
  color: #475569;
}
.stock-low { color: #ea580c; font-weight: 600; }
.stock-out { color: #dc2626; font-weight: 600; }
.card-actions {
  display: flex;
  gap: 6px;
  padding: 0 14px 12px;
}

/* Pills */
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  background: #f1f5f9;
  color: #64748b;
}
.vis-live { background: #dcfce7; color: #15803d; }
.vis-vendor_off { background: #f1f5f9; color: #64748b; }
.vis-store_suspended { background: #fee2e2; color: #b91c1c; }
.vis-hidden { background: #fef3c7; color: #b45309; }
.vis-resubmitted { background: #dbeafe; color: #1d4ed8; }
.vis-removed { background: #fee2e2; color: #b91c1c; }
.rp-pending { background: #ffe4e6; color: #be123c; }
.rp-approved { background: #fee2e2; color: #b91c1c; }
.rp-rejected { background: #f1f5f9; color: #64748b; }

/* Pager */
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
}
.pager-info {
  font-size: 12px;
  color: #64748b;
}
.pager-btns {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Lists */
.lists-intro {
  font-size: 12.5px;
  color: #64748b;
  max-width: 760px;
}
.lists-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}
.list-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 12px;
}
.count-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 20px;
  background: #f5f3ff;
  color: #5b4fc9;
  white-space: nowrap;
}
.tax-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.tax-list li {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border: 1px solid #eef1f6;
  border-radius: 10px;
  background: #fbfcfe;
}
.tax-list li.off {
  opacity: 0.6;
}
.reorder {
  display: flex;
  flex-direction: column;
}
.reorder button {
  border: none;
  background: none;
  font-size: 8px;
  line-height: 1;
  padding: 2px 3px;
  color: #94a3b8;
  cursor: pointer;
}
.reorder button:disabled {
  opacity: 0.3;
  cursor: default;
}
.reorder button:not(:disabled):hover {
  color: #5b4fc9;
}
.tax-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.tax-label {
  border: 1px solid transparent;
  background: transparent;
  border-radius: 6px;
  padding: 3px 6px;
  margin-left: -6px;
  font-size: 13px;
  font-weight: 500;
  color: #1e2739;
  width: 100%;
}
.tax-label:hover {
  border-color: #e2e8f0;
  background: #fff;
}
.tax-meta {
  font-size: 11px;
  color: #94a3b8;
}
.tax-meta code {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 10.5px;
}
.switch {
  position: relative;
  width: 34px;
  height: 20px;
  flex-shrink: 0;
  cursor: pointer;
}
.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.switch span {
  position: absolute;
  inset: 0;
  border-radius: 20px;
  background: #cbd5e1;
  transition: background 0.2s;
}
.switch span::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}
.switch input:checked + span {
  background: #16a34a;
}
.switch input:checked + span::after {
  transform: translateX(14px);
}
.switch input:focus-visible + span {
  outline: 2px solid #7c6ff7;
  outline-offset: 2px;
}
.icon-x {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  border: none;
  background: transparent;
  color: #94a3b8;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.icon-x:not(:disabled):hover {
  background: #fef2f2;
  color: #dc2626;
}
.icon-x:disabled {
  opacity: 0.35;
  cursor: default;
}
.unlisted {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}
.unlisted-chip {
  border: 1px dashed #c7c2fb;
  background: #faf9ff;
  color: #5b4fc9;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 12px;
  cursor: pointer;
}
.unlisted-chip span {
  color: #94a3b8;
}
.add-row {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}
.add-row .select {
  flex: 1;
  min-width: 0;
}

/* Drawer */
.drawer-backdrop,
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.38);
  backdrop-filter: blur(2px);
  z-index: 300;
}
.dialog-backdrop {
  z-index: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(620px, 100vw);
  background: #f8fafc;
  z-index: 301;
  display: flex;
  flex-direction: column;
  box-shadow: -20px 0 50px rgba(15, 23, 42, 0.18);
  outline: none;
}
.drawer-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 20px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
}
.drawer-title {
  flex: 1;
  min-width: 0;
}
.drawer-title h2 {
  margin: 0 0 6px;
  font-size: 17px;
  font-weight: 600;
  color: #1e2739;
}
.drawer-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 18px 28px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.drawer-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 18px;
  background: #fff;
  border-top: 1px solid #e9edf3;
  flex-wrap: wrap;
}
.foot-note {
  margin-right: auto;
}
.gallery-main {
  aspect-ratio: 16 / 10;
  border-radius: 14px;
  overflow: hidden;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
}
.gallery-main img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumbs {
  display: flex;
  gap: 8px;
  margin-top: 8px;
  overflow-x: auto;
}
.thumbs button {
  width: 56px;
  height: 56px;
  flex-shrink: 0;
  padding: 0;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid transparent;
  cursor: pointer;
}
.thumbs button.active {
  border-color: #7c6ff7;
}
.thumbs img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.box {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.box-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.box-label {
  display: block;
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94a3b8;
  margin-bottom: 6px;
}
.status-box {
  font-size: 12.5px;
}
.status-box strong {
  font-size: 13.5px;
}
.status-box.vis-live { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.status-box.vis-vendor_off { background: #f8fafc; color: #475569; }
.status-box.vis-store_suspended,
.status-box.vis-removed { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.status-box.vis-hidden { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.status-box.vis-resubmitted { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.reason {
  font-style: italic;
}
.vendor-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.vendor-row > div {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.facts {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 7px 12px;
  margin: 0;
  font-size: 13px;
}
.facts dt {
  color: #94a3b8;
}
.facts dd {
  margin: 0;
  color: #1e2739;
}
.text-block {
  margin: 10px 0 0;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
  font-size: 13px;
  color: #334155;
  white-space: pre-wrap;
}
.strong {
  font-weight: 600;
  color: #1e2739;
  font-size: 13px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.sub-inline {
  font-size: 12px;
  color: #94a3b8;
}
.rating {
  color: #f59e0b;
  font-size: 12px;
}
.mini-list {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.mini-list li {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.mini-list li > div {
  display: flex;
  flex-direction: column;
}
.timeline {
  list-style: none;
  margin: 4px 0 0;
  padding: 0 0 0 4px;
}
.timeline li {
  --dot: #94a3b8;
  position: relative;
  display: flex;
  gap: 12px;
  padding-bottom: 14px;
}
.timeline li:not(:last-child)::before {
  content: "";
  position: absolute;
  left: 5px;
  top: 14px;
  bottom: 0;
  width: 2px;
  background: #e2e8f0;
}
.timeline li > div {
  display: flex;
  flex-direction: column;
}
.ev-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #fff;
  border: 3px solid var(--dot);
  margin-top: 3px;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}
.ev-approved,
.ev-restored { --dot: #16a34a; }
.ev-hidden { --dot: #d97706; }
.ev-removed { --dot: #dc2626; }
.ev-resubmitted { --dot: #3b82f6; }

/* Dialog */
.dialog {
  width: min(460px, 100%);
  background: #fff;
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}
.dialog h3 {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 600;
  color: #1e2739;
}
.dialog p {
  margin: 0 0 12px;
  font-size: 13px;
  color: #475569;
}
.dialog textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 13px;
  color: #1e2739;
  resize: vertical;
}
.dialog textarea.invalid {
  border-color: #fca5a5;
}
.dialog-error {
  color: #b91c1c !important;
  font-size: 12.5px !important;
  margin: 8px 0 0 !important;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

/* Responsive */
@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .lists-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 760px) {
  .lists-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 0 16px 32px;
  }
  .search-wrap {
    max-width: none;
  }
  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .facts {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
  .card:hover {
    transform: none;
  }
  .slide-enter-active,
  .slide-leave-active,
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}
</style>
