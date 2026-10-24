# BloomCraft

## UI work: always use ui-ux-pro-max

Before designing, building, redesigning or restyling any UI (pages, components, layouts, dark mode), load the `ui-ux-pro-max:ui-ux-pro-max` skill with the Skill tool and follow it. Also load `ui-ux-pro-max:ui-styling` when the work is mostly components or dark-mode styling. Do this in every session, without being asked.

Keep the project's own look on top of the skill's guidance:
- **ERP (HR, Finance, CRM):** the vendor portal look. Use the `--v-*` tokens, `components/hr/HrPage.vue` and the `sp-*`/`fx-*` classes, inside the shared shell in `layouts/erp/`. Every page must work in light and dark mode.
- **Customer side and sign-in:** the logo's navy (#363F4E) and green (#4DB173) from `styles/bloomcraft.css`.
- **Loading:** use skeletons (`.bc-skeleton`), never spinners or loading overlays.
