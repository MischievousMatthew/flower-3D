// composables/useSidebarState.js
import { ref } from "vue";

const isCollapsed = ref(false);

export function useSidebarState() {
  return { isCollapsed };
}
