<template>
    <main class="trend-chat" :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ open: sidebarOpen }">
            <div class="sidebar-heading">
                <div>
                    <strong>{{ labels.conversations }}</strong>
                    <span>{{ subtool.name || labels.title }}</span>
                </div>
                <button class="icon-button mobile-only" type="button" :aria-label="labels.close" @click="sidebarOpen = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <button class="new-chat" type="button" :disabled="creatingConversation || submitting" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                {{ creatingConversation ? labels.creating : labels.newChat }}
            </button>

            <div v-if="loadingConversations" class="sidebar-status">{{ labels.loading }}</div>
            <div v-else-if="filteredConversations.length === 0" class="sidebar-status">
                {{ labels.noConversations }}
            </div>
            <div v-else class="conversation-list">
                <div
                    v-for="conversation in filteredConversations"
                    :key="conversation.uuid"
                    class="conversation-row"
                    :class="{ active: activeConversation?.uuid === conversation.uuid }"
                >
                    <button type="button" class="conversation-open" @click="openConversation(conversation)">
                        <i class="bi bi-image"></i>
                        <span>{{ conversationTitle(conversation) }}</span>
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

        <button v-if="sidebarOpen" class="sidebar-overlay" type="button" :aria-label="labels.close" @click="sidebarOpen = false"></button>

        <section class="workspace">
            <header class="topbar">
                <button class="icon-button mobile-only" type="button" :aria-label="labels.open" @click="sidebarOpen = true">
                    <i class="bi bi-list"></i>
                </button>
                <div class="tool-avatar"><i class="bi bi-trophy-fill"></i></div>
                <div class="tool-copy">
                    <h1>{{ subtool.name || labels.title }}</h1>
                    <p>{{ subtool.description || labels.subtitle }}</p>
                </div>
            </header>

            <div ref="messageArea" class="messages">
                <div v-if="loadingMessages" class="center-state">
                    <span class="spinner"></span>{{ labels.loadingConversation }}
                </div>

                <div v-else-if="messages.length === 0" class="empty-state">
                    <div class="empty-icon"><i class="bi bi-trophy"></i></div>
                    <h2>{{ labels.emptyTitle }}</h2>
                    <p>{{ labels.emptyBody }}</p>
                </div>

                <article
                    v-for="message in messages"
                    v-else
                    :key="message.key"
                    class="message"
                    :class="[message.role, { error: message.isError }]"
                >
                    <div class="message-avatar">
                        <i :class="message.role === 'user' ? 'bi bi-person-fill' : 'bi bi-stars'"></i>
                    </div>
                    <div class="message-body">
                        <p>{{ message.content }}</p>

                        <img
                            v-if="message.inputPreview"
                            class="input-preview"
                            :src="message.inputPreview"
                            :alt="labels.uploadedImage"
                        />

                        <div v-if="message.files.length" class="result-grid">
                            <figure v-for="file in message.files" :key="file.id || file.preview_url" class="result-card">
                                <div class="result-image-wrap">
                                    <span v-if="file.loading" class="spinner"></span>
                                    <img
                                        v-else-if="file.objectUrl"
                                        :src="file.objectUrl"
                                        :alt="file.filename || labels.generatedImage"
                                    />
                                    <div v-else class="image-error">
                                        <i class="bi bi-image"></i>{{ labels.imageUnavailable }}
                                    </div>
                                </div>
                                <button type="button" class="download-button" :disabled="file.downloading" @click="downloadFile(file)">
                                    <i class="bi bi-download"></i>
                                    {{ file.downloading ? labels.downloading : labels.download }}
                                </button>
                            </figure>
                        </div>
                    </div>
                </article>

                <article v-if="submitting" class="message assistant">
                    <div class="message-avatar"><i class="bi bi-stars"></i></div>
                    <div class="message-body generating">
                        <span class="spinner"></span>
                        <div><strong>{{ labels.generating }}</strong><small>{{ labels.generatingHint }}</small></div>
                    </div>
                </article>
            </div>

            <footer class="composer">
                <div v-if="errorMessage" class="error-banner" role="alert">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ errorMessage }}</span>
                    <button type="button" :aria-label="labels.close" @click="errorMessage = ''"><i class="bi bi-x"></i></button>
                </div>

                <div v-if="selectedFile" class="selected-file">
                    <img :src="selectedPreview" :alt="labels.uploadedImage" />
                    <div><strong>{{ selectedFile.name }}</strong><span>{{ formatBytes(selectedFile.size) }}</span></div>
                    <button type="button" :aria-label="labels.removeImage" :disabled="submitting" @click="clearSelectedFile">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="composer-row">
                    <input ref="fileInput" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" @change="selectFile" />
                    <button class="upload-button" type="button" :disabled="submitting" :aria-label="labels.upload" @click="fileInput?.click()">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <textarea
                        ref="promptInput"
                        v-model="prompt"
                        :placeholder="labels.placeholder"
                        :aria-label="labels.placeholder"
                        :disabled="submitting"
                        maxlength="1000"
                        rows="1"
                        @keydown.enter.exact.prevent="submit"
                    ></textarea>
                    <button class="send-button" type="button" :disabled="!canSubmit" :aria-label="labels.generate" @click="submit">
                        <i :class="submitting ? 'bi bi-hourglass-split' : 'bi bi-stars'"></i>
                        <span>{{ labels.generate }}</span>
                    </button>
                </div>
                <p class="composer-note">{{ labels.fileHint }}</p>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import chatServices from "@/services/chat/chatServices";
import trendServices from "@/services/chat/trendServices";
import homeService from "@/services/home/homeService";

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ALLOWED_TYPES = new Set(["image/jpeg", "image/png", "image/webp"]);

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const isArabic = computed(() => String(locale.value || route.params.lang || "en").toLowerCase() === "ar");

const labelKeys = [
    "conversations", "title", "subtitle", "newChat", "creating", "loading",
    "noConversations", "loadingConversation", "emptyTitle", "emptyBody", "close",
    "open", "deleteChat", "uploadedImage", "generatedImage", "imageUnavailable",
    "download", "downloading", "generating", "generatingHint", "removeImage",
    "upload", "placeholder", "generate", "fileHint", "promptRequired", "imageRequired",
    "invalidImage", "imageTooLarge", "genericError", "insufficient", "auth", "unsupported",
];
const labels = computed(() => Object.fromEntries(labelKeys.map((key) => [
    key,
    t(`user.trendChat.${key}`),
])));

const subtool = ref({ id: null, slug: "", name: "", description: "" });
const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const prompt = ref("");
const selectedFile = ref(null);
const selectedPreview = ref("");
const fileInput = ref(null);
const promptInput = ref(null);
const messageArea = ref(null);
const sidebarOpen = ref(false);
const loadingConversations = ref(true);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const submitting = ref(false);
const deletingUuid = ref("");
const errorMessage = ref("");
const objectUrls = new Set();

const filteredConversations = computed(() => conversations.value.filter((item) =>
    !subtool.value.id || Number(item.sub_tool_id) === Number(subtool.value.id)
));
const canSubmit = computed(() => !submitting.value && prompt.value.trim().length > 0 && selectedFile.value !== null);

const authenticated = () => Boolean(localStorage.getItem("auth_token"));
const uuid = () => window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
const formatBytes = (bytes) => bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
const conversationTitle = (item) => item.title || item.first_user_message_content || `${labels.value.title} ${String(item.uuid || "").slice(-6)}`;
const pendingRequestKey = (conversationUuid) => `trend-image-pending:${conversationUuid}`;

const resolveIdempotencyKey = (conversationUuid, text, file) => {
    const signature = `${text}\n${file.name}\n${file.size}\n${file.lastModified}`;
    try {
        const saved = JSON.parse(sessionStorage.getItem(pendingRequestKey(conversationUuid)) || "null");
        if (saved?.signature === signature && saved?.key) return saved.key;
    } catch {
        // A malformed pending entry is safely replaced below.
    }
    const key = uuid();
    try {
        sessionStorage.setItem(pendingRequestKey(conversationUuid), JSON.stringify({ key, signature }));
    } catch {
        // Idempotency still works for this in-memory submission.
    }
    return key;
};

const clearPendingRequest = (conversationUuid) => {
    try {
        sessionStorage.removeItem(pendingRequestKey(conversationUuid));
    } catch {
        // Ignore unavailable session storage.
    }
};

const rememberUrl = (blob) => {
    const value = URL.createObjectURL(blob);
    objectUrls.add(value);
    return value;
};

const revokeUrl = (value) => {
    if (!value || !objectUrls.has(value)) return;
    URL.revokeObjectURL(value);
    objectUrls.delete(value);
};

const scrollBottom = async () => {
    await nextTick();
    if (messageArea.value) messageArea.value.scrollTop = messageArea.value.scrollHeight;
};

const normalizeFiles = (message) => {
    const metadata = message?.metadata && typeof message.metadata === "object" ? message.metadata : {};
    const rows = Array.isArray(message?.files) ? message.files : Array.isArray(metadata.files) ? metadata.files : [];
    return rows.filter((file) => file && (file.preview_url || file.download_url)).map((file) => ({ ...file, objectUrl: "", loading: true, downloading: false }));
};

const mapMessage = (message, index = 0) => ({
    key: message.id || message.assistant_message_id || `message-${index}-${uuid()}`,
    role: message.role || (message.assistant_message_id ? "assistant" : "user"),
    content: String(message.content || message.message || ""),
    isError: Boolean(message.is_error || message.success === false),
    files: normalizeFiles(message),
    inputPreview: message.inputPreview || "",
});

const hydrateFiles = async (rows) => {
    await Promise.all(rows.flatMap((message) => message.files.map(async (file) => {
        try {
            const blob = await trendServices.fetchProtectedImage(file.preview_url || file.download_url);
            file.objectUrl = rememberUrl(blob);
        } catch {
            file.objectUrl = "";
        } finally {
            file.loading = false;
        }
    })));
};

const selectFile = (event) => {
    errorMessage.value = "";
    const file = event.target.files?.[0] || null;
    event.target.value = "";
    if (!file) return;
    if (!ALLOWED_TYPES.has(file.type)) {
        errorMessage.value = labels.value.invalidImage;
        return;
    }
    if (file.size > MAX_FILE_BYTES) {
        errorMessage.value = labels.value.imageTooLarge;
        return;
    }
    revokeUrl(selectedPreview.value);
    selectedFile.value = file;
    selectedPreview.value = rememberUrl(file);
};

const clearSelectedFile = () => {
    revokeUrl(selectedPreview.value);
    selectedFile.value = null;
    selectedPreview.value = "";
};

const loadSubtool = async () => {
    try {
        const response = await homeService.showSubtool(route.params.slug);
        const data = response?.data || {};
        subtool.value = {
            id: data.id || null,
            slug: data.slug || route.params.slug || "",
            name: data.translation?.name || data.name || labels.value.title,
            description: data.translation?.description || data.description || labels.value.subtitle,
        };
    } catch {
        subtool.value = { id: null, slug: route.params.slug || "", name: labels.value.title, description: labels.value.subtitle };
    }
};

const loadConversations = async () => {
    if (!authenticated()) {
        loadingConversations.value = false;
        return;
    }
    loadingConversations.value = true;
    try {
        const response = await chatServices.getConversations();
        conversations.value = Array.isArray(response?.data) ? response.data : [];
    } catch {
        conversations.value = [];
    } finally {
        loadingConversations.value = false;
    }
};

const loadConversation = async (conversationUuid) => {
    if (!conversationUuid || !authenticated()) return;
    loadingMessages.value = true;
    errorMessage.value = "";
    try {
        const response = await chatServices.getConversation(conversationUuid);
        const data = response?.data || {};
        activeConversation.value = data;
        messages.value = (Array.isArray(data.message) ? data.message : []).map(mapMessage);
        await hydrateFiles(messages.value);
        await scrollBottom();
    } catch (error) {
        messages.value = [];
        errorMessage.value = error.response?.data?.message || labels.value.genericError;
    } finally {
        loadingMessages.value = false;
    }
};

const createConversation = async () => {
    if (!authenticated()) {
        await router.push(`/${route.params.lang || homeService.getLang()}/auth`);
        throw new Error(labels.value.auth);
    }
    creatingConversation.value = true;
    try {
        const response = await chatServices.createConversation(route.params.slug);
        const conversation = response?.data;
        if (!conversation?.uuid) throw new Error(labels.value.genericError);
        activeConversation.value = conversation;
        conversations.value = [conversation, ...conversations.value.filter((item) => item.uuid !== conversation.uuid)];
        await router.replace(`/${route.params.lang || homeService.getLang()}/subtool/${route.params.slug}/chat7/${conversation.uuid}`);
        return conversation;
    } finally {
        creatingConversation.value = false;
    }
};

const startNewChat = async () => {
    errorMessage.value = "";
    try {
        await createConversation();
        messages.value = [];
        clearSelectedFile();
        prompt.value = "";
        sidebarOpen.value = false;
    } catch (error) {
        errorMessage.value = error.message || labels.value.genericError;
    }
};

const openConversation = async (conversation) => {
    sidebarOpen.value = false;
    if (route.params.uuid !== conversation.uuid) {
        await router.push(`/${route.params.lang || homeService.getLang()}/subtool/${route.params.slug}/chat7/${conversation.uuid}`);
    } else {
        await loadConversation(conversation.uuid);
    }
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
            await router.replace(`/${route.params.lang || homeService.getLang()}/subtool/${route.params.slug}/chat7`);
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || labels.value.genericError;
    } finally {
        deletingUuid.value = "";
    }
};

const submit = async () => {
    errorMessage.value = "";
    const text = prompt.value.trim();
    if (!text) {
        errorMessage.value = labels.value.promptRequired;
        return;
    }
    if (!selectedFile.value) {
        errorMessage.value = labels.value.imageRequired;
        return;
    }
    if (!authenticated()) {
        await router.push(`/${route.params.lang || homeService.getLang()}/auth`);
        return;
    }

    let trend;
    try {
        trend = trendServices.resolveTrend(subtool.value);
    } catch {
        errorMessage.value = labels.value.unsupported;
        return;
    }

    submitting.value = true;
    const file = selectedFile.value;
    const inputPreview = selectedPreview.value;
    let conversation = activeConversation.value;

    try {
        if (!conversation?.uuid) conversation = await createConversation();
        const idempotencyKey = resolveIdempotencyKey(conversation.uuid, text, file);
        const localUserMessage = mapMessage({ role: "user", content: text, inputPreview });
        messages.value.push(localUserMessage);
        selectedFile.value = null;
        selectedPreview.value = "";
        prompt.value = "";
        await scrollBottom();

        const result = await trendServices.generate(subtool.value, {
            conversation_uuid: conversation.uuid,
            user_message: text,
            selected_model_id: trend.selectedModelId,
            state: { parameters: {} },
            idempotency_key: idempotencyKey,
        }, file);

        if (!result?.success || !Array.isArray(result.files) || !result.files[0]?.download_url) {
            throw new Error(labels.value.genericError);
        }

        const assistant = mapMessage({ ...result, role: "assistant", content: result.message });
        messages.value.push(assistant);
        await hydrateFiles([assistant]);
        clearPendingRequest(conversation.uuid);
        await loadConversations();
    } catch (error) {
        const status = error.response?.status;
        if (status && conversation?.uuid) clearPendingRequest(conversation.uuid);
        errorMessage.value = status === 402
            ? labels.value.insufficient
            : error.response?.data?.message || error.message || labels.value.genericError;
    } finally {
        submitting.value = false;
        await scrollBottom();
    }
};

const downloadFile = async (file) => {
    if (!file?.download_url || file.downloading) return;
    file.downloading = true;
    try {
        const blob = await trendServices.fetchProtectedImage(file.download_url);
        const objectUrl = rememberUrl(blob);
        const anchor = document.createElement("a");
        anchor.href = objectUrl;
        anchor.download = file.filename || `${subtool.value.slug || "trend-image"}.webp`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => revokeUrl(objectUrl), 1000);
    } catch {
        errorMessage.value = labels.value.imageUnavailable;
    } finally {
        file.downloading = false;
    }
};

const initialize = async () => {
    locale.value = String(route.params.lang || homeService.getLang() || "en").toLowerCase();
    await Promise.all([loadSubtool(), loadConversations()]);
    if (route.params.uuid) await loadConversation(route.params.uuid);
};

onMounted(initialize);
onUnmounted(() => objectUrls.forEach((value) => URL.revokeObjectURL(value)));

watch(() => route.params.uuid, async (nextUuid, previousUuid) => {
    if (nextUuid === previousUuid) return;
    if (nextUuid && activeConversation.value?.uuid === nextUuid) return;
    if (nextUuid) await loadConversation(nextUuid);
    else {
        activeConversation.value = null;
        messages.value = [];
    }
});

watch(() => [route.params.slug, route.params.lang], async ([nextSlug, nextLang], [previousSlug, previousLang]) => {
    if (nextSlug === previousSlug && nextLang === previousLang) return;
    await initialize();
});
</script>

<style scoped>
.trend-chat { --navy: #154677; --blue: #2ba6de; display: grid; grid-template-columns: 290px minmax(0, 1fr); height: calc(100vh - 72px); min-height: 620px; background: #f4f7fb; color: #172033; }
button, textarea { font: inherit; }
button { border: 0; }
.sidebar { display: flex; flex-direction: column; gap: 18px; min-width: 0; padding: 22px 16px; background: #fff; border-inline-end: 1px solid #e6ebf2; z-index: 30; }
.sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.sidebar-heading div { display: grid; gap: 3px; }
.sidebar-heading strong { font-size: 18px; color: var(--navy); }
.sidebar-heading span { color: #7a8495; font-size: 12px; }
.new-chat { display: flex; align-items: center; justify-content: center; gap: 9px; padding: 11px 15px; border-radius: 12px; background: var(--navy); color: #fff; font-weight: 700; cursor: pointer; }
.new-chat:disabled, .send-button:disabled, .upload-button:disabled { opacity: .55; cursor: not-allowed; }
.sidebar-status { padding: 28px 8px; text-align: center; color: #8892a3; font-size: 13px; }
.conversation-list { display: grid; gap: 7px; overflow-y: auto; }
.conversation-row { display: flex; align-items: center; gap: 4px; border-radius: 11px; border: 1px solid transparent; }
.conversation-row:hover, .conversation-row.active { background: #eef6fc; border-color: #d8eaf7; }
.conversation-open { display: flex; align-items: center; gap: 9px; min-width: 0; flex: 1; padding: 11px; background: transparent; color: #455064; cursor: pointer; text-align: start; }
.conversation-open span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conversation-delete { padding: 9px; background: transparent; color: #9aa3b2; cursor: pointer; }
.conversation-delete:hover { color: #d33b4c; }
.workspace { min-width: 0; display: grid; grid-template-rows: auto minmax(0, 1fr) auto; }
.topbar { display: flex; align-items: center; gap: 13px; min-height: 82px; padding: 14px 24px; background: rgba(255,255,255,.94); border-bottom: 1px solid #e6ebf2; }
.tool-avatar, .empty-icon { display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, var(--navy), var(--blue)); box-shadow: 0 9px 24px rgba(21,70,119,.22); }
.tool-avatar { width: 46px; height: 46px; border-radius: 14px; font-size: 20px; }
.tool-copy { min-width: 0; }
.tool-copy h1 { margin: 0; font-size: 18px; color: var(--navy); }
.tool-copy p { margin: 3px 0 0; color: #7b8595; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.messages { overflow-y: auto; padding: 28px clamp(16px, 5vw, 70px); scroll-behavior: smooth; }
.center-state, .empty-state { height: 100%; display: flex; align-items: center; justify-content: center; color: #7d8797; gap: 10px; }
.empty-state { flex-direction: column; text-align: center; }
.empty-icon { width: 72px; height: 72px; border-radius: 22px; font-size: 30px; margin-bottom: 7px; }
.empty-state h2 { margin: 0; color: var(--navy); font-size: 22px; }
.empty-state p { max-width: 430px; margin: 0; line-height: 1.7; }
.message { display: flex; align-items: flex-start; gap: 11px; max-width: 850px; margin: 0 auto 20px; }
.message.user { flex-direction: row-reverse; }
[dir="rtl"] .message.user { flex-direction: row-reverse; }
.message-avatar { flex: 0 0 auto; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 11px; background: #e8f3fb; color: var(--navy); }
.message.user .message-avatar { background: var(--navy); color: #fff; }
.message-body { max-width: min(680px, calc(100% - 48px)); padding: 12px 14px; border-radius: 6px 18px 18px 18px; background: #fff; border: 1px solid #e4eaf1; box-shadow: 0 5px 18px rgba(23,32,51,.05); }
.message.user .message-body { background: var(--navy); border-color: var(--navy); color: #fff; border-radius: 18px 6px 18px 18px; }
.message.error .message-body { border-color: #efb4bb; background: #fff4f5; color: #a32736; }
.message-body p { margin: 0; white-space: pre-wrap; line-height: 1.65; }
.input-preview { display: block; max-width: 210px; max-height: 210px; margin-top: 10px; border-radius: 12px; object-fit: cover; }
.result-grid { display: grid; gap: 12px; margin-top: 12px; }
.result-card { margin: 0; overflow: hidden; border: 1px solid #dfe6ef; border-radius: 14px; background: #f8fafc; }
.result-image-wrap { min-width: 280px; min-height: 280px; display: grid; place-items: center; background: #edf1f6; }
.result-image-wrap img { display: block; width: 100%; max-height: 620px; object-fit: contain; }
.image-error { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #7f8999; }
.download-button { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px; background: #fff; color: var(--navy); font-weight: 700; cursor: pointer; }
.generating { display: flex; align-items: center; gap: 12px; }
.generating div { display: grid; gap: 2px; }
.generating small { color: #7d8796; }
.spinner { width: 20px; height: 20px; border: 2px solid #cfdae5; border-top-color: var(--blue); border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.composer { padding: 14px clamp(16px, 5vw, 70px) 18px; background: #fff; border-top: 1px solid #e3e9f0; }
.composer-row { max-width: 850px; margin: auto; display: flex; align-items: flex-end; gap: 8px; padding: 7px; border: 1px solid #dbe3ec; border-radius: 16px; background: #f9fbfd; box-shadow: 0 8px 28px rgba(21,70,119,.07); }
.composer-row:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(43,166,222,.12); }
.composer textarea { flex: 1; min-height: 44px; max-height: 120px; resize: vertical; padding: 11px 5px; border: 0; outline: 0; background: transparent; color: inherit; }
.upload-button { width: 44px; height: 44px; flex: 0 0 auto; border-radius: 12px; background: #e9f3fb; color: var(--navy); cursor: pointer; font-size: 18px; }
.send-button { min-height: 44px; display: flex; align-items: center; gap: 7px; padding: 0 17px; border-radius: 12px; background: var(--navy); color: #fff; font-weight: 700; cursor: pointer; }
.composer-note { margin: 8px auto 0; max-width: 850px; color: #8b94a4; font-size: 11px; text-align: center; }
.selected-file { max-width: 850px; margin: 0 auto 10px; display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 12px; background: #eef6fc; border: 1px solid #d7eafa; }
.selected-file img { width: 48px; height: 48px; border-radius: 9px; object-fit: cover; }
.selected-file div { min-width: 0; flex: 1; display: grid; }
.selected-file strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
.selected-file span { color: #7c8798; font-size: 11px; }
.selected-file button, .error-banner button, .icon-button { background: transparent; color: inherit; cursor: pointer; }
.error-banner { max-width: 850px; margin: 0 auto 10px; display: flex; align-items: center; gap: 9px; padding: 10px 12px; border-radius: 11px; color: #9e2634; background: #fff0f2; border: 1px solid #f1c2c8; font-size: 13px; }
.error-banner span { flex: 1; }
.icon-button { width: 40px; height: 40px; border-radius: 10px; }
.sidebar-overlay, .mobile-only { display: none; }
.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }

:global(html[data-theme="dark"]) .trend-chat { background: var(--theme-bg); color: var(--theme-text-primary); }
:global(html[data-theme="dark"]) .sidebar, :global(html[data-theme="dark"]) .topbar, :global(html[data-theme="dark"]) .composer { background: var(--theme-surface); border-color: var(--theme-border); }
:global(html[data-theme="dark"]) .message-body, :global(html[data-theme="dark"]) .result-card, :global(html[data-theme="dark"]) .download-button { background: var(--theme-surface-secondary); border-color: var(--theme-border); color: var(--theme-text-primary); }
:global(html[data-theme="dark"]) .composer-row { background: var(--theme-surface-secondary); border-color: var(--theme-border); }
:global(html[data-theme="dark"]) .sidebar-heading strong, :global(html[data-theme="dark"]) .tool-copy h1, :global(html[data-theme="dark"]) .empty-state h2 { color: var(--theme-text-primary); }
:global(html[data-theme="dark"]) .conversation-row:hover, :global(html[data-theme="dark"]) .conversation-row.active, :global(html[data-theme="dark"]) .selected-file { background: rgba(43,166,222,.12); border-color: rgba(43,166,222,.25); }

@media (max-width: 900px) {
    .trend-chat { display: block; height: calc(100dvh - 64px); min-height: 520px; }
    .workspace { height: 100%; }
    .sidebar { position: fixed; inset-block: 0; inset-inline-start: 0; width: min(88vw, 310px); transform: translateX(-105%); transition: transform .2s ease; box-shadow: 18px 0 45px rgba(16,30,50,.2); }
    [dir="rtl"] .sidebar { transform: translateX(105%); }
    .sidebar.open { transform: translateX(0); }
    .sidebar-overlay { display: block; position: fixed; inset: 0; z-index: 20; background: rgba(10,20,34,.46); }
    .mobile-only { display: inline-grid; place-items: center; }
    .topbar { min-height: 70px; padding: 10px 14px; }
    .messages { padding: 20px 12px; }
    .composer { padding: 10px 10px 12px; }
    .send-button span { display: none; }
    .send-button { width: 44px; padding: 0; justify-content: center; }
    .result-image-wrap { min-width: 220px; min-height: 220px; }
}
</style>
