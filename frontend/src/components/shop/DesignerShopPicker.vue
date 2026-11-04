<template>
  <Teleport to="body">
    <Transition name="dsp">
      <div v-if="open" class="dsp bc-theme" @click.self="close">
        <section ref="dialogEl" class="dsp-dialog" role="dialog" aria-modal="true" aria-labelledby="dsp-title" aria-describedby="dsp-lead" @keydown="onKeydown">
          <header class="dsp-head">
            <div>
              <p class="dsp-label">3D Designer</p>
              <h2 id="dsp-title">Choose a flower shop</h2>
              <p id="dsp-lead" class="dsp-lead">These shops have flowers, fillers, wraps and ribbons ready, so you can build the whole bouquet.</p>
            </div>
            <button ref="closeEl" type="button" class="dsp-close" aria-label="Close" @click="close">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
          </header>

          <ul v-if="loading" class="dsp-list" aria-label="Loading shops">
            <li v-for="n in 4" :key="n" class="dsp-skel">
              <span class="bc-skeleton dsp-skel-logo"></span>
              <span class="dsp-skel-lines">
                <span class="bc-skeleton dsp-skel-line"></span>
                <span class="bc-skeleton dsp-skel-line short"></span>
              </span>
            </li>
          </ul>

          <div v-else-if="error" class="dsp-empty">
            <p>We couldn't load the shops right now.</p>
            <button type="button" class="dsp-btn" @click="load">Try again</button>
          </div>

          <div v-else-if="!shops.length" class="dsp-empty">
            <p><strong>No shop is ready for the 3D designer yet.</strong></p>
            <p>A shop needs at least one flower, filler, wrapper and ribbon before you can design with it.</p>
            <button type="button" class="dsp-btn" @click="browse">Browse flower shops</button>
          </div>

          <ul v-else class="dsp-list">
            <li v-for="shop in shops" :key="shop.id">
              <button type="button" class="dsp-shop" @click="choose(shop)">
                <span class="dsp-logo">
                  <img v-if="shop.store_logo_path" :src="shop.store_logo_path" alt="" loading="lazy" />
                  <span v-else aria-hidden="true">{{ shop.store_name?.charAt(0) || "B" }}</span>
                </span>
                <span class="dsp-copy">
                  <strong>{{ shop.store_name }}</strong>
                  <span v-if="shop.store_address" class="dsp-place">{{ shop.store_address }}</span>
                  <span class="dsp-parts">
                    <span v-for="part in parts(shop)" :key="part" class="dsp-chip">{{ part }}</span>
                  </span>
                  <span v-if="shop.taking_orders === false" class="dsp-paused">Not taking orders right now. You can still design.</span>
                </span>
                <span class="dsp-go" aria-hidden="true">
                  Design here
                  <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </span>
              </button>
            </li>
          </ul>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, onUnmounted, ref, watch } from "vue";
import api from "../../plugins/axios.js";

/**
 * "Explore 3D Designer": the page blurs behind a list of the shops that have
 * everything a bouquet needs (at least one flower, filler, wrapper and ribbon),
 * and the customer picks which shop to design with.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(["close", "choose", "browse"]);

const shops = ref([]);
const loading = ref(false);
const error = ref(false);
const loaded = ref(false);
const dialogEl = ref(null);
const closeEl = ref(null);
let returnFocus = null;

async function load() {
  loading.value = true;
  error.value = false;
  try {
    const { data } = await api.get("/stores/designer-ready");
    shops.value = Array.isArray(data?.data) ? data.data : [];
    loaded.value = true;
  } catch (_) {
    error.value = true;
  } finally {
    loading.value = false;
  }
}

const plural = (count, one, many) => `${count} ${count === 1 ? one : many}`;
const parts = (shop) => {
  const d = shop.designer || {};
  return [
    plural(d.flowers || 0, "flower", "flowers"),
    plural(d.fillers || 0, "filler", "fillers"),
    plural(d.wrappers || 0, "wrap", "wraps"),
    plural(d.ribbons || 0, "ribbon", "ribbons"),
  ];
};

function close() {
  emit("close");
}

function choose(shop) {
  emit("choose", shop);
}

function browse() {
  emit("browse");
}

// Esc closes; Tab stays inside the dialog
function onKeydown(event) {
  if (event.key === "Escape") {
    event.stopPropagation();
    close();
    return;
  }
  if (event.key !== "Tab" || !dialogEl.value) return;
  const focusable = [...dialogEl.value.querySelectorAll("button:not([disabled]), [href], input, [tabindex]:not([tabindex='-1'])")];
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      returnFocus = document.activeElement;
      document.body.style.overflow = "hidden";
      if (!loaded.value) load();
      await nextTick();
      closeEl.value?.focus();
    } else {
      document.body.style.overflow = "";
      returnFocus?.focus?.();
      returnFocus = null;
    }
  },
);

onUnmounted(() => {
  document.body.style.overflow = "";
});
</script>

<style scoped>
.dsp {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(250, 251, 250, 0.62);
  backdrop-filter: blur(14px) saturate(1.1);
  -webkit-backdrop-filter: blur(14px) saturate(1.1);
}

.dsp-dialog {
  display: flex;
  flex-direction: column;
  width: min(760px, 100%);
  max-height: min(86vh, 760px);
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--paper);
  box-shadow: 0 30px 80px -30px rgba(54, 63, 78, 0.35);
}

.dsp-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 26px 28px 18px;
  border-bottom: 1px solid var(--line-soft);
}

.dsp-label {
  margin: 0 0 4px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--leaf-deep);
}

.dsp-head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 36px);
  font-weight: 500;
  line-height: 1.1;
  color: var(--ink);
}

.dsp-lead {
  margin: 8px 0 0;
  max-width: 52ch;
  font-size: 14px;
  line-height: 1.6;
  color: var(--ink-soft);
}

.dsp-close {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.dsp-close:hover {
  border-color: var(--ink);
  background: var(--ivory);
}

.dsp-close svg,
.dsp-go svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.dsp-list {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 18px 28px 28px;
  overflow-y: auto;
  list-style: none;
}

.dsp-shop {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  width: 100%;
  padding: 16px 18px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.dsp-shop:hover {
  border-color: var(--leaf);
  box-shadow: 0 12px 28px -20px rgba(46, 122, 79, 0.55);
}

.dsp-shop:active {
  transform: scale(0.995);
}

.dsp-logo {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  overflow: hidden;
  border-radius: 50%;
  background: var(--ivory);
  font-family: var(--font-display);
  font-size: 24px;
  color: var(--leaf-deep);
}

.dsp-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dsp-copy {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.dsp-copy strong {
  font-size: 16px;
  font-weight: 500;
}

.dsp-place {
  overflow: hidden;
  font-size: 13px;
  color: var(--ink-soft);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dsp-parts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 4px;
}

.dsp-chip {
  padding: 2px 10px;
  border-radius: 999px;
  background: var(--ivory);
  font-size: 12px;
  color: var(--ink);
}

.dsp-paused {
  font-size: 12px;
  color: var(--ink-soft);
}

.dsp-go {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 16px;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: #fff;
  font-size: 13.5px;
  font-weight: 500;
  white-space: nowrap;
  transition: background-color 0.18s ease;
}

.dsp-shop:hover .dsp-go {
  background: var(--ink);
}

.dsp-shop:focus-visible,
.dsp-close:focus-visible,
.dsp-btn:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

.dsp-skel {
  display: grid;
  grid-template-columns: 56px 1fr;
  align-items: center;
  gap: 16px;
  padding: 16px 18px;
  border: 1px solid var(--line-soft);
  border-radius: 14px;
}

.dsp-skel-logo {
  width: 56px;
  height: 56px;
  border-radius: 50%;
}

.dsp-skel-lines {
  display: grid;
  gap: 8px;
}

.dsp-skel-line {
  height: 14px;
  width: 60%;
  border-radius: 6px;
}

.dsp-skel-line.short {
  width: 85%;
  height: 22px;
}

.dsp-empty {
  display: grid;
  justify-items: center;
  gap: 6px;
  padding: 36px 28px 40px;
  text-align: center;
  color: var(--ink-soft);
  font-size: 14px;
}

.dsp-empty p {
  margin: 0;
  max-width: 44ch;
}

.dsp-empty strong {
  color: var(--ink);
  font-weight: 500;
}

.dsp-btn {
  min-height: 44px;
  margin-top: 12px;
  padding: 0 22px;
  border: 0;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: #fff;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.dsp-btn:hover {
  background: var(--ink);
}

.dsp-enter-active,
.dsp-leave-active {
  transition: opacity 0.22s ease;
}

.dsp-enter-active .dsp-dialog,
.dsp-leave-active .dsp-dialog {
  transition: transform 0.22s var(--ease-out, ease-out);
}

.dsp-enter-from,
.dsp-leave-to {
  opacity: 0;
}

.dsp-enter-from .dsp-dialog,
.dsp-leave-to .dsp-dialog {
  transform: translateY(12px) scale(0.98);
}

@media (max-width: 600px) {
  .dsp {
    align-items: end;
    padding: 0;
  }

  .dsp-dialog {
    max-height: 90vh;
    border-radius: 18px 18px 0 0;
  }

  .dsp-head {
    padding: 20px 16px 14px;
  }

  .dsp-list {
    padding: 14px 16px 20px;
  }

  .dsp-shop {
    grid-template-columns: 48px minmax(0, 1fr);
    padding: 14px;
  }

  .dsp-logo {
    width: 48px;
    height: 48px;
  }

  .dsp-go {
    grid-column: 1 / -1;
    justify-content: center;
  }
}

@media (prefers-reduced-motion: reduce) {
  .dsp-enter-active,
  .dsp-leave-active,
  .dsp-enter-active .dsp-dialog,
  .dsp-leave-active .dsp-dialog,
  .dsp-shop {
    transition: none;
  }
}
</style>
