// Pure helpers behind the customizer's layout: which steps show, the receipt
// lines, what each key does, and where the selection card sits on the stage.

const STEP_ORDER = [
  { key: "flowers", label: "Flowers" },
  { key: "fillers", label: "Fillers", needs: "hasFillers" },
  { key: "wrap", label: "Wrap", needs: "hasWrapper" },
  { key: "ribbon", label: "Ribbon", needs: "hasRibbon" },
  { key: "card", label: "Card", needs: "hasCards" },
  { key: "review", label: "Review" },
];

// Steps for parts the bouquet doesn't have are left out; the rest number on from 01
export function buildSteps(parts = {}) {
  return STEP_ORDER.filter((step) => !step.needs || parts[step.needs]).map((step, index) => ({
    key: step.key,
    label: step.label,
    number: String(index + 1).padStart(2, "0"),
  }));
}

const sum = (items) => items.reduce((total, item) => total + Number(item.price || 0), 0);

// One line per thing on the order, in the order the customer builds it
export function receiptLines({ flowers = [], fillers = [], outerWrapper, innerWrapper, ribbon, card } = {}) {
  const lines = [{ label: `Stems (${flowers.length})`, amount: sum(flowers) }];
  if (fillers.length) lines.push({ label: `Fillers (${fillers.length})`, amount: sum(fillers) });
  if (outerWrapper) lines.push({ label: `Outer: ${outerWrapper.name}`, amount: Number(outerWrapper.price || 0) });
  if (innerWrapper) lines.push({ label: `Inner: ${innerWrapper.name}`, amount: Number(innerWrapper.price || 0) });
  if (ribbon) lines.push({ label: ribbon.name, amount: Number(ribbon.price || 0) });
  if (card) lines.push({ label: `Card: ${card.product_name} (${card.size})`, amount: Number(card.price || 0) });
  return lines;
}

// Arrow keys match the on-screen pad; E / Q move away from / toward you
export const MOVE_KEYS = {
  arrowup: "up",
  arrowdown: "down",
  arrowleft: "left",
  arrowright: "right",
  e: "forward",
  q: "backward",
};

// Blender-style shortcuts: G move, R rotate, S size, F focus
export function keyAction(event, state) {
  if (!state.ready || state.modalOpen || event.ctrlKey || event.metaKey || event.altKey || event.inField) return null;
  const key = String(event.key || "").toLowerCase();

  if (key === "escape") return state.placing ? "cancel-placing" : "deselect";
  if (!state.hasTarget) return null;
  if (key === "g") return "mode:translate";
  if (key === "r") return "mode:rotate";
  if (key === "s") return state.bouquetSelected ? null : "mode:scale";
  if (key === "f") return "focus";
  if (key === "delete" || key === "backspace") return state.hasSelectedFlower ? "remove" : null;
  if (MOVE_KEYS[key]) return `nudge:${MOVE_KEYS[key]}`;
  return null;
}

// Beside the selection, flipped to its left when there's no room on the right,
// and always kept inside the stage
export function anchorCard(point, stage, { cardWidth, cardHeight, gap = 24, margin = 16 }) {
  let side = "right";
  let left = point.x + gap;
  if (left + cardWidth > stage.width - margin) {
    side = "left";
    left = point.x - gap - cardWidth;
  }
  left = Math.min(Math.max(left, margin), stage.width - margin - cardWidth);
  const top = Math.min(Math.max(point.y - cardHeight / 2, margin), stage.height - margin - cardHeight);
  return { left, top, side };
}
