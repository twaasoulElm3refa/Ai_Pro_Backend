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

            <div v-else class="conversation-list history-list">
                <div
                    v-for="conversation in conversations"
                    :key="conversation.uuid"
                    class="conversation-item history-item"
                    :class="{ active: conversation.uuid === activeConversation?.uuid }"
                >
                    <button type="button" class="conversation-open history-item-main" @click="openConversation(conversation)">
                        <i class="bi bi-chat-left-text"></i>

                        <div class="history-item-info">
                            <span class="history-item-title">{{ conversation.title }}</span>
                        </div>
                    </button>

                    <button
                        type="button"
                        class="conversation-delete history-delete"
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
                                <template v-if="isBusinessNameMessage(message)">
                                    <div class="business-response-card">
                                        <div class="ai-response-header">
                                            <div class="ai-response-title">
                                                <i class="bi bi-stars"></i>
                                                <span>{{ message.metadata?.title || labels.businessNameResultTitle }}</span>
                                            </div>

                                            <button
                                                type="button"
                                                class="copy-card-button"
                                                @click="copyText(getMessageText(message), `msg-${message.localKey}`)"
                                            >
                                                <i class="bi bi-copy"></i>
                                                {{ copiedKey === `msg-${message.localKey}` ? labels.copied : labels.copyAll }}
                                            </button>
                                        </div>

                                        <div class="business-result-list">
                                            <article
                                                v-for="(item, itemIndex) in message.results"
                                                :key="`${message.localKey}-${item.id || itemIndex}`"
                                                class="business-result-card"
                                            >
                                                <div class="result-header">
                                                    <div class="result-title-stack">
                                                        <strong>{{ item.title || `${labels.businessNameResultTitle} ${itemIndex + 1}` }}</strong>
                                                        <span>{{ item.subject || labels.businessNameResultTitle }}</span>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        @click="copyText(getResultCopyText(item, getMessageToolId(message)), `result-${message.localKey}-${item.id || itemIndex}`)"
                                                    >
                                                        <i class="bi bi-copy"></i>
                                                        {{ copiedKey === `result-${message.localKey}-${item.id || itemIndex}` ? labels.copied : labels.copyResult }}
                                                    </button>
                                                </div>

                                                <div class="business-result-body">
                                                    <p class="business-name">{{ item.text }}</p>
                                                    <p v-if="item.meta?.slogan" class="business-slogan">{{ item.meta.slogan }}</p>

                                                    <div v-if="getBusinessDomains(item).length" class="business-domain-section">
                                                        <span class="business-domain-label">{{ labels.domainIdeas }}</span>
                                                        <div class="business-domain-list">
                                                            <span
                                                                v-for="domain in getBusinessDomains(item)"
                                                                :key="domain"
                                                                class="domain-chip"
                                                            >
                                                                {{ domain }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        </div>
                                    </div>
                                </template>

                                <div v-else class="ai-response-card">
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

                                    <div class="ai-response-content" v-html="formatMessage(getMessageText(message))"></div>

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
                        <template v-if="isBusinessNameTool">
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
                                    <option>Creative</option>
                                    <option>Professional</option>
                                    <option>Friendly</option>
                                    <option>Luxury</option>
                                    <option>Modern</option>
                                    <option>Simple</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.nameStyle }}</span>
                                <select v-model="toolState.name_style">
                                    <option>Brandable</option>
                                    <option>Descriptive</option>
                                    <option>Short</option>
                                    <option>Modern</option>
                                    <option>Arabic</option>
                                    <option>English</option>
                                    <option>Tech Style</option>
                                </select>
                            </label>

                            <label>
                                <span>{{ labels.industry }}</span>
                                <input v-model="toolState.industry" type="text">
                            </label>

                            <label>
                                <span>{{ labels.targetAudience }}</span>
                                <input v-model="toolState.target_audience" type="text">
                            </label>

                            <label>
                                <span>{{ labels.resultsCount }}</span>
                                <input v-model.number="toolState.results_count" type="number" min="1" max="30">
                            </label>

                            <label class="wide">
                                <span>{{ labels.keywords }}</span>
                                <input
                                    :value="serializeList(toolState.keywords)"
                                    type="text"
                                    placeholder="AI, marketing, tools"
                                    @input="updateListField('keywords', $event.target.value)"
                                >
                            </label>

                            <label class="wide">
                                <span>{{ labels.avoidWords }}</span>
                                <input
                                    :value="serializeList(toolState.avoid_words)"
                                    type="text"
                                    placeholder="cheap, copy, old"
                                    @input="updateListField('avoid_words', $event.target.value)"
                                >
                            </label>

                            <fieldset class="wide checkbox-field">
                                <legend>{{ labels.businessNameOptions }}</legend>
                                <label class="checkbox-option">
                                    <input v-model="toolState.include_slogans" type="checkbox">
                                    <span>{{ labels.includeSlogans }}</span>
                                </label>
                                <label class="checkbox-option">
                                    <input v-model="toolState.include_domain_ideas" type="checkbox">
                                    <span>{{ labels.includeDomainIdeas }}</span>
                                </label>
                            </fieldset>
                        </template>

                        <template v-else-if="isHumanizer">
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
        title_ar: "أنسنة النصوص بالذكاء الاصطناعي",
        title_en: "AI Humanizer",
        subtitle_ar: "تحويل النص إلى صياغة طبيعية وأكثر بشرية",
        subtitle_en: "Rewrite AI-like text into natural human language",
    },
    20: {
        sub_tool_id: 20,
        tool_key: "business_name_generator",
        model_key: "business_name_generator",
        title_ar: "مولد أسماء المشاريع",
        title_en: "Business Name Generator",
        subtitle_ar: "توليد أسماء مشاريع مميزة مع أفكار شعارات ودومينات",
        subtitle_en: "Generate memorable business names with slogan and domain ideas",
    },
};

const CHAT4_SUB_TOOL_IDS = Object.keys(CHAT4_TOOLS).map(Number);
const DETECTOR_SUB_TOOL_ID = 17;
const HUMANIZER_SUB_TOOL_ID = 18;
const BUSINESS_NAME_SUB_TOOL_ID = 20;

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
const isBusinessNameTool = computed(() => Number(activeSubToolId.value) === BUSINESS_NAME_SUB_TOOL_ID);

const labels = computed(() => isArabic.value ? {
    title: activeToolConfig.value.title_ar,
    subtitle: activeToolConfig.value.subtitle_ar,
    newChat: "محادثة جديدة",
    creating: "جارٍ الإنشاء...",
    recent: "المحادثات الأخيرة",
    loading: "جارٍ التحميل...",
    noChats: "لا توجد محادثات بعد",
    deleteChat: "حذف المحادثة",
    loadingConversation: "جارٍ تحميل المحادثة...",
    welcome: isBusinessNameTool.value
        ? "اكتب فكرة مشروعك وسنولد لك أسماء مناسبة مع شعارات قصيرة وأفكار دومينات عند الحاجة."
        : isHumanizer.value
            ? "الصق النص الذي تريد أنسنته، وسنحوله إلى صياغة طبيعية وسلسة مع الحفاظ على المعنى."
            : "الصق النص أو اكتب طلبك، وسنحلل مؤشرات الأسلوب والبنية والتكرار بدون ادعاء اليقين.",
    placeholder: isBusinessNameTool.value
        ? "اكتب فكرة المشروع أو صف النشاط الذي تريد توليد أسماء له..."
        : isHumanizer.value
            ? "الصق النص المراد أنسنته أو اكتب طلبك هنا..."
            : "الصق النص المراد تحليله أو اكتب طلبك هنا...",
    send: "إرسال",
    hint: "Enter للإرسال، وShift + Enter لسطر جديد",
    options: isBusinessNameTool.value
        ? "خيارات الأداة"
        : isHumanizer.value
            ? "خيارات الأداة"
            : "خيارات الأداة",
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
    aiResponseTitle: isBusinessNameTool.value
        ? "أسماء المشاريع المقترحة"
        : isHumanizer.value
            ? "النص بعد الأنسنة"
            : "نتيجة التحليل",
    tone: "النبرة",
    audience: "الجمهور",
    humanizeLevel: "درجة الأنسنة",
    resultsCount: "عدد النتائج",
    preserveMeaning: "الحفاظ على المعنى",
    preserveKeywords: "الحفاظ على الكلمات المفتاحية",
    humanizerOptions: "خيارات الأنسنة",
    humanizerResultTitle: "النص بعد الأنسنة",
    businessNameOptions: "خيارات توليد الأسماء",
    businessNameResultTitle: "أسماء المشاريع المقترحة",
    nameStyle: "أسلوب الاسم",
    industry: "المجال",
    targetAudience: "الجمهور المستهدف",
    keywords: "كلمات مهمة",
    avoidWords: "كلمات يجب تجنبها",
    includeSlogans: "إضافة شعارات قصيرة",
    includeDomainIdeas: "إضافة أفكار دومينات",
    domainIdeas: "أفكار الدومينات",
    score: "درجة احتمال الذكاء",
    signals: "المؤشرات",
    rewriteTips: "نصائح إعادة الصياغة",
    copy: "نسخ",
    copied: "تم النسخ",
    copyResult: "نسخ النتيجة",
    copyAll: "نسخ كل النتائج",
    authRequired: "يجب تسجيل الدخول أولاً.",
    genericError: isBusinessNameTool.value
        ? "تعذر توليد أسماء المشاريع الآن. يرجى المحاولة مرة أخرى."
        : isHumanizer.value
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
    welcome: isBusinessNameTool.value
        ? "Describe your project and we will generate business names, short slogans, and domain ideas when needed."
        : isHumanizer.value
            ? "Paste text to humanize, and we will rewrite it into a natural, smooth version while preserving the meaning."
            : "Paste text or ask for an analysis. The detector reviews style, structure, repetition, and specificity without claiming certainty.",
    placeholder: isBusinessNameTool.value
        ? "Describe the business idea or startup you want names for..."
        : isHumanizer.value
            ? "Paste text to humanize or type your request..."
            : "Paste text to analyze or type your request...",
    send: "Send",
    hint: "Enter to send, Shift + Enter for a new line",
    options: isBusinessNameTool.value
        ? "Business name options"
        : isHumanizer.value
            ? "Humanizer options"
            : "Detector options",
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
    aiResponseTitle: isBusinessNameTool.value
        ? "Suggested business names"
        : isHumanizer.value
            ? "Humanized text"
            : "Analysis result",
    tone: "Tone",
    audience: "Audience",
    humanizeLevel: "Humanize level",
    resultsCount: "Results count",
    preserveMeaning: "Preserve meaning",
    preserveKeywords: "Preserve keywords",
    humanizerOptions: "Humanizer options",
    humanizerResultTitle: "Humanized text",
    businessNameOptions: "Business name options",
    businessNameResultTitle: "Suggested business names",
    nameStyle: "Name style",
    industry: "Industry",
    targetAudience: "Target audience",
    keywords: "Important keywords",
    avoidWords: "Words to avoid",
    includeSlogans: "Include slogans",
    includeDomainIdeas: "Include domain ideas",
    domainIdeas: "Domain ideas",
    score: "AI Likelihood Score",
    signals: "Signals",
    rewriteTips: "Rewrite Tips",
    copy: "Copy",
    copied: "Copied",
    copyResult: "Copy result",
    copyAll: "Copy all results",
    authRequired: "Please sign in first.",
    genericError: isBusinessNameTool.value
        ? "Could not generate business names right now. Please try again."
        : isHumanizer.value
            ? "Could not humanize the text right now. Please try again."
            : "Could not analyze the content right now. Please try again.",
});

const examplePrompt = computed(() => isBusinessNameTool.value
    ? "Generate 10 Arabic business names for a startup that sells AI tools for marketers"
    : isHumanizer.value
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
const businessNameExtraOptionChoices = [
    "Easy to remember",
    "Avoid duplicates",
    "Brandable names",
    "Short names",
    "Modern sound",
    "Domain-friendly",
    "Avoid hard pronunciation",
];

const extraOptionChoices = computed(() => {
    if (isBusinessNameTool.value) return businessNameExtraOptionChoices;
    if (isHumanizer.value) return humanizerExtraOptionChoices;
    return detectorExtraOptionChoices;
});

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

function createBusinessNameState() {
    return {
        business_idea: null,
        industry: null,
        target_audience: null,
        language: "Auto Detect",
        tone: "Creative",
        name_style: "Brandable",
        keywords: [],
        avoid_words: [],
        results_count: 10,
        include_slogans: true,
        include_domain_ideas: true,
        extra_options: ["Easy to remember", "Avoid duplicates", "Brandable names"],
        last_output: null,
    };
}

function createDefaultStateForTool(subToolId) {
    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) return createHumanizerState();
    if (Number(subToolId) === BUSINESS_NAME_SUB_TOOL_ID) return createBusinessNameState();
    return createDetectorState();
}

const toolState = ref(createDefaultStateForTool(DETECTOR_SUB_TOOL_ID));
const pageTitle = computed(() => isArabic.value ? activeToolConfig.value.title_ar : activeToolConfig.value.title_en);
const sendDisabled = computed(() => isSending.value || isAssistantTyping.value);

const optionsSummary = computed(() => {
    const state = toolState.value || {};

    if (isBusinessNameTool.value) {
        return [state.language, state.tone, state.name_style, `${state.results_count || 10} results`]
            .filter(Boolean)
            .join(" / ");
    }

    if (isHumanizer.value) {
        return [state.language, state.tone, state.humanize_level, `${state.results_count || 1} result`]
            .filter(Boolean)
            .join(" / ");
    }

    return [state.language, state.analysis_depth, state.detection_focus]
        .filter(Boolean)
        .join(" / ");
});

const typingText = computed(() => {
    if (isBusinessNameTool.value) {
        return isArabic.value ? "جاري الكتابة" : "Generating business names";
    }

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

function getMessageToolId(message) {
    return Number(
        message?.metadata?.sub_tool_id
        || message?.responseState?.sub_tool_id
        || activeSubToolId.value
        || DETECTOR_SUB_TOOL_ID
    );
}

function isBusinessNameMessage(message) {
    return getMessageToolId(message) === BUSINESS_NAME_SUB_TOOL_ID;
}

function getBusinessDomains(item) {
    return Array.isArray(item?.meta?.domain_ideas)
        ? item.meta.domain_ideas.map((domain) => String(domain || "").trim()).filter(Boolean)
        : [];
}

function getResultCopyText(item, toolId = activeSubToolId.value) {
    if (Number(toolId) === BUSINESS_NAME_SUB_TOOL_ID) {
        const parts = [String(item?.text || "").trim()].filter(Boolean);

        if (item?.meta?.slogan) {
            parts.push(`Slogan: ${String(item.meta.slogan).trim()}`);
        }

        const domains = getBusinessDomains(item);
        if (domains.length) {
            parts.push(`Domains: ${domains.join(", ")}`);
        }

        return parts.join("\n");
    }

    return String(item?.text || item?.content || item?.output || "").trim();
}

function getMessageText(message) {
    if (message?.results?.length) {
        const toolId = getMessageToolId(message);

        return message.results
            .map((item) => getResultCopyText(item, toolId))
            .filter(Boolean)
            .join("\n\n");
    }

    return String(message?.content || "");
}

function getResultTitle(subToolId = activeSubToolId.value) {
    if (Number(subToolId) === BUSINESS_NAME_SUB_TOOL_ID) {
        return isArabic.value ? "أسماء المشاريع المقترحة" : "Suggested business names";
    }

    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) {
        return isArabic.value ? "النص بعد الأنسنة" : "Humanized text";
    }

    return isArabic.value ? "نتيجة التحليل" : "Analysis result";
}

function serializeList(list) {
    return Array.isArray(list) ? list.join(", ") : "";
}

function parseCsvList(value) {
    return String(value || "")
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
}

function updateListField(key, value) {
    toolState.value[key] = parseCsvList(value);
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
            ? "يرجى تسجيل الدخول أولاً للمتابعة."
            : "Please sign in first to continue.";
    }

    if (text.includes("422") || lower.includes("validation")) {
        return isArabic.value
            ? "يرجى التأكد من إدخال النص المطلوب بشكل صحيح."
            : "Please make sure the required text is entered correctly.";
    }

    return labels.value.genericError;
}

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
        if (match?.[1]) return match[1].trim();
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

function extractBusinessIdea(userMessage) {
    const text = String(userMessage || "").trim();
    const patterns = [
        /business names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /project names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /startup names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /أسماء\s+(?:مشاريع|مشروع|شركات|شركة)\s+(?:عن|لـ|ل)?\s*([\s\S]+)$/i,
        /اسماء\s+(?:مشاريع|مشروع|شركات|شركة)\s+(?:عن|لـ|ل)?\s*([\s\S]+)$/i,
        /فكرة\s+المشروع:\s*([\s\S]+)$/i,
        /business idea:\s*([\s\S]+)$/i,
    ];

    for (const pattern of patterns) {
        const match = text.match(pattern);
        if (match?.[1]) return match[1].trim();
    }

    return text;
}

function extractRequestedCount(text, fallback = 10) {
    const match = String(text || "").match(/(?:generate|write|create|اكتب|ولد|أنشئ)\s+(\d+)/i);
    return Number(match?.[1] || fallback);
}

function detectLanguageFromText(text) {
    const value = String(text || "");
    if (/arabic|عربي|العربية|بالعربي/i.test(value) || /[\u0600-\u06FF]/.test(value)) {
        return "Arabic";
    }
    if (/english|إنجليزي|الإنجليزية/i.test(value)) {
        return "English";
    }
    return "Auto Detect";
}

function extractBusinessNameMeta(userMessage) {
    const text = String(userMessage || "").trim();

    const audienceMatch =
        text.match(/for\s+([a-zA-Z\s]+)$/i)
        || text.match(/target audience:\s*([^.\n]+)/i)
        || text.match(/الجمهور:\s*([^.\n]+)/i);

    let targetAudience = audienceMatch?.[1]?.trim() || null;

    if (/marketers/i.test(text)) {
        targetAudience = "marketers";
    }

    let industry = null;
    if (/AI tools|artificial intelligence|ذكاء اصطناعي|أدوات ذكاء/i.test(text)) {
        industry = "AI tools";
    }

    return {
        industry,
        target_audience: targetAudience,
    };
}

function extractBusinessKeywords(text) {
    const matches = String(text || "").match(/\b[A-Za-z]{2,}\b/g) || [];
    return [...new Set(matches)].slice(0, 5);
}

function buildBusinessNameState(userMessage, currentState = {}) {
    const text = String(userMessage || "").trim();
    const businessIdea = currentState.business_idea || extractBusinessIdea(text);
    const meta = extractBusinessNameMeta(text);
    const keywords = Array.isArray(currentState.keywords) && currentState.keywords.length
        ? currentState.keywords
        : extractBusinessKeywords(text);

    return {
        business_idea: businessIdea || text,
        industry: currentState.industry || meta.industry || "General",
        target_audience: currentState.target_audience || meta.target_audience || "General Audience",
        language: currentState.language || detectLanguageFromText(text),
        tone: currentState.tone || "Creative",
        name_style: currentState.name_style || "Brandable",
        keywords,
        avoid_words: Array.isArray(currentState.avoid_words) ? currentState.avoid_words : [],
        results_count: Math.max(1, Math.min(30, Number(currentState.results_count || extractRequestedCount(text, 10)))),
        include_slogans: currentState.include_slogans ?? true,
        include_domain_ideas: currentState.include_domain_ideas ?? true,
        extra_options: currentState.extra_options?.length
            ? currentState.extra_options
            : ["Easy to remember", "Avoid duplicates", "Brandable names"],
        last_output: null,
    };
}

function buildChat4ToolState(subToolId, userMessage, currentState = {}) {
    if (Number(subToolId) === BUSINESS_NAME_SUB_TOOL_ID) {
        return buildBusinessNameState(userMessage, currentState);
    }

    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) {
        return buildHumanizerState(userMessage, currentState);
    }

    return buildDetectorState(userMessage, currentState);
}

function getStateSeedText(state = toolState.value) {
    return String(state?.content || state?.business_idea || "");
}

function buildChat4Payload(messageText, conversation) {
    const subToolId = Number(activeSubToolId.value);
    const config = CHAT4_TOOLS[subToolId] || CHAT4_TOOLS[DETECTOR_SUB_TOOL_ID];
    const requestState = buildChat4ToolState(subToolId, messageText, {
        ...toolState.value,
        content: null,
        business_idea: null,
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
        return normalizeResultText(value.text || value.name || value.content || value.output || value.message || "");
    }

    const text = String(value || "").trim();
    if (!text || text === "[object Object]") return "";

    const parsed = safeJsonParse(text.replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/i, ""));
    if (parsed && typeof parsed === "object") {
        const nestedResults = Array.isArray(parsed.results) ? parsed.results : [];
        if (nestedResults.length) {
            return nestedResults
                .map((item) => normalizeResultText(item?.text || item?.name || item?.content || item?.output || ""))
                .filter(Boolean)
                .join("\n\n");
        }

        return normalizeResultText(parsed.text || parsed.name || parsed.content || parsed.output || parsed.message || "");
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
            text: normalizeResultText(item.text || item.name || item.content || item.output || ""),
            title: item.title || null,
            subject: item.subject || null,
            meta: item.meta && typeof item.meta === "object" ? item.meta : {},
        })).filter((item) => item.text.trim());
    }

    if (response?.state?.last_output) {
        return String(response.state.last_output)
            .split("\n")
            .map((line) => line.trim())
            .filter(Boolean)
            .map((text, index) => ({
                id: index + 1,
                text,
                title: null,
                subject: null,
                meta: {},
            }));
    }

    return [];
}

const unwrapApiData = (response) => response?.data || response || {};

const normalizeStateFromResponse = (state = {}, subToolId = activeSubToolId.value) => buildChat4ToolState(
    subToolId,
    state.business_idea || state.content || "",
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
    toolState.value = normalizeStateFromResponse(
        stored || createDefaultStateForTool(activeSubToolId.value),
        activeSubToolId.value
    );
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
        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "assistant",
            is_error: true,
            content: cleanErrorMessage(error),
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
    toolState.value = buildChat4ToolState(activeSubToolId.value, getStateSeedText(toolState.value), toolState.value);
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
    height: calc(100vh - 70px);
    min-height: calc(100vh - 70px);
    display: grid;
    grid-template-columns: 290px minmax(0, 1fr);
    overflow: hidden;
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
    width: 42px;
    height: 42px;
    border-radius: 14px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.icon-button,
.conversation-delete {
    border: 1px solid #d3e2ef;
    background: #fff;
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
    background: rgb(0, 0, 44);
    font-weight: 700;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.new-chat-button:hover:not(:disabled) {
    transform: scale(1.02);
    background: var(--navy);
    box-shadow: 0 20px 36px rgba(18, 63, 109, 0.16);
}

.section-label {
    margin: 18px 0 10px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sidebar-status {
    padding: 18px 10px;
    border: 1px dashed #d8e6f7;
    border-radius: 12px;
    color: var(--muted);
    text-align: center;
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
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    color: var(--navy);
}

.workspace {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}

.workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 24px max(24px, calc((100% - 900px) / 2));
    border-bottom: 1px solid var(--line);
    background: #fff;
}

.workspace-header h1 {
    margin: 4px 0 0;
    font-size: 28px;
    line-height: 1.2;
}

.eyebrow {
    margin: 0;
    color: var(--blue);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.tool-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tool-badges span {
    padding: 6px 10px;
    border: 1px solid #d5e4ef;
    border-radius: 999px;
    color: var(--navy);
    background: #f8fbfe;
    font-size: 12px;
}

.messages {
    flex: 1;
    min-height: 0;
    padding: 26px max(24px, calc((100% - 900px) / 2)) 34px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

.center-status,
.welcome-card {
    display: grid;
    place-items: center;
    text-align: center;
}

.center-status {
    gap: 10px;
    min-height: 220px;
    color: var(--muted);
}

.spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #cfe3ef;
    border-top-color: var(--blue);
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

.welcome-card {
    gap: 16px;
    max-width: 680px;
    margin: 40px auto 0;
    padding: 30px 24px;
    border: 1px solid #d7e6f2;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 16px 36px rgba(18, 63, 109, 0.08);
}

.welcome-card h2,
.welcome-card p {
    margin: 0;
}

.welcome-card p {
    color: var(--muted);
    line-height: 1.8;
}

.suggestion {
    padding: 11px 14px;
    border: 1px dashed #b9d7ed;
    border-radius: 12px;
    color: var(--navy);
    background: #f7fcff;
}

.message-list {
    display: grid;
    gap: 18px;
}

.message-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.message-row.user {
    flex-direction: row-reverse;
}

.avatar {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
    box-shadow: 0 8px 18px rgba(18, 63, 109, 0.18);
}

.message-body {
    min-width: 0;
    max-width: min(760px, 100%);
}

.message-content {
    padding: 13px 15px;
    border: 1px solid #dce7f1;
    border-radius: 16px;
    background: #fff;
    line-height: 1.85;
    box-shadow: 0 12px 26px rgba(18, 63, 109, 0.06);
}

.user .message-content {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.message-content :deep(p) {
    margin: 0 0 0.9em;
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
    padding: 13px 15px;
    border: 1px solid #d8e6f7;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 12px 26px rgba(18, 63, 109, 0.06);
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

.ai-response-card,
.business-response-card {
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

.copy-card-button,
.result-header button {
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

.ai-response-content {
    padding: 16px;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.9;
}

.ai-response-content :deep(p) {
    margin: 0 0 0.9em;
}

.ai-response-content :deep(p:last-child) {
    margin-bottom: 0;
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
    line-height: 1.75;
}

.business-result-list {
    display: grid;
    gap: 12px;
    padding: 14px;
}

.business-result-card {
    overflow: hidden;
    border: 1px solid #d6e9f4;
    border-radius: 10px;
    background: #fff;
}

.result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border-bottom: 1px solid #e2eef5;
    color: var(--navy);
    background: #f8fbfe;
}

.result-title-stack {
    display: grid;
    gap: 2px;
}

.result-title-stack strong {
    font-size: 14px;
}

.result-title-stack span {
    color: var(--muted);
    font-size: 12px;
}

.business-result-body {
    display: grid;
    gap: 12px;
    padding: 16px;
}

.business-name {
    margin: 0;
    color: var(--navy);
    font-size: 22px;
    font-weight: 800;
    line-height: 1.4;
}

.business-slogan {
    margin: 0;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.8;
}

.business-domain-section {
    display: grid;
    gap: 8px;
}

.business-domain-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

.business-domain-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.domain-chip {
    padding: 6px 10px;
    border: 1px solid #d3e6f3;
    border-radius: 999px;
    color: var(--blue);
    background: #eef8fd;
    font-size: 12px;
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

.composer {
    position: sticky;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 40;
    flex-shrink: 0;
    padding: 12px max(24px, calc((100% - 900px) / 2)) 14px;
    border-top: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    box-shadow: 0 -14px 35px rgba(18, 63, 109, 0.08);
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

@media (max-width: 980px) {
    .detector-chat {
        grid-template-columns: 1fr;
    }

    .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: min(320px, 84vw);
        transform: translateX(-105%);
        transition: transform 0.22s ease;
        box-shadow: 0 12px 40px rgba(18, 63, 109, 0.15);
    }

    [dir="rtl"] .sidebar {
        inset: 0 0 0 auto;
        transform: translateX(105%);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .mobile-only,
    .sidebar-overlay {
        display: block;
    }

    .sidebar-overlay {
        position: fixed;
        inset: 0;
        z-index: 10;
        border: 0;
        background: rgba(16, 35, 53, 0.35);
    }

    .workspace-header,
    .messages,
    .composer {
        padding-inline: 18px;
    }

    .workspace-header {
        align-items: flex-start;
    }

    .workspace-header h1 {
        font-size: 24px;
    }

    .tool-badges {
        justify-content: flex-end;
    }

    .options-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .wide {
        grid-column: span 2;
    }

    .ai-response-card,
    .business-response-card {
        min-width: 0;
    }
}

@media (max-width: 640px) {
    .options-grid {
        grid-template-columns: 1fr;
    }

    .wide {
        grid-column: span 1;
    }

    .message-row,
    .message-row.user {
        flex-direction: column;
    }

    .avatar {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
    }

    .message-body {
        max-width: 100%;
    }

    .business-name {
        font-size: 19px;
    }
}
</style>
