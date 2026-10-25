import { computed, ref, unref, watch } from "vue";

// Every long list on the site works the same way: 10 rows first, "Show more"
// adds 10, then 20 at a time, and the end says "You're all caught up".
export const FIRST_ROWS = 10;
const STEPS = [10, 20];

/** How many rows the next "Show more" adds, after `clicks` earlier clicks */
export const stepAfter = (clicks) => STEPS[Math.min(clicks, STEPS.length - 1)];

/** How many rows show after `clicks` clicks: 10, 20, 40, 60, … */
export const rowsAfter = (clicks) => {
  let rows = FIRST_ROWS;
  for (let i = 0; i < clicks; i++) rows += stepAfter(i);
  return rows;
};

/**
 * For a list the page already has in full (filtered on the page).
 * Pass the whole list; show `visible`. Starts over when `resetOn` changes
 * (a new filter or search).
 */
export function useShowMoreLocal(list, resetOn = null) {
  const clicks = ref(0);
  const all = computed(() => unref(list) ?? []);
  const visible = computed(() => all.value.slice(0, rowsAfter(clicks.value)));
  const hasMore = computed(() => all.value.length > visible.value.length);
  const showMore = () => {
    if (hasMore.value) clicks.value++;
  };
  const reset = () => {
    clicks.value = 0;
  };
  if (resetOn) watch(resetOn, reset, { deep: true });

  return { visible, hasMore, showMore, reset, total: computed(() => all.value.length), shown: computed(() => visible.value.length) };
}

/**
 * For a list the server hands out in numbered pages (?page=1, 2, …) of any size.
 * Pages are fetched behind the scenes as rows are needed; people only ever see
 * 10, then 10 more, then 20 at a time. `fetchPage(page)` returns
 * { items, lastPage } (or { items, hasMore }), plus `total` when known.
 */
export function useShowMorePaged(fetchPage) {
  const buffer = ref([]);
  const clicks = ref(0);
  const page = ref(0);
  const lastPage = ref(1);
  const serverHasMore = ref(false);
  const total = ref(0);
  const loading = ref(false);
  const loadingMore = ref(false);
  const loaded = ref(false);
  const error = ref("");
  // The error itself, for pages that react to a particular one
  const failure = ref(null);
  let request = 0;

  const visible = computed(() => buffer.value.slice(0, rowsAfter(clicks.value)));
  const hasMore = computed(() => buffer.value.length > visible.value.length || serverHasMore.value);

  async function fetchNext(mine) {
    const next = page.value + 1;
    const res = await fetchPage(next);
    if (mine !== request) return false;
    buffer.value = [...buffer.value, ...(res.items ?? [])];
    page.value = next;
    lastPage.value = res.lastPage ?? lastPage.value;
    serverHasMore.value = res.hasMore ?? next < (res.lastPage ?? 1);
    if (res.total !== undefined) total.value = res.total;
    else if (!serverHasMore.value) total.value = buffer.value.length;
    return true;
  }

  /** Enough rows for what should show, fetching more pages if needed */
  async function fill(mine) {
    while (serverHasMore.value && buffer.value.length < rowsAfter(clicks.value)) {
      if (!(await fetchNext(mine))) return;
    }
  }

  async function load() {
    const mine = ++request;
    loading.value = true;
    error.value = "";
    failure.value = null;
    clicks.value = 0;
    page.value = 0;
    buffer.value = [];
    serverHasMore.value = true;
    try {
      await fill(mine);
      if (mine === request) loaded.value = true;
    } catch (e) {
      if (mine === request) {
        serverHasMore.value = false;
        failure.value = e;
        error.value = e?.response?.data?.message || e?.message || "This list couldn't be loaded.";
      }
    } finally {
      if (mine === request) loading.value = false;
    }
  }

  async function showMore() {
    if (!hasMore.value || loadingMore.value) return;
    const mine = request;
    loadingMore.value = true;
    try {
      clicks.value++;
      await fill(mine);
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || "More couldn't be loaded.";
    } finally {
      loadingMore.value = false;
    }
  }

  /** Loads again after a change (an order cancelled…), keeping as many rows showing as before */
  async function refresh() {
    const mine = ++request;
    const keep = clicks.value;
    error.value = "";
    try {
      const fresh = [];
      let next = 0;
      let more = true;
      let last = 1;
      let count;
      while (more && fresh.length < rowsAfter(keep)) {
        next++;
        const res = await fetchPage(next);
        if (mine !== request) return;
        fresh.push(...(res.items ?? []));
        last = res.lastPage ?? last;
        more = res.hasMore ?? next < last;
        if (res.total !== undefined) count = res.total;
      }
      buffer.value = fresh;
      page.value = next;
      lastPage.value = last;
      serverHasMore.value = more;
      total.value = count ?? (more ? total.value : fresh.length);
      clicks.value = keep;
    } catch (e) {
      if (mine === request) error.value = e?.response?.data?.message || e?.message || "This list couldn't be refreshed.";
    }
  }

  return { items: visible, visible, buffer, total, hasMore, loading, loadingMore, loaded, error, failure, load, showMore, refresh };
}

/**
 * For pages that keep the whole answer in one writable object (data.value =
 * { items, meta, … }) and change it in place. Pages are added to that list as
 * needed; show `rows` (10, then 10 more, then 20 at a time).
 * fetchPayload(page) returns the answer for one page; onPage(payload, page)
 * runs after each one (e.g. to copy preferences out of it).
 */
export function useShowMoreInto(dataRef, listKey, fetchPayload, onPage = null) {
  const clicks = ref(0);
  const page = ref(0);
  const lastPage = ref(1);
  const loadingMore = ref(false);
  let request = 0;

  const list = () => dataRef.value?.[listKey] ?? [];
  const rows = computed(() => list().slice(0, rowsAfter(clicks.value)));
  const hasMore = computed(() => list().length > rows.value.length || page.value < lastPage.value);
  const total = computed(() => dataRef.value?.meta?.total ?? list().length);

  async function fetchInto(next, mine, replace) {
    const payload = await fetchPayload(next);
    if (mine !== request) return false;
    if (replace) dataRef.value = payload;
    else dataRef.value[listKey].push(...(payload?.[listKey] ?? []));
    page.value = next;
    lastPage.value = payload?.meta?.last_page ?? 1;
    onPage?.(payload, next);
    return true;
  }

  async function fill(mine) {
    while (page.value < lastPage.value && list().length < rowsAfter(clicks.value)) {
      if (!(await fetchInto(page.value + 1, mine, false))) return;
    }
  }

  /** Starts over (opening the page, a new filter); throws like the page's own request would */
  async function load() {
    const mine = ++request;
    clicks.value = 0;
    if (!(await fetchInto(1, mine, true))) return;
    await fill(mine);
  }

  async function showMore() {
    if (!hasMore.value || loadingMore.value) return;
    const mine = request;
    loadingMore.value = true;
    try {
      clicks.value++;
      await fill(mine);
    } finally {
      loadingMore.value = false;
    }
  }

  /** Loads again after a change, keeping as many rows showing */
  async function refresh() {
    const keep = clicks.value;
    const mine = ++request;
    if (!(await fetchInto(1, mine, true))) return;
    clicks.value = keep;
    await fill(mine);
  }

  return { rows, hasMore, total, loadingMore, load, showMore, refresh };
}

/**
 * For pages whose own fetch(page) fills a rows ref and a meta ref
 * ({ current_page, last_page, total }). The fetch adds to the rows for
 * page > 1 (rows.value = page > 1 ? [...rows.value, ...new] : new); this
 * decides which pages to ask for and shows `rows` 10, 10, then 20 at a time.
 */
export function useShowMoreRows(rowsRef, metaRef, fetchPage) {
  const clicks = ref(0);
  const loadingMore = ref(false);
  const all = () => rowsRef.value ?? [];
  const rows = computed(() => all().slice(0, rowsAfter(clicks.value)));
  const current = () => Number(metaRef.value?.current_page ?? 1);
  const last = () => Number(metaRef.value?.last_page ?? 1);
  const hasMore = computed(() => all().length > rows.value.length || current() < last());
  const total = computed(() => metaRef.value?.total ?? all().length);

  async function fill() {
    while (all().length < rowsAfter(clicks.value) && current() < last()) {
      const before = current();
      await fetchPage(before + 1);
      if (current() <= before) return; // the page didn't load; don't loop
    }
  }
  /** Starts over (opening the page, a new filter or search) */
  async function load() {
    clicks.value = 0;
    await fetchPage(1);
    await fill();
  }
  async function showMore() {
    if (!hasMore.value || loadingMore.value) return;
    loadingMore.value = true;
    try {
      clicks.value++;
      await fill();
    } finally {
      loadingMore.value = false;
    }
  }
  /** Loads again after a change, keeping as many rows showing */
  async function refresh() {
    await fetchPage(1);
    await fill();
  }

  return { rows, hasMore, total, loadingMore, load, showMore, refresh };
}

/**
 * For a list the server hands out in slices (?offset=&limit=, answering
 * { items, meta: { has_more, total } }). `fetchSlice({ offset, limit })`
 * returns that answer; `load()` starts over, `showMore()` adds the next slice.
 */
export function useShowMoreRemote(fetchSlice) {
  const items = ref([]);
  const total = ref(0);
  const hasMore = ref(false);
  const loading = ref(false);
  const loadingMore = ref(false);
  const loaded = ref(false);
  const error = ref("");
  const clicks = ref(0);
  let request = 0;

  async function load() {
    const mine = ++request;
    loading.value = true;
    error.value = "";
    clicks.value = 0;
    try {
      const res = await fetchSlice({ offset: 0, limit: FIRST_ROWS });
      if (mine !== request) return;
      items.value = res.items ?? [];
      hasMore.value = !!res.meta?.has_more;
      total.value = res.meta?.total ?? items.value.length;
      loaded.value = true;
    } catch (e) {
      if (mine === request) error.value = e?.response?.data?.message || e?.message || "This list couldn't be loaded.";
    } finally {
      if (mine === request) loading.value = false;
    }
  }

  async function showMore() {
    if (!hasMore.value || loadingMore.value) return;
    const mine = request;
    loadingMore.value = true;
    try {
      const res = await fetchSlice({ offset: items.value.length, limit: stepAfter(clicks.value) });
      if (mine !== request) return;
      items.value = [...items.value, ...(res.items ?? [])];
      hasMore.value = !!res.meta?.has_more;
      total.value = res.meta?.total ?? total.value;
      clicks.value++;
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || "More couldn't be loaded.";
    } finally {
      loadingMore.value = false;
    }
  }

  /** Reloads what's showing (same number of rows) without starting over, e.g. for live updates */
  async function refresh() {
    if (loading.value || loadingMore.value) return;
    const mine = request;
    try {
      const res = await fetchSlice({ offset: 0, limit: Math.max(FIRST_ROWS, Math.min(items.value.length, 50)) });
      if (mine !== request) return;
      items.value = res.items ?? [];
      hasMore.value = !!res.meta?.has_more;
      total.value = res.meta?.total ?? items.value.length;
    } catch {
      // keep what's on screen; the next refresh tries again
    }
  }

  return { items, total, hasMore, loading, loadingMore, loaded, error, load, showMore, refresh };
}
