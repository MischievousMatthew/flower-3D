<template>
  <div v-if="notice" class="csb" :class="notice.tone" role="status" aria-live="polite">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path v-if="notice.tone === 'paused'" d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01" />
      <path v-else d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
    </svg>
    <p><strong>{{ notice.title }}</strong> {{ notice.text }}</p>
    <button v-if="notice.tone === 'upcoming'" type="button" class="csb-close" aria-label="Hide this notice" @click="dismiss">✕</button>
  </div>
</template>

<script setup>
// Tells customers when ordering is paused, or when a pause is planned
// (Admin → System settings). Shown under the top bar on customer pages.
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { usePlatformStatus } from "../../composables/usePlatformStatus";

const { status, refresh } = usePlatformStatus();
const HIDDEN_KEY = "bc_hidden_pause_notice";
const hidden = ref("");
const now = ref(Date.now());
// Planned pauses are announced from this far ahead
const ANNOUNCE_DAYS = 7;

const when = (iso) =>
  new Date(iso).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });

const notice = computed(() => {
  if (!status.loaded) return null;
  const start = status.checkout_pause_starts_at ? new Date(status.checkout_pause_starts_at).getTime() : null;
  const end = status.checkout_pause_ends_at;
  if (status.checkout_paused) {
    return {
      tone: "paused",
      title: "Ordering is paused.",
      text: `${status.checkout_pause_message}${end ? ` It reopens ${when(end)}.` : ""}`,
    };
  }
  if (start && start > now.value && start - now.value < ANNOUNCE_DAYS * 86400000 && hidden.value !== status.checkout_pause_starts_at) {
    return {
      tone: "upcoming",
      title: "Planned maintenance:",
      text: `ordering pauses ${when(status.checkout_pause_starts_at)}${end ? ` to ${when(end)}` : ""}. Place orders before then.`,
    };
  }
  return null;
});

function dismiss() {
  hidden.value = status.checkout_pause_starts_at;
  try {
    localStorage.setItem(HIDDEN_KEY, hidden.value);
  } catch {
    // remembered for this visit only
  }
}

let timer = null;
onMounted(() => {
  try {
    hidden.value = localStorage.getItem(HIDDEN_KEY) || "";
  } catch {
    hidden.value = "";
  }
  refresh();
  // A planned pause starts (or ends) by itself: look again every minute
  timer = setInterval(() => {
    now.value = Date.now();
    refresh();
  }, 60000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.csb {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 8px 16px;
  font-family: var(--font-sans, inherit);
  font-size: 13.5px;
  line-height: 1.45;
  text-align: center;
}
.csb.paused {
  background: #b42318;
  color: #fff;
}
.csb.upcoming {
  background: #fff4e5;
  color: #7a3e00;
  border-bottom: 1px solid #f6d7ad;
}
.csb svg {
  flex: none;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.csb p {
  margin: 0;
}
.csb-close {
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  margin: -6px -8px -6px 4px;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: inherit;
  cursor: pointer;
}
.csb-close:hover {
  background: rgba(0, 0, 0, 0.06);
}
.csb-close:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 1px;
}
</style>
