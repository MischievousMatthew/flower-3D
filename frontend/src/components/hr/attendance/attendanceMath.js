// Pure math for the attendance face check. No browser APIs, so it can be tested with Node
// (tests/attendanceMath.test.mjs). The server makes the final decisions; see
// docs/superpowers/specs/2026-10-22-attendance-security-design.md

export const FLASH_RGB = { red: [255, 40, 40], green: [40, 255, 40], blue: [40, 80, 255] };
export const CHANNEL = { red: 0, green: 1, blue: 2 };

export const CHALLENGES = {
  blink: { label: "Blink twice", hint: "Close both eyes fully, then open them. Do it twice." },
  smile: { label: "Smile", hint: "Give a real smile and hold it." },
  turn_left: { label: "Turn your head left", hint: "Turn toward your left shoulder, then hold." },
  turn_right: { label: "Turn your head right", hint: "Turn toward your right shoulder, then hold." },
  nod: { label: "Nod your head", hint: "Move your head down and up." },
  move_closer: { label: "Move closer", hint: "Lean in toward the camera." },
  move_back: { label: "Move back", hint: "Lean away from the camera." },
};

export const dist2d = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

/** Eye aspect ratio from 6 eye points: about 0.3 open, under 0.2 closed */
export function eyeRatio(pts) {
  if (!pts || pts.length < 6) return 0.3;
  const h = dist2d(pts[0], pts[3]);
  return h === 0 ? 0 : (dist2d(pts[1], pts[5]) + dist2d(pts[2], pts[4])) / (2 * h);
}

/**
 * How far the nose sits from the middle of the eyes, as a share of eye distance.
 * Positive = nose toward the image's right. The camera faces the person, so when they
 * turn to their own left, the nose moves to the image's right.
 */
export function yaw(leftEye, rightEye, noseTip) {
  const centre = (pts) => pts.reduce((a, p) => ({ x: a.x + p.x / pts.length, y: a.y + p.y / pts.length }), { x: 0, y: 0 });
  const l = centre(leftEye);
  const r = centre(rightEye);
  const mid = { x: (l.x + r.x) / 2, y: (l.y + r.y) / 2 };
  const span = dist2d(l, r) || 1;
  return (noseTip.x - mid.x) / span;
}

/** Counts blinks from a stream of eye ratios: a clear dip below the recent open level, then back up. */
export function createBlinkCounter() {
  let open = null;
  let closed = false;
  let count = 0;
  return {
    push(ratio) {
      if (open === null) open = ratio;
      if (!closed && ratio < open * 0.72) closed = true;
      else if (closed && ratio > open * 0.88) {
        closed = false;
        count++;
      }
      if (!closed) open = open * 0.85 + ratio * 0.15;
      return count;
    },
    get count() {
      return count;
    },
  };
}

/**
 * Judges one challenge from a stream of face frames.
 * frame: { width (face box width), yaw, ratio (eye ratio), smile (0–1), noseY (share of box height) }
 */
export function createChallengeJudge(id, baseline) {
  const blinks = createBlinkCounter();
  let held = 0;
  let minNose = Infinity;
  let maxNose = -Infinity;
  const hold = (ok, frames = 4) => (held = ok ? held + 1 : 0) >= frames;

  return (f) => {
    switch (id) {
      case "blink":
        return blinks.push(f.ratio) >= 2;
      case "smile":
        return hold(f.smile > 0.6);
      case "turn_left":
        return hold(f.yaw > baseline.yaw + 0.22);
      case "turn_right":
        return hold(f.yaw < baseline.yaw - 0.22);
      case "move_closer":
        return hold(f.width > baseline.width * 1.15, 3);
      case "move_back":
        return hold(f.width < baseline.width * 0.87, 3);
      case "nod":
        minNose = Math.min(minNose, f.noseY);
        maxNose = Math.max(maxNose, f.noseY);
        return maxNose - minNose > 0.12;
      default:
        return false;
    }
  };
}

/** Mean of several face descriptors (128 numbers each) */
export function averageDescriptor(list) {
  const out = new Array(128).fill(0);
  for (const d of list) for (let i = 0; i < 128; i++) out[i] += d[i] / list.length;
  return out.map((v) => Math.round(v * 1e6) / 1e6);
}

export function descriptorDistance(a, b) {
  let s = 0;
  for (let i = 0; i < 128; i++) s += (a[i] - b[i]) ** 2;
  return Math.sqrt(s);
}

/** Change in average face color between the frame before a flash and during it */
export const colorDelta = (before, during) => [0, 1, 2].map((i) => Math.round((during[i] - before[i]) * 100) / 100);

/** How many flashes the face visibly reflected — the same rule the server applies. */
export function flashMatches(samples) {
  return samples.filter(({ color, delta }) => {
    const c = CHANNEL[color];
    if (c === undefined || !delta) return false;
    const others = delta.filter((_, i) => i !== c);
    return delta[c] > 1.5 && delta[c] > Math.max(...others);
  }).length;
}
