<template>
  <div class="supplier-list-page">
    
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Suppliers</h1>
        <p class="page-sub">Manage your supply chain partners</p>
      </div>
      <router-link
        to="/erp/procurement/supply-chain/suppliers/create"
        class="btn-primary"
      >
        <svg viewBox="0 0 20 20" fill="currentColor" width="16">
          <path
            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
          />
        </svg>
        Add Supplier
      </router-link>
    </div>

    <!-- Stats Strip -->
    <div class="stats-strip">
      <div class="stat-card" v-for="s in stats" :key="s.label">
        <span class="stat-num" :style="{ color: s.color }">{{ s.value }}</span>
        <span class="stat-label">{{ s.label }}</span>
      </div>
    </div>

    <!-- Filters & Table Card -->
    <div class="card">
      <div class="card-toolbar">
        <div class="filter-tabs">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            class="filter-tab"
            :class="{ active: activeTab === tab.value }"
            @click="
              activeTab = tab.value;
              fetchSuppliers();
            "
          >
            {{ tab.label }}
          </button>
        </div>
        <div class="toolbar-right">
          <div class="search-box">
            <svg
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              width="15"
            >
              <circle cx="9" cy="9" r="6" />
              <path d="m14 14 3 3" />
            </svg>
            <input
              v-model="searchQuery"
              placeholder="Search anything…"
              @input="debouncedSearch"
            />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>
                <input
                  type="checkbox"
                  @change="toggleSelectAll"
                  :checked="allSelected"
                />
              </th>
              <th>Supplier Name</th>
              <th>Contact</th>
              <th>Status</th>
              <th>Last Order</th>
              <th>Orders</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="loading-row">
                <div class="spinner"></div>
              </td>
            </tr>
            <tr v-else-if="!suppliers.length">
              <td colspan="7" class="empty-row">No suppliers found</td>
            </tr>
            <tr
              v-for="s in suppliers"
              :key="s.id"
              class="data-row"
              :class="{ selected: selectedIds.includes(s.id) }"
            >
              <td>
                <input type="checkbox" :value="s.id" v-model="selectedIds" />
              </td>
              <td>
                <div class="supplier-name-cell">
                  <div class="supplier-avatar-wrap">
                    <img
                      v-if="s.logo_url"
                      :src="s.logo_url"
                      :alt="s.company_name"
                      class="supplier-avatar-img"
                      @error="$event.target.style.display = 'none'"
                    />
                    <div
                      v-else
                      class="supplier-avatar"
                      :style="{ background: avatarColor(s.company_name) }"
                    >
                      {{ s.company_name?.[0]?.toUpperCase() ?? "?" }}
                    </div>
                  </div>
                  <div>
                    <div class="supplier-name">{{ s.company_name }}</div>
                    <div class="supplier-loc">
                      <svg viewBox="0 0 12 16" fill="currentColor" width="9">
                        <path
                          d="M6 0C3.24 0 1 2.24 1 5c0 3.75 5 11 5 11s5-7.25 5-11c0-2.76-2.24-5-5-5zm0 7.5A2.5 2.5 0 113.5 5 2.5 2.5 0 016 7.5z"
                        />
                      </svg>
                      {{
                        s.address?.split(",").slice(-2).join(",").trim() || "—"
                      }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <div class="contact-cell">
                  <div class="contact-name">{{ s.contact_person }}</div>
                  <a
                    :href="`mailto:${s.email}`"
                    class="contact-email contact-email-link"
                  >
                    {{ s.email }}
                  </a>
                </div>
              </td>
              <td>
                <span class="status-badge" :class="s.status">{{
                  s.status
                }}</span>
              </td>
              <td class="muted-cell">{{ formatDate(s.updated_at) }}</td>
              <td class="muted-cell">{{ s.purchase_orders_count ?? "—" }}</td>
              <td>
                <div class="action-cell">
                  <router-link
                    :to="`/erp/procurement/supply-chain/suppliers/${s.id}/edit`"
                    class="action-btn edit-btn"
                    title="Edit"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="15"
                    >
                      <path d="M13.5 3.5l3 3L7 16H4v-3L13.5 3.5z" />
                    </svg>
                  </router-link>
                  <button
                    v-if="s.status === 'active'"
                    @click="deactivate(s)"
                    class="action-btn warn-btn"
                    title="Deactivate"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="15"
                    >
                      <circle cx="10" cy="10" r="7" />
                      <path d="M7 10h6" />
                    </svg>
                  </button>
                  <button
                    v-else
                    @click="activate(s)"
                    class="action-btn ok-btn"
                    title="Activate"
                  >
                    <svg
                      viewBox="0 0 20 20"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                      width="15"
                    >
                      <circle cx="10" cy="10" r="7" />
                      <path d="M7 10l2 2 4-4" />
                    </svg>
                  </button>
                  <button @click="openMenu(s)" class="action-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="15">
                      <circle cx="10" cy="4" r="1.5" />
                      <circle cx="10" cy="10" r="1.5" />
                      <circle cx="10" cy="16" r="1.5" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="meta.last_page > 1">
        <button
          class="page-btn"
          :disabled="meta.current_page === 1"
          @click="goPage(meta.current_page - 1)"
        >
          ‹
        </button>
        <span class="page-info"
          >Page {{ meta.current_page }} of {{ meta.last_page }}</span
        >
        <button
          class="page-btn"
          :disabled="meta.current_page === meta.last_page"
          @click="goPage(meta.current_page + 1)"
        >
          ›
        </button>
      </div>
    </div>

    <!-- Context Menu -->
    <div
      v-if="menuSupplier"
      class="context-menu"
      :style="menuStyle"
      @mouseleave="menuSupplier = null"
    >
      <button @click="blacklist(menuSupplier)">🚫 Blacklist</button>
      <button @click="deleteSupplier(menuSupplier)" class="danger">
        🗑 Delete
      </button>
    </div>

    <!-- Toast -->
    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import { supplierService } from "../../../../../services/supplierService";


const suppliers = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const activeTab = ref("all");
const selectedIds = ref([]);
const menuSupplier = ref(null);
const menuStyle = ref({});
const meta = ref({ current_page: 1, last_page: 1 });

const statusTabs = [
  { label: "All", value: "all" },
  { label: "Active", value: "active" },
  { label: "Inactive", value: "inactive" },
  { label: "Pending", value: "blacklisted" },
];

const stats = ref([
  { label: "Total Suppliers", value: 0, color: "#111827" },
  { label: "Active", value: 0, color: "#10b981" },
  { label: "Inactive", value: 0, color: "#f59e0b" },
  { label: "Blacklisted", value: 0, color: "#ef4444" },
]);

const allSelected = computed(
  () =>
    suppliers.value.length &&
    selectedIds.value.length === suppliers.value.length,
);

let searchTimeout = null;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(fetchSuppliers, 400);
};

async function fetchSuppliers(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 12 };
    if (activeTab.value !== "all") params.status = activeTab.value;
    if (searchQuery.value) params.search = searchQuery.value;

    const res = await supplierService.list(params);

    const raw = res?.data?.data ?? res?.data ?? res ?? [];
    suppliers.value = Array.isArray(raw) ? raw.filter((s) => s?.id) : [];
    meta.value = res?.data?.meta ?? res?.meta ?? meta.value;

    stats.value[0].value = res?.data?.meta?.total ?? suppliers.value.length;
    const counts = { active: 0, inactive: 0, blacklisted: 0 };
    suppliers.value.forEach((s) => {
      if (counts[s.status] !== undefined) counts[s.status]++;
    });
    stats.value[1].value = counts.active;
    stats.value[2].value = counts.inactive;
    stats.value[3].value = counts.blacklisted;
  } catch (e) {
    console.error(e);
    showToast("Failed to load suppliers", "error");
  } finally {
    loading.value = false;
  }
}

async function activate(s) {
  try {
    await supplierService.activate(s.id);
    s.status = "active";
    showToast(`${s.company_name} activated`);
  } catch {
    showToast("Failed to activate supplier", "error");
  }
}

async function deactivate(s) {
  try {
    await supplierService.deactivate(s.id);
    s.status = "inactive";
    showToast(`${s.company_name} deactivated`, "warn");
  } catch {
    showToast("Failed to deactivate supplier", "error");
  }
}

async function blacklist(s) {
  try {
    await supplierService.blacklist(s.id);
    s.status = "blacklisted";
    menuSupplier.value = null;
    showToast(`${s.company_name} blacklisted`, "error");
  } catch {
    showToast("Failed to blacklist supplier", "error");
  }
}

async function deleteSupplier(s) {
  if (!confirm(`Delete ${s.company_name}?`)) return;
  try {
    await supplierService.remove(s.id);
    suppliers.value = suppliers.value.filter((x) => x.id !== s.id);
    menuSupplier.value = null;
    showToast("Supplier deleted");
  } catch {
    showToast("Failed to delete supplier", "error");
  }
}

function openMenu(s) {
  menuSupplier.value = s;
  menuStyle.value = { top: "80px", right: "40px" };
}

function toggleSelectAll(e) {
  selectedIds.value = e.target.checked ? suppliers.value.map((s) => s.id) : [];
}

function goPage(p) {
  fetchSuppliers(p);
}

function avatarColor(name) {
  const colors = [
    "#dbeafe",
    "#dcfce7",
    "#fef3c7",
    "#fce7f3",
    "#ede9fe",
    "#e0f2fe",
  ];
  if (!name) return colors[0];
  return colors[name.charCodeAt(0) % colors.length];
}

function formatDate(d) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function showToast(msg, type = "success") {
  const map = {
    success: toast.success,
    error: toast.error,
    warn: toast.warning,
  };
  (map[type] ?? toast.success)(msg, {
    autoClose: 3000,
    position: toast.POSITION.TOP_RIGHT,
  });
}

onMounted(fetchSuppliers);
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.supplier-list-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}
.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: #6b7280;
  margin: 2px 0 0;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition:
    background 0.15s,
    transform 0.1s;
}
.btn-primary:hover {
  background: #059669;
  transform: translateY(-1px);
}

/* Stats Strip */
.stats-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  border: 1px solid #e8ecf0;
}
.stat-num {
  font-size: 26px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.stat-label {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

/* Card */
.card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e8ecf0;
  overflow: hidden;
}
.card-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid #f0f2f5;
}

.filter-tabs {
  display: flex;
  gap: 4px;
}
.filter-tab {
  padding: 6px 14px;
  border-radius: 7px;
  border: none;
  background: none;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition:
    background 0.15s,
    color 0.15s;
}
.filter-tab:hover {
  background: #f3f4f6;
  color: #374151;
}
.filter-tab.active {
  background: #ecfdf5;
  color: #10b981;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.search-box input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: #374151;
  width: 200px;
}
.search-box input::placeholder {
  color: #9ca3af;
}
.search-box svg {
  color: #9ca3af;
  flex-shrink: 0;
}

/* Table */
.table-wrap {
  overflow-x: auto;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.data-table thead tr {
  background: #f9fafb;
}
.data-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid #e8ecf0;
}
.data-row td {
  padding: 13px 16px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}
.data-row:last-child td {
  border-bottom: none;
}
.data-row:hover td {
  background: #fafafa;
}
.data-row.selected td {
  background: #f0fdf4;
}

.loading-row,
.empty-row {
  text-align: center;
  padding: 48px 16px !important;
  color: #9ca3af;
  font-size: 14px;
}
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 0 auto;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.supplier-name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.supplier-avatar-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid #e8ecf0;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #b0b1b1;
}

.supplier-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 10px;
}
.supplier-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  color: #374151;
  flex-shrink: 0;
}

.supplier-name {
  font-weight: 600;
  color: #111827;
  font-size: 13.5px;
}
.supplier-loc {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #9ca3af;
  margin-top: 2px;
}

.contact-name {
  font-weight: 500;
  color: #374151;
}
.contact-email {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 2px;
}

.contact-email-link {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.15s;
}

.contact-email-link:hover {
  color: #2563eb;
  text-decoration: underline;
}

.muted-cell {
  color: #6b7280;
}

/* Status Badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}
.status-badge.active {
  background: #dcfce7;
  color: #16a34a;
}
.status-badge.inactive {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.blacklisted {
  background: #fee2e2;
  color: #dc2626;
}
.status-badge.pending {
  background: #ede9fe;
  color: #7c3aed;
}

/* Actions */
.action-cell {
  display: flex;
  align-items: center;
  gap: 4px;
}
.action-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  transition: all 0.15s;
  text-decoration: none;
}
.action-btn:hover {
  border-color: #d1d5db;
  background: #f9fafb;
  color: #374151;
}
.edit-btn:hover {
  border-color: #a5f3fc;
  color: #0891b2;
  background: #ecfeff;
}
.warn-btn:hover {
  border-color: #fde68a;
  color: #d97706;
  background: #fffbeb;
}
.ok-btn:hover {
  border-color: #a7f3d0;
  color: #059669;
  background: #ecfdf5;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 20px;
  border-top: 1px solid #f0f2f5;
}
.page-btn {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  border: 1px solid #e5e7eb;
  background: #fff;
  cursor: pointer;
  font-size: 16px;
  color: #374151;
  display: flex;
  align-items: center;
  justify-content: center;
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-info {
  font-size: 13px;
  color: #6b7280;
}

/* Context Menu */
.context-menu {
  position: fixed;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  z-index: 100;
  min-width: 160px;
}
.context-menu button {
  width: 100%;
  text-align: left;
  padding: 10px 16px;
  background: none;
  border: none;
  font-size: 13.5px;
  cursor: pointer;
  color: #374151;
}
.context-menu button:hover {
  background: #f9fafb;
}
.context-menu button.danger {
  color: #ef4444;
}
.context-menu button.danger:hover {
  background: #fff5f5;
}

/* Toast */
.toast {
  position: fixed;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%);
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 500;
  z-index: 500;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.toast.success {
  background: #111827;
  color: #fff;
}
.toast.error {
  background: #ef4444;
  color: #fff;
}
.toast.warn {
  background: #f59e0b;
  color: #fff;
}
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(12px);
}
</style>
