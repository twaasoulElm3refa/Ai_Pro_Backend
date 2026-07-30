<template>
    <main class="detector-chat" :class="{ 'sidebar-collapsed': desktopSidebarCollapsed }"
        :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-image"></i></span>
                <strong>{{ labels.title }}</strong>
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
                    <span class="welcome-icon"><i class="bi bi-image"></i></span>
                    <h1>{{ labels.title }}</h1>
                    <p>{{ labels.welcome }}</p>
                    <button type="button" class="suggestion" @click="fillExample">
                        {{ labels.example }}
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
                            'card-shell': message.role === 'assistant' && (message.images.length || message.is_error),
                        }">
                            <template v-if="message.role === 'assistant' && message.images.length">
                                <div class="image-response-card">
                                    <div class="response-heading">
                                        <span>
                                            <i class="bi bi-stars"></i>
                                            {{ message.content || labels.generated }}
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
                                                    :alt="labels.generated" loading="lazy"
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
                                        <button type="button" class="regenerate-button" :disabled="isSending"
                                            @click="regenerate(message)">
                                            <span v-if="isSending" class="button-spinner"></span>
                                            <i v-else class="bi bi-arrow-clockwise"></i>
                                            {{ labels.regenerate }}
                                        </button>
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
                                        <strong>{{ labels.generating }}</strong>
                                        <small>{{ labels.generationWait }}</small>
                                    </div>
                                </div>

                                <span class="generation-spinner" aria-hidden="true"></span>
                            </div>

                            <div class="loading-image-grid" :class="`loading-count-${Math.min(state.results_count, 4)}`">
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

            <footer class="composer">
                <div v-if="errorMessage" class="error-banner">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ errorMessage }}</span>
                    <button v-if="lastFailedRequest" type="button" :disabled="isSending" @click="retryLastRequest">
                        {{ labels.retry }}
                    </button>
                </div>

                <details class="options-panel">
                    <summary class="options-panel-header">
                        <span><i class="bi bi-sliders"></i> {{ labels.settings }}</span>
                        <span class="options-chevron" aria-hidden="true">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>

                    <div class="options-form">
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
                    <textarea ref="textareaRef" v-model="userMessage" rows="1" maxlength="10000"
                        :placeholder="labels.placeholder" :disabled="isSending" @input="autoResize"
                        @keydown.enter.exact.prevent="sendMessage()"></textarea>
                    <button type="button" class="send-button" :disabled="!canSend" :aria-label="labels.send"
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
import useSeoMeta from "@/composables/useSeoMeta";
import {
    normalizeGeneratedImages,
    normalizeImageGenerationMessage,
    unwrapApiData,
} from "@/utils/imageGeneratorResults";

const IMAGE_TOOL = {
    sub_tool_id: 21,
    tool_key: "ai_image_generator",
    model_key: "image_generator",
};

const createDefaultState = () => ({
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

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();
const activeConversation = ref(null);
const currentSubtool = ref(null);
const conversations = ref([]);
const messages = ref([]);
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

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "en").toLowerCase() === "ar"
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
});

const activeSubToolId = computed(() => Number(
    activeConversation.value?.sub_tool_id
    || currentSubtool.value?.id
    || currentSubtool.value?.sub_tool_id
    || IMAGE_TOOL.sub_tool_id
));

const canSend = computed(() => Boolean(userMessage.value.trim()) && !isSending.value && !creatingConversation.value);

useSeoMeta({
    title: computed(() => labels.value.title),
    description: computed(() => labels.value.welcome),
});

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

function stateFromMessage(message) {
    const candidate = message?.state || message?.metadata?.state;
    return candidate && typeof candidate === "object"
        ? { ...createDefaultState(), ...candidate }
        : null;
}

function normalizeSeed(value) {
    if (value === "" || value === undefined || value === null) return null;
    const seed = Number(value);
    return Number.isFinite(seed) ? seed : null;
}

function cleanRequestState(requestState = {}, regenerate = false) {
    return {
        ...createDefaultState(),
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

function cleanUiState(source = {}) {
    return {
        ...cleanRequestState(source, false),
        seed: null,
        last_output: null,
    };
}

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
        state.value = latestState ? cleanUiState(latestState) : createDefaultState();
        await loadMessagePreviews();
        await scrollToBottom();
    } catch {
        messages.value = [];
        errorMessage.value = labels.value.genericError;
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

function buildPayload(prompt, conversation, requestState, regenerate = false) {
    const cleanState = cleanRequestState(requestState, regenerate);

    return {
        sub_tool_id: Number(conversation.sub_tool_id || activeSubToolId.value),
        conversation_uuid: conversation.uuid,
        user_message: prompt,
        state: cleanState,
        tool: IMAGE_TOOL.tool_key,
        tool_key: IMAGE_TOOL.tool_key,
        model_key: IMAGE_TOOL.model_key,
        regenerate,
        idempotency_key: crypto.randomUUID(),
        debug: false,
    };
}

async function sendMessage(options = {}) {
    const prompt = String(options.prompt ?? userMessage.value).trim();
    if (!prompt || isSending.value) {
        if (!prompt) errorMessage.value = labels.value.promptRequired;
        return;
    }

    const regenerate = Boolean(options.regenerate);
    const requestState = cleanRequestState(options.requestState || state.value, regenerate);
    const optimisticKey = `local-user-${Date.now()}`;
    errorMessage.value = "";
    lastFailedRequest.value = null;
    isSending.value = true;

    try {
        const conversation = await ensureConversation();
        const payload = buildPayload(prompt, conversation, requestState, regenerate);
        messages.value.push(mapMessage({
            localKey: optimisticKey,
            role: "user",
            content: prompt,
            metadata: { state: payload.state },
        }, messages.value.length));
        messages.value[messages.value.length - 1].localKey = optimisticKey;
        userMessage.value = "";
        autoResize();
        await scrollToBottom();

        const result = responseData(await chatServices.sendMessage(payload));
        const images = normalizeGeneratedImages(result.images);
        const assistant = mapMessage({
            id: result.assistant_message_id,
            role: "assistant",
            content: result.message,
            is_error: result.success === false || result.type === "error",
            images,
            state: result.state,
            metadata: {
                ...result,
                images,
                generation: result.metadata,
                request_prompt: prompt,
                failed_files: result.failed_files || [],
            },
        }, messages.value.length);
        messages.value.push(assistant);
        state.value = cleanUiState(result.state || requestState);

        if (assistant.is_error) {
            lastFailedRequest.value = {
                prompt,
                requestState: cleanRequestState(requestState, false),
                regenerate: false,
            };
        } else {
            await loadPreviewsForMessage(assistant);
        }

        await loadConversations();
        await scrollToBottom();
    } catch (error) {
        if (import.meta.env.DEV) {
            console.error("[chat5 image generation error]", {
                status: error?.response?.status,
                data: error?.response?.data,
                message: error?.message,
            });
        }

        const message = error?.response?.data?.message || error?.response?.data?.error || labels.value.genericError;
        errorMessage.value = localizeError(message);
        lastFailedRequest.value = {
            prompt,
            requestState: cleanRequestState(requestState, false),
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
    sendMessage({
        prompt,
        requestState: {
            ...cleanRequestState(requestState, true),
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
    const previousState = stateFromMessage(message) || createDefaultState();

    sendMessage({
        prompt,
        requestState: {
            ...cleanRequestState(previousState, true),
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

async function startNewChat() {
    if (creatingConversation.value || isSending.value) return;
    creatingConversation.value = true;
    try {
        const conversation = formatConversation(
            responseData(await chatServices.createConversation(route.params.slug))
        );
        revokePreviewUrls();
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
    } catch {
        errorMessage.value = labels.value.genericError;
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
            activeConversation.value = null;
            messages.value = [];
            state.value = createDefaultState();
            await router.push(routePath());
        }
    } finally {
        deletingUuid.value = "";
    }
}

async function loadMessagePreviews() {
    await Promise.all(messages.value.map(loadPreviewsForMessage));
}

async function loadPreviewsForMessage(message) {
    if (message.role !== "assistant" || !message.images.length) return;
    await Promise.all(message.images.map(loadImagePreview));
}

async function loadImagePreview(image) {
    if (!image?.preview_url || previewLoading.value[image.id]) return;
    previewLoading.value = { ...previewLoading.value, [image.id]: true };
    previewErrors.value = { ...previewErrors.value, [image.id]: false };
    try {
        const blob = await chatServices.fetchProtectedBlob(toAbsoluteUrl(image.preview_url));
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

async function downloadImage(image) {
    actionLoading.value = `download-${image.id}`;
    try {
        const blob = await chatServices.fetchProtectedBlob(toAbsoluteUrl(image.download_url));
        const href = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = href;
        link.download = image.filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(href);
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

function toAbsoluteUrl(url) {
    return new URL(String(url || ""), window.location.origin).toString();
}

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

function fillExample() {
    userMessage.value = labels.value.example;
    nextTick(autoResize);
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
    if (!text || /could not|failed|error/i.test(text)) return labels.value.genericError;
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
        activeConversation.value = null;
        messages.value = [];
        state.value = createDefaultState();
        await initialize();
    }
);

onMounted(initialize);
onUnmounted(() => {
    revokePreviewUrls();
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

.generation-header-content > div {
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
        linear-gradient(
            100deg,
            transparent 20%,
            rgba(255, 255, 255, 0.72) 48%,
            transparent 76%
        ),
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
}

.options-panel-header::-webkit-details-marker {
    display: none;
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
    transition:
        transform 0.3s ease,
        background-color 0.2s ease;
}
.options-panel-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
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
.options-panel-header {
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
</style>
