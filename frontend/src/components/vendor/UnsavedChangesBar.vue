<template>
  <Teleport to="body">
    <Transition name="ucb">
      <div
        v-if="show"
        ref="bar"
        class="ucb"
        :class="{ viewing, alert: state.blocked, shake }"
        role="region"
        aria-label="Unsaved changes"
        @keydown="onKey"
      >
        <!-- Stepping through the changes -->
        <template v-if="viewing && current">
          <div class="ucb-steps" role="group" aria-label="Go through your changes">
            <button type="button" class="ucb-step" aria-label="Previous change" :disabled="saving" @click="step(-1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 14l-5-5l-5 5" /></svg>
            </button>
            <button type="button" class="ucb-step" aria-label="Next change" :disabled="saving" @click="step(1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 10l-5 5l-5-5" /></svg>
            </button>
          </div>
          <p class="ucb-text" aria-live="polite">
            <b>{{ current.label }}<em v-if="current.removed"> · removed</em></b>
            <small>Change {{ index + 1 }} of {{ changes.length }}</small>
          </p>
          <button type="button" class="ucb-btn ucb-ghost" @click="closeView">Done</button>
        </template>

        <!-- The summary -->
        <template v-else>
          <span class="ucb-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
          </span>
          <p class="ucb-text" :role="state.blocked ? 'alert' : null">
            <b>{{ state.blocked ? "Save or discard your changes first" : "You have unsaved changes" }}</b>
            <small>{{ changes.length }} {{ changes.length === 1 ? "change" : "changes" }} not saved yet</small>
          </p>
          <button type="button" class="ucb-btn ucb-ghost" :disabled="saving" @click="openView">View changes</button>
        </template>

        <span class="ucb-sep" aria-hidden="true"></span>
        <button type="button" class="ucb-btn ucb-ghost ucb-discard" :disabled="saving" @click="discard">Discard</button>
        <span class="ucb-sep" aria-hidden="true"></span>
        <button type="button" class="ucb-btn ucb-save" :disabled="saving" @click="save">
          {{ saving ? "Saving…" : owner.saveLabel }}
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
// The bar for unsaved changes on vendor pages (see composables/useUnsavedChanges.js).
// Mounted once in App.vue; it shows whenever the current page has edits.
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { discardUnsaved, saveUnsaved, unsavedState as state } from "../../composables/useUnsavedChanges";

const bar = ref(null);
const viewing = ref(false);
const index = ref(0);
const shake = ref(false);
let lit = null;

const owner = computed(() => state.owner);
const changes = computed(() => owner.value?.changes.value ?? []);
const show = computed(() => !!owner.value && changes.value.length > 0);
const saving = computed(() => !!owner.value?.saving.value);
const current = computed(() => changes.value[index.value] ?? null);

const reduced = () => window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

function find(change) {
  if (!change) return null;
  const sel = [change.target, `[data-change~="${CSS.escape(change.key)}"]`, change.fallback].filter(Boolean);
  for (const s of sel) {
    const el = document.querySelector(s);
    if (el && el.getClientRects().length) return el;
  }
  return null;
}
function unlight() {
  lit?.classList.remove("bc-change-focus");
  lit = null;
}
function light() {
  unlight();
  const el = find(current.value);
  if (!el) return;
  el.classList.remove("bc-change-focus");
  void el.offsetWidth; // restart the pulse
  el.classList.add("bc-change-focus");
  lit = el;
  el.scrollIntoView({ behavior: reduced() ? "auto" : "smooth", block: "center" });
}

function openView() {
  index.value = 0;
  viewing.value = true;
  nextTick(light);
}
function closeView() {
  viewing.value = false;
  unlight();
}
function step(dir) {
  const n = changes.value.length;
  if (!n) return;
  index.value = (index.value + dir + n) % n;
  light();
}
function onKey(e) {
  if (!viewing.value) return;
  if (e.key === "ArrowUp" || e.key === "ArrowDown") {
    e.preventDefault();
    step(e.key === "ArrowUp" ? -1 : 1);
  } else if (e.key === "Escape") {
    closeView();
  }
}
async function save() {
  unlight();
  await saveUnsaved();
}
function discard() {
  closeView();
  discardUnsaved();
}

// The list shrinks as edits are undone: stay on a valid change
watch(
  () => changes.value.map((c) => c.key).join("|"),
  () => {
    if (!changes.value.length) return closeView();
    if (index.value >= changes.value.length) index.value = changes.value.length - 1;
    if (viewing.value) nextTick(light);
  },
);
// A new page owns the bar
watch(owner, closeView);

// Something tried to leave: draw the eye to the bar
watch(
  () => state.attention,
  () => {
    shake.value = false;
    requestAnimationFrame(() => (shake.value = true));
    setTimeout(() => (shake.value = false), 600);
    nextTick(() => bar.value?.querySelector(".ucb-save")?.focus({ preventScroll: true }));
  },
);

// Lift the regular snackbars above this bar while it shows
let ro = null;
const root = document.documentElement;
watch(
  [show, bar],
  () => {
    ro?.disconnect();
    if (!show.value || !bar.value) {
      root.style.removeProperty("--bc-unsaved-offset");
      return;
    }
    ro = new ResizeObserver(() => root.style.setProperty("--bc-unsaved-offset", `${bar.value.offsetHeight + 12}px`));
    ro.observe(bar.value);
  },
  { flush: "post" },
);
onBeforeUnmount(() => {
  ro?.disconnect();
  unlight();
  root.style.removeProperty("--bc-unsaved-offset");
});
</script>

<style scoped>
.ucb {
  position: fixed;
  z-index: 9990;
  left: 50%;
  bottom: 24px;
  display: flex;
  align-items: center;
  gap: 6px;
  width: max-content;
  max-width: calc(100vw - 32px);
  padding: 8px 8px 8px 14px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
  background: var(--v-raised);
  color: var(--v-text);
  box-shadow: 0 14px 36px var(--v-shadow), 0 2px 6px var(--v-shadow);
  font-family: var(--font-sans, "Poppins", system-ui, sans-serif);
  transform: translateX(-50%);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.ucb.alert {
  border-color: var(--v-red-text);
  box-shadow: 0 0 0 3px var(--v-red-soft), 0 14px 36px var(--v-shadow);
}
.ucb-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.ucb.alert .ucb-icon {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.ucb svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.ucb-text {
  display: grid;
  min-width: 0;
  margin: 0 8px 0 4px;
  line-height: 1.3;
}
.ucb-text b {
  font-size: 14px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ucb-text b em {
  font-style: normal;
  font-weight: 500;
  color: var(--v-red-text);
}
.ucb-text small {
  font-size: 12px;
  color: var(--v-muted);
  font-variant-numeric: tabular-nums;
}
.ucb.alert .ucb-text b {
  color: var(--v-red-text);
}
/* Up above down, on one vertical line */
.ucb-steps {
  flex: none;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--v-subtle);
}
.ucb-step {
  display: grid;
  place-items: center;
  width: 36px;
  height: 22px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--v-text-2);
  cursor: pointer;
}
.ucb-step + .ucb-step {
  border-top: 1px solid var(--v-border);
}
.ucb-step:hover:not(:disabled) {
  background: var(--v-subtle-2);
  color: var(--v-text);
}
.ucb-step svg {
  width: 20px;
  height: 20px;
}
.ucb-sep {
  flex: none;
  width: 1px;
  height: 22px;
  background: var(--v-border);
}
.ucb-btn {
  flex: none;
  min-height: 38px;
  padding: 0 14px;
  border: 0;
  border-radius: 10px;
  font: inherit;
  font-size: 13.5px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.ucb-ghost {
  background: transparent;
  color: var(--v-text);
}
.ucb-ghost:hover:not(:disabled) {
  background: var(--v-subtle-2);
}
.ucb-discard {
  color: var(--v-red-text);
}
.ucb-discard:hover:not(:disabled) {
  background: var(--v-red-soft);
}
.ucb-save {
  background: var(--bc-accent, #4db173);
  color: var(--bc-accent-ink, #fff);
}
.ucb-save:hover:not(:disabled) {
  filter: brightness(0.95);
}
.ucb-btn:disabled,
.ucb-step:disabled {
  opacity: 0.55;
  cursor: default;
}
.ucb-btn:focus-visible,
.ucb-step:focus-visible {
  outline: 2px solid var(--bc-accent, #4db173);
  outline-offset: 2px;
}
.ucb-step:focus-visible {
  outline-offset: -2px;
}
.ucb.shake {
  animation: ucb-shake 0.45s ease;
}
@keyframes ucb-shake {
  0%, 100% { transform: translateX(-50%); }
  20% { transform: translateX(calc(-50% - 8px)); }
  40% { transform: translateX(calc(-50% + 8px)); }
  60% { transform: translateX(calc(-50% - 5px)); }
  80% { transform: translateX(calc(-50% + 5px)); }
}
.ucb-enter-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}
.ucb-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.ucb-enter-from,
.ucb-leave-to {
  opacity: 0;
  transform: translate(-50%, 16px);
}
@media (max-width: 640px) {
  .ucb {
    bottom: 16px;
    flex-wrap: wrap;
    width: calc(100vw - 32px);
    padding: 10px;
    row-gap: 8px;
  }
  /* The message fills the first row; the buttons share the second */
  .ucb-text {
    flex: 1 1 calc(100% - 60px);
  }
  .ucb-sep {
    display: none;
  }
  .ucb-btn {
    flex: 1 1 0;
    min-height: 44px;
    padding: 0 10px;
  }
  .ucb-step {
    width: 44px;
    height: 24px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .ucb.shake {
    animation: none;
  }
  .ucb-enter-active,
  .ucb-leave-active {
    transition: opacity 0.15s ease;
  }
  .ucb-enter-from,
  .ucb-leave-to {
    transform: translateX(-50%);
  }
}
</style>

<style>
/* The field or section View changes is pointing at */
.bc-change-focus {
  border-radius: 12px;
  outline: 2px solid var(--bc-accent, #4db173);
  outline-offset: 4px;
  box-shadow: 0 0 0 8px color-mix(in srgb, var(--bc-accent, #4db173) 16%, transparent);
  animation: bc-change-pulse 1.4s ease-out 1;
  scroll-margin: 96px;
}
@keyframes bc-change-pulse {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--bc-accent, #4db173) 45%, transparent); }
  100% { box-shadow: 0 0 0 8px color-mix(in srgb, var(--bc-accent, #4db173) 16%, transparent); }
}
@media (prefers-reduced-motion: reduce) {
  .bc-change-focus {
    animation: none;
  }
}
</style>
