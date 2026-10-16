// Reads a flower photo's colors for the matched 3D build: up to three petal
// colors (most common first) and one leaf green. Pure, so it runs in tests.

const DEFAULT_FLOWER = "#c81e2d";
const DEFAULT_LEAF = "#4c8a3c";
const MAX_SAMPLES = 20000;

const toHex = ([r, g, b]) => `#${[r, g, b].map((c) => Math.round(c).toString(16).padStart(2, "0")).join("")}`;
const dist2 = (a, b) => (a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2 + (a[2] - b[2]) ** 2;

function hsl([r, g, b]) {
  const [R, G, B] = [r / 255, g / 255, b / 255];
  const max = Math.max(R, G, B), min = Math.min(R, G, B);
  const l = (max + min) / 2, c = max - min;
  const s = c === 0 ? 0 : c / (1 - Math.abs(2 * l - 1));
  let h = 0;
  if (c) {
    if (max === R) h = ((G - B) / c) % 6;
    else if (max === G) h = (B - R) / c + 2;
    else h = (R - G) / c + 4;
    h = (h * 60 + 360) % 360;
  }
  return { h, s, l };
}

const mean = (pixels) => {
  const sum = [0, 0, 0];
  for (const p of pixels) for (let i = 0; i < 3; i++) sum[i] += p[i];
  return sum.map((v) => v / Math.max(pixels.length, 1));
};

// Background = the typical color around the photo's edge
function borderColor({ data, width, height }) {
  const edge = [];
  const push = (x, y) => {
    const i = (y * width + x) * 4;
    if (data[i + 3] > 20) edge.push([data[i], data[i + 1], data[i + 2]]);
  };
  for (let x = 0; x < width; x++) { push(x, 0); push(x, height - 1); }
  for (let y = 0; y < height; y++) { push(0, y); push(width - 1, y); }
  return edge.length ? mean(edge) : [255, 255, 255];
}

// Small deterministic k-means: farthest-point start, a few rounds
function kmeans(pixels, k) {
  const centers = [pixels[0]];
  while (centers.length < k) {
    let best = null, bestD = -1;
    for (let i = 0; i < pixels.length; i += 7) {
      const d = Math.min(...centers.map((c) => dist2(c, pixels[i])));
      if (d > bestD) { bestD = d; best = pixels[i]; }
    }
    if (!best || bestD < 400) break;
    centers.push(best);
  }
  let groups = [];
  for (let round = 0; round < 8; round++) {
    groups = centers.map(() => []);
    for (const p of pixels) {
      let bi = 0, bd = Infinity;
      centers.forEach((c, i) => { const d = dist2(c, p); if (d < bd) { bd = d; bi = i; } });
      groups[bi].push(p);
    }
    groups.forEach((g, i) => { if (g.length) centers[i] = mean(g); });
  }
  return centers.map((c, i) => ({ color: c, size: groups[i].length })).filter((g) => g.size);
}

/**
 * @param {{ data: Uint8ClampedArray, width: number, height: number }} image RGBA pixels
 * @returns {{ flowers: string[], leaf: string }} hex colors
 */
export function extractPalette(image) {
  const { data, width, height } = image;
  const bg = borderColor(image);
  const step = Math.max(1, Math.floor((width * height) / MAX_SAMPLES));
  const flowers = [];
  const leaves = [];

  for (let p = 0; p < width * height; p += step) {
    const i = p * 4;
    if (data[i + 3] < 128) continue;
    const rgb = [data[i], data[i + 1], data[i + 2]];
    if (dist2(rgb, bg) < 45 ** 2) continue; // background
    const { h, s, l } = hsl(rgb);
    if (l < 0.08 || (l < 0.2 && s < 0.25)) continue; // deep shadow
    if (l > 0.95 && s < 0.2 && hsl(bg).l > 0.85) continue; // glare on a light backdrop
    if (h >= 70 && h <= 170 && s > 0.18 && l > 0.1 && l < 0.75) leaves.push(rgb);
    else flowers.push(rgb);
  }

  let flowerColors = [];
  if (flowers.length) {
    const clusters = kmeans(flowers, 3).sort((a, b) => b.size - a.size);
    const total = clusters.reduce((n, c) => n + c.size, 0);
    for (const c of clusters) {
      if (c.size / total < 0.12) continue;
      if (flowerColors.some((f) => dist2(f, c.color) < 40 ** 2)) continue;
      flowerColors.push(c.color);
    }
  }

  return {
    flowers: flowerColors.length ? flowerColors.map(toHex) : [DEFAULT_FLOWER],
    leaf: leaves.length ? toHex(mean(leaves)) : DEFAULT_LEAF,
  };
}
