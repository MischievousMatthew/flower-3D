// composables/useSidebarState.js
import { ref } from "vue";

const isCollapsed = ref(false);
const isMobileOpen = ref(false);

export function useSidebarState() {
  const toggleMobile = () => (isMobileOpen.value = !isMobileOpen.value);
  const closeMobile = () => (isMobileOpen.value = false);
  return { isCollapsed, isMobileOpen, toggleMobile, closeMobile };
}
