<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <!-- ── Header ─────────────────────────────────────── -->
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
            <h1 class="page-title">Reported Products</h1>
            <p class="page-subtitle">Review and action customer reports</p>
          </div>
        </div>
        <div class="header-actions">
          <div class="search-box">
            <span class="search-icon">🔍</span>
            <input
              type="text"
              placeholder="Search product or reason..."
              v-model="searchQuery"
              @input="onSearch"
            />
          </div>
          <div class="user-profile">
            <div class="admin-avatar">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <div class="profile-info">
              <span class="profile-name">{{ user?.name ?? "Admin" }}</span>
              <span class="profile-role">Administrator</span>
            </div>
          </div>
        </div>
      </header>

      <!-- ── Stats row ──────────────────────────────────── -->
      <div class="stats-row">
        <div
          class="stat-pill"
          :class="{ active: activeTab === 'all' }"
          @click="setTab('all')"
        >
          <span class="stat-pill-icon">📋</span>
          <div>
            <span class="stat-pill-num">{{ counts.all }}</span>
            <span class="stat-pill-label">All Reports</span>
          </div>
        </div>
        <div
          class="stat-pill"
          :class="{ active: activeTab === 'pending' }"
          @click="setTab('pending')"
        >
          <span class="stat-pill-icon">⏳</span>
          <div>
            <span class="stat-pill-num">{{ counts.pending }}</span>
            <span class="stat-pill-label">Pending</span>
          </div>
        </div>
        <div
          class="stat-pill"
          :class="{ active: activeTab === 'approved' }"
          @click="setTab('approved')"
        >
          <span class="stat-pill-icon">🚫</span>
          <div>
            <span class="stat-pill-num">{{ counts.approved }}</span>
            <span class="stat-pill-label">Banned</span>
          </div>
        </div>
        <div
          class="stat-pill"
          :class="{ active: activeTab === 'rejected' }"
          @click="setTab('rejected')"
        >
          <span class="stat-pill-icon">✅</span>
          <div>
            <span class="stat-pill-num">{{ counts.rejected }}</span>
            <span class="stat-pill-label">Dismissed</span>
          </div>
        </div>
      </div>

      <!-- ── Content body ───────────────────────────────── -->
      <div class="content-body">
        <div v-if="loading" class="state-box">
          <div class="loading-spinner"></div>
          <p>Loading reports...</p>
        </div>

        <div v-else-if="reports.length === 0" class="state-box">
          <span class="empty-icon">🎉</span>
          <h3>No reports found</h3>
          <p>
            {{
              activeTab === "pending"
                ? "There are no pending reports to review."
                : activeTab === "all"
                  ? "No reports have been submitted yet."
                  : `No ${activeTab} reports.`
            }}
          </p>
        </div>

        <template v-else>
          <div class="table-wrap">
            <table class="reports-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Vendor</th>
                  <th>Reported By</th>
                  <th>Reason</th>
                  <th>Details</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th class="th-actions">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="report in reports"
                  :key="report.id"
                  class="report-row"
                  @click="openDetail(report)"
                >
                  <td>
                    <div class="cell-product">
                      <div class="product-thumb">
                        <img
                          v-if="getProductImage(report.product)"
                          :src="getProductImage(report.product)"
                          :alt="report.product?.product_name"
                          @error="handleImgError"
                        />
                        <span v-else class="thumb-fallback">🌸</span>
                      </div>
                      <div class="product-text">
                        <span class="product-name">{{
                          report.product?.product_name ?? "—"
                        }}</span>
                        <span
                          class="product-status-badge"
                          :class="`ps-${report.product?.status}`"
                          >{{
                            formatProductStatus(report.product?.status)
                          }}</span
                        >
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="cell-muted">{{ getStoreName(report) }}</span>
                  </td>
                  <td>
                    <div class="cell-reporter">
                      <span class="reporter-name"
                        >{{ report.reporter?.name }}
                        {{ report.reporter?.surname }}</span
                      >
                      <span class="reporter-email">{{
                        report.reporter?.email
                      }}</span>
                    </div>
                  </td>
                  <td>
                    <span
                      class="reason-badge"
                      :class="`reason-${report.reason}`"
                      >{{ formatReason(report.reason) }}</span
                    >
                  </td>
                  <td>
                    <span class="cell-desc">{{
                      report.description
                        ? truncate(report.description, 55)
                        : "—"
                    }}</span>
                  </td>
                  <td>
                    <span class="cell-date">{{
                      formatDate(report.created_at)
                    }}</span>
                  </td>
                  <td>
                    <span class="status-badge" :class="`rs-${report.status}`">{{
                      formatReportStatus(report.status)
                    }}</span>
                  </td>
                  <td class="td-actions" @click.stop>
                    <template v-if="report.status === 'pending'">
                      <button
                        class="btn-ban"
                        :disabled="reviewing === report.id"
                        @click="reviewReport(report.id, 'approve')"
                      >
                        <span
                          v-if="
                            reviewing === report.id &&
                            reviewAction === 'approve'
                          "
                          class="btn-spinner"
                        ></span>
                        <span v-else>🚫 Ban</span>
                      </button>
                      <button
                        class="btn-dismiss"
                        :disabled="reviewing === report.id"
                        @click="reviewReport(report.id, 'reject')"
                      >
                        <span
                          v-if="
                            reviewing === report.id && reviewAction === 'reject'
                          "
                          class="btn-spinner"
                        ></span>
                        <span v-else>✓ Dismiss</span>
                      </button>
                    </template>
                    <span v-else class="reviewed-by"
                      >Reviewed by {{ report.reviewer?.name ?? "Admin" }}</span
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="pagination.last_page > 1" class="pagination">
            <button
              class="page-btn"
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
            >
              ← Prev
            </button>
            <button
              v-for="p in visiblePages"
              :key="p"
              class="page-btn"
              :class="{ active: pagination.current_page === p }"
              @click="changePage(p)"
            >
              {{ p }}
            </button>
            <button
              class="page-btn"
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
            >
              Next →
            </button>
          </div>
        </template>
      </div>
    </main>

    <!-- ══════════ Report Detail Modal ══════════ -->
    <transition name="modal-fade">
      <div
        v-if="detailModal && selectedReport"
        class="modal-overlay"
        @click.self="detailModal = false"
      >
        <div class="modal-card">
          <div class="modal-header">
            <h2>Report Details</h2>
            <button class="btn-close" @click="detailModal = false">✕</button>
          </div>

          <div class="modal-body">
            <div class="modal-grid">
              <!-- ══ LEFT — photos / 3D model only ══ -->
              <div class="modal-left">
                <!-- View toggle -->
                <div
                  class="view-tabs"
                  v-if="selectedReport.product?.models?.length"
                >
                  <button
                    class="view-tab"
                    :class="{ active: modalView === 'images' }"
                    @click="modalView = 'images'"
                  >
                    🖼️ Photos
                  </button>
                  <button
                    class="view-tab"
                    :class="{ active: modalView === '3d' }"
                    @click="modalView = '3d'"
                  >
                    🎨 3D Model
                  </button>
                </div>

                <!-- Image view -->
                <div v-if="modalView === 'images'" class="image-panel">
                  <div
                    class="modal-product-img"
                    :class="{
                      clickable: !!getModalImage(selectedReport.product),
                    }"
                    @click="openLightbox(getModalImage(selectedReport.product))"
                    title="Click to enlarge"
                  >
                    <img
                      v-if="getModalImage(selectedReport.product)"
                      :src="getModalImage(selectedReport.product)"
                      :alt="selectedReport.product?.product_name"
                      @error="handleImgError"
                    />
                    <span v-else class="modal-img-fallback">🌸</span>
                    <div
                      class="img-zoom-hint"
                      v-if="getModalImage(selectedReport.product)"
                    >
                      <span>🔍 Click to enlarge</span>
                    </div>
                  </div>

                  <!-- Thumbnails -->
                  <div
                    v-if="(selectedReport.product?.images?.length ?? 0) > 1"
                    class="thumbnail-row"
                  >
                    <div
                      v-for="(img, idx) in selectedReport.product.images"
                      :key="img.id"
                      class="thumb-item"
                      :class="{ active: modalImageIndex === idx }"
                      @click="modalImageIndex = idx"
                    >
                      <img :src="img.image_url" @error="handleImgError" />
                    </div>
                  </div>

                  <!-- No image placeholder -->
                  <div
                    v-if="
                      !getProductImage(selectedReport.product) &&
                      !selectedReport.product?.images?.length
                    "
                    class="no-image-note"
                  >
                    <span>No images uploaded for this product.</span>
                  </div>
                </div>

                <!-- 3D Model view -->
                <div v-else-if="modalView === '3d'" class="model-viewer-wrap">
                  <ModelViewer3D
                    :model-url="getModelUrl(selectedReport.product.models[0])"
                    :model-type="selectedReport.product.models[0].model_type"
                    background-color="#f8f9fa"
                  />
                  <div class="model-meta">
                    <span class="model-type-badge">{{
                      selectedReport.product.models[0].model_type?.toUpperCase()
                    }}</span>
                    <span class="model-size">{{
                      formatFileSize(selectedReport.product.models[0].file_size)
                    }}</span>
                  </div>
                </div>
              </div>

              <!-- ══ RIGHT — ALL information ══ -->
              <div class="modal-right">
                <!-- Product info -->
                <div class="modal-section">
                  <div class="section-title-row">
                    <h4>Product</h4>
                    <span
                      class="product-status-badge"
                      :class="`ps-${selectedReport.product?.status}`"
                    >
                      {{ formatProductStatus(selectedReport.product?.status) }}
                    </span>
                  </div>
                  <div class="info-row">
                    <label>Name</label>
                    <span>{{
                      selectedReport.product?.product_name ?? "—"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <label>Category</label>
                    <span>{{ selectedReport.product?.category ?? "—" }}</span>
                  </div>
                  <div class="info-row">
                    <label>Price</label>
                    <span class="price-val"
                      >₱{{
                        formatCurrency(selectedReport.product?.selling_price)
                      }}</span
                    >
                  </div>
                  <div class="info-row">
                    <label>SKU</label>
                    <span class="sku-val">{{
                      selectedReport.product?.sku ?? "—"
                    }}</span>
                  </div>
                </div>

                <div class="section-divider"></div>

                <!-- Vendor info -->
                <div class="modal-section">
                  <h4>Vendor</h4>
                  <div class="info-row">
                    <label>Store Name</label>
                    <span>{{ getStoreName(selectedReport) }}</span>
                  </div>
                  <div class="info-row">
                    <label>Owner</label>
                    <span>{{
                      selectedReport.product?.owner?.name ?? "—"
                    }}</span>
                  </div>
                  <div class="info-row">
                    <label>Ban Count</label>
                    <span
                      :class="{
                        'text-danger':
                          (selectedReport.vendor_ban_count ?? 0) >= 2,
                      }"
                    >
                      {{ selectedReport.vendor_ban_count ?? 0 }} / 3
                    </span>
                  </div>
                  <div class="info-row">
                    <label>Suspended</label>
                    <span
                      :class="
                        selectedReport.vendor_suspended
                          ? 'text-danger'
                          : 'text-success'
                      "
                    >
                      {{ selectedReport.vendor_suspended ? "Yes ⚠️" : "No ✓" }}
                    </span>
                  </div>
                </div>

                <div class="section-divider"></div>

                <!-- Report info -->
                <div class="modal-section">
                  <h4>Report Info</h4>
                  <div class="info-row">
                    <label>Reported By</label>
                    <span
                      >{{ selectedReport.reporter?.name }}
                      {{ selectedReport.reporter?.surname }}</span
                    >
                  </div>
                  <div class="info-row">
                    <label>Email</label>
                    <span>{{ selectedReport.reporter?.email }}</span>
                  </div>
                  <div class="info-row">
                    <label>Date</label>
                    <span>{{ formatDate(selectedReport.created_at) }}</span>
                  </div>
                  <div class="info-row">
                    <label>Reason</label>
                    <span
                      class="reason-badge"
                      :class="`reason-${selectedReport.reason}`"
                    >
                      {{ formatReason(selectedReport.reason) }}
                    </span>
                  </div>
                  <div
                    v-if="selectedReport.description"
                    class="info-row info-row--col"
                  >
                    <label>Details</label>
                    <p class="report-desc">{{ selectedReport.description }}</p>
                  </div>
                </div>

                <div class="section-divider"></div>

                <!-- Review status -->
                <div class="modal-section">
                  <div class="section-title-row">
                    <h4>Review Status</h4>
                    <span
                      class="status-badge"
                      :class="`rs-${selectedReport.status}`"
                    >
                      {{ formatReportStatus(selectedReport.status) }}
                    </span>
                  </div>
                  <template v-if="selectedReport.reviewer">
                    <div class="info-row">
                      <label>Reviewed by</label>
                      <span>{{ selectedReport.reviewer?.name }}</span>
                    </div>
                  </template>
                  <template v-if="selectedReport.reviewed_at">
                    <div class="info-row">
                      <label>Reviewed on</label>
                      <span>{{ formatDate(selectedReport.reviewed_at) }}</span>
                    </div>
                  </template>
                </div>

                <!-- Permanent ban warning -->
                <div
                  v-if="selectedReport.status === 'pending'"
                  class="warn-box"
                >
                  <span class="warn-icon">⚠️</span>
                  <div>
                    <strong>Banning is permanent and cannot be undone.</strong>
                    <p>
                      The product will be hidden from all customers. If the
                      vendor accumulates 3 bans their account will be
                      automatically suspended.
                    </p>
                  </div>
                </div>
              </div>
              <!-- end modal-right -->
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="detailModal = false">
              Close
            </button>
            <template v-if="selectedReport.status === 'pending'">
              <button
                class="btn-dismiss-large"
                :disabled="reviewing === selectedReport.id"
                @click="reviewReport(selectedReport.id, 'reject', true)"
              >
                <span
                  v-if="
                    reviewing === selectedReport.id && reviewAction === 'reject'
                  "
                  class="btn-spinner white"
                ></span>
                <span v-else>✓ Dismiss Report</span>
              </button>
              <button
                class="btn-ban-large"
                :disabled="reviewing === selectedReport.id"
                @click="reviewReport(selectedReport.id, 'approve', true)"
              >
                <span
                  v-if="
                    reviewing === selectedReport.id &&
                    reviewAction === 'approve'
                  "
                  class="btn-spinner white"
                ></span>
                <span v-else>🚫 Ban Product</span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <!-- ══════════ Lightbox ══════════ -->
    <transition name="lightbox-fade">
      <div v-if="lightboxUrl" class="lightbox-overlay" @click="closeLightbox">
        <button class="lightbox-close" @click.stop="closeLightbox">✕</button>
        <div class="lightbox-img-wrap" @click.stop>
          <img
            :src="lightboxUrl"
            alt="Full size"
            class="lightbox-img"
            @error="handleImgError"
          />
        </div>
        <p class="lightbox-hint">
          Click outside or ✕ to close &nbsp;·&nbsp; Esc
        </p>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import { useAuth } from "../../composables/useAuth";
import { useSidebarState } from "../../composables/useSidebarState";
import api from "../../plugins/axios";

const { user } = useAuth();
const { toggleMobile } = useSidebarState();

// ── State ─────────────────────────────────────────────
const reports = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const activeTab = ref("pending");
const reviewing = ref(null);
const reviewAction = ref("");
const detailModal = ref(false);
const selectedReport = ref(null);
const modalView = ref("images");
const modalImageIndex = ref(0);
const lightboxUrl = ref(null);

const counts = ref({ all: 0, pending: 0, approved: 0, rejected: 0 });
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
});

// ── Auth ──────────────────────────────────────────────
const getToken = () =>
  localStorage.getItem("auth_token") || sessionStorage.getItem("auth_token");

// ── Keyboard shortcuts ────────────────────────────────
const onKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (lightboxUrl.value) {
    closeLightbox();
    return;
  }
  if (detailModal.value) {
    detailModal.value = false;
  }
};
onMounted(() => {
  window.addEventListener("keydown", onKeydown);
  fetchReports();
  fetchCounts();
});
onUnmounted(() => window.removeEventListener("keydown", onKeydown));

// ── Lightbox ──────────────────────────────────────────
const openLightbox = (url) => {
  if (url) lightboxUrl.value = url;
};
const closeLightbox = () => {
  lightboxUrl.value = null;
};

// ── Image helpers ─────────────────────────────────────
const getProductImage = (product) => {
  if (!product) return null;
  return (
    product.primary_image?.image_url ??
    product.primaryImage?.image_url ??
    product.images?.find((img) => img.is_primary)?.image_url ??
    product.images?.[0]?.image_url ??
    null
  );
};

const getModalImage = (product) => {
  if (!product) return null;
  const imgs = product.images;
  if (imgs?.length && modalImageIndex.value < imgs.length)
    return imgs[modalImageIndex.value].image_url;
  return getProductImage(product);
};

const handleImgError = (e) => {
  e.target.style.display = "none";
};

// ── 3D model URL — route through API to avoid CORS ───
const getModelUrl = (modelRecord) => {
  if (!modelRecord) return "";
  const raw = modelRecord.model_url ?? modelRecord.model_path ?? "";
  const filename = raw.split("/").pop();
  return `${API_BASE}/customer/product-models/${filename}`;
};

// ── Vendor store name ─────────────────────────────────
const getStoreName = (report) => {
  if (!report) return "—";
  if (report.vendor_store_name) return report.vendor_store_name;
  if (report.product?.vendor_name) return report.product.vendor_name;
  const vd = report.product?.owner?.vendor_data;
  if (vd) {
    try {
      const p = typeof vd === "string" ? JSON.parse(vd) : vd;
      if (p?.store_name) return p.store_name;
    } catch (_) {
      /* ignore */
    }
  }
  return report.product?.owner?.name ?? "—";
};

// ── Fetch ─────────────────────────────────────────────
const fetchReports = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/reports', {
      params: {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...(activeTab.value !== 'all' && { status: activeTab.value }),
        ...(searchQuery.value && { search: searchQuery.value })
      }
    });
    const data = res.data;

    if (data.success) {
      reports.value = data.data.data ?? data.data;
      const meta = data.data.meta ?? data.data;
      pagination.value = {
        current_page: meta.current_page ?? 1,
        last_page: meta.last_page ?? 1,
        per_page: meta.per_page ?? 20,
        total: meta.total ?? reports.value.length,
      };
    } else {
      reports.value = [];
    }
  } catch (e) {
    console.error(e);
    reports.value = [];
  } finally {
    loading.value = false;
  }
};

const fetchCounts = async () => {
  try {
    const results = await Promise.all(
      ["pending", "approved", "rejected"].map((s) =>
        fetch(`${API_BASE}/admin/reports?status=${s}&per_page=1`, {
          headers: {
            Authorization: `Bearer ${getToken()}`,
            Accept: "application/json",
          },
        }).then((r) => r.json()),
      ),
    );
    counts.value.pending = results[0]?.data?.total ?? 0;
    counts.value.approved = results[1]?.data?.total ?? 0;
    counts.value.rejected = results[2]?.data?.total ?? 0;
    counts.value.all =
      counts.value.pending + counts.value.approved + counts.value.rejected;
  } catch (_) {
    /* silent */
  }
};

// ── Review action ─────────────────────────────────────
const reviewReport = async (reportId, action, fromModal = false) => {
  reviewing.value = reportId;
  reviewAction.value = action;
  try {
    const res = await api.post(`/admin/reports/${reportId}/review`, { action });
    const data = res.data;
    if (data.success) {
      toast.success(
        action === "approve" ? "Product banned." : "Report dismissed.",
      );
      if (action === "approve" && data.vendor_suspended) {
        toast.warning(
          `Vendor suspended (${data.vendor_ban_count} bans reached).`,
          { autoClose: 5000 },
        );
      }
      if (fromModal) detailModal.value = false;
      await fetchReports();
      await fetchCounts();
    } else {
      toast.error(data.message || "Failed to review report.");
    }
  } catch {
    toast.error("Request failed. Please try again.");
  } finally {
    reviewing.value = null;
    reviewAction.value = "";
  }
};

const openDetail = (report) => {
  selectedReport.value = report;
  modalView.value = "images";
  modalImageIndex.value = 0;
  detailModal.value = true;
};

// ── Tabs & pagination ─────────────────────────────────
const setTab = (tab) => {
  activeTab.value = tab;
  pagination.value.current_page = 1;
  fetchReports();
};
const changePage = (p) => {
  if (p >= 1 && p <= pagination.value.last_page) {
    pagination.value.current_page = p;
    fetchReports();
  }
};
let searchTimeout = null;
const onSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.value.current_page = 1;
    fetchReports();
  }, 400);
};

// ── Computed ──────────────────────────────────────────
const visiblePages = computed(() => {
  const total = pagination.value.last_page,
    cur = pagination.value.current_page;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 4) return [1, 2, 3, 4, 5];
  if (cur >= total - 3)
    return [total - 4, total - 3, total - 2, total - 1, total];
  return [cur - 2, cur - 1, cur, cur + 1, cur + 2];
});

// ── Formatters ────────────────────────────────────────
const truncate = (t, n = 60) => (t.length > n ? t.slice(0, n) + "…" : t);
const formatDate = (d) =>
  d
    ? new Date(d).toLocaleDateString("en-PH", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      })
    : "—";
const formatReason = (r) =>
  ({
    counterfeit: "Counterfeit",
    misleading: "Misleading",
    inappropriate: "Inappropriate",
    prohibited: "Prohibited",
    spam: "Spam",
    other: "Other",
  })[r] ?? r;
const formatReportStatus = (s) =>
  ({
    pending: "Pending Review",
    approved: "Product Banned",
    rejected: "Dismissed",
  })[s] ?? s;
const formatProductStatus = (s) =>
  ({
    active: "Active",
    inactive: "Inactive",
    draft: "Draft",
    banned: "Banned",
  })[s] ??
  s ??
  "—";
const formatCurrency = (v) => (v ? parseFloat(v).toFixed(2) : "0.00");
const formatFileSize = (b) => {
  if (!b) return "0 B";
  const k = 1024,
    sz = ["B", "KB", "MB", "GB"],
    i = Math.floor(Math.log(b) / Math.log(k));
  return Math.round((b / Math.pow(k, i)) * 100) / 100 + " " + sz[i];
};

watch(activeTab, fetchReports);
</script>

<style scoped>
* {
  font-family:
    "Poppins",
    -apple-system,
    sans-serif;
  box-sizing: border-box;
}

.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f5f7fa;
}
.main-content {
  margin-left: 260px;
  flex: 1;
  padding: 28px;
}

/* Header */
.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
}

.mobile-toggle:hover {
  background: #f1f5f9;
  color: #1e293b;
}

@media (max-width: 968px) {
  .mobile-toggle {
    display: flex;
  }
}
.page-title {
  font-size: 26px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}
.page-subtitle {
  font-size: 14px;
  color: #718096;
  margin: 2px 0 0;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 14px;
}
.search-box {
  position: relative;
  display: flex;
  align-items: center;
}
.search-box input {
  width: 280px;
  padding: 10px 14px 10px 38px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 14px;
  background: white;
  font-family: inherit;
  transition: all 0.2s;
}
.search-box input:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}
.search-icon {
  position: absolute;
  left: 12px;
  font-size: 15px;
  color: #94a3b8;
  pointer-events: none;
}
.user-profile {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-left: 14px;
  border-left: 1px solid #e2e8f0;
}
.profile-img {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  object-fit: cover;
}
.profile-info {
  display: flex;
  flex-direction: column;
}
.profile-name {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
}
.profile-role {
  font-size: 11px;
  color: #48bb78;
  font-weight: 600;
}

/* Stats row */
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}
.stat-pill {
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  transition: all 0.2s;
}
.stat-pill:hover {
  border-color: #48bb78;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.12);
}
.stat-pill.active {
  border-color: #48bb78;
  background: #f0fff4;
  box-shadow: 0 2px 10px rgba(72, 187, 120, 0.15);
}
.stat-pill-icon {
  font-size: 24px;
}
.stat-pill-num {
  display: block;
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  line-height: 1;
}
.stat-pill-label {
  display: block;
  font-size: 12px;
  color: #718096;
  margin-top: 2px;
}

/* Content body */
.content-body {
  background: white;
  border-radius: 14px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

/* State box */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 24px;
  color: #718096;
}
.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}
.state-box h3 {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
  margin: 0 0 6px;
}
.state-box p {
  font-size: 14px;
  margin: 0;
}
.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f0f0f0;
  border-top-color: #48bb78;
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
  margin-bottom: 16px;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Table */
.table-wrap {
  overflow-x: auto;
}
.reports-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.reports-table thead tr {
  background: #f8fafc;
  border-bottom: 1.5px solid #e2e8f0;
}
.reports-table th {
  padding: 14px 16px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.th-actions {
  text-align: right;
}
.report-row {
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.15s;
}
.report-row:hover {
  background: #f8fafc;
}
.report-row td {
  padding: 14px 16px;
  vertical-align: middle;
}
.td-actions {
  text-align: right;
  white-space: nowrap;
}
.cell-product {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 180px;
}
.product-thumb {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  overflow: hidden;
  background: #f7fafc;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
}
.product-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.thumb-fallback {
  font-size: 20px;
}
.product-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.product-name {
  font-size: 13px;
  font-weight: 600;
  color: #1a202c;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 160px;
}
.product-status-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 700;
}
.ps-active {
  background: #d1fae5;
  color: #065f46;
}
.ps-inactive {
  background: #e2e8f0;
  color: #475569;
}
.ps-draft {
  background: #fef3c7;
  color: #92400e;
}
.ps-banned {
  background: #fee2e2;
  color: #991b1b;
}
.cell-muted {
  font-size: 13px;
  color: #718096;
}
.cell-reporter {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.reporter-name {
  font-size: 13px;
  font-weight: 500;
  color: #2d3748;
}
.reporter-email {
  font-size: 11.5px;
  color: #94a3b8;
}
.cell-desc {
  font-size: 12.5px;
  color: #64748b;
  font-style: italic;
}
.cell-date {
  font-size: 12px;
  color: #94a3b8;
  white-space: nowrap;
}
.reason-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}
.reason-counterfeit {
  background: #fef3c7;
  color: #92400e;
}
.reason-misleading {
  background: #ede9fe;
  color: #5b21b6;
}
.reason-inappropriate {
  background: #fee2e2;
  color: #991b1b;
}
.reason-prohibited {
  background: #ffedd5;
  color: #9a3412;
}
.reason-spam {
  background: #e0e7ff;
  color: #3730a3;
}
.reason-other {
  background: #f1f5f9;
  color: #475569;
}
.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 700;
  white-space: nowrap;
}
.rs-pending {
  background: #fef3c7;
  color: #92400e;
}
.rs-approved {
  background: #fee2e2;
  color: #991b1b;
}
.rs-rejected {
  background: #d1fae5;
  color: #065f46;
}
.btn-ban,
.btn-dismiss {
  padding: 6px 12px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  font-family: inherit;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.btn-ban {
  background: #fee2e2;
  color: #991b1b;
  margin-right: 6px;
}
.btn-ban:hover:not(:disabled) {
  background: #fecaca;
}
.btn-dismiss {
  background: #d1fae5;
  color: #065f46;
}
.btn-dismiss:hover:not(:disabled) {
  background: #a7f3d0;
}
.btn-ban:disabled,
.btn-dismiss:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.reviewed-by {
  font-size: 12px;
  color: #94a3b8;
  font-style: italic;
}
.btn-spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid rgba(0, 0, 0, 0.15);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
.btn-spinner.white {
  border-color: rgba(255, 255, 255, 0.3);
  border-top-color: white;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
  border-top: 1px solid #f1f5f9;
}
.page-btn {
  padding: 7px 12px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  font-size: 13.5px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  min-width: 36px;
}
.page-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
}
.page-btn.active {
  background: #48bb78;
  color: white;
  border-color: #48bb78;
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.admin-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #48bb78, #2f855a);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.admin-avatar svg {
  width: 18px;
  height: 18px;
  color: white;
  stroke: white;
}

/* ══ Modal shell ══ */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.modal-card {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 1020px;
  max-height: 95vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
  border-radius: 16px;
  overflow: visible;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 28px;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.modal-header h2 {
  font-size: 20px;
  font-weight: 700;
  color: #1a202c;
  margin: 0;
}
.btn-close {
  width: 34px;
  height: 34px;
  background: #f7fafc;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  font-size: 16px;
  color: #718096;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn-close:hover {
  background: #fee2e2;
  color: #e53e3e;
}
.modal-body {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  padding: 0;
}

/* ══ Two-column grid ══
   Left  (40 %) : photos / 3D model only — sticky so it stays visible while scrolling
   Right (60 %) : ALL info sections, scrollable
*/
.modal-grid {
  display: grid;
  grid-template-columns: 40% 60%;
  align-items: start;
}

/* ── LEFT column ── */
.modal-left {
  position: relative;
  padding: 24px 20px 24px 28px;
  border-right: 1px solid #f0f4f8;
  background: #fafbfc;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* View tabs */
.view-tabs {
  display: flex;
  gap: 8px;
}
.view-tab {
  padding: 7px 18px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  color: #718096;
  font-family: inherit;
}
.view-tab.active {
  background: #48bb78;
  color: white;
  border-color: #48bb78;
}
.view-tab:hover:not(.active) {
  border-color: #48bb78;
  color: #48bb78;
}

/* Image panel */
.image-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.modal-product-img {
  position: relative;
  width: 100%;
  aspect-ratio: 4/3;
  border-radius: 12px;
  overflow: hidden;
  background: #f0f4f8;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
}
.modal-product-img.clickable {
  cursor: zoom-in;
}
.modal-product-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.25s;
}
.modal-product-img.clickable:hover img {
  transform: scale(1.04);
}
.modal-img-fallback {
  font-size: 52px;
  color: #cbd5e0;
}

/* Zoom hint */
.img-zoom-hint {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(transparent, rgba(0, 0, 0, 0.42));
  color: white;
  font-size: 11.5px;
  font-weight: 600;
  text-align: center;
  padding: 22px 8px 8px;
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}
.modal-product-img.clickable:hover .img-zoom-hint {
  opacity: 1;
}

/* Thumbnails */
.thumbnail-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.thumb-item {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2.5px solid transparent;
  transition: all 0.2s;
  flex-shrink: 0;
  background: #f0f4f8;
}
.thumb-item.active {
  border-color: #48bb78;
}
.thumb-item:hover {
  border-color: #94a3b8;
}
.thumb-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* No image note */
.no-image-note {
  text-align: center;
  font-size: 13px;
  color: #94a3b8;
  padding: 16px 0;
}

/* 3D viewer */
.model-viewer-wrap {
  width: 100%;
  height: 420px;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
}

:deep(.model-viewer-container) {
  min-height: 420px !important;
  height: 420px !important;
  border-radius: 12px;
}

.model-meta {
  display: flex;
  align-items: center;
  gap: 10px;
}
.model-type-badge {
  padding: 3px 10px;
  background: #7c3aed;
  color: white;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}
.model-size {
  font-size: 12px;
  color: #718096;
}

/* ── RIGHT column ── */
.modal-right {
  padding: 24px 28px 24px 24px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* Section divider */
.section-divider {
  height: 1px;
  background: #f0f4f8;
  margin: 4px 0 16px;
}

/* Section title row (title + badge side by side) */
.section-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.section-title-row h4 {
  margin: 0;
}

.modal-section {
  margin-bottom: 16px;
}
.modal-section h4 {
  font-size: 11.5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 10px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 7px 0;
  border-bottom: 1px solid #f8fafc;
  font-size: 13.5px;
}
.info-row label {
  color: #94a3b8;
  font-weight: 500;
  flex-shrink: 0;
  margin-right: 12px;
  font-size: 13px;
}
.info-row span {
  color: #1a202c;
  font-weight: 500;
  text-align: right;
}
.info-row--col {
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.price-val {
  color: #48bb78 !important;
  font-size: 15px !important;
  font-weight: 700 !important;
}
.sku-val {
  color: #718096 !important;
  font-size: 12px !important;
  font-family: monospace;
}

.report-desc {
  font-size: 13px;
  color: #4a5568;
  background: #f8fafc;
  border-radius: 8px;
  padding: 10px 12px;
  margin: 0;
  line-height: 1.6;
  border: 1px solid #e2e8f0;
  width: 100%;
}

/* Warning */
.warn-box {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  border-left: 4px solid #f59e0b;
  margin-top: 8px;
}
.warn-icon {
  font-size: 20px;
  flex-shrink: 0;
  margin-top: 1px;
}
.warn-box strong {
  display: block;
  font-size: 13px;
  color: #92400e;
  margin-bottom: 4px;
}
.warn-box p {
  font-size: 12.5px;
  color: #b45309;
  margin: 0;
  line-height: 1.5;
}

.text-danger {
  color: #e53e3e !important;
  font-weight: 600;
}
.text-success {
  color: #48bb78 !important;
  font-weight: 600;
}

/* Modal footer */
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 28px;
  border-top: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.btn-cancel {
  padding: 10px 22px;
  background: white;
  color: #4a5568;
  border: 1.5px solid #e2e8f0;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}
.btn-cancel:hover {
  background: #f7fafc;
}
.btn-dismiss-large {
  padding: 10px 24px;
  background: #d1fae5;
  color: #065f46;
  border: none;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 150px;
  justify-content: center;
}
.btn-dismiss-large:hover:not(:disabled) {
  background: #a7f3d0;
}
.btn-ban-large {
  padding: 10px 24px;
  background: #e53e3e;
  color: white;
  border: none;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 150px;
  justify-content: center;
}
.btn-ban-large:hover:not(:disabled) {
  background: #c53030;
  transform: translateY(-1px);
}
.btn-ban-large:disabled,
.btn-dismiss-large:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Modal transitions */
.modal-fade-enter-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-fade-leave-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

/* ══ Lightbox ══ */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.92);
  z-index: 99999;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: zoom-out;
  padding: 20px;
}
.lightbox-close {
  position: fixed;
  top: 20px;
  right: 24px;
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.12);
  border: none;
  border-radius: 50%;
  color: white;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
  z-index: 1;
}
.lightbox-close:hover {
  background: rgba(255, 255, 255, 0.25);
}
.lightbox-img-wrap {
  max-width: 90vw;
  max-height: 85vh;
  cursor: default;
}
.lightbox-img {
  max-width: 100%;
  max-height: 85vh;
  border-radius: 8px;
  object-fit: contain;
  display: block;
}
.lightbox-hint {
  color: rgba(255, 255, 255, 0.4);
  font-size: 12px;
  margin-top: 14px;
}
.lightbox-fade-enter-active {
  transition: opacity 0.18s ease;
}
.lightbox-fade-leave-active {
  transition: opacity 0.15s ease;
}
.lightbox-fade-enter-from,
.lightbox-fade-leave-to {
  opacity: 0;
}

/* Responsive */
@media (max-width: 1100px) {
  .main-content {
    margin-left: 0;
  }
}
@media (max-width: 900px) {
  .stats-row {
    grid-template-columns: repeat(2, 1fr);
  }
  .modal-grid {
    grid-template-columns: 1fr;
  }
  .modal-left {
    position: static;
    border-right: none;
    border-bottom: 1px solid #f0f4f8;
  }
}
@media (max-width: 640px) {
  .content-header {
    flex-direction: row;
    align-items: center;
    gap: 12px;
  }
  .stats-row {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
