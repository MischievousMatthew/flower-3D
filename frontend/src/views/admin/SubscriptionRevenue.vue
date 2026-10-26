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
            <h1 class="page-title">Subscription Management</h1>
            <p class="page-subtitle">
              What vendors pay for their BloomCraft plan
              <template v-if="overview">· updated {{ updatedLabel }}</template>
            </p>
          </div>
        </div>

        <div class="header-right">
          <div class="range-group" role="tablist" aria-label="Date range">
            <button
              v-for="option in RANGES"
              :key="option.value"
              type="button"
              role="tab"
              class="range-btn"
              :class="{ active: range === option.value }"
              :aria-selected="range === option.value"
              @click="setRange(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
          <button type="button" class="ghost-btn" :disabled="loading" @click="refresh" title="Refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="23 4 23 10 17 10" />
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg>
            <span>{{ loading ? "Refreshing" : "Refresh" }}</span>
          </button>
        </div>
      </header>

      <div class="dashboard-body">
        <!-- Could not load -->
        <section v-if="loadError && !overview" class="panel error-panel">
          <h2 class="panel-title">The revenue figures couldn't be loaded</h2>
          <p class="panel-sub">{{ loadError }}</p>
          <button type="button" class="primary-btn" @click="refresh">Try again</button>
        </section>

        <template v-else>
          <!-- Headline KPIs -->
          <section class="kpi-row">
            <template v-if="!overview">
              <div v-for="n in 4" :key="n" class="kpi-card">
                <div class="sk sk-line" style="width: 45%"></div>
                <div class="sk sk-value"></div>
                <div class="sk sk-line" style="width: 60%"></div>
              </div>
            </template>
            <template v-else>
              <div class="kpi-card kpi-feature">
                <div class="kpi-top">
                  <span class="kpi-label">Monthly recurring revenue</span>
                  <span class="kpi-icon kpi-icon-purple">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /><polyline points="17 6 23 6 23 12" /></svg>
                  </span>
                </div>
                <div class="kpi-value">{{ money(kpis.mrr) }}</div>
                <div class="kpi-footer">
                  <span class="kpi-note">{{ money(kpis.arr) }} a year (ARR)</span>
                </div>
              </div>

              <div class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Revenue · {{ rangeLabel }}</span>
                  <span class="kpi-icon kpi-icon-green">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                  </span>
                </div>
                <div class="kpi-value">{{ money(kpis.revenue) }}</div>
                <div class="kpi-footer">
                  <span v-if="revenueChange" class="kpi-badge" :class="revenueChange.class">{{ revenueChange.text }}</span>
                  <span class="kpi-note">{{ comparisonNote }}</span>
                </div>
              </div>

              <div class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Paying vendors</span>
                  <span class="kpi-icon kpi-icon-blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>
                  </span>
                </div>
                <div class="kpi-value">{{ kpis.paying_vendors }}</div>
                <div class="kpi-footer">
                  <span class="kpi-badge" :class="kpis.new_paying ? 'badge-up' : 'badge-flat'">+{{ kpis.new_paying }} new</span>
                  <span class="kpi-note">{{ kpis.trialing_vendors }} on a free trial</span>
                </div>
              </div>

              <div class="kpi-card">
                <div class="kpi-top">
                  <span class="kpi-label">Avg. revenue per vendor</span>
                  <span class="kpi-icon kpi-icon-amber">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                  </span>
                </div>
                <div class="kpi-value">{{ money(kpis.arpu) }}</div>
                <div class="kpi-footer">
                  <span class="kpi-note">per paying vendor, monthly (ARPU)</span>
                </div>
              </div>
            </template>
          </section>

          <!-- Health metrics -->
          <section class="metric-row">
            <template v-if="!overview">
              <div v-for="n in 4" :key="n" class="metric-card">
                <div class="sk sk-line" style="width: 55%"></div>
                <div class="sk sk-line" style="width: 35%; height: 18px"></div>
              </div>
            </template>
            <template v-else>
              <div class="metric-card">
                <span class="metric-label">Churn rate</span>
                <span class="metric-value">{{ kpis.churn_rate }}%</span>
                <span class="metric-note">{{ plural(kpis.churned, "vendor") }} stopped paying · {{ rangeLabel }}</span>
              </div>
              <div class="metric-card">
                <span class="metric-label">Trial conversion</span>
                <span class="metric-value">{{ kpis.trial_conversion.rate === null ? "—" : kpis.trial_conversion.rate + "%" }}</span>
                <span class="metric-note">
                  <template v-if="kpis.trial_conversion.decided">{{ kpis.trial_conversion.converted }} of {{ plural(kpis.trial_conversion.decided, "finished trial") }} paid</template>
                  <template v-else>{{ plural(kpis.trial_conversion.trials, "trial") }}, none finished yet</template>
                </span>
              </div>
              <div class="metric-card">
                <span class="metric-label">Checkout success</span>
                <span class="metric-value">{{ kpis.checkout_success_rate === null ? "—" : kpis.checkout_success_rate + "%" }}</span>
                <span class="metric-note">{{ kpis.failed_checkouts }} failed · {{ kpis.pending_checkouts }} not completed</span>
              </div>
              <div class="metric-card">
                <span class="metric-label">Lifetime revenue</span>
                <span class="metric-value">{{ money(kpis.lifetime_revenue) }}</span>
                <span class="metric-note">{{ plural(kpis.transactions, "payment") }} · {{ rangeLabel }}</span>
              </div>
            </template>
          </section>

          <!-- Trend + plan mix -->
          <section class="split-row">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Revenue trend</h2>
                  <p class="panel-sub">{{ seriesSubtitle }}</p>
                </div>
                <div class="seg" role="tablist" aria-label="Chart metric">
                  <button
                    v-for="metric in TREND_METRICS"
                    :key="metric.value"
                    type="button"
                    class="seg-btn"
                    :class="{ active: trendMetric === metric.value }"
                    @click="trendMetric = metric.value"
                  >
                    {{ metric.label }}
                  </button>
                </div>
              </div>
              <div class="chart-wrap">
                <div v-if="!overview" class="sk sk-chart"></div>
                <canvas v-show="overview" ref="trendCanvas"></canvas>
              </div>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">MRR by plan</h2>
                  <p class="panel-sub">Share of monthly recurring revenue</p>
                </div>
              </div>
              <div class="donut-wrap">
                <div v-if="!overview" class="sk sk-donut"></div>
                <template v-else>
                  <canvas v-show="kpis.mrr > 0" ref="planCanvas"></canvas>
                  <div v-if="kpis.mrr > 0" class="donut-center">
                    <span class="donut-value">{{ moneyShort(kpis.mrr) }}</span>
                    <span class="donut-caption">MRR</span>
                  </div>
                  <p v-else class="empty-msg">No paying vendors yet</p>
                </template>
              </div>
              <ul v-if="overview" class="legend-list">
                <li v-for="plan in plans" :key="plan.key">
                  <span class="legend-dot" :style="{ background: PLAN_COLORS[plan.key] }"></span>
                  <span class="legend-name">{{ plan.name }}</span>
                  <span class="legend-meta">{{ plan.mrr_share }}%</span>
                  <span class="legend-num">{{ money(plan.mrr) }}</span>
                </li>
              </ul>
            </div>
          </section>

          <!-- Plan performance + status -->
          <section class="split-row">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Plan performance</h2>
                  <p class="panel-sub">Subscribers and revenue per plan · {{ rangeLabel }}</p>
                </div>
              </div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Plan</th>
                      <th class="num">Price / mo</th>
                      <th class="num">Paying</th>
                      <th class="num">Trialing</th>
                      <th class="num">MRR</th>
                      <th>Share</th>
                      <th class="num">Revenue</th>
                    </tr>
                  </thead>
                  <tbody v-if="!overview">
                    <tr v-for="n in 4" :key="n">
                      <td v-for="c in 7" :key="c"><div class="sk sk-line"></div></td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr v-for="plan in plans" :key="plan.key">
                      <td>
                        <span class="plan-chip" :style="planChipStyle(plan.key)">{{ plan.name }}</span>
                      </td>
                      <td class="num muted">
                        {{ plan.custom_pricing ? "from " + money(plan.price) : money(plan.price) }}
                      </td>
                      <td class="num">{{ plan.paying }}</td>
                      <td class="num">{{ plan.trialing }}</td>
                      <td class="num strong">{{ money(plan.mrr) }}</td>
                      <td>
                        <div class="share-bar">
                          <span :style="{ width: plan.mrr_share + '%', background: PLAN_COLORS[plan.key] }"></span>
                        </div>
                      </td>
                      <td class="num">{{ money(plan.revenue) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Subscription status</h2>
                  <p class="panel-sub">Every vendor with a plan, right now</p>
                </div>
              </div>
              <template v-if="!overview">
                <div v-for="n in 6" :key="n" class="sk sk-line" style="margin-bottom: 14px"></div>
              </template>
              <ul v-else class="status-list">
                <li v-for="row in statuses" :key="row.status">
                  <div class="status-top">
                    <span class="status-pill" :class="'st-' + row.status">{{ STATUS_LABELS[row.status] }}</span>
                    <span class="status-count">{{ row.count }}</span>
                  </div>
                  <div class="status-bar">
                    <span :class="'bar-' + row.status" :style="{ width: statusShare(row.count) + '%' }"></span>
                  </div>
                </li>
              </ul>
            </div>
          </section>

          <!-- Renewals + top vendors -->
          <section class="split-row even">
            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Coming up in 30 days</h2>
                  <p class="panel-sub">Renewals due and free trials ending</p>
                </div>
                <span v-if="overview && renewals.length" class="head-total">{{ money(upcomingTotal) }} due</span>
              </div>
              <template v-if="!overview">
                <div v-for="n in 3" :key="n" class="row-skeleton">
                  <div class="sk sk-avatar"></div>
                  <div style="flex: 1"><div class="sk sk-line" style="width: 50%"></div><div class="sk sk-line" style="width: 30%"></div></div>
                </div>
              </template>
              <p v-else-if="!renewals.length" class="empty-msg">Nothing due in the next 30 days</p>
              <ul v-else class="vendor-list">
                <li v-for="row in renewals" :key="row.vendor.id + row.kind">
                  <VendorAvatar :vendor="row.vendor" :size="34" />
                  <div class="vendor-info">
                    <span class="vendor-name">{{ row.vendor.store_name }}</span>
                    <span class="vendor-sub">
                      {{ row.plan_name }} ·
                      <span :class="row.kind === 'trial_ends' ? 'tag-trial' : 'tag-renew'">
                        {{ row.kind === "trial_ends" ? "Trial ends" : "Renews" }}
                      </span>
                    </span>
                  </div>
                  <div class="vendor-right">
                    <span class="vendor-amount">{{ money(row.amount) }}</span>
                    <span class="vendor-sub">{{ formatDate(row.date) }} · {{ daysLabel(row.days_left) }}</span>
                  </div>
                </li>
              </ul>
            </div>

            <div class="panel">
              <div class="panel-head">
                <div>
                  <h2 class="panel-title">Top paying vendors</h2>
                  <p class="panel-sub">By lifetime subscription revenue</p>
                </div>
              </div>
              <template v-if="!overview">
                <div v-for="n in 3" :key="n" class="row-skeleton">
                  <div class="sk sk-avatar"></div>
                  <div style="flex: 1"><div class="sk sk-line" style="width: 50%"></div><div class="sk sk-line" style="width: 30%"></div></div>
                </div>
              </template>
              <p v-else-if="!topVendors.length" class="empty-msg">No payments yet</p>
              <ul v-else class="vendor-list">
                <li v-for="(row, index) in topVendors" :key="row.vendor_id">
                  <span class="rank">{{ index + 1 }}</span>
                  <VendorAvatar :vendor="row.vendor" :size="34" />
                  <div class="vendor-info">
                    <span class="vendor-name">{{ row.vendor.store_name }}</span>
                    <span class="vendor-sub">{{ plural(row.payments, "payment") }} · last {{ formatDate(row.last_paid_at) }}</span>
                  </div>
                  <span class="vendor-amount">{{ money(row.revenue) }}</span>
                </li>
              </ul>
            </div>
          </section>

          <!-- Transactions / subscribers -->
          <section class="panel">
            <div class="panel-head wrap">
              <div class="tabs" role="tablist">
                <button type="button" class="tab" :class="{ active: tab === 'transactions' }" @click="switchTab('transactions')">
                  Transactions
                  <span class="tab-count">{{ transactionTotal }}</span>
                </button>
                <button type="button" class="tab" :class="{ active: tab === 'subscribers' }" @click="switchTab('subscribers')">
                  Subscribers
                  <span class="tab-count">{{ subscribers.length }}</span>
                </button>
                <button type="button" class="tab" :class="{ active: tab === 'enterprise' }" @click="switchTab('enterprise')">
                  Enterprise inquiries
                  <span class="tab-count" :class="{ hot: enterprise.counts.new }">{{ enterprise.counts.new ?? 0 }} new</span>
                </button>
              </div>

              <div v-if="tab === 'enterprise'" class="filters">
                <select v-model="enterprise.status" class="select" aria-label="Inquiry status">
                  <option value="">Every inquiry</option>
                  <option v-for="(label, value) in INQUIRY_STATUS" :key="value" :value="value">{{ label }}</option>
                </select>
              </div>
              <div v-else class="filters">
                <div class="search-wrap">
                  <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                  <input
                    v-model="filters.search"
                    class="search-input"
                    type="search"
                    :placeholder="tab === 'transactions' ? 'Store, owner or reference' : 'Store, owner or email'"
                  />
                </div>
                <select v-model="filters.status" class="select">
                  <option value="">All statuses</option>
                  <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filters.plan" class="select">
                  <option value="">All plans</option>
                  <option v-for="plan in PLAN_OPTIONS" :key="plan.value" :value="plan.value">{{ plan.label }}</option>
                </select>
                <button
                  v-if="tab === 'transactions'"
                  type="button"
                  class="ghost-btn"
                  :disabled="exporting || !transactionTotal"
                  @click="exportCsv"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                  <span>{{ exporting ? "Exporting…" : "Export CSV" }}</span>
                </button>
              </div>
            </div>

            <!-- Transactions -->
            <div v-if="tab === 'transactions'" class="table-scroll">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Vendor</th>
                    <th>Reference</th>
                    <th>Plan</th>
                    <th class="num">Amount</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Paid</th>
                  </tr>
                </thead>
                <tbody v-if="transactionsLoading && !transactions.length">
                  <tr v-for="n in 5" :key="n">
                    <td v-for="c in 7" :key="c"><div class="sk sk-line"></div></td>
                  </tr>
                </tbody>
                <tbody v-else-if="!transactions.length">
                  <tr><td colspan="7" class="empty-cell">{{ hasFilters ? "No transactions match these filters" : "No subscription payments yet" }}</td></tr>
                </tbody>
                <tbody v-else :class="{ dim: transactionsLoading }">
                  <tr v-for="row in txList.rows.value" :key="row.id">
                    <td>
                      <div class="vendor-cell">
                        <VendorAvatar :vendor="row.vendor" />
                        <div class="vendor-info">
                          <span class="vendor-name">{{ row.vendor.store_name }}</span>
                          <span class="vendor-sub">{{ row.vendor.name }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="mono">{{ row.reference_number }}</td>
                    <td><span class="plan-chip" :style="planChipStyle(row.plan_key)">{{ row.plan_name }}</span></td>
                    <td class="num strong">{{ money(row.amount) }}</td>
                    <td><span class="status-pill" :class="'st-' + row.status">{{ CHECKOUT_LABELS[row.status] ?? row.status }}</span></td>
                    <td class="muted">{{ formatDateTime(row.created_at) }}</td>
                    <td class="muted">{{ row.paid_at ? formatDateTime(row.paid_at) : "—" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Enterprise "Talk to us" inquiries -->
            <div v-else-if="tab === 'enterprise'" class="eq-list">
              <template v-if="enterprise.loading && !enterprise.rows.length">
                <div v-for="n in 3" :key="n" class="eq-item"><div class="sk sk-line" style="width: 40%"></div><div class="sk sk-line"></div><div class="sk sk-line" style="width: 70%"></div></div>
              </template>
              <p v-else-if="!enterprise.rows.length" class="empty-cell">{{ enterprise.status ? "No inquiries with this status" : "No one has asked about Enterprise yet. The \"Talk to us\" form on the pricing page sends them here." }}</p>
              <article v-for="row in enterprise.rows" v-else :key="row.id" class="eq-item" :class="{ fresh: row.status === 'new' }">
                <header class="eq-item-head">
                  <div>
                    <span class="vendor-name">{{ row.shop_name }}</span>
                    <span class="vendor-sub">{{ row.name }}<template v-if="row.branches"> · {{ row.branches }} {{ row.branches === 1 ? "branch" : "branches" }}</template> · {{ formatDateTime(row.created_at) }}</span>
                  </div>
                  <span class="status-pill" :class="'iq-' + row.status">{{ INQUIRY_STATUS[row.status] ?? row.status }}</span>
                </header>
                <p class="eq-message">{{ row.message }}</p>
                <div class="eq-contact">
                  <a :href="`mailto:${row.email}?subject=${encodeURIComponent('BloomCraft Enterprise plan')}`" class="ghost-btn">{{ row.email }}</a>
                  <a :href="`tel:${row.phone.replace(/\s/g, '')}`" class="ghost-btn">{{ row.phone }}</a>
                  <router-link v-if="row.vendor" :to="{ path: '/admin/vendor-requests', query: { tab: 'active', vendor: row.vendor.id } }" class="ghost-btn">Open {{ row.vendor.store_name }} →</router-link>
                  <span v-else class="vendor-sub">Not a vendor yet</span>
                </div>
                <div v-if="canManagePlans" class="eq-update">
                  <select v-model="row.draftStatus" class="select" :aria-label="`Status for ${row.shop_name}`">
                    <option v-for="(label, value) in INQUIRY_STATUS" :key="value" :value="value">{{ label }}</option>
                  </select>
                  <input v-model="row.draftNote" class="search-input eq-note" maxlength="1000" placeholder="Private note (e.g. sent a quote)" :aria-label="`Note for ${row.shop_name}`" />
                  <button type="button" class="ghost-btn" :disabled="row.saving || (row.draftStatus === row.status && (row.draftNote ?? '') === (row.admin_note ?? ''))" @click="saveInquiry(row)">{{ row.saving ? "Saving…" : "Save" }}</button>
                </div>
                <p v-else-if="row.admin_note" class="vendor-sub">Note: {{ row.admin_note }}</p>
                <p v-if="row.handled_by" class="vendor-sub">Last updated by {{ row.handled_by }} · {{ formatDateTime(row.handled_at) }}</p>
              </article>
            </div>

            <!-- Subscribers -->
            <div v-else class="table-scroll">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Vendor</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th class="num">MRR</th>
                    <th>Started</th>
                    <th>Renews / ends</th>
                    <th class="num">Lifetime</th>
                  </tr>
                </thead>
                <tbody v-if="subscribersLoading && !subscribers.length">
                  <tr v-for="n in 4" :key="n">
                    <td v-for="c in 7" :key="c"><div class="sk sk-line"></div></td>
                  </tr>
                </tbody>
                <tbody v-else-if="!subscribers.length">
                  <tr><td colspan="7" class="empty-cell">{{ hasFilters ? "No subscribers match these filters" : "No vendor has a plan yet" }}</td></tr>
                </tbody>
                <tbody v-else :class="{ dim: subscribersLoading }">
                  <tr v-for="row in subscribers" :key="row.id">
                    <td>
                      <div class="vendor-cell">
                        <VendorAvatar :vendor="row.vendor" />
                        <div class="vendor-info">
                          <span class="vendor-name">{{ row.vendor.store_name }}</span>
                          <span class="vendor-sub">{{ row.vendor.email }}</span>
                        </div>
                      </div>
                    </td>
                    <td><span class="plan-chip" :style="planChipStyle(row.plan_key)">{{ row.plan_name }}</span></td>
                    <td><span class="status-pill" :class="'st-' + row.status">{{ STATUS_LABELS[row.status] ?? row.status }}</span></td>
                    <td class="num strong">{{ money(row.monthly_value) }}</td>
                    <td class="muted">{{ formatDate(row.started_at) }}</td>
                    <td class="muted">{{ formatDate(row.ends_at) }}</td>
                    <td class="num">
                      {{ money(row.lifetime_revenue) }}
                      <span class="vendor-sub">{{ plural(row.payments, "payment") }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <ShowMore v-if="tab === 'transactions' && transactions.length" tone="admin" :has-more="txList.hasMore.value" :loading="txList.loadingMore.value" :shown="txList.rows.value.length" :total="txList.total.value" @more="txList.showMore()" />
          </section>
        </template>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { toast } from "@/utils/snackbar";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import VendorAvatar from "./components/VendorAvatar.vue";
import api from "../../plugins/axios";
import ShowMore from "../../components/common/ShowMore.vue";
import { useShowMoreRows } from "../../composables/useShowMore";
import { useAdminAccess } from "../../composables/useAdminAccess";
import { useRoute } from "vue-router";

const { toggleMobile } = useSidebarState();

const RANGES = [
  { value: "7d", label: "7D", long: "last 7 days" },
  { value: "30d", label: "30D", long: "last 30 days" },
  { value: "90d", label: "90D", long: "last 90 days" },
  { value: "12m", label: "12M", long: "last 12 months" },
  { value: "ytd", label: "YTD", long: "this year" },
  { value: "all", label: "All", long: "all time" },
];
const TREND_METRICS = [
  { value: "revenue", label: "Revenue" },
  { value: "transactions", label: "Payments" },
  { value: "new_paying", label: "New vendors" },
];
const PLAN_COLORS = {
  starter: "#94a3b8",
  business: "#38bdf8",
  professional: "#7c6ff7",
  enterprise: "#f59e0b",
};
const PLAN_OPTIONS = [
  { value: "starter", label: "Starter" },
  { value: "business", label: "Business" },
  { value: "professional", label: "Professional" },
  { value: "enterprise", label: "Enterprise" },
];
const STATUS_LABELS = {
  active: "Active",
  trialing: "Free trial",
  past_due: "Past due",
  pending: "Pending",
  cancelled: "Cancelled",
  expired: "Expired",
};
const CHECKOUT_LABELS = {
  paid: "Paid",
  pending: "Not completed",
  failed: "Failed",
  cancelled: "Cancelled",
};

// ── Overview ─────────────────────────────────────────────────────
const range = ref("30d");
const overview = ref(null);
const loading = ref(false);
const loadError = ref("");
const trendMetric = ref("revenue");
const now = ref(Date.now());

const kpis = computed(() => overview.value?.kpis ?? {});
const plans = computed(() => overview.value?.plans ?? []);
const statuses = computed(() => overview.value?.statuses ?? []);
const renewals = computed(() => overview.value?.renewals ?? []);
const topVendors = computed(() => overview.value?.top_vendors ?? []);
const rangeLabel = computed(() => RANGES.find((r) => r.value === range.value)?.long ?? "");
const upcomingTotal = computed(() => renewals.value.reduce((sum, row) => sum + Number(row.amount || 0), 0));

const updatedLabel = computed(() => {
  if (!overview.value) return "";
  const seconds = Math.max(0, Math.round((now.value - new Date(overview.value.generated_at)) / 1000));
  if (seconds < 60) return "just now";
  const minutes = Math.round(seconds / 60);
  return minutes < 60 ? `${minutes} min ago` : `${Math.round(minutes / 60)} h ago`;
});

const revenueChange = computed(() => {
  const current = kpis.value.revenue;
  const previous = kpis.value.revenue_previous;
  if (previous === null || previous === undefined) return null;
  if (previous === 0) {
    return current > 0 ? { text: "New", class: "badge-up" } : { text: "0%", class: "badge-flat" };
  }
  const change = ((current - previous) / previous) * 100;
  const rounded = Math.abs(change) < 0.05 ? 0 : change;
  return {
    text: `${rounded > 0 ? "▲" : rounded < 0 ? "▼" : ""} ${Math.abs(rounded).toFixed(1)}%`,
    class: rounded > 0 ? "badge-up" : rounded < 0 ? "badge-down" : "badge-flat",
  };
});

const comparisonNote = computed(() =>
  kpis.value.revenue_previous === null || kpis.value.revenue_previous === undefined
    ? "since the first subscription"
    : `vs ${money(kpis.value.revenue_previous)} the period before`,
);

const seriesSubtitle = computed(() => {
  const unit = overview.value?.series?.unit;
  const per = unit === "day" ? "Daily" : unit === "week" ? "Weekly" : "Monthly";
  return `${per} totals · ${rangeLabel.value}`;
});

const statusTotal = computed(() => statuses.value.reduce((sum, row) => sum + row.count, 0));
const statusShare = (count) => (statusTotal.value ? Math.round((count / statusTotal.value) * 100) : 0);

let requestId = 0;
const fetchOverview = async () => {
  const id = ++requestId;
  loading.value = true;
  loadError.value = "";
  try {
    const { data } = await api.get("/admin/subscriptions/revenue", { params: { range: range.value } });
    if (id !== requestId) return;
    overview.value = data.data;
    now.value = Date.now();
    await nextTick();
    await renderCharts();
  } catch (error) {
    if (id !== requestId) return;
    loadError.value = error.response?.data?.message || "Check your connection and try again.";
    if (overview.value) toast.error("The revenue figures couldn't be refreshed.");
  } finally {
    if (id === requestId) loading.value = false;
  }
};

const setRange = (value) => {
  if (range.value === value) return;
  range.value = value;
  fetchOverview();
};

const refresh = () => {
  fetchOverview();
  if (tab.value === "transactions") txList.load();
  else fetchSubscribers();
};

// ── Charts (Chart.js, as on the admin dashboard) ─────────────────
const trendCanvas = ref(null);
const planCanvas = ref(null);
let trendChart = null;
let planChart = null;

const loadChartJs = () =>
  new Promise((resolve, reject) => {
    if (window.Chart) return resolve();
    const existing = document.querySelector("script[data-chartjs]");
    const script = existing || document.createElement("script");
    script.addEventListener("load", resolve);
    script.addEventListener("error", reject);
    if (!existing) {
      script.src = "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js";
      script.dataset.chartjs = "true";
      document.head.appendChild(script);
    }
  });

const renderCharts = async () => {
  try {
    await loadChartJs();
  } catch {
    return; // Charts are extra; the numbers on the page still show
  }
  renderTrendChart();
  renderPlanChart();
};

const renderTrendChart = () => {
  if (!trendCanvas.value || !overview.value || !window.Chart) return;
  const points = overview.value.series.points;
  const metric = trendMetric.value;
  const isMoney = metric === "revenue";
  trendChart?.destroy();

  const datasets = [
    {
      type: "bar",
      label: TREND_METRICS.find((m) => m.value === metric).label,
      data: points.map((p) => p[metric]),
      backgroundColor: "#7c6ff7",
      hoverBackgroundColor: "#5b4fc9",
      borderRadius: 5,
      maxBarThickness: 36,
      yAxisID: "y",
      order: 2,
    },
  ];
  if (isMoney) {
    datasets.push({
      type: "line",
      label: "Cumulative",
      data: points.map((p) => p.cumulative),
      borderColor: "#4ade80",
      backgroundColor: "rgba(74, 222, 128, 0.12)",
      fill: true,
      tension: 0.35,
      pointRadius: 0,
      pointHoverRadius: 4,
      borderWidth: 2,
      yAxisID: "y1",
      order: 1,
    });
  }

  trendChart = new window.Chart(trendCanvas.value, {
    type: "bar",
    data: { labels: points.map((p) => p.label), datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { display: isMoney, position: "bottom", labels: { boxWidth: 10, boxHeight: 10, color: "#64748b", font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: (c) => ` ${c.dataset.label}: ${isMoney ? money(c.raw) : c.raw}`,
          },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: "#94a3b8", font: { size: 11 }, maxRotation: 0, autoSkipPadding: 12 } },
        y: {
          beginAtZero: true,
          grid: { color: "#f1f5f9" },
          ticks: { color: "#94a3b8", font: { size: 11 }, precision: 0, callback: (v) => (isMoney ? moneyShort(v) : v) },
        },
        y1: { display: isMoney, position: "right", beginAtZero: true, grid: { display: false }, ticks: { color: "#86efac", font: { size: 11 }, callback: (v) => moneyShort(v) } },
      },
    },
  });
};

const renderPlanChart = () => {
  planChart?.destroy();
  planChart = null;
  if (!planCanvas.value || !window.Chart || !(kpis.value.mrr > 0)) return;
  planChart = new window.Chart(planCanvas.value, {
    type: "doughnut",
    data: {
      labels: plans.value.map((p) => p.name),
      datasets: [
        {
          data: plans.value.map((p) => p.mrr),
          backgroundColor: plans.value.map((p) => PLAN_COLORS[p.key]),
          borderWidth: 0,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "74%",
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => ` ${c.label}: ${money(c.raw)}` } } },
    },
  });
};

watch(trendMetric, renderTrendChart);

// ── Transactions & subscribers ───────────────────────────────────
const route = useRoute();
const tab = ref("transactions");
const filters = reactive({ search: "", status: "", plan: "" });
const transactions = ref([]);
const transactionsLoading = ref(false);
const transactionTotal = ref(0);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const subscribers = ref([]);
const subscribersLoading = ref(false);
const exporting = ref(false);

const hasFilters = computed(() => Boolean(filters.search.trim() || filters.status || filters.plan));
const statusOptions = computed(() =>
  tab.value === "transactions"
    ? Object.entries(CHECKOUT_LABELS).map(([value, label]) => ({ value, label }))
    : Object.entries(STATUS_LABELS).map(([value, label]) => ({ value, label })),
);

const filterParams = () => ({
  search: filters.search.trim() || undefined,
  status: filters.status || undefined,
  plan: filters.plan || undefined,
});

let transactionRequest = 0;
const fetchTransactions = async (page = 1) => {
  const id = ++transactionRequest;
  transactionsLoading.value = true;
  try {
    const { data } = await api.get("/admin/subscriptions/transactions", {
      params: { ...filterParams(), page, per_page: 10 },
    });
    if (id !== transactionRequest) return;
    transactions.value = page > 1 ? [...transactions.value, ...(data.data ?? [])] : data.data;
    meta.value = data.meta;
    transactionTotal.value = data.meta.total;
  } catch {
    if (id === transactionRequest) toast.error("The transactions couldn't be loaded.");
  } finally {
    if (id === transactionRequest) transactionsLoading.value = false;
  }
};
// 10 rows first, then 10 more, then 20 at a time
const txList = useShowMoreRows(transactions, meta, fetchTransactions);

let subscriberRequest = 0;
const fetchSubscribers = async () => {
  const id = ++subscriberRequest;
  subscribersLoading.value = true;
  try {
    const { data } = await api.get("/admin/subscriptions/subscribers", { params: filterParams() });
    if (id === subscriberRequest) subscribers.value = data.data;
  } catch {
    if (id === subscriberRequest) toast.error("The subscribers couldn't be loaded.");
  } finally {
    if (id === subscriberRequest) subscribersLoading.value = false;
  }
};

const switchTab = (value) => {
  if (tab.value === value) return;
  tab.value = value;
  if (value === "enterprise") return void fetchInquiries();
  // The two tabs use different statuses; clearing one refetches via the watcher
  if (filters.status) return void (filters.status = "");
  if (value === "transactions") txList.load();
  else fetchSubscribers();
};

let searchTimer = null;
watch(
  () => [filters.search, filters.status, filters.plan],
  () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      if (tab.value === "transactions") txList.load();
      else if (tab.value === "subscribers") fetchSubscribers();
    }, 300);
  },
);

// ── Enterprise "Talk to us" inquiries ────────────────────────────
const INQUIRY_STATUS = { new: "New", contacted: "Contacted", won: "Now on Enterprise", closed: "Closed" };
const { can: adminCan } = useAdminAccess();
const canManagePlans = computed(() => adminCan("subscriptions.manage"));
const enterprise = reactive({ rows: [], counts: {}, status: "", loading: false });
let inquiryRequest = 0;
const fetchInquiries = async () => {
  const id = ++inquiryRequest;
  enterprise.loading = true;
  try {
    const { data } = await api.get("/admin/subscriptions/enterprise-inquiries", { params: { status: enterprise.status || undefined } });
    if (id !== inquiryRequest) return;
    enterprise.rows = data.data.inquiries.map((r) => ({ ...r, draftStatus: r.status, draftNote: r.admin_note ?? "", saving: false }));
    enterprise.counts = data.data.counts ?? {};
  } catch {
    if (id === inquiryRequest) toast.error("The Enterprise inquiries couldn't be loaded.");
  } finally {
    if (id === inquiryRequest) enterprise.loading = false;
  }
};
watch(() => enterprise.status, fetchInquiries);
const saveInquiry = async (row) => {
  row.saving = true;
  try {
    const { data } = await api.post(`/admin/subscriptions/enterprise-inquiries/${row.id}`, { status: row.draftStatus, note: row.draftNote.trim() || null });
    toast.success(row.draftStatus === "won" ? `Saved. To move ${row.shop_name} to Enterprise, open the shop and use Change plan.` : data.message || "Saved.");
    await fetchInquiries();
  } catch (error) {
    toast.error(error?.response?.data?.message || "That didn't save. Try again.");
    row.saving = false;
  }
};

const exportCsv = async () => {
  exporting.value = true;
  try {
    const response = await api.get("/admin/subscriptions/transactions/export", {
      params: filterParams(),
      responseType: "blob",
    });
    const url = URL.createObjectURL(new Blob([response.data], { type: "text/csv" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = `subscription-transactions-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  } catch {
    toast.error("The CSV couldn't be exported. Try again.");
  } finally {
    exporting.value = false;
  }
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", minimumFractionDigits: 2 });
const money = (value) => (value === null || value === undefined ? "—" : peso.format(Number(value)));
const moneyShort = (value) => {
  const n = Number(value) || 0;
  if (Math.abs(n) >= 1_000_000) return `₱${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`;
  if (Math.abs(n) >= 1_000) return `₱${(n / 1_000).toFixed(1).replace(/\.0$/, "")}K`;
  return `₱${n.toFixed(0)}`;
};
const plural = (count, word) => `${count} ${word}${count === 1 ? "" : "s"}`;
const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—";
const formatDateTime = (value) =>
  value
    ? new Date(value).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" })
    : "—";
const daysLabel = (days) => (days === 0 ? "today" : days === 1 ? "tomorrow" : `in ${days} days`);
const planChipStyle = (key) => {
  const color = PLAN_COLORS[key] ?? "#94a3b8";
  return { color, background: `${color}1f` };
};

// ── Lifecycle ────────────────────────────────────────────────────
let clockTimer = null;
onMounted(() => {
  fetchOverview();
  txList.load();
  fetchSubscribers();
  fetchInquiries();
  // A "new Enterprise inquiry" notification links straight to this tab
  if (route.query.tab === "enterprise") tab.value = "enterprise";
  clockTimer = setInterval(() => (now.value = Date.now()), 30_000);
});

onBeforeUnmount(() => {
  clearInterval(clockTimer);
  clearTimeout(searchTimer);
  trendChart?.destroy();
  planChart?.destroy();
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
.dashboard-body {
  padding: 0 28px 40px;
}
.dashboard-body > section {
  margin-top: 20px;
}

/* ── Header ─────────────────────────────────────────── */
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 28px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  position: sticky;
  top: 0;
  z-index: 100;
  flex-wrap: wrap;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
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

.range-group,
.seg {
  display: inline-flex;
  padding: 3px;
  background: #f1f5f9;
  border-radius: 9px;
  gap: 2px;
}
.range-btn,
.seg-btn {
  border: none;
  background: transparent;
  padding: 6px 11px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.range-btn:hover,
.seg-btn:hover {
  color: #1e2739;
}
.range-btn.active,
.seg-btn.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 13px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, border-color 0.15s;
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
.primary-btn:hover {
  background: #5b4fc9;
}
.ghost-btn:disabled {
  opacity: 0.55;
  cursor: default;
}

/* ── Skeletons ──────────────────────────────────────── */
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
.sk-value {
  height: 30px;
  width: 70%;
  margin: 14px 0 12px;
}
.sk-chart {
  height: 100%;
  border-radius: 10px;
}
.sk-donut {
  width: 170px;
  height: 170px;
  border-radius: 50%;
  margin: 0 auto;
}
.sk-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  flex-shrink: 0;
}
.row-skeleton {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
}
@keyframes shimmer {
  from {
    background-position: 100% 0;
  }
  to {
    background-position: 0 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}

/* ── KPI cards ──────────────────────────────────────── */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
.kpi-card {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 18px 20px;
  transition: box-shadow 0.2s;
}
.kpi-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}
.kpi-feature {
  background: linear-gradient(135deg, #7c6ff7, #5b4fc9);
  border-color: transparent;
  color: #fff;
}
.kpi-feature .kpi-label,
.kpi-feature .kpi-note {
  color: rgba(255, 255, 255, 0.8);
}
.kpi-feature .kpi-value {
  color: #fff;
}
.kpi-feature .kpi-icon {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
}
.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.kpi-label {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}
.kpi-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-icon-purple {
  background: #ede9ff;
  color: #7c6ff7;
}
.kpi-icon-green {
  background: #dcfce7;
  color: #16a34a;
}
.kpi-icon-blue {
  background: #e0f2fe;
  color: #0284c7;
}
.kpi-icon-amber {
  background: #fef3c7;
  color: #d97706;
}
.kpi-value {
  font-size: 26px;
  font-weight: 700;
  color: #1e2739;
  margin: 12px 0 10px;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.kpi-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.kpi-note {
  font-size: 11.5px;
  color: #94a3b8;
}
.kpi-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
}
.badge-up {
  background: #dcfce7;
  color: #16a34a;
}
.badge-down {
  background: #fee2e2;
  color: #dc2626;
}
.badge-flat {
  background: #f1f5f9;
  color: #64748b;
}

/* ── Health metrics ─────────────────────────────────── */
.metric-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}
.metric-card {
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 14px 18px;
}
.metric-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}
.metric-value {
  font-size: 19px;
  font-weight: 700;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
}
.metric-note {
  font-size: 11.5px;
  color: #94a3b8;
}

/* ── Panels ─────────────────────────────────────────── */
.split-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 16px;
}
.split-row.even {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 12px;
  padding: 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}
.panel-head.wrap {
  flex-wrap: wrap;
  align-items: center;
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
.head-total {
  font-size: 12px;
  font-weight: 600;
  color: #5b4fc9;
  background: #ede9ff;
  padding: 4px 10px;
  border-radius: 20px;
  white-space: nowrap;
}
.error-panel {
  text-align: center;
  padding: 48px 20px;
}
.error-panel .primary-btn {
  margin-top: 16px;
}
.empty-msg {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 28px 0;
  margin: 0;
}

/* ── Charts ─────────────────────────────────────────── */
.chart-wrap {
  position: relative;
  height: 280px;
}
.donut-wrap {
  position: relative;
  height: 190px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}
.donut-value {
  font-size: 20px;
  font-weight: 700;
  color: #1e2739;
}
.donut-caption {
  font-size: 11px;
  color: #94a3b8;
  letter-spacing: 0.08em;
}
.legend-list {
  list-style: none;
  padding: 0;
  margin: 18px 0 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.legend-list li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  flex-shrink: 0;
}
.legend-name {
  flex: 1;
  color: #475569;
}
.legend-meta {
  color: #94a3b8;
  font-size: 12px;
}
.legend-num {
  font-weight: 600;
  color: #1e2739;
  min-width: 96px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

/* ── Tables ─────────────────────────────────────────── */
.table-scroll {
  overflow-x: auto;
  margin: 0 -4px;
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
.data-table th:first-child {
  border-radius: 6px 0 0 6px;
}
.data-table th:last-child {
  border-radius: 0 6px 6px 0;
}
.data-table td {
  padding: 11px 10px;
  border-bottom: 1px solid #f1f5f9;
  color: #1e2739;
  white-space: nowrap;
  vertical-align: middle;
}
.data-table tbody tr:last-child td {
  border-bottom: none;
}
.data-table tbody tr:hover td {
  background: #fafbfd;
}
.data-table tbody.dim {
  opacity: 0.55;
  transition: opacity 0.2s;
}
.num {
  text-align: right !important;
  font-variant-numeric: tabular-nums;
}
.strong {
  font-weight: 600;
}
.muted {
  color: #64748b !important;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace !important;
  font-size: 12px;
  color: #475569 !important;
}
.empty-cell {
  text-align: center;
  color: #94a3b8 !important;
  padding: 32px 10px !important;
}
.share-bar {
  width: 110px;
  height: 6px;
  background: #f1f5f9;
  border-radius: 6px;
  overflow: hidden;
}
.share-bar span {
  display: block;
  height: 100%;
  border-radius: 6px;
}
.plan-chip {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
}

/* ── Status pills and bars ──────────────────────────── */
.tab-count.hot {
  background: #fee2e2;
  color: #b91c1c;
}
.eq-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.eq-item {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  background: #fff;
}
.eq-item.fresh {
  border-color: #c7d2fe;
  box-shadow: inset 3px 0 0 #6366f1;
}
.eq-item-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.eq-item-head > div {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.eq-message {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.55;
  color: #334155;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.eq-contact,
.eq-update {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.eq-contact a {
  text-decoration: none;
}
.eq-note {
  flex: 1 1 220px;
  min-width: 0;
}
.iq-new { background: #e0e7ff; color: #3730a3; }
.iq-contacted { background: #fef3c7; color: #92400e; }
.iq-won { background: #dcfce7; color: #166534; }
.iq-closed { background: #f1f5f9; color: #475569; }
.status-pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}
.st-active,
.st-paid {
  background: #dcfce7;
  color: #16a34a;
}
.st-trialing {
  background: #e0f2fe;
  color: #0284c7;
}
.st-pending {
  background: #fef3c7;
  color: #b45309;
}
.st-past_due,
.st-failed {
  background: #fee2e2;
  color: #dc2626;
}
.st-cancelled,
.st-expired {
  background: #f1f5f9;
  color: #64748b;
}
.status-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.status-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.status-count {
  font-size: 14px;
  font-weight: 600;
  color: #1e2739;
}
.status-bar {
  height: 6px;
  border-radius: 6px;
  background: #f1f5f9;
  overflow: hidden;
}
.status-bar span {
  display: block;
  height: 100%;
  border-radius: 6px;
  transition: width 0.4s ease;
}
.bar-active {
  background: #4ade80;
}
.bar-trialing {
  background: #38bdf8;
}
.bar-past_due {
  background: #f87171;
}
.bar-pending {
  background: #fbbf24;
}
.bar-cancelled,
.bar-expired {
  background: #cbd5e1;
}

/* ── Vendor rows ────────────────────────────────────── */
.vendor-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.vendor-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
}
.vendor-list li:last-child {
  border-bottom: none;
}
.vendor-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.vendor-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.vendor-name {
  font-size: 13px;
  font-weight: 500;
  color: #1e2739;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vendor-sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.vendor-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
}
.vendor-amount {
  font-size: 13px;
  font-weight: 600;
  color: #1e2739;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.tag-trial {
  color: #0284c7;
}
.tag-renew {
  color: #16a34a;
}
.rank {
  width: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  text-align: center;
}

/* ── Tabs, filters, pager ───────────────────────────── */
.tabs {
  display: flex;
  gap: 4px;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: none;
  background: transparent;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.tab.active {
  background: #ede9ff;
  color: #5b4fc9;
}
.tab-count {
  font-size: 11px;
  font-weight: 600;
  background: #fff;
  border-radius: 20px;
  padding: 1px 7px;
  color: inherit;
}
.filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.search-ico {
  position: absolute;
  left: 10px;
  color: #94a3b8;
  pointer-events: none;
}
.search-input,
.select {
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.search-input {
  padding-left: 31px;
  width: 220px;
}
.search-input:focus,
.select:focus {
  outline: none;
  border-color: #7c6ff7;
}
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
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
  gap: 8px;
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .kpi-row,
  .metric-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 1100px) {
  .split-row,
  .split-row.even {
    grid-template-columns: minmax(0, 1fr);
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
  .dashboard-body {
    padding: 0 16px 32px;
  }
  .kpi-row,
  .metric-row {
    grid-template-columns: minmax(0, 1fr);
  }
  .range-group {
    width: 100%;
    overflow-x: auto;
  }
  .filters,
  .search-wrap,
  .search-input {
    width: 100%;
  }
  .select {
    flex: 1;
  }
  .chart-wrap {
    height: 230px;
  }
}
</style>
