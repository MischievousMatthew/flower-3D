import { computed, ref, unref, watch } from "vue";

// Every long list on the site works the same way: 10 rows a page, with page
// numbers under it (components/common/PageNumbers.vue):
//   Showing 11–20 of 37        ‹ Prev  1 [2] 3 4  Next ›
// Each helper below returns the rows of the current page plus
//   page, pages, total, goTo(n)
// The older names (hasMore, showMore, loadingMore…) still work: showMore()
// goes to the next page and loadingMore is true while a page loads.
export const PER_PAGE = 10;

const pagesFor = (total) => Math.max(1, Math.ceil((Number(total) || 0) / PER_PAGE));
const sliceOf = (list, page) => list.slice((page - 1) * PER_PAGE, page * PER_PAGE);
const errorText = (e, fallback) => e?.response?.data?.message || e?.message || fallback;

/**
 * For a list the page already has in full (filtered on the page).
 * Pass the whole list; show `visible`. Back to page 1 when `resetOn` changes
 * (a new filter or search).
 */
export function useShowMoreLocal(list, resetOn = null) {
  const page = ref(1);
  const all = computed(() => unref(list) ?? []);
  const total = computed(() => all.value.length);
  const pages = computed(() => pagesFor(total.value));
  // A page that no longer exists (the list got shorter) shows the last one
  const current = computed(() => Math.min(page.value, pages.value));
  const visible = computed(() => sliceOf(all.value, current.value));
  const hasMore = computed(() => current.value < pages.value);
  const goTo = (n) => {
    page.value = Math.min(Math.max(1, n), pages.value);
  };
  const showMore = () => goTo(current.value + 1);
  const reset = () => {
    page.value = 1;
  };
  if (resetOn) watch(resetOn, reset, { deep: true });

  return { visible, hasMore, showMore, reset, goTo, page: current, pages, total, shown: computed(() => visible.value.length), loadingMore: ref(false) };
}

/**
 * For a list the server hands out in numbered pages (?page=1, 2, …) of any size.
 * The server's pages are fetched behind the scenes as needed; people see 10
 * rows a page. `fetchPage(page)` returns { items, lastPage } (or
 * { items, hasMore }), plus `total` when known.
 */
export function useShowMorePaged(fetchPage) {
  const buffer = ref([]);
  const page = ref(1);
  const serverPage = ref(0);
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

  const pages = computed(() =>
    total.value ? pagesFor(total.value) : Math.ceil(buffer.value.length / PER_PAGE) + (serverHasMore.value ? 1 : 0) || 1,
  );
  const visible = computed(() => sliceOf(buffer.value, page.value));
  const hasMore = computed(() => page.value < pages.value);

  async function fetchNext(mine) {
    const next = serverPage.value + 1;
    const res = await fetchPage(next);
    if (mine !== request) return false;
    buffer.value = [...buffer.value, ...(res.items ?? [])];
    serverPage.value = next;
    lastPage.value = res.lastPage ?? lastPage.value;
    serverHasMore.value = res.hasMore ?? next < (res.lastPage ?? 1);
    if (res.total !== undefined && res.total !== null) total.value = Number(res.total);
    else if (!serverHasMore.value) total.value = buffer.value.length;
    return true;
  }

  /** Enough rows for page `n`, fetching more of the server's pages if needed */
  async function fill(mine, n) {
    while (serverHasMore.value && buffer.value.length < n * PER_PAGE) {
      if (!(await fetchNext(mine))) return false;
    }
    return mine === request;
  }

  async function load() {
    const mine = ++request;
    loading.value = true;
    error.value = "";
    failure.value = null;
    page.value = 1;
    serverPage.value = 0;
    buffer.value = [];
    total.value = 0;
    serverHasMore.value = true;
    try {
      await fill(mine, 1);
      if (mine === request) loaded.value = true;
    } catch (e) {
      if (mine === request) {
        serverHasMore.value = false;
        failure.value = e;
        error.value = errorText(e, "This list couldn't be loaded.");
      }
    } finally {
      if (mine === request) loading.value = false;
    }
  }

  async function goTo(n) {
    if (loadingMore.value || n < 1 || n === page.value) return;
    const mine = request;
    loadingMore.value = true;
    try {
      if (await fill(mine, n)) page.value = Math.min(n, pages.value);
    } catch (e) {
      error.value = errorText(e, "That page couldn't be loaded.");
    } finally {
      loadingMore.value = false;
    }
  }
  const showMore = () => goTo(page.value + 1);

  /** Loads again after a change (an order cancelled…), staying on the same page */
  async function refresh() {
    const mine = ++request;
    const keep = page.value;
    error.value = "";
    try {
      const fresh = [];
      let next = 0;
      let more = true;
      let last = 1;
      let count;
      while (more && fresh.length < keep * PER_PAGE) {
        next++;
        const res = await fetchPage(next);
        if (mine !== request) return;
        fresh.push(...(res.items ?? []));
        last = res.lastPage ?? last;
        more = res.hasMore ?? next < last;
        if (res.total !== undefined && res.total !== null) count = Number(res.total);
      }
      buffer.value = fresh;
      serverPage.value = next;
      lastPage.value = last;
      serverHasMore.value = more;
      total.value = count ?? (more ? total.value : fresh.length);
      page.value = Math.min(keep, pages.value);
    } catch (e) {
      if (mine === request) error.value = errorText(e, "This list couldn't be refreshed.");
    }
  }

  return { items: visible, visible, buffer, total, page, pages, goTo, hasMore, loading, loadingMore, loaded, error, failure, load, showMore, refresh };
}

/**
 * For pages that keep the whole answer in one writable object (data.value =
 * { items, meta, … }) and change it in place. The server's pages are added to
 * that list as needed; show `rows` (the current page's 10).
 * fetchPayload(page) returns the answer for one page; onPage(payload, page)
 * runs after each one (e.g. to copy preferences out of it).
 */
export function useShowMoreInto(dataRef, listKey, fetchPayload, onPage = null) {
  const page = ref(1);
  const serverPage = ref(0);
  const lastPage = ref(1);
  const loadingMore = ref(false);
  let request = 0;

  const list = () => dataRef.value?.[listKey] ?? [];
  const total = computed(() => dataRef.value?.meta?.total ?? list().length);
  const pages = computed(() => pagesFor(total.value));
  const rows = computed(() => sliceOf(list(), page.value));
  const hasMore = computed(() => page.value < pages.value);

  async function fetchInto(next, mine, replace) {
    const payload = await fetchPayload(next);
    if (mine !== request) return false;
    if (replace) dataRef.value = payload;
    else dataRef.value[listKey].push(...(payload?.[listKey] ?? []));
    serverPage.value = next;
    lastPage.value = payload?.meta?.last_page ?? 1;
    onPage?.(payload, next);
    return true;
  }

  async function fill(mine, n) {
    while (serverPage.value < lastPage.value && list().length < n * PER_PAGE) {
      if (!(await fetchInto(serverPage.value + 1, mine, false))) return false;
    }
    return mine === request;
  }

  /** Starts over (opening the page, a new filter); throws like the page's own request would */
  async function load() {
    const mine = ++request;
    page.value = 1;
    if (!(await fetchInto(1, mine, true))) return;
    await fill(mine, 1);
  }

  async function goTo(n) {
    if (loadingMore.value || n < 1 || n === page.value) return;
    const mine = request;
    loadingMore.value = true;
    try {
      if (await fill(mine, n)) page.value = Math.min(n, pages.value);
    } finally {
      loadingMore.value = false;
    }
  }
  const showMore = () => goTo(page.value + 1);

  /** Loads again after a change, staying on the same page */
  async function refresh() {
    const keep = page.value;
    const mine = ++request;
    if (!(await fetchInto(1, mine, true))) return;
    await fill(mine, keep);
    page.value = Math.min(keep, pages.value);
  }

  return { rows, hasMore, total, page, pages, goTo, loadingMore, load, showMore, refresh };
}

/**
 * For pages whose own fetch(page) fills a rows ref and a meta ref
 * ({ current_page, last_page, total }). The fetch adds to the rows for
 * page > 1 (rows.value = page > 1 ? [...rows.value, ...new] : new); this
 * decides which of the server's pages to ask for and shows `rows`, the
 * current page's 10.
 */
export function useShowMoreRows(rowsRef, metaRef, fetchPage) {
  const page = ref(1);
  const loadingMore = ref(false);
  const all = () => rowsRef.value ?? [];
  const current = () => Number(metaRef.value?.current_page ?? 1);
  const last = () => Number(metaRef.value?.last_page ?? 1);
  const total = computed(() => metaRef.value?.total ?? all().length);
  const pages = computed(() => pagesFor(total.value));
  const rows = computed(() => sliceOf(all(), Math.min(page.value, pages.value)));
  const hasMore = computed(() => page.value < pages.value);

  async function fill(n) {
    while (all().length < n * PER_PAGE && current() < last()) {
      const before = current();
      await fetchPage(before + 1);
      if (current() <= before) return; // the page didn't load; don't loop
    }
  }
  /** Starts over (opening the page, a new filter or search) */
  async function load() {
    page.value = 1;
    await fetchPage(1);
    await fill(1);
  }
  async function goTo(n) {
    if (loadingMore.value || n < 1 || n === page.value) return;
    loadingMore.value = true;
    try {
      await fill(n);
      page.value = Math.min(n, pages.value);
    } finally {
      loadingMore.value = false;
    }
  }
  const showMore = () => goTo(page.value + 1);
  /** Loads again after a change, staying on the same page */
  async function refresh() {
    const keep = page.value;
    await fetchPage(1);
    await fill(keep);
    page.value = Math.min(keep, pages.value);
  }

  return { rows, hasMore, total, page, pages, goTo, loadingMore, load, showMore, refresh };
}

/**
 * For a list the server hands out in slices (?offset=&limit=, answering
 * { items, meta: { has_more, total } }). `fetchSlice({ offset, limit })`
 * returns that answer; `load()` starts over, `goTo(n)` fetches page n.
 */
export function useShowMoreRemote(fetchSlice) {
  const items = ref([]);
  const total = ref(0);
  const page = ref(1);
  const serverHasMore = ref(false);
  const loading = ref(false);
  const loadingMore = ref(false);
  const loaded = ref(false);
  const error = ref("");
  let request = 0;

  const pages = computed(() => (total.value ? pagesFor(total.value) : page.value + (serverHasMore.value ? 1 : 0)));
  const hasMore = computed(() => page.value < pages.value);

  async function fetchAt(n, mine) {
    const res = await fetchSlice({ offset: (n - 1) * PER_PAGE, limit: PER_PAGE });
    if (mine !== request) return false;
    items.value = res.items ?? [];
    serverHasMore.value = !!res.meta?.has_more;
    total.value = res.meta?.total ?? (n - 1) * PER_PAGE + items.value.length;
    page.value = n;
    return true;
  }

  async function load() {
    const mine = ++request;
    loading.value = true;
    error.value = "";
    try {
      if (await fetchAt(1, mine)) loaded.value = true;
    } catch (e) {
      if (mine === request) error.value = errorText(e, "This list couldn't be loaded.");
    } finally {
      if (mine === request) loading.value = false;
    }
  }

  async function goTo(n) {
    if (loadingMore.value || loading.value || n < 1 || n === page.value) return;
    const mine = ++request;
    loadingMore.value = true;
    try {
      await fetchAt(n, mine);
    } catch (e) {
      error.value = errorText(e, "That page couldn't be loaded.");
    } finally {
      loadingMore.value = false;
    }
  }
  const showMore = () => goTo(page.value + 1);

  /** Reloads the page that's showing without starting over, e.g. for live updates */
  async function refresh() {
    if (loading.value || loadingMore.value) return;
    const mine = request;
    try {
      const keep = page.value;
      if (!(await fetchAt(keep, mine))) return;
      // That page emptied out (rows went away): the last page there is
      if (!items.value.length && keep > 1) await fetchAt(pages.value, mine);
    } catch {
      // keep what's on screen; the next refresh tries again
    }
  }

  return { items, total, page, pages, goTo, hasMore, loading, loadingMore, loaded, error, load, showMore, refresh };
}
