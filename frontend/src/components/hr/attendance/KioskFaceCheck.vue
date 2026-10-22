<template>
  <div class="kf">
    <div class="kf-top">
      <div class="st-person">
        <span class="st-avatar" :style="{ background: personColor(session.employee.name) }">{{ initialsOf(session.employee.name) }}</span>
        <div>
          <strong>{{ session.employee.name }}</strong>
          <small>{{ heading }}</small>
        </div>
      </div>
      <span class="kf-timer" :class="{ low: secondsLeft <= 20 }" :aria-label="`${secondsLeft} seconds left`">{{ secondsLeft }}s</span>
    </div>

    <div class="kf-stage">
      <video ref="video" class="kf-video" playsinline muted></video>
      <div class="kf-oval" :class="ovalTone" aria-hidden="true"></div>
      <div v-if="phase === 'loading'" class="kf-cover">
        <div class="kf-bar"><i :style="{ width: `${Math.round(progress * 100)}%` }"></i></div>
        <span>Getting the face check ready…</span>
      </div>
      <div v-if="direction" class="kf-arrow" :class="direction" aria-hidden="true">{{ direction === "left" ? "←" : "→" }}</div>
    </div>

    <ol class="kf-steps" aria-label="Checks">
      <li v-for="(id, i) in session.challenges" :key="i" :class="{ on: phase === 'challenge' && step === i, done: doneSteps > i }">
        {{ CHALLENGES[id]?.label ?? id }}
      </li>
      <li :class="{ on: phase === 'flash', done: ['capture', 'sending'].includes(phase) }">Color check</li>
    </ol>

    <div class="kf-say" role="status" aria-live="polite">
      <strong>{{ say.title }}</strong>
      <span>{{ say.hint }}</span>
    </div>

    <div class="kf-foot">
      <button type="button" class="sp-btn sp-btn-ghost" @click="cancel">Cancel</button>
    </div>

    <Teleport to="body">
      <div v-if="flashColor" class="kf-flash" :style="{ background: flashColor }" aria-hidden="true"></div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { CHALLENGES, FLASH_RGB, averageDescriptor, colorDelta, createChallengeJudge, descriptorDistance } from "./attendanceMath";
import { faceColor, loadModels, photoDescriptor, readFrame, snapshot, spoofScores, startCamera, wait } from "./faceEngine";
import { personColor } from "../../vendor/staff/staffUi";
import { initialsOf } from "../hrUi";

const props = defineProps({
  // What the server handed out for this scan: challenges, flash colors, employee, purpose
  session: { type: Object, required: true },
});
const emit = defineEmits(["done", "fail", "cancel"]);

const CHALLENGE_LIMIT_MS = 9500;
const POSITION_FRAMES = 8;

const video = ref(null);
const phase = ref("loading");
const progress = ref(0);
const step = ref(0);
const doneSteps = ref(0);
const say = ref({ title: "One moment", hint: "Loading the face check." });
const ovalTone = ref("");
const direction = ref(null);
const flashColor = ref(null);
const secondsLeft = ref(90);

const enrolling = computed(() => props.session.purpose === "enroll");
const heading = computed(() => (enrolling.value ? "Enrolling face" : props.session.purpose === "time_in" ? "Timing in" : "Timing out"));

let cam = null;
let stopped = false;
let countdown = null;

const ensure = () => {
  if (stopped) throw Object.assign(new Error("stopped"), { stopped: true });
};

function cancel() {
  stop();
  emit("cancel");
}

function stop() {
  stopped = true;
  clearInterval(countdown);
  flashColor.value = null;
  cam?.stop();
}

/** Wait for one face, centred and close enough, held still for a moment. */
async function position() {
  phase.value = "position";
  say.value = { title: "Look at the camera", hint: "Fit your face in the oval. Only one person in view." };
  const held = [];
  while (held.length < POSITION_FRAMES) {
    ensure();
    const f = await readFrame(video.value);
    if (!f || f.many) {
      held.length = 0;
      ovalTone.value = f?.many ? "bad" : "";
      say.value = f?.many
        ? { title: "Only one person, please", hint: "Others should step out of the camera's view." }
        : { title: "Look at the camera", hint: "Fit your face in the oval." };
      await wait(120);
      continue;
    }
    const cx = f.box.x + f.box.width / 2;
    const centred = Math.abs(cx - f.videoWidth / 2) < f.videoWidth * 0.18;
    const near = f.width > f.videoWidth * 0.22;
    if (!centred || !near) {
      held.length = 0;
      ovalTone.value = "warn";
      say.value = { title: near ? "Move to the middle" : "Come a little closer", hint: "Fit your face in the oval." };
      await wait(100);
      continue;
    }
    ovalTone.value = "good";
    say.value = { title: "Hold still", hint: "Checking your face…" };
    held.push(f);
    await wait(80);
  }
  const mean = (k) => held.reduce((a, f) => a + f[k], 0) / held.length;
  return { width: mean("width"), yaw: mean("yaw"), box: held.at(-1).box };
}

/** The server's challenges, in the server's order, each timed. */
async function challenges(baseline) {
  phase.value = "challenge";
  const results = [];
  for (const [i, id] of props.session.challenges.entries()) {
    step.value = i;
    const info = CHALLENGES[id] ?? { label: id, hint: "" };
    say.value = { title: info.label, hint: info.hint };
    direction.value = id === "turn_left" ? "left" : id === "turn_right" ? "right" : null;
    const judge = createChallengeJudge(id, baseline);
    const started = performance.now();
    let passed = false;
    while (!passed) {
      ensure();
      if (performance.now() - started > CHALLENGE_LIMIT_MS) {
        throw new Error(`"${info.label}" wasn't done in time. Scan the QR code to try again.`);
      }
      const f = await readFrame(video.value);
      if (f?.many) throw new Error("More than one face appeared. Scan the QR code to try again with only one person in view.");
      if (!f) {
        ovalTone.value = "warn";
        continue;
      }
      ovalTone.value = "good";
      passed = judge(f);
    }
    const duration = Math.max(400, Math.round(performance.now() - started));
    results.push({ id, passed: true, duration_ms: duration });
    doneSteps.value = i + 1;
    direction.value = null;
    say.value = { title: "Good", hint: "Next one…" };
    await wait(450);
  }
  return results;
}

/** Flash the server's colors and measure how the face reflects each one. */
async function flash() {
  phase.value = "flash";
  say.value = { title: "Hold still, look at the screen", hint: "The screen will flash a few colors." };
  await wait(700);
  const samples = [];
  for (const color of props.session.flash) {
    ensure();
    const f = await readFrame(video.value);
    if (!f || f.many) {
      samples.push({ color, delta: null });
      continue;
    }
    const before = faceColor(video.value, f.box);
    const [r, g, b] = FLASH_RGB[color];
    flashColor.value = `rgb(${r}, ${g}, ${b})`;
    await wait(260);
    const during1 = faceColor(video.value, f.box);
    await wait(140);
    const during2 = faceColor(video.value, f.box);
    flashColor.value = null;
    samples.push({ color, delta: colorDelta(before, during1.map((v, i) => (v + during2[i]) / 2)) });
    await wait(300);
  }
  return samples;
}

/** Descriptors for matching, anti-spoof scores, and evidence photos. */
async function capture(count) {
  phase.value = "capture";
  say.value = { title: enrolling.value ? "Hold still for the photos" : "Hold still", hint: enrolling.value ? "Taking a few pictures of your face." : "Reading your face." };
  const frames = [];
  let tries = 0;
  while (frames.length < count && tries < count * 4) {
    ensure();
    tries++;
    const f = await readFrame(video.value, { descriptor: true });
    if (f?.many) throw new Error("More than one face appeared. Scan the QR code to try again.");
    if (f?.descriptor) frames.push(f);
    await wait(enrolling.value ? 420 : 120);
  }
  if (frames.length < Math.min(count, 3)) throw new Error("Couldn't see your face clearly. Use better light and scan again.");
  const scores = await spoofScores(video.value);
  return { frames, scores };
}

async function run() {
  try {
    countdown = setInterval(() => {
      secondsLeft.value = Math.max(0, Math.round((new Date(props.session.expires_at) - Date.now()) / 1000));
      if (secondsLeft.value === 0 && !stopped) {
        stop();
        emit("fail", "Time ran out. Scan the QR code again.");
      }
    }, 500);

    const photoRef = !enrolling.value && !props.session.enrolled && props.session.employee.avatar_url
      ? loadModels((p) => (progress.value = p)).then(() => photoDescriptor(props.session.employee.avatar_url))
      : null;
    photoRef?.catch(() => {});
    await loadModels((p) => (progress.value = p));
    ensure();
    cam = await startCamera(video.value);

    const baseline = await position();
    const snaps = [snapshot(video.value)];
    const challengeResults = await challenges(baseline);
    snaps.push(snapshot(video.value));
    const flashSamples = enrolling.value ? [] : await flash();
    const { frames, scores } = await capture(enrolling.value ? 5 : 6);
    snaps.push(snapshot(video.value));

    phase.value = "sending";
    say.value = { title: "Checking…", hint: "Almost done." };
    const common = { challenge_results: challengeResults, antispoof: scores.antispoof, liveness: scores.liveness, camera: cam.camera };

    if (enrolling.value) {
      emit("done", { ...common, descriptors: frames.slice(0, 5).map((f) => f.descriptor), snapshots: snaps });
    } else {
      const best = [...frames].sort((a, b) => b.score - a.score).slice(0, 3);
      const descriptor = averageDescriptor(best.map((f) => f.descriptor));
      let photoDistance = null;
      if (photoRef) photoDistance = Math.round(descriptorDistance(descriptor, await photoRef) * 1000) / 1000;
      emit("done", { ...common, descriptor: props.session.enrolled ? descriptor : null, photo_distance: photoDistance, flash: flashSamples, snapshots: snaps });
    }
    stop();
  } catch (e) {
    if (e?.stopped) return;
    stop();
    emit("fail", e?.name === "NotAllowedError" ? "Allow the camera for this site, then scan again." : e?.message || "The face check stopped. Scan again.");
  }
}

onMounted(run);
onBeforeUnmount(stop);
</script>

<style scoped>
.kf {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.kf-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.kf-timer {
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--v-subtle);
  font-size: 12.5px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: var(--v-text-2);
}
.kf-timer.low {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.kf-stage {
  position: relative;
  overflow: hidden;
  aspect-ratio: 4 / 3;
  border-radius: 18px;
  background: #0b0f14;
}
.kf-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1); /* a mirror, so "left" is the person's left on screen too */
}
.kf-oval {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 46%;
  height: 72%;
  transform: translate(-50%, -50%);
  border: 3px dashed rgba(255, 255, 255, 0.55);
  border-radius: 50%;
  box-shadow: 0 0 0 999px rgba(11, 15, 20, 0.35);
  transition: border-color 0.2s;
}
.kf-oval.good {
  border-style: solid;
  border-color: #48bb78;
}
.kf-oval.warn {
  border-color: #f6ad55;
}
.kf-oval.bad {
  border-color: #fc8181;
}
.kf-cover {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: #0b0f14;
  color: #e6eaf0;
  font-size: 13px;
}
.kf-bar {
  width: min(240px, 70%);
  height: 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.15);
  overflow: hidden;
}
.kf-bar i {
  display: block;
  height: 100%;
  background: var(--bc-accent);
  transition: width 0.3s;
}
.kf-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  font-size: 54px;
  font-weight: 700;
  color: #fff;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
  animation: kf-nudge 0.9s ease-in-out infinite;
}
.kf-arrow.left {
  left: 6%;
}
.kf-arrow.right {
  right: 6%;
}
@keyframes kf-nudge {
  50% {
    transform: translateY(-50%) scale(1.15);
  }
}
.kf-steps {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.kf-steps li {
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--v-subtle);
  font-size: 12px;
  color: var(--v-muted);
}
.kf-steps li.on {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.kf-steps li.done {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.kf-say {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-height: 46px;
  text-align: center;
}
.kf-say strong {
  font-size: 18px;
  font-weight: 600;
  color: var(--v-text);
}
.kf-say span {
  font-size: 13px;
  color: var(--v-muted);
}
.kf-foot {
  display: flex;
  justify-content: center;
}
.kf-flash {
  position: fixed;
  inset: 0;
  z-index: 3000;
}
@media (prefers-reduced-motion: reduce) {
  .kf-arrow {
    animation: none;
  }
}
</style>
