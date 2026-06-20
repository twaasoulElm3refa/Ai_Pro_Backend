<template>
    <main class="chat3-root" :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="chat3-sidebar" :class="{ open: sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-key-fill"></i></span>
                <div>
                    <strong>{{ pageTitle }}</strong>
                    <small>{{ isArabic ? "مجموعة أدوات المحتوى" : "Content toolset" }}</small>
                </div>
                <button class="icon-button mobile-only" type="button" @click="sidebarOpen = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <button class="new-chat-button" type="button" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                {{ creatingConversation ? labels.creating : labels.newChat }}
            </button>

            <div class="tool-switcher">
                <p class="section-label">{{ isArabic ? "الأدوات" : "Tools" }}</p>
                <button
                    v-for="tool in registryTools"
                    :key="tool.id"
                    class="tool-option"
                    :class="{ active: tool.id === activeTool.id }"
                    type="button"
                >
                    <i class="bi bi-key"></i>
                    <span>{{ localizedToolTitle(tool) }}</span>
                </button>
            </div>

            <p class="section-label">{{ labels.recent }}</p>

            <div v-if="loadingConversations" class="sidebar-status">{{ labels.loading }}</div>
            <div v-else-if="conversations.length === 0" class="sidebar-status">{{ labels.noChats }}</div>

            <div v-else class="conversation-list">
                <div
                    v-for="conversation in conversations"
                    :key="conversation.uuid"
                    class="conversation-item"
                    :class="{ active: conversation.uuid === activeConversation?.uuid }"
                >
                    <button type="button" class="conversation-open" @click="openConversation(conversation)">
                        <i class="bi bi-chat-left-text"></i>
                        <span>{{ conversation.title }}</span>
                    </button>

                    <button
                        type="button"
                        class="conversation-delete"
                        :disabled="deletingUuid === conversation.uuid"
                        :aria-label="labels.deleteChat"
                        @click="deleteConversation(conversation)"
                    >
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </aside>

        <button v-if="sidebarOpen" class="sidebar-overlay" type="button" @click="sidebarOpen = false"></button>

        <section class="workspace">
            <div ref="messagesContainer" class="messages" role="log" aria-live="polite">
                <div v-if="loadingMessages" class="center-status">
                    <span class="spinner"></span>
                    {{ labels.loadingConversation }}
                </div>

                <div v-else-if="messages.length === 0" class="welcome-card">
                    <span class="welcome-icon"><i class="bi bi-key-fill"></i></span>
                    <h2>{{ pageTitle }}</h2>
                    <p>{{ welcomeText }}</p>

                    <button class="suggestion" type="button" @click="fillExample">
                        {{ examplePrompt }}
                    </button>
                </div>

                <div v-else class="message-list">
                    <article
                        v-for="message in messages"
                        :key="message.localKey"
                        class="message-row"
                        :class="message.role"
                    >
                        <div class="avatar">
                            <i :class="message.role === 'assistant' ? 'bi bi-stars' : 'bi bi-person-fill'"></i>
                        </div>

                        <div class="message-body" :class="{ error: message.is_error }">
                            <div v-if="message.typing" class="typing">
                                <span></span><span></span><span></span>
                            </div>

                            <template v-else>
                                <KeywordResult
                                    v-if="isKeywordResultMessage(message)"
                                    :message="message"
                                    :labels="labels"
                                    :copied-key="copiedKey"
                                    @copy-keyword="copyKeyword"
                                    @copy-all="copyAllKeywords"
                                    @regenerate="regenerateKeywordResult"
                                    @refine="startRefine"
                                />

                                <div
                                    v-else-if="message.content"
                                    class="message-content"
                                    v-html="formatMessage(message.content)"
                                ></div>
                            </template>
                        </div>
                    </article>
                </div>
            </div>

            <footer class="composer">
                <div v-if="errorMessage" class="error-banner">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ errorMessage }}
                </div>

                <details class="options-panel">
                    <summary>
                        <span><i class="bi bi-sliders"></i> {{ labels.options }}</span>
                        <span class="options-summary">{{ optionsSummary }}</span>
                    </summary>

                    <div class="options-grid">
                        <label v-for="field in activeTool.fields" :key="field.key">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <input v-model="toolState[field.key]" type="text" :placeholder="field.placeholder">
                        </label>

                        <label>
                            <span>{{ labels.resultsCount }}</span>
                            <input v-model.number="toolState.results_count" type="number" min="1" max="100">
                        </label>

                        <label v-for="field in activeTool.booleanFields" :key="field.key">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <select v-model="toolState[field.key]">
                                <option :value="null">{{ labels.defaultValue }}</option>
                                <option :value="true">{{ labels.yes }}</option>
                                <option :value="false">{{ labels.no }}</option>
                            </select>
                        </label>

                        <label class="wide">
                            <span>{{ labels.extraOptions }}</span>
                            <input v-model="extraOptionsText" type="text" :placeholder="labels.extraOptionsPlaceholder">
                        </label>
                    </div>
                </details>

                <div v-if="refineMode" class="refine-banner">
                    <i class="bi bi-pencil-square"></i>
                    {{ isArabic ? "اكتب التعديل المطلوب ثم أرسل الرسالة." : "Type your refinement and send it." }}
                    <button type="button" @click="refineMode = false">{{ labels.cancel }}</button>
                </div>

                <div class="input-box">
                    <textarea
                        ref="textareaRef"
                        v-model="userMessage"
                        rows="1"
                        :placeholder="composerPlaceholder"
                        :disabled="sendDisabled"
                        @input="autoResize"
                        @keydown.enter.exact.prevent="sendKeywordMessage"
                    ></textarea>

                    <button
                        type="button"
                        class="send-button"
                        :disabled="sendDisabled || !userMessage.trim()"
                        :aria-label="labels.send"
                        @click="sendKeywordMessage"
                    >
                        <i :class="sendingMessage ? 'bi bi-hourglass-split' : 'bi bi-send-fill'"></i>
                    </button>
                </div>

                <p class="composer-hint">{{ labels.hint }}</p>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import MarkdownIt from "markdown-it";
import DOMPurify from "dompurify";
import chatServices from "@/services/chat/chatServices";
import homeService from "@/services/home/homeService";
import useSeoMeta from "@/composables/useSeoMeta";

const KEYWORD_GENERATOR_SUB_TOOL_ID = 13;
const KEYWORD_GENERATOR_TOOL_KEY = "ai_keyword_generator";
const KEYWORD_GENERATOR_MODEL_KEY = "keyword_generator";

const getInitialKeywordGeneratorState = () => ({
    topic: null,
    industry: null,
    target_audience: null,
    language: null,
    keyword_type: null,
    search_intent: null,
    location: null,
    results_count: null,
    include_long_tail: null,
    include_clusters: null,
    extra_options: [],
    last_output: null,
});

const isPlainObject = (value) => value !== null && typeof value === "object" && !Array.isArray(value);

const safeJsonParse = (value) => {
    if (typeof value !== "string") return null;

    const text = value.trim();
    if (!text || (!text.startsWith("{") && !text.startsWith("["))) return null;

    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
};

const unwrapResponse = (source = {}) => {
    if (source?.data && typeof source.data === "object") return source.data;
    return source || {};
};

const normalizeKeywordItem = (item, index = 0) => {
    const row = isPlainObject(item) ? item : { text: item };
    const meta = isPlainObject(row.meta) ? row.meta : {};
    const text = String(row.text || row.title || row.keyword || row.name || "").trim();
    const title = String(row.title || row.keyword || row.text || "").trim();

    if (!text && !title) return null;

    return {
        id: row.id || index + 1,
        title: title || text,
        subject: String(row.subject || "").trim(),
        text: text || title,
        meta: {
            type: meta.type || row.type || null,
            intent: meta.intent || row.intent || null,
            cluster: meta.cluster || row.cluster || null,
        },
    };
};

const normalizeKeywordItems = (items = []) =>
    (Array.isArray(items) ? items : [])
        .map((item, index) => normalizeKeywordItem(item, index))
        .filter(Boolean);

const normalizeKeywordGeneratorResults = (source = {}) => {
    const response = unwrapResponse(source);
    const candidates = [];

    if (typeof response?.state?.last_output === "string") {
        candidates.push(safeJsonParse(response.state.last_output));
    }

    if (typeof response?.results?.[0]?.text === "string") {
        candidates.push(safeJsonParse(response.results[0].text));
    }

    candidates.push(response);

    for (const candidate of candidates) {
        if (!candidate) continue;

        if (Array.isArray(candidate)) {
            const normalized = normalizeKeywordItems(candidate);
            if (normalized.length) return normalized;
        }

        if (Array.isArray(candidate.results)) {
            const normalized = normalizeKeywordItems(candidate.results);
            if (normalized.length) return normalized;
        }
    }

    if (Array.isArray(response.results)) {
        const normalized = normalizeKeywordItems(response.results);
        if (normalized.length) return normalized;
    }

    const fallbackText = String(
        response?.state?.last_output
        || response?.results?.[0]?.text
        || response?.message
        || response?.content
        || ""
    ).trim();

    return fallbackText ? [normalizeKeywordItem({ id: 1, text: fallbackText }, 0)].filter(Boolean) : [];
};

const CHAT3_TOOLS = {
    [KEYWORD_GENERATOR_SUB_TOOL_ID]: {
        id: KEYWORD_GENERATOR_SUB_TOOL_ID,
        slug: "keyword-generator",
        toolKey: KEYWORD_GENERATOR_TOOL_KEY,
        modelKey: KEYWORD_GENERATOR_MODEL_KEY,
        title: "مولد الكلمات المفتاحية",
        titleEn: "Keyword Generator",
        getInitialState: getInitialKeywordGeneratorState,
        normalizeResults: normalizeKeywordGeneratorResults,
        fields: [
            { key: "topic", labelAr: "الموضوع", labelEn: "Topic", placeholder: "AI tools for content creators" },
            { key: "industry", labelAr: "المجال", labelEn: "Industry", placeholder: "Technology" },
            { key: "target_audience", labelAr: "الجمهور المستهدف", labelEn: "Target audience", placeholder: "Content creators" },
            { key: "language", labelAr: "اللغة", labelEn: "Language", placeholder: "Arabic" },
            { key: "keyword_type", labelAr: "نوع الكلمات", labelEn: "Keyword type", placeholder: "SEO keywords" },
            { key: "search_intent", labelAr: "نية البحث", labelEn: "Search intent", placeholder: "Mixed" },
            { key: "location", labelAr: "الموقع", labelEn: "Location", placeholder: "Saudi Arabia" },
        ],
        booleanFields: [
            { key: "include_long_tail", labelAr: "تضمين Long-tail", labelEn: "Include long-tail" },
            { key: "include_clusters", labelAr: "تضمين المجموعات", labelEn: "Include clusters" },
        ],
    },
};

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar"
);

const labels = computed(() => isArabic.value ? {
    newChat: "محادثة جديدة",
    creating: "جاري الإنشاء...",
    recent: "المحادثات الأخيرة",
    loading: "جاري التحميل...",
    noChats: "لا توجد محادثات بعد",
    loadingConversation: "جاري تحميل المحادثة...",
    deleteChat: "حذف المحادثة",
    options: "خيارات الأداة",
    resultsCount: "عدد النتائج",
    defaultValue: "افتراضي",
    yes: "نعم",
    no: "لا",
    extraOptions: "خيارات إضافية",
    extraOptionsPlaceholder: "افصل الخيارات بفواصل",
    send: "إرسال",
    hint: "Enter للإرسال، و Shift + Enter لسطر جديد",
    copy: "نسخ",
    copied: "تم النسخ",
    copyAll: "نسخ الكل",
    regenerate: "إعادة التوليد",
    refine: "تعديل/تحسين",
    cancel: "إلغاء",
    genericError: "تعذر تنفيذ الطلب. حاول مرة أخرى.",
    authRequired: "يرجى تسجيل الدخول أولًا.",
} : {
    newChat: "New chat",
    creating: "Creating...",
    recent: "Recent chats",
    loading: "Loading...",
    noChats: "No conversations yet",
    loadingConversation: "Loading conversation...",
    deleteChat: "Delete conversation",
    options: "Tool options",
    resultsCount: "Results count",
    defaultValue: "Default",
    yes: "Yes",
    no: "No",
    extraOptions: "Extra options",
    extraOptionsPlaceholder: "Separate options with commas",
    send: "Send",
    hint: "Press Enter to send, Shift + Enter for a new line",
    copy: "Copy",
    copied: "Copied",
    copyAll: "Copy all",
    regenerate: "Regenerate",
    refine: "Edit/Refine",
    cancel: "Cancel",
    genericError: "The request could not be completed. Please try again.",
    authRequired: "Please sign in first.",
});

const activeTool = computed(() => CHAT3_TOOLS[KEYWORD_GENERATOR_SUB_TOOL_ID]);
const registryTools = computed(() => Object.values(CHAT3_TOOLS));
const toolState = ref(activeTool.value.getInitialState());
const subtool = ref({
    id: KEYWORD_GENERATOR_SUB_TOOL_ID,
    name: "",
    description: "",
    promptPlaceholder: "",
    toolKey: KEYWORD_GENERATOR_TOOL_KEY,
    modelKey: KEYWORD_GENERATOR_MODEL_KEY,
});
const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const userMessage = ref("");
const errorMessage = ref("");
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const sendingMessage = ref(false);
const streamingAssistant = ref(false);
const deletingUuid = ref("");
const sidebarOpen = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const eventSource = ref(null);
const copiedKey = ref("");
const refineMode = ref(false);

const markdown = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
});

const localizedToolTitle = (tool) => isArabic.value ? tool.title : tool.titleEn;
const pageTitle = computed(() => subtool.value.name || localizedToolTitle(activeTool.value));
const welcomeText = computed(() =>
    subtool.value.description
    || (isArabic.value
        ? "ولّد كلمات مفتاحية منظمة مع النوع، نية البحث، والمجموعات عندما تكون متاحة."
        : "Generate organized keyword ideas with type, search intent, and clusters when available.")
);
const examplePrompt = "Generate 20 SEO keywords for an article about AI tools for content creators in arabic";
const composerPlaceholder = computed(() =>
    refineMode.value
        ? (isArabic.value ? "مثال: زوّد long-tail وركز على السعودية" : "Example: add more long-tail keywords and focus on Saudi Arabia")
        : (subtool.value.promptPlaceholder || examplePrompt)
);
const sendDisabled = computed(() => sendingMessage.value || streamingAssistant.value);
const optionsSummary = computed(() => {
    const state = normalizeKeywordState(toolState.value);
    const count = Object.entries(state)
        .filter(([key, value]) => key !== "last_output" && key !== "extra_options" && value !== null && value !== "")
        .length + (Array.isArray(state.extra_options) ? state.extra_options.length : 0);

    return count ? String(count) : labels.value.defaultValue;
});
const extraOptionsText = computed({
    get: () => Array.isArray(toolState.value.extra_options) ? toolState.value.extra_options.join(", ") : "",
    set: (value) => {
        toolState.value.extra_options = String(value || "")
            .split(",")
            .map((item) => item.trim())
            .filter(Boolean);
    },
});

useSeoMeta({
    title: computed(() => `${pageTitle.value} | Ai Pro`),
    description: computed(() => welcomeText.value),
});

const formatMessage = (value = "") =>
    DOMPurify.sanitize(markdown.render(String(value || "")), { USE_PROFILES: { html: true } });

const createLocalKey = () => `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;
const createIdempotencyKey = () => {
    if (window?.crypto?.randomUUID) return window.crypto.randomUUID();

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === "x" ? random : (random & 0x3) | 0x8;
        return value.toString(16);
    });
};

const normalizeKeywordState = (state = {}) => {
    const base = activeTool.value.getInitialState();
    const source = isPlainObject(state) ? state : {};
    const normalized = { ...base };

    Object.keys(base).forEach((key) => {
        if (key === "extra_options") {
            normalized.extra_options = Array.isArray(source.extra_options)
                ? source.extra_options.map((item) => String(item || "").trim()).filter(Boolean)
                : [];
            return;
        }

        if (["include_long_tail", "include_clusters"].includes(key)) {
            normalized[key] = typeof source[key] === "boolean" ? source[key] : null;
            return;
        }

        if (key === "results_count") {
            const count = Number(source[key]);
            normalized[key] = Number.isFinite(count) && count > 0 ? Math.floor(count) : null;
            return;
        }

        normalized[key] = source[key] === undefined || source[key] === ""
            ? null
            : source[key];
    });

    return normalized;
};

const metadataFrom = (message = {}) => {
    if (isPlainObject(message.metadata)) return message.metadata;
    if (typeof message.metadata === "string") return safeJsonParse(message.metadata) || {};
    if (isPlainObject(message.toolMeta)) return message.toolMeta;
    return {};
};

const isKeywordMessage = (message = {}) => {
    const meta = metadataFrom(message);
    const tool = String(meta.tool_key || meta.tool || message.tool_key || message.tool || "").toLowerCase();

    return Number(meta.sub_tool_id || message.sub_tool_id || 0) === KEYWORD_GENERATOR_SUB_TOOL_ID
        || tool === KEYWORD_GENERATOR_TOOL_KEY;
};

const isKeywordResultMessage = (message = {}) =>
    message.role === "assistant"
    && !message.typing
    && isKeywordMessage(message)
    && Array.isArray(message.keywordResults)
    && message.keywordResults.length > 0;

const getAssistantOutput = (message = {}) => {
    const meta = metadataFrom(message);

    return String(
        meta.state?.last_output
        || message.responseState?.last_output
        || message.content
        || meta.results?.[0]?.text
        || ""
    ).trim();
};

const mapMessage = (message = {}, index = 0) => {
    const meta = metadataFrom(message);
    const keyword = message.role === "assistant" && isKeywordMessage(message);
    const responsePayload = {
        ...message,
        ...meta,
        state: meta.state || message.state,
        results: meta.normalized_results || meta.results || message.results,
    };
    const keywordResults = keyword
        ? (Array.isArray(meta.normalized_results) && meta.normalized_results.length
            ? meta.normalized_results
            : activeTool.value.normalizeResults(responsePayload))
        : [];

    return {
        ...message,
        localKey: message.localKey || `${message.role || "message"}-${message.id || index}-${message.created_at || createLocalKey()}`,
        role: message.role || "assistant",
        content: keyword && keywordResults.length ? "" : String(message.content || meta.message || ""),
        metadata: meta,
        responseState: isPlainObject(meta.state) ? normalizeKeywordState(meta.state) : null,
        keywordResults,
        is_error: Boolean(message.is_error || meta.success === false || meta.type === "error"),
        typing: Boolean(message.typing),
    };
};

const conversationTitle = (conversation = {}) => {
    const first = conversation.first_user_message_content
        || conversation.first_message_content
        || conversation.message?.find?.((item) => item.role === "user")?.content
        || "";
    const title = String(first).replace(/<[^>]*>/g, " ").trim().split(/\s+/).slice(0, 4).join(" ");

    return title || `${pageTitle.value} ${String(conversation.uuid || "").slice(-5)}`;
};

const formatConversation = (conversation = {}) => ({
    ...conversation,
    title: conversationTitle(conversation),
});

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const autoResize = () => {
    if (!textareaRef.value) return;

    textareaRef.value.style.height = "auto";
    textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 180)}px`;
};

const resetTextarea = () => {
    if (textareaRef.value) textareaRef.value.style.height = "auto";
};

const stateStorageKey = (uuid = activeConversation.value?.uuid || "new") =>
    `chat3-tool-state:${activeTool.value.id}:${uuid}`;

const persistState = (uuid = activeConversation.value?.uuid) => {
    if (!uuid) return;

    try {
        sessionStorage.setItem(stateStorageKey(uuid), JSON.stringify(normalizeKeywordState(toolState.value)));
    } catch {
        // Session storage is optional.
    }
};

const restoreState = (uuid) => {
    if (!uuid) {
        toolState.value = activeTool.value.getInitialState();
        return;
    }

    try {
        const stored = safeJsonParse(sessionStorage.getItem(stateStorageKey(uuid)) || "");
        toolState.value = normalizeKeywordState(stored || {});
    } catch {
        toolState.value = activeTool.value.getInitialState();
    }
};

const requireAuth = async () => {
    if (localStorage.getItem("auth_token")) return true;

    errorMessage.value = labels.value.authRequired;
    await router.push(`/${homeService.getLang()}/auth`);
    return false;
};

const loadSubtool = async () => {
    try {
        const response = await homeService.showSubtool(route.params.slug);
        const data = response?.data || {};
        const config = isPlainObject(data.config) ? data.config : safeJsonParse(data.config) || {};

        subtool.value = {
            id: Number(data.id || config.sub_tool_id || KEYWORD_GENERATOR_SUB_TOOL_ID),
            name: data.translation?.name || data.name || localizedToolTitle(activeTool.value),
            description: data.translation?.description || data.description || "",
            promptPlaceholder: data.translation?.prompt_placeholder || data.prompt_placeholder || "",
            toolKey: data.tool_key || config.tool_key || KEYWORD_GENERATOR_TOOL_KEY,
            modelKey: data.model_key || config.model_key || KEYWORD_GENERATOR_MODEL_KEY,
        };
    } catch {
        subtool.value = {
            id: KEYWORD_GENERATOR_SUB_TOOL_ID,
            name: localizedToolTitle(activeTool.value),
            description: "",
            promptPlaceholder: examplePrompt,
            toolKey: KEYWORD_GENERATOR_TOOL_KEY,
            modelKey: KEYWORD_GENERATOR_MODEL_KEY,
        };
    }
};

const loadConversations = async () => {
    if (!localStorage.getItem("auth_token")) {
        conversations.value = [];
        return;
    }

    loadingConversations.value = true;

    try {
        const response = await chatServices.getConversations();
        const rows = Array.isArray(response?.data) ? response.data : [];
        conversations.value = rows
            .filter((conversation) => Number(conversation.sub_tool_id) === activeTool.value.id)
            .map(formatConversation);
    } finally {
        loadingConversations.value = false;
    }
};

const hydrateStateFromMessages = (rows = []) => {
    const latest = [...rows]
        .reverse()
        .map((row, index) => mapMessage(row, index))
        .find((message) => message.role === "assistant" && message.responseState);

    if (latest?.responseState) {
        toolState.value = normalizeKeywordState(latest.responseState);
        persistState();
    }
};

const loadConversationDetails = async (uuid) => {
    if (!uuid || !localStorage.getItem("auth_token")) {
        activeConversation.value = null;
        messages.value = [];
        return;
    }

    loadingMessages.value = true;

    try {
        const response = await chatServices.getConversation(uuid);
        const conversation = response?.data || null;
        const rows = Array.isArray(conversation?.message) ? conversation.message : [];

        if (conversation) {
            activeConversation.value = formatConversation(conversation);
            const existingIndex = conversations.value.findIndex((item) => item.uuid === uuid);
            if (existingIndex >= 0) conversations.value[existingIndex] = activeConversation.value;
        }

        messages.value = rows.map(mapMessage);
        hydrateStateFromMessages(rows);
        await scrollToBottom();
    } finally {
        loadingMessages.value = false;
    }
};

const ensureConversation = async () => {
    if (!(await requireAuth())) return null;
    if (activeConversation.value?.uuid) return activeConversation.value;

    const response = await chatServices.createConversation(route.params.slug);
    const conversation = formatConversation(response?.data || {});

    activeConversation.value = conversation;
    conversations.value = [
        conversation,
        ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
    ];

    restoreState(conversation.uuid);
    await router.replace(`/${homeService.getLang()}/subtool/${route.params.slug}/chat3/${conversation.uuid}`);

    return conversation;
};

const closeStream = () => {
    if (eventSource.value) {
        eventSource.value.close();
        eventSource.value = null;
    }

    streamingAssistant.value = false;
};

const openAssistantStream = async (conversation, afterId) => {
    if (!conversation?.uuid) return;

    closeStream();
    streamingAssistant.value = true;

    const typingMessage = mapMessage({
        localKey: createLocalKey(),
        role: "assistant",
        content: "",
        typing: true,
        created_at: new Date().toISOString(),
    });

    messages.value.push(typingMessage);
    await scrollToBottom();

    const params = new URLSearchParams({
        after_id: String(afterId || 0),
        token: localStorage.getItem("auth_token") || "",
    });
    const source = new EventSource(`/api/v1/conversation/${conversation.uuid}/stream?${params.toString()}`);
    eventSource.value = source;

    source.onmessage = async (event) => {
        const payload = JSON.parse(event.data || "{}");
        const index = messages.value.findIndex((message) => message.localKey === typingMessage.localKey);

        if (payload.type === "token" && index >= 0) {
            await scrollToBottom();
        }

        if (payload.type === "error" && index >= 0) {
            messages.value[index].typing = false;
            messages.value[index].is_error = true;
            messages.value[index].content = payload.content || labels.value.genericError;
            closeStream();
        }

        if (payload.type === "done") {
            closeStream();
            await loadConversationDetails(conversation.uuid);
        }
    };

    source.onerror = async () => {
        closeStream();
        await loadConversationDetails(conversation.uuid);
    };
};

const buildPayload = (conversation, text, options = {}) => {
    const state = normalizeKeywordState(options.state || toolState.value);
    const payload = {
        user_id: Number(conversation.user_id) || null,
        sub_tool_id: activeTool.value.id,
        conversation_uuid: conversation.uuid,
        user_message: text,
        content: text,
        tool: activeTool.value.toolKey,
        tool_key: activeTool.value.toolKey,
        model_key: activeTool.value.modelKey,
        state,
        debug: false,
        idempotency_key: createIdempotencyKey(),
    };

    if (options.regenerate) {
        payload.regenerate = true;
        payload.previous_output = String(options.previousOutput || "");
    }

    return payload;
};

const submitKeywordRequest = async (text, options = {}) => {
    const cleanText = String(text || "").trim();
    if (!cleanText || sendDisabled.value) return;

    errorMessage.value = "";
    sendingMessage.value = true;

    try {
        const conversation = await ensureConversation();
        if (!conversation?.uuid) return;

        const payload = buildPayload(conversation, cleanText, options);
        const requestState = normalizeKeywordState(payload.state);

        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "user",
            content: cleanText,
            created_at: new Date().toISOString(),
        }));

        userMessage.value = "";
        refineMode.value = false;
        resetTextarea();
        toolState.value = requestState;
        persistState(conversation.uuid);
        await scrollToBottom();

        const response = await chatServices.sendMessage(payload);
        const directPayload = unwrapResponse(response);
        const directResults = activeTool.value.normalizeResults(directPayload);

        if (directResults.length && (directPayload.tool || directPayload.sub_tool_id || directPayload.state)) {
            const metadata = {
                type: directPayload.type || "result",
                tool: activeTool.value.toolKey,
                tool_key: activeTool.value.toolKey,
                model_key: activeTool.value.modelKey,
                sub_tool_id: activeTool.value.id,
                state: normalizeKeywordState(directPayload.state || requestState),
                request_payload: payload,
                request_id: directPayload.request_id || null,
                usage: directPayload.usage || null,
                cost: directPayload.cost || null,
                normalized_results: directResults,
            };

            messages.value.push(mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                content: "",
                metadata,
                created_at: new Date().toISOString(),
            }));
            await scrollToBottom();
            return;
        }

        await openAssistantStream(conversation, response?.data?.message_id || directPayload.message_id);
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || labels.value.genericError;
    } finally {
        sendingMessage.value = false;
    }
};

const sendKeywordMessage = async () => {
    await submitKeywordRequest(userMessage.value);
};

const startNewChat = async () => {
    if (creatingConversation.value || !(await requireAuth())) return;

    creatingConversation.value = true;
    errorMessage.value = "";

    try {
        const response = await chatServices.createConversation(route.params.slug);
        const conversation = formatConversation(response?.data || {});

        activeConversation.value = conversation;
        conversations.value = [
            conversation,
            ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
        ];
        messages.value = [];
        toolState.value = activeTool.value.getInitialState();
        sidebarOpen.value = false;

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat3/${conversation.uuid}`);
    } finally {
        creatingConversation.value = false;
    }
};

const openConversation = async (conversation) => {
    if (!conversation?.uuid) return;

    restoreState(conversation.uuid);

    if (route.params.uuid === conversation.uuid) {
        await loadConversationDetails(conversation.uuid);
        sidebarOpen.value = false;
        return;
    }

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat3/${conversation.uuid}`);
    sidebarOpen.value = false;
};

const deleteConversation = async (conversation) => {
    if (!conversation?.uuid || deletingUuid.value) return;

    deletingUuid.value = conversation.uuid;

    try {
        await chatServices.deleteConversation(conversation.uuid);
        conversations.value = conversations.value.filter((item) => item.uuid !== conversation.uuid);

        if (activeConversation.value?.uuid === conversation.uuid) {
            closeStream();
            activeConversation.value = null;
            messages.value = [];
            toolState.value = activeTool.value.getInitialState();
            await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat3`);
        }
    } finally {
        deletingUuid.value = "";
    }
};

const fillExample = () => {
    userMessage.value = examplePrompt;
    nextTick(() => {
        textareaRef.value?.focus();
        autoResize();
    });
};

const keywordCopyText = (item) => String(item?.text || item?.title || "").trim();

const copyKeyword = async ({ text, key }) => {
    await navigator.clipboard.writeText(text);
    copiedKey.value = key;
    window.setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = "";
    }, 1200);
};

const copyAllKeywords = async (message) => {
    const text = (message.keywordResults || [])
        .map(keywordCopyText)
        .filter(Boolean)
        .join("\n");

    if (!text) return;

    await copyKeyword({ text, key: `${message.localKey}:all` });
};

const findUserInputBeforeMessage = (message) => {
    const index = messages.value.findIndex((item) => item.localKey === message.localKey);
    const rows = index >= 0 ? messages.value.slice(0, index) : messages.value;

    return [...rows].reverse().find((item) => item.role === "user")?.content || "";
};

const regenerateKeywordResult = async (message) => {
    const meta = metadataFrom(message);
    const oldPayload = isPlainObject(meta.request_payload) ? meta.request_payload : {};
    const text = String(oldPayload.user_message || findUserInputBeforeMessage(message) || "").trim();
    if (!text) return;

    await submitKeywordRequest(text, {
        regenerate: true,
        previousOutput: getAssistantOutput(message),
        state: meta.state || message.responseState || toolState.value,
    });
};

const startRefine = async (message) => {
    if (message?.responseState) {
        toolState.value = normalizeKeywordState(message.responseState);
        persistState();
    }

    refineMode.value = true;
    userMessage.value = "";
    await nextTick();
    textareaRef.value?.focus();
};

const initialize = async () => {
    locale.value = homeService.getLang();

    await loadSubtool();
    restoreState(route.params.uuid);
    await loadConversations();

    if (route.params.uuid) {
        await loadConversationDetails(route.params.uuid);
    }
};

const handleLanguageChange = async () => {
    locale.value = homeService.getLang();
    await loadSubtool();
};

onMounted(async () => {
    await initialize();
    window.addEventListener("lang-changed", handleLanguageChange);
});

onUnmounted(() => {
    closeStream();
    window.removeEventListener("lang-changed", handleLanguageChange);
});

watch(
    () => route.params.uuid,
    async (uuid) => {
        closeStream();
        restoreState(uuid);

        if (uuid) {
            await loadConversationDetails(uuid);
        } else {
            activeConversation.value = null;
            messages.value = [];
        }
    }
);

watch(
    () => route.params.slug,
    async () => {
        closeStream();
        await loadSubtool();
        await loadConversations();
    }
);

const KeywordResult = defineComponent({
    name: "KeywordResult",
    props: {
        message: { type: Object, required: true },
        labels: { type: Object, required: true },
        copiedKey: { type: String, default: "" },
    },
    emits: ["copy-keyword", "copy-all", "regenerate", "refine"],
    setup(props, { emit }) {
        const tag = (value) => value ? h("span", { class: "keyword-tag" }, value) : null;
        const copyText = (item) => String(item?.text || item?.title || "").trim();

        return () => h("div", { class: "keyword-result" }, [
            h("div", { class: "keyword-toolbar" }, [
                h("strong", {}, props.labels.resultsCount),
                h("div", { class: "keyword-actions" }, [
                    h("button", {
                        type: "button",
                        onClick: () => emit("copy-all", props.message),
                    }, [
                        h("i", { class: "bi bi-copy" }),
                        props.copiedKey === `${props.message.localKey}:all` ? props.labels.copied : props.labels.copyAll,
                    ]),
                    h("button", {
                        type: "button",
                        onClick: () => emit("regenerate", props.message),
                    }, [
                        h("i", { class: "bi bi-arrow-clockwise" }),
                        props.labels.regenerate,
                    ]),
                    h("button", {
                        type: "button",
                        onClick: () => emit("refine", props.message),
                    }, [
                        h("i", { class: "bi bi-pencil-square" }),
                        props.labels.refine,
                    ]),
                ]),
            ]),
            h("div", { class: "keyword-grid" }, props.message.keywordResults.map((item, index) => {
                const key = `${props.message.localKey}:${item.id || index}`;
                const tags = [
                    tag(item.meta?.type),
                    tag(item.meta?.intent),
                    tag(item.meta?.cluster),
                ].filter(Boolean);

                return h("section", { class: "keyword-card", key }, [
                    h("div", { class: "keyword-card-head" }, [
                        h("span", { class: "keyword-index" }, String(item.id || index + 1)),
                        h("button", {
                            type: "button",
                            onClick: () => emit("copy-keyword", { text: copyText(item), key }),
                        }, [
                            h("i", { class: "bi bi-copy" }),
                            props.copiedKey === key ? props.labels.copied : props.labels.copy,
                        ]),
                    ]),
                    h("strong", { class: "keyword-text" }, item.text || item.title),
                    item.subject ? h("small", { class: "keyword-subject" }, item.subject) : null,
                    tags.length ? h("div", { class: "keyword-tags" }, tags) : null,
                ]);
            })),
        ]);
    },
});
</script>

<style scoped>
.chat3-root {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 320px 1fr;
    background: #f6f8fb;
    color: #111827;
}

.chat3-sidebar {
    background: #ffffff;
    border-inline-end: 1px solid #e5e7eb;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    min-height: 100vh;
}

.sidebar-brand,
.workspace-header,
.result-header,
.keyword-toolbar,
.keyword-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.sidebar-brand {
    align-items: flex-start;
}

.sidebar-brand strong,
.workspace-header h1 {
    color: #0f172a;
}

.sidebar-brand small,
.eyebrow,
.section-label,
.composer-hint,
.keyword-subject {
    color: #64748b;
}

.brand-icon,
.welcome-icon,
.avatar {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ecfdf5;
    color: #047857;
    flex: 0 0 auto;
}

.icon-button,
.conversation-delete,
.send-button,
.keyword-actions button,
.keyword-card-head button {
    border: 0;
    cursor: pointer;
}

.icon-button {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #0f172a;
}

.mobile-only {
    display: none;
}

.new-chat-button,
.tool-option,
.suggestion {
    width: 100%;
    min-height: 44px;
    border: 0;
    border-radius: 10px;
    background: #0f172a;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 700;
    cursor: pointer;
}

.new-chat-button:disabled,
.send-button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.tool-switcher {
    display: grid;
    gap: 8px;
}

.tool-option {
    justify-content: flex-start;
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.tool-option.active {
    background: #ecfdf5;
    color: #047857;
    border-color: #bbf7d0;
}

.section-label {
    margin: 0;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.sidebar-status {
    padding: 14px;
    border-radius: 10px;
    background: #f8fafc;
    color: #64748b;
}

.conversation-list {
    display: grid;
    gap: 8px;
    overflow-y: auto;
}

.conversation-item {
    display: grid;
    grid-template-columns: 1fr 38px;
    align-items: center;
    gap: 6px;
    border-radius: 10px;
    padding: 6px;
    background: #f8fafc;
}

.conversation-item.active {
    background: #e0f2fe;
}

.conversation-open {
    border: 0;
    background: transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: #334155;
    cursor: pointer;
}

.conversation-open span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-delete {
    height: 34px;
    border-radius: 9px;
    background: #fee2e2;
    color: #b91c1c;
}

.workspace {
    min-width: 0;
    display: grid;
    grid-template-rows: auto 1fr auto;
    height: 100vh;
}

.workspace-header {
    padding: 18px 24px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

.workspace-header h1,
.eyebrow {
    margin: 0;
}

.eyebrow {
    font-size: 12px;
    font-weight: 800;
}

.tool-badges {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tool-badges span,
.keyword-tag {
    border-radius: 999px;
    background: #e0f2fe;
    color: #0369a1;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
}

.messages {
    overflow-y: auto;
    padding: 24px;
}

.center-status,
.welcome-card {
    max-width: 720px;
    margin: 40px auto;
    text-align: center;
}

.center-status {
    color: #64748b;
}

.spinner {
    width: 18px;
    height: 18px;
    border: 2px solid #cbd5e1;
    border-top-color: #047857;
    border-radius: 50%;
    display: inline-block;
    animation: spin 0.8s linear infinite;
}

.welcome-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 16px 50px rgba(15, 23, 42, 0.08);
}

.suggestion {
    margin-top: 12px;
    background: #ecfdf5;
    color: #047857;
}

.message-list {
    display: grid;
    gap: 18px;
}

.message-row {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 12px;
    align-items: flex-start;
}

.message-row.user {
    grid-template-columns: minmax(0, 1fr) 42px;
}

.message-row.user .avatar {
    grid-column: 2;
    background: #eff6ff;
    color: #2563eb;
}

.message-row.user .message-body {
    grid-column: 1;
    grid-row: 1;
    justify-self: end;
    background: #0f172a;
    color: #ffffff;
}

.message-body {
    max-width: min(860px, 100%);
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 12px 36px rgba(15, 23, 42, 0.07);
}

.message-body.error {
    border-color: #fecaca;
    background: #fff1f2;
}

.message-content :deep(p) {
    margin: 0 0 10px;
}

.typing {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px;
}

.typing span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #047857;
    animation: typing 0.9s infinite ease-in-out;
}

.typing span:nth-child(2) {
    animation-delay: 0.15s;
}

.typing span:nth-child(3) {
    animation-delay: 0.3s;
}

.keyword-result {
    display: grid;
    gap: 14px;
}

.keyword-toolbar {
    align-items: flex-start;
}

.keyword-actions {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.keyword-actions button,
.keyword-card-head button,
.refine-banner button {
    min-height: 34px;
    border-radius: 9px;
    background: #f1f5f9;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 10px;
    font-weight: 700;
}

.keyword-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 12px;
}

.keyword-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    background: #f8fafc;
    display: grid;
    gap: 10px;
}

.keyword-index {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #047857;
    color: #ffffff;
    font-weight: 800;
}

.keyword-text {
    color: #0f172a;
    line-height: 1.5;
}

.keyword-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.composer {
    padding: 18px 24px;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
}

.error-banner,
.refine-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    border-radius: 12px;
    padding: 10px 12px;
}

.error-banner {
    background: #fff1f2;
    color: #b91c1c;
}

.refine-banner {
    background: #ecfdf5;
    color: #047857;
}

.options-panel {
    margin-bottom: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #f8fafc;
}

.options-panel summary {
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    font-weight: 800;
}

.options-summary {
    color: #64748b;
}

.options-grid {
    padding: 0 14px 14px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.options-grid label {
    display: grid;
    gap: 6px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.options-grid input,
.options-grid select,
.input-box textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 10px 12px;
    outline: none;
    background: #ffffff;
    color: #0f172a;
}

.options-grid .wide {
    grid-column: 1 / -1;
}

.input-box {
    display: grid;
    grid-template-columns: 1fr 48px;
    gap: 10px;
    align-items: end;
}

.input-box textarea {
    resize: none;
    min-height: 48px;
    max-height: 180px;
}

.send-button {
    height: 48px;
    border-radius: 12px;
    background: #047857;
    color: #ffffff;
    font-size: 18px;
}

.composer-hint {
    margin: 8px 0 0;
    font-size: 12px;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes typing {
    0%, 80%, 100% {
        transform: translateY(0);
        opacity: 0.45;
    }

    40% {
        transform: translateY(-5px);
        opacity: 1;
    }
}

@media (max-width: 960px) {
    .chat3-root {
        grid-template-columns: 1fr;
    }

    .chat3-sidebar {
        position: fixed;
        inset-block: 0;
        width: min(86vw, 340px);
        z-index: 50;
        transform: translateX(-110%);
        transition: transform 0.24s ease;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
    }

    [dir="rtl"] .chat3-sidebar {
        right: 0;
        left: auto;
        transform: translateX(110%);
    }

    [dir="ltr"] .chat3-sidebar {
        left: 0;
        right: auto;
    }

    .chat3-sidebar.open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        inset: 0;
        z-index: 40;
        background: rgba(15, 23, 42, 0.45);
        border: 0;
    }

    .mobile-only {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .workspace {
        height: 100vh;
    }

    .tool-badges {
        display: none;
    }

    .options-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .workspace-header,
    .messages,
    .composer {
        padding-inline: 14px;
    }

    .workspace-header h1 {
        font-size: 20px;
    }

    .message-row,
    .message-row.user {
        grid-template-columns: 1fr;
    }

    .message-row .avatar {
        display: none;
    }

    .message-row.user .message-body {
        grid-column: auto;
    }

    .keyword-toolbar {
        display: grid;
    }

    .keyword-actions {
        justify-content: stretch;
    }

    .keyword-actions button {
        flex: 1 1 auto;
        justify-content: center;
    }
}
</style>
