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
            <h1 class="page-title">Vendor Management</h1>
            <p class="page-subtitle">Review applications and manage every store on BloomCraft</p>
          </div>
        </div>
        <div class="header-right">
          <button v-if="isApplicationTab" type="button" class="ghost-btn" :disabled="exporting" @click="exportApplications">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
            <span>{{ exporting ? "Exporting…" : "Export CSV" }}</span>
          </button>
          <button type="button" class="ghost-btn" :disabled="listLoading" @click="refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg>
            <span>{{ listLoading ? "Refreshing" : "Refresh" }}</span>
          </button>
        </div>
      </header>

      <div class="page-body">
        <!-- Status cards / tabs -->
        <section class="tab-grid" role="tablist" aria-label="Vendor status">
          <button
            v-for="t in TABS"
            :key="t.key"
            type="button"
            role="tab"
            class="tab-card"
            :class="[`tone-${t.tone}`, { active: tab === t.key }]"
            :aria-selected="tab === t.key"
            @click="setTab(t.key)"
          >
            <span class="tab-icon" v-html="t.icon"></span>
            <span class="tab-text">
              <span class="tab-label">{{ t.label }}</span>
              <span class="tab-count">
                <template v-if="summary">{{ summary[t.key] }}</template>
                <span v-else class="sk sk-inline"></span>
              </span>
            </span>
            <span class="tab-hint">{{ t.hint }}</span>
          </button>
        </section>

        <section class="panel">
          <div class="toolbar">
            <div class="search-wrap">
              <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
              <input v-model="search" type="search" class="search-input" placeholder="Store, owner or email" aria-label="Search vendors" />
            </div>
            <div class="toolbar-filters">
              <template v-if="isApplicationTab">
                <select v-model="appFilters.business_type" class="select" aria-label="Business type">
                  <option value="">Any business type</option>
                  <option value="individual">Sole proprietor</option>
                  <option value="partnership">Partnership</option>
                  <option value="corporation">Corporation</option>
                </select>
                <label class="date-field"><span>From</span><input v-model="appFilters.start_date" type="date" class="select" /></label>
                <label class="date-field"><span>To</span><input v-model="appFilters.end_date" type="date" class="select" /></label>
              </template>
              <select v-else v-model="vendorSort" class="select" aria-label="Sort vendors">
                <option value="newest">Newest first</option>
                <option value="sales">Top sales (30 days)</option>
                <option value="orders">Most orders (30 days)</option>
                <option value="rating">Best rated</option>
                <option value="name">Name A–Z</option>
              </select>
              <button v-if="hasFilters" type="button" class="link-btn" @click="clearFilters">Clear</button>
            </div>
          </div>

          <!-- Applications (pending / rejected) -->
          <div v-if="isApplicationTab" class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Store</th>
                  <th>Owner</th>
                  <th>Business type</th>
                  <th>Submitted</th>
                  <th>Status</th>
                  <th class="right">{{ tab === "pending" ? "Review" : "" }}</th>
                </tr>
              </thead>
              <tbody v-if="listLoading && !applications.length">
                <tr v-for="n in 6" :key="n"><td v-for="c in 6" :key="c"><div class="sk sk-line"></div></td></tr>
              </tbody>
              <tbody v-else-if="!applications.length">
                <tr><td colspan="6" class="empty-cell">{{ tab === "pending" ? "No applications waiting. You're all caught up." : "No rejected applications." }}</td></tr>
              </tbody>
              <tbody v-else :class="{ dim: listLoading }">
                <tr v-for="a in appList.rows.value" :key="a.id" class="click-row" tabindex="0" @click="openApplication(a)" @keydown.enter="openApplication(a)">
                  <td>
                    <div class="vendor-cell">
                      <VendorAvatar :vendor="{ store_name: a.store_name, logo_url: a.store_logo_url }" :size="34" />
                      <div>
                        <span class="cell-main">{{ a.store_name }}</span>
                        <span class="sub">{{ shortAddress(a.store_address) }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="cell-main">{{ a.owner_name }}</span>
                    <span class="sub">{{ a.email }}</span>
                  </td>
                  <td class="muted">{{ businessType(a.business_type) }}</td>
                  <td class="muted">{{ formatDate(a.submitted_at) }}<span class="sub">{{ timeAgo(a.submitted_at) }}</span></td>
                  <td><span class="pill" :class="`ap-${appStatus(a)}`">{{ APP_STATUS_LABELS[appStatus(a)] ?? appStatus(a) }}</span></td>
                  <td class="right" @click.stop>
                    <div v-if="a.status === 'pending'" class="row-actions">
                      <button type="button" class="icon-action ok" title="Approve" aria-label="Approve" @click="askApprove(a)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><polyline points="20 6 9 17 4 12" /></svg>
                      </button>
                      <button
                        v-if="!['needs_resubmission', 'pending_review'].includes(a.resubmission_status)"
                        type="button"
                        class="icon-action"
                        title="Ask for corrections"
                        aria-label="Ask for corrections"
                        @click="askResubmission(a)"
                      >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="1 4 1 10 7 10" /><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" /></svg>
                      </button>
                      <button type="button" class="icon-action bad" title="Reject" aria-label="Reject" @click="askReject(a)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                      </button>
                    </div>
                    <svg v-else class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6" /></svg>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Vendors (active / suspended) -->
          <div v-else class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Store</th>
                  <th>Plan</th>
                  <th class="num">Products</th>
                  <th class="num">Orders · 30d</th>
                  <th class="num">Sales · 30d</th>
                  <th>Rating</th>
                  <th>Flags</th>
                  <th class="right"></th>
                </tr>
              </thead>
              <tbody v-if="listLoading && !vendors.length">
                <tr v-for="n in 6" :key="n"><td v-for="c in 8" :key="c"><div class="sk sk-line"></div></td></tr>
              </tbody>
              <tbody v-else-if="!vendors.length">
                <tr><td colspan="8" class="empty-cell">{{ tab === "suspended" ? "No suspended vendors." : search ? "No vendors match your search." : "No vendors yet." }}</td></tr>
              </tbody>
              <tbody v-else :class="{ dim: listLoading }">
                <tr v-for="v in vendorList.visible.value" :key="v.id" class="click-row" :class="{ selected: drawer.vendorId === v.id }" tabindex="0" @click="openVendor(v.id)" @keydown.enter="openVendor(v.id)">
                  <td>
                    <div class="vendor-cell">
                      <VendorAvatar :vendor="v" :size="34" />
                      <div>
                        <span class="cell-main">{{ v.store_name }}</span>
                        <span class="sub">{{ v.owner_name }} · {{ v.email }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span v-if="v.subscription" class="pill" :class="`sub-${v.subscription.status}`">{{ v.subscription.plan_name }}</span>
                    <span v-else class="sub">No plan</span>
                    <span v-if="v.subscription" class="sub">{{ SUB_STATUS[v.subscription.status] ?? v.subscription.status }}</span>
                  </td>
                  <td class="num">{{ v.products_active }}<span class="sub">of {{ v.products_total }}</span></td>
                  <td class="num">{{ v.orders_30d }}<span class="sub">{{ v.orders }} total</span></td>
                  <td class="num strong">{{ money(v.sales_30d) }}<span class="sub">{{ money(v.sales) }} total</span></td>
                  <td>
                    <span v-if="v.rating !== null" class="rating">★ {{ v.rating.toFixed(1) }}</span>
                    <span v-else class="sub">No reviews</span>
                    <span v-if="v.reviews" class="sub">{{ plural(v.reviews, "review") }}</span>
                  </td>
                  <td>
                    <div class="flags">
                      <span v-if="v.open_disputes" class="flag rose">{{ v.open_disputes }} dispute{{ v.open_disputes === 1 ? "" : "s" }}</span>
                      <span v-if="v.pending_orders" class="flag amber">{{ v.pending_orders }} pending</span>
                      <span v-if="v.strikes" class="flag red" :title="`${v.strikes} banned product(s); 3 suspends the store`">{{ v.strikes }}/3 strikes</span>
                      <span v-if="!v.open_disputes && !v.pending_orders && !v.strikes" class="sub">—</span>
                    </div>
                  </td>
                  <td class="right" @click.stop>
                    <button v-if="v.status === 'active'" type="button" class="mini-btn danger" @click="askSuspend(v)">Suspend</button>
                    <button v-else type="button" class="mini-btn ok" @click="askReactivate(v)">Reactivate</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 10 rows, then 10 more, then 20 at a time -->
          <ShowMore
            v-if="isApplicationTab ? applications.length : vendors.length"
            tone="admin"
            :has-more="isApplicationTab ? appList.hasMore.value : vendorList.hasMore.value"
            :loading="appList.loadingMore.value"
            :shown="isApplicationTab ? appList.rows.value.length : vendorList.shown.value"
            :total="isApplicationTab ? appList.total.value : vendorList.total.value"
            @more="isApplicationTab ? appList.showMore() : vendorList.showMore()"
          />
        </section>
      </div>
    </main>

    <!-- ═══ Drawer ═══ -->
    <Transition name="fade"><div v-if="drawer.open" class="drawer-backdrop" @click="closeDrawer"></div></Transition>
    <Transition name="slide">
      <aside v-if="drawer.open" ref="drawerEl" class="drawer" role="dialog" aria-modal="true" tabindex="-1" @keydown.esc="closeDrawer">
        <!-- Application -->
        <template v-if="drawer.mode === 'application' && drawer.application">
          <header class="drawer-head">
            <VendorAvatar :vendor="{ store_name: drawer.application.store_name, logo_url: drawer.application.store_logo_url }" :size="46" />
            <div class="drawer-title">
              <h2>{{ drawer.application.store_name }}</h2>
              <div class="drawer-meta">
                <span class="pill" :class="`ap-${appStatus(drawer.application)}`">{{ APP_STATUS_LABELS[appStatus(drawer.application)] }}</span>
                <span class="sub">{{ drawer.application.owner_name }} · applied {{ timeAgo(drawer.application.submitted_at) }}</span>
              </div>
            </div>
            <button type="button" class="icon-btn" aria-label="Close" @click="closeDrawer"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
          </header>
          <div class="drawer-body">
            <VendorApplicationDetails
              :app="drawer.application"
              :resubmissions="drawer.resubmissions"
              :actionable="drawer.application.status === 'pending'"
              @view-document="openDocument"
              @approve-resubmission="approveResubmission"
              @request-again="askAgain"
            />
          </div>
          <footer v-if="drawer.application.status === 'pending'" class="drawer-foot">
            <button type="button" class="ghost-btn danger-text" @click="askReject(drawer.application)">Reject</button>
            <button
              v-if="!['needs_resubmission', 'pending_review'].includes(drawer.application.resubmission_status)"
              type="button"
              class="ghost-btn"
              @click="askResubmission(drawer.application)"
            >
              Ask for corrections
            </button>
            <button type="button" class="primary-btn ok" @click="askApprove(drawer.application)">Approve vendor</button>
          </footer>
        </template>

        <!-- Vendor -->
        <template v-else-if="drawer.mode === 'vendor'">
          <header class="drawer-head">
            <template v-if="profile">
              <VendorAvatar :vendor="profile.vendor" :size="46" />
              <div class="drawer-title">
                <h2>{{ profile.vendor.store_name }}</h2>
                <div class="drawer-meta">
                  <span class="pill" :class="profile.vendor.status === 'suspended' ? 'st-suspended' : 'st-active'">{{ profile.vendor.status === "suspended" ? "Suspended" : "Active" }}</span>
                  <span v-if="profile.vendor.subscription" class="pill" :class="`sub-${profile.vendor.subscription.status}`">{{ profile.vendor.subscription.plan_name }}</span>
                  <span class="sub">Vendor since {{ formatDate(profile.vendor.joined_at) }}</span>
                </div>
              </div>
            </template>
            <div v-else class="drawer-title">
              <div class="sk sk-line" style="width: 200px; height: 18px"></div>
              <div class="sk sk-line" style="width: 140px"></div>
            </div>
            <button type="button" class="icon-btn" aria-label="Close" @click="closeDrawer"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
          </header>

          <div v-if="profile?.vendor.status === 'suspended'" class="suspended-banner">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" /><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" /></svg>
            <div>
              <strong>Store suspended</strong>
              <span v-if="lastSuspension">{{ lastSuspension.reason }} · {{ lastSuspension.by }}, {{ formatDate(lastSuspension.at) }}</span>
              <span>Hidden from customers; the owner and staff can't sign in.</span>
            </div>
            <button type="button" class="mini-btn ok" @click="askReactivate(profile.vendor)">Reactivate</button>
          </div>

          <nav class="drawer-tabs" role="tablist">
            <button v-for="t in VENDOR_TABS" :key="t.key" type="button" role="tab" class="drawer-tab" :class="{ active: drawer.tab === t.key }" :aria-selected="drawer.tab === t.key" @click="setVendorTab(t.key)">
              {{ t.label }}
            </button>
          </nav>

          <div class="drawer-body">
            <p v-if="drawer.error" class="empty-msg">{{ drawer.error }}</p>

            <template v-else-if="!profile">
              <div v-for="n in 4" :key="n" class="card"><div class="sk sk-line" style="width: 40%"></div><div class="sk sk-line"></div></div>
            </template>

            <!-- Performance -->
            <template v-else-if="drawer.tab === 'performance'">
              <div class="stat-grid">
                <div class="stat"><span class="stat-label">Lifetime sales</span><span class="stat-value">{{ money(perf.sales) }}</span><span class="sub">{{ plural(perf.paid_orders, "paid order") }}</span></div>
                <div class="stat"><span class="stat-label">Avg. order value</span><span class="stat-value">{{ money(perf.average_order_value) }}</span><span class="sub">Paid orders</span></div>
                <div class="stat"><span class="stat-label">Rating</span><span class="stat-value">{{ profile.vendor.rating !== null ? "★ " + profile.vendor.rating.toFixed(1) : "—" }}</span><span class="sub">{{ plural(profile.vendor.reviews, "review") }}</span></div>
                <div class="stat"><span class="stat-label">Avg. fulfilment</span><span class="stat-value">{{ fulfilment(perf.avg_fulfilment_hours) }}</span><span class="sub">Order to delivered</span></div>
              </div>

              <div class="card">
                <div class="card-head">
                  <span class="card-label">Order outcomes</span>
                  <span class="sub">{{ plural(perf.finished_orders, "finished order") }}</span>
                </div>
                <div v-for="r in outcomeRates" :key="r.label" class="rate-row">
                  <span class="rate-label">{{ r.label }}</span>
                  <div class="rate-bar"><span :style="{ width: (r.value ?? 0) + '%', background: r.color }"></span></div>
                  <span class="rate-value">{{ r.value === null ? "—" : r.value + "%" }}</span>
                </div>
                <p v-if="perf.stale_pending" class="callout warn">{{ plural(perf.stale_pending, "order") }} pending for more than 3 days.</p>
                <p v-if="profile.vendor.open_disputes" class="callout rose">{{ plural(profile.vendor.open_disputes, "open return/refund request") }}.</p>
                <p v-if="profile.vendor.strikes" class="callout red">{{ profile.vendor.strikes }}/3 strikes from banned products. The third suspends the store automatically.</p>
              </div>

              <div class="card">
                <div class="card-head">
                  <span class="card-label">Last 6 months</span>
                  <span class="sub">Bars: sales · dots: orders</span>
                </div>
                <div class="month-chart">
                  <div v-for="m in perf.months" :key="m.label" class="month">
                    <div class="month-bar-wrap">
                      <span class="month-orders" :style="{ bottom: barPct(m.orders, maxMonthOrders) + '%' }" :title="`${m.orders} orders`"></span>
                      <span class="month-bar" :style="{ height: barPct(m.sales, maxMonthSales) + '%' }" :title="money(m.sales)"></span>
                    </div>
                    <span class="month-label">{{ m.label }}</span>
                    <span class="month-value">{{ moneyShort(m.sales) }}</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- Products -->
            <template v-else-if="drawer.tab === 'products'">
              <p v-if="!profile.products.length" class="empty-msg">This vendor hasn't added products yet.</p>
              <div v-else class="product-grid">
                <article v-for="p in profile.products" :key="p.id" class="product">
                  <div class="product-img">
                    <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy" />
                    <span v-else>🌸</span>
                    <span class="pill product-status" :class="`pr-${p.status}`">{{ humanize(p.status) }}</span>
                  </div>
                  <div class="product-body">
                    <span class="cell-main">{{ p.name }}</span>
                    <span class="sub">{{ humanize(p.category) }}</span>
                    <div class="product-row">
                      <span class="strong">{{ money(p.price) }}</span>
                      <span v-if="p.rating !== null" class="rating small">★ {{ p.rating.toFixed(1) }}</span>
                    </div>
                    <div class="product-row sub">
                      <span :class="{ low: p.low_stock }">{{ p.stock }} in stock</span>
                      <span>{{ p.sold }} sold</span>
                    </div>
                  </div>
                </article>
              </div>
            </template>

            <!-- Orders -->
            <template v-else-if="drawer.tab === 'orders'">
              <div class="chip-row">
                <span v-for="(count, status) in profile.orders.counts" :key="status" class="count-chip"><span class="pill" :class="`st-${status}`">{{ ORDER_STATUS[status] ?? status }}</span> {{ count }}</span>
                <span v-if="!Object.keys(profile.orders.counts).length" class="sub">No orders yet.</span>
              </div>
              <ul v-if="profile.orders.recent.length" class="row-list">
                <li v-for="o in profile.orders.recent" :key="o.id" tabindex="0" @click="goToOrder(o.id)" @keydown.enter="goToOrder(o.id)">
                  <div class="row-main">
                    <span class="cell-main mono">{{ o.order_number }}</span>
                    <span class="sub">{{ o.customer || "Customer" }} · {{ plural(o.items_count, "item") }} · {{ o.method_label }} · {{ timeAgo(o.created_at) }}</span>
                  </div>
                  <div class="row-right">
                    <span class="strong">{{ money(o.total_amount) }}</span>
                    <span class="pill" :class="`st-${o.status}`">{{ ORDER_STATUS[o.status] ?? o.status }}</span>
                  </div>
                </li>
              </ul>
              <router-link v-if="profile.orders.recent.length" to="/admin/orders" class="link-btn block">Open Orders to search all of them →</router-link>
            </template>

            <!-- Subscription -->
            <template v-else-if="drawer.tab === 'subscription'">
              <div v-if="canManagePlans" class="plan-actions">
                <span class="sub">Put this shop on any plan. You'll confirm before anything changes.</span>
                <button type="button" class="primary-btn" @click="askPlan(profile)">Change plan</button>
              </div>
              <div v-if="profile.subscription.current" class="plan-card" :class="`sub-${profile.subscription.current.status}`">
                <div>
                  <span class="card-label">Current plan</span>
                  <span class="plan-name">{{ profile.subscription.current.plan_name }}</span>
                  <span class="pill" :class="`sub-${profile.subscription.current.status}`">{{ SUB_STATUS[profile.subscription.current.status] ?? profile.subscription.current.status }}</span>
                </div>
                <dl class="facts compact">
                  <dt>Started</dt><dd>{{ formatDate(profile.subscription.current.started_at) }}</dd>
                  <dt>{{ profile.subscription.current.status === "trialing" ? "Trial ends" : "Renews / ends" }}</dt><dd>{{ formatDate(profile.subscription.current.ends_at) }}</dd>
                  <dt>Last paid</dt><dd>{{ profile.subscription.current.paid_amount !== null ? money(profile.subscription.current.paid_amount) : "—" }}</dd>
                  <dt>Lifetime paid</dt><dd>{{ money(profile.subscription.lifetime_paid) }}</dd>
                </dl>
                <div v-if="profile.subscription.current.resource_limits" class="limits">
                  <span v-for="(limit, key) in profile.subscription.current.resource_limits" :key="key" class="limit">
                    <b>{{ limit === null ? "∞" : limit }}</b> {{ LIMIT_LABELS[key] ?? key }}
                  </span>
                </div>
              </div>
              <p v-else class="empty-msg">No subscription yet.</p>

              <div class="card">
                <span class="card-label">Subscription payments</span>
                <p v-if="!profile.subscription.payments.length" class="sub">No payments started.</p>
                <ul v-else class="row-list flat">
                  <li v-for="p in profile.subscription.payments" :key="p.id">
                    <div class="row-main">
                      <span class="cell-main">{{ p.plan_name }}</span>
                      <span class="sub mono">{{ p.reference }} · {{ formatDate(p.paid_at || p.created_at) }}</span>
                    </div>
                    <div class="row-right">
                      <span class="strong">{{ money(p.amount) }}</span>
                      <span class="pill" :class="`pay-${p.status}`">{{ PAYMENT_STATUS[p.status] ?? p.status }}</span>
                    </div>
                  </li>
                </ul>
              </div>
            </template>

            <!-- Registration -->
            <template v-else-if="drawer.tab === 'registration'">
              <template v-if="registration.loading">
                <div v-for="n in 3" :key="n" class="card"><div class="sk sk-line" style="width: 40%"></div><div class="sk sk-line"></div></div>
              </template>
              <p v-else-if="!registration.app" class="empty-msg">No registration on file for this vendor.</p>
              <VendorApplicationDetails v-else :app="registration.app" :resubmissions="registration.resubmissions" @view-document="openDocument" />
            </template>

            <!-- History -->
            <template v-else-if="drawer.tab === 'history'">
              <ol class="timeline">
                <li v-for="e in profile.history" :key="e.id" :class="`ev-${e.action}`">
                  <span class="ev-dot"></span>
                  <div>
                    <span class="cell-main">{{ HISTORY_LABEL[e.action] ?? "Changed" }} by {{ e.by }}</span>
                    <span v-if="e.reason" class="ev-reason">“{{ e.reason }}”</span>
                    <span class="sub">{{ formatDateTime(e.at) }}</span>
                  </div>
                </li>
                <li class="ev-joined">
                  <span class="ev-dot"></span>
                  <div>
                    <span class="cell-main">Approved as a vendor</span>
                    <span class="sub">{{ formatDateTime(profile.vendor.joined_at) }}</span>
                  </div>
                </li>
              </ol>
            </template>
          </div>

          <footer v-if="profile && profile.vendor.status === 'active'" class="drawer-foot">
            <span class="sub foot-note">Suspending hides the store and signs out the owner and staff.</span>
            <button type="button" class="ghost-btn danger-text" @click="askSuspend(profile.vendor)">Suspend vendor</button>
          </footer>
        </template>
      </aside>
    </Transition>

    <!-- ═══ Dialogs ═══ -->
    <Transition name="fade">
      <div v-if="dialog.type" class="dialog-backdrop" @click.self="closeDialog">
        <div class="dialog" role="dialog" aria-modal="true" @keydown.esc="closeDialog">
          <!-- Approve -->
          <template v-if="dialog.type === 'approve'">
            <span class="dialog-icon ok"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><polyline points="20 6 9 17 4 12" /></svg></span>
            <h3>Approve {{ dialog.target.store_name }}?</h3>
            <p>A vendor account is created and the owner is emailed so they can finish setting up their store.</p>
          </template>

          <!-- Reject -->
          <template v-else-if="dialog.type === 'reject'">
            <span class="dialog-icon bad"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></span>
            <h3>Reject {{ dialog.target.store_name }}?</h3>
            <p>The owner gets this reason by email.</p>
            <textarea v-model="dialog.text" rows="4" placeholder="Explain what's missing or wrong (at least 10 characters)" :class="{ invalid: dialog.error }"></textarea>
          </template>

          <!-- Ask for corrections -->
          <template v-else-if="dialog.type === 'resubmission'">
            <h3>Ask {{ dialog.target.store_name }} for corrections</h3>
            <p>Pick only what needs fixing. The owner gets a link to resubmit those items.</p>
            <div v-if="dialog.loading" class="sk sk-line" style="height: 120px"></div>
            <div v-else class="field-list">
              <div v-for="field in dialog.fields" :key="field.field_name" class="field-row">
                <label class="check">
                  <input v-model="dialog.selected" type="checkbox" :value="field.field_name" />
                  <span>{{ field.label }}</span>
                </label>
                <textarea v-if="dialog.selected.includes(field.field_name)" v-model="dialog.reasons[field.field_name]" rows="2" :placeholder="`What's wrong with the ${field.label.toLowerCase()}? (optional)`"></textarea>
              </div>
            </div>
          </template>

          <!-- Ask again for one item -->
          <template v-else-if="dialog.type === 'again'">
            <h3>Ask again for {{ dialog.target.label }}</h3>
            <p>The owner will be asked to resubmit this item.</p>
            <textarea v-model="dialog.text" rows="3" placeholder="What's still wrong? (optional)"></textarea>
          </template>

          <!-- Suspend -->
          <template v-else-if="dialog.type === 'suspend'">
            <span class="dialog-icon bad"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10" /><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" /></svg></span>
            <h3>Suspend {{ dialog.target.store_name }}?</h3>
            <ul class="impact">
              <li>The store and its products disappear from the shop, and no new orders can be placed.</li>
              <li>The owner and all their staff are signed out and can't sign back in.</li>
              <li v-if="dialog.target.pending_orders">{{ plural(dialog.target.pending_orders, "pending order") }} will wait until the store is reactivated.</li>
              <li>Payments, balances and past orders are kept as they are.</li>
            </ul>
            <textarea v-model="dialog.text" rows="3" placeholder="Reason (kept in the vendor's history)" :class="{ invalid: dialog.error }"></textarea>
          </template>

          <!-- Reactivate -->
          <template v-else-if="dialog.type === 'reactivate'">
            <span class="dialog-icon ok"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" /></svg></span>
            <h3>Reactivate {{ dialog.target.store_name }}?</h3>
            <p>The store becomes visible again and the owner and staff can sign in.<template v-if="dialog.target.strikes"> They still have {{ dialog.target.strikes }}/3 strikes.</template></p>
            <textarea v-model="dialog.text" rows="2" placeholder="Note for the history (optional)"></textarea>
          </template>

          <!-- Change plan -->
          <template v-else-if="dialog.type === 'plan'">
            <h3>Change {{ dialog.target.store_name }}'s plan</h3>
            <p>Now: <b>{{ dialog.target.current ? `${dialog.target.current.plan_name} (${SUB_STATUS[dialog.target.current.status] ?? dialog.target.current.status})` : "No plan" }}</b>. The change starts right away, and no payment is taken.</p>
            <div class="plan-form">
              <label class="plan-field">
                <span>New plan</span>
                <select v-model="dialog.planKey">
                  <option v-for="o in PLAN_CHOICES" :key="o.key" :value="o.key">{{ o.label }}</option>
                </select>
              </label>
              <label v-if="dialog.planKey !== 'none'" class="plan-field">
                <span>For how long</span>
                <select v-model="dialog.months">
                  <option v-for="o in PLAN_LENGTHS" :key="String(o.months)" :value="o.months">{{ o.label }}</option>
                </select>
              </label>
            </div>
            <p class="plan-summary" :class="{ danger: dialog.planKey === 'none' }">{{ planSummary }}</p>
            <textarea v-model="dialog.text" rows="2" placeholder="Reason (the vendor sees it, and it's kept in their history)" :class="{ invalid: dialog.error && dialog.text.trim().length < 5 }"></textarea>
            <label class="plan-field plan-confirm">
              <span>Type <b>{{ dialog.target.store_name }}</b> to confirm</span>
              <input v-model="dialog.confirm" autocomplete="off" spellcheck="false" :placeholder="dialog.target.store_name" />
            </label>
          </template>

          <p v-if="dialog.error" class="dialog-error">{{ dialog.error }}</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="dialog.busy" @click="closeDialog">Cancel</button>
            <button type="button" class="primary-btn" :class="dialogTone" :disabled="dialog.busy || dialogDisabled" @click="confirmDialog">
              {{ dialog.busy ? "Working…" : DIALOG_CONFIRM[dialog.type] }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Document viewer -->
    <Transition name="fade">
      <div v-if="doc.url" class="dialog-backdrop" @click.self="doc.url = null">
        <div class="viewer" role="dialog" aria-modal="true">
          <header>
            <span class="cell-main">{{ doc.title }}</span>
            <div class="viewer-actions">
              <a :href="doc.url" target="_blank" rel="noopener" class="ghost-btn">Open in new tab ↗</a>
              <button type="button" class="icon-btn" aria-label="Close" @click="doc.url = null"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
            </div>
          </header>
          <iframe v-if="/\.pdf|\/raw\//i.test(doc.url)" :src="doc.url" title="Document"></iframe>
          <img v-else :src="doc.url" :alt="doc.title" />
        </div>
      </div>
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
import VendorApplicationDetails from "./components/VendorApplicationDetails.vue";
import api from "../../plugins/axios";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMoreLocal, useShowMoreRows } from "../../composables/useShowMore";
import { linkedId } from "../../utils/hiddenIds";
import { useAdminAccess } from "../../composables/useAdminAccess";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

const icon = (path) => `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${path}</svg>`;
const TABS = [
  { key: "pending", label: "Applications", hint: "Waiting for review", tone: "violet", icon: icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>') },
  { key: "active", label: "Active vendors", hint: "Selling on BloomCraft", tone: "green", icon: icon('<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>') },
  { key: "suspended", label: "Suspended", hint: "Hidden and signed out", tone: "red", icon: icon('<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>') },
  { key: "rejected", label: "Rejected", hint: "Applications turned down", tone: "gray", icon: icon('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>') },
];
const VENDOR_TABS = [
  { key: "performance", label: "Performance" },
  { key: "products", label: "Products" },
  { key: "orders", label: "Orders" },
  { key: "subscription", label: "Subscription" },
  { key: "registration", label: "Registration" },
  { key: "history", label: "History" },
];
const APP_STATUS_LABELS = {
  pending: "New",
  needs_resubmission: "Waiting for corrections",
  pending_review: "Corrections received",
  approved: "Approved",
  rejected: "Rejected",
  under_review: "Under review",
};
const ORDER_STATUS = { pending: "Pending", processing: "Processing", out_for_delivery: "Out for delivery", completed: "Completed", cancelled: "Cancelled", failed: "Failed", returned: "Returned", refunded: "Refunded" };
const SUB_STATUS = { active: "Paid", trialing: "Free trial", expired: "Expired", cancelled: "Cancelled", past_due: "Past due", pending: "Pending" };
const PAYMENT_STATUS = { paid: "Paid", pending: "Not completed", failed: "Failed", cancelled: "Cancelled" };
const LIMIT_LABELS = { branches: "branches", warehouses: "warehouses", staff_employees: "staff" };
const DIALOG_CONFIRM = { approve: "Approve", reject: "Reject application", resubmission: "Send request", again: "Ask again", suspend: "Suspend vendor", reactivate: "Reactivate", plan: "Change plan" };
const HISTORY_LABEL = { suspended: "Suspended", reactivated: "Reactivated", plan_changed: "Plan changed" };
// Admin → change a shop's plan (AdminVendorController@changePlan)
const PLAN_CHOICES = [
  { key: "starter", label: "Starter" },
  { key: "business", label: "Business" },
  { key: "professional", label: "Professional" },
  { key: "enterprise", label: "Enterprise" },
  { key: "none", label: "No plan (remove it)" },
];
const PLAN_LENGTHS = [
  { months: 1, label: "1 month" },
  { months: 3, label: "3 months" },
  { months: 6, label: "6 months" },
  { months: 12, label: "12 months" },
  { months: 24, label: "24 months" },
  { months: null, label: "No end date" },
];

// ── Summary ──────────────────────────────────────────────────────
const summary = ref(null);
const fetchSummary = async () => {
  try {
    const { data } = await api.get("/admin/vendors/summary");
    summary.value = data.data;
  } catch {
    // the cards just show no counts
  }
};

// ── Lists ────────────────────────────────────────────────────────
const tab = ref(TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "pending");
const isApplicationTab = computed(() => tab.value === "pending" || tab.value === "rejected");
const search = ref("");
const appFilters = reactive({ business_type: "", start_date: "", end_date: "" });
const vendorSort = ref("newest");
const applications = ref([]);
const vendors = ref([]);
const listLoading = ref(false);
const pager = reactive({ page: 1, lastPage: 1, total: 0, perPage: 12 });

const hasFilters = computed(() => Boolean(search.value.trim() || appFilters.business_type || appFilters.start_date || appFilters.end_date));
// Vendors are all loaded at once; applications come a page at a time
const vendorList = useShowMoreLocal(vendors, () => [tab.value, search.value, vendorSort.value]);

let listRequest = 0;
const fetchList = async (page = 1) => {
  const id = ++listRequest;
  listLoading.value = true;
  try {
    if (isApplicationTab.value) {
      const { data } = await api.get("/admin/vendor-applications", {
        params: {
          status: tab.value,
          page,
          per_page: pager.perPage,
          search: search.value.trim() || undefined,
          ...Object.fromEntries(Object.entries(appFilters).filter(([, v]) => v)),
        },
      });
      if (id !== listRequest) return;
      applications.value = page > 1 ? [...applications.value, ...(data.data ?? [])] : data.data ?? [];
      Object.assign(pager, { page: data.meta?.current_page ?? 1, lastPage: data.meta?.last_page ?? 1, total: data.meta?.total ?? applications.value.length });
    } else {
      const { data } = await api.get("/admin/vendors", {
        params: { status: tab.value, search: search.value.trim() || undefined, sort: vendorSort.value },
      });
      if (id !== listRequest) return;
      vendors.value = data.data ?? [];
      Object.assign(pager, { page: 1, total: vendors.value.length, lastPage: 1 });
    }
  } catch {
    if (id === listRequest) toast.error("The list couldn't be loaded.");
  } finally {
    if (id === listRequest) listLoading.value = false;
  }
};

// 10 applications first, then 10 more, then 20 at a time
const appList = useShowMoreRows(applications, computed(() => ({ current_page: pager.page, last_page: pager.lastPage, total: pager.total })), fetchList);

const setTab = (key) => {
  if (tab.value === key) return;
  tab.value = key;
  applications.value = [];
  vendors.value = [];
  pager.page = 1;
  router.replace({ query: { ...route.query, tab: key } });
  appList.load();
};

const clearFilters = () => {
  search.value = "";
  Object.assign(appFilters, { business_type: "", start_date: "", end_date: "" });
};

const refresh = () => {
  fetchSummary();
  appList.refresh();
  if (drawer.mode === "vendor" && drawer.vendorId) loadVendor(drawer.vendorId);
};

let searchTimer = null;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => appList.load(), 300);
});
watch(() => ({ ...appFilters }), () => appList.load());
watch(vendorSort, () => appList.load());

// ── Export ───────────────────────────────────────────────────────
const exporting = ref(false);
const exportApplications = async () => {
  exporting.value = true;
  try {
    const response = await api.get("/admin/vendor-applications/export", {
      params: { status: tab.value, ...Object.fromEntries(Object.entries(appFilters).filter(([, v]) => v)) },
      responseType: "blob",
    });
    const url = URL.createObjectURL(new Blob([response.data], { type: response.headers["content-type"] || "text/csv" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = `vendor-applications-${tab.value}-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  } catch {
    toast.error("The export couldn't be created.");
  } finally {
    exporting.value = false;
  }
};

// ── Drawer ───────────────────────────────────────────────────────
const drawer = reactive({ open: false, mode: null, application: null, resubmissions: [], vendorId: null, tab: "performance", error: "" });
const drawerEl = ref(null);
const profile = ref(null);
const registration = reactive({ loading: false, app: null, resubmissions: [], loadedFor: null });
let lastFocus = null;

const perf = computed(() => profile.value?.performance ?? {});
const lastSuspension = computed(() => profile.value?.history.find((e) => e.action === "suspended"));
const maxMonthSales = computed(() => Math.max(0, ...(perf.value.months ?? []).map((m) => m.sales)));
const maxMonthOrders = computed(() => Math.max(0, ...(perf.value.months ?? []).map((m) => m.orders)));
const outcomeRates = computed(() => [
  { label: "Completed", value: perf.value.completion_rate, color: "#22c55e" },
  { label: "Cancelled", value: perf.value.cancellation_rate, color: "#94a3b8" },
  { label: "Returned / refunded", value: perf.value.return_rate, color: "#8b5cf6" },
]);

const showDrawer = async () => {
  if (!drawer.open) lastFocus = document.activeElement;
  drawer.open = true;
  await nextTick();
  drawerEl.value?.focus();
};

const openApplication = async (application) => {
  Object.assign(drawer, { mode: "application", application, resubmissions: [], vendorId: null, error: "" });
  showDrawer();
  try {
    const { data } = await api.get(`/admin/vendor-applications/${application.id}/resubmissions`);
    if (drawer.application?.id === application.id) drawer.resubmissions = data.items || [];
  } catch {
    // resubmissions are optional detail
  }
};

let vendorRequest = 0;
const loadVendor = async (id) => {
  const requestId = ++vendorRequest;
  drawer.error = "";
  try {
    const { data } = await api.get(`/admin/vendors/${id}`);
    if (requestId === vendorRequest) profile.value = data.data;
  } catch (error) {
    if (requestId === vendorRequest) drawer.error = error.response?.status === 404 ? "This vendor no longer exists." : "This vendor couldn't be loaded.";
  }
};

const openVendor = (id, vendorTab = "performance") => {
  if (drawer.vendorId !== id) {
    profile.value = null;
    Object.assign(registration, { app: null, resubmissions: [], loadedFor: null });
  }
  Object.assign(drawer, { mode: "vendor", vendorId: id, application: null, tab: vendorTab });
  router.replace({ query: { ...route.query, vendor: id } });
  showDrawer();
  loadVendor(id);
};

const setVendorTab = async (key) => {
  drawer.tab = key;
  if (key !== "registration" || !profile.value?.application_id || registration.loadedFor === profile.value.application_id) return;
  const appId = profile.value.application_id;
  registration.loading = true;
  try {
    const [{ data: app }, resub] = await Promise.all([
      api.get(`/admin/vendor-applications/${appId}`),
      api.get(`/admin/vendor-applications/${appId}/resubmissions`).catch(() => ({ data: { items: [] } })),
    ]);
    Object.assign(registration, { app, resubmissions: resub.data.items || [], loadedFor: appId });
  } catch {
    toast.error("The registration details couldn't be loaded.");
  } finally {
    registration.loading = false;
  }
};

const closeDrawer = () => {
  drawer.open = false;
  vendorRequest++;
  router.replace({ query: { ...route.query, vendor: null } });
  lastFocus?.focus?.();
};

const goToOrder = (id) => router.push({ path: "/admin/orders", query: { order: id } });

// ── Documents ────────────────────────────────────────────────────
const doc = reactive({ url: null, title: "" });
const openDocument = (url, title) => {
  if (!url) return toast.error("This document isn't available.");
  Object.assign(doc, { url, title: title || "Document" });
};

// ── Dialogs ──────────────────────────────────────────────────────
const blankDialog = () => ({ type: null, target: null, text: "", error: "", busy: false, loading: false, fields: [], selected: [], reasons: {}, planKey: "starter", months: 1, confirm: "" });
const dialog = reactive(blankDialog());
const dialogTone = computed(() => (["reject", "suspend"].includes(dialog.type) || (dialog.type === "plan" && dialog.planKey === "none") ? "danger" : ["approve", "reactivate"].includes(dialog.type) ? "ok" : ""));
const sameName = (a, b) => String(a ?? "").trim().toLowerCase() === String(b ?? "").trim().toLowerCase();
const dialogDisabled = computed(
  () => (dialog.type === "resubmission" && !dialog.selected.length) || (dialog.type === "plan" && !sameName(dialog.confirm, dialog.target?.store_name)),
);
const planSummary = computed(() => {
  if (dialog.type !== "plan") return "";
  const from = dialog.target.current?.plan_name ?? "No plan";
  if (dialog.planKey === "none") return `${from} → No plan. Plan features stop working for this shop right away.`;
  const to = PLAN_CHOICES.find((o) => o.key === dialog.planKey)?.label;
  if (!dialog.months) return `${from} → ${to}, with no end date.`;
  const end = new Date();
  end.setMonth(end.getMonth() + dialog.months);
  return `${from} → ${to}, until ${formatDate(end.toISOString())}.`;
});
const { can: adminCan } = useAdminAccess();
const canManagePlans = computed(() => adminCan("subscriptions.manage"));

const openDialog = (type, target, extra = {}) => Object.assign(dialog, blankDialog(), { type, target }, extra);
const closeDialog = () => {
  if (!dialog.busy) Object.assign(dialog, blankDialog());
};

const askApprove = (a) => openDialog("approve", a);
const askReject = (a) => openDialog("reject", a);
const askSuspend = (v) => openDialog("suspend", v);
const askPlan = (p) => {
  const current = p.subscription.current;
  const next = PLAN_CHOICES.find((o) => o.key !== "none" && o.key !== current?.plan_key)?.key ?? "starter";
  openDialog("plan", { id: p.vendor.id, store_name: p.vendor.store_name, current }, { planKey: next, months: 1 });
};
const askReactivate = (v) => openDialog("reactivate", v);
const askAgain = (item) => openDialog("again", item, { text: item.rejection_reason || "" });
const askResubmission = async (a) => {
  openDialog("resubmission", a, { loading: true });
  try {
    const { data } = await api.get("/admin/vendor-resubmission-fields");
    dialog.fields = data.fields || [];
  } catch {
    toast.error("The list of requirements couldn't be loaded.");
    Object.assign(dialog, blankDialog());
  } finally {
    dialog.loading = false;
  }
};

const apiMessage = (error, fallback) => error?.response?.data?.message || error?.response?.data?.error || fallback;

const afterApplicationChange = async () => {
  if (drawer.mode === "application") closeDrawer();
  await Promise.all([appList.refresh(), fetchSummary()]);
};

const confirmDialog = async () => {
  const { type, target } = dialog;
  const text = dialog.text.trim();
  dialog.error = "";

  if (type === "reject" && text.length < 10) return (dialog.error = "Give a reason of at least 10 characters.");
  if (type === "suspend" && text.length < 5) return (dialog.error = "Give a reason of at least 5 characters.");
  if (type === "plan" && text.length < 5) return (dialog.error = "Say why the plan is changing (at least 5 characters).");
  if (type === "plan" && !sameName(dialog.confirm, target.store_name)) return (dialog.error = `Type “${target.store_name}” to confirm.`);

  dialog.busy = true;
  try {
    let message = "";
    if (type === "approve") {
      ({ data: { message } } = await api.post(`/admin/vendor-applications/${target.id}/approve`));
      await afterApplicationChange();
    } else if (type === "reject") {
      ({ data: { message } } = await api.post(`/admin/vendor-applications/${target.id}/reject`, { rejection_reason: text }));
      await afterApplicationChange();
    } else if (type === "resubmission") {
      ({ data: { message } } = await api.post(`/admin/vendor-applications/${target.id}/resubmission-requests`, {
        fields: dialog.selected.map((field_name) => ({ field_name, rejection_reason: dialog.reasons[field_name]?.trim() || null })),
      }));
      await afterApplicationChange();
    } else if (type === "again") {
      ({ data: { message } } = await api.post(`/admin/vendor-application-resubmissions/${target.id}/request-again`, { rejection_reason: text || null }));
      await reloadResubmissions();
      appList.refresh();
    } else if (type === "plan") {
      ({ data: { message } } = await api.post(`/admin/vendors/${target.id}/plan`, {
        plan_key: dialog.planKey,
        months: dialog.planKey === "none" ? null : dialog.months,
        reason: text,
        confirm: dialog.confirm.trim(),
      }));
      await Promise.all([appList.load(), fetchSummary(), drawer.vendorId === target.id ? loadVendor(target.id) : null]);
    } else if (type === "suspend" || type === "reactivate") {
      const body = type === "suspend" ? { reason: text } : { note: text || null };
      ({ data: { message } } = await api.post(`/admin/vendors/${target.id}/${type}`, body));
      await Promise.all([appList.load(), fetchSummary(), drawer.vendorId === target.id ? loadVendor(target.id) : null]);
    }
    toast.success(message || "Done.");
    dialog.busy = false;
    closeDialog();
  } catch (error) {
    dialog.busy = false;
    dialog.error = apiMessage(error, "That didn't work. Try again.");
  }
};

const reloadResubmissions = async () => {
  if (!drawer.application) return;
  const { data } = await api.get(`/admin/vendor-applications/${drawer.application.id}/resubmissions`);
  drawer.resubmissions = data.items || [];
};

const approveResubmission = async (item) => {
  try {
    const { data } = await api.post(`/admin/vendor-application-resubmissions/${item.id}/approve`);
    toast.success(data.message || "Requirement accepted.");
    await reloadResubmissions();
    appList.refresh();
  } catch (error) {
    toast.error(apiMessage(error, "The requirement couldn't be accepted."));
  }
};

// ── Formatting ───────────────────────────────────────────────────
const appStatus = (a) => (a.status === "pending" && a.resubmission_status ? a.resubmission_status : a.status);
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => (v === null || v === undefined ? "—" : peso.format(Number(v)));
const moneyShort = (value) => {
  const n = Number(value) || 0;
  if (n >= 1_000_000) return `₱${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`;
  if (n >= 1_000) return `₱${(n / 1_000).toFixed(1).replace(/\.0$/, "")}K`;
  return `₱${n.toFixed(0)}`;
};
const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const humanize = (s) => (s ? String(s).replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()) : "—");
const businessType = (t) => ({ individual: "Sole proprietor", partnership: "Partnership", corporation: "Corporation" })[t] ?? t ?? "—";
const shortAddress = (address) => {
  if (!address) return "No address";
  const parts = address.split(",").map((p) => p.trim()).filter(Boolean);
  return parts.slice(-2).join(", ");
};
const barPct = (value, max) => (max > 0 ? Math.max(value > 0 ? 6 : 0, Math.round((value / max) * 100)) : 0);
const fulfilment = (hours) => {
  if (hours === null || hours === undefined) return "—";
  return hours < 48 ? `${Math.round(hours)} h` : `${(hours / 24).toFixed(1)} days`;
};
const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const timeAgo = (v) => {
  if (!v) return "";
  const days = Math.floor((Date.now() - new Date(v)) / 86_400_000);
  if (days < 1) return "today";
  if (days === 1) return "yesterday";
  if (days < 31) return `${days} days ago`;
  const months = Math.floor(days / 30);
  return months < 12 ? `${months} month${months === 1 ? "" : "s"} ago` : `${Math.floor(months / 12)} year${months >= 24 ? "s" : ""} ago`;
};

// ── Lifecycle ────────────────────────────────────────────────────
const onKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (doc.url) doc.url = null;
  else if (dialog.type) closeDialog();
  else if (drawer.open) closeDrawer();
};

onMounted(() => {
  fetchSummary();
  appList.load();
  const vendorId = linkedId("vendor");
  if (vendorId) openVendor(vendorId);
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
  text-decoration: none;
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
  border-color: #fecaca;
  background: #fef2f2;
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
.primary-btn.ok {
  background: #16a34a;
}
.primary-btn.ok:hover:not(:disabled) {
  background: #15803d;
}
.primary-btn.danger {
  background: #dc2626;
}
.primary-btn.danger:hover:not(:disabled) {
  background: #b91c1c;
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
.link-btn.block {
  display: inline-block;
  margin-top: 10px;
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
.mini-btn.danger {
  color: #b91c1c;
}
.mini-btn.danger:hover {
  background: #fef2f2;
  border-color: #fecaca;
}
.mini-btn.ok {
  background: #16a34a;
  border-color: transparent;
  color: #fff;
}
.icon-action {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #6d5ff0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.icon-action.ok {
  color: #16a34a;
}
.icon-action.ok:hover {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}
.icon-action.bad {
  color: #dc2626;
}
.icon-action.bad:hover {
  background: #dc2626;
  border-color: #dc2626;
  color: #fff;
}
.icon-action:not(.ok):not(.bad):hover {
  background: #ede9fe;
}

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
  width: 28px;
  height: 20px;
  vertical-align: middle;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Tab cards */
.tab-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.tab-card {
  --c: #7c6ff7;
  --bg: #f5f3ff;
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-rows: auto auto;
  column-gap: 12px;
  align-items: center;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid #e9edf3;
  background: #fff;
  cursor: pointer;
  text-align: left;
  position: relative;
  overflow: hidden;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.tab-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}
.tab-card.active {
  border-color: var(--c);
  background: var(--bg);
}
.tab-card::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 3px;
  background: var(--c);
  opacity: 0;
}
.tab-card.active::after {
  opacity: 1;
}
.tab-icon {
  grid-row: span 2;
  width: 40px;
  height: 40px;
  border-radius: 11px;
  background: var(--bg);
  color: var(--c);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.tab-card.active .tab-icon {
  background: #fff;
}
.tab-text {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}
.tab-label {
  font-size: 13px;
  font-weight: 600;
  color: #1e2739;
}
.tab-count {
  font-size: 22px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.tab-hint {
  font-size: 11.5px;
  color: #94a3b8;
}
.tone-green { --c: #16a34a; --bg: #f0fdf4; }
.tone-red { --c: #dc2626; --bg: #fef2f2; }
.tone-gray { --c: #64748b; --bg: #f8fafc; }

/* Panel & toolbar */
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
.date-field {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #94a3b8;
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
  padding: 12px 10px;
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
  max-width: 260px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
  font-weight: 400;
}
.vendor-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.row-actions {
  display: inline-flex;
  gap: 6px;
}
.chev {
  color: #cbd5e1;
}
.rating {
  color: #b45309;
  font-weight: 600;
  font-size: 12.5px;
}
.rating.small {
  font-size: 11.5px;
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
.flag.rose { background: #fff1f2; color: #be123c; }
.flag.amber { background: #fffbeb; color: #b45309; }
.flag.red { background: #fef2f2; color: #b91c1c; }

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
.ap-pending { background: #ede9fe; color: #6d28d9; }
.ap-needs_resubmission { background: #fef3c7; color: #b45309; }
.ap-pending_review { background: #dbeafe; color: #1d4ed8; }
.ap-approved { background: #dcfce7; color: #15803d; }
.ap-rejected { background: #fee2e2; color: #b91c1c; }
.st-active { background: #dcfce7; color: #15803d; }
.st-suspended { background: #fee2e2; color: #b91c1c; }
.sub-active { background: #dcfce7; color: #15803d; }
.sub-trialing { background: #e0f2fe; color: #0369a1; }
.sub-expired,
.sub-cancelled { background: #f1f5f9; color: #64748b; }
.sub-past_due { background: #fee2e2; color: #b91c1c; }
.st-pending { background: #fef3c7; color: #b45309; }
.st-processing { background: #dbeafe; color: #1d4ed8; }
.st-out_for_delivery { background: #e0f2fe; color: #0369a1; }
.st-completed { background: #dcfce7; color: #15803d; }
.st-cancelled,
.st-failed { background: #f1f5f9; color: #64748b; }
.st-returned,
.st-refunded { background: #ede9fe; color: #6d28d9; }
.pay-paid { background: #dcfce7; color: #15803d; }
.pay-pending { background: #fef3c7; color: #b45309; }
.pay-failed { background: #fee2e2; color: #b91c1c; }
.pr-active { background: #dcfce7; color: #15803d; }
.pr-draft { background: #fef3c7; color: #b45309; }

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
  width: min(680px, 100vw);
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
.drawer-tabs {
  display: flex;
  gap: 2px;
  padding: 0 14px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  overflow-x: auto;
}
.drawer-tab {
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
.empty-msg {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 28px 0;
  margin: 0;
}

/* Drawer content */
.card {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 16px;
}
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.card-label {
  display: block;
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94a3b8;
}
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 12px 14px;
  min-width: 0;
}
.stat-label {
  font-size: 11.5px;
  color: #64748b;
}
.stat-value {
  font-size: 17px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.rate-row {
  display: grid;
  grid-template-columns: 140px 1fr 48px;
  align-items: center;
  gap: 10px;
  margin: 8px 0;
  font-size: 12.5px;
}
.rate-label {
  color: #475569;
}
.rate-bar {
  height: 7px;
  border-radius: 7px;
  background: #f1f5f9;
  overflow: hidden;
}
.rate-bar span {
  display: block;
  height: 100%;
  border-radius: 7px;
}
.rate-value {
  text-align: right;
  font-weight: 600;
  color: #1e2739;
}
.callout {
  margin: 8px 0 0;
  padding: 8px 10px;
  border-radius: 8px;
  font-size: 12.5px;
}
.callout.warn { background: #fffbeb; color: #92400e; }
.callout.rose { background: #fff1f2; color: #9f1239; }
.callout.red { background: #fef2f2; color: #991b1b; }
.month-chart {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
  align-items: end;
}
.month {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.month-bar-wrap {
  position: relative;
  width: 100%;
  height: 120px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: linear-gradient(#f8fafc, #f8fafc) bottom / 100% 1px no-repeat;
}
.month-bar {
  width: 60%;
  max-width: 34px;
  border-radius: 6px 6px 2px 2px;
  background: linear-gradient(180deg, #a79ffb, #6d5ff0);
  min-height: 0;
  transition: height 0.4s ease;
}
.month-orders {
  position: absolute;
  left: 50%;
  width: 9px;
  height: 9px;
  margin-left: -4.5px;
  margin-bottom: -4.5px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #f59e0b;
  z-index: 1;
}
.month-label {
  font-size: 11.5px;
  color: #64748b;
}
.month-value {
  font-size: 11px;
  color: #94a3b8;
}
.product-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.product {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  overflow: hidden;
  min-width: 0;
}
.product-img {
  position: relative;
  aspect-ratio: 4 / 3;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
}
.product-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.product-status {
  position: absolute;
  top: 8px;
  left: 8px;
}
.product-body {
  padding: 10px 12px;
}
.product-row {
  display: flex;
  justify-content: space-between;
  gap: 6px;
  margin-top: 4px;
  font-size: 12.5px;
}
.product-row.sub {
  font-size: 11.5px;
}
.product-row .low {
  color: #dc2626;
  font-weight: 600;
}
.chip-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.count-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px 4px 4px;
  border-radius: 20px;
  background: #fff;
  border: 1px solid #e9edf3;
  font-size: 12.5px;
  font-weight: 600;
  color: #1e2739;
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
.row-list.flat {
  border: none;
  border-radius: 0;
}
.row-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border-bottom: 1px solid #f1f5f9;
}
.row-list li:last-child {
  border-bottom: none;
}
.row-list:not(.flat) li {
  cursor: pointer;
}
.row-list:not(.flat) li:hover {
  background: #fafaff;
}
.row-list.flat li {
  padding: 9px 0;
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
.plan-card {
  background: radial-gradient(120% 140% at 100% 0%, #f5f3ff 0%, #fff 70%);
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.plan-name {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: #1e2739;
  margin: 2px 0 6px;
}
.facts {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 6px 12px;
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
.limits {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.limit {
  font-size: 12px;
  color: #475569;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 8px;
  padding: 4px 10px;
}
.limit b {
  color: #1e2739;
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
.ev-plan_changed { --dot: #2563eb; }
.ev-joined { --dot: #7c6ff7; }
.ev-reason {
  display: block;
  font-size: 12.5px;
  color: #475569;
  font-style: italic;
}

/* Dialogs */
.dialog {
  width: min(480px, 100%);
  max-height: calc(100vh - 32px);
  overflow-y: auto;
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
.plan-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 12px;
}
.plan-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 10px;
}
.plan-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 12.5px;
  font-weight: 600;
  color: #334155;
}
.plan-field select,
.plan-field input {
  width: 100%;
  min-height: 40px;
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  color: #1e2739;
  font: inherit;
  font-size: 13px;
  font-weight: 400;
}
.plan-field select:focus-visible,
.plan-field input:focus-visible {
  outline: 2px solid #7c6ff7;
  outline-offset: 1px;
}
.plan-confirm {
  margin-top: 10px;
}
.plan-confirm b {
  color: #1e2739;
}
.dialog .plan-summary {
  padding: 9px 11px;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-weight: 600;
}
.dialog .plan-summary.danger {
  background: #fef2f2;
  color: #b91c1c;
}
@media (max-width: 480px) {
  .plan-form {
    grid-template-columns: 1fr;
  }
}
.impact {
  margin: 0 0 12px;
  padding-left: 18px;
  font-size: 12.5px;
  color: #475569;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.field-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 46vh;
  overflow-y: auto;
}
.field-row {
  border: 1px solid #eef1f6;
  border-radius: 10px;
  padding: 8px 10px;
}
.check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #1e2739;
  cursor: pointer;
}
.check input {
  accent-color: #7c6ff7;
}
.field-row textarea {
  margin-top: 8px;
}

/* Viewer */
.viewer {
  width: min(960px, 100%);
  max-height: calc(100vh - 32px);
  background: #fff;
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.viewer header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid #e9edf3;
}
.viewer-actions {
  display: flex;
  gap: 8px;
}
.viewer img,
.viewer iframe {
  width: 100%;
  max-height: calc(100vh - 110px);
  object-fit: contain;
  border: none;
  background: #f8fafc;
}
.viewer iframe {
  height: 75vh;
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
  .tab-grid {
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
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 0 16px 32px;
  }
  .tab-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .search-wrap {
    max-width: none;
  }
  .stat-grid,
  .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .rate-row {
    grid-template-columns: 100px 1fr 44px;
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
  .slide-enter-active,
  .slide-leave-active,
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}
</style>
