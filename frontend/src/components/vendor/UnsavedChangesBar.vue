<template>
  <Teleport to="body">
    <Transition name="ucb">
      <div v-if="show" ref="bar" class="ucb" :class="{ alert: state.blocked, shake, admin: isAdmin }" role="region" aria-label="Unsaved changes">
        <span class="ucb-title" :role="state.blocked ? 'alert' : null">
          {{ state.blocked ? "Save or discard your changes first" : "Unsaved changes" }}
        </span>

        <!-- View changes: up and down on one vertical line, each jump highlights the field -->
        <div class="ucb-view">
          <span class="ucb-count" aria-live="polite">
            View changes
            <small>{{ index >= 0 ? `${index + 1} of ${changes.length}` : changes.length }}</small>
          </span>
          <div class="ucb-arrows">
            <button type="button" aria-label="Go to the previous change" title="Previous change" @click="step(-1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 14l-5-5l-5 5" /></svg>
            </button>
            <button type="button" aria-label="Go to the next change" title="Next change" @click="step(1)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 10l-5 5l-5-5" /></svg>
            </button>
          </div>
        </div>

        <span class="ucb-sep" aria-hidden="true"></span>
        <button type="button" class="ucb-btn" :disabled="saving" @click="discard">Discard</button>
        <span class="ucb-sep" aria-hidden="true"></span>
        <button type="button" class="ucb-btn ucb-save" :disabled="saving" @click="save">
          {{ saving ? "Saving…" : owner.saveLabel }}
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
// The save bar for vendor pages, the same as the customer profile's
// (see composables/useUnsavedChanges.js). Mounted once in App.vue; it shows
// whenever the current page has edits, and is the only Save / Discard there.
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { discardUnsaved, saveUnsaved, unsavedState as state } from "../../composables/useUnsavedChanges";
import { toast } from "@/utils/snackbar";

const bar = ref(null);
const index = ref(-1); // the change last jumped to; -1 before the first jump
const shake = ref(false);
let flashed = null;
let flashTimer = 0;

const owner = computed(() => state.owner);
const changes = computed(() => owner.value?.changes.value ?? []);
const show = computed(() => !!owner.value && changes.value.length > 0);
const saving = computed(() => !!owner.value?.saving.value);
const isAdmin = computed(() => owner.value?.look === "admin");

const reduced = () => window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

function find(change) {
  const sel = [change.target, `[data-change~="${CSS.escape(change.key)}"]`, change.fallback].filter(Boolean);
  for (const s of sel) {
    const el = document.querySelector(s);
    if (el && el.getClientRects().length) return el;
  }
  return null;
}
function unflash() {
  clearTimeout(flashTimer);
  flashed?.classList.remove("bc-change-focus", "bc-change-focus--admin");
  flashed = null;
}

/** Jump to the previous or next change and highlight it for a moment */
function step(dir) {
  const list = changes.value;
  if (!list.length) return;
  const now = index.value < list.length ? index.value : -1;
  index.value = now < 0 ? (dir > 0 ? 0 : list.length - 1) : (now + dir + list.length) % list.length;
  const el = find(list[index.value]);
  if (!el) return;
  el.scrollIntoView({ behavior: reduced() ? "auto" : "smooth", block: "center" });
  unflash();
  requestAnimationFrame(() => {
    el.classList.add("bc-change-focus");
    if (isAdmin.value) el.classList.add("bc-change-focus--admin");
    flashed = el;
    flashTimer = setTimeout(unflash, 1800);
  });
}

async function save() {
  unflash();
  await saveUnsaved();
}
function discard() {
  unflash();
  discardUnsaved();
  toast.info("Changes discarded.");
}

// The list changed (a field edited or put back): start counting again
watch(() => changes.value.map((c) => c.key).join("|"), () => (index.value = -1));
watch(owner, () => {
  index.value = -1;
  unflash();
});

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
    ro = new ResizeObserver(() => bar.value && root.style.setProperty("--bc-unsaved-offset", `${bar.value.offsetHeight + 12}px`));
    ro.observe(bar.value);
  },
  { flush: "post" },
);
onBeforeUnmount(() => {
  ro?.disconnect();
  unflash();
  root.style.removeProperty("--bc-unsaved-offset");
});
</script>

<style scoped>
.ucb {
  --ucb-accent: var(--bc-accent, #4db173);
  --ucb-accent-ink: var(--bc-accent-ink, #fff);
  position: fixed;
  z-index: 9990;
  left: 50%;
  bottom: calc(24px + env(safe-area-inset-bottom));
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  width: max-content;
  max-width: calc(100vw - 32px);
  padding: 6px 6px 6px 18px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-raised);
  color: var(--v-text);
  box-shadow: 0 12px 32px var(--v-shadow), 0 2px 6px var(--v-shadow);
  font-family: var(--font-sans, "Poppins", system-ui, sans-serif);
  transform: translateX(-50%);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
/* Admin pages: the admin panel tokens and theme color */
.ucb.admin {
  --ucb-accent: var(--a-accent);
  --ucb-accent-ink: var(--a-accent-ink);
  border-color: var(--a-border);
  background: var(--a-surface);
  color: var(--a-text);
}
.ucb.admin .ucb-count {
  color: var(--a-muted);
}
.ucb.admin .ucb-count small,
.ucb.admin .ucb-arrows button {
  color: var(--a-text);
}
.ucb.admin .ucb-arrows button:hover,
.ucb.admin .ucb-btn:hover:not(:disabled) {
  background: var(--a-subtle-2);
}
.ucb.admin .ucb-arrows button:hover {
  color: var(--a-accent);
}
.ucb.admin .ucb-sep {
  background: var(--a-border);
}
.ucb.admin .ucb-btn {
  color: var(--a-text-2, var(--a-text));
}
.ucb.admin .ucb-save {
  background: var(--a-accent);
  color: var(--a-accent-ink);
}
.ucb.admin .ucb-save:hover:not(:disabled) {
  background: var(--a-accent-hover, var(--a-accent));
  color: var(--a-accent-ink);
}
.ucb.admin.alert {
  border-color: var(--a-red-text);
  box-shadow: 0 0 0 3px var(--a-red-soft), 0 12px 32px rgba(15, 23, 42, 0.18);
}
.ucb.admin.alert .ucb-title,
.ucb.admin.alert .ucb-title::before {
  color: var(--a-red-text);
}
.ucb.admin.alert .ucb-title::before {
  background: var(--a-red-text);
}
.ucb.admin .ucb-title::before {
  background: var(--a-amber-text);
}
.ucb.alert {
  border-color: var(--v-red-text);
  box-shadow: 0 0 0 3px var(--v-red-soft), 0 12px 32px var(--v-shadow);
}
.ucb-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
}
/* Waiting to be saved */
.ucb-title::before {
  content: "";
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--v-amber-text);
}
.ucb.alert .ucb-title {
  color: var(--v-red-text);
}
.ucb.alert .ucb-title::before {
  background: var(--v-red-text);
}
.ucb-view {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding-left: 6px;
}
.ucb-count {
  display: grid;
  line-height: 1.2;
  color: var(--v-muted);
  font-size: 12.5px;
  text-align: right;
}
.ucb-count small {
  color: var(--v-text);
  font-size: 12px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
/* Up above down */
.ucb-arrows {
  display: grid;
}
.ucb-arrows button {
  display: grid;
  place-items: center;
  width: 32px;
  height: 22px;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: none;
  color: var(--v-text);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.ucb-arrows button:hover {
  background: var(--v-subtle-2);
  color: var(--ucb-accent);
}
.ucb-arrows svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.ucb-sep {
  width: 1px;
  height: 24px;
  background: var(--v-border);
}
.ucb-btn {
  min-height: 40px;
  padding: 0 14px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--v-text-2);
  font: 600 13.5px var(--font-sans, "Poppins", system-ui, sans-serif);
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.ucb-btn:hover:not(:disabled) {
  background: var(--v-subtle-2);
  color: var(--v-text);
}
.ucb-save {
  background: var(--v-ink);
  color: var(--v-ink-text);
}
.ucb-save:hover:not(:disabled) {
  background: var(--ucb-accent);
  color: var(--ucb-accent-ink);
}
.ucb-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.ucb button:focus-visible {
  outline: 2px solid var(--ucb-accent);
  outline-offset: 2px;
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
.ucb-enter-active,
.ucb-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.ucb-enter-from,
.ucb-leave-to {
  opacity: 0;
  transform: translate(-50%, 14px);
}
@media (max-width: 640px) {
  .ucb {
    bottom: calc(16px + env(safe-area-inset-bottom));
    justify-content: space-between;
    width: calc(100vw - 32px);
    padding: 8px 8px 8px 14px;
  }
  .ucb-btn {
    min-height: 44px;
  }
  .ucb-arrows button {
    width: 40px;
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
/* The field View changes jumped to, highlighted for a moment */
.bc-change-focus {
  --ucb-accent: var(--bc-accent, #4db173);
  border-radius: 12px;
  /* A tint over whatever the card's own background is */
  background-image: linear-gradient(color-mix(in srgb, var(--ucb-accent) 8%, transparent), color-mix(in srgb, var(--ucb-accent) 8%, transparent));
  box-shadow: 0 0 0 8px color-mix(in srgb, var(--ucb-accent) 8%, transparent),
    0 0 0 9px color-mix(in srgb, var(--ucb-accent) 35%, transparent);
  transition: box-shadow 0.3s ease;
  scroll-margin: 96px;
}
/* On admin pages the highlight follows the admin theme color */
.bc-change-focus.bc-change-focus--admin {
  --ucb-accent: var(--a-accent, #7c6ff7);
}
</style>
