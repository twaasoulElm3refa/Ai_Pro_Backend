<template>
    <main class="chat-root" aria-label="AI chat workspace">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon"><i class="bi bi-chat-square-text-fill"></i></div>
                    <div>
                        <span class="brand-name">Conversations</span>
                        <p class="brand-subtitle">{{ subtool.name || "Chat Workspace" }}</p>
                    </div>
                </div>
                <button type="button" class="icon-btn mobile-only" aria-label="Close conversations sidebar" @click="sidebarOpen = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <button type="button" class="new-chat-btn" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                <span>{{ creatingConversation ? "Creating..." : "New Chat" }}</span>
            </button>

            <div class="history-section">
                <p class="history-label">Recent Chats</p>

                <div v-if="loadingConversations" class="history-skeletons">
                    <div v-for="item in 5" :key="item" class="history-skeleton"></div>
                </div>

                <div v-else-if="filteredConversations.length === 0" class="history-empty">
                    <i class="bi bi-chat-dots"></i>
                    <span>No conversations yet</span>
                </div>

                <TransitionGroup v-else name="history-item" tag="div" class="history-list">
                    <div
                        v-for="conversation in filteredConversations"
                        :key="conversation.uuid"
                        class="history-item"
                        :class="{ active: activeConversation?.uuid === conversation.uuid }"
                    >
                        <button type="button" class="history-item-main" @click="openConversation(conversation)">
                            <i class="bi bi-chat-left-text"></i>
                            <div class="history-item-info">
                                <span class="history-item-title">{{ conversation.title }}</span>
                                <span class="history-item-date">{{ conversation.subtitle }}</span>
                            </div>
                        </button>
                        <button type="button" class="history-delete"
                            :aria-label="`Delete ${conversation.title || 'conversation'}`" @click="removeConversation(conversation)">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </TransitionGroup>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="icon-btn menu-btn" aria-label="Open conversations sidebar" @click="sidebarOpen = true">
                        <i class="bi bi-list"></i>
                    </button>

                    <div class="model-info" v-if="!toolLoading">
                        <div class="model-avatar">
                            <img v-if="subtool.imageUrl" :src="subtool.optimizedImageUrl || subtool.imageUrl" loading="lazy"
                                decoding="async" @error="onModelImageError"
                                :alt="subtool.name ? `${subtool.name} icon` : 'AI model icon'" />
                            <i v-else class="bi bi-stars"></i>
                        </div>
                        <div>
                            <p class="model-name">{{ subtool.name }}</p>
                            <p class="model-desc">{{ subtool.description }}</p>
                        </div>
                    </div>

                    <div v-else class="model-info">
                        <div class="model-avatar skeleton-box"></div>
                        <div>
                            <div class="skel-line w-32"></div>
                            <div class="skel-line w-48 mt-1"></div>
                        </div>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="model-badge">
                        <i class="bi bi-link-45deg"></i>
                        {{ activeConversation?.uuid ? `#${activeConversation.uuid.slice(-6)}` : "Draft" }}
                    </div>
                </div>
            </header>

            <div class="messages-wrap" ref="messagesContainer">
                <div v-if="loadingMessages" class="messages-skeleton">
                    <div v-for="item in 4" :key="item" class="message-skeleton" :class="item % 2 === 0 ? 'assistant' : 'user'"></div>
                </div>

                <div v-else-if="messages.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-stars"></i>
                    </div>
                    <h2 class="empty-title">{{ subtool.name || "New Conversation" }}</h2>
                    <p class="empty-desc">
                        {{ activeConversation?.uuid ? "Start sending messages in this conversation." : "Create a new chat or send your first message to begin." }}
                    </p>
                    <button v-if="subtool.promptPlaceholder" type="button" class="suggestion-chip" @click="fillPlaceholder">
                        <i class="bi bi-lightning-charge-fill"></i>
                        {{ subtool.promptPlaceholder }}
                    </button>
                </div>

                <TransitionGroup v-else name="msg" tag="div" class="messages-list">
                    <div
                        v-for="msg in messages"
                        :key="msg.localKey"
                        class="message-row"
                        :class="msg.role"
                    >
                        <div class="msg-avatar" v-if="msg.role === 'assistant'">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="msg-bubble">
                            <div class="msg-content">
                                <span v-if="msg.streaming && !msg.content" class="typing-indicator">
                                    <span></span><span></span><span></span>
                                </span>
                                <span v-else v-html="formatMessage(msg.content)"></span>
                            </div>
                            <span class="msg-time">{{ msg.time }}</span>
                        </div>

                        <div class="msg-avatar user-avatar" v-if="msg.role === 'user'">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                </TransitionGroup>
            </div>

            <div class="input-area">
                <div class="input-box" :class="{ focused: inputFocused }">
                    <textarea
                        ref="textareaRef"
                        v-model="userInput"
                        class="chat-input"
                        aria-label="Chat message input"
                        :placeholder="subtool.promptPlaceholder || 'Write your message here...'"
                        rows="1"
                        :disabled="sendingMessage || streamingAssistant"
                        @focus="inputFocused = true"
                        @blur="inputFocused = false"
                        @keydown.enter.exact.prevent="submitMessage"
                        @keydown.shift.enter.exact="newLine"
                        @input="autoResize"
                    ></textarea>

                    <div class="input-actions">
                        <span class="char-count">{{ userInput.length }}</span>
                        <button
                            type="button"
                            class="send-btn"
                            aria-label="Send chat message"
                            :disabled="!userInput.trim() || sendingMessage || streamingAssistant"
                            @click="submitMessage"
                        >
                            <i class="bi" :class="sendingMessage || streamingAssistant ? 'bi-hourglass-split' : 'bi-send-fill'"></i>
                        </button>
                    </div>
                </div>
                <p class="input-hint">
                    <i class="bi bi-info-circle"></i>
                    Enter to send · Shift+Enter for a new line
                </p>
            </div>
        </div>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import homeService from "@/services/home/homeService";
import chatServices from "@/services/chat/chatServices";
import useSeoMeta from "@/composables/useSeoMeta";

const route = useRoute();
const router = useRouter();
const isArabic = computed(() => String(route.params.lang || localStorage.getItem("language") || "en").toLowerCase() === "ar");
const isAuthenticated = computed(() => Boolean(localStorage.getItem("auth_token")));

const toolLoading = ref(true);
const loadingConversations = ref(true);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const sendingMessage = ref(false);
const streamingAssistant = ref(false);
const removingConversationUuid = ref("");

const subtool = ref({
    id: null,
    name: "",
    description: "",
    promptPlaceholder: "",
    imageUrl: "",
    optimizedImageUrl: "",
});
const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const userInput = ref("");
const inputFocused = ref(false);
const sidebarOpen = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const activeEventSource = ref(null);
const streamingConversationUuid = ref("");

const filteredConversations = computed(() =>
    conversations.value.filter((conversation) =>
        !subtool.value.id || conversation.sub_tool_id === subtool.value.id
    )
);
const seoTitle = computed(() =>
    isArabic.value
        ? `${subtool.value.name || "الدردشة"} | Ai Pro`
        : `${subtool.value.name || "Chat Workspace"} | Ai Pro`
);
const seoDescription = computed(() =>
    isArabic.value
        ? "تحدث مع الأداة الفرعية المختارة، نظّم سجل المحادثات، وأرسل رسائلك في مساحة عمل مركزة مع استجابات فورية وسياق واضح لكل محادثة."
        : "Chat with your selected AI subtool, manage conversation history, and send messages in a focused workspace with real-time responses and organized context."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

const now = () => {
    const date = new Date();
    return `${date.getHours()}:${String(date.getMinutes()).padStart(2, "0")}`;
};

const formatConversation = (conversation) => ({
    ...conversation,
    title: `Conversation ${String(conversation.uuid || "").slice(-6) || conversation.id}`,
    subtitle: `UUID: ${conversation.uuid}`,
});

const mapMessage = (message, index = 0) => ({
    ...message,
    localKey: `${message.role || "message"}-${index}-${message.id || message.created_at || message.content}`,
    time: message.created_at
        ? new Intl.DateTimeFormat("en-US", { hour: "2-digit", minute: "2-digit" }).format(new Date(message.created_at))
        : now(),
});

const escapeHtml = (text = "") =>
    String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

const formatMessage = (text = "") =>
    escapeHtml(text)
        .replace(/\n/g, "<br>")
        .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
        .replace(/\*(.*?)\*/g, "<em>$1</em>")
        .replace(/`(.*?)`/g, "<code>$1</code>");

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 180)}px`;
};

const resetTextarea = () => {
    if (textareaRef.value) {
        textareaRef.value.style.height = "auto";
    }
};

const closeAssistantStream = () => {
    if (activeEventSource.value) {
        activeEventSource.value.close();
        activeEventSource.value = null;
    }

    streamingAssistant.value = false;
    streamingConversationUuid.value = "";
};

const streamUrl = (uuid, afterId) => {
    const token = localStorage.getItem("auth_token") || "";
    const params = new URLSearchParams({
        after_id: String(afterId || 0),
        token,
    });

    return `/api/v1/conversation/${uuid}/stream?${params.toString()}`;
};

const openAssistantStream = async (conversation, afterId) => {
    if (!conversation?.uuid || streamingAssistant.value) return;

    const assistantMessage = mapMessage(
        {
            content: "",
            role: "assistant",
            created_at: new Date().toISOString(),
            streaming: true,
        },
        messages.value.length
    );

    messages.value.push(assistantMessage);
    streamingAssistant.value = true;
    streamingConversationUuid.value = conversation.uuid;
    await scrollToBottom();

    const source = new EventSource(streamUrl(conversation.uuid, afterId));
    activeEventSource.value = source;

    source.onmessage = async (event) => {
        const payload = JSON.parse(event.data || "{}");
        const index = messages.value.findIndex((item) => item.localKey === assistantMessage.localKey);

        if (index === -1) return;

        if (payload.type === "token") {
            messages.value[index].content += payload.content || "";
            await scrollToBottom();
        }

        if (payload.type === "error") {
            messages.value[index].content = payload.content || "Something went wrong.";
            messages.value[index].streaming = false;
            closeAssistantStream();
        }

        if (payload.type === "done") {
            messages.value[index] = mapMessage(
                {
                    ...(payload.message || messages.value[index]),
                    content: payload.message?.content || messages.value[index].content,
                    role: "assistant",
                    streaming: false,
                },
                index
            );
            closeAssistantStream();
            await scrollToBottom();
        }
    };

    source.onerror = () => {
        const index = messages.value.findIndex((item) => item.localKey === assistantMessage.localKey);

        if (index !== -1 && !messages.value[index].content) {
            messages.value[index].content = "Connection interrupted. Please try again.";
            messages.value[index].streaming = false;
        }

        closeAssistantStream();
    };
};

const newLine = () => {
    userInput.value += "\n";
    nextTick(autoResize);
};

const fillPlaceholder = () => {
    userInput.value = subtool.value.promptPlaceholder;
    textareaRef.value?.focus();
    autoResize();
};

const onModelImageError = (event) => {
    if (event?.target?.src !== subtool.value.imageUrl) {
        event.target.src = subtool.value.imageUrl;
    }
};

const loadSubtool = async () => {
    toolLoading.value = true;
    try {
        const res = await homeService.showSubtool(route.params.slug);
        const data = res?.data || {};
        subtool.value = {
            id: data.id || null,
            name: data.name || data.translation?.name || "AI Tool",
            description: data.description || data.translation?.description || "",
            promptPlaceholder: data.prompt_placeholder || "",
            imageUrl: data.image ? `/storage/${data.image}` : "",
            optimizedImageUrl: data.image ? `/storage/${data.image}`.replace(/\.(png|jpe?g)$/i, ".webp") : "",
        };
    } catch {
        subtool.value = {
            id: null,
            name: "AI Tool",
            description: "Start a conversation.",
            promptPlaceholder: "Write your message...",
            imageUrl: "",
            optimizedImageUrl: "",
        };
    } finally {
        toolLoading.value = false;
    }
};

const loadConversations = async () => {
    if (!isAuthenticated.value) {
        conversations.value = [];
        loadingConversations.value = false;
        return;
    }

    loadingConversations.value = true;
    try {
        const response = await chatServices.getConversations();
        const rows = Array.isArray(response?.data) ? response.data : [];
        conversations.value = rows.map(formatConversation);
    } finally {
        loadingConversations.value = false;
    }
};

const loadConversationDetails = async (uuid) => {
    if (!isAuthenticated.value) {
        activeConversation.value = null;
        messages.value = [];
        return;
    }

    if (!uuid) {
        activeConversation.value = null;
        messages.value = [];
        return;
    }

    loadingMessages.value = true;
    try {
        const response = await chatServices.getConversation(uuid);
        const conversation = response?.data || null;
        activeConversation.value = conversation
            ? formatConversation({
                ...(conversations.value.find((item) => item.uuid === uuid) || {}),
                ...conversation,
            })
            : null;

        const rows = Array.isArray(conversation?.message) ? conversation.message : [];
        messages.value = rows.map((message, index) => mapMessage(message, index));
        await scrollToBottom();
    } finally {
        loadingMessages.value = false;
    }
};

const syncRouteConversation = async () => {
    if (!route.params.uuid) {
        activeConversation.value = null;
        messages.value = [];
        return;
    }

    const existing = conversations.value.find((item) => item.uuid === route.params.uuid);
    if (existing) {
        activeConversation.value = existing;
    }

    await loadConversationDetails(route.params.uuid);
};

const openConversation = async (conversation) => {
    sidebarOpen.value = false;
    if (!conversation?.uuid) return;

    if (route.params.uuid === conversation.uuid) {
        await loadConversationDetails(conversation.uuid);
        return;
    }

    await router.push(`/${route.params.lang}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
};

const startNewChat = async () => {
    if (!isAuthenticated.value || creatingConversation.value) return;

    creatingConversation.value = true;
    try {
        const response = await chatServices.createConversation(route.params.slug);
        const conversation = formatConversation(response?.data || {});
        conversations.value = [conversation, ...conversations.value.filter((item) => item.uuid !== conversation.uuid)];
        activeConversation.value = conversation;
        messages.value = [];
        sidebarOpen.value = false;
        await router.push(`/${route.params.lang}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
    } finally {
        creatingConversation.value = false;
    }
};

const ensureConversation = async () => {
    if (!isAuthenticated.value) return null;

    if (activeConversation.value?.id && activeConversation.value?.uuid) {
        return activeConversation.value;
    }

    const response = await chatServices.createConversation(route.params.slug);
    const conversation = formatConversation(response?.data || {});
    conversations.value = [conversation, ...conversations.value.filter((item) => item.uuid !== conversation.uuid)];
    activeConversation.value = conversation;
    await router.push(`/${route.params.lang}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
    return conversation;
};

const submitMessage = async () => {
    const content = userInput.value.trim();
    if (!content || sendingMessage.value || streamingAssistant.value) return;

    sendingMessage.value = true;

    const conversation = await ensureConversation();
    if (!conversation) {
        sendingMessage.value = false;
        return;
    }
    const optimisticMessage = mapMessage(
        {
            content,
            role: "user",
            created_at: new Date().toISOString(),
        },
        messages.value.length
    );

    messages.value.push(optimisticMessage);
    userInput.value = "";
    resetTextarea();
    await scrollToBottom();

    try {
        const response = await chatServices.sendMessage({
            content,
            conversation_id: conversation.id,
            role: "user",
        });

        const savedMessage = response?.data?.message;
        if (savedMessage) {
            const lastIndex = messages.value.findIndex((item) => item.localKey === optimisticMessage.localKey);
            if (lastIndex !== -1) {
                messages.value[lastIndex] = mapMessage(savedMessage, lastIndex);
            }
        }

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        await openAssistantStream(conversation, response?.data?.message_id);
    } catch {
        messages.value = messages.value.filter((item) => item.localKey !== optimisticMessage.localKey);
    } finally {
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const removeConversation = async (conversation) => {
    if (!conversation?.uuid || removingConversationUuid.value === conversation.uuid) return;

    removingConversationUuid.value = conversation.uuid;
    try {
        if (streamingConversationUuid.value === conversation.uuid) {
            closeAssistantStream();
        }

        await chatServices.deleteConversation(conversation.uuid);
        conversations.value = conversations.value.filter((item) => item.uuid !== conversation.uuid);

        if (activeConversation.value?.uuid === conversation.uuid || route.params.uuid === conversation.uuid) {
            activeConversation.value = null;
            messages.value = [];
            await router.push(`/${route.params.lang}/subtool/${route.params.slug}/chat`);
        }
    } finally {
        removingConversationUuid.value = "";
    }
};

onMounted(async () => {
    await Promise.all([loadSubtool(), loadConversations()]);
    await syncRouteConversation();
});

onUnmounted(() => {
    closeAssistantStream();
});

watch(
    () => route.params.slug,
    async () => {
        closeAssistantStream();
        await Promise.all([loadSubtool(), loadConversations()]);
        await syncRouteConversation();
    }
);

watch(
    () => route.params.uuid,
    async () => {
        closeAssistantStream();
        await syncRouteConversation();
    }
);
</script>

<style scoped>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.chat-root {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fbff 0%, #eef7fc 100%);
    font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
    direction: rtl;
}

.sidebar {
    width: 320px;
    background: #ffffff;
    border-left: 1px solid rgba(21, 70, 119, 0.1);
    box-shadow: 0 24px 46px rgba(21, 70, 119, 0.08);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    transition: transform 0.3s ease;
    z-index: 100;
    overflow: hidden;
}

.sidebar-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 22px 18px 18px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    box-shadow: 0 16px 30px rgba(21, 70, 119, 0.16);
}

.brand-name {
    display: block;
    color: #154677;
    font-weight: 800;
    font-size: 1rem;
}

.brand-subtitle {
    margin-top: 4px;
    color: #5f7288;
    font-size: 0.82rem;
}

.mobile-only {
    display: none;
}

.new-chat-btn {
    margin: 16px 14px;
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #2ba6de;
    color: #ffffff;
    border: none;
    border-radius: 14px;
    padding: 0 16px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.new-chat-btn:hover:not(:disabled) {
    transform: scale(1.02);
    background: #2398cb;
    box-shadow: 0 20px 36px rgba(21, 70, 119, 0.16);
}

.new-chat-btn:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.history-section {
    flex: 1;
    overflow-y: auto;
    padding: 0 14px 18px;
}

.history-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #94a3b8;
    padding: 8px 4px 12px;
}

.history-skeletons,
.history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.history-skeleton {
    min-height: 68px;
    border-radius: 16px;
    background: linear-gradient(90deg, #eef5fb 25%, #e5eff7 50%, #eef5fb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.25s linear infinite;
}

.history-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    color: #94a3b8;
    font-size: 13px;
    padding: 28px 0;
}

.history-empty i {
    font-size: 22px;
}

.history-item {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.history-item-main {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #f8fbff;
    color: #5f7288;
    border: 1px solid rgba(21, 70, 119, 0.08);
    border-radius: 16px;
    padding: 14px;
    cursor: pointer;
    text-align: right;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.history-item.active .history-item-main,
.history-item-main:hover {
    transform: scale(1.02);
    background: rgba(43, 166, 222, 0.08);
    color: #154677;
    border-color: rgba(43, 166, 222, 0.2);
    box-shadow: 0 18px 32px rgba(21, 70, 119, 0.08);
}

.history-item-main i {
    margin-top: 2px;
    flex-shrink: 0;
    font-size: 15px;
}

.history-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow: hidden;
    min-width: 0;
}

.history-item-title {
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.history-item-date {
    font-size: 11px;
    color: #94a3b8;
}

.history-delete {
    width: 42px;
    min-width: 42px;
    border: 1px solid rgba(21, 70, 119, 0.08);
    border-radius: 14px;
    background: #ffffff;
    color: #154677;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.history-delete:hover {
    transform: scale(1.02);
    background: rgba(43, 166, 222, 0.08);
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.08);
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(21, 70, 119, 0.2);
    backdrop-filter: blur(4px);
    z-index: 99;
}

.main-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: transparent;
}

.topbar {
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    flex-shrink: 0;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.topbar-right {
    display: flex;
    align-items: center;
}

.menu-btn {
    display: none;
}

.model-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.model-avatar {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    overflow: hidden;
    flex-shrink: 0;
}

.model-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.model-name {
    font-size: 15px;
    font-weight: 800;
    color: #154677;
}

.model-desc {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
    max-width: 360px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.model-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.messages-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 28px 22px;
    scroll-behavior: smooth;
}

.messages-wrap::-webkit-scrollbar {
    width: 6px;
}

.messages-wrap::-webkit-scrollbar-thumb {
    background: rgba(21, 70, 119, 0.14);
    border-radius: 999px;
}

.messages-skeleton,
.messages-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 920px;
    margin: 0 auto;
}

.message-skeleton {
    min-height: 88px;
    border-radius: 20px;
    background: linear-gradient(90deg, #eef5fb 25%, #e5eff7 50%, #eef5fb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.25s linear infinite;
    width: 72%;
}

.message-skeleton.user {
    align-self: flex-start;
}

.message-skeleton.assistant {
    align-self: flex-end;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    text-align: center;
    gap: 12px;
    padding: 24px;
}

.empty-icon {
    width: 76px;
    height: 76px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    margin-bottom: 8px;
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.18);
}

.empty-title {
    font-size: 24px;
    font-weight: 800;
    color: #154677;
}

.empty-desc {
    font-size: 14px;
    color: #94a3b8;
    max-width: 420px;
    line-height: 1.8;
}

.suggestion-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 14px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 8px;
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.suggestion-chip:hover {
    background: rgba(43, 166, 222, 0.16);
    transform: scale(1.02);
    box-shadow: 0 16px 28px rgba(21, 70, 119, 0.08);
}

.message-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.message-row.user {
    flex-direction: row-reverse;
}

.msg-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}

.user-avatar {
    background: rgba(43, 166, 222, 0.12);
    color: #154677;
}

.msg-bubble {
    max-width: 72%;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.message-row.user .msg-bubble {
    align-items: flex-end;
}

.msg-content {
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.75;
    word-break: break-word;
    box-shadow: 0 18px 32px rgba(21, 70, 119, 0.08);
}

.message-row.user .msg-content {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: white;
    border-radius: 18px 6px 18px 18px;
}

.message-row.assistant .msg-content {
    background: #ffffff;
    color: #154677;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 6px 18px 18px 18px;
}

.typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-width: 42px;
}

.typing-indicator span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2ba6de;
    animation: typing-bounce 0.9s infinite ease-in-out;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.12s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.24s;
}

.msg-time {
    font-size: 10px;
    color: #94a3b8;
    padding: 0 4px;
}

.input-area {
    padding: 16px 22px 20px;
    border-top: 1px solid rgba(21, 70, 119, 0.08);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    flex-shrink: 0;
}

.input-box {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 18px;
    padding: 12px 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    max-width: 920px;
    margin: 0 auto;
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.06);
}

.input-box.focused {
    border-color: rgba(43, 166, 222, 0.4);
    box-shadow: 0 0 0 4px rgba(43, 166, 222, 0.12);
    transform: scale(1.005);
}

.chat-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    resize: none;
    font-size: 14px;
    color: #154677;
    line-height: 1.7;
    font-family: inherit;
    direction: rtl;
    min-height: 24px;
    max-height: 180px;
}

.chat-input::placeholder {
    color: #94a3b8;
}

.input-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.char-count {
    font-size: 11px;
    color: #94a3b8;
}

.send-btn {
    width: 42px;
    height: 42px;
    background: #2ba6de;
    color: white;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.send-btn:hover:not(:disabled) {
    background: #2398cb;
    transform: scale(1.05);
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.16);
}

.send-btn:disabled {
    background: #cfe6f5;
    cursor: not-allowed;
}

.input-hint {
    text-align: center;
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    max-width: 920px;
    margin: 8px auto 0;
}

.icon-btn {
    width: 36px;
    height: 36px;
    background: transparent;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #94a3b8;
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.icon-btn:hover {
    background: rgba(43, 166, 222, 0.08);
    color: #154677;
    transform: scale(1.02);
}

.skeleton-box {
    background: #e2e8f0;
    animation: pulse 1.5s ease-in-out infinite;
}

.skel-line {
    height: 12px;
    background: #e2e8f0;
    border-radius: 999px;
    animation: pulse 1.5s ease-in-out infinite;
}

.w-32 {
    width: 128px;
}

.w-48 {
    width: 192px;
}

.mt-1 {
    margin-top: 4px;
}

.msg-enter-active,
.history-item-enter-active {
    transition: all 0.25s ease;
}

.msg-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.history-item-enter-from {
    opacity: 0;
    transform: translateX(8px);
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }

    50% {
        opacity: 0.55;
    }
}

@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

@keyframes typing-bounce {
    0%, 80%, 100% {
        opacity: 0.35;
        transform: translateY(0);
    }

    40% {
        opacity: 1;
        transform: translateY(-4px);
    }
}

@media (max-width: 900px) {
    .sidebar {
        position: fixed;
        top: 0;
        right: 0;
        height: 100%;
        transform: translateX(100%);
    }

    .sidebar.sidebar-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        display: block;
    }

    .mobile-only,
    .menu-btn {
        display: flex !important;
    }

    .model-desc {
        display: none;
    }
}

@media (max-width: 720px) {
    .topbar,
    .input-area,
    .messages-wrap {
        padding-inline: 14px;
    }

    .msg-bubble {
        max-width: 88%;
    }
}
</style>
