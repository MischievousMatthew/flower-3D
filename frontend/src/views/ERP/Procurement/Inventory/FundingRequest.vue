<template>
  <HrPage title="Funding requests" subtitle="Ask Finance for money to restock. Draft a request, send it for approval, then turn approved ones into purchase orders.">
    <template #actions>
      <button
        type="button"
        class="sp-btn sp-btn-primary"
        :disabled="!canCreateFunding"
        :title="canCreateFunding ? '' : permissionMessages.create"
        @click="goToCreate"
      >
        <span v-html="ICONS.plus"></span>New request
      </button>
    </template>

    <!-- Stages: also the filter -->
    <section class="fr-stages" aria-label="Request stages">
      <template v-if="loading && !requests.length">
        <div v-for="n in 5" :key="n" class="sp-card fr-stage"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton fr-sk-num"></div></div>
      </template>
      <template v-else>
        <button
          v-for="s in STAGES"
          :key="s.value"
          type="button"
          class="sp-card fr-stage"
          :class="[`t-${s.tone}`, { on: activeStatusTab === s.value }]"
          :aria-pressed="activeStatusTab === s.value"
          @click="activeStatusTab = s.value"
        >
          <span class="fr-stage-label"><span v-if="s.value !== 'all'" class="fr-dot"></span>{{ s.label }}</span>
          <strong>{{ getStatusCount(s.value) }}</strong>
          <small v-if="s.value === 'Approved' && approvedTotal > 0">{{ money(approvedTotal) }} approved</small>
          <small v-else-if="s.value === 'Pending' && pendingTotal > 0">{{ money(pendingTotal) }} waiting</small>
        </button>
      </template>
    </section>

    <div class="sp-card fr-bar">
      <label class="sp-search fr-search">
        <span v-html="ICONS.search"></span>
        <input v-model="searchQuery" class="sp-input" type="search" placeholder="Request number, item or who asked" aria-label="Search funding requests" />
      </label>
      <select v-model="urgency" class="sp-select fr-select" aria-label="Urgency">
        <option value="">Any urgency</option>
        <option v-for="u in URGENCIES" :key="u" :value="u">{{ u }}</option>
      </select>
      <button v-if="filtered" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="clearFilters">Clear</button>
    </div>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="fetchRequests">Try again</button>
    </div>

    <section class="sp-card fr-card" aria-label="Funding requests">
      <div v-if="loading && !requests.length" class="fr-rows">
        <div v-for="n in 5" :key="n" class="fr-row sk"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
      </div>

      <div v-else-if="!filteredRequests.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.wallet"></span>
        <h3>{{ filtered ? "No requests match" : "No funding requests yet" }}</h3>
        <p>{{ filtered ? "Try another stage, urgency or search." : "When stock runs low, ask Finance for the money to restock. Requests can wait as drafts until you're ready to send them." }}</p>
        <button v-if="filtered" type="button" class="sp-btn sp-btn-ghost" @click="clearFilters">Clear filters</button>
        <button v-else-if="canCreateFunding" type="button" class="sp-btn sp-btn-primary" @click="goToCreate"><span v-html="ICONS.plus"></span>New request</button>
      </div>

      <template v-else>
        <div class="fr-head" aria-hidden="true">
          <span>Request</span><span>Item</span><span class="num">Estimated cost</span><span>Finance</span><span>Status</span><span></span>
        </div>
        <ul class="fr-rows" :class="{ dim: loading }">
          <li v-for="r in filteredRequests" :key="r.id" class="fr-row" :class="{ selected: selectedRequestId === r.id }" @click="viewRequest(r.id)">
            <span class="fr-id">
              <router-link :to="{ name: 'FundingRequestDetails', params: { id: r.id } }" class="fr-link" @click.stop="selectedRequestId = r.id">
                {{ r.finance_request_id }}
              </router-link>
              <small>{{ formatDate(r.request_date) }}<template v-if="r.submitted_by_name"> · by {{ r.submitted_by_name }}</template></small>
              <span v-if="r.urgency_level && r.urgency_level !== 'Normal'" class="sp-chip fr-urgency" :class="r.urgency_level === 'Critical' ? 'red' : 'amber'">
                <span v-if="r.urgency_level === 'Critical'" v-html="ICONS.alert"></span>{{ r.urgency_level }}
              </span>
            </span>

            <span class="fr-item">
              <b>{{ r.product_name }}</b>
              <small>{{ [r.flower_category, r.variant].filter(Boolean).join(" · ") }}</small>
              <small class="fr-qty">{{ Number(r.requested_qty || 0).toLocaleString("en-PH") }} {{ (r.uom || "units").toLowerCase() }}</small>
            </span>

            <span class="fr-amount num">
              <b v-if="Number(r.estimated_total_cost) > 0">{{ money(r.estimated_total_cost) }}</b>
              <b v-else class="fr-none">No cost yet</b>
              <small v-if="r.estimated_gross_margin !== null && r.estimated_gross_margin !== undefined" :class="marginTone(r.estimated_gross_margin)">
                {{ Number(r.estimated_gross_margin).toFixed(1) }}% margin
              </small>
            </span>

            <span class="fr-finance">
              <b>{{ r.approver_name || r.accounting_manager_name || "Not assigned" }}</b>
              <small v-if="r.finance_recommendation" class="fr-rec" :class="recommendationOf(r.finance_recommendation).tone">
                {{ recommendationOf(r.finance_recommendation).label }}
              </small>
            </span>

            <span class="fr-status">
              <span class="sp-chip" :class="statusOf(r.request_status).tone">{{ statusOf(r.request_status).label }}</span>
              <small v-if="r.request_status === 'Approved' && r.payment_status">{{ r.payment_status === "paid" ? "Paid" : "Not paid yet" }}</small>
            </span>

            <span class="fr-actions" @click.stop>
              <button
                type="button"
                class="sp-icon-btn"
                :aria-label="`Actions for ${r.finance_request_id}`"
                :aria-expanded="openMenuId === r.id"
                aria-haspopup="menu"
                @click="toggleMenu(r.id)"
              >
                <span v-html="ICONS.more"></span>
              </button>
              <div v-if="openMenuId === r.id" class="fr-menu" role="menu">
                <button type="button" role="menuitem" @click="viewRequest(r.id)"><span v-html="ICONS.eye"></span>View details</button>
                <template v-if="r.request_status === 'Draft'">
                  <button type="button" role="menuitem" :disabled="!canEditFunding" :title="canEditFunding ? '' : permissionMessages.edit" @click="editRequest(r.id)">
                    <span v-html="ICONS.edit"></span>Edit request
                  </button>
                  <button type="button" role="menuitem" :disabled="!canEditFunding" :title="canEditFunding ? '' : permissionMessages.edit" @click="askSubmit(r)">
                    <span v-html="ICONS.send"></span>Send to Finance
                  </button>
                </template>
                <button type="button" role="menuitem" class="danger" :disabled="!canDeleteFunding" :title="canDeleteFunding ? '' : permissionMessages.delete" @click="askDelete(r)">
                  <span v-html="ICONS.trash"></span>Delete
                </button>
              </div>
            </span>
          </li>
        </ul>
      </template>
    </section>

    <!-- Confirm: send to Finance / delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="confirming" class="sp-backdrop" @click="confirming = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="confirming" class="sp-modal-wrap" @keydown.esc="confirming = null">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="fr-confirm-title" aria-describedby="fr-confirm-text">
            <div class="sp-modal-head">
              <div>
                <h3 id="fr-confirm-title">{{ confirming.kind === "delete" ? "Delete this request?" : "Send to Finance?" }}</h3>
                <p id="fr-confirm-text">
                  <template v-if="confirming.kind === 'delete'">{{ confirming.request.finance_request_id }} for {{ confirming.request.product_name }} will be removed. This can't be undone.</template>
                  <template v-else>{{ confirming.request.finance_request_id }} for {{ confirming.request.product_name }} goes to {{ confirming.request.approver_name || confirming.request.accounting_manager_name || "the Finance approver" }} for review. You can't edit it while it's waiting.</template>
                </p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="confirming = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-modal-foot">
              <button ref="cancelBtn" type="button" class="sp-btn sp-btn-ghost" @click="confirming = null">Cancel</button>
              <button
                type="button"
                class="sp-btn"
                :class="confirming.kind === 'delete' ? 'sp-btn-danger' : 'sp-btn-primary'"
                :disabled="confirming.busy"
                @click="confirmAction"
              >
                {{ confirming.busy ? (confirming.kind === "delete" ? "Deleting…" : "Sending…") : confirming.kind === "delete" ? "Delete request" : "Send to Finance" }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../../../../plugins/axios";
import HrPage from "../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { money } from "../../../../components/vendor/finance/financeUi";
import { toast } from "@/utils/snackbar";
import { PERMISSION_TOOLTIPS, useAssignment } from "../../../../composables/useAssignment";
import "../../../../components/vendor/finance/finance.css";

const router = useRouter();
const { can } = useAssignment();

const searchQuery = ref("");
const activeStatusTab = ref("all");
const urgency = ref("");
const selectedRequestId = ref(null);
const openMenuId = ref(null);
const loading = ref(false);
const error = ref(null);
const requests = ref([]);
const confirming = ref(null); // { kind: "submit" | "delete", request, busy }
const cancelBtn = ref(null);
const canCreateFunding = computed(() => can("inventory_funding", "create"));
const canEditFunding = computed(() => can("inventory_funding", "edit"));
const canDeleteFunding = computed(() => can("inventory_funding", "delete"));
const permissionMessages = PERMISSION_TOOLTIPS;

// The request's stages (the values stay the ones the server uses)
const STAGES = [
  { label: "All requests", value: "all", tone: "slate" },
  { label: "Draft", value: "Draft", tone: "slate" },
  { label: "Waiting for Finance", value: "Pending", tone: "amber" },
  { label: "Approved", value: "Approved", tone: "green" },
  { label: "Rejected", value: "Rejected", tone: "red" },
];
const URGENCIES = ["Normal", "Urgent", "Critical"];
const STATUS = {
  Draft: { label: "Draft", tone: "slate" },
  Pending: { label: "Waiting for Finance", tone: "amber" },
  Approved: { label: "Approved", tone: "green" },
  Rejected: { label: "Rejected", tone: "red" },
};
const statusOf = (status) => STATUS[status] || { label: status || "Unknown", tone: "slate" };
// Finance's recommendation, in words
const RECOMMENDATION = {
  Approve: { label: "Recommends approving", tone: "green" },
  "Approve Partial": { label: "Recommends part of it", tone: "amber" },
  Reject: { label: "Recommends rejecting", tone: "red" },
};
const recommendationOf = (value) => RECOMMENDATION[value] || { label: value, tone: "" };

const filtered = computed(() => activeStatusTab.value !== "all" || !!urgency.value || !!searchQuery.value.trim());

const filteredRequests = computed(() => {
  let list = requests.value;
  if (activeStatusTab.value !== "all") list = list.filter((r) => r.request_status === activeStatusTab.value);
  if (urgency.value) list = list.filter((r) => (r.urgency_level || "Normal") === urgency.value);
  const query = searchQuery.value.trim().toLowerCase();
  if (query) {
    list = list.filter((r) =>
      [r.finance_request_id, r.product_name, r.submitted_by_name].some((value) => String(value || "").toLowerCase().includes(query)),
    );
  }
  return list;
});

const getStatusCount = (status) => (status === "all" ? requests.value.length : requests.value.filter((r) => r.request_status === status).length);
const totalOf = (status) =>
  requests.value.filter((r) => r.request_status === status).reduce((sum, r) => sum + Number(r.estimated_total_cost || 0), 0);
const approvedTotal = computed(() => totalOf("Approved"));
const pendingTotal = computed(() => totalOf("Pending"));

const marginTone = (margin) => {
  const value = Number(margin);
  if (value >= 30) return "good";
  if (value >= 20) return "ok";
  return "low";
};

const formatDate = (date) => {
  if (!date) return "No date";
  return new Date(date).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
};

function clearFilters() {
  activeStatusTab.value = "all";
  urgency.value = "";
  searchQuery.value = "";
}

const fetchRequests = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await api.get("/procurement/inventory/funding-requests");
    if (data.success) requests.value = data.data;
  } catch (err) {
    error.value = err.response?.data?.message || "We couldn't load the funding requests.";
  } finally {
    loading.value = false;
  }
};

const goToCreate = () =>
  canCreateFunding.value
    ? router.push("/erp/procurement/inventory/funding-request/create")
    : toast.error("You do not have permission to create funding requests");

// The id travels in the route params (opaque URL), never visibly
const viewRequest = (id) => {
  selectedRequestId.value = id;
  openMenuId.value = null;
  router.push({ name: "FundingRequestDetails", params: { id } });
};

const editRequest = (id) => {
  openMenuId.value = null;
  if (!canEditFunding.value) {
    toast.error("You do not have permission to edit funding requests");
    return;
  }
  router.push({ name: "EditFundingRequest", params: { id } });
};

function askSubmit(request) {
  openMenuId.value = null;
  if (!canEditFunding.value) {
    toast.error("You do not have permission to submit funding requests");
    return;
  }
  confirming.value = { kind: "submit", request, busy: false };
}

function askDelete(request) {
  openMenuId.value = null;
  if (!canDeleteFunding.value) {
    toast.error("You do not have permission to delete funding requests");
    return;
  }
  confirming.value = { kind: "delete", request, busy: false };
}

// Focus Cancel when the dialog opens, so Enter never deletes by accident
watch(confirming, async (open, before) => {
  if (open && !before) {
    await nextTick();
    cancelBtn.value?.focus();
  }
});

async function confirmAction() {
  const current = confirming.value;
  if (!current || current.busy) return;
  current.busy = true;
  const id = current.request.id;
  try {
    const { data } =
      current.kind === "delete"
        ? await api.delete(`/procurement/inventory/funding-requests/${id}`)
        : await api.post(`/procurement/inventory/funding-requests/${id}/submit`);
    if (data.success) {
      toast.success(data.message || (current.kind === "delete" ? "Request deleted." : "Sent to Finance."));
      confirming.value = null;
      await fetchRequests();
    } else {
      current.busy = false;
    }
  } catch (err) {
    current.busy = false;
    toast.error(err.response?.data?.message || (current.kind === "delete" ? "We couldn't delete the request." : "We couldn't send the request."));
  }
}

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

// Clicking anywhere else, or Esc, closes the row menu
function closeMenu(event) {
  if (event.type === "keydown" && event.key !== "Escape") return;
  if (event.type === "click" && event.target.closest?.(".fr-actions")) return;
  openMenuId.value = null;
}

onMounted(() => {
  fetchRequests();
  document.addEventListener("click", closeMenu);
  document.addEventListener("keydown", closeMenu);
});
onBeforeUnmount(() => {
  document.removeEventListener("click", closeMenu);
  document.removeEventListener("keydown", closeMenu);
});
</script>

<style scoped>
/* Stages */
.fr-stages {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}
.fr-stage {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid var(--v-border);
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.fr-stage:hover {
  border-color: var(--v-border-strong);
}
.fr-stage.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.fr-stage:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.fr-stage-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.fr-stage strong {
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fr-stage small {
  font-size: 12px;
  color: var(--v-muted);
  font-variant-numeric: tabular-nums;
}
.fr-sk-num {
  width: 40px;
  height: 22px;
  border-radius: 6px;
}
.fr-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--v-faint);
}
.t-amber .fr-dot { background: #f59e0b; }
.t-green .fr-dot { background: #10b981; }
.t-red .fr-dot { background: #ef4444; }

/* Filters */
.fr-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 12px;
  margin-bottom: 16px;
}
.fr-search {
  flex: 1;
  min-width: 220px;
}
.fr-select {
  width: auto;
  min-width: 160px;
}

/* Rows */
.fr-card {
  padding: 8px;
}
.fr-head,
.fr-row {
  display: grid;
  grid-template-columns: minmax(150px, 0.9fr) minmax(0, 1.4fr) minmax(120px, 0.7fr) minmax(140px, 0.9fr) minmax(140px, 0.8fr) 44px;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
}
.fr-head {
  padding-top: 10px;
  padding-bottom: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--v-muted);
}
.num {
  text-align: right;
}
.fr-rows {
  margin: 0;
  padding: 0;
  list-style: none;
  transition: opacity 0.2s;
}
.fr-rows.dim {
  opacity: 0.55;
}
.fr-rows > li + li {
  border-top: 1px solid var(--v-border);
}
.fr-row {
  position: relative;
  border-radius: 12px;
  cursor: pointer;
  transition: background 0.15s;
}
.fr-row:hover,
.fr-row.selected {
  background: var(--v-subtle);
}
.fr-row.sk {
  grid-template-columns: 1fr 2fr 1fr;
  cursor: default;
}
.fr-row > span {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.fr-row b,
.fr-link {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fr-link {
  align-self: flex-start;
  text-decoration: none;
}
.fr-link:hover {
  color: var(--bc-accent);
}
.fr-link:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
  border-radius: 4px;
}
.fr-row small {
  overflow: hidden;
  font-size: 12px;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fr-urgency {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
}
.fr-urgency :deep(svg) {
  width: 13px;
  height: 13px;
}
.fr-row small.fr-qty {
  color: var(--v-text-2);
  font-weight: 500;
}
.fr-amount {
  align-items: flex-end;
  font-variant-numeric: tabular-nums;
}
.fr-row b.fr-none {
  color: var(--v-muted);
  font-weight: 500;
}
.fr-row small.good { color: var(--v-green-text); font-weight: 600; }
.fr-row small.ok { color: var(--v-amber-text); font-weight: 600; }
.fr-row small.low { color: var(--v-red-text); font-weight: 600; }
.fr-row small.fr-rec.green { color: var(--v-green-text); }
.fr-row small.fr-rec.amber { color: var(--v-amber-text); }
.fr-row small.fr-rec.red { color: var(--v-red-text); }
.fr-status {
  align-items: flex-start;
}

/* Row menu */
.fr-actions {
  position: relative;
  align-items: flex-end;
}
.fr-actions .sp-icon-btn {
  width: 40px;
  height: 40px;
}
.fr-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 20;
  display: grid;
  min-width: 200px;
  padding: 6px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-raised);
  box-shadow: 0 18px 40px -16px var(--v-shadow), 0 4px 12px -6px var(--v-shadow);
}
.fr-menu button {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 0 12px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-text);
  font: inherit;
  font-size: 13.5px;
  text-align: left;
  cursor: pointer;
}
.fr-menu button:hover:not(:disabled) {
  background: var(--v-subtle);
}
.fr-menu button:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: -2px;
}
.fr-menu button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}
.fr-menu button.danger {
  color: var(--v-red-text);
}
.fr-menu button :deep(svg) {
  width: 16px;
  height: 16px;
}

/* Tablets and phones: each request becomes a small card */
@media (max-width: 1100px) {
  .fr-stages {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 900px) {
  .fr-head {
    display: none;
  }
  .fr-row {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
      "id actions"
      "item item"
      "amount status"
      "finance finance";
    row-gap: 10px;
  }
  .fr-id { grid-area: id; }
  .fr-item { grid-area: item; }
  .fr-amount { grid-area: amount; align-items: flex-start; text-align: left; }
  .fr-finance { grid-area: finance; }
  .fr-status { grid-area: status; align-items: flex-end; }
  .fr-actions { grid-area: actions; }
}
@media (max-width: 560px) {
  .fr-stages {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .fr-select {
    flex: 1;
  }
}
@media (prefers-reduced-motion: reduce) {
  .fr-stage,
  .fr-row,
  .fr-rows {
    transition: none;
  }
}
</style>
