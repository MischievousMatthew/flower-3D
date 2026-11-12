<template>
  <div
    v-if="current"
    class="announce"
    :class="[`tone-${tone}`, { paused: isPaused }]"
    role="region"
    aria-roledescription="carousel"
    aria-label="Announcements"
    @mouseenter="hovering = true"
    @mouseleave="hovering = false"
    @focusin="focused = true"
    @focusout="focused = false"
  >
    <span class="announce-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" v-html="ICONS[tone]"></svg>
    </span>

    <Transition name="announce-swap" mode="out-in">
      <p :key="current.id" class="announce-text" aria-live="polite">
        <strong class="announce-title">{{ current.title }}</strong>
        <span v-if="current.message" class="announce-msg">{{ current.message }}</span>
        <button v-if="current.link_type" type="button" class="announce-link" @click="openLink(current)">
          {{ current.link_label || "Learn more" }}
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </button>
      </p>
    </Transition>

    <div v-if="showArrows || play.dismissible || visible.length > 1" class="announce-controls">
      <template v-if="showArrows">
        <button type="button" class="announce-btn" aria-label="Previous announcement" @click="stepManual(-1)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <span class="announce-count" aria-hidden="true">{{ position + 1 }} / {{ visible.length }}</span>
        <button type="button" class="announce-btn" aria-label="Next announcement" @click="stepManual(1)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </template>
      <span v-else-if="visible.length > 1" class="announce-count" aria-hidden="true">{{ position + 1 }} / {{ visible.length }}</span>
      <button v-if="play.dismissible" type="button" class="announce-btn announce-close" aria-label="Dismiss announcement" @click="dismiss(current.id)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </div>

    <!-- The progress line is the timer: when it fills, the next announcement shows -->
    <span
      v-if="autoAdvance"
      :key="`${current.id}-${cycle}`"
      class="announce-progress"
      :style="{ animationDuration: `${play.seconds}s` }"
      aria-hidden="true"
      @animationend="step(1)"
    ></span>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";
import { useAuth } from "../../composables/useAuth";
import { ANNOUNCEMENT_ICONS as ICONS } from "../../constants/announcementTones";

/** Announcements from Admin → Promotion Management, one at a time; dismissals are remembered */
const router = useRouter();
const { site, followLink, reload } = useSiteContent();

// Signed-in customers only: guests (and vendors browsing the shop) don't get the bar.
// The server leaves announcements out for them too; signing in or out fetches again.
const { user } = useAuth();
const isCustomer = computed(() => !!user.value && (!user.value.role || user.value.role === "customer"));
watch(isCustomer, () => reload());
const STORAGE_KEY = "bc_dismissed_announcements";
const DAY = 86_400_000;

/**
 * Preview mode (Admin → Promotion Management, before posting): shows the
 * given `items` with the given bar `settings`; links don't navigate and
 * closing one isn't remembered.
 */
const props = defineProps({
  preview: { type: Boolean, default: false },
  items: { type: Array, default: null },
  settings: { type: Object, default: null },
});
const openLink = (item) => {
  if (!props.preview) followLink(router, item);
};

// Admin → Promotion Management → Announcements & promos → Bar settings
const DEFAULTS = { seconds: 7, autoplay: true, pause_on_hover: true, show_arrows: true, dismissible: true, dismiss_for: "forever" };
const play = computed(() => {
  const s = { ...DEFAULTS, ...((props.settings ?? site.announcement_bar) || {}) };
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
const dismissed = ref(props.preview ? [] : readDismissed());
const index = ref(0);
const cycle = ref(0); // bumps to restart the progress line
const hovering = ref(false);
const focused = ref(false);
const reduced = ref(false);

const isHidden = (id) => {
  if (!play.value.dismissible) return false;
  const entry = dismissed.value.find((d) => d.id === id);
  if (!entry) return false;
  if (play.value.dismiss_for === "forever") return true;
  return Date.now() - entry.at < Number(play.value.dismiss_for) * DAY;
};
// The admin preview always shows its items; the live bar only for customers
const visible = computed(() => (props.items ?? (isCustomer.value ? site.announcements : [])).filter((a) => !isHidden(a.id)));
const position = computed(() => index.value % Math.max(visible.value.length, 1));
const current = computed(() => visible.value[position.value] ?? null);
const tone = computed(() => (ICONS[current.value?.tone] ? current.value.tone : "brand"));
const showArrows = computed(() => play.value.show_arrows && visible.value.length > 1);
const autoAdvance = computed(() => play.value.autoplay && !reduced.value && visible.value.length > 1);
// keyboard users get the same pause as mouse users
const isPaused = computed(() => (play.value.pause_on_hover && hovering.value) || focused.value);

const step = (delta) => {
  const n = visible.value.length;
  if (n) index.value = (index.value + delta + n) % n;
  cycle.value++;
};
// a click on an arrow gives the new announcement its full time
const stepManual = (delta) => step(delta);

const dismiss = (id) => {
  dismissed.value = [...dismissed.value.filter((d) => d.id !== id), { id, at: Date.now() }];
  index.value = 0;
  cycle.value++;
  if (props.preview) return;
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(dismissed.value.slice(-50)));
  } catch {
    // remembered for this visit only
  }
};

watch(() => [play.value.seconds, play.value.autoplay], () => cycle.value++);
onMounted(() => {
  reduced.value = !!window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
});
</script>

<style scoped>
.announce {
  --bg: linear-gradient(100deg, var(--espresso, #262d39) 0%, var(--ink, #363f4e) 100%);
  --fg: var(--paper, #fafbfa);
  --soft: rgba(250, 251, 250, 0.74);
  --badge-bg: rgba(77, 177, 115, 0.2);
  --badge-fg: var(--leaf-light, #8fd1a6);
  --btn-hover: rgba(250, 251, 250, 0.12);
  --chip-bg: var(--leaf, #4db173);
  --chip-fg: #10301c;
  --border: rgba(255, 255, 255, 0.06);
  --track: var(--leaf, #4db173);

  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 52px;
  padding: 7px 8px 7px 8px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--fg);
  font-family: var(--font-sans, "Poppins", sans-serif);
  font-size: 13px;
  line-height: 1.45;
  overflow: hidden;
  box-shadow: 0 6px 18px -10px rgba(38, 45, 57, 0.45);
}

/* Tones — each pairing is at least 4.5:1 */
.tone-info {
  --bg: #eaf0fa;
  --fg: #1e3a6b;
  --soft: #3d5683;
  --badge-bg: #d3e0f5;
  --badge-fg: #1e3a6b;
  --btn-hover: rgba(30, 58, 107, 0.08);
  --chip-bg: #1e3a6b;
  --chip-fg: #ffffff;
  --border: #d3e0f5;
  --track: #3b6bc4;
  box-shadow: none;
}
.tone-success {
  --bg: #e8f5ec;
  --fg: #1f5130;
  --soft: #2f6542;
  --badge-bg: #cfead8;
  --badge-fg: var(--leaf-deep, #2e7a4f);
  --btn-hover: rgba(31, 81, 48, 0.08);
  --chip-bg: var(--leaf-deep, #2e7a4f);
  --chip-fg: #ffffff;
  --border: #cfead8;
  --track: var(--leaf, #4db173);
  box-shadow: none;
}
.tone-warning {
  --bg: #fdf3e1;
  --fg: #6e420a;
  --soft: #7f5216;
  --badge-bg: #f8e2b8;
  --badge-fg: #8a5300;
  --btn-hover: rgba(110, 66, 10, 0.08);
  --chip-bg: #8a5300;
  --chip-fg: #ffffff;
  --border: #f5dfb3;
  --track: #d4962e;
  box-shadow: none;
}

.announce-icon {
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--badge-bg);
  color: var(--badge-fg);
}
.announce-icon svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.announce-text {
  flex: 1;
  min-width: 0;
  margin: 0;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  column-gap: 10px;
  row-gap: 2px;
}
.announce-title {
  font-weight: 600;
  letter-spacing: 0.005em;
}
.announce-msg {
  color: var(--soft);
}
.announce-title + .announce-msg::before {
  content: "";
  display: inline-block;
  width: 4px;
  height: 4px;
  margin: 0 10px 2px 0;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.5;
  vertical-align: middle;
}

.announce-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 30px;
  padding: 4px 12px;
  border: none;
  border-radius: 999px;
  background: var(--chip-bg);
  color: var(--chip-fg);
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: filter 0.2s ease, transform 0.2s var(--ease-out, ease);
}
.announce-link svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.2s var(--ease-out, ease);
}
.announce-link:hover {
  filter: brightness(1.08);
}
.announce-link:hover svg {
  transform: translateX(3px);
}

.announce-controls {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 2px;
}
.announce-count {
  min-width: 34px;
  text-align: center;
  font-size: 11.5px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  color: var(--soft);
}
.announce-btn {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: inherit;
  cursor: pointer;
  transition: background 0.18s ease;
}
.announce-btn svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.announce-close svg {
  width: 14px;
  height: 14px;
}
.announce-btn:hover {
  background: var(--btn-hover);
}
.announce-btn:focus-visible,
.announce-link:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}

/* Time left on this announcement */
.announce-progress {
  position: absolute;
  left: 28px;
  right: 28px;
  bottom: 0;
  height: 2px;
  border-radius: 2px;
  background: var(--track);
  opacity: 0.85;
  transform-origin: left center;
  transform: scaleX(0);
  animation-name: announce-fill;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}
.announce.paused .announce-progress {
  animation-play-state: paused;
}
@keyframes announce-fill {
  to {
    transform: scaleX(1);
  }
}

/* Swap between announcements */
.announce-swap-enter-active,
.announce-swap-leave-active {
  transition: opacity 0.22s ease, transform 0.28s var(--ease-out, ease);
}
.announce-swap-leave-active {
  transition-duration: 0.15s;
}
.announce-swap-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.announce-swap-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (max-width: 640px) {
  .announce {
    align-items: flex-start;
    gap: 10px;
    padding: 10px 6px 12px 10px;
    border-radius: 18px;
    font-size: 12.5px;
  }
  .announce-icon {
    width: 32px;
    height: 32px;
  }
  .announce-text {
    align-self: center;
  }
  .announce-title + .announce-msg::before {
    display: none;
  }
  .announce-msg {
    flex-basis: 100%;
  }
  .announce-link {
    margin-top: 6px;
  }
  .announce-count {
    display: none;
  }
  .announce-btn {
    width: 40px;
    height: 40px;
  }
  .announce-progress {
    left: 18px;
    right: 18px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .announce-swap-enter-active,
  .announce-swap-leave-active,
  .announce-link,
  .announce-link svg,
  .announce-btn {
    transition: none;
  }
}
</style>
