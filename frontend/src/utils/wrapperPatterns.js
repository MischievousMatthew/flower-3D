// Wrapping-paper patterns for the outer wrapper: plain (just the colour),
// newspaper (newsprint, like the classic newspaper-wrapped bouquet), or words
// (the customer's word written over and over, e.g. "love", on their colour).
// Drawn on a canvas in the browser, so no image files are needed. Used by the
// customizer and by the 3D viewer that rebuilds saved bouquets.
import * as THREE from "three";

export const WRAPPER_PATTERNS = [
  { key: "plain", label: "Plain" },
  { key: "newspaper", label: "Newspaper" },
  { key: "words", label: "Words" },
];
export const MAX_PATTERN_WORD = 16;
// The word's size, as a percent of the normal size (mirrors BouquetPresets)
export const MIN_PATTERN_SIZE = 50;
export const MAX_PATTERN_SIZE = 200;
// Prints go on paper wraps only (mirrors BouquetPresets::PATTERN_DESIGNS)
export const PATTERN_DESIGNS = ["classic", "korean"];
const SCRIPT_FONT = `"Dancing Script", "Segoe Script", "Brush Script MT", cursive`;
const SIZE = 1024;

/** Letters, numbers, spaces and a few marks; up to MAX_PATTERN_WORD characters */
export function cleanPatternWord(text) {
  return String(text ?? "")
    .replace(/[^\p{L}\p{N} '&!♥.-]/gu, "")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, MAX_PATTERN_WORD);
}

/** A word size between 50% and 200%, in steps of 5; 100 when missing */
export function cleanPatternSize(size) {
  const n = Number(size);
  if (size === null || size === undefined || size === "" || !Number.isFinite(n)) return 100;
  return Math.round(Math.min(MAX_PATTERN_SIZE, Math.max(MIN_PATTERN_SIZE, n)) / 5) * 5;
}

/** A saved pattern, or null for plain paper */
export function normalizePattern(pattern) {
  const type = pattern?.type;
  if (type === "newspaper") return { type };
  if (type === "words") {
    const text = cleanPatternWord(pattern.text);
    if (!text) return null;
    const size = cleanPatternSize(pattern.size);
    return size === 100 ? { type, text } : { type, text, size };
  }
  return null;
}

// ── Drawing ────────────────────────────────────────────────────────────

// Same marks every time, so a saved bouquet looks the way it did
function seeded(seed) {
  let s = seed >>> 0 || 1;
  return () => ((s = (s * 1664525 + 1013904223) >>> 0) / 4294967296);
}

const NEWS_WORDS = (
  "the city council market weather flowers garden spring news daily report people world local season bloom " +
  "today morning evening sunday market prices festival music review letters editor sports results notice"
).split(" ");
const HEADLINES = ["THE DAILY BLOOM", "CITY NEWS", "GARDEN TIMES", "MORNING POST", "WEEKEND EDITION", "FLOWER MARKET"];

function drawNewspaper(ctx) {
  const rand = seeded(7);
  ctx.fillStyle = "#efece4";
  ctx.fillRect(0, 0, SIZE, SIZE);

  const columns = 4;
  const gap = 22;
  const colWidth = (SIZE - gap * (columns + 1)) / columns;
  for (let c = 0; c < columns; c++) {
    const x = gap + c * (colWidth + gap);
    let y = 18;
    while (y < SIZE) {
      if (rand() < 0.16) {
        // A headline
        ctx.fillStyle = "#2b2b2b";
        ctx.font = `700 ${Math.round(30 + rand() * 18)}px Georgia, "Times New Roman", serif`;
        y += 40;
        ctx.fillText(HEADLINES[Math.floor(rand() * HEADLINES.length)], x, y, colWidth);
        y += 14;
        continue;
      }
      // A paragraph of small print
      ctx.fillStyle = "#4a4a4a";
      ctx.font = `400 15px Georgia, "Times New Roman", serif`;
      const lines = 4 + Math.floor(rand() * 7);
      for (let l = 0; l < lines && y < SIZE; l++) {
        y += 19;
        let line = "";
        while (ctx.measureText(line).width < colWidth - 30) line += `${NEWS_WORDS[Math.floor(rand() * NEWS_WORDS.length)]} `;
        ctx.fillText(line, x, y, colWidth);
      }
      y += 12;
    }
    // Column rule
    ctx.fillStyle = "rgba(40, 40, 40, 0.25)";
    if (c > 0) ctx.fillRect(x - gap / 2, 0, 1.5, SIZE);
  }
}

function mix(hex, toward, amount) {
  const a = new THREE.Color(hex);
  return `#${a.lerp(new THREE.Color(toward), amount).getHexString()}`;
}

function drawWords(ctx, hex, text, size = 100) {
  const scale = size / 100;
  const background = hex || "#d9467a";
  ctx.fillStyle = background;
  ctx.fillRect(0, 0, SIZE, SIZE);
  // Pale letters on a dark paper, darker letters on a light one
  const light = new THREE.Color(background).getHSL({}).l > 0.68;
  ctx.fillStyle = light ? mix(background, "#000000", 0.38) : mix(background, "#ffffff", 0.72);
  ctx.font = `700 ${Math.round(170 * scale)}px ${SCRIPT_FONT}`;
  ctx.textBaseline = "middle";

  const step = Math.max(ctx.measureText(text).width + 90 * scale, 260 * scale);
  const rowHeight = 250 * scale;
  const rows = Math.ceil((SIZE * 0.75) / rowHeight) + 1; // enough rows to cover the turned square
  ctx.save();
  ctx.translate(SIZE / 2, SIZE / 2);
  ctx.rotate(-0.22);
  // Cover the rotated square, offsetting every other row
  for (let row = -rows, i = 0; row <= rows; row++, i++) {
    const y = row * rowHeight;
    for (let x = -SIZE * 1.2 + (i % 2 ? step / 2 : 0); x < SIZE * 1.2; x += step) ctx.fillText(text, x, y);
  }
  ctx.restore();
}

const cache = new Map();

/** The pattern's texture (cached), or null for plain paper */
export async function patternTexture(pattern, hex) {
  const p = normalizePattern(pattern);
  if (!p || typeof document === "undefined") return null;
  const key = p.type === "words" ? `w|${hex}|${p.text}|${p.size ?? 100}` : "n";
  if (cache.has(key)) return cache.get(key);

  if (p.type === "words") {
    try {
      await document.fonts?.load(`700 170px "Dancing Script"`);
    } catch (_) {
      // falls back to the browser's script font
    }
  }
  const canvas = document.createElement("canvas");
  canvas.width = canvas.height = SIZE;
  const ctx = canvas.getContext("2d");
  if (p.type === "words") drawWords(ctx, hex, p.text, p.size);
  else drawNewspaper(ctx);

  const texture = new THREE.CanvasTexture(canvas);
  texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.anisotropy = 4;
  texture.channel = 1; // the wrap's own "around and up" coordinates, see wrapUv
  if (cache.size > 20) cache.clear();
  cache.set(key, texture);
  return texture;
}

/**
 * Gives the wrap meshes a second set of texture coordinates that run around
 * the bouquet and up it, so a pattern wraps like paper whatever the model's
 * own coordinates are. The seam sits at the back. Done once per mesh.
 */
export function wrapUv(meshes) {
  if (!meshes.length) return;
  const box = new THREE.Box3();
  meshes.forEach((m) => {
    m.updateWorldMatrix(true, false);
    box.expandByObject(m);
  });
  const center = box.getCenter(new THREE.Vector3());
  const size = box.getSize(new THREE.Vector3());
  const height = size.y || 1;
  const around = Math.PI * Math.max(size.x, size.z, 1e-6); // circumference
  const turns = 2; // pattern repeats around the bouquet
  const tileUp = (around / turns) || height; // square tiles
  const v = new THREE.Vector3();

  meshes.forEach((mesh) => {
    if (mesh.userData.wrapUv) return;
    mesh.geometry = mesh.geometry.clone();
    const pos = mesh.geometry.attributes.position;
    const uv = new Float32Array(pos.count * 2);
    for (let i = 0; i < pos.count; i++) {
      v.fromBufferAttribute(pos, i).applyMatrix4(mesh.matrixWorld);
      const angle = Math.atan2(v.x - center.x, v.z - center.z); // 0 at the front
      uv[i * 2] = (angle / (Math.PI * 2) + 0.5) * turns;
      uv[i * 2 + 1] = (v.y - box.min.y) / tileUp;
    }
    mesh.geometry.setAttribute("uv1", new THREE.BufferAttribute(uv, 2));
    mesh.userData.wrapUv = true;
  });
}

const latest = new WeakMap();

/**
 * Puts a pattern on a wrap layer (null = plain). The words pattern is drawn on
 * `hex`. Only the newest call for a layer wins, so quick changes don't flicker.
 */
export async function applyWrapPattern(layer, pattern, hex) {
  if (!layer?.setPattern) return;
  const id = (latest.get(layer) || 0) + 1;
  latest.set(layer, id);
  const texture = await patternTexture(pattern, hex);
  if (latest.get(layer) === id) layer.setPattern(texture);
}

/** "Newspaper" / "Words: love" / "" for plain, for order screens */
export function patternLabel(pattern) {
  const p = normalizePattern(pattern);
  if (!p) return "";
  return p.type === "newspaper" ? "Newspaper print" : `Words: “${p.text}”`;
}

/** A small picture of the print (data URL), for forms and option lists; "" for plain paper */
export async function patternPreview(pattern, hex) {
  const texture = await patternTexture(pattern, hex);
  return texture?.image?.toDataURL?.("image/jpeg", 0.8) ?? "";
}
