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

      <!-- Shops inside the circle, nearest first -->
      <div v-if="!loading && !error && hasPin" class="smap-range" aria-live="polite">
        <div class="smap-range-head">
          <p class="smap-range-title">
            <strong>{{ inRange.length }}</strong> {{ inRange.length === 1 ? "shop" : "shops" }} within {{ radiusKm }} km of your address
            <span class="smap-sep" aria-hidden="true">·</span> {{ shops.length }} on the map
          </p>
          <label class="smap-only">
            <input type="checkbox" :checked="pageFilter" @change="$emit('update:pageFilter', $event.target.checked)" />
            <span class="smap-only-track" aria-hidden="true"></span>
            <span>Show only these shops' flowers on this page</span>
          </label>
        </div>

        <ul v-if="inRange.length" class="smap-cards">
          <li v-for="item in inRange" :key="item.shop.id">
            <article class="smap-card" :class="{ active: focusedId === item.shop.id }">
              <button type="button" class="smap-card-main" :aria-label="`Show ${item.shop.store_name} on the map`" @click="focusShop(item.shop)">
                <span class="smap-card-logo">
                  <img v-if="item.shop.store_logo_path" :src="item.shop.store_logo_path" alt="" loading="lazy" />
                  <span v-else aria-hidden="true">{{ item.shop.store_name?.charAt(0) || "B" }}</span>
                </span>
                <span class="smap-card-text">
                  <strong>{{ item.shop.store_name }}</strong>
                  <small>{{ item.km.toFixed(1) }} km away<template v-if="item.shop.taking_orders === false"> · not taking orders now</template></small>
                </span>
              </button>
              <button type="button" class="smap-card-visit" @click="$emit('visit', item.shop.id)">Visit</button>
            </article>
          </li>
        </ul>

        <div v-else class="smap-none">
          <template v-if="nearest">
            <p>
              No shops within {{ radiusKm }} km. The nearest is <strong>{{ nearest.shop.store_name }}</strong>, {{ nearest.km.toFixed(1) }} km away.
            </p>
            <button v-if="reachKm" type="button" class="smap-btn" @click="radiusKm = reachKm">Show shops within {{ reachKm }} km</button>
            <button v-else type="button" class="smap-btn smap-btn--ghost" @click="focusShop(nearest.shop)">See it on the map</button>
          </template>
          <p v-else>No shops have pinned their location yet.</p>
        </div>
      </div>

      <p v-if="loading || error || !hasPin" class="smap-foot" aria-live="polite">
        <template v-if="loading">Finding shops</template>
        <template v-else-if="error"></template>
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
const props = defineProps({
  // Whether the rest of the Shop page shows only the shops inside the circle
  pageFilter: { type: Boolean, default: true },
});
const emit = defineEmits(["visit", "range", "update:pageFilter"]);
const RADII = [1, 2, 3, 4, 5];

const { isAuthenticated, user } = useAuth();
const mapEl = ref(null);
const shops = ref([]);
const loading = ref(true);
const error = ref(false);
const radiusKm = ref(3);

let map = null;
let circle = null;
let shopLayer = null; // the shop pins, regrouped whenever the zoom changes

// The signed-in customer's own pin and photo (the session user doesn't carry them)
const me = ref(null);
const home = computed(() => {
  const lat = Number(me.value?.latitude);
  const lng = Number(me.value?.longitude);
  return me.value?.has_pin && Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
});

async function loadMe() {
  if (!isAuthenticated.value) {
    me.value = null;
    return;
  }
  try {
    const { data } = await api.get("/stores/map/me");
    me.value = data?.success ? data : null;
  } catch (_) {
    me.value = null;
  }
}
const hasPin = computed(() => !!home.value);

// Straight-line distance in km
function distanceKm([lat1, lng1], [lat2, lng2]) {
  const rad = (d) => (d * Math.PI) / 180;
  const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.sqrt(a));
}
// Every shop with its distance from the customer, nearest first
const byDistance = computed(() =>
  home.value
    ? shops.value.map((shop) => ({ shop, km: distanceKm(home.value, [shop.latitude, shop.longitude]) })).sort((a, b) => a.km - b.km)
    : [],
);
const inRange = computed(() => byDistance.value.filter((item) => item.km <= radiusKm.value));
const inRangeIds = computed(() => new Set(inRange.value.map((item) => item.shop.id)));
const nearest = computed(() => byDistance.value[0] || null);
// The smallest choice (up to 5 km) that reaches the nearest shop, or null when it's farther
const reachKm = computed(() => (nearest.value ? RADII.find((km) => km >= nearest.value.km) ?? null : null));
const focusedId = ref(null);

const escapeHtml = (text) =>
  String(text ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

// A round pin with the shop's logo (or its first letter) and a point underneath
// Inside the circle: bright with a green glow. Outside: faded and smaller.
const rangeClass = (group) => (!home.value ? "" : group.some((shop) => inRangeIds.value.has(shop.id)) ? " is-in" : " is-out");

function shopIcon(shop) {
  const face = shop.store_logo_path
    ? `<img src="${escapeHtml(shop.store_logo_path)}" alt="" loading="lazy" />`
    : `<span>${escapeHtml(shop.store_name?.charAt(0) || "B")}</span>`;
  return L.divIcon({
    className: "smap-pin-wrap" + rangeClass([shop]),
    html: `<span class="smap-pin">${face}</span>`,
    iconSize: [48, 58],
    iconAnchor: [24, 58],
    popupAnchor: [0, -54],
  });
}

// The customer's own pin: their profile picture (or initial) in a green ring
function homeIcon() {
  const name = me.value?.name || user.value?.name || "";
  const face = me.value?.profile_picture
    ? `<img src="${escapeHtml(me.value.profile_picture)}" alt="" />`
    : `<span>${escapeHtml(name.charAt(0).toUpperCase() || "Y")}</span>`;
  return L.divIcon({
    className: "smap-pin-wrap",
    html: `<span class="smap-pin smap-pin--me">${face}<i>You</i></span>`,
    iconSize: [52, 62],
    iconAnchor: [26, 62],
    popupAnchor: [0, -58],
  });
}

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

// Pins closer than this on screen would cover each other, so they share one stacked pin
const OVERLAP_PX = 40;

/** Groups the shops whose pins would overlap at the current zoom */
function groupShops() {
  const groups = [];
  for (const shop of shops.value) {
    const at = map.latLngToLayerPoint([shop.latitude, shop.longitude]);
    const near = groups.find((g) => g.at.distanceTo(at) < OVERLAP_PX);
    if (near) near.shops.push(shop);
    else groups.push({ at, shops: [shop] });
  }
  return groups;
}

const markerFor = new Map(); // shop id → its own pin (when it isn't stacked)

function drawShops() {
  if (!map || !shopLayer) return;
  shopLayer.clearLayers();
  markerFor.clear();
  for (const { shops: group } of groupShops()) {
    if (group.length === 1) {
      const shop = group[0];
      const marker = L.marker([shop.latitude, shop.longitude], {
        icon: shopIcon(shop),
        title: shop.store_name,
        alt: shop.store_name,
        riseOnHover: true,
        zIndexOffset: inRangeIds.value.has(shop.id) ? 500 : 0,
      })
        .addTo(shopLayer)
        .bindPopup(popupFor(shop), { closeButton: true });
      markerFor.set(shop.id, marker);
      continue;
    }
    const center = L.latLngBounds(group.map((s) => [s.latitude, s.longitude])).getCenter();
    const names = group.map((s) => s.store_name).join(", ");
    const marker = L.marker(center, { icon: stackIcon(group), title: names, alt: `${group.length} shops: ${names}`, riseOnHover: true }).addTo(shopLayer);
    marker.on("click", () => openStack(marker, group));
  }
}

/** Zoom in until the shops separate; if they never would, list them instead */
function openStack(marker, group) {
  const bounds = L.latLngBounds(group.map((s) => [s.latitude, s.longitude]));
  const target = Math.min(map.getBoundsZoom(bounds, false, L.point(OVERLAP_PX * 3, OVERLAP_PX * 3)), map.getMaxZoom());
  const apart = (zoom) => {
    const points = group.map((s) => map.project([s.latitude, s.longitude], zoom));
    return points.every((a, i) => points.every((b, j) => i === j || a.distanceTo(b) >= OVERLAP_PX));
  };
  if (target > map.getZoom() && apart(target)) {
    map.flyToBounds(bounds, { padding: [80, 80], maxZoom: target });
    return;
  }
  marker.bindPopup(stackPopup(group), { closeButton: true, maxWidth: 300 }).openPopup();
}

// Up to three logos fanned out, with how many shops share the spot
function stackIcon(group) {
  const faces = group
    .slice(0, 3)
    .map((shop, i) => {
      const face = shop.store_logo_path
        ? `<img src="${escapeHtml(shop.store_logo_path)}" alt="" loading="lazy" />`
        : `<span>${escapeHtml(shop.store_name?.charAt(0) || "B")}</span>`;
      return `<span class="smap-stack-face" style="--i:${i}">${face}</span>`;
    })
    .join("");
  const width = 48 + Math.min(group.length - 1, 2) * 18;
  return L.divIcon({
    className: "smap-pin-wrap" + rangeClass(group),
    html: `<span class="smap-stack" style="width:${width}px">${faces}<b>${group.length}</b></span>`,
    iconSize: [width, 58],
    iconAnchor: [width / 2, 58],
    popupAnchor: [0, -54],
  });
}

// A list of the shops at one spot, each with its own Visit button
function stackPopup(group) {
  const box = document.createElement("div");
  box.className = "smap-popup smap-popup--list";
  const title = document.createElement("strong");
  title.textContent = `${group.length} shops here`;
  box.appendChild(title);
  for (const shop of group) {
    const row = document.createElement("div");
    row.className = "smap-popup-row";
    const logo = document.createElement("span");
    logo.className = "smap-popup-logo";
    if (shop.store_logo_path) {
      const img = document.createElement("img");
      img.src = shop.store_logo_path;
      img.alt = "";
      logo.appendChild(img);
    } else {
      logo.textContent = shop.store_name?.charAt(0) || "B";
    }
    const text = document.createElement("span");
    text.className = "smap-popup-text";
    const name = document.createElement("b");
    name.textContent = shop.store_name;
    text.appendChild(name);
    const detail = document.createElement("small");
    detail.textContent = home.value
      ? `${distanceKm(home.value, [shop.latitude, shop.longitude]).toFixed(1)} km from your address`
      : shop.store_address || "";
    if (detail.textContent) text.appendChild(detail);
    const visit = document.createElement("button");
    visit.type = "button";
    visit.textContent = "Visit";
    visit.setAttribute("aria-label", `Visit ${shop.store_name}`);
    visit.addEventListener("click", () => emit("visit", shop.id));
    row.append(logo, text, visit);
    box.appendChild(row);
  }
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

  shopLayer = L.layerGroup().addTo(map);
  drawShops();
  map.on("zoomend", drawShops);
  if (home.value) {
    L.marker(home.value, { icon: homeIcon(), title: "Your address", alt: "Your address", zIndexOffset: 1000 })
      .addTo(map)
      .bindTooltip("Your pinned address", { direction: "top", offset: [0, -58] });
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
    const [{ data }] = await Promise.all([api.get("/stores/map"), loadMe()]);
    shops.value = Array.isArray(data?.data) ? data.data : [];
  } catch (_) {
    error.value = true;
  } finally {
    loading.value = false;
  }
  if (!error.value) render();
}

/** From a card: fly to the shop and open its pin (zooming in far enough to unstack it) */
function focusShop(shop) {
  if (!map) return;
  focusedId.value = shop.id;
  mapEl.value?.scrollIntoView({ behavior: "smooth", block: "center" });
  map.once("moveend", () => markerFor.get(shop.id)?.openPopup());
  map.flyTo([shop.latitude, shop.longitude], Math.max(map.getZoom(), 17), { duration: 0.8 });
}

// Tell the page which shops are inside the circle (null when the customer has no pin)
function emitRange() {
  emit(
    "range",
    home.value
      ? {
          km: radiusKm.value,
          shopIds: inRange.value.map((item) => item.shop.id),
          ownerIds: inRange.value.map((item) => item.shop.owner_id).filter(Boolean),
        }
      : null,
  );
}

watch(radiusKm, () => {
  drawCircle(true);
  drawShops();
});
watch(
  () => [home.value?.join(), radiusKm.value, inRange.value.map((item) => item.shop.id).join()],
  emitRange,
  { immediate: true },
);
// Logging in or out redraws the map around the customer's pin
watch(isAuthenticated, async () => {
  await loadMe();
  if (!loading.value && !error.value) render();
});

onMounted(load);
onBeforeUnmount(() => {
  map?.remove();
  map = null;
  shopLayer = null;
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

.smap-range {
  margin-top: 16px;
}

.smap-range-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px 20px;
  margin-bottom: 14px;
}

.smap-range-title {
  margin: 0;
  font-size: 14px;
  color: var(--ink-soft);
}

.smap-range-title strong {
  color: var(--ink);
  font-weight: 600;
}

.smap-only {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  font-size: 13.5px;
  color: var(--ink);
  cursor: pointer;
}

.smap-only input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.smap-only-track {
  position: relative;
  flex-shrink: 0;
  width: 38px;
  height: 22px;
  border-radius: 999px;
  background: rgba(54, 63, 78, 0.2);
  transition: background-color 0.2s ease;
}

.smap-only-track::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(54, 63, 78, 0.3);
  transition: transform 0.2s ease;
}

.smap-only input:checked + .smap-only-track {
  background: var(--leaf-deep);
}

.smap-only input:checked + .smap-only-track::after {
  transform: translateX(16px);
}

.smap-only input:focus-visible + .smap-only-track {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.smap-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.smap-card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px 8px 8px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.smap-card:hover,
.smap-card.active {
  border-color: var(--leaf);
  box-shadow: 0 10px 24px -18px rgba(46, 122, 79, 0.6);
}

.smap-card-main {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 12px;
  min-width: 0;
  min-height: 44px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.smap-card-logo {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 44px;
  height: 44px;
  overflow: hidden;
  border-radius: 50%;
  background: var(--ivory);
  font-family: var(--font-display);
  font-size: 20px;
  color: var(--leaf-deep);
}

.smap-card-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.smap-card-text {
  display: grid;
  min-width: 0;
}

.smap-card-text strong {
  overflow: hidden;
  font-size: 14px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.smap-card-text small {
  font-size: 12.5px;
  color: var(--leaf-deep);
}

.smap-card-visit {
  flex-shrink: 0;
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: transparent;
  color: var(--ink);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.smap-card-visit:hover {
  border-color: var(--ink);
}

.smap-card-main:focus-visible,
.smap-card-visit:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
  border-radius: 10px;
}

.smap-none {
  display: grid;
  justify-items: start;
  gap: 10px;
  padding: 18px 20px;
  border: 1px dashed var(--line);
  border-radius: 14px;
  background: #fff;
  font-size: 14px;
  color: var(--ink-soft);
}

.smap-none p {
  margin: 0;
}

.smap-none strong {
  color: var(--ink);
  font-weight: 600;
}

.smap-btn--ghost {
  border: 1px solid var(--line);
  background: transparent;
  color: var(--ink);
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

/* In range: a soft green glow. Out of range: faded and a little smaller. */
.smap-pin-wrap.is-in .smap-pin,
.smap-pin-wrap.is-in .smap-stack-face {
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.45), 0 8px 18px -4px rgba(46, 122, 79, 0.55);
}

.smap-pin-wrap.is-out {
  opacity: 0.45;
  filter: grayscale(0.6);
}

.smap-pin-wrap.is-out .smap-pin,
.smap-pin-wrap.is-out .smap-stack {
  transform: scale(0.82);
  transform-origin: 50% 100%;
}

.smap-pin-wrap.is-out:hover {
  opacity: 0.85;
  filter: none;
}

.leaflet-marker-icon:hover .smap-pin {
  transform: translateY(-2px) scale(1.05);
}

.smap-pin--me {
  width: 52px;
  height: 52px;
  border-color: #4db173;
  background: #363f4e;
}

.smap-pin--me::after {
  border-top-color: #4db173;
}

.leaflet-container .leaflet-marker-pane .smap-pin--me img {
  width: 46px !important;
  height: 46px !important;
  max-width: 46px !important;
  max-height: 46px !important;
}

.smap-pin--me span {
  color: #fff;
}

.smap-pin--me i {
  position: absolute;
  top: -10px;
  left: 50%;
  padding: 1px 7px;
  border-radius: 999px;
  background: #2e7a4f;
  color: #fff;
  font: 600 10px/1.5 "Poppins", -apple-system, "Segoe UI", sans-serif;
  font-style: normal;
  letter-spacing: 0.04em;
  transform: translateX(-50%);
  white-space: nowrap;
}

.smap-stack {
  position: relative;
  display: block;
  height: 48px;
}

.smap-stack-face {
  position: absolute;
  top: 0;
  left: calc(var(--i) * 18px);
  z-index: calc(3 - var(--i));
  box-sizing: border-box;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  overflow: hidden;
  border: 3px solid #fff;
  border-radius: 50%;
  background: #f2f5f2;
  box-shadow: 0 6px 16px -4px rgba(54, 63, 78, 0.45);
}

.leaflet-container .leaflet-marker-pane .smap-stack-face img {
  display: block;
  width: 42px !important;
  height: 42px !important;
  max-width: 42px !important;
  max-height: 42px !important;
  border-radius: 50%;
  object-fit: cover;
}

.smap-stack-face span {
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 22px;
  font-weight: 600;
  color: #2e7a4f;
}

.smap-stack::after {
  content: "";
  position: absolute;
  left: 24px;
  bottom: -10px;
  border-left: 7px solid transparent;
  border-right: 7px solid transparent;
  border-top: 10px solid #fff;
  transform: translateX(-50%);
}

.smap-stack b {
  position: absolute;
  top: -6px;
  right: -6px;
  z-index: 5;
  display: grid;
  place-items: center;
  min-width: 22px;
  height: 22px;
  padding: 0 5px;
  border: 2px solid #fff;
  border-radius: 999px;
  background: #2e7a4f;
  color: #fff;
  font: 600 11px/1 "Poppins", -apple-system, "Segoe UI", sans-serif;
}

.leaflet-marker-icon:hover .smap-stack {
  transform: translateY(-2px);
}

.smap-popup--list {
  gap: 8px;
  min-width: 240px;
}

.smap-popup-row {
  display: grid;
  grid-template-columns: 36px 1fr auto;
  align-items: center;
  gap: 10px;
  padding-top: 8px;
  border-top: 1px solid rgba(54, 63, 78, 0.08);
}

.smap-popup-logo {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  overflow: hidden;
  border-radius: 50%;
  background: #f2f5f2;
  color: #2e7a4f;
  font-weight: 600;
}

.smap-popup-logo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.smap-popup-text {
  display: grid;
  min-width: 0;
}

.smap-popup-text b {
  overflow: hidden;
  font-size: 13px;
  font-weight: 600;
  color: #363f4e;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.smap-popup-text small {
  font-size: 11.5px;
  color: #6a7282;
}

.smap-popup .smap-popup-row button {
  margin-top: 0;
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
