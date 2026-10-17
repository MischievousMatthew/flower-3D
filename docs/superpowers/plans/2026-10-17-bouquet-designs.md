# Bouquet Designs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Five bouquet base designs, one per occasion, shared by the customizer and the order/cart viewer.

**Architecture:** `utils/bouquetDesigns/` builds a base group + dressing interface; classic loads a filler-free GLB; the other four are generated meshes.

**Tech Stack:** three 0.182, @gltf-transform/core 4.5.1 (dev, asset script), node:test, Laravel.

**Spec:** `docs/superpowers/specs/2026-10-17-bouquet-designs-design.md`

## Global Constraints

- Same base normalization and wrapper-scale math in customizer and viewer.
- Saved bouquets without `design` render as classic (unchanged look minus fillers).
- Loading states: skeleton/text only.

## Review Focus

- Old orders (no design, wrapper_scale 1) still show flowers where they were placed.
- Orders with wrapper_scale > 1 line up in the viewer (was scale-only before).
- Wedding (hand-tied) still gets a ribbon; sympathy basket shows no ribbon.
- Flowers can be placed on every design (bed is a target).

---

### Task 1: Classic asset script → `public/Boquet.classic.glb`
### Task 2: Designs module + tests (`src/utils/bouquetDesigns/*`, `tests/bouquetDesigns.test.mjs`)
### Task 3: Presets carry `design` (server + client), saved in customization
### Task 4: Customizer + viewer use `createBouquetBase`
### Task 5: Browser check of each design
