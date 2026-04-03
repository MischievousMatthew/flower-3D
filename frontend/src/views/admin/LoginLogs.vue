<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <div>
            <h1 class="page-title">Login Logs</h1>
            <p class="page-subtitle">Monitor account sign-ins across admin, vendor, customer, and employee accounts.</p>
          </div>
        </div>

        <div class="header-actions">
          <div class="search-box">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by name, username, device, IP..."
              @input="onSearch"
            />
            <span class="search-icon">🔍</span>
          </div>
          <select v-model="actorType" class="filter-select" @change="fetchLogs(1)">
            <option value="">All accounts</option>
            <option value="admin">Admins</option>
            <option value="vendor">Vendors</option>
            <option value="customer">Customers</option>
            <option value="employee">Employees</option>
          </select>
        </div>
      </header>

      <LoadingOverlay :visible="loading" message="Loading login logs..." />

      <section class="stats-grid">
        <div class="stat-card">
          <span class="stat-label">Total logs</span>
          <strong class="stat-value">{{ pagination.total }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">Current page</span>
          <strong class="stat-value">{{ pagination.current_page }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">Per page</span>
          <strong class="stat-value">{{ pagination.per_page }}</strong>
        </div>
      </section>

      <section class="content-panel">
        <div v-if="!logs.length && !loading" class="empty-state">
          No login activity found.
        </div>

        <div v-else class="table-wrap">
          <table class="logs-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Account</th>
                <th>Time</th>
                <th>Device</th>
                <th>Location</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td>
                  <div class="user-cell">
                    <strong>{{ log.actor_name }}</strong>
                    <span>{{ log.username || log.email || "Unknown account" }}</span>
                  </div>
                </td>
                <td>
                  <div class="meta-cell">
                    <span class="badge">{{ log.actor_type }}</span>
                    <small>{{ log.role || "No role" }}</small>
                  </div>
                </td>
                <td>
                  <div class="meta-cell">
                    <strong>{{ formatDate(log.logged_in_at) }}</strong>
                    <small>{{ formatRelative(log.logged_in_at) }}</small>
                  </div>
                </td>
                <td>
                  <div class="meta-cell">
                    <strong>{{ log.device_name || "Unknown device" }}</strong>
                    <small>{{ [log.browser, log.platform].filter(Boolean).join(" • ") || "Unknown environment" }}</small>
                  </div>
                </td>
                <td>
                  <div class="meta-cell">
                    <strong>{{ log.location_label || "Location unavailable" }}</strong>
                    <small>
                      {{
                        log.location_accuracy
                          ? `Accuracy ±${log.location_accuracy}m`
                          : log.timezone || "No precision data"
                      }}
                    </small>
                  </div>
                </td>
                <td>{{ log.ip_address || "Unknown IP" }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.last_page > 1" class="pagination">
          <button
            class="page-btn"
            :disabled="pagination.current_page === 1 || loading"
            @click="fetchLogs(pagination.current_page - 1)"
          >
            Prev
          </button>
          <button
            v-for="page in visiblePages"
            :key="page"
            class="page-btn"
            :class="{ active: pagination.current_page === page }"
            :disabled="loading"
            @click="fetchLogs(page)"
          >
            {{ page }}
          </button>
          <button
            class="page-btn"
            :disabled="pagination.current_page === pagination.last_page || loading"
            @click="fetchLogs(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../plugins/axios";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import { useSidebarState } from "../../composables/useSidebarState";

const { openMobile } = useSidebarState();

const logs = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const actorType = ref("");
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
});

let searchTimer = null;

const toggleMobile = () => {
  openMobile();
};

const visiblePages = computed(() => {
  const current = pagination.value.current_page;
  const last = pagination.value.last_page;
  const start = Math.max(1, current - 2);
  const end = Math.min(last, current + 2);

  return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});

const formatDate = (value) => {
  if (!value) return "Unknown time";

  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
};

const formatRelative = (value) => {
  if (!value) return "";

  const minutes = Math.round((Date.now() - new Date(value).getTime()) / 60000);
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes} minute${minutes === 1 ? "" : "s"} ago`;

  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;

  const days = Math.round(hours / 24);
  return `${days} day${days === 1 ? "" : "s"} ago`;
};

const fetchLogs = async (page = 1) => {
  loading.value = true;

  try {
    const { data } = await api.get("/admin/login-logs", {
      params: {
        page,
        per_page: pagination.value.per_page,
        search: searchQuery.value || undefined,
        actor_type: actorType.value || undefined,
      },
    });

    const payload = data?.data ?? {};
    logs.value = payload.data ?? [];
    pagination.value = {
      current_page: payload.current_page ?? 1,
      last_page: payload.last_page ?? 1,
      per_page: payload.per_page ?? 20,
      total: payload.total ?? 0,
    };
  } catch (error) {
    console.error("Failed to fetch login logs:", error);
    logs.value = [];
  } finally {
    loading.value = false;
  }
};

const onSearch = () => {
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => {
    fetchLogs(1);
  }, 350);
};

onMounted(() => {
  fetchLogs();
});
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
}

.admin-layout {
  min-height: 100vh;
  background: #f8fafc;
}

.main-content {
  margin-left: 240px;
  padding: 24px;
}

.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-toggle {
  display: none;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
}

.page-title {
  margin: 0;
  font-size: 28px;
  color: #0f172a;
}

.page-subtitle {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 14px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.search-box {
  position: relative;
}

.search-box input,
.filter-select {
  border: 1px solid #dbe3ef;
  border-radius: 12px;
  background: #fff;
  padding: 12px 14px;
  font-size: 14px;
}

.search-box input {
  width: 320px;
  padding-right: 40px;
}

.search-icon {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card,
.content-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
}

.stat-card {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

.stat-value {
  font-size: 28px;
  color: #0f172a;
}

.content-panel {
  padding: 20px;
}

.empty-state {
  padding: 48px 20px;
  text-align: center;
  color: #64748b;
}

.table-wrap {
  overflow-x: auto;
}

.logs-table {
  width: 100%;
  border-collapse: collapse;
}

.logs-table th,
.logs-table td {
  text-align: left;
  padding: 14px 12px;
  border-bottom: 1px solid #eef2f7;
  vertical-align: top;
}

.logs-table th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.user-cell,
.meta-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.user-cell strong,
.meta-cell strong {
  color: #0f172a;
}

.user-cell span,
.meta-cell small {
  color: #64748b;
}

.badge {
  width: fit-content;
  text-transform: capitalize;
  background: #e2e8f0;
  color: #0f172a;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 20px;
}

.page-btn {
  min-width: 42px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #dbe3ef;
  background: #fff;
  cursor: pointer;
}

.page-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}

.page-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
    padding: 16px;
  }

  .content-header {
    flex-direction: column;
    align-items: stretch;
  }

  .mobile-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box input,
  .filter-select {
    width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
