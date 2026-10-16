<template>
  <div class="me">
    <div
      ref="viewport"
      class="me-view"
      tabindex="0"
      role="application"
      aria-label="3D editor. Drag to orbit, G to move, R to rotate, S to scale, question mark for help."
      @pointerenter="hovered = true"
      @pointerleave="hovered = false"
      @contextmenu.prevent
    >
      <canvas ref="canvas"></canvas>

      <!-- Loading: skeleton + text, never a spinner -->
      <div v-if="loading" class="me-loading">
        <div class="bc-skeleton me-loading-sk"></div>
        <span>Loading your 3D model…</span>
      </div>
      <p v-if="loadError" class="me-error">{{ loadError }}</p>

      <!-- Toolbar -->
      <div class="me-toolbar" role="toolbar" aria-label="Transform tools">
        <button
          v-for="t in TOOLS"
          :key="t.id"
          type="button"
          class="me-tool"
          :class="{ on: mode === t.id }"
          :disabled="selected === 'light' && t.id !== 'translate'"
          :aria-pressed="mode === t.id"
          :aria-label="`${t.label} (${t.key})`"
          :data-tip="`${t.label} · ${t.key}\n${t.tip}`"
          @click="setMode(t.id)"
        >
          <span v-html="TOOL_ICONS[t.id]"></span>
        </button>
        <span class="me-sep"></span>
        <button
          v-for="a in AXES"
          :key="a.id"
          type="button"
          class="me-tool me-axis"
          :class="[a.id, { on: axisLock === a.id }]"
          :aria-pressed="axisLock === a.id"
          :aria-label="`Lock to ${a.id.toUpperCase()} axis (${a.id.toUpperCase()})`"
          :data-tip="`Lock to ${a.id.toUpperCase()} · ${a.id.toUpperCase()}\n${a.tip}`"
          @click="toggleAxis(a.id)"
        >{{ a.id.toUpperCase() }}</button>
        <span class="me-sep"></span>
        <button type="button" class="me-tool" :disabled="!undoStack.length" aria-label="Undo (Ctrl+Z)" data-tip="Undo · Ctrl+Z" @click="undo"><span v-html="TOOL_ICONS.undo"></span></button>
        <button type="button" class="me-tool" :disabled="!redoStack.length" aria-label="Redo (Ctrl+Shift+Z)" data-tip="Redo · Ctrl+Shift+Z" @click="redo"><span v-html="TOOL_ICONS.redo"></span></button>
        <button type="button" class="me-tool" aria-label="Reset all changes" data-tip="Reset · clear every change to the flower" @click="resetAll"><span v-html="TOOL_ICONS.reset"></span></button>
      </div>

      <!-- Views -->
      <div class="me-views" role="toolbar" aria-label="Views">
        <button v-for="v in VIEWS" :key="v.id" type="button" class="me-view-btn" :aria-label="`${v.label} view (${v.key})`" :data-tip="`${v.label} · ${v.key}\n${v.tip}`" @click="setView(v.id)">{{ v.label }}</button>
      </div>

      <!-- Status strip: what's selected + the main keys -->
      <div class="me-hint" aria-hidden="true">
        <b>{{ selected === "light" ? "Light" : "Flower" }}</b> · {{ modeLabel }}<template v-if="axisLock"> · {{ axisLock.toUpperCase() }} only</template>
        <span class="me-hint-keys">Drag: orbit · Shift+drag: pan · Wheel: zoom · G R S · ? help</span>
      </div>
    </div>

    <!-- Light & brightness -->
    <div class="me-sliders">
      <label class="me-slider" title="Makes the flower's own colors lighter or darker. Saved in the .glb file.">
        <span>Brightness <small>(saved)</small></span>
        <input v-model.number="brightness" type="range" min="0.4" max="2.5" step="0.05" aria-label="Flower brightness" @change="emitChange" />
        <output>{{ Math.round(brightness * 100) }}%</output>
      </label>
      <label class="me-slider" title="How strong the light is in this preview. Not saved: the shop lights models itself.">
        <span>Light strength <small>(preview)</small></span>
        <input v-model.number="lightStrength" type="range" min="0" max="4" step="0.1" aria-label="Light strength" />
        <output>{{ lightStrength.toFixed(1) }}</output>
      </label>
      <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" title="Select the light (the sun icon) so you can move it" @click="select('light')">Move the light</button>
    </div>

    <!-- How to use -->
    <details class="me-help" :open="helpOpen" @toggle="helpOpen = $event.target.open">
      <summary>How to use the 3D editor <small>(press ? in the view)</small></summary>
      <div class="me-help-grid">
        <section v-for="group in HELP" :key="group.title">
          <h4>{{ group.title }}</h4>
          <dl>
            <template v-for="[k, d] in group.rows" :key="k">
              <dt>{{ k }}</dt>
              <dd>{{ d }}</dd>
            </template>
          </dl>
        </section>
      </div>
    </details>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import { OrbitControls } from "three/examples/jsm/controls/OrbitControls.js";
import { TransformControls } from "three/examples/jsm/controls/TransformControls.js";
import { RoomEnvironment } from "three/examples/jsm/environments/RoomEnvironment.js";
import { cleanObject } from "./meshCleanup";
import { bakeEdits } from "./bakeEdits";
import { exportGlb } from "./matchedModel";
import { HELP, TOOLS, VIEWS } from "./editorShortcuts";

const props = defineProps({
  modelUrl: { type: String, required: true },
  // Changes when it's a different model (new photo / new AI result): edits reset
  resetKey: { type: [String, Number], default: 0 },
  dark: { type: Boolean, default: false },
  // Remove stray fragments (AI models); BloomCraft's own builds are clean already
  clean: { type: Boolean, default: true },
});
const emit = defineEmits(["cleaned", "change"]);

const icon = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
const TOOL_ICONS = {
  translate: icon('<path d="M12 2v20M2 12h20M12 2l-3 3M12 2l3 3M12 22l-3-3M12 22l3-3M2 12l3-3M2 12l3 3M22 12l-3-3M22 12l-3 3"/>'),
  rotate: icon('<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/>'),
  scale: icon('<path d="M4 20 20 4M14 4h6v6M10 20H4v-6"/>'),
  undo: icon('<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>'),
  redo: icon('<path d="m15 14 5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/>'),
  reset: icon('<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>'),
};
const AXES = [
  { id: "x", tip: "Red: left–right." },
  { id: "y", tip: "Green: up–down (in Blender this would be Z)." },
  { id: "z", tip: "Blue: front–back." },
];

const canvas = ref(null);
const viewport = ref(null);
const loading = ref(true);
const loadError = ref("");
const hovered = ref(false);
const helpOpen = ref(false);
const mode = ref("rotate");
const axisLock = ref(null);
const selected = ref("model");
const brightness = ref(1);
const lightStrength = ref(2.2);
const undoStack = ref([]);
const redoStack = ref([]);

const modeLabel = computed(() => TOOLS.find((t) => t.id === mode.value)?.label ?? "");

let renderer, scene, camera, orbit, gizmo, pivot, model, sun, sunHandle, grid, floor, envTex, resizeObs, frameId;
let disposed = false;
const loader = new GLTFLoader();
const materialBase = new Map(); // material -> its original color, for the brightness preview
const HOME = { pos: new THREE.Vector3(2.6, 1.9, 3.4), target: new THREE.Vector3(0, 0.75, 0) };

// ── Scene ──────────────────────────────────────────────────────────────
function themeColors() {
  return props.dark
    ? { bg: "#1b222c", grid: 0x3a4555, gridMinor: 0x262e3a }
    : { bg: "#eef1f5", grid: 0xb8c0cc, gridMinor: 0xdde2e8 };
}

function buildGrid() {
  if (grid) scene.remove(grid);
  const c = themeColors();
  grid = new THREE.Group();
  const minor = new THREE.GridHelper(10, 40, c.gridMinor, c.gridMinor);
  const major = new THREE.GridHelper(10, 10, c.grid, c.grid);
  minor.position.y = major.position.y = 0.001;
  grid.add(minor, major);
  // Blender's red X and green (here Y-up, so blue Z) axis lines on the floor
  const line = (a, b, color) => new THREE.Line(new THREE.BufferGeometry().setFromPoints([a, b]), new THREE.LineBasicMaterial({ color }));
  grid.add(line(new THREE.Vector3(-5, 0.002, 0), new THREE.Vector3(5, 0.002, 0), 0xe5484d));
  grid.add(line(new THREE.Vector3(0, 0.002, -5), new THREE.Vector3(0, 0.002, 5), 0x3b82f6));
  scene.add(grid);
  scene.background = new THREE.Color(c.bg);
}

function setupScene() {
  renderer = new THREE.WebGLRenderer({ canvas: canvas.value, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.1;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  envTex = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environment = envTex;
  scene.environmentIntensity = 0.55;
  pmrem.dispose();

  camera = new THREE.PerspectiveCamera(40, 1, 0.01, 100);
  camera.position.copy(HOME.pos);

  scene.add(new THREE.HemisphereLight(0xffffff, 0x8a8f99, 0.6));
  sun = new THREE.DirectionalLight(0xffffff, lightStrength.value);
  sun.position.set(2, 3.5, 2.5);
  sun.castShadow = true;
  sun.shadow.mapSize.set(1024, 1024);
  sun.shadow.camera.left = sun.shadow.camera.bottom = -2;
  sun.shadow.camera.right = sun.shadow.camera.top = 2;
  sun.shadow.bias = -0.0005;
  scene.add(sun, sun.target);

  // The light's handle: a small sun you can click and drag, like a light in Blender
  sunHandle = new THREE.Mesh(new THREE.SphereGeometry(0.09, 16, 12), new THREE.MeshBasicMaterial({ color: 0xffc94d }));
  sunHandle.name = "LightHandle";
  sunHandle.position.copy(sun.position);
  const rays = new THREE.LineSegments(
    new THREE.BufferGeometry().setFromPoints(
      Array.from({ length: 8 }, (_, i) => {
        const a = (i / 8) * Math.PI * 2;
        return [new THREE.Vector3(Math.cos(a) * 0.13, Math.sin(a) * 0.13, 0), new THREE.Vector3(Math.cos(a) * 0.2, Math.sin(a) * 0.2, 0)];
      }).flat(),
    ),
    new THREE.LineBasicMaterial({ color: 0xffc94d }),
  );
  sunHandle.add(rays);
  scene.add(sunHandle);

  floor = new THREE.Mesh(new THREE.PlaneGeometry(20, 20), new THREE.ShadowMaterial({ opacity: 0.25 }));
  floor.rotation.x = -Math.PI / 2;
  floor.receiveShadow = true;
  scene.add(floor);
  buildGrid();

  // Blender-like navigation: left/middle drag orbits (Shift pans), right drag pans, wheel zooms
  orbit = new OrbitControls(camera, renderer.domElement);
  orbit.target.copy(HOME.target);
  orbit.enableDamping = true;
  orbit.dampingFactor = 0.12;
  orbit.mouseButtons = { LEFT: THREE.MOUSE.ROTATE, MIDDLE: THREE.MOUSE.ROTATE, RIGHT: THREE.MOUSE.PAN };
  orbit.minDistance = 0.4;
  orbit.maxDistance = 20;

  gizmo = new TransformControls(camera, renderer.domElement);
  gizmo.setMode(mode.value);
  gizmo.setSize(0.9);
  gizmo.addEventListener("dragging-changed", (e) => {
    orbit.enabled = !e.value;
    if (e.value) pushUndo();
    else afterTransform();
  });
  gizmo.addEventListener("objectChange", () => {
    if (gizmo.object === sunHandle) sun.position.copy(sunHandle.position);
  });
  scene.add(gizmo.getHelper());

  pivot = new THREE.Group();
  pivot.name = "Pivot";
  scene.add(pivot);
}

// ── Model ──────────────────────────────────────────────────────────────
function disposeObject(obj) {
  obj?.traverse((c) => {
    if (!c.isMesh) return;
    c.geometry?.dispose();
    [].concat(c.material).forEach((m) => {
      m?.map?.dispose();
      m?.dispose();
    });
  });
}

async function loadModel(url, keepEdits) {
  loading.value = true;
  loadError.value = "";
  let gltf;
  try {
    gltf = await loader.loadAsync(url);
  } catch (_) {
    if (!disposed) {
      loadError.value = "Couldn't show this 3D model. Try downloading it instead.";
      loading.value = false;
    }
    return;
  }
  if (disposed) return;
  const next = gltf.scene;
  const removed = props.clean ? cleanObject(next) : 0;
  emit("cleaned", removed);

  // Normalize: about 1.5 tall-ish, centred on the pivot (which sits on the floor)
  const box = new THREE.Box3().setFromObject(next);
  const size = box.getSize(new THREE.Vector3());
  const k = 1.5 / Math.max(size.x, size.y, size.z, 1e-6);
  next.scale.multiplyScalar(k);
  const center = box.getCenter(new THREE.Vector3()).multiplyScalar(k);
  next.position.sub(center);

  materialBase.clear();
  next.traverse((c) => {
    if (!c.isMesh) return;
    c.castShadow = true;
    c.receiveShadow = true;
    [].concat(c.material).forEach((m) => {
      if (!m) return;
      m.side = THREE.DoubleSide;
      if (m.color && !materialBase.has(m)) materialBase.set(m, m.color.clone());
    });
  });

  if (model) {
    pivot.remove(model);
    disposeObject(model);
  }
  model = next;
  pivot.add(model);
  if (!keepEdits) {
    pivot.position.set(0, 0, 0);
    pivot.rotation.set(0, 0, 0);
    pivot.scale.set(1, 1, 1);
    brightness.value = 1;
    undoStack.value = [];
    redoStack.value = [];
  }
  applyBrightness();
  snapToFloor();
  select(selected.value === "light" ? "light" : "model");
  if (!keepEdits) setView("home");
  loading.value = false;
}

function snapToFloor() {
  if (!model) return;
  pivot.updateMatrixWorld(true);
  const box = new THREE.Box3().setFromObject(model);
  if (Number.isFinite(box.min.y)) pivot.position.y -= box.min.y;
}

function afterTransform() {
  if (gizmo.object === pivot) snapToFloor();
  emitChange();
}

const emitChange = () => emit("change");

function applyBrightness() {
  const k = brightness.value;
  for (const [m, base] of materialBase) m.color.copy(base).multiplyScalar(k);
}
watch(brightness, applyBrightness);
watch(lightStrength, (v) => sun && (sun.intensity = v));

// ── Selection, modes, axes ─────────────────────────────────────────────
function select(which) {
  selected.value = which;
  if (!gizmo) return;
  if (which === "light") {
    gizmo.attach(sunHandle);
    setMode("translate");
  } else if (pivot) {
    gizmo.attach(pivot);
  }
}

function setMode(m) {
  if (selected.value === "light" && m !== "translate") return;
  mode.value = m;
  gizmo?.setMode(m);
  // Scale handles read best in the object's own frame, like Blender's
  gizmo?.setSpace(m === "scale" ? "local" : "world");
}

function toggleAxis(a) {
  axisLock.value = axisLock.value === a ? null : a;
  if (!gizmo) return;
  gizmo.showX = !axisLock.value || axisLock.value === "x";
  gizmo.showY = !axisLock.value || axisLock.value === "y";
  gizmo.showZ = !axisLock.value || axisLock.value === "z";
}

// ── Undo / reset ───────────────────────────────────────────────────────
const snapshot = () => ({ p: pivot.position.clone(), q: pivot.quaternion.clone(), s: pivot.scale.clone() });
function restore(s) {
  pivot.position.copy(s.p);
  pivot.quaternion.copy(s.q);
  pivot.scale.copy(s.s);
  snapToFloor();
  emitChange();
}
function pushUndo() {
  if (gizmo.object !== pivot) return;
  undoStack.value = [...undoStack.value.slice(-49), snapshot()];
  redoStack.value = [];
}
function undo() {
  const s = undoStack.value.at(-1);
  if (!s) return;
  redoStack.value = [...redoStack.value, snapshot()];
  undoStack.value = undoStack.value.slice(0, -1);
  restore(s);
}
function redo() {
  const s = redoStack.value.at(-1);
  if (!s) return;
  undoStack.value = [...undoStack.value, snapshot()];
  redoStack.value = redoStack.value.slice(0, -1);
  restore(s);
}
function clearPart(part) {
  if (gizmo.object !== pivot) return;
  pushUndoManual();
  if (part === "translate") pivot.position.set(0, 0, 0);
  if (part === "rotate") pivot.rotation.set(0, 0, 0);
  if (part === "scale") pivot.scale.set(1, 1, 1);
  snapToFloor();
  emitChange();
}
function pushUndoManual() {
  undoStack.value = [...undoStack.value.slice(-49), snapshot()];
  redoStack.value = [];
}
function resetAll() {
  pushUndoManual();
  pivot.position.set(0, 0, 0);
  pivot.rotation.set(0, 0, 0);
  pivot.scale.set(1, 1, 1);
  brightness.value = 1;
  snapToFloor();
  emitChange();
}

// ── Views ──────────────────────────────────────────────────────────────
function frame(target = selected.value === "light" ? sunHandle : model) {
  if (!target) return;
  const box = new THREE.Box3().setFromObject(target);
  const sphere = box.getBoundingSphere(new THREE.Sphere());
  const r = Math.max(sphere.radius, 0.25);
  const dist = r / Math.sin(THREE.MathUtils.degToRad(camera.fov / 2)) * 1.15;
  const dir = camera.position.clone().sub(orbit.target).normalize();
  orbit.target.copy(sphere.center);
  camera.position.copy(sphere.center).addScaledVector(dir, dist);
  orbit.update();
}

function setView(id, opposite = false) {
  if (!camera) return;
  const s = opposite ? -1 : 1;
  const dirs = {
    front: new THREE.Vector3(0, 0, 1 * s),
    right: new THREE.Vector3(1 * s, 0, 0),
    top: new THREE.Vector3(0, 1 * s, 0.0001),
    home: HOME.pos.clone().sub(HOME.target).normalize(),
  };
  if (id === "frame") return frame();
  const d = dirs[id];
  if (!d) return;
  const center = model ? new THREE.Box3().setFromObject(model).getCenter(new THREE.Vector3()) : HOME.target.clone();
  orbit.target.copy(center);
  camera.position.copy(center).addScaledVector(d.normalize(), 4);
  orbit.update();
  if (model) frame(model);
}

// ── Keyboard (only while the pointer is over the view or it has focus) ──
function onKey(e) {
  const t = e.target;
  if (t && /^(input|textarea|select)$/i.test(t.tagName)) return;
  const focused = viewport.value?.contains(document.activeElement);
  if (!hovered.value && !focused) return;

  const key = e.key.toLowerCase();
  const isNum = (n) => e.code === `Numpad${n}` || e.code === `Digit${n}`;

  if (e.key === "?" || e.code === "F1") {
    helpOpen.value = !helpOpen.value;
  } else if (e.key === "Escape") {
    if (gizmo.dragging) cancelDrag();
    else if (axisLock.value) toggleAxis(axisLock.value);
  } else if ((e.ctrlKey || e.metaKey) && key === "z") {
    e.shiftKey ? redo() : undo();
  } else if ((e.ctrlKey || e.metaKey) && key === "y") {
    redo();
  } else if (e.altKey && ["g", "r", "s"].includes(key)) {
    clearPart({ g: "translate", r: "rotate", s: "scale" }[key]);
  } else if (key === "g") setMode("translate");
  else if (key === "r") setMode("rotate");
  else if (key === "s" && !e.ctrlKey) setMode("scale");
  else if (["x", "y", "z"].includes(key) && !e.ctrlKey) toggleAxis(key);
  else if (isNum(1)) setView("front", e.ctrlKey);
  else if (isNum(3)) setView("right", e.ctrlKey);
  else if (isNum(7)) setView("top", e.ctrlKey);
  else if (isNum(0)) setView("home");
  else if (e.key === "Home" || e.code === "NumpadDecimal") frame();
  else return;
  e.preventDefault();
}

// Cancel a drag like Blender: back to where it started, drag over, no undo step
function cancelDrag() {
  gizmo.reset();
  gizmo.domElement.removeEventListener("pointermove", gizmo._onPointerMove);
  gizmo.pointerUp({ button: 0 });
  undoStack.value = undoStack.value.slice(0, -1);
}

// Right-click while dragging cancels, like Blender
function onPointerDownCapture(e) {
  if (e.button === 2 && gizmo?.dragging) {
    cancelDrag();
    e.stopPropagation();
  }
}

// Click (not drag) on the flower or the sun selects it
let downAt = null;
function onPointerDown(e) {
  downAt = { x: e.clientX, y: e.clientY };
  viewport.value?.focus({ preventScroll: true });
}
function onPointerUp(e) {
  if (!downAt || gizmo.dragging) return;
  const moved = Math.hypot(e.clientX - downAt.x, e.clientY - downAt.y);
  downAt = null;
  if (moved > 4 || e.button !== 0) return;
  const rect = renderer.domElement.getBoundingClientRect();
  const ndc = new THREE.Vector2(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1);
  const ray = new THREE.Raycaster();
  ray.setFromCamera(ndc, camera);
  if (ray.intersectObject(sunHandle, true).length) select("light");
  else if (model && ray.intersectObject(model, true).length) select("model");
}

// ── Lifecycle ──────────────────────────────────────────────────────────
function resize() {
  const el = viewport.value;
  if (!el || !renderer) return;
  const w = el.clientWidth, h = el.clientHeight;
  renderer.setSize(w, h, false);
  camera.aspect = w / Math.max(h, 1);
  camera.updateProjectionMatrix();
}

function loop() {
  frameId = requestAnimationFrame(loop);
  orbit.update();
  renderer.render(scene, camera);
}

onMounted(() => {
  setupScene();
  resize();
  resizeObs = new ResizeObserver(resize);
  resizeObs.observe(viewport.value);
  const el = renderer.domElement;
  el.addEventListener("pointerdown", onPointerDownCapture, true);
  el.addEventListener("pointerdown", onPointerDown);
  el.addEventListener("pointerup", onPointerUp);
  window.addEventListener("keydown", onKey);
  loop();
  loadModel(props.modelUrl, false);
});

watch(() => props.modelUrl, (url, old) => url && url !== old && loadModel(url, true));
watch(() => props.resetKey, () => props.modelUrl && loadModel(props.modelUrl, false));
watch(() => props.dark, () => scene && buildGrid());

onBeforeUnmount(() => {
  disposed = true;
  cancelAnimationFrame(frameId);
  window.removeEventListener("keydown", onKey);
  resizeObs?.disconnect();
  gizmo?.detach();
  gizmo?.dispose();
  orbit?.dispose();
  disposeObject(model);
  envTex?.dispose();
  renderer?.dispose();
});

/** The edited model as a .glb Blob: rotation, size and brightness are saved */
async function exportEdited() {
  if (!model) throw new Error("No model loaded");
  // Brightness is baked into the colors, so export from the original colors
  for (const [m, base] of materialBase) m.color.copy(base);
  try {
    const edited = bakeEdits(model, { rotation: pivot.rotation.clone(), scale: pivot.scale.clone(), brightness: brightness.value });
    return await exportGlb(edited);
  } finally {
    applyBrightness();
  }
}

defineExpose({ exportEdited });
</script>

<style scoped>
.me {
  display: grid;
  gap: 10px;
}
.me-view {
  position: relative;
  height: 460px;
  overflow: hidden;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  outline: none;
  background: var(--v-subtle);
}
.me-view:focus-visible {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 35%, transparent);
}
.me-view canvas {
  display: block;
  width: 100%;
  height: 100%;
  touch-action: none;
}
.me-loading {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 10px;
  background: var(--v-surface);
  color: var(--v-muted);
  font-size: 13px;
}
.me-loading-sk {
  width: 70%;
  height: 260px;
  border-radius: 14px;
}
.me-error {
  position: absolute;
  inset: auto 12px 12px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-red-soft);
  color: var(--v-red-text);
  font-size: 12.5px;
}

/* Toolbars float over the view, Blender-style */
.me-toolbar,
.me-views {
  position: absolute;
  display: flex;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-glass);
  backdrop-filter: blur(6px);
}
.me-toolbar {
  top: 10px;
  left: 10px;
  flex-direction: column;
}
.me-views {
  top: 10px;
  right: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
  max-width: 60%;
}
.me-sep {
  height: 1px;
  margin: 2px 4px;
  background: var(--v-border);
}
.me-tool,
.me-view-btn {
  position: relative;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-text-2);
  font: inherit;
  cursor: pointer;
}
.me-tool {
  width: 34px;
  height: 34px;
}
.me-tool :deep(svg) {
  width: 18px;
  height: 18px;
}
.me-view-btn {
  padding: 6px 9px;
  font-size: 12px;
  font-weight: 500;
}
.me-tool:hover:not(:disabled),
.me-view-btn:hover {
  background: var(--v-subtle-2);
  color: var(--v-text);
}
.me-tool.on {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.me-tool:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
.me-axis {
  font-size: 12px;
  font-weight: 700;
}
.me-axis.x { color: #e5484d; }
.me-axis.y { color: #30a46c; }
.me-axis.z { color: #3b82f6; }
.me-axis.on.x { background: #e5484d; color: #fff; }
.me-axis.on.y { background: #30a46c; color: #fff; }
.me-axis.on.z { background: #3b82f6; color: #fff; }

/* Tooltips: name · shortcut, then what it does */
[data-tip]::after {
  content: attr(data-tip);
  position: absolute;
  z-index: 5;
  width: max-content;
  max-width: 240px;
  padding: 7px 9px;
  border-radius: 8px;
  background: #1a202c;
  color: #f7fafc;
  font-size: 11.5px;
  font-weight: 400;
  line-height: 1.45;
  text-align: left;
  white-space: pre-line;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.12s 0.25s;
}
[data-tip]:hover::after,
[data-tip]:focus-visible::after {
  opacity: 1;
}
.me-toolbar [data-tip]::after {
  left: calc(100% + 8px);
  top: 50%;
  transform: translateY(-50%);
}
.me-views [data-tip]::after {
  right: 0;
  top: calc(100% + 8px);
}

.me-hint {
  position: absolute;
  left: 10px;
  right: 10px;
  bottom: 10px;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 4px 12px;
  padding: 6px 10px;
  border-radius: 10px;
  background: var(--v-glass);
  color: var(--v-text-2);
  font-size: 11.5px;
  pointer-events: none;
}
.me-hint b {
  color: var(--v-text);
}
.me-hint-keys {
  color: var(--v-muted);
}

.me-sliders {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 18px;
}
.me-slider {
  display: grid;
  grid-template-columns: auto 140px 44px;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.me-slider small {
  color: var(--v-muted);
}
.me-slider input {
  accent-color: var(--bc-accent);
}
.me-slider output {
  color: var(--v-muted);
  font-variant-numeric: tabular-nums;
}

.me-help {
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle);
}
.me-help summary {
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  cursor: pointer;
}
.me-help summary small {
  font-weight: 400;
  color: var(--v-muted);
}
.me-help-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 20px;
  padding: 0 14px 14px;
}
.me-help h4 {
  margin: 0 0 6px;
  font-size: 12.5px;
  color: var(--v-text);
}
.me-help dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 4px 10px;
  margin: 0;
  font-size: 12px;
}
.me-help dt {
  color: var(--v-text-2);
  font-weight: 600;
}
.me-help dd {
  margin: 0;
  color: var(--v-muted);
}

@media (max-width: 900px) {
  .me-view {
    height: 380px;
  }
  .me-help-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .me-hint-keys {
    display: none;
  }
}
</style>
