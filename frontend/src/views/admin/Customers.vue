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
            <h1 class="page-title">Customer Management</h1>
            <p class="page-subtitle">Accounts, order history and complaints. Personal details stay masked unless you need them.</p>
          </div>
        </div>
        <div class="header-right">
          <div class="view-switch" role="tablist">
            <button type="button" role="tab" :class="{ active: view === 'accounts' }" @click="view = 'accounts'">Accounts</button>
            <button type="button" role="tab" :class="{ active: view === 'complaints' }" @click="openComplaints">
              Complaints
              <span v-if="summary?.open_complaints" class="badge-count">{{ summary.open_complaints }}</span>
            </button>
          </div>
          <button type="button" class="ghost-btn" :disabled="loading" @click="refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            <span>{{ loading ? "Refreshing" : "Refresh" }}</span>
          </button>
        </div>
      </header>

      <div class="page-body">
        <!-- ═══ Accounts ═══ -->
        <template v-if="view === 'accounts'">
          <section class="stat-grid">
            <button
              v-for="t in TABS"
              :key="t.key"
              type="button"
              class="stat-card"
              :class="[`tone-${t.tone}`, { active: filters.tab === t.key }]"
              @click="filters.tab = t.key"
            >
              <span class="stat-label"><span class="dot"></span>{{ t.label }}</span>
              <span class="stat-value">
                <template v-if="summary">{{ summary[t.key] }}</template>
                <span v-else class="sk sk-inline"></span>
              </span>
              <span class="stat-hint">{{ t.hint }}</span>
            </button>
          </section>

          <section class="panel">
            <div class="toolbar">
              <div class="search-wrap">
                <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                <input v-model="filters.search" type="search" class="search-input" placeholder="Name, username, email or #ID" aria-label="Search customers" />
              </div>
              <div class="toolbar-filters">
                <select v-model="filters.region" class="select" aria-label="Region">
                  <option value="">All regions</option>
                  <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                </select>
                <select v-model="filters.verified" class="select" aria-label="Verified">
                  <option value="">Verified or not</option>
                  <option value="yes">Verified</option>
                  <option value="no">Not verified</option>
                </select>
                <select v-model="filters.sort" class="select" aria-label="Sort">
                  <option value="newest">Newest first</option>
                  <option value="oldest">Oldest first</option>
                  <option value="active">Recently active</option>
                  <option value="spent">Top spenders</option>
                  <option value="orders">Most orders</option>
                  <option value="name">Name A–Z</option>
                </select>
                <button v-if="hasFilters" type="button" class="link-btn" @click="clearFilters">Clear</button>
              </div>
            </div>

            <div class="table-scroll">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Location</th>
                    <th class="num">Orders</th>
                    <th class="num">Spent</th>
                    <th>Last active</th>
                    <th>Flags</th>
                    <th class="right"></th>
                  </tr>
                </thead>
                <tbody v-if="loading && !rows.length">
                  <tr v-for="n in 8" :key="n"><td v-for="c in 7" :key="c"><div class="sk sk-line"></div></td></tr>
                </tbody>
                <tbody v-else-if="loadError && !rows.length">
                  <tr><td colspan="7" class="empty-cell">The customers couldn't be loaded. <button type="button" class="link-btn" @click="fetchCustomers(meta.current_page)">Try again</button></td></tr>
                </tbody>
                <tbody v-else-if="!rows.length">
                  <tr><td colspan="7" class="empty-cell">{{ hasFilters || filters.tab !== "all" ? "No customers match." : "No customers yet." }}</td></tr>
                </tbody>
                <tbody v-else :class="{ dim: loading }">
                  <tr v-for="(c, i) in rows" :key="c.id" class="click-row" :class="{ selected: drawer.id === c.id }" tabindex="0" @click="openCustomer(c.id)" @keydown.enter="openCustomer(c.id)">
                    <td>
                      <div class="person">
                        <Avatar :name="c.name" :src="c.avatar_url" />
                        <div>
                          <span class="cell-main">{{ c.name }}</span>
                          <span class="sub">No. {{ String((meta.current_page - 1) * meta.per_page + i + 1).padStart(3, "0") }} · {{ c.email_masked }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="muted">{{ c.location || "—" }}</td>
                    <td class="num">{{ c.orders }}<span class="sub">{{ c.paid_orders }} paid</span></td>
                    <td class="num strong">{{ money(c.total_spent) }}</td>
                    <td class="muted">{{ c.last_active_at ? timeAgo(c.last_active_at) : "Never" }}<span class="sub">joined {{ formatDate(c.joined_at) }}</span></td>
                    <td>
                      <div class="flags">
                        <span v-if="c.status === 'suspended'" class="flag red">Suspended</span>
                        <span v-if="c.open_complaints" class="flag rose">{{ plural(c.open_complaints, "open complaint") }}</span>
                        <span v-if="!c.verified" class="flag amber">Not verified</span>
                        <span v-if="c.status !== 'suspended' && !c.open_complaints && c.verified" class="sub">—</span>
                      </div>
                    </td>
                    <td class="right" @click.stop>
                      <button v-if="c.status === 'active'" type="button" class="mini-btn danger" @click="askSuspend(c)">Suspend</button>
                      <button v-else type="button" class="mini-btn ok" @click="askReactivate(c)">Reactivate</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="meta.total > meta.per_page" class="pager">
              <span class="pager-info">{{ (meta.current_page - 1) * meta.per_page + 1 }}–{{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }}</span>
              <div class="pager-btns">
                <button type="button" class="ghost-btn" :disabled="meta.current_page <= 1 || loading" @click="fetchCustomers(meta.current_page - 1)">Previous</button>
                <span class="pager-info">{{ meta.current_page }} / {{ meta.last_page }}</span>
                <button type="button" class="ghost-btn" :disabled="meta.current_page >= meta.last_page || loading" @click="fetchCustomers(meta.current_page + 1)">Next</button>
              </div>
            </div>
          </section>
        </template>

        <!-- ═══ Complaints ═══ -->
        <section v-else class="panel">
          <div class="toolbar">
            <div>
              <h2 class="panel-title">Customer complaints</h2>
              <p class="panel-sub">Return and refund requests and product reports filed by customers</p>
            </div>
            <div class="seg">
              <button v-for="f in COMPLAINT_FILTERS" :key="f.value" type="button" class="seg-btn" :class="{ active: complaintStatus === f.value }" @click="complaintStatus = f.value">{{ f.label }}</button>
            </div>
          </div>
          <p class="info-note">
            Return and refund requests are decided by the vendor, so refunds and stock stay with their checks.
            Product reports are reviewed in Review Management. Open a customer to see their full history.
          </p>
          <template v-if="complaints.loading && !complaints.loaded">
            <div v-for="n in 5" :key="n" class="sk sk-line" style="height: 52px; margin-bottom: 8px"></div>
          </template>
          <p v-else-if="!complaints.items.length" class="empty-msg">No complaints{{ complaintStatus ? " here" : "" }}.</p>
          <ul v-else class="complaint-list">
            <li v-for="item in complaints.items" :key="item.key">
              <span class="kind" :class="`k-${item.kind}`">{{ KIND[item.kind] }}</span>
              <div class="complaint-main">
                <span class="cell-main">
                  <button type="button" class="link-plain" @click="openCustomer(item.customer.id)">{{ item.customer.name || "Customer" }}</button>
                  · {{ item.subject }}
                </span>
                <span class="sub ellipsis">“{{ item.reason }}”</span>
                <span v-if="item.vendor" class="sub">{{ item.vendor.store_name }} · {{ timeAgo(item.created_at) }}</span>
              </div>
              <div class="complaint-right">
                <span class="pill" :class="`cs-${item.status}`">{{ COMPLAINT_STATUS[item.status] ?? item.status }}</span>
                <router-link v-if="item.order_id" :to="{ path: '/admin/orders', query: { order: item.order_id } }" class="link-btn">Open order →</router-link>
                <router-link v-else :to="{ path: '/admin/reviews', query: { tab: 'reports' } }" class="link-btn">Open report →</router-link>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </main>

    <!-- ═══ Customer drawer ═══ -->
    <Transition name="fade"><div v-if="drawer.id" class="drawer-backdrop" @click="closeCustomer"></div></Transition>
    <Transition name="slide">
      <aside v-if="drawer.id" ref="drawerEl" class="drawer" role="dialog" aria-modal="true" tabindex="-1">
        <header class="drawer-head">
          <template v-if="detail">
            <Avatar :name="detail.name" :src="detail.avatar_url" :size="48" />
            <div class="drawer-title">
              <h2>{{ detail.name }}</h2>
              <div class="drawer-meta">
                <span class="pill" :class="detail.status === 'suspended' ? 'st-suspended' : 'st-active'">{{ detail.status === "suspended" ? "Suspended" : "Active" }}</span>
                <span v-if="detail.verified" class="pill st-verified">Verified</span>
                <span class="sub">Joined {{ formatDate(detail.joined_at) }}</span>
              </div>
            </div>
          </template>
          <div v-else class="drawer-title">
            <div class="sk sk-line" style="width: 200px; height: 18px"></div>
            <div class="sk sk-line" style="width: 140px"></div>
          </div>
          <button type="button" class="icon-btn" aria-label="Close" @click="closeCustomer"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
        </header>

        <div v-if="detail?.status === 'suspended'" class="suspended-banner">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" /><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" /></svg>
          <div>
            <strong>Account suspended</strong>
            <span v-if="lastSuspension">{{ lastSuspension.reason }} · {{ lastSuspension.by }}, {{ formatDate(lastSuspension.at) }}</span>
            <span>They can't sign in, order, review or report.</span>
          </div>
          <button type="button" class="mini-btn ok" @click="askReactivate(detail)">Reactivate</button>
        </div>

        <nav class="drawer-tabs" role="tablist">
          <button v-for="t in DRAWER_TABS" :key="t.key" type="button" role="tab" class="drawer-tab" :class="{ active: drawer.tab === t.key }" @click="drawer.tab = t.key">
            {{ t.label }}
            <span v-if="t.key === 'complaints' && detail?.open_complaints" class="tab-count warn">{{ detail.open_complaints }}</span>
          </button>
        </nav>

        <div class="drawer-body">
          <p v-if="drawer.error" class="empty-msg">{{ drawer.error }}</p>
          <template v-else-if="!detail">
            <div v-for="n in 4" :key="n" class="box"><div class="sk sk-line" style="width: 40%"></div><div class="sk sk-line"></div></div>
          </template>

          <!-- Overview -->
          <template v-else-if="drawer.tab === 'overview'">
            <div class="privacy-card" :class="{ revealed: revealed }">
              <div class="privacy-head">
                <span class="box-label">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                  Personal details
                </span>
                <button v-if="!revealed" type="button" class="mini-btn" @click="askReveal">Reveal</button>
                <span v-else class="sub">Shown just now · recorded in History</span>
              </div>
              <dl class="facts">
                <dt>Email</dt><dd>{{ revealed?.email ?? detail.email_masked ?? "—" }}</dd>
                <dt>Phone</dt><dd>{{ revealed?.contact_number ?? detail.phone_masked ?? "—" }}</dd>
                <dt>Address</dt><dd>{{ revealed?.address ?? detail.location ?? "—" }}</dd>
                <dt>Birthday</dt><dd>{{ revealed ? (revealed.date_of_birth ? formatDate(revealed.date_of_birth) : "—") : "••••" }}</dd>
              </dl>
            </div>

            <div class="tiles">
              <div class="tile"><span class="tile-label">Orders</span><span class="tile-value">{{ detail.orders }}</span><span class="sub">{{ detail.stats.completed }} completed</span></div>
              <div class="tile"><span class="tile-label">Total spent</span><span class="tile-value">{{ money(detail.total_spent) }}</span><span class="sub">{{ money(detail.stats.average_order) }} average</span></div>
              <div class="tile"><span class="tile-label">Cancelled</span><span class="tile-value" :class="{ warn: (detail.stats.cancellation_rate ?? 0) >= 40 }">{{ detail.stats.cancellation_rate === null ? "—" : detail.stats.cancellation_rate + "%" }}</span><span class="sub">{{ detail.stats.cancelled }} of finished orders</span></div>
              <div class="tile"><span class="tile-label">Returns</span><span class="tile-value">{{ detail.stats.returned }}</span><span class="sub">{{ plural(detail.stats.reviews, "review") }} written</span></div>
            </div>

            <div class="box">
              <span class="box-label">Account</span>
              <dl class="facts">
                <dt>Username</dt><dd>{{ detail.profile.username || "—" }}</dd>
                <dt>Gender</dt><dd>{{ detail.profile.gender || "—" }}</dd>
                <dt>Two-step login</dt><dd>{{ detail.profile.two_factor_enabled ? "On" : "Off" }}</dd>
                <dt>Delivery pin</dt><dd>{{ detail.profile.has_pinned_location ? "Set" : "Not set" }}</dd>
                <dt>Last active</dt><dd>{{ detail.last_active_at ? formatDateTime(detail.last_active_at) : "Never" }}</dd>
                <dt>Favourite store</dt>
                <dd>
                  <span v-if="detail.stats.favourite_store" class="inline-vendor">
                    <VendorAvatar :vendor="detail.stats.favourite_store" :size="20" />
                    {{ detail.stats.favourite_store.store_name }} <span class="sub-inline">({{ plural(detail.stats.favourite_store.orders, "order") }})</span>
                  </span>
                  <template v-else>—</template>
                </dd>
              </dl>
            </div>
          </template>

          <!-- Orders -->
          <template v-else-if="drawer.tab === 'orders'">
            <p v-if="!detail.recent_orders.length" class="empty-msg">No orders yet.</p>
            <ul v-else class="row-list">
              <li v-for="o in detail.recent_orders" :key="o.id" tabindex="0" @click="goToOrder(o.id)" @keydown.enter="goToOrder(o.id)">
                <VendorAvatar :vendor="o.vendor" :size="32" />
                <div class="row-main">
                  <span class="cell-main mono">{{ o.order_number }}</span>
                  <span class="sub">{{ o.vendor.store_name }} · {{ plural(o.items_count, "item") }} · {{ o.method_label }} · {{ timeAgo(o.created_at) }}</span>
                </div>
                <div class="row-right">
                  <span class="strong">{{ money(o.total_amount) }}</span>
                  <span class="pill" :class="`st-${o.status}`">{{ ORDER_STATUS[o.status] ?? o.status }}</span>
                </div>
              </li>
            </ul>
            <p v-if="detail.orders > detail.recent_orders.length" class="sub center">Showing the latest {{ detail.recent_orders.length }} of {{ detail.orders }}.</p>
          </template>

          <!-- Complaints -->
          <template v-else-if="drawer.tab === 'complaints'">
            <p v-if="!detail.complaints.length" class="empty-msg">This customer hasn't filed any complaints.</p>
            <article v-for="item in detail.complaints" :key="item.key" class="box">
              <div class="box-head">
                <span class="kind" :class="`k-${item.kind}`">{{ KIND[item.kind] }}</span>
                <span class="pill" :class="`cs-${item.status}`">{{ COMPLAINT_STATUS[item.status] ?? item.status }}</span>
              </div>
              <span class="strong">{{ item.subject }}<template v-if="item.vendor"> · {{ item.vendor.store_name }}</template></span>
              <p class="quote">“{{ item.reason }}”</p>
              <span v-if="item.response" class="sub">Vendor replied: {{ item.response }}</span>
              <div class="box-foot">
                <span class="sub">{{ formatDateTime(item.created_at) }}</span>
                <router-link v-if="item.order_id" :to="{ path: '/admin/orders', query: { order: item.order_id } }" class="link-btn">Open order →</router-link>
                <router-link v-else :to="{ path: '/admin/reviews', query: { tab: 'reports' } }" class="link-btn">Open in Review Management →</router-link>
              </div>
            </article>
          </template>

          <!-- Activity -->
          <template v-else-if="drawer.tab === 'activity'">
            <p v-if="!detail.logins.length" class="empty-msg">No sign-ins recorded.</p>
            <ul v-else class="row-list flat-list">
              <li v-for="(l, i) in detail.logins" :key="i">
                <span class="device-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" /><line x1="8" y1="21" x2="16" y2="21" /><line x1="12" y1="17" x2="12" y2="21" /></svg>
                </span>
                <div class="row-main">
                  <span class="cell-main">{{ l.device }}</span>
                  <span class="sub">{{ l.location || "Location not shared" }}<template v-if="l.ip"> · IP {{ l.ip }}</template></span>
                </div>
                <span class="sub">{{ formatDateTime(l.at) }}</span>
              </li>
            </ul>
          </template>

          <!-- History -->
          <template v-else-if="drawer.tab === 'history'">
            <ol class="timeline">
              <li v-for="e in detail.history" :key="e.id" :class="`ev-${e.action}`">
                <span class="ev-dot"></span>
                <div>
                  <span class="cell-main">{{ EVENT_LABEL[e.action] ?? e.action }} · {{ e.by }}</span>
                  <span v-if="e.reason" class="ev-reason">“{{ e.reason }}”</span>
                  <span class="sub">{{ formatDateTime(e.at) }}</span>
                </div>
              </li>
              <li class="ev-joined">
                <span class="ev-dot"></span>
                <div>
                  <span class="cell-main">Signed up</span>
                  <span class="sub">{{ formatDateTime(detail.joined_at) }}</span>
                </div>
              </li>
            </ol>
          </template>
        </div>

        <footer v-if="detail && detail.status === 'active'" class="drawer-foot">
          <span class="sub foot-note">Suspend only when it's justified. The reason is kept on record.</span>
          <button type="button" class="ghost-btn danger-text" @click="askSuspend(detail)">Suspend account</button>
        </footer>
      </aside>
    </Transition>

    <!-- ═══ Dialogs ═══ -->
    <Transition name="fade">
      <div v-if="dialog.type" class="dialog-backdrop" @click.self="closeDialog">
        <div class="dialog" role="dialog" aria-modal="true">
          <template v-if="dialog.type === 'reveal'">
            <span class="dialog-icon info"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg></span>
            <h3>Reveal {{ dialog.target.name }}'s details?</h3>
            <p>Their full email, phone, address and birthday will be shown. Your name, the time and your reason are recorded in their history.</p>
            <textarea v-model="dialog.text" rows="2" placeholder="Why do you need them? e.g. contacting them about order ORD-…" :class="{ invalid: dialog.error }"></textarea>
          </template>
          <template v-else-if="dialog.type === 'suspend'">
            <span class="dialog-icon bad"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10" /><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" /></svg></span>
            <h3>Suspend {{ dialog.target.name }}?</h3>
            <ul class="impact">
              <li>They're signed out now and can't sign back in.</li>
              <li>They can't place orders, write reviews, report products or chat.</li>
              <li>Their existing orders, payments and refunds carry on as normal.</li>
            </ul>
            <textarea v-model="dialog.text" rows="3" placeholder="Reason (kept in their history)" :class="{ invalid: dialog.error }"></textarea>
          </template>
          <template v-else-if="dialog.type === 'reactivate'">
            <span class="dialog-icon ok"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg></span>
            <h3>Reactivate {{ dialog.target.name }}?</h3>
            <p>They can sign in and shop again.</p>
            <textarea v-model="dialog.text" rows="2" placeholder="Note for the history (optional)"></textarea>
          </template>

          <p v-if="dialog.error" class="dialog-error">{{ dialog.error }}</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="dialog.busy" @click="closeDialog">Cancel</button>
            <button type="button" class="primary-btn" :class="{ danger: dialog.type === 'suspend', ok: dialog.type === 'reactivate' }" :disabled="dialog.busy" @click="confirmDialog">
              {{ dialog.busy ? "Working…" : CONFIRM[dialog.type] }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";
import { linkedId } from "../../utils/hiddenIds";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

// Customer photo, or initials
const Avatar = defineComponent({
  props: { name: String, src: String, size: { type: Number, default: 34 } },
  setup(props) {
    const broken = ref(false);
    const initials = computed(() =>
      (props.name || "?").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(""),
    );
    return () =>
      h(
        "span",
        { class: "c-avatar", style: { width: `${props.size}px`, height: `${props.size}px`, fontSize: `${Math.round(props.size * 0.36)}px` } },
        props.src && !broken.value ? h("img", { src: props.src, alt: "", onError: () => (broken.value = true) }) : initials.value,
      );
  },
});

const TABS = [
  { key: "all", label: "All customers", hint: "Everyone signed up", tone: "slate" },
  { key: "active", label: "Active", hint: "Can sign in and order", tone: "green" },
  { key: "new", label: "New", hint: "Joined in the last 30 days", tone: "violet" },
  { key: "with_complaints", label: "With open complaints", hint: "Return, refund or report pending", tone: "rose" },
  { key: "suspended", label: "Suspended", hint: "Can't sign in", tone: "red" },
];
const DRAWER_TABS = [
  { key: "overview", label: "Overview" },
  { key: "orders", label: "Orders" },
  { key: "complaints", label: "Complaints" },
  { key: "activity", label: "Sign-ins" },
  { key: "history", label: "History" },
];
const COMPLAINT_FILTERS = [
  { value: "pending", label: "Open" },
  { value: "resolved", label: "Resolved" },
  { value: "", label: "All" },
];
const KIND = { return: "Return", refund: "Refund", product_report: "Product report" };
const COMPLAINT_STATUS = { open: "Open", approved: "Approved", rejected: "Declined", upheld: "Upheld", dismissed: "Dismissed" };
const ORDER_STATUS = { pending: "Pending", processing: "Processing", out_for_delivery: "Out for delivery", completed: "Completed", cancelled: "Cancelled", failed: "Failed", returned: "Returned", refunded: "Refunded" };
const EVENT_LABEL = { suspended: "Suspended", reactivated: "Reactivated", contact_viewed: "Personal details viewed" };
const CONFIRM = { reveal: "Reveal details", suspend: "Suspend account", reactivate: "Reactivate" };

// ── Accounts ─────────────────────────────────────────────────────
const view = ref(route.query.view === "complaints" ? "complaints" : "accounts");
const summary = ref(null);
const rows = ref([]);
const regions = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const loading = ref(false);
const loadError = ref(false);
const blankFilters = () => ({ tab: "all", search: "", region: "", verified: "", sort: "newest" });
const filters = reactive({ ...blankFilters(), tab: TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "all" });
const hasFilters = computed(() => Boolean(filters.search.trim() || filters.region || filters.verified));

const fetchSummary = async () => {
  try {
    const { data } = await api.get("/admin/customers/summary");
    summary.value = data.data;
  } catch {
    // counts are optional
  }
};

let listRequest = 0;
const fetchCustomers = async (page = 1) => {
  const id = ++listRequest;
  loading.value = true;
  loadError.value = false;
  try {
    const params = Object.fromEntries(Object.entries({ ...filters, search: filters.search.trim(), page, per_page: 15 }).filter(([, v]) => v !== ""));
    const { data } = await api.get("/admin/customers", { params });
    if (id !== listRequest) return;
    rows.value = data.data;
    meta.value = data.meta;
    regions.value = data.regions ?? [];
  } catch {
    if (id !== listRequest) return;
    loadError.value = true;
    if (rows.value.length) toast.error("The customers couldn't be refreshed.");
  } finally {
    if (id === listRequest) loading.value = false;
  }
};

let searchTimer = null;
watch(
  () => ({ ...filters }),
  (next, prev) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchCustomers(1), next.search !== prev.search ? 300 : 0);
    if (next.tab !== prev.tab) router.replace({ query: { ...route.query, tab: next.tab } });
  },
);
const clearFilters = () => Object.assign(filters, { search: "", region: "", verified: "" });

const refresh = () => {
  fetchSummary();
  if (view.value === "accounts") fetchCustomers(meta.value.current_page);
  else fetchComplaints();
  if (drawer.id) loadCustomer(drawer.id);
};

// ── Complaints ───────────────────────────────────────────────────
const complaintStatus = ref("pending");
const complaints = reactive({ items: [], loading: false, loaded: false });
const fetchComplaints = async () => {
  complaints.loading = true;
  try {
    const { data } = await api.get("/admin/customers/complaints", { params: { status: complaintStatus.value || undefined } });
    complaints.items = data.data;
    complaints.loaded = true;
  } catch {
    toast.error("The complaints couldn't be loaded.");
  } finally {
    complaints.loading = false;
  }
};
const openComplaints = () => {
  view.value = "complaints";
  if (!complaints.loaded) fetchComplaints();
};
watch(complaintStatus, fetchComplaints);
watch(view, (v) => router.replace({ query: { ...route.query, view: v === "complaints" ? "complaints" : undefined } }));

// ── Drawer ───────────────────────────────────────────────────────
const drawer = reactive({ id: null, tab: "overview", error: "" });
const drawerEl = ref(null);
const detail = ref(null);
const revealed = ref(null);
const lastSuspension = computed(() => detail.value?.history.find((e) => e.action === "suspended"));
let lastFocus = null;

let detailRequest = 0;
const loadCustomer = async (id) => {
  const requestId = ++detailRequest;
  drawer.error = "";
  try {
    const { data } = await api.get(`/admin/customers/${id}`);
    if (requestId === detailRequest) detail.value = data.data;
  } catch (error) {
    if (requestId === detailRequest) drawer.error = error.response?.status === 404 ? "This customer no longer exists." : "This customer couldn't be loaded.";
  }
};

const openCustomer = async (id) => {
  if (!id) return;
  if (!drawer.id) lastFocus = document.activeElement;
  if (drawer.id !== id) {
    detail.value = null;
    revealed.value = null;
    drawer.tab = "overview";
  }
  drawer.id = id;
  router.replace({ query: { ...route.query, customer: id } });
  loadCustomer(id);
  await nextTick();
  drawerEl.value?.focus();
};

const closeCustomer = () => {
  drawer.id = null;
  revealed.value = null; // details are never kept around after closing
  detailRequest++;
  router.replace({ query: { ...route.query, customer: null } });
  lastFocus?.focus?.();
};

const goToOrder = (id) => router.push({ path: "/admin/orders", query: { order: id } });

// ── Dialogs ──────────────────────────────────────────────────────
const dialog = reactive({ type: null, target: null, text: "", error: "", busy: false });
const openDialog = (type, target) => Object.assign(dialog, { type, target, text: "", error: "", busy: false });
const closeDialog = () => {
  if (!dialog.busy) dialog.type = null;
};
const askSuspend = (c) => openDialog("suspend", c);
const askReactivate = (c) => openDialog("reactivate", c);
const askReveal = () => openDialog("reveal", detail.value);

const confirmDialog = async () => {
  const { type, target } = dialog;
  const text = dialog.text.trim();
  if ((type === "suspend" || type === "reveal") && text.length < 5) {
    dialog.error = "Give a reason of at least 5 characters.";
    return;
  }
  dialog.busy = true;
  dialog.error = "";
  try {
    if (type === "reveal") {
      const { data } = await api.post(`/admin/customers/${target.id}/reveal`, { reason: text });
      revealed.value = data.data;
      loadCustomer(target.id); // picks up the new history entry
    } else {
      const body = type === "suspend" ? { reason: text } : { note: text || null };
      const { data } = await api.post(`/admin/customers/${target.id}/${type}`, body);
      toast.success(data.message);
      await Promise.all([fetchCustomers(meta.value.current_page), fetchSummary(), drawer.id === target.id ? loadCustomer(target.id) : null]);
    }
    dialog.busy = false;
    dialog.type = null;
  } catch (error) {
    dialog.busy = false;
    dialog.error = error.response?.data?.message || error.response?.data?.errors?.reason?.[0] || "That didn't work. Try again.";
  }
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => (v === null || v === undefined ? "—" : peso.format(Number(v)));
const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const timeAgo = (v) => {
  if (!v) return "";
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "just now" : `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  if (days < 31) return `${days} day${days === 1 ? "" : "s"} ago`;
  return formatDate(v);
};

// ── Lifecycle ────────────────────────────────────────────────────
const onKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (dialog.type) closeDialog();
  else if (drawer.id) closeCustomer();
};

onMounted(() => {
  fetchSummary();
  fetchCustomers();
  if (view.value === "complaints") fetchComplaints();
  const customerId = linkedId("customer");
  if (customerId) openCustomer(customerId);
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
.page-body > section {
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
.view-switch,
.seg {
  display: inline-flex;
  padding: 3px;
  background: #f1f5f9;
  border-radius: 10px;
  gap: 2px;
}
.view-switch button,
.seg-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  background: transparent;
  padding: 7px 13px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.view-switch button.active,
.seg-btn.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.badge-count {
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 10px;
  background: #e11d48;
  color: #fff;
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
}
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.primary-btn:hover:not(:disabled) { background: #5b4fc9; }
.primary-btn.ok { background: #16a34a; }
.primary-btn.ok:hover:not(:disabled) { background: #15803d; }
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
.link-plain {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-weight: 600;
  color: #1e2739;
  cursor: pointer;
}
.link-plain:hover {
  color: #5b4fc9;
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
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  white-space: nowrap;
}
.mini-btn:hover {
  border-color: #c7c2fb;
  color: #5b4fc9;
}
.mini-btn.danger { color: #b91c1c; }
.mini-btn.danger:hover { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.mini-btn.ok { background: #16a34a; border-color: transparent; color: #fff; }

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
  width: 26px;
  height: 20px;
  vertical-align: middle;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Stat cards */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
}
.stat-card {
  --c: #475569;
  --bg: #f8fafc;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid #e9edf3;
  background: #fff;
  cursor: pointer;
  text-align: left;
  position: relative;
  overflow: hidden;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.stat-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}
.stat-card.active {
  border-color: var(--c);
  background: var(--bg);
}
.stat-card::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 3px;
  background: var(--c);
  opacity: 0;
}
.stat-card.active::after {
  opacity: 1;
}
.stat-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--c);
}
.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #1e2739;
  margin-top: 4px;
  font-variant-numeric: tabular-nums;
}
.stat-hint {
  font-size: 11px;
  color: #94a3b8;
}
.tone-green { --c: #16a34a; --bg: #f0fdf4; }
.tone-violet { --c: #7c6ff7; --bg: #f5f3ff; }
.tone-rose { --c: #e11d48; --bg: #fff1f2; }
.tone-red { --c: #dc2626; --bg: #fef2f2; }

/* Panel & toolbar */
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
textarea:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.info-note {
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
  padding: 32px 0;
  margin: 0;
}

/* Table */
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
  padding: 11px 10px;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
  vertical-align: middle;
  color: #1e2739;
}
.data-table tbody.dim {
  opacity: 0.55;
}
.click-row {
  cursor: pointer;
}
.click-row:hover td,
.click-row:focus-visible td {
  background: #fafaff;
}
.click-row.selected td {
  background: #f5f3ff;
}
.click-row:focus-visible {
  outline: 2px solid #7c6ff7;
  outline-offset: -2px;
}
.empty-cell {
  text-align: center;
  color: #94a3b8 !important;
  padding: 40px 10px !important;
}
.num {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}
.right {
  text-align: right !important;
}
.strong {
  font-weight: 600;
  color: #1e2739;
}
.muted {
  color: #64748b !important;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  font-size: 12px;
}
.cell-main {
  display: block;
  font-weight: 500;
  color: #1e2739;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
  font-weight: 400;
}
.sub.center {
  text-align: center;
  margin-top: 10px;
}
.sub-inline {
  font-size: 12px;
  color: #94a3b8;
}
.ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.person {
  display: flex;
  align-items: center;
  gap: 10px;
}
.c-avatar {
  border-radius: 50%;
  background: linear-gradient(135deg, #f0abfc, #a78bfa);
  color: #fff;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.c-avatar :deep(img),
.c-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.flags {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.flag {
  font-size: 11px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 6px;
  white-space: nowrap;
}
.flag.red { background: #fef2f2; color: #b91c1c; }
.flag.rose { background: #fff1f2; color: #be123c; }
.flag.amber { background: #fffbeb; color: #b45309; }

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
.st-active { background: #dcfce7; color: #15803d; }
.st-suspended { background: #fee2e2; color: #b91c1c; }
.st-verified { background: #e0f2fe; color: #0369a1; }
.st-pending { background: #fef3c7; color: #b45309; }
.st-processing { background: #dbeafe; color: #1d4ed8; }
.st-out_for_delivery { background: #e0f2fe; color: #0369a1; }
.st-completed { background: #dcfce7; color: #15803d; }
.st-cancelled,
.st-failed { background: #f1f5f9; color: #64748b; }
.st-returned,
.st-refunded { background: #ede9fe; color: #6d28d9; }
.cs-open { background: #fef3c7; color: #b45309; }
.cs-approved,
.cs-upheld { background: #dcfce7; color: #15803d; }
.cs-rejected,
.cs-dismissed { background: #f1f5f9; color: #64748b; }
.kind {
  display: inline-block;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 3px 8px;
  border-radius: 6px;
  white-space: nowrap;
}
.k-return { background: #ede9fe; color: #6d28d9; }
.k-refund { background: #e0f2fe; color: #0369a1; }
.k-product_report { background: #ffe4e6; color: #be123c; }

/* Complaints */
.complaint-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid #eef1f6;
  border-radius: 12px;
  overflow: hidden;
}
.complaint-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
}
.complaint-list li:last-child {
  border-bottom: none;
}
.complaint-main {
  flex: 1;
  min-width: 0;
}
.complaint-main .cell-main {
  max-width: none;
}
.complaint-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

/* Pager */
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 14px;
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
  align-items: center;
  gap: 14px;
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
  gap: 6px;
  flex-wrap: wrap;
}
.drawer-meta .sub {
  display: inline;
}
.suspended-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  background: #fef2f2;
  border-bottom: 1px solid #fecaca;
  color: #991b1b;
  font-size: 12.5px;
}
.suspended-banner > div {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.drawer-tabs {
  display: flex;
  gap: 2px;
  padding: 0 14px;
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

/* Drawer content */
.box,
.privacy-card {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.privacy-card {
  background: repeating-linear-gradient(135deg, #fbfbfe 0 10px, #f8f8fd 10px 20px);
}
.privacy-card.revealed {
  background: #fffdf5;
  border-color: #fde68a;
}
.privacy-head,
.box-head,
.box-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.box-foot {
  margin-top: 6px;
}
.box-label {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94a3b8;
  margin-bottom: 6px;
}
.facts {
  display: grid;
  grid-template-columns: 130px 1fr;
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
  overflow-wrap: anywhere;
}
.inline-vendor {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.tiles {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
.tile {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 12px 14px;
  min-width: 0;
}
.tile-label {
  font-size: 11.5px;
  color: #64748b;
}
.tile-value {
  font-size: 18px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.tile-value.warn {
  color: #b45309;
}
.quote {
  margin: 6px 0 2px;
  padding: 8px 10px;
  background: #f8fafc;
  border-left: 3px solid #c7c2fb;
  border-radius: 0 8px 8px 0;
  font-size: 13px;
  color: #334155;
  font-style: italic;
}
.row-list {
  list-style: none;
  margin: 0;
  padding: 0;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  overflow: hidden;
}
.row-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
}
.row-list.flat-list li {
  cursor: default;
}
.row-list li:last-child {
  border-bottom: none;
}
.row-list:not(.flat-list) li:hover {
  background: #fafaff;
}
.row-main {
  flex: 1;
  min-width: 0;
}
.row-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  font-size: 13px;
  white-space: nowrap;
}
.device-icon {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: #f1f5f9;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.timeline {
  list-style: none;
  margin: 0;
  padding: 4px 0 0 6px;
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
.ev-suspended { --dot: #dc2626; }
.ev-reactivated { --dot: #16a34a; }
.ev-contact_viewed { --dot: #d97706; }
.ev-joined { --dot: #7c6ff7; }
.ev-reason {
  display: block;
  font-size: 12.5px;
  color: #475569;
  font-style: italic;
}

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
.dialog-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
}
.dialog-icon.ok { background: #dcfce7; color: #16a34a; }
.dialog-icon.bad { background: #fee2e2; color: #dc2626; }
.dialog-icon.info { background: #fef3c7; color: #d97706; }
.impact {
  margin: 0 0 12px;
  padding-left: 18px;
  font-size: 12.5px;
  color: #475569;
  display: flex;
  flex-direction: column;
  gap: 4px;
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
  .stat-grid {
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
    padding: 0 16px 32px;
  }
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .search-wrap {
    max-width: none;
  }
  .tiles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .facts {
    grid-template-columns: 1fr;
    gap: 2px;
  }
  .complaint-list li {
    flex-wrap: wrap;
  }
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
</style>
