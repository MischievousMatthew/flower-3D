<template>
  <Teleport to="body">
    <Transition name="eq-fade">
      <div v-if="open" class="eq-backdrop" @click.self="close">
        <form ref="dialog" class="eq-modal" role="dialog" aria-modal="true" aria-labelledby="eq-title" novalidate @submit.prevent="send">
          <header class="eq-head">
            <div>
              <p class="eq-eyebrow">Enterprise plan</p>
              <h2 id="eq-title">{{ sent ? "Thanks, we got it" : "Talk to us" }}</h2>
              <p v-if="!sent">Tell us about your shop. Pricing starts at ₱15,000 a month and is set to your branches, warehouses and integrations.</p>
            </div>
            <button type="button" class="eq-close" aria-label="Close" @click="close">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </header>

          <div v-if="sent" class="eq-done" role="status">
            <span class="eq-done-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg></span>
            <p>{{ sent }}</p>
            <p class="eq-hint">We'll reply to <b>{{ form.email }}</b> or call <b>{{ form.phone }}</b>.</p>
            <button type="button" class="eq-btn eq-btn-primary" @click="close">Done</button>
          </div>

          <template v-else>
            <div class="eq-grid">
              <label class="eq-field">
                <span>Your name</span>
                <input ref="first" v-model.trim="form.name" autocomplete="name" maxlength="150" :aria-invalid="!!errors.name" :class="{ invalid: errors.name }" />
                <small v-if="errors.name" class="eq-error">{{ errors.name }}</small>
              </label>
              <label class="eq-field">
                <span>Shop or company</span>
                <input v-model.trim="form.shop_name" autocomplete="organization" maxlength="150" :aria-invalid="!!errors.shop_name" :class="{ invalid: errors.shop_name }" />
                <small v-if="errors.shop_name" class="eq-error">{{ errors.shop_name }}</small>
              </label>
              <label class="eq-field">
                <span>Email</span>
                <input v-model.trim="form.email" type="email" autocomplete="email" maxlength="150" :aria-invalid="!!errors.email" :class="{ invalid: errors.email }" />
                <small v-if="errors.email" class="eq-error">{{ errors.email }}</small>
              </label>
              <label class="eq-field">
                <span>Mobile number</span>
                <input v-model="form.phone" v-phone type="tel" :aria-invalid="!!errors.phone" :class="{ invalid: errors.phone }" />
                <small v-if="errors.phone" class="eq-error">{{ errors.phone }}</small>
              </label>
              <label class="eq-field">
                <span>Branches <small>(optional)</small></span>
                <input v-model="form.branches" type="number" inputmode="numeric" min="1" max="500" placeholder="e.g. 3" :aria-invalid="!!errors.branches" :class="{ invalid: errors.branches }" />
                <small v-if="errors.branches" class="eq-error">{{ errors.branches }}</small>
              </label>
              <label class="eq-field eq-wide">
                <span>What do you need?</span>
                <textarea v-model.trim="form.message" rows="4" maxlength="2000" placeholder="e.g. Three branches sharing one warehouse, and our POS connected to BloomCraft." :aria-invalid="!!errors.message" :class="{ invalid: errors.message }"></textarea>
                <small v-if="errors.message" class="eq-error">{{ errors.message }}</small>
              </label>
            </div>
            <p v-if="failure" class="eq-banner" role="alert">{{ failure }}</p>
            <footer class="eq-foot">
              <button type="button" class="eq-btn eq-btn-ghost" @click="close">Cancel</button>
              <button type="submit" class="eq-btn eq-btn-primary" :disabled="sending">{{ sending ? "Sending…" : "Send" }}</button>
            </footer>
          </template>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, reactive, ref, watch } from "vue";
import api from "../../plugins/axios";
import { PHONE_MESSAGE, isPhone } from "../../utils/phone";

// "Talk to us" on the Enterprise plan. Visitors and signed-in vendors use the
// same form; a vendor's inquiry is linked to their shop.
const props = defineProps({
  open: { type: Boolean, default: false },
  prefill: { type: Object, default: () => ({}) },
  vendor: { type: Boolean, default: false },
});
const emit = defineEmits(["close"]);

const EMPTY = { name: "", shop_name: "", email: "", phone: "", branches: "", message: "" };
const form = reactive({ ...EMPTY });
const errors = reactive({});
const failure = ref("");
const sending = ref(false);
const sent = ref("");
const first = ref(null);
let returnFocus = null;

watch(
  () => props.open,
  async (open) => {
    if (!open) return;
    returnFocus = document.activeElement;
    Object.assign(form, EMPTY, Object.fromEntries(Object.entries(props.prefill ?? {}).filter(([k, v]) => k in EMPTY && v)));
    Object.keys(errors).forEach((k) => delete errors[k]);
    failure.value = "";
    sent.value = "";
    document.addEventListener("keydown", onKey);
    await nextTick();
    first.value?.focus();
  },
);

function close() {
  if (sending.value) return;
  document.removeEventListener("keydown", onKey);
  emit("close");
  returnFocus?.focus?.();
}

const onKey = (e) => e.key === "Escape" && close();

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.name) errors.name = "Enter your name.";
  if (!form.shop_name) errors.shop_name = "Enter your shop or company.";
  if (!/^\S+@\S+\.\S+$/.test(form.email)) errors.email = "Enter a valid email.";
  if (!isPhone(form.phone)) errors.phone = PHONE_MESSAGE;
  if (form.branches !== "" && (Number(form.branches) < 1 || Number(form.branches) > 500)) errors.branches = "Use a number from 1 to 500.";
  if (form.message.length < 10) errors.message = "Tell us a little more about what you need.";
  return !Object.keys(errors).length;
}

async function send() {
  if (sending.value || !validate()) return;
  sending.value = true;
  failure.value = "";
  try {
    const body = { ...form, branches: form.branches === "" ? null : Number(form.branches) };
    const { data } = await api.post(props.vendor ? "/vendor/enterprise-inquiries" : "/enterprise-inquiries", body);
    sent.value = data?.message || "Our team will reach you within 1–2 business days.";
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    failure.value = Object.keys(fieldErrors).length
      ? "Fix the highlighted fields."
      : e?.response?.status === 429
        ? "You've sent a few already. Try again in an hour."
        : e?.response?.data?.message || "Couldn't send it. Check your connection and try again.";
  } finally {
    sending.value = false;
  }
}

onBeforeUnmount(() => document.removeEventListener("keydown", onKey));
</script>

<style scoped>
/* Vendor theme tokens when they exist (they follow the vendor's dark mode), brand colors otherwise */
.eq-backdrop {
  --eq-navy: #363f4e;
  --eq-green: #4db173;
  --eq-surface: var(--v-surface, #ffffff);
  --eq-text: var(--v-text, #1f2733);
  --eq-muted: var(--v-muted, #5d6675);
  --eq-border: var(--v-border, #dfe3e8);
  --eq-field: var(--v-subtle, #f5f7f6);
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 16px;
  overflow-y: auto;
  background: rgba(20, 26, 34, 0.55);
}
.eq-modal {
  width: min(620px, 100%);
  max-height: calc(100dvh - 32px);
  overflow-y: auto;
  padding: 24px;
  border-radius: 20px;
  background: var(--eq-surface);
  color: var(--eq-text);
  font-family: inherit;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
}
.eq-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}
.eq-eyebrow {
  margin: 0 0 4px;
  color: var(--eq-green);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.eq-head h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: var(--eq-text);
}
.eq-head p:not(.eq-eyebrow) {
  margin: 6px 0 0;
  color: var(--eq-muted);
  font-size: 14px;
  line-height: 1.5;
}
.eq-close {
  display: grid;
  flex: none;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--eq-border);
  border-radius: 12px;
  background: transparent;
  color: var(--eq-text);
  cursor: pointer;
}
.eq-close svg,
.eq-done-icon svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.eq-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.eq-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--eq-text);
}
.eq-field small {
  font-weight: 400;
  color: var(--eq-muted);
}
.eq-wide {
  grid-column: 1 / -1;
}
.eq-field input,
.eq-field textarea {
  width: 100%;
  min-height: 44px;
  padding: 10px 12px;
  border: 1px solid var(--eq-border);
  border-radius: 12px;
  background: var(--eq-field);
  color: var(--eq-text);
  font: inherit;
  font-size: 15px;
  font-weight: 400;
}
.eq-field textarea {
  resize: vertical;
}
.eq-field input:focus-visible,
.eq-field textarea:focus-visible,
.eq-btn:focus-visible,
.eq-close:focus-visible {
  outline: 2px solid var(--eq-green);
  outline-offset: 2px;
}
.eq-field .invalid {
  border-color: #d64545;
}
.eq-field .eq-error {
  color: #d64545;
  font-size: 12.5px;
}
.eq-banner {
  margin: 14px 0 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(214, 69, 69, 0.1);
  color: #b83232;
  font-size: 13px;
}
.eq-foot {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}
.eq-btn {
  min-height: 44px;
  padding: 0 20px;
  border: 1px solid transparent;
  border-radius: 12px;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.eq-btn-primary {
  background: var(--eq-navy);
  color: #fff;
}
.eq-btn-primary:hover:not(:disabled) {
  background: var(--eq-green);
}
.eq-btn-primary:disabled {
  opacity: 0.6;
  cursor: default;
}
.eq-btn-ghost {
  border-color: var(--eq-border);
  background: transparent;
  color: var(--eq-text);
}
.eq-done {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 8px 0 4px;
  text-align: center;
}
.eq-done p {
  margin: 0;
  font-size: 15px;
  line-height: 1.5;
}
.eq-done .eq-hint {
  color: var(--eq-muted);
  font-size: 13.5px;
}
.eq-done .eq-btn {
  margin-top: 10px;
}
.eq-done-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.15);
  color: var(--eq-green);
}
.eq-done-icon svg {
  width: 26px;
  height: 26px;
}
.eq-fade-enter-active,
.eq-fade-leave-active {
  transition: opacity 0.18s ease;
}
.eq-fade-enter-from,
.eq-fade-leave-to {
  opacity: 0;
}
@media (max-width: 560px) {
  .eq-modal {
    padding: 18px 16px;
  }
  .eq-grid {
    grid-template-columns: 1fr;
  }
  .eq-foot .eq-btn {
    flex: 1;
  }
}
@media (prefers-reduced-motion: reduce) {
  .eq-fade-enter-active,
  .eq-fade-leave-active {
    transition: none;
  }
}
</style>
