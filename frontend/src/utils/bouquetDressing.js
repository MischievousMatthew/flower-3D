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

  // Bow on the front of the band (+Z sits at a quarter turn)
  const bow = new THREE.Group();
  const frontPoint = outlinePoint(SECTORS / 4);
  bow.position.copy(frontPoint).addScaledVector(front, bandHeight * 0.15);
  bow.quaternion.setFromRotationMatrix(new THREE.Matrix4().makeBasis(side, axis, front));

  const loopRadius = bandHeight * 1.35;
  [-1, 1].forEach((direction) => {
    const loop = new THREE.Mesh(new THREE.TorusGeometry(loopRadius, loopRadius * 0.34, 10, 40), material);
    loop.scale.set(1, 0.62, 0.28);
    loop.position.set(direction * loopRadius * 0.95, loopRadius * 0.12, 0);
    loop.rotation.set(0, direction * -0.35, direction * 0.28);
    bow.add(loop);

    const tail = new THREE.Mesh(new THREE.BoxGeometry(bandHeight * 0.85, loopRadius * 2.6, bandHeight * 0.08), material);
    tail.geometry.translate(0, -loopRadius * 1.3, 0);
    tail.rotation.set(-0.12, 0, direction * 0.32);
    tail.position.set(direction * bandHeight * 0.18, 0, -bandHeight * 0.05);
    bow.add(tail);
  });
  const knot = new THREE.Mesh(new THREE.SphereGeometry(bandHeight * 0.62, 20, 16), material);
  knot.scale.set(1, 0.9, 0.55);
  bow.add(knot);
  ribbon.add(bow);

  ribbon.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
  });
  root.add(ribbon);
  return ribbon;
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
  const ribbonMaterial = new THREE.MeshStandardMaterial({
    color: "#caa6d9",
    roughness: 0.42,
    metalness: 0.05,
    side: THREE.DoubleSide,
  });
  const ribbon = buildRibbon(root, [...outerMeshes, ...innerMeshes], ribbonMaterial);

  return {
    outer,
    inner,
    ribbon,
    wrapMeshes: [...outerMeshes, ...innerMeshes],
    applyRibbon(hex) {
      if (hex) ribbonMaterial.color.set(hex);
      if (ribbon) ribbon.visible = !!hex;
    },
  };
}
