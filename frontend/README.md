# Vue 3 + Vite

This template should help get you started developing with Vue 3 in Vite. The template uses Vue 3 `<script setup>` SFCs, check out the [script setup docs](https://v3.vuejs.org/api/sfc-script-setup.html#sfc-script-setup) to learn more.

Learn more about IDE Support for Vue in the [Vue Docs Scaling up Guide](https://vuejs.org/guide/scaling-up/tooling.html#ide-support).

## Opaque client URLs

Named Vue routes are exposed with stable opaque tokens. Existing readable paths remain compatibility redirects, while normal links resolve to opaque URLs.

To add a page, add its normal named route in `src/router/index.js`; do not create a token manually. Before the first new route is built, set the same `ROUTE_TOKEN_SECRET` in local development and Vercel. The `predev` and `prebuild` hooks register a deterministic token automatically, preserving bookmarks across deployments.
