<template>
  <div class="rq">
    <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="load">Try again</button></p>

    <section class="sp-stats rq-stats" aria-label="Review summary">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </template>
      <template v-else>
        <button v-for="t in TABS" :key="t.key" type="button" class="sp-stat" :class="{ on: tab === t.key }" @click="tab = t.key">
          <span class="sp-stat-icon" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
          <div><strong>{{ data[t.key].length }}</strong><span>{{ t.label }}</span></div>
        </button>
      </template>
    </section>

    <p v-if="!canDecide && data" class="sp-banner info rq-note"><span v-html="ICONS.lock"></span>You can see these, but approving needs the attendance "approve" permission.</p>

    <section class="sp-card">
      <ul v-if="!data" class="rq-sk">
        <li v-for="n in 3" :key="n"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></li>
      </ul>

      <!-- Overtime -->
      <template v-else-if="tab === 'overtime'">
        <div v-if="!data.overtime.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.clock"></span>
          <h3>No overtime waiting</h3>
          <p>When someone times out after their shift ends, the extra time waits here. It's paid at the overtime rate only after it's approved.</p>
        </div>
        <article v-for="o in data.overtime" :key="o.id" class="rq-item">
          <header class="rq-head">
            <div>
              <strong>{{ o.employee }}</strong>
              <small>{{ formatDate(o.date) }} · scheduled {{ o.scheduled ? range12(o.scheduled) : "—" }} · worked {{ time12(o.time_in) }} – {{ time12(o.time_out) }}</small>
            </div>
            <span class="sp-chip violet">{{ minutesText(o.minutes) }} overtime</span>
          </header>
          <p v-if="o.scan_needs_review" class="rq-why">This day's scan also needs review under Flagged scans.</p>
          <div v-if="canDecide" class="rq-actions">
            <label class="rq-minutes">
              <span>Approve</span>
              <input v-model.number="otMinutes[o.id]" type="number" min="1" :max="o.minutes" class="sp-input" :aria-label="`Minutes to approve for ${o.employee}`" />
              <span>of {{ o.minutes }} min</span>
            </label>
            <input v-model="notes[`o${o.id}`]" class="sp-input rq-note-input" maxlength="500" placeholder="Note (needed to reject)" :aria-label="`Note for ${o.employee}`" />
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy === `o${o.id}`" @click="decide('overtime', o, 'reject')">Reject</button>
            <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === `o${o.id}`" @click="decide('overtime', o, 'approve')">Approve</button>
          </div>
        </article>
      </template>

      <!-- Flagged scans -->
      <template v-else-if="tab === 'records'">
        <div v-if="!data.records.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.check"></span>
          <h3>Nothing to review</h3>
          <p>Scans that look doubtful — a borderline face match, a weak live-face check, a face that isn't enrolled yet — show up here.</p>
        </div>
        <article v-for="r in data.records" :key="r.id" class="rq-item">
          <header class="rq-head">
            <div>
              <strong>{{ r.employee }}</strong>
              <small>{{ formatDate(r.date) }} · in {{ r.time_in ? time12(r.time_in) : "—" }}<template v-if="r.time_out"> · out {{ time12(r.time_out) }}</template></small>
            </div>
            <span class="sp-chip amber">Needs review</span>
          </header>
          <ul class="rq-reasons">
            <li v-for="reason in r.reasons" :key="reason">{{ reason }}</li>
          </ul>
          <div v-for="which in ['time_in', 'time_out']" :key="which">
            <dl v-if="r.evidence[which]" class="rq-facts">
              <div><dt>{{ which === "time_in" ? "Time in" : "Time out" }}</dt><dd>{{ r.evidence[which].device || "—" }}<template v-if="r.evidence[which].operator"> · by {{ r.evidence[which].operator }}</template></dd></div>
              <div><dt>Face match</dt><dd>{{ matchText(r.evidence[which].face_distance) }}</dd></div>
              <div><dt>Live-face</dt><dd>{{ pct(r.evidence[which].liveness) }} · screen check {{ pct(r.evidence[which].antispoof) }}</dd></div>
              <div><dt>Color flashes</dt><dd>{{ r.evidence[which].flash ?? "—" }} of 4</dd></div>
              <div><dt>From the shop</dt><dd>{{ r.evidence[which].distance_m ?? "—" }} m</dd></div>
            </dl>
          </div>
          <div v-if="photos[r.id]" class="rq-photos">
            <div v-if="photos[r.id] === 'loading'" class="bc-skeleton rq-photo"></div>
            <img v-for="(url, i) in photos[r.id] === 'loading' ? [] : photos[r.id]" :key="i" :src="url" class="rq-photo" alt="Scan photo" />
          </div>
          <div class="rq-actions">
            <button v-if="(r.photo_count.time_in + r.photo_count.time_out) && !photos[r.id]" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="showPhotos(r)"><span v-html="ICONS.image"></span>See photos</button>
            <template v-if="canDecide">
              <input v-model="notes[`r${r.id}`]" class="sp-input rq-note-input" maxlength="500" placeholder="Note (needed to reject)" :aria-label="`Note for ${r.employee}`" />
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy === `r${r.id}`" @click="decide('record', r, 'reject')">Reject</button>
              <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === `r${r.id}`" @click="decide('record', r, 'approve')">Approve</button>
            </template>
          </div>
        </article>
      </template>

      <!-- Corrections -->
      <template v-else-if="tab === 'corrections'">
        <div v-if="!data.corrections.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.edit"></span>
          <h3>No corrections waiting</h3>
          <p>When HR asks to fix or add someone's times, the request waits here for someone else to approve.</p>
        </div>
        <article v-for="c in data.corrections" :key="c.id" class="rq-item">
          <header class="rq-head">
            <div>
              <strong>{{ c.employee }}</strong>
              <small>{{ formatDate(c.date) }} · asked by {{ c.requested_by }} {{ relativeTime(c.requested_at) }}</small>
            </div>
            <span class="sp-chip blue">Correction</span>
          </header>
          <div class="rq-change">
            <span>{{ c.current ? `${c.current.time_in ? time12(c.current.time_in) : "—"} – ${c.current.time_out ? time12(c.current.time_out) : "—"}` : "No record" }}</span>
            <span v-html="ICONS.right" aria-hidden="true"></span>
            <b>{{ time12(c.time_in) }} – {{ c.time_out ? time12(c.time_out) : "no time out" }}</b>
          </div>
          <p class="rq-why">“{{ c.reason }}”</p>
          <div v-if="canDecide" class="rq-actions">
            <input v-model="notes[`c${c.id}`]" class="sp-input rq-note-input" maxlength="500" placeholder="Note (needed to reject)" :aria-label="`Note for ${c.employee}`" />
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy === `c${c.id}`" @click="decide('correction', c, 'reject')">Reject</button>
            <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy === `c${c.id}`" @click="decide('correction', c, 'approve')">Approve</button>
          </div>
        </article>
      </template>

      <!-- Blocked attempts -->
      <template v-else>
        <div v-if="!data.blocked.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.shield"></span>
          <h3>No blocked attempts</h3>
          <p>Scans the system refused in the last 14 days — wrong face, a photo or screen, checks done too fast — are listed here.</p>
        </div>
        <div v-else class="st-scroll">
          <table class="st-table cards">
            <thead><tr><th>When</th><th>For</th><th>Device</th><th>Why it was refused</th></tr></thead>
            <tbody>
              <tr v-for="b in data.blocked" :key="b.id">
                <td data-label="When">{{ formatDate(b.at, true) }}</td>
                <td data-label="For"><strong>{{ b.employee }}</strong><small class="rq-sub">{{ PURPOSE[b.purpose] ?? b.purpose }}<template v-if="b.operator"> · scanned by {{ b.operator }}</template></small></td>
                <td data-label="Device">{{ b.device || "—" }}</td>
                <td data-label="Why">{{ b.reason || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import { SHOP_ICONS as ICONS } from "../../vendor/shop/shopIcons";
import { formatDate } from "../../vendor/finance/financeUi";
import { range12, relativeTime, time12 } from "../../vendor/staff/staffUi";

const props = defineProps({
  // { load(), decide(id, decision, note), decideCorrection(id, decision, note), evidence(id, which, index) }
  api: { type: Object, required: true },
  canDecide: { type: Boolean, default: true },
});
const emit = defineEmits(["changed"]);

const TABS = [
  { key: "overtime", label: "Overtime to approve", tone: "violet", icon: "clock" },
  { key: "records", label: "Flagged scans", tone: "amber", icon: "alert" },
  { key: "corrections", label: "Corrections waiting", tone: "blue", icon: "edit" },
  { key: "blocked", label: "Blocked attempts (14 days)", tone: "red", icon: "shield" },
];
const PURPOSE = { time_in: "Time in", time_out: "Time out", enroll: "Face enrollment" };

const data = ref(null);
const error = ref("");
const tab = ref("records");
const notes = reactive({});
const busy = ref(null);
const photos = reactive({});
const otMinutes = reactive({});
const minutesText = (m) => (m >= 60 ? `${Math.floor(m / 60)} h${m % 60 ? ` ${m % 60} min` : ""}` : `${m} min`);

const pct = (v) => (v === null || v === undefined ? "—" : `${Math.round(v * 100)}%`);
const matchText = (d) => (d === null || d === undefined ? "—" : d <= 0.45 ? `strong (${d})` : d <= 0.55 ? `borderline (${d})` : `weak (${d})`);

async function load() {
  error.value = "";
  try {
    data.value = await props.api.load();
    data.value.overtime ??= [];
    for (const o of data.value.overtime) otMinutes[o.id] ??= o.minutes;
    // Open on whatever is waiting
    tab.value = TABS.find((t) => data.value[t.key]?.length)?.key ?? tab.value;
  } catch (e) {
    error.value = e?.response?.data?.message || "Couldn't load the review queue.";
  }
}

async function showPhotos(r) {
  photos[r.id] = "loading";
  try {
    const jobs = [];
    for (const which of ["time_in", "time_out"]) for (let i = 0; i < r.photo_count[which]; i++) jobs.push(props.api.evidence(r.id, which, i));
    photos[r.id] = await Promise.all(jobs);
  } catch (e) {
    delete photos[r.id];
    toast.error(e?.response?.data?.message || "Couldn't open the photos.");
  }
}

const KIND = { record: { key: "r", list: "records" }, correction: { key: "c", list: "corrections" }, overtime: { key: "o", list: "overtime" } };

async function decide(kind, item, decision) {
  const key = `${KIND[kind].key}${item.id}`;
  const note = (notes[key] ?? "").trim();
  if (decision === "reject" && !note) {
    toast.info("Add a note saying why you're rejecting it.");
    return;
  }
  busy.value = key;
  try {
    const res = kind === "record"
      ? await props.api.decide(item.id, decision, note)
      : kind === "correction"
        ? await props.api.decideCorrection(item.id, decision, note)
        : await props.api.decideOvertime(item.id, decision, decision === "approve" ? (otMinutes[item.id] || item.minutes) : null, note);
    toast.success(res.message);
    const list = KIND[kind].list;
    data.value[list] = data.value[list].filter((x) => x.id !== item.id);
    emit("changed");
  } catch (e) {
    const errors = e?.response?.data?.errors;
    toast.error((errors && Object.values(errors).flat()[0]) || e?.response?.data?.message || "Couldn't save the decision.");
  } finally {
    busy.value = null;
  }
}

onMounted(load);
defineExpose({ load });
</script>

<style scoped>
.rq-stats {
  margin-bottom: 16px;
}
.rq-stats .grow {
  flex: 1;
}
.rq-note {
  margin-bottom: 14px;
}
.rq-sk {
  display: flex;
  flex-direction: column;
  gap: 18px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.rq-item {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 0;
  border-top: 1px solid var(--v-border);
}
.rq-item:first-of-type {
  padding-top: 0;
  border-top: 0;
}
.rq-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}
.rq-head strong {
  display: block;
  font-size: 14.5px;
  font-weight: 600;
  color: var(--v-text);
}
.rq-head small,
.rq-sub {
  display: block;
  font-size: 12.5px;
  color: var(--v-muted);
}
.rq-reasons {
  margin: 0;
  padding: 10px 14px 10px 28px;
  border-radius: 12px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
  font-size: 12.5px;
}
.rq-facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 8px 14px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.rq-facts dt {
  font-size: 11px;
  color: var(--v-faint);
}
.rq-facts dd {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-text);
}
.rq-photos {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rq-photo {
  width: 150px;
  height: 112px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--v-border);
}
.rq-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
.rq-minutes {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: var(--v-muted);
}
.rq-minutes .sp-input {
  width: 76px;
  padding: 7px 8px;
}
.rq-note-input {
  flex: 1 1 220px;
  max-width: 360px;
  padding: 7px 10px;
}
.rq-change {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 13px;
  color: var(--v-muted);
}
.rq-change :deep(svg) {
  width: 14px;
  height: 14px;
}
.rq-change b {
  color: var(--v-text);
  font-weight: 600;
}
.rq-why {
  margin: 0;
  font-size: 13px;
  font-style: italic;
  color: var(--v-text-2);
}
</style>
