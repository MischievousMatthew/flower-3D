<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <!-- Header -->
      <header class="content-header">
        <div class="header-left">
          <button
            class="mobile-toggle"
            @click="toggleMobile"
            aria-label="Toggle Menu"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <div class="header-title-block">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">
              Welcome back — here's what's happening today
            </p>
          </div>
        </div>
        <div class="header-right">
          <div class="search-wrap">
            <svg
              class="search-ico"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input class="search-input" type="text" placeholder="Search..." />
          </div>
          <button class="icon-pill">
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span class="notif-dot"></span>
          </button>
          <div class="avatar-pill">
            <div class="avatar-circle">A</div>
            <span class="avatar-name">Admin</span>
          </div>
        </div>
      </header>

      <div class="dashboard-body">
        <!-- Loading state -->
        <LoadingOverlay :visible="isLoading" message="Loading dashboard..." />

        <!-- KPI Cards Row -->
        <section class="kpi-row">
          <div class="kpi-card kpi-vendors">
            <div class="kpi-top">
              <span class="kpi-label">Total Vendors</span>
              <div class="kpi-icon-wrap kpi-icon-purple">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                  <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
              </div>
            </div>
            <div class="kpi-value">{{ stats.total_vendors ?? "—" }}</div>
            <div class="kpi-footer">
              <span class="kpi-badge kpi-badge-up"
                >+{{ stats.new_vendors_this_month ?? 0 }} this month</span
              >
            </div>
          </div>

          <div class="kpi-card kpi-pending">
            <div class="kpi-top">
              <span class="kpi-label">Pending Requests</span>
              <div class="kpi-icon-wrap kpi-icon-amber">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
              </div>
            </div>
            <div class="kpi-value">{{ stats.pending_vendors ?? "—" }}</div>
            <div class="kpi-footer">
              <span class="kpi-badge kpi-badge-warn">Needs review</span>
            </div>
          </div>

          <div class="kpi-card kpi-approved">
            <div class="kpi-top">
              <span class="kpi-label">Approved Vendors</span>
              <div class="kpi-icon-wrap kpi-icon-green">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </div>
            </div>
            <div class="kpi-value">{{ stats.approved_vendors ?? "—" }}</div>
            <div class="kpi-footer">
              <span class="kpi-badge kpi-badge-up">Active on platform</span>
            </div>
          </div>

          <div class="kpi-card kpi-reports">
            <div class="kpi-top">
              <span class="kpi-label">Product Reports</span>
              <div class="kpi-icon-wrap kpi-icon-red">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                  />
                  <line x1="12" y1="9" x2="12" y2="13" />
                  <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
              </div>
            </div>
            <div class="kpi-value">{{ stats.pending_reports ?? "—" }}</div>
            <div class="kpi-footer">
              <span class="kpi-badge kpi-badge-danger">Requires action</span>
            </div>
          </div>
        </section>

        <!-- Charts Row -->
        <section class="charts-row">
          <!-- Vendor Applications Chart -->
          <div class="chart-card chart-wide">
            <div class="chart-card-header">
              <div>
                <h2 class="chart-title">Vendor Applications</h2>
                <p class="chart-sub">Monthly submission trend</p>
              </div>
              <div class="legend-row">
                <span class="legend-dot" style="background: #7c6ff7"></span
                ><span class="legend-text">Pending</span>
                <span
                  class="legend-dot"
                  style="background: #4ade80; margin-left: 12px"
                ></span
                ><span class="legend-text">Approved</span>
                <span
                  class="legend-dot"
                  style="background: #f87171; margin-left: 12px"
                ></span
                ><span class="legend-text">Rejected</span>
              </div>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="vendorChart"></canvas>
            </div>
          </div>

          <!-- Status Donut -->
          <div class="chart-card chart-narrow">
            <div class="chart-card-header">
              <div>
                <h2 class="chart-title">Status Breakdown</h2>
                <p class="chart-sub">All-time distribution</p>
              </div>
            </div>
            <div class="chart-canvas-wrap" style="height: 200px">
              <canvas id="donutChart"></canvas>
            </div>
            <div class="donut-legend">
              <div class="donut-leg-row">
                <span class="legend-dot" style="background: #7c6ff7"></span
                ><span class="legend-text">Pending</span
                ><span class="legend-num">{{
                  stats.pending_vendors ?? 0
                }}</span>
              </div>
              <div class="donut-leg-row">
                <span class="legend-dot" style="background: #4ade80"></span
                ><span class="legend-text">Approved</span
                ><span class="legend-num">{{
                  stats.approved_vendors ?? 0
                }}</span>
              </div>
              <div class="donut-leg-row">
                <span class="legend-dot" style="background: #f87171"></span
                ><span class="legend-text">Rejected</span
                ><span class="legend-num">{{
                  stats.rejected_vendors ?? 0
                }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Bottom Row -->
        <section class="bottom-row">
          <!-- Recent Applications Table -->
          <div class="table-card">
            <div class="table-card-header">
              <div>
                <h2 class="chart-title">Recent Applications</h2>
                <p class="chart-sub">Latest vendor registration requests</p>
              </div>
              <router-link to="/admin/vendor-requests" class="view-all-link">
                View all
                <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </router-link>
            </div>
            <div
              v-if="recentApplications.length === 0 && !isLoading"
              class="empty-msg"
            >
              No applications yet
            </div>
            <div v-else class="mini-table">
              <div class="mini-table-head">
                <span>Store</span>
                <span>Owner</span>
                <span>Type</span>
                <span>Status</span>
                <span>Date</span>
              </div>
              <div
                v-for="app in recentApplications"
                :key="app.id"
                class="mini-table-row"
              >
                <span class="store-cell">
                  <span class="store-ava">{{
                    app.store_name?.charAt(0) ?? "?"
                  }}</span>
                  <span class="store-nm">{{ app.store_name }}</span>
                </span>
                <span class="text-secondary">{{ app.owner_name }}</span>
                <span class="text-secondary">{{
                  formatBizType(app.business_type)
                }}</span>
                <span>
                  <span class="status-pill" :class="'pill-' + app.status">{{
                    formatStatus(app.status)
                  }}</span>
                </span>
                <span class="text-secondary">{{
                  formatDate(app.submitted_at)
                }}</span>
              </div>
            </div>
          </div>

          <!-- Product Reports Card -->
          <div class="reports-card">
            <div class="table-card-header">
              <div>
                <h2 class="chart-title">Product Reports</h2>
                <p class="chart-sub">Flagged items needing review</p>
              </div>
              <router-link to="/admin/reports" class="view-all-link">
                View all
                <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </router-link>
            </div>
            <div
              v-if="recentReports.length === 0 && !isLoading"
              class="empty-msg"
            >
              No reports at this time
            </div>
            <div v-for="rpt in recentReports" :key="rpt.id" class="report-row">
              <div class="report-icon-wrap">
                <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                  />
                </svg>
              </div>
              <div class="report-info">
                <span class="report-name">{{
                  rpt.product?.name ?? "Unnamed Product"
                }}</span>
                <span class="report-reason">{{ rpt.reason }}</span>
              </div>
              <span class="status-pill" :class="'pill-' + rpt.status">{{
                formatStatus(rpt.status)
              }}</span>
            </div>

            <!-- Quick links -->
            <div class="quick-links">
              <h3 class="ql-title">Quick Actions</h3>
              <router-link to="/admin/vendor-requests" class="ql-item">
                <span class="ql-ico ql-ico-purple">
                  <svg
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                  </svg>
                </span>
                <span>Review vendor requests</span>
                <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  class="ql-arrow"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </router-link>
              <router-link to="/admin/product-approval" class="ql-item">
                <span class="ql-ico ql-ico-green">
                  <svg
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                </span>
                <span>Approve products</span>
                <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  class="ql-arrow"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 19 12" />
                </svg>
              </router-link>
              <router-link to="/admin/reports" class="ql-item">
                <span class="ql-ico ql-ico-red">
                  <svg
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path
                      d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                    />
                  </svg>
                </span>
                <span>Review reported products</span>
                <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  class="ql-arrow"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 19 12" />
                </svg>
              </router-link>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();

const isLoading = ref(true);

const stats = ref({
  total_vendors: 0,
  pending_vendors: 0,
  approved_vendors: 0,
  rejected_vendors: 0,
  new_vendors_this_month: 0,
  pending_reports: 0,
});

const recentApplications = ref([]);
const recentReports = ref([]);

// ─── Charts ────────────────────────────────────────────────
let vendorChartInst = null;
let donutChartInst = null;

const loadChartJs = () =>
  new Promise((resolve) => {
    if (window.Chart) return resolve();
    const s = document.createElement("script");
    s.src =
      "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js";
    s.onload = resolve;
    document.head.appendChild(s);
  });

const renderVendorChart = (monthlyData) => {
  const ctx = document.getElementById("vendorChart");
  if (!ctx) return;
  if (vendorChartInst) vendorChartInst.destroy();

  const labels = monthlyData.map((d) => d.month);
  const pending = monthlyData.map((d) => d.pending);
  const approved = monthlyData.map((d) => d.approved);
  const rejected = monthlyData.map((d) => d.rejected);

  vendorChartInst = new window.Chart(ctx, {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: "Pending",
          data: pending,
          backgroundColor: "#7c6ff7",
          borderRadius: 4,
          barPercentage: 0.7,
        },
        {
          label: "Approved",
          data: approved,
          backgroundColor: "#4ade80",
          borderRadius: 4,
          barPercentage: 0.7,
        },
        {
          label: "Rejected",
          data: rejected,
          backgroundColor: "#f87171",
          borderRadius: 4,
          barPercentage: 0.7,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { mode: "index" } },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: "#94a3b8", font: { size: 11 } },
        },
        y: {
          grid: { color: "#f1f5f9" },
          ticks: { color: "#94a3b8", font: { size: 11 }, precision: 0 },
        },
      },
    },
  });
};

const renderDonutChart = (pending, approved, rejected) => {
  const ctx = document.getElementById("donutChart");
  if (!ctx) return;
  if (donutChartInst) donutChartInst.destroy();

  donutChartInst = new window.Chart(ctx, {
    type: "doughnut",
    data: {
      labels: ["Pending", "Approved", "Rejected"],
      datasets: [
        {
          data: [pending, approved, rejected],
          backgroundColor: ["#7c6ff7", "#4ade80", "#f87171"],
          borderWidth: 0,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "72%",
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.raw}` } },
      },
    },
  });
};

// ─── Build monthly data from raw applications ───────────────
const buildMonthlyData = (applications) => {
  const months = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ];
  const now = new Date();
  const last6 = [];
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    last6.push({
      month: months[d.getMonth()],
      year: d.getFullYear(),
      pending: 0,
      approved: 0,
      rejected: 0,
    });
  }

  applications.forEach((app) => {
    const d = new Date(app.submitted_at);
    const bucket = last6.find(
      (b) => b.month === months[d.getMonth()] && b.year === d.getFullYear(),
    );
    if (bucket) {
      const st = app.status?.toLowerCase();
      if (st === "pending") bucket.pending++;
      else if (st === "approved") bucket.approved++;
      else if (st === "rejected") bucket.rejected++;
    }
  });

  return last6;
};

// ─── Fetch all data ─────────────────────────────────────────
const fetchDashboard = async () => {
  isLoading.value = true;
  try {
    // Statistics
    const { data: statsData } = await api.get(
      "/admin/vendor-applications/statistics",
    );
    stats.value = {
      total_vendors:
        (statsData.approved ?? 0) +
        (statsData.pending ?? 0) +
        (statsData.rejected ?? 0),
      pending_vendors: statsData.pending ?? 0,
      approved_vendors: statsData.approved ?? 0,
      rejected_vendors: statsData.rejected ?? 0,
      new_vendors_this_month: statsData.this_month ?? 0,
      pending_reports: 0,
    };

    // Recent applications (all statuses, last 6)
    const { data: appData } = await api.get("/admin/vendor-applications", {
      params: { per_page: 5, page: 1 },
    });
    const allApps = appData.data ?? [];
    recentApplications.value = allApps.slice(0, 5);

    // All applications for chart (get a bigger set)
    const { data: chartData } = await api.get("/admin/vendor-applications", {
      params: { per_page: 100, page: 1 },
    });
    const chartApps = chartData.data ?? [];
    const monthly = buildMonthlyData(chartApps);

    // Product Reports
    try {
      const { data: rptData } = await api.get("/admin/reports", {
        params: { per_page: 5, status: "pending" },
      });
      const reports = rptData.data ?? rptData ?? [];
      recentReports.value = Array.isArray(reports) ? reports.slice(0, 4) : [];
      stats.value.pending_reports =
        rptData.meta?.total ?? recentReports.value.length;
    } catch (_) {
      recentReports.value = [];
    }

    // Render charts after DOM updates
    await nextTick();
    await loadChartJs();
    renderVendorChart(monthly);
    renderDonutChart(
      stats.value.pending_vendors,
      stats.value.approved_vendors,
      stats.value.rejected_vendors,
    );
  } catch (err) {
    console.error("Dashboard fetch error:", err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchDashboard);

// ─── Formatters ─────────────────────────────────────────────
const formatBizType = (t) =>
  ({
    individual: "Sole Prop",
    partnership: "Partnership",
    corporation: "Corporation",
  })[t] ??
  t ??
  "—";
const formatStatus = (s) =>
  ({
    pending: "Pending",
    approved: "Approved",
    rejected: "Rejected",
    under_review: "Under Review",
  })[s] ??
  s ??
  "—";
const formatDate = (d) => {
  if (!d) return "—";
  try {
    return new Date(d).toLocaleDateString("en-PH", {
      month: "short",
      day: "numeric",
      year: "2-digit",
    });
  } catch {
    return d;
  }
};
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}

/* ── Layout ───────────────────────────────────────────────── */
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}

.main-content {
  margin-left: 240px;
  flex: 1;
  padding: 0;
  min-height: 100vh;
  transition: margin-left 0.3s;
}

.dashboard-body {
  padding: 0 28px 40px;
}

/* ── Header ───────────────────────────────────────────────── */
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 28px 20px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
}

@media (max-width: 968px) {
  .mobile-toggle {
    display: flex;
  }
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
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.search-ico {
  position: absolute;
  left: 11px;
  color: #94a3b8;
  pointer-events: none;
}
.search-input {
  padding: 8px 14px 8px 34px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  width: 220px;
  color: #1e2739;
  transition: border 0.2s;
  background: #f8fafc;
}
.search-input:focus {
  outline: none;
  border-color: #7c6ff7;
  background: #fff;
}

.icon-pill {
  position: relative;
  width: 36px;
  height: 36px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
}
.notif-dot {
  position: absolute;
  top: 7px;
  right: 7px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #f87171;
  border: 1.5px solid #fff;
}

.avatar-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 12px 4px 4px;
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  background: #fff;
  cursor: pointer;
}
.avatar-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7c6ff7, #5b4fc9);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
}
.avatar-name {
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
}

/* ── Dashboard body spacing ───────────────────────────────── */
.dashboard-body > section {
  margin-top: 24px;
}

/* ── KPI Cards ────────────────────────────────────────────── */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.kpi-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #e9edf3;
  transition: box-shadow 0.2s;
}
.kpi-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.07);
}

.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.kpi-label {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}

.kpi-icon-wrap {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.kpi-icon-purple {
  background: #ede9ff;
  color: #7c6ff7;
}
.kpi-icon-amber {
  background: #fef3c7;
  color: #d97706;
}
.kpi-icon-green {
  background: #dcfce7;
  color: #16a34a;
}
.kpi-icon-red {
  background: #fee2e2;
  color: #dc2626;
}

.kpi-value {
  font-size: 32px;
  font-weight: 700;
  color: #1e2739;
  margin-bottom: 10px;
  line-height: 1;
}

.kpi-badge {
  font-size: 11px;
  font-weight: 500;
  padding: 3px 9px;
  border-radius: 20px;
}
.kpi-badge-up {
  background: #dcfce7;
  color: #16a34a;
}
.kpi-badge-warn {
  background: #fef3c7;
  color: #b45309;
}
.kpi-badge-danger {
  background: #fee2e2;
  color: #dc2626;
}

/* ── Charts Row ───────────────────────────────────────────── */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 16px;
}

.chart-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e9edf3;
  padding: 20px;
}

.chart-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 12px;
  flex-wrap: wrap;
}

.chart-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
}
.chart-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}

.legend-row {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 2px;
  display: inline-block;
}
.legend-text {
  font-size: 12px;
  color: #64748b;
}

.chart-canvas-wrap {
  position: relative;
  height: 220px;
}

/* Donut card */
.donut-legend {
  margin-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.donut-leg-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.donut-leg-row .legend-text {
  flex: 1;
  font-size: 13px;
  color: #475569;
}
.legend-num {
  font-size: 13px;
  font-weight: 600;
  color: #1e2739;
}

/* ── Bottom Row ───────────────────────────────────────────── */
.bottom-row {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 16px;
}

.table-card,
.reports-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e9edf3;
  padding: 20px;
}

.table-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;
}

.view-all-link {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 500;
  color: #7c6ff7;
  text-decoration: none;
  white-space: nowrap;
  padding-top: 2px;
  transition: opacity 0.2s;
}
.view-all-link:hover {
  opacity: 0.75;
}

.empty-msg {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 32px 0;
}

/* Mini Table */
.mini-table {
  width: 100%;
}
.mini-table-head {
  display: grid;
  grid-template-columns: 2fr 1.3fr 1fr 1fr 1fr;
  padding: 8px 10px;
  background: #f8fafc;
  border-radius: 6px;
  margin-bottom: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.mini-table-row {
  display: grid;
  grid-template-columns: 2fr 1.3fr 1fr 1fr 1fr;
  align-items: center;
  padding: 10px 10px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
  transition: background 0.15s;
}
.mini-table-row:hover {
  background: #f8fafc;
  border-radius: 6px;
}
.mini-table-row:last-child {
  border-bottom: none;
}

.store-cell {
  display: flex;
  align-items: center;
  gap: 9px;
}
.store-ava {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7c6ff7, #5b4fc9);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
}
.store-nm {
  font-size: 13px;
  font-weight: 500;
  color: #1e2739;
}
.text-secondary {
  color: #64748b;
}

/* Status pills */
.status-pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}
.pill-pending {
  background: #fef3c7;
  color: #b45309;
}
.pill-approved {
  background: #dcfce7;
  color: #16a34a;
}
.pill-rejected {
  background: #fee2e2;
  color: #dc2626;
}
.pill-under_review {
  background: #e0e7ff;
  color: #4338ca;
}

/* Reports card rows */
.report-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
}
.report-row:last-of-type {
  border-bottom: none;
}

.report-icon-wrap {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: #fee2e2;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.report-info {
  flex: 1;
  min-width: 0;
}
.report-name {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #1e2739;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.report-reason {
  display: block;
  font-size: 11px;
  color: #94a3b8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Quick Links */
.quick-links {
  margin-top: 20px;
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
}
.ql-title {
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 10px;
}

.ql-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 8px;
  text-decoration: none;
  font-size: 13px;
  color: #475569;
  font-weight: 500;
  transition: background 0.15s;
  margin-bottom: 4px;
}
.ql-item:hover {
  background: #f8fafc;
  color: #1e2739;
}

.ql-ico {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ql-ico-purple {
  background: #ede9ff;
  color: #7c6ff7;
}
.ql-ico-green {
  background: #dcfce7;
  color: #16a34a;
}
.ql-ico-red {
  background: #fee2e2;
  color: #dc2626;
}

.ql-arrow {
  margin-left: auto;
  color: #cbd5e0;
}
.ql-item:hover .ql-arrow {
  color: #7c6ff7;
}

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
  .kpi-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1100px) {
  .charts-row {
    grid-template-columns: 1fr;
  }
  .bottom-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .kpi-row {
    grid-template-columns: repeat(2, 1fr);
  }
  .search-input {
    width: 160px;
  }
}

@media (max-width: 640px) {
  .kpi-row {
    grid-template-columns: 1fr;
  }
  .content-header {
    flex-wrap: wrap;
    gap: 12px;
  }
  .header-right {
    width: 100%;
  }
  .search-input {
    flex: 1;
    width: auto;
  }
  .dashboard-body {
    padding: 0 14px 40px;
  }
  .mini-table-head,
  .mini-table-row {
    grid-template-columns: 2fr 1fr 1fr;
  }
  .mini-table-head span:nth-child(2),
  .mini-table-row span:nth-child(2),
  .mini-table-head span:nth-child(3),
  .mini-table-row span:nth-child(3) {
    display: none;
  }
}
</style>
