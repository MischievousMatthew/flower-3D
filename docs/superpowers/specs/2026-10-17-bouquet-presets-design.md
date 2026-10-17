# Bouquet Occasion Presets — Design

Date: 2026-10-17
Status: Approved in conversation ("Build the preset")

## Goal

Customers start the 3D customizer by choosing an occasion (Wedding, Birthday,
…) and a size (number of flowers). Each occasion has its own build (Wedding is
hand-tied: ribbon, no wrapper) and its own sizes. Vendors turn occasions on or
off and set the sizes. Bigger bouquets get a bigger wrapper automatically.

## Occasions (fixed list; vendors can't add new ones)

| key | Label | Wrapper | Ribbon | Default sizes |
|---|---|---|---|---|
| `wedding` | Wedding | no | yes | 6, 12, 24 |
| `romance` | Anniversary / Valentine's | yes | yes | 6, 12, 24 |
| `birthday` | Birthday | yes | yes | 4, 8, 12 |
| `graduation` | Graduation | yes | yes | 3, 6, 9 |
| `sympathy` | Sympathy | yes | no | 6, 12 |
| `just_because` | Just because | yes | yes | 3, 6, 12 |

Sizes: 1–36 stems each, 1–4 sizes per occasion, unique, sorted. At least one
occasion stays on.

## Storage and API

- `vendor_applications.bouquet_presets` (JSON, nullable): `{ key: { enabled, sizes[] } }`.
  Missing → defaults. Unknown keys ignored.
- `App\Support\BouquetPresets`: defaults, `forOwner(ownerId)` (merged list),
  `normalize(input)` (validation, throws with field messages), `check(ownerId, customizations)`.
- `GET /api/vendor/bouquet-presets`, `PUT /api/vendor/bouquet-presets`
  (vendor, `subscription.module:products`).
- `GET /api/stores/{id}/customization-options` adds `presets`: enabled occasions
  only, `{ key, label, wrapper, ribbon, sizes }`.

## Validation (server)

In `BouquetCustomizationOption::resolveSelections` (cart add/update and both
checkout paths):

- `customizations.preset = { key, size }` required for new bouquets. Missing
  preset → legacy bouquet: up to 3 flowers, existing rules.
- Occasion must be enabled for the vendor; size must be one of its sizes;
  `count(flowers) ≤ size`.
- No wrapper occasion → wrapper/inner wrapper selections dropped (not charged).
  No ribbon occasion → ribbon dropped.
- Filler limit = `max(5, ceil(size / 2))`.
- Checkout `custom_items` max raised from 3 to 36 (the absolute cap).

## Customizer

1. Start screen: occasion cards (vendor's enabled ones) → size cards → Start
   arranging. One enabled occasion skips to sizes. Editing a cart item skips the
   start screen and restores its preset (legacy items: "Just because · 3").
2. `MAX_FLOWERS` = chosen size. Header chip "Wedding · 12 stems" with Change;
   sizes below the placed count are disabled with an explanation.
3. Build: occasions without a wrapper hide the paper meshes (and they stop
   receiving clicks), hide the wrapper panel and drop wrapper charges; no-ribbon
   occasions hide the ribbon panel and ribbon.
4. Wrapper scale from size, interpolated: 6→1.0, 12→1.15, 24→1.35, 36→1.5
   (≤6 = 1.0). Follows the chosen size, not the placed count.
5. "Fill the rest with this flower": places copies at evenly spread points on
   the bouquet top (downward raycasts on a sunflower spiral inside the opening).
6. Payload `preset: { key, size }`; `window.alert` removed.

## Vendor UI

Shop → 3D Bouquets → new **Presets** tab: a card per occasion with an on/off
switch, its build shown as text, size chips (remove ×, "+ Add size" number
input), one Save button, server errors shown inline.

## Tests

- Backend: defaults; save + validation (range, duplicates, max 4, last-enabled);
  public endpoint lists only enabled; resolveSelections rejects disabled
  occasion / size not offered / too many flowers, drops wrapper for wedding;
  legacy bouquet still passes; checkout accepts 24 custom items.
- Frontend: `wrapperScaleFor`, preset helpers, spiral placement points.
