<template>
  <div class="lp">
    <!-- Shop header -->
    <header class="lp-shop">
      <div class="lp-logo">
        <img v-if="shop?.logo" :src="shop.logo" alt="" />
        <span v-else-if="shop" v-html="ICONS.flower"></span>
        <div v-else class="bc-skeleton lp-logo-sk"></div>
      </div>
      <div>
        <small>Leave &amp; overtime</small>
        <h1 v-if="shop">{{ shop.name }}</h1>
        <div v-else-if="!fatal" class="bc-skeleton sp-sk-line w50"></div>
      </div>
      <button v-if="me" type="button" class="sp-btn sp-btn-ghost sp-btn-sm lp-out" @click="signOut">Done</button>
    </header>

    <!-- No or bad link -->
    <section v-if="fatal" class="sp-card lp-card">
      <div class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.alert"></span>
        <h3>Scan your shop's leave QR code</h3>
        <p>{{ fatal }}</p>
      </div>
    </section>

    <!-- Scan badge -->
    <section v-else-if="!me" class="sp-card lp-card">
      <div v-if="!scanning" class="lp-intro">
        <span class="lp-badge-art" v-html="ICONS.user"></span>
        <h2>Scan your ID badge</h2>
        <p>Point your phone's camera at the QR code on your employee badge. It's how we know it's you.</p>
        <button type="button" class="sp-btn sp-btn-primary lp-wide" :disabled="!shop || busy" @click="startScan">
          <span v-html="ICONS.grid"></span>{{ busy ? "Checking…" : "Scan my badge" }}
        </button>
        <p v-if="scanError" class="sp-error">{{ scanError }}</p>
      </div>
      <div v-else class="lp-scan">
        <div class="lp-camera">
          <video ref="video" playsinline muted></video>
          <div class="lp-frame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          <div v-if="busy" class="lp-cover">Checking your badge…</div>
        </div>
        <div class="lp-scan-actions">
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="flipCamera"><span v-html="ICONS.history"></span>Switch camera</button>
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="stopScan">Cancel</button>
        </div>
        <p class="sp-hint lp-center">Hold the badge's QR code inside the frame.</p>
      </div>
    </section>

    <!-- Signed in -->
    <template v-else>
      <section class="lp-hello">
        <span class="lp-avatar" :style="{ background: personColor(me.employee.name) }">
          <img v-if="me.employee.avatar" :src="me.employee.avatar" alt="" />
          <template v-else>{{ initialsOf(me.employee.name) }}</template>
        </span>
        <div>
          <h2>Hi, {{ me.employee.name.split(" ")[0] }}</h2>
          <small>{{ [me.employee.employee_id, me.employee.position].filter(Boolean).join(" · ") }}</small>
        </div>
      </section>
      <p v-if="me.old_badge" class="sp-banner warn lp-note"><span v-html="ICONS.alert"></span>You scanned an old badge. Your requests still go through, but ask HR for a new one.</p>

      <!-- Balances -->
      <section class="lp-balances" aria-label="Leave balances">
        <article v-for="b in me.balances" :key="b.leave_type" class="lp-bal" :class="{ low: b.available !== null && b.available <= 0 }">
          <small>{{ b.label }}</small>
          <strong>{{ b.available === null ? "Any" : fmt(b.available) }}</strong>
          <span>{{ b.allowance === null ? "No yearly limit" : `left of ${fmt(b.allowance)} this year` }}</span>
          <em v-if="b.pending">{{ fmt(b.pending) }} waiting</em>
        </article>
      </section>

      <div class="sp-seg lp-tabs" role="tablist" aria-label="What to do">
        <button v-for="t in TABS" :key="t.key" type="button" role="tab" :class="{ on: tab === t.key }" :aria-selected="tab === t.key" @click="tab = t.key">
          {{ t.label }}<span v-if="t.key === 'mine' && waitingCount" class="count">{{ waitingCount }}</span>
        </button>
      </div>

      <!-- Request leave -->
      <form v-if="tab === 'leave'" class="sp-card lp-card" novalidate @submit.prevent="sendLeave">
        <label class="sp-field">
          <span class="sp-label">Kind of leave</span>
          <select v-model="leave.leave_type" class="sp-select" :class="{ invalid: errors.leave_type }">
            <option value="" disabled>Choose…</option>
            <option v-for="b in me.balances" :key="b.leave_type" :value="b.leave_type" :disabled="b.available !== null && b.available <= 0">
              {{ b.label }} — {{ b.available === null ? "no limit" : `${fmt(b.available)} left` }}{{ b.is_paid ? "" : " · unpaid" }}
            </option>
          </select>
          <small v-if="errors.leave_type" class="sp-error">{{ errors.leave_type }}</small>
          <small v-else-if="chosen" class="sp-hint">{{ chosen.is_paid ? "Paid" : "Unpaid" }}{{ chosen.requires_document ? " · needs a document" : "" }}. Only your working days are counted.</small>
        </label>
        <div class="sp-grid-2">
          <label class="sp-field">
            <span class="sp-label">First day</span>
            <input v-model="leave.start_date" type="date" class="sp-input" :min="earliest" :class="{ invalid: errors.start_date }" />
          </label>
          <label class="sp-field">
            <span class="sp-label">Last day</span>
            <input v-model="leave.end_date" type="date" class="sp-input" :min="leave.start_date || earliest" :class="{ invalid: errors.end_date }" />
          </label>
        </div>
        <small v-if="errors.start_date || errors.end_date" class="sp-error">{{ errors.start_date || errors.end_date }}</small>
        <label class="sp-field">
          <span class="sp-label">Reason</span>
          <textarea v-model="leave.reason" class="sp-textarea" maxlength="1000" placeholder="e.g. Fever since last night" :class="{ invalid: errors.reason }"></textarea>
          <small v-if="errors.reason" class="sp-error">{{ errors.reason }}</small>
        </label>
        <label class="lp-file" :class="{ picked: leave.document, invalid: errors.document }">
          <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" @change="pickDocument" />
          <span v-html="ICONS.upload"></span>
          <span>{{ leave.document ? leave.document.name : `Add a document${chosen?.requires_document ? "" : " (optional)"}` }}</span>
          <small>Medical certificate, death certificate… PDF or photo, up to 10 MB</small>
        </label>
        <small v-if="errors.document" class="sp-error">{{ errors.document }}</small>
        <p v-if="formError" class="sp-error">{{ formError }}</p>
        <button type="submit" class="sp-btn sp-btn-primary lp-wide" :disabled="busy">{{ busy ? "Sending…" : "Send leave request" }}</button>
      </form>

      <!-- Request overtime -->
      <form v-else-if="tab === 'overtime'" class="sp-card lp-card" novalidate @submit.prevent="sendOvertime">
        <p class="lp-lead">Ask before you stay late. Once HR approves, staying up to that long after your shift is approved automatically.</p>
        <div class="sp-grid-2">
          <label class="sp-field">
            <span class="sp-label">Day</span>
            <input v-model="ot.date" type="date" class="sp-input" :min="me.today" :class="{ invalid: errors.date }" />
          </label>
          <label class="sp-field">
            <span class="sp-label">How long after your shift</span>
            <select v-model.number="ot.minutes" class="sp-select" :class="{ invalid: errors.minutes }">
              <option v-for="m in OT_OPTIONS" :key="m" :value="m">{{ durationText(m) }}</option>
            </select>
          </label>
        </div>
        <small v-if="errors.date || errors.minutes" class="sp-error">{{ errors.date || errors.minutes }}</small>
        <label class="sp-field">
          <span class="sp-label">Why</span>
          <textarea v-model="ot.reason" class="sp-textarea" maxlength="1000" placeholder="e.g. Finishing 40 bouquets for a wedding" :class="{ invalid: errors.reason }"></textarea>
          <small v-if="errors.reason" class="sp-error">{{ errors.reason }}</small>
        </label>
        <p v-if="formError" class="sp-error">{{ formError }}</p>
        <button type="submit" class="sp-btn sp-btn-primary lp-wide" :disabled="busy">{{ busy ? "Sending…" : "Send overtime request" }}</button>
      </form>

      <!-- My requests -->
      <section v-else class="lp-list">
        <p v-if="!me.leaves.length && !me.overtime.length" class="sp-card lp-card lp-empty">You haven't sent any requests yet.</p>
        <article v-for="r in mine" :key="`${r.kind}${r.id}`" class="sp-card lp-req">
          <header>
            <div>
              <b>{{ r.kind === "leave" ? r.label : "Overtime" }}</b>
              <small>{{ r.kind === "leave" ? `${dateRange(r.start_date, r.end_date)} · ${daysText(r.days)}` : `${dateRange(r.date, r.date)} · ${durationText(r.minutes)}` }}</small>
            </div>
            <span class="sp-chip" :class="LEAVE_STATUS[r.status].tone">{{ LEAVE_STATUS[r.status].label }}</span>
          </header>
          <p class="lp-reason">{{ r.reason }}</p>
          <p v-if="r.review_note" class="lp-review"><b>{{ r.reviewer }}:</b> {{ r.review_note }}</p>
          <details>
            <summary>History</summary>
            <RequestHistory :history="r.history" />
          </details>
          <button v-if="r.status === 'pending'" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="cancel(r)">Cancel this request</button>
        </article>
      </section>

      <p class="sp-hint lp-center">For your privacy, this page signs you out after {{ minutesLeft }} minutes.</p>
    </template>

    <!-- Sent -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="sent" class="sp-backdrop" @click="sent = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="sent" class="sp-modal-wrap">
          <div class="sp-modal lp-sent" role="alertdialog" aria-modal="true" aria-labelledby="lp-sent">
            <span class="lp-sent-icon" v-html="ICONS.check"></span>
            <h3 id="lp-sent">Request sent</h3>
            <p>{{ sent }}</p>
            <button type="button" class="sp-btn sp-btn-primary lp-wide" @click="sent = null; tab = 'mine'">See my requests</button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import jsQR from "jsqr";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { personColor } from "../../../../components/vendor/staff/staffUi";
import { initialsOf } from "../../../../components/hr/hrUi";
import RequestHistory from "../../../../components/hr/leave/RequestHistory.vue";
import { LEAVE_STATUS, dateRange, daysText, durationText, publicLeave } from "../../../../services/leaveDesk";
import "../../../../components/vendor/shop/shop.css";

const TABS = [
  { key: "leave", label: "Leave" },
  { key: "overtime", label: "Overtime" },
  { key: "mine", label: "My requests" },
];
const OT_OPTIONS = [30, 60, 90, 120, 180, 240, 300, 360];

const route = useRoute();
const shopCode = String(route.query.s ?? "");
const shop = ref(null);
const fatal = ref("");
const me = ref(null);
const token = ref("");
const tab = ref("leave");
const busy = ref(false);
const scanning = ref(false);
const scanError = ref("");
const video = ref(null);
const facing = ref("environment");
const errors = reactive({});
const formError = ref("");
const sent = ref(null);
const minutesLeft = ref(30);
const leave = reactive({ leave_type: "", start_date: "", end_date: "", reason: "", document: null });
const ot = reactive({ date: "", minutes: 60, reason: "" });

let stream = null;
let timer = null;
let expiry = null;

const chosen = computed(() => me.value?.balances.find((b) => b.leave_type === leave.leave_type));
const earliest = computed(() => {
  const d = new Date(`${me.value.today}T00:00:00`);
  d.setDate(d.getDate() - me.value.backdate_days);
  return d.toISOString().slice(0, 10);
});
const mine = computed(() => [
  ...me.value.leaves.map((l) => ({ ...l, kind: "leave", sort: l.filed_at })),
  ...me.value.overtime.map((o) => ({ ...o, kind: "overtime", sort: o.filed_at })),
].sort((a, b) => String(b.sort).localeCompare(String(a.sort))));
const waitingCount = computed(() => mine.value?.filter((r) => r.status === "pending").length ?? 0);
const fmt = (n) => (Number(n) % 1 ? Number(n).toFixed(1) : String(Number(n)));

function message(e, fallback) {
  const list = e?.response?.data?.errors;
  return (list && Object.values(list).flat()[0]) || e?.response?.data?.message || fallback;
}

async function loadShop() {
  if (!shopCode) {
    fatal.value = "This page opens from the leave QR code posted at your shop.";
    return;
  }
  try {
    shop.value = (await publicLeave.shop(shopCode)).data;
    document.title = `Leave · ${shop.value.name}`;
  } catch (e) {
    fatal.value = message(e, "This leave link isn't valid.");
  }
}

// ── Badge scan ──

async function startScan() {
  scanError.value = "";
  scanning.value = true;
  await nextTick();
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing.value } }, audio: false });
    video.value.srcObject = stream;
    await video.value.play();
  } catch (e) {
    scanning.value = false;
    scanError.value = e?.name === "NotAllowedError" ? "Allow the camera for this page, then try again." : "Couldn't open the camera.";
    return;
  }
  const canvas = document.createElement("canvas");
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  timer = setInterval(() => {
    const v = video.value;
    if (!v || v.readyState < 2 || busy.value) return;
    canvas.width = v.videoWidth;
    canvas.height = v.videoHeight;
    ctx.drawImage(v, 0, 0);
    const code = jsQR(ctx.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height);
    if (code?.data) identify(code.data);
  }, 250);
}

function stopScan() {
  clearInterval(timer);
  stream?.getTracks().forEach((t) => t.stop());
  stream = null;
  scanning.value = false;
}

function flipCamera() {
  facing.value = facing.value === "environment" ? "user" : "environment";
  stopScan();
  startScan();
}

async function identify(badge) {
  busy.value = true;
  try {
    const res = await publicLeave.identify(shopCode, badge);
    token.value = res.data.token;
    expiry = Date.now() + res.data.expires_in * 1000;
    me.value = res.data;
    stopScan();
    countdown();
  } catch (e) {
    scanError.value = message(e, "That badge didn't work.");
    stopScan();
  } finally {
    busy.value = false;
  }
}

function countdown() {
  clearInterval(timer);
  timer = setInterval(() => {
    minutesLeft.value = Math.max(0, Math.ceil((expiry - Date.now()) / 60000));
    if (Date.now() >= expiry) signOut();
  }, 15000);
}

function signOut() {
  clearInterval(timer);
  me.value = null;
  token.value = "";
  tab.value = "leave";
}

// ── Requests ──

function resetErrors() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
}

function pickDocument(e) {
  const f = e.target.files?.[0] ?? null;
  delete errors.document;
  if (f && f.size > 10 * 1024 * 1024) {
    errors.document = "Documents can be up to 10 MB.";
    e.target.value = "";
    return;
  }
  leave.document = f;
}

async function run(action, onDone) {
  busy.value = true;
  try {
    const res = await action();
    me.value = { ...me.value, ...res.data };
    onDone?.(res);
  } catch (e) {
    const list = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(list)) errors[k] = [].concat(v)[0];
    if (list.token) signOut();
    formError.value = Object.keys(list).length ? "" : message(e, "Couldn't send it. Try again.");
  } finally {
    busy.value = false;
  }
}

function sendLeave() {
  resetErrors();
  if (!leave.leave_type) errors.leave_type = "Choose a kind of leave.";
  if (!leave.start_date) errors.start_date = "Pick the first day.";
  if (!leave.end_date) errors.end_date = "Pick the last day.";
  if (leave.reason.trim().length < 5) errors.reason = "Say a bit about why.";
  if (chosen.value?.requires_document && !leave.document) errors.document = "This kind of leave needs a document.";
  if (Object.keys(errors).length) return;
  run(() => publicLeave.file(token.value, { ...leave, reason: leave.reason.trim() }), (res) => {
    sent.value = `${res.data.request.label}, ${dateRange(res.data.request.start_date, res.data.request.end_date)} (${daysText(res.data.request.days)}). HR will review it.`;
    Object.assign(leave, { leave_type: "", start_date: "", end_date: "", reason: "", document: null });
  });
}

function sendOvertime() {
  resetErrors();
  if (!ot.date) errors.date = "Pick the day.";
  if (ot.reason.trim().length < 5) errors.reason = "Say a bit about why.";
  if (Object.keys(errors).length) return;
  run(() => publicLeave.overtime(token.value, { ...ot, reason: ot.reason.trim() }), () => {
    sent.value = `${durationText(ot.minutes)} of overtime on ${dateRange(ot.date, ot.date)}. HR will review it.`;
    Object.assign(ot, { date: "", minutes: 60, reason: "" });
  });
}

function cancel(r) {
  resetErrors();
  run(() => (r.kind === "leave" ? publicLeave.cancel(token.value, r.id) : publicLeave.cancelOvertime(token.value, r.id)));
}

onMounted(loadShop);
onBeforeUnmount(() => {
  stopScan();
  clearInterval(timer);
});
</script>

<style scoped>
.lp {
  max-width: 560px;
  margin: 0 auto;
  padding: 18px 16px 48px;
  font-family: "Poppins", sans-serif;
  color: var(--v-text);
}
.lp-shop {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}
.lp-shop small {
  font-size: 12px;
  color: var(--v-muted);
}
.lp-shop h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}
.lp-out {
  margin-left: auto;
}
.lp-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  overflow: hidden;
  border-radius: 16px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent);
  box-shadow: 0 2px 10px var(--v-shadow);
}
.lp-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.lp-logo :deep(svg) {
  width: 26px;
  height: 26px;
}
.lp-logo-sk {
  width: 100%;
  height: 100%;
}
.lp-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.lp-intro {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 12px 4px;
  text-align: center;
}
.lp-intro h2 {
  margin: 4px 0 0;
  font-size: 19px;
  font-weight: 600;
}
.lp-intro p {
  margin: 0;
  font-size: 13.5px;
  color: var(--v-muted);
}
.lp-badge-art {
  display: grid;
  place-items: center;
  width: 72px;
  height: 72px;
  border-radius: 22px;
  background: linear-gradient(135deg, var(--bc-accent-soft), var(--v-subtle));
  color: var(--bc-accent);
}
.lp-badge-art :deep(svg) {
  width: 32px;
  height: 32px;
}
.lp-wide {
  width: 100%;
  padding: 12px 16px;
  font-size: 14px;
}
.lp-camera {
  position: relative;
  overflow: hidden;
  aspect-ratio: 3 / 4;
  border-radius: 18px;
  background: #0b0f14;
}
.lp-camera video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.lp-frame {
  position: absolute;
  inset: 26% 18%;
}
.lp-frame i {
  position: absolute;
  width: 30px;
  height: 30px;
  border: 4px solid #fff;
}
.lp-frame i:nth-child(1) { top: 0; left: 0; border-right: 0; border-bottom: 0; border-radius: 12px 0 0 0; }
.lp-frame i:nth-child(2) { top: 0; right: 0; border-left: 0; border-bottom: 0; border-radius: 0 12px 0 0; }
.lp-frame i:nth-child(3) { bottom: 0; left: 0; border-right: 0; border-top: 0; border-radius: 0 0 0 12px; }
.lp-frame i:nth-child(4) { bottom: 0; right: 0; border-left: 0; border-top: 0; border-radius: 0 0 12px 0; }
.lp-cover {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(11, 15, 20, 0.65);
  color: #fff;
  font-size: 14px;
}
.lp-scan-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 10px;
}
.lp-center {
  text-align: center;
}
.lp-hello {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}
.lp-hello h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}
.lp-hello small {
  font-size: 12.5px;
  color: var(--v-muted);
}
.lp-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  overflow: hidden;
  border-radius: 15px;
  color: #fff;
  font-weight: 700;
}
.lp-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.lp-note {
  margin-bottom: 12px;
}
.lp-balances {
  display: flex;
  gap: 10px;
  margin: 0 -16px 16px;
  padding: 2px 16px 6px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}
.lp-balances::-webkit-scrollbar {
  display: none;
}
.lp-bal {
  flex: 0 0 146px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-surface);
  scroll-snap-align: start;
}
.lp-bal small {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
}
.lp-bal strong {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.1;
  color: var(--bc-accent);
}
.lp-bal.low strong {
  color: var(--v-faint);
}
.lp-bal span {
  font-size: 11px;
  color: var(--v-muted);
}
.lp-bal em {
  font-size: 11px;
  font-style: normal;
  color: var(--v-amber-text);
}
.lp-tabs {
  display: flex;
  width: 100%;
  margin-bottom: 14px;
}
.lp-tabs button {
  flex: 1;
}
.lp-lead {
  margin: 0;
  font-size: 13px;
  color: var(--v-muted);
}
.lp-file {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 10px;
  padding: 14px;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 14px;
  font-size: 13px;
  color: var(--v-text-2);
  cursor: pointer;
}
.lp-file.picked {
  border-style: solid;
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.lp-file.invalid {
  border-color: #fc8181;
}
.lp-file input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.lp-file :deep(svg) {
  width: 18px;
  height: 18px;
  color: var(--bc-accent);
}
.lp-file small {
  flex-basis: 100%;
  font-size: 11.5px;
  color: var(--v-faint);
}
.lp-file:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.lp-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.lp-empty {
  color: var(--v-muted);
  text-align: center;
}
.lp-req {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
}
.lp-req header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}
.lp-req b {
  font-size: 14px;
  font-weight: 600;
}
.lp-req header small {
  display: block;
  font-size: 12px;
  color: var(--v-muted);
}
.lp-reason {
  margin: 0;
  font-size: 13px;
  color: var(--v-text-2);
}
.lp-review {
  margin: 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 12.5px;
}
.lp-req details summary {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--bc-accent);
  cursor: pointer;
}
.lp-req details[open] summary {
  margin-bottom: 8px;
}
.lp-req .sp-btn {
  align-self: flex-start;
}
.lp-sent {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}
.lp-sent h3 {
  margin: 0;
  font-size: 18px;
}
.lp-sent p {
  margin: 0 0 8px;
  font-size: 13.5px;
  color: var(--v-muted);
}
.lp-sent-icon {
  display: grid;
  place-items: center;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.lp-sent-icon :deep(svg) {
  width: 28px;
  height: 28px;
}
</style>
