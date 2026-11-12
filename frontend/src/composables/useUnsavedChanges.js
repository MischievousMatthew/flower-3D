import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { onBeforeRouteLeave, useRouter } from "vue-router";

// Unsaved changes on a vendor page, shown in one bar at the bottom of the
// screen (components/vendor/UnsavedChangesBar.vue, mounted once in App.vue)
// instead of a browser "Leave without saving?" box.
//
// A page lists the things a vendor can change:
//   useUnsavedChanges({
//     fields: () => [{ key: "store_name", label: "Shop name", value: form.value?.store_name }, …],
//     save, discard, saving,
//   })
// and calls markClean() whenever what's on screen matches what's saved
// (after loading, saving or discarding). Each changed field is found on the
// page by [data-change~="key"] (or `target`, a selector), so View changes can
// scroll to it and highlight it.
//
// Leaving the page (or switching a tab that would drop the edits, through
// guardUnsaved) is held back: the bar asks to save or discard first, and
// once that's done the vendor goes where they were heading.

const state = reactive({
  owner: null, // the registration currently showing in the bar
  attention: 0, // bumps when something was held back, so the bar can shake
  blocked: false, // a move away is waiting on save or discard
});
let pending = null;

/** The page that owns the bar right now, or null */
export const unsavedState = state;

/**
 * Run `proceed` now if nothing is unsaved; otherwise hold it until the
 * vendor saves or discards. Returns true when it ran straight away.
 */
export function guardUnsaved(proceed) {
  const o = state.owner;
  if (!o || !o.dirty.value || o.saving.value) {
    proceed?.();
    return true;
  }
  pending = proceed ?? null;
  state.blocked = true;
  state.attention++;
  return false;
}

function settle() {
  state.blocked = false;
  const go = pending;
  pending = null;
  go?.();
}

/** Save from the bar; if a move away was waiting, carry on once nothing is left unsaved */
export async function saveUnsaved() {
  const o = state.owner;
  if (!o || o.saving.value) return;
  await o.save();
  if (state.owner === o && !o.dirty.value) settle();
}
export function discardUnsaved() {
  const o = state.owner;
  if (!o) return;
  o.discard();
  o.markClean();
  settle();
}
/** The vendor chose to stay and keep editing */
export function stayOnPage() {
  pending = null;
  state.blocked = false;
}

const toJson = (v) => JSON.stringify(v ?? null);

export function useUnsavedChanges({ fields, save, discard, saving, saveLabel = "Save", enabled = () => true }) {
  const router = useRouter();
  const baseline = ref(null); // key → { json, label, target }

  const list = () => (fields() ?? []).filter(Boolean);

  function markClean() {
    baseline.value = Object.fromEntries(list().map((f) => [f.key, { json: toJson(f.value), label: f.label, fallback: f.fallback }]));
  }

  // In the page's order; fields that were removed (an occasion deleted) come last
  const changes = computed(() => {
    if (!baseline.value || !enabled()) return [];
    const now = list();
    const out = now
      .filter((f) => !baseline.value[f.key] || baseline.value[f.key].json !== toJson(f.value))
      .map((f) => ({ key: f.key, label: f.label, target: f.target, fallback: f.fallback, removed: false }));
    const seen = new Set(now.map((f) => f.key));
    for (const [key, b] of Object.entries(baseline.value)) {
      if (!seen.has(key)) out.push({ key, label: b.label, target: b.fallback ?? null, removed: true });
    }
    return out;
  });

  const reg = {
    changes,
    dirty: computed(() => changes.value.length > 0),
    saving: computed(() => !!(typeof saving === "function" ? saving() : saving?.value)),
    saveLabel,
    save: () => save(),
    discard: () => discard(),
    markClean,
  };

  onMounted(() => (state.owner = reg));
  onBeforeUnmount(() => {
    if (state.owner === reg) {
      state.owner = null;
      state.blocked = false;
      pending = null;
    }
  });

  // Moving to another page waits for save or discard
  if (getCurrentInstance()) {
    onBeforeRouteLeave((to) => {
      if (!reg.dirty.value || reg.saving.value) return true;
      guardUnsaved(() => router.push(to.fullPath));
      return false;
    });
  }

  // Closing the tab or reloading: browsers only allow their own message here
  const beforeUnload = (e) => {
    if (reg.dirty.value && !reg.saving.value) {
      e.preventDefault();
      e.returnValue = "";
    }
  };
  onMounted(() => window.addEventListener("beforeunload", beforeUnload));
  onBeforeUnmount(() => window.removeEventListener("beforeunload", beforeUnload));

  return { markClean, changes, dirty: reg.dirty };
}
