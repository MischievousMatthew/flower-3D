<template>
  <div class="fc-root" :class="`theme-${theme}`">
    <NavHeader :cartCount="cartCount" :isAuthenticated="isAuthenticated" />

    <!-- ── Toolbar ─────────────────────────────────────────────────────── -->
    <div class="fc-toolbar">
      <div class="fc-toolbar-left">
        <div class="fc-logo-mark">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z" />
            <circle cx="12" cy="9" r="2.5" fill="#1a1208" />
          </svg>
        </div>
        <span class="fc-title">Bouquet Studio</span>
        <div class="fc-tabs">
          <button
            v-for="t in TABS"
            :key="t.key"
            class="fc-tab"
            :class="{ active: activeTab === t.key }"
            @click="activeTab = t.key"
          >
            {{ t.label }}
          </button>
        </div>
      </div>
      <div class="fc-toolbar-right">
        <button class="fc-icon-btn" title="Reset" @click="resetCamera">
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
          >
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
          </svg>
        </button>
        <button class="fc-icon-btn" title="Screenshot" @click="takeScreenshot">
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
          >
            <rect x="3" y="7" width="18" height="14" rx="2" />
            <circle cx="12" cy="14" r="3" />
            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          </svg>
        </button>
        <button
          class="fc-icon-btn"
          :class="{ active: autoRotate }"
          title="Auto rotate"
          @click="autoRotate = !autoRotate"
        >
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
          >
            <path d="M21.5 2v6h-6" />
            <path d="M21.34 15.57a10 10 0 1 1-.57-8.38" />
          </svg>
        </button>
        <button
          class="fc-icon-btn"
          :class="{ active: wireframe }"
          title="Wireframe"
          @click="toggleWireframe"
        >
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
          >
            <polygon points="12 2 22 20 2 20" />
          </svg>
        </button>
        <div class="fc-vdiv"></div>
        <button class="fc-theme-btn" @click="toggleTheme">
          <svg
            v-if="theme === 'dark'"
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
          </svg>
          <svg
            v-else
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="12" cy="12" r="4" />
            <path
              d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"
            />
          </svg>
          {{ theme === "dark" ? "Light" : "Dark" }}
        </button>
        <button
          class="fc-save-btn"
          @click="handleSaveDesign"
          :disabled="isSaving"
        >
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
          >
            <path
              d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
            />
            <polyline points="17 21 17 13 7 13 7 21" />
            <polyline points="7 3 7 8 15 8" />
          </svg>
          {{ isSaving ? "Saving…" : "Save" }}
        </button>
      </div>
    </div>

    <!-- ── 3-column layout ────────────────────────────────────────────── -->
    <div class="fc-layout">
      <!-- LEFT -->
      <aside class="fc-left">
        <div class="fc-left-head">
          <span class="fc-panel-title">Flowers</span>
          <span class="fc-counter"
            ><b>{{ selectedFlowers.length }}</b
            >/{{ MAX_FLOWERS }}</span
          >
        </div>
        <div class="fc-search-box">
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
          >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            v-model="flowerSearch"
            placeholder="Search flowers…"
            class="fc-search"
          />
        </div>

        <div v-if="loadingFlowers" class="fc-grid">
          <div v-for="n in 6" :key="n" class="fc-card fc-card-skel">
            <div class="fc-skel fc-skel-img"></div>
            <div style="padding: 7px 9px 9px">
              <div
                class="fc-skel"
                style="
                  height: 10px;
                  width: 70%;
                  border-radius: 3px;
                  margin-bottom: 5px;
                "
              ></div>
              <div
                class="fc-skel"
                style="height: 10px; width: 45%; border-radius: 3px"
              ></div>
            </div>
          </div>
        </div>

        <div v-else class="fc-grid">
          <div
            v-for="f in filteredFlowers"
            :key="f.id"
            class="fc-card"
            :class="{
              'fc-card-drag': draggingFlower?.id === f.id,
              'fc-card-off': !canAdd(f),
              'fc-card-on': countOf(f.id) > 0,
            }"
            draggable="true"
            @dragstart="onDragStart($event, f)"
            @dragend="onDragEnd"
            @click="addFlower(f)"
          >
            <div class="fc-card-media">
              <img
                v-if="getImg(f)"
                :src="getImg(f)"
                class="fc-card-img"
                @error="(e) => (e.target.style.display = 'none')"
              />
              <div v-else class="fc-card-no-img">
                <svg
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  opacity="0.3"
                >
                  <path
                    d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"
                  />
                </svg>
              </div>
              <div class="fc-card-ov">
                <span class="fc-ov-label" :class="{ off: !canAdd(f) }">
                  {{
                    !canAdd(f)
                      ? selectedFlowers.length >= MAX_FLOWERS
                        ? "Full"
                        : "Limit"
                      : "+ Add"
                  }}
                </span>
              </div>
              <div v-if="countOf(f.id) > 0" class="fc-badge">
                {{ countOf(f.id) }}
              </div>
            </div>
            <div class="fc-card-foot">
              <p class="fc-card-nm">{{ f.product_name }}</p>
              <p class="fc-card-pr">
                ₱{{ Math.round(parseFloat(f.selling_price)) }}
              </p>
            </div>
          </div>
          <div v-if="!filteredFlowers.length" class="fc-no-flowers">
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.3"
              opacity="0.25"
            >
              <path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z" />
            </svg>
            <p>No flowers found</p>
          </div>
        </div>

        <div class="fc-dots-wrap">
          <span class="fc-dots-lbl">Bouquet slots</span>
          <div class="fc-dots">
            <div
              v-for="i in MAX_FLOWERS"
              :key="i"
              class="fc-dot"
              :class="{
                'fc-dot-on': !!selectedFlowers[i - 1],
                'fc-dot-next': i === selectedFlowers.length + 1,
              }"
            ></div>
          </div>
        </div>
      </aside>

      <!-- CENTER -->
      <div
        class="fc-canvas"
        @dragover.prevent="isDragOver = true"
        @drop.prevent="onDrop"
        @dragleave="isDragOver = false"
        :class="{ 'fc-drop': isDragOver }"
      >
        <div ref="viewerContainer" class="fc-viewer"></div>

        <transition name="fade">
          <div v-if="isDragOver" class="fc-drop-hint">
            <div class="fc-drop-ring">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path
                  d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"
                />
              </svg>
              Drop to add
            </div>
          </div>
        </transition>

        <transition name="fade">
          <div v-if="isLoading3D" class="fc-loader">
            <div class="fc-spin"></div>
            <span>Loading…</span>
          </div>
        </transition>

        <div v-if="selectedFlowers.length" class="fc-chips">
          <div
            v-for="(f, i) in selectedFlowers"
            :key="i"
            class="fc-chip"
            @click="removeFlower(i)"
            :title="'Remove ' + f.product_name"
          >
            <img
              v-if="getImg(f)"
              :src="getImg(f)"
              class="fc-chip-img"
              @error="(e) => (e.target.style.display = 'none')"
            />
            <span v-else class="fc-chip-lt">{{
              (f.product_name || "F")[0]
            }}</span>
            <div class="fc-chip-x">✕</div>
          </div>
        </div>

        <div class="fc-hint-bar">
          <span>Drag to rotate</span><span class="fc-dot-sep">·</span>
          <span>Scroll to zoom</span><span class="fc-dot-sep">·</span>
          <span>Drop flowers here</span>
        </div>
      </div>

      <!-- RIGHT -->
      <aside class="fc-right">
        <!-- Wrapping -->
        <div
          v-show="activeTab === 'flowers' || activeTab === 'wrapping'"
          class="fc-section"
        >
          <p class="fc-sec-lbl">Wrapping paper</p>
          <div class="fc-swatches">
            <button
              v-for="c in wrapPresets"
              :key="c"
              class="fc-sw"
              :class="{ active: paperColor === c }"
              :style="{ background: c }"
              @click="applyPaper(c)"
            ></button>
          </div>
          <div class="fc-color-row">
            <input
              type="color"
              v-model="paperColor"
              @input="applyPaper(paperColor)"
              class="fc-color-pick"
            />
            <code class="fc-hex">{{ paperColor.toUpperCase() }}</code>
            <span
              class="fc-color-chip"
              :style="{ background: paperColor }"
            ></span>
          </div>
        </div>

        <!-- Ribbon -->
        <div
          v-show="activeTab === 'flowers' || activeTab === 'ribbon'"
          class="fc-section"
        >
          <p class="fc-sec-lbl">Ribbon</p>
          <div class="fc-swatches">
            <button
              v-for="c in ribbonPresets"
              :key="c"
              class="fc-sw"
              :class="{ active: ribbonColor === c }"
              :style="{ background: c }"
              @click="applyRibbon(c)"
            ></button>
          </div>
          <div class="fc-color-row">
            <input
              type="color"
              v-model="ribbonColor"
              @input="applyRibbon(ribbonColor)"
              class="fc-color-pick"
            />
            <code class="fc-hex">{{ ribbonColor.toUpperCase() }}</code>
            <span
              class="fc-color-chip"
              :style="{ background: ribbonColor }"
            ></span>
          </div>
        </div>

        <!-- Arrangement -->
        <div v-show="activeTab === 'flowers'" class="fc-section">
          <p class="fc-sec-lbl">Arrangement</p>
          <div class="fc-sl-row">
            <span class="fc-sl-lbl">Spread</span
            ><input
              type="range"
              min="0.5"
              max="2"
              step="0.05"
              v-model.number="bloomSpread"
              @input="rebuildFlowers"
              class="fc-sl"
            /><span class="fc-sl-val">{{ bloomSpread.toFixed(1) }}×</span>
          </div>
          <div class="fc-sl-row">
            <span class="fc-sl-lbl">Height</span
            ><input
              type="range"
              min="0.5"
              max="2"
              step="0.05"
              v-model.number="flowerHeight"
              @input="rebuildFlowers"
              class="fc-sl"
            /><span class="fc-sl-val">{{ flowerHeight.toFixed(1) }}×</span>
          </div>
          <div class="fc-sl-row">
            <span class="fc-sl-lbl">Density</span
            ><input
              type="range"
              min="1"
              max="3"
              step="0.1"
              v-model.number="density"
              @input="rebuildFlowers"
              class="fc-sl"
            /><span class="fc-sl-val">{{ density.toFixed(1) }}</span>
          </div>
        </div>

        <!-- Summary -->
        <div class="fc-section fc-sum">
          <p class="fc-sec-lbl">Order summary</p>
          <div v-if="!selectedFlowers.length" class="fc-sum-empty">
            <svg
              width="34"
              height="34"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.2"
              opacity="0.2"
            >
              <path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z" />
              <circle cx="12" cy="9" r="2.5" />
            </svg>
            <p>Add flowers to start</p>
          </div>
          <div v-else class="fc-lines">
            <div v-for="(g, nm) in groupedFlowers" :key="nm" class="fc-line">
              <span class="fc-ln">{{ nm }}</span>
              <span class="fc-lq">×{{ g.qty }}</span>
              <span class="fc-lp">₱{{ (g.price * g.qty).toFixed(0) }}</span>
            </div>
          </div>
          <div class="fc-sep"></div>
          <div class="fc-meta">
            <div class="fc-mr">
              <span>Wrapping</span
              ><span class="fc-ms" :style="{ background: paperColor }"></span>
            </div>
            <div class="fc-mr">
              <span>Ribbon</span
              ><span class="fc-ms" :style="{ background: ribbonColor }"></span>
            </div>
            <div class="fc-mr">
              <span>Flowers</span
              ><span>{{ selectedFlowers.length }}/{{ MAX_FLOWERS }}</span>
            </div>
          </div>
          <div class="fc-sep"></div>
          <div class="fc-total">
            <span class="fc-tot-lbl">Total</span>
            <span class="fc-tot-val"
              >₱{{
                totalPrice.toLocaleString("en-PH", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })
              }}</span
            >
          </div>
          <button
            class="fc-btn-co"
            :disabled="!selectedFlowers.length || isCheckingOut"
            @click="checkout"
          >
            <span v-if="isCheckingOut">Processing…</span>
            <template v-else
              >Proceed to checkout
              <svg
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <line x1="5" y1="12" x2="19" y2="12" />
                <polyline points="12 5 19 12 12 19" /></svg
            ></template>
          </button>
          <button
            class="fc-btn-ca"
            :disabled="!selectedFlowers.length"
            @click="addCart"
          >
            <svg
              width="13"
              height="13"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="9" cy="21" r="1" />
              <circle cx="20" cy="21" r="1" />
              <path
                d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"
              />
            </svg>
            Add to cart
          </button>
        </div>
      </aside>
    </div>

    <!-- Toast -->
    <transition name="toast-anim">
      <div v-if="toast.show" class="fc-toast" :class="toast.type">
        <svg
          v-if="toast.type === 'success'"
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
        >
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <svg
          v-else
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
        >
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";

const router = useRouter();
const route = useRoute();
const { isAuthenticated } = useAuth();
const cartStore = useCart();

const MAX_FLOWERS = 7;
const TABS = [
  { key: "flowers", label: "Flowers" },
  { key: "wrapping", label: "Wrapping" },
  { key: "ribbon", label: "Ribbon" },
  { key: "summary", label: "Summary" },
];

// State
const activeTab = ref("flowers");
const flowerSearch = ref("");
const flowers = ref([]);
const loadingFlowers = ref(false);
const selectedFlowers = ref([]);
const draggingFlower = ref(null);
const isDragOver = ref(false);
const isSaving = ref(false);
const isCheckingOut = ref(false);
const autoRotate = ref(true);
const wireframe = ref(false);
const theme = ref("dark");
const paperColor = ref("#c8a862");
const ribbonColor = ref("#c9b4d4");
const bloomSpread = ref(1.0);
const flowerHeight = ref(1.0);
const density = ref(1.5);
const viewerContainer = ref(null);
const isLoading3D = ref(true);
const toast = reactive({ show: false, message: "", type: "success" });

const wrapPresets = [
  "#c8a862",
  "#ede0cc",
  "#e8c9c9",
  "#c9e2d4",
  "#cdd4e8",
  "#242220",
  "#f5f0e8",
];
const ribbonPresets = [
  "#c9b4d4",
  "#f4c0d1",
  "#b4d4c9",
  "#b4c9e8",
  "#eed4b4",
  "#8b5a3c",
  "#f2ede8",
];

// Three.js
let scene, camera, renderer, animId;
let bouquetGroup = null,
  flowerGroup = null;
let paperMeshes = [],
  ribbonMeshes = [];
let dragging3D = false,
  prevMouse = { x: 0, y: 0 };
let rotX = 0,
  rotY = 0,
  zoom = 5;
const loader = new GLTFLoader();
const cache = new Map();
let fVer = 0;

// Computed
const cartCount = computed(() => cartStore.count?.value ?? 0);
const filteredFlowers = computed(() => {
  const q = flowerSearch.value.toLowerCase();
  return flowers.value.filter(
    (f) => !q || (f.product_name || "").toLowerCase().includes(q),
  );
});
const totalPrice = computed(() =>
  selectedFlowers.value.reduce(
    (s, f) => s + parseFloat(f.price ?? f.selling_price ?? 0),
    0,
  ),
);
const groupedFlowers = computed(() => {
  const g = {};
  for (const f of selectedFlowers.value) {
    if (!g[f.product_name])
      g[f.product_name] = {
        qty: 0,
        price: parseFloat(f.price ?? f.selling_price ?? 0),
      };
    g[f.product_name].qty++;
  }
  return g;
});
const countOf = (id) => selectedFlowers.value.filter((f) => f.id === id).length;
const canAdd = (f) =>
  selectedFlowers.value.length < MAX_FLOWERS &&
  countOf(f.id) < (parseInt(f.quantity_in_stock ?? 99) || 99);

// Lifecycle
onMounted(async () => {
  try {
    theme.value = localStorage.getItem("fc_theme") || "dark";
  } catch {}
  if (isAuthenticated.value) await cartStore.initialize?.();
  await fetchFlowers();
  initViewer();
  window.addEventListener("resize", onResize);
});
onUnmounted(() => {
  cleanup();
  window.removeEventListener("resize", onResize);
});

// Data
const fetchFlowers = async () => {
  loadingFlowers.value = true;
  try {
    const r = await api.get("customer/products", { params: { per_page: 40 } });
    if (r.data.success) {
      const raw = Array.isArray(r.data.data)
        ? r.data.data
        : (r.data.data?.data ?? []);
      flowers.value = raw.length ? raw : demoFlowers();
    } else flowers.value = demoFlowers();
  } catch {
    flowers.value = demoFlowers();
  } finally {
    loadingFlowers.value = false;
  }
};

const demoFlowers = () => [
  {
    id: 1,
    product_name: "Red Rose",
    selling_price: "85",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1518709594023-6eab9bab7b23?w=220&h=220&fit=crop",
    },
  },
  {
    id: 2,
    product_name: "White Lily",
    selling_price: "70",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1525310072745-f49212b5ac6d?w=220&h=220&fit=crop",
    },
  },
  {
    id: 3,
    product_name: "Sunflower",
    selling_price: "60",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1597848212624-a19eb35e2651?w=220&h=220&fit=crop",
    },
  },
  {
    id: 4,
    product_name: "Pink Tulip",
    selling_price: "75",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=220&h=220&fit=crop",
    },
  },
  {
    id: 5,
    product_name: "Baby's Breath",
    selling_price: "40",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1563241527-3004b7be0ffd?w=220&h=220&fit=crop",
    },
  },
  {
    id: 6,
    product_name: "Lavender",
    selling_price: "55",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=220&h=220&fit=crop",
    },
  },
  {
    id: 7,
    product_name: "Daisy",
    selling_price: "45",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1596438459194-f275f413d6ff?w=220&h=220&fit=crop",
    },
  },
  {
    id: 8,
    product_name: "Orchid",
    selling_price: "120",
    primary_image: {
      image_url:
        "https://images.unsplash.com/photo-1555708982-8645ec9ce3cc?w=220&h=220&fit=crop",
    },
  },
];

const getImg = (f) =>
  f?.primary_image?.image_url ?? f?.images?.[0]?.image_url ?? null;

// 3D
const initViewer = () => {
  if (!viewerContainer.value) return;
  cleanup();
  isLoading3D.value = true;

  const dark = theme.value === "dark";
  const bg = dark ? "#1e1a14" : "#ede6da";

  scene = new THREE.Scene();
  scene.background = new THREE.Color(bg);
  scene.fog = new THREE.FogExp2(dark ? 0x1e1a14 : 0xede6da, 0.032);

  const w = viewerContainer.value.clientWidth,
    h = viewerContainer.value.clientHeight;
  camera = new THREE.PerspectiveCamera(38, w / h, 0.1, 200);
  camera.position.set(0, 1.2, zoom);

  renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(w, h);
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.15;
  viewerContainer.value.appendChild(renderer.domElement);

  // Lighting — warm, cinematic
  scene.add(new THREE.AmbientLight(0xfff5e8, 0.65));
  const sun = new THREE.DirectionalLight(0xfff3d0, 1.5);
  sun.position.set(4, 9, 6);
  sun.castShadow = true;
  sun.shadow.mapSize.setScalar(2048);
  sun.shadow.camera.far = 40;
  [-6, 6].forEach((v) => {
    sun.shadow.camera.left = sun.shadow.camera.bottom = -v;
    sun.shadow.camera.right = sun.shadow.camera.top = v;
  });
  scene.add(sun);
  const fill = new THREE.DirectionalLight(0xc8deff, 0.4);
  fill.position.set(-5, 3, -3);
  scene.add(fill);
  const rim = new THREE.DirectionalLight(0xffe8c0, 0.55);
  rim.position.set(0, 5, -7);
  scene.add(rim);
  const warm = new THREE.PointLight(0xffb870, 0.7, 14);
  warm.position.set(2, 5, 3);
  scene.add(warm);

  // Ground
  const ground = new THREE.Mesh(
    new THREE.CircleGeometry(9, 64),
    new THREE.ShadowMaterial({ opacity: dark ? 0.38 : 0.16 }),
  );
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -2.5;
  ground.receiveShadow = true;
  scene.add(ground);

  // Glow ring
  const ring = new THREE.Mesh(
    new THREE.RingGeometry(1.4, 2.2, 80),
    new THREE.MeshBasicMaterial({
      color: 0xb8902a,
      transparent: true,
      opacity: dark ? 0.12 : 0.07,
      side: THREE.DoubleSide,
    }),
  );
  ring.rotation.x = -Math.PI / 2;
  ring.position.y = -2.48;
  scene.add(ring);

  bouquetGroup = new THREE.Group();
  flowerGroup = new THREE.Group();
  scene.add(bouquetGroup);
  scene.add(flowerGroup);

  buildBouquet();
  setupControls();
  animLoop();
};

// Bouquet builder
const clearGrp = (g) => {
  while (g.children.length) g.remove(g.children[0]);
};

const buildBouquet = () => {
  if (!bouquetGroup) return;
  clearGrp(bouquetGroup);
  paperMeshes = [];
  ribbonMeshes = [];

  const pmat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(paperColor.value),
    roughness: 0.8,
    metalness: 0.02,
    side: THREE.DoubleSide,
  });
  const outer = new THREE.Mesh(
    new THREE.ConeGeometry(1.5, 3.4, 52, 1, true),
    pmat,
  );
  outer.position.y = -0.2;
  outer.castShadow = true;
  paperMeshes.push(outer);
  bouquetGroup.add(outer);

  const imat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(shade(paperColor.value, -28)),
    roughness: 0.9,
    side: THREE.BackSide,
  });
  const inner = new THREE.Mesh(
    new THREE.ConeGeometry(1.44, 3.38, 52, 1, true),
    imat,
  );
  inner.position.y = -0.2;
  bouquetGroup.add(inner);

  for (let i = 0; i < 12; i++) {
    const a = (i / 12) * Math.PI * 2;
    const cm = new THREE.MeshStandardMaterial({
      color: new THREE.Color(shade(paperColor.value, -55)),
      roughness: 1,
    });
    const cr = new THREE.Mesh(new THREE.BoxGeometry(0.022, 3.4, 0.022), cm);
    cr.position.set(Math.sin(a) * 1.47, -0.2, Math.cos(a) * 1.47);
    bouquetGroup.add(cr);
  }

  const rmat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(ribbonColor.value),
    roughness: 0.32,
    metalness: 0.12,
  });
  const band = new THREE.Mesh(
    new THREE.TorusGeometry(0.65, 0.07, 16, 80),
    rmat,
  );
  band.position.y = -1.65;
  band.rotation.x = Math.PI / 2;
  band.castShadow = true;
  ribbonMeshes.push(band);
  bouquetGroup.add(band);

  const bowMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(ribbonColor.value),
    roughness: 0.28,
    metalness: 0.14,
  });
  for (const [s] of [[-1], [1]]) {
    const loop = new THREE.Mesh(
      new THREE.TorusGeometry(0.26, 0.06, 10, 48, Math.PI),
      bowMat,
    );
    loop.position.set(s * 0.32, -1.5, 0);
    loop.rotation.z = s * (-Math.PI / 5);
    if (s === 1) loop.rotation.y = Math.PI;
    loop.castShadow = true;
    ribbonMeshes.push(loop);
    bouquetGroup.add(loop);
  }
  const knot = new THREE.Mesh(new THREE.SphereGeometry(0.1, 16, 16), bowMat);
  knot.position.y = -1.5;
  ribbonMeshes.push(knot);
  bouquetGroup.add(knot);
  for (const s of [-1, 1]) {
    const tail = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.6, 0.05), bowMat);
    tail.position.set(s * 0.14, -1.95, 0);
    tail.rotation.z = s * 0.3;
    tail.castShadow = true;
    ribbonMeshes.push(tail);
    bouquetGroup.add(tail);
  }

  const smat = new THREE.MeshStandardMaterial({
    color: 0x3e7234,
    roughness: 0.85,
  });
  for (let i = 0; i < 8; i++) {
    const a = (i / 8) * Math.PI * 2,
      s = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.042, 2.8, 8), smat);
    s.position.set(Math.sin(a) * 0.3, 0.18, Math.cos(a) * 0.3);
    s.rotation.z = Math.sin(a) * 0.07;
    s.castShadow = true;
    bouquetGroup.add(s);
  }

  const lmat = new THREE.MeshStandardMaterial({
    color: 0x428036,
    roughness: 0.76,
    side: THREE.DoubleSide,
  });
  for (let i = 0; i < 10; i++) {
    const a = (i / 10) * Math.PI * 2,
      r = 0.78 + (i % 3) * 0.14,
      lf = new THREE.Mesh(new THREE.PlaneGeometry(0.4, 0.7), lmat);
    lf.position.set(Math.sin(a) * r, 0.6 + (i % 2) * 0.28, Math.cos(a) * r);
    lf.rotation.y = a;
    lf.rotation.x = -0.32;
    lf.castShadow = true;
    bouquetGroup.add(lf);
  }

  const bmat = new THREE.MeshStandardMaterial({
    color: 0xf5f2ee,
    roughness: 0.6,
  });
  for (let i = 0; i < 50; i++) {
    const a = Math.random() * Math.PI * 2,
      r = 0.18 + Math.random() * 1.0,
      bb = new THREE.Mesh(
        new THREE.SphereGeometry(0.033 + Math.random() * 0.032, 6, 6),
        bmat,
      );
    bb.position.set(
      Math.sin(a) * r,
      1.3 + Math.random() * 1.1,
      Math.cos(a) * r,
    );
    bouquetGroup.add(bb);
  }

  bouquetGroup.position.y = 0.7;
  bouquetGroup.scale.setScalar(0.01);
  const t0 = Date.now();
  const enter = () => {
    const t = Math.min((Date.now() - t0) / 900, 1);
    bouquetGroup.scale.setScalar(1 - Math.pow(1 - t, 3));
    if (t < 1) requestAnimationFrame(enter);
  };
  enter();
  isLoading3D.value = false;
  void rebuildFlowers();
};

const PCOLS = [
  0xe8344d, 0xf9a8d4, 0xfde047, 0xc084fc, 0xfb923c, 0x60a5fa, 0xf472b6,
  0x34d399,
];

const rebuildFlowers = async () => {
  if (!flowerGroup) return;
  clearGrp(flowerGroup);
  const v = ++fVer;
  const meshes = await Promise.all(
    selectedFlowers.value.map((f, i) => buildFlowerMesh(f, i)),
  );
  if (v !== fVer || !flowerGroup) return;
  meshes.filter(Boolean).forEach((m) => flowerGroup.add(m));
};

const buildFlowerMesh = async (flower, idx) => {
  const url = modelUrl(flower);
  const g = new THREE.Group();
  const total = Math.max(selectedFlowers.value.length, 1);
  const a = (idx / total) * Math.PI * 2;
  const r = (0.22 + (idx % 3) * 0.2) * bloomSpread.value;
  const h = 1.35 + (idx % 2) * 0.32 * flowerHeight.value;

  g.position.set(Math.sin(a) * r, h + bouquetGroup.position.y, Math.cos(a) * r);

  if (url) {
    try {
      let model;
      if (cache.has(url)) {
        model = cache.get(url).clone(true);
      } else {
        const gltf = await new Promise((res, rej) =>
          loader.load(url, res, undefined, rej),
        );
        const root = gltf.scene ?? gltf.scenes?.[0];
        root.traverse((c) => {
          if (c.isMesh) {
            c.castShadow = true;
            c.receiveShadow = true;
          }
        });
        cache.set(url, root);
        model = root.clone(true);
      }
      const box = new THREE.Box3().setFromObject(model);
      const sz = box.getSize(new THREE.Vector3());
      const sc = (0.72 * density.value) / Math.max(sz.x, sz.y, sz.z, 1);
      model.scale.setScalar(sc);
      model.position.sub(box.getCenter(new THREE.Vector3()).multiplyScalar(sc));
      model.rotation.y = a + Math.PI;
      g.add(model);
    } catch {
      g.add(proceduralFlower(idx));
    }
  } else {
    g.add(proceduralFlower(idx));
  }

  g.scale.setScalar(0.01);
  const t0 = Date.now();
  const pop = () => {
    const t = Math.min((Date.now() - t0) / 480, 1);
    g.scale.setScalar(1 - Math.pow(1 - t, 2));
    if (t < 1) requestAnimationFrame(pop);
  };
  pop();
  return g;
};

const proceduralFlower = (idx) => {
  const g = new THREE.Group();
  const col = PCOLS[idx % PCOLS.length];
  const pm = new THREE.MeshStandardMaterial({
    color: col,
    roughness: 0.52,
    metalness: 0.04,
  });
  const cm = new THREE.MeshStandardMaterial({
    color: 0xf6c722,
    roughness: 0.45,
  });
  g.add(new THREE.Mesh(new THREE.SphereGeometry(0.12, 14, 14), cm));
  const n = 6 + (idx % 4);
  for (let p = 0; p < n; p++) {
    const a = (p / n) * Math.PI * 2,
      pt = new THREE.Mesh(new THREE.SphereGeometry(0.1, 8, 6), pm);
    pt.position.set(Math.sin(a) * 0.2, 0.01, Math.cos(a) * 0.2);
    pt.scale.set(1, 0.44, 1.9);
    pt.castShadow = true;
    g.add(pt);
  }
  return g;
};

const modelUrl = (f) => {
  if (!f) return null;
  const rec = Array.isArray(f.models) ? f.models[0] : null;
  const raw = rec?.model_url ?? rec?.model_path ?? f.model_3d_url ?? null;
  if (!raw || typeof raw !== "string") return null;
  if (/^https?:\/\//i.test(raw)) return raw.replace(/^http:\/\//i, "https://");
  if (raw.startsWith("/")) return `${location.origin}${raw}`;
  return `https://res.cloudinary.com/dqs1e1inx/raw/upload/${raw.replace(/^\/+/, "")}`;
};

// Colors
const applyPaper = (c) => {
  paperColor.value = c;
  if (paperMeshes[0]) paperMeshes[0].material.color.set(c);
  bouquetGroup?.children.forEach((ch) => {
    if (ch.geometry?.type === "ConeGeometry" && ch !== paperMeshes[0])
      ch.material.color.set(shade(c, -28));
  });
};
const applyRibbon = (c) => {
  ribbonColor.value = c;
  ribbonMeshes.forEach((m) => m.material?.color.set(c));
};

// Drag & drop
const onDragStart = (e, f) => {
  if (!canAdd(f)) {
    e.preventDefault();
    return;
  }
  draggingFlower.value = f;
  e.dataTransfer.effectAllowed = "copy";
};
const onDragEnd = () => {
  draggingFlower.value = null;
};
const onDrop = () => {
  isDragOver.value = false;
  if (draggingFlower.value) {
    addFlower(draggingFlower.value);
    draggingFlower.value = null;
  }
};

const addFlower = (f, silent = false) => {
  if (!canAdd(f)) {
    showToast(
      selectedFlowers.value.length >= MAX_FLOWERS
        ? `Full — max ${MAX_FLOWERS}`
        : "Stock limit reached",
      "error",
    );
    return;
  }
  selectedFlowers.value.push({
    ...f,
    price: parseFloat(f.price ?? f.selling_price ?? 0),
    product_name: f.product_name || f.name,
  });
  void rebuildFlowers();
  if (!silent) showToast(`${f.product_name} added!`, "success");
};
const removeFlower = (i) => {
  selectedFlowers.value.splice(i, 1);
  void rebuildFlowers();
};

// Controls
const setupControls = () => {
  const c = renderer.domElement;
  c.addEventListener("mousedown", (e) => {
    dragging3D = true;
    autoRotate.value = false;
    prevMouse = { x: e.clientX, y: e.clientY };
    c.style.cursor = "grabbing";
  });
  c.addEventListener("mouseup", () => {
    dragging3D = false;
    c.style.cursor = "grab";
  });
  c.addEventListener("mouseleave", () => {
    dragging3D = false;
  });
  c.addEventListener("mousemove", (e) => {
    if (!dragging3D) return;
    rotY += (e.clientX - prevMouse.x) * 0.006;
    rotX = Math.max(
      -0.75,
      Math.min(0.75, rotX + (e.clientY - prevMouse.y) * 0.006),
    );
    prevMouse = { x: e.clientX, y: e.clientY };
  });
  c.addEventListener(
    "wheel",
    (e) => {
      e.preventDefault();
      zoom = Math.max(2, Math.min(12, zoom + e.deltaY * 0.005));
    },
    { passive: false },
  );
  c.style.cursor = "grab";
  let ts = null;
  c.addEventListener(
    "touchstart",
    (e) => {
      ts = { x: e.touches[0].clientX, y: e.touches[0].clientY };
      autoRotate.value = false;
    },
    { passive: true },
  );
  c.addEventListener(
    "touchmove",
    (e) => {
      if (!ts) return;
      rotY += (e.touches[0].clientX - ts.x) * 0.008;
      rotX = Math.max(
        -0.75,
        Math.min(0.75, rotX + (e.touches[0].clientY - ts.y) * 0.008),
      );
      ts = { x: e.touches[0].clientX, y: e.touches[0].clientY };
    },
    { passive: true },
  );
};

const animLoop = () => {
  animId = requestAnimationFrame(animLoop);
  if (autoRotate.value) rotY += 0.0035;
  const fy = Math.sin(Date.now() * 0.0008) * 0.055;
  if (bouquetGroup) {
    bouquetGroup.rotation.y = rotY;
    bouquetGroup.rotation.x = rotX;
    bouquetGroup.position.y = 0.7 + fy;
  }
  if (flowerGroup) {
    flowerGroup.rotation.y = rotY;
    flowerGroup.rotation.x = rotX;
    flowerGroup.position.y = fy;
  }
  camera.position.z = zoom;
  camera.lookAt(0, 0.5, 0);
  renderer?.render(scene, camera);
};

const onResize = () => {
  if (!viewerContainer.value || !camera || !renderer) return;
  const w = viewerContainer.value.clientWidth,
    h = viewerContainer.value.clientHeight;
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
  renderer.setSize(w, h);
};
const resetCamera = () => {
  rotX = 0;
  rotY = 0;
  zoom = 5;
  autoRotate.value = true;
};
const toggleWireframe = () => {
  wireframe.value = !wireframe.value;
  const tw = (o) => {
    if (o.isMesh && o.material) {
      const ms = Array.isArray(o.material) ? o.material : [o.material];
      ms.forEach((m) => (m.wireframe = wireframe.value));
    }
    o.children?.forEach(tw);
  };
  tw(bouquetGroup);
  tw(flowerGroup);
};
const takeScreenshot = () => {
  renderer?.render(scene, camera);
  const a = document.createElement("a");
  a.href = renderer?.domElement.toDataURL("image/png");
  a.download = "bouquet.png";
  a.click();
};
const cleanup = () => {
  cancelAnimationFrame(animId);
  if (renderer) {
    renderer.dispose();
    try {
      viewerContainer.value?.removeChild(renderer.domElement);
    } catch {}
  }
};

const toggleTheme = () => {
  theme.value = theme.value === "dark" ? "light" : "dark";
  try {
    localStorage.setItem("fc_theme", theme.value);
  } catch {}
  if (scene) {
    const bg = theme.value === "dark" ? "#1e1a14" : "#ede6da";
    scene.background = new THREE.Color(bg);
    scene.fog = new THREE.FogExp2(
      theme.value === "dark" ? 0x1e1a14 : 0xede6da,
      0.032,
    );
  }
};

const checkout = async () => {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  if (!selectedFlowers.value.length) return;
  isCheckingOut.value = true;
  try {
    const r = await api.post("custom-orders", {
      flowers: selectedFlowers.value.map((f) => ({
        id: f.id,
        name: f.product_name,
        price: f.price ?? f.selling_price,
      })),
      total_price: totalPrice.value,
      paper_color: paperColor.value,
      ribbon_color: ribbonColor.value,
    });
    if (r.data.success) {
      showToast("Order placed! 🌸", "success");
      router.push("/customer/orders");
    }
  } catch (e) {
    showToast(e?.response?.data?.message || "Failed to place order", "error");
  } finally {
    isCheckingOut.value = false;
  }
};

const addCart = () => {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login" });
    return;
  }
  showToast("Custom bouquet added to cart! 🌸", "success");
};

const handleSaveDesign = () => {
  isSaving.value = true;
  try {
    localStorage.setItem(
      "savedBouquet",
      JSON.stringify({
        flowers: selectedFlowers.value,
        paperColor: paperColor.value,
        ribbonColor: ribbonColor.value,
        bloomSpread: bloomSpread.value,
        flowerHeight: flowerHeight.value,
        density: density.value,
      }),
    );
    showToast("Design saved!", "success");
  } finally {
    isSaving.value = false;
  }
};

const shade = (hex, pct) => {
  try {
    const n = parseInt(hex.replace("#", ""), 16);
    const r = Math.max(0, Math.min(255, (n >> 16) + pct));
    const g = Math.max(0, Math.min(255, ((n >> 8) & 0xff) + pct));
    const b = Math.max(0, Math.min(255, (n & 0xff) + pct));
    return "#" + ((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1);
  } catch {
    return hex;
  }
};

let tTimer = null;
const showToast = (msg, type = "success") => {
  Object.assign(toast, { show: true, message: msg, type });
  clearTimeout(tTimer);
  tTimer = setTimeout(() => {
    toast.show = false;
  }, 3000);
};

watch(paperColor, (c) => applyPaper(c));
watch(ribbonColor, (c) => applyRibbon(c));
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@300;400;500;600&display=swap");

.fc-root {
  --bg: #18140f;
  --panel: #201c16;
  --pb: rgba(210, 180, 130, 0.1);
  --cb: rgba(210, 180, 130, 0.13);
  --text: #ede4d4;
  --muted: #8a7a66;
  --hint: #534a3c;
  --acc: #c8a862;
  --accd: #a8883e;
  --accdim: rgba(200, 168, 98, 0.12);
  --ibg: rgba(255, 255, 255, 0.05);
  --ibr: rgba(210, 180, 130, 0.18);
  --tbg: rgba(255, 255, 255, 0.05);
  --bbg: rgba(255, 255, 255, 0.06);
  --bbr: rgba(210, 180, 130, 0.2);
  --cvs: #1e1a14;
  --div: rgba(210, 180, 130, 0.08);
  min-height: 100vh;
  background: var(--bg);
  font-family: "DM Sans", sans-serif;
  color: var(--text);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.fc-root.theme-light {
  --bg: #f0ebe1;
  --panel: #faf6ef;
  --pb: rgba(120, 90, 50, 0.11);
  --cb: rgba(120, 90, 50, 0.13);
  --text: #2a221a;
  --muted: #7a6852;
  --hint: #a8906e;
  --acc: #9a6e2e;
  --accd: #7a5218;
  --accdim: rgba(154, 110, 46, 0.1);
  --ibg: rgba(255, 255, 255, 0.85);
  --ibr: rgba(120, 90, 50, 0.2);
  --tbg: rgba(120, 90, 50, 0.08);
  --bbg: rgba(120, 90, 50, 0.06);
  --bbr: rgba(120, 90, 50, 0.18);
  --cvs: #e4dcd0;
  --div: rgba(120, 90, 50, 0.09);
}
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* TOOLBAR */
.fc-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 18px;
  height: 52px;
  background: var(--panel);
  border-bottom: 1px solid var(--pb);
  flex-shrink: 0;
  margin-top: 72px;
  z-index: 50;
  gap: 12px;
}
.fc-toolbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}
.fc-toolbar-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.fc-logo-mark {
  width: 28px;
  height: 28px;
  background: var(--acc);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #1a1208;
  flex-shrink: 0;
}
.fc-title {
  font-family: "Playfair Display", serif;
  font-size: 15.5px;
  font-weight: 600;
  color: var(--acc);
  white-space: nowrap;
  letter-spacing: 0.01em;
}
.fc-tabs {
  display: flex;
  background: var(--tbg);
  border-radius: 7px;
  padding: 3px;
  gap: 2px;
}
.fc-tab {
  padding: 5px 13px;
  border: none;
  background: transparent;
  border-radius: 5px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.17s;
  font-family: "DM Sans", sans-serif;
}
.fc-tab:hover {
  color: var(--text);
}
.fc-tab.active {
  background: var(--acc);
  color: #1a1208;
  font-weight: 600;
}
.fc-icon-btn {
  width: 31px;
  height: 31px;
  border: 1px solid var(--bbr);
  background: var(--bbg);
  border-radius: 7px;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.17s;
}
.fc-icon-btn:hover,
.fc-icon-btn.active {
  border-color: var(--acc);
  color: var(--acc);
  background: var(--accdim);
}
.fc-vdiv {
  width: 1px;
  height: 20px;
  background: var(--pb);
  margin: 0 3px;
}
.fc-theme-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 0 11px;
  height: 31px;
  border: 1px solid var(--bbr);
  background: var(--bbg);
  border-radius: 7px;
  color: var(--muted);
  font-size: 11.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.17s;
  font-family: "DM Sans", sans-serif;
}
.fc-theme-btn:hover {
  border-color: var(--acc);
  color: var(--acc);
}
.fc-save-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 15px;
  height: 31px;
  background: var(--acc);
  border: none;
  border-radius: 7px;
  color: #1a1208;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.17s;
  font-family: "DM Sans", sans-serif;
}
.fc-save-btn:hover {
  background: var(--accd);
  transform: translateY(-1px);
}
.fc-save-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none;
}

/* LAYOUT */
.fc-layout {
  display: grid;
  grid-template-columns: 268px 1fr 286px;
  flex: 1;
  height: calc(100vh - 72px - 52px);
  overflow: hidden;
}

/* LEFT */
.fc-left {
  background: var(--panel);
  border-right: 1px solid var(--pb);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.fc-left-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 13px 9px;
  border-bottom: 1px solid var(--div);
  flex-shrink: 0;
}
.fc-panel-title {
  font-family: "Playfair Display", serif;
  font-size: 14.5px;
  font-weight: 600;
  color: var(--text);
}
.fc-counter {
  font-size: 11.5px;
  background: var(--accdim);
  color: var(--acc);
  padding: 2px 8px;
  border-radius: 20px;
}
.fc-counter b {
  font-weight: 700;
}
.fc-search-box {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 12px;
  border-bottom: 1px solid var(--div);
  flex-shrink: 0;
  color: var(--hint);
}
.fc-search {
  flex: 1;
  border: none;
  background: transparent;
  color: var(--text);
  font-size: 12.5px;
  font-family: "DM Sans", sans-serif;
  outline: none;
}
.fc-search::placeholder {
  color: var(--hint);
}

.fc-grid {
  flex: 1;
  overflow-y: auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px;
  padding: 9px 11px;
  align-content: start;
  scrollbar-width: thin;
  scrollbar-color: rgba(200, 168, 98, 0.15) transparent;
}
.fc-grid::-webkit-scrollbar {
  width: 3px;
}
.fc-grid::-webkit-scrollbar-thumb {
  background: rgba(200, 168, 98, 0.18);
  border-radius: 3px;
}

.fc-card {
  background: var(--cb);
  border: 1px solid var(--cb);
  border-radius: 9px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.18s;
  position: relative;
  user-select: none;
}
.fc-card:hover:not(.fc-card-off) {
  border-color: var(--acc);
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.32);
}
.fc-card.fc-card-on {
  border-color: rgba(200, 168, 98, 0.4);
}
.fc-card.fc-card-drag {
  opacity: 0.42;
  transform: scale(0.93);
}
.fc-card.fc-card-off {
  opacity: 0.35;
  cursor: not-allowed;
}
.fc-card-media {
  width: 100%;
  aspect-ratio: 1;
  overflow: hidden;
  position: relative;
  background: rgba(0, 0, 0, 0.12);
}
.fc-card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.28s;
}
.fc-card:hover .fc-card-img {
  transform: scale(1.07);
}
.fc-card-no-img {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--ibg);
}
.fc-card-ov {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.17s;
}
.fc-card:hover .fc-card-ov {
  opacity: 1;
}
.fc-ov-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--acc);
  background: rgba(0, 0, 0, 0.62);
  padding: 4px 12px;
  border-radius: 20px;
}
.fc-ov-label.off {
  color: #f4a4a4;
}
.fc-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 19px;
  height: 19px;
  background: var(--acc);
  color: #1a1208;
  border-radius: 50%;
  font-size: 10.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.fc-card-foot {
  padding: 6px 9px 8px;
}
.fc-card-nm {
  font-size: 11px;
  font-weight: 500;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-bottom: 2px;
}
.fc-card-pr {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--acc);
}
.fc-card-skel {
  cursor: default;
}
.fc-skel {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0.04) 25%,
    rgba(255, 255, 255, 0.09) 50%,
    rgba(255, 255, 255, 0.04) 75%
  );
  background-size: 200% 100%;
  animation: sk 1.4s infinite;
}
.fc-skel-img {
  width: 100%;
  aspect-ratio: 1;
}
@keyframes sk {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
.fc-no-flowers {
  grid-column: 1/-1;
  text-align: center;
  padding: 30px 12px;
  color: var(--hint);
}
.fc-no-flowers p {
  font-size: 12.5px;
  margin-top: 8px;
}
.fc-dots-wrap {
  padding: 9px 13px 11px;
  border-top: 1px solid var(--div);
  flex-shrink: 0;
}
.fc-dots-lbl {
  display: block;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--hint);
  margin-bottom: 7px;
}
.fc-dots {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.fc-dot {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  border: 1.5px solid var(--cb);
  transition: all 0.18s;
}
.fc-dot-on {
  background: var(--accdim);
  border-color: var(--acc);
}
.fc-dot-next {
  border-color: var(--acc);
  border-style: dashed;
  animation: pdot 1.6s ease infinite;
}
@keyframes pdot {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(200, 168, 98, 0.22);
  }
  50% {
    box-shadow: 0 0 0 4px rgba(200, 168, 98, 0.07);
  }
}

/* CANVAS */
.fc-canvas {
  position: relative;
  background: var(--cvs);
  overflow: hidden;
}
.fc-canvas.fc-drop {
  outline: 2px dashed rgba(200, 168, 98, 0.48);
  outline-offset: -2px;
}
.fc-viewer {
  width: 100%;
  height: 100%;
  display: block;
}
.fc-drop-hint {
  position: absolute;
  inset: 0;
  background: rgba(200, 168, 98, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  z-index: 5;
}
.fc-drop-ring {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 9px;
  width: 140px;
  height: 140px;
  border: 2px dashed rgba(200, 168, 98, 0.55);
  border-radius: 50%;
  color: var(--acc);
  font-size: 13px;
  font-weight: 600;
  animation: drp 1.1s ease infinite;
}
@keyframes drp {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.04);
    opacity: 0.72;
  }
}
.fc-loader {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: rgba(25, 20, 12, 0.8);
  z-index: 10;
}
.fc-spin {
  width: 38px;
  height: 38px;
  border: 2.5px solid rgba(200, 168, 98, 0.18);
  border-top-color: var(--acc);
  border-radius: 50%;
  animation: spin 0.85s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.fc-loader span {
  font-size: 13px;
  color: var(--muted);
}
.fc-chips {
  position: absolute;
  top: 13px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
  z-index: 5;
}
.fc-chip {
  width: 33px;
  height: 33px;
  border-radius: 50%;
  border: 2px solid rgba(200, 168, 98, 0.32);
  overflow: hidden;
  cursor: pointer;
  position: relative;
  flex-shrink: 0;
  transition: all 0.17s;
  background: #28220e;
}
.fc-chip:hover {
  border-color: #e8344d;
  transform: scale(1.1);
}
.fc-chip-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.fc-chip-lt {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  color: var(--acc);
}
.fc-chip-x {
  position: absolute;
  inset: 0;
  background: rgba(232, 52, 77, 0.82);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 13px;
  opacity: 0;
  transition: opacity 0.17s;
}
.fc-chip:hover .fc-chip-x {
  opacity: 1;
}
.fc-hint-bar {
  position: absolute;
  bottom: 13px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 7px;
  background: rgba(12, 9, 5, 0.82);
  border: 1px solid rgba(200, 168, 98, 0.1);
  border-radius: 20px;
  padding: 5px 15px;
  font-size: 11px;
  color: var(--hint);
  backdrop-filter: blur(5px);
  white-space: nowrap;
  pointer-events: none;
}
.fc-dot-sep {
  color: rgba(200, 168, 98, 0.2);
}

/* RIGHT */
.fc-right {
  background: var(--panel);
  border-left: 1px solid var(--pb);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(200, 168, 98, 0.1) transparent;
}
.fc-right::-webkit-scrollbar {
  width: 3px;
}
.fc-right::-webkit-scrollbar-thumb {
  background: rgba(200, 168, 98, 0.14);
  border-radius: 3px;
}
.fc-section {
  padding: 13px 15px;
  border-bottom: 1px solid var(--div);
}
.fc-sec-lbl {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: var(--hint);
  margin-bottom: 9px;
}
.fc-swatches {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 9px;
}
.fc-sw {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.17s;
  padding: 0;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.28);
}
.fc-sw:hover {
  transform: scale(1.18);
}
.fc-sw.active {
  border-color: var(--acc);
  box-shadow: 0 0 0 3px rgba(200, 168, 98, 0.24);
}
.fc-color-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.fc-color-pick {
  width: 30px;
  height: 26px;
  border: 1px solid var(--ibr);
  border-radius: 6px;
  background: none;
  cursor: pointer;
  padding: 2px;
}
.fc-hex {
  font-size: 11px;
  color: var(--muted);
  font-family: monospace;
}
.fc-color-chip {
  width: 20px;
  height: 20px;
  border-radius: 5px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  margin-left: auto;
}
.fc-sl-row {
  display: flex;
  align-items: center;
  gap: 7px;
  margin: 7px 0;
}
.fc-sl-lbl {
  font-size: 11.5px;
  color: var(--muted);
  width: 48px;
  flex-shrink: 0;
}
.fc-sl {
  flex: 1;
  accent-color: var(--acc);
  height: 3px;
  cursor: pointer;
  outline: none;
}
.fc-sl-val {
  font-size: 11px;
  color: var(--acc);
  font-family: monospace;
  width: 26px;
  text-align: right;
}
.fc-sum {
  flex: 1;
}
.fc-sum-empty {
  text-align: center;
  padding: 22px 0;
  color: var(--hint);
}
.fc-sum-empty p {
  font-size: 12px;
  margin-top: 8px;
}
.fc-lines {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin: 8px 0;
}
.fc-line {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
}
.fc-ln {
  flex: 1;
  color: var(--muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fc-lq {
  color: var(--hint);
}
.fc-lp {
  color: var(--acc);
  font-weight: 600;
  min-width: 36px;
  text-align: right;
}
.fc-sep {
  height: 1px;
  background: var(--div);
  margin: 9px 0;
}
.fc-meta {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.fc-mr {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11.5px;
  color: var(--hint);
}
.fc-ms {
  width: 15px;
  height: 15px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.fc-total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin: 11px 0;
}
.fc-tot-lbl {
  font-size: 13px;
  color: var(--muted);
}
.fc-tot-val {
  font-family: "Playfair Display", serif;
  font-size: 25px;
  font-weight: 600;
  color: var(--acc);
}
.fc-btn-co {
  width: 100%;
  padding: 11px 14px;
  background: var(--acc);
  border: none;
  border-radius: 9px;
  color: #1a1208;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s;
  font-family: "DM Sans", sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 7px;
}
.fc-btn-co:hover:not(:disabled) {
  background: var(--accd);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(200, 168, 98, 0.24);
}
.fc-btn-co:disabled {
  opacity: 0.44;
  cursor: not-allowed;
  transform: none;
}
.fc-btn-ca {
  width: 100%;
  padding: 9px 14px;
  background: transparent;
  border: 1px solid var(--bbr);
  border-radius: 9px;
  color: var(--acc);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.18s;
  font-family: "DM Sans", sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
.fc-btn-ca:hover:not(:disabled) {
  background: var(--accdim);
  border-color: var(--acc);
}
.fc-btn-ca:disabled {
  opacity: 0.38;
  cursor: not-allowed;
}

/* TOAST */
.fc-toast {
  position: fixed;
  bottom: 20px;
  right: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 15px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 500;
  z-index: 9999;
  font-family: "DM Sans", sans-serif;
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
}
.fc-toast.success {
  background: #142b12;
  border: 1px solid rgba(74, 222, 128, 0.22);
  color: #86efac;
}
.fc-toast.error {
  background: #2b1212;
  border: 1px solid rgba(248, 113, 113, 0.22);
  color: #fca5a5;
}
.toast-anim-enter-active {
  transition: all 0.26s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-anim-leave-active {
  transition: all 0.17s ease;
}
.toast-anim-enter-from {
  opacity: 0;
  transform: translateX(14px) scale(0.9);
}
.toast-anim-leave-to {
  opacity: 0;
  transform: translateX(10px);
}
.fade-enter-active {
  transition: opacity 0.18s;
}
.fade-leave-active {
  transition: opacity 0.14s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* RESPONSIVE */
@media (max-width: 1100px) {
  .fc-layout {
    grid-template-columns: 238px 1fr 262px;
  }
}
@media (max-width: 880px) {
  .fc-layout {
    grid-template-columns: 1fr;
    grid-template-rows: auto 55vh auto;
    height: auto;
    overflow: auto;
  }
  .fc-canvas {
    min-height: 55vh;
  }
  .fc-toolbar {
    flex-wrap: wrap;
    height: auto;
    padding: 8px 14px;
    gap: 8px;
  }
}
@media (max-width: 600px) {
  .fc-tabs {
    overflow-x: auto;
  }
  .fc-grid {
    grid-template-columns: repeat(3, 1fr);
  }
  .fc-title {
    display: none;
  }
}
</style>
