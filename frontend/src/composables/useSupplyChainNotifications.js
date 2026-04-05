import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useAuth } from "./useAuth";
import { useAssignment } from "./useAssignment";
import { orderService } from "../services/orderService";

const scOrderCounts = ref({
  pending: 0,
  processing: 0,
  shipped: 0,
  received: 0,
  completed: 0,
});
const loadingSupplyChainNotifications = ref(false);

let pollingInterval = null;
let activeConsumers = 0;

const TRACKED_STATUSES = [
  "pending",
  "processing",
  "shipped",
  "received",
  "completed",
];

const shouldEnableSupplyChainNotifications = (user, canView) =>
  user?.type === "employee" && canView("sc_orders");

const stopPolling = () => {
  if (!pollingInterval) return;

  window.clearInterval(pollingInterval);
  pollingInterval = null;
};

export function useSupplyChainNotifications() {
  const { user, isAuthenticated } = useAuth();
  const { canView } = useAssignment();

  const hasSupplyChainOrderAccess = computed(() =>
    shouldEnableSupplyChainNotifications(user.value, canView),
  );

  const scOrdersBadgeCount = computed(
    () =>
      (scOrderCounts.value.pending || 0) +
      (scOrderCounts.value.processing || 0) +
      (scOrderCounts.value.shipped || 0) +
      (scOrderCounts.value.received || 0),
  );

  const refreshSupplyChainNotifications = async () => {
    if (!isAuthenticated.value || !hasSupplyChainOrderAccess.value) return;

    try {
      loadingSupplyChainNotifications.value = true;

      const responses = await Promise.all(
        TRACKED_STATUSES.map((status) =>
          orderService.list({ status, per_page: 1 }),
        ),
      );

      const nextCounts = {};

      TRACKED_STATUSES.forEach((status, index) => {
        const response = responses[index];
        nextCounts[status] =
          response?.data?.meta?.total ?? response?.data?.total ?? 0;
      });

      scOrderCounts.value = {
        ...scOrderCounts.value,
        ...nextCounts,
      };
    } catch (error) {
      console.error("Error loading supply chain order notifications:", error);
    } finally {
      loadingSupplyChainNotifications.value = false;
    }
  };

  const startPolling = async () => {
    if (!isAuthenticated.value || !hasSupplyChainOrderAccess.value) return;

    await refreshSupplyChainNotifications();

    if (pollingInterval) return;

    pollingInterval = window.setInterval(() => {
      refreshSupplyChainNotifications();
    }, 15000);
  };

  onMounted(() => {
    activeConsumers += 1;
    startPolling();
  });

  onUnmounted(() => {
    activeConsumers = Math.max(0, activeConsumers - 1);

    if (activeConsumers === 0) {
      stopPolling();
    }
  });

  watch(
    () => [isAuthenticated.value, user.value?.type, hasSupplyChainOrderAccess.value],
    async ([authenticated, userType, hasAccess]) => {
      if (!authenticated || userType !== "employee" || !hasAccess) {
        scOrderCounts.value = {
          pending: 0,
          processing: 0,
          shipped: 0,
          received: 0,
          completed: 0,
        };
        stopPolling();
        return;
      }

      await startPolling();
    },
    { immediate: true },
  );

  return {
    scOrderCounts,
    scOrdersBadgeCount,
    loadingSupplyChainNotifications,
    refreshSupplyChainNotifications,
  };
}
