// Pure helpers behind the customizer's layout: which steps show, the receipt
// lines, what each key does, and where the selection card sits on the stage.

const STEP_ORDER = [
  // Fillers go in first as the bed of greenery; the flowers sit in it
  { key: "fillers", label: "Fillers", needs: "hasFillers" },
  { key: "flowers", label: "Flowers" },
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

// Where the guide's message card goes beside the part it points at: the first
// side with room (right, left, below, above). Narrow screens, or no side with
// room, dock it to the top or bottom edge, away from the part. `arrow` is where
// the pointing arrow sits and which way it points.
export function placeTip(target, view, { width, height, gap = 30, margin = 16, narrow = 720 }) {
  const clamp = (value, min, max) => Math.max(min, Math.min(value, Math.max(min, max)));
  if (!target) {
    return { side: "center", left: (view.width - width) / 2, top: (view.height - height) / 2, arrow: null };
  }
  const cx = target.left + target.width / 2;
  const cy = target.top + target.height / 2;
  const sideTop = clamp(cy - height / 2, margin, view.height - height - margin);
  const sideLeft = clamp(cx - width / 2, margin, view.width - width - margin);
  const fits = {
    right: target.left + target.width + gap + width <= view.width - margin,
    left: target.left - gap - width >= margin,
    bottom: target.top + target.height + gap + height <= view.height - margin,
    top: target.top - gap - height >= margin,
  };
  if (view.width >= narrow) {
    if (fits.right) {
      const left = target.left + target.width + gap;
      return { side: "right", left, top: sideTop, arrow: { dir: "left", x: left - gap / 2, y: clamp(cy, sideTop + 18, sideTop + height - 18) } };
    }
    if (fits.left) {
      const left = target.left - gap - width;
      return { side: "left", left, top: sideTop, arrow: { dir: "right", x: left + width + gap / 2, y: clamp(cy, sideTop + 18, sideTop + height - 18) } };
    }
  }
  if (fits.bottom) {
    const top = target.top + target.height + gap;
    return { side: "bottom", left: sideLeft, top, arrow: { dir: "up", x: clamp(cx, sideLeft + 18, sideLeft + width - 18), y: top - gap / 2 } };
  }
  if (fits.top) {
    const top = target.top - gap - height;
    return { side: "top", left: sideLeft, top, arrow: { dir: "down", x: clamp(cx, sideLeft + 18, sideLeft + width - 18), y: top + height + gap / 2 } };
  }
  // No room beside it (a big part, or a phone): dock to the edge farther from it
  const below = cy < view.height / 2;
  const top = below ? view.height - height - margin : margin;
  return { side: below ? "dock-bottom" : "dock-top", left: sideLeft, top, arrow: { dir: below ? "up" : "down", x: clamp(cx, sideLeft + 18, sideLeft + width - 18), y: below ? top - 14 : top + height + 14 } };
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

// How far a saved custom bouquet is from the size the customer chose:
// { placed, size, missing } when it has empty flower spots, else null
export function bouquetShortfall(customization) {
  if (customization?.type !== "custom_flower_bouquet") return null;
  const size = Number(customization.preset?.size || 0);
  const placed = Array.isArray(customization.flowers) ? customization.flowers.length : 0;
  if (!size || placed >= size) return null;
  return { placed, size, missing: size - placed };
}

// The flower to fill the empty spots with: the one used most that still has
// stock, else any flower with stock. `used` counts how many of each are placed.
export function fillFlowerChoice(flowers = [], used = {}) {
  const left = (flower) => Number(flower.quantity_in_stock || 0) - Number(used[flower.id] || 0);
  const ready = flowers.filter((flower) => flower.model && left(flower) > 0);
  return ready.sort((a, b) => Number(used[b.id] || 0) - Number(used[a.id] || 0))[0] || null;
}
