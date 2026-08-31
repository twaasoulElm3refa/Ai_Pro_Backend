<template>
    <main
        class="general-chat-shell"
        :class="{ 'sidebar-collapsed': desktopSidebarCollapsed }"
        :dir="isArabic ? 'rtl' : 'ltr'"
    >
        <aside class="chat-sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-stars"></i></span>
                <div class="brand-copy">
                    <strong>{{ selectedTool.name }}</strong>
                    <small>{{ copy.generalChat }}</small>
                </div>
                <button
                    type="button"
                    class="icon-button sidebar-close-button"
                    :aria-label="copy.closeSidebar"
                    @click="closeSidebar"
                >
                    <i class="bi bi-layout-sidebar-inset-reverse"></i>
                </button>
            </div>

            <button type="button" class="new-chat-button" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                {{ copy.newChat }}
            </button>

            <p class="section-label">{{ copy.recent }}</p>

            <div v-if="!conversations.length" class="sidebar-empty">
                <i class="bi bi-chat-left-text"></i>
                <span>{{ copy.noChats }}</span>
            </div>

            <div v-else class="conversation-list">
                <div
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    class="conversation-item"
                    :class="{ active: conversation.id === activeConversationId }"
                >
                    <button type="button" class="conversation-open" @click="openConversation(conversation.id)">
                        <i class="bi bi-chat-left-text"></i>
                        <span>{{ conversation.title }}</span>
                    </button>
                    <button
                        type="button"
                        class="conversation-delete"
                        :aria-label="copy.deleteChat"
                        @click="deleteConversation(conversation.id)"
                    >
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </aside>

        <button
            v-if="desktopSidebarCollapsed"
            type="button"
            class="desktop-sidebar-toggle"
            :aria-label="copy.openSidebar"
            @click="openSidebar"
        >
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <button
            v-if="!sidebarOpen"
            type="button"
            class="mobile-sidebar-toggle"
            :aria-label="copy.openSidebar"
            @click="openSidebar"
        >
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <button
            v-if="sidebarOpen"
            type="button"
            class="sidebar-overlay"
            :aria-label="copy.closeSidebar"
            @click="sidebarOpen = false"
        ></button>

        <section class="chat-workspace">
            <header class="workspace-header">
                <span class="header-icon"><i class="bi bi-stars"></i></span>
                <div class="workspace-title">
                    <p>{{ copy.generalChat }}</p>
                    <h1>{{ selectedTool.name }}</h1>
                </div>
                <div class="model-badges">
                    <span v-if="selectedTool.tier" class="model-badge" :class="`tier-${selectedTool.tier}`">
                        {{ tierLabel }}
                    </span>
                    <span v-if="selectedTool.is_free" class="model-badge free-badge">
                        <i class="bi bi-unlock"></i>
                        {{ copy.free }}
                    </span>
                </div>
            </header>

            <div ref="messagesContainer" class="messages-area">
                <div v-if="!activeConversation || !activeConversation.messages.length" class="welcome-card">
                    <span class="welcome-icon"><i class="bi bi-chat-square-dots"></i></span>
                    <p class="welcome-eyebrow">{{ copy.generalChat }}</p>
                    <h2>{{ copy.startConversation }}</h2>
                    <p class="welcome-text">
                        {{ copy.selectedModel }} <strong>{{ selectedTool.name }}</strong>
                    </p>
                    <p v-if="selectedTool.prompt_placeholder" class="prompt-suggestion">
                        <i class="bi bi-lightbulb"></i>
                        {{ selectedTool.prompt_placeholder }}
                    </p>
                    <button v-if="!activeConversation" type="button" class="welcome-button" @click="startNewChat">
                        <i class="bi bi-plus-lg"></i>
                        {{ copy.newChat }}
                    </button>
                </div>

                <div v-else class="message-list" aria-live="polite">
                    <article
                        v-for="message in activeConversation.messages"
                        :key="message.id"
                        class="chat-message user-message"
                    >
                        <span class="message-avatar"><i class="bi bi-person"></i></span>
                        <div class="message-content">
                            <span class="message-role">{{ copy.you }}</span>
                            <p>{{ message.text }}</p>
                        </div>
                    </article>
                </div>
            </div>

            <footer class="composer">
                <div class="composer-box">
                    <textarea
                        ref="textareaRef"
                        v-model="draft"
                        rows="1"
                        :placeholder="composerPlaceholder"
                        :aria-label="copy.message"
                        @input="resizeTextarea"
                        @keydown.enter.exact.prevent="sendMessage"
                    ></textarea>
                    <button
                        type="button"
                        class="send-button"
                        :disabled="!draft.trim()"
                        :aria-label="copy.send"
                        @click="sendMessage"
                    >
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <p>{{ copy.hint }}</p>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute } from "vue-router";
import homeService from "@/services/home/homeService";

const route = useRoute();
const { locale } = useI18n();

const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const conversations = ref([]);
const activeConversationId = ref(null);
const draft = ref("");
const textareaRef = ref(null);
const messagesContainer = ref(null);
let localId = 0;

const normalizeBoolean = (value) => value === true || value === 1 || value === "1";

const prettifySlug = (slug) => {
    const value = String(slug || "general-chat").replace(/[-_]+/g, " ").trim();
    return value.replace(/\b\w/g, (letter) => letter.toUpperCase()) || "General Chat";
};

const fallbackTool = (slug) => ({
    id: null,
    main_tool_id: null,
    name: prettifySlug(slug),
    meta_name: "",
    slug: String(slug || "general-chat"),
    prompt_placeholder: "",
    is_active: true,
    tier: "",
    is_free: false,
});

const normalizeTool = (tool, slug) => {
    const fallback = fallbackTool(slug);

    if (!tool || typeof tool !== "object") return fallback;

    const tier = String(tool.tier || "").toLowerCase();

    return {
        id: tool.id ?? null,
        main_tool_id: tool.main_tool_id ?? null,
        name: String(tool.name || tool.meta_name || fallback.name),
        meta_name: String(tool.meta_name || ""),
        slug: String(tool.slug || fallback.slug),
        prompt_placeholder: String(tool.prompt_placeholder || ""),
        is_active: tool.is_active === undefined ? true : normalizeBoolean(tool.is_active),
        tier: ["free", "standard", "advanced"].includes(tier) ? tier : "",
        is_free: normalizeBoolean(tool.is_free),
    };
};

const readSelectedTool = (slug) => {
    const historyTool = window.history.state?.generalChatTool;

    if (historyTool && String(historyTool.slug) === String(slug)) {
        return normalizeTool(historyTool, slug);
    }

    try {
        const storedTool = JSON.parse(sessionStorage.getItem(`general-chat-tool:${slug}`) || "null");

        if (storedTool && String(storedTool.slug) === String(slug)) {
            return normalizeTool(storedTool, slug);
        }
    } catch {
        // A slug-based display fallback is used when storage is unavailable or invalid.
    }

    return fallbackTool(slug);
};

const selectedTool = ref(readSelectedTool(route.params.slug));

const isArabic = computed(
    () => String(locale.value || route.params.lang || homeService.getLang() || "en").toLowerCase() === "ar"
);

const copy = computed(() =>
    isArabic.value
        ? {
              generalChat: "محادثة عامة",
              newChat: "محادثة جديدة",
              recent: "المحادثات الأخيرة",
              noChats: "لا توجد محادثات مؤقتة بعد",
              untitled: "محادثة جديدة",
              deleteChat: "حذف المحادثة",
              openSidebar: "فتح قائمة المحادثات",
              closeSidebar: "إغلاق قائمة المحادثات",
              startConversation: "ابدأ محادثتك",
              selectedModel: "أنت تتحدث الآن باستخدام",
              message: "اكتب رسالتك",
              send: "إرسال الرسالة",
              hint: "Enter للإرسال · Shift + Enter لسطر جديد · هذه المحادثة محلية مؤقتاً",
              you: "أنت",
              free: "مجاني",
              tiers: { free: "مجاني", standard: "قياسي", advanced: "متقدم" },
          }
        : {
              generalChat: "General Chat",
              newChat: "New Chat",
              recent: "Recent conversations",
              noChats: "No temporary conversations yet",
              untitled: "New conversation",
              deleteChat: "Delete conversation",
              openSidebar: "Open conversations sidebar",
              closeSidebar: "Close conversations sidebar",
              startConversation: "Start your conversation",
              selectedModel: "You are currently chatting with",
              message: "Type your message",
              send: "Send message",
              hint: "Enter to send · Shift + Enter for a new line · This chat is currently local only",
              you: "You",
              free: "Free",
              tiers: { free: "Free", standard: "Standard", advanced: "Advanced" },
          }
);

const activeConversation = computed(
    () => conversations.value.find((conversation) => conversation.id === activeConversationId.value) || null
);

const tierLabel = computed(() => copy.value.tiers[selectedTool.value.tier] || selectedTool.value.tier);

const composerPlaceholder = computed(
    () => selectedTool.value.prompt_placeholder || `${copy.value.message} — ${selectedTool.value.name}`
);

const createConversation = () => {
    localId += 1;

    return {
        id: `local-${Date.now()}-${localId}`,
        title: copy.value.untitled,
        messages: [],
    };
};

const focusComposer = async () => {
    await nextTick();
    textareaRef.value?.focus();
};

const startNewChat = () => {
    const conversation = createConversation();
    conversations.value.unshift(conversation);
    activeConversationId.value = conversation.id;
    sidebarOpen.value = false;
    draft.value = "";
    focusComposer();
};

const openConversation = (conversationId) => {
    activeConversationId.value = conversationId;
    sidebarOpen.value = false;
    focusComposer();
};

const deleteConversation = (conversationId) => {
    const index = conversations.value.findIndex((conversation) => conversation.id === conversationId);
    if (index === -1) return;

    conversations.value.splice(index, 1);

    if (activeConversationId.value === conversationId) {
        activeConversationId.value = conversations.value[0]?.id || null;
    }
};

const resizeTextarea = () => {
    const textarea = textareaRef.value;
    if (!textarea) return;

    textarea.style.height = "auto";
    textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
};

const scrollToLatestMessage = async () => {
    await nextTick();
    const container = messagesContainer.value;
    if (container) container.scrollTop = container.scrollHeight;
};

const sendMessage = () => {
    const text = draft.value.trim();
    if (!text) return;

    if (!activeConversation.value) {
        const conversation = createConversation();
        conversations.value.unshift(conversation);
        activeConversationId.value = conversation.id;
    }

    const conversation = activeConversation.value;
    conversation.messages.push({
        id: `message-${Date.now()}-${conversation.messages.length}`,
        role: "user",
        text,
    });

    if (conversation.messages.length === 1) {
        conversation.title = text.length > 42 ? `${text.slice(0, 42)}…` : text;
    }

    draft.value = "";
    nextTick(() => {
        resizeTextarea();
        scrollToLatestMessage();
        textareaRef.value?.focus();
    });
};

const openSidebar = () => {
    if (window.innerWidth > 900) {
        desktopSidebarCollapsed.value = false;
        return;
    }

    sidebarOpen.value = true;
};

const closeSidebar = () => {
    if (window.innerWidth > 900) {
        desktopSidebarCollapsed.value = true;
        return;
    }

    sidebarOpen.value = false;
};

const syncLocale = () => {
    locale.value = String(route.params.lang || homeService.getLang() || "en").toLowerCase();
};

const handleLangChanged = () => {
    syncLocale();
};

onMounted(() => {
    syncLocale();
    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => route.params.slug,
    (slug, previousSlug) => {
        if (!slug || slug === previousSlug) return;
        selectedTool.value = readSelectedTool(slug);
        conversations.value = [];
        activeConversationId.value = null;
        draft.value = "";
    }
);

watch(
    () => route.params.lang,
    (lang, previousLang) => {
        if (!lang || lang === previousLang) return;
        syncLocale();
    }
);
</script>

<style scoped>
.general-chat-shell {
    --chat-header-offset: 70px;
    height: calc(100dvh - var(--chat-header-offset));
    min-height: 0;
    position: relative;
    display: grid;
    grid-template-columns: 284px minmax(0, 1fr);
    overflow: hidden;
    color: var(--theme-text-primary);
    background: var(--theme-bg);
    transition: grid-template-columns 0.24s ease;
}

button,
textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.chat-sidebar {
    position: relative;
    z-index: 30;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 22px 17px;
    overflow: hidden;
    border-inline-end: 1px solid var(--theme-border);
    background: var(--theme-surface);
    transition: width 0.24s ease, padding 0.24s ease, transform 0.24s ease;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    margin-bottom: 22px;
}

.brand-icon,
.header-icon,
.welcome-icon,
.message-avatar {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: #ffffff;
    background: linear-gradient(145deg, #154677, #2ba6de);
    box-shadow: 0 10px 25px rgba(21, 70, 119, 0.18);
}

.brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
}

.brand-copy,
.workspace-title {
    min-width: 0;
}

.brand-copy strong,
.brand-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.brand-copy strong {
    color: var(--theme-text-primary);
    font-size: 14px;
}

.brand-copy small {
    margin-top: 2px;
    color: var(--theme-text-muted);
    font-size: 11px;
}

.icon-button {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    flex: 0 0 36px;
    margin-inline-start: auto;
    border: 0;
    border-radius: 10px;
    color: var(--theme-text-muted);
    background: transparent;
}

.icon-button:hover {
    color: var(--theme-text-primary);
    background: var(--theme-hover);
}

.new-chat-button {
    width: calc(100% - 18px);
    min-height: 44px;
    margin-inline: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 13px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 11px 25px rgba(21, 70, 119, 0.16);
}

.section-label {
    margin: 26px 8px 10px;
    color: var(--theme-text-muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.sidebar-empty {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 18px 10px;
    color: var(--theme-text-muted);
    font-size: 12px;
    line-height: 1.5;
}

.conversation-list {
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 9px;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    align-items: stretch;
    gap: 7px;
}

.conversation-open {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--theme-border);
    border-radius: 13px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
    text-align: start;
}

.conversation-open span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 700;
}

.conversation-item.active .conversation-open,
.conversation-open:hover {
    border-color: var(--theme-border-strong);
    color: var(--theme-text-primary);
    background: var(--theme-hover);
}

.conversation-delete {
    width: 39px;
    flex: 0 0 39px;
    display: grid;
    place-items: center;
    border: 1px solid var(--theme-border);
    border-radius: 12px;
    color: var(--theme-text-muted);
    background: var(--theme-surface-secondary);
}

.conversation-delete:hover {
    color: var(--app-danger);
    background: var(--theme-hover);
}

.chat-workspace {
    min-width: 0;
    min-height: 0;
    height: 100%;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    overflow: hidden;
}

.workspace-header {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 16px 26px;
    border-bottom: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.header-icon {
    width: 40px;
    height: 40px;
    border-radius: 13px;
}

.workspace-title p {
    margin: 0;
    color: var(--theme-accent);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.workspace-title h1 {
    max-width: min(50vw, 620px);
    margin: 3px 0 0;
    overflow: hidden;
    color: var(--theme-text-primary);
    font-size: 18px;
    font-weight: 850;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.model-badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 7px;
    margin-inline-start: auto;
}

.model-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 9px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.model-badge.tier-free,
.free-badge {
    color: var(--app-success);
}

.model-badge.tier-standard {
    color: var(--theme-accent);
}

.model-badge.tier-advanced {
    color: var(--theme-primary);
}

.messages-area {
    min-height: 0;
    overflow-y: auto;
    padding: 32px max(22px, calc((100% - 900px) / 2));
}

.welcome-card {
    max-width: 620px;
    margin: clamp(24px, 9vh, 90px) auto 0;
    padding: 38px;
    border: 1px solid var(--theme-border);
    border-radius: 24px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 18px 50px var(--theme-shadow);
    text-align: center;
}

.welcome-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 17px;
    border-radius: 20px;
    font-size: 24px;
}

.welcome-eyebrow {
    margin: 0 0 7px;
    color: var(--theme-accent);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.welcome-card h2 {
    margin: 0;
    font-size: clamp(1.45rem, 3vw, 2rem);
}

.welcome-text {
    margin: 10px 0 0;
    color: var(--theme-text-secondary);
    line-height: 1.7;
}

.welcome-text strong {
    color: var(--theme-text-primary);
}

.prompt-suggestion {
    max-width: 520px;
    margin: 20px auto 0;
    padding: 10px 14px;
    border: 1px solid var(--theme-border);
    border-radius: 14px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
    font-size: 13px;
    line-height: 1.6;
}

.prompt-suggestion i {
    margin-inline-end: 5px;
    color: var(--theme-accent);
}

.welcome-button {
    min-width: min(100%, 240px);
    margin-top: 20px;
    padding: 11px 18px;
    border: 0;
    border-radius: 13px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-size: 13px;
    font-weight: 800;
}

.message-list {
    width: min(100%, 900px);
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.chat-message {
    max-width: min(78%, 720px);
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-left: auto;
}

.message-avatar {
    width: 34px;
    height: 34px;
    border-radius: 11px;
    font-size: 14px;
}

.message-content {
    min-width: 0;
    padding: 12px 15px;
    border-radius: 16px 4px 16px 16px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2b7fb2);
    box-shadow: 0 10px 24px rgba(21, 70, 119, 0.15);
}

.message-role {
    display: block;
    margin-bottom: 3px;
    color: rgba(255, 255, 255, 0.72);
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.message-content p {
    margin: 0;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
    line-height: 1.7;
}

.composer {
    padding: 13px max(22px, calc((100% - 900px) / 2)) max(11px, env(safe-area-inset-bottom));
    border-top: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.composer-box {
    display: flex;
    align-items: flex-end;
    gap: 9px;
    padding: 8px;
    border: 1px solid var(--theme-border-strong);
    border-radius: 16px;
    background: var(--theme-input-bg);
    box-shadow: 0 8px 25px var(--theme-shadow);
}

.composer-box:focus-within {
    border-color: var(--theme-accent);
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.12);
}

.composer textarea {
    min-width: 0;
    min-height: 42px;
    max-height: 160px;
    flex: 1;
    padding: 10px;
    resize: none;
    border: 0;
    outline: 0;
    color: var(--theme-text-primary);
    background: transparent;
    line-height: 1.5;
}

.composer textarea::placeholder {
    color: var(--theme-text-muted);
}

.send-button {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 13px;
    color: #ffffff;
    background: linear-gradient(145deg, #154677, #2ba6de);
}

.composer > p {
    margin: 7px 4px 0;
    color: var(--theme-text-muted);
    font-size: 10px;
    text-align: center;
}

.desktop-sidebar-toggle,
.mobile-sidebar-toggle {
    position: absolute;
    inset-block-start: 12px;
    inset-inline-start: 12px;
    z-index: 45;
    width: 42px;
    height: 42px;
    place-items: center;
    border: 1px solid var(--theme-border);
    border-radius: 13px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 12px 28px var(--theme-shadow);
}

.desktop-sidebar-toggle {
    display: grid;
}

.mobile-sidebar-toggle,
.sidebar-overlay {
    display: none;
}

@media (min-width: 901px) {
    .general-chat-shell.sidebar-collapsed {
        grid-template-columns: 0 minmax(0, 1fr);
    }

    .general-chat-shell.sidebar-collapsed .chat-sidebar {
        width: 0;
        padding-inline: 0;
        border-color: transparent;
        pointer-events: none;
    }

    .general-chat-shell.sidebar-collapsed .chat-sidebar > * {
        visibility: hidden;
        opacity: 0;
    }

    .general-chat-shell.sidebar-collapsed .workspace-header {
        padding-inline-start: 72px;
    }
}

@media (max-width: 900px) {
    .general-chat-shell {
        --chat-header-offset: 64px;
        grid-template-columns: minmax(0, 1fr);
    }

    .chat-sidebar {
        position: fixed;
        inset-block-start: var(--chat-header-offset);
        inset-block-end: 0;
        inset-inline-start: 0;
        width: min(310px, 87vw);
        z-index: 70;
        transform: translateX(-110%);
        box-shadow: 18px 0 45px var(--theme-shadow);
    }

    .general-chat-shell[dir="rtl"] .chat-sidebar {
        transform: translateX(110%);
    }

    .chat-sidebar.sidebar-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        inset-block-start: var(--chat-header-offset);
        inset-block-end: 0;
        inset-inline: 0;
        z-index: 60;
        display: block;
        border: 0;
        background: var(--theme-overlay);
    }

    .desktop-sidebar-toggle {
        display: none;
    }

    .mobile-sidebar-toggle {
        display: grid;
    }

    .workspace-header {
        padding-inline-start: 68px;
    }
}

@media (max-width: 580px) {
    .workspace-header {
        gap: 9px;
        padding-block: 13px;
        padding-inline-end: 12px;
    }

    .header-icon {
        display: none;
    }

    .workspace-title h1 {
        max-width: calc(100vw - 170px);
        font-size: 15px;
    }

    .model-badges .model-badge:not(.free-badge) {
        display: none;
    }

    .messages-area,
    .composer {
        padding-inline: 12px;
    }

    .messages-area {
        padding-block: 20px;
    }

    .welcome-card {
        margin-top: 18px;
        padding: 28px 18px;
        border-radius: 20px;
    }

    .welcome-icon {
        width: 56px;
        height: 56px;
        border-radius: 17px;
    }

    .chat-message {
        max-width: 92%;
    }

    .message-avatar {
        display: none;
    }

    .composer > p {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>
