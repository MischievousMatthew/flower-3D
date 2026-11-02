<template>
  <Teleport to="body">
    <Transition name="pf">
      <div v-if="show && failure" class="pf-overlay bc-theme" @click.self="close">
        <div
          ref="dialog"
          class="pf-modal"
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="pf-title"
          aria-describedby="pf-body"
          @keydown="onKeydown"
        >
          <div class="pf-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <rect x="3" y="6" width="18" height="13" rx="2.5" />
              <path d="M3 10h18" />
              <path d="m14.5 13.5 3 3m0-3-3 3" />
            </svg>
          </div>

          <h3 id="pf-title" class="pf-title">{{ failure.title }}</h3>

          <div id="pf-body" class="pf-body">
            <p class="pf-reason">{{ failure.message }}</p>
            <p class="pf-next">{{ failure.next }}</p>
          </div>

          <dl v-if="orderNumber" class="pf-meta">
            <div>
              <dt>Order</dt>
              <dd>{{ orderNumber }}</dd>
            </div>
            <div v-if="failure.code">
              <dt>PayMongo code</dt>
              <dd>{{ failure.code }}</dd>
            </div>
          </dl>

          <div class="pf-actions">
            <button v-if="secondaryLabel" type="button" class="pf-btn" @click="$emit('secondary')">
              {{ secondaryLabel }}
            </button>
            <button ref="primary" type="button" class="pf-btn pf-btn--primary" @click="close">
              {{ primaryLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";

/* Explains why an online payment didn't go through, from the order's
   payment_failure ({ title, message, next, code }) sent by the API. */
const props = defineProps({
  show: { type: Boolean, default: false },
  failure: { type: Object, default: null },
  orderNumber: { type: String, default: "" },
  primaryLabel: { type: String, default: "Got it" },
  secondaryLabel: { type: String, default: "" },
});
const emit = defineEmits(["close", "secondary"]);

const dialog = ref(null);
const primary = ref(null);
let returnFocusTo = null;

const close = () => emit("close");

watch(
  () => props.show && !!props.failure,
  async (open) => {
    if (open) {
      returnFocusTo = document.activeElement;
      await nextTick();
      primary.value?.focus();
    } else {
      returnFocusTo?.focus?.();
      returnFocusTo = null;
    }
  },
);

// Esc closes; Tab stays inside the dialog
function onKeydown(e) {
  if (e.key === "Escape") {
    e.preventDefault();
    close();
    return;
  }
  if (e.key !== "Tab") return;
  const buttons = [...dialog.value.querySelectorAll("button")];
  const first = buttons[0];
  const last = buttons[buttons.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
}
</script>

<style scoped>
.pf-overlay {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  line-height: 1.6;
}

.pf-modal {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 100%;
  max-width: 420px;
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  padding: 36px 32px 28px;
  border-radius: 24px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
  text-align: center;
}

.pf-ico {
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  margin-bottom: 6px;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.1);
  color: var(--leaf-deep);
}

.pf-ico svg {
  width: 26px;
  height: 26px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pf-title {
  margin: 0;
  color: var(--ink);
  font-size: 26px;
  line-height: 1.2;
}

.pf-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pf-body p {
  margin: 0;
}

.pf-reason {
  color: var(--ink);
  font-size: 15px;
  font-weight: 400;
}

.pf-next {
  color: var(--ink-soft);
  font-size: 14px;
}

.pf-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px 20px;
  width: 100%;
  margin: 6px 0 0;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--ivory);
  font-size: 12.5px;
}

.pf-meta div {
  display: flex;
  gap: 6px;
}

.pf-meta dt {
  color: var(--ink-soft);
}

.pf-meta dd {
  margin: 0;
  color: var(--ink);
  font-weight: 500;
  word-break: break-all;
}

.pf-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  width: 100%;
  margin-top: 14px;
}

.pf-btn {
  min-height: 44px;
  padding: 12px 22px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition:
    background 0.3s var(--ease-out),
    color 0.3s var(--ease-out),
    border-color 0.3s var(--ease-out);
}

.pf-btn:hover {
  border-color: var(--ink);
}

.pf-btn--primary {
  border-color: var(--ink);
  background: var(--ink);
  color: var(--ivory);
}

.pf-btn--primary:hover {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
}

.pf-btn:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

.pf-enter-active,
.pf-leave-active {
  transition: opacity 0.25s var(--ease-out);
}

.pf-enter-active .pf-modal,
.pf-leave-active .pf-modal {
  transition: transform 0.25s var(--ease-out);
}

.pf-enter-from,
.pf-leave-to {
  opacity: 0;
}

.pf-enter-from .pf-modal,
.pf-leave-to .pf-modal {
  transform: translateY(10px) scale(0.98);
}

.pf-leave-active {
  transition-duration: 0.18s;
}

@media (max-width: 480px) {
  .pf-modal {
    padding: 30px 20px 22px;
  }

  .pf-actions .pf-btn {
    flex: 1 1 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pf-enter-active,
  .pf-leave-active,
  .pf-enter-active .pf-modal,
  .pf-leave-active .pf-modal {
    transition: none;
  }
}
</style>
