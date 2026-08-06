<template>
    <main class="prompt-chat" :class="{ 'sidebar-collapsed': desktopSidebarCollapsed }"
        :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-key-fill"></i></span>
                <div>
                    <strong>{{ pageTitle }}</strong>
                    <small>{{ isArabic ? "مجموعة أدوات المحتوى" : "Content toolset" }}</small>
                </div>
                <button class="icon-button sidebar-close-toggle" type="button"
                    :aria-label="isArabic ? 'قفل قائمة المحادثات' : 'Close conversations sidebar'"
                    @click="closeSidebar">
                    <i class="bi bi-layout-sidebar-inset-reverse"></i>
                </button>
            </div>

            <button class="new-chat-button" type="button" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                {{ creatingConversation ? labels.creating : labels.newChat }}
            </button>
            <p class="section-label">{{ labels.recent }}</p>

            <div v-if="loadingConversations" class="sidebar-status">{{ labels.loading }}</div>
            <div v-else-if="conversations.length === 0" class="sidebar-status">{{ labels.noChats }}</div>

            <div v-else class="conversation-list history-list">
                <div v-for="conversation in conversations" :key="conversation.uuid" class="conversation-item history-item"
                    :class="{ active: conversation.uuid === activeConversation?.uuid }">
                    <button type="button" class="conversation-open history-item-main" @click="openConversation(conversation)">
                        <i class="bi bi-chat-left-text"></i>

                        <div class="history-item-info">
                            <span class="history-item-title">{{ conversation.title }}</span>
                        </div>
                    </button>

                    <button type="button" class="conversation-delete history-delete" :disabled="deletingUuid === conversation.uuid"
                        :aria-label="labels.deleteChat" @click="deleteConversation(conversation)">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </aside>

        <button v-if="desktopSidebarCollapsed" type="button" class="desktop-sidebar-open-toggle"
            :aria-label="isArabic ? 'فتح قائمة المحادثات' : 'Open conversations sidebar'" @click="openSidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <button v-if="sidebarOpen" class="sidebar-overlay" type="button" @click="sidebarOpen = false"></button>
        <button v-if="!sidebarOpen" class="mobile-sidebar-toggle mobile-only" type="button"
            :aria-label="isArabic ? 'فتح قائمة المحادثات' : 'Open conversations sidebar'" @click="openSidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

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

                    <button
                        v-if="!hasActiveConversation"
                        type="button"
                        class="welcome-new-chat-button"
                        :disabled="creatingConversation || sendingMessage"
                        @click="startNewChat"
                    >
                        <span v-if="creatingConversation" class="button-spinner" aria-hidden="true"></span>
                        <i v-else class="bi bi-chat-square-text-fill" aria-hidden="true"></i>
                        <span>{{ creatingConversation ? labels.creating : createConversationLabel }}</span>
                    </button>

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
                                <SeoToolResult v-if="isSeoToolResultMessage(message)" :message="message"
                                    :results="getSeoToolResults(message)" :labels="labels" :copied-key="copiedKey"
                                    @copy-result="copyKeyword" @copy-all="copyAllSeoResults"
                                    @edit-options="editMessageOptions" />

                                <div v-else-if="message.content" class="message-content"
                                    :class="{ 'tool-output-text': isSeoToolMessage(message) }"
                                    v-html="formatMessage(message.content)"></div>
                            </template>
                        </div>
                    </article>

                    <article v-if="isAssistantTyping" class="message-row assistant assistant-typing-row" dir="rtl">
                        <div class="avatar">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="message-body assistant-typing-body">
                            <div class="assistant-typing-content">
                                <span class="assistant-typing-text">جاري الكتابة</span>
                                <span class="typing-dots" aria-hidden="true">
                                    <span class="typing-dot"></span>
                                    <span class="typing-dot animation-delay-150"></span>
                                    <span class="typing-dot animation-delay-300"></span>
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <footer v-if="hasActiveConversation" class="composer">
                <div v-if="errorMessage" class="error-banner">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ errorMessage }}
                </div>

                <details ref="optionsPanelRef" class="options-panel">
                    <summary>
                        <span><i class="bi bi-sliders"></i> {{ labels.options }}</span>
                        <span class="options-panel-meta">
                            <i class="bi bi-chevron-down options-chevron" aria-hidden="true"></i>
                        </span>
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

                        <fieldset v-if="activeTool.extraOptions?.length" class="wide checkbox-field">
                            <legend>{{ labels.extraOptions }}</legend>
                            <label v-for="option in activeTool.extraOptions" :key="option" class="checkbox-option">
                                <input type="checkbox" :checked="toolState.extra_options.includes(option)"
                                    @change="toggleExtraOption(option)">
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

                    <button type="button" class="send-button" :disabled="sendDisabled || !canSendMessage"
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
    include_long_tail: false,
    include_clusters: false,
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

const stripJsonFence = (value = "") =>
    String(value || "").trim().replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/i, "").trim();

const parseLooseJson = (value) => safeJsonParse(stripJsonFence(value));

const pushSeoCandidate = (candidates, value) => {
    if (value === null || value === undefined || value === "") return;

    candidates.push(value);

    if (typeof value === "string") {
        const parsed = parseLooseJson(value);
        if (parsed) candidates.push(parsed);
    }
};

const directSeoText = (row = {}) => {
    if (!isPlainObject(row)) return row;

    return row.text
        ?? row.content
        ?? row.description
        ?? row.meta_description
        ?? row.optimized_text
        ?? row.optimized_content
        ?? row.analysis
        ?? row.output
        ?? row.message
        ?? row.reply
        ?? "";
};

const normalizeToolResultItem = (item, index = 0) => {
    if (typeof item === "string") {
        const parsed = parseLooseJson(item);
        if (Array.isArray(parsed?.results)) return normalizeToolResultItems(parsed.results);

        item = { text: item };
    }

    if (!isPlainObject(item)) return null;

    const rawText = item.text ?? item.content ?? item.description ?? item.meta_description ?? "";
    const parsedText = typeof rawText === "string" ? parseLooseJson(rawText) : null;
    if (Array.isArray(parsedText?.results)) return normalizeToolResultItems(parsedText.results);

    const text = cleanOutputText(rawText);
    if (!text || looksLikeJsonText(text) || reasoningPattern.test(text)) return null;

    return {
        id: Number(item.id) || index + 1,
        text,
        title: item.title ?? null,
        subject: item.subject ?? null,
        meta: isPlainObject(item.meta) ? item.meta : {},
    };
};

const normalizeToolResultItems = (items = []) =>
    (Array.isArray(items) ? items : [])
        .flatMap((item, index) => {
            const normalized = normalizeToolResultItem(item, index);
            return Array.isArray(normalized) ? normalized : [normalized];
        })
        .filter(Boolean)
        .map((item, index) => ({ ...item, id: Number(item.id) || index + 1 }));

const collectToolResponseLayers = (source = {}) => {
    const layers = [];
    const queue = [source];
    const seen = new Set();

    while (queue.length && layers.length < 12) {
        const current = queue.shift();
        if (!isPlainObject(current) || seen.has(current)) continue;

        seen.add(current);
        layers.push(current);

        ["data", "response", "raw"].forEach((key) => {
            if (isPlainObject(current[key])) queue.push(current[key]);
        });
    }

    return layers;
};

const normalizeLastOutputResults = (value) => {
    const text = cleanOutputText(value);
    if (!text) return [];

    const parsed = parseLooseJson(text);
    if (Array.isArray(parsed?.results)) return normalizeToolResultItems(parsed.results);

    return text
        .split(/\n+/)
        .map((line, index) => ({
            id: index + 1,
            text: cleanOutputText(line),
            title: null,
            subject: null,
            meta: {},
        }))
        .filter((item) => item.text.length > 0);
};

const normalizeToolResults = (response) => {
    if (!response) return [];

    const layers = collectToolResponseLayers(response);

    for (const layer of layers) {
        for (const items of [layer.results, layer.normalized_results]) {
            const normalized = normalizeToolResultItems(items);
            if (normalized.length) return normalized;
        }
    }

    for (const layer of layers) {
        const state = isPlainObject(layer.state) ? layer.state : null;
        const responseState = isPlainObject(layer.responseState) ? layer.responseState : null;
        const fromState = normalizeLastOutputResults(state?.last_output ?? responseState?.last_output);
        if (fromState.length) return fromState;
    }

    for (const layer of layers) {
        for (const value of [layer.content, layer.output, layer.reply, layer.text, layer.message]) {
            const normalized = normalizeLastOutputResults(value);
            if (normalized.length) return normalized;
        }
    }

    return [];
};

const normalizeSeoTextItem = (item, index = 0) => {
    const rawText = directSeoText(item);
    const parsedText = typeof rawText === "string" ? parseLooseJson(rawText) : null;

    if (Array.isArray(parsedText?.results)) {
        return normalizeSeoItems(parsedText.results);
    }

    const text = cleanOutputText(rawText);
    if (!text || looksLikeJsonText(text) || reasoningPattern.test(text)) return null;

    return {
        id: Number(isPlainObject(item) ? item.id : 0) || index + 1,
        text,
        meta: isPlainObject(item?.meta) ? item.meta : {},
    };
};

const normalizeSeoItems = (items = []) =>
    (Array.isArray(items) ? items : [])
        .flatMap((item, index) => {
            const normalized = normalizeSeoTextItem(item, index);
            return Array.isArray(normalized) ? normalized : [normalized];
        })
        .filter(Boolean)
        .map((item, index) => ({ ...item, id: Number(item.id) || index + 1 }));

const collectSeoCandidates = (source = {}) => {
    const response = unwrapResponse(source);
    const meta = metadataFrom(response);
    const requestPayload = isPlainObject(meta.request_payload)
        ? meta.request_payload
        : isPlainObject(response.request_payload)
            ? response.request_payload
            : {};
    const candidates = [];

    [
        meta.normalized_results,
        response.normalized_results,
        meta.results,
        response.results,
        meta.state?.last_output,
        response.responseState?.last_output,
        response.state?.last_output,
        requestPayload.state?.last_output,
        response.last_output,
        meta.text,
        response.text,
        meta.content,
        response.content,
        meta.message,
        response.message,
        meta.reply,
        response.reply,
        meta.output,
        response.output,
        response,
    ].forEach((value) => pushSeoCandidate(candidates, value));

    return candidates;
};

const normalizeSeoToolResults = (source = {}) => {
    const directResults = normalizeToolResults(source);
    if (directResults.length) return directResults;

    for (const candidate of collectSeoCandidates(source)) {
        if (Array.isArray(candidate)) {
            const normalized = normalizeSeoItems(candidate);
            if (normalized.length) return normalized;
            continue;
        }

        if (isPlainObject(candidate)) {
            const nestedResults = candidate.normalized_results || candidate.results;
            if (Array.isArray(nestedResults)) {
                const normalized = normalizeSeoItems(nestedResults);
                if (normalized.length) return normalized;
            }

            const item = normalizeSeoTextItem(candidate, 0);
            const normalized = Array.isArray(item) ? item : [item].filter(Boolean);
            if (normalized.length) return normalized;
            continue;
        }

        const item = normalizeSeoTextItem(candidate, 0);
        const normalized = Array.isArray(item) ? item : [item].filter(Boolean);
        if (normalized.length) return normalized;
    }

    return [];
};

const normalizeKeywordGeneratorResults = normalizeSeoToolResults;

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
        normalizeResults: normalizeSeoToolResults,
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
        normalizeResults: normalizeSeoToolResults,
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
        normalizeResults: normalizeSeoToolResults,
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
const createConversationLabel = computed(() =>
    isArabic.value ? "إنشاء محادثة جديدة" : "Create new conversation"
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
    editOptions: "تعديل الخيارات",
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
    editOptions: "Edit options",
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
const getDefaultStateForTool = (toolId) => {
    const tool = CHAT3_TOOLS[Number(toolId)] || CHAT3_TOOLS[KEYWORD_GENERATOR_SUB_TOOL_ID];
    return tool.getInitialState();
};
const toolStates = ref(
    Object.fromEntries(
        Object.values(CHAT3_TOOLS).map((tool) => [tool.id, getDefaultStateForTool(tool.id)])
    )
);
const toolState = computed({
    get: () => {
        const toolId = activeTool.value.id;
        if (!toolStates.value[toolId]) {
            toolStates.value[toolId] = getDefaultStateForTool(toolId);
        }
        return toolStates.value[toolId];
    },
    set: (value) => {
        toolStates.value[activeTool.value.id] = value;
    },
});
const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const hasActiveConversation = computed(() =>
    Boolean(activeConversation.value?.uuid || route.params.uuid)
);
const userMessage = ref("");
const errorMessage = ref("");
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const sendingMessage = ref(false);
const streamingAssistant = ref(false);
const isAssistantTyping = ref(false);
const deletingUuid = ref("");
const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const eventSource = ref(null);
const copiedKey = ref("");
const refineMode = ref(false);
const optionsPanelRef = ref(null);

const markdown = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
});

const localizedToolTitle = (tool) => isArabic.value ? tool.title : tool.titleEn;

const MOBILE_SIDEBAR_BREAKPOINT = 900;

const isMobileSidebar = () => window.innerWidth <= MOBILE_SIDEBAR_BREAKPOINT;

const closeSidebar = () => {
    if (isMobileSidebar()) {
        sidebarOpen.value = false;
        return;
    }

    desktopSidebarCollapsed.value = true;
};

const openSidebar = () => {
    if (isMobileSidebar()) {
        sidebarOpen.value = true;
        return;
    }

    desktopSidebarCollapsed.value = false;
};
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
const sendDisabled = computed(() =>
    sendingMessage.value || streamingAssistant.value || isAssistantTyping.value
);
const optionsSummary = computed(() => {
    const state = normalizeToolState(toolState.value);
    const count = Object.entries(state)
        .filter(([key, value]) => key !== "last_output" && key !== "extra_options" && value !== null && value !== "")
        .length + (Array.isArray(state.extra_options) ? state.extra_options.length : 0);

    return count ? String(count) : labels.value.defaultValue;
});
const extraOptionsText = computed({
    get: () => Array.isArray(toolState.value?.extra_options) ? toolState.value.extra_options.join(", ") : "",
    set: (value) => {
        toolState.value.extra_options = String(value || "")
            .split(",")
            .map((item) => item.trim())
            .filter(Boolean);
    },
});

const addExtraOption = (option) => {
    const value = String(option || "").trim();
    if (!value) return;
    if (!Array.isArray(toolState.value.extra_options)) toolState.value.extra_options = [];
    if (!toolState.value.extra_options.includes(value)) toolState.value.extra_options.push(value);
};

const removeExtraOption = (option) => {
    if (!Array.isArray(toolState.value.extra_options)) {
        toolState.value.extra_options = [];
        return;
    }
    toolState.value.extra_options = toolState.value.extra_options.filter((item) => item !== option);
};

const toggleExtraOption = (option) => {
    if (toolState.value.extra_options.includes(option)) {
        removeExtraOption(option);
    } else {
        addExtraOption(option);
    }
};

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

const toPositiveInteger = (value, fallback = 3) => {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? Math.floor(number) : fallback;
};

const normalizeToolState = (state = {}, toolId = activeTool.value.id) => {
    const tool = CHAT3_TOOLS[Number(toolId)] || activeTool.value;
    const base = tool.getInitialState();
    const source = isPlainObject(state) ? state : {};
    const normalized = { ...base };
    const booleanKeys = (tool.booleanFields || []).map((field) => field.key);
    const arrayKeys = [
        "extra_options",
        ...(tool.arrayFields || []).map((field) => field.key),
        ...(tool.textArrayFields || []).map((field) => field.key),
    ];

    Object.keys(base).forEach((key) => {
        if (arrayKeys.includes(key)) {
            normalized[key] = Array.isArray(source[key])
                ? source[key].map((item) => String(item || "").trim()).filter(Boolean)
                : [...(base[key] || [])];
            return;
        }

        if (booleanKeys.includes(key)) {
            normalized[key] = typeof source[key] === "boolean"
                ? source[key]
                : Boolean(base[key]);
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

const resolveToolKey = (tool = activeTool.value) =>
    tool?.tool_key || tool?.toolKey || subtool.value?.toolKey || KEYWORD_GENERATOR_TOOL_KEY;

const resolveModelKey = (tool = activeTool.value) =>
    tool?.model_key || tool?.modelKey || subtool.value?.modelKey || KEYWORD_GENERATOR_MODEL_KEY;

const mapMessageToRequiredToolFields = (state, message, toolId, toolKey, options = {}) => {
    const next = { ...(isPlainObject(state) ? state : {}) };
    const cleanMessage = String(message || "").trim();
    const key = String(toolKey || "").toLowerCase();
    const preserveExisting = Boolean(options.preserveExisting);
    const valueFor = (value) => (preserveExisting && String(value || "").trim() ? value : cleanMessage);

    if (!cleanMessage) return next;

    next.content = valueFor(next.content);
    next.topic = valueFor(next.topic);
    next.user_message = cleanMessage;

    if (Number(toolId) === KEYWORD_GENERATOR_SUB_TOOL_ID || key === KEYWORD_GENERATOR_TOOL_KEY) {
        next.topic = valueFor(next.topic);
    }

    if (key === "ai_social_post_generator" || key === "social_post_generator") {
        next.content = valueFor(next.content);
        next.topic = valueFor(next.topic);
    }

    if (key === "ai_email_writer" || key === "email_writer") {
        next.purpose = valueFor(next.purpose);
        next.content = valueFor(next.content);
    }

    if (key === "ai_script_generator" || key === "script_generator") {
        next.topic = valueFor(next.topic);
    }

    if (key === "ai_product_description_generator" || key === "product_description_generator") {
        next.product = valueFor(next.product);
        next.content = valueFor(next.content);
    }

    if (key === "prompt_enhancer" || key === "ai_prompt_enhancer") {
        next.original_prompt = valueFor(next.original_prompt);
    }

    if (key === "idea_generator" || key === "ai_idea_generator") {
        next.topic = valueFor(next.topic);
    }

    return next;
};

const metadataFrom = (message = {}) => {
    if (isPlainObject(message.metadata)) return message.metadata;
    if (typeof message.metadata === "string") return safeJsonParse(message.metadata) || {};
    if (isPlainObject(message.toolMeta)) return message.toolMeta;
    return {};
};

const SEO_TOOL_IDS = [
    KEYWORD_GENERATOR_SUB_TOOL_ID,
    META_DESCRIPTION_SUB_TOOL_ID,
    CONTENT_ANALYZER_SUB_TOOL_ID,
    CONTENT_OPTIMIZER_SUB_TOOL_ID,
];

const requestPayloadFrom = (message = {}) => {
    const meta = metadataFrom(message);
    if (isPlainObject(meta.request_payload)) return meta.request_payload;
    if (isPlainObject(message.request_payload)) return message.request_payload;
    if (isPlainObject(message.requestPayload)) return message.requestPayload;
    return {};
};

const responseStateFrom = (message = {}, toolId = resolveMessageSubToolId(message)) => {
    const meta = metadataFrom(message);
    const requestPayload = requestPayloadFrom(message);
    const state = message.responseState
        || meta.state
        || message.state
        || requestPayload.state
        || {};

    return isPlainObject(state) ? normalizeToolState(state, toolId) : null;
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

const getToolOutput = (source = {}, subToolId = resolveMessageSubToolId(source)) => {
    if (!SEO_TOOL_IDS.includes(Number(subToolId))) return "";

    return normalizeSeoToolResults(source)
        .map((item) => String(item?.text || "").trim())
        .filter(Boolean)
        .join("\n\n");
};

const isSeoToolMessage = (message = {}) =>
    SEO_TOOL_IDS.includes(resolveMessageSubToolId(message));

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

const isSeoToolResultMessage = (message = {}) =>
    message.role === "assistant"
    && !message.typing
    && SEO_TOOL_IDS.includes(resolveMessageSubToolId(message))
    && getSeoToolResults(message).length > 0;

const getSeoToolResults = (message = {}) =>
    Array.isArray(message.seoToolResults) && message.seoToolResults.length
        ? message.seoToolResults
        : normalizeSeoToolResults(message);

const looksLikeJsonText = (value = "") => {
    const text = String(value || "").trim();
    return text.startsWith("{") || text.startsWith("[");
};

const mapMessage = (message = {}, index = 0) => {
    const meta = metadataFrom(message);
    const subToolId = resolveMessageSubToolId(message);
    const seoTool = message.role === "assistant" && SEO_TOOL_IDS.includes(subToolId);
    const responsePayload = {
        ...message,
        ...meta,
        state: meta.state || message.state,
        request_payload: meta.request_payload || message.request_payload,
        results: meta.normalized_results || meta.results || message.results,
        normalized_results: meta.normalized_results || message.normalized_results,
        raw: message.raw || meta.raw || meta.raw_response,
    };
    const seoToolResults = seoTool ? normalizeSeoToolResults(responsePayload) : [];
    const rawContent = String(message.content || meta.message || "");
    const formattedError = isArabic.value
        ? "تعذر تنسيق نتيجة الأداة. يرجى إعادة المحاولة."
        : "The tool result could not be formatted. Please try again.";

    return {
        ...message,
        localKey: message.localKey || `${message.role || "message"}-${message.id || index}-${message.created_at || createLocalKey()}`,
        role: message.role || "assistant",
        content: seoTool
            ? (seoToolResults.length ? "" : (looksLikeJsonText(rawContent) ? formattedError : cleanOutputText(rawContent) || formattedError))
            : rawContent,
        metadata: meta,
        responseState: responseStateFrom({ ...message, metadata: meta }, subToolId),
        keywordResults: subToolId === KEYWORD_GENERATOR_SUB_TOOL_ID ? seoToolResults : [],
        metaDescriptionResults: subToolId === META_DESCRIPTION_SUB_TOOL_ID ? seoToolResults : [],
        seoToolResults,
        results: seoTool ? seoToolResults : message.results,
        count: Number(message.count || meta.count || seoToolResults.length || 0),
        raw: message.raw || meta.raw || meta.raw_response || null,
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
    if (!uuid) return;

    try {
        const stored = safeJsonParse(sessionStorage.getItem(stateStorageKey(uuid)) || "");
        if (stored) toolState.value = normalizeToolState(stored, activeTool.value.id);
    } catch {
        // Keep the in-memory state if storage is unavailable or invalid.
    }
};

const resetCurrentToolOptions = () => {
    toolStates.value[activeTool.value.id] = getDefaultStateForTool(activeTool.value.id);
    persistState();
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
    try {
        const stored = safeJsonParse(sessionStorage.getItem(stateStorageKey()) || "");
        if (stored) {
            toolState.value = normalizeToolState(stored, activeTool.value.id);
            return;
        }
    } catch {
        // Fall back to the latest state stored with the conversation messages.
    }

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
    isAssistantTyping.value = false;
};

const openAssistantStream = async (conversation, afterId) => {
    if (!conversation?.uuid) return;

    closeStream();
    streamingAssistant.value = true;
    isAssistantTyping.value = true;
    await scrollToBottom();

    const params = new URLSearchParams({
        after_id: String(afterId || 0),
        token: localStorage.getItem("auth_token") || "",
    });
    const source = new EventSource(`/api/v1/conversation/${conversation.uuid}/stream?${params.toString()}`);
    eventSource.value = source;

    source.onmessage = async (event) => {
        const payload = JSON.parse(event.data || "{}");

        if (payload.type === "token") {
            await scrollToBottom();
        }

        if (payload.type === "error") {
            isAssistantTyping.value = false;
            messages.value.push(mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                is_error: true,
                content: (
                    payload.message
                    || payload.detail
                    || payload.content
                    || labels.value.genericError
                ),
                created_at: new Date().toISOString(),
            }));
            closeStream();
            await scrollToBottom();
        }

        if (payload.type === "done") {
            isAssistantTyping.value = false;
            closeStream();
            await loadConversationDetails(conversation.uuid);
        }
    };

    source.onerror = async () => {
        isAssistantTyping.value = false;
        closeStream();
        await loadConversationDetails(conversation.uuid);
    };
};

const inferStateFromMessage = (state, text, options = {}) => {
    const toolId = activeTool.value.id;
    const toolKey = resolveToolKey();
    const next = mapMessageToRequiredToolFields(
        normalizeToolState(state, toolId),
        text,
        toolId,
        toolKey,
        options
    );
    const hadExplicitContent = Boolean(isPlainObject(state) && String(state.content || "").trim());
    const message = String(text || "").trim();
    const contentAfterColon = message.includes(":")
        ? message.slice(message.indexOf(":") + 1).trim()
        : "";

    if ([META_DESCRIPTION_SUB_TOOL_ID, CONTENT_ANALYZER_SUB_TOOL_ID, CONTENT_OPTIMIZER_SUB_TOOL_ID].includes(toolId)) {
        next.content = hadExplicitContent ? next.content : contentAfterColon || message;
    }

    if (toolId === META_DESCRIPTION_SUB_TOOL_ID && !next.primary_keyword) {
        const match = message.match(/\bfor\s+(?:an?\s+)?(.+?)(?:[.!?]|$)/i);
        next.primary_keyword = cleanOutputText(match?.[1] || next.content);
        if (!hadExplicitContent && next.primary_keyword) {
            next.content = next.primary_keyword;
        }
    }

    if (toolId === CONTENT_ANALYZER_SUB_TOOL_ID && !next.target_keyword) {
        const match = message.match(/:\s*([A-Za-z][A-Za-z\s-]{2,40})/);
        next.target_keyword = match?.[1]?.trim().split(/\s+/).slice(0, 3).join(" ") || null;
    }

    if (toolId === CONTENT_OPTIMIZER_SUB_TOOL_ID && !next.primary_keyword) {
        const match = message.match(/\bkeyword\s+(.+?)(?=:|,|\.|$)/i);
        next.primary_keyword = cleanOutputText(match?.[1] || "");
    }

    next.results_count = toPositiveInteger(next.results_count, 3);
    next.extra_options = Array.isArray(next.extra_options) ? next.extra_options : [];

    return next;
};

const buildToolPayload = (messageText, conversation = activeConversation.value, options = {}) => {
    const text = String(messageText || "").trim();
    const tool = activeTool.value;
    const toolId = Number(tool?.id || subtool.value?.id || KEYWORD_GENERATOR_SUB_TOOL_ID);
    const toolKey = resolveToolKey(tool);
    const modelKey = resolveModelKey(tool);
    const state = inferStateFromMessage(options.state || toolState.value, text, {
        preserveExisting: Boolean(options.state),
    });
    state.results_count = toPositiveInteger(state.results_count, 3);
    state.extra_options = Array.isArray(state.extra_options) ? state.extra_options : [];

    return {
        user_id: Number(conversation?.user_id) || null,
        sub_tool_id: toolId,
        conversation_uuid: conversation?.uuid,
        user_message: text,
        content: text,
        tool: toolKey,
        tool_key: toolKey,
        model_key: modelKey,
        state,
        debug: false,
        idempotency_key: createIdempotencyKey(),
    };
};

const submitKeywordRequest = async (text, options = {}) => {
    const cleanText = String(text || "").trim();
    if (!cleanText || sendDisabled.value) return;

    errorMessage.value = "";
    sendingMessage.value = true;

    try {
        const conversation = await ensureConversation();
        if (!conversation?.uuid) return;

        const payload = buildToolPayload(cleanText, conversation, options);
        const requestState = toolRequestState(payload.state);

        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "user",
            content: cleanText,
            created_at: new Date().toISOString(),
        }));
        isAssistantTyping.value = true;

        userMessage.value = "";
        refineMode.value = false;
        resetTextarea();
        toolState.value = requestState;
        persistState(conversation.uuid);
        await scrollToBottom();

        const response = await chatServices.sendMessage(payload);
        const directPayload = unwrapResponse(response);
        const directResults = SEO_TOOL_IDS.includes(activeTool.value.id)
            ? activeTool.value.normalizeResults(directPayload)
            : [];
        const directOutput = getToolOutput(directPayload, activeTool.value.id);
        const directSubToolId = Number(directPayload.sub_tool_id || 0);
        const directToolKey = String(directPayload.tool || directPayload.tool_key || "").toLowerCase();
        const isDirectFinalResponse = directSubToolId === activeTool.value.id
            || directToolKey === activeTool.value.toolKey;

        if ((directResults.length || directOutput || isDirectFinalResponse) && (
            directPayload.tool
            || directPayload.sub_tool_id
            || directPayload.state
            || directPayload.results
            || directPayload.message
            || directPayload.content
        )) {
            const metadata = {
                type: directPayload.type || "result",
                tool: directPayload.tool || activeTool.value.toolKey,
                tool_key: directPayload.tool_key || directPayload.tool || activeTool.value.toolKey,
                model_key: directPayload.model_key || activeTool.value.modelKey,
                sub_tool_id: directPayload.sub_tool_id || activeTool.value.id,
                state: normalizeToolState(directPayload.state || requestState),
                request_payload: payload,
                normalized_results: directResults,
                results: Array.isArray(directPayload.results) ? directPayload.results : [],
                message: directPayload.message || "",
                count: Number(directPayload.count || directResults.length || 0),
                raw_response: directPayload,
            };

            isAssistantTyping.value = false;
            messages.value.push(mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                content: directOutput,
                type: directPayload.type || "result",
                tool: metadata.tool,
                tool_key: metadata.tool_key,
                model_key: metadata.model_key,
                sub_tool_id: metadata.sub_tool_id,
                results: directResults,
                state: metadata.state,
                count: metadata.count,
                raw: directPayload,
                metadata,
                created_at: new Date().toISOString(),
            }));
            await scrollToBottom();
            return;
        }

        await openAssistantStream(conversation, response?.data?.message_id || directPayload.message_id);
    } catch (error) {
        isAssistantTyping.value = false;
        errorMessage.value =
            error?.response?.data?.message
            || error?.response?.data?.detail
            || error?.response?.data?.error
            || labels.value.genericError;
    } finally {
        if (!streamingAssistant.value) {
            isAssistantTyping.value = false;
        }
        sendingMessage.value = false;
    }
};

const sendKeywordMessage = async () => {
    const toolId = Number(activeTool.value?.id || 0);
    const currentState = toolStates.value[toolId];
    const message = buildUserMessage(toolId, currentState);

    await submitKeywordRequest(message, {
        state: currentState,
    });
};

const buildUserMessage = (toolId, state = {}) => {
    const typedMessage = String(userMessage.value || "").trim();
    if (typedMessage) return typedMessage;

    const content = String(state.content || "").trim();
    const topic = String(state.topic || "").trim();

    if (Number(toolId) === META_DESCRIPTION_SUB_TOOL_ID && content) {
        return `Write SEO meta descriptions for: ${content}`;
    }
    if (Number(toolId) === CONTENT_ANALYZER_SUB_TOOL_ID && content) {
        return `Analyze this content: ${content}`;
    }
    if (Number(toolId) === CONTENT_OPTIMIZER_SUB_TOOL_ID && content) {
        return `Optimize this content for SEO: ${content}`;
    }
    if (Number(toolId) === KEYWORD_GENERATOR_SUB_TOOL_ID && topic) {
        return `Generate SEO keywords for: ${topic}`;
    }

    return content || topic;
};

const canSendMessage = computed(() =>
    Boolean(buildUserMessage(activeTool.value.id, toolState.value))
);

const submitCurrentToolFromOptions = async () => {
    const toolId = Number(activeTool.value?.id || 0);
    const currentState = toolStates.value[toolId];

    if (!toolId || !currentState) {
        errorMessage.value = isArabic.value
            ? "برجاء اختيار أداة أولًا."
            : "Please select a tool first.";
        return;
    }

    const message = buildUserMessage(toolId, currentState);
    if (!message) {
        errorMessage.value = isArabic.value
            ? "برجاء كتابة محتوى أو رسالة قبل الإرسال."
            : "Please enter content or a message before sending.";
        return;
    }

    await submitKeywordRequest(message, {
        state: currentState,
    });
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
        sidebarOpen.value = false;

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat3/${conversation.uuid}`);
        await nextTick();
        textareaRef.value?.focus();
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

const copyAllSeoResults = async (message) => {
    const text = getSeoToolResults(message)
        .map(keywordCopyText)
        .filter(Boolean)
        .join("\n");

    if (!text) return;

    await copyKeyword({ text, key: `${message.localKey}:all` });
};

const focusFirstOptionField = async () => {
    await nextTick();
    const panel = optionsPanelRef.value;
    const field = panel?.querySelector?.(
        "textarea:not([disabled]), input:not([disabled]):not([type='hidden']), select:not([disabled])"
    );
    field?.focus?.();
};

const editMessageOptions = async (message) => {
    const toolId = resolveMessageSubToolId(message);
    const state = responseStateFrom(message, toolId) || responseStateFrom({ metadata: metadataFrom(message) }, toolId);

    if (!state) return;

    toolStates.value[toolId] = normalizeToolState(state, toolId);
    refineMode.value = false;
    userMessage.value = "";
    optionsPanelRef.value?.setAttribute?.("open", "");
    persistState(activeConversation.value?.uuid);
    await focusFirstOptionField();
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
    document.body.style.overflow = "";
    window.removeEventListener("lang-changed", handleLanguageChange);
});

watch(sidebarOpen, (isOpen) => {
    if (window.innerWidth > MOBILE_SIDEBAR_BREAKPOINT) {
        document.body.style.overflow = "";
        return;
    }

    document.body.style.overflow = isOpen ? "hidden" : "";
});

watch(
    () => toolStates.value[activeTool.value.id],
    () => {
        if (activeConversation.value?.uuid) persistState(activeConversation.value.uuid);
    },
    { deep: true }
);

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

const SeoToolResult = defineComponent({
    name: "SeoToolResult",
    props: {
        message: { type: Object, required: true },
        results: { type: Array, default: () => [] },
        labels: { type: Object, required: true },
        copiedKey: { type: String, default: "" },
    },
    emits: ["copy-result", "copy-all", "edit-options"],
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
            textWrap: "min-w-0 break-words text-[#15324b] leading-7 [overflow-wrap:anywhere]",
            keywordText: "block break-words text-[#15324b] leading-7 [overflow-wrap:anywhere]",
            subject: "block text-[#687b8e] leading-6 [overflow-wrap:anywhere]",
            copyButton: "grid h-[34px] w-[34px] shrink-0 place-items-center rounded-[10px] border border-[#d1d5db] bg-white p-0 text-[#111827] transition-colors hover:bg-[#f9fafb] disabled:cursor-not-allowed disabled:opacity-60",
            actionBar: "flex justify-start border-t border-[#e5e7eb] bg-[#fbfdff] px-3.5 py-2",
            editButton: "inline-flex items-center gap-1.5 rounded-[10px] border border-[#d1d5db] bg-white px-3 py-2 text-xs font-semibold text-[#123f6d] transition-colors hover:bg-[#f3faff]",
        };

        const copyText = (item) => String(item?.text || "").trim();
        const allCopyKey = `${props.message.localKey}:all`;
        const resultCount = () => Number(props.message.count || props.results.length || 0);
        const resultTitle = () => props.labels.resultTitle
            || (document.documentElement.dir === "rtl" ? "النتيجة" : "Result");

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
                        h("strong", { class: tw.title }, resultTitle()),
                        h("small", { class: tw.count }, `${resultCount()} ${props.labels.resultsCount}`),
                    ]),
                ]),

                h("div", { class: tw.list }, props.results.map((item, index) => {
                    const key = `${props.message.localKey}:${item.id || index}`;

                    return h("section", { class: tw.item, key }, [
                        h("div", { class: tw.row }, [
                            h("span", { class: tw.index }, String(index + 1)),
                            h("div", {
                                class: tw.textWrap,
                                innerHTML: formatMessage(item.text || ""),
                            }),
                            h("button", {
                                class: tw.copyButton,
                                type: "button",
                                title: props.copiedKey === key ? props.labels.copied : props.labels.copy,
                                "aria-label": props.copiedKey === key ? props.labels.copied : props.labels.copy,
                                onClick: () => emit("copy-result", { text: copyText(item), key }),
                            }, [
                                h("i", {
                                    class: props.copiedKey === key
                                        ? "bi bi-check2 text-[#1f87c9]"
                                        : "bi bi-copy text-[#111827]",
                                }),
                            ]),
                        ]),
                    ]);
                })),

                h("div", { class: tw.actionBar }, [
                    h("button", {
                        class: tw.editButton,
                        type: "button",
                        onClick: () => emit("edit-options", props.message),
                    }, [
                        h("i", { class: "bi bi-sliders text-[#1f87c9]" }),
                        h("span", {}, props.labels.editOptions),
                    ]),
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
    height: calc(100vh - 70px);
    min-height: 0;
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    transition: grid-template-columns 0.25s ease;
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
    pointer-events: auto;
    overflow: hidden;
    transition: width 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.sidebar-brand {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}

.sidebar-close-toggle {
    flex-shrink: 0;
    margin-inline-start: auto;
    pointer-events: auto;
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
    width: calc(100% - 20px);
    /* margin-inline: 10px; */
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
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.conversation-open {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(18, 63, 109, 0.08);
    border-radius: 16px;
    color: var(--muted);
    background: #fbfdff;
    text-align: start;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.conversation-item.active .conversation-open,
.conversation-open:hover {
    transform: scale(1.02);
    border-color: rgba(31, 135, 201, 0.20);
    background: rgba(31, 135, 201, 0.08);
    color: var(--navy);
    box-shadow: 0 18px 32px rgba(18, 63, 109, 0.08);
}

.conversation-open i {
    margin-top: 2px;
    flex-shrink: 0;
    font-size: 15px;
}

.history-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    overflow: hidden;
}

.history-item-title {
    font-size: 13px;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-delete {
    display: grid;
    place-items: center;
    width: 42px;
    min-width: 42px;
    border: 1px solid rgba(18, 63, 109, 0.08);
    border-radius: 14px;
    color: var(--navy);
    background: #ffffff;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.conversation-delete:hover {
    transform: scale(1.02);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 14px 28px rgba(18, 63, 109, 0.08);
}

.icon-button {
    position: relative;
    z-index: 3;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 10px;
    color: var(--muted);
    background: transparent;
    pointer-events: auto;
}

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

.desktop-sidebar-open-toggle {
    position: fixed;
    top: 84px;
    inset-inline-start: 12px;
    z-index: 120;
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(18, 63, 109, 0.10);
    border-radius: 14px;
    background: #ffffff;
    color: var(--navy);
    box-shadow: 0 14px 30px rgba(18, 63, 109, 0.14);
    pointer-events: auto;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.desktop-sidebar-open-toggle:hover {
    transform: scale(1.04);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 18px 36px rgba(18, 63, 109, 0.18);
}

@media (min-width: 901px) {
    .prompt-chat.sidebar-collapsed {
        grid-template-columns: 0 minmax(0, 1fr);
    }

    .prompt-chat.sidebar-collapsed .sidebar {
        width: 0;
        padding-inline: 0;
        border-color: transparent;
        box-shadow: none;
        pointer-events: none;
    }

    .prompt-chat.sidebar-collapsed .sidebar>* {
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
    }
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

.welcome-new-chat-button {
    width: min(100%, 390px);
    min-height: 52px;
    margin: 20px auto 0;
    padding: 12px 22px;
    border: 0;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--blue), var(--navy));
    color: #ffffff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 12px 28px rgba(21, 70, 119, 0.18);
    transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
}

.welcome-new-chat-button:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 15px 34px rgba(21, 70, 119, 0.24);
}

.welcome-new-chat-button:focus-visible {
    outline: 3px solid rgba(43, 166, 222, 0.28);
    outline-offset: 3px;
}

.welcome-new-chat-button:disabled {
    cursor: not-allowed;
    opacity: 0.7;
    transform: none;
}

.welcome-new-chat-button i {
    font-size: 17px;
    line-height: 1;
}

.button-spinner {
    display: inline-block;
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: welcome-button-spin 0.7s linear infinite;
}

@keyframes welcome-button-spin {
    to {
        transform: rotate(360deg);
    }
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

.assistant-typing-row {
    animation: typing-fade-in 0.16s ease-out;
}

.assistant-typing-body {
    width: fit-content;
    max-width: min(420px, 75%);
}

.assistant-typing-content {
    display: flex;
    align-items: center;
    gap: 8px;
}

.assistant-typing-text {
    color: var(--navy);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.5;
}

.typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.typing-dot {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: var(--navy);
    display: inline-block;
    animation: typing-bounce 0.8s infinite ease-in-out;
}

.animation-delay-150 {
    animation-delay: 0.15s;
}

.animation-delay-300 {
    animation-delay: 0.3s;
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

.options-panel-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.options-chevron {
    transition: transform 0.2s ease;
}

.options-panel[open] .options-chevron {
    transform: rotate(180deg);
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

.options-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.options-submit-button,
.options-reset-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 14px;
    border-radius: 10px;
    font-weight: 700;
}

.options-submit-button {
    border: 0;
    color: #fff;
    background: var(--navy);
}

.options-reset-button {
    border: 1px solid var(--line);
    color: var(--navy);
    background: #fff;
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

@keyframes typing-bounce {

    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.45;
    }

    40% {
        transform: translateY(-6px);
        opacity: 1;
    }
}

@keyframes typing-fade-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
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
        width: min(300px, 86vw);
        z-index: 60;
        transform: translateX(-110%);
        transition: transform 0.2s ease;
    }

    [dir="rtl"] .sidebar {
        transform: translateX(110%);
    }

    .sidebar.sidebar-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        z-index: 50;
        inset: 0;
        display: block;
        border: 0;
        background: rgba(9, 31, 51, 0.38);
        pointer-events: auto;
    }

    .mobile-only {
        display: grid;
    }

    .mobile-sidebar-toggle {
        position: fixed;
        top: 12px;
        inset-inline-start: 12px;
        z-index: 45;
        width: 42px;
        height: 42px;
        place-items: center;
        border: 1px solid rgba(18, 63, 109, 0.10);
        border-radius: 14px;
        color: var(--navy);
        background: #ffffff;
        box-shadow: 0 14px 30px rgba(18, 63, 109, 0.14);
        pointer-events: auto;
    }

    .desktop-sidebar-open-toggle {
        display: none;
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
/* =========================
   DARK MODE
========================= */
:global(html[data-theme="dark"]) .prompt-chat {
    --navy: #154677;
    --blue: #5cc8f0;
    --cyan: #5cc8f0;
    --ink: var(--theme-text-primary);
    --muted: var(--theme-text-muted);
    --line: var(--theme-border);
    background: var(--theme-bg);
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .prompt-chat .sidebar,
:global(html[data-theme="dark"]) .prompt-chat .workspace-header,
:global(html[data-theme="dark"]) .prompt-chat .composer {
    background: var(--theme-surface);
    border-color: var(--theme-border);
}

:global(html[data-theme="dark"]) .prompt-chat .sidebar-brand strong,
:global(html[data-theme="dark"]) .prompt-chat .workspace-header h1,
:global(html[data-theme="dark"]) .prompt-chat .welcome-card h2,
:global(html[data-theme="dark"]) .prompt-chat .assistant-typing-text {
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .prompt-chat .sidebar-brand small,
:global(html[data-theme="dark"]) .prompt-chat .section-label,
:global(html[data-theme="dark"]) .prompt-chat .welcome-card p,
:global(html[data-theme="dark"]) .prompt-chat .options-summary,
:global(html[data-theme="dark"]) .prompt-chat .composer-hint {
    color: var(--theme-text-muted);
}

:global(html[data-theme="dark"]) .prompt-chat .conversation-open,
:global(html[data-theme="dark"]) .prompt-chat .conversation-delete,
:global(html[data-theme="dark"]) .prompt-chat .icon-button,
:global(html[data-theme="dark"]) .prompt-chat .desktop-sidebar-open-toggle,
:global(html[data-theme="dark"]) .prompt-chat .mobile-sidebar-toggle,
:global(html[data-theme="dark"]) .prompt-chat .suggestion,
:global(html[data-theme="dark"]) .prompt-chat .options-panel,
:global(html[data-theme="dark"]) .prompt-chat .options-reset-button,
:global(html[data-theme="dark"]) .prompt-chat .checkbox-field .checkbox-option,
:global(html[data-theme="dark"]) .prompt-chat .input-box,
:global(html[data-theme="dark"]) .prompt-chat .refine-banner {
    background: var(--theme-surface-secondary);
    color: var(--theme-text-secondary);
    border-color: var(--theme-border);
    box-shadow: none;
}

:global(html[data-theme="dark"]) .prompt-chat .conversation-item.active .conversation-open,
:global(html[data-theme="dark"]) .prompt-chat .conversation-open:hover,
:global(html[data-theme="dark"]) .prompt-chat .conversation-delete:hover,
:global(html[data-theme="dark"]) .prompt-chat .icon-button:hover,
:global(html[data-theme="dark"]) .prompt-chat .suggestion:hover {
    background: var(--theme-hover);
    color: var(--theme-text-primary);
    border-color: rgba(43, 166, 222, 0.34);
}

:global(html[data-theme="dark"]) .prompt-chat .welcome-card,
:global(html[data-theme="dark"]) .prompt-chat .message-body {
    background: var(--theme-surface-secondary);
    color: var(--theme-text-primary);
    border-color: var(--theme-border);
    box-shadow: 0 12px 28px var(--theme-shadow);
}

:global(html[data-theme="dark"]) .prompt-chat .user .message-body {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    border-color: transparent;
}

:global(html[data-theme="dark"]) .prompt-chat .options-grid input,
:global(html[data-theme="dark"]) .prompt-chat .options-grid select,
:global(html[data-theme="dark"]) .prompt-chat .options-grid textarea,
:global(html[data-theme="dark"]) .prompt-chat .input-box textarea {
    background: var(--theme-input-bg);
    color: var(--theme-text-primary);
    border-color: var(--theme-border-strong);
}

:global(html[data-theme="dark"]) .prompt-chat .options-grid input:focus,
:global(html[data-theme="dark"]) .prompt-chat .options-grid select:focus,
:global(html[data-theme="dark"]) .prompt-chat .options-grid textarea:focus,
:global(html[data-theme="dark"]) .prompt-chat .input-box:focus-within {
    border-color: rgba(43, 166, 222, 0.72);
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.13);
}

:global(html[data-theme="dark"]) .prompt-chat .message-body.error,
:global(html[data-theme="dark"]) .prompt-chat .error-banner {
    background: rgba(248, 113, 113, 0.10);
    color: #f87171;
    border-color: rgba(248, 113, 113, 0.24);
}

:global(html[data-theme="dark"]) .prompt-chat .sidebar-overlay {
    background: var(--theme-overlay);
}

:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class~="bg-white"]),
:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="bg-[#eaf6fc]"]),
:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="bg-[#fbfdff]"]) {
    background: var(--theme-surface-elevated);
}

:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="border-[#d1d5db]"]),
:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="border-[#e5e7eb]"]) {
    border-color: var(--theme-border);
}

:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="text-[#111827]"]),
:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="text-[#123f6d]"]),
:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="text-[#15324b]"]) {
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .prompt-chat .message-body :deep([class*="text-[#687b8e]"]) {
    color: var(--theme-text-muted);
}
</style>
