import * as THREE from "three";

/*
 * Wrapper and ribbon handling for the bouquet base model (/Boquet.glb).
 *
 * The model has two paper layers and no ribbon:
 *   - outer wrap: the textured kraft paper (material "paper04_05_06")
 *   - inner wrap: the plain pink paper (material "Pink")
 * Everything else (leaves, daisies, lavender, small plants) must keep its
 * own colours, so only these two layers are ever tinted. The ribbon is
 * generated here and tied around the narrowest part of the wrap.
 */

const OUTER_MATCH = /paper|kraft|wrap.*outer|outer.*wrap/i;
const INNER_MATCH = /^pink$|wrap.*inner|inner.*wrap|tissue/i;

const materialsOf = (mesh) => (Array.isArray(mesh.material) ? mesh.material : [mesh.material]).filter(Boolean);

function classifyMesh(mesh) {
  const names = [mesh.name, ...materialsOf(mesh).map((material) => material.name)].join(" ");
  if (INNER_MATCH.test(materialsOf(mesh).map((material) => material.name).join(" ")) || /inner/i.test(mesh.name)) return "inner";
  if (OUTER_MATCH.test(names)) return "outer";
  return null;
}

// Each wrap layer gets its own material copies so tinting can never leak
// onto another mesh that happens to share a material.
function createLayer(meshes) {
  const entries = [];
  meshes.forEach((mesh) => {
    const cloned = materialsOf(mesh).map((material) => {
      const copy = material.clone();
      entries.push({ material: copy, color: copy.color.clone(), map: copy.map, tintMap: null });
      return copy;
    });
    mesh.material = Array.isArray(mesh.material) ? cloned : cloned[0];
  });

  return {
    meshes,
    // null restores the colour the model shipped with
    apply(hex) {
      entries.forEach((entry) => {
        const { material } = entry;
        if (!hex) {
          material.color.copy(entry.color);
          material.map = entry.map;
        } else {
          if (entry.map && !entry.tintMap) entry.tintMap = createTintableMap(entry.map);
          material.map = entry.tintMap || entry.map;
          material.color.set(hex);
        }
        material.needsUpdate = true;
      });
    },
  };
}

// A light greyscale copy of the paper texture, so a tint shows its true
// colour while the paper grain stays visible.
function createTintableMap(texture) {
  const image = texture.image;
  const width = image?.width;
  const height = image?.height;
  if (!width || !height || typeof document === "undefined") return null;

  const size = Math.min(1024, Math.max(width, height));
  const canvas = document.createElement("canvas");
  canvas.width = Math.round((width / Math.max(width, height)) * size);
  canvas.height = Math.round((height / Math.max(width, height)) * size);
  const context = canvas.getContext("2d", { willReadFrequently: true });
  try {
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
    const data = pixels.data;
    let total = 0;
    for (let i = 0; i < data.length; i += 4) total += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
    const mean = total / (data.length / 4) || 1;
    const gain = 236 / mean;
    for (let i = 0; i < data.length; i += 4) {
      const value = Math.min(255, (0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]) * gain);
      data[i] = data[i + 1] = data[i + 2] = value;
    }
    context.putImageData(pixels, 0, 0);
  } catch {
    return null;
  }

  const tinted = new THREE.CanvasTexture(canvas);
  tinted.flipY = texture.flipY;
  tinted.wrapS = texture.wrapS;
  tinted.wrapT = texture.wrapT;
  tinted.repeat.copy(texture.repeat);
  tinted.offset.copy(texture.offset);
  tinted.rotation = texture.rotation;
  tinted.center.copy(texture.center);
  tinted.channel = texture.channel;
  tinted.colorSpace = texture.colorSpace;
  tinted.anisotropy = texture.anisotropy;
  return tinted;
}

/*
 * Ribbon: find the wrap's long axis, look for its narrowest ring in the
 * lower half, and wrap a flat band around the real outline there with a
 * bow facing the front (+Z). Built in the bouquet root's local space so it
 * moves and scales with the bouquet.
 */
function buildRibbon(root, wrapMeshes, material) {
  root.updateMatrixWorld(true);
  const toRoot = new THREE.Matrix4().copy(root.matrixWorld).invert();
  const points = [];
  const vertex = new THREE.Vector3();
  const matrix = new THREE.Matrix4();

  wrapMeshes.forEach((mesh) => {
    const position = mesh.geometry?.attributes?.position;
    if (!position) return;
    matrix.multiplyMatrices(toRoot, mesh.matrixWorld);
    const step = Math.max(1, Math.floor(position.count / 6000));
    for (let i = 0; i < position.count; i += step) {
      points.push(vertex.fromBufferAttribute(position, i).applyMatrix4(matrix).clone());
    }
  });
  if (points.length < 50) return null;

  const centroid = points.reduce((sum, point) => sum.add(point), new THREE.Vector3()).divideScalar(points.length);
  const axis = principalAxis(points, centroid);
  if (axis.y < 0) axis.negate(); // point towards the flowers

  // Plane basis around the axis, with "front" as close to +Z as possible
  const front = new THREE.Vector3(0, 0, 1).projectOnPlane(axis);
  if (front.lengthSq() < 1e-6) front.set(1, 0, 0).projectOnPlane(axis);
  front.normalize();
  const side = new THREE.Vector3().crossVectors(axis, front).normalize();

  let minT = Infinity;
  let maxT = -Infinity;
  const samples = points.map((point) => {
    const offset = point.clone().sub(centroid);
    const t = offset.dot(axis);
    minT = Math.min(minT, t);
    maxT = Math.max(maxT, t);
    return { t, x: offset.dot(side), z: offset.dot(front) };
  });
  const length = maxT - minT;
  if (!(length > 0)) return null;

  // Narrowest ring between 15% and 55% of the wrap height
  const BINS = 40;
  const bins = Array.from({ length: BINS }, () => []);
  samples.forEach((sample) => {
    const index = Math.min(BINS - 1, Math.floor(((sample.t - minT) / length) * BINS));
    bins[index].push(sample);
  });
  let best = -1;
  let bestRadius = Infinity;
  for (let index = Math.floor(BINS * 0.15); index <= Math.floor(BINS * 0.55); index += 1) {
    const ring = bins[index];
    if (ring.length < 12) continue;
    const cx = ring.reduce((sum, s) => sum + s.x, 0) / ring.length;
    const cz = ring.reduce((sum, s) => sum + s.z, 0) / ring.length;
    const distances = ring.map((s) => Math.hypot(s.x - cx, s.z - cz)).sort((a, b) => a - b);
    const radius = distances[Math.floor(distances.length * 0.9)];
    if (radius < bestRadius) {
      bestRadius = radius;
      best = index;
    }
  }
  if (best < 0) return null;

  const bandHeight = length * 0.06;
  const ringT = minT + ((best + 0.5) / BINS) * length;
  const ring = samples.filter((sample) => Math.abs(sample.t - ringT) <= bandHeight);
  const cx = ring.reduce((sum, s) => sum + s.x, 0) / ring.length;
  const cz = ring.reduce((sum, s) => sum + s.z, 0) / ring.length;

  // Outline of the wrap at that height: outermost point per angle sector
  const SECTORS = 64;
  const radii = new Array(SECTORS).fill(0);
  ring.forEach((sample) => {
    const angle = Math.atan2(sample.z - cz, sample.x - cx);
    const index = ((Math.round((angle / (Math.PI * 2)) * SECTORS) % SECTORS) + SECTORS) % SECTORS;
    radii[index] = Math.max(radii[index], Math.hypot(sample.x - cx, sample.z - cz));
  });
  fillGaps(radii);
  const smooth = radii.map((_, i) => (radii[(i + SECTORS - 1) % SECTORS] + radii[i] * 2 + radii[(i + 1) % SECTORS]) / 4);
  const lift = bestRadius * 0.04 + length * 0.004;

  const ringCenter = centroid.clone().addScaledVector(axis, ringT).addScaledVector(side, cx).addScaledVector(front, cz);
  const outlinePoint = (index) => {
    const angle = (index / SECTORS) * Math.PI * 2;
    const radius = smooth[index % SECTORS] + lift;
    return ringCenter.clone().addScaledVector(side, Math.cos(angle) * radius).addScaledVector(front, Math.sin(angle) * radius);
  };

  // Flat band following the outline
  const positions = [];
  const indices = [];
  const half = axis.clone().multiplyScalar(bandHeight / 2);
  for (let i = 0; i <= SECTORS; i += 1) {
    const point = outlinePoint(i);
    positions.push(...point.clone().sub(half).toArray(), ...point.clone().add(half).toArray());
    if (i < SECTORS) {
      const a = i * 2;
      indices.push(a, a + 2, a + 1, a + 1, a + 2, a + 3);
    }
  }
  const bandGeometry = new THREE.BufferGeometry();
  bandGeometry.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
  bandGeometry.setIndex(indices);
  bandGeometry.computeVertexNormals();

  const ribbon = new THREE.Group();
  ribbon.name = "GeneratedRibbon";
  ribbon.add(new THREE.Mesh(bandGeometry, material));

  // Satin bow on the front of the band (+Z sits at a quarter turn): two
  // flattened loops, a wrapped knot and two long tails with angled cuts.
  // Bow space: x = side, y = up the wrap, z = out of the wrap.
  const width = bandHeight;
  const bowLift = width * 0.35;
  const frontPoint = outlinePoint(SECTORS / 4);
  const bow = new THREE.Group();
  bow.position.copy(frontPoint).addScaledVector(front, bowLift);
  bow.quaternion.setFromRotationMatrix(new THREE.Matrix4().makeBasis(side, axis, front));

  // Each loop runs out along the front, folds at the tip and comes back
  // behind itself, pinched where it meets the knot.
  const loopLength = width * 3.1;
  const loopDepth = width * 0.42;
  const loopGeometry = ribbonStrip(
    (u) => {
      const angle = u * Math.PI * 2;
      const reach = (1 - Math.cos(angle)) / 2; // 0 at the knot, 1 at the tip
      return {
        point: new THREE.Vector3(loopLength * reach, width * 0.5 * reach, loopDepth * Math.sin(angle) * (0.35 + 0.65 * reach)),
        width: UP,
        half: (width / 2) * (0.4 + 0.6 * Math.sqrt(reach)),
      };
    },
    { rows: 72, cup: 0.18 },
  );
  loopGeometry.rotateY(0.15); // tips lean back towards the wrap
  [-1, 1].forEach((direction) => {
    const loop = new THREE.Mesh(loopGeometry, material);
    loop.scale.x = direction;
    bow.add(loop);
  });

  const knotGeometry = ribbonStrip(
    (u) => {
      const angle = u * Math.PI * 2;
      return {
        point: new THREE.Vector3(0, Math.cos(angle) * width * 0.36, Math.sin(angle) * width * 0.3 + width * 0.05),
        width: RIGHT,
        half: width * 0.36,
      };
    },
    { rows: 32, cup: 0.25 },
  );
  bow.add(new THREE.Mesh(knotGeometry, material));

  // Tails hang down the wrap and are kept just in front of its surface.
  const bowZ = cz + smooth[SECTORS / 4] + lift + bowLift;
  const clearance = lift + width * 0.05;
  const surfaceZ = (x, y) => {
    let highest = -Infinity;
    samples.forEach((sample) => {
      if (Math.abs(sample.t - (ringT + y)) < width * 0.5 && Math.abs(sample.x - (cx + x)) < width * 0.6) {
        highest = Math.max(highest, sample.z);
      }
    });
    return highest - bowZ;
  };
  const tailLength = Math.min(width * 7.5, (ringT - minT) * 0.85);
  [
    { direction: -1, spread: 0.05, sway: 0.18, twist: 0.55, phase: 0.4, reach: 1 },
    { direction: 1, spread: 0.16, sway: 0.25, twist: -0.95, phase: 1.6, reach: 0.9 },
  ].forEach(({ direction, spread, sway, twist, phase, reach }) => {
    const tailDrop = tailLength * reach;
    const STEPS = 20;
    const path = [];
    for (let i = 0; i <= STEPS; i += 1) {
      const s = i / STEPS;
      const x = direction * (width * 0.1 + tailDrop * spread * s) + Math.sin(s * Math.PI * 1.4 + phase) * width * sway * s;
      const y = -width * 0.1 - tailDrop * s;
      const z = Math.max(width * 0.18 * (1 - s) ** 2, surfaceZ(x, y) + clearance);
      path.push(new THREE.Vector3(x, y, z));
    }
    const curve = new THREE.CatmullRomCurve3(path);
    const tailGeometry = ribbonStrip(
      (u) => {
        const tangent = curve.getTangentAt(u);
        return {
          point: curve.getPointAt(u),
          width: RIGHT.clone().projectOnPlane(tangent).normalize().applyAxisAngle(tangent, twist * Math.sin(u * Math.PI * 0.85)),
          half: width * 0.45,
        };
      },
      { rows: 64, cup: 0.12, cut: (width * 0.9) / tailDrop },
    );
    bow.add(new THREE.Mesh(tailGeometry, material));
  });
  ribbon.add(bow);

  ribbon.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
  });
  root.add(ribbon);
  return ribbon;
}

const UP = new THREE.Vector3(0, 1, 0);
const RIGHT = new THREE.Vector3(1, 0, 0);

/*
 * Sweep a ribbon strip along a path. frame(u) gives, for u in 0..1, the
 * centre point, the direction across the ribbon and half its width. cup
 * curves the strip slightly across its width so satin catches the light;
 * cut trims the far end diagonally (as a fraction of the length).
 */
function ribbonStrip(frame, { rows = 48, cols = 4, cup = 0, cut = 0 } = {}) {
  const positions = [];
  const uvs = [];
  const indices = [];
  const tangent = new THREE.Vector3();
  const normal = new THREE.Vector3();
  const step = 1 / (rows * 4);
  for (let i = 0; i <= rows; i += 1) {
    for (let j = 0; j <= cols; j += 1) {
      const v = (j / cols) * 2 - 1;
      const u = Math.max(0, Math.min(i / rows, 1 - (cut * (v + 1)) / 2));
      const { point, width, half } = frame(u);
      tangent.subVectors(frame(Math.min(1, u + step)).point, frame(Math.max(0, u - step)).point).normalize();
      normal.crossVectors(tangent, width).normalize();
      const vertex = point.clone().addScaledVector(width, half * v).addScaledVector(normal, cup * half * (1 - v * v));
      positions.push(vertex.x, vertex.y, vertex.z);
      uvs.push(j / cols, u);
      if (i < rows && j < cols) {
        const a = i * (cols + 1) + j;
        const b = a + cols + 1;
        indices.push(a, b, a + 1, a + 1, b, b + 1);
      }
    }
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
  geometry.setAttribute("uv", new THREE.Float32BufferAttribute(uvs, 2));
  geometry.setIndex(indices);
  geometry.computeVertexNormals();
  return geometry;
}

function principalAxis(points, centroid) {
  const c = [0, 0, 0, 0, 0, 0]; // xx xy xz yy yz zz
  points.forEach((point) => {
    const x = point.x - centroid.x;
    const y = point.y - centroid.y;
    const z = point.z - centroid.z;
    c[0] += x * x; c[1] += x * y; c[2] += x * z; c[3] += y * y; c[4] += y * z; c[5] += z * z;
  });
  const axis = new THREE.Vector3(0, 1, 0);
  for (let i = 0; i < 24; i += 1) {
    const { x, y, z } = axis;
    axis.set(c[0] * x + c[1] * y + c[2] * z, c[1] * x + c[3] * y + c[4] * z, c[2] * x + c[4] * y + c[5] * z).normalize();
  }
  return axis;
}

function fillGaps(values) {
  const count = values.length;
  const known = values.map((value, index) => (value > 0 ? index : -1)).filter((index) => index >= 0);
  if (!known.length) return;
  for (let i = 0; i < count; i += 1) {
    if (values[i] > 0) continue;
    let previous = i;
    let next = i;
    while (values[(previous + count) % count] <= 0) previous -= 1;
    while (values[next % count] <= 0) next += 1;
    const a = values[(previous + count) % count];
    const b = values[next % count];
    values[i] = a + ((b - a) * (i - previous)) / (next - previous);
  }
}

/**
 * Set up the wrap layers and ribbon on a loaded bouquet. Call after the
 * bouquet root has its final scale and position.
 */
export function dressBouquet(root) {
  const outerMeshes = [];
  const innerMeshes = [];
  root.traverse((child) => {
    if (!child.isMesh) return;
    const layer = classifyMesh(child);
    if (layer === "outer") outerMeshes.push(child);
    if (layer === "inner") innerMeshes.push(child);
  });

  const outer = createLayer(outerMeshes);
  const inner = createLayer(innerMeshes);
  // Satin: low roughness plus a sheen tinted towards white
  const ribbonMaterial = new THREE.MeshPhysicalMaterial({
    color: "#caa6d9",
    roughness: 0.34,
    metalness: 0,
    sheen: 0.7,
    sheenRoughness: 0.32,
    clearcoat: 0.2,
    clearcoatRoughness: 0.45,
    side: THREE.DoubleSide,
  });
  const tintSheen = () => ribbonMaterial.sheenColor.copy(ribbonMaterial.color).lerp(new THREE.Color("#ffffff"), 0.55);
  tintSheen();
  const ribbon = buildRibbon(root, [...outerMeshes, ...innerMeshes], ribbonMaterial);

  return {
    outer,
    inner,
    ribbon,
    wrapMeshes: [...outerMeshes, ...innerMeshes],
    applyRibbon(hex) {
      if (hex) {
        ribbonMaterial.color.set(hex);
        tintSheen();
      }
      if (ribbon) ribbon.visible = !!hex;
    },
  };
}
