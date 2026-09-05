<template>
    <main class="free-ai-chat" :dir="isRtl ? 'rtl' : 'ltr'">
        <button
            v-if="sidebarOpen && isMobile"
            type="button"
            class="sidebar-overlay"
            :aria-label="t('freeAiModels.closeSidebar')"
            @click="sidebarOpen = false"
        ></button>

        <aside class="conversation-sidebar" :class="{ open: sidebarOpen, collapsed: desktopSidebarCollapsed }">
            <div class="sidebar-heading">
                <div class="tool-identity">
                    <span class="tool-avatar">
                        <img v-if="mainTool.image_url" :src="mainTool.image_url" :alt="mainTool.name" />
                        <i v-else class="bi bi-stars"></i>
                    </span>
                    <span class="tool-copy">
                        <small>{{ t("freeAiModels.mainTool") }}</small>
                        <strong>{{ mainTool.name || readableSlug }}</strong>
                    </span>
                </div>
                <button type="button" class="icon-button sidebar-close" :aria-label="t('freeAiModels.closeSidebar')" @click="closeSidebar">
                    <i class="bi" :class="isMobile ? 'bi-x-lg' : collapseIcon"></i>
                </button>
            </div>

            <button type="button" class="new-conversation-button" :disabled="creatingConversation" @click="newConversation">
                <span v-if="creatingConversation" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <i v-else class="bi bi-plus-lg"></i>
                {{ creatingConversation ? t("freeAiModels.creatingConversation") : t("freeAiModels.newConversation") }}
            </button>

            <div class="history-heading">
                <span>{{ t("freeAiModels.recentConversations") }}</span>
                <span>{{ conversations.length }}</span>
            </div>

            <div class="history-list" :aria-label="t('freeAiModels.conversationList')">
                <div v-if="loadingConversations" class="history-state">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ t("freeAiModels.loadingConversations") }}
                </div>
                <div v-else-if="!conversations.length" class="history-state">
                    <i class="bi bi-chat-square-text"></i>
                    {{ t("freeAiModels.noConversations") }}
                </div>
                <article
                    v-for="item in conversations"
                    v-else
                    :key="item.uuid"
                    class="history-item"
                    :class="{ active: item.uuid === activeUuid }"
                >
                    <button type="button" class="history-open" @click="openConversation(item)">
                        <i class="bi bi-chat-left-text"></i>
                        <span>
                            <strong>{{ conversationTitle(item) }}</strong>
                            <small>{{ item.selected_model?.name || t("freeAiModels.defaultModel") }}</small>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="history-delete"
                        :disabled="deletingUuid === item.uuid"
                        :aria-label="t('freeAiModels.deleteConversation')"
                        @click="deleteConversation(item)"
                    >
                        <span v-if="deletingUuid === item.uuid" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        <i v-else class="bi bi-trash3"></i>
                    </button>
                </article>
            </div>
        </aside>

        <section class="chat-workspace">
            <header class="workspace-header">
                <div class="header-leading">
                    <button
                        v-if="isMobile || desktopSidebarCollapsed"
                        type="button"
                        class="icon-button"
                        :aria-label="t('freeAiModels.openSidebar')"
                        @click="openSidebar"
                    >
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="header-avatar">
                        <img v-if="mainTool.image_url" :src="mainTool.image_url" :alt="mainTool.name" />
                        <i v-else class="bi bi-stars"></i>
                    </span>
                    <span class="header-copy">
                        <strong>{{ mainTool.name || readableSlug }}</strong>
                        <small>
                            <span class="status-dot"></span>
                            {{ selectedModel?.name || t("freeAiModels.defaultModel") }}
                            <span v-if="modelSaving"> · {{ t("freeAiModels.modelSaving") }}</span>
                        </small>
                    </span>
                </div>
                <button type="button" class="back-button" @click="backToTool">
                    <i class="bi bi-arrow-left"></i>
                    <span>{{ t("freeAiModels.backToModel") }}</span>
                </button>
            </header>

            <div v-if="loadingConversation" class="conversation-state" aria-live="polite">
                <span class="spinner-border" aria-hidden="true"></span>
                <p>{{ t("freeAiModels.loadingConversation") }}</p>
            </div>
            <div v-else-if="loadError" class="conversation-state error-state">
                <i class="bi bi-exclamation-triangle"></i>
                <h1>{{ t("freeAiModels.conversationUnavailable") }}</h1>
                <p>{{ t("freeAiModels.conversationUnavailableDescription") }}</p>
                <button type="button" class="retry-button" @click="loadConversation">
                    {{ t("freeAiModels.tryAgain") }}
                </button>
            </div>
            <section v-else class="messages" :aria-label="t('freeAiModels.conversation')">
                <div class="empty-conversation">
                    <span class="empty-icon"><i class="bi bi-chat-dots"></i></span>
                    <h1>{{ t("freeAiModels.emptyChatTitle", { name: mainTool.name || readableSlug }) }}</h1>
                    <p>{{ t("freeAiModels.emptyChatDescription") }}</p>
                    <span class="pending-pill"><i class="bi bi-clock-history"></i>{{ t("freeAiModels.integrationPending") }}</span>
                </div>
            </section>

            <footer class="composer-area">
                <div class="composer-shell">
                    <div class="composer-box">
                        <FreeAiModelSelector
                            :models="catalogModels"
                            :selected-model="selectedModel"
                            :loading="catalogLoading"
                            :error="catalogError"
                            :disabled="!conversation?.uuid || modelSaving"
                            @select="selectExecutionModel"
                            @retry="loadCatalog(true)"
                        />
                        <div class="message-compose-row">
                            <textarea
                                rows="1"
                                :placeholder="t('freeAiModels.inputPlaceholder')"
                                :aria-label="t('freeAiModels.inputPlaceholder')"
                                disabled
                            ></textarea>
                            <button type="button" class="send-button" disabled :aria-label="t('freeAiModels.send')">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </div>
                    <p class="composer-hint"><i class="bi bi-info-circle"></i>{{ t("freeAiModels.composerUnavailableHint") }}</p>
                </div>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";
import freeAiModelService from "@/services/freeAiModels/freeAiModelService";
import { getFreeAiCatalogSource } from "@/services/freeAiModels/freeAiCatalogSources";
import modelCatalogService from "@/services/modelCatalog/modelCatalogService";
import { readSelectedCatalogModel, saveSelectedCatalogModel } from "@/services/modelCatalog/selectedModelStorage";
import FreeAiModelSelector from "@/components/free-ai-models/FreeAiModelSelector.vue";

const MOBILE_BREAKPOINT = 900;
const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const conversation = ref(null);
const conversations = ref([]);
const loadingConversation = ref(true);
const loadingConversations = ref(true);
const loadError = ref(false);
const creatingConversation = ref(false);
const deletingUuid = ref(null);
const catalogModels = ref([]);
const catalogLoading = ref(false);
const catalogError = ref(false);
const selectedModel = ref(null);
const modelSaving = ref(false);
const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const viewportWidth = ref(typeof window === "undefined" ? 1200 : window.innerWidth);
let loadedCatalogSource = null;
let catalogRequestId = 0;
let conversationRequestId = 0;

const activeUuid = computed(() => String(route.params.uuid || ""));
const pageSlug = computed(() => String(route.params.slug || ""));
const catalogSource = computed(() => getFreeAiCatalogSource(conversation.value));
const isMobile = computed(() => viewportWidth.value <= MOBILE_BREAKPOINT);
const isRtl = computed(() => locale.value === "ar");
const readableSlug = computed(() => pageSlug.value.replace(/-/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase()));
const mainTool = computed(() => conversation.value?.model || {});
const collapseIcon = computed(() => (isRtl.value ? "bi-chevron-right" : "bi-chevron-left"));

const seoTitle = computed(() => mainTool.value.meta_title || mainTool.value.name || "AI Pro");
const seoDescription = computed(() => mainTool.value.meta_description || mainTool.value.description || "");
const seoKeywords = computed(() => mainTool.value.seo_keywords || "");
useSeoMeta({ title: seoTitle, description: seoDescription, keywords: seoKeywords });

const modelKey = (model) => String(model?.providerModelId || model?.provider_model_id || model?.id || "");

function catalogMatch(selection) {
    if (!selection) return null;
    return catalogModels.value.find((model) => {
        const sameId = String(model.id ?? "") === String(selection.id ?? "");
        const requestedProvider = selection.provider_model_id || selection.providerModelId;
        return sameId && (!requestedProvider || model.providerModelId === requestedProvider);
    }) || null;
}

function selectedSnapshot(selection) {
    if (!selection?.name) return null;
    return {
        id: selection.id ?? null,
        name: selection.name,
        providerModelId: selection.provider_model_id || selection.providerModelId || "",
        tier: "standard",
        isFree: false,
        isAvailable: true,
        isRecommended: false,
    };
}

function defaultCatalogModel() {
    if (!catalogSource.value) return null;
    const remembered = readSelectedCatalogModel(catalogSource.value, pageSlug.value);
    const rememberedMatch = catalogMatch(remembered);
    if (rememberedMatch?.isAvailable) return rememberedMatch;
    return catalogModels.value.find((model) => model.isAvailable && model.isRecommended)
        || catalogModels.value.find((model) => model.isAvailable)
        || null;
}

function syncSelectedModel() {
    const persisted = conversation.value?.selected_model;
    selectedModel.value = catalogMatch(persisted) || selectedSnapshot(persisted) || defaultCatalogModel();
}

async function loadCatalog(force = false) {
    const source = catalogSource.value;
    const slug = pageSlug.value;
    if (!source) {
        catalogRequestId++;
        catalogModels.value = [];
        catalogLoading.value = false;
        catalogError.value = true;
        selectedModel.value = selectedSnapshot(conversation.value?.selected_model);
        return;
    }
    if (!force && loadedCatalogSource === source && catalogModels.value.length) return;

    const requestId = ++catalogRequestId;
    catalogLoading.value = true;
    catalogError.value = false;
    try {
        const result = await modelCatalogService.getModels(source, {
            fallbackDescription: t("freeAiModels.modelDescriptionFallback"),
        });
        if (requestId !== catalogRequestId || slug !== pageSlug.value || source !== catalogSource.value) return;
        catalogModels.value = result.models;
        loadedCatalogSource = source;
        syncSelectedModel();
    } catch {
        if (requestId !== catalogRequestId || slug !== pageSlug.value || source !== catalogSource.value) return;
        catalogModels.value = [];
        catalogError.value = true;
        selectedModel.value = selectedSnapshot(conversation.value?.selected_model);
    } finally {
        if (requestId === catalogRequestId) catalogLoading.value = false;
    }
}

async function loadConversation() {
    const requestId = ++conversationRequestId;
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const isCurrent = () => requestId === conversationRequestId && slug === pageSlug.value && uuid === activeUuid.value;
    loadingConversation.value = true;
    loadError.value = false;
    try {
        const response = await freeAiModelService.getConversation(slug, uuid);
        if (!isCurrent()) return;
        conversation.value = response?.data || null;
        syncSelectedModel();
        loadCatalog();
    } catch {
        if (!isCurrent()) return;
        conversation.value = null;
        loadError.value = true;
    } finally {
        if (isCurrent()) loadingConversation.value = false;
    }
}

async function loadConversations() {
    const slug = pageSlug.value;
    loadingConversations.value = true;
    try {
        const response = await freeAiModelService.getConversations(slug);
        if (slug !== pageSlug.value) return;
        conversations.value = Array.isArray(response?.data) ? response.data : [];
    } catch {
        if (slug !== pageSlug.value) return;
        conversations.value = [];
    } finally {
        if (slug === pageSlug.value) loadingConversations.value = false;
    }
}

function summaryFromConversation(item) {
    return {
        uuid: item.uuid,
        title: null,
        is_pinned: Boolean(item.is_pinned),
        created_at: item.created_at,
        updated_at: item.updated_at || item.created_at,
        selected_model: item.selected_model || null,
    };
}

function upsertConversationSummary(item) {
    const summary = summaryFromConversation(item);
    conversations.value = [summary, ...conversations.value.filter((entry) => entry.uuid !== summary.uuid)];
}

function conversationTitle(item) {
    return item.title || `${t("freeAiModels.newConversation")} · ${String(item.uuid).slice(0, 6)}`;
}

async function openConversation(item) {
    if (item.uuid === activeUuid.value) {
        sidebarOpen.value = false;
        return;
    }
    sidebarOpen.value = false;
    await router.push({ name: "free-ai-model.chat", params: { lang: homeService.getLang(), slug: pageSlug.value, uuid: item.uuid } });
}

async function newConversation() {
    if (creatingConversation.value) return;
    creatingConversation.value = true;
    try {
        const chosen = selectedModel.value?.isAvailable ? selectedModel.value : null;
        const response = await freeAiModelService.createConversation(pageSlug.value, chosen);
        const created = response?.data;
        if (!created?.uuid) throw new Error("Missing conversation UUID");
        upsertConversationSummary(created);
        sidebarOpen.value = false;
        await router.push({ name: "free-ai-model.chat", params: { lang: homeService.getLang(), slug: pageSlug.value, uuid: created.uuid } });
    } catch {
        // ApiClient provides the shared request error feedback.
    } finally {
        creatingConversation.value = false;
    }
}

async function deleteConversation(item) {
    if (deletingUuid.value) return;
    deletingUuid.value = item.uuid;
    try {
        await freeAiModelService.deleteConversation(pageSlug.value, item.uuid);
        conversations.value = conversations.value.filter((entry) => entry.uuid !== item.uuid);
        if (item.uuid === activeUuid.value) {
            const next = conversations.value[0];
            if (next) await openConversation(next);
            else await newConversation();
        }
    } catch {
        // ApiClient provides the shared request error feedback.
    } finally {
        deletingUuid.value = null;
    }
}

async function selectExecutionModel(model) {
    if (!conversation.value?.uuid || !model?.isAvailable || modelSaving.value || modelKey(model) === modelKey(selectedModel.value)) return;
    const previous = selectedModel.value;
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    selectedModel.value = model;
    modelSaving.value = true;
    try {
        const response = await freeAiModelService.updateConversationModel(slug, uuid, model);
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        conversation.value = response?.data || conversation.value;
        syncSelectedModel();
        upsertConversationSummary(conversation.value);
        if (catalogSource.value) saveSelectedCatalogModel(catalogSource.value, pageSlug.value, selectedModel.value);
    } catch {
        if (slug === pageSlug.value && uuid === activeUuid.value) selectedModel.value = previous;
    } finally {
        modelSaving.value = false;
    }
}

function openSidebar() {
    if (isMobile.value) sidebarOpen.value = true;
    else desktopSidebarCollapsed.value = false;
}

function closeSidebar() {
    if (isMobile.value) sidebarOpen.value = false;
    else desktopSidebarCollapsed.value = true;
}

function backToTool() {
    router.push({ name: "free-ai-model.show", params: { lang: homeService.getLang(), slug: pageSlug.value } });
}

function handleResize() {
    viewportWidth.value = window.innerWidth;
    if (!isMobile.value) sidebarOpen.value = false;
}

function handleLanguageChanged() {
    loadedCatalogSource = null;
    catalogModels.value = [];
    loadCatalog(true);
}

onMounted(() => {
    window.addEventListener("resize", handleResize);
    window.addEventListener("lang-changed", handleLanguageChanged);
    Promise.all([loadConversations(), loadConversation()]);
});

onBeforeUnmount(() => {
    catalogRequestId++;
    conversationRequestId++;
    window.removeEventListener("resize", handleResize);
    window.removeEventListener("lang-changed", handleLanguageChanged);
    document.body.style.overflow = "";
});

watch(sidebarOpen, (open) => {
    if (isMobile.value) document.body.style.overflow = open ? "hidden" : "";
});

watch([pageSlug, activeUuid], ([slug, uuid], [previousSlug, previousUuid]) => {
    if (!slug || !uuid || (slug === previousSlug && uuid === previousUuid)) return;
    if (slug !== previousSlug) {
        catalogRequestId++;
        conversation.value = null;
        conversations.value = [];
        catalogModels.value = [];
        selectedModel.value = null;
        loadedCatalogSource = null;
        catalogLoading.value = false;
        catalogError.value = false;
        loadConversations();
    }
    loadConversation();
});
</script>

<style scoped>
.free-ai-chat {
    --navbar-height: 118px;
    width: 100%;
    height: calc(100dvh - var(--navbar-height));
    min-height: 0;
    display: flex;
    overflow: hidden;
    color: var(--theme-text-primary);
    background: var(--theme-bg);
}

button,
textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

.conversation-sidebar {
    position: relative;
    z-index: 20;
    width: 292px;
    min-width: 292px;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 14px 12px;
    overflow: hidden;
    border-inline-end: 1px solid var(--theme-border);
    background: var(--theme-surface);
    transition: width 0.22s ease, min-width 0.22s ease, padding 0.22s ease;
}

.conversation-sidebar.collapsed {
    width: 0;
    min-width: 0;
    padding-inline: 0;
    border-inline-end: 0;
}

.conversation-sidebar.collapsed > * {
    visibility: hidden;
}

.sidebar-heading,
.tool-identity,
.header-leading {
    min-width: 0;
    display: flex;
    align-items: center;
}

.sidebar-heading {
    justify-content: space-between;
    gap: 8px;
    padding: 3px 2px 13px;
}

.tool-identity,
.header-leading {
    gap: 10px;
}

.tool-avatar,
.header-avatar {
    overflow: hidden;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: #fff;
    background: linear-gradient(145deg, #154677, #2ba6de);
}

.tool-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
}

.tool-avatar img,
.header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tool-copy,
.header-copy {
    min-width: 0;
}

.tool-copy small,
.tool-copy strong,
.header-copy strong,
.header-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tool-copy small {
    color: var(--theme-text-muted);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.tool-copy strong {
    margin-top: 2px;
    font-size: 13px;
}

.icon-button {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    border: 1px solid var(--theme-border);
    border-radius: 11px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
}

.icon-button:hover {
    color: var(--theme-accent);
    border-color: var(--theme-border-strong);
    background: var(--theme-hover);
}

.new-conversation-button {
    width: 100%;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 10px 22px rgba(21, 70, 119, 0.2);
}

.new-conversation-button:disabled {
    cursor: wait;
    opacity: 0.65;
}

.history-heading {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 20px 6px 8px;
    color: var(--theme-text-muted);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.history-list {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: var(--theme-border-strong) transparent;
}

.history-state {
    display: grid;
    place-items: center;
    gap: 8px;
    padding: 34px 12px;
    color: var(--theme-text-muted);
    font-size: 11px;
    text-align: center;
}

.history-state i {
    font-size: 23px;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-bottom: 4px;
    padding: 3px;
    border: 1px solid transparent;
    border-radius: 11px;
}

.history-item:hover,
.history-item.active {
    border-color: var(--theme-border);
    background: var(--theme-hover);
}

.history-item.active {
    box-shadow: inset 3px 0 0 var(--theme-accent);
}

[dir="rtl"] .history-item.active {
    box-shadow: inset -3px 0 0 var(--theme-accent);
}

.history-open {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 6px;
    border: 0;
    color: var(--theme-text-primary);
    background: transparent;
    text-align: start;
}

.history-open > i {
    flex: 0 0 auto;
    color: var(--theme-text-muted);
}

.history-open > span {
    min-width: 0;
}

.history-open strong,
.history-open small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.history-open strong {
    font-size: 11px;
}

.history-open small {
    margin-top: 3px;
    color: var(--theme-text-muted);
    font-size: 9px;
}

.history-delete {
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    flex: 0 0 31px;
    border: 0;
    border-radius: 9px;
    color: var(--theme-text-muted);
    background: transparent;
    opacity: 0;
}

.history-item:hover .history-delete,
.history-item.active .history-delete,
.history-delete:focus-visible {
    opacity: 1;
}

.history-delete:hover {
    color: var(--app-danger);
    background: var(--theme-surface-secondary);
}

.chat-workspace {
    min-width: 0;
    min-height: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.workspace-header {
    min-height: 68px;
    flex: 0 0 68px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 18px;
    border-bottom: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.header-avatar {
    width: 42px;
    height: 42px;
    border-radius: 13px;
}

.header-copy strong {
    font-size: 14px;
}

.header-copy small {
    margin-top: 3px;
    color: var(--theme-text-muted);
    font-size: 10px;
}

.status-dot {
    width: 6px;
    height: 6px;
    display: inline-block;
    margin-inline-end: 4px;
    border-radius: 50%;
    background: var(--app-success);
}

.back-button,
.retry-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 12px;
    border: 1px solid var(--theme-border);
    border-radius: 11px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
    font-size: 11px;
    font-weight: 800;
}

[dir="rtl"] .back-button i {
    transform: rotate(180deg);
}

.messages {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    background:
        radial-gradient(circle at 50% 30%, rgba(43, 166, 222, 0.07), transparent 34%),
        var(--theme-bg);
}

.empty-conversation,
.conversation-state {
    width: min(620px, calc(100% - 32px));
    min-height: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 36px 0;
    text-align: center;
}

.empty-icon {
    width: 62px;
    height: 62px;
    display: grid;
    place-items: center;
    border: 1px solid var(--theme-border);
    border-radius: 20px;
    color: var(--theme-accent);
    background: var(--theme-surface);
    font-size: 26px;
    box-shadow: 0 16px 36px var(--theme-shadow);
}

.empty-conversation h1,
.conversation-state h1 {
    margin: 19px 0 8px;
    font-size: clamp(1.35rem, 3vw, 2rem);
}

.empty-conversation p,
.conversation-state p {
    max-width: 520px;
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 13px;
    line-height: 1.7;
}

.pending-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 17px;
    padding: 7px 11px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    color: var(--theme-text-muted);
    background: var(--theme-surface);
    font-size: 9px;
    font-weight: 800;
}

.conversation-state {
    flex: 1;
    gap: 10px;
}

.conversation-state > p,
.conversation-state > h1 {
    margin: 0;
}

.error-state > i {
    color: var(--app-danger);
    font-size: 34px;
}

.retry-button {
    margin-top: 8px;
}

.composer-area {
    flex: 0 0 auto;
    padding: 12px 18px 10px;
    border-top: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.composer-shell {
    width: min(980px, 100%);
    margin: 0 auto;
}

.composer-box {
    display: flex;
    align-items: stretch;
    overflow: visible;
    border: 1px solid var(--theme-border-strong);
    border-radius: 16px;
    background: var(--theme-surface-elevated);
    box-shadow: 0 10px 28px var(--theme-shadow);
}

.message-compose-row {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 7px 6px 10px;
}

.message-compose-row textarea {
    min-width: 0;
    min-height: 38px;
    max-height: 100px;
    flex: 1;
    resize: none;
    padding: 9px 6px;
    border: 0;
    outline: 0;
    color: var(--theme-text-secondary);
    background: transparent;
    font-size: 12px;
}

.message-compose-row textarea:disabled {
    cursor: not-allowed;
    opacity: 0.72;
}

.send-button {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    border: 0;
    border-radius: 11px;
    color: #fff;
    background: linear-gradient(135deg, #154677, #2ba6de);
}

.send-button:disabled {
    cursor: not-allowed;
    filter: grayscale(0.55);
    opacity: 0.45;
}

.composer-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin: 7px 0 0;
    color: var(--theme-text-muted);
    font-size: 8px;
    text-align: center;
}

.sidebar-overlay {
    display: none;
}

@media (max-width: 900px) {
    .free-ai-chat {
        --navbar-height: 104px;
    }

    .conversation-sidebar,
    .conversation-sidebar.collapsed {
        position: fixed;
        inset-block-start: var(--navbar-height);
        inset-block-end: 0;
        inset-inline-start: 0;
        z-index: 80;
        width: min(310px, 88vw);
        min-width: min(310px, 88vw);
        padding: 14px 12px;
        border-inline-end: 1px solid var(--theme-border);
        visibility: visible;
        transform: translateX(-105%);
        transition: transform 0.22s ease;
        box-shadow: 12px 0 36px var(--theme-shadow);
    }

    [dir="rtl"] .conversation-sidebar,
    [dir="rtl"] .conversation-sidebar.collapsed {
        transform: translateX(105%);
        box-shadow: -12px 0 36px var(--theme-shadow);
    }

    .conversation-sidebar.open,
    [dir="rtl"] .conversation-sidebar.open {
        transform: translateX(0);
    }

    .conversation-sidebar.collapsed > * {
        visibility: visible;
    }

    .sidebar-overlay {
        position: fixed;
        inset-block-start: var(--navbar-height);
        inset-block-end: 0;
        inset-inline: 0;
        z-index: 70;
        display: block;
        border: 0;
        background: rgba(4, 16, 29, 0.48);
        cursor: default;
    }

    .workspace-header {
        padding-inline: 12px;
    }
}

@media (max-width: 640px) {
    .free-ai-chat {
        --navbar-height: 90px;
    }

    .workspace-header {
        min-height: 62px;
        flex-basis: 62px;
        padding: 8px 9px;
    }

    .header-avatar {
        width: 36px;
        height: 36px;
        border-radius: 11px;
    }

    .header-copy strong {
        max-width: 38vw;
        font-size: 12px;
    }

    .header-copy small {
        max-width: 44vw;
        font-size: 8px;
    }

    .back-button {
        width: 36px;
        min-height: 36px;
        padding: 0;
    }

    .back-button span {
        display: none;
    }

    .composer-area {
        padding: 8px 9px 7px;
    }

    .composer-box {
        flex-direction: column;
        border-radius: 14px;
    }

    .message-compose-row {
        min-height: 50px;
        padding: 5px 6px 5px 9px;
    }

    .composer-hint {
        margin-top: 5px;
        font-size: 7px;
    }

    .empty-conversation {
        width: calc(100% - 24px);
        padding-block: 26px;
    }
}
</style>
