<template>
  <HrPage title="Order scanner" subtitle="Scan any order's barcode. It moves to its next step on its own: packed, handed to the rider, then dropped off." :tabs="SCAN_TABS">
    <template #actions>
      <span class="ss-live"><span class="ss-live-dot"></span>{{ queue === null ? "Loading…" : `${queue.length} in progress` }}</span>
    </template>

    <div class="ss-grid">
      <div class="ss-col">
        <!-- Scanner -->
        <section class="sp-card ss-scan" aria-labelledby="ss-scan-title">
          <div class="sp-card-head">
            <div><h2 id="ss-scan-title">Scan an order</h2><p>Use a barcode scanner, the camera, or type the code and press Enter</p></div>
          </div>
          <ScannerInput ref="scannerRef" placeholder="Scan an order barcode…" :auto-focus="true" @scan="handleScan" />
        </section>

        <!-- Last scan: where it was and where it is now -->
        <Transition name="sp-fade">
          <section v-if="lastResult" class="sp-card ss-last" aria-live="polite">
            <span class="ss-last-ic" v-html="ICONS.check"></span>
            <div class="grow">
              <b>{{ lastResult.ref }}</b>
              <span>{{ lastResult.customer }}</span>
              <span class="ss-move">
                <span class="sp-chip" :class="SCAN_STEPS[lastResult.from]?.tone ?? 'slate'">{{ stepLabel(lastResult.from) }}</span>
                <span class="ss-arrow" aria-label="moved to" v-html="ICONS.chevronRight"></span>
                <span class="sp-chip" :class="SCAN_STEPS[lastResult.to]?.tone ?? 'slate'">{{ stepLabel(lastResult.to) }}</span>
              </span>
            </div>
            <small>{{ lastResult.time }}</small>
          </section>
        </Transition>

        <!-- Scan log -->
        <section v-if="scanLog.length" class="sp-card" aria-labelledby="ss-log-title">
          <div class="sp-card-head">
            <div><h2 id="ss-log-title">This session</h2><p>Every scan since you opened the page</p></div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="scanLog = []">Clear</button>
          </div>
          <ul class="ss-log">
            <li v-for="e in scanLog" :key="e.id" :class="e.type">
              <span class="ss-log-dot" aria-hidden="true"></span>
              <code>{{ e.barcode }}</code>
              <span class="grow">{{ e.message }}</span>
              <small>{{ e.time }}</small>
            </li>
          </ul>
        </section>

        <!-- How to -->
        <section class="sp-card" aria-labelledby="ss-how">
          <div class="sp-card-head"><div><h2 id="ss-how">How it works</h2><p>The same barcode, scanned once at each step</p></div></div>
          <ol class="ss-steps">
            <li><span class="ss-step-n b-amber">1</span><span><strong>Packed:</strong> scan the order's <strong>BLM-</strong> label. It moves from To process to <strong>To ship</strong>.</span></li>
            <li><span class="ss-step-n b-blue">2</span><span><strong>Handed to the rider:</strong> scan it again. It's now <strong>On the way</strong>.</span></li>
            <li><span class="ss-step-n b-violet">3</span><span><strong>At the customer's door:</strong> scan it once more. It moves to <strong>To receive</strong> and the customer is asked to confirm.</span></li>
          </ol>
        </section>
      </div>

      <div class="ss-col">
        <!-- Where every order is: tap a step to see only those -->
        <section class="sp-card" aria-labelledby="ss-where">
          <div class="sp-card-head">
            <div><h2 id="ss-where">Where orders are</h2><p>{{ doneCount }} scanned today on this device</p></div>
          </div>
          <div class="ss-stages" role="group" aria-label="Filter the list by step">
            <button
              v-for="s in STAGES"
              :key="s.status"
              type="button"
              class="ss-stage"
              :class="[`t-${SCAN_STEPS[s.status].tone}`, { on: stageFilter === s.status }]"
              :aria-pressed="stageFilter === s.status"
              @click="stageFilter = stageFilter === s.status ? null : s.status"
            >
              <span class="ss-stage-ic" v-html="ICONS[SCAN_STEPS[s.status].icon]"></span>
              <strong>{{ queue === null ? "–" : counts[s.status] }}</strong>
              <span>{{ stepLabel(s.status) }}</span>
              <small>Next scan: {{ s.next }}</small>
            </button>
          </div>
        </section>

        <!-- Queue -->
        <section class="sp-card" aria-labelledby="ss-queue">
          <div class="sp-card-head">
            <div><h2 id="ss-queue">{{ stageFilter ? stepLabel(stageFilter) : "All orders in progress" }}</h2><p>Refreshes every 10 seconds</p></div>
            <button v-if="stageFilter" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="stageFilter = null">Show all</button>
          </div>
          <label v-if="(queue?.length ?? 0) > 5" class="sp-search ss-find">
            <span v-html="ICONS.search"></span>
            <input v-model="find" class="sp-input" type="search" placeholder="Order number or customer" aria-label="Find an order" />
          </label>
          <ul v-if="queue === null" class="ss-queue"><li v-for="n in 4" :key="n"><div class="bc-skeleton sp-sk-line w70"></div></li></ul>
          <div v-else-if="!shownQueue.length" class="ss-empty">
            <span v-html="ICONS.check"></span>
            <b>All clear</b>
            <small>{{ stageFilter ? `Nothing is at ${stepLabel(stageFilter)} right now.` : "No orders are waiting for a scan." }}</small>
          </div>
          <ul v-else class="ss-queue">
            <li v-for="d in shownQueue" :key="d.id">
              <span class="ss-q-ic" :class="`t-${SCAN_STEPS[d.status]?.tone}`" v-html="ICONS[SCAN_STEPS[d.status]?.icon ?? 'box']"></span>
              <span class="grow">
                <b>{{ d.order?.order_number ?? d.delivery_id }}</b>
                <small>{{ d.order?.delivery_contact_name ?? "—" }}<template v-if="d.order?.reservation_date"> · for {{ shortDate(d.order.reservation_date) }}</template></small>
              </span>
              <span class="sp-chip" :class="SCAN_STEPS[d.status]?.tone ?? 'slate'">{{ stepLabel(d.status) }}</span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </HrPage>
</template>

<script setup>
// The one order scanner: every scan moves a delivery one step forward from
// wherever it is (To process → To ship → On the way → To receive), so staff
// don't switch pages per step. The server checks and applies the step ("auto").
import { computed, onMounted, onUnmounted, ref } from "vue";
import HrPage from "../hr/HrPage.vue";
import ScannerInput from "../../layouts/components/ScannerInput.vue";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { apiMessage } from "../vendor/shop/useVendorCatalog";
import { deliveryService } from "../../services/deliveryService";
import { SCAN_STEPS, SCAN_TABS, stepLabel } from "./scanTabs";
import { toast } from "@/utils/snackbar";

// The steps a scan can still move an order out of
const STAGES = [
  { status: "pending", next: "To ship" },
  { status: "to_processed", next: "On the way" },
  { status: "to_ship", next: "To receive" },
];

// What was scanned today on this device, so a reload keeps the tally
const TODAY_KEY = `orderScans_${new Date().toISOString().slice(0, 10)}`;
const readDone = () => {
  try {
    return Number(localStorage.getItem(TODAY_KEY) || 0) || 0;
  } catch {
    return 0;
  }
};
const saveDone = (n) => {
  try {
    localStorage.setItem(TODAY_KEY, String(n));
  } catch {
    /* only a convenience */
  }
};

const scannerRef = ref(null);
const queue = ref(null);
const lastResult = ref(null);
const scanLog = ref([]);
const doneCount = ref(readDone());
const find = ref("");
const stageFilter = ref(null);
let logId = 0;
let pollTimer = null;

const counts = computed(() => Object.fromEntries(STAGES.map((s) => [s.status, (queue.value ?? []).filter((d) => d.status === s.status).length])));
const shownQueue = computed(() => {
  const q = find.value.trim().toLowerCase();
  return (queue.value ?? [])
    .filter((d) => !stageFilter.value || d.status === stageFilter.value)
    .filter((d) => !q || `${d.order?.order_number ?? ""} ${d.delivery_id ?? ""} ${d.order?.delivery_contact_name ?? ""}`.toLowerCase().includes(q));
});
const shortDate = (d) => new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
const clock = (withSeconds = false) => new Date().toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit", ...(withSeconds ? { second: "2-digit" } : {}) });

async function loadQueue() {
  try {
    const res = await deliveryService.scOrders({ status: STAGES.map((s) => s.status).join(","), per_page: 100 });
    const raw = res?.data?.data ?? res?.data ?? [];
    queue.value = Array.isArray(raw) ? raw : [];
  } catch {
    queue.value = queue.value ?? [];
  }
}

async function handleScan(barcode) {
  scannerRef.value?.setScanning(true);
  const code = barcode.trim().toUpperCase();
  // Where it was before this scan, if it's in the list
  const before = (queue.value ?? []).find((d) => [d.barcode, d.delivery_id].some((v) => String(v ?? "").toUpperCase() === code));
  try {
    const res = await deliveryService.scan(code, "auto");
    const delivery = res?.data?.delivery ?? res?.data ?? res;
    const to = delivery.status;
    const from = before?.status ?? Object.keys(SCAN_STEPS).find((k) => SCAN_STEPS[k].next === to) ?? null;
    const ref_ = delivery.order?.order_number ?? delivery.delivery_id ?? code;
    lastResult.value = { ref: ref_, customer: delivery.order?.delivery_contact_name ?? "Customer", from, to, time: clock() };
    doneCount.value += 1;
    saveDone(doneCount.value);
    addLog(code, "ok", `${ref_}: ${stepLabel(from)} → ${stepLabel(to)}`);
    scannerRef.value?.showResult("ok", `${ref_} moved to ${stepLabel(to)}`);
    toast.success(`${ref_} is now ${stepLabel(to)}.`, { toastId: "scan-ok" });
    await loadQueue();
  } catch (e) {
    const msg = apiMessage(e, "That scan didn't work. Check the barcode and try again.");
    addLog(code, "err", msg);
    scannerRef.value?.showResult("err", msg);
  } finally {
    scannerRef.value?.setScanning(false);
  }
}

function addLog(barcode, type, message) {
  scanLog.value.unshift({ id: ++logId, barcode, type, message, time: clock(true) });
  if (scanLog.value.length > 30) scanLog.value.pop();
}

onMounted(() => {
  loadQueue();
  pollTimer = setInterval(loadQueue, 10000);
});
onUnmounted(() => clearInterval(pollTimer));
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ss-live {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.ss-live-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #10b981;
  animation: ss-pulse 1.8s ease-in-out infinite;
}
@keyframes ss-pulse {
  50% { opacity: 0.35; }
}
.ss-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.ss-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.ss-scan {
  border-top: 3px solid var(--bc-accent);
}
.ss-last {
  display: flex;
  align-items: center;
  gap: 12px;
}
.ss-last-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.ss-last-ic :deep(svg) {
  width: 20px;
  height: 20px;
}
.ss-last b {
  font-size: 15px;
  color: var(--v-text);
}
.ss-last .grow > span {
  font-size: 13px;
  color: var(--v-text-2);
}
.ss-move {
  display: inline-flex !important;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}
.ss-arrow {
  display: inline-flex;
  color: var(--v-faint);
}
.ss-arrow :deep(svg) {
  width: 14px;
  height: 14px;
}
.ss-last small {
  align-self: flex-start;
  font-size: 12px;
  color: var(--v-faint);
}
.ss-log {
  list-style: none;
  display: grid;
  gap: 4px;
  max-height: 280px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
}
.ss-log li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-text-2);
}
.ss-log code {
  font-size: 12px;
  color: var(--v-text);
}
.ss-log small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.ss-log-dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
}
.ss-log li.err .ss-log-dot {
  background: var(--v-red-text);
}
.ss-log li.err .grow {
  color: var(--v-red-text);
}

/* Where orders are */
.ss-stages {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.ss-stage {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-height: 44px;
  padding: 12px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-surface);
  font: inherit;
  text-align: left;
  color: inherit;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.ss-stage:hover {
  border-color: var(--v-border-strong);
}
.ss-stage:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.ss-stage strong {
  margin-top: 6px;
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
}
.ss-stage > span:not(.ss-stage-ic) {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text-2);
}
.ss-stage small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ss-stage-ic {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 9px;
}
.ss-stage-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.ss-stage.t-amber .ss-stage-ic { background: var(--v-amber-soft); color: var(--v-amber-text); }
.ss-stage.t-blue .ss-stage-ic { background: var(--v-blue-soft); color: var(--v-blue-text); }
.ss-stage.t-violet .ss-stage-ic { background: var(--v-purple-soft); color: var(--v-purple-text); }
.ss-stage.on.t-amber { border-color: var(--v-amber-text); background: var(--v-amber-soft); }
.ss-stage.on.t-blue { border-color: var(--v-blue-text); background: var(--v-blue-soft); }
.ss-stage.on.t-violet { border-color: var(--v-purple-text); background: var(--v-purple-soft); }

.ss-steps {
  list-style: none;
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
}
.ss-steps li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
}
.ss-steps strong {
  color: var(--v-text);
}
.ss-step-n {
  flex: none;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}
.b-amber { background: #d97706; }
.b-blue { background: #2563eb; }
.b-violet { background: #7c3aed; }
.ss-find {
  margin-bottom: 10px;
}
.ss-queue {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 460px;
  overflow-y: auto;
}
.ss-queue li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 2px;
}
.ss-queue li + li {
  border-top: 1px solid var(--v-border);
}
.ss-queue b {
  font-size: 13px;
  color: var(--v-text);
}
.ss-queue small {
  font-size: 12px;
  color: var(--v-muted);
}
.ss-queue .sp-chip {
  flex: none;
}
.ss-q-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.ss-q-ic.t-amber { background: var(--v-amber-soft); color: var(--v-amber-text); }
.ss-q-ic.t-blue { background: var(--v-blue-soft); color: var(--v-blue-text); }
.ss-q-ic.t-violet { background: var(--v-purple-soft); color: var(--v-purple-text); }
.ss-q-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.ss-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 22px 10px;
  text-align: center;
}
.ss-empty :deep(svg) {
  width: 26px;
  height: 26px;
  color: var(--v-green-text);
}
.ss-empty b {
  font-size: 14px;
  color: var(--v-text);
}
.ss-empty small {
  font-size: 12.5px;
  color: var(--v-muted);
}
@media (max-width: 1000px) {
  .ss-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 480px) {
  .ss-stages {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .ss-live-dot {
    animation: none;
  }
}
</style>
