// A row of tabs that may be wider than the page: the mouse wheel scrolls it
// sideways, arrow buttons show when tabs are cut off, and the open tab is
// kept in view.
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

export function useTabScroller() {
  const strip = ref(null);
  const canLeft = ref(false);
  const canRight = ref(false);
  const route = useRoute();
  let observer = null;

  const update = () => {
    const el = strip.value;
    if (!el) return;
    canLeft.value = el.scrollLeft > 2;
    canRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 2;
  };

  const scrollByPage = (dir) => {
    const el = strip.value;
    if (el) el.scrollBy({ left: dir * Math.max(160, el.clientWidth * 0.7), behavior: "smooth" });
  };

  // A plain vertical wheel turn moves the tabs sideways, only while there's room to move
  const onWheel = (e) => {
    const el = strip.value;
    if (!el || Math.abs(e.deltaX) > Math.abs(e.deltaY) || el.scrollWidth <= el.clientWidth) return;
    const atStart = el.scrollLeft <= 0 && e.deltaY < 0;
    const atEnd = el.scrollLeft + el.clientWidth >= el.scrollWidth - 1 && e.deltaY > 0;
    if (atStart || atEnd) return;
    e.preventDefault();
    el.scrollLeft += e.deltaY;
  };

  const showActive = async () => {
    await nextTick();
    const el = strip.value;
    const active = el?.querySelector('[aria-current="page"]');
    if (!el || !active) return update();
    const left = active.offsetLeft; // the strip is position: relative
    if (left < el.scrollLeft || left + active.offsetWidth > el.scrollLeft + el.clientWidth) {
      el.scrollLeft = left - (el.clientWidth - active.offsetWidth) / 2;
    }
    update();
  };

  onMounted(() => {
    const el = strip.value;
    if (!el) return;
    el.addEventListener("wheel", onWheel, { passive: false });
    el.addEventListener("scroll", update, { passive: true });
    if (typeof ResizeObserver !== "undefined") {
      observer = new ResizeObserver(update);
      observer.observe(el);
    }
    showActive();
  });
  onBeforeUnmount(() => {
    strip.value?.removeEventListener("wheel", onWheel);
    strip.value?.removeEventListener("scroll", update);
    observer?.disconnect();
  });
  watch(() => route.name, showActive);

  return { strip, canLeft, canRight, scrollByPage };
}
