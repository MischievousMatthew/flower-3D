<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <div>
            <h1 class="page-title">Order Management</h1>
            <p class="page-subtitle">Every order across all vendors</p>
          </div>
        </div>
        <div class="header-right">
          <span class="readonly-chip" title="Status, payment and delivery changes stay with the vendor's own checks">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
            View only
          </span>
          <button type="button" class="ghost-btn" :disabled="listLoading" @click="refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            <span>{{ listLoading ? "Refreshing" : "Refresh" }}</span>
          </button>
        </div>
      </header>

      <div class="page-body">
        <!-- Status cards double as tabs -->
        <section class="bucket-grid" role="tablist" aria-label="Order status">
          <template v-if="!summary">
            <div v-for="n in 8" :key="n" class="bucket-card">
              <div class="sk sk-line" style="width: 55%"></div>
              <div class="sk sk-line" style="width: 30%; height: 22px; margin-top: 10px"></div>
            </div>
          </template>
          <template v-else>
            <button
              v-for="bucket in buckets"
              :key="bucket.key"
              type="button"
              role="tab"
              class="bucket-card"
              :class="[`tone-${bucket.tone}`, { active: filters.bucket === bucket.key }]"
              :aria-selected="filters.bucket === bucket.key"
              @click="setBucket(bucket.key)"
            >
              <span class="bucket-top">
                <span class="bucket-dot"></span>
                <span class="bucket-label">{{ bucket.label }}</span>
              </span>
              <span class="bucket-count">{{ bucket.count }}</span>
              <span class="bucket-hint">{{ bucket.hint }}</span>
            </button>
          </template>
        </section>

        <!-- What "needs attention" means, filterable -->
        <section v-if="filters.bucket === 'issues' && summary" class="issue-strip">
          <button
            type="button"
            class="issue-chip"
            :class="{ active: !filters.issue }"
            @click="filters.issue = ''"
          >
            All issues <b>{{ summary.issues }}</b>
          </button>
          <button
            v-for="rule in summary.issue_breakdown.filter((r) => r.count)"
            :key="rule.key"
            type="button"
            class="issue-chip"
            :class="[`sev-${rule.severity}`, { active: filters.issue === rule.key }]"
            @click="filters.issue = filters.issue === rule.key ? '' : rule.key"
          >
            <span class="sev-dot"></span>
            {{ rule.label }} <b>{{ rule.count }}</b>
          </button>
        </section>

        <section class="panel">
          <!-- Toolbar -->
          <div class="toolbar">
            <div class="search-wrap">
              <svg class="search-ico" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
              <input
                v-model="filters.search"
                type="search"
                class="search-input"
                placeholder="Search order no., customer, vendor or status"
                aria-label="Search orders"
              />
            </div>
            <div class="toolbar-filters">
              <select v-model="filters.payment_status" class="select" aria-label="Payment status">
                <option value="">Any payment</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="refunded">Refunded</option>
                <option value="failed">Failed</option>
              </select>
              <select v-model="filters.payment_method" class="select" aria-label="Payment method">
                <option value="">Any method</option>
                <option v-for="(label, value) in METHOD_LABELS" :key="value" :value="value">{{ label }}</option>
              </select>
              <label class="date-field">
                <span>From</span>
                <input v-model="filters.from" type="date" class="select" :max="filters.to || undefined" />
              </label>
              <label class="date-field">
                <span>To</span>
                <input v-model="filters.to" type="date" class="select" :min="filters.from || undefined" />
              </label>
              <select v-model="filters.sort" class="select" aria-label="Sort">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="amount_high">Highest total</option>
                <option value="amount_low">Lowest total</option>
              </select>
              <button v-if="hasFilters" type="button" class="link-btn" @click="clearFilters">Clear filters</button>
            </div>
          </div>

          <!-- Orders table -->
          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Vendor</th>
                  <th class="num">Total</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Flags</th>
                  <th aria-label="Open"></th>
                </tr>
              </thead>
              <tbody v-if="listLoading && !orders.length">
                <tr v-for="n in 8" :key="n">
                  <td v-for="c in 8" :key="c"><div class="sk sk-line"></div></td>
                </tr>
              </tbody>
              <tbody v-else-if="listError && !orders.length">
                <tr>
                  <td colspan="8" class="empty-cell">
                    The orders couldn't be loaded.
                    <button type="button" class="link-btn" @click="orderList.refresh()">Try again</button>
                  </td>
                </tr>
              </tbody>
              <tbody v-else-if="!orders.length">
                <tr>
                  <td colspan="8" class="empty-cell">
                    {{ hasFilters ? "No orders match these filters." : "No orders yet." }}
                  </td>
                </tr>
              </tbody>
              <tbody v-else :class="{ dim: listLoading }">
                <tr
                  v-for="order in orderList.rows.value"
                  :key="order.id"
                  class="order-row"
                  :class="{ selected: drawer.id === order.id }"
                  tabindex="0"
                  @click="openOrder(order.id)"
                  @keydown.enter="openOrder(order.id)"
                >
                  <td>
                    <span class="order-no">{{ order.order_number }}</span>
                    <span class="sub">{{ formatDateTime(order.created_at) }}</span>
                  </td>
                  <td>
                    <span class="cell-main">{{ order.customer.name || "—" }}</span>
                    <span class="sub">{{ order.customer.email }}</span>
                  </td>
                  <td>
                    <div class="vendor-cell">
                      <VendorAvatar :vendor="order.vendor" />
                      <span class="cell-main">{{ order.vendor.store_name }}</span>
                    </div>
                  </td>
                  <td class="num">
                    <span class="cell-main strong">{{ money(order.total_amount) }}</span>
                    <span class="sub">{{ plural(order.items_count, "item") }}</span>
                  </td>
                  <td>
                    <span class="pill" :class="`pay-${order.payment_status}`">{{ PAYMENT_LABELS[order.payment_status] ?? order.payment_status }}</span>
                    <span class="sub">{{ METHOD_LABELS[order.payment_method] ?? order.payment_method }}</span>
                  </td>
                  <td>
                    <span class="pill" :class="`st-${order.status}`">{{ STATUS_LABELS[order.status] ?? order.status }}</span>
                  </td>
                  <td>
                    <div class="flags">
                      <span v-if="order.open_requests" class="flag flag-dispute" :title="`${order.open_requests} open return/refund request`">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
                        Dispute
                      </span>
                      <span
                        v-for="issue in order.issues.filter((i) => i.key !== 'open_dispute')"
                        :key="issue.key"
                        class="flag"
                        :class="`sev-${issue.severity}`"
                        :title="issue.label"
                      >
                        <span class="sev-dot"></span>{{ ISSUE_SHORT[issue.key] ?? "Issue" }}
                      </span>
                      <span v-if="!order.issues.length && !order.open_requests" class="sub">—</span>
                    </div>
                  </td>
                  <td class="chev">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6" /></svg>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <ShowMore v-if="orders.length" tone="admin" :has-more="orderList.hasMore.value" :loading="orderList.loadingMore.value" :shown="orderList.rows.value.length" :total="orderList.total.value" @more="orderList.showMore()" />
        </section>
      </div>
    </main>

    <!-- Order detail drawer -->
    <Transition name="fade">
      <div v-if="drawer.id" class="drawer-backdrop" @click="closeOrder"></div>
    </Transition>
    <Transition name="slide">
      <aside
        v-if="drawer.id"
        ref="drawerEl"
        class="drawer"
        role="dialog"
        aria-modal="true"
        :aria-label="`Order ${detail?.order_number ?? ''}`"
        tabindex="-1"
        @keydown.esc="closeOrder"
      >
        <header class="drawer-head">
          <div class="drawer-title">
            <template v-if="detail">
              <h2>{{ detail.order_number }}</h2>
              <div class="drawer-pills">
                <span class="pill" :class="`st-${detail.status}`">{{ STATUS_LABELS[detail.status] ?? detail.status }}</span>
                <span class="pill" :class="`pay-${detail.payment_status}`">{{ PAYMENT_LABELS[detail.payment_status] ?? detail.payment_status }}</span>
                <span class="sub">Placed {{ formatDateTime(detail.created_at) }}</span>
              </div>
            </template>
            <template v-else>
              <div class="sk sk-line" style="width: 220px; height: 20px"></div>
              <div class="sk sk-line" style="width: 160px"></div>
            </template>
          </div>
          <button type="button" class="icon-btn" aria-label="Close" @click="closeOrder">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
          </button>
        </header>

        <div v-if="drawer.error" class="drawer-body">
          <p class="empty-msg">{{ drawer.error }}</p>
          <button type="button" class="primary-btn" @click="loadDetail(drawer.id)">Try again</button>
        </div>

        <template v-else>
          <nav class="drawer-tabs" role="tablist">
            <button
              v-for="t in drawerTabs"
              :key="t.key"
              type="button"
              role="tab"
              class="drawer-tab"
              :class="{ active: drawer.tab === t.key }"
              :aria-selected="drawer.tab === t.key"
              @click="drawer.tab = t.key"
            >
              {{ t.label }}
              <span v-if="t.count" class="tab-count" :class="{ warn: t.warn }">{{ t.count }}</span>
            </button>
          </nav>

          <div class="drawer-body">
            <!-- Loading -->
            <template v-if="!detail">
              <div v-for="n in 4" :key="n" class="card">
                <div class="sk sk-line" style="width: 40%"></div>
                <div class="sk sk-line"></div>
                <div class="sk sk-line" style="width: 70%"></div>
              </div>
            </template>

            <template v-else>
              <!-- Issues banner -->
              <div v-if="detail.issues.length && drawer.tab === 'overview'" class="alert">
                <div class="alert-head">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>
                  Needs attention
                </div>
                <ul>
                  <li v-for="issue in detail.issues" :key="issue.key" :class="`sev-${issue.severity}`">
                    <span class="sev-dot"></span>{{ issue.label }}
                  </li>
                </ul>
              </div>

              <!-- Overview -->
              <template v-if="drawer.tab === 'overview'">
                <div class="card-grid">
                  <div class="card">
                    <span class="card-label">Customer</span>
                    <span class="card-main">{{ detail.customer.name || "—" }}</span>
                    <span class="sub">{{ detail.customer.email || "No email" }}</span>
                    <span v-if="detail.customer.contact_number" class="sub">{{ detail.customer.contact_number }}</span>
                  </div>
                  <div class="card">
                    <span class="card-label">Vendor</span>
                    <div class="vendor-cell">
                      <VendorAvatar :vendor="detail.vendor" />
                      <div>
                        <span class="card-main">{{ detail.vendor.store_name }}</span>
                        <span class="sub">{{ detail.vendor.email || detail.vendor.name }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card">
                  <span class="card-label">Delivery</span>
                  <dl class="facts">
                    <dt>Recipient</dt>
                    <dd>{{ detail.delivery.contact_name || "—" }} <span class="sub-inline">{{ detail.delivery.contact_number }}</span></dd>
                    <dt>Address</dt>
                    <dd>{{ detail.delivery.address || "—" }}</dd>
                    <template v-if="detail.delivery.reservation_date">
                      <dt>Requested for</dt>
                      <dd>{{ formatDate(detail.delivery.reservation_date) }}</dd>
                    </template>
                    <dt>Delivery status</dt>
                    <dd>{{ detail.delivery.status ? humanize(detail.delivery.status) : "Not started" }}</dd>
                    <template v-if="detail.delivery.barcode">
                      <dt>Tracking code</dt>
                      <dd class="mono">{{ detail.delivery.barcode }}</dd>
                    </template>
                  </dl>
                  <p v-if="detail.customer_notes" class="quote">“{{ detail.customer_notes }}”</p>
                </div>

                <div class="card">
                  <span class="card-label">Items</span>
                  <ul class="item-list">
                    <li v-for="item in detail.items" :key="item.id">
                      <img v-if="item.image" :src="item.image" alt="" class="item-img" loading="lazy" />
                      <span v-else class="item-img placeholder">🌸</span>
                      <div class="item-info">
                        <span class="card-main">{{ item.name }}</span>
                        <span class="sub">
                          {{ item.quantity }} × {{ money(item.unit_price) }}
                          <template v-if="item.size"> · {{ item.size }}</template>
                          <template v-if="item.color"> · {{ item.color }}</template>
                        </span>
                        <div v-if="item.custom" class="custom-tags">
                          <span v-if="item.custom.wrapper" class="tag">Wrapper: {{ item.custom.wrapper }}</span>
                          <span v-if="item.custom.ribbon" class="tag">Ribbon: {{ item.custom.ribbon }}</span>
                          <span v-for="filler in item.custom.fillers ?? []" :key="filler" class="tag">{{ filler }}</span>
                          <span v-if="item.custom.card" class="tag">Card: {{ item.custom.card.name }} ({{ item.custom.card.size }})</span>
                        </div>
                        <p v-if="item.custom?.card?.message" class="quote small">“{{ item.custom.card.message }}”</p>
                        <p v-if="item.notes" class="sub">Note: {{ item.notes }}</p>
                      </div>
                      <span class="item-price">{{ money(item.subtotal) }}</span>
                    </li>
                  </ul>
                </div>

                <div class="card">
                  <span class="card-label">Payment</span>
                  <dl class="amounts">
                    <dt>Subtotal</dt><dd>{{ money(detail.amounts.subtotal) }}</dd>
                    <template v-if="detail.amounts.delivery_fee"><dt>Delivery fee</dt><dd>{{ money(detail.amounts.delivery_fee) }}</dd></template>
                    <template v-if="detail.amounts.tax"><dt>Tax</dt><dd>{{ money(detail.amounts.tax) }}</dd></template>
                    <template v-if="detail.amounts.discount"><dt>Discount</dt><dd>−{{ money(detail.amounts.discount) }}</dd></template>
                    <dt class="total">Total</dt><dd class="total">{{ money(detail.amounts.total) }}</dd>
                  </dl>
                  <dl class="facts">
                    <dt>Method</dt>
                    <dd>{{ METHOD_LABELS[detail.payment_method] ?? detail.payment_method }}</dd>
                    <dt>Paid</dt>
                    <dd>{{ detail.payment.paid_at ? formatDateTime(detail.payment.paid_at) : "Not confirmed" }}</dd>
                    <template v-if="detail.payment.paymongo_payment_intent_id || detail.payment.paymongo_source_id">
                      <dt>PayMongo ref.</dt>
                      <dd class="mono">{{ detail.payment.paymongo_payment_intent_id || detail.payment.paymongo_source_id }}</dd>
                    </template>
                  </dl>
                </div>

                <div v-if="detail.transactions.length" class="card">
                  <span class="card-label">Vendor balance entries</span>
                  <ul class="txn-list">
                    <li v-for="t in detail.transactions" :key="t.id">
                      <div>
                        <span class="card-main">{{ t.description || humanize(t.category) }}</span>
                        <span class="sub">{{ formatDateTime(t.created_at) }} · {{ humanize(t.status) }}</span>
                      </div>
                      <span class="txn-amount" :class="t.type === 'debit' ? 'neg' : 'pos'">
                        {{ t.type === "debit" ? "−" : "+" }}{{ money(t.amount) }}
                      </span>
                    </li>
                  </ul>
                </div>
              </template>

              <!-- Timeline -->
              <ol v-else-if="drawer.tab === 'timeline'" class="timeline">
                <li v-for="(event, index) in detail.timeline" :key="index" :class="`ev-${event.kind}`">
                  <span class="ev-dot"></span>
                  <div class="ev-body">
                    <span class="card-main">{{ event.title }}</span>
                    <span v-if="event.detail" class="ev-detail">{{ event.detail }}</span>
                    <span class="sub">{{ formatDateTime(event.at) }}</span>
                  </div>
                </li>
                <li v-if="!detail.timeline.length" class="empty-msg">Nothing recorded yet.</li>
              </ol>

              <!-- Disputes -->
              <template v-else-if="drawer.tab === 'disputes'">
                <p class="info-note">
                  Return and refund requests are decided by the vendor, so refunds and stock go through their checks.
                  Use notes to record what you find.
                </p>
                <p v-if="!detail.requests.length" class="empty-msg">No return or refund requests on this order.</p>
                <article v-for="r in detail.requests" :key="r.id" class="card dispute">
                  <div class="dispute-head">
                    <span class="card-main">{{ humanize(r.type) }} request</span>
                    <span class="pill" :class="`req-${r.status}`">{{ humanize(r.status) }}</span>
                  </div>
                  <span class="sub">From {{ r.customer || "the customer" }} · {{ formatDateTime(r.created_at) }}</span>
                  <p class="quote">“{{ r.reason }}”</p>
                  <a v-if="r.media_url && r.media_type === 'image'" :href="r.media_url" target="_blank" rel="noopener" class="evidence">
                    <img :src="r.media_url" alt="Photo the customer attached" />
                  </a>
                  <a v-else-if="r.media_url" :href="r.media_url" target="_blank" rel="noopener" class="link-btn">Open attached file</a>
                  <div v-if="r.status !== 'pending'" class="vendor-reply">
                    <span class="card-label">Vendor's reply · {{ formatDateTime(r.updated_at) }}</span>
                    <p>{{ r.vendor_response || "No note left." }}</p>
                  </div>
                  <span v-else class="sub waiting">Waiting for the vendor since {{ timeAgo(r.created_at) }}</span>
                </article>
              </template>

              <!-- Notes -->
              <template v-else-if="drawer.tab === 'notes'">
                <form class="note-form" @submit.prevent="addNote">
                  <label for="admin-note" class="card-label">Private note</label>
                  <textarea
                    id="admin-note"
                    v-model="noteDraft"
                    rows="3"
                    maxlength="2000"
                    placeholder="What did you check, who did you contact, what's next?"
                  ></textarea>
                  <div class="note-actions">
                    <span class="sub">Only admins see these notes. They don't change the order.</span>
                    <button type="submit" class="primary-btn" :disabled="!noteDraft.trim() || savingNote">
                      {{ savingNote ? "Saving…" : "Add note" }}
                    </button>
                  </div>
                </form>
                <p v-if="!detail.notes.length" class="empty-msg">No notes yet.</p>
                <ul v-else class="note-list">
                  <li v-for="note in detail.notes" :key="note.id">
                    <div class="note-meta">
                      <span class="note-avatar">{{ (note.author || "A").charAt(0) }}</span>
                      <span class="card-main">{{ note.author }}</span>
                      <span class="sub">{{ formatDateTime(note.created_at) }}</span>
                    </div>
                    <p>{{ note.body }}</p>
                  </li>
                </ul>
              </template>
            </template>
          </div>
        </template>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMoreRows } from "../../composables/useShowMore";
import { linkedId } from "../../utils/hiddenIds";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

const STATUS_LABELS = {
  pending: "Pending",
  processing: "Processing",
  out_for_delivery: "Out for delivery",
  completed: "Completed",
  cancelled: "Cancelled",
  failed: "Failed",
  returned: "Returned",
  refunded: "Refunded",
};
const PAYMENT_LABELS = { paid: "Paid", unpaid: "Unpaid", refunded: "Refunded", failed: "Failed" };
const METHOD_LABELS = { cod: "Cash on delivery", ewallet: "E-wallet", bank_transfer: "Bank transfer", gcash: "GCash", maya: "Maya", card: "Card" };
const ISSUE_SHORT = {
  paid_not_refunded: "No refund",
  completed_unpaid: "Unpaid",
  stock_not_restored: "Stock",
  stale_pending: "Stuck",
  stale_in_progress: "Stalled",
};

// ── Summary ──────────────────────────────────────────────────────
const summary = ref(null);

const buckets = computed(() => {
  const s = summary.value;
  if (!s) return [];
  return [
    { key: "", label: "All orders", count: s.total, hint: `${s.today} today`, tone: "slate" },
    { key: "pending", label: "Pending", count: s.buckets.pending, hint: "Waiting on vendor", tone: "amber" },
    { key: "processing", label: "Processing", count: s.buckets.processing, hint: "Preparing or on the way", tone: "blue" },
    { key: "completed", label: "Completed", count: s.buckets.completed, hint: "Delivered", tone: "green" },
    { key: "cancelled", label: "Cancelled", count: s.buckets.cancelled, hint: "Incl. failed payments", tone: "gray" },
    { key: "refunded", label: "Refunded", count: s.buckets.refunded, hint: "Refunded or returned", tone: "violet" },
    { key: "disputes", label: "Disputes", count: s.open_disputes, hint: "Open return/refund requests", tone: "rose" },
    { key: "issues", label: "Needs attention", count: s.issues, hint: "Flagged automatically", tone: "red" },
  ];
});

const fetchSummary = async () => {
  try {
    const { data } = await api.get("/admin/orders/summary");
    summary.value = data.data;
  } catch {
    toast.error("The order totals couldn't be loaded.");
  }
};

// ── List ─────────────────────────────────────────────────────────
const blankFilters = () => ({
  bucket: "",
  issue: "",
  search: "",
  payment_status: "",
  payment_method: "",
  from: "",
  to: "",
  sort: "newest",
});
const filters = reactive({ ...blankFilters(), bucket: typeof route.query.tab === "string" ? route.query.tab : "" });
const orders = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const listLoading = ref(false);
const listError = ref(false);

const hasFilters = computed(() =>
  Boolean(filters.search.trim() || filters.issue || filters.payment_status || filters.payment_method || filters.from || filters.to || filters.bucket),
);

let listRequest = 0;
const fetchOrders = async (page = 1) => {
  const id = ++listRequest;
  listLoading.value = true;
  listError.value = false;
  try {
    const params = Object.fromEntries(
      Object.entries({ ...filters, search: filters.search.trim(), page, per_page: 15 }).filter(([, v]) => v !== "" && v !== null),
    );
    const { data } = await api.get("/admin/orders", { params });
    if (id !== listRequest) return;
    orders.value = page > 1 ? [...orders.value, ...(data.data ?? [])] : data.data;
    meta.value = data.meta;
  } catch {
    if (id !== listRequest) return;
    listError.value = true;
    if (orders.value.length) toast.error("The orders couldn't be refreshed.");
  } finally {
    if (id === listRequest) listLoading.value = false;
  }
};
// 10 rows first, then 10 more, then 20 at a time
const orderList = useShowMoreRows(orders, meta, fetchOrders);

const setBucket = (key) => {
  filters.bucket = filters.bucket === key ? "" : key;
  filters.issue = "";
};

const clearFilters = () => Object.assign(filters, blankFilters());

const refresh = () => {
  fetchSummary();
  orderList.refresh();
  if (drawer.id) loadDetail(drawer.id);
};

let searchTimer = null;
watch(
  () => ({ ...filters }),
  (now, before) => {
    clearTimeout(searchTimer);
    const delay = now.search !== before.search ? 300 : 0;
    searchTimer = setTimeout(() => orderList.load(), delay);
    if (now.bucket !== before.bucket) {
      router.replace({ query: { ...route.query, tab: now.bucket || undefined } });
    }
  },
);

// ── Detail drawer ────────────────────────────────────────────────
const drawer = reactive({ id: null, tab: "overview", error: "" });
const detail = ref(null);
const drawerEl = ref(null);
const noteDraft = ref("");
const savingNote = ref(false);
let lastFocused = null;

const drawerTabs = computed(() => [
  { key: "overview", label: "Overview" },
  { key: "timeline", label: "Timeline", count: detail.value?.timeline.length },
  {
    key: "disputes",
    label: "Disputes",
    count: detail.value?.requests.length,
    warn: detail.value?.requests.some((r) => r.status === "pending"),
  },
  { key: "notes", label: "Notes", count: detail.value?.notes.length },
]);

let detailRequest = 0;
const loadDetail = async (id) => {
  const requestId = ++detailRequest;
  drawer.error = "";
  try {
    const { data } = await api.get(`/admin/orders/${id}`);
    if (requestId === detailRequest) detail.value = data.data;
  } catch (error) {
    if (requestId !== detailRequest) return;
    drawer.error = error.response?.status === 404 ? "This order no longer exists." : "The order couldn't be loaded.";
  }
};

const openOrder = async (id, tab = "overview") => {
  if (!drawer.id) lastFocused = document.activeElement;
  drawer.id = id;
  drawer.tab = tab;
  detail.value = null;
  noteDraft.value = "";
  router.replace({ query: { ...route.query, order: id } });
  loadDetail(id);
  await nextTick();
  drawerEl.value?.focus();
};

const closeOrder = () => {
  drawer.id = null;
  detail.value = null;
  detailRequest++;
  router.replace({ query: { ...route.query, order: null } });
  lastFocused?.focus?.();
};

const addNote = async () => {
  const body = noteDraft.value.trim();
  if (!body || !detail.value) return;
  savingNote.value = true;
  try {
    const { data } = await api.post(`/admin/orders/${detail.value.id}/notes`, { body });
    detail.value.notes.unshift(data.data);
    noteDraft.value = "";
    toast.success("Note added.");
  } catch (error) {
    toast.error(error.response?.data?.message || "The note couldn't be saved.");
  } finally {
    savingNote.value = false;
  }
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => (v === null || v === undefined ? "—" : peso.format(Number(v)));
const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const humanize = (s) => (s ? String(s).replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "—");
const formatDate = (v) =>
  v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—";
const formatDateTime = (v) =>
  v
    ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" })
    : "—";
const timeAgo = (v) => {
  const days = Math.floor((Date.now() - new Date(v)) / 86_400_000);
  if (days < 1) return "today";
  if (days === 1) return "yesterday";
  if (days < 31) return `${days} days ago`;
  const months = Math.floor(days / 30);
  return months === 1 ? "a month ago" : `${months} months ago`;
};

// ── Lifecycle ────────────────────────────────────────────────────
const onKeydown = (event) => {
  if (event.key === "Escape" && drawer.id) closeOrder();
};

onMounted(() => {
  fetchSummary();
  orderList.load();
  const fromLink = linkedId("order");
  if (fromLink) openOrder(fromLink);
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

/* ── Layout ─────────────────────────────────────────── */
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
.page-body > section {
  margin-top: 20px;
}

/* ── Header ─────────────────────────────────────────── */
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
.readonly-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 11px;
  border-radius: 20px;
  background: #f1f5f9;
  color: #475569;
  font-size: 12px;
  font-weight: 500;
}

/* ── Buttons ────────────────────────────────────────── */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 13px;
  border-radius: 8px;
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
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.primary-btn:hover:not(:disabled) {
  background: #5b4fc9;
}
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
  padding: 4px 6px;
  text-decoration: none;
}
.link-btn:hover {
  text-decoration: underline;
}
.icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.icon-btn:hover {
  color: #1e2739;
  border-color: #cbd5e1;
}

/* ── Skeleton ───────────────────────────────────────── */
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
@keyframes shimmer {
  from {
    background-position: 100% 0;
  }
  to {
    background-position: 0 0;
  }
}

/* ── Status cards ───────────────────────────────────── */
.bucket-grid {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 12px;
}
.bucket-card {
  --tone: #64748b;
  --tone-bg: #f1f5f9;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 14px 14px 12px;
  border-radius: 12px;
  border: 1px solid #e9edf3;
  background: #fff;
  text-align: left;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.bucket-card::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 3px;
  background: var(--tone);
  opacity: 0;
  transition: opacity 0.15s;
}
.bucket-card:hover {
  border-color: #d7dce5;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
  transform: translateY(-1px);
}
.bucket-card.active {
  border-color: var(--tone);
  background: var(--tone-bg);
}
.bucket-card.active::after {
  opacity: 1;
}
.bucket-top {
  display: flex;
  align-items: center;
  gap: 7px;
}
.bucket-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--tone);
}
.bucket-label {
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  white-space: nowrap;
}
.bucket-count {
  font-size: 24px;
  font-weight: 700;
  color: #1e2739;
  line-height: 1.25;
  margin-top: 6px;
  font-variant-numeric: tabular-nums;
}
.bucket-hint {
  font-size: 11px;
  color: #94a3b8;
  line-height: 1.3;
}
.tone-slate { --tone: #475569; --tone-bg: #f8fafc; }
.tone-amber { --tone: #f59e0b; --tone-bg: #fffbeb; }
.tone-blue { --tone: #3b82f6; --tone-bg: #eff6ff; }
.tone-green { --tone: #22c55e; --tone-bg: #f0fdf4; }
.tone-gray { --tone: #94a3b8; --tone-bg: #f8fafc; }
.tone-violet { --tone: #8b5cf6; --tone-bg: #f5f3ff; }
.tone-rose { --tone: #f43f5e; --tone-bg: #fff1f2; }
.tone-red { --tone: #dc2626; --tone-bg: #fef2f2; }

/* ── Issue strip ────────────────────────────────────── */
.issue-strip {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.issue-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 12px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 12px;
  color: #475569;
  cursor: pointer;
}
.issue-chip b {
  font-weight: 600;
  color: #1e2739;
}
.issue-chip.active {
  border-color: #7c6ff7;
  background: #f5f3ff;
  color: #4c3fd0;
}
.sev-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #94a3b8;
  flex-shrink: 0;
}
.sev-high .sev-dot { background: #dc2626; }
.sev-medium .sev-dot { background: #f59e0b; }
.sev-low .sev-dot { background: #94a3b8; }

/* ── Panel + toolbar ────────────────────────────────── */
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
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
  flex: 1 1 280px;
  max-width: 380px;
}
.search-ico {
  position: absolute;
  left: 12px;
  color: #94a3b8;
  pointer-events: none;
}
.search-input,
.select {
  padding: 9px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.search-input {
  width: 100%;
  padding-left: 35px;
  background: #f8fafc;
}
.search-input:focus,
.select:focus {
  outline: none;
  border-color: #7c6ff7;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.date-field {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #94a3b8;
}
.date-field .select {
  padding: 7px 9px;
}

/* ── Table ──────────────────────────────────────────── */
.table-scroll {
  overflow-x: auto;
  margin: 0 -6px;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.data-table th {
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 9px 10px;
  background: #f8fafc;
  white-space: nowrap;
}
.data-table th:first-child { border-radius: 8px 0 0 8px; }
.data-table th:last-child { border-radius: 0 8px 8px 0; }
.data-table td {
  padding: 12px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  white-space: nowrap;
  color: #1e2739;
}
.data-table tbody.dim {
  opacity: 0.55;
  transition: opacity 0.2s;
}
.order-row {
  cursor: pointer;
  transition: background 0.12s;
}
.order-row:hover td,
.order-row:focus-visible td {
  background: #fafaff;
}
.order-row:focus-visible {
  outline: 2px solid #7c6ff7;
  outline-offset: -2px;
}
.order-row.selected td {
  background: #f5f3ff;
}
.order-no {
  display: block;
  font-weight: 600;
  font-size: 12.5px;
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  color: #1e2739;
}
.cell-main {
  display: block;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 220px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.sub-inline {
  font-size: 12px;
  color: #94a3b8;
  margin-left: 4px;
}
.num {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}
.strong {
  font-weight: 600;
}
.chev {
  color: #cbd5e1;
  width: 28px;
}
.order-row:hover .chev {
  color: #7c6ff7;
}
.empty-cell {
  text-align: center;
  color: #94a3b8 !important;
  padding: 40px 10px !important;
}
.vendor-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}

/* ── Pills & flags ──────────────────────────────────── */
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}
.st-pending { background: #fef3c7; color: #b45309; }
.st-processing { background: #dbeafe; color: #1d4ed8; }
.st-out_for_delivery { background: #e0f2fe; color: #0369a1; }
.st-completed { background: #dcfce7; color: #15803d; }
.st-cancelled,
.st-failed { background: #f1f5f9; color: #64748b; }
.st-returned,
.st-refunded { background: #ede9fe; color: #6d28d9; }
.pay-paid { background: #dcfce7; color: #15803d; }
.pay-unpaid { background: #fff7ed; color: #c2410c; }
.pay-refunded { background: #ede9fe; color: #6d28d9; }
.pay-failed { background: #fee2e2; color: #b91c1c; }
.req-pending { background: #fef3c7; color: #b45309; }
.req-approved { background: #dcfce7; color: #15803d; }
.req-rejected { background: #f1f5f9; color: #64748b; }
.pay-paid,
.pay-unpaid,
.pay-refunded,
.pay-failed {
  margin-bottom: 3px;
}
.flags {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.flag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  background: #f8fafc;
  border: 1px solid #eef1f6;
  color: #475569;
}
.flag.sev-high {
  background: #fef2f2;
  border-color: #fee2e2;
  color: #b91c1c;
}
.flag-dispute {
  background: #fff1f2;
  border-color: #ffe4e6;
  color: #be123c;
}

/* ── Pager ──────────────────────────────────────────── */
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
  flex-wrap: wrap;
}
.pager-info,
.page-num {
  font-size: 12px;
  color: #64748b;
}
.pager-btns {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ── Drawer ─────────────────────────────────────────── */
.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(2px);
  z-index: 300;
}
.drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(600px, 100vw);
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
  justify-content: space-between;
  gap: 12px;
  padding: 20px 22px 14px;
  background: #fff;
}
.drawer-title h2 {
  margin: 0 0 8px;
  font-size: 17px;
  font-weight: 600;
  color: #1e2739;
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
}
.drawer-pills {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.drawer-pills .sub {
  display: inline;
  margin-left: 4px;
}
.drawer-tabs {
  display: flex;
  gap: 2px;
  padding: 0 16px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  overflow-x: auto;
}
.drawer-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 11px 10px;
  border: none;
  background: none;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  white-space: nowrap;
}
.drawer-tab.active {
  color: #5b4fc9;
  border-bottom-color: #7c6ff7;
}
.tab-count {
  font-size: 10.5px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 10px;
  background: #f1f5f9;
  color: #64748b;
}
.tab-count.warn {
  background: #ffe4e6;
  color: #be123c;
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 18px 28px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Drawer content ─────────────────────────────────── */
.card {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.card-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.card-label {
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94a3b8;
  margin-bottom: 6px;
}
.card-main {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: #1e2739;
}
.facts,
.amounts {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 7px 12px;
  margin: 2px 0 0;
  font-size: 13px;
}
.facts dt,
.amounts dt {
  color: #94a3b8;
}
.facts dd,
.amounts dd {
  margin: 0;
  color: #1e2739;
  overflow-wrap: anywhere;
}
.amounts {
  grid-template-columns: 1fr auto;
  padding-bottom: 12px;
  margin-bottom: 10px;
  border-bottom: 1px dashed #e2e8f0;
}
.amounts dd {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.amounts .total {
  font-weight: 700;
  color: #1e2739;
  font-size: 14px;
  padding-top: 6px;
  border-top: 1px solid #f1f5f9;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  font-size: 12px;
}
.quote {
  margin: 10px 0 0;
  padding: 10px 12px;
  background: #f8fafc;
  border-left: 3px solid #c7c2fb;
  border-radius: 0 8px 8px 0;
  font-size: 13px;
  color: #334155;
  font-style: italic;
  white-space: pre-wrap;
}
.quote.small {
  margin-top: 6px;
  padding: 6px 10px;
  font-size: 12px;
}
.item-list,
.txn-list,
.note-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.item-list li {
  display: flex;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
}
.item-list li:last-child,
.txn-list li:last-child {
  border-bottom: none;
}
.item-img {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  object-fit: cover;
  flex-shrink: 0;
  background: #f1f5f9;
}
.item-img.placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}
.item-info {
  flex: 1;
  min-width: 0;
}
.item-price {
  font-weight: 600;
  font-size: 13px;
  color: #1e2739;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.custom-tags {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
  margin-top: 6px;
}
.tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
  background: #f5f3ff;
  color: #5b4fc9;
}
.txn-list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 9px 0;
  border-bottom: 1px solid #f1f5f9;
}
.txn-amount {
  font-weight: 600;
  font-size: 13px;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.txn-amount.pos { color: #15803d; }
.txn-amount.neg { color: #b91c1c; }

.alert {
  background: #fff7f7;
  border: 1px solid #fecaca;
  border-radius: 12px;
  padding: 12px 14px;
}
.alert-head {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  font-weight: 600;
  color: #b91c1c;
  margin-bottom: 6px;
}
.alert ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.alert li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: #7f1d1d;
}
.info-note {
  margin: 0;
  padding: 10px 12px;
  font-size: 12.5px;
  color: #475569;
  background: #eef2ff;
  border-radius: 10px;
}
.empty-msg {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 28px 0;
  margin: 0;
  list-style: none;
}

/* Timeline */
.timeline {
  list-style: none;
  margin: 0;
  padding: 6px 4px 0 6px;
  position: relative;
}
.timeline li {
  --dot: #94a3b8;
  position: relative;
  display: flex;
  gap: 14px;
  padding-bottom: 18px;
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
.ev-body {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}
.ev-detail {
  font-size: 12.5px;
  color: #475569;
  overflow-wrap: anywhere;
}
.ev-created { --dot: #7c6ff7; }
.ev-paid,
.ev-delivered,
.ev-resolved { --dot: #22c55e; }
.ev-delivery { --dot: #3b82f6; }
.ev-stock { --dot: #94a3b8; }
.ev-cancelled,
.ev-rejected { --dot: #64748b; }
.ev-dispute { --dot: #f43f5e; }
.ev-finance { --dot: #f59e0b; }

/* Disputes */
.dispute-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.evidence img {
  margin-top: 10px;
  max-width: 100%;
  max-height: 240px;
  border-radius: 10px;
  object-fit: cover;
  display: block;
}
.vendor-reply {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}
.vendor-reply p {
  margin: 0;
  font-size: 13px;
  color: #334155;
}
.waiting {
  margin-top: 10px;
  color: #b45309;
}

/* Notes */
.note-form {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
}
.note-form textarea {
  width: 100%;
  resize: vertical;
  min-height: 80px;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 13px;
  color: #1e2739;
}
.note-form textarea:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.note-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 10px;
}
.note-list li {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 10px;
}
.note-list p {
  margin: 8px 0 0;
  font-size: 13px;
  color: #334155;
  white-space: pre-wrap;
}
.note-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}
.note-meta .card-main {
  font-size: 13px;
}
.note-meta .sub {
  margin-left: auto;
}
.note-avatar {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ede9fe;
  color: #5b4fc9;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
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
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
  .slide-enter-active,
  .slide-leave-active,
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 1440px) {
  .bucket-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
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
    padding: 0 16px 32px;
  }
  .bucket-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .search-wrap {
    max-width: none;
  }
  .toolbar-filters {
    width: 100%;
  }
  .toolbar-filters .select {
    flex: 1;
  }
  .card-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .facts {
    grid-template-columns: 1fr;
    gap: 2px;
  }
  .facts dd {
    margin-bottom: 8px;
  }
}
</style>
