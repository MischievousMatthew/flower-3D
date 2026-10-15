# Vendor Dark Mode — Design

Date: 2026-10-15
Status: Approved in conversation, awaiting written-spec review

## Goal

Vendors can switch the vendor area (every `/vendor/...` page) to a dark
appearance. Light mode keeps looking exactly as it does today. The rest of
the site (landing, customer shop, admin, ERP) is out of scope and stays light.

## What the vendor sees

### The switch

The control is the existing `.sp-switch` toggle (the one used on Add Product
and Shop Settings), colored with the vendor's theme color when on. It appears
in three places that always show the same state:

1. **Sidebar footer**: a "Dark mode" row under "Theme color".
2. **Navbar profile dropdown** (`vendorHeader.vue`): a "Dark mode" row between
   the menu links and Log out. Flipping it does not close the dropdown.
3. **Shop Settings page** (`views/vendor/account/ShopSettings.vue`): a new
   **Appearance** card in the right-hand column containing:
   - **Dark mode** switch
   - **Match my device** switch: follows the OS/browser
     `prefers-color-scheme`, live (reacts when the device setting changes).
     While on, the Dark mode switch shows what the device is currently using.
   - **Theme color** swatches plus "Any color" picker (same options as the
     sidebar picker)

### Rules

- Default for a new browser: **Match my device = on**.
- Flipping Dark mode anywhere (sidebar, navbar, settings) sets an explicit
  light/dark choice and turns Match my device off.
- Turning Match my device back on returns to following the device.
- Appearance changes apply instantly and are saved in this browser
  (`localStorage`), like the theme color. They are not part of Shop Settings'
  Save button, which keeps covering shop settings only.

## Architecture

### `useVendorTheme.js` (renamed from `useSidebarTheme.js`)

One module owns both appearance settings:

- `accent` (existing theme color) and `mode`: `"system" | "light" | "dark"`,
  persisted under `bc_vendor_sidebar_accent` (existing key, kept so saved
  colors survive) and `bc_vendor_color_mode`.
- `isDark`: computed from `mode` and a `matchMedia("(prefers-color-scheme: dark)")`
  listener.
- `setDark(boolean)` sets `mode` to `"dark"` or `"light"`; `setMatchDevice(boolean)`
  sets `mode` to `"system"` or to the current effective value.
- `applyVendorTheme(router)`, called once from `main.js` before mount:
  - sets the `--bc-accent*` variables (existing behavior), choosing the dark
    or light shade set depending on `isDark`
  - sets `data-vendor-theme="dark"` on `<html>` only when `isDark` is true
    **and** the current route path starts with `/vendor`; removes it otherwise
    (re-evaluated on every route change via `router.afterEach` and immediately
    for the initial route so there is no light flash on refresh)
- All importers of `useSidebarTheme` are updated to the new name.

### Color tokens: `src/styles/vendor-theme.css`

A new global stylesheet imported from `main.js`. It defines light values on
`:root` (equal to today's colors) and dark values under
`html[data-vendor-theme="dark"]`. Because the dark selector exists only on
vendor routes, shared components using these tokens (NotificationBell,
SupportCenter, ReturnCasePanel) render unchanged on customer/admin pages.

| Role | Token | Light | Dark |
|---|---|---|---|
| Page background | `--v-bg` | `#f4f6f8` | `#0f141b` |
| Card / panel | `--v-surface` | `#ffffff` | `#161c25` |
| Dropdown, modal, popover | `--v-raised` | `#ffffff` | `#1d2430` |
| Hover, striped rows, light fills | `--v-subtle` | `#f7fafc` | `#1b222c` |
| Subtle fill, stronger | `--v-subtle-2` | `#edf2f7` | `#222a36` |
| Input background | `--v-input` | `#ffffff` | `#121821` |
| Border | `--v-border` | `#e2e8f0` | `#2a3340` |
| Strong border | `--v-border-strong` | `#cbd5e0` | `#3a4555` |
| Main text | `--v-text` | `#1a202c` | `#e6eaf0` |
| Secondary text | `--v-text-2` | `#4a5568` | `#c3cad5` |
| Muted text | `--v-muted` | `#718096` | `#95a0b0` |
| Faint text, placeholders | `--v-faint` | `#a0aec0` | `#6b7686` |
| Frosted header | `--v-glass` | `rgba(255,255,255,0.92)` | `rgba(22,28,37,0.88)` |
| Shadow color | `--v-shadow` | `rgba(15,23,42,0.08)` | `rgba(0,0,0,0.45)` |

Status hues (`red`, `amber`, `blue`, `green`, `purple`, `pink`, `teal`) each
get `--v-<hue>-soft` (tinted background) and `--v-<hue>-text` (text on that
background). Light values are today's pale tint / dark text (for example red:
`#fff5f5` / `#c53030`); dark values are a deep tint / light text (for example
red: `#3a1d22` / `#feb2b2`). Exact dark values are tuned so every pair passes
WCAG AA (4.5:1) on its soft background and on `--v-surface`.

Also under the dark selector:

- `color-scheme: dark` so native date pickers, selects and scrollbars go dark
- `.bc-skeleton` dark shimmer colors
- vue3-toastify CSS variables set to dark values

### Theme color in dark mode

`useVendorTheme` computes a second shade set for dark mode:
`soft`, `muted` and `border` become dark tints of the accent (mixed toward
`--v-surface`), and `deep`/`strong` (accent-colored text) become lighter
shades (mixed toward white) so they stay readable. `--bc-accent`,
`--bc-accent-hover` and `--bc-accent-ink` are unchanged.

## Converting existing styles

### Scope (files)

- `src/views/vendor/**` (36 files)
- `src/components/vendor/**` (shared CSS and components)
- `src/layouts/vendorHeader.vue`, `src/layouts/Sidebar/VendorSidebar.vue`
- `src/components/support/NotificationBell.vue`,
  `src/components/support/SupportCenter.vue`,
  `src/components/returns/ReturnCasePanel.vue`

### Mapping rules

A script maps each hardcoded color to a token based on the CSS property it
appears in:

- `background` / `background-color` with white → `--v-surface` (or
  `--v-raised` for dropdown/modal/popover selectors, `--v-input` for
  input/select/textarea selectors); very light neutrals → `--v-bg`,
  `--v-subtle`, `--v-subtle-2` by lightness
- `border*` / `outline` neutrals → `--v-border` / `--v-border-strong`
- `color` neutrals → `--v-text`, `--v-text-2`, `--v-muted`, `--v-faint` by
  lightness
- pale status tints → `--v-<hue>-soft`; dark status text → `--v-<hue>-text`
- `color: #fff` / `white` (text on colored buttons) is **not** changed
- shadows using black/slate rgba → `--v-shadow` where it is the main shadow
  color
- Medium, saturated colors that read well on both (e.g. `#e53e3e`,
  `#f6ad55`, chart bars) are left as is

Colors the script cannot classify confidently are listed and handled by hand.
Every file diff is reviewed.

### Never changed

Product photos and logos, the 3D bouquet viewer (`3DModelViewer.vue`,
`Order3DModal.vue` canvas), map tiles, chart data palettes, product color
swatches defined in JS (`staffUi.js`, `crmUi.js`, category palettes), and the
decorative storefront cover gradient.

## Order of work

Each step builds and is committed on its own:

1. `useVendorTheme.js`, `vendor-theme.css`, the three switches (sidebar,
   navbar, Appearance card)
2. Shared styles (`shop.css`, `finance.css`, `account.css`, `staff.css`),
   sidebar, header, shared support/returns components
3. Shop pages
4. Orders pages
5. CRM pages
6. Books (finance) pages
7. Staff pages
8. Account pages
9. Dashboard, Profile, Customization, Add Product, Support, Force Change Password

## Verification

- `vite build` passes after every step.
- A contrast script checks every text/background token pair in dark mode
  against WCAG AA (4.5:1 normal text).
- Light mode: the token light values equal today's dominant colors; merging
  near-duplicate grays (e.g. `#f7f9fa` → `#f7fafc`) is an intended,
  imperceptible change.
- Browser pass: each vendor page opened in both modes and screenshotted (via
  Chrome automation if a dev server and vendor login are available, otherwise
  a page checklist for the user).
- Switch behavior: the three switches stay in sync; Match my device follows a
  live OS change; leaving `/vendor` removes dark mode; refresh on a vendor page
  in dark mode shows no light flash.

## Out of scope

- Dark mode for customer, guest, admin and ERP pages
- Saving the preference to the vendor's account on the server
