<template>
  <div class="ps" :class="`ps-${tone}`">
    <button type="button" class="ps-btn" :disabled="disabled" @click="suggest">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="8" cy="15" r="4" />
        <path d="M11 12l8-8M16 7l3 3M14 9l2 2" />
      </svg>
      {{ showing ? "Suggest another" : "Suggest a strong password" }}
    </button>

    <!-- The suggestion, so it can be copied or saved before signing in -->
    <div v-if="showing" class="ps-card" role="status">
      <code class="ps-code">{{ suggested }}</code>
      <button type="button" class="ps-copy" :aria-label="copied ? 'Copied' : 'Copy password'" @click="copy">
        <svg v-if="copied" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
        <svg v-else viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2.5" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" /></svg>
        <span>{{ copied ? "Copied" : "Copy" }}</span>
      </button>
      <p class="ps-note">Filled in both boxes. Save it in your password manager or somewhere safe.</p>
    </div>
  </div>
</template>

<script setup>
// "Suggest a strong password": makes one that meets every rule, hands it to
// the form (which fills the password and its confirmation), and shows it
// with a Copy button so the person can keep it.
import { computed, ref } from "vue";
import { generatePassword } from "../../utils/passwordRules";

const props = defineProps({
  min: { type: Number, default: 8 },
  disabled: { type: Boolean, default: false },
  // customer (navy/green, sign-in pages) or vendor (vendor portal tokens, dark mode)
  tone: { type: String, default: "customer" },
  // The password box right now: the card hides once they type their own
  current: { type: String, default: "" },
});
const emit = defineEmits(["use"]);

const suggested = ref("");
const copied = ref(false);
const showing = computed(() => !!suggested.value && props.current === suggested.value);
let copiedTimer = null;

function suggest() {
  suggested.value = generatePassword(16, props.min);
  copied.value = false;
  emit("use", suggested.value);
}

async function copy() {
  try {
    await navigator.clipboard.writeText(suggested.value);
    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 2000);
  } catch {
    // No clipboard access: the password is on screen to copy by hand
  }
}

/** The person typed their own: hide the suggestion */
function clear() {
  suggested.value = "";
  copied.value = false;
}

defineExpose({ clear, value: suggested });
</script>

<style scoped>
.ps {
  --ps-ink: var(--ink, #363f4e);
  --ps-soft: var(--ink-soft, #5f6776);
  --ps-accent: var(--leaf-deep, #2e7a4f);
  --ps-line: var(--line, rgba(54, 63, 78, 0.12));
  --ps-card: rgba(77, 177, 115, 0.08);
  --ps-surface: #ffffff;
  display: grid;
  gap: 10px;
}

.ps-vendor {
  --ps-ink: var(--v-text);
  --ps-soft: var(--v-muted);
  --ps-accent: var(--v-green-text);
  --ps-line: var(--v-border);
  --ps-card: var(--v-subtle);
  --ps-surface: var(--v-surface);
}

.ps-btn {
  display: inline-flex;
  align-items: center;
  justify-self: start;
  gap: 8px;
  min-height: 40px;
  padding: 0 14px 0 12px;
  border: 1px solid var(--ps-line);
  border-radius: 999px;
  background: var(--ps-surface);
  color: var(--ps-ink);
  font: 500 13.5px var(--font-sans, inherit);
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.ps-btn svg,
.ps-copy svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ps-btn svg {
  color: var(--ps-accent);
}

.ps-btn:hover:not(:disabled) {
  border-color: var(--ps-accent);
}

.ps-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ps-card {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 6px 10px;
  padding: 10px 10px 10px 14px;
  border: 1px dashed var(--ps-line);
  border-radius: 12px;
  background: var(--ps-card);
}

.ps-code {
  overflow-wrap: anywhere;
  color: var(--ps-ink);
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 15px;
  letter-spacing: 0.04em;
}

.ps-copy {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 12px;
  border: 1px solid var(--ps-line);
  border-radius: 999px;
  background: var(--ps-surface);
  color: var(--ps-ink);
  font: 600 12.5px var(--font-sans, inherit);
  cursor: pointer;
}

.ps-copy:hover {
  border-color: var(--ps-accent);
  color: var(--ps-accent);
}

.ps-note {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--ps-soft);
  font-size: 12.5px;
  line-height: 1.45;
}

.ps-btn:focus-visible,
.ps-copy:focus-visible {
  outline: 2px solid var(--ps-accent);
  outline-offset: 2px;
}
</style>
