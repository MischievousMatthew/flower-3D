<template>
  <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
  <div class="chat-layout" :class="{ embedded: isEmbeddedLayout }">
    <component :is="headerComponent" v-if="headerComponent" />

    <div class="chat-container" :style="contentStyle">
      <div class="chat-content">
        <!-- Sidebar - Conversations List -->
        <div class="conversations-sidebar" :style="sidebarStyle">
          <div class="sidebar-header">
            <div class="search-box">
              <span class="search-icon">🔍</span>
              <input
                type="text"
                :placeholder="`Search ${counterpartLabel}...`"
                v-model="searchQuery"
              />
            </div>

            <div class="tabs">
              <button
                class="tab"
                :class="{ active: activeTab === 'all' }"
                @click="activeTab = 'all'"
              >
                All Chats
              </button>
              <button
                class="tab"
                :class="{ active: activeTab === 'unread' }"
                @click="activeTab = 'unread'"
              >
                Unread
                <span v-if="unreadCount > 0" class="badge">{{
                  unreadCount
                }}</span>
              </button>
            </div>
          </div>

          <div class="conversations-list">
            <div
              v-for="conversation in filteredConversations"
              :key="conversation.id"
              class="conversation-item"
              :class="{
                active: selectedConversation?.id === conversation.id,
                unread: conversation.unread_count > 0,
              }"
              @click="selectConversation(conversation)"
            >
              <div class="vendor-avatar-wrapper">
                <img
                  v-if="getOtherUser(conversation).avatar"
                  :src="getOtherUser(conversation).avatar"
                  class="vendor-avatar"
                  :alt="getOtherUser(conversation).name"
                />
                <div v-else class="vendor-avatar-placeholder">
                  {{ getInitials(getOtherUser(conversation).name) }}
                </div>
                <span
                  v-if="getOtherUser(conversation).online"
                  class="online-indicator"
                ></span>
              </div>

              <div class="conversation-info">
                <div class="conversation-header">
                  <h4 class="vendor-name">
                    {{ getOtherUser(conversation).display_name }}
                  </h4>
                  <span class="conversation-time">{{
                    conversation.last_message_time
                  }}</span>
                </div>
                <div class="conversation-preview">
                  <p>{{ conversation.last_message || "No messages yet" }}</p>

                  <span
                    v-if="conversation.unread_count > 0"
                    class="unread-badge"
                  >
                    {{ conversation.unread_count }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div
              v-if="filteredConversations.length === 0"
              class="empty-conversations"
            >
              <div class="empty-icon">💬</div>
              <p>No conversations found</p>
              <button
                class="btn-primary"
                @click="showNewChatModal = true"
                :disabled="!canStartConversation"
              >
                Start New Chat
              </button>
              <p v-if="!canStartConversation" class="read-only-note">
                View-only access allows reading conversations only.
              </p>
            </div>
          </div>
        </div>

        <!-- Main Chat Area -->
        <div class="main-chat-area">
          <div v-if="!selectedConversation" class="empty-state">
            <div class="empty-icon">💬</div>
            <h3>Select a conversation</h3>
            <p>
              Choose a {{ counterpartLabelSingular }} from the list to
              start chatting
            </p>
            <button
              class="btn-primary"
              @click="showNewChatModal = true"
              :disabled="!canStartConversation"
            >
              Start New Chat
            </button>
            <p v-if="!canStartConversation" class="read-only-note">
              View-only access allows reading conversations only.
            </p>
          </div>

          <div v-else class="chat-window">
            <!-- Chat Window Header -->
            <div class="chat-window-header">
              <div class="vendor-info-header">
                <div class="vendor-avatar-wrapper">
                  <img
                    v-if="getOtherUser(selectedConversation).avatar"
                    :src="getOtherUser(selectedConversation).avatar"
                    class="vendor-avatar"
                    :alt="getOtherUser(selectedConversation).name"
                  />
                  <div v-else class="vendor-avatar-placeholder">
                    {{ getInitials(getOtherUser(selectedConversation).name) }}
                  </div>
                  <span
                    v-if="getOtherUser(selectedConversation).online"
                    class="online-indicator"
                  ></span>
                </div>
                <div class="vendor-details">
                  <div class="vendor-name">
                    {{ getOtherUser(selectedConversation).display_name }}
                  </div>
                  <div class="vendor-status">
                    {{
                      getOtherUser(selectedConversation).online
                        ? "Online"
                        : "Offline"
                    }}
                  </div>
                </div>
              </div>
              <div class="header-actions">
                <button
                  class="action-btn"
                  title="More"
                  @click="toggleUserDetails"
                >
                  ⋮
                </button>
              </div>
            </div>

            <!-- Messages Area -->
            <div class="messages-container" ref="messagesArea">
              <div
                v-if="
                  !selectedConversation.messages ||
                  selectedConversation.messages.length === 0
                "
                class="no-messages"
              >
                <div class="empty-icon">💬</div>
                <p>No messages yet. Start the conversation!</p>
              </div>

              <div v-else>
                <div class="message-date">
                  <span>Today</span>
                </div>

                <div
                  v-for="message in selectedConversation.messages"
                  :key="message.id"
                  class="message-wrapper"
                  :class="{ sent: message.is_own, received: !message.is_own }"
                >
                  <div v-if="!message.is_own" class="message-avatar-wrapper">
                    <img
                      v-if="getOtherUser(selectedConversation).avatar"
                      :src="getOtherUser(selectedConversation).avatar"
                      class="message-avatar"
                      :alt="getOtherUser(selectedConversation).name"
                    />
                    <div v-else class="message-avatar-placeholder">
                      {{ getInitials(getOtherUser(selectedConversation).name) }}
                    </div>
                  </div>

                  <div class="message-content">
                    <div class="message-bubble">
                      <!-- TEXT ONLY (show only if there's text and not just attachment placeholder) -->
                      <p
                        v-if="
                          message.text && !isAttachmentPlaceholder(message.text)
                        "
                        class="message-text"
                      >
                        {{ message.text }}
                      </p>

                      <!-- ATTACHMENTS (images/files) -->
                      <div
                        v-if="
                          message.attachments && message.attachments.length > 0
                        "
                        class="message-attachments"
                      >
                        <div
                          v-for="(attachment, idx) in message.attachments"
                          :key="idx"
                          class="attachment-item"
                        >
                          <img
                            v-if="isImageFile(attachment.type)"
                            :src="attachment.url"
                            alt="Attachment"
                            class="attachment-image"
                            @click="openImageViewer(attachment.url)"
                          />

                          <div v-else class="attachment-file">
                            <span class="file-icon">
                              {{ getFileIcon(attachment.type) }}
                            </span>
                            <div class="file-info">
                              <span class="file-name">{{
                                attachment.name
                              }}</span>
                              <span class="file-size">
                                {{ formatFileSize(attachment.size) }}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="message-meta">
                      <span class="message-time">{{ message.time }}</span>
                      <span v-if="message.is_own" class="message-status">
                        {{ message.read ? "✓✓" : "✓" }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Typing Indicator - only show when OTHER user is typing -->
              <div v-if="isOtherUserTyping" class="typing-wrapper">
                <div class="message-avatar-wrapper">
                  <img
                    v-if="getOtherUser(selectedConversation).avatar"
                    :src="getOtherUser(selectedConversation).avatar"
                    class="message-avatar"
                    :alt="getOtherUser(selectedConversation).name"
                  />
                  <div v-else class="message-avatar-placeholder">
                    {{ getInitials(getOtherUser(selectedConversation).name) }}
                  </div>
                </div>
                <div class="typing-indicator">
                  <span></span>
                  <span></span>
                  <span></span>
                </div>
              </div>
            </div>

            <!-- Message Input -->
            <div class="message-input-area" :style="messageInputArea">
              <!-- Selected Files Preview -->
              <div v-if="selectedFiles.length > 0" class="files-preview">
                <div
                  v-for="(file, index) in selectedFiles"
                  :key="index"
                  class="file-preview-item"
                >
                  <span class="file-icon">{{ getFileIcon(file.type) }}</span>
                  <span class="file-name">{{ file.name }}</span>
                  <button class="remove-file-btn" @click="removeFile(index)" :disabled="!canSendMessages">
                    ✕
                  </button>
                </div>
              </div>

              <div class="input-wrapper">
                <button
                  class="attach-btn"
                  @click="openFileUpload"
                  title="Attach file"
                  :disabled="!canSendMessages"
                >
                  📎
                </button>
                <input
                  type="file"
                  ref="fileInput"
                  @change="handleFileUpload"
                  accept="image/*,.pdf,.doc,.docx,.txt"
                  multiple
                  style="display: none"
                />

                <input
                  type="text"
                  v-model="messageText"
                  :placeholder="
                    canSendMessages
                      ? 'Type your message...'
                      : 'You have view-only access to this chat'
                  "
                  @keyup.enter="sendMessage"
                  @input="handleTyping"
                  :disabled="!canSendMessages || isSending"
                  class="message-input"
                />

                <button class="emoji-btn" title="Emoji">😊</button>

                <button
                  class="send-btn"
                  @click="sendMessage"
                  :disabled="!canSend"
                  title="Send message"
                >
                  ➤
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Sidebar - User Details -->
        <div
          v-if="showUserDetails && selectedConversation"
          class="info-sidebar"
        >
          <div class="right-sidebar-header">
            <h3>{{ isVendor ? "Customer" : "Vendor" }} Information</h3>
            <button class="close-btn" @click="toggleUserDetails">✕</button>
          </div>

          <div class="vendor-profile">
            <div class="profile-avatar-wrapper">
              <img
                v-if="getOtherUser(selectedConversation).avatar"
                :src="getOtherUser(selectedConversation).avatar"
                class="profile-avatar"
                :alt="getOtherUser(selectedConversation).name"
              />
              <div v-else class="profile-avatar-placeholder">
                {{ getInitials(getOtherUser(selectedConversation).name) }}
              </div>
            </div>
            <h4>{{ getOtherUser(selectedConversation).display_name }}</h4>
            <p class="vendor-email">
              {{ getOtherUser(selectedConversation).email }}
            </p>
            <p class="vendor-contact">
              {{ getOtherUser(selectedConversation).contact_number }}
            </p>
          </div>

          <div class="sidebar-section">
            <h4 class="section-title">Quick Actions</h4>
            <button
              v-if="!isVendor"
              class="action-button"
              @click="viewVendorShop"
            >
              <span class="action-icon">🏪</span>
              <span>View Shop</span>
            </button>
            <button v-if="!isVendor" class="action-button" @click="viewOrders">
              <span class="action-icon">📦</span>
              <span>My Orders</span>
            </button>
          </div>

          <div class="sidebar-section" v-if="userDetails">
            <h4 class="section-title">Details</h4>
            <div class="detail-item">
              <span class="detail-label">Address:</span>
              <span class="detail-value">{{
                userDetails.address || "N/A"
              }}</span>
            </div>
          </div>

          <div class="sidebar-section">
            <h4 class="section-title">Shared Files</h4>
            <div v-if="sharedFiles.length > 0" class="shared-files-grid">
              <div
                v-for="file in sharedFiles"
                :key="file.name"
                class="shared-file-item"
                @click="downloadFile(file)"
              >
                <span class="file-icon-large">{{ file.icon }}</span>
                <span class="file-name-small">{{ file.name }}</span>
                <span class="file-size-small">{{ file.size }}</span>
              </div>
            </div>
            <div v-else class="no-shared-files">
              <p>No shared files</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Image Viewer Modal -->
    <div v-if="imageViewerUrl" class="modal-overlay" @click="closeImageViewer">
      <div class="image-viewer-modal" @click.stop>
        <button class="close-viewer-btn" @click="closeImageViewer">✕</button>
        <img :src="imageViewerUrl" alt="Full size image" />
      </div>
    </div>

    <!-- New Chat Modal -->
    <div
      v-if="showNewChatModal"
      class="modal-overlay"
      @click="showNewChatModal = false"
    >
      <div class="new-chat-modal" @click.stop>
        <div class="modal-header">
          <h3>Start New Chat</h3>
          <button class="close-btn" @click="showNewChatModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="search-box">
            <span class="search-icon">🔍</span>
            <input
              type="text"
              :placeholder="`Search ${counterpartLabel}...`"
              v-model="userSearchQuery"
              @input="searchUsers"
            />
          </div>
          <div class="vendors-list">
            <div
              v-for="user in searchedUsers"
              :key="user.id"
              class="vendor-item"
              :class="{ disabled: !canStartConversation }"
              @click="startNewConversation(user)"
            >
              <img
                v-if="user.avatar"
                :src="user.avatar"
                class="vendor-avatar-small"
                :alt="user.name"
              />
              <div v-else class="vendor-avatar-placeholder-small">
                {{ getInitials(user.name) }}
              </div>
              <div class="vendor-item-info">
                <div class="vendor-item-name">
                  {{ user.display_name }}
                </div>
                <div class="vendor-item-email">{{ user.email }}</div>
              </div>
            </div>
            <div
              v-if="searchedUsers.length === 0 && userSearchQuery"
              class="no-vendors"
            >
              <p>No {{ counterpartLabel }} found</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import NavHeader from "../NavHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import { toast } from "vue3-toastify";
import api from "../../plugins/axios";

const props = defineProps({
  chatRole: {
    type: String,
    default: "auto",
  },
  layoutMode: {
    type: String,
    default: "default",
  },
  counterpartLabel: {
    type: String,
    default: "",
  },
  canSendMessages: {
    type: Boolean,
    default: true,
  },
  allowNewChat: {
    type: Boolean,
    default: true,
  },
});

const router = useRouter();
const { user, isAuthenticated } = useAuth();

const isEmbeddedLayout = computed(() => props.layoutMode === "embedded");
const isCustomer = computed(() => {
  if (props.chatRole === "customer") return true;
  if (props.chatRole === "vendor") return false;
  return user.value?.role === "customer";
});
const isVendor = computed(() => {
  if (props.chatRole === "vendor") return true;
  if (props.chatRole === "customer") return false;
  return user.value?.role === "vendor";
});
const counterpartLabel = computed(() => {
  if (props.counterpartLabel) return props.counterpartLabel;
  return isVendor.value ? "customers" : "vendors";
});
const counterpartLabelSingular = computed(() =>
  counterpartLabel.value.endsWith("s")
    ? counterpartLabel.value.slice(0, -1)
    : counterpartLabel.value,
);
const canSendMessages = computed(() => props.canSendMessages);
const canStartConversation = computed(
  () => props.canSendMessages && props.allowNewChat,
);
const headerComponent = computed(() => {
  if (isEmbeddedLayout.value) {
    return null;
  }

  return isVendor.value ? VendorSidebar : NavHeader;
});

const contentStyle = computed(() => {
  if (isEmbeddedLayout.value) {
    return {};
  }

  if (isCustomer.value) {
    return { marginTop: "80px", marginLeft: "0" };
  }

  return {};
});

const sidebarStyle = computed(() => {
  if (isEmbeddedLayout.value) {
    return {};
  }

  if (isVendor.value) {
    return { marginLeft: "260px" };
  }

  return {};
});

const messageInputArea = computed(() => {
  if (isEmbeddedLayout.value) {
    return {};
  }

  if (isCustomer.value) {
    return { marginBottom: "80px" };
  }

  return {};
});

// State
const activeTab = ref("all");
const searchQuery = ref("");
const selectedConversation = ref(null);
const messageText = ref("");
const isOtherUserTyping = ref(false);
const isSending = ref(false);
const showUserDetails = ref(false);
const messagesArea = ref(null);
const fileInput = ref(null);
const selectedFiles = ref([]);
const conversations = ref([]);
const isLoading = ref(false);
const isLoadingMessage = ref("");
const sharedFiles = ref([]);
const userDetails = ref(null);
const imageViewerUrl = ref(null);
const showNewChatModal = ref(false);
const userSearchQuery = ref("");
const searchedUsers = ref([]);

// Polling
let pollingInterval = null;
const lastMessageId = ref(0);

// Computed
const filteredConversations = computed(() => {
  let result = conversations.value;

  if (activeTab.value === "unread") {
    result = result.filter((c) => c.unread_count > 0);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter((c) => {
      const otherUser = getOtherUser(c);
      return (
        otherUser.name.toLowerCase().includes(query) ||
        otherUser.display_name.toLowerCase().includes(query) ||
        otherUser.email.toLowerCase().includes(query) ||
        c.last_message?.toLowerCase().includes(query)
      );
    });
  }

  return result;
});

const unreadCount = computed(() => {
  return conversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0);
});

const canSend = computed(() => {
  return (
    props.canSendMessages &&
    (messageText.value.trim() || selectedFiles.value.length > 0) &&
    !isSending.value
  );
});

// Methods
const getInitials = (name) => {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
};

const getOtherUser = (conversation) => {
  if (!conversation)
    return {
      name: "",
      email: "",
      avatar: null,
      online: false,
      display_name: "",
    };

  if (isVendor.value) {
    return conversation.customer || {};
  } else {
    return conversation.vendor || {};
  }
};

const isAttachmentPlaceholder = (text) => {
  return text === "📷 Image" || text === "📎 File" || text === " ";
};

const toggleUserDetails = async () => {
  showUserDetails.value = !showUserDetails.value;

  if (showUserDetails.value && selectedConversation.value) {
    await loadUserDetails();
  }
};

const loadUserDetails = async () => {
  try {
    const otherUserId = getOtherUser(selectedConversation.value).id;
    const endpoint = isVendor.value
      ? `chat/customer/${otherUserId}/details`
      : `chat/vendor/${otherUserId}/details`;

    const { data } = await api.get(endpoint);

    if (data.success) {
      userDetails.value = isVendor.value ? data.customer : data.vendor;
      sharedFiles.value = userDetails.value.shared_files || [];
    }
  } catch (error) {
    console.error("Error loading user details:", error);
  }
};

const selectConversation = async (conversation) => {
  try {
    selectedConversation.value = conversation;
    showUserDetails.value = false;

    if (conversation.unread_count > 0) {
      conversation.unread_count = 0;
    }

    if (conversation.messages && conversation.messages.length > 0) {
      nextTick(() => scrollToBottom());
      return;
    }

    const { data } = await api.get(
      `/chat/conversations/${conversation.id}/messages`,
    );

    if (data.success) {
      conversation.messages = data.messages || [];

      if (isVendor.value) {
        conversation.customer = conversation.customer || data.other_user;
      } else {
        conversation.vendor = conversation.vendor || data.other_user;
      }

      if (conversation.messages.length > 0) {
        lastMessageId.value = Math.max(
          ...conversation.messages.map((m) => m.id),
        );
      }

      nextTick(() => scrollToBottom());
    }
  } catch (error) {
    console.error("Error fetching messages:", error);
    toast.error("Failed to load messages");
  }
};

const formatLastMessagePreview = (conversation, message, currentUserId) => {
  // Check if message has sender_id and compare with current user
  const isOwnMessage =
    message.sender_id === currentUserId || message.is_own === true;

  const senderName = isOwnMessage
    ? "You"
    : getOtherUser(conversation).display_name ||
      getOtherUser(conversation).name;

  if (message.attachments && message.attachments.length > 0) {
    const allImages = message.attachments.every(
      (a) => a.type && a.type.startsWith("image/"),
    );

    return allImages
      ? `${senderName} sent a photo`
      : `${senderName} sent a file`;
  } else {
    const messageText = message.text || "";
    return `${senderName}: ${messageText}`;
  }
};

const sendMessage = async () => {
  if (!props.canSendMessages || !canSend.value) return;

  const textToSend = messageText.value.trim();
  const filesToSend = [...selectedFiles.value];

  if (!textToSend && filesToSend.length === 0) return;

  try {
    isSending.value = true;

    const formData = new FormData();
    formData.append("conversation_id", selectedConversation.value.id);

    if (textToSend) {
      formData.append("message", textToSend);
    }

    filesToSend.forEach((file) => {
      formData.append("attachments[]", file);
    });

    const { data } = await api.post("chat/messages/send", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (data.success) {
      data.message.is_own = true;
      selectedConversation.value.messages.push(data.message);

      selectedConversation.value.last_message = formatLastMessagePreview(
        selectedConversation.value,
        data.message,
        user.value?.id,
      );

      selectedConversation.value.last_message_time = "Just now";
      lastMessageId.value = data.message.id;
      messageText.value = "";
      selectedFiles.value = [];

      nextTick(() => scrollToBottom());
    }
  } catch (error) {
    console.error("Error sending message:", error);
    toast.error("Failed to send message");
  } finally {
    isSending.value = false;
  }
};

let typingTimeout = null;
const handleTyping = () => {
  if (typingTimeout) {
    clearTimeout(typingTimeout);
  }

  typingTimeout = setTimeout(() => {}, 2000);
};

const scrollToBottom = () => {
  if (messagesArea.value) {
    nextTick(() => {
      messagesArea.value.scrollTop = messagesArea.value.scrollHeight;
    });
  }
};

const openFileUpload = () => {
  if (!props.canSendMessages) return;
  fileInput.value?.click();
};

const handleFileUpload = (event) => {
  if (!props.canSendMessages) return;
  const files = Array.from(event.target.files);
  selectedFiles.value.push(...files);
  event.target.value = "";
};

const removeFile = (index) => {
  if (!props.canSendMessages) return;
  selectedFiles.value.splice(index, 1);
};

const openImageViewer = (url) => {
  imageViewerUrl.value = url;
};

const closeImageViewer = () => {
  imageViewerUrl.value = null;
};

const isImageFile = (type) => {
  return type && type.startsWith("image/");
};

const getFileIcon = (fileType) => {
  if (!fileType) return "📎";
  if (fileType.includes("image")) return "🖼️";
  if (fileType.includes("pdf")) return "📄";
  if (fileType.includes("word") || fileType.includes("document")) return "📝";
  if (fileType.includes("sheet")) return "📊";
  if (fileType.includes("zip") || fileType.includes("archive")) return "📦";
  return "📎";
};

const formatFileSize = (bytes) => {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const viewVendorShop = () => {
  if (selectedConversation.value?.vendor?.id) {
    router.push(`/shop/vendor/${selectedConversation.value.vendor.id}`);
  }
};

const viewOrders = () => {
  router.push("/customer/orders");
};

const downloadFile = (file) => {
  window.open(file.url, "_blank");
};

const searchUsers = async () => {
  if (!canStartConversation.value) {
    searchedUsers.value = [];
    return;
  }

  if (!userSearchQuery.value.trim()) {
    searchedUsers.value = [];
    return;
  }

  try {
    const endpoint = isVendor.value
      ? "/chat/search-customers"
      : "/chat/search-vendors";
    const { data } = await api.get(endpoint, {
      params: { search: userSearchQuery.value },
    });

    if (data.success) {
      searchedUsers.value = isVendor.value ? data.customers : data.vendors;
    }
  } catch (error) {
    console.error("Error searching users:", error);
  }
};

const startNewConversation = async (otherUser) => {
  if (!canStartConversation.value) return;

  try {
    const { data } = await api.post("/chat/conversations/start", {
      [isVendor.value ? "customer_id" : "vendor_id"]: otherUser.id,
      message: "Hi! I'd like to connect with you.",
    });

    if (data.success) {
      showNewChatModal.value = false;
      await fetchConversations();

      // Select the new conversation
      const newConv = conversations.value.find(
        (c) => c.id === data.conversation.id,
      );
      if (newConv) {
        await selectConversation(newConv);
      }

      toast.success("Conversation started!");
    }
  } catch (error) {
    console.error("Error starting conversation:", error);
    toast.error("Failed to start conversation");
  }
};

const pollNewMessages = async () => {
  if (!selectedConversation.value) return;

  try {
    const { data } = await api.get("/chat/poll", {
      params: {
        last_message_id: lastMessageId.value,
        conversation_id: selectedConversation.value.id,
      },
    });

    if (data.success && data.new_messages?.length > 0) {
      const relevantMessages = data.new_messages.filter(
        (msg) => msg.conversation_id === selectedConversation.value.id,
      );

      if (relevantMessages.length > 0) {
        relevantMessages.forEach((msg) => {
          msg.is_own = false;
        });

        selectedConversation.value.messages.push(...relevantMessages);
        lastMessageId.value = data.last_message_id;

        const latestMessage = relevantMessages[relevantMessages.length - 1];

        selectedConversation.value.last_message = formatLastMessagePreview(
          selectedConversation.value,
          latestMessage,
          user.value?.id,
        );

        selectedConversation.value.last_message_time = "Just now";

        nextTick(() => scrollToBottom());

        isOtherUserTyping.value = true;
        setTimeout(() => {
          isOtherUserTyping.value = false;
        }, 1000);
      }
    }

    if (data.updated_conversations) {
      data.updated_conversations.forEach((updatedConv) => {
        const conv = conversations.value.find((c) => c.id === updatedConv.id);
        if (conv) {
          const lastMessage = {
            text: updatedConv.last_message,
            attachments: updatedConv.last_message_attachments || [],
          };

          const isOwnMessage =
            updatedConv.last_message_sender_id === user.value?.id;

          conv.last_message = formatLastMessagePreview(
            conv,
            lastMessage,
            user.value?.id,
          );

          conv.last_message_time = updatedConv.last_message_time;
          conv.unread_count = updatedConv.unread_count;
        }
      });
    }
  } catch (error) {
    console.error("Error polling messages:", error);
  }
};

const fetchConversations = async () => {
  isLoading.value = true;
  isLoadingMessage.value = "Getting your messages…";
  try {
    const endpoint = isVendor.value
      ? "/chat/conversations"
      : "/chat/my-conversations";
    const { data } = await api.get(endpoint);

    if (data.success) {
      conversations.value = data.conversations.map((conv) => ({
        ...conv,
        messages: [],
      }));

      if (conversations.value.length > 0 && !selectedConversation.value) {
        await selectConversation(conversations.value[0]);
      }
    }
  } catch (error) {
    console.error("Error fetching conversations:", error);
    toast.error("Failed to load conversations");
  } finally {
    isLoading.value = false;
  }
};

// Lifecycle
onMounted(() => {
  if (!isAuthenticated.value) {
    router.push("/guest/login");
    return;
  }

  fetchConversations();
  pollingInterval = setInterval(pollNewMessages, 3000);
});

onUnmounted(() => {
  if (pollingInterval) {
    clearInterval(pollingInterval);
  }
  if (typingTimeout) {
    clearTimeout(typingTimeout);
  }
});
</script>

<style scoped>
* {
  font-family: "Poppins", sans-serif;
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

html,
body {
  height: 100%;
  margin: 0;
  overflow: hidden !important;
}

.chat-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.chat-layout.embedded {
  height: 100%;
  min-height: calc(100vh - 170px);
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
}

.chat-container {
  margin-left: 0;
  flex: 1;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.chat-layout.embedded .chat-container {
  height: 100%;
}

.chat-content {
  display: flex;
  width: 100%;
  height: 100%;
}

.read-only-note {
  margin-top: 12px;
  font-size: 13px;
  color: #64748b;
  text-align: center;
}

.conversations-sidebar {
  margin: 0;
  width: 380px;
  background: #ffffff;
  border-right: 1px solid #e9ecef;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
}

.sidebar-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e9ecef;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 0;
  z-index: 10;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.search-box {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  border: 2px solid transparent;
  transition: all 0.3s ease;
  border: 1px solid #dee2e6;
}

.search-box:focus-within {
  background: #ffffff;
  border-color: #2d3748;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.search-icon {
  font-size: 18px;
  color: #adb5bd;
}

.search-box input {
  background: none;
  border: none;
  flex: 1;
  outline: none;
  color: #212529;
  font-size: 15px;
  font-weight: 400;
}

.search-box input::placeholder {
  color: #adb5bd;
}

.tabs {
  display: flex;
  gap: 8px;
  background: #f8f9fa;
  padding: 6px;
  border-radius: 12px;
}

.tab {
  flex: 1;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
  background: transparent;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition:
    background 0.25s ease,
    color 0.25s ease;
}

.tab:hover {
  color: #495057;
  background: rgba(102, 126, 234, 0.1);
}

.tab.active {
  background: #2d3748;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(45, 55, 72, 0.4);
}

.tab:not(.active) {
  border: 1px solid #2d3748;
  box-shadow: 0 4px 12px rgba(45, 55, 72, 0.4);
}

.badge {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
  font-size: 10px;
  padding: 2px 7px;
  border-radius: 999px;
  font-weight: 700;
  min-width: 18px;
  text-align: center;
}

.tab:not(.active) .badge {
  background: #2d3748;
}

.conversations-list {
  flex: 1;
  overflow-y: auto;
  padding: 4px;
}

.conversations-list::-webkit-scrollbar {
  width: 6px;
}

.conversations-list::-webkit-scrollbar-track {
  background: transparent;
}

.conversations-list::-webkit-scrollbar-thumb {
  background: #dee2e6;
  border-radius: 10px;
}

.conversations-list::-webkit-scrollbar-thumb:hover {
  background: #ced4da;
}

.conversation-item {
  display: flex;
  gap: 14px;
  padding: 16px;
  cursor: pointer;
  border-radius: 12px;
  margin-bottom: 4px;
  transition: all 0.3s ease;
  position: relative;
  border: 2px solid transparent;
}

.conversation-item:hover {
  background: #f8f9fa;
  transform: translateX(4px);
}

.conversation-item.active {
  background: linear-gradient(
    135deg,
    rgba(102, 126, 234, 0.1) 0%,
    rgba(118, 75, 162, 0.1) 100%
  );
  border-color: #2d3748;
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.15);
}

.conversation-item.unread {
  background: linear-gradient(
    135deg,
    rgba(102, 126, 234, 0.05) 0%,
    rgba(118, 75, 162, 0.05) 100%
  );
}

.conversation-item.unread .vendor-name {
  font-weight: 700;
}

.conversation-item.unread .conversation-preview p {
  font-weight: 600;
  color: #495057;
}

.vendor-avatar-wrapper {
  position: relative;
  flex-shrink: 0;
}

.vendor-avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.vendor-avatar-placeholder {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #2d3748;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-weight: 700;
  font-size: 20px;
  border: 3px solid #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.online-indicator {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 14px;
  height: 14px;
  background: #10b981;
  border: 3px solid #ffffff;
  border-radius: 50%;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.4);
}

.conversation-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.conversation-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.vendor-name {
  font-size: 15px;
  font-weight: 600;
  color: #212529;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-time {
  font-size: 12px;
  color: #adb5bd;
  flex-shrink: 0;
  font-weight: 500;
}

.conversation-preview {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

.conversation-preview p {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  line-height: 1.4;
}

.unread-badge {
  background: #2d3748;
  color: #ffffff;
  font-size: 11px;
  padding: 4px 10px;
  border-radius: 12px;
  font-weight: 700;
  flex-shrink: 0;
  min-width: 24px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
}

.empty-conversations {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
  opacity: 0.5;
}

.empty-conversations p {
  color: #6c757d;
  margin-bottom: 20px;
  font-size: 15px;
}

.main-chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.empty-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px;
  text-align: center;
}

.empty-state h3 {
  font-size: 24px;
  color: #212529;
  margin: 16px 0 12px 0;
  font-weight: 700;
}

.empty-state p {
  color: #6c757d;
  margin-bottom: 24px;
  font-size: 15px;
}

.btn-primary {
  padding: 14px 32px;
  background: #2d3748;
  color: #ffffff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5);
}

.btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.chat-window {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #ffffff;
  height: 100%;
}

.chat-window-header {
  padding: 20px 28px;
  background: #ffffff;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  flex-shrink: 0;
}

.vendor-info-header {
  display: flex;
  align-items: center;
  gap: 14px;
}

.vendor-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.vendor-details .vendor-name {
  font-size: 18px;
  font-weight: 700;
  color: #212529;
}

.vendor-status {
  font-size: 13px;
  color: #10b981;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}

.vendor-status::before {
  content: "";
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  display: inline-block;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.header-actions {
  display: flex;
  gap: 10px;
}

.action-btn {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  border: 2px solid #e9ecef;
  background: #ffffff;
  color: #6c757d;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.action-btn:hover {
  background: #2d3748;
  border-color: #2d3748;
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(45, 55, 72, 1);
}

.messages-container {
  margin-left: 10px;
  margin-right: 10px;
  flex: 1;
  overflow-y: auto;
}

.messages-container::-webkit-scrollbar {
  width: 8px;
}

.messages-container::-webkit-scrollbar-track {
  background: transparent;
}

.messages-container::-webkit-scrollbar-thumb {
  background-color: transparent;
}

.no-messages {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
}

.no-messages p {
  color: #6c757d;
  margin-top: 16px;
  font-size: 15px;
}

.message-date {
  text-align: center;
  margin: 28px 0;
}

.message-date span {
  background: #ffffff;
  padding: 8px 20px;
  border-radius: 20px;
  font-size: 13px;
  color: #6c757d;
  font-weight: 600;
  border: 2px solid #e9ecef;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.message-wrapper {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  margin-bottom: 16px;
  width: 100%;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.message-wrapper.received {
  justify-content: flex-start;
}

.message-wrapper.sent {
  justify-content: flex-end;
}

.message-avatar-wrapper {
  flex-shrink: 0;
  align-self: flex-end;
}

.message-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.message-avatar-placeholder {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #2d3748;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-weight: 700;
  font-size: 14px;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.message-content {
  display: flex;
  flex-direction: column;
  max-width: 75%;
}

.message-bubble {
  padding: 14px 18px;
  border-radius: 18px;
  font-size: 15px;
  line-height: 1.5;
  display: inline-block;
  width: fit-content;
  max-width: 100%;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: anywhere;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.message-wrapper.received .message-bubble {
  background: #ffffff;
  color: #212529;
  border-top-left-radius: 4px;
  border: 1px solid #e9ecef;
}

.message-wrapper.sent .message-bubble {
  background: #2d3748;
  color: #ffffff;
  border-top-right-radius: 4px;
}

.message-bubble p {
  margin: 0;
}

.message-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  font-size: 12px;
  color: #adb5bd;
  font-weight: 500;
}

.message-wrapper.sent .message-meta {
  justify-content: flex-end;
}

.message-time {
  font-size: 12px;
}

.message-status {
  color: #10b981;
  font-size: 12px;
}

.message-attachments {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.attachment-item {
  max-width: 100%;
}

.attachment-image {
  max-width: 280px;
  border-radius: 12px;
  cursor: pointer;
  transition: transform 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.attachment-image:hover {
  transform: scale(1.02);
}

.attachment-file {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.attachment-file:hover {
  background: rgba(0, 0, 0, 0.08);
}

.message-wrapper.sent .attachment-file {
  background: rgba(255, 255, 255, 0.2);
}

.message-wrapper.sent .attachment-file:hover {
  background: rgba(255, 255, 255, 0.3);
}

.file-icon {
  font-size: 28px;
}

.file-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.file-name {
  font-size: 14px;
  font-weight: 600;
}

.file-size {
  font-size: 12px;
  opacity: 0.7;
}

.typing-wrapper {
  display: flex;
  gap: 12px;
  align-items: flex-end;
  margin-bottom: 20px;
  animation: slideIn 0.3s ease;
}

.typing-indicator {
  background: #ffffff;
  padding: 14px 18px;
  border-radius: 18px;
  border-top-left-radius: 4px;
  display: flex;
  gap: 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border: 1px solid #e9ecef;
}

.typing-indicator span {
  width: 8px;
  height: 8px;
  background: #ced4da;
  border-radius: 50%;
  animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  0%,
  60%,
  100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-10px);
  }
}

.message-input-area {
  padding: 20px 28px;
  background: #ffffff;
  border-top: 1px solid #e9ecef;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
  flex-shrink: 0;
}

.files-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
}

.file-preview-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 10px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.file-preview-item:hover {
  border-color: #2d3748;
  background: rgba(102, 126, 234, 0.05);
}

.file-preview-item .file-name {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 500;
}

.remove-file-btn {
  background: none;
  border: none;
  color: #dc3545;
  font-size: 18px;
  cursor: pointer;
  padding: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.remove-file-btn:hover {
  background: #dc3545;
  color: #ffffff;
}

.input-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f8f9fa;
  border-radius: 24px;
  padding: 10px 16px;
  border: 2px solid #e9ecef;
  transition: all 0.3s ease;
}

.input-wrapper:focus-within {
  background: #ffffff;
  border-color: #2d3748;
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.15);
}

.attach-btn {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  padding: 6px;
  color: #6c757d;
  transition: all 0.3s ease;
  border-radius: 8px;
}

.attach-btn:hover {
  transform: scale(1.15);
  color: #2d3748;
  background: rgba(102, 126, 234, 0.1);
}

.attach-btn:disabled,
.emoji-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  background: none;
}

.message-input {
  flex: 1;
  border: none;
  background: none;
  outline: none;
  color: #212529;
  font-size: 15px;
  padding: 8px 10px;
  font-weight: 400;
}

.message-input::placeholder {
  color: #adb5bd;
}

.message-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.emoji-btn {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  padding: 6px;
  transition: all 0.3s ease;
  border-radius: 8px;
}

.emoji-btn:hover {
  transform: scale(1.15);
  background: rgba(102, 126, 234, 0.1);
}

.send-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: none;
  background: #2d3748;
  color: #ffffff;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.send-btn:hover:not(:disabled) {
  transform: scale(1.08);
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
}

.send-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.info-sidebar {
  width: 340px;
  background: #ffffff;
  border-left: 1px solid #e9ecef;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.05);
}

.info-sidebar::-webkit-scrollbar {
  width: 6px;
}

.info-sidebar::-webkit-scrollbar-track {
  background: transparent;
}

.info-sidebar::-webkit-scrollbar-thumb {
  background: #dee2e6;
  border-radius: 10px;
}

.info-sidebar::-webkit-scrollbar-thumb:hover {
  background: #ced4da;
}

.right-sidebar-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #ffffff;
  position: sticky;
  top: 0;
  z-index: 10;
}

.right-sidebar-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #212529;
  margin: 0;
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: #f8f9fa;
  color: #6c757d;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.close-btn:hover {
  background: #e9ecef;
  color: #212529;
  transform: rotate(90deg);
}

.vendor-profile {
  padding: 32px 24px;
  text-align: center;
  border-bottom: 1px solid #e9ecef;
}

.profile-avatar-wrapper {
  margin: 0 auto 16px;
  width: 96px;
  height: 96px;
}

.profile-avatar {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #f8f9fa;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.profile-avatar-placeholder {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: #2d3748;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-weight: 700;
  font-size: 36px;
  border: 4px solid #f8f9fa;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.vendor-profile h4 {
  font-size: 20px;
  font-weight: 700;
  color: #212529;
  margin: 0 0 10px 0;
}

.vendor-email {
  font-size: 14px;
  color: #6c757d;
  margin: 6px 0;
}

.vendor-contact {
  font-size: 14px;
  color: #6c757d;
  margin: 6px 0;
  font-weight: 500;
}

.sidebar-section {
  padding: 24px;
  border-bottom: 1px solid #e9ecef;
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  color: #212529;
  margin: 0 0 16px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.action-button {
  width: 100%;
  padding: 14px 18px;
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  font-size: 15px;
  color: #212529;
  margin-bottom: 10px;
  transition: all 0.3s ease;
  text-align: left;
  font-weight: 500;
}

.action-button:hover {
  background: #2d3748;
  border-color: #2d3748;
  color: #ffffff;
  transform: translateX(4px);
  box-shadow: 0 4px 12px rgba(45, 55, 72, 1);
}

.action-icon {
  font-size: 22px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f8f9fa;
}

.detail-item:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.detail-label {
  font-size: 12px;
  color: #adb5bd;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 14px;
  color: #212529;
  font-weight: 500;
}

.shared-files-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.shared-file-item {
  padding: 16px;
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.shared-file-item:hover {
  background: rgba(102, 126, 234, 0.1);
  border-color: #2d3748;
  transform: translateY(-4px);
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.2);
}

.file-icon-large {
  font-size: 36px;
}

.file-name-small {
  font-size: 12px;
  color: #212529;
  font-weight: 600;
  text-align: center;
  word-break: break-word;
}

.file-size-small {
  font-size: 11px;
  color: #adb5bd;
  font-weight: 500;
}

.no-shared-files {
  text-align: center;
  padding: 32px;
  color: #adb5bd;
  font-size: 14px;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.image-viewer-modal {
  position: relative;
  max-width: 90%;
  max-height: 90%;
  animation: zoomIn 0.4s ease;
}

@keyframes zoomIn {
  from {
    transform: scale(0.85);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.image-viewer-modal img {
  max-width: 100%;
  max-height: 90vh;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.close-viewer-btn {
  position: absolute;
  top: -48px;
  right: 0;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: #ffffff;
  color: #212529;
  font-size: 22px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.close-viewer-btn:hover {
  background: #dc3545;
  color: #ffffff;
  transform: scale(1.1) rotate(90deg);
}

.new-chat-modal {
  background: #ffffff;
  border-radius: 20px;
  width: 90%;
  max-width: 520px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
  animation: slideUp 0.4s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(40px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  padding: 24px 28px;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  color: #212529;
  margin: 0;
}

.modal-body {
  padding: 24px 28px;
  overflow-y: auto;
  flex: 1;
}

.modal-body .search-box {
  margin-bottom: 20px;
}

.vendors-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 420px;
  overflow-y: auto;
}

.vendors-list::-webkit-scrollbar {
  width: 6px;
}

.vendors-list::-webkit-scrollbar-track {
  background: transparent;
}

.vendors-list::-webkit-scrollbar-thumb {
  background: #dee2e6;
  border-radius: 10px;
}

.vendor-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.vendor-item:hover {
  background: rgba(102, 126, 234, 0.1);
  border-color: #2d3748;
  transform: translateX(4px);
}

.vendor-item.disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.vendor-item.disabled:hover {
  background: #f8f9fa;
  border-color: #e9ecef;
  transform: none;
}

.vendor-avatar-small {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.vendor-avatar-placeholder-small {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #2d3748;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-weight: 700;
  font-size: 16px;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.vendor-item-info {
  flex: 1;
}

.vendor-item-name {
  font-size: 15px;
  font-weight: 600;
  color: #212529;
  margin-bottom: 4px;
}

.vendor-item-email {
  font-size: 13px;
  color: #6c757d;
}

.no-vendors {
  text-align: center;
  padding: 48px 20px;
  color: #adb5bd;
  font-size: 15px;
}

/* Responsive Styles */
@media (max-width: 1024px) {
  .info-sidebar {
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    z-index: 100;
    box-shadow: -8px 0 24px rgba(0, 0, 0, 0.15);
  }
}

@media (max-width: 768px) {
  .conversations-sidebar {
    width: 100%;
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    z-index: 50;
  }

  .main-chat-area {
    width: 100%;
  }

  .message-wrapper {
    max-width: 80%;
  }

  .info-sidebar {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .conversations-sidebar {
    width: 100%;
  }

  .message-wrapper {
    max-width: 85%;
  }

  .vendor-avatar,
  .vendor-avatar-placeholder {
    width: 48px;
    height: 48px;
    font-size: 18px;
  }

  .message-avatar,
  .message-avatar-placeholder {
    width: 32px;
    height: 32px;
    font-size: 12px;
  }
}
</style>
