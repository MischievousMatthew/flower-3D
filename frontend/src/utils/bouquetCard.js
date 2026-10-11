import * as THREE from "three";

/*
 * Message card for custom bouquets: a plain white folding card that faces
 * the viewer (+Z). The front cover is hinged on the left edge and swings open
 * to show the message printed on the inside page.
 *
 * Shared by the flower customizer and the 3D viewer, so a saved card looks
 * the same everywhere. The returned group is the card's own root; callers
 * position, rotate and scale it like any placed flower.
 */

export const CARD_SIZE_SCALE = { Small: 0.8, Medium: 1, Large: 1.22 };
const CARD_WIDTH = 0.42; // Medium, in scene units (the bouquet is ~3.5 tall)
const CARD_HEIGHT = 0.58;
const CARD_THICKNESS = 0.008;
const OPEN_ANGLE = -2.53; // cover swings ~145° to the left, slightly toward the viewer
const PLACEHOLDER = "Double-click to write your message";
const INK = "#3a2f2a";

// ── Message allowance ──────────────────────────────────────────────────────
// "letters" counts characters except spaces; "words" counts space-separated words.

export function countCardMessage(message, limitType) {
  const trimmed = String(message || "").trim();
  if (!trimmed) return 0;
  return limitType === "letters" ? trimmed.replace(/\s+/g, "").length : trimmed.split(/\s+/).length;
}

/** The longest start of `message` that stays within the allowance */
export function clampCardMessage(message, limitType, limit) {
  const text = String(message || "");
  if (!limit || countCardMessage(text, limitType) <= limit) return text;

  let used = 0;
  let inWord = false;
  for (let i = 0; i < text.length; i += 1) {
    const isSpace = /\s/.test(text[i]);
    if (limitType === "letters") {
      if (!isSpace) used += 1;
      if (used > limit) return text.slice(0, i);
    } else {
      if (!isSpace && !inWord) {
        used += 1;
        if (used > limit) return text.slice(0, i).replace(/\s+$/, "");
      }
      inWord = !isSpace;
    }
  }
  return text;
}

// ── Inside page ────────────────────────────────────────────────────────────

let fontsReady = null;
const whenFontsReady = () => {
  if (!fontsReady) {
    fontsReady =
      typeof document !== "undefined" && document.fonts?.load
        ? document.fonts.load('italic 40px "Cormorant Garamond"').catch(() => null)
        : Promise.resolve();
  }
  return fontsReady;
};

function wrapLines(context, text, maxWidth) {
  const lines = [];
  text.split(/\n/).forEach((paragraph) => {
    const words = paragraph.split(/\s+/).filter(Boolean);
    if (!words.length) {
      lines.push("");
      return;
    }
    let line = "";
    words.forEach((word) => {
      const next = line ? `${line} ${word}` : word;
      if (context.measureText(next).width <= maxWidth || !line) {
        line = next;
      } else {
        lines.push(line);
        line = word;
      }
    });
    lines.push(line);
  });
  return lines;
}

function drawPage(canvas, message) {
  const context = canvas.getContext("2d");
  const { width, height } = canvas;
  context.fillStyle = "#ffffff";
  context.fillRect(0, 0, width, height);

  // A fine inset rule, like a printed card
  context.strokeStyle = "rgba(58, 47, 42, 0.14)";
  context.lineWidth = 2;
  context.strokeRect(22, 22, width - 44, height - 44);

  const text = String(message || "").trim();
  const isPlaceholder = !text;
  const content = isPlaceholder ? PLACEHOLDER : text;
  const maxWidth = width * 0.76;
  const maxHeight = height * 0.78;

  // Largest font size at which the message fits the page
  let size = isPlaceholder ? 30 : 46;
  let lines = [];
  for (; size >= 14; size -= 2) {
    context.font = `italic ${size}px "Cormorant Garamond", Georgia, serif`;
    lines = wrapLines(context, content, maxWidth);
    if (lines.length * size * 1.3 <= maxHeight) break;
  }

  context.fillStyle = isPlaceholder ? "rgba(58, 47, 42, 0.38)" : INK;
  context.textAlign = "center";
  context.textBaseline = "middle";
  const lineHeight = size * 1.3;
  const top = height / 2 - ((lines.length - 1) * lineHeight) / 2;
  lines.forEach((line, index) => context.fillText(line, width / 2, top + index * lineHeight));
}

// ── Card object ────────────────────────────────────────────────────────────

const paper = () => new THREE.MeshStandardMaterial({ color: "#ffffff", roughness: 0.82, metalness: 0 });

export function createCard({ size = "Medium", message = "", open = false } = {}) {
  const root = new THREE.Group();
  const content = new THREE.Group(); // scaled by card size
  root.add(content);

  const canvas = document.createElement("canvas");
  canvas.width = 512;
  canvas.height = Math.round((512 * CARD_HEIGHT) / CARD_WIDTH);
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.anisotropy = 4;
  const pageMaterial = new THREE.MeshStandardMaterial({ map: texture, roughness: 0.85, metalness: 0 });

  // Inside page (back half); its front face carries the message
  const pageGeometry = new THREE.BoxGeometry(CARD_WIDTH, CARD_HEIGHT, CARD_THICKNESS);
  const page = new THREE.Mesh(pageGeometry, [paper(), paper(), paper(), paper(), pageMaterial, paper()]);
  content.add(page);

  // Front cover, hinged along the left edge
  const hinge = new THREE.Group();
  hinge.position.set(-CARD_WIDTH / 2, 0, CARD_THICKNESS);
  const cover = new THREE.Mesh(new THREE.BoxGeometry(CARD_WIDTH, CARD_HEIGHT, CARD_THICKNESS), paper());
  cover.position.set(CARD_WIDTH / 2, 0, CARD_THICKNESS / 2);
  hinge.add(cover);
  content.add(hinge);

  root.traverse((child) => {
    if (child.isMesh) {
      child.castShadow = true;
      child.receiveShadow = true;
    }
  });

  root.userData.card = { content, hinge, canvas, texture, message, open: !!open, animation: 0 };
  root.userData.isCard = true;

  setCardSize(root, size);
  setCardMessage(root, message);
  hinge.rotation.y = open ? OPEN_ANGLE : 0;
  return root;
}

export function setCardSize(card, size) {
  const scale = CARD_SIZE_SCALE[size] ?? 1;
  card.userData.card.content.scale.setScalar(scale);
}

export function setCardMessage(card, message) {
  const state = card.userData.card;
  state.message = message;
  drawPage(state.canvas, message);
  state.texture.needsUpdate = true;
  // Redraw once the card font has loaded, so the first draw isn't in Georgia
  whenFontsReady().then(() => {
    if (state.message !== message) return;
    drawPage(state.canvas, message);
    state.texture.needsUpdate = true;
  });
}

/** Swing the cover open or closed (animated unless `animate` is false) */
export function setCardOpen(card, open, { animate = true } = {}) {
  const state = card.userData.card;
  state.open = !!open;
  const target = open ? OPEN_ANGLE : 0;
  cancelAnimationFrame(state.animation);

  const reduceMotion = typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  if (!animate || reduceMotion) {
    state.hinge.rotation.y = target;
    return;
  }

  const start = state.hinge.rotation.y;
  const startedAt = performance.now();
  const duration = 520;
  const step = (now) => {
    const t = Math.min(1, (now - startedAt) / duration);
    const eased = 1 - Math.pow(1 - t, 3);
    state.hinge.rotation.y = start + (target - start) * eased;
    if (t < 1) state.animation = requestAnimationFrame(step);
  };
  state.animation = requestAnimationFrame(step);
}

export function disposeCard(card) {
  const state = card.userData.card;
  if (!state) return;
  cancelAnimationFrame(state.animation);
  card.traverse((child) => {
    if (!child.isMesh) return;
    child.geometry?.dispose();
    (Array.isArray(child.material) ? child.material : [child.material]).forEach((material) => material?.dispose());
  });
  state.texture.dispose();
}
