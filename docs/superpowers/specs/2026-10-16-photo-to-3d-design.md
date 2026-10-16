# Photo to 3D — Design

Date: 2026-10-16
Status: Approved in conversation ("Do it"); user reviews the finished feature

## Goal

A vendor uploads a photo of a flower (e.g. one rose) or a bouquet and gets a
real 3D object (`.glb`) they can spin, download, or attach to one of their
products. Free to run: no paid APIs, nothing heavy on the Render free-tier
backend.

## Verified facts (spike, 2026-10-16)

- `trellis-community/TRELLIS` Space: `/start_session` → `/preprocess_image`
  → `/generate_and_extract_glb` turned a 960×1244 rose photo into a textured
  GLB (1.56 MB, 15.5k triangles, real depth ≈ width) in **28 s**.
- `tencent/Hunyuan3D-2` Space: `/generation_all` exists but asks for **135 s**
  of GPU per run; with the anonymous quota already partly used it was refused:
  `You have exceeded your ZeroGPU quota (135s requested vs. 130s left). Try again in 23:58:03.`
- ZeroGPU quota is per visitor connection (IP) and shared across Spaces, so
  each vendor has their own small daily allowance (≈3 TRELLIS runs).
- Both Spaces send `access-control-allow-origin` for
  `https://bloomcraft-app.vercel.app`, so the browser can call them directly.
- `microsoft/TRELLIS` and `stabilityai/TripoSR` Spaces were broken on the same
  day — Spaces break without notice.

## The page

Route `VendorPhotoTo3D`, path `/vendor/photo-to-3d`, meta
`subscriptionModule: PRODUCTS`, shown as a **Photo to 3D** tab in the Shop
section (`ShopLayout`). Add Product's 3D model field links to it.

1. Photo: drop or pick JPG/PNG/WebP, ≤ 10 MB. Resized in the browser to
   ≤ 1024 px on the long side before sending. Tips: one subject, plain
   background, good light.
2. What is it: **Single flower** or **Bouquet** (used by the fallback and as a
   hint in the UI).
3. **Make 3D** → skeleton preview with live text (no spinners): "Connecting to
   the free AI…", "Removing the background…", "Building the 3D shape… 0:34".
4. Result in the app's `3DModelViewer` (orbit, zoom) with source label
   ("Made by AI" / "Matched build").
5. Actions: **Download .glb**, **Attach to a product** (choose from the
   vendor's products; `POST vendor/products/{id}` with `_method=PUT` and
   `model_file`, the existing Cloudinary upload), **Start over**.

## Conversion

### A — free AI (browser → Hugging Face via `@gradio/client@2.7.0`)

Providers live in one config list, tried in order:

1. `trellis-community/TRELLIS` (defaults: seed 0, ss 7.5/12, slat 3/12,
   mesh_simplify 0.95, texture_size 1024)
2. `tencent/Hunyuan3D-2` `/generation_all` (only reached when 1 fails for a
   reason other than quota, since quota is shared)

Errors are classified from the message:

- `quota` — contains "exceeded your ZeroGPU quota"; parse "Try again in
  HH:MM:SS" into a reset time shown to the vendor. Stop trying more AI providers.
- `busy` / `down` — queue full, Space sleeping/erroring, network/CORS. Try the
  next provider.
- `bad-photo` — preprocessing returns nothing usable. Ask for a clearer photo.

A whole-run timeout of 4 minutes per provider.

### B — matched build (fully in the browser)

Offered automatically when A fails ("The free AI can't take this photo right
now — reason. Build a matching 3D instead?"), and always available as a
secondary button.

- **Colors:** `extractPalette` reads the resized photo's pixels, drops
  background (near-white, near-black, very low saturation along the border)
  and splits flower colors from leaf greens; k-means (k ≤ 3) gives up to three
  flower colors plus one leaf color.
- **Shapes:** BloomCraft's sculpted heads from `landingFlowers.js` (rose,
  tulip, peony, daisy), recolored to the palette keeping each head's
  light-to-dark shading.
- **Single flower:** one head (chosen type, default rose) on a curved stem
  with two leaves.
- **Bouquet:** vendor sets counts per type (default 7 roses); heads domed
  over a bundle of stems in a code-made paper cone (paper color picker from
  the customizer's paper presets).
- Exported with three's `GLTFExporter` (binary), target < 2 MB.

## Limits shown on the page

AI takes about 30 s–3 min; about one free conversion per day per connection (a TRELLIS run reserves more GPU time than is left after one run);
single flowers come out best; photos go to Hugging Face's public demo
servers; attached models must be ≤ 10 MB (Cloudinary free tier).

## Files

- `frontend/src/components/vendor/photo3d/aiProviders.js` — provider list,
  `convertWithAI(file, { onStage, kind })`, `classifyAiError(message)`
- `frontend/src/components/vendor/photo3d/photoPalette.js` — `extractPalette`
- `frontend/src/components/vendor/photo3d/matchedModel.js` —
  `buildMatchedModel(options)`, `exportGlb(object3d)`
- `frontend/src/composables/landingFlowers.js` — export
  `createHeadGeometry(head, seed)` (existing builders, no behavior change)
- `frontend/src/views/vendor/shop/PhotoTo3D.vue` — the page
- `frontend/src/router/index.js`, `ShopLayout.vue` tab, `AddProduct.vue` link
- `frontend/tests/photo3d.test.mjs`

## Verification

- Unit tests: palette from synthetic red-rose pixels → red flower + green
  leaf, background ignored; error classification incl. quota reset parsing;
  matched model builds for single and bouquet and exports a valid GLB
  (`glTF` magic) under 2 MB.
- `vite build` passes; the opaque-route sync gives the new route a token.
- Live: one real TRELLIS run already verified; browser check by the user.

## Out of scope

Paid APIs, server-side generation, saving generation history, editing the
AI mesh.
