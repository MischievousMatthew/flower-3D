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
            <h1 class="page-title">Refund Management</h1>
            <p class="page-subtitle">Money returned to customers, and the return and refund requests behind it</p>
          </div>
        </div>
        <button type="button" class="ghost-btn" :disabled="loading" @click="load">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
          {{ loading ? "Refreshing" : "Refresh" }}
        </button>
      </header>

      <div class="page-body">
        <p class="info-note">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
          View only. Customers and shops settle each request between them: they agree on a full refund, a partial refund or a replacement,
          the shop sends it, and the customer confirms. A confirmed refund is then recorded on the vendor's books for the agreed amount.
        </p>

        <section v-if="error && !data" class="panel center">
          <h2 class="panel-title">Refunds couldn't be loaded</h2>
          <button type="button" class="primary-btn" @click="load">Try again</button>
        </section>

        <template v-else>
          <!-- KPIs -->
          <section class="kpi-grid">
            <template v-if="!data">
              <div v-for="n in 5" :key="n" class="kpi"><span class="sk" style="height: 11px; width: 55%"></span><span class="sk" style="height: 22px; width: 70%; margin-top: 10px"></span></div>
            </template>
            <template v-else>
              <div class="kpi feature">
                <span class="kpi-label">Refunded in total</span>
                <span class="kpi-value">{{ money(sum.refunded_total) }}</span>
                <span class="kpi-note">{{ plural(sum.refund_count, "refund") }}</span>
              </div>
              <div class="kpi">
                <span class="kpi-label">Last 30 days</span>
                <span class="kpi-value">{{ money(sum.refunded_30d) }}</span>
                <span class="kpi-note">{{ plural(sum.refund_count_30d, "refund") }}</span>
              </div>
              <button type="button" class="kpi clickable" :class="{ warn: sum.requests.pending }" @click="status = 'open'">
                <span class="kpi-label">Open requests</span>
                <span class="kpi-value">{{ sum.requests.pending }}</span>
                <span class="kpi-note">{{ sum.oldest_open_at ? `Oldest waiting ${timeAgo(sum.oldest_open_at)}` : "Nothing waiting" }}</span>
              </button>
              <div class="kpi">
                <span class="kpi-label">Time to agree</span>
                <span class="kpi-value">{{ sum.avg_decision_hours_90d === null ? "—" : hours(sum.avg_decision_hours_90d) }}</span>
                <span class="kpi-note">Average, last 90 days</span>
              </div>
              <div class="kpi" :class="{ bad: sum.not_debited }">
                <span class="kpi-label">Not taken from vendor</span>
                <span class="kpi-value">{{ sum.not_debited }}</span>
                <span class="kpi-note">{{ sum.not_debited ? "Refunds with no vendor balance entry" : "Every refund was debited" }}</span>
              </div>
            </template>
          </section>

          <!-- Requests -->
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Return &amp; refund requests</h2>
                <p class="panel-sub">Click one to read the whole conversation. Open requests with no activity for {{ OVERDUE_DAYS }} days are flagged.</p>
              </div>
              <div class="seg">
                <button v-for="f in STATUS" :key="f.value" type="button" class="seg-btn" :class="{ active: status === f.value }" @click="status = f.value">
                  {{ f.label }}<span v-if="data && f.count" class="seg-count">{{ sum.requests[f.count] ?? 0 }}</span>
                </button>
              </div>
            </div>
            <div class="toolbar">
              <input v-model="requestSearch" type="search" class="input grow" placeholder="Order number, customer, store or reason" aria-label="Search requests" />
              <select v-model="requestType" class="input" aria-label="Type">
                <option value="">Returns and refunds</option>
                <option value="refund">Refunds only</option>
                <option value="return">Returns only</option>
              </select>
            </div>
            <div v-if="!data || (loading && requestsLoading)" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-row"></span></div>
            <p v-else-if="!filteredRequests.length" class="empty">{{ data.disputes.length ? "No request matches your search." : "No requests here." }}</p>
            <ul v-else class="row-list">
              <li v-for="d in filteredRequests" :key="d.id" tabindex="0" :class="{ selected: caseId === d.id }" @click="caseId = d.id" @keydown.enter="caseId = d.id">
                <VendorAvatar :vendor="d.vendor" :size="34" />
                <div class="row-main">
                  <span class="strong">
                    <span class="type-tag" :class="d.type">{{ d.type === "refund" ? "Refund" : "Return" }}</span>
                    <span class="mono">{{ d.order_number || "—" }}</span>
                    <span class="muted"> · {{ d.vendor.store_name }}</span>
                  </span>
                  <span class="sub ellipsis">“{{ d.reason || "No reason given" }}” — {{ d.customer || "customer" }}</span>
                  <span v-if="d.settled" class="sub ellipsis">Agreed: {{ describe(d.settled.resolution, d.settled.amount) }}</span>
                  <span v-else-if="d.offer" class="sub ellipsis">{{ d.offer.by === "vendor" ? "Shop offered" : "Customer asked for" }} {{ describe(d.offer.resolution, d.offer.amount) }}</span>
                  <span v-if="d.vendor_response && ['rejected', 'withdrawn'].includes(d.status)" class="sub ellipsis">“{{ d.vendor_response }}”</span>
                </div>
                <div class="row-pills">
                  <span class="pill" :class="`rq-${d.status}`">{{ STAGE_LABEL[d.stage] ?? d.status }}</span>
                  <span v-if="d.next_actor" class="sub">{{ d.next_actor === "vendor" ? "Shop to act" : "Customer to act" }}</span>
                  <span v-if="isOverdue(d)" class="pill overdue">Overdue</span>
                </div>
                <div class="row-right">
                  <span class="strong">{{ money(d.amount) }}</span>
                  <span class="sub">{{ d.method_label }} · {{ PAYMENT_LABEL[d.payment_status] ?? d.payment_status }}</span>
                  <span class="sub">{{ d.next_actor ? `last activity ${timeAgo(d.last_activity_at)} ago` : formatDate(d.last_activity_at) }}</span>
                </div>
              </li>
            </ul>
          </section>

          <!-- Refunds paid -->
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Refunds paid</h2>
                <p class="panel-sub">The latest 100. "Vendor debited" means the amount was taken back from the vendor's balance.</p>
              </div>
              <input v-model="refundSearch" type="search" class="input" placeholder="Order, customer or store" aria-label="Search refunds" />
            </div>
            <div v-if="!data" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row"></span></div>
            <p v-else-if="!filteredRefunds.length" class="empty">{{ data.refunds.length ? "No refund matches your search." : "No refunds yet." }}</p>
            <ul v-else class="row-list">
              <li v-for="r in filteredRefunds" :key="`${r.order_id}-${r.refunded_at}`" tabindex="0" @click="openOrder(r.order_id)" @keydown.enter="openOrder(r.order_id)">
                <VendorAvatar :vendor="r.vendor" :size="34" />
                <div class="row-main">
                  <span class="strong mono">{{ r.order_number }}</span>
                  <span class="sub">{{ r.vendor.store_name }} → {{ r.customer || "customer" }} · {{ r.method_label }}</span>
                </div>
                <span class="pill" :class="r.vendor_debited ? 'rq-approved' : 'bad'" :title="r.vendor_debited ? 'Taken back from the vendor balance' : 'No vendor balance entry found for this refund'">
                  {{ r.vendor_debited ? "Vendor debited" : "Not debited" }}
                </span>
                <div class="row-right">
                  <span class="strong neg">−{{ money(r.amount) }}</span>
                  <span class="sub">{{ formatDate(r.refunded_at) }}</span>
                </div>
              </li>
            </ul>
          </section>
        </template>
      </div>
    </main>

    <Transition name="fade"><div v-if="caseId" class="drawer-backdrop" @click="caseId = null"></div></Transition>
    <Transition name="slide">
      <aside v-if="caseId" class="drawer" role="dialog" aria-modal="true" aria-label="Return or refund request">
        <header class="drawer-head">
          <div>
            <h2>{{ activeCase?.order_number || "Return / refund" }}</h2>
            <p v-if="activeCase" class="sub">{{ activeCase.vendor?.store_name }} · {{ activeCase.customer || "customer" }}</p>
          </div>
          <div class="drawer-actions">
            <button v-if="activeCase" type="button" class="ghost-btn" @click="openOrder(activeCase.order_id, 'disputes')">Open order</button>
            <button type="button" class="ghost-btn" aria-label="Close" @click="caseId = null">✕</button>
          </div>
        </header>
        <div class="drawer-body">
          <ReturnCasePanel :case-id="caseId" role="admin" api-base="/admin/finance/refunds" />
        </div>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import ReturnCasePanel from "../../components/returns/ReturnCasePanel.vue";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();
const router = useRouter();

const OVERDUE_DAYS = 2;
const STATUS = [
  { value: "", label: "All" },
  { value: "pending", label: "Agreeing", count: "negotiating" },
  { value: "approved", label: "Agreed", count: "agreed" },
  { value: "completed", label: "Settled", count: "completed" },
  { value: "rejected", label: "Declined", count: "closed" },
];
const STAGE_LABEL = {
  awaiting_vendor: "Shop to reply",
  awaiting_customer: "Offer to customer",
  awaiting_return: "Item coming back",
  awaiting_refund: "Refund to send",
  confirm_refund: "Refund sent",
  awaiting_replacement: "Replacement to send",
  confirm_replacement: "Replacement sent",
  completed: "Settled",
  rejected: "Declined",
  withdrawn: "Withdrawn",
};
const PAYMENT_LABEL = { paid: "paid", refunded: "refunded", pending: "unpaid", unpaid: "unpaid", failed: "payment failed" };

const data = ref(null);
const loading = ref(false);
const requestsLoading = ref(false);
const error = ref(false);
const status = ref("");
const requestSearch = ref("");
const requestType = ref("");
const refundSearch = ref("");
const caseId = ref(null);
const activeCase = computed(() => data.value?.disputes?.find((d) => d.id === caseId.value) ?? null);
const sum = computed(() => data.value?.summary);

const load = async () => {
  loading.value = true;
  error.value = false;
  try {
    const res = await api.get("/admin/finance/refunds", { params: { dispute_status: status.value || undefined } });
    data.value = res.data.data;
  } catch {
    error.value = true;
    if (data.value) toast.error("Refunds couldn't be refreshed.");
  } finally {
    loading.value = false;
    requestsLoading.value = false;
  }
};
watch(status, () => {
  requestsLoading.value = true;
  load();
});

const matches = (term, ...fields) => !term || fields.some((f) => String(f ?? "").toLowerCase().includes(term));
const filteredRequests = computed(() => {
  const term = requestSearch.value.trim().toLowerCase();
  return (data.value?.disputes ?? []).filter(
    (d) => (!requestType.value || d.type === requestType.value) && matches(term, d.order_number, d.customer, d.vendor?.store_name, d.reason),
  );
});
const filteredRefunds = computed(() => {
  const term = refundSearch.value.trim().toLowerCase();
  return (data.value?.refunds ?? []).filter((r) => matches(term, r.order_number, r.customer, r.vendor?.store_name));
});
const isOverdue = (d) => !!d.next_actor && Date.now() - new Date(d.last_activity_at) > OVERDUE_DAYS * 86_400_000;
const describe = (resolution, amount) =>
  resolution === "replacement" ? "a replacement" : `a ${resolution === "full_refund" ? "full" : "partial"} refund of ${money(amount)}`;
const openOrder = (id, tab) => router.push({ path: "/admin/orders", query: { order: id, ...(tab ? { tab } : {}) } });

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => peso.format(Number(v || 0));
const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const hours = (h) => (h < 48 ? `${Math.round(h)} h` : `${(h / 24).toFixed(1)} days`);
const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
const timeAgo = (v) => {
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "under a minute" : `${minutes} min`;
  const h = Math.floor(minutes / 60);
  if (h < 24) return `${h} h`;
  const days = Math.floor(h / 24);
  return `${days} day${days === 1 ? "" : "s"}`;
};

onMounted(load);
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
}
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
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
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
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
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
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
}
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.ghost-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.info-note {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  margin: 0;
  padding: 11px 14px;
  font-size: 12.5px;
  line-height: 1.5;
  color: #475569;
  background: #eef2ff;
  border-radius: 12px;
}
.info-note svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: #6d5ff0;
}

/* Skeleton */
.sk {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, #eef1f6 30%, #f8fafc 50%, #eef1f6 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk-row {
  height: 56px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* KPIs */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid #e9edf3;
  background: #fff;
  text-align: left;
  min-width: 0;
}
.kpi.clickable {
  cursor: pointer;
  font: inherit;
}
.kpi.clickable:hover {
  border-color: #c7c2fb;
}
.kpi.feature {
  background: radial-gradient(120% 140% at 100% 0%, #8f84ff 0%, #6d5ff0 45%, #4c3fd0 100%);
  border-color: transparent;
}
.kpi.feature * {
  color: #fff !important;
}
.kpi.warn {
  border-color: #fde68a;
  background: #fffbeb;
}
.kpi.bad {
  border-color: #fecaca;
  background: #fff7f7;
}
.kpi-label {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
}
.kpi-value {
  font-size: 21px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.kpi-note {
  font-size: 11.5px;
  color: #94a3b8;
}

/* Panels */
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel.center {
  text-align: center;
  padding: 40px;
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
  color: #1e2739;
  margin: 0 0 4px;
}
.panel-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
}
.seg {
  display: inline-flex;
  padding: 3px;
  gap: 2px;
  border-radius: 10px;
  background: #f1f5f9;
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
  color: #64748b;
  cursor: pointer;
}
.seg-btn.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.seg-count {
  font-size: 11px;
  padding: 0 6px;
  border-radius: 10px;
  background: #e2e8f0;
  color: #475569;
}
.toolbar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.grow {
  flex: 1 1 240px;
}
.input {
  padding: 8px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.input:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.empty {
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
  padding: 24px 0;
  margin: 0;
}

/* Rows */
.row-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid #eef1f6;
  border-radius: 12px;
  overflow: hidden;
}
.row-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.12s;
}
.row-list li:last-child {
  border-bottom: none;
}
.row-list li:hover,
.row-list li:focus-visible {
  background: #fafaff;
  outline: none;
}
.row-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.row-pills {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.row-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
  white-space: nowrap;
  min-width: 120px;
}
.strong {
  font-weight: 600;
  color: #1e2739;
  font-size: 13px;
}
.strong.neg {
  color: #be123c;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.muted {
  color: #94a3b8;
  font-weight: 400;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 12px;
}
.ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.type-tag {
  display: inline-block;
  margin-right: 6px;
  padding: 1px 7px;
  border-radius: 6px;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.type-tag.refund {
  background: #fce7f3;
  color: #be185d;
}
.type-tag.return {
  background: #e0f2fe;
  color: #0369a1;
}
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.rq-pending { background: #fef3c7; color: #b45309; }
.rq-approved { background: #dcfce7; color: #15803d; }
.rq-rejected { background: #f1f5f9; color: #64748b; }
.pill.bad,
.pill.overdue { background: #fee2e2; color: #b91c1c; }

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .kpi-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 14px 16px 32px;
  }
  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .row-list li {
    flex-wrap: wrap;
  }
  .row-pills {
    flex-direction: row;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}

.row-list li.selected {
  background: #f5f3ff;
}
.rq-completed { background: #dcfce7; color: #15803d; }
.rq-withdrawn { background: #f1f5f9; color: #64748b; }
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.32);
  z-index: 300;
}
.drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(620px, 100vw);
  z-index: 301;
  display: flex;
  flex-direction: column;
  background: #fff;
  box-shadow: -12px 0 40px rgba(15, 23, 42, 0.18);
  --return-accent: #6d5ff0;
  --return-accent-soft: #f1efff;
}
.drawer-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 20px;
  border-bottom: 1px solid #eef1f6;
}
.drawer-head h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e2739;
}
.drawer-actions {
  display: flex;
  gap: 6px;
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 18px 20px 28px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
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
</style>
