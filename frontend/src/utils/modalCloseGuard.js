// Clicking outside a modal or drawer closes it, unless something was typed or
// changed in it: then the first outside click only warns, and a second one
// within a few seconds closes it. A modal nobody touched still closes at once.
//
// It works for every modal on the site without changes to each one: a click
// on a full-screen backdrop is checked before the page's own handler sees it.
// A backdrop can opt out with data-close-guard="off" (e.g. the mobile menu).
import { toast } from "vue3-toastify";

const SECOND_CLICK_WITHIN_MS = 6000;
const FIELDS = "input:not([type=hidden]):not([type=button]):not([type=submit]):not([type=reset]):not([type=search]), textarea, select";

// What each field held before the person touched it
const before = new WeakMap();
// When each backdrop last warned
const warnedAt = new WeakMap();

const valueOf = (el) => {
  if (el.type === "checkbox" || el.type === "radio") return String(el.checked);
  if (el.type === "file") return String(el.files?.length ?? 0);
  if (el.tagName === "SELECT" && el.multiple) return [...el.selectedOptions].map((o) => o.value).join("\u0000");
  return el.value;
};

const remember = (event) => {
  const el = event.target;
  if (!(el instanceof Element)) return;
  // Every field of the form being used, so a button that fills one ("Generate") counts too
  const scope = el.closest("form, [role=dialog], [aria-modal=true]");
  const fields = scope ? scope.querySelectorAll(FIELDS) : el.matches(FIELDS) ? [el] : [];
  for (const field of fields) {
    if (!before.has(field)) before.set(field, valueOf(field));
  }
};

/** A full-screen layer behind a modal (or the layer that holds it) */
const isBackdrop = (el) => {
  if (!(el instanceof HTMLElement) || el.dataset.closeGuard === "off") return false;
  const style = getComputedStyle(el);
  if (style.position !== "fixed" && style.position !== "absolute") return false;
  const box = el.getBoundingClientRect();
  return box.width >= window.innerWidth * 0.9 && box.height >= window.innerHeight * 0.9;
};

/** The modal this backdrop closes: inside it, or right next to it */
const panelsFor = (backdrop) => {
  if (backdrop.querySelector(FIELDS)) return [backdrop];
  return [...(backdrop.parentElement?.children ?? [])].filter((el) => el !== backdrop && el.querySelector(FIELDS));
};

/** Something in the modal differs from how it was before the person touched it */
const changed = (panels) =>
  panels.some((panel) => [...panel.querySelectorAll(FIELDS)].some((el) => before.has(el) && valueOf(el) !== before.get(el)));

const guard = (event) => {
  const backdrop = event.target;
  // Only a click on the backdrop itself, never inside the modal
  if (!isBackdrop(backdrop)) return;
  if (!changed(panelsFor(backdrop))) return;

  const last = warnedAt.get(backdrop) ?? 0;
  if (Date.now() - last < SECOND_CLICK_WITHIN_MS) {
    // Second click: close as asked
    warnedAt.delete(backdrop);
    return;
  }
  event.preventDefault();
  event.stopImmediatePropagation();
  warnedAt.set(backdrop, Date.now());
  toast.warning("You've entered information here. Click outside again to close without saving, or keep editing.", {
    toastId: "modal-close-guard",
    autoClose: SECOND_CLICK_WITHIN_MS,
  });
};

let installed = false;
export function installModalCloseGuard() {
  if (installed || typeof document === "undefined") return;
  installed = true;
  // Before typing (focus) and before a click changes a checkbox (pointerdown)
  document.addEventListener("focusin", remember, true);
  document.addEventListener("pointerdown", remember, true);
  document.addEventListener("click", guard, true);
}

// For tests
export const _internals = { isBackdrop, panelsFor, changed, before, valueOf };
