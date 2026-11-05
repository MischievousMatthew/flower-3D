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
            <h1 class="page-title">Review Management</h1>
            <p class="page-subtitle">Customer reviews and the listings customers reported</p>
          </div>
        </div>
        <span v-if="!canModerate" class="readonly-chip">View only · your role can't moderate</span>
      </header>

      <div class="page-body">
        <nav class="tabs" role="tablist">
          <button type="button" role="tab" class="tab" :class="{ active: tab === 'reviews' }" :aria-selected="tab === 'reviews'" @click="setTab('reviews')">
            Customer reviews
            <span v-if="summary" class="tab-count">{{ summary.reviews }}</span>
          </button>
          <button type="button" role="tab" class="tab" :class="{ active: tab === 'reports' }" :aria-selected="tab === 'reports'" @click="setTab('reports')">
            Reported listings
            <span v-if="summary?.pending_reports" class="tab-count hot">{{ summary.pending_reports }}</span>
          </button>
        </nav>

        <!-- ═══ Reviews ═══ -->
        <template v-if="tab === 'reviews'">
          <section class="stat-grid">
            <template v-if="!summary">
              <div v-for="n in 4" :key="n" class="stat"><span class="sk" style="height: 11px; width: 50%"></span><span class="sk" style="height: 22px; width: 60%; margin-top: 10px"></span></div>
            </template>
            <template v-else>
              <div class="stat feature">
                <span class="stat-label">Average rating</span>
                <span class="stat-value">★ {{ summary.average_rating ? summary.average_rating.toFixed(1) : "—" }}</span>
                <div class="star-bars">
                  <div v-for="n in [5, 4, 3, 2, 1]" :key="n" class="star-bar">
                    <span>{{ n }}★</span>
                    <i><b :style="{ width: starShare(n) }"></b></i>
                    <span>{{ summary.stars[n] }}</span>
                  </div>
                </div>
              </div>
              <div class="stat">
                <span class="stat-label">Reviews</span>
                <span class="stat-value">{{ summary.reviews }}</span>
                <span class="stat-note">{{ summary.new_7d }} new this week</span>
              </div>
              <button type="button" class="stat clickable" :class="{ warn: summary.low_ratings_30d }" @click="applyQuick({ rating: '1', status: 'shown' })">
                <span class="stat-label">Low ratings (1–2★)</span>
                <span class="stat-value">{{ summary.low_ratings_30d }}</span>
                <span class="stat-note">Last 30 days · worth a read</span>
              </button>
              <button type="button" class="stat clickable" @click="applyQuick({ status: 'hidden' })">
                <span class="stat-label">Hidden by admins</span>
                <span class="stat-value">{{ summary.hidden }}</span>
                <span class="stat-note">Not shown on the shop or counted in ratings</span>
              </button>
            </template>
          </section>

          <section class="panel">
            <div class="toolbar">
              <input v-model="reviewFilters.search" type="search" class="input grow" placeholder="Search review text, product or store" aria-label="Search reviews" />
              <select v-model="reviewFilters.rating" class="input" aria-label="Rating">
                <option value="">Any rating</option>
                <option v-for="n in [5, 4, 3, 2, 1]" :key="n" :value="String(n)">{{ n }} star{{ n === 1 ? "" : "s" }}</option>
              </select>
              <select v-model="reviewFilters.status" class="input" aria-label="Status">
                <option value="">Showing and hidden</option>
                <option value="shown">Showing</option>
                <option value="hidden">Hidden</option>
              </select>
              <label class="check"><input v-model="reviewFilters.photo" type="checkbox" /> With photo</label>
            </div>

            <div v-if="loading.reviews && !reviews.length" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-card"></span></div>
            <p v-else-if="!reviews.length" class="empty">No reviews match these filters.</p>
            <ul v-else class="card-list" :class="{ dim: loading.reviews }">
              <li v-for="r in reviewList.rows.value" :key="r.id" class="review-card" :class="{ hidden: r.hidden }">
                <div class="rc-product">
                  <img v-if="r.product.image" :src="r.product.image" alt="" class="thumb" />
                  <span v-else class="thumb empty-thumb">🌸</span>
                  <div class="rc-product-text">
                    <router-link :to="{ path: '/admin/product-approval', query: { product: r.product.id } }" class="strong link">{{ r.product.name }}</router-link>
                    <span class="sub">{{ r.vendor.store_name }}</span>
                  </div>
                </div>
                <div class="rc-body">
                  <div class="rc-top">
                    <span class="stars" :aria-label="`${r.rating} out of 5`">
                      <span v-for="n in 5" :key="n" :class="{ on: n <= r.rating }">★</span>
                    </span>
                    <span class="sub-inline">{{ r.reviewer }}<template v-if="r.is_anonymous"> · posted anonymously</template> · order {{ r.order_number || "—" }} · {{ formatDate(r.created_at) }}<template v-if="r.edited"> · edited</template></span>
                  </div>
                  <p class="comment" :class="{ none: !r.comment }">{{ r.comment || "No written comment." }}</p>
                  <a v-if="r.image_url" :href="r.image_url" target="_blank" rel="noopener" class="photo"><img :src="r.image_url" alt="Photo attached to the review" /></a>
                  <p v-if="r.hidden" class="hidden-note">
                    Hidden by {{ r.hidden.by }} {{ timeAgo(r.hidden.at) }} — “{{ r.hidden.reason }}”
                  </p>
                </div>
                <div v-if="canModerate" class="rc-actions">
                  <button v-if="!r.hidden" type="button" class="mini-btn danger" @click="askHide(r)">Hide</button>
                  <button v-else type="button" class="mini-btn ok" :disabled="busyId === r.id" @click="restore(r)">{{ busyId === r.id ? "Restoring…" : "Restore" }}</button>
                </div>
              </li>
            </ul>
            <ShowMore v-if="reviews.length" tone="admin" :has-more="reviewList.hasMore.value" :loading="reviewList.loadingMore.value" :shown="reviewList.rows.value.length" :total="reviewList.total.value" @more="reviewList.showMore()" />
          </section>
        </template>

        <!-- ═══ Reported listings ═══ -->
        <section v-else class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Reported listings</h2>
              <p class="panel-sub">
                Upholding a report removes the listing and gives the vendor a strike. At {{ STRIKE_LIMIT }} strikes the store is suspended automatically.
              </p>
            </div>
            <div class="seg">
              <button v-for="f in REPORT_FILTERS" :key="f.value" type="button" class="seg-btn" :class="{ active: reportFilters.status === f.value }" @click="reportFilters.status = f.value">
                {{ f.label }}<span v-if="f.value && reportCounts[f.value]" class="seg-count">{{ reportCounts[f.value] }}</span>
              </button>
            </div>
          </div>
          <div class="toolbar">
            <input v-model="reportFilters.search" type="search" class="input grow" placeholder="Search product, reason or details" aria-label="Search reports" />
          </div>

          <div v-if="loading.reports && !reports.length" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-card"></span></div>
          <p v-else-if="!reports.length" class="empty">{{ reportFilters.status === "pending" ? "No reports waiting. You're all caught up." : "No reports here." }}</p>
          <ul v-else class="card-list" :class="{ dim: loading.reports }">
            <li v-for="r in reportList.rows.value" :key="r.id" class="report-card">
              <img v-if="r.product.image" :src="r.product.image" alt="" class="thumb lg" />
              <span v-else class="thumb lg empty-thumb">🌸</span>
              <div class="rp-body">
                <div class="rp-top">
                  <router-link :to="{ path: '/admin/product-approval', query: { product: r.product.id } }" class="strong link">{{ r.product.name }}</router-link>
                  <span class="pill" :class="`st-${r.status}`">{{ REPORT_LABEL[r.status] }}</span>
                  <span v-if="r.product.moderation_status === 'removed'" class="pill bad">Listing removed</span>
                </div>
                <span class="sub">
                  {{ r.vendor.store_name }}
                  · <span :class="{ 'strike-warn': r.vendor_strikes >= STRIKE_LIMIT - 1 }">{{ r.vendor_strikes }} / {{ STRIKE_LIMIT }} strikes</span>
                  · {{ r.product.reports }} report{{ r.product.reports === 1 ? "" : "s" }} on this listing
                </span>
                <p class="reason"><b>{{ r.reason }}</b><template v-if="r.description"> — {{ r.description }}</template></p>
                <span class="sub">
                  Reported by {{ r.reporter }} · {{ formatDate(r.created_at) }}
                  <template v-if="r.reviewed_at"> · decided by {{ r.reviewed_by || "an admin" }} {{ formatDate(r.reviewed_at) }}</template>
                </span>
              </div>
              <div v-if="canModerate && r.status === 'pending'" class="rc-actions">
                <button type="button" class="mini-btn danger" @click="askReport(r, 'approve')">Uphold &amp; remove</button>
                <button type="button" class="mini-btn" @click="askReport(r, 'reject')">Dismiss</button>
              </div>
            </li>
          </ul>
          <ShowMore v-if="reports.length" tone="admin" :has-more="reportList.hasMore.value" :loading="reportList.loadingMore.value" :shown="reportList.rows.value.length" :total="reportList.total.value" @more="reportList.showMore()" />
        </section>
      </div>
    </main>

    <!-- ═══ Confirm dialog ═══ -->
    <Transition name="fade">
      <div v-if="dialog.type" class="dialog-backdrop" @click.self="closeDialog">
        <form class="dialog" @submit.prevent="confirmDialog">
          <template v-if="dialog.type === 'hide'">
            <h3>Hide this review?</h3>
            <p class="sub">It stops showing on the shop and no longer counts towards the product's or store's rating. The text and rating aren't changed, and you can restore it later.</p>
            <div class="chips">
              <button v-for="q in QUICK_REASONS" :key="q" type="button" class="chip-btn" :class="{ on: dialog.reason === q }" @click="dialog.reason = q">{{ q }}</button>
            </div>
            <label class="field">
              <span>Reason (kept in the audit log)</span>
              <textarea v-model="dialog.reason" class="input" rows="3" maxlength="500" required minlength="5" placeholder="Why is it being hidden?"></textarea>
            </label>
          </template>
          <template v-else>
            <h3>{{ dialog.action === "approve" ? "Uphold this report?" : "Dismiss this report?" }}</h3>
            <p v-if="dialog.action === 'approve'" class="sub">
              “{{ dialog.target.product.name }}” will be removed from the shop and {{ dialog.target.vendor.store_name }} gets a strike
              ({{ dialog.target.vendor_strikes + 1 }} / {{ STRIKE_LIMIT }}).
              <b v-if="dialog.target.vendor_strikes + 1 >= STRIKE_LIMIT" class="danger-text">This is their {{ STRIKE_LIMIT }}rd strike, so the store will be suspended.</b>
            </p>
            <p v-else class="sub">The listing stays live and the report is closed. The vendor isn't affected.</p>
          </template>
          <p v-if="dialog.error" class="form-error">{{ dialog.error }}</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="dialog.busy" @click="closeDialog">Cancel</button>
            <button type="submit" class="primary-btn" :class="{ danger: dialog.type === 'hide' || dialog.action === 'approve' }" :disabled="dialog.busy">
              {{ dialog.busy ? "Saving…" : dialog.type === "hide" ? "Hide review" : dialog.action === "approve" ? "Uphold & remove" : "Dismiss report" }}
            </button>
          </div>
        </form>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import { useSidebarState } from "../../composables/useSidebarState";
import { useAdminAccess } from "../../composables/useAdminAccess";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import api from "../../plugins/axios";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMoreRows } from "../../composables/useShowMore";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();
const adminAccess = useAdminAccess();
const canModerate = computed(() => adminAccess.can("products.moderate"));

const STRIKE_LIMIT = 3;
const REPORT_FILTERS = [
  { value: "pending", label: "Open" },
  { value: "approved", label: "Upheld" },
  { value: "rejected", label: "Dismissed" },
  { value: "", label: "All" },
];
const REPORT_LABEL = { pending: "Open", approved: "Upheld", rejected: "Dismissed" };
const QUICK_REASONS = ["Offensive or abusive language", "Shares personal information", "Spam or advertising", "Not about this product"];

const tab = ref(route.query.tab === "reports" ? "reports" : "reviews");
const loading = reactive({ reviews: false, reports: false });
const summary = ref(null);
const busyId = ref(null);

const fetchSummary = async () => {
  try {
    summary.value = (await api.get("/admin/reviews/summary")).data.data;
  } catch {
    // the lists still load
  }
};

// ── Reviews ──────────────────────────────────────────────────────
const reviews = ref([]);
const reviewMeta = ref({ current_page: 1, last_page: 1, total: 0 });
const reviewFilters = reactive({ search: "", rating: "", status: "", photo: false });
let reviewRequest = 0;
const fetchReviews = async (page = 1) => {
  const id = ++reviewRequest;
  loading.reviews = true;
  try {
    const params = { page, search: reviewFilters.search || undefined, rating: reviewFilters.rating || undefined, status: reviewFilters.status || undefined, photo: reviewFilters.photo ? 1 : undefined };
    const { data } = await api.get("/admin/reviews", { params });
    if (id !== reviewRequest) return;
    reviews.value = page > 1 ? [...reviews.value, ...(data.data ?? [])] : data.data;
    reviewMeta.value = data.meta;
  } catch {
    if (id === reviewRequest) toast.error("Reviews couldn't be loaded.");
  } finally {
    if (id === reviewRequest) loading.reviews = false;
  }
};
// 10 rows first, then 10 more, then 20 at a time
const reviewList = useShowMoreRows(reviews, reviewMeta, fetchReviews);
let reviewTimer = null;
watch(
  () => ({ ...reviewFilters }),
  () => {
    clearTimeout(reviewTimer);
    reviewTimer = setTimeout(() => reviewList.load(), 300);
  },
);
const applyQuick = (values) => Object.assign(reviewFilters, { search: "", rating: "", status: "", photo: false }, values);
const starShare = (n) => {
  const total = Object.values(summary.value?.stars ?? {}).reduce((a, b) => a + b, 0);
  return total ? `${Math.round((summary.value.stars[n] / total) * 100)}%` : "0%";
};

const restore = async (review) => {
  busyId.value = review.id;
  try {
    const { data } = await api.post(`/admin/reviews/${review.id}/restore`);
    toast.success(data.message);
    reviewList.refresh();
    fetchSummary();
  } catch (e) {
    toast.error(e.response?.data?.message || "The review couldn't be restored.");
  } finally {
    busyId.value = null;
  }
};

// ── Reports ──────────────────────────────────────────────────────
const reports = ref([]);
const reportMeta = ref({ current_page: 1, last_page: 1, total: 0 });
const reportCounts = ref({});
const reportFilters = reactive({ status: "pending", search: "" });
let reportRequest = 0;
const fetchReports = async (page = 1) => {
  const id = ++reportRequest;
  loading.reports = true;
  try {
    const params = { page, status: reportFilters.status || undefined, search: reportFilters.search || undefined };
    const { data } = await api.get("/admin/reviews/reports", { params });
    if (id !== reportRequest) return;
    reports.value = page > 1 ? [...reports.value, ...(data.data ?? [])] : data.data;
    reportMeta.value = data.meta;
    reportCounts.value = data.counts ?? {};
  } catch {
    if (id === reportRequest) toast.error("Reports couldn't be loaded.");
  } finally {
    if (id === reportRequest) loading.reports = false;
  }
};
// 10 rows first, then 10 more, then 20 at a time
const reportList = useShowMoreRows(reports, reportMeta, fetchReports);
let reportTimer = null;
watch(
  () => ({ ...reportFilters }),
  () => {
    clearTimeout(reportTimer);
    reportTimer = setTimeout(() => reportList.load(), 250);
  },
);

// ── Dialog ───────────────────────────────────────────────────────
const dialog = reactive({ type: null, action: null, target: null, reason: "", busy: false, error: "" });
const askHide = (review) => Object.assign(dialog, { type: "hide", action: null, target: review, reason: "", busy: false, error: "" });
const askReport = (report, action) => Object.assign(dialog, { type: "report", action, target: report, reason: "", busy: false, error: "" });
const closeDialog = () => {
  if (!dialog.busy) dialog.type = null;
};
const confirmDialog = async () => {
  dialog.busy = true;
  dialog.error = "";
  try {
    if (dialog.type === "hide") {
      const { data } = await api.post(`/admin/reviews/${dialog.target.id}/hide`, { reason: dialog.reason.trim() });
      toast.success(data.message);
      reviewList.refresh();
    } else {
      const { data } = await api.post(`/admin/reports/${dialog.target.id}/review`, { action: dialog.action });
      toast.success(dialog.action === "approve" ? "Report upheld. The listing was removed." : "Report dismissed.");
      if (data.vendor_suspended) toast.warning(`${dialog.target.vendor.store_name} reached ${STRIKE_LIMIT} strikes and was suspended.`);
      reportList.refresh();
    }
    fetchSummary();
    dialog.busy = false;
    dialog.type = null;
  } catch (e) {
    dialog.busy = false;
    const errors = e.response?.data?.errors;
    dialog.error = errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "That couldn't be saved.";
  }
};

// ── Tabs ─────────────────────────────────────────────────────────
const loaded = { reviews: false, reports: false };
const loadTab = (key) => {
  if (loaded[key]) return;
  loaded[key] = true;
  if (key === "reviews") reviewList.load();
  else reportList.load();
};
const setTab = (key) => {
  tab.value = key;
  router.replace({ query: { ...route.query, tab: key } });
  loadTab(key);
};

// ── Formatting ───────────────────────────────────────────────────
const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
const timeAgo = (v) => {
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "just now" : `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? "" : "s"} ago`;
};

onMounted(() => {
  adminAccess.load().catch(() => {});
  fetchSummary();
  loadTab(tab.value);
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
  background: var(--a-subtle);
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
}
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: var(--a-surface);
  border-bottom: 1px solid var(--a-border);
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: var(--a-muted);
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: var(--a-faint);
  margin: 2px 0 0;
}
.readonly-chip {
  font-size: 12px;
  padding: 6px 11px;
  border-radius: 20px;
  background: var(--a-subtle-2);
  color: var(--a-text-2);
}
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Buttons */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}
.ghost-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
}
.primary-btn {
  border: none;
  background: var(--a-accent);
  color: var(--a-accent-ink);
}
.primary-btn.danger {
  background: #dc2626;
}
.primary-btn:disabled,
.ghost-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.mini-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
  cursor: pointer;
  white-space: nowrap;
}
.mini-btn:hover:not(:disabled) {
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.mini-btn:disabled {
  opacity: 0.45;
  cursor: default;
}
.mini-btn.danger {
  color: var(--a-red-text);
}
.mini-btn.danger:hover:not(:disabled) {
  border-color: var(--a-red-border);
  background: var(--a-red-soft);
  color: var(--a-red-text);
}
.mini-btn.ok {
  color: var(--a-green-text);
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.tab.active {
  background: var(--a-accent-soft-2);
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.tab-count {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 10px;
  background: var(--a-track);
  color: var(--a-text-2);
}
.tab-count.hot {
  background: #f43f5e;
  color: #fff;
}

/* Skeleton */
.sk {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, var(--a-subtle-2) 30%, var(--a-subtle) 50%, var(--a-subtle-2) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sk-card {
  height: 96px;
  border-radius: 12px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Stats */
.stat-grid {
  display: grid;
  grid-template-columns: 1.4fr repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  text-align: left;
  font: inherit;
  min-width: 0;
}
.stat.clickable {
  cursor: pointer;
}
.stat.clickable:hover {
  border-color: var(--a-accent-muted);
}
.stat.warn {
  border-color: var(--a-amber-border);
  background: var(--a-amber-soft);
}
.stat.feature {
  background: radial-gradient(120% 140% at 100% 0%, var(--a-accent-light) 0%, var(--a-accent-hover) 45%, var(--a-accent-strong) 100%);
  border-color: transparent;
  color: var(--a-accent-ink);
}
.stat.feature .stat-label,
.stat.feature .stat-value {
  color: #fff;
}
.stat-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--a-muted);
}
.stat-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--a-text);
}
.stat-note {
  font-size: 11.5px;
  color: var(--a-faint);
}
.star-bars {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-top: 6px;
}
.star-bar {
  display: grid;
  grid-template-columns: 26px 1fr 22px;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.85);
}
.star-bar span:last-child {
  text-align: right;
}
.star-bar i {
  height: 6px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.22);
  overflow: hidden;
}
.star-bar b {
  display: block;
  height: 100%;
  border-radius: 6px;
  background: var(--a-amber-soft);
}

/* Panels & filters */
.panel {
  background: var(--a-surface);
  border: 1px solid var(--a-border);
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0 0 4px;
}
.panel-sub {
  font-size: 12px;
  color: var(--a-faint);
  margin: 0;
  max-width: 640px;
}
.toolbar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 14px;
}
.grow {
  flex: 1 1 240px;
}
.input {
  padding: 8px 11px;
  border: 1px solid var(--a-border);
  border-radius: 9px;
  font-size: 12.5px;
  color: var(--a-text);
  background: var(--a-surface);
  font-family: inherit;
}
.input:focus {
  outline: none;
  border-color: var(--a-accent);
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--a-text-2);
  cursor: pointer;
}
.check input {
  accent-color: var(--a-accent);
}
.seg {
  display: inline-flex;
  padding: 3px;
  gap: 2px;
  border-radius: 10px;
  background: var(--a-subtle-2);
}
.seg-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: transparent;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--a-muted);
  cursor: pointer;
}
.seg-btn.active {
  background: var(--a-surface);
  color: var(--a-accent-text);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.seg-count {
  font-size: 11px;
  padding: 0 6px;
  border-radius: 10px;
  background: var(--a-track);
  color: var(--a-text-2);
}
.empty {
  text-align: center;
  color: var(--a-faint);
  font-size: 13px;
  padding: 28px 0;
  margin: 0;
}

/* Cards */
.card-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.card-list.dim {
  opacity: 0.55;
}
.review-card,
.report-card {
  display: flex;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid var(--a-border);
  border-radius: 12px;
  background: var(--a-surface);
}
.review-card.hidden {
  background: var(--a-subtle);
  border-style: dashed;
}
.rc-product {
  display: flex;
  gap: 10px;
  width: 220px;
  flex-shrink: 0;
}
.rc-product-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.thumb {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  object-fit: cover;
  flex-shrink: 0;
  background: var(--a-accent-soft);
}
.thumb.lg {
  width: 64px;
  height: 64px;
}
.empty-thumb {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}
.rc-body,
.rp-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.rc-top,
.rp-top {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.stars {
  font-size: 15px;
  letter-spacing: 1px;
  color: #e2e8f0;
}
.stars .on {
  color: #f59e0b;
}
.comment {
  margin: 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--a-text-2);
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.comment.none {
  color: var(--a-faint);
  font-style: italic;
}
.review-card.hidden .comment {
  color: var(--a-faint);
}
.photo img {
  width: 84px;
  height: 84px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid var(--a-border);
}
.hidden-note {
  margin: 0;
  padding: 7px 10px;
  border-radius: 8px;
  background: var(--a-red-soft);
  color: var(--a-red-text);
  font-size: 12px;
}
.reason {
  margin: 2px 0;
  font-size: 13px;
  color: var(--a-text-2);
  overflow-wrap: anywhere;
}
.rc-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
  flex-shrink: 0;
}
.strong {
  font-weight: 600;
  color: var(--a-text);
  font-size: 13px;
}
.link {
  text-decoration: none;
}
.link:hover {
  color: var(--a-accent-text);
  text-decoration: underline;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: var(--a-faint);
}
.sub-inline {
  font-size: 11.5px;
  color: var(--a-faint);
}
.strike-warn {
  color: var(--a-red-text);
  font-weight: 600;
}
.pill {
  display: inline-block;
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.st-pending { background: var(--a-amber-soft); color: var(--a-amber-text); }
.st-approved { background: var(--a-red-soft); color: var(--a-red-text); }
.st-rejected { background: var(--a-subtle-2); color: var(--a-muted); }
.pill.bad { background: var(--a-red-soft); color: var(--a-red-text); }

/* Pager (rendered by the Pager component) */
:deep(.pager) {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--a-border);
}
:deep(.pager-btns) {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

/* Dialog */
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.38);
  backdrop-filter: blur(2px);
  z-index: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.dialog {
  width: min(500px, 100%);
  background: var(--a-surface);
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.dialog h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--a-text);
}
.dialog .sub {
  font-size: 12.5px;
  line-height: 1.55;
  color: var(--a-muted);
}
.danger-text {
  display: block;
  margin-top: 6px;
  color: var(--a-red-text);
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.chip-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  border-radius: 20px;
  padding: 5px 11px;
  font-size: 12px;
  color: var(--a-text-2);
  cursor: pointer;
}
.chip-btn.on {
  border-color: var(--a-accent-muted);
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
}
.field .input {
  width: 100%;
  resize: vertical;
}
.form-error {
  margin: 0;
  color: var(--a-red-text);
  font-size: 12.5px;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .stat-grid {
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
  .review-card {
    flex-direction: column;
  }
  .rc-product {
    width: auto;
  }
  .rc-actions {
    flex-direction: row;
    align-items: center;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 14px 16px 32px;
  }
  .stat-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .report-card {
    flex-wrap: wrap;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
</style>
