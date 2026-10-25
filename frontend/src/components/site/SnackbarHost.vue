<template>
  <Teleport to="body">
    <!-- Polite: read out without moving focus. Errors are assertive. -->
    <div class="sb-host" :class="{ dark }" aria-live="polite" aria-relevant="additions">
      <TransitionGroup name="sb">
        <div
          v-for="s in snackbars.items"
          :key="s.id"
          class="sb"
          :class="s.type"
          :role="s.type === 'error' ? 'alert' : 'status'"
          @mouseenter="hold(s.id)"
          @mouseleave="release(s.id)"
          @focusin="hold(s.id)"
          @focusout="release(s.id)"
        >
          <span class="sb-icon" aria-hidden="true" v-html="ICONS[s.type] ?? ICONS.info"></span>
          <p class="sb-text">{{ s.text }}</p>
          <button v-if="s.action" type="button" class="sb-action" @click="runAction(s)">{{ s.action.label }}</button>
          <button type="button" class="sb-close" aria-label="Dismiss" @click="dismiss(s.id)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
          <i v-if="s.duration" class="sb-timer" :class="{ paused: s.held }" :style="{ animationDuration: `${s.duration}ms` }"></i>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
// Shows the snackbars from utils/snackbar.js (mounted once in App.vue).
// Dark when the page is in dark mode (vendor and ERP pages), light otherwise.
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { dismiss, hold, release, snackbars } from "../../utils/snackbar";

const router = useRouter();
const ICONS = {
  success: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9"/></svg>',
  error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg>',
  warning: '<svg viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
  info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.8h.01"/></svg>',
};

function runAction(s) {
  dismiss(s.id);
  if (s.action.to) router.push(s.action.to);
  else s.action.onClick?.();
}

// Follows the page: vendor and ERP pages put data-vendor-theme="dark" on <html>
const dark = ref(false);
let observer = null;
const read = () => (dark.value = document.documentElement.getAttribute("data-vendor-theme") === "dark");
onMounted(() => {
  read();
  observer = new MutationObserver(read);
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ["data-vendor-theme"] });
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<style scoped>
.sb-host {
  --sb-bg: #ffffff;
  --sb-text: #2b323d;
  --sb-border: rgba(54, 63, 78, 0.12);
  --sb-shadow: 0 12px 32px rgba(30, 39, 57, 0.16), 0 2px 6px rgba(30, 39, 57, 0.08);
  --sb-action: #2f8f5b;
  --sb-close: #6b7280;
  --sb-hover: rgba(54, 63, 78, 0.07);
  --sb-success: #2f8f5b;
  --sb-error: #c0392b;
  --sb-warning: #b7791f;
  --sb-info: #363f4e;
  position: fixed;
  z-index: 10000;
  left: 50%;
  bottom: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: min(460px, calc(100vw - 32px));
  pointer-events: none;
  transform: translateX(-50%);
}
.sb-host.dark {
  --sb-bg: #1f2633;
  --sb-text: #eef1f6;
  --sb-border: rgba(255, 255, 255, 0.08);
  --sb-shadow: 0 14px 36px rgba(0, 0, 0, 0.45);
  --sb-action: #7ee2a8;
  --sb-close: #aab3c2;
  --sb-hover: rgba(255, 255, 255, 0.08);
  --sb-success: #6ad79b;
  --sb-error: #ff8a80;
  --sb-warning: #f6c66b;
  --sb-info: #c9d3e3;
}
.sb {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 52px;
  padding: 10px 8px 10px 14px;
  overflow: hidden;
  border: 1px solid var(--sb-border);
  border-radius: 14px;
  background: var(--sb-bg);
  color: var(--sb-text);
  box-shadow: var(--sb-shadow);
  font-family: var(--font-sans, "Poppins", system-ui, sans-serif);
  pointer-events: auto;
}
.sb-icon {
  flex: none;
  display: grid;
  width: 22px;
  height: 22px;
}
.sb-icon :deep(svg) {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.sb.success .sb-icon { color: var(--sb-success); }
.sb.error .sb-icon { color: var(--sb-error); }
.sb.warning .sb-icon { color: var(--sb-warning); }
.sb.info .sb-icon { color: var(--sb-info); }
.sb-text {
  flex: 1;
  min-width: 0;
  margin: 0;
  font-size: 14px;
  line-height: 1.45;
  overflow-wrap: anywhere;
}
.sb-action {
  flex: none;
  min-height: 36px;
  padding: 0 10px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--sb-action);
  font: inherit;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}
.sb-action:hover {
  background: var(--sb-hover);
}
.sb-close {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--sb-close);
  cursor: pointer;
}
.sb-close:hover {
  background: var(--sb-hover);
  color: var(--sb-text);
}
.sb-close svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
}
.sb-action:focus-visible,
.sb-close:focus-visible {
  outline: 2px solid var(--sb-action);
  outline-offset: 1px;
}
/* How long it stays, shrinking; it stops while held */
.sb-timer {
  position: absolute;
  left: 0;
  bottom: 0;
  height: 3px;
  width: 100%;
  background: currentColor;
  opacity: 0.18;
  transform-origin: left;
  animation: sb-timer linear forwards;
}
.sb.success .sb-timer { color: var(--sb-success); }
.sb.error .sb-timer { color: var(--sb-error); }
.sb.warning .sb-timer { color: var(--sb-warning); }
.sb.info .sb-timer { color: var(--sb-info); }
.sb-timer.paused {
  animation-play-state: paused;
}
@keyframes sb-timer {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}
.sb-enter-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}
.sb-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.sb-enter-from {
  opacity: 0;
  transform: translateY(14px);
}
.sb-leave-to {
  opacity: 0;
  transform: scale(0.97);
}
.sb-move {
  transition: transform 0.2s ease;
}
@media (max-width: 640px) {
  .sb-host {
    bottom: 16px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sb-enter-active,
  .sb-leave-active,
  .sb-move {
    transition: none;
  }
  .sb-timer {
    animation: none;
    display: none;
  }
}
</style>
