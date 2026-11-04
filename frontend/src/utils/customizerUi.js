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
// The occasion's base price covers the wrapper, ribbon and fillers, so those show as included (0).
export function receiptLines({ flowers = [], fillers = [], outerWrapper, innerWrapper, ribbon, card, base = null } = {}) {
  const lines = [{ label: `Stems (${flowers.length})`, amount: sum(flowers) }];
  if (base && Number(base.amount) > 0) lines.push({ label: `Base price: ${base.label}`, amount: Number(base.amount) });
  if (fillers.length) lines.push({ label: `Fillers (${fillers.length})`, amount: 0 });
  if (outerWrapper) lines.push({ label: `Outer: ${outerWrapper.name}`, amount: 0 });
  if (innerWrapper) lines.push({ label: `Inner: ${innerWrapper.name}`, amount: 0 });
  if (ribbon) lines.push({ label: ribbon.name, amount: 0 });
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

// Blender-style shortcuts: G move, R rotate, S size, F focus; Ctrl+Z / Ctrl+Y undo and redo
export function keyAction(event, state) {
  if (!state.ready || state.modalOpen || event.altKey || event.inField) return null;
  const key = String(event.key || "").toLowerCase();

  // Ctrl (⌘ on a Mac) + Z undo, + Y or Shift+Z redo, + G group, + Shift+G ungroup
  if (event.ctrlKey || event.metaKey) {
    if (key === "z") return event.shiftKey ? "redo" : "undo";
    if (key === "y") return "redo";
    if (key === "g" && event.shiftKey) return state.grouped ? "ungroup" : null;
    if (key === "g") return state.grouping || state.hasSelectedFlower ? "group" : null;
    return null;
  }

  if (key === "escape") return state.grouping ? "cancel-grouping" : state.placing ? "cancel-placing" : "deselect";
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
