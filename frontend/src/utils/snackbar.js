import { reactive } from "vue";

// BloomCraft's snackbars (in place of toast pop-ups). At most two show at a
// time: a new one pushes out the oldest, unless the pointer is resting on it.
// Hovering or focusing a snackbar also pauses its timer.
//
// Same calls as before: toast.success(message), toast.error(…), toast.info(…),
// toast.warning(…), each with optional { autoClose, toastId, action }.
// action: { label, onClick } or { label, to } (a router location).

export const MAX_SHOWN = 2;
const DURATION = { success: 4000, info: 5000, warning: 6500, error: 7000 };

export const snackbars = reactive({ items: [] });
let seq = 0;

const timers = new Map();

function startTimer(item) {
  stopTimer(item.id);
  if (!item.duration) return;
  item.endsAt = Date.now() + item.remaining;
  timers.set(item.id, setTimeout(() => dismiss(item.id), item.remaining));
}
function stopTimer(id) {
  clearTimeout(timers.get(id));
  timers.delete(id);
}

export function dismiss(id) {
  stopTimer(id);
  const i = snackbars.items.findIndex((s) => s.id === id);
  if (i >= 0) snackbars.items.splice(i, 1);
}

/** The pointer (or keyboard focus) is on it: hold it open */
export function hold(id) {
  const item = snackbars.items.find((s) => s.id === id);
  if (!item || item.held) return;
  item.held = true;
  if (item.duration && item.endsAt) item.remaining = Math.max(1500, item.endsAt - Date.now());
  stopTimer(id);
}
/** Let it go: its timer carries on, and if too many are showing, it can be pushed out again */
export function release(id) {
  const item = snackbars.items.find((s) => s.id === id);
  if (!item || !item.held) return;
  item.held = false;
  startTimer(item);
  trim();
}

/** Keep at most two: the oldest one that isn't being held goes */
function trim() {
  while (snackbars.items.length > MAX_SHOWN) {
    const victim = snackbars.items.find((s) => !s.held);
    if (!victim) return; // both held: the new one waits until one is let go
    dismiss(victim.id);
  }
}

function show(type, message, options = {}) {
  const text = typeof message === "string" ? message : String(message ?? "");
  if (!text.trim()) return null;
  const duration = options.autoClose === false ? 0 : Number(options.autoClose) || DURATION[type] || 5000;

  // The same toastId (or the same text and kind) replaces the one already showing
  const key = options.toastId ?? `${type}:${text}`;
  const existing = snackbars.items.find((s) => s.key === key);
  if (existing) {
    Object.assign(existing, { text, type, action: options.action ?? null, duration, remaining: duration });
    if (!existing.held) startTimer(existing);
    return existing.id;
  }

  const item = { id: ++seq, key, type, text, action: options.action ?? null, duration, remaining: duration, held: false, endsAt: 0 };
  snackbars.items.push(item);
  startTimer(item);
  trim();
  return item.id;
}

export const toast = Object.assign((message, options) => show("info", message, options), {
  success: (message, options) => show("success", message, options),
  error: (message, options) => show("error", message, options),
  info: (message, options) => show("info", message, options),
  warning: (message, options) => show("warning", message, options),
  warn: (message, options) => show("warning", message, options),
  // Old toast positions are accepted and ignored: snackbars always sit in one place
  POSITION: new Proxy({}, { get: (_, name) => String(name) }),
  dismiss: (id) => (id === undefined ? [...snackbars.items].forEach((s) => dismiss(s.id)) : dismiss(id)),
  remove: (id) => (id === undefined ? [...snackbars.items].forEach((s) => dismiss(s.id)) : dismiss(id)),
});
export const snackbar = toast;
