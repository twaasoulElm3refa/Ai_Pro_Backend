<template>
    <main class="prompt-chat" :class="{ 'sidebar-collapsed': desktopSidebarCollapsed }"
        :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-stars"></i></span>
                <div>
                    <strong>{{ pageTitle }}</strong>
                </div>
                <button class="icon-button sidebar-close-toggle" type="button"
                    :aria-label="isArabic ? 'قفل قائمة المحادثات' : 'Close conversations sidebar'"
                    @click="closeSidebar">
                    <i class="bi bi-layout-sidebar-inset-reverse"></i>
                </button>
            </div>

            <button class="new-chat-button" type="button" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                {{ creatingConversation ? copy.creating : copy.newChat }}
            </button>

            <p class="section-label">{{ copy.recent }}</p>

            <div v-if="loadingConversations" class="sidebar-status">{{ copy.loading }}</div>
            <div v-else-if="conversations.length === 0" class="sidebar-status">{{ copy.noChats }}</div>

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
                        :aria-label="copy.deleteChat"
                        @click="deleteConversation(conversation)"
                    >
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
                    {{ copy.loadingConversation }}
                </div>

                <div v-else-if="messages.length === 0" class="welcome-card">
                    <span class="welcome-icon"><i class="bi bi-magic"></i></span>
                    <h2>{{ welcomeTitle }}</h2>
                    <p>{{ subtool.description || welcomeText }}</p>

                    <button
                        v-if="subtool.promptPlaceholder"
                        class="suggestion"
                        type="button"
                        @click="fillPlaceholder"
                    >
                        {{ subtool.promptPlaceholder }}
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
                                <div
                                    v-if="message.content"
                                    class="message-content"
                                    v-html="formatMessage(message.content)"
                                ></div>

                                <div v-if="message.results.length" class="result-list">
                                    <section
                                        v-for="(resultItem, index) in message.results"
                                        :key="index"
                                        class="result-card"
                                    >
                                        <div class="result-header">
                                            <strong>
                                                {{ resultCardTitle(message, resultItem, index) }}
                                            </strong>

                                            <button type="button" @click="copyResult(resultCopyText(resultItem), index)">
                                                <i class="bi bi-copy"></i>
                                                {{ copiedResult === index ? copy.copied : copy.copy }}
                                            </button>
                                        </div>

                                        <small v-if="resultCardSubject(resultItem)" class="result-subject">
                                            {{ resultCardSubject(resultItem) }}
                                        </small>

                                        <div
                                            v-if="resultCardText(resultItem)"
                                            class="result-text"
                                            v-html="formatMessage(resultCardText(resultItem))"
                                        ></div>
                                    </section>
                                </div>

                                <div
                                    v-if="message.role === 'assistant' && message.can_edit_options && !message.is_error"
                                    class="response-actions"
                                >
                                    <button type="button" @click="openToolOptionsForResponse(message)">
                                        <i class="bi bi-sliders"></i>
                                        {{ toolEditCopy.editOptions }}
                                    </button>
                                </div>
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

                <details ref="optionsPanel" class="options-panel">
                    <summary>
                        <span><i class="bi bi-sliders"></i> {{ copy.options }}</span>
                        <span class="options-panel-meta">
                            <span class="options-summary">{{ optionsSummary }}</span>
                            <i class="bi bi-chevron-down options-chevron" aria-hidden="true"></i>
                        </span>
                    </summary>

                    <div v-if="editingToolResponse" class="options-editing">
                        <span>
                            <i class="bi bi-arrow-repeat"></i>
                            {{ toolEditCopy.editingOptions }}
                        </span>

                        <div>
                            <button type="button" class="secondary" @click="cancelToolOptionsEdit">
                                {{ toolEditCopy.cancel }}
                            </button>
                            <button
                                type="button"
                                :disabled="sendDisabled"
                                @click="regenerateToolResponse(editingToolResponse, promptState)"
                            >
                                <i :class="sendDisabled ? 'bi bi-hourglass-split' : 'bi bi-arrow-repeat'"></i>
                                {{ toolEditCopy.regenerate }}
                            </button>
                        </div>
                    </div>

                    <div class="options-grid">
                        <label v-for="field in textFields" :key="field.key">
                            <span>{{ field.label }}</span>
                            <input v-model="promptState[field.key]" type="text" :placeholder="field.placeholder">
                        </label>

                        <label>
                            <span>{{ copy.resultsCount }}</span>
                            <input v-model.number="promptState.results_count" type="number" min="1" max="10">
                        </label>

                        <label v-for="field in booleanFields" :key="field.key">
                            <span>{{ field.label }}</span>
                            <select v-model="promptState[field.key]">
                                <option :value="null">{{ copy.defaultValue }}</option>
                                <option :value="true">{{ copy.yes }}</option>
                                <option :value="false">{{ copy.no }}</option>
                            </select>
                        </label>

                        <label class="wide">
                            <span>{{ copy.extraOptions }}</span>
                            <input v-model="extraOptionsText" type="text" :placeholder="copy.extraOptionsPlaceholder">
                        </label>
                    </div>
                </details>

                <div class="input-box">
                    <textarea
                        ref="textareaRef"
                        v-model="userMessage"
                        rows="1"
                        :placeholder="subtool.promptPlaceholder || composerPlaceholder"
                        :disabled="sendDisabled"
                        @input="autoResize"
                        @keydown.enter.exact.prevent="sendMessage"
                    ></textarea>

                    <button
                        type="button"
                        class="send-button"
                        :disabled="sendDisabled || !canSendMessage"
                        :aria-label="copy.send"
                        @click="sendMessage"
                    >
                        <i :class="sendingMessage ? 'bi bi-hourglass-split' : 'bi bi-send-fill'"></i>
                    </button>
                </div>

                <p class="composer-hint">{{ copy.hint }}</p>
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
import { cleanPromptEnhancerText } from "@/utils/promptEnhancerResults";
import {
    createIdeaGeneratorState,
    extractIdeaGeneratorResults,
} from "@/utils/ideaGeneratorResults";
import {
    createHookGeneratorState,
    extractHookGeneratorResults,
} from "@/utils/hookGeneratorResults";

const DEFAULT_SUB_TOOL_ID = 9;

const PROMPT_GENERATOR_SUB_TOOL_ID = 9;
const PROMPT_GENERATOR_TOOL_KEY = "ai_prompt_generator";
const PROMPT_GENERATOR_MODEL_KEY = "prompt_generator";

const PROMPT_ENHANCER_SUB_TOOL_ID = 10;
const PROMPT_ENHANCER_TOOL_KEY = "ai_prompt_enhancer";
const PROMPT_ENHANCER_MODEL_KEY = "prompt_enhancer";

const IDEA_GENERATOR_SUB_TOOL_ID = 11;
const IDEA_GENERATOR_TOOL_KEY = "ai_idea_generator";
const IDEA_GENERATOR_MODEL_KEY = "idea_generator";

const HOOK_GENERATOR_SUB_TOOL_ID = 12;
const HOOK_GENERATOR_TOOL_KEY = "ai_hook_generator";
const HOOK_GENERATOR_MODEL_KEY = "hook_generator";

const STATUS_TEXTS = [
    "Prompt generated successfully.",
    "Prompt generated successfully",
    "تم توليد البرومبت بنجاح.",
    "تم توليد البرومبت بنجاح",
    "Prompt enhanced successfully.",
    "Prompt enhanced successfully",
    "تم تحسين البرومبت بنجاح.",
    "تم تحسين البرومبت بنجاح",
    "Generated successfully.",
    "Generated successfully",
    "Enhanced successfully.",
    "Enhanced successfully",
    "Idea generated successfully.",
    "Idea generated successfully",
    "Ideas generated successfully.",
    "Ideas generated successfully",
    "تم توليد الأفكار بنجاح.",
    "تم توليد الأفكار بنجاح",
    "Hook generated successfully.",
    "Hook generated successfully",
    "Hooks generated successfully.",
    "Hooks generated successfully",
    "تم توليد الهوكات بنجاح.",
    "تم توليد الهوكات بنجاح",
    "Success",
    "success",
];

const createPromptGeneratorState = () => ({
    task: null,
    target_ai_tool: null,
    output_type: null,
    language: null,
    tone: null,
    audience: null,
    prompt_style: null,
    detail_level: null,
    include_constraints: null,
    results_count: null,
    extra_options: [],
    last_output: null,
});

const createPromptEnhancerState = () => ({
    original_prompt: null,
    target_ai_tool: null,
    language: null,
    enhancement_goal: null,
    tone: null,
    output_format: null,
    detail_level: null,
    preserve_intent: null,
    results_count: null,
    extra_options: [],
    last_output: null,
});

const createDefaultToolState = (subToolId = DEFAULT_SUB_TOOL_ID) => {
    if (Number(subToolId) === HOOK_GENERATOR_SUB_TOOL_ID) {
        return createHookGeneratorState();
    }

    if (Number(subToolId) === IDEA_GENERATOR_SUB_TOOL_ID) {
        return createIdeaGeneratorState();
    }

    if (Number(subToolId) === PROMPT_ENHANCER_SUB_TOOL_ID) {
        return createPromptEnhancerState();
    }

    return createPromptGeneratorState();
};

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "en").toLowerCase() === "ar"
);

const copy = computed(() => isArabic.value ? {
    title: "مولد البرومبت",
    enhancerTitle: "محسن البرومبتات",
    ideaGeneratorTitle: "مولد الأفكار",
    hookGeneratorTitle: "مولد الهوكات",
    subtitle: "مساحة عمل مخصصة",
    promptGenerator: "Prompt Generator",
    promptEnhancer: "محسن البرومبتات",
    ideaGenerator: "مولد الأفكار",
    hookGenerator: "مولد الهوكات",
    newChat: "محادثة جديدة",
    creating: "جارٍ الإنشاء...",
    recent: "المحادثات الأخيرة",
    loading: "جارٍ التحميل...",
    noChats: "لا توجد محادثات بعد",
    deleteChat: "حذف المحادثة",
    loadingConversation: "جارٍ تحميل المحادثة...",
    welcomeTitle: "ابدأ محادثتك الآن",
    generatorWelcome: "اكتب فكرتك وسنحولها إلى برومبت احترافي منظم.",
    enhancerWelcome: "اكتب البرومبت الذي تريد تحسينه وسنطوره ليصبح أوضح وأقوى.",
    ideaGeneratorWelcome: "اكتب موضوعك وسنولد لك أفكارًا متنوعة وعملية بعناوين وأوصاف واضحة.",
    hookGeneratorWelcome: "اكتب موضوعك وسنولد لك هوكات قصيرة وقوية ومناسبة للمنصة.",
    welcomeText: "اكتب طلبك وسنجهز لك الرد المناسب حسب الأداة المحددة.",
    result: "Result",
    prompt: "Prompt",
    enhancedPrompt: "البرومبت المحسن",
    idea: "فكرة",
    hook: "هوك",
    copy: "نسخ",
    copied: "تم النسخ",
    tokens: "توكن",
    options: "خيارات الأداة",
    resultsCount: "عدد النتائج",
    constraints: "تضمين قيود",
    preserveIntent: "الحفاظ على نفس الهدف",
    includeTitles: "تضمين العناوين",
    includeDescriptions: "تضمين الأوصاف",
    defaultValue: "افتراضي",
    yes: "نعم",
    no: "لا",
    extraOptions: "خيارات إضافية",
    extraOptionsPlaceholder: "افصل الخيارات بفاصلة",
    placeholder: "اكتب رسالتك هنا...",
    generatorPlaceholder: "اكتب فكرة البرومبت الذي تريد توليده...",
    enhancerPlaceholder: "اكتب البرومبت الذي تريد تحسينه...",
    ideaGeneratorPlaceholder: "اكتب الموضوع أو نوع الأفكار التي تريد توليدها...",
    hookGeneratorPlaceholder: "اكتب الموضوع أو المحتوى الذي تريد توليد هوكات له...",
    send: "إرسال",
    hint: "Enter للإرسال، وShift + Enter لسطر جديد",
    authRequired: "يجب تسجيل الدخول أولًا.",
    genericError: "تعذر تنفيذ الطلب. حاول مرة أخرى.",
} : {
    title: "Prompt Generator",
    enhancerTitle: "Prompt Enhancer",
    ideaGeneratorTitle: "Idea Generator",
    hookGeneratorTitle: "AI Hook Generator",
    subtitle: "Dedicated workspace",
    promptGenerator: "Prompt Generator",
    promptEnhancer: "Prompt Enhancer",
    ideaGenerator: "Idea Generator",
    hookGenerator: "Hook Generator",
    newChat: "New chat",
    creating: "Creating...",
    recent: "Recent conversations",
    loading: "Loading...",
    noChats: "No conversations yet",
    deleteChat: "Delete conversation",
    loadingConversation: "Loading conversation...",
    welcomeTitle: "Start your conversation",
    generatorWelcome: "Write your idea and generate a clear professional prompt.",
    enhancerWelcome: "Paste your prompt and enhance it into a clearer, stronger version.",
    ideaGeneratorWelcome: "Describe your topic and generate distinct ideas with clear titles and descriptions.",
    hookGeneratorWelcome: "Describe your topic and generate short, distinct, scroll-stopping hooks.",
    welcomeText: "Write your request and the selected tool will generate the response.",
    result: "Result",
    prompt: "Prompt",
    enhancedPrompt: "Enhanced Prompt",
    idea: "Idea",
    hook: "Hook",
    copy: "Copy",
    copied: "Copied",
    tokens: "tokens",
    options: "Tool options",
    resultsCount: "Results count",
    constraints: "Include constraints",
    preserveIntent: "Preserve intent",
    includeTitles: "Include titles",
    includeDescriptions: "Include descriptions",
    defaultValue: "Default",
    yes: "Yes",
    no: "No",
    extraOptions: "Extra options",
    extraOptionsPlaceholder: "Separate options with commas",
    placeholder: "Write your message here...",
    generatorPlaceholder: "Describe the prompt you want to generate...",
    enhancerPlaceholder: "Paste the prompt you want to improve...",
    ideaGeneratorPlaceholder: "Describe the ideas you want to generate...",
    hookGeneratorPlaceholder: "Describe the hooks you want to generate...",
    send: "Send",
    hint: "Press Enter to send, Shift + Enter for a new line",
    authRequired: "Please sign in first.",
    genericError: "The request could not be completed. Please try again.",
});

const toolEditCopy = computed(() => isArabic.value ? {
    editOptions: "تعديل الخيارات",
    editingOptions: "تعديل خيارات هذه الاستجابة",
    regenerate: "إعادة التوليد",
    cancel: "إلغاء",
} : {
    editOptions: "Edit options",
    editingOptions: "Editing options for this response",
    regenerate: "Regenerate",
    cancel: "Cancel",
});

const subtool = ref({
    id: DEFAULT_SUB_TOOL_ID,
    name: "",
    description: "",
    promptPlaceholder: "",
    toolKey: "",
    modelKey: "",
    config: {},
});

const activeSubToolId = computed(() => Number(subtool.value.id || DEFAULT_SUB_TOOL_ID));

const toolKeyBadge = computed(() =>
    subtool.value.toolKey
    || subtool.value.config?.tool_key
    || subtool.value.config?.tool
    || ""
);

const modelKeyBadge = computed(() =>
    subtool.value.modelKey
    || subtool.value.config?.model_key
    || ""
);

const isActivePromptGenerator = computed(() =>
    activeSubToolId.value === PROMPT_GENERATOR_SUB_TOOL_ID
    || toolKeyBadge.value === PROMPT_GENERATOR_TOOL_KEY
    || modelKeyBadge.value === PROMPT_GENERATOR_MODEL_KEY
);

const isActivePromptEnhancer = computed(() =>
    activeSubToolId.value === PROMPT_ENHANCER_SUB_TOOL_ID
    || toolKeyBadge.value === PROMPT_ENHANCER_TOOL_KEY
    || modelKeyBadge.value === PROMPT_ENHANCER_MODEL_KEY
);

const isActiveIdeaGenerator = computed(() =>
    activeSubToolId.value === IDEA_GENERATOR_SUB_TOOL_ID
    || toolKeyBadge.value === IDEA_GENERATOR_TOOL_KEY
    || modelKeyBadge.value === IDEA_GENERATOR_MODEL_KEY
);

const isActiveHookGenerator = computed(() =>
    activeSubToolId.value === HOOK_GENERATOR_SUB_TOOL_ID
    || toolKeyBadge.value === HOOK_GENERATOR_TOOL_KEY
    || modelKeyBadge.value === HOOK_GENERATOR_MODEL_KEY
);

const isActiveStructuredTool = computed(() =>
    isActivePromptGenerator.value
    || isActivePromptEnhancer.value
    || isActiveIdeaGenerator.value
    || isActiveHookGenerator.value
);

const isActivePromptTool = isActiveStructuredTool;

const pageTitle = computed(() =>
    subtool.value.name
    || (
        isActiveHookGenerator.value
            ? copy.value.hookGeneratorTitle
            : (
                isActiveIdeaGenerator.value
                    ? copy.value.ideaGeneratorTitle
                    : (isActivePromptEnhancer.value ? copy.value.enhancerTitle : copy.value.title)
            )
    )
);

const toolEyebrow = computed(() => {
    if (isActiveHookGenerator.value) return "Hook Generator";
    if (isActiveIdeaGenerator.value) return "Idea Generator";
    if (isActivePromptEnhancer.value) return "Prompt Enhancer";
    if (isActivePromptGenerator.value) return "Prompt Generator";
    return toolKeyBadge.value || "AI Tool";
});

const welcomeTitle = computed(() => {
    if (isActiveHookGenerator.value) return copy.value.hookGeneratorTitle;
    if (isActiveIdeaGenerator.value) return copy.value.ideaGeneratorTitle;
    if (isActivePromptEnhancer.value) return copy.value.enhancerTitle;
    if (isActivePromptGenerator.value) return copy.value.title;
    return copy.value.welcomeTitle;
});

const welcomeText = computed(() => {
    if (isActiveHookGenerator.value) return copy.value.hookGeneratorWelcome;
    if (isActiveIdeaGenerator.value) return copy.value.ideaGeneratorWelcome;
    if (isActivePromptEnhancer.value) return copy.value.enhancerWelcome;
    if (isActivePromptGenerator.value) return copy.value.generatorWelcome;
    return copy.value.welcomeText;
});

const composerPlaceholder = computed(() => {
    if (isActiveHookGenerator.value) return copy.value.hookGeneratorPlaceholder;
    if (isActiveIdeaGenerator.value) return copy.value.ideaGeneratorPlaceholder;
    if (isActivePromptEnhancer.value) return copy.value.enhancerPlaceholder;
    if (isActivePromptGenerator.value) return copy.value.generatorPlaceholder;
    return copy.value.placeholder;
});

const booleanFields = computed(() => {
    if (isActiveHookGenerator.value) {
        return [];
    }

    if (isActiveIdeaGenerator.value) {
        return [
            { key: "include_titles", label: copy.value.includeTitles },
            { key: "include_descriptions", label: copy.value.includeDescriptions },
        ];
    }

    return [isActivePromptEnhancer.value
        ? { key: "preserve_intent", label: copy.value.preserveIntent }
        : { key: "include_constraints", label: copy.value.constraints }];
});

const textFields = computed(() => {
    if (isActiveHookGenerator.value) {
        return [
            { key: "topic", label: isArabic.value ? "الموضوع" : "Topic", placeholder: "AI content marketing" },
            { key: "platform", label: isArabic.value ? "المنصة" : "Platform", placeholder: "LinkedIn" },
            { key: "content_type", label: isArabic.value ? "نوع المحتوى" : "Content type", placeholder: "Social post or video" },
            { key: "language", label: isArabic.value ? "اللغة" : "Language", placeholder: "Arabic" },
            { key: "tone", label: isArabic.value ? "النبرة" : "Tone", placeholder: "Engaging" },
            { key: "audience", label: isArabic.value ? "الجمهور" : "Audience", placeholder: "General Audience" },
            { key: "hook_style", label: isArabic.value ? "أسلوب الهوك" : "Hook style", placeholder: "Scroll-stopping" },
            { key: "length", label: isArabic.value ? "الطول" : "Length", placeholder: "Short" },
        ];
    }

    if (isActiveIdeaGenerator.value) {
        return [
            { key: "topic", label: isArabic.value ? "الموضوع" : "Topic", placeholder: "AI tools" },
            { key: "idea_type", label: isArabic.value ? "نوع الأفكار" : "Idea type", placeholder: "Content ideas" },
            { key: "industry", label: isArabic.value ? "المجال" : "Industry", placeholder: "Tech" },
            { key: "audience", label: isArabic.value ? "الجمهور" : "Audience", placeholder: "" },
            { key: "language", label: isArabic.value ? "اللغة" : "Language", placeholder: "" },
            { key: "tone", label: isArabic.value ? "النبرة" : "Tone", placeholder: "" },
            { key: "creativity_level", label: isArabic.value ? "مستوى الإبداع" : "Creativity level", placeholder: "" },
        ];
    }

    if (isActivePromptEnhancer.value) {
        return [
            { key: "original_prompt", label: isArabic.value ? "البرومبت الأصلي" : "Original prompt", placeholder: "" },
            { key: "target_ai_tool", label: isArabic.value ? "أداة الذكاء الاصطناعي" : "Target AI tool", placeholder: "ChatGPT" },
            { key: "language", label: isArabic.value ? "اللغة" : "Language", placeholder: "" },
            { key: "enhancement_goal", label: isArabic.value ? "هدف التحسين" : "Enhancement goal", placeholder: "" },
            { key: "tone", label: isArabic.value ? "النبرة" : "Tone", placeholder: "" },
            { key: "output_format", label: isArabic.value ? "صيغة المخرج" : "Output format", placeholder: "" },
            { key: "detail_level", label: isArabic.value ? "مستوى التفاصيل" : "Detail level", placeholder: "" },
        ];
    }

    return [
        { key: "task", label: isArabic.value ? "المهمة" : "Task", placeholder: "" },
        { key: "target_ai_tool", label: isArabic.value ? "أداة الذكاء الاصطناعي" : "Target AI tool", placeholder: "ChatGPT" },
        { key: "output_type", label: isArabic.value ? "نوع المخرج" : "Output type", placeholder: "" },
        { key: "language", label: isArabic.value ? "اللغة" : "Language", placeholder: "" },
        { key: "tone", label: isArabic.value ? "النبرة" : "Tone", placeholder: "" },
        { key: "audience", label: isArabic.value ? "الجمهور" : "Audience", placeholder: "" },
        { key: "prompt_style", label: isArabic.value ? "أسلوب البرومبت" : "Prompt style", placeholder: "" },
        { key: "detail_level", label: isArabic.value ? "مستوى التفاصيل" : "Detail level", placeholder: "" },
    ];
});

const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const promptState = ref(createDefaultToolState(DEFAULT_SUB_TOOL_ID));
const userMessage = ref("");
const errorMessage = ref("");
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const sendingMessage = ref(false);
const streamingAssistant = ref(false);
const deletingUuid = ref("");
const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const optionsPanel = ref(null);
const eventSource = ref(null);
const copiedResult = ref(null);
const editingToolResponse = ref(null);
const composerDraftBeforeEdit = ref("");

const markdown = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
});

const sendDisabled = computed(() => sendingMessage.value || streamingAssistant.value);

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

const extraOptionsText = computed({
    get: () => Array.isArray(promptState.value.extra_options) ? promptState.value.extra_options.join(", ") : "",
    set: (value) => {
        promptState.value.extra_options = String(value || "")
            .split(",")
            .map((item) => item.trim())
            .filter(Boolean);
    },
});

const optionsSummary = computed(() => {
    const count = Object.entries(promptState.value)
        .filter(([key, value]) => key !== "last_output" && key !== "extra_options" && value !== null && value !== "")
        .length + (Array.isArray(promptState.value.extra_options) ? promptState.value.extra_options.length : 0);

    return count ? `${count}` : copy.value.defaultValue;
});

const resolvePromptStateMessage = (
    state = promptState.value,
    subToolId = activeSubToolId.value,
    typedText = userMessage.value
) => {
    const typed = String(typedText || "").trim();
    if (typed) return typed;

    const source = isPlainObject(state) ? state : {};
    const preferredKeys = {
        [PROMPT_ENHANCER_SUB_TOOL_ID]: ["original_prompt"],
        [IDEA_GENERATOR_SUB_TOOL_ID]: ["topic"],
        [HOOK_GENERATOR_SUB_TOOL_ID]: ["topic"],
        [PROMPT_GENERATOR_SUB_TOOL_ID]: ["task"],
    }[Number(subToolId)] || [];

    return [...preferredKeys, ...textFields.value.map((field) => field.key)]
        .map((key) => String(source[key] || "").trim())
        .find(Boolean) || "";
};

const canSendMessage = computed(() => Boolean(resolvePromptStateMessage()));

useSeoMeta({
    title: computed(() => `${pageTitle.value} | Ai Pro`),
    description: computed(() =>
        subtool.value.description
        || (isArabic.value
            ? "مساحة محادثة مخصصة لاستخدام أدوات الذكاء الاصطناعي."
            : "Dedicated workspace for AI tools.")
    ),
});

const formatMessage = (value = "") => {
    const rendered = markdown.render(String(value || ""));
    return DOMPurify.sanitize(rendered, { USE_PROFILES: { html: true } });
};

const resultCardText = (result) =>
    isPlainObject(result) ? String(result.text || "") : String(result || "");

const resultCardSubject = (result) =>
    isPlainObject(result) ? String(result.subject || "") : "";

const resultCardTitle = (message, result, index) => {
    if (isPlainObject(result) && result.title) {
        return String(result.title);
    }

    const baseTitle = message.resultTitle || copy.value.result;
    return message.results.length > 1 ? `${baseTitle} ${index + 1}` : baseTitle;
};

const resultCopyText = (result) => {
    if (!isPlainObject(result)) return String(result || "");

    return [result.title, result.text]
        .map((value) => String(value || "").trim())
        .filter(Boolean)
        .join("\n\n");
};

const now = () => new Date().toISOString();
const createLocalKey = () => `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;

const debugClone = (value) => {
    try {
        return JSON.parse(JSON.stringify(value));
    } catch {
        return value;
    }
};

const debugCurrentTool = () => ({
    activeSubToolId: activeSubToolId.value,
    isPromptGenerator: isActivePromptGenerator.value,
    isPromptEnhancer: isActivePromptEnhancer.value,
    isIdeaGenerator: isActiveIdeaGenerator.value,
    isHookGenerator: isActiveHookGenerator.value,
    toolKeyBadge: toolKeyBadge.value,
    modelKeyBadge: modelKeyBadge.value,
    subtool: debugClone(subtool.value),
});

const promptStateKey = (uuid) => `tool-state:${activeSubToolId.value}:${uuid || "new"}`;

const isPlainObject = (value) => value !== null && typeof value === "object" && !Array.isArray(value);

const safeJsonParse = (value) => {
    if (typeof value !== "string") return null;

    const text = value.trim();

    if (!text || (!text.startsWith("{") && !text.startsWith("["))) {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
};

const parseMaybeJson = (value) => {
    if (isPlainObject(value)) return value;

    if (typeof value === "string") {
        const parsed = safeJsonParse(value);
        return isPlainObject(parsed) ? parsed : {};
    }

    return {};
};

const unwrapPayload = (source) => {
    if (!source || typeof source !== "object") return {};

    let payload = source.data && typeof source.data === "object"
        ? source.data
        : source;

    if (payload.response && typeof payload.response === "object") {
        payload = payload.response;
    }

    return payload;
};

const isStatusText = (value) => {
    const text = String(value || "").trim();

    if (!text) return true;

    return STATUS_TEXTS.some((status) => text.toLowerCase() === status.toLowerCase());
};

const isPromptToolPayload = (payload = {}, fallbackText = "") => {
    const raw = unwrapPayload(payload);
    const metadata = isPlainObject(raw.metadata) ? raw.metadata : {};
    const data = { ...raw, ...metadata };

    return Number(data.sub_tool_id) === PROMPT_GENERATOR_SUB_TOOL_ID
        || Number(data.sub_tool_id) === PROMPT_ENHANCER_SUB_TOOL_ID
        || Number(data.sub_tool_id) === IDEA_GENERATOR_SUB_TOOL_ID
        || Number(data.sub_tool_id) === HOOK_GENERATOR_SUB_TOOL_ID
        || data.tool === PROMPT_GENERATOR_TOOL_KEY
        || data.tool === PROMPT_ENHANCER_TOOL_KEY
        || data.tool === IDEA_GENERATOR_TOOL_KEY
        || data.tool === HOOK_GENERATOR_TOOL_KEY
        || data.model_key === PROMPT_GENERATOR_MODEL_KEY
        || data.model_key === PROMPT_ENHANCER_MODEL_KEY
        || data.model_key === IDEA_GENERATOR_MODEL_KEY
        || data.model_key === HOOK_GENERATOR_MODEL_KEY
        || isActivePromptTool.value
        || String(fallbackText || "").includes(PROMPT_GENERATOR_TOOL_KEY)
        || String(fallbackText || "").includes(PROMPT_ENHANCER_TOOL_KEY)
        || String(fallbackText || "").includes(IDEA_GENERATOR_TOOL_KEY)
        || String(fallbackText || "").includes(HOOK_GENERATOR_TOOL_KEY);
};

const isPromptEnhancerPayload = (payload = {}) => {
    const raw = unwrapPayload(payload);
    const metadata = isPlainObject(raw.metadata) ? raw.metadata : {};
    const data = { ...raw, ...metadata };

    return Number(data.sub_tool_id) === PROMPT_ENHANCER_SUB_TOOL_ID
        || data.tool === PROMPT_ENHANCER_TOOL_KEY
        || data.model_key === PROMPT_ENHANCER_MODEL_KEY
        || isActivePromptEnhancer.value;
};

const isIdeaGeneratorPayload = (payload = {}) => {
    const raw = unwrapPayload(payload);
    const metadata = isPlainObject(raw.metadata) ? raw.metadata : {};
    const data = { ...raw, ...metadata };

    return Number(data.sub_tool_id) === IDEA_GENERATOR_SUB_TOOL_ID
        || data.tool === IDEA_GENERATOR_TOOL_KEY
        || data.model_key === IDEA_GENERATOR_MODEL_KEY
        || isActiveIdeaGenerator.value;
};

const isHookGeneratorPayload = (payload = {}) => {
    const raw = unwrapPayload(payload);
    const metadata = isPlainObject(raw.metadata) ? raw.metadata : {};
    const data = { ...raw, ...metadata };

    return Number(data.sub_tool_id) === HOOK_GENERATOR_SUB_TOOL_ID
        || data.tool === HOOK_GENERATOR_TOOL_KEY
        || data.model_key === HOOK_GENERATOR_MODEL_KEY
        || isActiveHookGenerator.value;
};

const extractTextFromResultItem = (item, depth = 0) => {
    if (depth > 4 || item === null || item === undefined) return [];

    if (typeof item === "string") {
        if (isStatusText(item)) return [];

        const parsed = safeJsonParse(item);

        if (parsed) {
            return extractPromptToolTextsFromAny(parsed, depth + 1);
        }

        return [item];
    }

    if (Array.isArray(item)) {
        return item.flatMap((entry) => extractTextFromResultItem(entry, depth + 1));
    }

    if (isPlainObject(item)) {
        if (typeof item.text === "string") {
            return extractTextFromResultItem(item.text, depth + 1);
        }

        if (Array.isArray(item.results)) {
            return item.results.flatMap((entry) => extractTextFromResultItem(entry, depth + 1));
        }

        if (typeof item.last_output === "string") {
            return extractTextFromResultItem(item.last_output, depth + 1);
        }
    }

    return [];
};

const extractPromptToolTextsFromAny = (payload, depth = 0) => {
    if (depth > 4 || payload === null || payload === undefined) return [];

    if (typeof payload === "string") {
        return extractTextFromResultItem(payload, depth + 1);
    }

    if (Array.isArray(payload)) {
        return payload.flatMap((item) => extractTextFromResultItem(item, depth + 1));
    }

    if (!isPlainObject(payload)) return [];

    if (Array.isArray(payload.results) && payload.results.length) {
        return payload.results.flatMap((item) => extractTextFromResultItem(item, depth + 1));
    }

    if (isPlainObject(payload.data)) {
        const fromData = extractPromptToolTextsFromAny(payload.data, depth + 1);
        if (fromData.length) return fromData;
    }

    if (isPlainObject(payload.metadata)) {
        const fromMetadata = extractPromptToolTextsFromAny(payload.metadata, depth + 1);
        if (fromMetadata.length) return fromMetadata;
    }

    if (isPlainObject(payload.state) && payload.state.last_output) {
        return extractTextFromResultItem(payload.state.last_output, depth + 1);
    }

    if (payload.last_output) {
        return extractTextFromResultItem(payload.last_output, depth + 1);
    }

    if (typeof payload.text === "string") {
        return extractTextFromResultItem(payload.text, depth + 1);
    }

    return [];
};

const uniqueCleanTexts = (items = []) => {
    const seen = new Set();

    return items
        .map((item) => cleanPromptEnhancerText(item))
        .filter((item) => item && !isStatusText(item))
        .filter((item) => {
            if (seen.has(item)) return false;
            seen.add(item);
            return true;
        });
};

const extractPromptToolDisplayItems = (source = {}, fallbackText = "") => {
    const payload = unwrapPayload(source);
    const metadata = isPlainObject(payload.metadata) ? payload.metadata : {};
    const merged = { ...payload, ...metadata };
    const type = String(merged.type || "").trim().toLowerCase();

    if (type === "question") {
        const question = String(merged.message || fallbackText || "").trim();
        return question && !isStatusText(question) ? [question] : [];
    }

    const fromPayload = extractPromptToolTextsFromAny(merged);

    if (fromPayload.length) {
        return uniqueCleanTexts(fromPayload);
    }

    const fromFallback = extractPromptToolTextsFromAny(fallbackText);

    return uniqueCleanTexts(fromFallback);
};

const extractGenericResults = (payload = {}) => {
    const items = [];

    if (Array.isArray(payload.results)) {
        payload.results.forEach((item) => {
            if (typeof item === "string") {
                items.push(item);
            } else if (isPlainObject(item) && typeof item.text === "string") {
                items.push(item.text);
            }
        });
    }

    return uniqueCleanTexts(items);
};

const normalizeToolState = (state = {}, subToolId = activeSubToolId.value) => {
    const base = createDefaultToolState(subToolId);
    const source = isPlainObject(state) ? state : {};
    const normalized = { ...base };

    Object.keys(base).forEach((key) => {
        if (key === "extra_options") {
            normalized.extra_options = Array.isArray(source.extra_options) ? source.extra_options : [];
            return;
        }

        normalized[key] = source[key] !== undefined ? source[key] : base[key];
    });

    return normalized;
};

const cloneToolOptions = (options = {}, subToolId = activeSubToolId.value) =>
    debugClone(normalizeToolState(options, subToolId));

const buildToolPayload = (
    tool = subtool.value,
    options = promptState.value,
    text = userMessage.value,
    conversation = activeConversation.value
) => {
    const subToolId = Number(
        tool?.sub_tool_id
        || tool?.tool_id
        || tool?.id
        || activeSubToolId.value
    );

    return {
        user_id: Number(conversation?.user_id) || null,
        sub_tool_id: subToolId,
        conversation_uuid: conversation?.uuid || "",
        user_message: String(text || "").trim(),
        state: cloneToolOptions(options, subToolId),
        debug: false,
    };
};

const persistPromptState = (uuid = activeConversation.value?.uuid) => {
    if (!uuid) return;

    try {
        sessionStorage.setItem(promptStateKey(uuid), JSON.stringify(normalizeToolState(promptState.value)));
    } catch {
        // Session storage is optional.
    }
};

const restorePromptState = (uuid) => {
    if (!uuid) {
        promptState.value = createDefaultToolState(activeSubToolId.value);
        return;
    }

    try {
        const stored = JSON.parse(sessionStorage.getItem(promptStateKey(uuid)) || "null");
        promptState.value = normalizeToolState(stored || {});
    } catch {
        promptState.value = createDefaultToolState(activeSubToolId.value);
    }
};

const normalizeAssistantResponse = (source = {}, fallbackText = "") => {
    const rawPayload = unwrapPayload(source);
    const metadata = isPlainObject(rawPayload.metadata) ? rawPayload.metadata : {};
    const payload = { ...rawPayload, ...metadata };
    const type = String(payload.type || metadata.type || "").trim().toLowerCase();
    const isPromptTool = isPromptToolPayload(payload, fallbackText);
    const isEnhancer = isPromptEnhancerPayload(payload);
    const isIdeaGenerator = isIdeaGeneratorPayload(payload);
    const isHookGenerator = isHookGeneratorPayload(payload);
    const subToolId = Number(
        payload.sub_tool_id
        || payload.tool_id
        || payload.tool_payload?.sub_tool_id
        || activeSubToolId.value
    );

    const state = isPlainObject(payload.state)
        ? normalizeToolState(payload.state, subToolId)
        : null;
    const hasToolOptionsSnapshot = isPlainObject(payload.tool_options)
        || isPlainObject(payload.options)
        || isPlainObject(payload.tool_payload?.state);
    const toolOptionsSource = isPlainObject(payload.tool_options)
        ? payload.tool_options
        : (
            isPlainObject(payload.options)
                ? payload.options
                : (isPlainObject(payload.tool_payload?.state) ? payload.tool_payload.state : state)
        );
    const toolOptions = cloneToolOptions(toolOptionsSource || {}, subToolId);
    const toolPayload = isPlainObject(payload.tool_payload)
        ? debugClone(payload.tool_payload)
        : null;

    if (isPromptTool) {
        const isQuestion = type === "question";
        const displayItems = isQuestion
            ? extractPromptToolDisplayItems(source, fallbackText)
            : (
                isHookGenerator
                    ? extractHookGeneratorResults(source, fallbackText)
                    : (
                        isIdeaGenerator
                            ? extractIdeaGeneratorResults(source, fallbackText)
                            : extractPromptToolDisplayItems(source, fallbackText)
                    )
            );

        return {
            success: payload.success !== false,
            type,
            tool: payload.tool
                || toolKeyBadge.value
                || (
                    isHookGenerator
                        ? HOOK_GENERATOR_TOOL_KEY
                        : (
                            isIdeaGenerator
                                ? IDEA_GENERATOR_TOOL_KEY
                                : (isEnhancer ? PROMPT_ENHANCER_TOOL_KEY : PROMPT_GENERATOR_TOOL_KEY)
                        )
                ),
            provider: payload.provider || null,
            model_key: payload.model_key
                || modelKeyBadge.value
                || (
                    isHookGenerator
                        ? HOOK_GENERATOR_MODEL_KEY
                        : (
                            isIdeaGenerator
                                ? IDEA_GENERATOR_MODEL_KEY
                                : (isEnhancer ? PROMPT_ENHANCER_MODEL_KEY : PROMPT_GENERATOR_MODEL_KEY)
                        )
                ),
            state,
            content: isQuestion ? (displayItems[0] || "") : "",
            results: isQuestion ? [] : displayItems,
            resultTitle: isHookGenerator
                ? copy.value.hook
                : (
                    isIdeaGenerator
                        ? copy.value.idea
                        : (
                            isEnhancer
                                ? (
                                    displayItems.length > 1
                                        ? (isArabic.value ? "النسخة" : "Version")
                                        : copy.value.enhancedPrompt
                                )
                                : copy.value.prompt
                        )
                ),
            usage: isPlainObject(payload.usage) ? payload.usage : null,
            cost: isPlainObject(payload.cost) ? payload.cost : null,
            isPromptTool: true,
            isToolResponse: true,
            isQuestion,
            subToolId,
            toolName: payload.tool_name || pageTitle.value,
            toolOptions,
            toolPayload,
            hasToolOptionsSnapshot,
            canEditOptions: payload.can_edit_options !== false,
        };
    }

    const genericResults = extractGenericResults(payload);
    const content = String(
        payload.content
        || payload.message
        || fallbackText
        || ""
    ).trim();

    return {
        success: payload.success !== false,
        type,
        tool: payload.tool || toolKeyBadge.value || "",
        provider: payload.provider || null,
        model_key: payload.model_key || modelKeyBadge.value || "",
        state,
        content: genericResults.length ? "" : content,
        results: genericResults,
        resultTitle: copy.value.result,
        usage: isPlainObject(payload.usage) ? payload.usage : null,
        cost: isPlainObject(payload.cost) ? payload.cost : null,
        isPromptTool: false,
        isToolResponse: Boolean(payload.tool || payload.model_key || payload.state || payload.tool_options),
        isQuestion: type === "question",
        subToolId,
        toolName: payload.tool_name || pageTitle.value,
        toolOptions,
        toolPayload,
        hasToolOptionsSnapshot,
        canEditOptions: payload.can_edit_options !== false,
    };
};

const shouldHideDuplicateContent = (content, results, isPromptTool) => {
    if (isPromptTool && isStatusText(content)) return true;
    if (isPromptTool && results.length) return true;
    if (isPromptTool && safeJsonParse(content)) return true;
    if (!content || !results.length) return false;

    const normalizedContent = String(content).trim();
    const combinedResults = results
        .map((item) => isPlainObject(item) ? String(item.text || "") : String(item || ""))
        .join("\n\n")
        .trim();

    return normalizedContent === combinedResults;
};

const mapMessage = (message = {}, index = 0, context = {}) => {
    const normalized = message.role === "assistant"
        ? normalizeAssistantResponse(message, message.content)
        : {
            results: [],
            state: null,
            usage: null,
            cost: null,
            isQuestion: false,
            isPromptTool: false,
            isToolResponse: false,
            resultTitle: copy.value.result,
        };

    const results = normalized.results || [];
    const subToolId = Number(normalized.subToolId || activeSubToolId.value);
    const toolOptions = message.role === "assistant"
        ? cloneToolOptions(
            normalized.hasToolOptionsSnapshot
                ? normalized.toolOptions
                : (context.toolOptions || normalized.toolOptions || normalized.state || {}),
            subToolId
        )
        : null;
    const toolPayload = message.role === "assistant"
        ? {
            ...buildToolPayload(
                { id: subToolId },
                toolOptions,
                context.userMessage || "",
                activeConversation.value
            ),
            ...(isPlainObject(context.toolPayload) ? context.toolPayload : {}),
            ...(isPlainObject(normalized.toolPayload) ? normalized.toolPayload : {}),
            state: toolOptions,
        }
        : null;

    return {
        ...message,
        localKey: message.localKey || `${message.role || "message"}-${message.id || index}-${message.created_at || now()}`,
        content: normalized.isQuestion
            ? normalized.content
            : (
                shouldHideDuplicateContent(
                    message.content,
                    results,
                    normalized.isPromptTool
                ) ? "" : String(message.content || normalized.content || "")
            ),
        role: message.role || "assistant",
        results,
        resultTitle: normalized.resultTitle || copy.value.result,
        responseState: normalized.state,
        tool_id: message.role === "assistant" ? subToolId : null,
        tool_name: message.role === "assistant" ? normalized.toolName : "",
        tool_options: toolOptions,
        tool_payload: toolPayload,
        can_edit_options: message.role === "assistant"
            && normalized.isToolResponse
            && normalized.canEditOptions
            && !message.typing,
        usage: normalized.usage,
        cost: normalized.cost,
        metadata: message.role === "assistant" ? {
            type: normalized.type,
            tool: normalized.tool,
            provider: normalized.provider,
            model_key: normalized.model_key,
        } : null,
        is_error: Boolean(message.is_error) || normalized.success === false,
        typing: Boolean(message.typing),
    };
};

const mapMessages = (rows = []) => {
    let lastUserMessage = "";
    let lastToolOptions = null;
    let lastToolPayload = null;

    return rows.map((message, index) => {
        if (message.role === "user") {
            lastUserMessage = String(message.content || "");
            const metadata = isPlainObject(message.metadata) ? message.metadata : {};
            const state = isPlainObject(message.state)
                ? message.state
                : (isPlainObject(metadata.state) ? metadata.state : null);

            lastToolOptions = state;
            lastToolPayload = isPlainObject(metadata.request_payload)
                ? metadata.request_payload
                : {
                    sub_tool_id: message.sub_tool_id || metadata.sub_tool_id || activeSubToolId.value,
                    conversation_uuid: activeConversation.value?.uuid || "",
                    user_message: lastUserMessage,
                    state,
                    debug: false,
                };
        }

        return mapMessage(message, index, {
            userMessage: lastUserMessage,
            toolOptions: lastToolOptions,
            toolPayload: lastToolPayload,
        });
    });
};

const conversationTitle = (conversation = {}) => {
    const firstMessage = conversation.first_user_message_content
        || conversation.first_message_content
        || conversation.message?.find?.((message) => message.role === "user")?.content
        || "";

    const title = String(firstMessage)
        .replace(/<[^>]*>/g, " ")
        .trim()
        .split(/\s+/)
        .slice(0, 4)
        .join(" ");

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
    if (textareaRef.value) {
        textareaRef.value.style.height = "auto";
    }
};

const requireAuth = async () => {
    if (localStorage.getItem("auth_token")) return true;

    errorMessage.value = copy.value.authRequired;
    await router.push(`/${homeService.getLang()}/auth`);

    return false;
};

const loadSubtool = async () => {
    try {
        const response = await homeService.showSubtool(route.params.slug);
        const data = response?.data || {};
        const config = parseMaybeJson(data.config || data.settings);

        subtool.value = {
            id: Number(data.id || config.sub_tool_id || DEFAULT_SUB_TOOL_ID),
            name: data.translation?.name || data.name || config.name || copy.value.title,
            description: data.translation?.description || data.description || config.description || "",
            promptPlaceholder: data.translation?.prompt_placeholder || data.prompt_placeholder || config.prompt_placeholder || "",
            toolKey: data.tool_key || data.tool || config.tool_key || config.tool || "",
            modelKey: data.model_key || config.model_key || "",
            config,
        };

        promptState.value = normalizeToolState(promptState.value);
    } catch {
        subtool.value = {
            id: DEFAULT_SUB_TOOL_ID,
            name: copy.value.title,
            description: copy.value.welcomeText,
            promptPlaceholder: copy.value.placeholder,
            toolKey: PROMPT_GENERATOR_TOOL_KEY,
            modelKey: PROMPT_GENERATOR_MODEL_KEY,
            config: {},
        };

        promptState.value = createDefaultToolState(DEFAULT_SUB_TOOL_ID);
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
            .filter((conversation) => Number(conversation.sub_tool_id) === activeSubToolId.value)
            .map(formatConversation);
    } finally {
        loadingConversations.value = false;
    }
};

const hydrateStateFromMessages = (rows) => {
    const latestState = mapMessages(rows)
        .reverse()
        .find((message) => message.role === "assistant" && message.responseState)?.responseState;

    if (latestState) {
        promptState.value = normalizeToolState(latestState);
        persistPromptState();
    }
};

const loadConversationDetails = async (uuid) => {
    editingToolResponse.value = null;
    composerDraftBeforeEdit.value = "";

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

        messages.value = mapMessages(rows);
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

    restorePromptState(conversation.uuid);

    await router.replace(`/${homeService.getLang()}/subtool/${route.params.slug}/chat2/${conversation.uuid}`);

    return conversation;
};

const closeStream = () => {
    if (eventSource.value) {
        eventSource.value.close();
        eventSource.value = null;
    }

    streamingAssistant.value = false;
};

const openAssistantStream = async (conversation, afterId, toolContext = {}) => {
    closeStream();
    streamingAssistant.value = true;

    console.groupCollapsed("AI STREAM DEBUG");
    console.log("Conversation:", debugClone(conversation));
    console.log("After message id:", afterId);
    console.log("Stream URL:", `/api/v1/conversation/${conversation.uuid}/stream?after_id=${String(afterId || 0)}`);

    const typingMessage = mapMessage(
        {
            localKey: createLocalKey(),
            role: "assistant",
            content: "",
            typing: true,
            metadata: {
                sub_tool_id: toolContext.toolPayload?.sub_tool_id,
                tool_options: toolContext.toolOptions,
                tool_payload: toolContext.toolPayload,
            },
            created_at: now(),
        },
        0,
        {
            userMessage: toolContext.toolPayload?.user_message,
            toolOptions: toolContext.toolOptions,
            toolPayload: toolContext.toolPayload,
        }
    );

    console.log("Typing assistant message pushed before stream:", debugClone(typingMessage));

    messages.value.push(typingMessage);
    await scrollToBottom();

    const params = new URLSearchParams({
        after_id: String(afterId || 0),
        token: localStorage.getItem("auth_token") || "",
    });

    const source = new EventSource(`/api/v1/conversation/${conversation.uuid}/stream?${params.toString()}`);
    eventSource.value = source;

    source.onmessage = async (event) => {
        console.groupCollapsed("AI STREAM EVENT");
        console.log("Raw stream event:", event);
        console.log("Raw event.data:", event.data);

        const payload = JSON.parse(event.data || "{}");
        const index = messages.value.findIndex((message) => message.localKey === typingMessage.localKey);

        if (
            Number(payload?.response?.sub_tool_id) === IDEA_GENERATOR_SUB_TOOL_ID
            || payload?.response?.tool === IDEA_GENERATOR_TOOL_KEY
        ) {
            console.group("IDEA GENERATOR STREAM DEBUG");
            console.log("RAW STREAM EVENT:", event);
            console.log("RAW STREAM DATA:", event.data);
            console.log("PARSED STREAM PAYLOAD:", payload);
            console.log("STREAM RESPONSE:", payload.response);
            console.log("STREAM RESULTS:", payload.response?.results);
            console.log("STREAM STATE:", payload.response?.state);
            console.groupEnd();
        }

        console.log("Parsed stream payload:", debugClone(payload));
        console.log("Typing message index:", index);

        if (payload.type === "token" && index >= 0) {
            messages.value[index].typing = false;
            messages.value[index].content += payload.content || "";

            console.log("AI token content:", payload.content || "");
            console.log("Assistant content after token:", messages.value[index].content);
            console.log("Message printed in frontend from stream:", debugClone(messages.value[index]));

            await scrollToBottom();
        }

        if (payload.type === "error" && index >= 0) {
            messages.value[index].typing = false;
            messages.value[index].is_error = true;
            messages.value[index].content = payload.content || copy.value.genericError;

            console.error("AI stream error payload:", debugClone(payload));
            console.log("Error message printed in frontend:", debugClone(messages.value[index]));

            closeStream();
        }

        if (payload.type === "done") {
            console.log("AI stream done payload:", debugClone(payload));
            closeStream();
            await loadConversationDetails(conversation.uuid);
        }

        console.groupEnd();
    };

    source.onerror = async (error) => {
        console.error("AI stream EventSource error:", error);
        closeStream();
        await loadConversationDetails(conversation.uuid);
    };
};

const sendMessage = async (request = {}) => {
    const requestOverrides = request?.regenerate === true ? request : {};
    const requestState = normalizeToolState(requestOverrides.state ?? promptState.value);
    const text = resolvePromptStateMessage(requestState, activeSubToolId.value, requestOverrides.text ?? userMessage.value);

    if (!text || sendDisabled.value || !activeSubToolId.value) return;

    errorMessage.value = "";
    sendingMessage.value = true;
    editingToolResponse.value = null;
    composerDraftBeforeEdit.value = "";

    let debugGroupOpen = false;

    const closeSendDebugGroup = () => {
        if (debugGroupOpen) {
            console.groupEnd();
            debugGroupOpen = false;
        }
    };

    try {
        console.groupCollapsed("SEND MESSAGE FULL DEBUG");
        debugGroupOpen = true;

        console.log("1) USER TEXT FROM COMPOSER:", text);
        console.log("2) TOOL INFO BEFORE SEND:", debugCurrentTool());
        console.log("3) ACTIVE CONVERSATION BEFORE ensureConversation:", debugClone(activeConversation.value));
        console.log("4) CURRENT promptState BEFORE normalize:", debugClone(promptState.value));

        const conversation = await ensureConversation();

        console.log("5) CONVERSATION AFTER ensureConversation:", debugClone(conversation));

        if (!conversation?.uuid) {
            console.warn("No conversation UUID. Request stopped before sending.");
            closeSendDebugGroup();
            return;
        }

        console.log("6) NORMALIZED REQUEST STATE:", debugClone(requestState));

        const payload = buildToolPayload(subtool.value, requestState, text, conversation);

        console.log("7) PAYLOAD SENT TO BACKEND:", debugClone(payload));
        console.log("7.1) PAYLOAD JSON:", JSON.stringify(payload, null, 2));

        const userMessageObject = mapMessage({
            localKey: createLocalKey(),
            role: "user",
            content: text,
            created_at: now(),
        });

        console.log("8) USER MESSAGE OBJECT PUSHED TO FRONTEND:", debugClone(userMessageObject));
        console.log("8.1) USER TEXT PRINTED IN FRONTEND:", userMessageObject.content);

        messages.value.push(userMessageObject);

        userMessage.value = "";
        resetTextarea();
        promptState.value = requestState;
        persistPromptState(conversation.uuid);
        await scrollToBottom();

        console.time("9) chatServices.sendMessage duration");
        const response = await chatServices.sendMessage(payload);
        console.timeEnd("9) chatServices.sendMessage duration");

        if (Number(payload.sub_tool_id) === IDEA_GENERATOR_SUB_TOOL_ID) {
            const ideaDirectResponse = normalizeAssistantResponse(response);

            console.group("IDEA GENERATOR DEBUG");
            console.log("USER MESSAGE:", text);
            console.log("PAYLOAD SENT:", payload);
            console.log("RAW BACKEND RESPONSE:", response);
            console.log("response.data:", response?.data);
            console.log("response.data.results:", response?.data?.results);
            console.log("response.data.state:", response?.data?.state);
            console.log("NORMALIZED RESPONSE:", ideaDirectResponse);
            console.log("FRONTEND PRINTED RESULTS:", ideaDirectResponse.results);
            console.groupEnd();
        }

        console.groupCollapsed("10) RAW BACKEND RESPONSE");
        console.log("RAW response:", response);
        console.log("response.data:", response?.data);
        console.log("response.data.success:", response?.data?.success);
        console.log("response.data.type:", response?.data?.type);
        console.log("response.data.tool:", response?.data?.tool);
        console.log("response.data.provider:", response?.data?.provider);
        console.log("response.data.model_key:", response?.data?.model_key);
        console.log("response.data.message:", response?.data?.message);
        console.log("response.data.content:", response?.data?.content);
        console.log("response.data.results:", response?.data?.results);
        console.log("response.data.results[0]:", response?.data?.results?.[0]);
        console.log("response.data.results[0].text:", response?.data?.results?.[0]?.text);
        console.log("response.data.state:", response?.data?.state);
        console.log("response.data.state.last_output:", response?.data?.state?.last_output);
        console.log("response.data.last_output:", response?.data?.last_output);
        console.log("response.data.text:", response?.data?.text);
        console.log("response.data.usage:", response?.data?.usage);
        console.log("response.data.cost:", response?.data?.cost);
        console.log("response.data.count:", response?.data?.count);
        console.log("response.data.request_id:", response?.data?.request_id);
        console.log("RAW response JSON:", JSON.stringify(debugClone(response?.data || response), null, 2));
        console.groupEnd();

        const directResponse = normalizeAssistantResponse(response);
        const responseMetadata = {
            ...directResponse,
            sub_tool_id: payload.sub_tool_id,
            tool_name: pageTitle.value,
            tool_options: cloneToolOptions(requestState, payload.sub_tool_id),
            tool_payload: debugClone(payload),
            can_edit_options: true,
        };

        console.groupCollapsed("11) NORMALIZED DIRECT RESPONSE USED BY FRONTEND");
        console.log("directResponse:", debugClone(directResponse));
        console.log("directResponse.content:", directResponse.content);
        console.log("directResponse.results:", debugClone(directResponse.results));
        console.log("directResponse.results[0]:", directResponse.results?.[0]);
        console.log("directResponse.resultTitle:", directResponse.resultTitle);
        console.log("directResponse.state:", debugClone(directResponse.state));
        console.log("directResponse.usage:", debugClone(directResponse.usage));
        console.log("directResponse.cost:", debugClone(directResponse.cost));
        console.groupEnd();

        if (directResponse.isQuestion && directResponse.content) {
            const assistantMessage = mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                content: directResponse.content,
                metadata: responseMetadata,
                created_at: now(),
            }, 0, {
                userMessage: text,
                toolOptions: requestState,
                toolPayload: payload,
            });

            console.groupCollapsed("12) ASSISTANT QUESTION MESSAGE PRINTED IN FRONTEND");
            console.log("assistantMessage:", debugClone(assistantMessage));
            console.log("assistantMessage.content:", assistantMessage.content);
            console.log("THIS IS PRINTED IN FRONTEND:", assistantMessage.content);
            console.groupEnd();

            messages.value.push(assistantMessage);

            if (directResponse.state) {
                promptState.value = directResponse.state;
                persistPromptState(conversation.uuid);
            }

            console.log("13) ALL MESSAGES AFTER ASSISTANT QUESTION PUSH:", debugClone(messages.value));
            await scrollToBottom();
            closeSendDebugGroup();
            return;
        }

        if (directResponse.results.length) {
            const assistantMessage = mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                content: "",
                metadata: responseMetadata,
                created_at: now(),
            }, 0, {
                userMessage: text,
                toolOptions: requestState,
                toolPayload: payload,
            });

            console.groupCollapsed("12) ASSISTANT RESULT MESSAGE PRINTED IN FRONTEND");
            console.log("assistantMessage:", debugClone(assistantMessage));
            console.log("assistantMessage.results:", debugClone(assistantMessage.results));
            console.log("assistantMessage.results[0]:", assistantMessage.results?.[0]);
            console.log("THIS IS PRINTED IN FRONTEND:", assistantMessage.results?.[0]);
            console.log("Template variable resultText equals each item in assistantMessage.results");
            console.groupEnd();

            messages.value.push(assistantMessage);

            if (directResponse.state) {
                promptState.value = directResponse.state;
                persistPromptState(conversation.uuid);
            }

            console.log("13) ALL MESSAGES AFTER ASSISTANT RESULT PUSH:", debugClone(messages.value));
            await scrollToBottom();
            closeSendDebugGroup();
            return;
        }

        if (directResponse.content) {
            const assistantMessage = mapMessage({
                localKey: createLocalKey(),
                role: "assistant",
                content: directResponse.content,
                metadata: responseMetadata,
                created_at: now(),
            }, 0, {
                userMessage: text,
                toolOptions: requestState,
                toolPayload: payload,
            });

            console.groupCollapsed("12) ASSISTANT CONTENT MESSAGE PRINTED IN FRONTEND");
            console.log("assistantMessage:", debugClone(assistantMessage));
            console.log("assistantMessage.content:", assistantMessage.content);
            console.log("THIS IS PRINTED IN FRONTEND:", assistantMessage.content);
            console.groupEnd();

            messages.value.push(assistantMessage);

            if (directResponse.state) {
                promptState.value = directResponse.state;
                persistPromptState(conversation.uuid);
            }

            console.log("13) ALL MESSAGES AFTER ASSISTANT CONTENT PUSH:", debugClone(messages.value));
            await scrollToBottom();
            closeSendDebugGroup();
            return;
        }

        console.warn("12) No direct question, no direct results, no direct content. Opening stream...");
        console.log("Stream after_id:", response?.data?.message_id);
        console.log("This usually means frontend did not receive printable AI text in direct response.");

        await openAssistantStream(conversation, response?.data?.message_id, {
            toolOptions: requestState,
            toolPayload: payload,
        });
        closeSendDebugGroup();
    } catch (error) {
        console.groupCollapsed("SEND MESSAGE ERROR DEBUG");
        console.error("Caught error:", error);
        console.error("error.response:", error?.response);
        console.error("error.response.data:", error?.response?.data);
        console.error("error.message:", error?.message);
        console.groupEnd();

        errorMessage.value = error?.response?.data?.message || copy.value.genericError;
        closeSendDebugGroup();
    } finally {
        sendingMessage.value = false;
    }
};

const openToolOptionsForResponse = async (response) => {
    if (!response?.can_edit_options) return;

    if (!editingToolResponse.value) {
        composerDraftBeforeEdit.value = userMessage.value;
    }

    const subToolId = Number(response.tool_id || activeSubToolId.value);
    promptState.value = cloneToolOptions(response.tool_options || {}, subToolId);
    userMessage.value = String(response.tool_payload?.user_message || "");
    editingToolResponse.value = response;

    await nextTick();

    if (optionsPanel.value) {
        optionsPanel.value.open = true;
        optionsPanel.value.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    autoResize();
};

const cancelToolOptionsEdit = () => {
    editingToolResponse.value = null;
    userMessage.value = composerDraftBeforeEdit.value;
    composerDraftBeforeEdit.value = "";
    restorePromptState(activeConversation.value?.uuid);

    nextTick(() => autoResize());
};

const regenerateToolResponse = async (response, updatedOptions = promptState.value) => {
    const text = String(response?.tool_payload?.user_message || userMessage.value).trim();

    if (!text) {
        errorMessage.value = copy.value.genericError;
        return;
    }

    const subToolId = Number(response?.tool_id || activeSubToolId.value);
    const state = cloneToolOptions(updatedOptions, subToolId);

    editingToolResponse.value = null;
    composerDraftBeforeEdit.value = "";
    await sendMessage({
        regenerate: true,
        text,
        state,
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
        promptState.value = createDefaultToolState(activeSubToolId.value);
        editingToolResponse.value = null;
        composerDraftBeforeEdit.value = "";
        sidebarOpen.value = false;

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat2/${conversation.uuid}`);
    } finally {
        creatingConversation.value = false;
    }
};

const openConversation = async (conversation) => {
    if (!conversation?.uuid) return;

    sidebarOpen.value = false;
    restorePromptState(conversation.uuid);

    if (route.params.uuid === conversation.uuid) {
        await loadConversationDetails(conversation.uuid);
        return;
    }

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat2/${conversation.uuid}`);
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
            promptState.value = createDefaultToolState(activeSubToolId.value);
            editingToolResponse.value = null;
            composerDraftBeforeEdit.value = "";
            await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat2`);
        }
    } finally {
        deletingUuid.value = "";
    }
};

const fillPlaceholder = () => {
    userMessage.value = subtool.value.promptPlaceholder;

    nextTick(() => {
        textareaRef.value?.focus();
        autoResize();
    });
};

const copyResult = async (text, resultId) => {
    await navigator.clipboard.writeText(text);

    copiedResult.value = resultId;

    window.setTimeout(() => {
        copiedResult.value = null;
    }, 1200);
};

const initialize = async () => {
    locale.value = homeService.getLang();

    await loadSubtool();
    restorePromptState(route.params.uuid);
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
    () => route.params.uuid,
    async (uuid, previousUuid) => {
        if (uuid === previousUuid) return;

        closeStream();
        restorePromptState(uuid);

        if (uuid && activeConversation.value?.uuid === uuid && messages.value.length === 0) {
            return;
        }

        await loadConversationDetails(uuid);
    }
);

watch(
    () => route.params.slug,
    async (slug, previousSlug) => {
        if (slug === previousSlug) return;

        closeStream();
        activeConversation.value = null;
        messages.value = [];
        promptState.value = createDefaultToolState(activeSubToolId.value);
        editingToolResponse.value = null;
        composerDraftBeforeEdit.value = "";

        await initialize();
    }
);
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
    grid-template-columns: 280px minmax(0, 1fr);
    transition: grid-template-columns 0.25s ease;
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
    margin-inline: 10px;
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

.workspace {
    min-width: 0;
    min-height: 0;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    height: calc(100vh - 70px);
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
    padding-bottom: 28px;
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

.response-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}

.response-actions button,
.options-editing button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 10px;
    border: 1px solid #cfe3ef;
    border-radius: 9px;
    color: var(--blue);
    background: #f5fbfe;
}

.response-actions button:hover,
.options-editing button:hover:not(:disabled) {
    border-color: var(--blue);
    background: #eaf6fc;
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

.options-editing {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 13px 12px;
    padding: 10px 12px;
    border: 1px solid #cfe7f4;
    border-radius: 10px;
    color: var(--navy);
    background: #eef8fd;
}

.options-editing > span,
.options-editing > div {
    display: flex;
    align-items: center;
    gap: 7px;
}

.options-editing button {
    color: #fff;
    background: var(--blue);
}

.options-editing button.secondary {
    color: var(--muted);
    background: #fff;
}

.options-editing button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
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

    .options-editing {
        align-items: stretch;
        flex-direction: column;
    }

    .options-editing > div {
        justify-content: flex-end;
    }

    .options-grid .wide {
        grid-column: auto;
    }

    .welcome-card {
        padding: 28px 20px;
    }
}
</style>
