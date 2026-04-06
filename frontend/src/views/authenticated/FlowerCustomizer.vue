<template>
  <div class="fc-page">
    <NavHeader :cartCount="cartCount" :isAuthenticated="isAuthenticated" />
    <div class="fc-shell">
      <header class="fc-topbar">
        <div>
          <p class="fc-kicker">Flower Customizer</p>
          <h1>{{ storeName || "Build Your Bouquet" }}</h1>
          <p class="fc-subtitle">Drag a flower or pick one, then click the bouquet to place it.</p>
        </div>
        <div class="fc-head-actions">
          <span class="fc-pill">{{ selectedFlowers.length }}/{{ MAX_FLOWERS }} selected</span>
          <button class="fc-btn fc-btn-light" @click="toggleLeftSidebar">
            {{ isLeftSidebarCollapsed ? "Show Flowers" : "Hide Flowers" }}
          </button>
          <button class="fc-btn fc-btn-light" @click="toggleRightSidebar">
            {{ isRightSidebarCollapsed ? "Show Tools" : "Hide Tools" }}
          </button>
          <button class="fc-btn fc-btn-light" @click="resetDesign" :disabled="!selectedFlowers.length">Reset</button>
          <button class="fc-btn fc-btn-light" @click="toggleFullscreen" :disabled="!sceneReady">
            {{ isFullscreen ? "Exit Fullscreen" : "Fullscreen" }}
          </button>
          <button class="fc-btn fc-btn-light" @click="takeScreenshot" :disabled="!sceneReady">Screenshot</button>
        </div>
      </header>

      <div class="fc-layout" :class="{ 'left-collapsed': isLeftSidebarCollapsed, 'right-collapsed': isRightSidebarCollapsed }">
        <aside v-if="!isLeftSidebarCollapsed" class="fc-panel">
          <div class="fc-panel-head">
            <div>
              <h2>Flowers</h2>
              <p>3D vendor products only</p>
            </div>
            <span class="fc-pill">{{ selectedFlowers.length }}/{{ MAX_FLOWERS }}</span>
          </div>

          <input v-model="search" class="fc-input" type="search" placeholder="Search flowers" />

          <div v-if="!storeId" class="fc-empty">Open this page from a vendor storefront so the customizer can load that vendor's flowers.</div>
          <div v-else-if="loadingFlowers" class="fc-empty">Loading flowers...</div>
          <div v-else-if="!filteredFlowers.length" class="fc-empty">No valid customizable flowers were found for this vendor.</div>

          <div v-else class="fc-list">
            <article
              v-for="flower in filteredFlowers"
              :key="flower.id"
              class="fc-card"
              :class="{ active: pendingFlower?.id === flower.id, disabled: !canUseFlower(flower), dragging: draggedFlower?.id === flower.id }"
              :draggable="canUseFlower(flower)"
              @dragstart="onDragStart($event, flower)"
              @dragend="onDragEnd"
              @click="selectFlower(flower)"
            >
              <div class="fc-card-media">
                <img v-if="flower.image" :src="flower.image" :alt="flower.product_name" class="fc-card-image" />
                <div v-else class="fc-card-placeholder">No Photo</div>
              </div>
              <div class="fc-row">
                <strong>{{ flower.product_name }}</strong>
                <span>₱{{ flower.price.toFixed(2) }}</span>
              </div>
              <div class="fc-tags">
                <span class="fc-tag">{{ flower.quantity_in_stock }} in stock</span>
                <span class="fc-tag ok">3D model ready</span>
              </div>
              <div class="fc-row fc-note">
                <span>{{ countOfFlower(flower.id) ? `${countOfFlower(flower.id)} placed` : "Click then place on bouquet" }}</span>
                <span v-if="!canUseFlower(flower)">{{ selectedFlowers.length >= MAX_FLOWERS ? "Limit reached" : "Out of stock" }}</span>
              </div>
            </article>
          </div>
        </aside>

        <main
          class="fc-stage"
          :class="{ over: isDragOver, dragging: isTransformDragging }"
          @dragover.prevent="onCanvasDragOver"
          @dragleave="onCanvasDragLeave"
          @drop.prevent="onCanvasDrop"
        >
          <div class="fc-stage-actions">
            <button class="fc-icon-btn" @click="toggleLeftSidebar">
              {{ isLeftSidebarCollapsed ? "◀ Flowers" : "✕ Flowers" }}
            </button>
            <button class="fc-icon-btn" @click="toggleFullscreen" :disabled="!sceneReady">
              {{ isFullscreen ? "🗗 Exit" : "⛶ Full" }}
            </button>
            <button class="fc-icon-btn" @click="toggleRightSidebar">
              {{ isRightSidebarCollapsed ? "Tools ▶" : "Tools ✕" }}
            </button>
          </div>
          <div ref="viewerContainer" class="fc-viewer"></div>
          <div class="fc-overlay fc-top">
            <div class="fc-status">
              <strong>{{ pendingFlower ? `Ready to place: ${pendingFlower.product_name}` : draggedFlower ? `Drop ${draggedFlower.product_name} on the bouquet` : selectedFlower ? `Selected: ${selectedFlower.product_name}` : "Rotate the bouquet and pick a flower" }}</strong>
              <span>{{ selectedFlowers.length >= MAX_FLOWERS ? "Maximum of 3 flowers only" : selectedFlower ? "Drag anywhere on the bouquet surface to move the selected flower, or rotate it from the panel." : "Click directly on the bouquet wrapper to place the model" }}</span>
            </div>
          </div>
          <div v-if="bouquetLoadError" class="fc-overlay fc-center"><div class="fc-modal"><h3>Unable to load bouquet.glb</h3><p>{{ bouquetLoadError }}</p></div></div>
          <div v-else-if="isLoading3D" class="fc-overlay fc-center"><div class="fc-modal">Loading 3D bouquet...</div></div>
          <div v-if="dragFeedback" class="fc-overlay fc-bottom"><div class="fc-modal">{{ dragFeedback }}</div></div>
        </main>

        <aside v-if="!isRightSidebarCollapsed" class="fc-panel fc-side">
          <section class="fc-section">
            <div class="fc-panel-head"><h2>Placed Flowers</h2><span>{{ selectedFlowers.length }}/{{ MAX_FLOWERS }}</span></div>
            <div v-if="!selectedFlowers.length" class="fc-empty">No flowers placed yet.</div>
            <div v-else class="fc-list">
              <div
                v-for="selection in selectedFlowers"
                :key="selection.sceneId"
                class="fc-selection"
                :class="{ active: selectedSceneId === selection.sceneId }"
                @click="selectPlacedFlower(selection.sceneId)"
              >
                <div><strong>{{ selection.product_name }}</strong><p>₱{{ selection.price.toFixed(2) }}</p></div>
                <button class="fc-btn fc-btn-danger" @click.stop="removeFlower(selection.sceneId)">Remove</button>
              </div>
            </div>
          </section>

          <section class="fc-section">
            <div class="fc-panel-head"><h2>Wrapper Color</h2><span>{{ paperColor.toUpperCase() }}</span></div>
            <div class="fc-swatches">
              <button v-for="color in paperPresets" :key="color" class="fc-swatch" :class="{ active: paperColor === color }" :style="{ background: color }" @click="applyPaperColor(color)"></button>
            </div>
            <input v-model="paperColor" class="fc-input" type="color" @input="applyPaperColor(paperColor)" />
          </section>

          <section class="fc-section">
            <div class="fc-panel-head"><h2>Transform</h2><span>{{ selectedFlower ? selectedFlower.product_name : "Select a flower" }}</span></div>
            <div v-if="!selectedFlower" class="fc-empty">Select a placed flower, then drag it on the bouquet or rotate it here.</div>
            <div v-else class="fc-transform">
              <label class="fc-transform-row">
                <span>Rotate X</span>
                <strong>{{ Math.round(selectedFlower.rotation.xDeg) }}°</strong>
              </label>
              <input class="fc-range" type="range" min="-180" max="180" :value="selectedFlower.rotation.xDeg" @input="updateSelectedRotation('x', Number($event.target.value))" />
              <label class="fc-transform-row">
                <span>Rotate Y</span>
                <strong>{{ Math.round(selectedFlower.rotation.yDeg) }}°</strong>
              </label>
              <input class="fc-range" type="range" min="-180" max="180" :value="selectedFlower.rotation.yDeg" @input="updateSelectedRotation('y', Number($event.target.value))" />
              <div class="fc-transform-actions">
                <button class="fc-btn fc-btn-light" @click="nudgeSelectedRotation('x', -15)">X -15°</button>
                <button class="fc-btn fc-btn-light" @click="nudgeSelectedRotation('x', 15)">X +15°</button>
                <button class="fc-btn fc-btn-light" @click="nudgeSelectedRotation('y', -15)">Y -15°</button>
                <button class="fc-btn fc-btn-light" @click="nudgeSelectedRotation('y', 15)">Y +15°</button>
              </div>
              <button class="fc-btn fc-btn-light full" @click="resetSelectedTransform">Reset Selected Flower</button>
            </div>
          </section>

          <section class="fc-section">
            <div class="fc-panel-head"><h2>Ribbon Color</h2><span>{{ ribbonColor.toUpperCase() }}</span></div>
            <div class="fc-swatches">
              <button v-for="color in ribbonPresets" :key="color" class="fc-swatch" :class="{ active: ribbonColor === color }" :style="{ background: color }" @click="applyRibbonColor(color)"></button>
            </div>
            <input v-model="ribbonColor" class="fc-input" type="color" @input="applyRibbonColor(ribbonColor)" />
          </section>

          <section class="fc-section">
            <div class="fc-panel-head"><h2>Summary</h2><span>Live total</span></div>
            <div class="fc-summary"><span>Store</span><strong>{{ storeName || "Not set" }}</strong></div>
            <div class="fc-summary"><span>Owner ID</span><strong>{{ vendorOwnerId || "Missing" }}</strong></div>
            <div class="fc-summary"><span>Flowers</span><strong>{{ selectedFlowers.length }}/{{ MAX_FLOWERS }}</strong></div>
            <div class="fc-summary total"><span>Total</span><strong>₱{{ totalPrice.toFixed(2) }}</strong></div>
            <button class="fc-btn fc-btn-primary" @click="checkout" :disabled="!selectedFlowers.length || isCheckingOut">{{ isCheckingOut ? "Processing..." : "Proceed to Checkout" }}</button>
            <button class="fc-btn fc-btn-light full" @click="addCart" :disabled="!selectedFlowers.length">Add to Cart</button>
          </section>
        </aside>
      </div>
    </div>

    <transition name="fade"><div v-if="toast.show" class="fc-toast" :class="toast.type">{{ toast.message }}</div></transition>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import { OrbitControls } from "three/examples/jsm/controls/OrbitControls.js";
import * as SkeletonUtils from "three/examples/jsm/utils/SkeletonUtils.js";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";

const MAX_FLOWERS = 3;
const paperPresets = ["#d3b18f", "#f3e9dc", "#d7d2c8", "#e2c7cf", "#c8b6a6"];
const ribbonPresets = ["#caa6d9", "#d95f76", "#7d8f69", "#f0dfb2", "#6c5b7b"];

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const cartStore = useCart();

const viewerContainer = ref(null);
const flowers = ref([]);
const loadingFlowers = ref(false);
const search = ref("");
const pendingFlower = ref(null);
const draggedFlower = ref(null);
const selectedFlowers = ref([]);
const selectedSceneId = ref(null);
const isDragOver = ref(false);
const isTransformDragging = ref(false);
const isLeftSidebarCollapsed = ref(false);
const isRightSidebarCollapsed = ref(false);
const isFullscreen = ref(false);
const dragFeedback = ref("");
const paperColor = ref("#d3b18f");
const ribbonColor = ref("#caa6d9");
const isLoading3D = ref(true);
const sceneReady = ref(false);
const bouquetLoadError = ref("");
const isCheckingOut = ref(false);
const toast = reactive({ show: false, message: "", type: "success" });

const storeId = computed(() => String(route.query.store_id || route.params.id || ""));
const vendorOwnerId = computed(() => Number(route.query.owner_id || 0) || null);
const storeName = computed(() => String(route.query.store_name || ""));
const cartCount = computed(() => cartStore.count?.value ?? 0);
const filteredFlowers = computed(() => {
  const term = search.value.trim().toLowerCase();
  return flowers.value.filter((flower) => !term || flower.product_name.toLowerCase().includes(term));
});
const totalPrice = computed(() => selectedFlowers.value.reduce((sum, flower) => sum + flower.price, 0));
const selectedFlower = computed(() => selectedFlowers.value.find((flower) => flower.sceneId === selectedSceneId.value) || null);

let scene, camera, renderer, controls, bouquetRoot, flowerLayer, hoverMarker, animationFrameId = 0, pointerDown = null, transformDrag = null, toastTimer;
const loader = new GLTFLoader();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const bouquetTargets = [];
const ribbonMaterials = new Set();
const wrapperMaterials = new Set();
const modelCache = new Map();
const placedObjects = new Map();

const countOfFlower = (flowerId) => selectedFlowers.value.filter((flower) => flower.id === flowerId).length;
const canUseFlower = (flower) => !!flower?.model && selectedFlowers.value.length < MAX_FLOWERS && flower.quantity_in_stock > countOfFlower(flower.id);

onMounted(async () => {
  if (isAuthenticated.value) await cartStore.initialize?.();
  initScene();
  await Promise.all([loadBouquetModel(), fetchFlowers()]);
  window.addEventListener("resize", handleResize);
  document.addEventListener("fullscreenchange", syncFullscreenState);
});

onUnmounted(() => {
  window.removeEventListener("resize", handleResize);
  document.removeEventListener("fullscreenchange", syncFullscreenState);
  cleanupScene();
  clearTimeout(toastTimer);
});

function initScene() {
  if (!viewerContainer.value) return;
  scene = new THREE.Scene();
  scene.background = new THREE.Color("#b29f86");
  camera = new THREE.PerspectiveCamera(42, viewerContainer.value.clientWidth / viewerContainer.value.clientHeight, 0.1, 100);
  camera.position.set(0, 2.1, 6.4);
  renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(viewerContainer.value.clientWidth, viewerContainer.value.clientHeight);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.shadowMap.enabled = true;
  viewerContainer.value.appendChild(renderer.domElement);
  controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.target.set(0, 1.5, 0);
  controls.minDistance = 3.8;
  controls.maxDistance = 9;
  controls.maxPolarAngle = Math.PI * 0.48;
  const ambient = new THREE.AmbientLight(0xffffff, 1.2);
  const key = new THREE.DirectionalLight(0xfff1dc, 1.6);
  key.position.set(4, 8, 5);
  key.castShadow = true;
  key.shadow.mapSize.set(2048, 2048);
  const fill = new THREE.DirectionalLight(0xe2f0ff, 0.7);
  fill.position.set(-4, 3, -2);
  const ground = new THREE.Mesh(new THREE.CircleGeometry(10, 64), new THREE.ShadowMaterial({ opacity: 0.18 }));
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -0.02;
  ground.receiveShadow = true;
  hoverMarker = new THREE.Mesh(new THREE.SphereGeometry(0.08, 16, 16), new THREE.MeshStandardMaterial({ color: 0xffffff, emissive: 0xffffff, emissiveIntensity: 0.45 }));
  hoverMarker.visible = false;
  flowerLayer = new THREE.Group();
  scene.add(ambient, key, fill, ground, flowerLayer, hoverMarker);
  setupCanvasInteractions();
  animateScene();
}

async function loadBouquetModel() {
  isLoading3D.value = true;
  bouquetLoadError.value = "";
  try {
    const gltf = await loadFirstAvailableGltf(["/bouquet.glb", "/Boquet.glb"]);
    bouquetRoot = gltf.scene || gltf.scenes?.[0];
    if (!bouquetRoot) throw new Error("No scene found inside bouquet.glb.");

    bouquetRoot.traverse((child) => {
      if (!child.isMesh) return;
      child.castShadow = true;
      child.receiveShadow = true;
      bouquetTargets.push(child);
      const materials = Array.isArray(child.material) ? child.material : [child.material];
      const name = String(child.name || "").toLowerCase();
      if (name.includes("ribbon") || name.includes("bow") || name.includes("lace")) {
        materials.forEach((material) => ribbonMaterials.add(material));
      } else if (name.includes("wrapper") || name.includes("wrap") || name.includes("paper")) {
        materials.forEach((material) => wrapperMaterials.add(material));
      }
    });

    if (!wrapperMaterials.size) {
      bouquetTargets.forEach((target) => {
        const name = String(target.name || "").toLowerCase();
        if (name.includes("ribbon") || name.includes("bow")) return;
        const materials = Array.isArray(target.material) ? target.material : [target.material];
        materials.forEach((material) => wrapperMaterials.add(material));
      });
    }

    const box = new THREE.Box3().setFromObject(bouquetRoot);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const scale = 3.5 / Math.max(size.x, size.y, size.z, 1);
    bouquetRoot.scale.setScalar(scale);
    bouquetRoot.position.sub(center.multiplyScalar(scale));
    const scaledBox = new THREE.Box3().setFromObject(bouquetRoot);
    bouquetRoot.position.y -= scaledBox.min.y;
    scene.add(bouquetRoot);
    applyPaperColor(paperColor.value);
    applyRibbonColor(ribbonColor.value);
    sceneReady.value = true;
  } catch (error) {
    console.error("Failed to load bouquet base model:", error);
    bouquetLoadError.value = "Make sure the bouquet base model exists in `frontend/public` as `/bouquet.glb` or `/Boquet.glb`.";
  } finally {
    isLoading3D.value = false;
  }
}

async function fetchFlowers() {
  if (!storeId.value) return;
  loadingFlowers.value = true;
  try {
    const response = await api.get(`/stores/${storeId.value}/customizable-flowers`, {
      params: vendorOwnerId.value ? { owner_id: vendorOwnerId.value } : {},
    });
    const rawItems = Array.isArray(response.data?.data) ? response.data.data : [];
    flowers.value = rawItems.map(normalizeFlower).filter((flower) => {
      if (!flower.model) return false;
      if (flower.selling_type !== "per_piece") return false;
      if (!flower.is_customizable) return false;
      if (vendorOwnerId.value && flower.owner_id !== vendorOwnerId.value) return false;
      return true;
    });
  } catch (error) {
    console.error("Failed to fetch customizable flowers:", error);
    flowers.value = [];
    showToast("Failed to load vendor flowers.", "error");
  } finally {
    loadingFlowers.value = false;
  }
}

function normalizeFlower(flower) {
  const model = resolveModelUrl(flower);
  return {
    id: Number(flower.id),
    owner_id: Number(flower.owner_id || vendorOwnerId.value || 0) || null,
    product_name: flower.product_name || flower.name || "Unnamed Flower",
    price: Number(flower.price ?? flower.selling_price ?? 0),
    quantity_in_stock: Number(flower.quantity_in_stock ?? flower.stock ?? 0),
    selling_type: flower.selling_type || "per_piece",
    is_customizable: flower.is_customizable === undefined ? true : Boolean(flower.is_customizable),
    image: flower.primary_image_url || flower.primary_image?.image_url || flower.images?.[0]?.image_url || null,
    model,
    model_3d_url: model,
  };
}

function resolveModelUrl(flower) {
  const modelRecord = Array.isArray(flower.models) ? flower.models[0] : null;
  const rawModel = modelRecord?.model_url || modelRecord?.model_path || flower.model_3d_url || null;
  if (!rawModel || typeof rawModel !== "string") return null;
  if (/^https?:\/\//i.test(rawModel)) return rawModel.replace(/^http:\/\//i, "https://");
  if (rawModel.startsWith("/")) return `${window.location.origin}${rawModel}`;
  return `https://res.cloudinary.com/dqs1e1inx/raw/upload/${rawModel.replace(/^\/+/, "")}`;
}

function selectFlower(flower) {
  if (!canUseFlower(flower)) return enforceFlowerLimit(flower);
  pendingFlower.value = flower;
  showToast(`Click the bouquet to place ${flower.product_name}.`, "success");
}

function onDragStart(event, flower) {
  if (!canUseFlower(flower)) {
    event.preventDefault();
    return enforceFlowerLimit(flower);
  }
  draggedFlower.value = {
    id: flower.id,
    name: flower.product_name,
    product_name: flower.product_name,
    price: flower.price,
    model: flower.model,
    quantity_in_stock: flower.quantity_in_stock,
    owner_id: flower.owner_id,
    selling_type: flower.selling_type,
    is_customizable: flower.is_customizable,
  };
  dragFeedback.value = `Drop ${flower.product_name} on the bouquet`;
  event.dataTransfer.effectAllowed = "copy";
}

function onDragEnd() {
  draggedFlower.value = null;
  dragFeedback.value = "";
  isDragOver.value = false;
  hideHoverMarker();
}

function onCanvasDragOver(event) {
  isDragOver.value = true;
  if (!draggedFlower.value) return;
  dragFeedback.value = `Release to place ${draggedFlower.value.product_name}`;
  updateHoverMarker(event.clientX, event.clientY);
}

function onCanvasDragLeave() {
  isDragOver.value = false;
  dragFeedback.value = draggedFlower.value ? `Drop ${draggedFlower.value.product_name} on the bouquet` : "";
  hideHoverMarker();
}

async function onCanvasDrop(event) {
  isDragOver.value = false;
  dragFeedback.value = "";
  if (!draggedFlower.value) return;
  const placement = getPlacement(event.clientX, event.clientY);
  if (!placement) {
    draggedFlower.value = null;
    hideHoverMarker();
    return showToast("Drop directly on the bouquet.", "error");
  }
  await placeFlowerOnBouquet(draggedFlower.value, placement);
  draggedFlower.value = null;
  hideHoverMarker();
}

function setupCanvasInteractions() {
  const canvas = renderer.domElement;
  canvas.addEventListener("pointerdown", (event) => {
    if (event.button !== 0) return;
    pointerDown = { x: event.clientX, y: event.clientY };
    const placedHit = getPlacedFlowerHit(event.clientX, event.clientY);
    if (placedHit?.sceneId) {
      event.preventDefault();
      selectPlacedFlower(placedHit.sceneId);
      transformDrag = { sceneId: placedHit.sceneId, pointerId: event.pointerId };
      isTransformDragging.value = true;
      if (canvas.setPointerCapture) {
        canvas.setPointerCapture(event.pointerId);
      }
      controls.enabled = false;
      return;
    }

    if (
      selectedSceneId.value &&
      !pendingFlower.value &&
      !draggedFlower.value &&
      getPlacement(event.clientX, event.clientY)
    ) {
      event.preventDefault();
      transformDrag = { sceneId: selectedSceneId.value, pointerId: event.pointerId };
      isTransformDragging.value = true;
      if (canvas.setPointerCapture) {
        canvas.setPointerCapture(event.pointerId);
      }
      controls.enabled = false;
    }
  });
  canvas.addEventListener("pointermove", (event) => {
    if (transformDrag?.sceneId) {
      event.preventDefault();
      moveSelectedFlower(event.clientX, event.clientY, transformDrag.sceneId);
      return;
    }
    if (pendingFlower.value || draggedFlower.value) updateHoverMarker(event.clientX, event.clientY);
  });
  canvas.addEventListener("pointerleave", () => {
    endTransformDrag();
    hideHoverMarker();
  });
  canvas.addEventListener("pointerup", async (event) => {
    if (!pointerDown || event.button !== 0) return;
    const moved = Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y) > 6;
    if (transformDrag?.sceneId) {
      endTransformDrag(event.pointerId);
      pointerDown = null;
      return;
    }
    const placedHit = getPlacedFlowerHit(event.clientX, event.clientY);
    if (!moved && placedHit?.sceneId) {
      selectPlacedFlower(placedHit.sceneId);
      pointerDown = null;
      return;
    }
    pointerDown = null;
    if (moved || !pendingFlower.value) return;
    const placement = getPlacement(event.clientX, event.clientY);
    if (!placement) return showToast("Click directly on the bouquet to place the flower.", "error");
    await placeFlowerOnBouquet(pendingFlower.value, placement);
    pendingFlower.value = null;
    hideHoverMarker();
  });
  canvas.addEventListener("pointercancel", (event) => {
    endTransformDrag(event.pointerId);
    pointerDown = null;
  });
}

function getPlacement(clientX, clientY) {
  if (!viewerContainer.value || !camera || !bouquetTargets.length) return null;
  const bounds = viewerContainer.value.getBoundingClientRect();
  pointer.x = ((clientX - bounds.left) / bounds.width) * 2 - 1;
  pointer.y = -((clientY - bounds.top) / bounds.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const intersections = raycaster.intersectObjects(bouquetTargets, true);
  if (!intersections.length) return null;
  const hit = intersections[0];
  const normal = hit.face?.normal ? hit.face.normal.clone().transformDirection(hit.object.matrixWorld).normalize() : new THREE.Vector3(0, 1, 0);
  return { point: hit.point.clone().add(normal.clone().multiplyScalar(0.05)), normal };
}

function getPlacedFlowerHit(clientX, clientY) {
  if (!viewerContainer.value || !camera || !placedObjects.size) return null;
  const bounds = viewerContainer.value.getBoundingClientRect();
  pointer.x = ((clientX - bounds.left) / bounds.width) * 2 - 1;
  pointer.y = -((clientY - bounds.top) / bounds.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const intersections = raycaster.intersectObjects(Array.from(placedObjects.values()), true);
  if (!intersections.length) return null;
  let current = intersections[0].object;
  while (current && !current.userData?.sceneId) current = current.parent;
  return current?.userData?.sceneId ? { sceneId: current.userData.sceneId, object: current } : null;
}

function updateHoverMarker(clientX, clientY) {
  const placement = getPlacement(clientX, clientY);
  if (!placement) return hideHoverMarker();
  hoverMarker.visible = true;
  hoverMarker.position.copy(placement.point);
}

function hideHoverMarker() {
  if (hoverMarker) hoverMarker.visible = false;
}

function endTransformDrag(pointerId = null) {
  const canvas = renderer?.domElement;
  if (canvas && pointerId !== null && canvas.hasPointerCapture?.(pointerId)) {
    canvas.releasePointerCapture(pointerId);
  }
  transformDrag = null;
  isTransformDragging.value = false;
  if (controls) controls.enabled = true;
}

async function placeFlowerOnBouquet(flower, placement) {
  if (selectedFlowers.value.length >= MAX_FLOWERS) {
    window.alert("Maximum of 3 flowers only");
    showToast("Maximum of 3 flowers only.", "error");
    return;
  }
  if (countOfFlower(flower.id) >= Number(flower.quantity_in_stock || 0)) {
    return showToast("This flower is out of stock.", "error");
  }
  if (!flower.model) {
    return showToast("This flower has no 3D model.", "error");
  }

  try {
    const template = await loadFlowerTemplate(flower.model);
    const instance = SkeletonUtils.clone(template);
    const wrapper = new THREE.Group();
    const sceneId = `${flower.id}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const initialRotation = {
      xDeg: -5,
      yDeg: 180,
      zDeg: THREE.MathUtils.radToDeg((Math.random() - 0.5) * 0.14),
    };
    fitFlowerModel(instance);
    wrapper.add(instance);
    wrapper.position.copy(placement.point);
    wrapper.rotation.set(
      THREE.MathUtils.degToRad(initialRotation.xDeg),
      THREE.MathUtils.degToRad(initialRotation.yDeg),
      THREE.MathUtils.degToRad(initialRotation.zDeg),
    );
    wrapper.userData.sceneId = sceneId;
    flowerLayer.add(wrapper);
    placedObjects.set(sceneId, wrapper);
    selectedFlowers.value.push({
      sceneId,
      id: flower.id,
      owner_id: flower.owner_id,
      product_name: flower.product_name || flower.name,
      price: Number(flower.price || 0),
      model_3d_url: flower.model,
      position: { x: placement.point.x, y: placement.point.y, z: placement.point.z },
      rotation: initialRotation,
    });
    selectedSceneId.value = sceneId;
    showToast(`${flower.product_name} placed on bouquet.`, "success");
  } catch (error) {
    console.error("Failed to place flower model:", error);
    showToast("Failed to load the flower 3D model.", "error");
  }
}

async function loadFlowerTemplate(url) {
  if (modelCache.has(url)) return modelCache.get(url);
  const gltf = await loadGltf(url);
  const template = gltf.scene || gltf.scenes?.[0];
  if (!template) throw new Error(`No scene found in flower model: ${url}`);
  template.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
  });
  modelCache.set(url, template);
  return template;
}

function fitFlowerModel(model) {
  const sourceBox = new THREE.Box3().setFromObject(model);
  const size = sourceBox.getSize(new THREE.Vector3());
  const scale = 0.9 / Math.max(size.x, size.y, size.z, 1);
  model.scale.setScalar(scale);
  model.updateMatrixWorld(true);
  const scaledBox = new THREE.Box3().setFromObject(model);
  const center = scaledBox.getCenter(new THREE.Vector3());
  model.position.sub(center);
  model.updateMatrixWorld(true);
}

function applyPaperColor(color) {
  paperColor.value = color;
  wrapperMaterials.forEach((material) => material?.color?.set(color));
}

function applyRibbonColor(color) {
  ribbonColor.value = color;
  ribbonMaterials.forEach((material) => material?.color?.set(color));
}

function removeFlower(sceneId) {
  selectedFlowers.value = selectedFlowers.value.filter((flower) => flower.sceneId !== sceneId);
  if (selectedSceneId.value === sceneId) {
    selectedSceneId.value = selectedFlowers.value[0]?.sceneId ?? null;
  }
  const object = placedObjects.get(sceneId);
  if (object) {
    flowerLayer.remove(object);
    placedObjects.delete(sceneId);
  }
}

function resetDesign() {
  selectedFlowers.value.forEach((flower) => {
    const object = placedObjects.get(flower.sceneId);
    if (object) flowerLayer.remove(object);
  });
  selectedFlowers.value = [];
  selectedSceneId.value = null;
  placedObjects.clear();
  pendingFlower.value = null;
  draggedFlower.value = null;
  hideHoverMarker();
}

function selectPlacedFlower(sceneId) {
  selectedSceneId.value = sceneId;
}

function moveSelectedFlower(clientX, clientY, sceneId = selectedSceneId.value) {
  const placement = getPlacement(clientX, clientY);
  const object = sceneId ? placedObjects.get(sceneId) : null;
  if (!placement || !object) return;
  object.position.copy(placement.point);
  updateFlowerState(sceneId, {
    position: { x: placement.point.x, y: placement.point.y, z: placement.point.z },
  });
}

function updateSelectedRotation(axis, degrees) {
  const sceneId = selectedSceneId.value;
  const object = sceneId ? placedObjects.get(sceneId) : null;
  const flower = selectedFlower.value;
  if (!sceneId || !object || !flower) return;
  const nextRotation = { ...flower.rotation, [`${axis}Deg`]: degrees };
  if (axis === "x") object.rotation.x = THREE.MathUtils.degToRad(degrees);
  if (axis === "y") object.rotation.y = THREE.MathUtils.degToRad(degrees);
  updateFlowerState(sceneId, { rotation: nextRotation });
}

function nudgeSelectedRotation(axis, delta) {
  const flower = selectedFlower.value;
  if (!flower) return;
  updateSelectedRotation(axis, flower.rotation[`${axis}Deg`] + delta);
}

function resetSelectedTransform() {
  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object) return;
  const resetRotation = { xDeg: -5, yDeg: 180, zDeg: 0 };
  object.rotation.set(
    THREE.MathUtils.degToRad(resetRotation.xDeg),
    THREE.MathUtils.degToRad(resetRotation.yDeg),
    THREE.MathUtils.degToRad(resetRotation.zDeg),
  );
  updateFlowerState(flower.sceneId, { rotation: resetRotation });
}

function updateFlowerState(sceneId, patch) {
  selectedFlowers.value = selectedFlowers.value.map((flower) =>
    flower.sceneId === sceneId ? { ...flower, ...patch } : flower,
  );
}

function enforceFlowerLimit(flower) {
  if (selectedFlowers.value.length >= MAX_FLOWERS) {
    window.alert("Maximum of 3 flowers only");
    return showToast("Maximum of 3 flowers only.", "error");
  }
  if (flower.quantity_in_stock <= countOfFlower(flower.id)) {
    return showToast(`${flower.product_name} is out of stock.`, "error");
  }
  if (!flower.model) {
    return showToast(`${flower.product_name} has no 3D model.`, "error");
  }
}

async function checkout() {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  if (!selectedFlowers.value.length) return;
  isCheckingOut.value = true;
  try {
    const response = await api.post("custom-orders", {
      store_id: storeId.value,
      owner_id: vendorOwnerId.value,
      flowers: selectedFlowers.value.map((flower) => ({
        id: flower.id,
        name: flower.product_name,
        price: flower.price,
        model_3d_url: flower.model_3d_url,
        position: flower.position,
      })),
      total_price: totalPrice.value,
      paper_color: paperColor.value,
      ribbon_color: ribbonColor.value,
    });
    if (response.data?.success) {
      showToast("Custom bouquet submitted.", "success");
      router.push("/customer/orders");
    }
  } catch (error) {
    showToast(error?.response?.data?.message || "Failed to submit custom bouquet.", "error");
  } finally {
    isCheckingOut.value = false;
  }
}

function addCart() {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  showToast("Custom bouquet added to cart.", "success");
}

function takeScreenshot() {
  if (!renderer) return;
  renderer.render(scene, camera);
  const link = document.createElement("a");
  link.href = renderer.domElement.toDataURL("image/png");
  link.download = "flower-customizer.png";
  link.click();
}

function toggleLeftSidebar() {
  isLeftSidebarCollapsed.value = !isLeftSidebarCollapsed.value;
  queueResize();
}

function toggleRightSidebar() {
  isRightSidebarCollapsed.value = !isRightSidebarCollapsed.value;
  queueResize();
}

async function toggleFullscreen() {
  const stage = viewerContainer.value?.closest(".fc-stage");
  if (!stage) return;

  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    } else {
      await stage.requestFullscreen();
    }
  } catch (error) {
    console.error("Failed to toggle fullscreen:", error);
    showToast("Fullscreen is not available.", "error");
  }
}

function syncFullscreenState() {
  isFullscreen.value = !!document.fullscreenElement;
  queueResize();
}

function queueResize() {
  window.requestAnimationFrame(() => {
    handleResize();
  });
}

function animateScene() {
  animationFrameId = window.requestAnimationFrame(animateScene);
  controls?.update();
  renderer?.render(scene, camera);
}

function handleResize() {
  if (!viewerContainer.value || !camera || !renderer) return;
  camera.aspect = viewerContainer.value.clientWidth / viewerContainer.value.clientHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(viewerContainer.value.clientWidth, viewerContainer.value.clientHeight);
}

function cleanupScene() {
  window.cancelAnimationFrame(animationFrameId);
  controls?.dispose();
  renderer?.dispose();
  if (renderer?.domElement && viewerContainer.value?.contains(renderer.domElement)) {
    viewerContainer.value.removeChild(renderer.domElement);
  }
}

function loadGltf(url) {
  return new Promise((resolve, reject) => loader.load(url, resolve, undefined, reject));
}

async function loadFirstAvailableGltf(urls) {
  let lastError = null;
  for (const url of urls) {
    try {
      return await loadGltf(url);
    } catch (error) {
      lastError = error;
    }
  }
  throw lastError || new Error("Unable to load bouquet model.");
}

function showToast(message, type = "success") {
  toast.show = true;
  toast.message = message;
  toast.type = type;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.show = false;
  }, 2800);
}
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
* { box-sizing: border-box; font-family: "Poppins", sans-serif; }
.fc-page { min-height: 100vh; background: radial-gradient(circle at top left, rgba(255,255,255,.5), transparent 32%), linear-gradient(135deg, #f5efe5 0%, #e9ded0 100%); color: #2f241b; }
.fc-shell { padding: 96px 24px 24px; }
.fc-topbar { max-width: 1500px; margin: 0 auto 18px; display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; }
.fc-kicker { margin: 0 0 6px; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #9e7250; }
.fc-topbar h1 { margin: 0; font-size: 34px; line-height: 1.05; }
.fc-subtitle { margin: 10px 0 0; color: #6c5b4b; }
.fc-head-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
.fc-layout { max-width: 1500px; margin: 0 auto; display: grid; grid-template-columns: 320px minmax(0,1fr) 340px; gap: 18px; min-height: calc(100vh - 160px); }
.fc-layout.left-collapsed { grid-template-columns: minmax(0,1fr) 340px; }
.fc-layout.right-collapsed { grid-template-columns: 320px minmax(0,1fr); }
.fc-layout.left-collapsed.right-collapsed { grid-template-columns: minmax(0,1fr); }
.fc-panel, .fc-stage { min-height: 0; border-radius: 28px; }
.fc-panel { background: rgba(255,255,255,.88); border: 1px solid rgba(122,92,60,.12); box-shadow: 0 18px 50px rgba(91,64,41,.08); padding: 20px; display: flex; flex-direction: column; backdrop-filter: blur(16px); }
.fc-panel-head, .fc-row, .fc-summary, .fc-selection { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.fc-panel-head { margin-bottom: 14px; }
.fc-panel-head h2, .fc-card strong, .fc-selection strong { margin: 0; font-size: 18px; }
.fc-panel-head p, .fc-panel-head span, .fc-note { color: #7d6653; font-size: 13px; }
.fc-pill, .fc-tag { padding: 8px 12px; border-radius: 999px; background: rgba(140,98,62,.12); color: #7e5331; font-size: 12px; font-weight: 600; }
.fc-tag.ok { background: rgba(95,136,111,.14); color: #476653; }
.fc-input { width: 100%; border: 1px solid rgba(122,92,60,.16); border-radius: 14px; padding: 12px 14px; background: #fff; color: #2f241b; margin-bottom: 16px; }
.fc-list { display: grid; gap: 12px; overflow: auto; padding-right: 4px; }
.fc-card { border: 1px solid rgba(122,92,60,.12); border-radius: 18px; padding: 16px; background: linear-gradient(180deg, #fffdf8 0%, #f6efe7 100%); cursor: pointer; transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
.fc-card:hover:not(.disabled) { transform: translateY(-2px); border-color: #c58957; box-shadow: 0 16px 30px rgba(140,98,62,.12); }
.fc-card.active { border-color: #8b5e3c; box-shadow: 0 0 0 3px rgba(139,94,60,.12); }
.fc-card.disabled { opacity: .45; cursor: not-allowed; }
.fc-card.dragging { transform: scale(.98); }
.fc-card-media { width: 100%; aspect-ratio: 4 / 3; border-radius: 14px; overflow: hidden; margin-bottom: 12px; background: linear-gradient(135deg, rgba(243,233,220,.9), rgba(214,195,176,.8)); display: flex; align-items: center; justify-content: center; }
.fc-card-image { width: 100%; height: 100%; object-fit: cover; display: block; }
.fc-card-placeholder { font-size: 12px; font-weight: 600; letter-spacing: .04em; color: #8a6c52; text-transform: uppercase; }
.fc-tags { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0; }
.fc-stage { position: relative; overflow: hidden; background: radial-gradient(circle at top, rgba(255,255,255,.24), transparent 40%), linear-gradient(180deg, #b39d83 0%, #aa9278 100%); border: 1px solid rgba(122,92,60,.14); box-shadow: inset 0 1px 0 rgba(255,255,255,.22); }
.fc-stage.over { box-shadow: inset 0 0 0 2px rgba(255,255,255,.4), 0 0 0 4px rgba(197,137,87,.18); }
.fc-stage-actions { position: absolute; top: 18px; right: 18px; z-index: 3; display: flex; gap: 10px; }
.fc-stage .fc-viewer { width: 100%; height: 100%; min-height: 680px; cursor: grab; }
.fc-stage.dragging .fc-viewer { cursor: grabbing; }
.fc-stage:fullscreen { border-radius: 0; width: 100vw; height: 100vh; max-width: none; }
.fc-stage:fullscreen .fc-viewer { min-height: 100vh; }
.fc-stage:-webkit-full-screen { border-radius: 0; width: 100vw; height: 100vh; max-width: none; }
.fc-stage:-webkit-full-screen .fc-viewer { min-height: 100vh; }
.fc-overlay { position: absolute; left: 20px; right: 20px; pointer-events: none; display: flex; justify-content: center; }
.fc-top { top: 20px; }
.fc-center { inset: 0; align-items: center; }
.fc-bottom { bottom: 20px; }
.fc-status, .fc-modal { max-width: 460px; border-radius: 18px; padding: 14px 18px; background: rgba(255,255,255,.9); box-shadow: 0 16px 32px rgba(83,55,32,.12); backdrop-filter: blur(10px); text-align: center; }
.fc-status { display: grid; gap: 4px; }
.fc-status span, .fc-modal p { color: #6d5847; font-size: 13px; }
.fc-section { border: 1px solid rgba(122,92,60,.1); border-radius: 20px; padding: 16px; background: rgba(255,252,247,.86); }
.fc-side { gap: 16px; }
.fc-selection { padding: 12px 14px; border-radius: 16px; background: #fff; border: 1px solid rgba(122,92,60,.08); cursor: pointer; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
.fc-selection:hover { transform: translateY(-1px); border-color: rgba(139,94,60,.28); }
.fc-selection.active { border-color: #8b5e3c; box-shadow: 0 0 0 3px rgba(139,94,60,.12); }
.fc-selection p, .fc-card p { margin: 4px 0 0; color: #8a5b35; font-weight: 600; }
.fc-transform { display: grid; gap: 12px; }
.fc-transform-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #6c5a4c; }
.fc-range { width: 100%; accent-color: #8b5e3c; }
.fc-transform-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.fc-swatches { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.fc-swatch { width: 34px; height: 34px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; box-shadow: 0 8px 20px rgba(57,36,17,.14); }
.fc-swatch.active { border-color: #5e3f2a; }
.fc-summary { padding: 10px 0; font-size: 14px; border-bottom: 1px solid rgba(122,92,60,.08); }
.fc-summary.total { font-size: 18px; border-bottom: none; }
.fc-btn { border: none; border-radius: 14px; cursor: pointer; transition: transform .2s ease, opacity .2s ease; padding: 10px 16px; }
.fc-btn:disabled { opacity: .55; cursor: not-allowed; }
.fc-icon-btn { border: none; border-radius: 999px; background: rgba(255,255,255,.92); color: #5f4734; font-size: 12px; font-weight: 600; padding: 10px 14px; cursor: pointer; box-shadow: 0 10px 24px rgba(83,55,32,.12); transition: transform .2s ease, opacity .2s ease; }
.fc-icon-btn:disabled { opacity: .55; cursor: not-allowed; }
.fc-btn-primary { width: 100%; margin-top: 12px; padding: 14px 16px; background: #8b5e3c; color: #fff; font-weight: 600; }
.fc-btn-light { background: #fff; color: #60422c; box-shadow: 0 10px 30px rgba(90,60,35,.08); }
.fc-btn-danger { background: rgba(194,92,79,.12); color: #9b4338; }
.full { width: 100%; margin-top: 12px; }
.fc-empty { padding: 18px; border-radius: 18px; background: rgba(142,109,73,.08); color: #6c5a4c; font-size: 14px; line-height: 1.6; }
.fc-toast { position: fixed; right: 24px; bottom: 24px; padding: 14px 18px; border-radius: 16px; color: #fff; font-size: 14px; z-index: 40; box-shadow: 0 18px 30px rgba(63,42,26,.18); }
.fc-toast.success { background: #587b61; }
.fc-toast.error { background: #a5564d; }
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@media (max-width: 1220px) { .fc-layout { grid-template-columns: 280px minmax(0,1fr) 300px; } .fc-layout.left-collapsed { grid-template-columns: minmax(0,1fr) 300px; } .fc-layout.right-collapsed { grid-template-columns: 280px minmax(0,1fr); } }
@media (max-width: 980px) { .fc-shell { padding-top: 88px; } .fc-topbar, .fc-layout, .fc-layout.left-collapsed, .fc-layout.right-collapsed, .fc-layout.left-collapsed.right-collapsed { display: grid; grid-template-columns: 1fr; } .fc-viewer { min-height: 520px; } }
@media (max-width: 640px) { .fc-shell { padding-inline: 14px; } .fc-topbar h1 { font-size: 28px; } .fc-overlay { left: 12px; right: 12px; } }
</style>
