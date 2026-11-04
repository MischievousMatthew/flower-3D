<template>
  <section class="smap" aria-labelledby="smap-title">
    <div class="bc-container">
      <div class="smap-head">
        <div>
          <p class="bc-label">Shop map</p>
          <h2 id="smap-title">Flower shops <em>around you</em></h2>
          <p class="smap-lede">
            Every shop on BloomCraft, pinned where it is.
            <template v-if="hasPin">The circle shows how far you'd like to look from your address.</template>
          </p>
        </div>
        <label class="smap-radius" :class="{ off: !hasPin }">
          <span>Within</span>
          <select v-model.number="radiusKm" :disabled="!hasPin" aria-label="Show shops within this distance of your address">
            <option v-for="km in RADII" :key="km" :value="km">{{ km }} km</option>
          </select>
        </label>
      </div>

      <div class="smap-frame">
        <div ref="mapEl" class="smap-map" role="region" aria-label="Map of flower shops"></div>
        <div v-if="loading" class="smap-cover">
          <span class="bc-skeleton smap-skel"></span>
        </div>
        <div v-else-if="error" class="smap-cover smap-cover--note">
          <p>We couldn't load the shop map right now.</p>
          <button type="button" class="smap-btn" @click="load">Try again</button>
        </div>
      </div>

      <p class="smap-foot" aria-live="polite">
        <template v-if="loading">Finding shops</template>
        <template v-else-if="hasPin">
          <strong>{{ withinCount }}</strong> {{ withinCount === 1 ? "shop" : "shops" }} within {{ radiusKm }} km of your address
          <span class="smap-sep" aria-hidden="true">·</span> {{ shops.length }} on the map
        </template>
        <template v-else-if="isAuthenticated">
          {{ shops.length }} {{ shops.length === 1 ? "shop" : "shops" }} on the map.
          <router-link :to="{ name: 'Profile' }" class="smap-link">Pin your address in your profile</router-link> to see the shops around you.
        </template>
        <template v-else>
          {{ shops.length }} {{ shops.length === 1 ? "shop" : "shops" }} on the map. Log in and pin your address to see the shops around you.
        </template>
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import api from "../../plugins/axios.js";
import { useAuth } from "../../composables/useAuth";

/**
 * Every shop pinned on a map with its logo, and a circle around the
 * customer's pinned address (1 to 5 km) showing how far they'd like to look.
 */
const emit = defineEmits(["visit"]);
const RADII = [1, 2, 3, 4, 5];

const { isAuthenticated, user } = useAuth();
const mapEl = ref(null);
const shops = ref([]);
const loading = ref(true);
const error = ref(false);
const radiusKm = ref(3);

let map = null;
let circle = null;

const home = computed(() => {
  const lat = Number(user.value?.latitude);
  const lng = Number(user.value?.longitude);
  return Number.isFinite(lat) && Number.isFinite(lng) && (lat || lng) && user.value?.latitude != null ? [lat, lng] : null;
});
const hasPin = computed(() => !!home.value);

// Straight-line distance in km
function distanceKm([lat1, lng1], [lat2, lng2]) {
  const rad = (d) => (d * Math.PI) / 180;
  const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.sqrt(a));
}
const withinCount = computed(() =>
  home.value ? shops.value.filter((s) => distanceKm(home.value, [s.latitude, s.longitude]) <= radiusKm.value).length : 0,
);

const escapeHtml = (text) =>
  String(text ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

// A round pin with the shop's logo (or its first letter) and a point underneath
function shopIcon(shop) {
  const face = shop.store_logo_path
    ? `<img src="${escapeHtml(shop.store_logo_path)}" alt="" loading="lazy" />`
    : `<span>${escapeHtml(shop.store_name?.charAt(0) || "B")}</span>`;
  return L.divIcon({
    className: "smap-pin-wrap",
    html: `<span class="smap-pin">${face}</span>`,
    iconSize: [48, 58],
    iconAnchor: [24, 58],
    popupAnchor: [0, -54],
  });
}

const homeIcon = () =>
  L.divIcon({
    className: "smap-pin-wrap",
    html: '<span class="smap-home" aria-hidden="true"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  });

function popupFor(shop) {
  const box = document.createElement("div");
  box.className = "smap-popup";
  const name = document.createElement("strong");
  name.textContent = shop.store_name;
  box.appendChild(name);
  if (shop.store_address) {
    const place = document.createElement("span");
    place.textContent = shop.store_address;
    box.appendChild(place);
  }
  if (home.value) {
    const away = document.createElement("span");
    away.className = "smap-popup-km";
    away.textContent = `${distanceKm(home.value, [shop.latitude, shop.longitude]).toFixed(1)} km from your address`;
    box.appendChild(away);
  }
  const visit = document.createElement("button");
  visit.type = "button";
  visit.textContent = "Visit shop";
  visit.addEventListener("click", () => emit("visit", shop.id));
  box.appendChild(visit);
  return box;
}

function drawCircle(fit = true) {
  if (!map) return;
  circle?.remove();
  circle = null;
  if (!home.value) return;
  circle = L.circle(home.value, {
    radius: radiusKm.value * 1000,
    color: "#2e7a4f",
    weight: 2,
    fillColor: "#4db173",
    fillOpacity: 0.12,
  }).addTo(map);
  if (fit) map.fitBounds(circle.getBounds(), { padding: [24, 24] });
}

async function render() {
  await nextTick();
  if (!mapEl.value) return;
  map?.remove();
  map = L.map(mapEl.value, { scrollWheelZoom: false, zoomControl: true }).setView(home.value || [14.5995, 120.9842], 12);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "© OpenStreetMap contributors",
  }).addTo(map);

  const points = [];
  for (const shop of shops.value) {
    const at = [shop.latitude, shop.longitude];
    points.push(at);
    L.marker(at, { icon: shopIcon(shop), title: shop.store_name, alt: shop.store_name, riseOnHover: true })
      .addTo(map)
      .bindPopup(popupFor(shop), { closeButton: true });
  }
  if (home.value) {
    L.marker(home.value, { icon: homeIcon(), title: "Your address", zIndexOffset: 1000, keyboard: false }).addTo(map).bindTooltip("Your address");
    drawCircle(false);
  }
  fitView();
  // Once the map knows its real size, fit again (a fit made before that zooms too far out)
  setTimeout(() => {
    map?.invalidateSize();
    fitView();
  }, 120);
}

/** Zoom to the circle around the customer, or to all the shops */
function fitView() {
  if (!map) return;
  if (circle) map.fitBounds(circle.getBounds(), { padding: [24, 24] });
  else if (shops.value.length) map.fitBounds(L.latLngBounds(shops.value.map((s) => [s.latitude, s.longitude])), { padding: [48, 48], maxZoom: 14 });
}

async function load() {
  loading.value = true;
  error.value = false;
  try {
    const { data } = await api.get("/stores/map");
    shops.value = Array.isArray(data?.data) ? data.data : [];
  } catch (_) {
    error.value = true;
  } finally {
    loading.value = false;
  }
  if (!error.value) render();
}

watch(radiusKm, () => drawCircle(true));
// Logging in or pinning an address redraws the map around it
watch(home, () => {
  if (!loading.value && !error.value) render();
});

onMounted(load);
onBeforeUnmount(() => {
  map?.remove();
  map = null;
});
</script>

<style scoped>
.smap {
  padding: clamp(24px, 3vw, 40px) 0;
}

.smap-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px 24px;
  margin-bottom: 18px;
}

.smap-head h2 {
  margin: 4px 0 0;
  font-family: var(--font-display);
  font-size: clamp(30px, 3.6vw, 44px);
  font-weight: 500;
  line-height: 1.1;
  color: var(--ink);
}

.smap-head h2 em {
  color: var(--leaf-deep);
}

.smap-lede {
  margin: 8px 0 0;
  max-width: 56ch;
  font-size: 14.5px;
  color: var(--ink-soft);
}

.smap-radius {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--ink-soft);
}

.smap-radius select {
  min-height: 44px;
  padding: 0 38px 0 16px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #fff
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23363f4e' stroke-width='1.6' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E")
    no-repeat right 14px center / 16px;
  color: var(--ink);
  font: inherit;
  font-weight: 500;
  appearance: none;
  cursor: pointer;
}

.smap-radius select:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.smap-radius.off select {
  cursor: not-allowed;
  opacity: 0.55;
}

.smap-frame {
  position: relative;
  width: 100%;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: var(--ivory);
}

.smap-map {
  width: 100%;
  height: clamp(360px, 58vh, 580px);
  z-index: 0;
}

.smap-cover {
  position: absolute;
  inset: 0;
  z-index: 500;
  display: grid;
  place-items: center;
  background: var(--ivory);
}

.smap-skel {
  width: 100%;
  height: 100%;
  border-radius: 0;
}

.smap-cover--note {
  align-content: center;
  gap: 12px;
  color: var(--ink-soft);
  font-size: 14px;
}

.smap-cover--note p {
  margin: 0;
}

.smap-btn {
  min-height: 44px;
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

.smap-foot {
  margin: 12px 2px 0;
  font-size: 13.5px;
  color: var(--ink-soft);
}

.smap-foot strong {
  color: var(--ink);
  font-weight: 600;
}

.smap-sep {
  margin: 0 6px;
}

.smap-link {
  color: var(--leaf-deep);
  font-weight: 500;
}

@media (max-width: 600px) {
  .smap-frame {
    border-radius: 16px;
  }
}
</style>

<!-- Leaflet adds the pins and popups outside this component's markup, so these styles aren't scoped -->
<style>
.smap-pin-wrap {
  background: none;
  border: 0;
}

.smap-pin {
  position: relative;
  box-sizing: border-box;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border: 3px solid #fff;
  border-radius: 50%;
  background: #f2f5f2;
  box-shadow: 0 6px 16px -4px rgba(54, 63, 78, 0.45);
  transition: transform 0.15s ease;
}

.smap-pin::after {
  content: "";
  position: absolute;
  left: 50%;
  bottom: -10px;
  width: 0;
  height: 0;
  border-left: 7px solid transparent;
  border-right: 7px solid transparent;
  border-top: 10px solid #fff;
  transform: translateX(-50%);
}

/* Leaflet sets width: auto on every marker image; the logo must fit the pin */
.leaflet-container .leaflet-marker-pane .smap-pin img {
  display: block;
  width: 42px !important;
  height: 42px !important;
  max-width: 42px !important;
  max-height: 42px !important;
  border-radius: 50%;
  object-fit: cover;
}

.smap-pin span {
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 22px;
  font-weight: 600;
  color: #2e7a4f;
}

.leaflet-marker-icon:hover .smap-pin {
  transform: translateY(-2px) scale(1.05);
}

.smap-home {
  display: block;
  width: 22px;
  height: 22px;
  border: 4px solid #fff;
  border-radius: 50%;
  background: #363f4e;
  box-shadow: 0 0 0 6px rgba(77, 177, 115, 0.35), 0 4px 10px rgba(54, 63, 78, 0.35);
}

.smap-popup {
  display: grid;
  gap: 4px;
  min-width: 180px;
  font-family: "Poppins", -apple-system, "Segoe UI", sans-serif;
  color: #363f4e;
}

.smap-popup strong {
  font-size: 14px;
  font-weight: 600;
}

.smap-popup span {
  font-size: 12px;
  color: #6a7282;
}

.smap-popup .smap-popup-km {
  color: #2e7a4f;
  font-weight: 500;
}

.smap-popup button {
  justify-self: start;
  min-height: 34px;
  margin-top: 6px;
  padding: 0 14px;
  border: 0;
  border-radius: 999px;
  background: #2e7a4f;
  color: #fff;
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}

.smap-popup button:hover {
  background: #363f4e;
}

@media (prefers-reduced-motion: reduce) {
  .smap-pin {
    transition: none;
  }
}
</style>
