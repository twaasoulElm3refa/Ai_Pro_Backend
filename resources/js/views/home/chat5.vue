<template>
    <main class="detector-chat" :class="{ 'sidebar-collapsed': desktopSidebarCollapsed }"
        :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-quick-actions" :aria-label="labels.quickNavigation">
                <button type="button" class="sidebar-quick-button sidebar-home-button" :title="labels.home"
                    @click="goHome">
                    <i class="bi bi-house-door-fill"></i>
                    <span>{{ labels.home }}</span>
                </button>

                <button type="button" class="sidebar-quick-button sidebar-wallet-button" :title="labels.wallet"
                    @click="goToWallet">
                    <i class="bi bi-wallet2"></i>
                    <span class="sidebar-wallet-copy">
                        <small>{{ labels.wallet }}</small>
                        <strong>{{ walletLoading ? "..." : formattedWalletBalance }}</strong>
                    </span>
                </button>
            </div>

            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-image"></i></span>
                <strong>{{ activeTitle }}</strong>
                <button type="button" class="icon-button sidebar-close-toggle" :aria-label="labels.closeSidebar"
                    @click="closeSidebar">
                    <i class="bi bi-layout-sidebar-inset-reverse"></i>
                </button>
            </div>

            <button type="button" class="new-chat-button" :disabled="creatingConversation || isSending"
                @click="startNewChat">
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
                    <button type="button" class="conversation-delete"
                        :disabled="deletingUuid === conversation.uuid || isSending" :aria-label="labels.deleteChat"
                        @click="deleteConversation(conversation)">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </aside>

        <button v-if="desktopSidebarCollapsed" type="button" class="desktop-sidebar-open-toggle"
            :aria-label="labels.openSidebar" @click="openSidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <button v-if="sidebarOpen" type="button" class="sidebar-overlay" @click="sidebarOpen = false"></button>
        <button v-if="!sidebarOpen" type="button" class="mobile-sidebar-toggle mobile-only"
            :aria-label="labels.openSidebar" @click="openSidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <section class="workspace">
            <div ref="messagesContainer" class="messages" role="log" aria-live="polite">
                <div v-if="loadingMessages" class="center-status">
                    <span class="spinner"></span>
                    {{ labels.loadingConversation }}
                </div>

                <div v-else-if="messages.length === 0" class="welcome-card">
                    <span class="welcome-icon"><i :class="activeToolIcon"></i></span>
                    <h1>{{ activeTitle }}</h1>
                    <p>{{ activeWelcome }}</p>
                    <button
                        v-if="!hasActiveConversation"
                        type="button"
                        class="welcome-new-chat-button"
                        :disabled="creatingConversation || isSending"
                        @click="startNewChat"
                    >
                        <span v-if="creatingConversation" class="button-spinner" aria-hidden="true"></span>
                        <i v-else class="bi bi-chat-square-text-fill" aria-hidden="true"></i>
                        <span>{{ creatingConversation ? labels.creating : createConversationLabel }}</span>
                    </button>
                    <button v-if="!isBackgroundRemover" type="button" class="suggestion" @click="fillExample">
                        {{ isPromptGenerator ? promptLabels.example : labels.example }}
                    </button>
                </div>

                <div v-else class="message-list">
                    <article v-for="message in messages" :key="message.localKey" class="message-row"
                        :class="message.role">
                        <div class="avatar">
                            <i :class="message.role === 'assistant' ? 'bi bi-stars' : 'bi bi-person-fill'"></i>
                        </div>

                        <div class="message-body" :class="{
                            error: message.is_error,
                            'card-shell': message.role === 'assistant' && (message.images.length || message.is_error || isPromptGeneratorMessage(message)),
                        }">
                            <template v-if="message.role === 'assistant' && message.images.length">
                                <div class="image-response-card">
                                    <div class="response-heading">
                                        <span>
                                            <i class="bi bi-stars"></i>
                                            {{ message.content || activeGenerated }}
                                        </span>
                                    </div>

                                    <div class="generated-images"
                                        :class="`image-count-${Math.min(message.images.length, 4)}`">
                                        <article v-for="image in message.images" :key="image.id"
                                            class="generated-image-card">
                                            <div class="image-preview">
                                                <span v-if="previewLoading[image.id]" class="image-skeleton">
                                                    <i class="bi bi-image"></i>
                                                </span>
                                                <div v-else-if="previewErrors[image.id]" class="image-load-error">
                                                    <i class="bi bi-exclamation-circle"></i>
                                                    <span>{{ labels.previewFailed }}</span>
                                                    <button type="button" @click="loadImagePreview(image)">
                                                        {{ labels.retry }}
                                                    </button>
                                                </div>
                                                <img v-else-if="previewUrls[image.id]" :src="previewUrls[image.id]"
                                                    :alt="activeGenerated" loading="lazy"
                                                    @error="markPreviewFailed(image)">
                                            </div>

                                            <div class="image-actions">
                                                <button type="button" class="image-action image-action-primary"
                                                    :disabled="actionLoading === `download-${image.id}`"
                                                    @click="downloadImage(image)">
                                                    <span v-if="actionLoading === `download-${image.id}`"
                                                        class="button-spinner"></span>
                                                    <i v-else class="bi bi-download"></i>
                                                    {{ labels.download }}
                                                </button>
                                                <button type="button" class="image-action image-action-secondary"
                                                    @click="openImage(image)">
                                                    <i class="bi bi-arrows-fullscreen"></i>
                                                    {{ labels.open }}
                                                </button>
                                            </div>
                                        </article>
                                    </div>

                                    <div class="result-footer">
                                        <!-- <button type="button" class="regenerate-button" :disabled="isSending"
                                            @click="regenerate(message)">
                                            <span v-if="isSending" class="button-spinner"></span>
                                            <i v-else class="bi bi-arrow-clockwise"></i>
                                            {{ labels.regenerate }}
                                        </button> -->
                                    </div>

                                    <div v-if="message.metadata?.failed_files?.length" class="partial-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        {{ labels.partialResult }}
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="message.role === 'assistant' && message.is_error">
                                <div class="clean-error-card">
                                    <i class="bi bi-exclamation-circle"></i>
                                    <span>{{ localizeError(message.content) }}</span>
                                    <button type="button" :disabled="isSending" @click="retryMessage(message)">
                                        <i class="bi bi-arrow-clockwise"></i>
                                        {{ labels.retry }}
                                    </button>
                                </div>
                            </template>

                            <template v-else-if="message.role === 'assistant' && isPromptGeneratorMessage(message)">
                                <div class="prompt-response-card">
                                    <div class="response-heading">
                                        <span>
                                            <i class="bi bi-card-text"></i>
                                            {{ activeGenerated }}
                                        </span>
                                        <button type="button" class="copy-prompt-button" @click="copyPrompt(message)">
                                            <i class="bi bi-clipboard-check"></i>
                                            {{ copiedPromptKey === message.localKey ? promptLabels.copied :
                                                promptLabels.copyPrompt }}
                                        </button>
                                    </div>

                                    <p class="message-content prompt-output">{{ message.content }}</p>
                                </div>
                            </template>

                            <p v-else class="message-content">{{ message.content }}</p>
                        </div>
                    </article>

                    <article v-if="isSending" class="message-row assistant generation-message">
                        <div class="avatar generation-avatar"><i class="bi bi-stars"></i></div>
                        <div class="message-body card-shell generation-loading">
                            <div class="generation-header">
                                <div class="generation-header-content">
                                    <span class="generation-icon">
                                        <i class="bi bi-stars"></i>
                                    </span>

                                    <div>
                                        <strong>{{ activeGenerating }}</strong>
                                        <small>{{ activeGenerationWait }}</small>
                                    </div>
                                </div>

                                <span class="generation-spinner" aria-hidden="true"></span>
                            </div>

                            <div v-if="isPromptGenerator" class="loading-prompt-card">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>

                            <div v-else class="loading-image-grid"
                                :class="`loading-count-${Math.min(state.results_count, 4)}`">
                                <div v-for="index in state.results_count" :key="index" class="loading-image-card">
                                    <span class="loading-image-shimmer"></span>

                                    <span class="loading-image-placeholder">
                                        <i class="bi bi-image"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="generation-progress">
                                <span class="generation-progress-line"></span>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <footer v-if="hasActiveConversation" class="composer">
                <div v-if="errorMessage" class="error-banner">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ errorMessage }}</span>
                    <button v-if="lastFailedRequest" type="button" :disabled="isSending" @click="retryLastRequest">
                        {{ labels.retry }}
                    </button>
                </div>

                <details v-if="!isBackgroundRemover" class="options-panel">
                    <summary class="options-panel-header">
                        <span><i class="bi bi-sliders"></i> {{ isPromptGenerator ? promptLabels.settings :
                            labels.settings }}</span>
                        <span class="options-chevron" aria-hidden="true">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>

                    <div v-if="isPromptGenerator" class="options-form prompt-options-form">
                        <label>
                            <span>{{ promptLabels.language }}</span>
                            <select v-model="state.language" :disabled="isSending">
                                <option value="English">English</option>
                                <option value="Arabic">Arabic</option>
                                <option value="Auto Detect">Auto Detect</option>
                            </select>
                        </label>

                        <label>
                            <span>{{ promptLabels.aspectRatio }}</span>
                            <select v-model="state.aspect_ratio" :disabled="isSending">
                                <option value="1:1">1:1</option>
                                <option value="4:5">4:5</option>
                                <option value="9:16">9:16</option>
                                <option value="16:9">16:9</option>
                                <option value="3:2">3:2</option>
                            </select>
                        </label>

                        <label class="wide">
                            <span>{{ promptLabels.style }}</span>
                            <input v-model="state.style" type="text" :disabled="isSending">
                        </label>

                        <label>
                            <span>{{ promptLabels.camera }}</span>
                            <input v-model="state.camera" type="text" :disabled="isSending">
                        </label>

                        <label>
                            <span>{{ promptLabels.lighting }}</span>
                            <input v-model="state.lighting" type="text" :disabled="isSending">
                        </label>

                        <label>
                            <span>{{ promptLabels.textPolicy }}</span>
                            <select v-model="state.text_policy" :disabled="isSending">
                                <option value="No text">No text</option>
                                <option value="Minimal text only if requested">Minimal text only if requested</option>
                                <option value="Text allowed">Text allowed</option>
                            </select>
                        </label>

                        <label>
                            <span>{{ promptLabels.facePolicy }}</span>
                            <select v-model="state.face_policy" :disabled="isSending">
                                <option value="No visible human faces">No visible human faces</option>
                                <option value="Faces allowed if natural">Faces allowed if natural</option>
                                <option value="No restrictions">No restrictions</option>
                            </select>
                        </label>

                        <label class="wide">
                            <span>{{ labels.negativePrompt }}</span>
                            <textarea v-model="state.negative_prompt" rows="2" :disabled="isSending"></textarea>
                        </label>

                        <label class="wide">
                            <span>{{ promptLabels.extraOptions }}</span>
                            <textarea v-model="promptExtraOptionsText" rows="2" :disabled="isSending"></textarea>
                        </label>
                    </div>

                    <div v-else-if="isImageUpscaler" class="options-form">
                        <label>
                            <span>{{ labels.scale }}</span>
                            <select v-model.number="state.scale" :disabled="isSending">
                                <option :value="2">2x</option>
                                <option :value="4">4x</option>
                            </select>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" v-model="state.face_enhance" :disabled="isSending" />
                            <span>{{ labels.faceEnhance }}</span>
                        </label>
                    </div>

                    <div v-else-if="isImageGenerator" class="options-form">
                        <label>
                            <span>{{ labels.size }}</span>
                            <select v-model="state.size" :disabled="isSending">
                                <option value="512x512">512 × 512</option>
                                <option value="768x768">768 × 768</option>
                                <option value="1024x1024">1024 × 1024</option>
                                <option value="1024x1536">1024 × 1536</option>
                                <option value="1536x1024">1536 × 1024</option>
                            </select>
                        </label>

                        <label>
                            <span>{{ labels.quality }}</span>
                            <select v-model="state.quality" :disabled="isSending">
                                <option value="low">{{ labels.low }}</option>
                                <option value="medium">{{ labels.medium }}</option>
                                <option value="high">{{ labels.high }}</option>
                            </select>
                        </label>

                        <label>
                            <span>{{ labels.resultsCount }}</span>
                            <select v-model.number="state.results_count" :disabled="isSending">
                                <option :value="1">1</option>
                                <option :value="2">2</option>
                                <option :value="3">3</option>
                                <option :value="4">4</option>
                            </select>
                        </label>

                        <label>
                            <span>{{ labels.format }}</span>
                            <select v-model="state.output_format" :disabled="isSending">
                                <option value="png">PNG</option>
                                <option value="jpg">JPG</option>
                                <option value="webp">WebP</option>
                            </select>
                        </label>

                        <label class="wide">
                            <span>{{ labels.negativePrompt }}</span>
                            <textarea v-model="state.negative_prompt" rows="2" :disabled="isSending"
                                :placeholder="labels.negativePlaceholder"></textarea>
                        </label>

                        <label>
                            <span>{{ labels.seed }}</span>
                            <input v-model.number="state.seed" type="number" :disabled="isSending"
                                :placeholder="labels.random">
                        </label>
                    </div>
                </details>

                <div class="input-box">
                    <input type="file" ref="fileInputRef" accept="image/*" class="hidden-file-input"
                        @change="handleFileSelect" :disabled="isSending" />

                    <button v-if="isBackgroundRemover || isImageUpscaler" type="button" class="attach-button"
                        :disabled="isSending" :title="labels.uploadImage" @click="triggerFileInput">
                        <i class="bi bi-paperclip"></i>
                    </button>

                    <div v-if="(isBackgroundRemover || isImageUpscaler) && selectedFilePreview"
                        class="composer-file-preview">
                        <img :src="selectedFilePreview" :alt="selectedFile?.name" />
                        <button type="button" class="remove-file" :title="labels.removeImage"
                            @click="removeSelectedFile">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <textarea ref="textareaRef" v-model="userMessage" rows="1" maxlength="10000"
                        :placeholder="activePlaceholder" :disabled="isSending" @input="autoResize"
                        @keydown.enter.exact.prevent="sendMessage()"></textarea>

                    <button type="button" class="send-button" :disabled="!canSend" :aria-label="activeSendLabel"
                        @click="sendMessage()">
                        <i :class="isSending ? 'bi bi-hourglass-split' : 'bi bi-send-fill'"></i>
                    </button>
                </div>
                <p class="composer-hint">{{ labels.hint }}</p>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import chatServices from "@/services/chat/chatServices";
import homeService from "@/services/home/homeService";
import api from "@/services/ApiClient";
import useSeoMeta from "@/composables/useSeoMeta";
import {
    normalizeGeneratedImages,
    normalizeImageGenerationMessage,
    unwrapApiData,
} from "@/utils/imageGeneratorResults";

// ─── Constants ────────────────────────────────────────────────────────────────

const IMAGE_TOOL = {
    sub_tool_id: 21,
    tool_key: "ai_image_generator",
    model_key: "image_generator",
};

const BACKGROUND_REMOVER_TOOL = {
    sub_tool_id: 22,
    tool_key: "ai_background_remover",
    model_key: "background_remover",
};

const IMAGE_UPSCALER_TOOL = {
    sub_tool_id: 23,
    tool_key: "ai_image_upscaler",
    model_key: "image_upscaler",
};

const IMAGE_PROMPT_GENERATOR_TOOL = {
    sub_tool_id: 24,
    tool_key: "image_prompt_generator",
    model_key: "image_prompt_generator",
};

const BG_REMOVER_MESSAGE = "Remove the background from the uploaded image and return a transparent PNG.";
const IMAGE_UPSCALER_MESSAGE = "Upscale the uploaded image.";
const IMAGE_PROMPT_PREFIX = "Create a professional image prompt for";
const WALLET_CACHE_PREFIX = "navbar_wallet_cache_v1";
const WALLET_CACHE_TTL = 60 * 1000;

// ─── State factory ────────────────────────────────────────────────────────────

const createImageGeneratorState = () => ({
    provider: null,
    negative_prompt: "",
    size: "1024x1024",
    quality: "medium",
    results_count: 1,
    output_format: "png",
    seed: null,
    extra_options: [],
    last_output: null,
});

const createBackgroundRemoverState = () => ({
    provider: null,
    last_output: null,
});

const createImageUpscalerState = () => ({
    scale: 2,
    face_enhance: false,
    extra_options: [],
    last_output: null,
});

const createPromptGeneratorState = () => ({
    content: null,
    language: "English",
    style: "high-end editorial photography",
    aspect_ratio: "4:5",
    camera: "medium-wide shot",
    lighting: "cinematic side lighting",
    negative_prompt: "text, watermark, blurry image, distorted anatomy",
    text_policy: "No text",
    face_policy: "No visible human faces",
    results_count: 1,
    extra_options: ["realistic materials", "8K detail"],
    last_output: null,
});

// ─── Composables ──────────────────────────────────────────────────────────────

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

// ─── Reactive state ───────────────────────────────────────────────────────────

const activeConversation = ref(null);
const currentSubtool = ref(null);
const conversations = ref([]);
const messages = ref([]);
const hasActiveConversation = computed(() =>
    Boolean(activeConversation.value?.uuid || route.params.uuid)
);
const userMessage = ref("");
const state = ref(createDefaultState());
const errorMessage = ref("");
const lastFailedRequest = ref(null);
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const isSending = ref(false);
const deletingUuid = ref("");
const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const previewUrls = ref({});
const previewLoading = ref({});
const previewErrors = ref({});
const actionLoading = ref("");
const selectedFile = ref(null);
const selectedFilePreview = ref("");
const fileInputRef = ref(null);
const copiedPromptKey = ref("");
const walletBalance = ref(null);
const walletLoading = ref(false);

function resolveStateSubToolId(subToolId = null) {
    return Number(
        subToolId
        || activeConversation.value?.sub_tool_id
        || currentSubtool.value?.id
        || currentSubtool.value?.sub_tool_id
        || IMAGE_TOOL.sub_tool_id
    );
}

function createDefaultState(subToolId = null) {
    const resolvedSubToolId = resolveStateSubToolId(subToolId);

    if (resolvedSubToolId === BACKGROUND_REMOVER_TOOL.sub_tool_id) {
        return createBackgroundRemoverState();
    }

    if (resolvedSubToolId === IMAGE_UPSCALER_TOOL.sub_tool_id) {
        return createImageUpscalerState();
    }

    if (resolvedSubToolId === IMAGE_PROMPT_GENERATOR_TOOL.sub_tool_id) {
        return createPromptGeneratorState();
    }

    return createImageGeneratorState();
}

// ─── Computed ─────────────────────────────────────────────────────────────────

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "en").toLowerCase() === "ar"
);
const createConversationLabel = computed(() =>
    isArabic.value ? "إنشاء محادثة جديدة" : "Create new conversation"
);

const labels = computed(() => isArabic.value ? {
    title: "مولد الصور بالذكاء الاصطناعي",
    welcome: "صف الصورة التي تتخيلها، ثم اضبط الحجم والجودة لتحصل على صور جاهزة للعرض والتحميل.",
    example: "مشهد سينمائي للقاهرة المستقبلية وقت الغروب، تفاصيل معمارية دقيقة وإضاءة واقعية",
    placeholder: "اكتب وصفًا دقيقًا للصورة التي تريد إنشاءها...",
    negativePlaceholder: "مثال: ضبابية، جودة منخفضة، نص، علامة مائية",
    newChat: "محادثة جديدة",
    creating: "جاري الإنشاء...",
    recent: "المحادثات الأخيرة",
    loading: "جاري التحميل...",
    noChats: "لا توجد محادثات بعد",
    loadingConversation: "جاري تحميل المحادثة...",
    settings: "إعدادات التوليد",
    size: "المقاس",
    quality: "الجودة",
    resultsCount: "عدد الصور",
    format: "صيغة الملف",
    negativePrompt: "ما لا تريد ظهوره",
    seed: "Seed",
    random: "عشوائي",
    low: "منخفضة",
    medium: "متوسطة",
    high: "عالية",
    send: "توليد الصور",
    hint: "Enter للإرسال، وShift + Enter لسطر جديد",
    generating: "جاري توليد الصور وحفظها...",
    generationWait: "قد تستغرق العملية بضع لحظات، لا تغلق الصفحة.",
    generated: "تم توليد الصور بنجاح",
    download: "تحميل",
    open: "فتح",
    regenerate: "إعادة التوليد",
    retry: "إعادة المحاولة",
    previewFailed: "تعذر عرض الصورة",
    partialResult: "تم حفظ بعض الصور فقط، وتعذر تنزيل صورة أو أكثر من خدمة التوليد.",
    genericError: "تعذر توليد الصور الآن. حاول مرة أخرى.",
    promptRequired: "اكتب وصف الصورة أولًا.",
    downloadFailed: "تعذر تحميل الصورة.",
    openFailed: "تعذر فتح الصورة.",
    deleteConfirm: "هل تريد حذف هذه المحادثة؟",
    deleteChat: "حذف المحادثة",
    closeSidebar: "إغلاق قائمة المحادثات",
    openSidebar: "فتح قائمة المحادثات",
    home: "الرئيسية",
    wallet: "رصيد المحفظة",
    quickNavigation: "اختصارات التنقل والمحفظة",
    uploadImage: "رفع صورة",
    removeImage: "إزالة",
    fileRequired: "ارفع صورة أولًا.",
    bgRemoverTitle: "إزالة خلفية الصور بالذكاء الاصطناعي",
    bgRemoverWelcome: "ارفع صورة وسيتم إزالة الخلفية تلقائيًا وإرجاع صورة بخلفية شفافة.",
    bgRemoverPlaceholder: "ارفع صورة لإزالة خلفيتها...",
    bgRemoverMessagePlaceholder: "اكتب تعليمات إضافية اختيارية لإزالة الخلفية...",
    bgRemoverSend: "إزالة الخلفية",
    bgRemoverGenerating: "جاري إزالة الخلفية وحفظ الصورة...",
    bgRemoverGenerationWait: "قد تستغرق العملية بضع لحظات، لا تغلق الصفحة.",
    bgRemoverGenerated: "تمت إزالة الخلفية بنجاح",
    bgRemoverGenericError: "تعذر إزالة الخلفية الآن. حاول مرة أخرى.",
    scale: "نسبة التكبير",
    faceEnhance: "تحسين الوجوه",
    upscalerTitle: "تكبير وتحسين جودة الصور بالذكاء الاصطناعي",
    upscalerWelcome: "ارفع صورة لزيادة دقتها وتحسين تفاصيلها باستخدام الذكاء الاصطناعي.",
    upscalerPlaceholder: "ارفع صورة لترقية جودتها وتوضيح تفاصيلها...",
    upscalerMessagePlaceholder: "اكتب تعليمات إضافية اختيارية لتكبير الصورة وتحسينها...",
    upscalerSend: "تكبير الصورة",
    upscalerGenerating: "جاري تكبير الصورة وتحسين دقتها...",
    upscalerGenerationWait: "قد تستغرق العملية بضع لحظات، لا تغلق الصفحة.",
    upscalerGenerated: "تم تكبير الصورة بنجاح",
    upscalerGenericError: "تعذر تكبير الصورة الآن. حاول مرة أخرى.",
} : {
    title: "AI Image Generator",
    welcome: "Describe the image you have in mind, then choose its size and quality to generate ready-to-use images.",
    example: "A cinematic futuristic Cairo skyline at sunset, intricate architecture and realistic lighting",
    placeholder: "Describe the image you want to generate...",
    negativePlaceholder: "For example: blurry, low quality, text, watermark",
    newChat: "New chat",
    creating: "Creating...",
    recent: "Recent chats",
    loading: "Loading...",
    noChats: "No conversations yet",
    loadingConversation: "Loading conversation...",
    settings: "Generation settings",
    size: "Size",
    quality: "Quality",
    resultsCount: "Images",
    format: "Format",
    negativePrompt: "Negative prompt",
    seed: "Seed",
    random: "Random",
    low: "Low",
    medium: "Medium",
    high: "High",
    send: "Generate images",
    hint: "Press Enter to send, Shift + Enter for a new line",
    generating: "Generating and saving images...",
    generationWait: "This may take a few moments. Please keep this page open.",
    generated: "Images generated successfully",
    download: "Download",
    open: "Open",
    regenerate: "Regenerate",
    retry: "Retry",
    previewFailed: "Preview unavailable",
    partialResult: "Some images were saved, but one or more files could not be downloaded.",
    genericError: "Images could not be generated right now. Please try again.",
    promptRequired: "Describe the image first.",
    downloadFailed: "The image could not be downloaded.",
    openFailed: "The image could not be opened.",
    deleteConfirm: "Delete this conversation?",
    deleteChat: "Delete conversation",
    closeSidebar: "Close conversations sidebar",
    openSidebar: "Open conversations sidebar",
    home: "Home",
    wallet: "Wallet balance",
    quickNavigation: "Home and wallet shortcuts",
    uploadImage: "Upload image",
    removeImage: "Remove",
    fileRequired: "Upload an image first.",
    bgRemoverTitle: "AI Background Remover",
    bgRemoverWelcome: "Upload an image and the background will be removed automatically, returning a transparent PNG.",
    bgRemoverPlaceholder: "Upload an image to remove its background...",
    bgRemoverMessagePlaceholder: "Add optional instructions for removing the background...",
    bgRemoverSend: "Remove background",
    bgRemoverGenerating: "Removing background and saving...",
    bgRemoverGenerationWait: "This may take a few moments. Please keep this page open.",
    bgRemoverGenerated: "Background removed successfully",
    bgRemoverGenericError: "Background could not be removed right now. Please try again.",
    scale: "Scale factor",
    faceEnhance: "Face enhance",
    upscalerTitle: "AI Image Upscaler",
    upscalerWelcome: "Upload an image to increase its resolution and detail using AI.",
    upscalerPlaceholder: "Upload an image to upscale and enhance...",
    upscalerMessagePlaceholder: "Add optional instructions for upscaling and enhancing the image...",
    upscalerSend: "Upscale image",
    upscalerGenerating: "Upscaling image and enhancing details...",
    upscalerGenerationWait: "This may take a few moments. Please keep this page open.",
    upscalerGenerated: "Image upscaled successfully",
    upscalerGenericError: "Image could not be upscaled right now. Please try again.",
});

const currentLocale = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase()
);
const homePath = computed(() => `/${currentLocale.value}`);
const walletPath = computed(() => `/${currentLocale.value}/wallet`);
const formattedWalletBalance = computed(() => {
    if (walletBalance.value === null || walletBalance.value === undefined) return "—";

    const numericBalance = Number(walletBalance.value);
    const displayBalance = Number.isFinite(numericBalance)
        ? new Intl.NumberFormat("en-US", { maximumFractionDigits: 2 }).format(numericBalance)
        : String(walletBalance.value);

    return `${displayBalance} ${isArabic.value ? "ج.م" : "EGP"}`;
});

const activeSubToolId = computed(() => Number(
    activeConversation.value?.sub_tool_id
    || currentSubtool.value?.id
    || currentSubtool.value?.sub_tool_id
    || IMAGE_TOOL.sub_tool_id
));

const isBackgroundRemover = computed(() => activeSubToolId.value === BACKGROUND_REMOVER_TOOL.sub_tool_id);
const isImageUpscaler = computed(() => activeSubToolId.value === IMAGE_UPSCALER_TOOL.sub_tool_id);
const isImageGenerator = computed(() => activeSubToolId.value === IMAGE_TOOL.sub_tool_id);
const isPromptGenerator = computed(() => activeSubToolId.value === IMAGE_PROMPT_GENERATOR_TOOL.sub_tool_id);
const activeToolIcon = computed(() => {
    if (isBackgroundRemover.value) return "bi bi-magic";
    if (isImageUpscaler.value) return "bi bi-aspect-ratio";
    if (isPromptGenerator.value) return "bi bi-card-text";
    return "bi bi-image";
});
const promptLabels = computed(() => isArabic.value ? {
    title: "مولد وصف الصور بالذكاء الاصطناعي",
    welcome: "اكتب فكرة الصورة أو المشهد، وسيتم توليد برومبت احترافي جاهز للاستخدام.",
    example: "A cinematic futuristic Cairo skyline at sunset, realistic architecture, no text, no watermark",
    placeholder: "اكتب وصف الصورة أو الفكرة التي تريد تحويلها إلى برومبت احترافي...",
    settings: "إعدادات البرومبت",
    send: "توليد البرومبت",
    generating: "جاري إنشاء وصف احترافي للصورة...",
    generationWait: "سيتم تجهيز برومبت منسق ومناسب لمولدات الصور.",
    generated: "تم إنشاء البرومبت بنجاح",
    genericError: "تعذر إنشاء البرومبت الآن. حاول مرة أخرى.",
    promptRequired: "اكتب فكرة الصورة أولًا.",
    language: "اللغة",
    style: "النمط",
    aspectRatio: "نسبة الأبعاد",
    camera: "الكاميرا",
    lighting: "الإضاءة",
    textPolicy: "سياسة النص",
    facePolicy: "سياسة الوجوه",
    extraOptions: "خيارات إضافية",
    copyPrompt: "نسخ البرومبت",
    copied: "تم النسخ",
} : {
    title: "AI Image Prompt Generator",
    welcome: "Describe your idea and generate a professional image prompt ready to use.",
    example: "A cinematic futuristic Cairo skyline at sunset, realistic architecture, no text, no watermark",
    placeholder: "Describe the image idea you want to turn into a professional prompt...",
    settings: "Prompt settings",
    send: "Generate prompt",
    generating: "Generating a professional image prompt...",
    generationWait: "Preparing a structured prompt ready for image models.",
    generated: "Prompt generated successfully",
    genericError: "The prompt could not be generated right now. Please try again.",
    promptRequired: "Describe the image idea first.",
    language: "Language",
    style: "Style",
    aspectRatio: "Aspect ratio",
    camera: "Camera",
    lighting: "Lighting",
    textPolicy: "Text policy",
    facePolicy: "Face policy",
    extraOptions: "Extra options",
    copyPrompt: "Copy Prompt",
    copied: "Copied",
});
const activeTitle = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverTitle;
    if (isImageUpscaler.value) return labels.value.upscalerTitle;
    if (isPromptGenerator.value) return promptLabels.value.title;
    return labels.value.title;
});
const activeWelcome = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverWelcome;
    if (isImageUpscaler.value) return labels.value.upscalerWelcome;
    if (isPromptGenerator.value) return promptLabels.value.welcome;
    return labels.value.welcome;
});
const activePlaceholder = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverMessagePlaceholder;
    if (isImageUpscaler.value) return labels.value.upscalerMessagePlaceholder;
    if (isPromptGenerator.value) return promptLabels.value.placeholder;
    return labels.value.placeholder;
});
const activeSendLabel = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverSend;
    if (isImageUpscaler.value) return labels.value.upscalerSend;
    if (isPromptGenerator.value) return promptLabels.value.send;
    return labels.value.send;
});
const activeGenerating = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverGenerating;
    if (isImageUpscaler.value) return labels.value.upscalerGenerating;
    if (isPromptGenerator.value) return promptLabels.value.generating;
    return labels.value.generating;
});
const activeGenerationWait = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverGenerationWait;
    if (isImageUpscaler.value) return labels.value.upscalerGenerationWait;
    if (isPromptGenerator.value) return promptLabels.value.generationWait;
    return labels.value.generationWait;
});
const activeGenerated = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverGenerated;
    if (isImageUpscaler.value) return labels.value.upscalerGenerated;
    if (isPromptGenerator.value) return promptLabels.value.generated;
    return labels.value.generated;
});
const activeGenericError = computed(() => {
    if (isBackgroundRemover.value) return labels.value.bgRemoverGenericError;
    if (isImageUpscaler.value) return labels.value.upscalerGenericError;
    if (isPromptGenerator.value) return promptLabels.value.genericError;
    return labels.value.genericError;
});
const activePromptRequired = computed(() => isPromptGenerator.value ? promptLabels.value.promptRequired : labels.value.promptRequired);

const canSend = computed(() => {
    if (isSending.value || creatingConversation.value) return false;

    if (isBackgroundRemover.value || isImageUpscaler.value) {
        return Boolean(selectedFile.value);
    }

    return Boolean(userMessage.value.trim());
});

const promptExtraOptionsText = computed({
    get: () => normalizeStringList(state.value.extra_options).join(", "),
    set: (value) => {
        state.value.extra_options = splitOptions(value);
    },
});

useSeoMeta({
    title: computed(() => activeTitle.value),
    description: computed(() => activeWelcome.value),
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function routePath(uuid = "") {
    const suffix = uuid ? `/${uuid}` : "";
    return `/${homeService.getLang()}/subtool/${route.params.slug}/chat5${suffix}`;
}

function responseData(response) {
    return unwrapApiData(response) || {};
}

function formatConversation(conversation) {
    const firstMessage = String(
        conversation?.title
        || conversation?.first_user_message_content
        || conversation?.message?.find?.((item) => item.role === "user")?.content
        || labels.value.newChat
    ).trim();

    return {
        ...conversation,
        sub_tool_id: Number(conversation?.sub_tool_id || IMAGE_TOOL.sub_tool_id),
        title: firstMessage.split(/\s+/u).slice(0, 5).join(" "),
    };
}

function mapMessage(message, index) {
    return normalizeImageGenerationMessage(message, index);
}

function toolConfigForSubTool(subToolId = null) {
    const resolvedSubToolId = resolveStateSubToolId(subToolId);

    if (resolvedSubToolId === BACKGROUND_REMOVER_TOOL.sub_tool_id) {
        return BACKGROUND_REMOVER_TOOL;
    }

    if (resolvedSubToolId === IMAGE_PROMPT_GENERATOR_TOOL.sub_tool_id) {
        return IMAGE_PROMPT_GENERATOR_TOOL;
    }

    return IMAGE_TOOL;
}

function subToolIdFromMessage(message) {
    return Number(
        message?.metadata?.sub_tool_id
        || message?.sub_tool_id
        || activeSubToolId.value
    );
}

function stateFromMessage(message) {
    const candidate = message?.state || message?.metadata?.state;
    const subToolId = subToolIdFromMessage(message);
    return candidate && typeof candidate === "object"
        ? { ...createDefaultState(subToolId), ...candidate }
        : null;
}

function normalizeSeed(value) {
    if (value === "" || value === undefined || value === null) return null;
    const seed = Number(value);
    return Number.isFinite(seed) ? seed : null;
}

function normalizeStringList(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item || "").trim()).filter(Boolean);
    }

    if (typeof value === "string") {
        return splitOptions(value);
    }

    return [];
}

function splitOptions(value) {
    return String(value || "")
        .split(/[\n,]/u)
        .map((item) => item.trim())
        .filter(Boolean);
}

function normalizeResultsCount(value, fallback = 1, max = 4) {
    const count = Number(value || fallback);
    return Number.isFinite(count) ? Math.max(1, Math.min(max, count)) : fallback;
}

function cleanPromptGeneratorState(requestState = {}, regenerate = false) {
    const defaults = createPromptGeneratorState();

    return {
        ...defaults,
        content: requestState?.content ? String(requestState.content) : null,
        language: requestState?.language || defaults.language,
        style: requestState?.style || defaults.style,
        aspect_ratio: requestState?.aspect_ratio || defaults.aspect_ratio,
        camera: requestState?.camera || defaults.camera,
        lighting: requestState?.lighting || defaults.lighting,
        negative_prompt: String(requestState?.negative_prompt || defaults.negative_prompt),
        text_policy: requestState?.text_policy || defaults.text_policy,
        face_policy: requestState?.face_policy || defaults.face_policy,
        results_count: normalizeResultsCount(requestState?.results_count, defaults.results_count, 5),
        extra_options: normalizeStringList(requestState?.extra_options).length
            ? normalizeStringList(requestState.extra_options)
            : [...defaults.extra_options],
        last_output: regenerate ? requestState?.last_output ?? null : null,
    };
}

function cleanRequestState(requestState = {}, regenerate = false, subToolId = null) {
    const resolvedSubToolId = resolveStateSubToolId(subToolId);

    if (resolvedSubToolId === BACKGROUND_REMOVER_TOOL.sub_tool_id) {
        return {
            ...createBackgroundRemoverState(),
            provider: requestState?.provider ?? null,
            last_output: regenerate ? requestState?.last_output ?? null : null,
        };
    }

    if (resolvedSubToolId === IMAGE_UPSCALER_TOOL.sub_tool_id) {
        return {
            ...createImageUpscalerState(),
            scale: Number(requestState?.scale || 2),
            face_enhance: Boolean(requestState?.face_enhance),
            provider: requestState?.provider ?? null,
            last_output: regenerate ? requestState?.last_output ?? null : null,
        };
    }

    if (resolvedSubToolId === IMAGE_PROMPT_GENERATOR_TOOL.sub_tool_id) {
        return cleanPromptGeneratorState(requestState, regenerate);
    }

    return {
        ...createImageGeneratorState(),
        provider: requestState?.provider ?? null,
        negative_prompt: String(requestState?.negative_prompt || ""),
        size: requestState?.size || "1024x1024",
        quality: requestState?.quality || "medium",
        results_count: Number(requestState?.results_count || 1),
        output_format: requestState?.output_format || "png",
        seed: normalizeSeed(requestState?.seed),
        extra_options: [],
        last_output: regenerate ? requestState?.last_output ?? null : null,
    };
}

function cleanUiState(source = {}, subToolId = null) {
    const resolvedSubToolId = resolveStateSubToolId(subToolId);
    const cleanState = cleanRequestState(source, false, resolvedSubToolId);

    if (resolvedSubToolId === IMAGE_PROMPT_GENERATOR_TOOL.sub_tool_id) {
        return {
            ...cleanState,
            last_output: source?.last_output ?? null,
        };
    }

    return {
        ...cleanState,
        seed: null,
        last_output: null,
    };
}

/**
 * Build a fully-qualified download URL.
 *
 * Keep absolute local API URLs intact; relative URLs are resolved by Axios.
 */
function toApiUrl(url) {
    const str = String(url || "").trim();

    if (!str) return "";

    // الرابط كامل بالفعل
    if (/^https?:\/\//i.test(str)) {
        return str;
    }

    /*
     * chatServices لديه baseURL = /api/v1
     * لذلك نحذف /api/v1 من بداية الرابط إن كان موجودًا
     * حتى لا يصبح:
     * /api/v1/api/v1/generated-images/...
     */
    return str
        .replace(/^\/+/, "")
        .replace(/^api\/v1\/+/, "");
}

/**
 * Fetch a protected file from the internal API.
 *
 * Fetch a protected local file through the authenticated API client.
 */
async function fetchProtectedBlob(url) {
    return chatServices.fetchProtectedBlob(url);
}

// ─── Data loading ─────────────────────────────────────────────────────────────

async function loadSubtool() {
    try {
        const data = responseData(await homeService.showSubtool(route.params.slug));
        currentSubtool.value = data;
    } catch {
        currentSubtool.value = {
            id: IMAGE_TOOL.sub_tool_id,
            sub_tool_id: IMAGE_TOOL.sub_tool_id,
            slug: route.params.slug,
        };
    }
}

async function loadConversations() {
    loadingConversations.value = true;
    try {
        const data = responseData(await chatServices.getConversations());
        const rows = Array.isArray(data) ? data : [];
        conversations.value = rows
            .filter((conversation) => Number(conversation.sub_tool_id) === activeSubToolId.value)
            .map(formatConversation);
    } catch {
        conversations.value = [];
    } finally {
        loadingConversations.value = false;
    }
}

async function loadConversationDetails(uuid) {
    if (!uuid) return;

    loadingMessages.value = true;
    errorMessage.value = "";
    revokePreviewUrls();
    try {
        const conversation = responseData(await chatServices.getConversation(uuid));
        activeConversation.value = formatConversation(conversation);
        const rows = Array.isArray(conversation?.message)
            ? conversation.message
            : Array.isArray(conversation?.messages) ? conversation.messages : [];
        messages.value = rows.map(mapMessage);
        const latestState = [...messages.value]
            .reverse()
            .map(stateFromMessage)
            .find(Boolean);
        state.value = latestState
            ? cleanUiState(latestState, activeConversation.value?.sub_tool_id)
            : createDefaultState(activeConversation.value?.sub_tool_id);
        await loadMessagePreviews();
        await scrollToBottom();
    } catch {
        messages.value = [];
        errorMessage.value = activeGenericError.value;
    } finally {
        loadingMessages.value = false;
    }
}

async function ensureConversation() {
    if (activeConversation.value?.uuid) return activeConversation.value;

    creatingConversation.value = true;
    try {
        const conversation = formatConversation(
            responseData(await chatServices.createConversation(route.params.slug))
        );
        activeConversation.value = conversation;
        conversations.value = [
            conversation,
            ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
        ];
        await router.replace(routePath(conversation.uuid));
        return conversation;
    } finally {
        creatingConversation.value = false;
    }
}

// ─── File handling ────────────────────────────────────────────────────────────

function handleFileSelect(event) {
    const file = event?.target?.files?.[0];
    if (!file) return;
    if (selectedFilePreview.value) URL.revokeObjectURL(selectedFilePreview.value);
    selectedFile.value = file;
    selectedFilePreview.value = URL.createObjectURL(file);
    if (fileInputRef.value) fileInputRef.value.value = "";
}

function removeSelectedFile() {
    if (selectedFilePreview.value) URL.revokeObjectURL(selectedFilePreview.value);
    selectedFile.value = null;
    selectedFilePreview.value = "";
    if (fileInputRef.value) fileInputRef.value.value = "";
}

function triggerFileInput() {
    fileInputRef.value?.click();
}

// ─── Message building ─────────────────────────────────────────────────────────

function buildPayload(prompt, conversation, requestState, regenerate = false) {
    const subToolId = Number(conversation.sub_tool_id || activeSubToolId.value);
    const cleanState = cleanRequestState(requestState, regenerate, subToolId);
    const toolConfig = toolConfigForSubTool(subToolId);

    return {
        sub_tool_id: subToolId,
        conversation_uuid: conversation.uuid,
        user_message: prompt,
        state: cleanState,
        tool: toolConfig.tool_key,
        tool_key: toolConfig.tool_key,
        model_key: toolConfig.model_key,
        task_key: toolConfig.tool_key,
        regenerate,
        idempotency_key: crypto.randomUUID(),
        debug: false,
    };
}

function buildPromptGeneratorMessage(input) {
    const text = String(input || "").trim();
    if (!text) return "";

    return text.toLowerCase().startsWith(IMAGE_PROMPT_PREFIX.toLowerCase())
        ? text
        : `${IMAGE_PROMPT_PREFIX} ${text}`;
}

function textFromResults(results) {
    if (!Array.isArray(results)) return "";

    return results
        .map((result) => {
            if (typeof result === "string") return result;
            if (!result || typeof result !== "object") return "";
            return String(result.text || result.content || result.prompt || "").trim();
        })
        .filter(Boolean)
        .join("\n\n");
}

function assistantContentFromResult(result, isPromptTool) {
    if (!isPromptTool) {
        return String(result.message || "");
    }

    return String(
        result.reply
        || result.message
        || result.content
        || textFromResults(result.results || result.normalized_results)
        || result.state?.last_output
        || ""
    );
}

function isPromptGeneratorMessage(message) {
    const metadata = message?.metadata || {};
    return Number(metadata.sub_tool_id || message?.sub_tool_id || 0) === IMAGE_PROMPT_GENERATOR_TOOL.sub_tool_id
        || metadata.task_key === IMAGE_PROMPT_GENERATOR_TOOL.tool_key
        || metadata.tool_key === IMAGE_PROMPT_GENERATOR_TOOL.tool_key
        || metadata.tool === IMAGE_PROMPT_GENERATOR_TOOL.tool_key
        || metadata.model_key === IMAGE_PROMPT_GENERATOR_TOOL.model_key;
}

function buildBgRemoverFormData(conversation, file, userInput = "") {
    const formData = new FormData();
    const message = String(userInput || "").trim() || BG_REMOVER_MESSAGE;

    formData.append("conversation_uuid", String(conversation.uuid || ""));
    formData.append("sub_tool_id", String(BACKGROUND_REMOVER_TOOL.sub_tool_id));
    formData.append("user_message", message);
    formData.append("state", JSON.stringify({ provider: null, last_output: null }));
    formData.append("debug", "0");
    formData.append("tool", String(BACKGROUND_REMOVER_TOOL.tool_key || ""));
    formData.append("tool_key", String(BACKGROUND_REMOVER_TOOL.tool_key || ""));
    formData.append("model_key", String(BACKGROUND_REMOVER_TOOL.model_key || ""));
    formData.append("idempotency_key", crypto.randomUUID());
    formData.append("file", file);

    return formData;
}

function buildUpscalerFormData(conversation, file, requestState, userInput = "") {
    const formData = new FormData();
    const message = String(userInput || "").trim() || IMAGE_UPSCALER_MESSAGE;

    formData.append("conversation_uuid", String(conversation.uuid || ""));
    formData.append("sub_tool_id", String(IMAGE_UPSCALER_TOOL.sub_tool_id));
    formData.append("user_message", message);
    formData.append("state", JSON.stringify({
        scale: Number(requestState.scale || 2),
        face_enhance: Boolean(requestState.face_enhance),
        provider: requestState.provider ?? null,
        last_output: requestState.last_output ?? null,
    }));
    formData.append("debug", "0");
    formData.append("tool", String(IMAGE_UPSCALER_TOOL.tool_key || ""));
    formData.append("tool_key", String(IMAGE_UPSCALER_TOOL.tool_key || ""));
    formData.append("model_key", String(IMAGE_UPSCALER_TOOL.model_key || ""));
    formData.append("idempotency_key", crypto.randomUUID());
    formData.append("file", file);

    return formData;
}

// ─── Send / retry ─────────────────────────────────────────────────────────────

async function sendMessage(options = {}) {
    const isBgRemover = isBackgroundRemover.value;
    const isUpscaler = isImageUpscaler.value;
    const isPromptTool = isPromptGenerator.value;
    const rawPrompt = String(options.prompt ?? userMessage.value).trim();
    let prompt = rawPrompt;

    if (isBgRemover) {
        prompt = rawPrompt || BG_REMOVER_MESSAGE;
    } else if (isUpscaler) {
        prompt = rawPrompt || IMAGE_UPSCALER_MESSAGE;
    } else if (isPromptTool) {
        prompt = buildPromptGeneratorMessage(rawPrompt);
    }

    if (isSending.value) return;

    if ((isBgRemover || isUpscaler) && !selectedFile.value) {
        errorMessage.value = labels.value.fileRequired;
        return;
    }
    if (!isBgRemover && !isUpscaler && !rawPrompt) {
        errorMessage.value = activePromptRequired.value;
        return;
    }

    const regenerate = Boolean(options.regenerate);
    const requestState = cleanRequestState(options.requestState || state.value, regenerate, activeSubToolId.value);
    if (isPromptTool) {
        requestState.content = rawPrompt;
    }
    const optimisticKey = `local-user-${Date.now()}`;
    const displayContent = rawPrompt || selectedFile.value?.name || activeSendLabel.value;
    errorMessage.value = "";
    lastFailedRequest.value = null;
    isSending.value = true;

    try {
        const conversation = await ensureConversation();

        messages.value.push(mapMessage({
            localKey: optimisticKey,
            role: "user",
            content: displayContent,
            metadata: { state: requestState },
        }, messages.value.length));
        messages.value[messages.value.length - 1].localKey = optimisticKey;
        userMessage.value = "";
        autoResize();
        await scrollToBottom();

        let result;
        if (isBgRemover) {
            const formData = buildBgRemoverFormData(conversation, selectedFile.value, prompt);
            result = responseData(await chatServices.sendMessageFormData(formData));
            removeSelectedFile();

            if (result && Array.isArray(result.files)) {
                result.images = result.files.map((f) => ({
                    id: String(f.file_id || f.id || Date.now()),
                    filename: String(f.filename || "background-removed.png"),
                    content_type: String(f.content_type || "image/png"),
                    preview_url: String(f.download_url || f.preview_url || ""),
                    download_url: String(f.download_url || f.preview_url || ""),
                    size_bytes: Number(f.size_bytes || 0),
                }));
            }
        } else if (isUpscaler) {
            const formData = buildUpscalerFormData(conversation, selectedFile.value, requestState, prompt);
            result = responseData(await chatServices.sendMessageFormData(formData));
            removeSelectedFile();

            if (result && Array.isArray(result.files)) {
                result.images = result.files.map((f) => ({
                    id: String(f.file_id || f.id || Date.now()),
                    filename: String(f.filename || "image-upscaled.png"),
                    content_type: String(f.content_type || "image/png"),
                    preview_url: String(f.download_url || f.preview_url || ""),
                    download_url: String(f.download_url || f.preview_url || ""),
                    size_bytes: Number(f.size_bytes || 0),
                }));
            }
        } else {
            const payload = buildPayload(prompt, conversation, requestState, regenerate);
            result = responseData(await chatServices.sendMessage(payload));
        }

        const rawImages = isPromptTool ? [] : (result.images || result.files || []);
        const images = normalizeGeneratedImages(rawImages);
        const assistantContent = assistantContentFromResult(result, isPromptTool) || (
            isPromptTool ? promptLabels.value.generated : result.message
        );
        const assistant = mapMessage({
            id: result.assistant_message_id,
            role: "assistant",
            content: assistantContent,
            is_error: result.success === false || result.type === "error",
            images,
            state: result.state,
            metadata: {
                ...result,
                images,
                generation: result.metadata,
                request_prompt: rawPrompt,
                provider_user_message: prompt,
                last_output: result.state?.last_output || assistantContent,
                failed_files: result.failed_files || [],
            },
        }, messages.value.length);
        messages.value.push(assistant);
        state.value = cleanUiState(result.state || requestState, activeSubToolId.value);

        if (assistant.is_error) {
            lastFailedRequest.value = {
                prompt: rawPrompt,
                requestState: cleanRequestState(requestState, false, activeSubToolId.value),
                regenerate: false,
            };
        } else {
            await loadPreviewsForMessage(assistant);
        }

        await loadConversations();
        await scrollToBottom();
    } catch (error) {
        if (import.meta.env.DEV) {
            console.error("[chat5 send error]", {
                status: error?.response?.status,
                data: error?.response?.data,
                message: error?.message,
            });
        }

        const errorLabel = activeGenericError.value;
        const message = error?.response?.data?.message || error?.response?.data?.error || errorLabel;
        errorMessage.value = localizeError(message);
        lastFailedRequest.value = {
            prompt: rawPrompt,
            requestState: cleanRequestState(requestState, false, activeSubToolId.value),
            regenerate: false,
        };
    } finally {
        isSending.value = false;
    }
}

function retryLastRequest() {
    if (!lastFailedRequest.value) return;
    sendMessage(lastFailedRequest.value);
}

function retryMessage(message) {
    const prompt = String(message?.metadata?.request_prompt || lastUserPromptBefore(message) || "").trim();
    const requestState = stateFromMessage(message) || state.value;
    const subToolId = subToolIdFromMessage(message);
    sendMessage({
        prompt,
        requestState: {
            ...cleanRequestState(requestState, true, subToolId),
            last_output: message?.metadata?.last_output ?? requestState?.last_output ?? null,
        },
        regenerate: true,
    });
}

function regenerate(message) {
    const prompt = String(
        message?.metadata?.request_prompt
        || lastUserPromptBefore(message)
        || ""
    ).trim();
    const subToolId = subToolIdFromMessage(message);
    const previousState = stateFromMessage(message) || createDefaultState(subToolId);

    sendMessage({
        prompt,
        requestState: {
            ...cleanRequestState(previousState, true, subToolId),
            last_output: message?.metadata?.last_output ?? previousState?.last_output ?? null,
        },
        regenerate: true,
    });
}

function lastUserPromptBefore(message) {
    const index = messages.value.findIndex((item) => item.localKey === message.localKey);
    for (let cursor = index - 1; cursor >= 0; cursor -= 1) {
        if (messages.value[cursor].role === "user") return messages.value[cursor].content;
    }
    return "";
}

// ─── Conversation management ──────────────────────────────────────────────────

async function startNewChat() {
    if (creatingConversation.value || isSending.value) return;
    creatingConversation.value = true;
    try {
        const conversation = formatConversation(
            responseData(await chatServices.createConversation(route.params.slug))
        );
        revokePreviewUrls();
        removeSelectedFile();
        activeConversation.value = conversation;
        conversations.value = [
            conversation,
            ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
        ];
        messages.value = [];
        userMessage.value = "";
        state.value = createDefaultState();
        errorMessage.value = "";
        sidebarOpen.value = false;
        await router.push(routePath(conversation.uuid));
        await nextTick();
        if (!isBackgroundRemover.value && !isImageUpscaler.value) {
            textareaRef.value?.focus();
        }
    } catch {
        errorMessage.value = activeGenericError.value;
    } finally {
        creatingConversation.value = false;
    }
}

async function openConversation(conversation) {
    if (isSending.value || conversation.uuid === activeConversation.value?.uuid) return;
    sidebarOpen.value = false;
    await router.push(routePath(conversation.uuid));
}

async function deleteConversation(conversation) {
    if (!window.confirm(labels.value.deleteConfirm)) return;
    deletingUuid.value = conversation.uuid;
    try {
        await chatServices.deleteConversation(conversation.uuid);
        conversations.value = conversations.value.filter((item) => item.uuid !== conversation.uuid);
        if (activeConversation.value?.uuid === conversation.uuid) {
            revokePreviewUrls();
            removeSelectedFile();
            activeConversation.value = null;
            messages.value = [];
            state.value = createDefaultState();
            await router.push(routePath());
        }
    } finally {
        deletingUuid.value = "";
    }
}

// ─── Image preview / download ─────────────────────────────────────────────────

async function loadMessagePreviews() {
    await Promise.all(messages.value.map(loadPreviewsForMessage));
}

async function loadPreviewsForMessage(message) {
    if (message.role !== "assistant" || !message.images.length) return;
    await Promise.all(message.images.map(loadImagePreview));
}

/**
 * Load a preview for a single image.
 *
 * Uses the authenticated local API client for protected file access.
 */
async function loadImagePreview(image) {
    if (!image?.preview_url || previewLoading.value[image.id]) return;
    previewLoading.value = { ...previewLoading.value, [image.id]: true };
    previewErrors.value = { ...previewErrors.value, [image.id]: false };
    try {
        const url = toApiUrl(image.preview_url);
        const blob = await fetchProtectedBlob(url);
        revokePreviewUrl(image.id);
        previewUrls.value = {
            ...previewUrls.value,
            [image.id]: URL.createObjectURL(blob),
        };
    } catch {
        previewErrors.value = { ...previewErrors.value, [image.id]: true };
    } finally {
        previewLoading.value = { ...previewLoading.value, [image.id]: false };
    }
}

function markPreviewFailed(image) {
    revokePreviewUrl(image.id);
    previewErrors.value = { ...previewErrors.value, [image.id]: true };
}

/**
 * Download an image via the internal API.
 *
 * Fetches a protected local file, then triggers a browser download using a
 * temporary <a> element.
 */
async function downloadImage(image) {
    actionLoading.value = `download-${image.id}`;
    try {
        const url = toApiUrl(image.download_url);
        const blob = await fetchProtectedBlob(url);
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = objectUrl;
        link.download = image.filename || "image";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);
    } catch {
        errorMessage.value = labels.value.downloadFailed;
    } finally {
        actionLoading.value = "";
    }
}

async function openImage(image) {
    try {
        if (!previewUrls.value[image.id]) await loadImagePreview(image);
        const url = previewUrls.value[image.id];
        if (!url) throw new Error("preview unavailable");
        window.open(url, "_blank", "noopener,noreferrer");
    } catch {
        errorMessage.value = labels.value.openFailed;
    }
}

// ─── Preview URL lifecycle ────────────────────────────────────────────────────

function revokePreviewUrl(id) {
    const url = previewUrls.value[id];
    if (url) URL.revokeObjectURL(url);
    const next = { ...previewUrls.value };
    delete next[id];
    previewUrls.value = next;
}

function revokePreviewUrls() {
    Object.values(previewUrls.value).forEach((url) => URL.revokeObjectURL(url));
    previewUrls.value = {};
    previewLoading.value = {};
    previewErrors.value = {};
}

// ─── Sidebar quick actions ─────────────────────────────────────────────────────

function walletCacheKey() {
    return `${WALLET_CACHE_PREFIX}:${currentLocale.value}`;
}

function readWalletCache() {
    try {
        const raw = sessionStorage.getItem(walletCacheKey());
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        if (!parsed?.expiresAt || parsed.expiresAt < Date.now()) {
            sessionStorage.removeItem(walletCacheKey());
            return null;
        }

        return parsed.value;
    } catch {
        return null;
    }
}

function writeWalletCache(value) {
    try {
        sessionStorage.setItem(
            walletCacheKey(),
            JSON.stringify({
                value,
                expiresAt: Date.now() + WALLET_CACHE_TTL,
            })
        );
    } catch {
        // Ignore storage restrictions; the wallet still works without caching.
    }
}

async function fetchWalletBalance() {
    if (!localStorage.getItem("auth_token")) {
        walletBalance.value = null;
        return;
    }

    const cachedBalance = readWalletCache();
    if (cachedBalance !== null) {
        walletBalance.value = cachedBalance;
        return;
    }

    walletLoading.value = true;

    try {
        const response = await api.get("/users/wallet");

        if (response.data?.status === "success") {
            walletBalance.value = response.data.data?.balance ?? 0;
            writeWalletCache(walletBalance.value);
        }
    } catch {
        walletBalance.value = null;
    } finally {
        walletLoading.value = false;
    }
}

function goHome() {
    window.location.href = homePath.value;
}

function goToWallet() {
    window.location.href = walletPath.value;
}

// ─── UI helpers ───────────────────────────────────────────────────────────────

function fillExample() {
    userMessage.value = isPromptGenerator.value ? promptLabels.value.example : labels.value.example;
    nextTick(autoResize);
}

async function copyPrompt(message) {
    const text = String(message?.content || "").trim();
    if (!text) return;

    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement("textarea");
            textarea.value = text;
            textarea.setAttribute("readonly", "");
            textarea.style.position = "fixed";
            textarea.style.opacity = "0";
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand("copy");
            textarea.remove();
        }

        copiedPromptKey.value = message.localKey;
        window.setTimeout(() => {
            if (copiedPromptKey.value === message.localKey) copiedPromptKey.value = "";
        }, 1600);
    } catch {
        copiedPromptKey.value = "";
    }
}

function autoResize() {
    nextTick(() => {
        if (!textareaRef.value) return;
        textareaRef.value.style.height = "auto";
        textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 160)}px`;
    });
}

function localizeError(message) {
    const text = String(message || "").trim();
    if (!text || /could not|failed|error/i.test(text)) return activeGenericError.value;
    return text;
}

function closeSidebar() {
    if (window.innerWidth <= 900) {
        sidebarOpen.value = false;
    } else {
        desktopSidebarCollapsed.value = true;
    }
}

function openSidebar() {
    if (window.innerWidth <= 900) {
        sidebarOpen.value = true;
    } else {
        desktopSidebarCollapsed.value = false;
    }
}

async function scrollToBottom() {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

async function initialize() {
    await loadSubtool();
    await loadConversations();
    if (route.params.uuid) await loadConversationDetails(String(route.params.uuid));
}

watch(
    () => route.params.uuid,
    async (uuid, previousUuid) => {
        if (uuid === previousUuid) return;
        if (uuid) {
            await loadConversationDetails(String(uuid));
        } else {
            revokePreviewUrls();
            removeSelectedFile();
            activeConversation.value = null;
            messages.value = [];
            state.value = createDefaultState();
        }
    }
);

watch(
    () => route.params.slug,
    async (slug, previousSlug) => {
        if (!slug || slug === previousSlug) return;
        revokePreviewUrls();
        removeSelectedFile();
        activeConversation.value = null;
        messages.value = [];
        state.value = createDefaultState();
        await initialize();
    }
);

onMounted(() => {
    initialize();
    fetchWalletBalance();
});
onUnmounted(() => {
    revokePreviewUrls();
    removeSelectedFile();
});
</script>

<style scoped>
:global(html),
:global(body),
:global(#app) {
    height: 100%;
    min-height: 100%;
}

.detector-chat {
    --navy: #0d4d97;
    --blue: #1f87c9;
    --cyan: #35b8dc;
    --ink: #15324b;
    --muted: #687b8e;
    --line: #d8e6f7;
    width: 100%;
    height: 100dvh;
    min-height: 100dvh;
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    overflow: hidden;
    background: #f5f8fc;
    color: var(--ink);
    transition: grid-template-columns 0.25s ease;
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
    height: 100%;
    min-height: 0;
    padding: 24px 18px;
    overflow: hidden;
    border-inline-end: 1px solid var(--line);
    background: #fff;
    transition: width 0.25s ease, transform 0.25s ease, padding 0.25s ease;
}

.sidebar-quick-actions {
    display: grid;
    grid-template-columns: minmax(0, 0.82fr) minmax(0, 1.18fr);
    gap: 8px;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    margin-bottom: 16px;
}

.sidebar-quick-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    height: 42px;
    padding: 0 9px;
    overflow: hidden;
    border: 1px solid rgba(18, 63, 109, 0.1);
    border-radius: 12px;
    color: var(--navy);
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 8px 18px rgba(18, 63, 109, 0.07);
    transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.sidebar-quick-button:hover {
    transform: translateY(-1px);
    border-color: rgba(31, 135, 201, 0.28);
    box-shadow: 0 10px 22px rgba(18, 63, 109, 0.11);
}

.sidebar-quick-button i {
    flex: 0 0 auto;
    font-size: 15px;
}

.sidebar-home-button>span {
    min-width: 0;
    overflow: hidden;
    font-size: 11px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-wallet-copy {
    display: grid;
    min-width: 0;
    line-height: 1.05;
    text-align: start;
}

.sidebar-wallet-copy small,
.sidebar-wallet-copy strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-wallet-copy small {
    color: var(--muted);
    font-size: 8.5px;
    font-weight: 700;
}

.sidebar-wallet-copy strong {
    margin-top: 3px;
    color: var(--navy);
    font-size: 10.5px;
    font-weight: 800;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}

.sidebar-brand strong {
    min-width: 0;
    font-size: 15px;
    line-height: 1.4;
}

.brand-icon,
.welcome-icon {
    display: grid;
    flex: 0 0 auto;
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
    color: var(--navy);
    background: #fff;
}

.icon-button {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
}

.sidebar-close-toggle {
    margin-inline-start: auto;
}

.new-chat-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 48px;
    margin: 16px 10px;
    padding: 0 16px;
    border: 0;
    border-radius: 14px;
    color: #fff;
    background: #123f6d;
    font-size: 14px;
    font-weight: 700;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.new-chat-button:hover:not(:disabled) {
    transform: scale(1.02);
    box-shadow: 0 20px 36px rgba(18, 63, 109, 0.16);
}

.section-label {
    margin: 18px 0 10px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0;
}

.sidebar-status {
    padding: 18px 10px;
    border: 1px dashed var(--line);
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
    display: flex;
    flex: 1;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(18, 63, 109, 0.08);
    border-radius: 16px;
    color: var(--muted);
    background: #fbfdff;
    text-align: start;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.conversation-open span {
    overflow: hidden;
    font-size: 13px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-item.active .conversation-open,
.conversation-open:hover {
    transform: scale(1.02);
    color: var(--navy);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 18px 32px rgba(18, 63, 109, 0.08);
}

.conversation-delete {
    display: grid;
    flex: 0 0 42px;
    place-items: center;
    width: 42px;
    border-radius: 14px;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.conversation-delete:hover {
    transform: scale(1.02);
    background: rgba(31, 135, 201, 0.08);
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

.messages {
    flex: 1 1 auto;
    min-height: 0;
    padding: 26px max(24px, calc((100% - 900px) / 2)) 34px;
    overflow-x: hidden;
    overflow-y: auto;
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
    display: inline-block;
    width: 24px;
    height: 24px;
    border: 2px solid #cfe3ef;
    border-top-color: var(--blue);
    border-radius: 50%;
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

.welcome-card h1,
.welcome-card p {
    margin: 0;
}

.welcome-card h1 {
    font-size: 25px;
}

.welcome-card p {
    color: var(--muted);
    line-height: 1.8;
}

.suggestion {
    max-width: 100%;
    padding: 11px 14px;
    border: 1px dashed #b9d7ed;
    border-radius: 12px;
    color: var(--navy);
    background: #f7fcff;
    line-height: 1.6;
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
    flex: 0 0 38px;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1px solid #d5e5f2;
    border-radius: 12px;
    color: var(--navy);
    background: #fff;
}

.message-body {
    max-width: min(780px, calc(100% - 50px));
    padding: 13px 16px;
    border: 1px solid #dbe8f3;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 10px 24px rgba(18, 63, 109, 0.05);
}

.message-row.user .message-body {
    color: #fff;
    border-color: #123f6d;
    background: #123f6d;
}

.message-body.card-shell {
    width: min(780px, calc(100% - 50px));
    max-width: min(780px, calc(100% - 50px));
    padding: 0;
    overflow: hidden;
}

.message-content {
    margin: 0;
    line-height: 1.75;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.image-response-card {
    padding: 16px;
}

.prompt-response-card {
    padding: 16px;
}

.prompt-output {
    padding: 12px 14px;
    border: 1px solid #d8e6f7;
    border-radius: 10px;
    color: var(--ink);
    background: #fbfdff;
}

.copy-prompt-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid #bfd7e8;
    border-radius: 9px;
    color: var(--navy);
    background: #fff;
    font-size: 12px;
    font-weight: 800;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease;
}

.copy-prompt-button:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: var(--blue);
    background: #edf7fc;
}

.response-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    color: var(--navy);
}

.response-heading span {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
}

.response-heading small {
    color: var(--muted);
}

.generated-images {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
    gap: 12px;
}

.generated-images.image-count-2,
.generated-images.image-count-3,
.generated-images.image-count-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.generated-image-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(18, 63, 109, 0.06);
}

.image-preview {
    position: relative;
    display: grid;
    place-items: center;
    width: 100%;
    min-height: 280px;
    max-height: 620px;
    overflow: hidden;
    background: #f3f7fb;
}

.image-preview img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 620px;
    object-fit: contain;
}

.image-skeleton {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #e8f0f6 25%, #f7fbfe 50%, #e8f0f6 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
}

.image-skeleton {
    display: grid;
    place-items: center;
    color: #8aa8bd;
    font-size: 28px;
}

.image-load-error {
    display: grid;
    gap: 8px;
    place-items: center;
    padding: 16px;
    color: #9c3d3d;
    text-align: center;
}

.image-load-error button {
    padding: 6px 10px;
    border: 1px solid #e7c1c1;
    border-radius: 8px;
    color: #8f3434;
    background: #fff;
}

.image-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 12px;
    border-top: 1px solid var(--line);
    background: #fff;
}

.image-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 0;
    min-height: 42px;
    padding: 9px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.image-action-primary {
    border: 1px solid #123f6d;
    color: #fff;
    background: #123f6d;
}

.image-action-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    background: var(--navy);
    box-shadow: 0 8px 18px rgba(18, 63, 109, 0.18);
}

.image-action-secondary {
    border: 1px solid #bfd7e8;
    color: var(--navy);
    background: #fff;
}

.image-action-secondary:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: var(--blue);
    background: #edf7fc;
}

.image-action i {
    font-size: 14px;
}

.result-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 14px;
}

.regenerate-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 0 15px;
    border: 1px solid #bfd7e8;
    border-radius: 10px;
    color: var(--navy);
    background: #fff;
    font-size: 13px;
    font-weight: 700;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease;
}

.regenerate-button:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: var(--blue);
    background: #edf7fc;
}

.clean-error-card button,
.error-banner button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid #bfd7e8;
    border-radius: 10px;
    color: var(--navy);
    background: #fff;
    font-weight: 700;
}

.clean-error-card button:hover:not(:disabled) {
    background: #edf7fc;
}

.button-spinner {
    display: inline-block;
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

.partial-warning {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 12px;
    border: 1px solid #efd9a4;
    border-radius: 8px;
    color: #755719;
    background: #fff9e9;
    font-size: 13px;
}

.clean-error-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    color: #8d3030;
    background: #fff7f7;
}

.clean-error-card span {
    flex: 1;
}

.generation-message {
    align-items: flex-start;
}

.message-body.card-shell.generation-loading {
    width: min(620px, calc(100% - 50px));
    max-width: min(620px, calc(100% - 50px));
    padding: 16px;
    overflow: hidden;
    border: 1px solid #d8e6f7;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 14px 34px rgba(18, 63, 109, 0.08);
}

.generation-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
}

.generation-header-content {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.generation-header-content>div {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.generation-header strong {
    color: var(--navy);
    font-size: 14px;
    font-weight: 800;
}

.generation-header small {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

.generation-icon {
    display: grid;
    flex: 0 0 36px;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
    box-shadow: 0 8px 18px rgba(18, 63, 109, 0.16);
}

.generation-icon i {
    animation: generationSparkle 1.6s ease-in-out infinite;
}

.generation-spinner {
    flex: 0 0 auto;
    width: 21px;
    height: 21px;
    border: 2px solid #d5e7f3;
    border-top-color: var(--blue);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.loading-image-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
}

.loading-image-grid.loading-count-2,
.loading-image-grid.loading-count-3,
.loading-image-grid.loading-count-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.loading-image-card {
    position: relative;
    display: grid;
    place-items: center;
    width: 100%;
    aspect-ratio: 1 / 1;
    min-height: 180px;
    max-height: 360px;
    overflow: hidden;
    border: 1px solid #deebf4;
    border-radius: 14px;
    background: #edf4fa;
}

.loading-image-shimmer {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(100deg,
            transparent 20%,
            rgba(255, 255, 255, 0.72) 48%,
            transparent 76%),
        linear-gradient(145deg, #edf4fa, #e3eef7);
    background-size: 220% 100%, 100% 100%;
    animation: imageShimmer 1.5s linear infinite;
}

.loading-image-placeholder {
    position: relative;
    z-index: 2;
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    border: 1px solid rgba(31, 135, 201, 0.12);
    border-radius: 15px;
    color: #7ea6c1;
    background: rgba(255, 255, 255, 0.72);
    font-size: 22px;
    backdrop-filter: blur(4px);
    animation: imagePlaceholderPulse 1.6s ease-in-out infinite;
}

.loading-prompt-card {
    display: grid;
    gap: 9px;
    padding: 12px;
    border: 1px solid #deebf4;
    border-radius: 12px;
    background: #fbfdff;
}

.loading-prompt-card span {
    display: block;
    height: 12px;
    border-radius: 999px;
    background: linear-gradient(90deg, #e8f0f6 25%, #f7fbfe 50%, #e8f0f6 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
}

.loading-prompt-card span:nth-child(2) {
    width: 86%;
}

.loading-prompt-card span:nth-child(3) {
    width: 62%;
}

.generation-progress {
    height: 3px;
    margin-top: 14px;
    overflow: hidden;
    border-radius: 999px;
    background: #e3edf5;
}

.generation-progress-line {
    display: block;
    width: 38%;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--navy), var(--cyan));
    animation: generationProgress 1.7s ease-in-out infinite;
}

[dir="rtl"] .generation-progress-line {
    animation-name: generationProgressRtl;
}

.composer {
    position: relative;
    z-index: 10;
    flex: 0 0 auto;
    width: 100%;
    padding: 14px max(24px, calc((100% - 900px) / 2)) 16px;
    border-top: 1px solid var(--line);
    background: #fff;
}

.error-banner {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 10px;
    padding: 10px 12px;
    border: 1px solid #efc7c7;
    border-radius: 10px;
    color: #8d3030;
    background: #fff7f7;
    font-size: 13px;
}

.error-banner span {
    flex: 1;
}

.options-panel {
    margin-bottom: 10px;
    border: 1px solid #d7e6f2;
    border-radius: 12px;
    background: #fbfdff;
}

.options-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 14px;
    color: var(--navy);
    font-size: 13px;
    font-weight: 700;
    list-style: none;
    cursor: pointer;
    user-select: none;
}

.options-panel-header::-webkit-details-marker {
    display: none;
}

.options-panel-header::marker {
    display: none;
    content: "";
}

.options-form {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    padding: 0 14px 14px;
}

.options-form label {
    display: grid;
    gap: 6px;
    min-width: 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

.options-form label.wide {
    grid-column: span 3;
}

.options-form input,
.options-form select,
.options-form textarea {
    width: 100%;
    min-width: 0;
    padding: 9px 10px;
    border: 1px solid #cfdfea;
    border-radius: 9px;
    outline: none;
    color: var(--ink);
    background: #fff;
    font-size: 13px;
}

.options-form textarea {
    resize: vertical;
}

.options-form input:focus,
.options-form select:focus,
.options-form textarea:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.12);
}

.input-box {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 9px 10px 9px 14px;
    border: 1px solid #cfdfea;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(18, 63, 109, 0.06);
}

.input-box:focus-within {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.12);
}

.input-box textarea {
    flex: 1;
    min-height: 38px;
    max-height: 160px;
    padding: 8px 0;
    resize: none;
    border: 0;
    outline: none;
    color: var(--ink);
    background: transparent;
    line-height: 1.5;
}

.send-button {
    display: grid;
    flex: 0 0 42px;
    place-items: center;
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 12px;
    color: #fff;
    background: #123f6d;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.send-button:hover:not(:disabled) {
    transform: scale(1.04);
    box-shadow: 0 12px 24px rgba(18, 63, 109, 0.2);
}

.composer-hint {
    margin: 7px 4px 0;
    color: var(--muted);
    font-size: 11px;
    text-align: center;
}

.desktop-sidebar-open-toggle,
.mobile-sidebar-toggle {
    position: fixed;
    z-index: 120;
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(18, 63, 109, 0.1);
    border-radius: 14px;
    color: var(--navy);
    background: #fff;
    box-shadow: 0 14px 30px rgba(18, 63, 109, 0.14);
}

.desktop-sidebar-open-toggle {
    top: 12px;
    inset-inline-start: 12px;
}

.mobile-only {
    display: none;
}

.sidebar-overlay {
    display: none;
}

@media (min-width: 901px) {
    .detector-chat.sidebar-collapsed {
        grid-template-columns: 0 minmax(0, 1fr);
    }

    .detector-chat.sidebar-collapsed .sidebar {
        width: 0;
        padding-inline: 0;
        border-color: transparent;
        pointer-events: none;
    }

    .detector-chat.sidebar-collapsed .sidebar>* {
        visibility: hidden;
        opacity: 0;
    }
}

@media (max-width: 900px) {
    .detector-chat {
        grid-template-columns: minmax(0, 1fr);
    }

    .sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        inset-inline-start: 0;
        height: 100dvh;
        width: min(84vw, 310px);
        transform: translateX(-105%);
        box-shadow: 20px 0 50px rgba(18, 63, 109, 0.18);
    }

    [dir="rtl"] .sidebar {
        transform: translateX(105%);
    }

    .sidebar.sidebar-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        z-index: 15;
        display: block;
        inset: 0;
        border: 0;
        background: rgba(14, 42, 67, 0.35);
    }

    .mobile-only {
        display: grid;
    }

    .mobile-sidebar-toggle {
        top: 12px;
        inset-inline-start: 12px;
    }

    .desktop-sidebar-open-toggle {
        display: none;
    }

    .sidebar-quick-actions {
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
    }

    .sidebar-quick-button {
        height: 40px;
        padding-inline: 8px;
    }

    .messages {
        padding: 64px 16px 24px;
    }

    .composer {
        padding: 12px 16px;
    }

    .message-body,
    .message-body.card-shell {
        width: calc(100% - 48px);
        max-width: calc(100% - 48px);
    }

    .options-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .options-form label.wide {
        grid-column: 1 / -1;
    }
}

@media (max-width: 560px) {
    .message-body.card-shell.generation-loading {
        width: calc(100% - 48px);
        max-width: calc(100% - 48px);
        padding: 13px;
    }

    .generated-images.image-count-2,
    .generated-images.image-count-3,
    .generated-images.image-count-4 {
        grid-template-columns: minmax(0, 1fr);
    }

    .loading-image-grid,
    .loading-image-grid.loading-count-2,
    .loading-image-grid.loading-count-3,
    .loading-image-grid.loading-count-4 {
        grid-template-columns: minmax(0, 1fr);
    }

    .loading-image-card {
        min-height: 210px;
        max-height: 330px;
    }

    .generation-header small {
        font-size: 10px;
    }

    .image-actions {
        grid-template-columns: minmax(0, 1fr);
    }

    .image-action {
        width: 100%;
        min-height: 44px;
    }

    .result-footer {
        justify-content: stretch;
    }

    .regenerate-button {
        width: 100%;
    }

    .options-form {
        grid-template-columns: minmax(0, 1fr);
    }

    .options-form label.wide {
        grid-column: auto;
    }

    .response-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .clean-error-card {
        align-items: flex-start;
        flex-wrap: wrap;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes shimmer {
    to {
        background-position: -200% 0;
    }
}

@keyframes imageShimmer {
    from {
        background-position: 220% 0, 0 0;
    }

    to {
        background-position: -120% 0, 0 0;
    }
}

@keyframes imagePlaceholderPulse {

    0%,
    100% {
        transform: scale(1);
        opacity: 0.7;
    }

    50% {
        transform: scale(1.06);
        opacity: 1;
    }
}

@keyframes generationSparkle {

    0%,
    100% {
        transform: rotate(0deg) scale(1);
    }

    50% {
        transform: rotate(12deg) scale(1.12);
    }
}

@keyframes generationProgress {
    0% {
        transform: translateX(-130%);
    }

    50% {
        transform: translateX(120%);
    }

    100% {
        transform: translateX(300%);
    }
}

@keyframes generationProgressRtl {
    0% {
        transform: translateX(130%);
    }

    50% {
        transform: translateX(-120%);
    }

    100% {
        transform: translateX(-300%);
    }
}

.options-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: var(--navy);
    background: rgba(31, 135, 201, 0.08);
    transition: transform 0.3s ease, background-color 0.2s ease;
}

.options-chevron i {
    display: block;
    font-size: 14px;
    transition: transform 0.3s ease;
}

.options-panel[open] .options-chevron i {
    transform: rotate(180deg);
}

.options-panel-header:hover .options-chevron {
    background: rgba(31, 135, 201, 0.15);
}

.hidden-file-input {
    display: none;
}

.attach-button {
    display: grid;
    place-items: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    border: 1px solid #cfdfea;
    border-radius: 12px;
    color: var(--muted);
    background: #fbfdff;
    transition: all 0.2s ease;
}

.attach-button:hover:not(:disabled) {
    color: var(--navy);
    border-color: var(--blue);
    background: #edf7fc;
}

.composer-file-preview {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 8px;
    border: 1px solid #cfdfea;
    overflow: hidden;
    background: #f3f7fb;
}

.composer-file-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-file {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 20px;
    height: 20px;
    border: none;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    display: grid;
    place-items: center;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}

[dir="rtl"] .remove-file {
    right: auto;
    left: -4px;
}

.remove-file:hover {
    background: rgba(220, 53, 69, 0.9);
}

.bg-remover-placeholder {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 0 10px;
    color: var(--muted);
    font-size: 13px;
    user-select: none;
}

.bg-remover-placeholder p {
    margin: 0;
}
/* =========================
   DARK MODE
========================= */
:global(html[data-theme="dark"]) .detector-chat {
    --navy: #154677;
    --blue: #5cc8f0;
    --cyan: #5cc8f0;
    --ink: var(--theme-text-primary);
    --muted: var(--theme-text-muted);
    --line: var(--theme-border);
    background: var(--theme-bg);
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .detector-chat .sidebar,
:global(html[data-theme="dark"]) .detector-chat .composer {
    background: var(--theme-surface);
    border-color: var(--theme-border);
    box-shadow: 0 18px 44px var(--theme-shadow);
}

:global(html[data-theme="dark"]) .detector-chat .sidebar-brand strong,
:global(html[data-theme="dark"]) .detector-chat .welcome-card h2,
:global(html[data-theme="dark"]) .detector-chat .response-heading,
:global(html[data-theme="dark"]) .detector-chat .generation-header strong,
:global(html[data-theme="dark"]) .detector-chat .sidebar-wallet-copy strong {
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .detector-chat .section-label,
:global(html[data-theme="dark"]) .detector-chat .welcome-card p,
:global(html[data-theme="dark"]) .detector-chat .response-heading small,
:global(html[data-theme="dark"]) .detector-chat .generation-header small,
:global(html[data-theme="dark"]) .detector-chat .sidebar-wallet-copy small,
:global(html[data-theme="dark"]) .detector-chat .composer-hint {
    color: var(--theme-text-muted);
}

:global(html[data-theme="dark"]) .detector-chat .sidebar-quick-button,
:global(html[data-theme="dark"]) .detector-chat .conversation-open,
:global(html[data-theme="dark"]) .detector-chat .conversation-delete,
:global(html[data-theme="dark"]) .detector-chat .icon-button,
:global(html[data-theme="dark"]) .detector-chat .desktop-sidebar-open-toggle,
:global(html[data-theme="dark"]) .detector-chat .mobile-sidebar-toggle,
:global(html[data-theme="dark"]) .detector-chat .suggestion,
:global(html[data-theme="dark"]) .detector-chat .copy-prompt-button,
:global(html[data-theme="dark"]) .detector-chat .image-action-secondary,
:global(html[data-theme="dark"]) .detector-chat .regenerate-button,
:global(html[data-theme="dark"]) .detector-chat .options-panel,
:global(html[data-theme="dark"]) .detector-chat .input-box,
:global(html[data-theme="dark"]) .detector-chat .attach-button,
:global(html[data-theme="dark"]) .detector-chat .composer-file-preview {
    background: var(--theme-surface-secondary);
    color: var(--theme-text-secondary);
    border-color: var(--theme-border);
    box-shadow: none;
}

:global(html[data-theme="dark"]) .detector-chat .sidebar-quick-button:hover,
:global(html[data-theme="dark"]) .detector-chat .conversation-item.active .conversation-open,
:global(html[data-theme="dark"]) .detector-chat .conversation-open:hover,
:global(html[data-theme="dark"]) .detector-chat .conversation-delete:hover,
:global(html[data-theme="dark"]) .detector-chat .suggestion:hover,
:global(html[data-theme="dark"]) .detector-chat .image-action-secondary:hover:not(:disabled),
:global(html[data-theme="dark"]) .detector-chat .regenerate-button:hover:not(:disabled) {
    background: var(--theme-hover);
    color: var(--theme-text-primary);
    border-color: rgba(43, 166, 222, 0.34);
}

:global(html[data-theme="dark"]) .detector-chat .welcome-card,
:global(html[data-theme="dark"]) .detector-chat .message-body,
:global(html[data-theme="dark"]) .detector-chat .prompt-output,
:global(html[data-theme="dark"]) .detector-chat .generated-image-card,
:global(html[data-theme="dark"]) .detector-chat .message-body.card-shell.generation-loading,
:global(html[data-theme="dark"]) .detector-chat .loading-prompt-card {
    background: var(--theme-surface-secondary);
    color: var(--theme-text-primary);
    border-color: var(--theme-border);
    box-shadow: 0 12px 28px var(--theme-shadow);
}

:global(html[data-theme="dark"]) .detector-chat .message-row.user .message-body {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    border-color: transparent;
}

:global(html[data-theme="dark"]) .detector-chat .image-preview,
:global(html[data-theme="dark"]) .detector-chat .image-actions,
:global(html[data-theme="dark"]) .detector-chat .loading-image-card,
:global(html[data-theme="dark"]) .detector-chat .loading-image-placeholder {
    background: var(--theme-surface-elevated);
    border-color: var(--theme-border);
}

:global(html[data-theme="dark"]) .detector-chat .image-skeleton,
:global(html[data-theme="dark"]) .detector-chat .loading-prompt-card span {
    background: linear-gradient(90deg, var(--theme-surface) 25%, var(--theme-surface-elevated) 50%, var(--theme-surface) 75%);
    background-size: 200% 100%;
}

:global(html[data-theme="dark"]) .detector-chat .loading-image-shimmer {
    background:
        linear-gradient(100deg, transparent 20%, rgba(43, 166, 222, 0.10) 48%, transparent 76%),
        linear-gradient(145deg, var(--theme-surface), var(--theme-surface-elevated));
    background-size: 220% 100%, 100% 100%;
}

:global(html[data-theme="dark"]) .detector-chat .options-form input,
:global(html[data-theme="dark"]) .detector-chat .options-form select,
:global(html[data-theme="dark"]) .detector-chat .options-form textarea,
:global(html[data-theme="dark"]) .detector-chat .input-box textarea {
    background: var(--theme-input-bg);
    color: var(--theme-text-primary);
    border-color: var(--theme-border-strong);
}

:global(html[data-theme="dark"]) .detector-chat .options-form input:focus,
:global(html[data-theme="dark"]) .detector-chat .options-form select:focus,
:global(html[data-theme="dark"]) .detector-chat .options-form textarea:focus,
:global(html[data-theme="dark"]) .detector-chat .input-box:focus-within {
    border-color: rgba(43, 166, 222, 0.72);
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.13);
}

:global(html[data-theme="dark"]) .detector-chat .partial-warning {
    background: rgba(251, 191, 36, 0.10);
    color: #fbbf24;
    border-color: rgba(251, 191, 36, 0.24);
}

:global(html[data-theme="dark"]) .detector-chat .clean-error-card,
:global(html[data-theme="dark"]) .detector-chat .error-banner,
:global(html[data-theme="dark"]) .detector-chat .image-load-error button {
    background: rgba(248, 113, 113, 0.10);
    color: #f87171;
    border-color: rgba(248, 113, 113, 0.24);
}

:global(html[data-theme="dark"]) .detector-chat .sidebar-overlay {
    background: var(--theme-overlay);
}
</style>
