<template>
    <main class="detector-chat" :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ open: sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-shield-check"></i></span>
                <div>
                    <strong>{{ pageTitle }}</strong>
                    <small>{{ labels.subtitle }}</small>
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
                    <p class="eyebrow">{{ activeToolConfig.title_en }}</p>
                    <h1>{{ pageTitle }}</h1>
                </div>

                <div class="tool-badges">
                    <span>sub_tool_id: {{ activeSubToolId }}</span>
                    <span>{{ activeToolConfig.tool_key }}</span>
                </div>
            </header>

            <div ref="messagesContainer" class="messages" role="log" aria-live="polite">
                <div v-if="loadingMessages" class="center-status">
                    <span class="spinner"></span>
                    {{ labels.loadingConversation }}
                </div>

                <div v-else-if="messages.length === 0" class="welcome-card">
                    <span class="welcome-icon"><i class="bi bi-shield-check"></i></span>
                    <h2>{{ pageTitle }}</h2>
                    <p>{{ labels.welcome }}</p>
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

                        <div
                            class="message-body"
                            :class="{
                                error: message.is_error,
                                'card-shell': message.role === 'assistant' && (message.results?.length || message.is_error),
                            }"
                        >
                            <template v-if="message.role === 'assistant' && message.results?.length">
                                <div class="ai-response-card">
                                    <div class="ai-response-header">
                                        <div class="ai-response-title">
                                            <i class="bi bi-stars"></i>
                                            <span>{{ message.metadata?.title || labels.aiResponseTitle }}</span>
                                        </div>

                                        <button
                                            type="button"
                                            class="copy-card-button"
                                            @click="copyText(getMessageText(message), `msg-${message.localKey}`)"
                                        >
                                            <i class="bi bi-copy"></i>
                                            {{ copiedKey === `msg-${message.localKey}` ? labels.copied : labels.copy }}
                                        </button>
                                    </div>

                                    <div
                                        class="ai-response-content"
                                        v-html="formatMessage(getMessageText(message))"
                                    ></div>

                                    <div
                                        v-if="message.results[0]?.meta?.ai_likelihood_score !== undefined"
                                        class="ai-score-box"
                                    >
                                        <span>{{ labels.score }}</span>
                                        <strong>{{ message.results[0].meta.ai_likelihood_score }} / 100</strong>
                                    </div>

                                    <div v-if="message.results[0]?.meta?.signals?.length" class="ai-meta-section">
                                        <h4>{{ labels.signals }}</h4>
                                        <ul>
                                            <li v-for="signal in message.results[0].meta.signals" :key="signal">
                                                {{ signal }}
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="message.results[0]?.meta?.rewrite_tips?.length" class="ai-meta-section">
                                        <h4>{{ labels.rewriteTips }}</h4>
                                        <ul>
                                            <li v-for="tip in message.results[0].meta.rewrite_tips" :key="tip">
                                                {{ tip }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="message.role === 'assistant' && message.is_error">
                                <div class="clean-error-card">
                                    <i class="bi bi-exclamation-circle"></i>
                                    <span>{{ message.content }}</span>
                                </div>
                            </template>

                            <div
                                v-else-if="message.content"
                                class="message-content"
                                v-html="formatMessage(message.content)"
                            ></div>
                        </div>
                    </article>

                    <article v-if="isAssistantTyping" class="message-row assistant assistant-typing-row" dir="rtl">
                        <div class="avatar">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="message-body assistant-typing-body">
                            <div class="assistant-typing-content">
                                <span class="assistant-typing-text">{{ typingText }}</span>
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

            <footer class="composer">
                <div v-if="errorMessage" class="error-banner">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ errorMessage }}
                </div>

                <details ref="optionsPanelRef" class="options-panel">
                    <summary>
                        <span><i class="bi bi-sliders"></i> {{ labels.options }}</span>
                        <span class="options-summary">{{ optionsSummary }}</span>
                    </summary>

                    <form class="options-grid" @submit.prevent="applyOptions">
                        <template v-if="isHumanizer">
                            <label>
                                <span>{{ labels.language }}</span>
                                <select v-model="toolState.language">
                                    <option>Auto Detect</option>
                                    <option>Arabic</option>
                                    <option>English</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.tone }}</span>
                                <select v-model="toolState.tone">
                                    <option>Natural</option>
                                    <option>Professional</option>
                                    <option>Friendly</option>
                                    <option>Simple</option>
                                    <option>Creative</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.audience }}</span>
                                <select v-model="toolState.audience">
                                    <option>General Audience</option>
                                    <option>Students</option>
                                    <option>Professionals</option>
                                    <option>Customers</option>
                                    <option>Social Media Audience</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.humanizeLevel }}</span>
                                <select v-model="toolState.humanize_level">
                                    <option>Light</option>
                                    <option>Medium</option>
                                    <option>Strong</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.resultsCount }}</span>
                                <input v-model.number="toolState.results_count" type="number" min="1" max="5">
                            </label>

                            <fieldset class="wide checkbox-field">
                                <legend>{{ labels.humanizerOptions }}</legend>
                                <label class="checkbox-option">
                                    <input v-model="toolState.preserve_meaning" type="checkbox">
                                    <span>{{ labels.preserveMeaning }}</span>
                                </label>
                                <label class="checkbox-option">
                                    <input v-model="toolState.preserve_keywords" type="checkbox">
                                    <span>{{ labels.preserveKeywords }}</span>
                                </label>
                            </fieldset>
                        </template>

                        <template v-else>
                            <label>
                                <span>{{ labels.language }}</span>
                                <select v-model="toolState.language">
                                    <option>Auto Detect</option>
                                    <option>Arabic</option>
                                    <option>English</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.analysisDepth }}</span>
                                <select v-model="toolState.analysis_depth">
                                    <option>Quick</option>
                                    <option>Medium</option>
                                    <option>Deep</option>
                                </select>
                            </label>

                            <label class="wide">
                                <span>{{ labels.detectionFocus }}</span>
                                <select v-model="toolState.detection_focus">
                                    <option>AI writing signals</option>
                                    <option>Human tone</option>
                                    <option>Repetition and generic wording</option>
                                    <option>Structure and style</option>
                                </select>
                            </label>

                            <fieldset class="wide checkbox-field">
                                <legend>{{ labels.outputOptions }}</legend>
                                <label class="checkbox-option">
                                    <input v-model="toolState.include_score" type="checkbox">
                                    <span>{{ labels.includeScore }}</span>
                                </label>
                                <label class="checkbox-option">
                                    <input v-model="toolState.include_evidence" type="checkbox">
                                    <span>{{ labels.includeEvidence }}</span>
                                </label>
                                <label class="checkbox-option">
                                    <input v-model="toolState.include_rewrite_tips" type="checkbox">
                                    <span>{{ labels.includeRewriteTips }}</span>
                                </label>
                            </fieldset>
                        </template>

                        <fieldset class="wide checkbox-field">
                            <legend>{{ labels.extraOptions }}</legend>
                            <label
                                v-for="option in extraOptionChoices"
                                :key="option"
                                class="checkbox-option"
                            >
                                <input
                                    type="checkbox"
                                    :checked="toolState.extra_options.includes(option)"
                                    @change="toggleExtraOption(option)"
                                >
                                <span>{{ option }}</span>
                            </label>
                        </fieldset>

                        <div class="wide options-actions">
                            <button type="submit" class="options-submit-button" :disabled="sendDisabled">
                                <i class="bi bi-check2"></i>
                                {{ labels.applyOptions }}
                            </button>
                            <button type="button" class="options-reset-button" :disabled="sendDisabled" @click="resetOptions">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                {{ labels.resetOptions }}
                            </button>
                        </div>
                    </form>
                </details>

                <div class="input-box">
                    <textarea
                        ref="textareaRef"
                        v-model="userMessage"
                        rows="1"
                        :placeholder="labels.placeholder"
                        :disabled="sendDisabled"
                        @input="autoResize"
                        @keydown.enter.exact.prevent="sendMessage"
                    ></textarea>

                    <button
                        type="button"
                        class="send-button"
                        :disabled="sendDisabled || !userMessage.trim()"
                        :aria-label="labels.send"
                        @click="sendMessage"
                    >
                        <i :class="sendDisabled ? 'bi bi-hourglass-split' : 'bi bi-send-fill'"></i>
                    </button>
                </div>

                <p class="composer-hint">{{ labels.hint }}</p>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import MarkdownIt from "markdown-it";
import DOMPurify from "dompurify";
import chatServices from "@/services/chat/chatServices";
import homeService from "@/services/home/homeService";
import useSeoMeta from "@/composables/useSeoMeta";

const CHAT4_TOOLS = {
    17: {
        sub_tool_id: 17,
        tool_key: "ai_detector",
        model_key: "ai_detector",
        title_ar: "كاشف المحتوى الذكي",
        title_en: "AI Content Detector",
        subtitle_ar: "تحليل احتمالية كتابة المحتوى بالذكاء الاصطناعي",
        subtitle_en: "Focused AI-writing signal analysis",
    },
    18: {
        sub_tool_id: 18,
        tool_key: "ai_humanizer",
        model_key: "ai_humanizer",
        title_ar: " النصوص بالذكاء الاصطناعي",
        title_en: "AI Humanizer",
        subtitle_ar: "تحويل النص إلى صياغة طبيعية وأكثر بشرية",
        subtitle_en: "Rewrite AI-like text into natural human language",
    },
};
const CHAT4_SUB_TOOL_IDS = Object.keys(CHAT4_TOOLS).map(Number);
const DETECTOR_SUB_TOOL_ID = 17;
const HUMANIZER_SUB_TOOL_ID = 18;

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const markdown = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
});

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "en").toLowerCase() === "ar"
);

const activeConversation = ref(null);
const currentSubtool = ref(null);
const activeSubToolId = computed(() => {
    const candidates = [
        activeConversation.value?.sub_tool_id,
        route.params.sub_tool_id,
        currentSubtool.value?.id,
        currentSubtool.value?.sub_tool_id,
    ];

    const matched = candidates
        .map((value) => Number(value || 0))
        .find((value) => CHAT4_SUB_TOOL_IDS.includes(value));

    return matched || DETECTOR_SUB_TOOL_ID;
});
const activeToolConfig = computed(() => CHAT4_TOOLS[activeSubToolId.value] || CHAT4_TOOLS[DETECTOR_SUB_TOOL_ID]);
const isHumanizer = computed(() => Number(activeSubToolId.value) === HUMANIZER_SUB_TOOL_ID);

const labels = computed(() => isArabic.value ? {
    title: activeToolConfig.value.title_ar,
    subtitle: activeToolConfig.value.subtitle_ar,
    newChat: "محادثة جديدة",
    creating: "جاري الإنشاء...",
    recent: "المحادثات الأخيرة",
    loading: "جاري التحميل...",
    noChats: "لا توجد محادثات بعد",
    deleteChat: "حذف المحادثة",
    loadingConversation: "جاري تحميل المحادثة...",
    welcome: isHumanizer.value
        ? "الصق النص الذي تريد , وسنحوله إلى صياغة طبيعية وسلسة مع الحفاظ على المعنى."
        : "الصق النص أو اكتب طلبك، وسنحلل مؤشرات الأسلوب والبنية والتكرار بدون ادعاء اليقين.",
    placeholder: isHumanizer.value
        ? "الصق النص المراد أنسنته أو اكتب طلبك هنا..."
        : "الصق النص المراد تحليله أو اكتب طلبك هنا...",
    send: "إرسال",
    hint: "Enter للإرسال، وShift + Enter لسطر جديد",
    options: isHumanizer.value ? "خيارات الأنسنة" : "خيارات التحليل",
    applyOptions: "تعديل الخيارات",
    resetOptions: "إعادة تعيين الخيارات",
    language: "اللغة",
    analysisDepth: "عمق التحليل",
    detectionFocus: "تركيز الكشف",
    outputOptions: "مخرجات التحليل",
    includeScore: "إظهار درجة احتمالية الذكاء الاصطناعي",
    includeEvidence: "إظهار الأدلة والمؤشرات",
    includeRewriteTips: "إظهار نصائح إعادة الصياغة",
    extraOptions: "خيارات إضافية",
    aiResponseTitle: isHumanizer.value ? "النص بعد الأنسنة" : "نتيجة التحليل",
    tone: "النبرة",
    audience: "الجمهور",
    humanizeLevel: "درجة الأنسنة",
    resultsCount: "عدد النتائج",
    preserveMeaning: "الحفاظ على المعنى",
    preserveKeywords: "الحفاظ على الكلمات المفتاحية",
    humanizerOptions: "خيارات الأنسنة",
    humanizerResultTitle: "النص بعد الأنسنة",
    score: "AI Likelihood Score",
    signals: "Signals",
    rewriteTips: "Rewrite Tips",
    copy: "نسخ",
    copied: "تم النسخ",
    copyFull: "نسخ التحليل الكامل",
    copyResult: "نسخ النتيجة",
    copyAll: "نسخ كل النتائج",
    optionsApplied: "تم تحديث الخيارات. أرسل النص لتطبيقها.",
    authRequired: "يجب تسجيل الدخول أولاً.",
    genericError: isHumanizer.value
        ? "تعذر أنسنة النص الآن. يرجى المحاولة مرة أخرى."
        : "تعذر تحليل المحتوى الآن. يرجى المحاولة مرة أخرى.",
} : {
    title: activeToolConfig.value.title_en,
    subtitle: activeToolConfig.value.subtitle_en,
    newChat: "New chat",
    creating: "Creating...",
    recent: "Recent chats",
    loading: "Loading...",
    noChats: "No chats yet",
    deleteChat: "Delete chat",
    loadingConversation: "Loading conversation...",
    welcome: isHumanizer.value
        ? "Paste text to humanize, and we will rewrite it into a natural, smooth version while preserving the meaning."
        : "Paste text or ask for an analysis. The detector reviews style, structure, repetition, and specificity without claiming certainty.",
    placeholder: isHumanizer.value
        ? "Paste text to humanize or type your request..."
        : "Paste text to analyze or type your request...",
    send: "Send",
    hint: "Enter to send, Shift + Enter for a new line",
    options: isHumanizer.value ? "Humanizer options" : "Detector options",
    applyOptions: "Apply options",
    resetOptions: "Reset options",
    language: "Language",
    analysisDepth: "Analysis depth",
    detectionFocus: "Detection focus",
    outputOptions: "Analysis output",
    includeScore: "Include AI likelihood score",
    includeEvidence: "Include evidence",
    includeRewriteTips: "Include rewrite tips",
    extraOptions: "Extra options",
    aiResponseTitle: isHumanizer.value ? "Humanized text" : "Analysis result",
    tone: "Tone",
    audience: "Audience",
    humanizeLevel: "Humanize level",
    resultsCount: "Results count",
    preserveMeaning: "Preserve meaning",
    preserveKeywords: "Preserve keywords",
    humanizerOptions: "Humanizer options",
    humanizerResultTitle: "Humanized text",
    score: "AI Likelihood Score",
    signals: "Signals",
    rewriteTips: "Rewrite Tips",
    copy: "Copy",
    copied: "Copied",
    copyFull: "Copy full analysis",
    copyResult: "Copy result",
    copyAll: "Copy all results",
    optionsApplied: "Options updated. Send text to apply them.",
    authRequired: "Please sign in first.",
    genericError: isHumanizer.value
        ? "Could not humanize the text right now. Please try again."
        : "Could not analyze the content right now. Please try again.",
});

const examplePrompt = computed(() => isHumanizer.value
    ? "Humanize this text in Arabic: الذكاء الاصطناعي يقوم بتحسين عمليات إنشاء المحتوى بطريقة فعالة للغاية."
    : "Analyze this text and tell me if it sounds AI-written: Artificial intelligence is transforming the way businesses create content and communicate with customers."
);
const detectorExtraOptionChoices = [
    "Be cautious",
    "Do not claim certainty",
    "Highlight suspicious phrases",
    "Give practical rewrite suggestions",
];
const humanizerExtraOptionChoices = [
    "Improve flow",
    "Avoid robotic phrasing",
    "Make it sound natural",
    "Vary sentence structure",
    "Keep original meaning",
    "Simplify wording",
];
const extraOptionChoices = computed(() =>
    isHumanizer.value ? humanizerExtraOptionChoices : detectorExtraOptionChoices
);

function createDetectorState() {
    return {
        content: null,
        language: "Auto Detect",
        analysis_depth: "Medium",
        detection_focus: "AI writing signals",
        include_score: true,
        include_evidence: true,
        include_rewrite_tips: true,
        extra_options: ["Be cautious", "Do not claim certainty"],
        last_output: null,
    };
}

function createHumanizerState() {
    return {
        content: null,
        language: "Auto Detect",
        tone: "Natural",
        audience: "General Audience",
        humanize_level: "Medium",
        preserve_meaning: true,
        preserve_keywords: true,
        results_count: 1,
        extra_options: ["Improve flow", "Avoid robotic phrasing"],
        last_output: null,
    };
}

function createDefaultStateForTool(subToolId) {
    return Number(subToolId) === HUMANIZER_SUB_TOOL_ID
        ? createHumanizerState()
        : createDetectorState();
}

const toolState = ref(createDefaultStateForTool(DETECTOR_SUB_TOOL_ID));
const conversations = ref([]);
const messages = ref([]);
const userMessage = ref("");
const errorMessage = ref("");
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const isSending = ref(false);
const isAssistantTyping = ref(false);
const deletingUuid = ref("");
const sidebarOpen = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const copiedKey = ref("");
const optionsPanelRef = ref(null);

const pageTitle = computed(() => labels.value.title);
const sendDisabled = computed(() => isSending.value || isAssistantTyping.value);
const optionsSummary = computed(() => {
    const state = toolState.value || {};
    return isHumanizer.value
        ? [state.language, state.tone, state.humanize_level, `${state.results_count || 1} result`].filter(Boolean).join(" / ")
        : [state.language, state.analysis_depth, state.detection_focus].filter(Boolean).join(" / ");
});
const typingText = computed(() => {
    if (isHumanizer.value) {
        return isArabic.value ? "جاري الكتابة" : "Humanizing text";
    }

    return isArabic.value ? "جاري الكتابة" : "Analyzing content";
});

useSeoMeta({
    title: computed(() => `${pageTitle.value} | Ai Pro`),
    description: computed(() => labels.value.welcome),
});

const formatMessage = (value = "") =>
    DOMPurify.sanitize(markdown.render(String(value || "")), { USE_PROFILES: { html: true } });

function getMessageText(message) {
    if (message?.results?.length) {
        return message.results
            .map((item) => item.text || item.content || item.output || "")
            .filter(Boolean)
            .join("\n\n");
    }

    return String(message?.content || "");
}

function getResultTitle(subToolId = activeSubToolId.value) {
    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) {
        return isArabic.value ? "النص بعد الأنسنة" : "Humanized text";
    }

    return isArabic.value ? "نتيجة التحليل" : "Analysis result";
}

function cleanErrorMessage(error) {
    const raw =
        error?.response?.data?.message
        || error?.response?.data?.detail
        || error?.response?.data?.error
        || error?.message
        || "";

    const text = String(raw);
    const lower = text.toLowerCase();

    if (
        text.includes("429")
        || lower.includes("rate-limited")
        || lower.includes("rate limit")
        || lower.includes("too many requests")
    ) {
        return isArabic.value
            ? "الموديل مشغول مؤقتا بسبب كثرة الطلبات. يرجى المحاولة بعد قليل."
            : "The AI model is temporarily busy due to high demand. Please try again shortly.";
    }

    if (
        lower.includes("openrouter")
        || lower.includes("provider returned error")
        || lower.includes("provider error")
    ) {
        return isArabic.value
            ? "حدث خطأ مؤقت أثناء الاتصال بمزود الذكاء الاصطناعي. يرجى إعادة المحاولة."
            : "A temporary error occurred while connecting to the AI provider. Please try again.";
    }

    if (text.includes("401") || lower.includes("unauthenticated") || lower.includes("unauthorized")) {
        return isArabic.value
            ? "يرجى تسجيل الدخول أولا للمتابعة."
            : "Please sign in first to continue.";
    }

    if (text.includes("422") || lower.includes("validation")) {
        return isArabic.value
            ? "يرجى التأكد من إدخال النص المطلوب بشكل صحيح."
            : "Please make sure the required text is entered correctly.";
    }

    return isArabic.value
        ? "تعذر تنفيذ الطلب الآن. يرجى المحاولة مرة أخرى."
        : "Could not complete the request right now. Please try again.";
}

const createLocalKey = () => `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;

const createIdempotencyKey = () => {
    if (window?.crypto?.randomUUID) return window.crypto.randomUUID();

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === "x" ? random : (random & 0x3) | 0x8;
        return value.toString(16);
    });
};

const safeJsonParse = (value) => {
    if (!value || typeof value !== "string") return null;

    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
};

const metadataFrom = (message = {}) => {
    if (message.metadata && typeof message.metadata === "object") return message.metadata;
    if (typeof message.metadata === "string") return safeJsonParse(message.metadata) || {};
    return {};
};

function extractDetectorContent(userMessage) {
    const text = String(userMessage || "").trim();

    const markerPatterns = [
        /AI-written:\s*([\s\S]+)$/i,
        /ai generated:\s*([\s\S]+)$/i,
        /content:\s*([\s\S]+)$/i,
        /text:\s*([\s\S]+)$/i,
        /النص:\s*([\s\S]+)$/i,
        /المحتوى:\s*([\s\S]+)$/i,
    ];

    for (const pattern of markerPatterns) {
        const match = text.match(pattern);
        if (match?.[1]) {
            return match[1].trim();
        }
    }

    return text;
}

function buildDetectorState(userMessage, currentState = {}) {
    const content = currentState.content || extractDetectorContent(userMessage);

    return {
        content,
        language: currentState.language || "Auto Detect",
        analysis_depth: currentState.analysis_depth || "Medium",
        detection_focus: currentState.detection_focus || "AI writing signals",
        include_score: currentState.include_score ?? true,
        include_evidence: currentState.include_evidence ?? true,
        include_rewrite_tips: currentState.include_rewrite_tips ?? true,
        extra_options: currentState.extra_options?.length
            ? currentState.extra_options
            : ["Be cautious", "Do not claim certainty"],
        last_output: null,
    };
}

function extractHumanizerContent(userMessage) {
    const text = String(userMessage || "").trim();

    const markerPatterns = [
        /humanize this text in arabic:\s*([\s\S]+)$/i,
        /humanize this text:\s*([\s\S]+)$/i,
        /humanize:\s*([\s\S]+)$/i,
        /content:\s*([\s\S]+)$/i,
        /text:\s*([\s\S]+)$/i,
        /أنسن هذا النص:\s*([\s\S]+)$/i,
        /حوّل هذا النص:\s*([\s\S]+)$/i,
        /حول هذا النص:\s*([\s\S]+)$/i,
        /النص:\s*([\s\S]+)$/i,
        /المحتوى:\s*([\s\S]+)$/i,
    ];

    for (const pattern of markerPatterns) {
        const match = text.match(pattern);
        if (match?.[1]) return match[1].trim();
    }

    return text;
}

function buildHumanizerState(userMessage, currentState = {}) {
    const text = String(userMessage || "").trim();
    const content = currentState.content || extractHumanizerContent(text);
    const countMatch = text.match(/(?:generate|write|create|اكتب|أنشئ|ولد)\s+(\d+)/i);
    const resultsCount = Math.max(1, Math.min(5, Number(currentState.results_count || countMatch?.[1] || 1)));
    const hasArabic = /arabic|عربي|العربية|بالعربي/i.test(text) || /[\u0600-\u06FF]/.test(content);

    return {
        content,
        language: currentState.language && currentState.language !== "Auto Detect"
            ? currentState.language
            : (hasArabic ? "Arabic" : "Auto Detect"),
        tone: currentState.tone || "Natural",
        audience: currentState.audience || "General Audience",
        humanize_level: currentState.humanize_level || "Medium",
        preserve_meaning: currentState.preserve_meaning ?? true,
        preserve_keywords: currentState.preserve_keywords ?? true,
        results_count: resultsCount,
        extra_options: currentState.extra_options?.length
            ? currentState.extra_options
            : ["Improve flow", "Avoid robotic phrasing"],
        last_output: null,
    };
}

function buildChat4ToolState(subToolId, userMessage, currentState = {}) {
    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) {
        return buildHumanizerState(userMessage, currentState);
    }

    return buildDetectorState(userMessage, currentState);
}

function buildChat4Payload(messageText, conversation) {
    const subToolId = activeSubToolId.value;
    const config = CHAT4_TOOLS[subToolId] || CHAT4_TOOLS[DETECTOR_SUB_TOOL_ID];
    const requestState = buildChat4ToolState(subToolId, messageText, {
        ...toolState.value,
        content: null,
    });

    return {
        user_id: conversation?.user_id || null,
        sub_tool_id: subToolId,
        conversation_uuid: conversation?.uuid,
        user_message: messageText,
        content: messageText,
        tool: config.tool_key,
        tool_key: config.tool_key,
        model_key: config.model_key,
        state: requestState,
        debug: false,
        idempotency_key: createIdempotencyKey(),
    };
}

function normalizeResultText(value) {
    if (value && typeof value === "object") {
        return normalizeResultText(value.text || value.content || value.output || value.message || "");
    }

    const text = String(value || "").trim();

    if (!text || text === "[object Object]") {
        return "";
    }

    const parsed = safeJsonParse(text.replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/i, ""));
    if (parsed && typeof parsed === "object") {
        const nestedResults = Array.isArray(parsed.results) ? parsed.results : [];
        if (nestedResults.length) {
            return nestedResults
                .map((item) => normalizeResultText(item?.text || item?.content || item?.output || ""))
                .filter(Boolean)
                .join("\n\n");
        }

        return normalizeResultText(parsed.text || parsed.content || parsed.output || parsed.message || "");
    }

    if (
        text.includes("OpenRouter")
        || text.includes("https://openrouter.ai")
        || text.includes("Provider returned error")
    ) {
        return "";
    }

    return text;
}

function normalizeChat4Response(response) {
    const results = Array.isArray(response?.results) ? response.results : [];

    if (results.length) {
        return results.map((item, index) => ({
            id: item.id || index + 1,
            text: normalizeResultText(item.text || item.content || item.output || ""),
            title: item.title || null,
            subject: item.subject || null,
            meta: item.meta && typeof item.meta === "object" ? item.meta : {},
        })).filter((item) => item.text.trim());
    }

    if (response?.state?.last_output) {
        return [{
            id: 1,
            text: normalizeResultText(response.state.last_output),
            title: null,
            subject: null,
            meta: {},
        }].filter((item) => item.text.trim());
    }

    return [];
}

const unwrapApiData = (response) => response?.data || response || {};

const normalizeStateFromResponse = (state = {}, subToolId = activeSubToolId.value) => buildChat4ToolState(
    subToolId,
    state.content || "",
    {
        ...createDefaultStateForTool(subToolId),
        ...(state && typeof state === "object" ? state : {}),
    }
);

const mapMessage = (message = {}, index = 0) => {
    const meta = metadataFrom(message);
    const responseResults = normalizeChat4Response({
        results: meta.normalized_results || meta.results || message.results || [],
        state: meta.state || message.state || {},
    });
    const role = message.role || "assistant";

    return {
        localKey: message.localKey || message.id || `${role}-${index}-${createLocalKey()}`,
        id: message.id || null,
        role,
        content: String(message.content || ""),
        is_error: Boolean(message.is_error),
        results: role === "assistant" ? responseResults : [],
        responseState: meta.state || message.state || null,
        metadata: meta,
    };
};

const formatConversation = (conversation = {}) => {
    const uuid = conversation.uuid || conversation.conversation_uuid || "";
    const firstUserMessage = Array.isArray(conversation.message)
        ? conversation.message.find((item) => item.role === "user")?.content
        : "";

    return {
        ...conversation,
        uuid,
        user_id: conversation.user_id || null,
        sub_tool_id: Number(conversation.sub_tool_id || DETECTOR_SUB_TOOL_ID),
        title: conversation.title || firstUserMessage || pageTitle.value,
    };
};

const stateStorageKey = (uuid = activeConversation.value?.uuid || route.params.uuid || "draft") =>
    `chat4-tool-state:${activeSubToolId.value}:${uuid}`;

const persistState = (uuid = activeConversation.value?.uuid || route.params.uuid || "draft") => {
    sessionStorage.setItem(stateStorageKey(uuid), JSON.stringify(toolState.value));
};

const restoreState = (uuid = route.params.uuid || "draft") => {
    const stored = safeJsonParse(sessionStorage.getItem(stateStorageKey(uuid)) || "");
    toolState.value = normalizeStateFromResponse(stored || createDefaultStateForTool(activeSubToolId.value), activeSubToolId.value);
};

const hydrateStateFromMessages = (rows = []) => {
    const latest = [...rows]
        .reverse()
        .map(mapMessage)
        .find((message) => message.role === "assistant" && message.responseState);

    if (latest?.responseState) {
        toolState.value = normalizeStateFromResponse(latest.responseState, activeSubToolId.value);
        persistState();
    }
};

const requireAuth = async () => {
    if (localStorage.getItem("auth_token")) return true;

    errorMessage.value = labels.value.authRequired;
    return false;
};

const loadSubtool = async () => {
    if (!route.params.slug) {
        currentSubtool.value = null;
        return;
    }

    try {
        const response = await homeService.showSubtool(route.params.slug);
        const data = response?.data || response || {};
        const subToolId = Number(data.id || data.sub_tool_id || 0);

        currentSubtool.value = {
            ...data,
            id: CHAT4_SUB_TOOL_IDS.includes(subToolId) ? subToolId : DETECTOR_SUB_TOOL_ID,
        };
    } catch {
        currentSubtool.value = null;
    }
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
    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = "auto";
        }
    });
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
            .filter((conversation) => Number(conversation.sub_tool_id) === activeSubToolId.value)
            .map(formatConversation);
    } finally {
        loadingConversations.value = false;
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
            if (existingIndex >= 0) {
                conversations.value[existingIndex] = activeConversation.value;
            }
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
    await router.replace(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4/${conversation.uuid}`);

    return conversation;
};

const sendMessage = async () => {
    const text = String(userMessage.value || "").trim();
    if (!text || isSending.value || isAssistantTyping.value) return;

    errorMessage.value = "";
    isSending.value = true;

    try {
        const conversation = await ensureConversation();
        if (!conversation?.uuid) return;

        const payload = buildChat4Payload(text, conversation);

        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "user",
            content: text,
            created_at: new Date().toISOString(),
        }));

        toolState.value = payload.state;
        isAssistantTyping.value = true;
        userMessage.value = "";
        resetTextarea();
        persistState(conversation.uuid);
        await scrollToBottom();

        const response = await chatServices.sendMessage(payload);
        const directResponse = unwrapApiData(response);
        const results = normalizeChat4Response(directResponse);

        isAssistantTyping.value = false;

        if (!results.length) {
            throw new Error(labels.value.genericError);
        }

        const responseState = normalizeStateFromResponse(directResponse.state || payload.state, payload.sub_tool_id);
        toolState.value = responseState;
        persistState(conversation.uuid);

        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "assistant",
            content: results.map((item) => item.text).join("\n\n"),
            results,
            state: responseState,
            metadata: {
                type: directResponse.type || "result",
                title: getResultTitle(payload.sub_tool_id),
                tool: payload.tool,
                tool_key: payload.tool_key,
                model_key: payload.model_key,
                sub_tool_id: payload.sub_tool_id,
                state: responseState,
                results,
                normalized_results: results,
                request_payload: payload,
                count: results.length,
            },
            created_at: new Date().toISOString(),
        }));

        await scrollToBottom();
    } catch (error) {
        isAssistantTyping.value = false;
        const friendlyMessage = cleanErrorMessage(error);

        errorMessage.value = "";
        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "assistant",
            is_error: true,
            content: friendlyMessage,
            created_at: new Date().toISOString(),
        }));
        await scrollToBottom();
    } finally {
        isSending.value = false;
        isAssistantTyping.value = false;
    }
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
        toolState.value = createDefaultStateForTool(activeSubToolId.value);
        persistState(conversation.uuid);
        sidebarOpen.value = false;

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4/${conversation.uuid}`);
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

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4/${conversation.uuid}`);
    sidebarOpen.value = false;
};

const deleteConversation = async (conversation) => {
    if (!conversation?.uuid || deletingUuid.value) return;

    deletingUuid.value = conversation.uuid;

    try {
        await chatServices.deleteConversation(conversation.uuid);
        conversations.value = conversations.value.filter((item) => item.uuid !== conversation.uuid);

        if (activeConversation.value?.uuid === conversation.uuid) {
            activeConversation.value = null;
            messages.value = [];
            toolState.value = createDefaultStateForTool(activeSubToolId.value);
            await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4`);
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

const toggleExtraOption = (option) => {
    const selected = Array.isArray(toolState.value.extra_options)
        ? toolState.value.extra_options
        : [];

    toolState.value.extra_options = selected.includes(option)
        ? selected.filter((item) => item !== option)
        : [...selected, option];
};

const applyOptions = () => {
    toolState.value = buildChat4ToolState(activeSubToolId.value, toolState.value.content || "", toolState.value);
    persistState();
    errorMessage.value = "";
    if (optionsPanelRef.value) {
        optionsPanelRef.value.open = false;
    }
};

const resetOptions = () => {
    toolState.value = createDefaultStateForTool(activeSubToolId.value);
    persistState();
};

const copyText = async (text, key) => {
    const value = String(text || "").trim();
    if (!value) return;

    await navigator.clipboard.writeText(value);
    copiedKey.value = key;

    window.setTimeout(() => {
        copiedKey.value = "";
    }, 1200);
};

const initialize = async () => {
    locale.value = homeService.getLang();
    await loadSubtool();
    restoreState(route.params.uuid || "draft");
    await loadConversations();

    if (route.params.uuid) {
        await loadConversationDetails(route.params.uuid);
    }
};

const handleLanguageChange = async () => {
    locale.value = homeService.getLang();
};

onMounted(async () => {
    await initialize();
    window.addEventListener("lang-changed", handleLanguageChange);
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLanguageChange);
});

watch(
    () => route.params.uuid,
    async (uuid, previousUuid) => {
        if (uuid === previousUuid) return;

        restoreState(uuid || "draft");
        await loadConversationDetails(uuid);
    }
);

watch(activeSubToolId, (id, previousId) => {
    if (!id || id === previousId) return;

    toolState.value = createDefaultStateForTool(id);
    persistState(route.params.uuid || "draft");
});

watch(
    () => route.params.slug,
    async (slug, previousSlug) => {
        if (slug === previousSlug) return;

        activeConversation.value = null;
        messages.value = [];
        await loadSubtool();
        toolState.value = createDefaultStateForTool(activeSubToolId.value);
        await loadConversations();
    }
);
</script>

<style scoped>
.detector-chat {
    --navy: #0d4d97;
    --blue: #1f87c9;
    --cyan: #35b8dc;
    --ink: #15324b;
    --muted: #687b8e;
    --line: #d8e6f7;
    min-height: calc(100vh - 70px);
    display: grid;
    grid-template-columns: 290px minmax(0, 1fr);
    background: #f5f8fc;
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
    opacity: 0.6;
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

.spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    margin-inline-end: 8px;
    border: 2px solid #c9ddec;
    border-top-color: var(--navy);
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
    vertical-align: middle;
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

.tool-badges span {
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
    border-radius: 8px;
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

.message-body.card-shell {
    max-width: min(780px, 85%);
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.message-content :deep(p) {
    margin: 0 0 0.75em;
    line-height: 1.75;
}

.message-content :deep(p:last-child) {
    margin-bottom: 0;
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
    background: #0d4d97;
    display: inline-block;
    animation: typing-bounce 0.8s infinite ease-in-out;
}

.animation-delay-150 {
    animation-delay: 0.15s;
}

.animation-delay-300 {
    animation-delay: 0.3s;
}

.ai-response-card {
    overflow: hidden;
    min-width: min(620px, 68vw);
    border: 1px solid #d6e9f4;
    border-radius: 12px;
    background: #fbfdff;
    box-shadow: 0 10px 28px rgba(18, 63, 109, 0.08);
}

.ai-response-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 14px;
    border-bottom: 1px solid #e2eef5;
    background: #f0f8fc;
}

.ai-response-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--navy);
    font-size: 14px;
    font-weight: 800;
}

.copy-card-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid #cfe3ef;
    border-radius: 8px;
    color: var(--blue);
    background: #fff;
    font-size: 12px;
}

.copy-card-button:hover {
    border-color: var(--blue);
    background: #f7fcff;
}

.ai-response-content {
    padding: 16px;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.9;
    white-space: normal;
}

.ai-response-content :deep(p) {
    margin: 0 0 0.9em;
}

.ai-response-content :deep(p:last-child) {
    margin-bottom: 0;
}

.ai-response-content :deep(ul),
.ai-response-content :deep(ol) {
    margin: 0.75em 0;
    padding-inline-start: 22px;
}

.ai-response-content :deep(li) {
    margin-bottom: 0.35em;
}

.ai-score-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin: 0 16px 14px;
    padding: 12px 14px;
    border-radius: 10px;
    color: var(--navy);
    background: #eef6ff;
    font-size: 14px;
    font-weight: 700;
}

.ai-score-box strong {
    font-size: 18px;
}

.ai-meta-section {
    margin: 0 16px 16px;
    padding: 13px 14px;
    border: 1px solid #e2eef5;
    border-radius: 10px;
    background: #fff;
}

.ai-meta-section h4 {
    margin: 0 0 8px;
    color: var(--navy);
    font-size: 14px;
}

.ai-meta-section ul {
    margin: 0;
    padding-inline-start: 20px;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.75;
}

.clean-error-card {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    max-width: min(620px, 75vw);
    padding: 13px 15px;
    border: 1px solid #f1b9b9;
    border-radius: 12px;
    color: #8b2525;
    background: #fff4f4;
    font-size: 14px;
    line-height: 1.7;
}

.clean-error-card i {
    margin-top: 3px;
    flex: 0 0 auto;
}

.detector-result-list {
    display: grid;
    gap: 12px;
    min-width: min(680px, 68vw);
}

.detector-copy-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.detector-copy-bar button,
.result-header button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid #cfe3ef;
    border-radius: 8px;
    color: var(--blue);
    background: #fff;
}

.detector-result-card {
    overflow: hidden;
    border: 1px solid #d6e9f4;
    border-radius: 8px;
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

.result-subject {
    display: inline-block;
    margin: 12px 15px 0;
    padding: 4px 8px;
    border-radius: 999px;
    color: var(--blue);
    background: #eaf6fc;
}

.score-box {
    margin: 14px 15px 0;
    padding: 11px 13px;
    border-radius: 8px;
    color: var(--navy);
    background: #eef6ff;
    font-size: 14px;
    font-weight: 800;
}

.result-text {
    margin: 0;
    padding: 15px;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-line;
}

.meta-list {
    margin: 0 15px 15px;
}

.meta-list h4 {
    margin: 0 0 8px;
    color: var(--navy);
    font-size: 14px;
}

.meta-list ul {
    margin: 0;
    padding-inline-start: 20px;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.7;
}

.composer {
    padding: 14px max(24px, calc((100% - 900px) / 2)) 18px;
    border-top: 1px solid var(--line);
    background: #fff;
}

.options-panel {
    margin-bottom: 10px;
    border: 1px solid var(--line);
    border-radius: 8px;
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

.options-grid label,
.checkbox-field {
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
    border-radius: 8px;
    color: var(--ink);
    background: #fff;
    outline: none;
}

.options-grid input:focus,
.options-grid select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.1);
}

.wide {
    grid-column: span 2;
}

.checkbox-field {
    margin: 0;
    padding: 10px;
    border: 1px solid #e1edf6;
    border-radius: 8px;
    background: #fff;
}

.checkbox-field legend {
    padding: 0 4px;
    color: var(--navy);
    font-weight: 700;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--ink);
}

.options-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.options-actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid #cfe3ef;
    border-radius: 8px;
}

.options-submit-button {
    color: #fff;
    background: var(--navy);
}

.options-reset-button {
    color: var(--blue);
    background: #fff;
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
    border-radius: 8px;
    color: #8b2525;
    background: #fff4f4;
}

.mobile-only,
.sidebar-overlay {
    display: none;
}

@keyframes typing-bounce {
    0%, 80%, 100% {
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

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 900px) {
    .detector-chat {
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

    .detector-result-list {
        min-width: 0;
    }

    .ai-response-card {
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

    .message-body.card-shell,
    .ai-response-card {
        width: 100%;
        min-width: 0;
    }

    .ai-response-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .clean-error-card {
        max-width: 100%;
    }

    .options-grid {
        grid-template-columns: 1fr;
    }

    .wide {
        grid-column: auto;
    }

    .options-actions {
        justify-content: stretch;
    }

    .options-actions button {
        flex: 1;
    }

    .welcome-card {
        padding: 28px 20px;
    }
}
</style>
