<template>
  <Teleport to="body">
    <Transition name="nd-fade">
      <div v-if="notice" class="nd-backdrop" @click.self="close">
        <div class="nd-modal" role="dialog" aria-modal="true" aria-labelledby="nd-title" @keydown.esc="close">
          <header class="nd-head">
            <div>
              <p class="nd-meta">
                <span v-if="notice.important" class="nd-tag">Important</span>
                <time :datetime="notice.created_at">{{ when(notice.created_at) }}</time>
              </p>
              <h2 id="nd-title">{{ notice.title }}</h2>
            </div>
            <button ref="closeBtn" type="button" class="nd-close" aria-label="Close" @click="close">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </header>
          <p v-if="notice.body" class="nd-body">{{ notice.body }}</p>
          <p v-else class="nd-body nd-empty">There's nothing more to this notification.</p>
          <footer class="nd-foot">
            <button type="button" class="nd-btn nd-btn-ghost" @click="close">Close</button>
            <button v-if="notice.link_url" type="button" class="nd-btn nd-btn-primary" @click="go">Open</button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { notificationTarget } from "../vendor/crm/crmUi";

// The whole notification: title, full message, when, and a way to its page.
// Used by the vendor bell, the customer header and the vendor Notifications page.
const props = defineProps({ notice: { type: Object, default: null } });
const emit = defineEmits(["close"]);

const router = useRouter();
const closeBtn = ref(null);
let returnFocus = null;

watch(
  () => props.notice,
  async (n) => {
    if (!n) return;
    returnFocus = document.activeElement;
    await nextTick();
    closeBtn.value?.focus();
  },
);

function close() {
  emit("close");
  returnFocus?.focus?.();
}

function go() {
  const target = notificationTarget(props.notice.link_url);
  emit("close");
  if (typeof target === "string" && /^https?:\/\//i.test(target)) window.open(target, "_blank", "noopener");
  else if (target) router.push(target);
}

/** "Today, 3:42 PM", "Yesterday, 9:05 AM" or "Oct 20, 2026, 9:05 AM" */
function when(value) {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "";
  const time = d.toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" });
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return `Today, ${time}`;
  if (d.toDateString() === yesterday.toDateString()) return `Yesterday, ${time}`;
  return `${d.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" })}, ${time}`;
}
</script>

<style scoped>
/* Vendor theme tokens where they exist (they follow the vendor's dark mode), brand colors elsewhere */
.nd-backdrop {
  --nd-surface: var(--v-raised, #ffffff);
  --nd-text: var(--v-text, #1f2733);
  --nd-muted: var(--v-muted, #5d6675);
  --nd-border: var(--v-border, #dfe3e8);
  --nd-accent: var(--bc-accent, #363f4e);
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(20, 26, 34, 0.5);
}
.nd-modal {
  width: min(520px, 100%);
  max-height: calc(100dvh - 32px);
  overflow-y: auto;
  padding: 22px;
  border-radius: 18px;
  background: var(--nd-surface);
  color: var(--nd-text);
  font-family: inherit;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
}
.nd-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}
.nd-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 6px;
  font-size: 12.5px;
  color: var(--nd-muted);
}
.nd-tag {
  padding: 2px 8px;
  border-radius: 999px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 11px;
  font-weight: 700;
}
.nd-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.35;
  color: var(--nd-text);
  overflow-wrap: anywhere;
}
.nd-close {
  display: grid;
  flex: none;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--nd-border);
  border-radius: 12px;
  background: transparent;
  color: var(--nd-text);
  cursor: pointer;
}
.nd-close svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}
.nd-body {
  margin: 16px 0 0;
  font-size: 14.5px;
  line-height: 1.6;
  color: var(--nd-text);
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.nd-empty {
  color: var(--nd-muted);
}
.nd-foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 22px;
}
.nd-btn {
  min-height: 44px;
  padding: 0 20px;
  border: 1px solid transparent;
  border-radius: 12px;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.nd-btn-primary {
  background: var(--nd-accent);
  color: #fff;
}
.nd-btn-ghost {
  border-color: var(--nd-border);
  background: transparent;
  color: var(--nd-text);
}
.nd-close:focus-visible,
.nd-btn:focus-visible {
  outline: 2px solid var(--nd-accent);
  outline-offset: 2px;
}
.nd-fade-enter-active,
.nd-fade-leave-active {
  transition: opacity 0.16s ease;
}
.nd-fade-enter-from,
.nd-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .nd-fade-enter-active,
  .nd-fade-leave-active {
    transition: none;
  }
}
@media (max-width: 480px) {
  .nd-foot .nd-btn {
    flex: 1;
  }
}
</style>
