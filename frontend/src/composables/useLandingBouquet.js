// Landing-page bouquet scene.
//
// Loads the compressed landing copy of the real bouquet model
// (public/Boquet.landing.glb, generated from Boquet.glb with gltf-transform +
// meshopt) and splits it into three parts by material name so the scroll
// story can focus on flowers, the wrapper, and fillers independently.
//
// The landing page drives everything through plain, GSAP-tweenable objects:
//   rig      — THREE.Group: position / rotation / scale of the whole bouquet
//   view     — camera position (x, y, z) and look-at target (tx, ty, tz)
//   focus    — per-part opacity { flowers, wrapper, fillers } (0..1)
//   glow     — { opacity } of the soft bloom halo behind the bouquet
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import { MeshoptDecoder } from "three/examples/jsm/libs/meshopt_decoder.module.js";
import { createBloomLayer } from "./landingFlowers";

export const LANDING_BOUQUET_URL = "/Boquet.landing.glb";
// Byte size of the generated landing GLB, used when the server does not send
// a Content-Length header so the loader can still report real progress.
const LANDING_BOUQUET_BYTES = 3_640_000;

export const PART_KEYS = ["flowers", "wrapper", "fillers"];

// Same paper / ribbon swatches the 3D customizer offers.
export const PAPER_PRESETS = ["#d3b18f", "#f3e9dc", "#d7d2c8", "#e2c7cf", "#c8b6a6"];
export const RIBBON_PRESETS = ["#caa6d9", "#d95f76", "#7d8f69", "#f0dfb2", "#6c5b7b"];

// Filler kinds: a tint over the leaves' own textures (they are unlit, so the
// tint multiplies the texture; white keeps the original greenery).
export const FILLER_KINDS = [
  { key: "greenery", label: "Greenery", tint: "#ffffff" },
  { key: "eucalyptus", label: "Eucalyptus", tint: "#b7d2d6" },
  { key: "fern", label: "Fern", tint: "#8fbf72" },
  { key: "dried", label: "Dried gold", tint: "#e8c27e" },
];

// Filler arrangements: how far each piece moves out from the middle (share of
// the wrapper's radius), up (share of its height), and how big it is.
// `wave` lifts every other piece by that much and drops the rest.
export const FILLER_LOOKS = [
  { key: "classic", label: "Classic", spread: 0, lift: 0, scale: 1, wave: 0 },
  { key: "full", label: "Full and lush", spread: 0.16, lift: 0.02, scale: 1.22, wave: 0 },
  { key: "airy", label: "Light and airy", spread: 0.26, lift: 0.1, scale: 0.88, wave: 0.07 },
  { key: "tucked", label: "Neatly tucked", spread: -0.12, lift: -0.06, scale: 0.92, wave: 0 },
];

// Materials in Boquet.glb: paper04_05_06 + Pink form the wrapper; Leaves*,
// Sepals*, SmallPlants* are fillers; everything else (daisy Material.001,
// lavender Material + Flower_01_Stem) is the flowers.
function classifyMaterial(name = "") {
  if (/^(paper|pink)/i.test(name)) return "wrapper";
  if (/^(leaves|sepals|smallplants)/i.test(name)) return "fillers";
  return "flowers";
}

function radialTexture(innerColor, outerColor) {
  const size = 256;
  const canvas = document.createElement("canvas");
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext("2d");
  const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
  gradient.addColorStop(0, innerColor);
  gradient.addColorStop(1, outerColor);
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, size, size);
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  return texture;
}

function buildBloomGlow() {
  const material = new THREE.SpriteMaterial({
    map: radialTexture("rgba(214,240,222,0.85)", "rgba(214,240,222,0)"),
    transparent: true,
    depthWrite: false,
  });
  const sprite = new THREE.Sprite(material);
  sprite.scale.set(5.2, 5.2, 1);
  sprite.position.set(0, 0.1, -1.2);
  return sprite;
}

function buildGroundShadow() {
  const material = new THREE.MeshBasicMaterial({
    map: radialTexture("rgba(30,36,46,0.22)", "rgba(30,36,46,0)"),
    transparent: true,
    depthWrite: false,
  });
  const shadow = new THREE.Mesh(new THREE.PlaneGeometry(3.4, 2.2), material);
  shadow.rotation.x = -Math.PI / 2;
  return shadow;
}

function buildParticles(count) {
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 8;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 6;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 4;
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
  const material = new THREE.PointsMaterial({
    color: 0x8fd1a6,
    size: 0.035,
    transparent: true,
    opacity: 0.32,
    depthWrite: false,
  });
  return new THREE.Points(geometry, material);
}

export function createLandingBouquet(canvas, { onProgress, reducedMotion = false } = {}) {
  const isCoarse = window.matchMedia("(pointer: coarse)").matches;

  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: !isCoarse, powerPreference: "high-performance" });
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.setClearColor(0x000000, 0);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(38, 1, 0.05, 100);

  const view = { x: 0, y: 0, z: 6, tx: 0, ty: 0, tz: 0 };
  const focus = { flowers: 1, wrapper: 1, fillers: 1 };
  const applied = { flowers: 1, wrapper: 1, fillers: 1 };
  const glowState = { opacity: 1 };

  // rig: scroll-driven transforms. spinner: idle sway + pointer tilt.
  const rig = new THREE.Group();
  const spinner = new THREE.Group();
  rig.add(spinner);
  scene.add(rig);

  // Same light rig as the 3D customizer; most bouquet materials are lit
  // (only a few use KHR_materials_unlit).
  const ambient = new THREE.AmbientLight(0xffffff, 1.2);
  const key = new THREE.DirectionalLight(0xfff1dc, 1.6);
  key.position.set(4, 8, 5);
  const fill = new THREE.DirectionalLight(0xe2f0ff, 0.7);
  fill.position.set(-4, 3, -2);
  scene.add(ambient, key, fill);

  const glow = buildBloomGlow();
  scene.add(glow);
  const particles = buildParticles(isCoarse ? 40 : 70);
  scene.add(particles);

  const parts = Object.fromEntries(
    PART_KEYS.map((key) => [key, { meshes: [], materials: new Set(), center: new THREE.Vector3(), size: new THREE.Vector3() }]),
  );

  const pointer = { x: 0, y: 0 };
  const tilt = { x: 0, z: 0 };
  let interactionPaused = false;
  let running = false;
  let rafId = 0;
  let model = null;
  let bloom = null; // sculpted flower heads (see landingFlowers.js)

  // Filler pieces: where each sits in the model, and which way is "out" and
  // "up" for it, so a look can move it (see setFillerLook)
  const fillerPieces = []; // { mesh, base, baseScale, pivot, out, up, odd }
  const fillerTarget = { spread: 0, lift: 0, scale: 1, wave: 0 };
  const fillerNow = { ...fillerTarget };
  const fillerTint = new THREE.Color("#ffffff");
  let fillerTintNow = new THREE.Color("#ffffff");
  let fillerSize = { radius: 1, height: 1 };

  function placeFillers() {
    fillerPieces.forEach((piece) => {
      const wave = piece.odd ? fillerNow.wave : -fillerNow.wave * 0.5;
      // Sized around its own middle (pivot), so growing doesn't shift it
      piece.mesh.position
        .copy(piece.base)
        .addScaledVector(piece.pivot.clone().sub(piece.base), 1 - fillerNow.scale)
        .addScaledVector(piece.out, fillerNow.spread * fillerSize.radius)
        .addScaledVector(piece.up, (fillerNow.lift + wave) * fillerSize.height);
      piece.mesh.scale.copy(piece.baseScale).multiplyScalar(fillerNow.scale);
    });
    parts.fillers.materials.forEach((material) => material.color.copy(fillerTintNow));
  }

  /** Eases the fillers toward their chosen look and tint; true while still moving */
  function stepFillers() {
    if (!fillerPieces.length) return;
    let moving = false;
    const k = reducedMotion ? 1 : 0.07;
    Object.keys(fillerTarget).forEach((key) => {
      const gap = fillerTarget[key] - fillerNow[key];
      if (Math.abs(gap) > 0.0005) {
        fillerNow[key] += gap * k;
        moving = true;
      } else fillerNow[key] = fillerTarget[key];
    });
    const before = fillerTintNow.clone();
    fillerTintNow.lerp(fillerTint, k);
    const tintMoving = Math.abs(before.r - fillerTint.r) + Math.abs(before.g - fillerTint.g) + Math.abs(before.b - fillerTint.b) > 0.002;
    if (!tintMoving) fillerTintNow = fillerTint.clone();
    if (moving || tintMoving) placeFillers();
  }

  function setFillerLook(key) {
    const look = FILLER_LOOKS.find((entry) => entry.key === key) || FILLER_LOOKS[0];
    Object.assign(fillerTarget, { spread: look.spread, lift: look.lift, scale: look.scale, wave: look.wave });
  }

  function setFillerKind(key) {
    const kind = FILLER_KINDS.find((entry) => entry.key === key) || FILLER_KINDS[0];
    fillerTint.set(kind.tint);
  }

  /** Learns each filler piece's resting place and its out / up directions */
  function measureFillers(wrapperBox) {
    const center = wrapperBox.getCenter(new THREE.Vector3());
    const size = wrapperBox.getSize(new THREE.Vector3());
    fillerSize = { radius: Math.max(size.x, size.z) / 2, height: size.y };
    spinner.updateMatrixWorld(true);
    const axis = spinner.worldToLocal(center.clone());
    parts.fillers.meshes.forEach((mesh, index) => {
      const parent = mesh.parent;
      if (!parent) return;
      // The piece's middle and its direction away from the bouquet's axis, in the spinner's space
      const middle = spinner.worldToLocal(new THREE.Box3().setFromObject(mesh).getCenter(new THREE.Vector3()));
      const outward = new THREE.Vector3(middle.x - axis.x, 0, middle.z - axis.z);
      if (outward.lengthSq() < 1e-6) outward.set(Math.cos(index * 2.4), 0, Math.sin(index * 2.4));
      outward.normalize();
      // The same directions in the piece's own parent space, one unit long in the spinner's space
      const toParent = (vector) => {
        const from = parent.worldToLocal(spinner.localToWorld(middle.clone()));
        const to = parent.worldToLocal(spinner.localToWorld(middle.clone().add(vector)));
        return to.sub(from);
      };
      fillerPieces.push({
        mesh,
        base: mesh.position.clone(),
        baseScale: mesh.scale.clone(),
        pivot: parent.worldToLocal(spinner.localToWorld(middle.clone())),
        out: toParent(outward),
        up: toParent(new THREE.Vector3(0, 1, 0)),
        odd: index % 2 === 1,
      });
    });
  }

  function resize(width = window.innerWidth, height = window.innerHeight) {
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, isCoarse ? 1.5 : 2));
    renderer.setSize(width, height, false);
  }

  function applyFocus() {
    PART_KEYS.forEach((key) => {
      const value = focus[key];
      if (Math.abs(value - applied[key]) < 0.001) return;
      const transparent = value < 0.999;
      parts[key].materials.forEach((material) => {
        if (material.transparent !== transparent) {
          material.transparent = transparent;
          material.needsUpdate = true;
        }
        material.opacity = value;
        material.depthWrite = value > 0.6;
      });
      applied[key] = value;
    });
  }

  function frame(time) {
    rafId = requestAnimationFrame(frame);

    if (!reducedMotion) {
      spinner.position.y = Math.sin(time * 0.0006) * 0.05;
      spinner.rotation.y = Math.sin(time * 0.00035) * 0.16;
      particles.rotation.y += 0.0005;
    }
    if (!interactionPaused && !isCoarse && !reducedMotion) {
      tilt.x += (pointer.y * 0.16 - tilt.x) * 0.04;
      tilt.z += (pointer.x * -0.12 - tilt.z) * 0.04;
    }
    spinner.rotation.x = tilt.x;
    spinner.rotation.z = tilt.z;

    glow.material.opacity = glowState.opacity;
    applyFocus();
    stepFillers();

    camera.position.set(view.x, view.y, view.z);
    camera.lookAt(view.tx, view.ty, view.tz);
    renderer.render(scene, camera);
  }

  function start() {
    if (running) return;
    running = true;
    rafId = requestAnimationFrame(frame);
  }

  function stop() {
    running = false;
    cancelAnimationFrame(rafId);
  }

  function handleVisibility() {
    if (document.hidden) stop();
    else if (active) start();
  }
  let active = true;
  document.addEventListener("visibilitychange", handleVisibility);

  // Pause rendering entirely (e.g. once the footer blackout covers the canvas).
  function setActive(value) {
    active = value;
    if (value && !document.hidden) start();
    else stop();
  }

  function setPaperColor(color) {
    parts.wrapper.materials.forEach((material) => {
      if (/^paper/i.test(material.name)) material.color.set(color);
    });
  }

  function setRibbonColor(color) {
    parts.wrapper.materials.forEach((material) => {
      if (/^pink/i.test(material.name)) material.color.set(color);
    });
  }

  const loader = new GLTFLoader();
  loader.setMeshoptDecoder(MeshoptDecoder);

  const ready = new Promise((resolve, reject) => {
    loader.load(
      LANDING_BOUQUET_URL,
      (gltf) => {
        model = gltf.scene || gltf.scenes?.[0];
        if (!model) {
          reject(new Error("No scene found inside the landing bouquet model."));
          return;
        }

        // Normalize like FlowerCustomizer: fit the largest dimension, centre it.
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const scale = 2.6 / Math.max(size.x, size.y, size.z, 0.001);
        model.scale.setScalar(scale);
        model.position.sub(center.multiplyScalar(scale));
        spinner.add(model);
        model.updateMatrixWorld(true);

        model.traverse((child) => {
          if (!child.isMesh) return;
          const materials = Array.isArray(child.material) ? child.material : [child.material];
          const key = classifyMaterial(materials[0]?.name);
          parts[key].meshes.push(child);
          materials.forEach((material) => parts[key].materials.add(material));
        });

        // The base model has bare stems where vendors' flowers would go
        // (material "Material.001"); hide them, the sculpted heads replace them.
        parts.flowers.meshes = parts.flowers.meshes.filter((mesh) => {
          const name = (Array.isArray(mesh.material) ? mesh.material[0] : mesh.material)?.name;
          if (name !== "Material.001") return true;
          mesh.visible = false;
          return false;
        });

        // Part focus points in rig-local space (the rig is at identity here).
        PART_KEYS.forEach((key) => {
          if (!parts[key].meshes.length) return;
          const partBox = new THREE.Box3();
          parts[key].meshes.forEach((mesh) => partBox.expandByObject(mesh));
          partBox.getCenter(parts[key].center);
          partBox.getSize(parts[key].size);
        });

        // Sculpted flower heads, domed inside the wrapper opening.
        const wrapperBox = new THREE.Box3();
        parts.wrapper.meshes.forEach((mesh) => wrapperBox.expandByObject(mesh));
        const wSize = wrapperBox.getSize(new THREE.Vector3());
        const wCenter = wrapperBox.getCenter(new THREE.Vector3());
        measureFillers(wrapperBox);
        bloom = createBloomLayer({
          anchor: new THREE.Vector3(wCenter.x, wrapperBox.max.y - wSize.y * 0.2, wCenter.z),
          radius: Math.max(wSize.x, wSize.z) * 0.3,
          headSize: Math.max(wSize.x, wSize.z) * 0.24,
          reducedMotion,
        });
        spinner.add(bloom.group);
        parts.flowers.materials.add(bloom.material);
        const bloomBox = new THREE.Box3().setFromObject(bloom.group);
        bloomBox.getCenter(parts.flowers.center);
        bloomBox.getSize(parts.flowers.size);

        const shadow = buildGroundShadow();
        shadow.position.y = -1.32;
        spinner.add(shadow);

        resolve({ parts });
      },
      (event) => {
        const total = event.total || LANDING_BOUQUET_BYTES;
        onProgress?.(Math.min(event.loaded / total, 0.99));
      },
      reject,
    );
  });

  function dispose() {
    stop();
    bloom?.dispose();
    document.removeEventListener("visibilitychange", handleVisibility);
    scene.traverse((object) => {
      object.geometry?.dispose();
      const materials = Array.isArray(object.material) ? object.material : object.material ? [object.material] : [];
      materials.forEach((material) => {
        Object.values(material).forEach((value) => value?.isTexture && value.dispose());
        material.dispose();
      });
    });
    renderer.dispose();
  }

  resize();
  start();

  return {
    renderer,
    scene,
    camera,
    rig,
    view,
    focus,
    glow: glowState,
    parts,
    ready,
    resize,
    setActive,
    setPaperColor,
    setRibbonColor,
    setBloom(variety) {
      bloom?.setVariety(variety);
    },
    setFillerLook,
    setFillerKind,
    setPointer(x, y) {
      pointer.x = x;
      pointer.y = y;
    },
    setInteractionPaused(value) {
      interactionPaused = value;
    },
    dispose,
  };
}
