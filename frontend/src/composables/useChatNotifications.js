import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useAuth } from "./useAuth";
import { useAssignment } from "./useAssignment";
import api from "../plugins/axios";

const unreadChatCount = ref(0);
const chatConversations = ref([]);
const loadingChatNotifications = ref(false);

let pollingInterval = null;
let activeConsumers = 0;
let initializedSnapshot = false;
let previousUnreadByConversation = new Map();
let notificationPermissionRequested = false;

const shouldEnableChatNotifications = (user) => {
  if (!user) return false;

  if (user.type === "employee") {
    // We check if the employee has CRM module access before polling
    const { canView } = useAssignment();
    return canView("crm");
  }

  return ["customer", "vendor"].includes(user.role);
};

const getChatEndpoint = (user) => {
  if (!user) return null;

  return user.role === "customer"
    ? "/chat/my-conversations"
    : "/chat/conversations";
};

const getChatRoute = (user) => {
  if (!user) return "/";

  if (user.type === "employee") {
    return "/erp/crm/chat";
  }

  return user.role === "customer" ? "/customer/chat" : "/vendor/chat";
};

const canUseBrowserNotifications = () =>
  typeof window !== "undefined" &&
  "Notification" in window &&
  Notification.permission === "granted";

const requestNotificationPermission = () => {
  if (
    notificationPermissionRequested ||
    typeof window === "undefined" ||
    !("Notification" in window) ||
    Notification.permission !== "default"
  ) {
    return;
  }

  notificationPermissionRequested = true;

  const handleInteraction = () => {
    Notification.requestPermission().catch(() => {});
  };

  document.addEventListener("click", handleInteraction, { once: true });
};

const showBrowserNotification = (title, body, route) => {
  if (!canUseBrowserNotifications()) return;

  const notification = new Notification(title, {
    body,
    tag: `chat-${route}`,
  });

  notification.onclick = () => {
    window.focus();
    window.location.assign(route);
    notification.close();
  };
};

const fetchChatNotifications = async (user, router, { notify = false } = {}) => {
  const endpoint = getChatEndpoint(user);
  if (!endpoint) return;

  try {
    loadingChatNotifications.value = true;

    const { data } = await api.get(endpoint);
    if (!data?.success) return;

    const conversations = Array.isArray(data.conversations)
      ? data.conversations
      : [];

    chatConversations.value = conversations;
    unreadChatCount.value = conversations.reduce(
      (sum, conversation) => sum + (conversation.unread_count || 0),
      0,
    );

    const nextUnreadByConversation = new Map();

    conversations.forEach((conversation) => {
      nextUnreadByConversation.set(conversation.id, conversation.unread_count || 0);
    });

    if (notify && initializedSnapshot) {
      const chatRoute = getChatRoute(user);
      const isChatPage = window.location.pathname.startsWith(chatRoute);

      conversations.forEach((conversation) => {
        const previousUnread =
          previousUnreadByConversation.get(conversation.id) || 0;
        const currentUnread = conversation.unread_count || 0;

        if (currentUnread <= previousUnread || isChatPage) return;

        const senderName =
          conversation.vendor?.display_name ||
          conversation.customer?.display_name ||
          "New message";
        const messagePreview =
          conversation.last_message || "You have a new chat message.";
        const toastId = `chat-${conversation.id}-${currentUnread}-${conversation.last_message_time}`;

        toast.info(`${senderName}: ${messagePreview}`, {
          toastId,
          autoClose: 5000,
          onClick: () => router.push(chatRoute),
        });

        if (document.hidden) {
          showBrowserNotification(
            "New chat message",
            `${senderName}: ${messagePreview}`,
            chatRoute,
          );
        }
      });
    }

    previousUnreadByConversation = nextUnreadByConversation;
    initializedSnapshot = true;
  } catch (error) {
    console.error("Error loading chat notifications:", error);
  } finally {
    loadingChatNotifications.value = false;
  }
};

export function useChatNotifications() {
  const router = useRouter();
  const { user, isAuthenticated } = useAuth();

  const chatRoute = computed(() => getChatRoute(user.value));
  const hasUnreadChats = computed(() => unreadChatCount.value > 0);
  const supportsChatNotifications = computed(() =>
    shouldEnableChatNotifications(user.value),
  );

  const stopPolling = () => {
    if (!pollingInterval) return;

    window.clearInterval(pollingInterval);
    pollingInterval = null;
  };

  const startPolling = async () => {
    if (!isAuthenticated.value || !supportsChatNotifications.value) return;

    await fetchChatNotifications(user.value, router);

    if (pollingInterval) return;

    pollingInterval = window.setInterval(() => {
      fetchChatNotifications(user.value, router, { notify: true });
    }, 10000);
  };

  const refreshChatNotifications = async ({ notify = false } = {}) => {
    if (!isAuthenticated.value || !supportsChatNotifications.value) return;

    await fetchChatNotifications(user.value, router, { notify });
  };

  onMounted(() => {
    activeConsumers += 1;
    requestNotificationPermission();
    startPolling();
  });

  onUnmounted(() => {
    activeConsumers = Math.max(0, activeConsumers - 1);

    if (activeConsumers === 0) {
      stopPolling();
    }
  });

  watch(
    () => [isAuthenticated.value, user.value?.role, user.value?.type],
    async ([authenticated]) => {
      if (!authenticated || !supportsChatNotifications.value) {
        chatConversations.value = [];
        unreadChatCount.value = 0;
        previousUnreadByConversation = new Map();
        initializedSnapshot = false;
        stopPolling();
        return;
      }

      await startPolling();
    },
    { immediate: true },
  );

  return {
    unreadChatCount,
    chatConversations,
    loadingChatNotifications,
    hasUnreadChats,
    supportsChatNotifications,
    chatRoute,
    refreshChatNotifications,
  };
}
