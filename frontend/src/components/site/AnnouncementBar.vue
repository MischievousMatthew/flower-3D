<template>
  <div
    v-if="current"
    class="announce"
    :class="`tone-${current.tone || 'brand'}`"
    role="status"
    @mouseenter="hovering = true"
    @mouseleave="hovering = false"
  >
    <button v-if="showArrows" type="button" class="announce-nav" aria-label="Previous announcement" @click="stepManual(-1)">‹</button>
    <p class="announce-text">
      <strong>{{ current.title }}</strong>
      <span v-if="current.message"> {{ current.message }}</span>
      <button v-if="current.link_type" type="button" class="announce-link" @click="followLink(router, current)">
        {{ current.link_label || "Learn more" }} →
      </button>
    </p>
    <button v-if="showArrows" type="button" class="announce-nav" aria-label="Next announcement" @click="stepManual(1)">›</button>
    <button v-if="play.dismissible" type="button" class="announce-close" aria-label="Dismiss" @click="dismiss(current.id)">✕</button>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/** Announcements from Admin → Website, one at a time; dismissals are remembered */
const router = useRouter();
const { site, followLink } = useSiteContent();
const STORAGE_KEY = "bc_dismissed_announcements";

const DAY = 86_400_000;

// Admin → Promotion Management → Announcements & promos → Bar settings
const DEFAULTS = { seconds: 7, autoplay: true, pause_on_hover: true, show_arrows: true, dismissible: true, dismiss_for: "forever" };
const play = computed(() => {
  const s = { ...DEFAULTS, ...(site.announcement_bar || {}) };
  const seconds = Number(s.seconds);
  return { ...s, seconds: Number.isFinite(seconds) ? Math.min(30, Math.max(3, seconds)) : DEFAULTS.seconds };
});

/** [{ id, at }]: when each announcement was closed. Older visits saved bare ids; those count as closed now. */
const readDismissed = () => {
  try {
    const list = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
    return Array.isArray(list) ? list.map((d) => (typeof d === "object" && d ? d : { id: d, at: Date.now() })) : [];
  } catch {
    return [];
  }
};
const dismissed = ref(readDismissed());
const index = ref(0);
const hovering = ref(false);

const isHidden = (id) => {
  if (!play.value.dismissible) return false;
  const entry = dismissed.value.find((d) => d.id === id);
  if (!entry) return false;
  if (play.value.dismiss_for === "forever") return true;
  return Date.now() - entry.at < Number(play.value.dismiss_for) * DAY;
};
const visible = computed(() => site.announcements.filter((a) => !isHidden(a.id)));
const current = computed(() => visible.value[index.value % Math.max(visible.value.length, 1)] ?? null);
const showArrows = computed(() => play.value.show_arrows && visible.value.length > 1);

const step = (delta) => {
  const n = visible.value.length;
  if (n) index.value = (index.value + delta + n) % n;
};
const dismiss = (id) => {
  dismissed.value = [...dismissed.value.filter((d) => d.id !== id), { id, at: Date.now() }];
  index.value = 0;
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(dismissed.value.slice(-50)));
  } catch {
    // remembered for this visit only
  }
};

let timer = null;
let reduced = false;
const restart = () => {
  clearInterval(timer);
  timer = null;
  if (reduced || !play.value.autoplay) return;
  timer = setInterval(() => {
    if (play.value.pause_on_hover && hovering.value) return;
    if (visible.value.length > 1) step(1);
  }, play.value.seconds * 1000);
};
// a click on an arrow gives the new announcement its full time
const stepManual = (delta) => {
  step(delta);
  restart();
};
watch(() => [play.value.autoplay, play.value.seconds], restart);
onMounted(() => {
  reduced = !!window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  restart();
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.announce {
  --bg: var(--espresso, #262d39);
  --fg: var(--paper, #fafbfa);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  margin-bottom: 28px;
  border-radius: 999px;
  background: var(--bg);
  color: var(--fg);
  font-size: 13px;
  line-height: 1.4;
}
.tone-info { --bg: #e8eef8; --fg: #1e3a6b; }
.tone-success { --bg: #e7f3ea; --fg: #1f5130; }
.tone-warning { --bg: #fbf0dc; --fg: #7a4a0c; }
.announce-text {
  flex: 1;
  margin: 0;
  text-align: center;
}
.announce-text strong {
  font-weight: 600;
}
.announce-link {
  margin-left: 6px;
  border: none;
  background: none;
  color: inherit;
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
  padding: 0;
}
.announce-nav,
.announce-close {
  border: none;
  background: transparent;
  color: inherit;
  opacity: 0.7;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
  padding: 2px 6px;
}
.announce-close {
  font-size: 12px;
}
.announce-nav:hover,
.announce-close:hover {
  opacity: 1;
}
@media (max-width: 640px) {
  .announce {
    border-radius: 14px;
    font-size: 12.5px;
  }
}
</style>
