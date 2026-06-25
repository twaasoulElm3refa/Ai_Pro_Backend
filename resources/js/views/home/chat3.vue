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
            <p class="section-label">{{ labels.recent }}</p>

            <div v-if="loadingConversations" class="sidebar-status">{{ labels.loading }}</div>
            <div v-else-if="conversations.length === 0" class="sidebar-status">{{ labels.noChats }}</div>

            <div v-else class="conversation-list">
                <div v-for="conversation in conversations" :key="conversation.uuid" class="conversation-item"
                    :class="{ active: conversation.uuid === activeConversation?.uuid }">
                    <button type="button" class="conversation-open" @click="openConversation(conversation)">
                        <i class="bi bi-chat-left-text"></i>
                        <span>{{ conversation.title }}</span>
                    </button>

                    <button type="button" class="conversation-delete" :disabled="deletingUuid === conversation.uuid"
                        :aria-label="labels.deleteChat" @click="deleteConversation(conversation)">
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
                    <article v-for="message in messages" :key="message.localKey" class="message-row"
                        :class="message.role">
                        <div class="avatar">
                            <i :class="message.role === 'assistant' ? 'bi bi-stars' : 'bi bi-person-fill'"></i>
                        </div>

                        <div class="message-body" :class="{ error: message.is_error }">
                            <div v-if="message.typing" class="typing">
                                <span></span><span></span><span></span>
                            </div>

                            <template v-else>
                                <KeywordGeneratorResult v-if="isKeywordResultMessage(message)" :message="message"
                                    :results="getKeywordResults(message)" :labels="labels" :copied-key="copiedKey"
                                    @copy-keyword="copyKeyword" @copy-all="copyAllKeywords" />

                                <MetaDescriptionResult v-else-if="isMetaDescriptionResultMessage(message)"
                                    :message="message" :results="getMetaDescriptionResults(message)"
                                    :max-characters="message.responseState?.max_characters || 160"
                                    :labels="labels" :copied-key="copiedKey" @copy-result="copyKeyword" />

                                <div v-else-if="message.content" class="message-content"
                                    :class="{ 'tool-output-text': isSeoToolMessage(message) }"
                                    v-html="formatMessage(message.content)"></div>
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
                        <label v-for="field in activeTool.fields" :key="field.key"
                            :class="{ wide: field.wide }">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <textarea v-if="field.type === 'textarea'" v-model="toolState[field.key]" rows="4"
                                :placeholder="field.placeholder"></textarea>
                            <select v-else-if="field.type === 'select'" v-model="toolState[field.key]">
                                <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                            </select>
                            <input v-else v-model="toolState[field.key]" :type="field.type || 'text'"
                                :min="field.min" :max="field.max" :placeholder="field.placeholder">
                        </label>

                        <label v-if="activeTool.resultsCount">
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

                        <fieldset v-for="field in activeTool.arrayFields || []" :key="field.key"
                            class="wide checkbox-field">
                            <legend>{{ isArabic ? field.labelAr : field.labelEn }}</legend>
                            <label v-for="option in field.options" :key="option" class="checkbox-option">
                                <input v-model="toolState[field.key]" type="checkbox" :value="option">
                                <span>{{ option }}</span>
                            </label>
                        </fieldset>

                        <label v-for="field in activeTool.textArrayFields || []" :key="field.key" class="wide">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <input :value="getTextArrayValue(field.key)" type="text"
                                @input="setTextArrayValue(field.key, $event.target.value)">
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
                    <textarea ref="textareaRef" v-model="userMessage" rows="1" :placeholder="composerPlaceholder"
                        :disabled="sendDisabled" @input="autoResize"
                        @keydown.enter.exact.prevent="sendKeywordMessage"></textarea>

                    <button type="button" class="send-button" :disabled="sendDisabled || !userMessage.trim()"
                        :aria-label="labels.send" @click="sendKeywordMessage">
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
const META_DESCRIPTION_SUB_TOOL_ID = 14;
const META_DESCRIPTION_TOOL_KEY = "ai_meta_description_generator";
const META_DESCRIPTION_MODEL_KEY = "meta_description_generator";
const CONTENT_ANALYZER_SUB_TOOL_ID = 15;
const CONTENT_ANALYZER_TOOL_KEY = "ai_content_analyzer";
const CONTENT_ANALYZER_MODEL_KEY = "content_analyzer";
const CONTENT_OPTIMIZER_SUB_TOOL_ID = 16;
const CONTENT_OPTIMIZER_TOOL_KEY = "ai_content_optimizer";
const CONTENT_OPTIMIZER_MODEL_KEY = "content_optimizer";

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

const getInitialMetaDescriptionState = () => ({
    content: null,
    page_title: null,
    primary_keyword: null,
    language: "Auto Detect",
    tone: "Clear and persuasive",
    length: "Standard",
    max_characters: 160,
    include_cta: false,
    results_count: 3,
    extra_options: ["SEO-friendly", "Avoid keyword stuffing"],
    last_output: null,
});

const getInitialContentAnalyzerState = () => ({
    content: null,
    analysis_goal: "SEO and readability analysis",
    language: "Auto Detect",
    target_keyword: null,
    content_type: "Article / Page Content",
    audience: "General Audience",
    checks: ["SEO", "Readability", "Structure", "Keyword usage", "Search intent"],
    detail_level: "Medium",
    include_recommendations: true,
    extra_options: ["Prioritize actionable fixes", "Do not invent external metrics"],
    last_output: null,
});

const getInitialContentOptimizerState = () => ({
    content: null,
    optimization_goal: "Improve SEO, clarity, and readability",
    primary_keyword: null,
    secondary_keywords: [],
    language: "Auto Detect",
    tone: "Professional",
    content_type: "Article / Page Content",
    audience: "General Audience",
    seo_level: "Balanced",
    preserve_meaning: true,
    include_explanation: false,
    extra_options: ["Natural keyword usage", "Improve structure"],
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
        resultsCount: true,
    },
    [META_DESCRIPTION_SUB_TOOL_ID]: {
        id: META_DESCRIPTION_SUB_TOOL_ID,
        slug: "meta-description-generator",
        toolKey: META_DESCRIPTION_TOOL_KEY,
        modelKey: META_DESCRIPTION_MODEL_KEY,
        title: "مولد الوصف التعريفي",
        titleEn: "Meta Description Generator",
        getInitialState: getInitialMetaDescriptionState,
        fields: [
            { key: "content", labelAr: "المحتوى أو وصف الصفحة", labelEn: "Page content or description", type: "textarea", wide: true },
            { key: "page_title", labelAr: "عنوان الصفحة", labelEn: "Page title", type: "text" },
            { key: "primary_keyword", labelAr: "الكلمة المفتاحية الأساسية", labelEn: "Primary keyword", type: "text" },
            { key: "language", labelAr: "اللغة", labelEn: "Language", type: "select", options: ["Auto Detect", "Arabic", "English"] },
            { key: "tone", labelAr: "النبرة", labelEn: "Tone", type: "select", options: ["Clear and persuasive", "Professional", "Friendly", "Informative"] },
            { key: "length", labelAr: "الطول", labelEn: "Length", type: "select", options: ["Short", "Standard", "Long"] },
            { key: "max_characters", labelAr: "الحد الأقصى للحروف", labelEn: "Maximum characters", type: "number", min: 50, max: 320 },
        ],
        booleanFields: [
            { key: "include_cta", labelAr: "تضمين CTA", labelEn: "Include CTA" },
        ],
        resultsCount: true,
        extraOptions: ["SEO-friendly", "Avoid keyword stuffing"],
    },
    [CONTENT_ANALYZER_SUB_TOOL_ID]: {
        id: CONTENT_ANALYZER_SUB_TOOL_ID,
        slug: "content-analyzer",
        toolKey: CONTENT_ANALYZER_TOOL_KEY,
        modelKey: CONTENT_ANALYZER_MODEL_KEY,
        title: "تحليل المحتوى",
        titleEn: "Content Analyzer",
        getInitialState: getInitialContentAnalyzerState,
        fields: [
            { key: "content", labelAr: "المحتوى المراد تحليله", labelEn: "Content to analyze", type: "textarea", wide: true },
            { key: "analysis_goal", labelAr: "هدف التحليل", labelEn: "Analysis goal", type: "text" },
            { key: "target_keyword", labelAr: "الكلمة المفتاحية المستهدفة", labelEn: "Target keyword", type: "text" },
            { key: "language", labelAr: "اللغة", labelEn: "Language", type: "select", options: ["Auto Detect", "Arabic", "English"] },
            { key: "content_type", labelAr: "نوع المحتوى", labelEn: "Content type", type: "select", options: ["Article / Page Content", "Blog Post", "Landing Page", "Product Page"] },
            { key: "audience", labelAr: "الجمهور", labelEn: "Audience", type: "select", options: ["General Audience", "Customers", "Professionals", "Business Owners"] },
            { key: "detail_level", labelAr: "مستوى التفاصيل", labelEn: "Detail level", type: "select", options: ["Brief", "Medium", "Detailed"] },
        ],
        booleanFields: [
            { key: "include_recommendations", labelAr: "تضمين التوصيات", labelEn: "Include recommendations" },
        ],
        arrayFields: [
            { key: "checks", labelAr: "الفحوصات المطلوبة", labelEn: "Required checks", options: ["SEO", "Readability", "Structure", "Keyword usage", "Search intent"] },
        ],
        extraOptions: ["Prioritize actionable fixes", "Do not invent external metrics"],
    },
    [CONTENT_OPTIMIZER_SUB_TOOL_ID]: {
        id: CONTENT_OPTIMIZER_SUB_TOOL_ID,
        slug: "content-optimizer",
        toolKey: CONTENT_OPTIMIZER_TOOL_KEY,
        modelKey: CONTENT_OPTIMIZER_MODEL_KEY,
        title: "تحسين المحتوى",
        titleEn: "Content Optimizer",
        getInitialState: getInitialContentOptimizerState,
        fields: [
            { key: "content", labelAr: "المحتوى المراد تحسينه", labelEn: "Content to optimize", type: "textarea", wide: true },
            { key: "optimization_goal", labelAr: "هدف التحسين", labelEn: "Optimization goal", type: "text" },
            { key: "primary_keyword", labelAr: "الكلمة المفتاحية الأساسية", labelEn: "Primary keyword", type: "text" },
            { key: "language", labelAr: "اللغة", labelEn: "Language", type: "select", options: ["Auto Detect", "Arabic", "English"] },
            { key: "tone", labelAr: "النبرة", labelEn: "Tone", type: "select", options: ["Professional", "Friendly", "Persuasive", "Informative"] },
            { key: "content_type", labelAr: "نوع المحتوى", labelEn: "Content type", type: "select", options: ["Article / Page Content", "Blog Post", "Landing Page", "Product Page"] },
            { key: "audience", labelAr: "الجمهور", labelEn: "Audience", type: "select", options: ["General Audience", "Customers", "Professionals", "Business Owners"] },
            { key: "seo_level", labelAr: "مستوى SEO", labelEn: "SEO level", type: "select", options: ["Light", "Balanced", "Advanced"] },
        ],
        booleanFields: [
            { key: "preserve_meaning", labelAr: "الحفاظ على المعنى", labelEn: "Preserve meaning" },
            { key: "include_explanation", labelAr: "تضمين شرح التحسينات", labelEn: "Include optimization explanation" },
        ],
        textArrayFields: [
            { key: "secondary_keywords", labelAr: "الكلمات المفتاحية الثانوية", labelEn: "Secondary keywords" },
        ],
        extraOptions: ["Natural keyword usage", "Improve structure"],
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

const registryTools = computed(() => Object.values(CHAT3_TOOLS));
const subtool = ref({
    id: KEYWORD_GENERATOR_SUB_TOOL_ID,
    name: "",
    description: "",
    promptPlaceholder: "",
    toolKey: KEYWORD_GENERATOR_TOOL_KEY,
    modelKey: KEYWORD_GENERATOR_MODEL_KEY,
});
const activeTool = computed(() =>
    CHAT3_TOOLS[Number(subtool.value.id)] || CHAT3_TOOLS[KEYWORD_GENERATOR_SUB_TOOL_ID]
);
const toolState = ref(activeTool.value.getInitialState());
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
const examplePrompt = computed(() => ({
    [KEYWORD_GENERATOR_SUB_TOOL_ID]: "Generate 20 SEO keywords for an article about AI tools for content creators in arabic",
    [META_DESCRIPTION_SUB_TOOL_ID]: "Write 3 SEO meta descriptions for a television 32 inch.",
    [CONTENT_ANALYZER_SUB_TOOL_ID]: "Analyze this content for SEO and readability: AI content tools help marketers write faster and improve productivity.",
    [CONTENT_OPTIMIZER_SUB_TOOL_ID]: "Optimize this content for SEO using the keyword AI writing tools: AI tools help writers create content faster.",
}[activeTool.value.id] || ""));
const composerPlaceholder = computed(() =>
    refineMode.value
        ? (isArabic.value ? "مثال: زوّد long-tail وركز على السعودية" : "Example: add more long-tail keywords and focus on Saudi Arabia")
        : (subtool.value.promptPlaceholder || examplePrompt.value)
);
const sendDisabled = computed(() => sendingMessage.value || streamingAssistant.value);
const optionsSummary = computed(() => {
    const state = normalizeToolState(toolState.value);
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

const getTextArrayValue = (key) =>
    Array.isArray(toolState.value[key]) ? toolState.value[key].join(", ") : "";

const setTextArrayValue = (key, value) => {
    toolState.value[key] = String(value || "")
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
};

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

const normalizeToolState = (state = {}) => {
    const base = activeTool.value.getInitialState();
    const source = isPlainObject(state) ? state : {};
    const normalized = { ...base };
    const booleanKeys = (activeTool.value.booleanFields || []).map((field) => field.key);
    const arrayKeys = [
        "extra_options",
        ...(activeTool.value.arrayFields || []).map((field) => field.key),
        ...(activeTool.value.textArrayFields || []).map((field) => field.key),
    ];

    Object.keys(base).forEach((key) => {
        if (arrayKeys.includes(key)) {
            normalized[key] = Array.isArray(source[key])
                ? source[key].map((item) => String(item || "").trim()).filter(Boolean)
                : [...(base[key] || [])];
            return;
        }

        if (booleanKeys.includes(key)) {
            normalized[key] = typeof source[key] === "boolean" ? source[key] : base[key];
            return;
        }

        if (["results_count", "max_characters"].includes(key)) {
            const count = Number(source[key]);
            normalized[key] = Number.isFinite(count) && count > 0 ? Math.floor(count) : base[key];
            return;
        }

        normalized[key] = source[key] === undefined
            ? base[key]
            : source[key] === ""
                ? null
                : source[key];
    });

    return normalized;
};

const normalizeKeywordState = normalizeToolState;

const toolRequestState = (state = {}) => ({
    ...normalizeToolState(state),
    last_output: state?.last_output ?? null,
});

const keywordRequestState = toolRequestState;

const metadataFrom = (message = {}) => {
    if (isPlainObject(message.metadata)) return message.metadata;
    if (typeof message.metadata === "string") return safeJsonParse(message.metadata) || {};
    if (isPlainObject(message.toolMeta)) return message.toolMeta;
    return {};
};

const resolveMessageSubToolId = (message = {}) => {
    const meta = metadataFrom(message);
    const tool = String(meta.tool_key || meta.tool || message.tool_key || message.tool || "").toLowerCase();
    const modelKey = String(meta.model_key || message.model_key || "").toLowerCase();
    const explicitId = Number(message.sub_tool_id || message.subToolId || meta.sub_tool_id || meta.subToolId || 0);

    if (explicitId && CHAT3_TOOLS[explicitId]) return explicitId;

    const matched = registryTools.value.find((item) =>
        tool === item.toolKey || modelKey === item.modelKey
    );

    return matched?.id || Number(subtool.value.id) || KEYWORD_GENERATOR_SUB_TOOL_ID;
};

const cleanOutputText = (value) => {
    if (value === null || value === undefined) return "";

    let output = String(value).replace(/^\uFEFF/, "").trim();
    if (!output) return "";

    if (output.startsWith('"') && output.endsWith('"')) {
        try {
            const decoded = JSON.parse(output);
            if (typeof decoded === "string") output = decoded;
        } catch {
            // Keep non-JSON quoted text unchanged.
        }
    }

    if (!output.includes("\n") && /\\[nr]/.test(output)) {
        output = output.replace(/\\r\\n|\\n|\\r/g, "\n");
    }

    return output
        .replace(/\\"/g, '"')
        .replace(/\\([()[\]{}])/g, "$1")
        .replace(/\.?""+\s*$/g, ".")
        .replace(/^"{3,}|"{3,}$/g, "")
        .split(/\r?\n/)
        .filter((line) => !/^\s*(null|undefined)\s*$/i.test(line))
        .join("\n")
        .trim();
};

const reasoningPattern = /\b(we need to|let['’]s|check length|count characters|we must)\b/i;

const normalizeMetaDescriptionItem = (item, index = 0, maxCharacters = 160) => {
    const row = isPlainObject(item) ? item : { text: item };
    const text = cleanOutputText(row.text || row.content || row.description || "");
    if (!text || reasoningPattern.test(text)) return null;

    return {
        id: Number(row.id || index + 1),
        text,
        characters: Number(row.meta?.characters) || [...text].length,
        maxCharacters: Number(row.meta?.max_characters) || Number(maxCharacters) || 160,
    };
};

const extractMetaDescriptionsFromText = (value, maxCharacters = 160) => {
    const text = cleanOutputText(value);
    if (!text) return [];

    const parsed = safeJsonParse(
        text.replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/i, "").trim()
    );
    if (Array.isArray(parsed?.results)) {
        return parsed.results
            .map((item, index) => normalizeMetaDescriptionItem(item, index, maxCharacters))
            .filter(Boolean);
    }

    const candidates = text
        .split(/\n+/)
        .map((line) => line.replace(/^\s*(?:\d+[.)]|[-*•])\s*/, "").trim())
        .filter((line) => line && !reasoningPattern.test(line))
        .filter((line) => line.length >= 35 && line.length <= Number(maxCharacters || 160) + 40);

    return candidates
        .map((line, index) => normalizeMetaDescriptionItem(line, index, maxCharacters))
        .filter(Boolean);
};

const getMetaDescriptionResults = (source = {}) => {
    const response = unwrapResponse(source);
    const meta = metadataFrom(response);
    const state = meta.state || response.responseState || response.state || {};
    const maxCharacters = Number(state.max_characters) || 160;
    const rawResults = meta.normalized_results || response.metaDescriptionResults || meta.results || response.results;

    if (Array.isArray(rawResults)) {
        const normalized = rawResults
            .map((item, index) => normalizeMetaDescriptionItem(item, index, maxCharacters))
            .filter(Boolean);
        if (normalized.length) return normalized;
    }

    const fallbacks = [
        meta.results?.[0]?.text,
        response.results?.[0]?.text,
        meta.state?.last_output,
        response.state?.last_output,
        response.content,
        response.message,
    ];

    for (const candidate of fallbacks) {
        const extracted = extractMetaDescriptionsFromText(candidate, maxCharacters);
        if (extracted.length) return extracted;
    }

    return [];
};

const getToolOutput = (source = {}, subToolId = resolveMessageSubToolId(source)) => {
    const response = unwrapResponse(source);
    const meta = metadataFrom(response);

    if (subToolId === META_DESCRIPTION_SUB_TOOL_ID) {
        return getMetaDescriptionResults(response);
    }

    const firstResult = Array.isArray(meta.results)
        ? meta.results[0]
        : Array.isArray(response.results)
            ? response.results[0]
            : null;

    const output = firstResult?.text
        || (typeof firstResult === "string" ? firstResult : "")
        || meta.state?.last_output
        || response.state?.last_output
        || meta.message
        || response.message
        || response.content
        || "";

    const cleaned = cleanOutputText(output);
    return looksLikeJsonText(cleaned) ? "" : cleaned;
};

const isSeoToolMessage = (message = {}) =>
    [META_DESCRIPTION_SUB_TOOL_ID, CONTENT_ANALYZER_SUB_TOOL_ID, CONTENT_OPTIMIZER_SUB_TOOL_ID]
        .includes(resolveMessageSubToolId(message));

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

const isMetaDescriptionResultMessage = (message = {}) =>
    message.role === "assistant"
    && !message.typing
    && resolveMessageSubToolId(message) === META_DESCRIPTION_SUB_TOOL_ID
    && getMetaDescriptionResults(message).length > 0;

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
    const subToolId = resolveMessageSubToolId(message);
    const keyword = message.role === "assistant" && subToolId === KEYWORD_GENERATOR_SUB_TOOL_ID;
    const metaDescription = message.role === "assistant" && subToolId === META_DESCRIPTION_SUB_TOOL_ID;
    const seoTextTool = message.role === "assistant"
        && [CONTENT_ANALYZER_SUB_TOOL_ID, CONTENT_OPTIMIZER_SUB_TOOL_ID].includes(subToolId);
    const responsePayload = {
        ...message,
        ...meta,
        state: meta.state || message.state,
        results: meta.normalized_results || meta.results || message.results,
    };
    const keywordResults = keyword
        ? CHAT3_TOOLS[KEYWORD_GENERATOR_SUB_TOOL_ID].normalizeResults(responsePayload)
        : [];
    const metaDescriptionResults = metaDescription
        ? getMetaDescriptionResults(responsePayload)
        : [];
    const rawContent = String(message.content || meta.message || "");
    const toolOutput = seoTextTool ? getToolOutput(responsePayload, subToolId) : "";

    return {
        ...message,
        localKey: message.localKey || `${message.role || "message"}-${message.id || index}-${message.created_at || createLocalKey()}`,
        role: message.role || "assistant",
        content: keyword
            ? (keywordResults.length ? "" : (looksLikeJsonText(rawContent) ? keywordFallbackContent() : rawContent))
            : metaDescription
                ? (metaDescriptionResults.length
                    ? ""
                    : (isArabic.value
                        ? "لم يتم توليد أوصاف تعريفية صالحة. يرجى إعادة المحاولة."
                        : "No valid meta descriptions were generated. Please try again."))
                : seoTextTool
                    ? (toolOutput || (isArabic.value
                        ? "تعذر تنسيق نتيجة الأداة. يرجى إعادة المحاولة."
                        : "The tool result could not be formatted. Please try again."))
                    : rawContent,
        metadata: meta,
        responseState: isPlainObject(meta.state) ? normalizeToolState(meta.state) : null,
        keywordResults,
        metaDescriptionResults,
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
        const resolvedId = Number(data.id || config.sub_tool_id || KEYWORD_GENERATOR_SUB_TOOL_ID);
        const resolvedTool = CHAT3_TOOLS[resolvedId] || CHAT3_TOOLS[KEYWORD_GENERATOR_SUB_TOOL_ID];

        subtool.value = {
            id: resolvedTool.id,
            name: data.translation?.name || data.name || localizedToolTitle(resolvedTool),
            description: data.translation?.description || data.description || "",
            promptPlaceholder: data.translation?.prompt_placeholder || data.prompt_placeholder || "",
            toolKey: data.tool_key || config.tool_key || resolvedTool.toolKey,
            modelKey: data.model_key || config.model_key || resolvedTool.modelKey,
        };
    } catch {
        const resolvedTool = registryTools.value.find((item) => item.slug === route.params.slug)
            || CHAT3_TOOLS[KEYWORD_GENERATOR_SUB_TOOL_ID];
        subtool.value = {
            id: resolvedTool.id,
            name: localizedToolTitle(resolvedTool),
            description: "",
            promptPlaceholder: "",
            toolKey: resolvedTool.toolKey,
            modelKey: resolvedTool.modelKey,
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

const inferStateFromMessage = (state, text) => {
    const next = normalizeToolState(state);
    const hadExplicitContent = Boolean(isPlainObject(state) && String(state.content || "").trim());
    const message = String(text || "").trim();
    const contentAfterColon = message.includes(":")
        ? message.slice(message.indexOf(":") + 1).trim()
        : "";

    if ([META_DESCRIPTION_SUB_TOOL_ID, CONTENT_ANALYZER_SUB_TOOL_ID, CONTENT_OPTIMIZER_SUB_TOOL_ID].includes(activeTool.value.id)) {
        next.content = next.content || contentAfterColon || message;
    }

    if (activeTool.value.id === META_DESCRIPTION_SUB_TOOL_ID && !next.primary_keyword) {
        const match = message.match(/\bfor\s+(?:an?\s+)?(.+?)(?:[.!?]|$)/i);
        next.primary_keyword = cleanOutputText(match?.[1] || next.content);
        if (!hadExplicitContent && next.primary_keyword) {
            next.content = next.primary_keyword;
        }
    }

    if (activeTool.value.id === CONTENT_ANALYZER_SUB_TOOL_ID && !next.target_keyword) {
        const match = message.match(/:\s*([A-Za-z][A-Za-z\s-]{2,40})/);
        next.target_keyword = match?.[1]?.trim().split(/\s+/).slice(0, 3).join(" ") || null;
    }

    if (activeTool.value.id === CONTENT_OPTIMIZER_SUB_TOOL_ID && !next.primary_keyword) {
        const match = message.match(/\bkeyword\s+(.+?)(?=:|,|\.|$)/i);
        next.primary_keyword = cleanOutputText(match?.[1] || "");
    }

    return next;
};

const buildPayload = (conversation, text, options = {}) => {
    const state = inferStateFromMessage(options.state || toolState.value, text);
    const payload = {
        user_id: Number(conversation.user_id) || null,
        sub_tool_id: activeTool.value.id,
        conversation_uuid: conversation.uuid,
        user_message: text,
        state,
        debug: false,
    };

    if (activeTool.value.id === KEYWORD_GENERATOR_SUB_TOOL_ID) {
        payload.content = text;
        payload.tool = activeTool.value.toolKey;
        payload.tool_key = activeTool.value.toolKey;
        payload.model_key = activeTool.value.modelKey;
        payload.idempotency_key = createIdempotencyKey();
    }

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
        const requestState = toolRequestState(payload.state);

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
        const directResults = activeTool.value.id === KEYWORD_GENERATOR_SUB_TOOL_ID
            ? activeTool.value.normalizeResults(directPayload)
            : activeTool.value.id === META_DESCRIPTION_SUB_TOOL_ID
                ? getMetaDescriptionResults(directPayload)
                : [];
        const directOutput = [CONTENT_ANALYZER_SUB_TOOL_ID, CONTENT_OPTIMIZER_SUB_TOOL_ID]
            .includes(activeTool.value.id)
            ? getToolOutput(directPayload, activeTool.value.id)
            : "";
        const directSubToolId = Number(directPayload.sub_tool_id || 0);
        const directToolKey = String(directPayload.tool || directPayload.tool_key || "").toLowerCase();
        const isDirectFinalResponse = directSubToolId === activeTool.value.id
            || directToolKey === activeTool.value.toolKey;

        if ((directResults.length || directOutput || (
            activeTool.value.id === META_DESCRIPTION_SUB_TOOL_ID && isDirectFinalResponse
        )) && (
            directPayload.tool
            || directPayload.sub_tool_id
            || directPayload.state
            || directPayload.results
            || directPayload.message
        )) {
            const metadata = {
                type: directPayload.type || "result",
                tool: activeTool.value.toolKey,
                tool_key: activeTool.value.toolKey,
                model_key: activeTool.value.modelKey,
                sub_tool_id: activeTool.value.id,
                state: normalizeToolState(directPayload.state || requestState),
                request_payload: payload,
                normalized_results: directResults,
                results: Array.isArray(directPayload.results) ? directPayload.results : [],
                message: directPayload.message || "",
            };

            messages.value.push(mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                content: directOutput,
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
    userMessage.value = examplePrompt.value;
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
        restoreState(route.params.uuid);
        await loadConversations();
        if (route.params.uuid) {
            await loadConversationDetails(route.params.uuid);
        } else {
            activeConversation.value = null;
            messages.value = [];
        }
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
        const tw = {
            shell: "grid w-full max-w-[720px] min-w-0 gap-0 max-[900px]:max-w-full",
            copyBar: "box-border flex w-full items-center justify-start border border-[#d1d5db] border-b-0 rounded-t-[14px] bg-[#eaf6fc] px-3 py-2 max-sm:px-[11px] max-sm:py-[9px]",
            copyAllButton: "inline-flex items-center gap-1.5 border-0 bg-transparent p-0 text-xs font-semibold leading-snug text-[#111827] transition-colors hover:text-[#123f6d] disabled:cursor-not-allowed disabled:opacity-60",
            frame: "w-full min-w-0 overflow-hidden rounded-b-[14px] border border-[#d1d5db] bg-white shadow-[0_6px_18px_rgba(18,63,109,0.04)]",
            frameHeader: "flex items-center justify-between gap-3 border-b border-[#e5e7eb] bg-white px-3.5 py-[11px] text-[#123f6d]",
            titleWrap: "min-w-0",
            title: "block text-[13px] font-extrabold text-[#123f6d]",
            count: "mt-0.5 block text-[11px] text-[#687b8e]",
            list: "grid grid-cols-1 gap-0 p-0",
            item: "min-w-0 border-b border-[#e5e7eb] bg-white last:border-b-0",
            row: "grid min-w-0 grid-cols-[34px_minmax(0,1fr)_34px] items-start gap-3 px-3.5 pb-2 pt-3 max-sm:grid-cols-[30px_minmax(0,1fr)_32px] max-sm:gap-[9px] max-sm:p-[11px]",
            index: "grid h-7 w-7 place-items-center rounded-[9px] bg-gradient-to-br from-[#123f6d] to-[#1f87c9] font-extrabold text-white",
            textWrap: "grid min-w-0 gap-1",
            keywordText: "block break-words text-[#15324b] leading-7 [overflow-wrap:anywhere]",
            subject: "block text-[#687b8e] leading-6 [overflow-wrap:anywhere]",
            copyButton: "grid h-[34px] w-[34px] shrink-0 place-items-center rounded-[10px] border border-[#d1d5db] bg-white p-0 text-[#111827] transition-colors hover:bg-[#f9fafb] disabled:cursor-not-allowed disabled:opacity-60",
            meta: "flex flex-wrap gap-1.5 px-[60px] pb-3 max-sm:ps-[50px] max-sm:pe-[11px]",
            chip: "max-w-full rounded-full border border-[#d1d5db] bg-[#f9fafb] px-2 py-1 text-[11px] text-[#374151] [overflow-wrap:anywhere]",
        };

        const tag = (value) => value
            ? h("span", { class: tw.chip }, value)
            : null;

        const copyText = (item) => String(item?.text || "").trim();
        const allCopyKey = `${props.message.localKey}:all`;

        return () => h("div", { class: tw.shell }, [
            h("div", { class: tw.copyBar }, [
                h("button", {
                    class: tw.copyAllButton,
                    type: "button",
                    onClick: () => emit("copy-all", props.message),
                }, [
                    h("i", {
                        class: props.copiedKey === allCopyKey
                            ? "bi bi-check2 text-[#1f87c9]"
                            : "bi bi-copy text-[#1f87c9]",
                    }),
                    h("span", {}, props.copiedKey === allCopyKey ? props.labels.copied : props.labels.copyAll),
                ]),
            ]),

            h("div", { class: tw.frame }, [
                h("div", { class: tw.frameHeader }, [
                    h("div", { class: tw.titleWrap }, [
                        h("strong", { class: tw.title }, "النتيجة"),
                        h("small", { class: tw.count }, `${props.results.length} ${props.labels.resultsCount}`),
                    ]),
                ]),

                h("div", { class: tw.list }, props.results.map((item, index) => {
                    const key = `${props.message.localKey}:${item.id || index}`;
                    const tags = [
                        tag(item.type || item.meta?.type),
                        tag(item.intent || item.meta?.intent),
                        tag(item.cluster || item.meta?.cluster),
                    ].filter(Boolean);

                    return h("section", { class: tw.item, key }, [
                        h("div", { class: tw.row }, [
                            h("span", { class: tw.index }, String(item.id || index + 1)),
                            h("div", { class: tw.textWrap }, [
                                h("strong", { class: tw.keywordText }, item.text || item.title),
                                item.subject ? h("small", { class: tw.subject }, item.subject) : null,
                            ]),
                            h("button", {
                                class: tw.copyButton,
                                type: "button",
                                title: props.copiedKey === key ? props.labels.copied : props.labels.copy,
                                "aria-label": props.copiedKey === key ? props.labels.copied : props.labels.copy,
                                onClick: () => emit("copy-keyword", { text: copyText(item), key }),
                            }, [
                                h("i", {
                                    class: props.copiedKey === key
                                        ? "bi bi-check2 text-[#1f87c9]"
                                        : "bi bi-copy text-[#111827]",
                                }),
                            ]),
                        ]),
                        tags.length ? h("div", { class: tw.meta }, tags) : null,
                    ]);
                })),
            ]),

            h("div", { class: tw.regenerateBar }, [
                h("button", {
                    class: tw.regenerateButton,
                    type: "button",
                    onClick: () => emit("regenerate", props.message),
                }, [
                    h("i", { class: "bi bi-arrow-clockwise text-[#1f87c9]" }),
                    h("span", {}, props.labels.regenerate),
                ]),
            ]),
        ]);
    },
});

const MetaDescriptionResult = defineComponent({
    name: "MetaDescriptionResult",
    props: {
        message: { type: Object, required: true },
        results: { type: Array, default: () => [] },
        maxCharacters: { type: Number, default: 160 },
        labels: { type: Object, required: true },
        copiedKey: { type: String, default: "" },
    },
    emits: ["copy-result"],
    setup(props, { emit }) {
        return () => h("div", { class: "meta-description-results" },
            props.results.map((item, index) => {
                const key = `${props.message.localKey}:meta:${item.id || index}`;
                const count = Number(item.characters) || [...String(item.text || "")].length;
                const limit = Number(item.maxCharacters) || props.maxCharacters || 160;

                return h("section", { class: "meta-description-card", key }, [
                    h("div", { class: "meta-description-header" }, [
                        h("strong", {}, `${index + 1}`),
                        h("button", {
                            type: "button",
                            title: props.copiedKey === key ? props.labels.copied : props.labels.copy,
                            onClick: () => emit("copy-result", { text: item.text, key }),
                        }, [
                            h("i", {
                                class: props.copiedKey === key ? "bi bi-check2" : "bi bi-copy",
                            }),
                        ]),
                    ]),
                    h("p", { class: "meta-description-text" }, item.text),
                    h("small", { class: "meta-description-count" },
                        `${count} / ${limit} ${document.documentElement.dir === "rtl" ? "حرف" : "characters"}`
                    ),
                ]);
            })
        );
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
    height: calc(100vh - 70px);
    min-height: 0;
    display: grid;
    grid-template-columns: 290px minmax(0, 1fr);
    overflow: hidden;
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
    grid-template-rows: minmax(0, 1fr) auto;
    height: 100%;
    overflow: hidden;
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
    min-height: 0;
    overflow-y: auto;
    padding: 34px max(24px, calc((100% - 900px) / 2));
    padding-bottom: 22px;
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

.tool-output-text {
    white-space: pre-wrap;
}

.meta-description-results {
    display: grid;
    min-width: min(680px, 68vw);
    gap: 12px;
}

.meta-description-card {
    padding: 14px;
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    background: #fbfdff;
}

.meta-description-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.meta-description-header strong {
    display: grid;
    width: 28px;
    height: 28px;
    place-items: center;
    border-radius: 9px;
    color: #fff;
    background: var(--navy);
}

.meta-description-header button {
    width: 34px;
    height: 34px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: var(--navy);
    background: #fff;
}

.meta-description-text {
    margin: 0;
    line-height: 1.75;
    white-space: pre-wrap;
}

.meta-description-count {
    display: block;
    margin-top: 8px;
    color: var(--muted);
}

.result-list {
    display: grid;
    gap: 12px;
    min-width: min(680px, 68vw);
}

.message-content+.result-list {
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
    position: sticky;
    bottom: 0;
    z-index: 10;
    align-self: end;
    padding: 14px max(24px, calc((100% - 900px) / 2)) max(12px, env(safe-area-inset-bottom));
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
.options-grid select,
.options-grid textarea {
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
.options-grid select:focus,
.options-grid textarea:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.1);
}

.options-grid .wide {
    grid-column: span 2;
}

.checkbox-field {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    border: 0;
    padding: 0;
}

.checkbox-field legend {
    width: 100%;
    color: var(--muted);
    font-size: 12px;
}

.checkbox-field .checkbox-option {
    display: inline-flex;
    grid-auto-flow: column;
    align-items: center;
    width: auto;
    padding: 7px 10px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: #fff;
}

.checkbox-field .checkbox-option input {
    width: auto;
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

    .prompt-chat {
        height: calc(100vh - 64px);
    }

    .workspace {
        height: 100%;
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
</style>
