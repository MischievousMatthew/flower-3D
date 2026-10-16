// Run with: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import * as THREE from "three";
import { removeSmallPieces, cleanObject } from "../src/components/vendor/photo3d/meshCleanup.js";
import { bakeEdits } from "../src/components/vendor/photo3d/bakeEdits.js";

// A closed box made of `size`-wide triangles, offset along x
function boxPiece(offsetX, segments) {
  const g = new THREE.BoxGeometry(1, 1, 1, segments, segments, segments);
  g.translate(offsetX, 0, 0);
  return g;
}

test("removeSmallPieces drops a tiny loose fragment and keeps the main shape", () => {
  // Main shape: 8×8 box (768 triangles); fragment: a single triangle
  const index = [];
  const main = { tris: 0 };
  // Two separate triangle fans sharing no vertices
  for (let i = 0; i < 300; i++) { index.push(0, i + 1, i + 2); main.tris++; } // vertices 0..301
  index.push(400, 401, 402); // the stray fragment
  const out = removeSmallPieces(index, 403);
  assert.equal(out.removedTriangles, 1);
  assert.equal(out.index.length, main.tris * 3);
  assert.ok(!Array.from(out.index).includes(400));
});

test("removeSmallPieces keeps small real parts like a leaf, and drops the sliver", () => {
  const index = [];
  for (let i = 0; i < 300; i++) index.push(0, i + 1, i + 2);       // part A: 300 tris
  for (let i = 0; i < 4; i++) index.push(400, 401 + i, 402 + i);   // part B, a leaf: 4 tris (1.3%)
  index.push(500, 501, 502);                                        // fragment: 1 tri (0.3%)
  const out = removeSmallPieces(index, 503);
  assert.equal(out.removedTriangles, 1);
  assert.equal(out.index.length, 304 * 3);
});

test("cleanObject cleans every mesh, including unindexed geometry", () => {
  const big = boxPiece(0, 24); // 6,912 triangles, so the tiny box (12) is a 0.17% sliver
  const tiny = new THREE.BoxGeometry(0.02, 0.02, 0.02);
  tiny.translate(3, 0, 0);
  const merged = new THREE.BufferGeometry();
  // Put both into one unindexed geometry, the way some exporters write it
  const a = big.toNonIndexed(), b = tiny.toNonIndexed();
  const pos = new Float32Array([...a.attributes.position.array, ...b.attributes.position.array]);
  merged.setAttribute("position", new THREE.BufferAttribute(pos, 3));
  const root = new THREE.Group();
  root.add(new THREE.Mesh(merged, new THREE.MeshStandardMaterial()));
  const removed = cleanObject(root);
  assert.equal(removed, 12, "the tiny box's 12 triangles are gone");
  const mesh = root.children[0];
  mesh.geometry.computeBoundingBox();
  assert.ok(mesh.geometry.boundingBox.max.x < 1, "nothing left near the fragment");
});

test("bakeEdits writes brightness into vertex colors and keeps rotation and scale", () => {
  const g = new THREE.BoxGeometry(1, 1, 1);
  const colors = new Float32Array(g.attributes.position.count * 3).fill(0.3);
  g.setAttribute("color", new THREE.BufferAttribute(colors, 3));
  const source = new THREE.Group();
  source.add(new THREE.Mesh(g, new THREE.MeshStandardMaterial({ vertexColors: true })));

  const baked = bakeEdits(source, { rotation: new THREE.Euler(0, Math.PI / 2, 0), scale: 2, brightness: 2 });
  const mesh = baked.getObjectByProperty("type", "Mesh");
  assert.ok(Math.abs(mesh.geometry.attributes.color.getX(0) - 0.6) < 1e-6, "0.3 × 2 = 0.6");
  assert.ok(Math.abs(baked.children[0].rotation.y - Math.PI / 2) < 1e-6);
  assert.equal(baked.children[0].scale.x, 2);
  // The original is untouched (the page keeps editing it)
  assert.ok(Math.abs(g.attributes.color.getX(0) - 0.3) < 1e-6);
});

test("bakeEdits caps colors at 1 and brightens plain material colors too", () => {
  const g = new THREE.BoxGeometry(1, 1, 1);
  const source = new THREE.Group();
  source.add(new THREE.Mesh(g, new THREE.MeshStandardMaterial({ color: new THREE.Color(0.8, 0.4, 0.2) })));
  const baked = bakeEdits(source, { brightness: 2 });
  const c = baked.getObjectByProperty("type", "Mesh").material.color;
  assert.deepEqual([c.r, c.g, c.b].map((v) => +v.toFixed(3)), [1, 0.8, 0.4]);
});
