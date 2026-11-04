// Undo / redo for the bouquet customizer. Each entry is a snapshot of the
// design (a string, so two snapshots compare with ===). The customizer
// records a snapshot after each change and puts an older one back on undo.

export function createHistory(limit = 60) {
  let entries = [];
  let index = -1;

  return {
    /** Starts over with this as the only entry (nothing to undo) */
    reset(snapshot) {
      entries = snapshot === undefined ? [] : [snapshot];
      index = entries.length - 1;
    },
    /** Adds a change. Anything that was undone is dropped. False when nothing changed. */
    record(snapshot) {
      if (index < 0) {
        entries = [snapshot];
        index = 0;
        return true;
      }
      if (entries[index] === snapshot) return false;
      entries = entries.slice(0, index + 1);
      entries.push(snapshot);
      if (entries.length > limit) entries.shift();
      index = entries.length - 1;
      return true;
    },
    /** Swaps the current entry, e.g. when an undo couldn't put everything back */
    replace(snapshot) {
      if (index >= 0) entries[index] = snapshot;
    },
    current: () => (index >= 0 ? entries[index] : undefined),
    canUndo: () => index > 0,
    canRedo: () => index >= 0 && index < entries.length - 1,
    /** The snapshot to go back to, or null */
    undo() {
      if (index <= 0) return null;
      index -= 1;
      return entries[index];
    },
    /** The snapshot to go forward to, or null */
    redo() {
      if (index < 0 || index >= entries.length - 1) return null;
      index += 1;
      return entries[index];
    },
  };
}

/**
 * Groups after a change: any group left with fewer than two items is
 * dissolved. Items are { sceneId, group }; returns new objects only where the
 * group changed.
 */
export function tidyGroups(items) {
  const counts = new Map();
  items.forEach((item) => item.group && counts.set(item.group, (counts.get(item.group) || 0) + 1));
  return items.map((item) => (item.group && counts.get(item.group) < 2 ? { ...item, group: null } : item));
}

/** The scene ids a click on `sceneId` picks: the item, or its whole group */
export function pickUnit(items, sceneId) {
  const item = items.find((entry) => entry.sceneId === sceneId);
  if (!item) return [];
  return item.group ? items.filter((entry) => entry.group === item.group).map((entry) => entry.sceneId) : [sceneId];
}

/** Adds a clicked item (or its group) to the picked ids, or takes it out if it's all in already */
export function togglePick(items, picked, sceneId) {
  const unit = pickUnit(items, sceneId);
  if (!unit.length) return picked;
  const all = unit.every((id) => picked.includes(id));
  return all ? picked.filter((id) => !unit.includes(id)) : [...picked, ...unit.filter((id) => !picked.includes(id))];
}
