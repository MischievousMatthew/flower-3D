import { h } from "vue";
import "./customizerIcon.css";

// Line icons drawn on a 24px grid with a 1.5 stroke, matching Shop.vue.
const ICONS = {
  chevronLeft: [["path", { d: "M15 6l-6 6 6 6" }]],
  chevronRight: [["path", { d: "M9 6l6 6-6 6" }]],
  search: [["circle", { cx: 11, cy: 11, r: 6.5 }], ["path", { d: "m16 16 4 4" }]],
  panelLeft: [["rect", { x: 3, y: 4, width: 18, height: 16, rx: 2 }], ["path", { d: "M9 4v16" }]],
  panelRight: [["rect", { x: 3, y: 4, width: 18, height: 16, rx: 2 }], ["path", { d: "M15 4v16" }]],
  maximize: [["path", { d: "M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" }]],
  minimize: [["path", { d: "M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" }]],
  camera: [["path", { d: "M4 8h3l2-3h6l2 3h3v11H4z" }], ["circle", { cx: 12, cy: 13, r: 3.5 }]],
  reset: [["path", { d: "M4 12a8 8 0 1 0 2.4-5.7L4 8.5" }], ["path", { d: "M4 4v4.5h4.5" }]],
  rotate: [["path", { d: "M20 12a8 8 0 1 1-2.4-5.7L20 8.5" }], ["path", { d: "M20 4v4.5h-4.5" }]],
  lock: [["rect", { x: 5, y: 11, width: 14, height: 9, rx: 2 }], ["path", { d: "M8 11V8a4 4 0 0 1 8 0v3" }]],
  unlock: [["rect", { x: 5, y: 11, width: 14, height: 9, rx: 2 }], ["path", { d: "M8 11V8a4 4 0 0 1 7.6-1.7" }]],
  trash: [["path", { d: "M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" }]],
  arrowUp: [["path", { d: "M12 19V5M6 11l6-6 6 6" }]],
  arrowDown: [["path", { d: "M12 5v14M6 13l6 6 6-6" }]],
  arrowLeft: [["path", { d: "M19 12H5M11 6l-6 6 6 6" }]],
  arrowRight: [["path", { d: "M5 12h14M13 6l6 6-6 6" }]],
  plus: [["path", { d: "M12 5v14M5 12h14" }]],
  minus: [["path", { d: "M5 12h14" }]],
  check: [["path", { d: "m5 12.5 4.5 4.5L19 7.5" }]],
  x: [["path", { d: "M6 6l12 12M18 6 6 18" }]],
  info: [["circle", { cx: 12, cy: 12, r: 9 }], ["path", { d: "M12 11v5M12 8h.01" }]],
  bag: [["path", { d: "M5 8h14l-1 12H6z" }], ["path", { d: "M9 8V6.5a3 3 0 0 1 6 0V8" }]],
  move: [["path", { d: "M12 3v18M3 12h18M9 6l3-3 3 3M9 18l3 3 3-3M6 9l-3 3 3 3M18 9l3 3-3 3" }]],
  scale: [["path", { d: "M4 20h7v-7H4zM14 4h6v6M20 4l-8 8" }]],
  bouquet: [
    ["path", { d: "M7 10l5 11 5-11" }],
    ["circle", { cx: 9, cy: 7, r: 2.5 }],
    ["circle", { cx: 15, cy: 7, r: 2.5 }],
    ["circle", { cx: 12, cy: 4.5, r: 2.5 }],
  ],
  target: [["circle", { cx: 12, cy: 12, r: 7 }], ["circle", { cx: 12, cy: 12, r: 2 }], ["path", { d: "M12 2v3M12 19v3M2 12h3M19 12h3" }]],
  leaf: [["path", { d: "M5 19c0-8 5-14 14-14 0 9-6 14-14 14z" }], ["path", { d: "M5 19l7.5-7.5" }]],
  sparkle: [
    ["path", { d: "M11 3l1.8 5.2L18 10l-5.2 1.8L11 17l-1.8-5.2L4 10l5.2-1.8z" }],
    ["path", { d: "M18.5 15l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z" }],
  ],
  card: [["rect", { x: 3, y: 6, width: 18, height: 13, rx: 2 }], ["path", { d: "m3.5 7.5 8.5 6 8.5-6" }]],
  flower: [
    ["circle", { cx: 12, cy: 9, r: 2 }],
    ["path", { d: "M12 7a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5M14 9a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0M12 11a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5M10 9a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0" }],
    ["path", { d: "M12 16v6M12 19.5c-1.5-1.8-3.5-2.2-5-1.8" }],
  ],
};
const CustomizerIcon = (props) =>
  h(
    "svg",
    { class: "fc-icon", viewBox: "0 0 24 24", "aria-hidden": "true", focusable: "false" },
    (ICONS[props.name] || []).map(([tag, attrs]) => h(tag, attrs)),
  );
CustomizerIcon.props = ["name"];

export default CustomizerIcon;
