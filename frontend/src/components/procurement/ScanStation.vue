<template>
  <HrPage :title="title" :subtitle="subtitle" :tabs="SCAN_TABS">
    <template #actions>
      <span class="ss-live" :class="`t-${tone}`"><span class="ss-live-dot"></span>{{ queue === null ? "Loading…" : `${queue.length} waiting` }}</span>
    </template>

    <div class="ss-grid">
      <div class="ss-col">
        <!-- Scanner -->
        <section class="sp-card ss-scan" :class="`t-${tone}`" aria-labelledby="ss-scan-title">
          <div class="sp-card-head">
            <div><h2 id="ss-scan-title">Scan a delivery</h2><p>Use a barcode scanner, the camera, or type the code and press Enter</p></div>
          </div>
          <ScannerInput ref="scannerRef" :placeholder="placeholder" :auto-focus="true" @scan="handleScan" />
        </section>

        <!-- Last scan -->
        <Transition name="sp-fade">
          <section v-if="lastResult" class="sp-card ss-last" :class="`t-${tone}`" aria-live="polite">
            <span class="ss-last-ic" v-html="ICONS.check"></span>
            <div class="grow">
              <b>{{ lastResult.order?.order_number ?? lastResult.delivery_id }}</b>
              <span>{{ lastResult.order?.delivery_contact_name ?? "Customer" }} · now <strong>{{ nextLabel }}</strong></span>
            </div>
            <small>{{ lastTime }}</small>
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
      </div>

      <div class="ss-col">
        <!-- Progress -->
        <section class="sp-card ss-progress" aria-label="Progress today">
          <dl>
            <div><dt>Done today</dt><dd>{{ doneCount }}</dd></div>
            <div><dt>Still waiting</dt><dd>{{ queue?.length ?? 0 }}</dd></div>
            <div><dt>Progress</dt><dd :class="`c-${tone}`">{{ pct }}%</dd></div>
          </dl>
          <div class="ss-bar" role="img" :aria-label="`${pct}% done`"><span :class="`b-${tone}`" :style="{ width: `${pct}%` }"></span></div>
        </section>

        <!-- How to -->
        <section class="sp-card" aria-labelledby="ss-how">
          <div class="sp-card-head"><div><h2 id="ss-how">How it works</h2></div></div>
          <ol class="ss-steps">
            <li v-for="(s, i) in steps" :key="i"><span class="ss-step-n" :class="`b-${tone}`">{{ i + 1 }}</span><span v-html="s"></span></li>
          </ol>
        </section>

        <!-- Queue -->
        <section class="sp-card" aria-labelledby="ss-queue">
          <div class="sp-card-head">
            <div><h2 id="ss-queue">{{ queueTitle }}</h2><p>Refreshes every 10 seconds</p></div>
            <span class="sp-chip" :class="tone">{{ queue?.length ?? 0 }}</span>
          </div>
          <label v-if="(queue?.length ?? 0) > 5" class="sp-search ss-find">
            <span v-html="ICONS.search"></span>
            <input v-model="find" class="sp-input" type="search" placeholder="Order number or customer" aria-label="Find in the queue" />
          </label>
          <ul v-if="queue === null" class="ss-queue"><li v-for="n in 4" :key="n"><div class="bc-skeleton sp-sk-line w70"></div></li></ul>
          <div v-else-if="!queue.length" class="ss-empty">
            <span v-html="ICONS.check"></span>
            <b>All clear</b>
            <small>{{ emptyText }}</small>
          </div>
          <ul v-else class="ss-queue">
            <li v-for="d in shownQueue" :key="d.id" :class="{ done: doneIds.has(d.id) }">
              <span class="ss-q-ic" :class="`t-${tone}`" v-html="doneIds.has(d.id) ? ICONS.check : ICONS.box"></span>
              <span class="grow">
                <b>{{ d.order?.order_number ?? d.delivery_id }}</b>
                <small>{{ d.order?.delivery_contact_name ?? "—" }}<template v-if="d.order?.reservation_date"> · for {{ shortDate(d.order.reservation_date) }}</template></small>
              </span>
              <span class="ss-q-amt">{{ money(d.order?.total_amount) }}</span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </HrPage>
</template>

<script setup>
// One scanning station: scan a delivery's barcode to move it to the next
// step. The same flow as before (deliveryService.scan with the page's stage).
import { computed, onMounted, onUnmounted, ref } from "vue";
import HrPage from "../hr/HrPage.vue";
import ScannerInput from "../../layouts/components/ScannerInput.vue";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { apiMessage } from "../vendor/shop/useVendorCatalog";
import { money } from "../vendor/finance/financeUi";
import { deliveryService } from "../../services/deliveryService";
import { toast } from "@/utils/snackbar";

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: "" },
  stage: { type: String, required: true }, // to_process | to_ship | to_receive
  queueStatus: { type: String, required: true }, // deliveries waiting at this station
  queueTitle: { type: String, default: "Waiting" },
  nextLabel: { type: String, required: true },
  placeholder: { type: String, default: "Scan a delivery barcode…" },
  steps: { type: Array, default: () => [] },
  emptyText: { type: String, default: "Nothing waiting here right now." },
  tone: { type: String, default: "blue" },
  storageKey: { type: String, required: true },
});

const SCAN_TABS = [
  { name: "ScanToProcess", label: "To process", icon: "box" },
  { name: "ScanToShip", label: "To ship", icon: "truck" },
  { name: "ScanToReceive", label: "To receive", icon: "pin" },
  { name: "ScanCompleted", label: "Completed", icon: "check" },
];

// What was scanned today on this device, so a reload keeps the tally
const TODAY_KEY = `${props.storageKey}_${new Date().toISOString().slice(0, 10)}`;
const readDone = () => {
  try {
    return new Set(JSON.parse(localStorage.getItem(TODAY_KEY) || "[]"));
  } catch {
    return new Set();
  }
};
const saveDone = (ids) => {
  try {
    localStorage.setItem(TODAY_KEY, JSON.stringify([...ids]));
  } catch {
    /* only a convenience */
  }
};

const scannerRef = ref(null);
const queue = ref(null);
const lastResult = ref(null);
const lastTime = ref("");
const scanLog = ref([]);
const doneIds = ref(readDone());
const find = ref("");
let logId = 0;
let pollTimer = null;

const doneCount = computed(() => doneIds.value.size);
const pct = computed(() => {
  const total = doneCount.value + (queue.value?.length ?? 0);
  return total ? Math.round((doneCount.value / total) * 100) : 0;
});
const shownQueue = computed(() => {
  const q = find.value.trim().toLowerCase();
  return (queue.value ?? []).filter((d) => !q || `${d.order?.order_number ?? ""} ${d.delivery_id ?? ""} ${d.order?.delivery_contact_name ?? ""}`.toLowerCase().includes(q));
});
const shortDate = (d) => new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" });

async function loadQueue() {
  try {
    const res = await deliveryService.scOrders({ status: props.queueStatus, per_page: 100 });
    const raw = res?.data?.data ?? res?.data ?? [];
    queue.value = Array.isArray(raw) ? raw : [];
  } catch {
    queue.value = queue.value ?? [];
  }
}

async function handleScan(barcode) {
  scannerRef.value?.setScanning(true);
  const code = barcode.trim().toUpperCase();
  try {
    const res = await deliveryService.scan(code, props.stage);
    const delivery = res?.data?.delivery ?? res?.data ?? res;
    lastResult.value = delivery;
    lastTime.value = new Date().toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
    doneIds.value = new Set([...doneIds.value, delivery.id]);
    saveDone(doneIds.value);
    const ref_ = delivery.order?.order_number ?? delivery.delivery_id ?? code;
    addLog(code, "ok", `${ref_} → ${props.nextLabel}`);
    scannerRef.value?.showResult("ok", `${ref_} moved to ${props.nextLabel}`);
    toast.success(`${ref_} is now ${props.nextLabel}.`, { toastId: "scan-ok" });
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
  scanLog.value.unshift({ id: ++logId, barcode, type, message, time: new Date().toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit", second: "2-digit" }) });
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
  background: currentColor;
  animation: ss-pulse 1.8s ease-in-out infinite;
}
.t-amber .ss-live-dot, .ss-live.t-amber .ss-live-dot { color: #f59e0b; }
.ss-live.t-blue .ss-live-dot { color: #3b82f6; }
.ss-live.t-violet .ss-live-dot { color: #8b5cf6; }
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
  border-top: 3px solid var(--v-border-strong);
}
.ss-scan.t-amber { border-top-color: #f59e0b; }
.ss-scan.t-blue { border-top-color: #3b82f6; }
.ss-scan.t-violet { border-top-color: #8b5cf6; }
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
.ss-last small {
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
.ss-progress dl {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0 0 12px;
}
.ss-progress dt {
  font-size: 12px;
  color: var(--v-muted);
}
.ss-progress dd {
  margin: 2px 0 0;
  font-size: 22px;
  font-weight: 600;
  color: var(--v-text);
}
.c-amber { color: var(--v-amber-text) !important; }
.c-blue { color: var(--v-blue-text) !important; }
.c-violet { color: var(--v-purple-text) !important; }
.ss-bar {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.ss-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  transition: width 0.4s ease;
}
.b-amber { background: #f59e0b; }
.b-blue { background: #3b82f6; }
.b-violet { background: #8b5cf6; }
.ss-steps {
  list-style: none;
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
}
.ss-steps li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
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
.ss-find {
  margin-bottom: 10px;
}
.ss-queue {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 420px;
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
.ss-queue li.done {
  opacity: 0.55;
}
.ss-queue b {
  font-size: 13px;
  color: var(--v-text);
}
.ss-queue small {
  font-size: 12px;
  color: var(--v-muted);
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
.ss-q-amt {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
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
@media (prefers-reduced-motion: reduce) {
  .ss-live-dot {
    animation: none;
  }
  .ss-bar span {
    transition: none;
  }
}
</style>
