<template>
  <HrPage title="Attendance scanner" subtitle="Works only on a device the shop owner approved, while it's at the shop." :tabs="ATTENDANCE_TABS">
    <template #actions>
      <span v-if="status?.device" class="sp-chip" :class="DEVICE_TONE[status.device.status]">
        {{ status.device.name }} · {{ DEVICE_LABEL[status.device.status] }}
      </span>
    </template>

    <section class="sp-card ks">
      <!-- Checking -->
      <div v-if="state === 'checking'" class="ks-center">
        <div class="bc-skeleton ks-sk"></div>
        <p class="sp-hint">Checking this device…</p>
      </div>

      <!-- Problems before scanning -->
      <div v-else-if="state === 'blocked'" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.lock"></span>
        <h3>{{ blocked.title }}</h3>
        <p>{{ blocked.text }}</p>
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="check"><span v-html="ICONS.history"></span>Check again</button>
      </div>

      <!-- Ask for this device to be allowed -->
      <form v-else-if="state === 'request'" class="ks-request" novalidate @submit.prevent="requestDevice">
        <span class="sp-empty-icon" v-html="ICONS.shield"></span>
        <h3>Set up this device for attendance</h3>
        <p>Only devices the shop owner approves can take attendance, and only while they're at the shop. Ask from here, at the shop.</p>
        <label class="sp-field">
          <span class="sp-label">Name this device</span>
          <input v-model="deviceName" class="sp-input" maxlength="80" placeholder="e.g. Front counter tablet" />
        </label>
        <p v-if="requestError" class="sp-error">{{ requestError }}</p>
        <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy || !deviceName.trim()">{{ busy ? "Checking your location…" : "Ask the owner to approve" }}</button>
      </form>

      <!-- Waiting for approval -->
      <div v-else-if="state === 'pending'" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.clock"></span>
        <h3>Waiting for the shop owner</h3>
        <p>They can approve "{{ status.device.name }}" in the vendor portal under Staff → Attendance devices.</p>
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="check"><span v-html="ICONS.history"></span>Check again</button>
      </div>

      <!-- Scanner -->
      <template v-else-if="state === 'scan' || state === 'starting'">
        <div class="ks-bar">
          <div v-if="canEnroll" class="sp-seg" role="tablist" aria-label="Mode">
            <button type="button" role="tab" :class="{ on: mode === 'attendance' }" :aria-selected="mode === 'attendance'" @click="mode = 'attendance'">Time in / out</button>
            <button type="button" role="tab" :class="{ on: mode === 'enroll' }" :aria-selected="mode === 'enroll'" @click="mode = 'enroll'">Enroll a face</button>
          </div>
          <span v-if="position" class="ks-loc"><span v-html="ICONS.pin"></span>Location ±{{ position.accuracy }} m</span>
        </div>
        <div class="ks-camera">
          <video ref="qrVideo" class="ks-video" playsinline muted></video>
          <div class="ks-frame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          <div v-if="state === 'starting'" class="ks-cover">Checking the QR code and your location…</div>
        </div>
        <p class="ks-tip">{{ mode === "enroll" ? "Scan the QR code of the person whose face you're enrolling. They must be here in person." : "Hold the employee's QR code up to the camera." }}</p>
        <p v-if="cameraError" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ cameraError }}<button type="button" @click="startQr">Try again</button></p>
      </template>

      <!-- Face check -->
      <KioskFaceCheck v-else-if="state === 'face'" :session="session" @done="finish" @fail="showError" @cancel="reset" />

      <!-- Sending -->
      <div v-else-if="state === 'sending'" class="ks-center">
        <div class="bc-skeleton ks-sk"></div>
        <p class="sp-hint">Saving…</p>
      </div>

      <!-- Result -->
      <div v-else-if="state === 'result'" class="ks-result" :class="result.tone" role="status">
        <span class="ks-result-icon" v-html="result.tone === 'error' ? ICONS.alert : ICONS.check"></span>
        <h3>{{ result.title }}</h3>
        <p>{{ result.text }}</p>
        <ul v-if="result.reasons?.length" class="ks-reasons">
          <li v-for="r in result.reasons" :key="r">{{ r }}</li>
        </ul>
        <button type="button" class="sp-btn sp-btn-primary" @click="reset">Scan the next person</button>
        <small v-if="autoReset" class="sp-hint">Next scan in {{ autoReset }}s</small>
      </div>
    </section>
  </HrPage>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import jsQR from "jsqr";
import HrPage from "../../../../components/hr/HrPage.vue";
import KioskFaceCheck from "../../../../components/hr/attendance/KioskFaceCheck.vue";
import { ATTENDANCE_TABS } from "../../../../components/hr/hrUi";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { useAssignment } from "../../../../composables/useAssignment";
import attendanceKiosk, { currentPosition, deviceToken, kioskMessage } from "../../../../services/attendanceKiosk";

const DEVICE_LABEL = { pending: "waiting for approval", approved: "approved", revoked: "removed" };
const DEVICE_TONE = { pending: "amber", approved: "green", revoked: "red" };

const { can } = useAssignment();
const canEnroll = computed(() => can("attendance", "edit"));

const state = ref("checking");
const status = ref(null);
const blocked = ref({ title: "", text: "" });
const position = ref(null);
const deviceName = ref("");
const requestError = ref("");
const busy = ref(false);
const mode = ref("attendance");
const qrVideo = ref(null);
const cameraError = ref("");
const session = ref(null);
const result = ref(null);
const autoReset = ref(0);

let qrStream = null;
let scanTimer = null;
let resetTimer = null;

async function check() {
  state.value = "checking";
  try {
    status.value = await attendanceKiosk.status();
  } catch (e) {
    return block("Couldn't reach the server", kioskMessage(e, "Check the internet connection and try again."));
  }
  if (!status.value.location_set) {
    return block("The shop location isn't set", "The shop owner sets it in the vendor portal under Staff → Attendance devices.");
  }
  const device = status.value.device;
  if (!device || device.status === "revoked") {
    if (device?.status === "revoked") deviceToken.clear();
    state.value = "request";
    return;
  }
  if (device.status === "pending") {
    state.value = "pending";
    return;
  }
  try {
    position.value = await currentPosition();
  } catch (e) {
    return block("Location is needed", e.message);
  }
  state.value = "scan";
  startQr();
}

function block(title, text) {
  stopQr();
  blocked.value = { title, text };
  state.value = "blocked";
}

async function requestDevice() {
  busy.value = true;
  requestError.value = "";
  try {
    const pos = await currentPosition();
    await attendanceKiosk.requestDevice(deviceName.value.trim(), pos);
    await check();
  } catch (e) {
    requestError.value = kioskMessage(e, "Couldn't send the request.");
  } finally {
    busy.value = false;
  }
}

// ── QR scanning ──

async function startQr() {
  cameraError.value = "";
  await nextTick();
  try {
    qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
    qrVideo.value.srcObject = qrStream;
    await qrVideo.value.play();
  } catch (e) {
    cameraError.value = e?.name === "NotAllowedError" ? "Allow the camera for this site to scan QR codes." : "Couldn't start the camera.";
    return;
  }
  const canvas = document.createElement("canvas");
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  scanTimer = setInterval(() => {
    const v = qrVideo.value;
    if (!v || v.readyState < 2 || state.value !== "scan") return;
    canvas.width = v.videoWidth;
    canvas.height = v.videoHeight;
    ctx.drawImage(v, 0, 0);
    const code = jsQR(ctx.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height);
    if (code?.data) onQr(code.data);
  }, 200);
}

function stopQr() {
  clearInterval(scanTimer);
  qrStream?.getTracks().forEach((t) => t.stop());
  qrStream = null;
}

async function onQr(text) {
  state.value = "starting";
  try {
    position.value = await currentPosition();
    session.value = await attendanceKiosk.start(text, position.value, mode.value === "enroll");
    stopQr();
    state.value = "face";
  } catch (e) {
    stopQr();
    showError(kioskMessage(e, "Couldn't start the check."));
  }
}

// ── After the face check ──

async function finish(payload) {
  state.value = "sending";
  try {
    const pos = await currentPosition();
    if (session.value.purpose === "enroll") {
      const res = await attendanceKiosk.enroll(session.value.session_id, payload, pos);
      showResult({ tone: "ok", title: "Face enrolled", text: res.message });
    } else {
      const r = await attendanceKiosk.complete(session.value.session_id, payload, pos);
      const notes = [];
      if (r.type === "time_in" && r.late_minutes) notes.push(`Late by ${minutes(r.late_minutes)}`);
      if (r.undertime_minutes) notes.push(`Left ${minutes(r.undertime_minutes)} early`);
      if (r.overtime_minutes) notes.push(`${minutes(r.overtime_minutes)} overtime — paid once HR approves it`);
      const what = (r.type === "time_in" ? `Timed in at ${r.time_in}` : `Timed out at ${r.time_out} · ${r.total_hours} h today`) + (notes.length ? `. ${notes.join(". ")}` : "");
      showResult(r.needs_review
        ? { tone: "warn", title: `${r.employee_name}: saved, needs review`, text: `${what}. A manager will check it before it counts for pay.`, reasons: r.reasons }
        : { tone: r.late_minutes && r.type === "time_in" ? "warn" : "ok", title: `${r.employee_name}`, text: what });
    }
  } catch (e) {
    showError(kioskMessage(e, "Couldn't save the attendance."));
  }
}

const minutes = (m) => (m >= 60 ? `${Math.floor(m / 60)} h${m % 60 ? ` ${m % 60} min` : ""}` : `${m} min`);

function showError(message) {
  showResult({ tone: "error", title: "Not recorded", text: message });
}

function showResult(r) {
  result.value = r;
  state.value = "result";
  autoReset.value = r.tone === "error" ? 0 : 8;
  clearInterval(resetTimer);
  if (autoReset.value) {
    resetTimer = setInterval(() => {
      autoReset.value--;
      if (autoReset.value <= 0) reset();
    }, 1000);
  }
}

function reset() {
  clearInterval(resetTimer);
  autoReset.value = 0;
  session.value = null;
  result.value = null;
  check();
}

watch(mode, () => {
  if (state.value === "scan") cameraError.value = "";
});

onMounted(check);
onBeforeUnmount(() => {
  stopQr();
  clearInterval(resetTimer);
});
</script>

<style scoped>
.ks {
  max-width: 640px;
  margin: 0 auto;
}
.ks-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 24px 0;
}
.ks-sk {
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: 18px;
}
.ks-request {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 14px;
  max-width: 420px;
  margin: 0 auto;
  padding: 12px 0;
  text-align: center;
}
.ks-request .sp-empty-icon {
  margin: 0 auto;
}
.ks-request h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 600;
}
.ks-request p {
  margin: 0;
  font-size: 13px;
  color: var(--v-muted);
}
.ks-request .sp-field {
  text-align: left;
}
.ks-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
}
.ks-loc {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--v-muted);
}
.ks-loc :deep(svg) {
  width: 14px;
  height: 14px;
}
.ks-camera {
  position: relative;
  overflow: hidden;
  aspect-ratio: 4 / 3;
  border-radius: 18px;
  background: #0b0f14;
}
.ks-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1);
}
.ks-frame {
  position: absolute;
  inset: 22% 28%;
}
.ks-frame i {
  position: absolute;
  width: 28px;
  height: 28px;
  border: 4px solid #fff;
}
.ks-frame i:nth-child(1) { top: 0; left: 0; border-right: 0; border-bottom: 0; border-radius: 10px 0 0 0; }
.ks-frame i:nth-child(2) { top: 0; right: 0; border-left: 0; border-bottom: 0; border-radius: 0 10px 0 0; }
.ks-frame i:nth-child(3) { bottom: 0; left: 0; border-right: 0; border-top: 0; border-radius: 0 0 0 10px; }
.ks-frame i:nth-child(4) { bottom: 0; right: 0; border-left: 0; border-top: 0; border-radius: 0 0 10px 0; }
.ks-cover {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(11, 15, 20, 0.7);
  color: #e6eaf0;
  font-size: 13px;
  text-align: center;
}
.ks-tip {
  margin: 12px 0 0;
  font-size: 13px;
  color: var(--v-muted);
  text-align: center;
}
.ks-result {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 18px 0;
  text-align: center;
}
.ks-result h3 {
  margin: 0;
  font-size: 19px;
  font-weight: 600;
}
.ks-result p {
  margin: 0;
  max-width: 440px;
  font-size: 13.5px;
  color: var(--v-muted);
}
.ks-result-icon {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 50%;
}
.ks-result-icon :deep(svg) {
  width: 30px;
  height: 30px;
}
.ks-result.ok .ks-result-icon { background: var(--v-green-soft); color: var(--v-green-text); }
.ks-result.warn .ks-result-icon { background: var(--v-amber-soft); color: var(--v-amber-text); }
.ks-result.error .ks-result-icon { background: var(--v-red-soft); color: var(--v-red-text); }
.ks-reasons {
  margin: 0;
  padding: 10px 14px 10px 28px;
  border-radius: 12px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
  font-size: 12.5px;
  text-align: left;
}
</style>
