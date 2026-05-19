<template>
    <main class="chat-root" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-label="t('user.chat.workspaceAria')">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon"><i class="bi bi-chat-square-text-fill"></i></div>
                    <div>
                        <span class="brand-name">{{ t("user.chat.conversations") }}</span>
                        <p class="brand-subtitle">{{ subtool.name || t("user.chat.workspaceTitle") }}</p>
                    </div>
                </div>
                <button type="button" class="icon-btn mobile-only" :aria-label="t('user.chat.closeSidebarAria')"
                    @click="sidebarOpen = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <button type="button" class="new-chat-btn" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                <span>{{ creatingConversation ? t("user.chat.creating") : t("user.chat.newChat") }}</span>
            </button>

            <div class="history-section">
                <p class="history-label">{{ t("user.chat.recentChats") }}</p>

                <div v-if="loadingConversations" class="history-skeletons">
                    <div v-for="item in 5" :key="item" class="history-skeleton"></div>
                </div>

                <div v-else-if="filteredConversations.length === 0" class="history-empty">
                    <i class="bi bi-chat-dots"></i>
                    <span>{{ t("user.chat.noConversations") }}</span>
                </div>

                <TransitionGroup v-else name="history-item" tag="div" class="history-list">
                    <div v-for="conversation in filteredConversations" :key="conversation.uuid" class="history-item"
                        :class="{ active: activeConversation?.uuid === conversation.uuid }">
                        <button type="button" class="history-item-main" @click="openConversation(conversation)">
                            <i class="bi bi-chat-left-text"></i>
                            <div class="history-item-info">
                                <span class="history-item-title">{{ conversation.title }}</span>
                            </div>
                        </button>

                        <button type="button" class="history-delete" :aria-label="t('user.chat.deleteConversationAria', {
                            name: conversation.title || t('user.chat.conversationFallback')
                        })" @click="removeConversation(conversation)">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </TransitionGroup>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

        <div class="main-area">
            <div class="messages-wrap" ref="messagesContainer">
                <div v-if="loadingMessages" class="messages-skeleton">
                    <div v-for="item in 4" :key="item" class="message-skeleton"
                        :class="item % 2 === 0 ? 'assistant' : 'user'"></div>
                </div>

                <div v-else-if="messages.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-stars"></i>
                    </div>
                    <h2 class="empty-title">{{ subtool.name || t("user.chat.newConversation") }}</h2>
                    <p class="empty-desc">
                        {{
                            activeConversation?.uuid
                                ? t("user.chat.emptyWithConversation")
                                : t("user.chat.emptyWithoutConversation")
                        }}
                    </p>
                    <button v-if="subtool.promptPlaceholder" type="button" class="suggestion-chip"
                        @click="fillPlaceholder">
                        <i class="bi bi-lightning-charge-fill"></i>
                        {{ subtool.promptPlaceholder }}
                    </button>
                </div>

                <TransitionGroup v-else name="msg" tag="div" class="messages-list">
                    <div v-for="msg in messages" :key="msg.localKey" class="message-row" :class="msg.role">
                        <div class="msg-avatar" v-if="msg.role === 'assistant'">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="msg-bubble">
                            <div class="msg-content" :class="{ 'error-message': msg.is_error }">
                                <span v-if="msg.streaming && !msg.content" class="typing-indicator">
                                    <span></span><span></span><span></span>
                                </span>
                                <div v-else-if="msg.plainText" class="markdown-body plain-text-message">{{ displayMessageContent(msg) }}</div>
                                <div v-else class="markdown-body" v-html="formatMessage(displayMessageContent(msg), msg.role)"></div>
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
                <div v-if="conversationLimitExceeded" class="limit-warning">
                    <div class="limit-warning-icon">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="limit-warning-content">
                        <strong>ÙˆØµÙ„Øª Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø© Ø¥Ù„Ù‰ Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰</strong>
                        <span>Ø§Ø¨Ø¯Ø£ Ù…Ø­Ø§Ø¯Ø«Ø© Ø¬Ø¯ÙŠØ¯Ø© Ù„Ù„Ù…ØªØ§Ø¨Ø¹Ø© Ø¨Ø±Ø³Ø§Ø¦Ù„ Ø¥Ø¶Ø§ÙÙŠØ©.</span>
                    </div>
                </div>

                <div v-if="insufficientPoints && !conversationLimitExceeded" class="points-warning">
                    <div class="points-warning-icon">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="points-warning-content">
                        <strong>Insufficient points</strong>
                        <span>You can send again, but the assistant may return insufficient points until you
                            recharge.</span>
                    </div>
                    <button type="button" class="points-warning-action" @click="goToWallet">
                        Recharge wallet
                    </button>
                </div>

                <div class="input-box" :class="{ focused: inputFocused }">
                    <textarea ref="textareaRef" v-model="userInput" class="chat-input"
                        :aria-label="t('user.chat.inputAria')" :placeholder="conversationLimitExceeded
                            ? 'This conversation has reached the maximum limit. Start a new chat to continue.'
                            : (subtool.promptPlaceholder || t('user.chat.inputPlaceholder'))" rows="1"
                        :disabled="chatSendDisabled" @focus="inputFocused = true" @blur="inputFocused = false"
                        @keydown.enter.exact.prevent="onSubmitMessage" @keydown.shift.enter.exact="newLine"
                        @input="autoResize"></textarea>

                    <div class="input-actions">
                        <span class="char-count">{{ userInput.length }}</span>

                        <button v-if="hideSearchToggle" type="button" class="search-toggle-btn"
                            :class="{ active: searchEnabled }"
                            :aria-label="searchEnabled ? 'Disable web search' : 'Enable web search'"
                            :disabled="chatSendDisabled" @click="searchEnabled = !searchEnabled">
                            <i class="bi bi-search"></i>
                        </button>

                        <button type="button" class="send-btn" :aria-label="t('user.chat.sendAria')"
                            :disabled="!userInput.trim() || chatSendDisabled" @click="onSubmitMessage">
                            <i class="bi"
                                :class="sendingMessage || streamingAssistant ? 'bi-hourglass-split' : 'bi-send-fill'"></i>
                        </button>
                    </div>
                </div>

                <p class="input-hint">
                    <i class="bi bi-info-circle"></i>
                    {{ t("user.chat.inputHint") }}
                </p>
            </div>
        </div>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import MarkdownIt from "markdown-it";
import DOMPurify from "dompurify";
import homeService from "@/services/home/homeService";
import chatServices from "@/services/chat/chatServices";
import useSeoMeta from "@/composables/useSeoMeta";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar"
);

const isAuthenticated = computed(() => Boolean(localStorage.getItem("auth_token")));

const toolLoading = ref(true);
const loadingConversations = ref(true);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const sendingMessage = ref(false);
const streamingAssistant = ref(false);
const conversationLimitExceeded = ref(false);
const insufficientPoints = ref(false);
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
const searchEnabled = ref(false);
const inputFocused = ref(false);
const sidebarOpen = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const activeEventSource = ref(null);
const streamingConversationUuid = ref("");

const PENDING_SEND_TTL = 5 * 60 * 1000;
const inFlightSignatures = new Set();

const filteredConversations = computed(() =>
    conversations.value.filter((conversation) =>
        !subtool.value.id || conversation.sub_tool_id === subtool.value.id
    )
);

/**
 * Ù…Ù‡Ù…:
 * Ù‡Ù†Ø§ insufficientPoints Ø§ØªØ´Ø§Ù„Øª Ù…Ù† Ø§Ù„ØªØ¹Ø·ÙŠÙ„.
 * Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø© ØªØªÙ‚ÙÙ„ ÙÙ‚Ø· Ø¹Ù†Ø¯ limit Ø£Ùˆ Ø£Ø«Ù†Ø§Ø¡ Ø§Ù„Ø¥Ø±Ø³Ø§Ù„/Ø§Ù„Ø³ØªØ±ÙŠÙ….
 */
const chatSendDisabled = computed(() =>
    conversationLimitExceeded.value ||
    sendingMessage.value ||
    streamingAssistant.value
);

const seoTitle = computed(() =>
    isArabic.value
        ? `${subtool.value.name || t("user.chat.workspaceTitle")} | Ai Pro`
        : `${subtool.value.name || t("user.chat.workspaceTitle")} | Ai Pro`
);

const seoDescription = computed(() =>
    isArabic.value
        ? "ØªØ­Ø¯Ø« Ù…Ø¹ Ø§Ù„Ø£Ø¯Ø§Ø© Ø§Ù„ÙØ±Ø¹ÙŠØ© Ø§Ù„Ù…Ø®ØªØ§Ø±Ø©ØŒ Ù†Ø¸Ù‘Ù… Ø³Ø¬Ù„ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø§ØªØŒ ÙˆØ£Ø±Ø³Ù„ Ø±Ø³Ø§Ø¦Ù„Ùƒ ÙÙŠ Ù…Ø³Ø§Ø­Ø© Ø¹Ù…Ù„ Ù…Ø±ÙƒØ²Ø© Ù…Ø¹ Ø§Ø³ØªØ¬Ø§Ø¨Ø§Øª ÙÙˆØ±ÙŠØ© ÙˆØ³ÙŠØ§Ù‚ ÙˆØ§Ø¶Ø­ Ù„ÙƒÙ„ Ù…Ø­Ø§Ø¯Ø«Ø©."
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

const createClientMessageId = () => {
    if (window?.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `msg-${Date.now()}-${Math.random().toString(16).slice(2)}`;
};

const pendingSendStorageKey = (conversationUuid) =>
    `chat-pending-send:${conversationUuid || "unknown"}`;

const readPendingSend = (conversationUuid) => {
    if (!conversationUuid) return null;

    try {
        const raw = sessionStorage.getItem(pendingSendStorageKey(conversationUuid));
        if (!raw) return null;

        const parsed = JSON.parse(raw);

        if (!parsed?.idempotencyKey || !parsed?.content || !parsed?.expiresAt) {
            sessionStorage.removeItem(pendingSendStorageKey(conversationUuid));
            return null;
        }

        if (parsed.expiresAt < Date.now()) {
            sessionStorage.removeItem(pendingSendStorageKey(conversationUuid));
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
};

const writePendingSend = (conversationUuid, content, idempotencyKey) => {
    if (!conversationUuid) return;

    try {
        sessionStorage.setItem(
            pendingSendStorageKey(conversationUuid),
            JSON.stringify({
                content,
                idempotencyKey,
                expiresAt: Date.now() + PENDING_SEND_TTL,
            })
        );
    } catch {
        // Ignore storage edge cases.
    }
};

const clearPendingSend = (conversationUuid) => {
    if (!conversationUuid) return;

    try {
        sessionStorage.removeItem(pendingSendStorageKey(conversationUuid));
    } catch {
        // Ignore storage edge cases.
    }
};

const resolveIdempotencyKey = (conversationUuid, content) => {
    const pending = readPendingSend(conversationUuid);

    if (pending && pending.content === content) {
        return pending.idempotencyKey;
    }

    const idempotencyKey = createClientMessageId();
    writePendingSend(conversationUuid, content, idempotencyKey);

    return idempotencyKey;
};

const cleanConversationTitleText = (text = "") =>
    String(text || "")
        .replace(/<[^>]*>/g, " ")
        .replace(/[#*_`"'$%^&]/g, "")
        .replace(/[â€œâ€â€˜â€™]/g, "")
        .replace(/\s+/g, " ")
        .trim();

const getFirstUserMessageContent = (conversation = {}) => {
    if (typeof conversation.first_user_message_content === "string") {
        return conversation.first_user_message_content;
    }

    if (typeof conversation.first_message_content === "string") {
        return conversation.first_message_content;
    }

    if (typeof conversation.first_user_message === "string") {
        return conversation.first_user_message;
    }

    if (typeof conversation.first_message === "string") {
        return conversation.first_message;
    }

    if (conversation.first_user_message?.content) {
        return conversation.first_user_message.content;
    }

    if (conversation.first_message?.content) {
        return conversation.first_message.content;
    }

    const rows = Array.isArray(conversation.message)
        ? conversation.message
        : Array.isArray(conversation.messages)
            ? conversation.messages
            : [];

    const firstUserMessage = rows.find((message) =>
        message?.role === "user" && message?.content
    );

    return firstUserMessage?.content || "";
};

const makeConversationTitle = (conversation = {}) => {
    const firstUserContent = cleanConversationTitleText(
        getFirstUserMessageContent(conversation)
    );

    const words = firstUserContent
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 3);

    if (words.length) {
        return words.join(" ");
    }

    return t("user.chat.conversationTitle", {
        short: String(conversation.uuid || "").slice(-6) || conversation.id,
    });
};

const formatConversation = (conversation) => ({
    ...conversation,
    title: makeConversationTitle(conversation),
    subtitle: t("user.chat.conversationSubtitle", {
        uuid: conversation.uuid,
    }),
});

const mapMessage = (message, index = 0) => ({
    ...message,
    localKey: `${message.role || "message"}-${index}-${message.id || message.created_at || message.content}`,
    time: message.created_at
        ? new Intl.DateTimeFormat("en-US", {
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(message.created_at))
        : now(),
});

const markdownParser = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
    typographer: true,
});

const escapeHtml = (text = "") =>
    String(text || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

const formatMessage = (text = "", role = "") => {
    const safeText = escapeHtml(String(text || ""));
    const renderedHtml = markdownParser.render(safeText);

    return DOMPurify.sanitize(renderedHtml, {
        USE_PROFILES: { html: true },
    });
};

const hasInsufficientPointsContent = (content = "") => {
    const normalized = String(content || "").toLowerCase();

    return (
        normalized.includes("insufficient points") ||
        normalized.includes("recharge your wallet") ||
        normalized.includes("wallet to continue")
    );
};

/**
 * Ù…Ù‡Ù…:
 * Ø§Ù„Ø¯Ø§Ù„Ø© Ø¯ÙŠ ØªÙØ¶Ù„ Ù…ÙˆØ¬ÙˆØ¯Ø© Ø¹Ø´Ø§Ù† ØªØ¹Ø±Ø¶ Ø§Ù„ØªØ­Ø°ÙŠØ± ÙÙ‚Ø·.
 * Ù„ÙƒÙ†Ù‡Ø§ Ù„Ø§ ØªÙ‚ÙÙ„ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø© Ù„Ø£Ù† chatSendDisabled Ù„Ø§ ÙŠØ¹ØªÙ…Ø¯ Ø¹Ù„Ù‰ insufficientPoints.
 */
const resolveInsufficientPointsState = (rows = []) => {
    const lastAssistantMessage = [...rows]
        .reverse()
        .find((message) => message?.role === "assistant");

    insufficientPoints.value = Boolean(
        lastAssistantMessage?.is_error === true &&
        hasInsufficientPointsContent(lastAssistantMessage?.content || "")
    );
};

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
            const errorContent = payload.content || t("user.chat.genericError");

            messages.value[index].content = errorContent;
            messages.value[index].streaming = false;
            messages.value[index].is_error = true;

            if (hasInsufficientPointsContent(errorContent)) {
                insufficientPoints.value = true;
            }

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

            if (
                payload.message?.is_error === true &&
                hasInsufficientPointsContent(payload.message?.content || "")
            ) {
                insufficientPoints.value = true;
            }

            closeAssistantStream();
            await scrollToBottom();
        }
    };

    source.onerror = () => {
        const index = messages.value.findIndex((item) => item.localKey === assistantMessage.localKey);

        if (index !== -1 && !messages.value[index].content) {
            messages.value[index].content = t("user.chat.connectionInterrupted");
            messages.value[index].streaming = false;
            messages.value[index].is_error = true;
        }

        closeAssistantStream();
    };
};

const hideSearchToggle = computed(() =>
    Number(subtool.value?.id) === 1 // search
);

const extractorTool = computed(() =>
    Number(subtool.value?.id) === 4 // extractor
);

const HEADLINE_TOOL_ID = 4;

const HEADLINE_INTERNAL_PROMPT_MARKERS = [
    "Generate headlines using this extracted state only",
    "Return the final headlines only",
];

const HEADLINE_FIELD_LABELS = {
    content: "الموضوع",
    content_type: "نوع المحتوى",
    goal: "الهدف",
    language: "اللغة",
    tone: "النبرة",
    number_of_headlines: "عدد العناوين",
    headline_length: "طول العنوان",
    extra_options: "خيارات إضافية",
};

const HEADLINE_VALUE_LABELS = {
    Article: "مقال",
    News: "خبر",
    Product: "وصف منتج",
    "Social Post": "منشور اجتماعي",

    "Improve SEO": "تحسين SEO",
    "Increase CTR": "زيادة النقر",
    "Attract Attention": "جذب الانتباه",
    "News Style": "صياغة خبرية",

    Arabic: "العربية",
    English: "الإنجليزية",
    French: "الفرنسية",
    Russian: "الروسية",
    Chinese: "الصينية",

    Powerful: "قوية",
    Formal: "رسمية",
    Professional: "احترافية",
    Marketing: "تسويقية",
    Simple: "بسيطة",

    Auto: "تلقائي",
    Short: "قصير",
    Medium: "متوسط",
    Long: "طويل",

    "Include SEO-friendly headlines": "عناوين مناسبة للسيو",
};

const isHeadlineToolMessage = (msg = {}) => {
    const currentSubToolId = Number(subtool.value?.id || 0);
    const messageSubToolId = Number(msg?.sub_tool_id || msg?.subToolId || 0);

    return messageSubToolId === HEADLINE_TOOL_ID
        || currentSubToolId === HEADLINE_TOOL_ID;
};

const looksLikeHeadlineInternalPrompt = (text = "") => {
    const raw = String(text || "");

    return HEADLINE_INTERNAL_PROMPT_MARKERS.some((marker) =>
        raw.includes(marker)
    ) && raw.includes("{") && raw.includes("}");
};

const normalizeJsonQuotes = (text = "") =>
    String(text || "")
        .replace(/[“”]/g, "\"")
        .replace(/[‘’]/g, "'");

const extractJsonObjectFromText = (text = "") => {
    const raw = normalizeJsonQuotes(text);
    const firstBrace = raw.indexOf("{");
    const lastBrace = raw.lastIndexOf("}");

    if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
        return null;
    }

    const jsonText = raw.slice(firstBrace, lastBrace + 1);

    try {
        return JSON.parse(jsonText);
    } catch (error) {
        console.warn("Failed to parse headline internal JSON", error);
        return null;
    }
};

const humanizeHeadlineValue = (value) => {
    if (value === null || value === undefined || value === "") {
        return "غير محدد";
    }

    if (Array.isArray(value)) {
        return value.length
            ? value.map((item) => HEADLINE_VALUE_LABELS[item] || String(item)).join("، ")
            : "لا يوجد";
    }

    return HEADLINE_VALUE_LABELS[value] || String(value);
};

const buildHeadlineDisplayMessage = (state = {}) => [
    "طلب توليد عناوين",
    `${HEADLINE_FIELD_LABELS.content}: ${humanizeHeadlineValue(state.content)}`,
    `${HEADLINE_FIELD_LABELS.content_type}: ${humanizeHeadlineValue(state.content_type)}`,
    `${HEADLINE_FIELD_LABELS.goal}: ${humanizeHeadlineValue(state.goal)}`,
    `${HEADLINE_FIELD_LABELS.language}: ${humanizeHeadlineValue(state.language)}`,
    `${HEADLINE_FIELD_LABELS.tone}: ${humanizeHeadlineValue(state.tone)}`,
    `${HEADLINE_FIELD_LABELS.number_of_headlines}: ${humanizeHeadlineValue(state.number_of_headlines)}`,
    `${HEADLINE_FIELD_LABELS.headline_length}: ${humanizeHeadlineValue(state.headline_length)}`,
    `${HEADLINE_FIELD_LABELS.extra_options}: ${humanizeHeadlineValue(state.extra_options)}`,
].join("\n");

const stripHeadlineInternalPromptFallback = (text = "") =>
    String(text || "")
        .replace(/Generate headlines using this extracted state only\.?/gi, "")
        .replace(/Return the final headlines only\.?/gi, "")
        .replace(/[{}\[\]"]/g, "")
        .replace(/[:,]/g, " ")
        .replace(/\s+/g, " ")
        .trim();

const displayMessageContent = (msg = {}) => {
    const content = String(msg?.content || "");

    if (
        isHeadlineToolMessage(msg)
        && looksLikeHeadlineInternalPrompt(content)
    ) {
        const parsedState = extractJsonObjectFromText(content);

        if (parsedState) {
            return buildHeadlineDisplayMessage(parsedState);
        }

        return stripHeadlineInternalPromptFallback(content);
    }

    return content;
};

const getInitialExtractorState = () => ({
    content: null,
    content_type: null,
    goal: null,
    language: null,
    tone: null,
    number_of_headlines: null,
    headline_length: null,
    extra_options: [],
});

const extractorState = ref(getInitialExtractorState());
const extractorStep = ref(1);
const extractorFlowConversationKey = ref("");

const getExtractorConversationKey = () =>
    activeConversation.value?.uuid || route.params.uuid || "__draft__";

const FIELD_LABELS = {
    content: "الموضوع",
    content_type: "نوع المحتوى",
    goal: "الهدف",
    language: "اللغة",
    tone: "النبرة",
    number_of_headlines: "عدد العناوين",
    headline_length: "طول العنوان",
    extra_options: "خيارات إضافية",
};

const stripDangerousText = (value = "") =>
    String(value || "")
        .replace(/[<>]/g, "")
        .replace(/[`$\\]/g, "")
        .replace(/\b(select|insert|update|delete|drop|alter|truncate|union|where|from|script|iframe|onerror|onload)\b/gi, "")
        .replace(/\s+/g, " ")
        .trim();

const sanitizeExtractorState = (state = {}) => ({
    content: state.content ? stripDangerousText(state.content).slice(0, 500) : null,
    content_type: state.content_type ? stripDangerousText(state.content_type).slice(0, 80) : null,
    goal: state.goal ? stripDangerousText(state.goal).slice(0, 120) : null,
    language: state.language ? stripDangerousText(state.language).slice(0, 50) : null,
    tone: state.tone ? stripDangerousText(state.tone).slice(0, 50) : null,
    number_of_headlines: Number.isFinite(Number(state.number_of_headlines))
        ? Math.min(Math.max(Number(state.number_of_headlines), 1), 20)
        : null,
    headline_length: state.headline_length ? stripDangerousText(state.headline_length).slice(0, 50) : null,
    extra_options: Array.isArray(state.extra_options)
        ? state.extra_options
            .map((item) => stripDangerousText(item).slice(0, 120))
            .filter(Boolean)
            .slice(0, 10)
        : [],
});

const sanitizeExtractorUpdates = (updates = {}) => {
    const safe = {};

    if (Object.prototype.hasOwnProperty.call(updates, "content")) {
        safe.content = updates.content ? stripDangerousText(updates.content).slice(0, 500) : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "content_type")) {
        safe.content_type = updates.content_type ? stripDangerousText(updates.content_type).slice(0, 80) : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "goal")) {
        safe.goal = updates.goal ? stripDangerousText(updates.goal).slice(0, 120) : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "language")) {
        safe.language = updates.language ? stripDangerousText(updates.language).slice(0, 50) : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "tone")) {
        safe.tone = updates.tone ? stripDangerousText(updates.tone).slice(0, 50) : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "number_of_headlines")) {
        safe.number_of_headlines = Number.isFinite(Number(updates.number_of_headlines))
            ? Math.min(Math.max(Number(updates.number_of_headlines), 1), 20)
            : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "headline_length")) {
        safe.headline_length = updates.headline_length
            ? stripDangerousText(updates.headline_length).slice(0, 50)
            : null;
    }

    if (Object.prototype.hasOwnProperty.call(updates, "extra_options")) {
        safe.extra_options = Array.isArray(updates.extra_options)
            ? updates.extra_options
                .map((item) => stripDangerousText(item).slice(0, 120))
                .filter(Boolean)
                .slice(0, 10)
            : [];
    }

    return safe;
};

const formatExtractorValue = (value) => {
    if (value === null || value === undefined || value === "") {
        return "غير محدد";
    }

    if (Array.isArray(value)) {
        return value.length ? value.join("، ") : "غير محدد";
    }

    return String(value);
};

const buildExtractorSummaryText = (state, missing = []) => {
    const completedLines = Object.entries(state || {})
        .filter(([, value]) => {
            if (Array.isArray(value)) return value.length > 0;
            return value !== null && value !== undefined && value !== "";
        })
        .map(([key, value]) => `• ${FIELD_LABELS[key] || key}: ${formatExtractorValue(value)}`);

    const missingLines = missing.map((key) => `• ${FIELD_LABELS[key] || key}`);

    let text = "تمام ✅\n\n";

    if (completedLines.length) {
        text += `البيانات التي تم تجميعها:\n${completedLines.join("\n")}`;
    }

    if (missingLines.length) {
        text += `\n\nلسه ناقص:\n${missingLines.join("\n")}`;
    }

    return text.trim();
};

const buildExtractorMissingQuestion = (missing = []) => {
    const labels = missing.map((key) => FIELD_LABELS[key] || key).join("، ");
    return `لسه ناقص: ${labels}.\nاكتبهم لي بشكل مختصر من فضلك.`;
};

const addAssistantLocalMessage = async (content, extra = {}) => {
    messages.value.push(
        mapMessage(
            {
                content,
                role: "assistant",
                created_at: new Date().toISOString(),
                plainText: true,
                ...extra,
            },
            messages.value.length
        )
    );

    await scrollToBottom();
};

const addUserLocalMessage = async (content) => {
    messages.value.push(
        mapMessage(
            {
                content,
                role: "user",
                created_at: new Date().toISOString(),
                plainText: true,
            },
            messages.value.length
        )
    );

    await scrollToBottom();
};

const startExtractorFlow = async () => {
    extractorState.value = getInitialExtractorState();
    extractorStep.value = 1;
    extractorFlowConversationKey.value = getExtractorConversationKey();

    await addAssistantLocalMessage(
        "مرحبًا بك 👋\nهساعدك نجهز بيانات توليد العناوين خطوة بخطوة.\n\nأولًا: عايز العناوين عن أي موضوع؟ وهل تريدها SEO؟ وكم عنوان تريد؟"
    );
};

const resetExtractorFlow = () => {
    extractorState.value = getInitialExtractorState();
    extractorStep.value = 1;
    extractorFlowConversationKey.value = "";
};

const normalizeExtractorText = (text = "") =>
    String(text || "").trim();

const parseExtractorStepOne = (text) => {
    const raw = normalizeExtractorText(text);
    const numberMatch = raw.match(/(\d+)/);
    const number_of_headlines = numberMatch ? Number(numberMatch[1]) : null;

    const hasSeo = /(seo|سيو|السيو|تحسين\s*محركات\s*البحث)/i.test(raw);
    const extra_options = hasSeo ? ["Include SEO-friendly headlines"] : [];

    const markerMatch = raw.match(/(?:لمقال\s+عن|عن|حول|بخصوص)\s+(.+)$/i);
    let content = markerMatch?.[1]?.trim() || null;

    if (!content) {
        content = raw
            .replace(/\d+/g, " ")
            .replace(/(?:عايز|عاوز|أريد|اريد|عناوين|عنوان|قوية|قوي|سيو|seo|مقال|article|headlines?|powerful|strong)/gi, " ")
            .replace(/[،,.:;!?؟]/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    if (content) {
        content = content
            .replace(/(?:وسيو|والسيو|seo|سيو|تحسين\s*محركات\s*البحث)/gi, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    return {
        content: content || null,
        number_of_headlines,
        extra_options,
    };
};

const pickMappedValue = (text, dictionary = []) => {
    const normalized = String(text || "").toLowerCase();
    const hit = dictionary.find((entry) =>
        entry.keywords.some((keyword) => normalized.includes(keyword))
    );

    return hit?.value ?? null;
};

const parseExtractorStepTwo = (text) => {
    const normalized = normalizeExtractorText(text).toLowerCase();

    const language = pickMappedValue(normalized, [
        { value: "Arabic", keywords: ["arabic", "عربي", "العربية"] },
        { value: "English", keywords: ["english", "انجليزي", "إنجليزي"] },
        { value: "French", keywords: ["french", "فرنسي"] },
        { value: "Russian", keywords: ["russian", "روسي"] },
        { value: "Chinese", keywords: ["chinese", "صيني"] },
    ]);

    const tone = pickMappedValue(normalized, [
        { value: "Powerful", keywords: ["powerful", "قوي", "قوية"] },
        { value: "Formal", keywords: ["formal", "رسمي"] },
        { value: "Professional", keywords: ["professional", "احترافي"] },
        { value: "Marketing", keywords: ["marketing", "تسويقي"] },
        { value: "Simple", keywords: ["simple", "بسيط"] },
    ]);

    const content_type = pickMappedValue(normalized, [
        { value: "Article", keywords: ["article", "مقال"] },
        { value: "News", keywords: ["news", "خبر"] },
        { value: "Product", keywords: ["product", "منتج"] },
        { value: "Social Post", keywords: ["post", "بوست"] },
    ]);

    return {
        language,
        tone,
        content_type,
    };
};

const parseExtractorStepThree = (text) => {
    const normalized = normalizeExtractorText(text).toLowerCase();

    const headline_length = pickMappedValue(normalized, [
        { value: "Auto", keywords: ["auto", "تلقائي"] },
        { value: "Short", keywords: ["short", "قصير"] },
        { value: "Medium", keywords: ["medium", "متوسط"] },
        { value: "Long", keywords: ["long", "طويل"] },
    ]);

    const goal = pickMappedValue(normalized, [
        { value: "Improve SEO", keywords: ["تحسين السيو", "improve seo", "seo"] },
        { value: "Increase CTR", keywords: ["زيادة النقر", "click", "ctr"] },
        { value: "Attract Attention", keywords: ["جذب الانتباه"] },
        { value: "News Style", keywords: ["إخباري", "خبري"] },
    ]);

    return {
        headline_length,
        goal,
    };
};

const validateExtractorState = (state) => {
    const missing = [];
    const errors = [];

    if (!state?.content || !String(state.content).trim()) {
        missing.push("content");
    }

    if (!state?.content_type) {
        missing.push("content_type");
    }

    if (!state?.goal) {
        missing.push("goal");
    }

    if (!state?.language) {
        missing.push("language");
    }

    if (!state?.tone) {
        missing.push("tone");
    }

    if (state?.number_of_headlines === null || state?.number_of_headlines === undefined) {
        missing.push("number_of_headlines");
    } else if (!Number.isFinite(Number(state.number_of_headlines))) {
        errors.push("number_of_headlines must be a number");
    } else {
        const headlinesCount = Number(state.number_of_headlines);
        if (headlinesCount < 1 || headlinesCount > 20) {
            errors.push("number_of_headlines must be between 1 and 20");
        }
    }

    if (!state?.headline_length) {
        missing.push("headline_length");
    }

    if (!Array.isArray(state?.extra_options)) {
        errors.push("extra_options must be an array");
    }

    return {
        valid: missing.length === 0 && errors.length === 0,
        missing,
        errors,
    };
};

const buildExtractorValidationPayload = (extractedUpdates = {}) => ({
    state: sanitizeExtractorState(extractorState.value),
    extracted_updates: {
        content: extractedUpdates.content ?? null,
        content_type: extractedUpdates.content_type ?? null,
        goal: extractedUpdates.goal ?? null,
        language: extractedUpdates.language ?? null,
        tone: extractedUpdates.tone ?? null,
        number_of_headlines: extractedUpdates.number_of_headlines ?? null,
        headline_length: extractedUpdates.headline_length ?? null,
        extra_options: extractedUpdates.extra_options ?? null,
    },
    reply: "تم تحديث بيانات النموذج.",
});

const applyExtractorUpdates = (updates = {}) => {
    const nextState = { ...extractorState.value };

    if (updates.content !== undefined && updates.content !== null && updates.content !== "") {
        nextState.content = updates.content;
    }

    if (updates.content_type !== undefined && updates.content_type !== null && updates.content_type !== "") {
        nextState.content_type = updates.content_type;
    }

    if (updates.goal !== undefined && updates.goal !== null && updates.goal !== "") {
        nextState.goal = updates.goal;
    }

    if (updates.language !== undefined && updates.language !== null && updates.language !== "") {
        nextState.language = updates.language;
    }

    if (updates.tone !== undefined && updates.tone !== null && updates.tone !== "") {
        nextState.tone = updates.tone;
    }

    if (
        updates.number_of_headlines !== undefined &&
        updates.number_of_headlines !== null &&
        Number.isFinite(Number(updates.number_of_headlines))
    ) {
        nextState.number_of_headlines = Number(updates.number_of_headlines);
    }

    if (updates.headline_length !== undefined && updates.headline_length !== null && updates.headline_length !== "") {
        nextState.headline_length = updates.headline_length;
    }

    if (updates.extra_options !== undefined) {
        nextState.extra_options = Array.isArray(updates.extra_options)
            ? updates.extra_options
            : [];
    }

    extractorState.value = sanitizeExtractorState(nextState);
};

const getMissingPrompt = (field) => {
    const prompts = {
        content: "محتاج أعرف الموضوع الأساسي للعناوين. العناوين عن أي موضوع؟",
        number_of_headlines: "محتاج أعرف عدد العناوين المطلوب. تحب كام عنوان؟",
        language: "دلوقتي محتاج اللغة. مثال: عربي أو English.",
        tone: "دلوقتي محتاج نبرة العناوين. مثال: قوية أو رسمية.",
        content_type: "دلوقتي محتاج نوع المحتوى. مثال: مقال أو خبر.",
        headline_length: "اختار طول العنوان. مثال: قصير أو تلقائي.",
        goal: "حدد الهدف الأساسي. مثال: تحسين SEO أو زيادة CTR.",
    };

    return prompts[field] || "من فضلك أكمل البيانات الناقصة.";
};

const resolveNextExtractorStep = (missingFields = []) => {
    if (missingFields.some((field) => ["content", "number_of_headlines"].includes(field))) {
        return 1;
    }

    if (missingFields.some((field) => ["language", "tone", "content_type"].includes(field))) {
        return 2;
    }

    return 3;
};

const askExtractorStepQuestion = async (step) => {
    if (step === 2) {
        await addAssistantLocalMessage(
            "تمام ✅\nدلوقتي محتاج اللغة، ونبرة العناوين، ونوع المحتوى.\nمثال: عربي، قوية، مقال."
        );
        return;
    }

    if (step === 3) {
        await addAssistantLocalMessage(
            "آخر خطوة ✨\nاختار طول العنوان والهدف الأساسي.\nمثال: قصير، تحسين SEO."
        );
    }
};

const ensureExtractorFlowForCurrentConversation = async () => {
    if (!extractorTool.value) {
        resetExtractorFlow();
        return;
    }

    const conversationKey = getExtractorConversationKey();

    if (messages.value.length > 0) {
        extractorFlowConversationKey.value = conversationKey;
        return;
    }

    if (extractorFlowConversationKey.value === conversationKey) {
        return;
    }

    await startExtractorFlow();
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

const goToWallet = async () => {
    await router.push(`/${homeService.getLang()}/wallet`);
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
            name: data.name || data.translation?.name || t("user.chat.aiTool"),
            description: data.description || data.translation?.description || "",
            promptPlaceholder: data.prompt_placeholder || "",
            imageUrl: data.image ? `/storage/${data.image}` : "",
            optimizedImageUrl: data.image
                ? `/storage/${data.image}`.replace(/\.(png|jpe?g)$/i, ".webp")
                : "",
        };
    } catch {
        subtool.value = {
            id: null,
            name: t("user.chat.aiTool"),
            description: t("user.chat.startConversation"),
            promptPlaceholder: t("user.chat.inputPlaceholder"),
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
        insufficientPoints.value = false;
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
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        resetExtractorFlow();
        return;
    }

    if (!uuid) {
        activeConversation.value = null;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        resetExtractorFlow();
        return;
    }

    loadingMessages.value = true;

    try {
        const response = await chatServices.getConversation(uuid);
        const apiMessage = response?.message || response?.data?.message || "";

        /**
         * Ù‡Ù†Ø§ ÙÙ‚Ø· Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ Ù‡Ùˆ Ø§Ù„Ù„ÙŠ ÙŠÙ‚ÙÙ„ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø©.
         */
        conversationLimitExceeded.value = String(apiMessage)
            .toLowerCase()
            .includes("limit exceeded");

        console.info("[chat] conversation limit state resolved", {
            uuid,
            apiMessage,
            conversationLimitExceeded: conversationLimitExceeded.value,
        });

        const conversation = response?.data || null;
        const rows = Array.isArray(conversation?.message) ? conversation.message : [];

        if (conversation) {
            const formattedConversation = formatConversation({
                ...(conversations.value.find((item) => item.uuid === uuid) || {}),
                ...conversation,
            });

            activeConversation.value = formattedConversation;

            conversations.value = conversations.value.map((item) =>
                item.uuid === uuid
                    ? {
                        ...item,
                        title: formattedConversation.title,
                    }
                    : item
            );
        } else {
            activeConversation.value = null;
        }

        messages.value = rows.map((message, index) => mapMessage(message, index));

        resolveInsufficientPointsState(rows);
        await ensureExtractorFlowForCurrentConversation();

        await scrollToBottom();
    } finally {
        loadingMessages.value = false;
    }
};

const syncRouteConversation = async () => {
    if (!route.params.uuid) {
        activeConversation.value = null;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        await ensureExtractorFlowForCurrentConversation();
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

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
};

const startNewChat = async () => {
    if (!isAuthenticated.value || creatingConversation.value) return;

    creatingConversation.value = true;

    try {
        const response = await chatServices.createConversation(route.params.slug);
        const conversation = formatConversation(response?.data || {});

        conversations.value = [
            conversation,
            ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
        ];

        activeConversation.value = conversation;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        sidebarOpen.value = false;

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
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

    conversations.value = [
        conversation,
        ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
    ];

    activeConversation.value = conversation;
    insufficientPoints.value = false;

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat/${conversation.uuid}`);

    return conversation;
};

const sendExtractorToAi = async () => {
    if (conversationLimitExceeded.value) {
        return false;
    }

    if (sendingMessage.value || streamingAssistant.value) {
        return false;
    }

    const safeState = sanitizeExtractorState(extractorState.value);

    const finalExtractorPrompt = [
        "Generate headlines using this extracted state only.",
        "Return the final headlines only.",
        JSON.stringify(safeState, null, 2),
    ].join("\n\n");
    const displayContent = buildHeadlineDisplayMessage(safeState);

    sendingMessage.value = true;

    const conversation = await ensureConversation();

    if (!conversation) {
        sendingMessage.value = false;
        return false;
    }

    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, displayContent);
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return false;
    }

    inFlightSignatures.add(requestSignature);

    try {
        const payload = {
            content: displayContent,
            conversation_id: conversation.id,
            role: "user",
            idempotency_key: idempotencyKey,
            task_options: {
                search_mode: "off",
                max_tokens: 2500,
                temperature: 0.45,
                source: "headline_generator_state",
                internal_prompt: finalExtractorPrompt,
                extractor_state: safeState,
                headline_state: safeState,
            },
        };

        console.log("Extractor payload before send:", payload);

        const response = await chatServices.sendMessage(payload);

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        await openAssistantStream(conversation, response?.data?.message_id);
        return true;
    } catch {
        await addAssistantLocalMessage("حصل خطأ أثناء الإرسال. جرّب مرة أخرى.");
        return false;
    } finally {
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const handleExtractorSubmit = async (text) => {
    const inputText = String(text || "").trim();
    const safeUserText = stripDangerousText(inputText).slice(0, 1500);

    if (!safeUserText || sendingMessage.value || streamingAssistant.value || conversationLimitExceeded.value) {
        return;
    }

    await addUserLocalMessage(safeUserText);
    userInput.value = "";
    resetTextarea();

    let updates = {};

    if (extractorStep.value === 1) {
        updates = parseExtractorStepOne(safeUserText);
    } else if (extractorStep.value === 2) {
        updates = parseExtractorStepTwo(safeUserText);
    } else {
        updates = parseExtractorStepThree(safeUserText);
    }

    const safeUpdates = sanitizeExtractorUpdates(updates);

    applyExtractorUpdates(safeUpdates);

    const validation = validateExtractorState(extractorState.value);
    console.log("Extractor validation payload", buildExtractorValidationPayload(safeUpdates));

    if (extractorStep.value === 1) {
        const missingStepOne = ["content", "number_of_headlines"].filter((key) =>
            validation.missing.includes(key)
        );

        if (missingStepOne.length) {
            if (missingStepOne.length === 1) {
                await addAssistantLocalMessage(getMissingPrompt(missingStepOne[0]));
            } else {
                await addAssistantLocalMessage(buildExtractorMissingQuestion(missingStepOne));
            }
            return;
        }

        extractorStep.value = 2;
        await addAssistantLocalMessage(
            `${buildExtractorSummaryText(extractorState.value, validation.missing)}\n\nتمام ✅\nدلوقتي محتاج اللغة، ونبرة العناوين، ونوع المحتوى.\nمثال: عربي، قوية، مقال.`
        );
        return;
    }

    if (extractorStep.value === 2) {
        const missingStepTwo = ["language", "tone", "content_type"].filter((key) =>
            validation.missing.includes(key)
        );

        if (missingStepTwo.length) {
            await addAssistantLocalMessage(buildExtractorMissingQuestion(missingStepTwo));
            return;
        }

        extractorStep.value = 3;
        await addAssistantLocalMessage(
            `${buildExtractorSummaryText(extractorState.value, validation.missing)}\n\nآخر خطوة ✨\nاختار طول العنوان والهدف الأساسي.\nمثال: قصير، تحسين SEO.`
        );
        return;
    }

    if (!validation.valid) {
        const nextStep = resolveNextExtractorStep(validation.missing);
        extractorStep.value = nextStep;

        await addAssistantLocalMessage(
            `${buildExtractorSummaryText(extractorState.value, validation.missing)}\n\n${buildExtractorMissingQuestion(validation.missing)}`
        );
        return;
    }

    await addAssistantLocalMessage("تمام ✅ البيانات أصبحت جاهزة لتوليد العناوين.\nجاري توليد العناوين الآن...");
    await sendExtractorToAi();
};
const submitMessage = async () => {
    const rawContent = userInput.value.trim();

    /**
     * Ù…Ù‡Ù…:
     * Ù‡Ù†Ø§ Ø´Ù„Ù†Ø§ insufficientPoints Ù…Ù† Ø´Ø±Ø· Ø§Ù„Ù…Ù†Ø¹.
     * ÙŠØ¹Ù†ÙŠ Ù„Ùˆ Ø¢Ø®Ø± Ø±Ø¯ ÙƒØ§Ù† InsufficientØŒ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙŠÙ‚Ø¯Ø± ÙŠØ¨Ø¹Øª ØªØ§Ù†ÙŠ.
     * Ø§Ù„Ù‚ÙÙ„ ÙÙ‚Ø· Ø¹Ù†Ø¯ limit exceeded.
     */
    if (conversationLimitExceeded.value) {
        return;
    }

    if (!rawContent || sendingMessage.value || streamingAssistant.value) {
        return;
    }

    let content = rawContent;
    let headlineTaskOptions = null;

    if (Number(subtool.value?.id) === HEADLINE_TOOL_ID && looksLikeHeadlineInternalPrompt(rawContent)) {
        const parsedState = extractJsonObjectFromText(rawContent);

        if (parsedState) {
            const safeState = sanitizeExtractorState(parsedState);
            const internalPrompt = [
                "Generate headlines using this extracted state only.",
                "Return the final headlines only.",
                JSON.stringify(safeState, null, 2),
            ].join("\n\n");

            content = buildHeadlineDisplayMessage(safeState);
            headlineTaskOptions = {
                source: "headline_generator_state",
                internal_prompt: internalPrompt,
                headline_state: safeState,
                extractor_state: safeState,
            };
        } else {
            content = stripHeadlineInternalPromptFallback(rawContent);
            headlineTaskOptions = {
                source: "headline_generator_state",
                internal_prompt: rawContent,
            };
        }
    }

    sendingMessage.value = true;

    const conversation = await ensureConversation();

    if (!conversation) {
        sendingMessage.value = false;
        return;
    }

    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, content);
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return;
    }

    inFlightSignatures.add(requestSignature);

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
        const payload = {
            content,
            conversation_id: conversation.id,
            role: "user",
            idempotency_key: idempotencyKey,
            task_options: searchEnabled.value
                ? {
                    search_mode: "on",
                    web_search_max_results: 3,
                    web_search_total_results: 5,
                    max_tokens: 2500,
                    temperature: 0.45,
                }
                : {
                    search_mode: "off",
                    max_tokens: 2500,
                    temperature: 0.45,
                },
        };

        if (headlineTaskOptions) {
            payload.task_options = {
                ...payload.task_options,
                ...headlineTaskOptions,
            };
        }

        console.log("Chat payload before send:", JSON.stringify(payload, null, 2));

        const response = await chatServices.sendMessage(payload);

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

        clearPendingSend(conversation.uuid);

        await openAssistantStream(conversation, response?.data?.message_id);
    } catch {
        messages.value = messages.value.filter((item) => item.localKey !== optimisticMessage.localKey);
    } finally {
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const onSubmitMessage = async () => {
    if (extractorTool.value) {
        await handleExtractorSubmit(userInput.value);
        return;
    }

    await submitMessage();
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
            insufficientPoints.value = false;

            await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat`);
        }
    } finally {
        removingConversationUuid.value = "";
    }
};

const handleLangChanged = async () => {
    locale.value = homeService.getLang();

    closeAssistantStream();

    await Promise.all([
        loadSubtool(),
        loadConversations(),
    ]);

    await syncRouteConversation();
};

onMounted(async () => {
    locale.value = homeService.getLang();

    await Promise.all([
        loadSubtool(),
        loadConversations(),
    ]);

    await syncRouteConversation();

    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    closeAssistantStream();
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => route.params.slug,
    async () => {
        closeAssistantStream();

        await Promise.all([
            loadSubtool(),
            loadConversations(),
        ]);

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

watch(
    () => route.params.lang,
    async (nextLang, prevLang) => {
        if (!nextLang || nextLang === prevLang) return;

        await handleLangChanged();
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
    text-align: start;
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

.markdown-body {
    font-size: 14px;
    line-height: 1.75;
}

.markdown-body :deep(*) {
    word-break: break-word;
}

.markdown-body :deep(p) {
    margin: 0;
}

.markdown-body :deep(p + p) {
    margin-top: 10px;
}

.plain-text-message {
    white-space: pre-line;
}

.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3) {
    margin: 0 0 8px;
    line-height: 1.35;
    font-weight: 800;
}

.markdown-body :deep(h1) {
    font-size: 1.45rem;
}

.markdown-body :deep(h2) {
    font-size: 1.28rem;
}

.markdown-body :deep(h3) {
    font-size: 1.14rem;
}

.markdown-body :deep(strong) {
    font-weight: 800;
}

.markdown-body :deep(em) {
    font-style: italic;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
    margin: 8px 0;
    padding-inline-start: 20px;
}

.markdown-body :deep(li + li) {
    margin-top: 4px;
}

.markdown-body :deep(code) {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 7px;
    background: rgba(15, 23, 42, 0.08);
    font-size: 0.9em;
    font-family: "Cascadia Code", "Consolas", monospace;
}

.markdown-body :deep(pre) {
    margin: 10px 0;
    padding: 12px;
    border-radius: 12px;
    overflow-x: auto;
    background: #0f172a;
    color: #e2e8f0;
    border: 1px solid rgba(148, 163, 184, 0.24);
}

.markdown-body :deep(pre code) {
    display: block;
    padding: 0;
    background: transparent;
    color: inherit;
    font-size: 0.92em;
}

.markdown-body :deep(blockquote) {
    margin: 10px 0;
    padding: 8px 12px;
    border-inline-start: 3px solid rgba(43, 166, 222, 0.45);
    background: rgba(43, 166, 222, 0.08);
    border-radius: 10px;
}

.markdown-body :deep(a) {
    color: inherit;
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
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

.message-row.assistant .msg-content.error-message {
    border-color: rgba(220, 38, 38, 0.25);
    background: #fff1f2;
    color: #991b1b;
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
    margin-bottom: 3.5%;
    padding: 16px 22px 20px;
    border-top: 1px solid rgba(21, 70, 119, 0.08);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    flex-shrink: 0;
}

.limit-warning {
    max-width: 920px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 16px;
    background: #fff7ed;
    border: 1px solid rgba(234, 88, 12, 0.18);
    color: #9a3412;
    box-shadow: 0 14px 28px rgba(154, 52, 18, 0.08);
}

.limit-warning-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(234, 88, 12, 0.12);
    color: #ea580c;
    flex-shrink: 0;
}

.limit-warning-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 13px;
    line-height: 1.6;
}

.limit-warning-content strong {
    font-size: 14px;
    font-weight: 800;
}

.limit-warning-content span {
    color: #b45309;
}

.limit-warning-action {
    border: none;
    background: #ea580c;
    color: #ffffff;
    border-radius: 12px;
    padding: 9px 12px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.limit-warning-action:hover:not(:disabled) {
    background: #c2410c;
    transform: scale(1.03);
    box-shadow: 0 12px 22px rgba(234, 88, 12, 0.18);
}

.limit-warning-action:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.points-warning {
    max-width: 920px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 16px;
    background: #fff1f2;
    border: 1px solid rgba(220, 38, 38, 0.18);
    color: #991b1b;
    box-shadow: 0 14px 28px rgba(153, 27, 27, 0.08);
}

.points-warning-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(220, 38, 38, 0.12);
    color: #dc2626;
    flex-shrink: 0;
}

.points-warning-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 13px;
    line-height: 1.6;
}

.points-warning-content strong {
    font-size: 14px;
    font-weight: 800;
}

.points-warning-action {
    border: none;
    background: #dc2626;
    color: #ffffff;
    border-radius: 12px;
    padding: 9px 12px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.points-warning-action:hover:not(:disabled) {
    background: #b91c1c;
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
    min-height: 24px;
    max-height: 180px;
}

.chat-root[dir="rtl"] .chat-input {
    direction: rtl;
}

.chat-root[dir="ltr"] .chat-input {
    direction: ltr;
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

.search-toggle-btn {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.search-toggle-btn:hover:not(:disabled) {
    background: rgba(15, 23, 42, 0.08);
    color: #0f172a;
}

.search-toggle-btn.active {
    background: rgba(37, 99, 235, 0.12);
    color: #2563eb;
}

.search-toggle-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
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

    0%,
    100% {
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

    0%,
    80%,
    100% {
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

    .limit-warning {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .limit-warning-action {
        width: 100%;
    }

    .points-warning {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .points-warning-action {
        width: 100%;
    }

    .msg-bubble {
        max-width: 88%;
    }
}
</style>
