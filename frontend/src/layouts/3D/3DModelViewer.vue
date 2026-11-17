<template>
  <div class="model-viewer-container">
    <div v-if="isLoading" class="model-loading" role="status">
      <span class="bc-skeleton model-skel" aria-hidden="true"></span>
      <p>Loading the 3D model…</p>
    </div>

    <div v-if="error" class="model-error" role="alert">
      <svg class="error-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></svg>
      <p>{{ error }}</p>
    </div>

    <div ref="viewerContainer" class="viewer-canvas"></div>

    <div class="viewer-controls" role="toolbar" aria-label="3D view controls">
      <button type="button" class="control-btn" title="Reset view" aria-label="Reset view" @click="resetCamera">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /></svg>
      </button>
      <button
        type="button"
        class="control-btn"
        :class="{ active: isRotating }"
        :title="isRotating ? 'Stop turning' : 'Turn it slowly'"
        :aria-label="isRotating ? 'Stop turning' : 'Turn it slowly'"
        :aria-pressed="isRotating"
        @click="toggleRotation"
      >
        <svg v-if="isRotating" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1" /><rect x="14" y="5" width="4" height="14" rx="1" /></svg>
        <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5Z" /></svg>
      </button>
      <button
        type="button"
        class="control-btn"
        :class="{ active: showWireframe }"
        title="Show the wire frame"
        aria-label="Show the wire frame"
        :aria-pressed="showWireframe"
        @click="toggleWireframe"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16V8l-9-5-9 5v8l9 5 9-5Z" /><path d="m3.3 7 8.7 5 8.7-5M12 22V12" /></svg>
      </button>
      <button
        type="button"
        class="control-btn"
        :title="isFullscreen ? 'Exit full screen' : 'Full screen'"
        :aria-label="isFullscreen ? 'Exit full screen' : 'Full screen'"
        @click="toggleFullscreen"
      >
        <svg v-if="isFullscreen" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3v4a2 2 0 0 1-2 2H3M21 9h-4a2 2 0 0 1-2-2V3M15 21v-4a2 2 0 0 1 2-2h4M3 15h4a2 2 0 0 1 2 2v4" /></svg>
        <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3" /></svg>
      </button>
    </div>

    <div class="viewer-instructions">
      <p>Drag to turn · scroll to zoom</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import * as THREE from "three";
import * as SkeletonUtils from "three/examples/jsm/utils/SkeletonUtils.js";
import { LEGACY_DESIGN, createBouquetBase } from "../../utils/bouquetDesigns/index.js";
import { applyWrapPattern } from "../../utils/wrapperPatterns.js";
import { buildFillerBunch } from "../../utils/fillerBunch.js";
import { buildFillerSprigs } from "../../utils/fillerLayout.js";
import { createCard } from "../../utils/bouquetCard.js";

const props = defineProps({
  modelUrl: {
    type: String,
    default: "",
  },
  modelType: {
    type: String,
    default: "glb",
  },
  backgroundColor: {
    type: String,
    default: "#f8f9fa",
  },
  arrangement: {
    type: Object,
    default: null,
  },
});

const viewerContainer = ref(null);
const isLoading = ref(true);
const error = ref(null);
const isRotating = ref(true);
const showWireframe = ref(false);
const isFullscreen = ref(false);
const rotationX = ref(0);
const rotationY = ref(0);

let scene, camera, renderer, model;
let animationFrameId;
let isDragging = false;
let previousMousePosition = { x: 0, y: 0 };
let originalModelRotation = { x: 0, y: 0, z: 0 };

onMounted(() => {
  initViewer();
});

onUnmounted(() => {
  cleanup();
});

watch(
  () => props.modelUrl,
  () => {
    reloadModel();
  },
);

watch(
  () => props.arrangement,
  () => {
    reloadModel();
  },
  { deep: true },
);

watch(
  () => props.backgroundColor,
  () => {
    if (scene) {
      scene.background = new THREE.Color(props.backgroundColor);
    }
  },
);

const initViewer = async () => {
  if (!viewerContainer.value) return;

  try {
    // Scene setup
    scene = new THREE.Scene();
    scene.background = new THREE.Color(props.backgroundColor);
    scene.fog = new THREE.Fog(0xf8f9fa, 10, 25);

    // Camera setup
    const aspect =
      viewerContainer.value.clientWidth / viewerContainer.value.clientHeight;
    camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 1000);
    camera.position.set(0, 1, 5);

    // Renderer setup
    renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: true,
      powerPreference: "high-performance",
    });
    renderer.setSize(
      viewerContainer.value.clientWidth,
      viewerContainer.value.clientHeight,
    );
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    viewerContainer.value.appendChild(renderer.domElement);

    // Enhanced Lighting
    setupLighting();

    // Add environment
    setupEnvironment();

    // Setup controls
    setupMouseControls();

    // Load model
    await loadModel();

    // Start animation
    animate();

    // Handle window resize
    window.addEventListener("resize", handleResize);
    document.addEventListener("fullscreenchange", syncFullscreen);
  } catch (err) {
    console.error("Error initializing viewer:", err);
    error.value = "Failed to initialize 3D viewer";
    isLoading.value = false;
  }
};

const setupLighting = () => {
  // Ambient light
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
  scene.add(ambientLight);

  // Main directional light (sun)
  const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
  directionalLight.position.set(5, 10, 7.5);
  directionalLight.castShadow = true;
  directionalLight.shadow.mapSize.width = 1024;
  directionalLight.shadow.mapSize.height = 1024;
  directionalLight.shadow.camera.near = 0.5;
  directionalLight.shadow.camera.far = 50;
  scene.add(directionalLight);

  // Fill light (hemisphere)
  const fillLight = new THREE.HemisphereLight(0xffffff, 0x444444, 0.3);
  scene.add(fillLight);

  // Rim light for better definition
  const rimLight = new THREE.DirectionalLight(0xffffff, 0.4);
  rimLight.position.set(-5, 5, -5);
  scene.add(rimLight);
};

const setupEnvironment = () => {
  // Add a ground plane
  const groundGeometry = new THREE.CircleGeometry(5, 32);
  const groundMaterial = new THREE.MeshStandardMaterial({
    color: 0xffffff,
    roughness: 0.8,
    metalness: 0.2,
    side: THREE.DoubleSide,
  });
  const ground = new THREE.Mesh(groundGeometry, groundMaterial);
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -0.5;
  ground.receiveShadow = true;
  scene.add(ground);

  // Add subtle grid helper
  const gridHelper = new THREE.GridHelper(10, 20, 0x000000, 0x000000);
  gridHelper.material.opacity = 0.1;
  gridHelper.material.transparent = true;
  gridHelper.position.y = -0.49;
  scene.add(gridHelper);
};

const loadModel = async () => {
  try {
    isLoading.value = true;
    error.value = null;

    if (props.arrangement?.type === "custom_flower_bouquet") {
      await loadBouquetArrangement(props.arrangement);
      return;
    }

    if (!props.modelUrl) {
      throw new Error("No 3D model is available for this item.");
    }

    let loader;

    if (props.modelType === "glb" || props.modelType === "gltf") {
      // Dynamically import GLTFLoader
      const { GLTFLoader } =
        await import("three/examples/jsm/loaders/GLTFLoader.js");
      loader = new GLTFLoader();
    } else if (props.modelType === "fbx") {
      const { FBXLoader } =
        await import("three/examples/jsm/loaders/FBXLoader.js");
      loader = new FBXLoader();
    } else if (props.modelType === "obj") {
      const { OBJLoader } =
        await import("three/examples/jsm/loaders/OBJLoader.js");
      loader = new OBJLoader();
    } else {
      throw new Error(`Unsupported model type: ${props.modelType}`);
    }

    loader.load(
      props.modelUrl,
      (loadedModel) => {
        // Handle different loader responses
        if (props.modelType === "glb" || props.modelType === "gltf") {
          model = loadedModel.scene;
        } else {
          model = loadedModel;
        }

        model.traverse((child) => {
          if (child.isMesh) {
            child.castShadow = true;
            child.receiveShadow = true;

            if (child.material) {
              if (Array.isArray(child.material)) {
                child.material.forEach((mat) => {
                  mat.side = THREE.DoubleSide;
                });
              } else {
                child.material.side = THREE.DoubleSide;
              }
            }
          }
        });

        centerAndScaleModel();

        originalModelRotation = {
          x: model.rotation.x,
          y: model.rotation.y,
          z: model.rotation.z,
        };

        scene.add(model);
        isLoading.value = false;

        // Simple entrance animation without GSAP
        animateEntrance();
      },
      (progress) => {
        // Optional: Add progress bar UI
        const percentComplete = progress.total
          ? (progress.loaded / progress.total) * 100
          : 0;
        console.log(`Loading: ${percentComplete.toFixed(1)}%`);
      },
      (err) => {
        console.error("Error loading model:", err);
        error.value = "Failed to load 3D model. Please check the file URL.";
        isLoading.value = false;

        // Load a fallback flower model
        createFallbackFlower();
      },
    );
  } catch (err) {
    console.error("Error in loadModel:", err);
    error.value = err.message || "Failed to load model";
    isLoading.value = false;
    createFallbackFlower();
  }
};

const loadGltf = async (url) => {
  const { GLTFLoader } = await import(
    "three/examples/jsm/loaders/GLTFLoader.js"
  );
  const loader = new GLTFLoader();

  return new Promise((resolve, reject) => loader.load(url, resolve, undefined, reject));
};

const prepareModel = (object) => {
  object.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
    const materials = Array.isArray(child.material) ? child.material : [child.material];
    materials.forEach((material) => {
      if (material) material.side = THREE.DoubleSide;
    });
  });
};

const fitFlowerModel = (flowerModel) => {
  const sourceBox = new THREE.Box3().setFromObject(flowerModel);
  const size = sourceBox.getSize(new THREE.Vector3());
  const scale = 0.9 / Math.max(size.x, size.y, size.z, 1);
  flowerModel.scale.setScalar(scale);
  flowerModel.updateMatrixWorld(true);
  const center = new THREE.Box3().setFromObject(flowerModel).getCenter(
    new THREE.Vector3(),
  );
  flowerModel.position.sub(center);
  flowerModel.updateMatrixWorld(true);
};

const loadBouquetArrangement = async (arrangement) => {
  // The same base the customizer built: the design saved with the bouquet
  // (older ones saved none and keep the original model, fillers and all),
  // sized by its wrapper scale.
  const base = await createBouquetBase(arrangement.design || LEGACY_DESIGN, {
    loadGltf,
    wrapperScale: Number(arrangement.wrapper_scale ?? 1),
    modelUrl: arrangement.design_model_url || null,
  });
  const bouquet = base.root;
  prepareModel(bouquet);

  // Tint the two colour layers (empty = the design's own) and tie the ribbon.
  const dressing = base.dressing;
  dressing.outer.apply(arrangement.paper_color || null);
  dressing.inner.apply(arrangement.inner_paper_color || null);
  // Newspaper or words printed on each paper
  await Promise.all([
    applyWrapPattern(dressing.outer, arrangement.wrapper_pattern, arrangement.paper_color || null),
    applyWrapPattern(dressing.inner, arrangement.inner_wrapper_pattern, arrangement.inner_paper_color || null),
  ]);
  dressing.applyRibbon(arrangement.ribbon_color || null);

  model = new THREE.Group();
  model.add(bouquet);

  const byOrder = (first, second) => Number(first.placement_order || 0) - Number(second.placement_order || 0);
  // Fillers show as the same bunches of sprigs the customizer drew
  const items = [
    ...[...(arrangement.flowers || [])].sort(byOrder),
    ...[...(arrangement.fillers || [])].sort(byOrder).map((filler) => ({ ...filler, filler: true })),
  ];

  for (const flower of items) {
    if (!flower.model_3d_url) continue;
    const flowerResult = await loadGltf(flower.model_3d_url);
    const template = flowerResult.scene || flowerResult.scenes?.[0];
    if (!template) continue;

    const fitted = () => {
      const copy = SkeletonUtils.clone(template);
      prepareModel(copy);
      fitFlowerModel(copy);
      return copy;
    };
    const copy = () => {
      const raw = SkeletonUtils.clone(template);
      prepareModel(raw);
      return raw;
    };
    const placement = new THREE.Group();
    // Fillers: their pieces on the original bouquet's spots, or (saved before
    // those) the bunch the customizer drew then
    placement.add(
      flower.filler && flower.sprigs?.length
        ? buildFillerSprigs(template, flower.sprigs, copy)
        : flower.filler
          ? buildFillerBunch(fitted, flower.scene_id)
          : fitted(),
    );
    placement.position.set(
      Number(flower.position?.x || 0),
      Number(flower.position?.y || 0),
      Number(flower.position?.z || 0),
    );
    placement.rotation.set(
      THREE.MathUtils.degToRad(Number(flower.rotation?.xDeg || 0)),
      THREE.MathUtils.degToRad(Number(flower.rotation?.yDeg || 0)),
      THREE.MathUtils.degToRad(Number(flower.rotation?.zDeg || 0)),
    );
    const savedScale = typeof flower.scale === "number"
      ? flower.scale
      : Number(flower.scale?.x ?? 1);
    placement.scale.setScalar(savedScale);
    model.add(placement);
  }

  // Message card, as the customer left it (open or closed)
  const savedCard = arrangement.card;
  if (savedCard) {
    const card = createCard({ size: savedCard.size, message: savedCard.message, open: savedCard.open });
    card.position.set(
      Number(savedCard.position?.x || 0),
      Number(savedCard.position?.y || 0),
      Number(savedCard.position?.z || 0),
    );
    card.rotation.set(
      THREE.MathUtils.degToRad(Number(savedCard.rotation?.xDeg || 0)),
      THREE.MathUtils.degToRad(Number(savedCard.rotation?.yDeg || 0)),
      THREE.MathUtils.degToRad(Number(savedCard.rotation?.zDeg || 0)),
    );
    card.scale.setScalar(Number(savedCard.scale ?? 1));
    model.add(card);
  }

  originalModelRotation = { x: 0, y: 0, z: 0 };
  isRotating.value = false;
  setBouquetCamera();
  scene.add(model);
  isLoading.value = false;
  animateEntrance();
};

const setBouquetCamera = () => {
  camera.fov = 42;
  camera.position.set(0, 2.1, 6.4);
  camera.lookAt(0, 1.5, 0);
  camera.updateProjectionMatrix();
};

const animateEntrance = () => {
  if (!model) return;

  // Simple scale animation
  const startScale = 0.1;
  const endScale = model.scale.x;
  const duration = 1000; // 1 second
  const startTime = Date.now();

  model.scale.setScalar(startScale);

  const animateScale = () => {
    const elapsed = Date.now() - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function
    const easeOutCubic = 1 - Math.pow(1 - progress, 3);
    const currentScale = startScale + (endScale - startScale) * easeOutCubic;

    model.scale.setScalar(currentScale);

    if (progress < 1) {
      requestAnimationFrame(animateScale);
    }
  };

  animateScale();
};

const centerAndScaleModel = () => {
  if (!model) return;

  const box = new THREE.Box3().setFromObject(model);
  const center = box.getCenter(new THREE.Vector3());
  const size = box.getSize(new THREE.Vector3());

  const maxDim = Math.max(size.x, size.y, size.z);
  const scale = 1.5 / maxDim;

  model.scale.setScalar(scale);

  // Center the model, resting its lowest point on the ground (y = -0.5).
  // Models built from the ground up are unchanged; centred ones (e.g. AI
  // photo-to-3D) no longer sink halfway into the floor.
  const offset = center.multiplyScalar(-scale);
  model.position.copy(offset);
  model.position.y = -0.5 - box.min.y * scale;
};

const createFallbackFlower = () => {
  // Create a simple 3D flower as fallback
  const flowerGroup = new THREE.Group();

  // Stem (green cylinder)
  const stemGeometry = new THREE.CylinderGeometry(0.05, 0.05, 1.5, 8);
  const stemMaterial = new THREE.MeshStandardMaterial({
    color: 0x2ecc71,
    roughness: 0.8,
    metalness: 0.1,
  });
  const stem = new THREE.Mesh(stemGeometry, stemMaterial);
  stem.position.y = 0.75;
  stem.castShadow = true;
  flowerGroup.add(stem);

  // Leaves
  const leafGeometry = new THREE.PlaneGeometry(0.3, 0.5);
  const leafMaterial = new THREE.MeshStandardMaterial({
    color: 0x27ae60,
    side: THREE.DoubleSide,
  });

  const leaf1 = new THREE.Mesh(leafGeometry, leafMaterial);
  leaf1.position.set(0.2, 0.5, 0);
  leaf1.rotation.z = Math.PI / 4;
  leaf1.castShadow = true;
  flowerGroup.add(leaf1);

  const leaf2 = new THREE.Mesh(leafGeometry, leafMaterial);
  leaf2.position.set(-0.2, 0.5, 0);
  leaf2.rotation.z = -Math.PI / 4;
  leaf2.castShadow = true;
  flowerGroup.add(leaf2);

  // Flower center (yellow sphere)
  const centerGeometry = new THREE.SphereGeometry(0.15, 16, 16);
  const centerMaterial = new THREE.MeshStandardMaterial({
    color: 0xf1c40f,
    roughness: 0.5,
    metalness: 0.3,
  });
  const flowerCenter = new THREE.Mesh(centerGeometry, centerMaterial);
  flowerCenter.position.y = 1.8;
  flowerCenter.castShadow = true;
  flowerGroup.add(flowerCenter);

  // Petals (pink cones)
  const petalGeometry = new THREE.ConeGeometry(0.12, 0.3, 8);
  const petalMaterial = new THREE.MeshStandardMaterial({
    color: 0xe91e63,
    roughness: 0.7,
    metalness: 0.2,
  });

  const petalCount = 8;
  for (let i = 0; i < petalCount; i++) {
    const angle = (i / petalCount) * Math.PI * 2;
    const radius = 0.25;

    const petal = new THREE.Mesh(petalGeometry, petalMaterial);
    petal.position.set(Math.cos(angle) * radius, 1.8, Math.sin(angle) * radius);
    petal.rotation.x = Math.PI / 2;
    petal.rotation.z = angle;
    petal.castShadow = true;
    flowerGroup.add(petal);
  }

  model = flowerGroup;
  centerAndScaleModel();
  scene.add(model);

  // Store original rotation
  originalModelRotation = {
    x: model.rotation.x,
    y: model.rotation.y,
    z: model.rotation.z,
  };

  isLoading.value = false;
  animateEntrance();
};

const reloadModel = () => {
  if (model) {
    scene.remove(model);
    // Clean up old model resources
    disposeModel(model);
    model = null;
  }
  loadModel();
};

const disposeModel = (object) => {
  object.traverse((child) => {
    if (child.geometry) child.geometry.dispose();
    if (child.material) {
      if (Array.isArray(child.material)) {
        child.material.forEach((m) => m.dispose());
      } else {
        child.material.dispose();
      }
    }
    if (child.texture) child.texture.dispose();
  });
};

const setupMouseControls = () => {
  const canvas = renderer.domElement;
  let rotationX = 0;
  let rotationY = 0;
  let isDragging = false;

  const onMouseDown = (e) => {
    isDragging = true;
    isRotating.value = false;
    previousMousePosition = { x: e.clientX, y: e.clientY };
    canvas.style.cursor = "grabbing";
  };

  const onMouseUp = () => {
    isDragging = false;
    canvas.style.cursor = "grab";
  };

  const onMouseMove = (e) => {
    if (!isDragging || !model) return;

    const deltaMove = {
      x: e.clientX - previousMousePosition.x,
      y: e.clientY - previousMousePosition.y,
    };

    rotationY += deltaMove.x * 0.005;
    rotationX = Math.max(
      -Math.PI / 2,
      Math.min(Math.PI / 2, rotationX + deltaMove.y * 0.005),
    );

    model.rotation.x = rotationX;
    model.rotation.y = rotationY;

    previousMousePosition = { x: e.clientX, y: e.clientY };
  };

  const onWheel = (e) => {
    e.preventDefault();
    const zoomSpeed = 0.001;
    camera.position.z += e.deltaY * zoomSpeed;
    camera.position.z = Math.max(2, Math.min(15, camera.position.z));
  };

  canvas.addEventListener("mousedown", onMouseDown);
  canvas.addEventListener("mouseup", onMouseUp);
  canvas.addEventListener("mouseleave", onMouseUp);
  canvas.addEventListener("mousemove", onMouseMove);
  canvas.addEventListener("wheel", onWheel, { passive: false });
  canvas.style.cursor = "grab";

  let touchStartX = 0;
  let touchStartY = 0;
  let initialRotationX = 0;
  let initialRotationY = 0;

  const onTouchStart = (e) => {
    if (e.touches.length === 1) {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
      isRotating.value = false;

      initialRotationX = rotationX;
      initialRotationY = rotationY;

      e.preventDefault();
    }
  };

  const onTouchMove = (e) => {
    if (!model || e.touches.length !== 1) return;

    const deltaX = e.touches[0].clientX - touchStartX;
    const deltaY = e.touches[0].clientY - touchStartY;

    rotationY = initialRotationY + deltaX * 0.01;
    rotationX = Math.max(
      -Math.PI / 2,
      Math.min(Math.PI / 2, initialRotationX + deltaY * 0.01),
    );

    model.rotation.x = rotationX;
    model.rotation.y = rotationY;

    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    e.preventDefault();
  };

  canvas.addEventListener("touchstart", onTouchStart, { passive: false });
  canvas.addEventListener("touchmove", onTouchMove, { passive: false });

  const resetRotation = () => {
    rotationX = originalModelRotation.x;
    rotationY = originalModelRotation.y;
  };

  return { resetRotation };
};

const animate = () => {
  animationFrameId = requestAnimationFrame(animate);

  if (model && isRotating.value) {
    model.rotation.y += 0.003;
  }

  if (model && isRotating.value && props.arrangement?.type !== "custom_flower_bouquet") {
    model.position.y = -0.5 + Math.sin(Date.now() * 0.001) * 0.05;
  }

  renderer.render(scene, camera);
};

const handleResize = () => {
  if (!viewerContainer.value || !camera || !renderer) return;

  const width = viewerContainer.value.clientWidth;
  const height = viewerContainer.value.clientHeight;

  camera.aspect = width / height;
  camera.updateProjectionMatrix();
  renderer.setSize(width, height);
};

const resetCamera = () => {
  if (props.arrangement?.type === "custom_flower_bouquet") {
    setBouquetCamera();
  } else {
    camera.position.set(0, 1, 5);
    camera.lookAt(0, 0, 0);
  }

  if (model) {
    model.rotation.set(
      originalModelRotation.x,
      originalModelRotation.y,
      originalModelRotation.z,
    );
    model.position.y = props.arrangement?.type === "custom_flower_bouquet" ? 0 : -0.5;
  }
};

const toggleRotation = () => {
  isRotating.value = !isRotating.value;
};

const toggleWireframe = () => {
  showWireframe.value = !showWireframe.value;

  if (model) {
    model.traverse((child) => {
      if (child.isMesh) {
        if (Array.isArray(child.material)) {
          child.material.forEach((material) => {
            material.wireframe = showWireframe.value;
          });
        } else {
          child.material.wireframe = showWireframe.value;
        }
      }
    });
  }
};

const toggleFullscreen = () => {
  if (!viewerContainer.value) return;

  if (!document.fullscreenElement) {
    if (viewerContainer.value.requestFullscreen) {
      viewerContainer.value.requestFullscreen();
    } else if (viewerContainer.value.webkitRequestFullscreen) {
      viewerContainer.value.webkitRequestFullscreen();
    } else if (viewerContainer.value.msRequestFullscreen) {
      viewerContainer.value.msRequestFullscreen();
    }
    isFullscreen.value = true;
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen();
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    }
    isFullscreen.value = false;
  }

  // Handle resize after fullscreen change
  setTimeout(handleResize, 100);
};

// Leaving full screen with Esc doesn't go through toggleFullscreen
const syncFullscreen = () => {
  isFullscreen.value = !!document.fullscreenElement;
  setTimeout(handleResize, 100);
};

const cleanup = () => {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
  }

  window.removeEventListener("resize", handleResize);
  document.removeEventListener("fullscreenchange", syncFullscreen);

  // Exit fullscreen if active
  if (document.fullscreenElement && document.exitFullscreen) {
    document.exitFullscreen();
  }

  if (renderer) {
    renderer.dispose();
    if (viewerContainer.value && renderer.domElement) {
      viewerContainer.value.removeChild(renderer.domElement);
    }
  }

  // Clean up all resources
  if (scene) {
    disposeModel(scene);
  }
};
</script>

<style scoped>
.model-viewer-container {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 500px;
  border-radius: 16px;
  overflow: hidden;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.viewer-canvas {
  width: 100%;
  height: 100%;
  outline: none;
}

.model-skel {
  display: block;
  width: 160px;
  height: 200px;
  margin: 0 auto 14px;
  border-radius: 80px 80px 24px 24px;
}

.model-loading {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  z-index: 10;
}

.model-loading p {
  color: #4a5568;
  font-size: 16px;
  font-weight: 500;
}

.model-error {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  padding: 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  z-index: 10;
  max-width: 80%;
}

.error-icon {
  display: block;
  width: 36px;
  height: 36px;
  margin: 0 auto 12px;
  fill: none;
  stroke: #e53e3e;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.model-error p {
  color: #e53e3e;
  font-size: 14px;
  margin: 0;
  line-height: 1.5;
}

.viewer-controls {
  position: absolute;
  top: 24px;
  right: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  z-index: 5;
  background: rgba(255, 255, 255, 0.9);
  padding: 12px;
  border-radius: 12px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(226, 232, 240, 0.6);
}

.control-btn {
  width: 44px;
  height: 44px;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  color: #363f4e;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.control-btn svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.control-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.control-btn.active {
  background: #4db173;
  color: white;
  border-color: #4db173;
}

.viewer-instructions {
  position: absolute;
  bottom: 24px;
  left: 24px;
  padding: 12px 20px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 10px;
  font-size: 14px;
  color: #4a5568;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(226, 232, 240, 0.6);
  font-weight: 500;
  z-index: 5;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.viewer-instructions p {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Fullscreen styles */
.model-viewer-container:fullscreen {
  width: 100vw;
  height: 100vh;
  border-radius: 0;
}

.model-viewer-container:-webkit-full-screen {
  width: 100vw;
  height: 100vh;
  border-radius: 0;
}

.model-viewer-container:-moz-full-screen {
  width: 100vw;
  height: 100vh;
  border-radius: 0;
}

.model-viewer-container:-ms-fullscreen {
  width: 100vw;
  height: 100vh;
  border-radius: 0;
}

@media (max-width: 768px) {
  .model-viewer-container {
    min-height: 400px;
    border-radius: 12px;
  }

  .viewer-controls {
    bottom: 16px;
    right: 16px;
    padding: 10px;
    gap: 10px;
  }

  .control-btn {
    width: 40px;
    height: 40px;
    font-size: 18px;
  }

  .viewer-instructions {
    bottom: 16px;
    left: 16px;
    font-size: 12px;
    padding: 10px 16px;
  }
}

@media (max-width: 480px) {
  .model-viewer-container {
    min-height: 300px;
  }

  .viewer-controls {
    bottom: 12px;
    right: 12px;
    padding: 8px;
    gap: 8px;
  }

  .control-btn {
    width: 40px;
    height: 40px;
    font-size: 16px;
  }

  .viewer-instructions {
    bottom: 12px;
    left: 12px;
    font-size: 11px;
    padding: 8px 12px;
  }
}

/* Focus states for accessibility */
.control-btn:focus-visible {
  outline: 2px solid #48bb78;
  outline-offset: 2px;
}
</style>
