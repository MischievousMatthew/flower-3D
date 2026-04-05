<template>
  <div class="customizer-root" :class="`theme-${theme}`">
    <NavHeader :cartCount="cartCount" :isAuthenticated="isAuthenticated" />

    <!-- Top toolbar (mirrors the reference UI dark pill bar) -->
    <div class="toolbar">
      <div class="toolbar-left">
        <span class="toolbar-title">🌸 Bouquet Studio</span>
        <div class="toolbar-tabs">
          <button
            v-for="tab in ['Flowers', 'Wrapping', 'Ribbon', 'Summary']"
            :key="tab"
            class="toolbar-tab"
            :class="{ active: activeTab === tab }"
            @click="activeTab = tab"
          >
            {{ tab }}
          </button>
        </div>
      </div>
      <div class="toolbar-right">
        <button class="tool-btn" title="Reset view" @click="resetCamera">
          ↺
        </button>
        <button class="tool-btn" title="Screenshot" @click="takeScreenshot">
          📷
        </button>
        <button
          class="tool-btn"
          title="Toggle rotate"
          :class="{ active: autoRotate }"
          @click="autoRotate = !autoRotate"
        >
          ⟳
        </button>
        <button
          class="tool-btn"
          title="Wireframe"
          :class="{ active: wireframe }"
          @click="toggleWireframe"
        >
          ⬡
        </button>
        <button class="theme-toggle" @click="toggleTheme">
          <span>{{ theme === "dark" ? "Light" : "Dark" }} Mode</span>
        </button>
        <button
          class="btn-save-design"
          @click="handleSaveDesign"
          :disabled="isSaving"
        >
          <span v-if="isSaving">Saving…</span>
          <span v-else>Save Design</span>
        </button>
      </div>
    </div>

    <div class="customizer-layout">
      <!-- ══ LEFT PANEL ═══════════════════════════════════════════════════ -->
      <aside class="left-panel">
        <div class="panel-header">
          <h3>Flowers</h3>
          <span class="panel-count"
            >{{ selectedFlowers.length }}/{{ MAX_FLOWERS }} added</span
          >
        </div>

        <div class="search-bar">
          <input
            v-model="flowerSearch"
            placeholder="Search flowers…"
            class="search-input"
          />
        </div>

        <div v-if="loadingFlowers" class="flowers-grid">
          <div v-for="n in 6" :key="n" class="flower-card skeleton-card">
            <div class="skeleton sk-img"></div>
            <div class="skeleton sk-text"></div>
          </div>
        </div>

        <div v-else class="flowers-grid">
          <div
            v-for="flower in filteredFlowers"
            :key="flower.id"
            class="flower-card"
            :class="{
              'card-dragging': draggingFlower?.id === flower.id,
              'card-maxed': !canAddFlower(flower),
            }"
            draggable="true"
            @dragstart="onDragStart($event, flower)"
            @dragend="onDragEnd"
            @click="addFlower(flower)"
          >
            <div class="flower-card-img">
              <canvas
                v-if="flower.model_3d_url"
                :id="'preview-' + flower.id"
                class="flower-preview-canvas"
              ></canvas>
              <div v-else class="flower-preview-fallback">
                <span>3D Model Missing</span>
              </div>
              <div class="card-overlay">
                <span class="card-overlay-text">{{
                  !canAddFlower(flower) ? "Full" : "+ Add"
                }}</span>
              </div>
            </div>
            <div class="flower-card-info">
              <p class="flower-name">{{ flower.name || flower.product_name }}</p>
              <p class="flower-price">
                ₱{{ parseFloat(flower.selling_price).toFixed(0) }}
              </p>
              <p class="flower-model-status">3D Model Available</p>
            </div>
            <div class="flower-count-badge" v-if="countOf(flower.id) > 0">
              {{ countOf(flower.id) }}
            </div>
          </div>

          <div v-if="filteredFlowers.length === 0" class="no-flowers">
            <span>🌿</span>
            <p>No flowers found</p>
          </div>
        </div>

        <!-- Stem slots visual indicator -->
        <div class="stem-slots">
          <p class="slots-label">Bouquet Slots</p>
          <div class="slots-row">
            <div
              v-for="i in MAX_FLOWERS"
              :key="i"
              class="slot"
              :class="{
                'slot-filled': selectedFlowers[i - 1],
                'slot-active': i === selectedFlowers.length + 1,
              }"
            >
              <span v-if="selectedFlowers[i - 1]">🌸</span>
              <span
                v-else-if="i === selectedFlowers.length + 1"
                class="slot-pulse"
                >+</span
              >
              <span v-else class="slot-empty">○</span>
            </div>
          </div>
        </div>
      </aside>

      <!-- ══ CENTER 3D CANVAS ═══════════════════════════════════════════ -->
      <div
        class="canvas-panel"
        @dragover.prevent="onDragOver"
        @drop.prevent="onDropCanvas"
        @dragleave="onDragLeave"
        :class="{ 'drop-active': isDragOver }"
      >
        <!-- Drop hint overlay -->
        <div class="drop-hint" v-if="isDragOver">
          <div class="drop-hint-ring">
            <span>🌸 Drop Here</span>
          </div>
        </div>

        <!-- 3D Viewer mount -->
        <div ref="viewerContainer" class="viewer-canvas"></div>

        <!-- Loading overlay -->
        <div v-if="isLoading3D" class="canvas-loading">
          <div class="canvas-spinner"></div>
          <p>Loading bouquet…</p>
        </div>

        <!-- Error overlay -->
        <div v-if="canvas3DError" class="canvas-error">
          <span>⚠️</span>
          <p>{{ canvas3DError }}</p>
          <button @click="initViewer">Retry</button>
        </div>

        <!-- Bottom instruction bar -->
        <div class="canvas-instructions">
          <span>🖱 Drag to rotate</span>
          <span class="inst-sep">·</span>
          <span>Scroll to zoom</span>
          <span class="inst-sep">·</span>
          <span>Drop flowers to add</span>
        </div>

        <!-- Current bouquet chips overlay -->
        <div class="bouquet-chips" v-if="selectedFlowers.length > 0">
          <div
            v-for="(f, i) in selectedFlowers"
            :key="i"
            class="bouquet-chip"
            @click="removeFlower(i)"
            title="Click to remove"
          >
            <strong>{{ (f.name || f.product_name || "Flower").slice(0, 1) }}</strong>
            <span>✕</span>
          </div>
        </div>
      </div>

      <!-- ══ RIGHT PANEL ════════════════════════════════════════════════ -->
      <aside class="right-panel">
        <!-- TAB: Wrapping -->
        <div
          v-show="activeTab === 'Flowers' || activeTab === 'Wrapping'"
          class="panel-section"
        >
          <div class="panel-header">
            <h3>Wrapping Paper</h3>
          </div>
          <div class="color-presets">
            <button
              v-for="c in wrapPresets"
              :key="c"
              class="color-swatch"
              :style="{ background: c }"
              :class="{ active: paperColor === c }"
              @click="applyPaperColor(c)"
            ></button>
          </div>
          <div class="color-custom-row">
            <label class="color-label">Custom</label>
            <input
              type="color"
              v-model="paperColor"
              @input="applyPaperColor(paperColor)"
              class="color-picker"
            />
            <span class="color-hex">{{ paperColor }}</span>
          </div>
          <div
            class="color-preview-strip"
            :style="{
              background: `linear-gradient(135deg, ${paperColor}, ${shadeColor(paperColor, -30)})`,
            }"
          >
            <span>Wrapping Preview</span>
          </div>
        </div>

        <!-- TAB: Ribbon -->
        <div
          v-show="activeTab === 'Flowers' || activeTab === 'Ribbon'"
          class="panel-section"
        >
          <div class="panel-header">
            <h3>Ribbon</h3>
          </div>
          <div class="color-presets">
            <button
              v-for="c in ribbonPresets"
              :key="c"
              class="color-swatch"
              :style="{ background: c }"
              :class="{ active: ribbonColor === c }"
              @click="applyRibbonColor(c)"
            ></button>
          </div>
          <div class="color-custom-row">
            <label class="color-label">Custom</label>
            <input
              type="color"
              v-model="ribbonColor"
              @input="applyRibbonColor(ribbonColor)"
              class="color-picker"
            />
            <span class="color-hex">{{ ribbonColor }}</span>
          </div>
          <div
            class="color-preview-strip ribbon-strip"
            :style="{
              background: `linear-gradient(135deg, ${ribbonColor}, ${shadeColor(ribbonColor, -30)})`,
            }"
          >
            <span>Ribbon Preview</span>
          </div>
        </div>

        <!-- Flower adjustments (size sliders) -->
        <div v-show="activeTab === 'Flowers'" class="panel-section">
          <div class="panel-header">
            <h3>Arrangement</h3>
          </div>
          <div class="slider-row">
            <label>Bloom Spread</label>
            <input
              type="range"
              min="0.5"
              max="2"
              step="0.05"
              v-model.number="bloomSpread"
              @input="updateArrangement"
              class="custom-slider"
            />
            <span class="slider-val">{{ bloomSpread.toFixed(1) }}x</span>
          </div>
          <div class="slider-row">
            <label>Height</label>
            <input
              type="range"
              min="0.5"
              max="2"
              step="0.05"
              v-model.number="flowerHeight"
              @input="updateArrangement"
              class="custom-slider"
            />
            <span class="slider-val">{{ flowerHeight.toFixed(1) }}x</span>
          </div>
          <div class="slider-row">
            <label>Density</label>
            <input
              type="range"
              min="1"
              max="3"
              step="0.1"
              v-model.number="density"
              @input="updateArrangement"
              class="custom-slider"
            />
            <span class="slider-val">{{ density.toFixed(1) }}</span>
          </div>
        </div>

        <!-- ══ SUMMARY / PRICING ══ -->
        <div
          class="panel-section summary-section"
          v-show="activeTab === 'Summary' || selectedFlowers.length > 0"
        >
          <div class="panel-header">
            <h3>Order Summary</h3>
          </div>

          <div class="summary-items">
            <div v-if="selectedFlowers.length === 0" class="summary-empty">
              <span>🌿</span>
              <p>No flowers added yet</p>
            </div>
            <div v-else>
              <div
                v-for="(group, name) in groupedFlowers"
                :key="name"
                class="summary-line"
              >
                <span class="summary-flower-name">{{ name }}</span>
                <span class="summary-flower-qty">× {{ group.qty }}</span>
                <span class="summary-flower-price"
                  >₱{{ (group.price * group.qty).toFixed(0) }}</span
                >
              </div>
            </div>
          </div>

          <div class="summary-divider"></div>

          <div class="summary-meta">
            <div class="meta-row">
              <span>Wrapping</span>
              <span
                class="meta-swatch"
                :style="{ background: paperColor }"
              ></span>
            </div>
            <div class="meta-row">
              <span>Ribbon</span>
              <span
                class="meta-swatch"
                :style="{ background: ribbonColor }"
              ></span>
            </div>
            <div class="meta-row">
              <span>Flowers</span>
              <span>{{ selectedFlowers.length }} / {{ MAX_FLOWERS }}</span>
            </div>
          </div>

          <div class="summary-divider"></div>

          <div class="total-row">
            <span class="total-label">Total</span>
            <span class="total-price">₱{{ totalPrice.toFixed(2) }}</span>
          </div>

          <button
            class="btn-checkout"
            :disabled="selectedFlowers.length === 0 || isCheckingOut"
            @click="proceedToCustomCheckout"
          >
            <span v-if="isCheckingOut">Processing…</span>
            <span v-else>Proceed to Checkout →</span>
          </button>

          <button
            class="btn-add-to-cart-custom"
            @click="handleAddCustomToCart"
            :disabled="selectedFlowers.length === 0"
          >
            Add to Cart
          </button>
        </div>
      </aside>
    </div>

    <!-- ══ SUCCESS TOAST ══ -->
    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        {{ toast.message }}
      </div>
    </transition>
  </div>
</template>

<script setup>
import {
  ref,
  computed,
  reactive,
  onMounted,
  onUnmounted,
  watch,
  nextTick,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import { clone as cloneSkeleton } from "three/examples/jsm/utils/SkeletonUtils.js";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";

const router = useRouter();
const route = useRoute();
const { isAuthenticated } = useAuth();
const cartStore = useCart();

// ── Constants ─────────────────────────────────────────────────────────────
const MAX_FLOWERS = 7;

// ── State ─────────────────────────────────────────────────────────────────
const activeTab = ref("Flowers");
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

// Colors
const paperColor = ref("#c8a882");
const ribbonColor = ref("#d4a5c9");
const wrapPresets = [
  "#c8a882",
  "#f5f0e8",
  "#e8c9c9",
  "#c9e8d4",
  "#c9d4e8",
  "#2d2d2d",
  "#fff8f0",
];
const ribbonPresets = [
  "#d4a5c9",
  "#f8d7da",
  "#d7f8e8",
  "#d7e8f8",
  "#f8f0d7",
  "#8b4513",
  "#ffffff",
];

// Arrangement sliders
const bloomSpread = ref(1.0);
const flowerHeight = ref(1.0);
const density = ref(1.5);

// 3D
const viewerContainer = ref(null);
const isLoading3D = ref(true);
const canvas3DError = ref(null);

// Toast
const toast = reactive({ show: false, message: "", type: "success" });
const customizingContext = ref(null);

// ── Three.js refs ─────────────────────────────────────────────────────────
let scene, camera, renderer, animFrameId;
let bouquetGroup = null; // holds the base bouquet model
let flowerGroup = null; // holds added flower meshes
let paperMesh = null; // reference to wrapping paper mesh
let ribbonMesh = null; // reference to ribbon mesh
let isDragging3D = false;
let prevMouse = { x: 0, y: 0 };
let rotX = 0,
  rotY = 0;
let zoomLevel = 5;
const gltfLoader = new GLTFLoader();
const modelCache = new Map();
const previewRenderers = new Map();
let flowerRenderVersion = 0;

// ── Computed ──────────────────────────────────────────────────────────────
const cartCount = computed(() => cartStore.count.value);
const currentStoreId = computed(() => {
  const raw = route.query.store_id ?? customizingContext.value?.storeId ?? null;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});
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
  const groups = {};
  for (const f of selectedFlowers.value) {
    if (!groups[f.product_name]) {
      groups[f.product_name] = {
        qty: 0,
        price: parseFloat(f.price ?? f.selling_price ?? 0),
      };
    }
    groups[f.product_name].qty++;
  }
  return groups;
});

const countOf = (id) => selectedFlowers.value.filter((f) => f.id === id).length;
const getFlowerStock = (flower) =>
  Number.parseInt(flower?.stock ?? flower?.quantity_in_stock ?? 0, 10) || 0;
const canAddFlower = (flower) => {
  if (!getFlowerModelUrl(flower)) return false;
  if (selectedFlowers.value.length >= MAX_FLOWERS) return false;
  return countOf(flower.id) < getFlowerStock(flower);
};
const seedProductId = computed(() => {
  const routeProductId =
    route.params.id === "flower" ? null : route.params.id ?? null;
  const raw =
    route.query.product_id ??
    routeProductId ??
    customizingContext.value?.productId ??
    null;
  const parsed = Number(raw);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(async () => {
  hydrateCustomizerContext();
  hydrateTheme();
  if (isAuthenticated.value) {
    await cartStore.initialize();
  }
  await fetchFlowers();
  await seedInitialFlower();
  initViewer();
  window.addEventListener("resize", handleResize);
});

onUnmounted(() => {
  cleanup();
  disposePreviewRenderers();
  window.removeEventListener("resize", handleResize);
});

watch(
  () => route.query.store_id,
  async (nextStoreId, previousStoreId) => {
    if (nextStoreId === previousStoreId) return;
    hydrateCustomizerContext();
    selectedFlowers.value = [];
    flowerSearch.value = "";
    await fetchFlowers();
    await seedInitialFlower();
  },
);

watch(
  filteredFlowers,
  async () => {
    await nextTick();
    renderFlowerPreviews();
  },
  { deep: true },
);

watch(theme, (nextTheme) => {
  localStorage.setItem("flower_customizer_theme", nextTheme);
});

// ── Data fetching ─────────────────────────────────────────────────────────
const fetchFlowers = async () => {
  loadingFlowers.value = true;
  try {
    const endpoint = currentStoreId.value
      ? `stores/${currentStoreId.value}/customizable-flowers`
      : "customer/products";
    const params = currentStoreId.value
      ? {}
      : { selling_type: "per_piece_customizable", per_page: 30 };
    const res = await api.get(endpoint, { params });
    if (res.data.success) {
      const loadedFlowers = Array.isArray(res.data.data)
        ? res.data.data
        : (res.data.data?.data ?? []);
      flowers.value = loadedFlowers.filter((flower) => {
        const sellingType = flower.selling_type ?? null;
        if (sellingType && sellingType !== "per_piece_customizable") return false;
        return Boolean(getFlowerModelUrl(flower));
      });
    }
    if (!currentStoreId.value && flowers.value.length === 0) {
      flowers.value = generateDemoFlowers();
    }
  } catch {
    flowers.value = currentStoreId.value ? [] : generateDemoFlowers();
  } finally {
    loadingFlowers.value = false;
    await nextTick();
    renderFlowerPreviews();
  }
};

const generateDemoFlowers = () => [
  {
    id: 1,
    product_name: "Red Rose",
    selling_price: "85",
    primary_image: null,
    models: [],
  },
  {
    id: 2,
    product_name: "White Lily",
    selling_price: "70",
    primary_image: null,
    models: [],
  },
  {
    id: 3,
    product_name: "Sunflower",
    selling_price: "60",
    primary_image: null,
    models: [],
  },
  {
    id: 4,
    product_name: "Pink Tulip",
    selling_price: "75",
    primary_image: null,
    models: [],
  },
  {
    id: 5,
    product_name: "Baby's Breath",
    selling_price: "40",
    primary_image: null,
    models: [],
  },
  {
    id: 6,
    product_name: "Lavender",
    selling_price: "55",
    primary_image: null,
    models: [],
  },
  {
    id: 7,
    product_name: "Daisy",
    selling_price: "45",
    primary_image: null,
    models: [],
  },
  {
    id: 8,
    product_name: "Orchid",
    selling_price: "120",
    primary_image: null,
    models: [],
  },
];

// ── 3D Viewer ─────────────────────────────────────────────────────────────
const hydrateCustomizerContext = () => {
  try {
    const saved = localStorage.getItem("customize_item");
    customizingContext.value = saved ? JSON.parse(saved) : null;
  } catch {
    customizingContext.value = null;
  }
};

const hydrateTheme = () => {
  try {
    const savedTheme = localStorage.getItem("flower_customizer_theme");
    if (savedTheme === "light" || savedTheme === "dark") {
      theme.value = savedTheme;
    }
  } catch {
    theme.value = "dark";
  }
};

const toggleTheme = () => {
  theme.value = theme.value === "dark" ? "light" : "dark";
};

const resolveApiEntity = (payload) => {
  if (!payload) return null;
  if (payload.data?.data) return payload.data.data;
  if (payload.data) return payload.data;
  return payload;
};

const ensureFlowerLoaded = async (productId) => {
  const normalizedId = Number(productId);
  if (!normalizedId) return null;

  const existing = flowers.value.find(
    (flower) => Number(flower.id) === normalizedId,
  );
  if (existing) return existing;

  if (currentStoreId.value) {
    return null;
  }

  try {
    const response = await api.get(`customer/products/${normalizedId}`);
    const flower = resolveApiEntity(response.data);
    if (!flower?.id) return null;

    flowers.value = [
      flower,
      ...flowers.value.filter((item) => Number(item.id) !== normalizedId),
    ];
    return flower;
  } catch {
    return null;
  }
};

const seedInitialFlower = async () => {
  if (selectedFlowers.value.length > 0 || !seedProductId.value) return;

  const flower = await ensureFlowerLoaded(seedProductId.value);
  if (flower) {
    addFlower(flower, { silent: true });
  }
};

const initViewer = () => {
  if (!viewerContainer.value) return;
  cleanup();
  isLoading3D.value = true;
  canvas3DError.value = null;

  try {
    // Scene
    scene = new THREE.Scene();
    scene.background = new THREE.Color("#1a1612");
    scene.fog = new THREE.FogExp2(0x1a1612, 0.04);

    // Camera
    const w = viewerContainer.value.clientWidth;
    const h = viewerContainer.value.clientHeight;
    camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 200);
    camera.position.set(0, 1.2, zoomLevel);
    camera.lookAt(0, 0, 0);

    // Renderer
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
    viewerContainer.value.appendChild(renderer.domElement);

    // Lighting
    setupLighting();

    // Ground
    setupGround();

    // Groups
    bouquetGroup = new THREE.Group();
    flowerGroup = new THREE.Group();
    scene.add(bouquetGroup);
    scene.add(flowerGroup);

    // Try loading real GLB, fall back to procedural bouquet
    loadBouquetModel();

    // Mouse / touch controls
    setupControls();

    // Animate
    animate();
  } catch (e) {
    canvas3DError.value = "Failed to initialize 3D viewer: " + e.message;
    isLoading3D.value = false;
  }
};

const setupLighting = () => {
  // Warm ambient
  scene.add(new THREE.AmbientLight(0xfff3e0, 0.5));

  // Key light (top-right warm)
  const key = new THREE.DirectionalLight(0xfff0d0, 1.2);
  key.position.set(3, 6, 4);
  key.castShadow = true;
  key.shadow.mapSize.setScalar(1024);
  key.shadow.camera.far = 30;
  scene.add(key);

  // Fill (cool left)
  const fill = new THREE.DirectionalLight(0xd0e8ff, 0.4);
  fill.position.set(-4, 3, -2);
  scene.add(fill);

  // Rim (back)
  const rim = new THREE.DirectionalLight(0xffe0c0, 0.6);
  rim.position.set(0, 2, -5);
  scene.add(rim);

  // Point light — warm glow near bouquet
  const point = new THREE.PointLight(0xffcc88, 0.8, 10);
  point.position.set(1, 3, 2);
  scene.add(point);
};

const setupGround = () => {
  const geo = new THREE.CircleGeometry(6, 64);
  const mat = new THREE.MeshStandardMaterial({
    color: 0x1a1612,
    roughness: 0.9,
    metalness: 0.1,
  });
  const ground = new THREE.Mesh(geo, mat);
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -2.2;
  ground.receiveShadow = true;
  scene.add(ground);

  // Subtle glow ring on ground
  const ringGeo = new THREE.RingGeometry(1.5, 2.2, 64);
  const ringMat = new THREE.MeshBasicMaterial({
    color: 0x8b6914,
    transparent: true,
    opacity: 0.12,
    side: THREE.DoubleSide,
  });
  const ring = new THREE.Mesh(ringGeo, ringMat);
  ring.rotation.x = -Math.PI / 2;
  ring.position.y = -2.18;
  scene.add(ring);
};

const loadBouquetModel = () => {
  buildProceduralBouquet();
};

const clearGroup = (group) => {
  while (group.children.length) {
    group.remove(group.children[0]);
  }
};

const fitModelToBouquet = (model) => {
  const box = new THREE.Box3().setFromObject(model);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxAxis = Math.max(size.x, size.y, size.z) || 1;
  const scale = 3.2 / maxAxis;

  model.position.sub(center);
  model.scale.setScalar(scale);
  model.position.y = -0.45;
};

const getFlowerModelUrl = (flower) => {
  if (!flower) return null;

  const modelRecord = Array.isArray(flower.models) ? flower.models[0] : null;
  const rawUrl =
    modelRecord?.model_url ??
    modelRecord?.model_path ??
    flower.model_3d_url ??
    flower.model_3d_path ??
    null;

  if (!rawUrl || typeof rawUrl !== "string") return null;
  if (/^https?:\/\//i.test(rawUrl)) {
    return rawUrl.replace(/^http:\/\//i, "https://");
  }
  if (rawUrl.startsWith("/")) {
    return `${window.location.origin}${rawUrl}`;
  }
  return `https://res.cloudinary.com/dqs1e1inx/raw/upload/${rawUrl.replace(/^\/+/, "")}`;
};

const normalizeSceneModel = (model) => {
  model.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
    const materials = Array.isArray(child.material)
      ? child.material
      : [child.material];
    materials.filter(Boolean).forEach((material) => {
      material.side = THREE.DoubleSide;
      material.wireframe = wireframe.value;
    });
  });
  return model;
};

const cloneModel = (model) => {
  try {
    return cloneSkeleton(model);
  } catch {
    return model.clone(true);
  }
};

const loadModelAsset = (url) =>
  new Promise((resolve, reject) => {
    if (modelCache.has(url)) {
      resolve(cloneModel(modelCache.get(url)));
      return;
    }

    gltfLoader.load(
      url,
      (gltf) => {
        const modelRoot = gltf.scene || gltf.scenes?.[0];
        if (!modelRoot) {
          reject(new Error("Model scene is empty."));
          return;
        }

        normalizeSceneModel(modelRoot);
        modelCache.set(url, modelRoot);
        resolve(cloneModel(modelRoot));
      },
      undefined,
      reject,
    );
  });

const fitModelToBounds = (model, targetSize = 0.55) => {
  const box = new THREE.Box3().setFromObject(model);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxAxis = Math.max(size.x, size.y, size.z) || 1;
  const scale = targetSize / maxAxis;

  model.position.sub(center);
  model.scale.setScalar(scale);
  return model;
};

const disposePreviewRenderers = () => {
  for (const preview of previewRenderers.values()) {
    preview.renderer.dispose();
  }
  previewRenderers.clear();
};

const renderFlowerPreviews = async () => {
  disposePreviewRenderers();

  for (const flower of filteredFlowers.value.slice(0, 12)) {
    const modelUrl = getFlowerModelUrl(flower);
    const canvas = document.getElementById(`preview-${flower.id}`);

    if (!modelUrl || !(canvas instanceof HTMLCanvasElement)) continue;

    try {
      const renderer = new THREE.WebGLRenderer({
        canvas,
        antialias: true,
        alpha: true,
      });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
      renderer.setSize(canvas.clientWidth || 136, canvas.clientHeight || 136, false);

      const previewScene = new THREE.Scene();
      const previewCamera = new THREE.PerspectiveCamera(35, 1, 0.1, 100);
      previewCamera.position.set(0, 0.35, 2.4);

      previewScene.add(new THREE.AmbientLight(0xffffff, 0.9));
      const light = new THREE.DirectionalLight(0xfff1d6, 1.1);
      light.position.set(2, 3, 4);
      previewScene.add(light);

      const model = await loadModelAsset(modelUrl);
      fitModelToBounds(model, 1.1);
      model.rotation.y = -0.6;
      previewScene.add(model);

      renderer.render(previewScene, previewCamera);
      previewRenderers.set(flower.id, { renderer });
    } catch (error) {
      console.warn("Failed to render flower preview:", flower.name || flower.product_name, error);
    }
  }
};

// ── Procedural bouquet ────────────────────────────────────────────────────
const buildProceduralBouquet = () => {
  if (!bouquetGroup) return;

  // Clear
  clearGroup(bouquetGroup);

  // --- Wrapping paper cone ---
  const paperGeo = new THREE.ConeGeometry(1.3, 2.8, 32, 1, true);
  const paperMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(paperColor.value),
    roughness: 0.85,
    metalness: 0.05,
    side: THREE.DoubleSide,
  });
  paperMesh = new THREE.Mesh(paperGeo, paperMat);
  paperMesh.name = "wrapping";
  paperMesh.position.y = -0.5;
  paperMesh.castShadow = true;
  bouquetGroup.add(paperMesh);

  // Paper inner (slightly darker)
  const paperInnerGeo = new THREE.ConeGeometry(1.25, 2.78, 32, 1, true);
  const paperInnerMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(shadeColor(paperColor.value, -25)),
    roughness: 0.9,
    metalness: 0.0,
    side: THREE.BackSide,
  });
  const paperInner = new THREE.Mesh(paperInnerGeo, paperInnerMat);
  paperInner.position.y = -0.5;
  bouquetGroup.add(paperInner);

  // Paper fold creases (thin strips)
  for (let i = 0; i < 8; i++) {
    const angle = (i / 8) * Math.PI * 2;
    const creaseGeo = new THREE.BoxGeometry(0.03, 2.8, 0.03);
    const creaseMat = new THREE.MeshStandardMaterial({
      color: new THREE.Color(shadeColor(paperColor.value, -40)),
      roughness: 1,
    });
    const crease = new THREE.Mesh(creaseGeo, creaseMat);
    crease.position.set(Math.sin(angle) * 1.28, -0.5, Math.cos(angle) * 1.28);
    crease.rotation.y = angle;
    bouquetGroup.add(crease);
  }

  // --- Ribbon (torus knot-ish) ---
  const ribbonMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(ribbonColor.value),
    roughness: 0.4,
    metalness: 0.15,
  });

  // Horizontal band
  const bandGeo = new THREE.TorusGeometry(0.55, 0.06, 12, 64);
  ribbonMesh = new THREE.Mesh(bandGeo, ribbonMat);
  ribbonMesh.name = "ribbon";
  ribbonMesh.position.y = -1.55;
  ribbonMesh.rotation.x = Math.PI / 2;
  ribbonMesh.castShadow = true;
  bouquetGroup.add(ribbonMesh);

  // Bow left loop
  const bowGeo = new THREE.TorusGeometry(0.22, 0.055, 8, 32, Math.PI);
  const bowL = new THREE.Mesh(bowGeo, ribbonMat);
  bowL.position.set(-0.28, -1.42, 0);
  bowL.rotation.z = -Math.PI / 5;
  bowL.castShadow = true;
  bouquetGroup.add(bowL);

  // Bow right loop
  const bowR = new THREE.Mesh(bowGeo, ribbonMat);
  bowR.position.set(0.28, -1.42, 0);
  bowR.rotation.z = Math.PI / 5;
  bowR.rotation.y = Math.PI;
  bowR.castShadow = true;
  bouquetGroup.add(bowR);

  // Bow knot center
  const knotGeo = new THREE.SphereGeometry(0.08, 12, 12);
  const knot = new THREE.Mesh(knotGeo, ribbonMat);
  knot.position.y = -1.42;
  bouquetGroup.add(knot);

  // Ribbon tails
  for (const sign of [-1, 1]) {
    const tailGeo = new THREE.BoxGeometry(0.08, 0.5, 0.04);
    const tail = new THREE.Mesh(tailGeo, ribbonMat);
    tail.position.set(sign * 0.12, -1.85, 0);
    tail.rotation.z = sign * 0.3;
    tail.castShadow = true;
    bouquetGroup.add(tail);
  }

  // --- Stems ---
  const stemMat = new THREE.MeshStandardMaterial({
    color: 0x2d5a27,
    roughness: 0.9,
  });
  for (let i = 0; i < 5; i++) {
    const angle = (i / 5) * Math.PI * 2;
    const r = 0.3;
    const stemGeo = new THREE.CylinderGeometry(0.03, 0.04, 2.2, 8);
    const stem = new THREE.Mesh(stemGeo, stemMat);
    stem.position.set(
      Math.sin(angle) * r * 0.5,
      0.1,
      Math.cos(angle) * r * 0.5,
    );
    stem.rotation.z = Math.sin(angle) * 0.1;
    stem.castShadow = true;
    bouquetGroup.add(stem);
  }

  // --- Leaves ---
  const leafMat = new THREE.MeshStandardMaterial({
    color: 0x3a7a32,
    roughness: 0.8,
    side: THREE.DoubleSide,
  });
  for (let i = 0; i < 6; i++) {
    const angle = (i / 6) * Math.PI * 2;
    const leafGeo = new THREE.PlaneGeometry(0.35, 0.6);
    const leaf = new THREE.Mesh(leafGeo, leafMat);
    const r = 0.7 + Math.random() * 0.3;
    leaf.position.set(
      Math.sin(angle) * r,
      0.5 + Math.random() * 0.4,
      Math.cos(angle) * r,
    );
    leaf.rotation.y = angle;
    leaf.rotation.x = -0.4;
    leaf.castShadow = true;
    bouquetGroup.add(leaf);
  }

  // --- Baby's breath filler ---
  const babyMat = new THREE.MeshStandardMaterial({
    color: 0xffffff,
    roughness: 0.7,
  });
  for (let i = 0; i < 30; i++) {
    const bbGeo = new THREE.SphereGeometry(0.04 + Math.random() * 0.03, 6, 6);
    const bb = new THREE.Mesh(bbGeo, babyMat);
    const a = Math.random() * Math.PI * 2;
    const r = 0.3 + Math.random() * 0.8;
    bb.position.set(
      Math.sin(a) * r,
      1.2 + Math.random() * 0.8,
      Math.cos(a) * r,
    );
    bouquetGroup.add(bb);
  }

  // Position the whole group
  bouquetGroup.position.y = 0.5;

  // Entrance animation
  bouquetGroup.scale.setScalar(0.01);
  let startTime = Date.now();
  const entrance = () => {
    const t = Math.min((Date.now() - startTime) / 800, 1);
    const ease = 1 - Math.pow(1 - t, 3);
    bouquetGroup.scale.setScalar(ease);
    if (t < 1) requestAnimationFrame(entrance);
  };
  entrance();

  isLoading3D.value = false;

  // Rebuild flower overlays
  void rebuildFlowerMeshes();
};

// ── Flower meshes in 3D ───────────────────────────────────────────────────
const rebuildFlowerMeshes = async () => {
  if (!flowerGroup) return;
  clearGroup(flowerGroup);

  const renderVersion = ++flowerRenderVersion;
  const models = await Promise.all(
    selectedFlowers.value.map((flower, i) => buildFlowerMesh(flower, i)),
  );

  if (renderVersion !== flowerRenderVersion || !flowerGroup) return;

  models.filter(Boolean).forEach((flowerMesh) => {
    flowerGroup.add(flowerMesh);
  });
};

const buildFlowerMesh = async (flower, index) => {
  const modelUrl = getFlowerModelUrl(flower);
  if (!modelUrl) {
    console.warn("Missing 3D model for product:", flower.name || flower.product_name);
    return null;
  }

  const group = new THREE.Group();
  const total = selectedFlowers.value.length || 1;
  const angle = (index / total) * Math.PI * 2;
  const spread = bloomSpread.value;
  const r = 0.3 + (index % 3) * 0.25 * spread;
  const height = 1.5 + (index % 2) * 0.3 * flowerHeight.value;

  group.position.set(
    Math.sin(angle) * r,
    height + bouquetGroup.position.y,
    Math.cos(angle) * r,
  );

  try {
    const model = await loadModelAsset(modelUrl);
    fitModelToBounds(model, 0.8 * density.value);
    model.rotation.y = angle + Math.PI;
    model.rotation.x = Math.sin(index) * 0.08;
    group.add(model);
  } catch (error) {
    console.warn("Failed to load 3D model for product:", flower.name || flower.product_name, error);
    return null;
  }

  // Entrance pop
  group.scale.setScalar(0.01);
  const start = Date.now();
  const pop = () => {
    const t = Math.min((Date.now() - start) / 500, 1);
    const e = 1 - Math.pow(1 - t, 2);
    group.scale.setScalar(e);
    if (t < 1) requestAnimationFrame(pop);
  };
  pop();

  return group;
};

// ── Color updates ─────────────────────────────────────────────────────────
const applyPaperColor = (color) => {
  paperColor.value = color;
  if (paperMesh) {
    paperMesh.material.color.set(color);
    // Update creases and inner
    bouquetGroup?.children.forEach((c) => {
      if (c.geometry?.type === "ConeGeometry" && c !== paperMesh) {
        c.material.color.set(shadeColor(color, -25));
      }
    });
  }
};

const applyRibbonColor = (color) => {
  ribbonColor.value = color;
  bouquetGroup?.children.forEach((c) => {
    if (c.name === "ribbon" || c.material?.color) {
      // Update all ribbon-related meshes by checking names
    }
  });
  // Simplest: full rebuild
  buildProceduralBouquet();
};

const updateArrangement = () => {
  void rebuildFlowerMeshes();
};

// ── Drag & Drop ───────────────────────────────────────────────────────────
const onDragStart = (e, flower) => {
  if (!canAddFlower(flower)) {
    e.preventDefault();
    return;
  }

  const modelUrl = getFlowerModelUrl(flower);
  if (!modelUrl) {
    console.warn("Missing 3D model for product:", flower.name || flower.product_name);
    e.preventDefault();
    return;
  }

  draggingFlower.value = {
    ...flower,
    name: flower.name || flower.product_name,
    price: Number.parseFloat(flower.price ?? flower.selling_price ?? 0),
    stock: getFlowerStock(flower),
    model_3d_url: modelUrl,
    model: modelUrl,
  };
  e.dataTransfer.effectAllowed = "copy";
};
const onDragEnd = () => {
  draggingFlower.value = null;
};
const onDragOver = () => {
  isDragOver.value = true;
};
const onDragLeave = () => {
  isDragOver.value = false;
};
const onDropCanvas = () => {
  isDragOver.value = false;
  if (draggingFlower.value) {
    addFlower(draggingFlower.value);
    draggingFlower.value = null;
  }
};

const addFlower = (flower, options = {}) => {
  const { silent = false } = options;
  if (selectedFlowers.value.length >= MAX_FLOWERS) {
    showToast("Bouquet is full! Max " + MAX_FLOWERS + " flowers.", "error");
    return;
  }

  const normalizedFlower = {
    ...flower,
    name: flower.name || flower.product_name,
    product_name: flower.product_name || flower.name,
    price: Number.parseFloat(flower.price ?? flower.selling_price ?? 0),
    selling_price: Number.parseFloat(flower.price ?? flower.selling_price ?? 0),
    stock: getFlowerStock(flower),
    quantity_in_stock: getFlowerStock(flower),
    model_3d_url: getFlowerModelUrl(flower),
  };

  if (!normalizedFlower.model_3d_url) {
    console.warn(
      "Missing 3D model for product:",
      normalizedFlower.name || normalizedFlower.product_name,
    );
    showToast("This flower has no 3D model yet.", "error");
    return;
  }

  if (countOf(normalizedFlower.id) >= getFlowerStock(normalizedFlower)) {
    showToast("Stock limit reached for this flower.", "error");
    return;
  }

  selectedFlowers.value.push(normalizedFlower);
  void rebuildFlowerMeshes();
  if (!silent) {
    showToast(`${normalizedFlower.product_name} added!`, "success");
  }
};

const removeFlower = (index) => {
  selectedFlowers.value.splice(index, 1);
  void rebuildFlowerMeshes();
};

// ── Three.js controls ─────────────────────────────────────────────────────
const setupControls = () => {
  const canvas = renderer.domElement;

  const onMouseDown = (e) => {
    isDragging3D = true;
    autoRotate.value = false;
    prevMouse = { x: e.clientX, y: e.clientY };
    canvas.style.cursor = "grabbing";
  };
  const onMouseUp = () => {
    isDragging3D = false;
    canvas.style.cursor = "grab";
  };
  const onMouseMove = (e) => {
    if (!isDragging3D) return;
    const dx = e.clientX - prevMouse.x;
    const dy = e.clientY - prevMouse.y;
    rotY += dx * 0.006;
    rotX = Math.max(-0.8, Math.min(0.8, rotX + dy * 0.006));
    prevMouse = { x: e.clientX, y: e.clientY };
  };
  const onWheel = (e) => {
    e.preventDefault();
    zoomLevel = Math.max(2, Math.min(12, zoomLevel + e.deltaY * 0.005));
  };

  canvas.addEventListener("mousedown", onMouseDown);
  canvas.addEventListener("mouseup", onMouseUp);
  canvas.addEventListener("mouseleave", onMouseUp);
  canvas.addEventListener("mousemove", onMouseMove);
  canvas.addEventListener("wheel", onWheel, { passive: false });
  canvas.style.cursor = "grab";

  // Touch
  let tStart = null;
  canvas.addEventListener(
    "touchstart",
    (e) => {
      tStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
      autoRotate.value = false;
    },
    { passive: true },
  );
  canvas.addEventListener(
    "touchmove",
    (e) => {
      if (!tStart) return;
      const dx = e.touches[0].clientX - tStart.x;
      const dy = e.touches[0].clientY - tStart.y;
      rotY += dx * 0.008;
      rotX = Math.max(-0.8, Math.min(0.8, rotX + dy * 0.008));
      tStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
    },
    { passive: true },
  );
};

// ── Animation loop ────────────────────────────────────────────────────────
const animate = () => {
  animFrameId = requestAnimationFrame(animate);

  if (autoRotate.value) rotY += 0.004;

  if (bouquetGroup) {
    bouquetGroup.rotation.y = rotY;
    bouquetGroup.rotation.x = rotX;
    // Gentle float
    bouquetGroup.position.y = 0.5 + Math.sin(Date.now() * 0.0008) * 0.06;
  }
  if (flowerGroup) {
    flowerGroup.rotation.y = rotY;
    flowerGroup.rotation.x = rotX;
    flowerGroup.position.y = Math.sin(Date.now() * 0.0008) * 0.06;
  }

  camera.position.z = zoomLevel;
  camera.lookAt(0, 0, 0);

  renderer?.render(scene, camera);
};

const handleResize = () => {
  if (!viewerContainer.value || !camera || !renderer) return;
  const w = viewerContainer.value.clientWidth;
  const h = viewerContainer.value.clientHeight;
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
  renderer.setSize(w, h);
};

const resetCamera = () => {
  rotX = 0;
  rotY = 0;
  zoomLevel = 5;
  autoRotate.value = true;
};

const toggleWireframe = () => {
  wireframe.value = !wireframe.value;
  const traverse = (obj) => {
    if (obj.isMesh && obj.material) {
      const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
      mats.forEach((m) => {
        m.wireframe = wireframe.value;
      });
    }
    obj.children?.forEach(traverse);
  };
  traverse(bouquetGroup);
  traverse(flowerGroup);
};

const takeScreenshot = () => {
  renderer?.render(scene, camera);
  const url = renderer?.domElement.toDataURL("image/png");
  if (!url) return;
  const a = document.createElement("a");
  a.href = url;
  a.download = "bouquet-design.png";
  a.click();
};

const cleanup = () => {
  cancelAnimationFrame(animFrameId);
  disposePreviewRenderers();
  if (renderer) {
    renderer.dispose();
    viewerContainer.value?.removeChild(renderer.domElement);
  }
};

// ── Checkout & Save ───────────────────────────────────────────────────────
const checkout = async () => {
  if (!isAuthenticated.value) {
    router.push({
      path: "/guest/login",
      query: { redirect: router.currentRoute.value.fullPath },
    });
    return;
  }
  if (selectedFlowers.value.length === 0) return;
  isCheckingOut.value = true;
  try {
    const res = await api.post("custom-orders", {
      flowers: selectedFlowers.value.map((f) => ({
        id: f.id,
        name: f.name || f.product_name,
        price: f.price ?? f.selling_price,
        model_3d_url: f.model_3d_url,
      })),
      total_price: totalPrice.value,
      paper_color: paperColor.value,
      ribbon_color: ribbonColor.value,
      arrangement: {
        bloomSpread: bloomSpread.value,
        flowerHeight: flowerHeight.value,
        density: density.value,
      },
    });
    if (res.data.success) {
      showToast("Order placed successfully! 🌸", "success");
      router.push("/customer/orders");
    }
  } catch {
    showToast("Failed to place order. Please try again.", "error");
  } finally {
    isCheckingOut.value = false;
  }
};

const addCustomToCart = async () => {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login" });
    return;
  }
  showToast("Custom bouquet added to cart! 🌸", "success");
};

const saveDesign = async () => {
  isSaving.value = true;
  try {
    // Persist design config in localStorage for now (extend to API as needed)
    localStorage.setItem(
      "savedBouquetDesign",
      JSON.stringify({
        flowers: selectedFlowers.value,
        paperColor: paperColor.value,
        ribbonColor: ribbonColor.value,
        bloomSpread: bloomSpread.value,
        flowerHeight: flowerHeight.value,
        density: density.value,
      }),
    );
    showToast("Design saved! 💾", "success");
  } finally {
    isSaving.value = false;
  }
};

// ── Utilities ─────────────────────────────────────────────────────────────
const buildCustomizations = () => ({
  customization_type: "custom_bouquet",
  bouquet_flowers: selectedFlowers.value.map((flower) => ({
    id: flower.id,
    name: flower.name || flower.product_name,
    price: Number.parseFloat(flower.price ?? flower.selling_price ?? 0),
    model_3d_url: flower.model_3d_url ?? null,
  })),
  bouquet_summary: groupedFlowers.value,
  bouquet_total: totalPrice.value,
  paper_color: paperColor.value,
  ribbon_color: ribbonColor.value,
  arrangement: {
    bloomSpread: bloomSpread.value,
    flowerHeight: flowerHeight.value,
    density: density.value,
  },
});

const persistDesign = () => {
  localStorage.setItem(
    "savedBouquetDesign",
    JSON.stringify({
      productId: seedProductId.value,
      flowers: selectedFlowers.value,
      paperColor: paperColor.value,
      ribbonColor: ribbonColor.value,
      bloomSpread: bloomSpread.value,
      flowerHeight: flowerHeight.value,
      density: density.value,
    }),
  );
};

const getCartProductId = () =>
  seedProductId.value ??
  customizingContext.value?.productId ??
  selectedFlowers.value[0]?.id ??
  null;

const upsertCustomCartItem = async () => {
  const productId = getCartProductId();
  if (!productId) {
    throw new Error("No base product selected for this bouquet.");
  }

  persistDesign();

  const payload = {
    product_id: productId,
    quantity: 1,
    notes: `Custom bouquet with ${selectedFlowers.value.length} flower${selectedFlowers.value.length === 1 ? "" : "s"}`,
    customizations: buildCustomizations(),
  };

  let cartItemId = customizingContext.value?.cartItemId ?? null;

  if (cartItemId) {
    await api.put(`/cart/update/${cartItemId}`, {
      quantity: 1,
      notes: payload.notes,
      customizations: payload.customizations,
    });
  } else {
    const response = await cartStore.addToCart(payload);
    cartItemId = response?.data?.id ?? null;
  }

  await cartStore.refreshCart();

  if (!cartItemId) {
    const refreshed = cartStore.items.value.find(
      (item) => Number(item.product?.id) === Number(productId),
    );
    cartItemId = refreshed?.id ?? null;
  }

  customizingContext.value = {
    ...(customizingContext.value || {}),
    productId,
    cartItemId,
  };
  localStorage.setItem("customize_item", JSON.stringify(customizingContext.value));

  return cartItemId;
};

const proceedToCustomCheckout = async () => {
  if (!isAuthenticated.value) {
    router.push({
      path: "/guest/login",
      query: { redirect: router.currentRoute.value.fullPath },
    });
    return;
  }
  if (selectedFlowers.value.length === 0) return;

  isCheckingOut.value = true;
  try {
    const cartItemId = await upsertCustomCartItem();
    if (!cartItemId) {
      throw new Error("Unable to prepare checkout item.");
    }

    localStorage.setItem(
      "checkout_data",
      JSON.stringify({ cart_item_ids: [cartItemId] }),
    );
    router.push("/customer/checkout");
  } catch (error) {
    showToast(
      error?.response?.data?.message ||
        error?.message ||
        "Failed to prepare checkout. Please try again.",
      "error",
    );
  } finally {
    isCheckingOut.value = false;
  }
};

const handleAddCustomToCart = async () => {
  if (!isAuthenticated.value) {
    router.push({
      path: "/guest/login",
      query: { redirect: router.currentRoute.value.fullPath },
    });
    return;
  }
  if (selectedFlowers.value.length === 0) return;

  try {
    await upsertCustomCartItem();
    showToast("Custom bouquet added to cart! 🌸", "success");
  } catch (error) {
    showToast(
      error?.response?.data?.message ||
        "Failed to add custom bouquet to cart.",
      "error",
    );
  }
};

const handleSaveDesign = async () => {
  isSaving.value = true;
  try {
    persistDesign();
    showToast("Design saved! 💾", "success");
  } finally {
    isSaving.value = false;
  }
};

const shadeColor = (hex, percent) => {
  try {
    const num = parseInt(hex.replace("#", ""), 16);
    const r = Math.max(0, Math.min(255, (num >> 16) + percent));
    const g = Math.max(0, Math.min(255, ((num >> 8) & 0xff) + percent));
    const b = Math.max(0, Math.min(255, (num & 0xff) + percent));
    return "#" + ((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1);
  } catch {
    return hex;
  }
};

let toastTimeout = null;
const showToast = (message, type = "success") => {
  toast.message = message;
  toast.type = type;
  toast.show = true;
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => {
    toast.show = false;
  }, 3000);
};

// Rebuild on color change (debounced)
watch(paperColor, () => applyPaperColor(paperColor.value));
watch(ribbonColor, () => applyRibbonColor(ribbonColor.value));
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500&display=swap");

/* ── Root ──────────────────────────────────────────────────────────────── */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

.customizer-root {
  --fc-bg: #0f0d0b;
  --fc-text: #e8ddd0;
  --fc-toolbar-bg: rgba(20, 17, 14, 0.95);
  --fc-toolbar-border: rgba(200, 168, 130, 0.15);
  --fc-panel-bg: rgba(15, 13, 11, 0.98);
  --fc-panel-border: rgba(200, 168, 130, 0.12);
  --fc-card-bg: rgba(255, 255, 255, 0.04);
  --fc-card-border: rgba(200, 168, 130, 0.12);
  --fc-card-shadow: rgba(0, 0, 0, 0.4);
  --fc-tab-bg: rgba(255, 255, 255, 0.05);
  --fc-input-bg: rgba(255, 255, 255, 0.05);
  --fc-input-border: rgba(200, 168, 130, 0.15);
  --fc-input-placeholder: #4a3a2a;
  --fc-muted: #8a7a6a;
  --fc-soft: #6a5a4a;
  --fc-title: #c8a882;
  --fc-accent: #c8a882;
  --fc-accent-strong: #a8845e;
  --fc-accent-contrast: #1a1410;
  --fc-success: #8a9a7a;
  --fc-canvas-bg: #110e0b;
  --fc-preview-bg:
    radial-gradient(circle at top, rgba(200, 168, 130, 0.16), transparent 60%),
    linear-gradient(180deg, rgba(19, 17, 14, 0.96), rgba(9, 8, 7, 0.98));
  --fc-overlay: rgba(0, 0, 0, 0.5);
  --fc-chip-bg: rgba(200, 168, 130, 0.1);
  --fc-toast-success-bg: #1a2d1a;
  --fc-toast-success-border: rgba(74, 222, 128, 0.3);
  --fc-toast-success-text: #86efac;
  --fc-toast-error-bg: #2d1a1a;
  --fc-toast-error-border: rgba(248, 113, 113, 0.3);
  --fc-toast-error-text: #fca5a5;
  min-height: 100vh;
  background: var(--fc-bg);
  font-family: "DM Sans", sans-serif;
  color: var(--fc-text);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.customizer-root.theme-light {
  --fc-bg: #f6f1ea;
  --fc-text: #2b241d;
  --fc-toolbar-bg: rgba(255, 250, 245, 0.96);
  --fc-toolbar-border: rgba(115, 89, 58, 0.14);
  --fc-panel-bg: rgba(255, 252, 248, 0.96);
  --fc-panel-border: rgba(115, 89, 58, 0.12);
  --fc-card-bg: rgba(255, 255, 255, 0.9);
  --fc-card-border: rgba(115, 89, 58, 0.14);
  --fc-card-shadow: rgba(115, 89, 58, 0.14);
  --fc-tab-bg: rgba(115, 89, 58, 0.08);
  --fc-input-bg: rgba(255, 255, 255, 0.9);
  --fc-input-border: rgba(115, 89, 58, 0.18);
  --fc-input-placeholder: #a28f7a;
  --fc-muted: #7d6955;
  --fc-soft: #9e8a75;
  --fc-title: #8b5e34;
  --fc-accent: #a46d3d;
  --fc-accent-strong: #8b5e34;
  --fc-accent-contrast: #fffaf4;
  --fc-success: #56704a;
  --fc-canvas-bg: #e9dfd1;
  --fc-preview-bg:
    radial-gradient(circle at top, rgba(164, 109, 61, 0.18), transparent 60%),
    linear-gradient(180deg, rgba(255, 252, 248, 0.98), rgba(238, 229, 218, 0.98));
  --fc-overlay: rgba(63, 44, 26, 0.18);
  --fc-chip-bg: rgba(164, 109, 61, 0.12);
  --fc-toast-success-bg: #eff9ee;
  --fc-toast-success-border: rgba(68, 160, 95, 0.2);
  --fc-toast-success-text: #2f7a46;
  --fc-toast-error-bg: #fff1f1;
  --fc-toast-error-border: rgba(220, 38, 38, 0.18);
  --fc-toast-error-text: #c24141;
}

/* ── Toolbar ────────────────────────────────────────────────────────────── */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  background: var(--fc-toolbar-bg);
  border-bottom: 1px solid var(--fc-toolbar-border);
  z-index: 100;
  backdrop-filter: blur(10px);
  margin-top: 72px; /* NavHeader height */
  flex-shrink: 0;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 24px;
}

.toolbar-title {
  font-family: "Cormorant Garamond", serif;
  font-size: 18px;
  font-weight: 600;
  color: var(--fc-title);
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.toolbar-tabs {
  display: flex;
  background: var(--fc-tab-bg);
  border-radius: 8px;
  padding: 3px;
  gap: 2px;
}

.toolbar-tab {
  padding: 6px 16px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--fc-muted);
  cursor: pointer;
  transition: all 0.2s;
  font-family: "DM Sans", sans-serif;
}

.toolbar-tab:hover {
  color: var(--fc-title);
}
.toolbar-tab.active {
  background: var(--fc-accent);
  color: var(--fc-accent-contrast);
  font-weight: 600;
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tool-btn {
  width: 36px;
  height: 36px;
  border: 1px solid var(--fc-toolbar-border);
  background: var(--fc-card-bg);
  border-radius: 8px;
  color: var(--fc-muted);
  font-size: 16px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tool-btn:hover {
  border-color: var(--fc-accent);
  color: var(--fc-accent);
}
.tool-btn.active {
  background: var(--fc-chip-bg);
  border-color: var(--fc-accent);
  color: var(--fc-accent);
}

.theme-toggle {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--fc-toolbar-border);
  background: var(--fc-card-bg);
  color: var(--fc-title);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "DM Sans", sans-serif;
}

.theme-toggle:hover {
  border-color: var(--fc-accent);
  color: var(--fc-accent);
}

.btn-save-design {
  padding: 8px 20px;
  background: linear-gradient(135deg, var(--fc-accent), var(--fc-accent-strong));
  border: none;
  border-radius: 8px;
  color: var(--fc-accent-contrast);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "DM Sans", sans-serif;
}

.btn-save-design:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px color-mix(in srgb, var(--fc-accent) 35%, transparent);
}
.btn-save-design:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ── Layout ─────────────────────────────────────────────────────────────── */
.customizer-layout {
  display: grid;
  grid-template-columns: 280px 1fr 300px;
  flex: 1;
  overflow: hidden;
  height: calc(100vh - 72px - 57px);
}

/* ── Left Panel ─────────────────────────────────────────────────────────── */
.left-panel {
  background: var(--fc-panel-bg);
  border-right: 1px solid var(--fc-panel-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 16px;
  gap: 12px;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.panel-header h3 {
  font-family: "Cormorant Garamond", serif;
  font-size: 16px;
  font-weight: 600;
  color: var(--fc-title);
  letter-spacing: 0.04em;
}

.panel-count {
  font-size: 11px;
  color: var(--fc-soft);
  background: var(--fc-chip-bg);
  padding: 3px 8px;
  border-radius: 20px;
}

.search-bar {
  flex-shrink: 0;
}
.search-input {
  width: 100%;
  padding: 8px 12px;
  background: var(--fc-input-bg);
  border: 1px solid var(--fc-input-border);
  border-radius: 8px;
  color: var(--fc-title);
  font-size: 13px;
  font-family: "DM Sans", sans-serif;
  outline: none;
  transition: border-color 0.2s;
}

.search-input::placeholder {
  color: var(--fc-input-placeholder);
}
.search-input:focus {
  border-color: var(--fc-accent);
}

.flowers-grid {
  flex: 1;
  overflow-y: auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  align-content: start;
  scrollbar-width: thin;
  scrollbar-color: rgba(200, 168, 130, 0.2) transparent;
}

.flowers-grid::-webkit-scrollbar {
  width: 4px;
}
.flowers-grid::-webkit-scrollbar-thumb {
  background: rgba(200, 168, 130, 0.2);
  border-radius: 4px;
}

/* Flower card */
.flower-card {
  background: var(--fc-card-bg);
  border: 1px solid var(--fc-card-border);
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  user-select: none;
}

.flower-card:hover:not(.card-maxed) {
  border-color: var(--fc-accent);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px var(--fc-card-shadow);
}

.flower-card.card-dragging {
  opacity: 0.5;
  transform: scale(0.95);
}

.flower-card.card-maxed {
  opacity: 0.4;
  cursor: not-allowed;
}

.flower-card-img {
  width: 100%;
  aspect-ratio: 1;
  overflow: hidden;
  position: relative;
}

.flower-preview-canvas {
  width: 100%;
  height: 100%;
  display: block;
  background:
    var(--fc-preview-bg);
}
.flower-preview-fallback {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  padding: 12px;
  text-align: center;
  color: color-mix(in srgb, var(--fc-text) 78%, transparent);
  font-size: 11px;
  background: var(--fc-preview-bg);
}

.card-overlay {
  position: absolute;
  inset: 0;
  background: var(--fc-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
}

.flower-card:hover .card-overlay {
  opacity: 1;
}

.card-overlay-text {
  font-size: 13px;
  font-weight: 600;
  color: var(--fc-title);
  background: color-mix(in srgb, var(--fc-bg) 70%, transparent);
  padding: 4px 12px;
  border-radius: 20px;
}

.flower-card-info {
  padding: 6px 8px 8px;
}

.flower-name {
  font-size: 11px;
  font-weight: 500;
  color: var(--fc-title);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 2px;
}

.flower-price {
  font-size: 12px;
  font-weight: 600;
  color: var(--fc-success);
}
.flower-model-status {
  margin-top: 4px;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--fc-text) 52%, transparent);
}

.flower-count-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 20px;
  height: 20px;
  background: var(--fc-accent);
  color: var(--fc-accent-contrast);
  border-radius: 50%;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Skeleton */
.skeleton-card {
  border-color: rgba(200, 168, 130, 0.06);
  cursor: default;
}
.skeleton {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0.04) 25%,
    rgba(255, 255, 255, 0.08) 50%,
    rgba(255, 255, 255, 0.04) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 6px;
}
.sk-img {
  width: 100%;
  aspect-ratio: 1;
}
.sk-text {
  height: 24px;
  margin: 8px;
}
@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.no-flowers {
  grid-column: 1/-1;
  text-align: center;
  padding: 32px 16px;
  color: var(--fc-soft);
}
.no-flowers span {
  font-size: 32px;
  display: block;
  margin-bottom: 8px;
}
.no-flowers p {
  font-size: 13px;
}

/* Stem slots */
.stem-slots {
  flex-shrink: 0;
}
.slots-label {
  font-size: 11px;
  color: var(--fc-soft);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 8px;
}
.slots-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.slot {
  width: 34px;
  height: 34px;
  border: 1px solid var(--fc-input-border);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition: all 0.2s;
}

.slot-filled {
  border-color: var(--fc-accent);
  background: var(--fc-chip-bg);
}

.slot-active {
  border-color: var(--fc-accent);
  border-style: dashed;
  animation: pulse-slot 1.5s ease infinite;
}

@keyframes pulse-slot {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(200, 168, 130, 0.3);
  }
  50% {
    box-shadow: 0 0 0 4px rgba(200, 168, 130, 0.1);
  }
}

.slot-pulse {
  color: #c8a882;
  font-size: 18px;
  font-weight: 300;
}
.slot-empty {
  color: #2a2018;
  font-size: 12px;
}

/* ── Center Canvas ──────────────────────────────────────────────────────── */
.canvas-panel {
  position: relative;
  background: var(--fc-canvas-bg);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.canvas-panel.drop-active::after {
  content: "";
  position: absolute;
  inset: 0;
  border: 2px dashed var(--fc-accent);
  border-radius: 0;
  pointer-events: none;
  animation: dash-move 0.5s linear infinite;
}

@keyframes dash-move {
  0% {
    border-color: rgba(200, 168, 130, 0.5);
  }
  50% {
    border-color: rgba(200, 168, 130, 0.2);
  }
  100% {
    border-color: rgba(200, 168, 130, 0.5);
  }
}

.viewer-canvas {
  width: 100%;
  height: 100%;
  display: block;
}

/* Drop hint */
.drop-hint {
  position: absolute;
  inset: 0;
  background: rgba(200, 168, 130, 0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  z-index: 5;
}

.drop-hint-ring {
  width: 160px;
  height: 160px;
  border: 2px dashed rgba(200, 168, 130, 0.6);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: ring-pulse 1s ease infinite;
}

.drop-hint-ring span {
  font-size: 14px;
  font-weight: 600;
  color: #c8a882;
}

@keyframes ring-pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.05);
    opacity: 0.7;
  }
}

/* Canvas loading */
.canvas-loading,
.canvas-error {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: rgba(17, 14, 11, 0.85);
  z-index: 10;
  gap: 12px;
}

.canvas-spinner {
  width: 48px;
  height: 48px;
  border: 3px solid rgba(200, 168, 130, 0.2);
  border-top-color: #c8a882;
  border-radius: 50%;
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.canvas-loading p,
.canvas-error p {
  font-size: 13px;
  color: #8a7a6a;
}

.canvas-error button {
  padding: 6px 16px;
  background: rgba(200, 168, 130, 0.2);
  border: 1px solid rgba(200, 168, 130, 0.4);
  border-radius: 6px;
  color: #c8a882;
  font-size: 13px;
  cursor: pointer;
  font-family: "DM Sans", sans-serif;
}

/* Canvas instructions */
.canvas-instructions {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(15, 13, 11, 0.85);
  border: 1px solid rgba(200, 168, 130, 0.12);
  border-radius: 20px;
  padding: 6px 16px;
  font-size: 11px;
  color: #6a5a4a;
  backdrop-filter: blur(8px);
  white-space: nowrap;
  pointer-events: none;
}

.inst-sep {
  color: rgba(200, 168, 130, 0.2);
}

/* Bouquet chips */
.bouquet-chips {
  position: absolute;
  top: 16px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
  flex-wrap: nowrap;
  z-index: 5;
}

.bouquet-chip {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(200, 168, 130, 0.4);
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  flex-shrink: 0;
}

.bouquet-chip:hover {
  border-color: #e8344d;
  transform: scale(1.1);
}

.bouquet-chip strong {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  color: #f4e4d0;
  background: linear-gradient(135deg, #2a241e, #15110e);
}

.bouquet-chip span {
  position: absolute;
  inset: 0;
  background: rgba(232, 52, 77, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  color: white;
  opacity: 0;
  transition: opacity 0.2s;
}

.bouquet-chip:hover span {
  opacity: 1;
}

/* ── Right Panel ─────────────────────────────────────────────────────────── */
.right-panel {
  background: var(--fc-panel-bg);
  border-left: 1px solid var(--fc-panel-border);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(200, 168, 130, 0.1) transparent;
}

.right-panel::-webkit-scrollbar {
  width: 3px;
}
.right-panel::-webkit-scrollbar-thumb {
  background: var(--fc-input-border);
}

.panel-section {
  padding: 16px;
  border-bottom: 1px solid var(--fc-panel-border);
}

/* Color presets */
.color-presets {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin: 10px 0;
}

.color-swatch {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  padding: 0;
}

.color-swatch:hover {
  transform: scale(1.15);
}
.color-swatch.active {
  border-color: #c8a882;
  box-shadow: 0 0 0 2px rgba(200, 168, 130, 0.3);
}

.color-custom-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 8px 0;
}

.color-label {
  font-size: 12px;
  color: #6a5a4a;
}

.color-picker {
  width: 36px;
  height: 28px;
  border: 1px solid rgba(200, 168, 130, 0.2);
  border-radius: 6px;
  background: none;
  cursor: pointer;
  padding: 2px;
}

.color-hex {
  font-size: 11px;
  color: #8a7a6a;
  font-family: monospace;
}

.color-preview-strip {
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  padding: 0 12px;
  margin-top: 10px;
}

.color-preview-strip span {
  font-size: 11px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.7);
}

/* Sliders */
.slider-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 8px 0;
}

.slider-row label {
  font-size: 12px;
  color: #6a5a4a;
  width: 70px;
  flex-shrink: 0;
}

.custom-slider {
  flex: 1;
  height: 4px;
  border-radius: 2px;
  background: rgba(200, 168, 130, 0.15);
  outline: none;
  accent-color: #c8a882;
  cursor: pointer;
}

.slider-val {
  font-size: 11px;
  color: #c8a882;
  font-family: monospace;
  width: 30px;
  text-align: right;
}

/* Summary */
.summary-section {
  flex: 1;
}

.summary-items {
  margin: 10px 0;
  min-height: 80px;
}

.summary-empty {
  text-align: center;
  padding: 20px 0;
  color: #3a2a1a;
}
.summary-empty span {
  font-size: 24px;
  display: block;
  margin-bottom: 6px;
}
.summary-empty p {
  font-size: 12px;
}

.summary-line {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 6px 0;
  font-size: 12px;
}

.summary-flower-name {
  flex: 1;
  color: #a89a8a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.summary-flower-qty {
  color: #6a5a4a;
}
.summary-flower-price {
  color: #c8a882;
  font-weight: 600;
  min-width: 40px;
  text-align: right;
}

.summary-divider {
  height: 1px;
  background: rgba(200, 168, 130, 0.1);
  margin: 10px 0;
}

.summary-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #6a5a4a;
}
.meta-swatch {
  width: 16px;
  height: 16px;
  border-radius: 3px;
  border: 1px solid rgba(200, 168, 130, 0.2);
}

.total-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 12px 0;
}

.total-label {
  font-size: 14px;
  color: #8a7a6a;
}

.total-price {
  font-family: "Cormorant Garamond", serif;
  font-size: 28px;
  font-weight: 600;
  color: #c8a882;
}

.btn-checkout {
  width: 100%;
  padding: 13px;
  background: linear-gradient(135deg, #c8a882, #a8845e);
  border: none;
  border-radius: 10px;
  color: #1a1410;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "DM Sans", sans-serif;
  margin-bottom: 8px;
}

.btn-checkout:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(200, 168, 130, 0.3);
}

.btn-checkout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-add-to-cart-custom {
  width: 100%;
  padding: 11px;
  background: transparent;
  border: 1px solid rgba(200, 168, 130, 0.3);
  border-radius: 10px;
  color: #c8a882;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "DM Sans", sans-serif;
}

.btn-add-to-cart-custom:hover:not(:disabled) {
  background: rgba(200, 168, 130, 0.08);
}
.btn-add-to-cart-custom:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  z-index: 9999;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  font-family: "DM Sans", sans-serif;
}

.toast.success {
  background: var(--fc-toast-success-bg);
  border: 1px solid var(--fc-toast-success-border);
  color: var(--fc-toast-success-text);
}
.toast.error {
  background: var(--fc-toast-error-bg);
  border: 1px solid var(--fc-toast-error-border);
  color: var(--fc-toast-error-text);
}

.toast-slide-enter-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-slide-leave-active {
  transition: all 0.2s ease;
}
.toast-slide-enter-from {
  opacity: 0;
  transform: translateX(20px) scale(0.9);
}
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(20px);
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .customizer-layout {
    grid-template-columns: 240px 1fr 260px;
  }
}

@media (max-width: 900px) {
  .customizer-layout {
    grid-template-columns: 1fr;
    grid-template-rows: auto 50vh auto;
    height: auto;
    overflow: auto;
  }

  .toolbar {
    flex-wrap: wrap;
    gap: 8px;
  }
  .canvas-panel {
    min-height: 50vh;
  }
  .right-panel {
    max-height: 60vh;
    overflow-y: auto;
  }
}

@media (max-width: 640px) {
  .toolbar-title {
    display: none;
  }
  .toolbar-tabs {
    overflow-x: auto;
  }
  .flowers-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>
