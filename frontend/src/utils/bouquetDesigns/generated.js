// The four bouquet designs built in code. Each returns the same shape as the
// classic one: { root, dressing, targets, defaults }. Built standing on the
// floor (y = 0), centred, at the classic bouquet's normalized size.
import * as THREE from "three";
import { emptyLayer, finish, grain, makeBed, makeLayer, makeRibbon, seeded, surface } from "./common.js";

function result(root, { outer, inner, ribbon, wrapMeshes, bed, defaults }) {
  finish(root);
  return {
    root,
    dressing: {
      outer,
      inner,
      wrapMeshes,
      applyRibbon: (hex) => ribbon?.apply(hex),
    },
    targets: [bed, ...wrapMeshes],
    defaults,
  };
}

// ── Korean layered wrap ──────────────────────────────────────────────────
// Sheets of paper fan out from a tied neck in two layers, open and airy.

// Sheet radius at height y: a point at the bottom, the tied neck, then a wide flare
function koreanProfile(y, { neckY, neckR, topY, topR, tipR }) {
  if (y <= neckY) return THREE.MathUtils.lerp(tipR, neckR, Math.pow(y / neckY, 0.7));
  const t = (y - neckY) / (topY - neckY);
  return neckR + (topR - neckR) * Math.pow(t, 0.85);
}

function koreanSheet({ angle, span, topY, topR, neckY, neckR, lean, cup, wave, seed }) {
  const g = new THREE.PlaneGeometry(1, 1, 12, 22);
  const pos = g.attributes.position;
  const rand = seeded(seed);
  const phase = rand() * Math.PI * 2;
  for (let i = 0; i < pos.count; i++) {
    const u = pos.getX(i); // -0.5..0.5 across
    const v = pos.getY(i) + 0.5; // 0..1 up
    // Rounded top edge with a soft wave; corners fall away like real paper
    const yTop = topY * (1 - 0.16 * Math.pow(Math.abs(u) * 2, 2)) + Math.sin(u * 7 + phase) * wave;
    const y = v * yTop;
    const above = Math.max(0, (y - neckY) / (topY - neckY));
    let r = koreanProfile(y, { neckY, neckR, topY, topR, tipR: 0.05 });
    // Each sheet leans out on its own and is cupped across its width, so the
    // layers read as separate sheets rather than one smooth cone
    r += Math.pow(above, 2.2) * lean + (1 - Math.pow(Math.abs(u) * 2, 2)) * cup * above;
    const theta = angle + u * span * (y > neckY ? 1 : 0.5 + 0.5 * (y / neckY));
    pos.setXYZ(i, r * Math.sin(theta), y, r * Math.cos(theta));
  }
  g.computeVertexNormals();
  return grain(g, { seed });
}

export function buildKorean() {
  const root = new THREE.Group();
  root.name = "KoreanWrap";
  const neckY = 0.95;
  const outerMat = surface({ name: "OuterPaper" });
  const innerMat = surface({ name: "InnerPaper" });
  const rand = seeded(41);

  // Outer layer: 6 sheets, staggered heights, leaning well out
  const outer = [];
  for (let i = 0; i < 6; i++) {
    const tall = i % 2 === 0;
    outer.push(
      new THREE.Mesh(
        koreanSheet({
          angle: (i / 6) * Math.PI * 2 + (rand() - 0.5) * 0.15,
          span: 1.05,
          topY: tall ? 2.55 : 2.35,
          topR: 1.05,
          neckY,
          neckR: 0.27,
          lean: 0.55,
          cup: -0.12,
          wave: 0.05,
          seed: 11 + i,
        }),
        outerMat,
      ),
    );
  }
  // Inner layer: 5 taller, more upright sheets in the second colour
  const inner = [];
  for (let i = 0; i < 5; i++) {
    inner.push(
      new THREE.Mesh(
        koreanSheet({
          angle: (i / 5) * Math.PI * 2 + 0.5,
          span: 1.1,
          topY: 2.85,
          topR: 0.92,
          neckY,
          neckR: 0.22,
          lean: 0.3,
          cup: -0.08,
          wave: 0.06,
          seed: 31 + i,
        }),
        innerMat,
      ),
    );
  }
  root.add(...outer, ...inner);

  // The bed sits high so the flowers rise above the paper
  const bed = makeBed({ y: 2.45, radius: 0.85, height: 0.45, seed: 5 });
  root.add(bed);
  const ribbon = makeRibbon({ y: neckY, radius: 0.3, band: 0.14, bowSize: 0.42 });
  root.add(ribbon.group);

  const defaults = { outer: "#d9c2a7", inner: "#f1d4da", ribbon: "#d95f76" };
  return result(root, {
    outer: makeLayer(outer, defaults.outer),
    inner: makeLayer(inner, defaults.inner),
    ribbon,
    wrapMeshes: [...outer, ...inner],
    bed,
    defaults,
  });
}

// ── Round hand-tied ──────────────────────────────────────────────────────
// A dome of flowers on bundled stems with a satin wrap and bow. No paper.

export function buildHandTied() {
  const root = new THREE.Group();
  root.name = "HandTied";
  const rand = seeded(23);
  const stemMat = new THREE.MeshStandardMaterial({ color: "#5d7d43", roughness: 0.7, name: "Stems" });
  const waistY = 0.95;
  const stems = [];
  for (let i = 0; i < 22; i++) {
    const a = (i / 22) * Math.PI * 2 + rand() * 0.3;
    const r = 0.06 + rand() * 0.1;
    const curve = new THREE.CatmullRomCurve3([
      new THREE.Vector3(Math.cos(a + 0.6) * (r + 0.12), 0.03, Math.sin(a + 0.6) * (r + 0.12)),
      new THREE.Vector3(Math.cos(a) * r, waistY, Math.sin(a) * r),
      // Tops end under the flower dome, not out past its edge
      new THREE.Vector3(Math.cos(a - 0.3) * (r * 3 + 0.22), 1.68, Math.sin(a - 0.3) * (r * 3 + 0.22)),
    ]);
    const tube = new THREE.TubeGeometry(curve, 20, 0.028, 6, false);
    stems.push(new THREE.Mesh(tube, stemMat));
  }
  root.add(...stems);
  // Cut stem ends: a flat cap so the bundle looks trimmed
  const cut = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.26, 0.04, 24), new THREE.MeshStandardMaterial({ color: "#8aa86a", roughness: 0.8 }));
  cut.position.y = 0.02; // its 0.04 height reaches the floor
  root.add(cut);

  const bed = makeBed({ y: 1.7, radius: 0.82, height: 0.6, seed: 9 });
  root.add(bed);

  // Satin wrapped down the stems, then a bow
  const ribbon = makeRibbon({ y: waistY, radius: 0.22, band: 0.34, bowSize: 0.46 });
  root.add(ribbon.group);

  const defaults = { outer: "#ffffff", inner: "#ffffff", ribbon: "#f4efe6" };
  return result(root, {
    outer: emptyLayer(defaults.outer),
    inner: emptyLayer(defaults.inner),
    ribbon,
    wrapMeshes: [],
    bed,
    defaults,
  });
}

// ── Flower box ───────────────────────────────────────────────────────────
// A round hat box, flowers rising above the rim, satin band and bow.

export function buildBox() {
  const root = new THREE.Group();
  root.name = "FlowerBox";
  const R = 1.08;
  const H = 1.35;
  const boxMat = surface({ name: "Box", roughness: 0.6 });
  const linerMat = surface({ name: "BoxLiner", roughness: 0.9 });

  const wall = new THREE.Mesh(grain(new THREE.CylinderGeometry(R, R, H, 64, 1, true), { amount: 0.03 }), boxMat);
  wall.position.y = H / 2;
  const bottom = new THREE.Mesh(grain(new THREE.CircleGeometry(R, 64), { amount: 0.03 }), boxMat);
  bottom.rotation.x = -Math.PI / 2;
  bottom.position.y = 0.002;
  const rim = new THREE.Mesh(grain(new THREE.TorusGeometry(R, 0.035, 10, 64), { amount: 0.03 }), boxMat);
  rim.rotation.x = Math.PI / 2;
  rim.position.y = H;
  const liner = new THREE.Mesh(grain(new THREE.CylinderGeometry(R - 0.03, R - 0.03, H - 0.04, 64, 1, true), { amount: 0.05 }), linerMat);
  liner.position.y = H / 2;
  root.add(wall, bottom, rim, liner);

  const bed = makeBed({ y: H - 0.05, radius: R - 0.04, height: 0.45, seed: 13 });
  root.add(bed);
  const ribbon = makeRibbon({ y: H * 0.5, radius: R + 0.012, band: 0.2, bowSize: 0.42 });
  root.add(ribbon.group);

  const defaults = { outer: "#efe6dc", inner: "#c9a3ad", ribbon: "#b03a52" };
  return result(root, {
    outer: makeLayer([wall, bottom, rim], defaults.outer),
    inner: makeLayer([liner], defaults.inner),
    ribbon,
    wrapMeshes: [wall, bottom, rim, liner],
    bed,
    defaults,
  });
}

// ── Basket ───────────────────────────────────────────────────────────────
// A woven basket with an arched handle.

function wovenBowl({ bottomR, topR, height, segments = 96, rows = 22 }) {
  const profile = [];
  for (let i = 0; i <= rows; i++) {
    const t = i / rows;
    profile.push(new THREE.Vector2(bottomR + (topR - bottomR) * Math.pow(t, 0.8), t * height));
  }
  const g = new THREE.LatheGeometry(profile, segments);
  const pos = g.attributes.position;
  const colors = new Float32Array(pos.count * 3);
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i);
    const a = Math.atan2(z, x);
    const row = Math.floor((y / height) * rows);
    // Over-under weave: strands bulge out alternately
    const weave = Math.sin(a * 28 + (row % 2) * Math.PI) * 0.018;
    const k = 1 + weave / Math.hypot(x, z);
    pos.setXYZ(i, x * k, y, z * k);
    const shade = 0.78 + 0.22 * (0.5 + 0.5 * Math.sin(a * 28 + (row % 2) * Math.PI)) - (row % 3 === 0 ? 0.05 : 0);
    colors.set([shade, shade, shade], i * 3);
  }
  g.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  g.computeVertexNormals();
  return g;
}

export function buildBasket() {
  const root = new THREE.Group();
  root.name = "Basket";
  const H = 1.1;
  const topR = 1.15;
  const basketMat = surface({ name: "Basket", roughness: 0.9 });
  const linerMat = surface({ name: "BasketLiner", roughness: 0.95 });

  const bowl = new THREE.Mesh(wovenBowl({ bottomR: 0.78, topR, height: H }), basketMat);
  const base = new THREE.Mesh(grain(new THREE.CircleGeometry(0.8, 48), { amount: 0.1 }), basketMat);
  base.rotation.x = -Math.PI / 2;
  base.position.y = 0.002;
  const rim = new THREE.Mesh(grain(new THREE.TorusGeometry(topR + 0.01, 0.06, 10, 64), { amount: 0.15 }), basketMat);
  rim.rotation.x = Math.PI / 2;
  rim.position.y = H;
  const handleCurve = new THREE.CatmullRomCurve3([
    new THREE.Vector3(-topR + 0.02, H, 0),
    new THREE.Vector3(-topR * 0.85, H + 1.1, 0),
    new THREE.Vector3(0, H + 1.75, 0),
    new THREE.Vector3(topR * 0.85, H + 1.1, 0),
    new THREE.Vector3(topR - 0.02, H, 0),
  ]);
  const handle = new THREE.Mesh(grain(new THREE.TubeGeometry(handleCurve, 64, 0.055, 10, false), { amount: 0.15 }), basketMat);
  // Liner: a soft cloth just inside the rim
  const liner = new THREE.Mesh(
    grain(new THREE.CylinderGeometry(topR - 0.02, topR * 0.9, 0.22, 64, 1, true), { amount: 0.08 }),
    linerMat,
  );
  liner.position.y = H - 0.08;
  root.add(bowl, base, rim, handle, liner);

  const bed = makeBed({ y: H - 0.05, radius: topR - 0.06, height: 0.42, seed: 17 });
  root.add(bed);
  // A bow where the handle meets the rim (shown only if the occasion has a ribbon)
  const ribbon = makeRibbon({ y: H + 0.3, radius: 0.06, band: 0.08, bowSize: 0.3, front: new THREE.Vector3(1, 0, 0.0001) });
  ribbon.group.position.x = -topR * 0.92;
  root.add(ribbon.group);

  const defaults = { outer: "#b98a55", inner: "#f1ebe1", ribbon: "#8a9a6b" };
  return result(root, {
    outer: makeLayer([bowl, base, rim, handle], defaults.outer),
    inner: makeLayer([liner], defaults.inner),
    ribbon,
    wrapMeshes: [bowl, base, rim, handle, liner],
    bed,
    defaults,
  });
}
