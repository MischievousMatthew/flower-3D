<template>
  <Teleport to="body">
    <transition name="plan-modal">
      <div
        v-if="plan"
        class="plan-modal-backdrop"
        @click.self="$emit('close')"
        @keydown="handleKeydown"
      >
        <div
          ref="dialog"
          class="plan-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="plan-modal-title"
          aria-describedby="plan-modal-desc"
        >
          <button
            type="button"
            class="plan-modal-close"
            aria-label="Close"
            @click="$emit('close')"
          >
            ×
          </button>
          <span class="plan-modal-eyebrow">{{ plan.name }} · {{ plan.price }}{{ plan.priceSuffix }}</span>
          <h2 id="plan-modal-title">Do you have a BloomCraft vendor account?</h2>
          <p id="plan-modal-desc">
            Log in and we'll take you straight to checkout for this plan. New
            to BloomCraft? Register your flower shop first. Once your
            application is approved, log in and you'll go straight to checkout.
          </p>
          <div class="plan-modal-actions">
            <button type="button" class="plan-modal-btn plan-modal-btn--dark" @click="$emit('login')">
              Yes, log in
            </button>
            <button type="button" class="plan-modal-btn" @click="$emit('register')">
              No, register my shop
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";

const props = defineProps({
  // The plan being chosen; the modal is open while this is set.
  plan: { type: Object, default: null },
});
const emit = defineEmits(["close", "login", "register"]);

const dialog = ref(null);
let returnFocusTo = null;

const focusables = () =>
  Array.from(dialog.value?.querySelectorAll("button, [href], [tabindex]:not([tabindex='-1'])") || []);

watch(
  () => props.plan,
  async (plan) => {
    if (plan) {
      returnFocusTo = document.activeElement;
      await nextTick();
      focusables()[1]?.focus(); // first action, after the close button
    } else if (returnFocusTo?.focus) {
      returnFocusTo.focus();
      returnFocusTo = null;
    }
  },
);

function handleKeydown(event) {
  if (event.key === "Escape") {
    emit("close");
    return;
  }
  if (event.key !== "Tab") return;
  // Keep focus inside the dialog.
  const items = focusables();
  if (!items.length) return;
  const first = items[0];
  const last = items[items.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}
</script>

<style scoped>
.plan-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(27, 22, 18, 0.45);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.plan-modal {
  position: relative;
  width: 100%;
  max-width: 480px;
  padding: 40px 34px 32px;
  border-radius: 26px;
  background: #f4f1ec;
  color: #1e1b18;
  box-shadow: 0 30px 80px rgba(27, 22, 18, 0.25);
  font-family: "Poppins", sans-serif;
}

.plan-modal-close {
  position: absolute;
  top: 14px;
  right: 16px;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: #6e6761;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
}

.plan-modal-close:hover {
  background: rgba(30, 27, 24, 0.06);
}

.plan-modal-eyebrow {
  display: inline-block;
  margin-bottom: 12px;
  color: #b86558;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.14em;
}

.plan-modal h2 {
  margin: 0 0 12px;
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 32px;
  font-weight: 500;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.plan-modal p {
  margin: 0 0 26px;
  color: #6e6761;
  font-size: 14.5px;
  line-height: 1.7;
}

.plan-modal-actions {
  display: grid;
  gap: 10px;
}

.plan-modal-btn {
  padding: 14px 20px;
  border: 1px solid rgba(30, 27, 24, 0.15);
  border-radius: 999px;
  background: transparent;
  color: #1e1b18;
  font: inherit;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
}

.plan-modal-btn:hover {
  border-color: #1e1b18;
}

.plan-modal-btn--dark {
  background: #1e1b18;
  border-color: #1e1b18;
  color: #f4f1ec;
}

.plan-modal-btn--dark:hover {
  background: #b86558;
  border-color: #b86558;
}

.plan-modal-btn:focus-visible,
.plan-modal-close:focus-visible {
  outline: 2px solid #b86558;
  outline-offset: 3px;
}

.plan-modal-enter-active,
.plan-modal-leave-active {
  transition: opacity 0.3s ease;
}

.plan-modal-enter-active .plan-modal,
.plan-modal-leave-active .plan-modal {
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.plan-modal-enter-from,
.plan-modal-leave-to {
  opacity: 0;
}

.plan-modal-enter-from .plan-modal,
.plan-modal-leave-to .plan-modal {
  transform: translateY(16px) scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
  .plan-modal-enter-active,
  .plan-modal-leave-active,
  .plan-modal-enter-active .plan-modal,
  .plan-modal-leave-active .plan-modal {
    transition: none;
  }
}
</style>
