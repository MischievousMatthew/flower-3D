<template>
  <!-- Template remains the same -->
  <div class="model-viewer-container">
    <div v-if="isLoading" class="model-loading">
      <div class="loading-spinner"></div>
      <p>Loading 3D Model...</p>
    </div>

    <div v-if="error" class="model-error">
      <span class="error-icon">⚠️</span>
      <p>{{ error }}</p>
    </div>

    <div ref="viewerContainer" class="viewer-canvas"></div>

    <div class="viewer-controls">
      <button @click="resetCamera" class="control-btn" title="Reset View">
        🔄
      </button>
      <button
        @click="toggleRotation"
        class="control-btn"
        :class="{ active: isRotating }"
        title="Auto Rotate"
      >
        {{ isRotating ? "⏸️" : "▶️" }}
      </button>
      <button
        @click="toggleWireframe"
        class="control-btn"
        :class="{ active: showWireframe }"
        title="Wireframe"
      >
        🔲
      </button>
      <button @click="toggleFullscreen" class="control-btn" title="Fullscreen">
        ⛶
      </button>
    </div>

    <div class="viewer-instructions">
      <p>🖱️ Drag to rotate • Scroll to zoom</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import * as THREE from "three";

const props = defineProps({
  modelUrl: {
    type: String,
    required: true,
  },
  modelType: {
    type: String,
    default: "glb",
  },
  backgroundColor: {
    type: String,
    default: "#f8f9fa",
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

  // Center the model
  const offset = center.multiplyScalar(-scale);
  model.position.copy(offset);
  model.position.y = -0.5;
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

  if (model && isRotating.value) {
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
  camera.position.set(0, 1, 5);
  camera.lookAt(0, 0, 0);

  if (model) {
    rotationX = originalModelRotation.x;
    rotationY = originalModelRotation.y;

    model.rotation.set(rotationX, rotationY, originalModelRotation.z);
    model.position.y = -0.5;
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

const cleanup = () => {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
  }

  window.removeEventListener("resize", handleResize);

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

.loading-spinner {
  width: 60px;
  height: 60px;
  border: 5px solid rgba(226, 232, 240, 0.3);
  border-top: 5px solid #48bb78;
  border-radius: 50%;
  animation: spin 1s ease-in-out infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
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
  font-size: 48px;
  display: block;
  margin-bottom: 16px;
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
  font-size: 20px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.control-btn:hover {
  background: #f7fafc;
  transform: translateY(-3px) scale(1.05);
  box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
  border-color: #cbd5e0;
}

.control-btn.active {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
  color: white;
  border-color: #48bb78;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
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
    width: 36px;
    height: 36px;
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
