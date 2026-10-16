// Turns the editor's changes into a copy that can be saved as a .glb:
// rotation and size go on a wrapper node, brightness is written into the
// colors themselves (vertex colors, material colors and textures), because the
// shop's viewer has its own lights and knows nothing about our slider.
import * as THREE from "three";

const clamp01 = (v) => Math.min(Math.max(v, 0), 1);

function brightenTexture(texture, k) {
  const image = texture?.image;
  if (!image || typeof document === "undefined") return texture;
  const w = image.width, h = image.height;
  if (!w || !h) return texture;
  const canvas = document.createElement("canvas");
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext("2d");
  ctx.filter = `brightness(${k})`;
  ctx.drawImage(image, 0, 0, w, h);
  const out = new THREE.CanvasTexture(canvas);
  out.colorSpace = texture.colorSpace;
  out.flipY = texture.flipY;
  out.wrapS = texture.wrapS;
  out.wrapT = texture.wrapT;
  out.channel = texture.channel;
  return out;
}

/**
 * @param {THREE.Object3D} source the model as shown in the editor (not modified)
 * @param {{ rotation?: THREE.Euler, scale?: number | THREE.Vector3, brightness?: number }} edits
 * @returns {THREE.Group} a wrapper group holding an edited copy
 */
export function bakeEdits(source, { rotation = new THREE.Euler(), scale = 1, brightness = 1 } = {}) {
  const copy = source.clone(true);
  const k = Math.max(brightness, 0);

  copy.traverse((child) => {
    if (!child.isMesh) return;
    // Own copies, so the editor's model keeps its original data
    child.geometry = child.geometry.clone();
    const colors = child.geometry.attributes.color;
    if (colors && k !== 1) {
      for (let i = 0; i < colors.count; i++) {
        colors.setXYZ(i, clamp01(colors.getX(i) * k), clamp01(colors.getY(i) * k), clamp01(colors.getZ(i) * k));
      }
      colors.needsUpdate = true;
    }
    const list = Array.isArray(child.material) ? child.material : [child.material];
    const edited = list.map((m) => {
      const mat = m.clone();
      if (k !== 1) {
        // Vertex-colored meshes already got the brightness above
        if (!colors) mat.color?.setRGB(clamp01(mat.color.r * k), clamp01(mat.color.g * k), clamp01(mat.color.b * k));
        if (mat.map) mat.map = brightenTexture(mat.map, k);
      }
      return mat;
    });
    child.material = Array.isArray(child.material) ? edited : edited[0];
  });

  const holder = new THREE.Group();
  holder.name = "Model";
  holder.rotation.copy(rotation);
  if (typeof scale === "number") holder.scale.setScalar(scale);
  else holder.scale.copy(scale); // Blender-style per-axis stretch
  holder.add(copy);
  const root = new THREE.Group();
  root.name = "EditedModel";
  root.add(holder);
  return root;
}
