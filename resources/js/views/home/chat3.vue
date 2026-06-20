<template>
    <main class="prompt-chat" :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ open: sidebarOpen }">
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
            <header class="workspace-header">
                <button class="icon-button mobile-only" type="button" @click="sidebarOpen = true">
                    <i class="bi bi-list"></i>
                </button>

                <div>
                    <p class="eyebrow">{{ activeTool.toolKey }}</p>
                    <h1>{{ pageTitle }}</h1>
                </div>

                <div class="tool-badges">
                    <span>{{ activeTool.toolKey }}</span>
                    <span>{{ activeTool.modelKey }}</span>
                </div>
            </header>

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
                                <KeywordGeneratorResult
                                    v-if="isKeywordResultMessage(message)"
                                    :message="message"
                                    :results="getKeywordResults(message)"
                                    :labels="labels"
                                    :copied-key="copiedKey"
                                    @copy-keyword="copyKeyword"
                                    @copy-all="copyAllKeywords"
                                    @regenerate="regenerateKeywordResult"
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
    const text = String(row.text || row.title || row.subject || row.keyword || row.name || "").trim();
    const title = String(row.title || row.keyword || "").trim();

    if (!text) return null;

    return {
        id: row.id || index + 1,
        title,
        subject: String(row.subject || "").trim(),
        text,
        type: String(meta.type || row.type || "").trim(),
        intent: String(meta.intent || row.intent || "").trim(),
        cluster: String(meta.cluster || row.cluster || "").trim(),
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

const pushKeywordCandidate = (candidates, value) => {
    if (value === null || value === undefined || value === "") return;

    candidates.push(value);

    if (typeof value === "string") {
        const parsed = safeJsonParse(value);
        if (parsed) candidates.push(parsed);
    }
};

const normalizeKeywordGeneratorResults = (source = {}) => {
    const response = unwrapResponse(source);
    const candidates = [];
    const meta = metadataFrom(response);

    pushKeywordCandidate(candidates, meta.normalized_results);
    pushKeywordCandidate(candidates, response.normalized_results);
    pushKeywordCandidate(candidates, meta.state?.last_output);
    pushKeywordCandidate(candidates, response.state?.last_output);
    pushKeywordCandidate(candidates, meta.results?.[0]?.text);
    pushKeywordCandidate(candidates, response.results?.[0]?.text);
    pushKeywordCandidate(candidates, meta.results);
    pushKeywordCandidate(candidates, response.results);
    pushKeywordCandidate(candidates, response.text);
    pushKeywordCandidate(candidates, response.content);
    pushKeywordCandidate(candidates, response.reply);
    pushKeywordCandidate(candidates, meta.text);
    pushKeywordCandidate(candidates, meta.content);
    pushKeywordCandidate(candidates, meta.reply);
    pushKeywordCandidate(candidates, response);

    for (const candidate of candidates) {
        if (!candidate) continue;

        if (Array.isArray(candidate)) {
            const nestedJson = candidate.length === 1
                ? safeJsonParse(
                    typeof candidate[0] === "string"
                        ? candidate[0]
                        : (typeof candidate[0]?.text === "string" ? candidate[0].text : "")
                )
                : null;

            if (Array.isArray(nestedJson?.results)) {
                const normalized = normalizeKeywordItems(nestedJson.results);
                if (normalized.length) return normalized;
            }

            const normalized = normalizeKeywordItems(candidate);
            if (normalized.length) return normalized;
        }

        if (isPlainObject(candidate)) {
            if (Array.isArray(candidate.normalized_results)) {
                const normalized = normalizeKeywordItems(candidate.normalized_results);
                if (normalized.length) return normalized;
            }

            if (Array.isArray(candidate.results)) {
                const normalized = normalizeKeywordItems(candidate.results);
                if (normalized.length) return normalized;
            }

            if (typeof candidate.text === "string") {
                const parsedText = safeJsonParse(candidate.text);
                if (Array.isArray(parsedText?.results)) {
                    const normalized = normalizeKeywordItems(parsedText.results);
                    if (normalized.length) return normalized;
                }
            }
        }
    }

    return [];
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
    keywordResultsTitle: "الكلمات المفتاحية المقترحة",
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
    keywordResultsTitle: "Suggested keywords",
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

const keywordRequestState = (state = {}) => ({
    ...normalizeKeywordState(state),
    last_output: null,
});

const metadataFrom = (message = {}) => {
    if (isPlainObject(message.metadata)) return message.metadata;
    if (typeof message.metadata === "string") return safeJsonParse(message.metadata) || {};
    if (isPlainObject(message.toolMeta)) return message.toolMeta;
    return {};
};

const isKeywordGeneratorMessage = (message = {}) => {
    const meta = metadataFrom(message);
    const tool = String(meta.tool_key || meta.tool || message.tool_key || message.tool || "").toLowerCase();
    const modelKey = String(meta.model_key || message.model_key || "").toLowerCase();
    const subToolId = Number(
        message.sub_tool_id
        || message.subToolId
        || meta.sub_tool_id
        || meta.subToolId
        || subtool.value.id
        || 0
    );

    return subToolId === KEYWORD_GENERATOR_SUB_TOOL_ID
        || tool === KEYWORD_GENERATOR_TOOL_KEY
        || modelKey === KEYWORD_GENERATOR_MODEL_KEY;
};

const isKeywordMessage = isKeywordGeneratorMessage;

const isKeywordResultMessage = (message = {}) =>
    message.role === "assistant"
    && !message.typing
    && isKeywordGeneratorMessage(message)
    && getKeywordResults(message).length > 0;

const getKeywordResults = (message = {}) =>
    Array.isArray(message.keywordResults) && message.keywordResults.length
        ? message.keywordResults
        : normalizeKeywordGeneratorResults(message);

const looksLikeJsonText = (value = "") => {
    const text = String(value || "").trim();
    return text.startsWith("{") || text.startsWith("[");
};

const keywordFallbackContent = () =>
    isArabic.value
        ? "تم استلام نتيجة مولد الكلمات المفتاحية، لكن تعذر تنظيمها للعرض. حاول إعادة التوليد."
        : "Keyword results were received, but could not be formatted. Please try regenerating.";

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

const compactKeywordPreviousOutput = (message = {}) => {
    const resultText = getKeywordResults(message)
        .map((item) => String(item?.text || "").trim())
        .filter(Boolean)
        .join("\n");

    if (resultText) {
        return resultText.slice(0, 3000);
    }

    const output = getAssistantOutput(message);
    if (!output) return "";

    const parsed = safeJsonParse(
        output
            .replace(/^```(?:json)?\s*/i, "")
            .replace(/\s*```$/i, "")
            .trim()
    );
    const parsedResults = parsed ? normalizeKeywordGeneratorResults(parsed) : [];

    if (parsedResults.length) {
        return parsedResults
            .map((item) => String(item?.text || "").trim())
            .filter(Boolean)
            .join("\n")
            .slice(0, 3000);
    }

    if (looksLikeJsonText(output)) {
        return "";
    }

    return output
        .replace(/`+/g, "")
        .replace(/\s+/g, " ")
        .trim()
        .slice(0, 3000);
};

const mapMessage = (message = {}, index = 0) => {
    const meta = metadataFrom(message);
    const keyword = message.role === "assistant" && isKeywordGeneratorMessage(message);
    const responsePayload = {
        ...message,
        ...meta,
        state: meta.state || message.state,
        results: meta.normalized_results || meta.results || message.results,
    };
    const keywordResults = keyword ? activeTool.value.normalizeResults(responsePayload) : [];
    const rawContent = String(message.content || meta.message || "");

    return {
        ...message,
        localKey: message.localKey || `${message.role || "message"}-${message.id || index}-${message.created_at || createLocalKey()}`,
        role: message.role || "assistant",
        content: keyword
            ? (keywordResults.length ? "" : (looksLikeJsonText(rawContent) ? keywordFallbackContent() : rawContent))
            : rawContent,
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
    const state = keywordRequestState(options.state || toolState.value);
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
        payload.previous_output = String(options.previousOutput || "").slice(0, 3000);
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
        const requestState = keywordRequestState(payload.state);

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

const keywordCopyText = (item) => String(item?.text || "").trim();

const copyKeyword = async ({ text, key }) => {
    await navigator.clipboard.writeText(text);
    copiedKey.value = key;
    window.setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = "";
    }, 1200);
};

const copyAllKeywords = async (message) => {
    const text = getKeywordResults(message)
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
        previousOutput: compactKeywordPreviousOutput(message),
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

const KeywordGeneratorResult = defineComponent({
    name: "KeywordGeneratorResult",
    props: {
        message: { type: Object, required: true },
        results: { type: Array, default: () => [] },
        labels: { type: Object, required: true },
        copiedKey: { type: String, default: "" },
    },
    emits: ["copy-keyword", "copy-all", "regenerate"],
    setup(props, { emit }) {
        const tag = (value) => value ? h("span", { class: "keyword-chip" }, value) : null;
        const copyText = (item) => String(item?.text || "").trim();

        return () => h("div", { class: "keyword-results-card keyword-ai-response-card" }, [
            h("div", { class: "keyword-result-topbar" }, [
                h("div", { class: "keyword-results-title" }, [
                    h("strong", {}, props.labels.keywordResultsTitle || "Suggested keywords"),
                    h("small", {}, `${props.results.length} ${props.labels.resultsCount}`),
                ]),
                h("button", {
                    class: "keyword-copy-all-btn",
                    type: "button",
                    onClick: () => emit("copy-all", props.message),
                }, [
                    h("i", { class: props.copiedKey === `${props.message.localKey}:all` ? "bi bi-check2" : "bi bi-copy" }),
                    props.copiedKey === `${props.message.localKey}:all` ? props.labels.copied : props.labels.copyAll,
                ]),
            ]),

            h("div", { class: "keyword-results-frame" }, [
                h("div", { class: "keyword-results-list" }, props.results.map((item, index) => {
                    const key = `${props.message.localKey}:${item.id || index}`;
                    const tags = [
                        tag(item.type || item.meta?.type),
                        tag(item.intent || item.meta?.intent),
                        tag(item.cluster || item.meta?.cluster),
                    ].filter(Boolean);

                    return h("section", { class: "keyword-result-item", key }, [
                        h("div", { class: "keyword-result-main" }, [
                            h("span", { class: "keyword-index" }, String(item.id || index + 1)),
                            h("div", { class: "keyword-result-copy" }, [
                                h("strong", { class: "keyword-text" }, item.text || item.title),
                                item.subject ? h("small", { class: "keyword-subject" }, item.subject) : null,
                            ]),
                            h("button", {
                                class: "keyword-copy-btn",
                                type: "button",
                                title: props.copiedKey === key ? props.labels.copied : props.labels.copy,
                                "aria-label": props.copiedKey === key ? props.labels.copied : props.labels.copy,
                                onClick: () => emit("copy-keyword", { text: copyText(item), key }),
                            }, [
                                h("i", { class: props.copiedKey === key ? "bi bi-check2" : "bi bi-copy" }),
                            ]),
                        ]),
                        tags.length ? h("div", { class: "keyword-result-meta" }, tags) : null,
                    ]);
                })),
            ]),

            h("div", { class: "keyword-regenerate-wrap" }, [
                h("button", {
                    class: "keyword-regenerate-btn",
                    type: "button",
                    onClick: () => emit("regenerate", props.message),
                }, [
                    h("i", { class: "bi bi-arrow-clockwise" }),
                    props.labels.regenerate,
                ]),
            ]),
        ]);
    },
});
</script>

<style scoped>
.prompt-chat {
    --navy: #123f6d;
    --blue: #1f87c9;
    --cyan: #39bce8;
    --ink: #15324b;
    --muted: #687b8e;
    --line: #dce8f1;
    min-height: calc(100vh - 70px);
    display: grid;
    grid-template-columns: 290px minmax(0, 1fr);
    background: #f4f8fb;
    color: var(--ink);
}

button,
input,
select,
textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.58;
}

.sidebar {
    position: relative;
    z-index: 20;
    display: flex;
    flex-direction: column;
    min-height: 0;
    padding: 24px 18px;
    border-inline-end: 1px solid var(--line);
    background: #fff;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}

.sidebar-brand strong,
.sidebar-brand small {
    display: block;
}

.sidebar-brand small {
    margin-top: 2px;
    color: var(--muted);
    font-size: 12px;
}

.brand-icon,
.welcome-icon {
    display: grid;
    place-items: center;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--cyan));
    box-shadow: 0 10px 25px rgba(31, 135, 201, 0.2);
}

.brand-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 13px;
}

.new-chat-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 11px 14px;
    border: 0;
    border-radius: 12px;
    color: #fff;
    background: var(--navy);
}

.section-label {
    margin: 24px 8px 10px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.conversation-list {
    display: grid;
    gap: 6px;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    align-items: center;
    border-radius: 11px;
}

.conversation-item:hover,
.conversation-item.active {
    background: #eef7fc;
}

.conversation-open {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
    padding: 10px;
    border: 0;
    color: var(--ink);
    background: transparent;
    text-align: start;
}

.conversation-open span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-delete,
.icon-button {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 10px;
    color: var(--muted);
    background: transparent;
}

.conversation-delete:hover,
.icon-button:hover {
    color: var(--navy);
    background: #edf5fa;
}

.sidebar-status,
.center-status {
    padding: 22px 8px;
    color: var(--muted);
    text-align: center;
}

.workspace {
    min-width: 0;
    min-height: 0;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    height: calc(100vh - 70px);
}

.workspace-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 28px;
    border-bottom: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
}

.workspace-header h1 {
    margin: 2px 0 0;
    font-size: 19px;
}

.eyebrow {
    margin: 0;
    color: var(--blue);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.tool-badges {
    display: flex;
    gap: 7px;
    margin-inline-start: auto;
}

.tool-badges span,
.response-meta span {
    padding: 5px 9px;
    border: 1px solid #d6e9f4;
    border-radius: 999px;
    color: #357192;
    background: #f2faff;
    font-size: 11px;
}

.messages {
    overflow-y: auto;
    padding: 34px max(24px, calc((100% - 900px) / 2));
}

.welcome-card {
    max-width: 620px;
    margin: 10vh auto 0;
    padding: 38px;
    border: 1px solid var(--line);
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 18px 55px rgba(18, 63, 109, 0.08);
    text-align: center;
}

.welcome-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 18px;
    border-radius: 20px;
    font-size: 24px;
}

.welcome-card h2 {
    margin: 0 0 10px;
}

.welcome-card p {
    margin: 0;
    color: var(--muted);
    line-height: 1.7;
}

.suggestion {
    margin-top: 20px;
    padding: 9px 14px;
    border: 1px solid #cfe7f4;
    border-radius: 999px;
    color: var(--navy);
    background: #f3faff;
}

.message-list {
    display: grid;
    gap: 24px;
}

.message-row {
    display: flex;
    align-items: flex-start;
    gap: 11px;
}

.message-row.user {
    flex-direction: row-reverse;
}

.avatar {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    border-radius: 11px;
    color: #fff;
    background: var(--navy);
}

.user .avatar {
    background: #718397;
}

.message-body {
    max-width: min(780px, 85%);
    padding: 14px 16px;
    border: 1px solid var(--line);
    border-radius: 6px 18px 18px 18px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(18, 63, 109, 0.05);
}

.user .message-body {
    border: 0;
    border-radius: 18px 6px 18px 18px;
    color: #fff;
    background: var(--navy);
}

.message-body.error {
    border-color: #f2b7b7;
    color: #8b2525;
    background: #fff5f5;
}

.message-content :deep(p),
.result-text :deep(p) {
    margin: 0 0 0.75em;
    line-height: 1.75;
}

.message-content :deep(p:last-child),
.result-text :deep(p:last-child) {
    margin-bottom: 0;
}

.result-list {
    display: grid;
    gap: 12px;
    min-width: min(680px, 68vw);
}

.message-content + .result-list {
    margin-top: 14px;
}

.result-card {
    overflow: hidden;
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    background: #fbfdff;
}

.result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border-bottom: 1px solid #e2eef5;
    color: var(--navy);
    background: #f0f8fc;
}

.result-header button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border: 0;
    border-radius: 8px;
    color: var(--blue);
    background: #fff;
}

.result-text {
    padding: 15px;
    color: var(--ink);
}

.result-subject {
    display: inline-block;
    margin: 12px 15px 0;
    padding: 4px 8px;
    border-radius: 999px;
    color: var(--blue);
    background: #eaf6fc;
}

.response-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}

.typing {
    display: flex;
    gap: 5px;
    padding: 5px;
}

.typing span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--blue);
    animation: pulse 1s infinite alternate;
}

.typing span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing span:nth-child(3) {
    animation-delay: 0.4s;
}

.composer {
    padding: 14px max(24px, calc((100% - 900px) / 2)) 18px;
    border-top: 1px solid var(--line);
    background: #fff;
}

.options-panel {
    margin-bottom: 10px;
    border: 1px solid var(--line);
    border-radius: 13px;
    background: #f9fcfe;
}

.options-panel summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    color: var(--navy);
    cursor: pointer;
    list-style: none;
}

.options-panel summary::-webkit-details-marker {
    display: none;
}

.options-summary {
    color: var(--muted);
    font-size: 12px;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    padding: 4px 13px 14px;
}

.options-grid label {
    display: grid;
    gap: 5px;
    color: var(--muted);
    font-size: 12px;
}

.options-grid input,
.options-grid select {
    width: 100%;
    min-width: 0;
    padding: 8px 9px;
    border: 1px solid #d5e3ec;
    border-radius: 9px;
    color: var(--ink);
    background: #fff;
    outline: none;
}

.options-grid input:focus,
.options-grid select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.1);
}

.options-grid .wide {
    grid-column: span 2;
}

.input-box {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 9px;
    border: 1px solid #cadce7;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(18, 63, 109, 0.08);
}

.input-box:focus-within {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.1);
}

.input-box textarea {
    min-height: 42px;
    max-height: 180px;
    flex: 1;
    resize: none;
    padding: 10px;
    border: 0;
    color: var(--ink);
    background: transparent;
    outline: 0;
}

.send-button {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    border: 0;
    border-radius: 13px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.composer-hint {
    margin: 7px 4px 0;
    color: var(--muted);
    font-size: 11px;
    text-align: center;
}

.error-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 9px;
    padding: 9px 12px;
    border: 1px solid #f1b9b9;
    border-radius: 10px;
    color: #8b2525;
    background: #fff4f4;
}

.mobile-only,
.sidebar-overlay {
    display: none;
}

@keyframes pulse {
    to {
        opacity: 0.25;
        transform: translateY(-3px);
    }
}

@media (max-width: 900px) {
    .prompt-chat {
        grid-template-columns: 1fr;
    }

    .workspace {
        height: calc(100vh - 64px);
    }

    .sidebar {
        position: fixed;
        inset-block: 0;
        inset-inline-start: 0;
        width: min(310px, 86vw);
        transform: translateX(-110%);
        transition: transform 0.2s ease;
    }

    [dir="rtl"] .sidebar {
        transform: translateX(110%);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        z-index: 15;
        inset: 0;
        display: block;
        border: 0;
        background: rgba(9, 31, 51, 0.38);
    }

    .mobile-only {
        display: grid;
    }

    .sidebar-brand .mobile-only {
        margin-inline-start: auto;
    }

    .tool-badges {
        display: none;
    }

    .options-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .result-list {
        min-width: 0;
    }
}

@media (max-width: 560px) {
    .workspace-header {
        padding: 14px 16px;
    }

    .messages,
    .composer {
        padding-inline: 13px;
    }

    .message-body {
        max-width: calc(100% - 45px);
    }

    .options-grid {
        grid-template-columns: 1fr;
    }

    .options-grid .wide {
        grid-column: auto;
    }

    .welcome-card {
        padding: 28px 20px;
    }
}

/* Chat3 keyword-generator additions, layered on top of chat2 visual system. */
.tool-switcher {
    display: grid;
    gap: 6px;
}

.tool-option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px;
    border: 0;
    border-radius: 11px;
    color: var(--ink);
    background: transparent;
    text-align: start;
}

.tool-option:hover,
.tool-option.active {
    color: var(--navy);
    background: #eef7fc;
}

.keyword-results-card {
    display: grid;
    gap: 14px;
    width: min(720px, 100%);
    min-width: 0;
}

.keyword-results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    color: var(--navy);
    background: #f0f8fc;
}

.keyword-results-title {
    min-width: 0;
}

.keyword-results-header strong,
.keyword-results-header small {
    display: block;
}

.keyword-results-header small {
    margin-top: 2px;
    color: var(--muted);
    font-size: 12px;
}

.keyword-results-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.keyword-result-item {
    overflow: hidden;
    min-width: 0;
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    background: #fbfdff;
}

.keyword-result-main {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) 34px;
    align-items: start;
    gap: 12px;
    min-width: 0;
    padding: 12px 13px;
}

.keyword-result-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 59px 13px;
}

.keyword-chip {
    max-width: 100%;
    overflow-wrap: anywhere;
    padding: 4px 8px;
    border: 1px solid #d6e9f4;
    border-radius: 999px;
    color: #357192;
    background: #f2faff;
    font-size: 11px;
}

.keyword-copy-btn {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    padding: 0;
    border: 1px solid #d6e9f4;
    border-radius: 10px;
    color: var(--blue);
    background: #fff;
}

.keyword-result {
    min-width: min(680px, 68vw);
}

.keyword-toolbar {
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    color: var(--navy);
    background: #f0f8fc;
}

.keyword-actions {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 7px;
    justify-content: flex-end;
    min-width: 0;
}

.keyword-actions button,
.refine-banner button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border: 0;
    border-radius: 8px;
    color: var(--blue);
    background: #fff;
}

.keyword-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.keyword-card {
    overflow: hidden;
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    background: #fbfdff;
}

.keyword-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border-bottom: 1px solid #e2eef5;
    background: #f0f8fc;
}

.keyword-index {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 9px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
    font-weight: 800;
}

.keyword-result-copy {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.keyword-text {
    display: block;
    color: var(--ink);
    line-height: 1.7;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.keyword-subject {
    display: block;
    color: var(--muted);
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.keyword-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 12px 15px 15px;
}

.keyword-tag {
    padding: 4px 8px;
    border: 1px solid #d6e9f4;
    border-radius: 999px;
    color: #357192;
    background: #f2faff;
    font-size: 11px;
}

.refine-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 9px;
    padding: 9px 12px;
    border: 1px solid #cfe7f4;
    border-radius: 10px;
    color: var(--navy);
    background: #f3faff;
}

.refine-banner button {
    margin-inline-start: auto;
}

@media (max-width: 900px) {
    .keyword-results-card {
        width: 100%;
    }

    .keyword-result {
        min-width: 0;
    }
}

@media (max-width: 560px) {
    .keyword-results-header {
        display: grid;
        gap: 10px;
    }

    .keyword-result-main {
        grid-template-columns: 30px minmax(0, 1fr) 32px;
        gap: 9px;
        padding: 11px;
    }

    .keyword-result-meta {
        padding-inline: 50px 11px;
    }

    .keyword-actions {
        justify-content: stretch;
    }

    .keyword-actions button {
        flex: 1 1 auto;
        justify-content: center;
    }
}

/* Final keyword-generator AI response card layout */
.keyword-ai-response-card {
    width: min(760px, 100%);
    min-width: 0;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.keyword-result-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.keyword-result-topbar .keyword-results-title {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.keyword-result-topbar .keyword-results-title strong {
    color: var(--ink);
    font-size: 15px;
    line-height: 1.5;
}

.keyword-result-topbar .keyword-results-title small {
    color: var(--muted);
    font-size: 12px;
}

.keyword-copy-all-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    flex: 0 0 auto;
    padding: 8px 12px;
    border: 1px solid #cfd6dd;
    border-radius: 11px;
    color: #111827;
    background: #fff;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    font-size: 13px;
    font-weight: 700;
}

.keyword-copy-all-btn:hover {
    border-color: #b8c2cc;
    background: #f8fafc;
}

.keyword-results-frame {
    padding: 14px;
    border: 1.5px solid #c7cdd3;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.keyword-results-frame .keyword-results-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.keyword-results-frame .keyword-result-item {
    overflow: hidden;
    min-width: 0;
    border: 1px solid #e1e5ea;
    border-radius: 14px;
    background: #f9fafb;
}

.keyword-results-frame .keyword-result-main {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) 36px;
    align-items: start;
    gap: 12px;
    min-width: 0;
    padding: 13px 14px;
}

.keyword-results-frame .keyword-result-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 60px 13px;
}

.keyword-results-frame .keyword-chip {
    max-width: 100%;
    overflow-wrap: anywhere;
    padding: 4px 8px;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    color: #374151;
    background: #fff;
    font-size: 11px;
}

.keyword-results-frame .keyword-copy-btn {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    color: #111827;
    background: #fff;
}

.keyword-results-frame .keyword-copy-btn:hover {
    background: #f3f4f6;
}

.keyword-regenerate-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}

.keyword-regenerate-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 9px 14px;
    border: 1px solid #d1d5db;
    border-radius: 11px;
    color: #111827;
    background: #fff;
    font-size: 13px;
    font-weight: 800;
}

.keyword-regenerate-btn:hover {
    background: #f3f4f6;
}

.keyword-regenerate-btn i {
    font-size: 15px;
}

@media (max-width: 560px) {
    .keyword-result-topbar {
        display: grid;
        gap: 10px;
    }

    .keyword-copy-all-btn,
    .keyword-regenerate-btn {
        width: 100%;
    }

    .keyword-regenerate-wrap {
        justify-content: stretch;
    }

    .keyword-results-frame {
        padding: 11px;
        border-radius: 16px;
    }

    .keyword-results-frame .keyword-result-main {
        grid-template-columns: 30px minmax(0, 1fr) 32px;
        gap: 9px;
        padding: 11px;
    }

    .keyword-results-frame .keyword-result-meta {
        padding-inline: 50px 11px;
    }
}

</style>
