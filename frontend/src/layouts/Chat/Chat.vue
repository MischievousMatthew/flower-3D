<template>
  <div
    class="chat-layout"
    :class="{
      embedded: isEmbeddedLayout,
      'is-customer': isCustomer && !isEmbeddedLayout,
      'bc-theme': isCustomer && !isEmbeddedLayout,
      'is-vendor': isVendor && !isEmbeddedLayout,
      'thread-open': !!selectedConversation,
    }"
  >
    <component :is="headerComponent" v-if="headerComponent" />

    <div class="chat-shell">
      <!-- ── Conversation list ─────────────────────────────────────── -->
      <aside class="chat-list" aria-label="Conversations">
        <div class="chat-list-head">
          <div class="chat-list-title">
            <h1>Messages</h1>
            <button
              class="icon-btn"
              :disabled="!canStartConversation"
              :title="canStartConversation ? `New message` : permissionTooltip"
              aria-label="New message"
              @click="openNewChat"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 20h4L19 9l-4-4L4 16v4Z" />
                <path d="m13.5 6.5 4 4" />
              </svg>
            </button>
          </div>

          <label class="chat-search">
            <span class="sr-only">Search {{ counterpartLabel }}</span>
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="11" cy="11" r="6.5" />
              <path d="m16 16 4 4" />
            </svg>
            <input
              v-model="searchQuery"
              type="search"
              :placeholder="`Search ${counterpartLabel}`"
            />
          </label>

          <div class="chat-filter" role="tablist" aria-label="Filter conversations">
            <button
              role="tab"
              :aria-selected="activeTab === 'all'"
              :class="{ active: activeTab === 'all' }"
              @click="activeTab = 'all'"
            >
              All
            </button>
            <button
              role="tab"
              :aria-selected="activeTab === 'unread'"
              :class="{ active: activeTab === 'unread' }"
              @click="activeTab = 'unread'"
            >
              Unread
              <span v-if="unreadCount > 0" class="count">{{ unreadCount }}</span>
            </button>
            <button
              role="tab"
              :aria-selected="activeTab === 'archived'"
              :class="{ active: activeTab === 'archived' }"
              @click="activeTab = 'archived'"
            >
              Archived
            </button>
          </div>
        </div>

        <div class="chat-list-body">
          <template v-if="isLoading">
            <div v-for="n in 6" :key="n" class="conv conv--skeleton" aria-hidden="true">
              <span class="skeleton avatar"></span>
              <span class="conv-text">
                <span class="skeleton line"></span>
                <span class="skeleton line short"></span>
              </span>
            </div>
            <span class="sr-only">Loading conversations…</span>
          </template>

          <template v-else>
            <div
              v-for="conversation in filteredConversations"
              :key="conversation.id"
              class="conv-item"
              :class="{ confirming: confirmDeleteId === conversation.id }"
            >
              <!-- Asking before deleting, in place of the row -->
              <div
                v-if="confirmDeleteId === conversation.id"
                class="conv-confirm"
                role="alertdialog"
                :aria-label="`Delete conversation with ${getOtherUserDisplayName(conversation)}?`"
              >
                <p>
                  Delete this conversation?
                  <span>It's removed for you only.</span>
                </p>
                <div class="conv-confirm-actions">
                  <button class="btn btn-ghost btn-sm" @click="confirmDeleteId = null">Cancel</button>
                  <button
                    class="btn btn-danger btn-sm"
                    :disabled="deletingId === conversation.id"
                    @click="deleteConversation(conversation)"
                  >
                    {{ deletingId === conversation.id ? "Deleting..." : "Delete" }}
                  </button>
                </div>
              </div>

              <template v-else>
                <button
                  type="button"
                  class="conv"
                  :class="{
                    active: selectedConversation?.id === conversation.id,
                    unread: conversation.unread_count > 0,
                  }"
                  @click="selectConversation(conversation)"
                >
                  <span class="avatar">
                    <img
                      v-if="getOtherUser(conversation).avatar"
                      :src="getOtherUser(conversation).avatar"
                      alt=""
                    />
                    <span v-else>{{ getOtherUserAvatarInitial(conversation) }}</span>
                    <i v-if="getOtherUser(conversation).online" class="presence"></i>
                  </span>
                  <span class="conv-text">
                    <span class="conv-name">{{ getOtherUserDisplayName(conversation) }}</span>
                    <span class="conv-preview">{{ conversation.last_message || "No messages yet" }}</span>
                  </span>
                  <!-- Right column: time on top, delete (and unread count) below -->
                  <span class="conv-side">
                    <span class="conv-time">{{ conversation.last_message_time }}</span>
                    <span class="conv-side-bottom">
                      <span v-if="conversation.unread_count > 0" class="count">
                        {{ conversation.unread_count }}
                      </span>
                    </span>
                  </span>
                </button>
                <div
                  v-if="canArchiveConversations || canDeleteConversations"
                  class="conv-more"
                  :class="{ open: openMenuId === conversation.id }"
                >
                  <button
                    type="button"
                    class="conv-more-btn"
                    aria-haspopup="menu"
                    :aria-expanded="openMenuId === conversation.id"
                    :aria-label="`More options for ${getOtherUserDisplayName(conversation)}`"
                    title="More options"
                    @click.stop="toggleMenu(conversation.id)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <circle cx="5" cy="12" r="1.3" />
                      <circle cx="12" cy="12" r="1.3" />
                      <circle cx="19" cy="12" r="1.3" />
                    </svg>
                  </button>
                  <div v-if="openMenuId === conversation.id" class="conv-menu" role="menu" @click.stop>
                    <button
                      v-if="canArchiveConversations"
                      type="button"
                      role="menuitem"
                      :disabled="archivingId === conversation.id"
                      @click="toggleArchive(conversation)"
                    >
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16v4H4zM5 9v10h14V9M10 13h4" />
                      </svg>
                      {{ conversation.is_archived ? "Move to inbox" : "Archive" }}
                    </button>
                    <button
                      v-if="canDeleteConversations"
                      type="button"
                      role="menuitem"
                      class="danger"
                      @click="askDelete(conversation)"
                    >
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" />
                      </svg>
                      Delete
                    </button>
                  </div>
                </div>
              </template>
            </div>

            <div v-if="filteredConversations.length === 0" class="list-empty">
              <p class="list-empty-title">
                {{
                  searchQuery
                    ? "No conversations match your search"
                    : activeTab === "unread"
                      ? "You're all caught up"
                      : activeTab === "archived"
                        ? "No archived conversations"
                        : "No conversations yet"
                }}
              </p>
              <p v-if="!searchQuery && activeTab === 'archived'">
                Archive a chat from its ⋯ menu to tuck it away here. It comes back to your inbox when a new message arrives.
              </p>
              <p v-if="!searchQuery && activeTab === 'all'">
                {{
                  isVendor
                    ? "Customers who message your shop will appear here."
                    : "Message a shop from its storefront, or start a new chat."
                }}
              </p>
              <button
                v-if="!searchQuery && activeTab === 'all' && canStartConversation"
                class="btn btn-primary"
                @click="openNewChat"
              >
                Start a new chat
              </button>
              <p v-if="!canStartConversation" class="muted">
                View-only access allows reading conversations only.
              </p>
            </div>
          </template>
        </div>
      </aside>

      <!-- ── Thread ────────────────────────────────────────────────── -->
      <section class="chat-thread" :aria-label="selectedConversation ? 'Conversation' : 'No conversation selected'">
        <div v-if="!selectedConversation" class="thread-empty">
          <svg viewBox="0 0 48 48" aria-hidden="true">
            <path d="M8 12a4 4 0 0 1 4-4h24a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4H20l-8 7v-7h0a4 4 0 0 1-4-4V12Z" />
          </svg>
          <h2>Your messages</h2>
          <p>
            Pick a {{ counterpartLabelSingular }} on the left to read and reply to your conversation.
          </p>
          <button
            v-if="canStartConversation"
            class="btn btn-primary"
            @click="openNewChat"
          >
            Start a new chat
          </button>
        </div>

        <template v-else>
          <header class="thread-head">
            <button class="icon-btn back-btn" aria-label="Back to conversations" @click="closeThread">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7" /></svg>
            </button>
            <span class="avatar">
              <img
                v-if="getOtherUser(selectedConversation).avatar"
                :src="getOtherUser(selectedConversation).avatar"
                alt=""
              />
              <span v-else>{{ getOtherUserAvatarInitial(selectedConversation) }}</span>
              <i v-if="getOtherUser(selectedConversation).online" class="presence"></i>
            </span>
            <div class="thread-title">
              <h2>{{ getOtherUserDisplayName(selectedConversation) }}</h2>
              <p :class="{ online: getOtherUser(selectedConversation).online }">
                {{
                  getOtherUser(selectedConversation).online
                    ? "Online now"
                    : isVendor
                      ? "Customer"
                      : "Flower shop"
                }}
              </p>
            </div>
            <div class="thread-actions">
              <button
                v-if="!isVendor && selectedConversation.vendor?.store_id"
                class="btn btn-ghost"
                @click="viewVendorShop"
              >
                View shop
              </button>
              <button
                class="icon-btn"
                :class="{ active: showUserDetails }"
                :aria-pressed="showUserDetails"
                :aria-label="`${isVendor ? 'Customer' : 'Shop'} details`"
                @click="toggleUserDetails"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="8.5" />
                  <path d="M12 11v5M12 8h.01" />
                </svg>
              </button>
            </div>
          </header>

          <div ref="messagesArea" class="thread-body" aria-live="polite">
            <template v-if="loadingMessages">
              <div
                v-for="n in 5"
                :key="n"
                class="msg-row"
                :class="n % 2 ? 'received' : 'sent'"
                aria-hidden="true"
              >
                <span class="skeleton bubble-skeleton" :style="{ width: `${36 + ((n * 17) % 30)}%` }"></span>
              </div>
            </template>

            <div v-else-if="!selectedConversation.messages?.length" class="thread-start">
              <p>This is the start of your conversation with {{ getOtherUserDisplayName(selectedConversation) }}.</p>
            </div>

            <template v-else>
              <template v-for="item in messageItems" :key="item.key">
                <div v-if="item.type === 'day'" class="day-divider">
                  <span>{{ item.label }}</span>
                </div>
                <div
                  v-else
                  class="msg-row"
                  :class="{
                    sent: item.message.is_own,
                    received: !item.message.is_own,
                    'run-start': item.runStart,
                    'run-end': item.runEnd,
                  }"
                >
                  <span v-if="!item.message.is_own" class="msg-avatar" aria-hidden="true">
                    <template v-if="item.runEnd">
                      <img
                        v-if="getOtherUser(selectedConversation).avatar"
                        :src="getOtherUser(selectedConversation).avatar"
                        alt=""
                      />
                      <span v-else>{{ getOtherUserAvatarInitial(selectedConversation) }}</span>
                    </template>
                  </span>

                  <div class="msg">
                    <div
                      v-if="item.message.attachments?.length"
                      class="msg-attachments"
                    >
                      <template v-for="(attachment, idx) in item.message.attachments" :key="idx">
                        <button
                          v-if="isImageFile(attachment.type)"
                          type="button"
                          class="msg-image"
                          aria-label="Open image"
                          @click="openImageViewer(attachment.url)"
                        >
                          <img :src="attachment.url" alt="" loading="lazy" />
                        </button>
                        <a
                          v-else
                          :href="attachment.url"
                          target="_blank"
                          rel="noopener"
                          class="msg-file"
                        >
                          <span class="file-badge">{{ fileExtension(attachment.name) }}</span>
                          <span class="file-meta">
                            <span class="file-name">{{ attachment.name }}</span>
                            <span class="file-size">{{ formatFileSize(attachment.size) }}</span>
                          </span>
                        </a>
                      </template>
                    </div>

                    <!-- Order updates and products the shop sent -->
                    <button
                      v-if="item.message.meta?.kind === 'order'"
                      type="button"
                      class="msg-card"
                      :aria-label="`${item.message.meta.title} for order ${item.message.meta.order_number}. Open your orders`"
                      @click="router.push({ name: 'OrderTracking' })"
                    >
                      <span class="msg-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                      </span>
                      <span class="msg-card-main">
                        <small>Order update · {{ item.message.meta.order_number }}</small>
                        <strong>{{ item.message.meta.title }}</strong>
                        <span v-if="item.message.meta.note" class="msg-card-note">{{ item.message.meta.note }}</span>
                        <span class="msg-card-link">View your order</span>
                      </span>
                    </button>
                    <button
                      v-else-if="item.message.meta?.kind === 'product'"
                      type="button"
                      class="msg-card"
                      :aria-label="`${item.message.meta.name}. Open the product`"
                      @click="router.push({ name: 'ProductDetails', params: { id: String(item.message.meta.product_id) } })"
                    >
                      <span class="msg-card-img" aria-hidden="true">
                        <img v-if="item.message.meta.image" :src="item.message.meta.image" alt="" loading="lazy" />
                        <svg v-else viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/></svg>
                      </span>
                      <span class="msg-card-main">
                        <small>From the shop</small>
                        <strong>{{ item.message.meta.name }}</strong>
                        <span class="msg-card-price">₱{{ Number(item.message.meta.price || 0).toLocaleString("en-PH", { minimumFractionDigits: 2 }) }}</span>
                        <span class="msg-card-link">View product</span>
                      </span>
                    </button>

                    <!-- A question the customer asked about one of their orders or a product -->
                    <button
                      v-else-if="item.message.meta?.kind === 'order_question'"
                      type="button"
                      class="msg-card"
                      :aria-label="`Question about order ${item.message.meta.order_number}. Open your orders`"
                      @click="router.push({ name: 'OrderTracking' })"
                    >
                      <span class="msg-card-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                      </span>
                      <span class="msg-card-main">
                        <small>{{ item.message.is_own ? "You asked about" : "Question about" }} an order</small>
                        <strong>{{ item.message.meta.order_number }}</strong>
                        <span class="msg-card-note">{{ statusLabel(item.message.meta.order_status) }} · ₱{{ Number(item.message.meta.total || 0).toLocaleString("en-PH", { minimumFractionDigits: 2 }) }}</span>
                        <span class="msg-card-link">View your order</span>
                      </span>
                    </button>
                    <button
                      v-else-if="item.message.meta?.kind === 'product_question'"
                      type="button"
                      class="msg-card"
                      :aria-label="`Question about ${item.message.meta.name}. Open the product`"
                      @click="router.push({ name: 'ProductDetails', params: { id: String(item.message.meta.product_id) } })"
                    >
                      <span class="msg-card-img" aria-hidden="true">
                        <img v-if="item.message.meta.image" :src="item.message.meta.image" alt="" loading="lazy" />
                        <svg v-else viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/></svg>
                      </span>
                      <span class="msg-card-main">
                        <small>{{ item.message.is_own ? "You asked about" : "Question about" }}</small>
                        <strong>{{ item.message.meta.name }}</strong>
                        <span class="msg-card-price">₱{{ Number(item.message.meta.price || 0).toLocaleString("en-PH", { minimumFractionDigits: 2 }) }}</span>
                        <span class="msg-card-link">View product</span>
                      </span>
                    </button>

                    <p
                      v-if="item.message.text && !isAttachmentPlaceholder(item.message.text) && (!item.message.meta?.kind || ['order_question', 'product_question'].includes(item.message.meta.kind) || (item.message.meta.kind === 'product' && item.message.text !== item.message.meta.name))"
                      class="bubble"
                    >{{ item.message.text }}</p>

                    <span v-if="item.runEnd" class="msg-meta">
                      {{ formatMessageTime(item.message) }}
                      <template v-if="item.message.is_own">
                        · {{ item.message.read ? "Seen" : "Sent" }}
                      </template>
                    </span>
                  </div>
                </div>
              </template>
            </template>
          </div>

          <footer class="composer">
            <div v-if="activeAbout" class="composer-about" :class="{ 'composer-about--order': activeAbout.type === 'order' }" role="status">
              <span class="composer-about-img" aria-hidden="true">
                <img v-if="activeAbout.image" :src="activeAbout.image" alt="" />
                <svg v-else viewBox="0 0 24 24"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
              </span>
              <span class="composer-about-main">
                <small>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.4 11.6-9.2 9.2a5.5 5.5 0 0 1-7.8-7.8l9.2-9.2a3.7 3.7 0 0 1 5.2 5.2l-9.2 9.2a1.8 1.8 0 0 1-2.6-2.6l8.5-8.5" /></svg>
                  {{ activeAbout.type === "order" ? "Order attached" : "Asking about this product" }}
                </small>
                <strong>{{ activeAbout.type === "order" ? activeAbout.order_number : activeAbout.name }}</strong>
                <span v-if="activeAbout.type === 'order' && aboutOrderMeta" class="composer-about-meta">{{ aboutOrderMeta }}</span>
              </span>
              <button class="chip-remove" type="button" aria-label="Don't attach it to my message" @click="about = null">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
              </button>
            </div>
            <div v-if="selectedFiles.length > 0" class="composer-files">
              <span v-for="(file, index) in selectedFiles" :key="index" class="composer-file">
                <span class="file-badge">{{ fileExtension(file.name) }}</span>
                <span class="file-name">{{ file.name }}</span>
                <button
                  class="chip-remove"
                  :aria-label="`Remove ${file.name}`"
                  :disabled="!canSendMessages"
                  @click="removeFile(index)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
                </button>
              </span>
            </div>

            <div class="composer-row">
              <button
                class="icon-btn"
                :disabled="!canSendMessages"
                :title="canSendMessages ? 'Attach a photo or file' : permissionTooltip"
                aria-label="Attach a photo or file"
                @click="openFileUpload"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m20 11.5-7.8 7.8a5 5 0 0 1-7.1-7.1l8.1-8.1a3.4 3.4 0 0 1 4.8 4.8l-8.1 8.1a1.7 1.7 0 0 1-2.4-2.4l7.4-7.4" />
                </svg>
              </button>
              <input
                ref="fileInput"
                type="file"
                accept="image/*,.pdf,.doc,.docx,.txt"
                multiple
                hidden
                @change="handleFileUpload"
              />

              <label class="composer-field">
                <span class="sr-only">Message</span>
                <textarea
                  ref="composerInput"
                  v-model="messageText"
                  rows="1"
                  :placeholder="!canSendMessages ? 'You have view-only access to this chat' : activeAbout ? 'Write your question' : 'Write a message'"
                  :disabled="!canSendMessages || isSending"
                  :title="canSendMessages ? '' : permissionTooltip"
                  @keydown.enter.exact.prevent="sendMessage"
                  @input="resizeComposer"
                ></textarea>
              </label>

              <button
                class="send-btn"
                :disabled="!canSend"
                :title="canSend ? 'Send' : canSendMessages ? 'Write a message first' : permissionTooltip"
                aria-label="Send message"
                @click="sendMessage"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 19V5M6 11l6-6 6 6" />
                </svg>
              </button>
            </div>
            <p class="composer-hint">Enter to send, Shift + Enter for a new line</p>
          </footer>
        </template>
      </section>

      <!-- ── Details panel ─────────────────────────────────────────── -->
      <aside
        v-if="showUserDetails && selectedConversation"
        class="chat-details"
        :aria-label="`${isVendor ? 'Customer' : 'Shop'} details`"
      >
        <div class="details-head">
          <h3>{{ isVendor ? "Customer" : "Shop" }} details</h3>
          <button class="icon-btn" aria-label="Close details" @click="toggleUserDetails">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
          </button>
        </div>

        <div class="details-profile">
          <span class="avatar avatar--lg">
            <img
              v-if="getOtherUser(selectedConversation).avatar"
              :src="getOtherUser(selectedConversation).avatar"
              alt=""
            />
            <span v-else>{{ getOtherUserAvatarInitial(selectedConversation) }}</span>
          </span>
          <h4>{{ getOtherUserDisplayName(selectedConversation) }}</h4>
          <p v-if="getOtherUser(selectedConversation).email">{{ getOtherUser(selectedConversation).email }}</p>
          <p v-if="getOtherUser(selectedConversation).contact_number">
            {{ getOtherUser(selectedConversation).contact_number }}
          </p>
        </div>

        <div v-if="!isVendor" class="details-actions">
          <button
            v-if="selectedConversation.vendor?.store_id"
            class="btn btn-primary"
            @click="viewVendorShop"
          >
            View shop
          </button>
          <button class="btn btn-ghost" @click="viewOrders">My orders</button>
        </div>

        <dl v-if="userDetails" class="details-list">
          <div>
            <dt>Address</dt>
            <dd>{{ userDetails.address || "Not provided" }}</dd>
          </div>
        </dl>

        <div class="details-files">
          <h4>Shared files</h4>
          <ul v-if="sharedFiles.length > 0">
            <li v-for="file in sharedFiles" :key="file.url || file.name">
              <button type="button" class="details-file" @click="downloadFile(file)">
                <span class="file-badge">{{ fileExtension(file.name) }}</span>
                <span class="file-meta">
                  <span class="file-name">{{ file.name }}</span>
                  <span class="file-size">{{ file.size }}</span>
                </span>
              </button>
            </li>
          </ul>
          <p v-else class="muted">Photos and files you share will show up here.</p>
        </div>
      </aside>
    </div>

    <!-- Image viewer -->
    <div v-if="imageViewerUrl" class="overlay overlay--dark" @click="closeImageViewer">
      <button class="icon-btn viewer-close" aria-label="Close image" @click="closeImageViewer">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
      </button>
      <img :src="imageViewerUrl" alt="Shared image" class="viewer-image" @click.stop />
    </div>

    <!-- New chat -->
    <div v-if="showNewChatModal" class="overlay" @click="showNewChatModal = false">
      <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="new-chat-title" @click.stop>
        <div class="dialog-head">
          <h3 id="new-chat-title">New message</h3>
          <button class="icon-btn" aria-label="Close" @click="showNewChatModal = false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" /></svg>
          </button>
        </div>
        <label class="chat-search">
          <span class="sr-only">Search {{ counterpartLabel }}</span>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="11" cy="11" r="6.5" />
            <path d="m16 16 4 4" />
          </svg>
          <input
            ref="newChatSearchInput"
            v-model="userSearchQuery"
            type="search"
            :placeholder="`Search ${counterpartLabel} by name`"
            autocomplete="off"
            @input="searchUsers"
          />
        </label>
        <div class="dialog-results">
          <!-- Shops load once when this opens; typing then filters instantly -->
          <template v-if="!isVendor && shopsLoading">
            <div v-for="n in 4" :key="n" class="conv conv--skeleton" aria-hidden="true">
              <span class="skeleton avatar"></span>
              <span class="conv-text">
                <span class="skeleton line"></span>
                <span class="skeleton line short"></span>
              </span>
            </div>
            <span class="sr-only">Loading shops…</span>
          </template>
          <p v-else-if="!isVendor && shopsError" class="muted dialog-note">
            Couldn't load the shops.
            <button type="button" class="dialog-retry" @click="loadShops(true)">Try again</button>
          </p>
          <p v-if="!isVendor && !shopsLoading && !shopsError && !userSearchQuery.trim() && newChatResults.length" class="dialog-label">All shops</p>
          <button
            v-for="result in newChatResults"
            :key="result.id"
            type="button"
            class="conv"
            :disabled="!canStartConversation"
            @click="startNewConversation(result)"
          >
            <span class="avatar">
              <img v-if="result.avatar" :src="result.avatar" alt="" />
              <span v-else>{{ getSearchResultAvatarInitial(result) }}</span>
            </span>
            <span class="conv-text">
              <span class="conv-name">{{ getSearchResultDisplayName(result) }}</span>
              <span class="conv-preview">{{ isVendor ? result.email : result.location || "Flower shop" }}</span>
            </span>
          </button>
          <template v-if="!(shopsLoading || shopsError) || isVendor">
            <p v-if="newChatResults.length === 0 && userSearchQuery.trim()" class="muted dialog-note">
              No {{ counterpartLabel }} match "{{ userSearchQuery.trim() }}".
            </p>
            <p v-else-if="newChatResults.length === 0 && !userSearchQuery.trim()" class="muted dialog-note">
              {{ isVendor ? `Type a name to find ${counterpartLabel}.` : "No shops to message yet." }}
            </p>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import NavHeader from "../NavHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import { formatPhilippineDate, formatPhilippineTime } from "../../utils/philippineDateTime";

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
  initialVendorId: {
    type: [Number, String],
    default: null,
  },
  initialVendorName: {
    type: String,
    default: "",
  },
  // "Ask the shop" from a product page or an order: {type: "product"|"order", id, name|order_number, image}
  initialAbout: {
    type: Object,
    default: null,
  },
  // CRM employees need the crm edit / delete permissions; everyone else can
  canArchiveConversations: {
    type: Boolean,
    default: true,
  },
  canDeleteConversations: {
    type: Boolean,
    default: true,
  },
});

const permissionTooltip = "You don't have permission to create new records.";

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
  return isVendor.value ? "customers" : "shops";
});
const counterpartLabelSingular = computed(() =>
  counterpartLabel.value.endsWith("s") ? counterpartLabel.value.slice(0, -1) : counterpartLabel.value,
);
const canSendMessages = computed(() => props.canSendMessages);
const canStartConversation = computed(() => props.canSendMessages && props.allowNewChat);
const normalizedInitialVendorId = computed(() => {
  const parsedVendorId = Number.parseInt(props.initialVendorId, 10);
  return Number.isFinite(parsedVendorId) ? parsedVendorId : null;
});
const hasHandledInitialVendor = ref(false);
const headerComponent = computed(() => {
  if (isEmbeddedLayout.value) return null;
  return isVendor.value ? VendorSidebar : NavHeader;
});

// State
const activeTab = ref("all");
const searchQuery = ref("");
const selectedConversation = ref(null);
const messageText = ref("");
const isSending = ref(false);
const showUserDetails = ref(false);
const messagesArea = ref(null);
const composerInput = ref(null);
const fileInput = ref(null);
const newChatSearchInput = ref(null);
const selectedFiles = ref([]);
const conversations = ref([]);
const isLoading = ref(false);
const loadingMessages = ref(false);
const sharedFiles = ref([]);
const userDetails = ref(null);
const imageViewerUrl = ref(null);
const showNewChatModal = ref(false);
const userSearchQuery = ref("");
const searchedUsers = ref([]);
const confirmDeleteId = ref(null);
const deletingId = ref(null);
const openMenuId = ref(null);
const archivingId = ref(null);

// Polling
let pollingInterval = null;
const lastMessageId = ref(0);

// On narrow screens the list and the thread take turns filling the screen
const isNarrowScreen = () => window.matchMedia("(max-width: 768px)").matches;

// Computed
const filteredConversations = computed(() => {
  let result =
    activeTab.value === "archived"
      ? conversations.value.filter((c) => c.is_archived)
      : conversations.value.filter((c) => !c.is_archived);

  if (activeTab.value === "unread") {
    result = result.filter((c) => c.unread_count > 0);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter((c) => {
      const otherUser = getOtherUser(c);
      return (
        (otherUser.name || "").toLowerCase().includes(query) ||
        (otherUser.display_name || "").toLowerCase().includes(query) ||
        (otherUser.email || "").toLowerCase().includes(query) ||
        c.last_message?.toLowerCase().includes(query)
      );
    });
  }

  return result;
});

const unreadCount = computed(() =>
  conversations.value.reduce((sum, c) => sum + (c.is_archived ? 0 : c.unread_count || 0), 0),
);

const canSend = computed(
  () =>
    props.canSendMessages &&
    (messageText.value.trim() || selectedFiles.value.length > 0) &&
    !isSending.value,
);

// Messages with day dividers, and runs of consecutive messages from the
// same side (only the last message of a run shows the avatar and time).
const dayLabel = (value) => {
  const day = formatPhilippineDate(value);
  if (day === formatPhilippineDate(new Date())) return "Today";
  if (day === formatPhilippineDate(new Date(Date.now() - 86400000))) return "Yesterday";
  return day;
};

const messageItems = computed(() => {
  const messages = selectedConversation.value?.messages || [];
  const items = [];
  let previousDay = null;

  messages.forEach((message, index) => {
    const day = message.created_at ? formatPhilippineDate(message.created_at) : null;
    if (day && day !== previousDay) {
      items.push({ type: "day", key: `day-${day}`, label: dayLabel(message.created_at) });
      previousDay = day;
    }

    const previous = messages[index - 1];
    const next = messages[index + 1];
    const sameDay = (other) =>
      !other?.created_at || !message.created_at || formatPhilippineDate(other.created_at) === day;
    const sameSide = (other) => other && !!other.is_own === !!message.is_own && sameDay(other);

    items.push({
      type: "message",
      key: `msg-${message.id}`,
      message,
      runStart: !sameSide(previous),
      runEnd: !sameSide(next),
    });
  });

  return items;
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

const getStoreInitial = (storeName) => storeName?.trim()?.charAt(0)?.toUpperCase() || "?";

const getOtherUser = (conversation) => {
  if (!conversation) return { name: "", email: "", avatar: null, online: false, display_name: "" };
  return (isVendor.value ? conversation.customer : conversation.vendor) || {};
};

// Use the same vendor display-name fallback as Cart.vue. Conversation and
// search responses already include this data, so no additional API call is
// needed to identify each vendor's store.
const getVendorDisplayName = (vendor) =>
  vendor?.display_name || vendor?.store_name || vendor?.name || "Local Vendor";

const getOtherUserDisplayName = (conversation) => {
  const otherUser = getOtherUser(conversation);
  return isVendor.value
    ? otherUser.display_name || otherUser.name || "Customer"
    : getVendorDisplayName(otherUser);
};

const getOtherUserAvatarInitial = (conversation) =>
  isVendor.value
    ? getInitials(getOtherUserDisplayName(conversation))
    : getStoreInitial(getOtherUserDisplayName(conversation));

const getSearchResultDisplayName = (otherUser) =>
  isVendor.value
    ? otherUser?.display_name || otherUser?.name || "Customer"
    : getVendorDisplayName(otherUser);

const getSearchResultAvatarInitial = (otherUser) =>
  isVendor.value
    ? getInitials(getSearchResultDisplayName(otherUser))
    : getStoreInitial(getSearchResultDisplayName(otherUser));

// Photo/file-only messages used to be saved with placeholder text
const isAttachmentPlaceholder = (text) =>
  ["Image", "File", "📷 Image", "📎 File"].includes(text) || !String(text ?? "").trim();

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
    userDetails.value = null;
    sharedFiles.value = [];

    if (conversation.unread_count > 0) {
      conversation.unread_count = 0;
    }

    if (conversation.messages && conversation.messages.length > 0) {
      nextTick(() => scrollToBottom());
      return;
    }

    loadingMessages.value = true;
    const { data } = await api.get(`/chat/conversations/${conversation.id}/messages`);

    if (data.success) {
      conversation.messages = data.messages || [];

      if (isVendor.value) {
        conversation.customer = conversation.customer || data.other_user;
      } else {
        conversation.vendor = conversation.vendor || data.other_user;
      }

      if (conversation.messages.length > 0) {
        lastMessageId.value = Math.max(lastMessageId.value, ...conversation.messages.map((m) => m.id));
      }
    }
  } catch (error) {
    console.error("Error fetching messages:", error);
    toast.error("Couldn't load this conversation. Try again.");
  } finally {
    loadingMessages.value = false;
    nextTick(() => scrollToBottom());
  }
};

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

const closeMenu = () => {
  openMenuId.value = null;
};

const askDelete = (conversation) => {
  closeMenu();
  confirmDeleteId.value = conversation.id;
};

const toggleArchive = async (conversation) => {
  const archive = !conversation.is_archived;
  archivingId.value = conversation.id;
  try {
    const { data } = await api.post(
      `/chat/conversations/${conversation.id}/${archive ? "archive" : "unarchive"}`,
    );
    if (data.success) {
      conversation.is_archived = archive;
      toast.success(archive ? "Conversation archived" : "Moved back to your inbox");
    }
  } catch (error) {
    console.error("Error archiving conversation:", error);
    toast.error(error.response?.data?.message || "Couldn't update the conversation.");
  } finally {
    archivingId.value = null;
    closeMenu();
  }
};

const deleteConversation = async (conversation) => {
  deletingId.value = conversation.id;
  try {
    const { data } = await api.delete(`/chat/conversations/${conversation.id}`);
    if (data.success) {
      conversations.value = conversations.value.filter((c) => c.id !== conversation.id);
      if (selectedConversation.value?.id === conversation.id) closeThread();
      toast.success("Conversation deleted");
    }
  } catch (error) {
    console.error("Error deleting conversation:", error);
    toast.error(error.response?.data?.message || "Couldn't delete the conversation.");
  } finally {
    deletingId.value = null;
    confirmDeleteId.value = null;
  }
};

const closeThread = () => {
  selectedConversation.value = null;
  showUserDetails.value = false;
};

// Matches the server's preview: "You sent a photo" / "You: hi" for your own
// messages, "Shop sent a photo" / "hi" for the other person's.
const formatLastMessagePreview = (conversation, message, currentUserId) => {
  const isOwnMessage = message.is_own === true || (message.is_own === undefined && message.sender_id === currentUserId);
  const attachments = message.attachments || [];
  const text = isAttachmentPlaceholder(message.text) ? "" : message.text;

  if (!text && attachments.length > 0) {
    const allImages = attachments.every((a) => a.type && a.type.startsWith("image/"));
    const count = attachments.length;
    const what = allImages
      ? count > 1 ? `${count} photos` : "a photo"
      : count > 1 ? `${count} files` : "a file";
    return `${isOwnMessage ? "You" : getOtherUserDisplayName(conversation)} sent ${what}`;
  }
  return isOwnMessage ? `You: ${text}` : text;
};

const resizeComposer = () => {
  const el = composerInput.value;
  if (!el) return;
  el.style.height = "auto";
  el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
};

const sendMessage = async () => {
  if (!props.canSendMessages || !canSend.value) return;

  const textToSend = messageText.value.trim();
  const filesToSend = [...selectedFiles.value];

  if (!textToSend && filesToSend.length === 0) return;
  const askingAbout = activeAbout.value;
  if (askingAbout && !textToSend) {
    toast.error("Write your question first.");
    return;
  }

  try {
    isSending.value = true;

    const formData = new FormData();
    formData.append("conversation_id", selectedConversation.value.id);
    if (textToSend) formData.append("message", textToSend);
    if (askingAbout) formData.append(askingAbout.type === "order" ? "about_order_id" : "about_product_id", askingAbout.id);
    filesToSend.forEach((file) => formData.append("attachments[]", file));

    const { data } = await api.post("chat/messages/send", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (data.success) {
      data.message.is_own = true;
      selectedConversation.value.messages.push(data.message);
      selectedConversation.value.is_archived = false; // a new message returns it to the inbox
      selectedConversation.value.last_message = formatLastMessagePreview(
        selectedConversation.value,
        data.message,
        user.value?.id,
      );
      selectedConversation.value.last_message_time = "Just now";
      lastMessageId.value = Math.max(lastMessageId.value, data.message.id);
      messageText.value = "";
      selectedFiles.value = [];
      // The card goes with the first question only
      if (askingAbout) about.value = null;

      nextTick(() => {
        resizeComposer();
        scrollToBottom();
        composerInput.value?.focus();
      });
    }
  } catch (error) {
    console.error("Error sending message:", error);
    toast.error(error.response?.status === 422 && error.response?.data?.message ? error.response.data.message : "Your message wasn't sent. Try again.");
  } finally {
    isSending.value = false;
  }
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

const formatMessageTime = (message) =>
  message?.created_at ? formatPhilippineTime(message.created_at) : message?.time ?? "";

const handleFileUpload = (event) => {
  if (!props.canSendMessages) return;
  selectedFiles.value.push(...Array.from(event.target.files));
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

const isImageFile = (type) => type && type.startsWith("image/");

const fileExtension = (name) => {
  const ext = String(name || "").split(".").pop();
  return ext && ext !== name ? ext.slice(0, 4).toUpperCase() : "FILE";
};

const formatFileSize = (bytes) => {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const viewVendorShop = () => {
  const storeId = selectedConversation.value?.vendor?.store_id;
  if (storeId) {
    router.push({ name: "VendorStorefront", state: { storeId: String(storeId) } });
  }
};

const viewOrders = () => {
  router.push("/customer/orders");
};

const downloadFile = (file) => {
  window.open(file.url, "_blank");
};

const openNewChat = () => {
  if (!canStartConversation.value) return;
  userSearchQuery.value = "";
  searchedUsers.value = [];
  showNewChatModal.value = true;
  if (!isVendor.value) loadShops();
  nextTick(() => newChatSearchInput.value?.focus());
};

// ── Finding a shop: every shop is loaded once, then typing filters right
// away in the browser (no waiting on the server, no out-of-order results)
const allShops = ref(null);
const shopsLoading = ref(false);
const shopsError = ref(false);
// Lower case, accents dropped ("Café" matches "cafe")
const plain = (text) => String(text ?? "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").trim();

async function loadShops(force = false) {
  if ((allShops.value && !force) || shopsLoading.value) return;
  shopsLoading.value = true;
  shopsError.value = false;
  try {
    const { data } = await api.get("/chat/search-vendors", { params: { all: 1 } });
    allShops.value = data.success ? data.vendors : [];
  } catch (error) {
    console.error("Error loading shops:", error);
    shopsError.value = true;
  } finally {
    shopsLoading.value = false;
  }
}

/**
 * Shops whose name (or area) has every word typed, best matches first:
 * names starting with it, then a word in the name starting with it, then the rest.
 */
const newChatResults = computed(() => {
  if (isVendor.value) return searchedUsers.value;
  const shops = allShops.value || [];
  const query = plain(userSearchQuery.value);
  if (!query) return shops;
  const words = query.split(/\s+/);
  return shops
    .map((shop) => {
      const name = plain(shop.display_name || shop.store_name);
      const area = plain(shop.location);
      if (!words.every((w) => name.includes(w) || area.includes(w))) return null;
      const rank = name.startsWith(query) ? 0 : name.split(/\s+/).some((part) => part.startsWith(words[0])) ? 1 : words.every((w) => name.includes(w)) ? 2 : 3;
      return { shop, rank, name };
    })
    .filter(Boolean)
    .sort((a, b) => a.rank - b.rank || a.name.localeCompare(b.name))
    .map((hit) => hit.shop);
});

// Customers (shop side): asked of the server, a moment after typing stops;
// only the answer to the latest text is used
let customerSearchTimer = 0;
let customerSearchSeq = 0;
const searchUsers = () => {
  if (!isVendor.value) return; // shops filter in newChatResults
  clearTimeout(customerSearchTimer);
  const text = userSearchQuery.value.trim();
  if (!canStartConversation.value || !text) {
    customerSearchSeq++;
    searchedUsers.value = [];
    return;
  }
  customerSearchTimer = setTimeout(async () => {
    const seq = ++customerSearchSeq;
    try {
      const { data } = await api.get("/chat/search-customers", { params: { search: text } });
      if (seq === customerSearchSeq && data.success) searchedUsers.value = data.customers;
    } catch (error) {
      console.error("Error searching users:", error);
    }
  }, 250);
};

const startNewConversation = async (otherUser) => {
  if (!canStartConversation.value) return;

  try {
    const { data } = await api.post("/chat/conversations/start", {
      [isVendor.value ? "customer_id" : "vendor_id"]: otherUser.id,
      ...(about.value ? {} : { message: "Hi! I'd like to connect with you." }),
    });

    if (data.success) {
      showNewChatModal.value = false;
      await fetchConversations({ quiet: true });

      const newConv = conversations.value.find((c) => c.id === data.conversation.id);
      if (newConv) {
        await selectConversation(newConv);
      }
    }
  } catch (error) {
    console.error("Error starting conversation:", error);
    toast.error(error.response?.data?.message || "Couldn't start the conversation.");
  }
};

// The product or order the next message asks about (only in that shop's chat)
const about = ref(props.initialAbout ? { ...props.initialAbout, vendorId: Number(props.initialVendorId) } : null);
const activeAbout = computed(() =>
  about.value && !isVendor.value && Number(selectedConversation.value?.vendor?.id) === about.value.vendorId ? about.value : null,
);
// "Out for delivery · 2 items · ₱1,020.00" under an attached order
const aboutOrderMeta = computed(() => {
  const a = activeAbout.value;
  if (!a || a.type !== "order") return "";
  const parts = [];
  if (a.status) parts.push(a.status);
  if (Number(a.items_count) > 0) parts.push(`${a.items_count} ${Number(a.items_count) === 1 ? "item" : "items"}`);
  if (Number(a.total) > 0) parts.push(`₱${Number(a.total).toLocaleString("en-PH", { minimumFractionDigits: 2 })}`);
  return parts.join(" · ");
});

// From "Ask the shop" on an order: the question starts typed, ready to finish or send
let aboutDraftDone = false;
watch(activeAbout, (a) => {
  if (!a || a.type !== "order" || aboutDraftDone) return;
  aboutDraftDone = true;
  if (!messageText.value.trim()) messageText.value = `Hi! I have a question about my order ${a.order_number}: `;
  nextTick(() => {
    resizeComposer?.();
    const input = composerInput.value;
    if (input) {
      input.focus();
      const end = input.value.length;
      input.setSelectionRange?.(end, end);
    }
  });
});

const ORDER_STATUS_LABELS = { pending: "Waiting for the shop", processing: "Being prepared", ready: "Ready", out_for_delivery: "Out for delivery", delivered: "Delivered", completed: "Completed", cancelled: "Cancelled" };
const statusLabel = (status) => ORDER_STATUS_LABELS[status] ?? String(status ?? "").replace(/_/g, " ").replace(/^./, (c) => c.toUpperCase());

const handleInitialVendorConversation = async () => {
  if (
    hasHandledInitialVendor.value ||
    isVendor.value ||
    !normalizedInitialVendorId.value ||
    !canStartConversation.value
  ) {
    return;
  }

  const targetVendorId = normalizedInitialVendorId.value;
  const existingConversation = conversations.value.find(
    (conversation) => Number(conversation.vendor?.id) === targetVendorId,
  );

  hasHandledInitialVendor.value = true;

  if (existingConversation) {
    await selectConversation(existingConversation);
    return;
  }

  await startNewConversation({ id: targetVendorId, name: props.initialVendorName });
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

    if (data.success && data.new_messages?.length > 0 && selectedConversation.value) {
      const knownIds = new Set((selectedConversation.value.messages || []).map((m) => m.id));
      const relevantMessages = data.new_messages.filter(
        (msg) => msg.conversation_id === selectedConversation.value.id && !knownIds.has(msg.id),
      );

      if (relevantMessages.length > 0) {
        selectedConversation.value.messages.push(...relevantMessages);
        selectedConversation.value.is_archived = false;
        const latestMessage = relevantMessages[relevantMessages.length - 1];
        selectedConversation.value.last_message = formatLastMessagePreview(
          selectedConversation.value,
          latestMessage,
          user.value?.id,
        );
        selectedConversation.value.last_message_time = "Just now";
        nextTick(() => scrollToBottom());
      }
      lastMessageId.value = Math.max(lastMessageId.value, data.last_message_id || 0);
    }

    if (data.updated_conversations) {
      data.updated_conversations.forEach((updatedConv) => {
        const conv = conversations.value.find((c) => c.id === updatedConv.id);
        if (!conv) return;
        conv.last_message = formatLastMessagePreview(
          conv,
          { text: updatedConv.last_message, attachments: updatedConv.last_message_attachments || [] },
          user.value?.id,
        );
        conv.last_message_time = updatedConv.last_message_time;
        conv.unread_count = conv.id === selectedConversation.value?.id ? 0 : updatedConv.unread_count;
      });
    }
  } catch (error) {
    console.error("Error polling messages:", error);
  }
};

const fetchConversations = async ({ quiet = false } = {}) => {
  if (!quiet) isLoading.value = true;
  try {
    const endpoint = isVendor.value ? "/chat/conversations" : "/chat/my-conversations";
    const { data } = await api.get(endpoint);

    if (data.success) {
      const previous = new Map(conversations.value.map((c) => [c.id, c.messages]));
      conversations.value = data.conversations.map((conv) => ({
        ...conv,
        messages: previous.get(conv.id) || [],
      }));

      if (normalizedInitialVendorId.value && !isVendor.value) {
        await handleInitialVendorConversation();
      } else if (conversations.value.length > 0 && !selectedConversation.value && !isNarrowScreen()) {
        await selectConversation(conversations.value[0]);
      }
    }
  } catch (error) {
    console.error("Error fetching conversations:", error);
    toast.error("Couldn't load your conversations.");
  } finally {
    isLoading.value = false;
  }
};

watch(
  () => normalizedInitialVendorId.value,
  (nextVendorId, previousVendorId) => {
    if (nextVendorId && nextVendorId !== previousVendorId) {
      hasHandledInitialVendor.value = false;
      if (conversations.value.length > 0) {
        handleInitialVendorConversation();
      }
    }
  },
);

const handleEscape = (event) => {
  if (event.key !== "Escape") return;
  if (imageViewerUrl.value) closeImageViewer();
  else if (openMenuId.value) closeMenu();
  else if (confirmDeleteId.value) confirmDeleteId.value = null;
  else if (showNewChatModal.value) showNewChatModal.value = false;
  else if (showUserDetails.value) showUserDetails.value = false;
};

// Lifecycle
onMounted(() => {
  if (!isAuthenticated.value) {
    router.push("/guest/login");
    return;
  }

  fetchConversations();
  pollingInterval = setInterval(pollNewMessages, 3000);
  window.addEventListener("keydown", handleEscape);
  document.addEventListener("click", closeMenu);
});

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval);
  window.removeEventListener("keydown", handleEscape);
  document.removeEventListener("click", closeMenu);
});
</script>

<style scoped>
/* ── Theme ─────────────────────────────────────────────────────────────
   A calm, modern messenger: one sans-serif type scale, hairline borders,
   neutral surfaces and a single accent. The customer side takes the
   BloomCraft tokens (bloomcraft.css): navy for your own messages and
   primary actions, the logo green for live and unread signals. */
.chat-layout {
  --c-bg: #f4f6f5;
  --c-surface: #ffffff;
  --c-ink: #1f2933;
  --c-soft: #6b7280;
  --c-faint: #9aa1ab;
  --c-line: rgba(15, 23, 42, 0.08);
  --c-line-strong: rgba(15, 23, 42, 0.14);
  --c-hover: rgba(15, 23, 42, 0.04);
  --c-selected: rgba(15, 23, 42, 0.06);
  --c-accent: #2f855a;
  --c-accent-hover: #276749;
  --c-accent-ink: #ffffff;
  --c-own: #1f2933;
  --c-own-ink: #ffffff;
  --c-signal: #3fa36b;
  --c-signal-text: #2e7a4f;
  --c-signal-soft: rgba(63, 163, 107, 0.12);
  --c-avatar: #e9ecef;
  --c-skeleton: #e9ecea;
  --c-skeleton-hi: #f5f7f6;
  --c-danger: #b84034;
  --c-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
  --c-shadow-md: 0 8px 24px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.05);
  --c-shadow-lg: 0 24px 64px rgba(15, 23, 42, 0.18);
  --c-font: "Poppins", -apple-system, "Segoe UI", sans-serif;

  display: flex;
  height: 100vh;
  overflow: hidden;
  background: var(--c-bg);
  color: var(--c-ink);
  font-family: var(--c-font);
  -webkit-font-smoothing: antialiased;
}

.chat-layout.is-customer {
  --c-bg: var(--ivory);
  --c-surface: #ffffff;
  --c-ink: var(--ink);
  --c-soft: var(--ink-soft);
  --c-faint: #8a919c;
  --c-line: rgba(54, 63, 78, 0.09);
  --c-line-strong: rgba(54, 63, 78, 0.16);
  --c-hover: rgba(54, 63, 78, 0.04);
  --c-selected: rgba(54, 63, 78, 0.07);
  --c-accent: var(--ink);
  --c-accent-hover: #262d39;
  --c-accent-ink: #ffffff;
  --c-own: var(--ink);
  --c-own-ink: #ffffff;
  --c-signal: var(--leaf);
  --c-signal-text: var(--leaf-deep);
  --c-signal-soft: rgba(77, 177, 115, 0.12);
  --c-avatar: var(--ivory-deep);
  --c-skeleton: var(--ivory-deep);
  --c-skeleton-hi: var(--paper);
  --c-font: var(--font-sans);
}

.chat-layout.embedded {
  height: 100%;
  min-height: calc(100vh - 170px);
  overflow: hidden;
  border: 1px solid var(--c-line);
  border-radius: 18px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-md);
}

.chat-layout :is(h1, h2, h3, h4, p, dl, dd, ul) {
  margin: 0;
}

/* The site theme makes headings the display serif; the chat keeps one sans-serif */
.chat-layout :is(h1, h2, h3, h4) {
  font-family: var(--c-font);
  letter-spacing: -0.01em;
  line-height: 1.3;
}

.chat-layout .avatar {
  font-family: var(--c-font);
}

.chat-layout ul {
  padding: 0;
  list-style: none;
}

.chat-layout button {
  font-family: inherit;
}

.chat-layout svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.muted {
  color: var(--c-soft);
  font-size: 13px;
}

/* ── Shell: two panels floating on the page ───────────────────────── */
.chat-shell {
  position: relative;
  display: flex;
  flex: 1;
  gap: 12px;
  min-width: 0;
  height: 100%;
  padding: 12px;
}

.embedded .chat-shell {
  gap: 0;
  padding: 0;
}

.is-customer .chat-shell {
  height: calc(100dvh - 80px);
  margin-top: 80px;
  padding: 12px clamp(12px, 2vw, 24px) clamp(12px, 2vw, 24px);
}

.is-vendor .chat-shell {
  margin-left: 260px;
}

@media (max-width: 1200px) {
  .is-vendor .chat-shell {
    margin-left: 200px;
  }
}

@media (max-width: 968px) {
  .is-vendor .chat-shell {
    margin-left: 0;
  }
}

/* ── Shared bits ───────────────────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.btn-primary {
  background: var(--c-accent);
  color: var(--c-accent-ink);
}

.btn-primary:hover:not(:disabled) {
  background: var(--c-accent-hover);
}

.btn-ghost {
  border-color: var(--c-line-strong);
  background: var(--c-surface);
  color: var(--c-ink);
}

.btn-ghost:hover:not(:disabled) {
  background: var(--c-hover);
}

.icon-btn {
  display: inline-grid;
  place-items: center;
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 10px;
  background: transparent;
  color: var(--c-soft);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.icon-btn svg {
  width: 19px;
  height: 19px;
}

.icon-btn:hover:not(:disabled) {
  background: var(--c-hover);
  color: var(--c-ink);
}

.icon-btn.active {
  background: var(--c-selected);
  color: var(--c-ink);
}

.icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.avatar {
  position: relative;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--c-avatar);
  color: var(--c-ink);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.avatar img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}

.avatar--lg {
  width: 72px;
  height: 72px;
  font-size: 24px;
}

.presence {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 12px;
  height: 12px;
  border: 2.5px solid var(--c-surface);
  border-radius: 50%;
  background: var(--c-signal);
}

.count {
  display: inline-grid;
  place-items: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--c-signal);
  color: #ffffff;
  font-size: 10.5px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.skeleton {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, var(--c-skeleton) 30%, var(--c-skeleton-hi) 50%, var(--c-skeleton) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}

@keyframes shimmer {
  from {
    background-position: 100% 0;
  }
  to {
    background-position: 0 0;
  }
}

.file-badge {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  border-radius: 9px;
  background: var(--c-bg);
  color: var(--c-soft);
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.file-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.file-name {
  overflow: hidden;
  font-size: 13px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-size {
  color: var(--c-soft);
  font-size: 11.5px;
}

/* ── Conversation list ─────────────────────────────────────────────── */
.chat-list {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  width: 340px;
  min-height: 0;
  overflow: hidden;
  border: 1px solid var(--c-line);
  border-radius: 18px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-sm);
}

.embedded .chat-list {
  border-width: 0 1px 0 0;
  border-radius: 0;
  box-shadow: none;
}

.chat-list-head {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px 16px 12px;
}

.chat-list-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding-left: 4px;
}

.chat-list-title h1 {
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -0.01em;
  line-height: 1.2;
}

.chat-list-title .icon-btn {
  border: 1px solid var(--c-line-strong);
  color: var(--c-ink);
}

.chat-search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: var(--c-bg);
  color: var(--c-faint);
  transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.chat-search:focus-within {
  border-color: var(--c-line-strong);
  background: var(--c-surface);
  box-shadow: 0 0 0 3px var(--c-signal-soft);
}

.chat-search svg {
  width: 16px;
  height: 16px;
  stroke-width: 2;
}

.chat-search input {
  flex: 1;
  min-width: 0;
  height: 38px;
  border: none;
  outline: none;
  background: none;
  color: var(--c-ink);
  font: inherit;
  font-size: 13.5px;
}

.chat-search input::placeholder {
  color: var(--c-faint);
}

/* All / Unread / Archived: one segmented control */
.chat-filter {
  display: grid;
  grid-auto-columns: 1fr;
  grid-auto-flow: column;
  gap: 2px;
  padding: 3px;
  border-radius: 10px;
  background: var(--c-bg);
}

.chat-filter button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 30px;
  padding: 0 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--c-soft);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.chat-filter button:hover:not(.active) {
  color: var(--c-ink);
}

.chat-filter button.active {
  background: var(--c-surface);
  color: var(--c-ink);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08), 0 0 0 1px var(--c-line);
}

.chat-list-body {
  flex: 1;
  min-height: 0;
  padding: 2px 8px 12px;
  overflow-y: auto;
  scrollbar-width: thin;
}

.conv {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px;
  border: none;
  border-radius: 12px;
  background: transparent;
  color: var(--c-ink);
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}

.conv:hover:not(:disabled) {
  background: var(--c-hover);
}

.conv.active,
.conv.active:hover {
  background: var(--c-selected);
}

.conv:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.conv-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.conv-item {
  position: relative;
}

.conv-item + .conv-item {
  margin-top: 2px;
}

/* Time on top; the ⋯ button sits in the reserved space under it */
.conv-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  align-self: stretch;
  justify-content: space-between;
  gap: 6px;
  flex-shrink: 0;
  padding: 2px 0;
}

.conv-side-bottom {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 22px;
  padding-right: 30px;
}

.conv-more {
  position: absolute;
  right: 8px;
  bottom: 9px;
}

.conv-more-btn {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border: none;
  border-radius: 7px;
  background: transparent;
  color: var(--c-soft);
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.conv-more-btn svg {
  width: 16px;
  height: 16px;
  fill: currentColor;
  stroke: none;
}

.conv-item:hover .conv-more-btn,
.conv-more.open .conv-more-btn,
.conv-more-btn:focus-visible {
  opacity: 1;
}

/* Touch screens have no hover: keep it visible */
@media (hover: none) {
  .conv-more-btn {
    opacity: 1;
  }
}

.conv-more-btn:hover,
.conv-more.open .conv-more-btn {
  background: var(--c-surface);
  color: var(--c-ink);
  box-shadow: 0 0 0 1px var(--c-line);
}

.conv-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 10;
  display: flex;
  flex-direction: column;
  min-width: 180px;
  padding: 5px;
  border: 1px solid var(--c-line);
  border-radius: 12px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-md);
}

.conv-menu button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 36px;
  padding: 0 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--c-ink);
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}

.conv-menu button svg {
  width: 16px;
  height: 16px;
  color: var(--c-soft);
}

.conv-menu button:hover:not(:disabled) {
  background: var(--c-hover);
}

.conv-menu button:disabled {
  opacity: 0.5;
  cursor: wait;
}

.conv-menu button.danger,
.conv-menu button.danger svg {
  color: var(--c-danger);
}

.conv-menu button.danger:hover {
  background: rgba(184, 64, 52, 0.07);
}

.conv-confirm {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid rgba(184, 64, 52, 0.22);
  border-radius: 12px;
  background: rgba(184, 64, 52, 0.04);
}

.conv-confirm p {
  font-size: 13.5px;
  font-weight: 500;
}

.conv-confirm p span {
  display: block;
  color: var(--c-soft);
  font-size: 12px;
  font-weight: 400;
}

.conv-confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

.btn-sm {
  min-height: 30px;
  padding: 0 12px;
  border-radius: 8px;
  font-size: 12.5px;
}

.btn-danger {
  background: var(--c-danger);
  color: #ffffff;
}

.btn-danger:hover:not(:disabled) {
  background: #9a3329;
}

.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.conv-name {
  overflow: hidden;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conv-time {
  flex-shrink: 0;
  color: var(--c-faint);
  font-size: 11.5px;
  font-variant-numeric: tabular-nums;
}

.conv-preview {
  overflow: hidden;
  color: var(--c-soft);
  font-size: 12.5px;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conv.unread .conv-name {
  font-weight: 600;
}

.conv.unread .conv-preview {
  color: var(--c-ink);
  font-weight: 500;
}

.conv.unread .conv-time {
  color: var(--c-signal-text);
  font-weight: 600;
}

.conv--skeleton {
  cursor: default;
}

/* The avatar placeholder shimmers like the lines beside it */
.conv--skeleton .avatar.skeleton {
  border-radius: 50%;
  background: linear-gradient(100deg, var(--c-skeleton) 30%, var(--c-skeleton-hi) 50%, var(--c-skeleton) 70%);
  background-size: 300% 100%;
}

.conv--skeleton .line {
  height: 11px;
  width: 70%;
}

.conv--skeleton .line.short {
  width: 45%;
}

.list-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 44px 20px;
  color: var(--c-soft);
  font-size: 13px;
  line-height: 1.5;
  text-align: center;
}

.list-empty-title {
  color: var(--c-ink);
  font-size: 14px;
  font-weight: 600;
}

.list-empty .btn {
  margin-top: 10px;
}

/* ── Thread ────────────────────────────────────────────────────────── */
.chat-thread {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  border: 1px solid var(--c-line);
  border-radius: 18px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-sm);
}

.embedded .chat-thread {
  border: none;
  border-radius: 0;
  box-shadow: none;
}

.thread-empty {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 40px 24px;
  color: var(--c-soft);
  text-align: center;
}

.thread-empty svg {
  box-sizing: content-box;
  width: 26px;
  height: 26px;
  margin-bottom: 8px;
  padding: 16px;
  border: 1px solid var(--c-line);
  border-radius: 16px;
  background: var(--c-bg);
  color: var(--c-ink);
  stroke-width: 1.8;
}

.thread-empty h2 {
  color: var(--c-ink);
  font-size: 17px;
  font-weight: 600;
}

.thread-empty p {
  max-width: 34ch;
  font-size: 13.5px;
  line-height: 1.55;
}

.thread-empty .btn {
  margin-top: 10px;
}

.thread-head {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 64px;
  padding: 10px 14px 10px 18px;
  border-bottom: 1px solid var(--c-line);
  background: var(--c-surface);
}

.thread-head .avatar {
  width: 40px;
  height: 40px;
  font-size: 14px;
}

.back-btn {
  display: none;
}

.thread-title {
  flex: 1;
  min-width: 0;
}

.thread-title h2 {
  overflow: hidden;
  font-size: 15px;
  font-weight: 600;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.thread-title p {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--c-soft);
  font-size: 12px;
}

.thread-title p.online {
  color: var(--c-signal-text);
}

.thread-title p.online::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--c-signal);
}

.thread-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* The conversation: a quiet canvas between header and composer */
.thread-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
  padding: 20px clamp(16px, 4vw, 40px) 12px;
  overflow-y: auto;
  background: var(--c-bg);
  scrollbar-width: thin;
}

.thread-start {
  margin: auto;
  max-width: 40ch;
  color: var(--c-soft);
  font-size: 13px;
  line-height: 1.55;
  text-align: center;
}

/* "Today", with a hairline either side */
.day-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 20px 0 10px;
  color: var(--c-faint);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.day-divider::before,
.day-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: var(--c-line);
}

.msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  margin-top: 2px;
}

.msg-row.run-start {
  margin-top: 14px;
}

.msg-row.sent {
  justify-content: flex-end;
}

.msg-avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  margin-bottom: 20px;
  overflow: hidden;
  border-radius: 50%;
  font-size: 11px;
  font-weight: 600;
}

.msg-avatar:not(:empty) {
  background: var(--c-avatar);
}

.msg-row:not(.run-end) .msg-avatar {
  margin-bottom: 0;
}

.msg-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.msg {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: min(68%, 540px);
}

.sent .msg {
  align-items: flex-end;
}

.bubble {
  padding: 9px 14px;
  border-radius: 18px;
  background: var(--c-surface);
  color: var(--c-ink);
  font-size: 14px;
  line-height: 1.5;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  box-shadow: 0 0 0 1px var(--c-line), var(--c-shadow-sm);
}

.sent .bubble {
  background: var(--c-own);
  color: var(--c-own-ink);
  box-shadow: none;
}

/* Bubbles in a run read as one block: tighten the corners where they meet */
.received:not(.run-start) .bubble {
  border-top-left-radius: 6px;
}

.received:not(.run-end) .bubble {
  border-bottom-left-radius: 6px;
}

.sent:not(.run-start) .bubble {
  border-top-right-radius: 6px;
}

.sent:not(.run-end) .bubble {
  border-bottom-right-radius: 6px;
}

.msg-meta {
  padding: 0 4px;
  color: var(--c-faint);
  font-size: 11px;
  font-variant-numeric: tabular-nums;
}

/* Order updates, products and questions: a crisp card */
.msg-card {
  display: flex;
  align-items: center;
  gap: 12px;
  width: min(300px, 100%);
  margin-bottom: 4px;
  padding: 10px 12px 10px 10px;
  border: 1px solid var(--c-line);
  border-radius: 14px;
  background: var(--c-surface);
  color: var(--c-ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
  box-shadow: var(--c-shadow-sm);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.msg-card:hover {
  border-color: var(--c-line-strong);
  box-shadow: var(--c-shadow-md);
}

.msg-card-icon,
.msg-card-img {
  flex: none;
  display: grid;
  place-items: center;
  align-self: flex-start;
  width: 48px;
  height: 48px;
  overflow: hidden;
  border-radius: 10px;
  background: var(--c-bg);
  color: var(--c-soft);
}

.msg-card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.msg-card-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.msg-card-main small {
  color: var(--c-faint);
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.msg-card-main strong {
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.msg-card-note {
  color: var(--c-soft);
  font-size: 13px;
  line-height: 1.45;
  white-space: pre-wrap;
}

.msg-card-price {
  color: var(--c-ink);
  font-size: 13px;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.msg-card-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  color: var(--c-signal-text);
  font-size: 12.5px;
  font-weight: 600;
}

.msg-card-link::after {
  content: "→";
  transition: transform 0.15s ease;
}

.msg-card:hover .msg-card-link::after {
  transform: translateX(3px);
}

.msg-attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.sent .msg-attachments {
  justify-content: flex-end;
}

.msg-image {
  padding: 0;
  overflow: hidden;
  border: none;
  border-radius: 14px;
  background: var(--c-surface);
  box-shadow: 0 0 0 1px var(--c-line);
  cursor: zoom-in;
}

.msg-image img {
  display: block;
  width: 220px;
  max-width: 100%;
  height: 220px;
  object-fit: cover;
  transition: transform 0.25s ease;
}

.msg-image:hover img {
  transform: scale(1.03);
}

.msg-file,
.details-file {
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: 280px;
  padding: 8px 12px 8px 8px;
  border: 1px solid var(--c-line);
  border-radius: 12px;
  background: var(--c-surface);
  color: var(--c-ink);
  text-decoration: none;
  transition: border-color 0.15s ease;
}

.msg-file:hover,
.details-file:hover {
  border-color: var(--c-line-strong);
}

.bubble-skeleton {
  height: 38px;
  border-radius: 18px;
}

.msg-row:has(.bubble-skeleton) {
  margin-top: 12px;
}

/* ── Composer: one bar, attach and send inside it ─────────────────── */
.composer {
  padding: 10px clamp(12px, 3vw, 28px) 12px;
  border-top: 1px solid var(--c-line);
  background: var(--c-surface);
}

.composer-files {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

/* The product or order the next message asks about */
.composer-about {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  padding: 8px;
  border: 1px solid var(--c-line);
  border-left: 3px solid var(--c-signal);
  border-radius: 12px;
  background: var(--c-bg);
}

.composer-about-img {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  overflow: hidden;
  border-radius: 8px;
  background: var(--c-surface);
  color: var(--c-soft);
}

.composer-about-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.composer-about-img svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.composer-about-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.composer-about-main small {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--c-signal-text);
  font-size: 11px;
  font-weight: 600;
}

.composer-about-main small svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.composer-about-meta {
  overflow: hidden;
  color: var(--c-soft);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* An attached order: a bigger photo, like a little order card */
.composer-about--order .composer-about-img {
  width: 48px;
  height: 48px;
  border-radius: 10px;
}

.composer-about-main strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.composer-file {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  max-width: 240px;
  padding: 5px;
  border: 1px solid var(--c-line);
  border-radius: 10px;
  background: var(--c-bg);
}

.composer-file .file-badge {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: var(--c-surface);
  font-size: 8.5px;
}

.chip-remove {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border: none;
  border-radius: 7px;
  background: transparent;
  color: var(--c-soft);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.chip-remove:hover:not(:disabled) {
  background: var(--c-hover);
  color: var(--c-ink);
}

.chip-remove svg {
  width: 15px;
  height: 15px;
}

.composer-row {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  padding: 5px;
  border: 1px solid var(--c-line-strong);
  border-radius: 14px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-sm);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.composer-row:focus-within {
  border-color: var(--c-signal);
  box-shadow: 0 0 0 3px var(--c-signal-soft);
}

.composer-field {
  display: flex;
  flex: 1;
  min-width: 0;
  padding: 0 4px;
}

.composer-field textarea {
  flex: 1;
  min-height: 36px;
  max-height: 160px;
  padding: 7px 0;
  border: none;
  outline: none;
  background: none;
  color: var(--c-ink);
  font: inherit;
  font-size: 14px;
  line-height: 1.5;
  resize: none;
}

.composer-field textarea::placeholder {
  color: var(--c-faint);
}

.composer-field textarea:disabled {
  cursor: not-allowed;
}

.send-btn {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 10px;
  background: var(--c-accent);
  color: var(--c-accent-ink);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, transform 0.1s ease;
}

.send-btn svg {
  width: 18px;
  height: 18px;
  stroke-width: 2.2;
}

.send-btn:hover:not(:disabled) {
  background: var(--c-accent-hover);
}

.send-btn:active:not(:disabled) {
  transform: scale(0.94);
}

.send-btn:disabled {
  background: var(--c-bg);
  color: var(--c-faint);
  cursor: not-allowed;
}

/* Shown only while typing, so it doesn't add noise */
.composer-hint {
  margin-top: 6px !important;
  color: var(--c-faint);
  font-size: 11px;
  text-align: right;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.composer:focus-within .composer-hint {
  opacity: 1;
}

/* ── Details panel ─────────────────────────────────────────────────── */
.chat-details {
  display: flex;
  flex-direction: column;
  gap: 20px;
  flex-shrink: 0;
  width: 300px;
  padding: 14px 18px 24px;
  overflow-y: auto;
  border: 1px solid var(--c-line);
  border-radius: 18px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-sm);
}

.embedded .chat-details {
  border-width: 0 0 0 1px;
  border-radius: 0;
  box-shadow: none;
}

.details-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.details-head h3 {
  font-size: 14px;
  font-weight: 600;
}

.details-profile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 0 4px;
  text-align: center;
}

.details-profile .avatar {
  margin-bottom: 10px;
}

.details-profile h4 {
  font-size: 16px;
  font-weight: 600;
}

.details-profile p {
  color: var(--c-soft);
  font-size: 12.5px;
  overflow-wrap: anywhere;
}

.details-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.details-actions .btn:only-child {
  grid-column: 1 / -1;
}

.details-list > div {
  padding: 12px 0;
  border-top: 1px solid var(--c-line);
  border-bottom: 1px solid var(--c-line);
}

.details-list dt,
.details-files h4 {
  color: var(--c-faint);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.details-list dd {
  margin-top: 4px;
  font-size: 13px;
  line-height: 1.5;
}

.details-files h4 {
  margin-bottom: 10px;
}

.details-files ul {
  display: grid;
  gap: 6px;
}

.details-file {
  width: 100%;
  max-width: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

/* ── Overlays ──────────────────────────────────────────────────────── */
.overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 12vh 16px 16px;
  background: rgba(20, 25, 33, 0.36);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
}

.overlay--dark {
  align-items: center;
  padding: 24px;
  background: rgba(16, 20, 27, 0.9);
}

.viewer-image {
  max-width: min(92vw, 1100px);
  max-height: 86vh;
  border-radius: 12px;
  object-fit: contain;
}

.viewer-close {
  position: absolute;
  top: 18px;
  right: 18px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.viewer-close:hover {
  background: rgba(255, 255, 255, 0.22) !important;
  color: #fff !important;
}

.dialog {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  max-width: 440px;
  max-height: 70vh;
  padding: 16px 16px 10px;
  border: 1px solid var(--c-line);
  border-radius: 18px;
  background: var(--c-surface);
  box-shadow: var(--c-shadow-lg);
}

.dialog-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-left: 4px;
}

.dialog-head h3 {
  font-size: 16px;
  font-weight: 600;
}

.dialog-results {
  min-height: 80px;
  overflow-y: auto;
}

.dialog-label {
  padding: 2px 10px 6px;
  color: var(--c-faint);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.dialog-retry {
  margin-left: 4px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--c-signal-text);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.dialog-note {
  padding: 18px 6px;
  text-align: center;
}

/* ── Focus ─────────────────────────────────────────────────────────── */
.chat-layout :is(button, a, input, textarea):focus-visible {
  outline: 2px solid var(--c-signal);
  outline-offset: 2px;
}

.chat-search input:focus-visible,
.composer-field textarea:focus-visible {
  outline: none;
}

/* ── Responsive ────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .chat-details {
    position: absolute;
    top: 12px;
    right: 12px;
    bottom: 12px;
    z-index: 5;
    box-shadow: var(--c-shadow-lg);
  }

  .is-customer .chat-details {
    right: clamp(12px, 2vw, 24px);
    bottom: clamp(12px, 2vw, 24px);
  }
}

@media (max-width: 968px) {
  .chat-list {
    width: 300px;
  }
}

/* Phones: the list and the open conversation take turns, edge to edge */
@media (max-width: 768px) {
  .chat-shell,
  .is-customer .chat-shell {
    padding: 0;
  }

  .chat-list,
  .chat-thread {
    width: 100%;
    border: none;
    border-radius: 0;
    box-shadow: none;
  }

  .chat-thread {
    display: none;
  }

  .thread-open .chat-list {
    display: none;
  }

  .thread-open .chat-thread {
    display: flex;
  }

  .back-btn {
    display: inline-grid;
    margin-left: -6px;
  }

  .thread-head {
    padding: 8px 10px;
  }

  .thread-actions .btn {
    display: none;
  }

  .msg {
    max-width: 84%;
  }

  .composer-hint {
    display: none;
  }

  .chat-details,
  .is-customer .chat-details {
    inset: 0;
    width: 100%;
    border: none;
    border-radius: 0;
  }

  .msg-image img {
    width: 180px;
    height: 180px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton {
    animation: none;
  }

  .msg-image img,
  .msg-card-link::after,
  .send-btn {
    transition: none;
  }
}
</style>
