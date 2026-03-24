<template>
  <div class="pr-wrap">
    <!-- ── Rating Summary ──────────────────────────────────────────────────── -->
    <div class="pr-summary" v-if="summary">
      <div class="pr-summary__score">
        <span class="pr-big-num">{{ summary.average_rating?.toFixed(1) }}</span>
        <div class="pr-summary__stars">
          <span
            v-for="n in 5"
            :key="n"
            class="pr-star"
            :class="starClass(n, summary.average_rating)"
            >★</span
          >
        </div>
        <span class="pr-total-lbl"
          >{{ summary.total_reviews }} review{{
            summary.total_reviews !== 1 ? "s" : ""
          }}</span
        >
        <!-- sold count (vendor view) -->
        <div v-if="soldCount !== null" class="pr-sold-badge">
          <svg viewBox="0 0 16 16" fill="none" class="pr-sold-ico">
            <path
              d="M2 2h2l2.4 9h6l1.6-6H6"
              stroke="currentColor"
              stroke-width="1.4"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <circle cx="7" cy="13" r="1" fill="currentColor" />
            <circle cx="13" cy="13" r="1" fill="currentColor" />
          </svg>
          {{ soldCount }} sold
        </div>
      </div>

      <!-- Breakdown bars -->
      <div class="pr-breakdown" v-if="summary.breakdown">
        <div v-for="n in [5, 4, 3, 2, 1]" :key="n" class="pr-bar-row">
          <span class="pr-bar-label">{{ n }}★</span>
          <div class="pr-bar-track">
            <div
              class="pr-bar-fill"
              :style="{ width: (summary.breakdown[n]?.percentage ?? 0) + '%' }"
            ></div>
          </div>
          <span class="pr-bar-count">{{
            summary.breakdown[n]?.count ?? 0
          }}</span>
        </div>
      </div>
    </div>

    <!-- ── Skeleton ──────────────────────────────────────────────────────────── -->
    <div v-if="loading" class="pr-skels">
      <div v-for="n in 3" :key="n" class="pr-skel">
        <div class="pr-skel__head">
          <div class="pr-skel__avatar"></div>
          <div class="pr-skel__lines">
            <div class="pr-skel__line pr-skel__line--name"></div>
            <div class="pr-skel__line pr-skel__line--date"></div>
          </div>
        </div>
        <div class="pr-skel__line pr-skel__line--stars"></div>
        <div class="pr-skel__line"></div>
        <div class="pr-skel__line pr-skel__line--short"></div>
      </div>
    </div>

    <!-- ── Empty ─────────────────────────────────────────────────────────────── -->
    <div v-else-if="!reviews.length" class="pr-empty">
      <svg viewBox="0 0 48 48" fill="none" class="pr-empty-ico">
        <circle cx="24" cy="24" r="20" stroke="#e2e8f0" stroke-width="2" />
        <path
          d="M24 16v8M24 28h.01"
          stroke="#a0aec0"
          stroke-width="2.5"
          stroke-linecap="round"
        />
      </svg>
      <p>No reviews yet. Be the first to review!</p>
    </div>

    <!-- ── Sort + Review list ────────────────────────────────────────────────── -->
    <template v-else>
      <div class="pr-toolbar">
        <span class="pr-toolbar-count"
          >{{ meta.total }} review{{ meta.total !== 1 ? "s" : "" }}</span
        >
        <select v-model="sortBy" @change="load(1)" class="pr-sort">
          <option value="newest">Newest</option>
          <option value="oldest">Oldest</option>
          <option value="highest">Highest rated</option>
          <option value="lowest">Lowest rated</option>
        </select>
      </div>

      <div class="pr-list">
        <div v-for="review in reviews" :key="review.id" class="pr-item">
          <!-- Avatar + name -->
          <div class="pr-item__head">
            <div class="pr-avatar">
              {{ avatarInitial(review.display_name) }}
            </div>
            <div class="pr-item__meta">
              <div class="pr-item__name-row">
                <span class="pr-item__name">{{ review.display_name }}</span>
                <span v-if="review.is_anonymous" class="pr-anon-badge"
                  >🕶 Anonymous</span
                >
                <span v-if="review.is_mine" class="pr-mine-badge"
                  >Your review</span
                >
              </div>
              <span class="pr-item__date">{{
                fmtDate(review.created_at)
              }}</span>
            </div>
            <!-- Edit / Delete (own reviews) -->
            <div v-if="review.is_mine" class="pr-item__actions">
              <button
                class="pr-action-btn"
                title="Edit"
                @click="startEdit(review)"
              >
                <svg viewBox="0 0 16 16" fill="none">
                  <path
                    d="M11 2l3 3L5 14H2v-3L11 2z"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
              <button
                class="pr-action-btn pr-action-btn--del"
                title="Delete"
                @click="deleteReview(review)"
              >
                <svg viewBox="0 0 16 16" fill="none">
                  <path
                    d="M3 4h10M6 4V3h4v1M5 4v8h6V4"
                    stroke="currentColor"
                    stroke-width="1.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
          </div>

          <!-- Stars -->
          <div class="pr-item__stars">
            <span
              v-for="n in 5"
              :key="n"
              class="pr-star-sm"
              :class="{ lit: n <= review.rating }"
              >★</span
            >
          </div>

          <!-- Comment -->
          <p v-if="review.comment" class="pr-item__comment">
            {{ review.comment }}
          </p>

          <!-- Image -->
          <div
            v-if="review.image_url"
            class="pr-item__img-wrap"
            @click="openImage(review.image_url)"
          >
            <img
              :src="review.image_url"
              alt="Review photo"
              class="pr-item__img"
            />
            <div class="pr-item__img-overlay">🔍</div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="pr-pager">
        <button
          class="pr-pager-btn"
          :disabled="meta.current_page === 1"
          @click="load(meta.current_page - 1)"
        >
          ← Prev
        </button>
        <span class="pr-pager-info"
          >{{ meta.current_page }} / {{ meta.last_page }}</span
        >
        <button
          class="pr-pager-btn"
          :disabled="meta.current_page === meta.last_page"
          @click="load(meta.current_page + 1)"
        >
          Next →
        </button>
      </div>
    </template>

    <!-- ── Edit modal ────────────────────────────────────────────────────────── -->
    <Transition name="pr-modal">
      <div v-if="editModal.show" class="pr-overlay" @click.self="closeEdit">
        <div class="pr-edit-modal">
          <div class="pr-edit-modal__head">
            <h3>Edit Review</h3>
            <button class="pr-edit-close" @click="closeEdit">
              <svg viewBox="0 0 20 20" fill="none">
                <path
                  d="M5 5l10 10M15 5l-10 10"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                />
              </svg>
            </button>
          </div>
          <div class="pr-edit-body">
            <!-- Stars -->
            <div class="pr-edit-field">
              <label class="pr-edit-lbl">Rating</label>
              <div class="pr-stars-edit">
                <button
                  v-for="n in 5"
                  :key="n"
                  type="button"
                  class="rv-star"
                  :class="{
                    lit: n <= (editModal.hoverStar || editModal.rating),
                  }"
                  @mouseenter="editModal.hoverStar = n"
                  @mouseleave="editModal.hoverStar = 0"
                  @click="editModal.rating = n"
                >
                  ★
                </button>
              </div>
            </div>
            <!-- Comment -->
            <div class="pr-edit-field">
              <label class="pr-edit-lbl">Review</label>
              <textarea
                v-model="editModal.comment"
                rows="4"
                class="pr-edit-textarea"
                placeholder="Update your review…"
              ></textarea>
            </div>
            <!-- Anonymous -->
            <label class="pr-edit-anon">
              <input type="checkbox" v-model="editModal.is_anonymous" />
              <span class="pr-edit-anon__lbl">Post anonymously</span>
            </label>
          </div>
          <div class="pr-edit-footer">
            <button
              class="pr-btn pr-btn--ghost"
              @click="closeEdit"
              :disabled="editModal.saving"
            >
              Cancel
            </button>
            <button
              class="pr-btn pr-btn--green"
              @click="saveEdit"
              :disabled="editModal.saving || !editModal.rating"
            >
              {{ editModal.saving ? "Saving…" : "Save Changes" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Image lightbox ────────────────────────────────────────────────────── -->
    <Transition name="pr-modal">
      <div
        v-if="lightbox"
        class="pr-overlay pr-overlay--dark"
        @click="lightbox = null"
      >
        <img :src="lightbox" alt="Review photo" class="pr-lightbox-img" />
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from "vue";
import axios from "../../plugins/axios";

const props = defineProps({
  productId: { type: Number, required: true },
  /** Pass sold count from vendor view; leave null for public view */
  soldCount: { type: Number, default: null },
  /** vendor=true fetches from /vendor/products/{id}/reviews */
  vendor: { type: Boolean, default: false },
});

const emit = defineEmits(["updated"]);

const reviews = ref([]);
const summary = ref(null);
const loading = ref(false);
const sortBy = ref("newest");
const lightbox = ref(null);
const meta = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 10,
});

const editModal = reactive({
  show: false,
  id: null,
  rating: 0,
  hoverStar: 0,
  comment: "",
  is_anonymous: false,
  saving: false,
});

function authHeaders() {
  const token =
    localStorage.getItem("token") || localStorage.getItem("auth_token");
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function load(page = 1) {
  loading.value = true;
  try {
    const endpoint = props.vendor
      ? `vendor/products/${props.productId}/reviews`
      : `products/${props.productId}/reviews`;

    const res = await axios.get(endpoint, {
      params: { page, per_page: meta.per_page, sort: sortBy.value },
      headers: authHeaders(),
    });
    const body = res?.data ?? res;

    reviews.value = Array.isArray(body?.data) ? body.data : [];
    if (body?.summary) summary.value = body.summary;
    if (body?.meta) Object.assign(meta, body.meta);
  } catch (e) {
    console.error("[ProductReviews]", e);
  } finally {
    loading.value = false;
  }
}

async function loadSummary() {
  if (summary.value) return; // already loaded with reviews
  try {
    const res = await axios.get(`products/${props.productId}/reviews/summary`);
    const body = res?.data ?? res;
    if (body?.data) summary.value = body.data;
  } catch (e) {
    /* non-fatal */
  }
}

// ── Edit ──────────────────────────────────────────────────────────────────────
function startEdit(review) {
  Object.assign(editModal, {
    show: true,
    id: review.id,
    rating: review.rating,
    hoverStar: 0,
    comment: review.comment ?? "",
    is_anonymous: review.is_anonymous,
    saving: false,
  });
}
function closeEdit() {
  editModal.show = false;
}

async function saveEdit() {
  editModal.saving = true;
  try {
    await axios.put(
      `reviews/${editModal.id}`,
      {
        rating: editModal.rating,
        comment: editModal.comment,
        is_anonymous: editModal.is_anonymous,
      },
      { headers: authHeaders() },
    );
    closeEdit();
    await load(meta.current_page);
    emit("updated");
  } catch (e) {
    alert(e.response?.data?.message ?? "Update failed.");
  } finally {
    editModal.saving = false;
  }
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function deleteReview(review) {
  if (!confirm("Delete your review? This cannot be undone.")) return;
  try {
    await axios.delete(`reviews/${review.id}`, { headers: authHeaders() });
    await load(meta.current_page);
    emit("updated");
  } catch (e) {
    alert(e.response?.data?.message ?? "Delete failed.");
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function avatarInitial(name) {
  return name?.[0]?.toUpperCase() ?? "?";
}
function fmtDate(iso) {
  return iso
    ? new Date(iso).toLocaleDateString("en-PH", {
        year: "numeric",
        month: "short",
        day: "numeric",
      })
    : "—";
}
function openImage(url) {
  lightbox.value = url;
}

function starClass(n, avg) {
  if (n <= Math.floor(avg)) return "pr-star--full";
  if (n - avg < 1) return "pr-star--half";
  return "pr-star--empty";
}

onMounted(async () => {
  await load();
  await loadSummary();
});
watch(
  () => props.productId,
  () => load(1),
);
</script>

<style scoped>
.pr-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: "Poppins", sans-serif;
}

/* ── Summary ─────────────────────────────────────────────────────────────── */
.pr-summary {
  display: flex;
  gap: 24px;
  padding: 20px;
  background: #fff;
  border: 1px solid #e8edf2;
  border-radius: 12px;
  flex-wrap: wrap;
}
.pr-summary__score {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 100px;
}
.pr-big-num {
  font-size: 2.8rem;
  font-weight: 800;
  color: #1a202c;
  line-height: 1;
}
.pr-summary__stars {
  display: flex;
  gap: 2px;
}
.pr-star {
  font-size: 1rem;
}
.pr-star--full {
  color: #f6ad55;
}
.pr-star--half {
  color: #f6ad55;
  opacity: 0.5;
}
.pr-star--empty {
  color: #e2e8f0;
}
.pr-total-lbl {
  font-size: 0.75rem;
  color: #718096;
}
.pr-sold-badge {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  background: #f0fff4;
  border: 1px solid #c6f6d5;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #276749;
  margin-top: 4px;
}
.pr-sold-ico {
  width: 13px;
  height: 13px;
  color: #276749;
}

.pr-breakdown {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 7px;
  justify-content: center;
  min-width: 160px;
}
.pr-bar-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pr-bar-label {
  font-size: 0.75rem;
  color: #718096;
  width: 22px;
  text-align: right;
  flex-shrink: 0;
}
.pr-bar-track {
  flex: 1;
  height: 8px;
  background: #f0f2f5;
  border-radius: 4px;
  overflow: hidden;
}
.pr-bar-fill {
  height: 100%;
  background: #f6ad55;
  border-radius: 4px;
  transition: width 0.3s;
}
.pr-bar-count {
  font-size: 0.72rem;
  color: #a0aec0;
  width: 22px;
  flex-shrink: 0;
}

/* ── Skeleton ────────────────────────────────────────────────────────────── */
.pr-skels {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.pr-skel {
  padding: 16px;
  background: #fff;
  border: 1px solid #e8edf2;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pr-skel__head {
  display: flex;
  gap: 10px;
}
.pr-skel__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #f0f2f5;
  flex-shrink: 0;
}
.pr-skel__lines {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.pr-skel__line {
  height: 10px;
  border-radius: 5px;
  background: linear-gradient(90deg, #f0f2f5 25%, #e2e8f0 50%, #f0f2f5 75%);
  background-size: 200%;
  animation: shimmer 1.4s infinite;
}
.pr-skel__line--name {
  width: 40%;
}
.pr-skel__line--date {
  width: 25%;
}
.pr-skel__line--stars {
  width: 80px;
}
.pr-skel__line--short {
  width: 55%;
}
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* ── Empty ───────────────────────────────────────────────────────────────── */
.pr-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 40px 20px;
  text-align: center;
}
.pr-empty-ico {
  width: 48px;
  height: 48px;
  opacity: 0.6;
}
.pr-empty p {
  font-size: 0.875rem;
  color: #718096;
  margin: 0;
}

/* ── Toolbar ─────────────────────────────────────────────────────────────── */
.pr-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.pr-toolbar-count {
  font-size: 0.8rem;
  color: #718096;
}
.pr-sort {
  padding: 6px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  font-family: inherit;
  font-size: 0.8rem;
  color: #2d3748;
  background: #fff;
  outline: none;
  cursor: pointer;
}
.pr-sort:focus {
  border-color: #48bb78;
}

/* ── Review list ─────────────────────────────────────────────────────────── */
.pr-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pr-item {
  padding: 16px;
  background: #fff;
  border: 1px solid #e8edf2;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: box-shadow 0.2s;
}
.pr-item:hover {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.pr-item__head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.pr-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #48bb78, #276749);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.875rem;
  flex-shrink: 0;
}
.pr-item__meta {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pr-item__name-row {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.pr-item__name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #2d3748;
}
.pr-item__date {
  font-size: 0.72rem;
  color: #a0aec0;
}
.pr-anon-badge {
  font-size: 0.68rem;
  padding: 2px 7px;
  background: #f7f8fa;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  color: #718096;
}
.pr-mine-badge {
  font-size: 0.68rem;
  padding: 2px 7px;
  background: #f0fff4;
  border: 1px solid #c6f6d5;
  border-radius: 20px;
  color: #276749;
  font-weight: 600;
}

.pr-item__actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}
.pr-action-btn {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #718096;
  transition: all 0.15s;
}
.pr-action-btn:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}
.pr-action-btn--del:hover {
  border-color: #fc8181;
  color: #e53e3e;
  background: #fff5f5;
}
.pr-action-btn svg {
  width: 13px;
  height: 13px;
}

.pr-item__stars {
  display: flex;
  gap: 2px;
}
.pr-star-sm {
  font-size: 1rem;
  color: #e2e8f0;
}
.pr-star-sm.lit {
  color: #f6ad55;
}

.pr-item__comment {
  font-size: 0.875rem;
  color: #4a5568;
  line-height: 1.6;
  margin: 0;
}

.pr-item__img-wrap {
  position: relative;
  cursor: pointer;
  width: fit-content;
}
.pr-item__img {
  max-width: 180px;
  max-height: 130px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  display: block;
}
.pr-item__img-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
  font-size: 1.2rem;
}
.pr-item__img-wrap:hover .pr-item__img-overlay {
  opacity: 1;
}

/* ── Pagination ──────────────────────────────────────────────────────────── */
.pr-pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
}
.pr-pager-info {
  font-size: 0.875rem;
  color: #718096;
}
.pr-pager-btn {
  padding: 8px 16px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 7px;
  font-family: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.15s;
}
.pr-pager-btn:hover:not(:disabled) {
  border-color: #48bb78;
  color: #48bb78;
}
.pr-pager-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── Overlay & Edit modal ────────────────────────────────────────────────── */
.pr-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 400;
  padding: 20px;
}
.pr-overlay--dark {
  background: rgba(0, 0, 0, 0.8);
}
.pr-lightbox-img {
  max-width: 90vw;
  max-height: 88vh;
  border-radius: 10px;
  object-fit: contain;
}

.pr-edit-modal {
  background: #fff;
  border-radius: 14px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.16);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.pr-edit-modal__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f0f2f5;
}
.pr-edit-modal__head h3 {
  font-size: 1rem;
  font-weight: 700;
  margin: 0;
  color: #1a202c;
}
.pr-edit-close {
  background: none;
  border: none;
  cursor: pointer;
  color: #a0aec0;
  padding: 4px;
}
.pr-edit-close svg {
  width: 16px;
  height: 16px;
}
.pr-edit-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.pr-edit-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.pr-edit-lbl {
  font-size: 0.8rem;
  font-weight: 600;
  color: #2d3748;
}
.pr-stars-edit {
  display: flex;
  gap: 4px;
}
.rv-star {
  font-size: 2rem;
  line-height: 1;
  background: none;
  border: none;
  cursor: pointer;
  color: #e2e8f0;
  transition:
    color 0.12s,
    transform 0.1s;
  padding: 0;
}
.rv-star.lit {
  color: #f6ad55;
}
.rv-star:hover {
  transform: scale(1.12);
}
.pr-edit-textarea {
  width: 100%;
  padding: 9px 12px;
  background: #f7f8fa;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.875rem;
  color: #2d3748;
  resize: vertical;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.pr-edit-textarea:focus {
  border-color: #48bb78;
  background: #fff;
}
.pr-edit-anon {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  color: #4a5568;
  cursor: pointer;
}
.pr-edit-anon__lbl {
  font-weight: 500;
}
.pr-edit-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 20px;
  border-top: 1px solid #f0f2f5;
}

.pr-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.18s;
}
.pr-btn--green {
  background: #48bb78;
  color: #fff;
}
.pr-btn--green:hover:not(:disabled) {
  background: #38a169;
}
.pr-btn--ghost {
  background: transparent;
  color: #718096;
  border: 1.5px solid #e2e8f0;
}
.pr-btn--ghost:hover:not(:disabled) {
  background: #f7f8fa;
}
.pr-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pr-modal-enter-active,
.pr-modal-leave-active {
  transition:
    opacity 0.2s,
    transform 0.22s;
}
.pr-modal-enter-from,
.pr-modal-leave-to {
  opacity: 0;
  transform: scale(0.97);
}
</style>
