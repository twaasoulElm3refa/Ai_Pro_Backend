<template>
    <section class="free-chat-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div v-if="loading" class="chat-state" aria-live="polite">
            <span class="spinner-border" aria-hidden="true"></span>
            <p>{{ t("freeAiModels.loadingConversation") }}</p>
        </div>

        <div v-else-if="loadError" class="chat-state error-state">
            <i class="bi bi-shield-exclamation"></i>
            <h1>{{ t("freeAiModels.conversationUnavailable") }}</h1>
            <p>{{ t("freeAiModels.conversationUnavailableDescription") }}</p>
            <button type="button" class="primary-button" @click="backToModel">
                {{ t("freeAiModels.backToModel") }}
            </button>
        </div>

        <div v-else class="chat-shell">
            <aside class="chat-sidebar" :class="{ open: sidebarOpen }">
                <button
                    type="button"
                    class="sidebar-close"
                    :aria-label="t('freeAiModels.closeSidebar')"
                    @click="sidebarOpen = false"
                >
                    <i class="bi bi-x-lg"></i>
                </button>

                <div class="model-card">
                    <img v-if="conversation.model?.image_url" :src="conversation.model.image_url" :alt="modelName" />
                    <span v-else class="model-icon"><i class="bi bi-stars"></i></span>
                    <div class="model-card-copy">
                        <small>{{ t("freeAiModels.model") }}</small>
                        <strong>{{ modelName }}</strong>
                    </div>
                </div>

                <p class="sidebar-description">{{ modelDescription }}</p>

                <div class="sidebar-badges">
                    <span :class="`tier-${modelTier}`">{{ tierLabel }}</span>
                    <span :class="modelIsFree ? 'free-state' : 'paid-state'">
                        <i :class="modelIsFree ? 'bi bi-unlock' : 'bi bi-lock'"></i>
                        {{ modelIsFree ? t("freeAiModels.free") : t("freeAiModels.paid") }}
                    </span>
                </div>

                <button type="button" class="new-chat-button" :disabled="creatingConversation" @click="newConversation">
                    <span v-if="creatingConversation" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <i v-else class="bi bi-plus-lg"></i>
                    {{ creatingConversation ? t("freeAiModels.creatingConversation") : t("freeAiModels.newConversation") }}
                </button>

                <div class="conversation-info">
                    <div>
                        <span class="info-icon"><i class="bi bi-person"></i></span>
                        <p>
                            <small>{{ t("freeAiModels.signedInAs") }}</small>
                            <strong>{{ conversation.user?.name }}</strong>
                        </p>
                    </div>
                    <div>
                        <span class="info-icon"><i class="bi bi-hash"></i></span>
                        <p>
                            <small>{{ t("freeAiModels.conversationId") }}</small>
                            <code>{{ shortUuid }}</code>
                        </p>
                    </div>
                </div>
            </aside>

            <button
                v-if="sidebarOpen"
                type="button"
                class="sidebar-overlay"
                :aria-label="t('freeAiModels.closeSidebar')"
                @click="sidebarOpen = false"
            ></button>

            <main class="chat-workspace">
                <header class="chat-header">
                    <button
                        type="button"
                        class="mobile-menu-button"
                        :aria-label="t('freeAiModels.openSidebar')"
                        @click="sidebarOpen = true"
                    >
                        <i class="bi bi-layout-sidebar-inset"></i>
                    </button>

                    <div class="header-model">
                        <span class="online-dot"></span>
                        <div>
                            <small>{{ t("freeAiModels.modelAvailable") }}</small>
                            <strong>{{ modelName }}</strong>
                        </div>
                    </div>

                    <div class="header-badges">
                        <span :class="`tier-${modelTier}`">{{ tierLabel }}</span>
                        <span :class="modelIsFree ? 'free-state' : 'paid-state'">
                            {{ modelIsFree ? t("freeAiModels.free") : t("freeAiModels.paid") }}
                        </span>
                    </div>

                    <button type="button" class="back-button" @click="backToModel">
                        <i class="bi bi-arrow-left"></i>
                        <span>{{ t("freeAiModels.backToModel") }}</span>
                    </button>
                </header>

                <div class="messages-area">
                    <div class="empty-chat">
                        <span class="empty-icon"><i class="bi bi-chat-square-text"></i></span>
                        <span class="ready-label">
                            <span class="online-dot"></span>
                            {{ t("freeAiModels.conversationReady") }}
                        </span>
                        <h1>{{ t("freeAiModels.emptyChatTitle", { name: modelName }) }}</h1>
                        <p class="empty-description">{{ modelDescription }}</p>
                        <p>{{ t("freeAiModels.emptyChatDescription") }}</p>
                        <span class="coming-soon">
                            <i class="bi bi-clock-history"></i>
                            {{ t("freeAiModels.integrationPending") }}
                        </span>
                    </div>
                </div>

                <footer class="composer">
                    <div class="composer-box">
                        <textarea :placeholder="t('freeAiModels.inputPlaceholder')" disabled rows="1"></textarea>
                        <button type="button" disabled :aria-label="t('freeAiModels.send')">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                    <p><i class="bi bi-info-circle"></i>{{ t("freeAiModels.composerUnavailableHint") }}</p>
                </footer>
            </main>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";
import freeAiModelService from "@/services/freeAiModels/freeAiModelService";
import { readSelectedCatalogModel } from "@/services/modelCatalog/selectedModelStorage";

const CATALOG_SOURCE = "general_chat";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const conversation = ref({});
const catalogModel = ref(readSelectedCatalogModel(CATALOG_SOURCE, route.params.slug));
const loading = ref(true);
const loadError = ref(false);
const creatingConversation = ref(false);
const sidebarOpen = ref(false);

const shortUuid = computed(() => String(conversation.value.uuid || "").slice(0, 12));
const modelName = computed(() => catalogModel.value?.name || conversation.value.model?.name || t("freeAiModels.model"));
const modelDescription = computed(() =>
    catalogModel.value?.description
    || conversation.value.model?.description
    || t("freeAiModels.modelDescriptionFallback")
);
const modelTier = computed(() => catalogModel.value?.tier || "free");
const modelIsFree = computed(() => catalogModel.value ? catalogModel.value.isFree === true : true);
const tierLabel = computed(() => {
    const key = {
        free: "tierFree",
        standard: "tierStandard",
        advanced: "tierAdvanced",
    }[modelTier.value] || "tierStandard";

    return t(`freeAiModels.${key}`);
});

const seoTitle = computed(() =>
    conversation.value.model?.meta_title
    || conversation.value.model?.name
    || catalogModel.value?.name
    || "AI Pro"
);
const seoDescription = computed(() =>
    conversation.value.model?.meta_description
    || conversation.value.model?.description
    || catalogModel.value?.description
    || ""
);
const seoKeywords = computed(() => conversation.value.model?.seo_keywords || "");

useSeoMeta({ title: seoTitle, description: seoDescription, keywords: seoKeywords });

async function loadConversation() {
    loading.value = true;
    loadError.value = false;
    sidebarOpen.value = false;

    try {
        const response = await freeAiModelService.getConversation(route.params.slug, route.params.uuid);
        conversation.value = response?.data || {};
    } catch {
        conversation.value = {};
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

async function newConversation() {
    if (creatingConversation.value) return;
    creatingConversation.value = true;

    try {
        const response = await freeAiModelService.createConversation(route.params.slug);
        const uuid = response?.data?.uuid;

        if (!uuid) throw new Error("Missing conversation UUID");

        sidebarOpen.value = false;
        await router.replace({
            name: "free-ai-model.chat",
            params: { lang: homeService.getLang(), slug: route.params.slug, uuid },
        });
    } catch {
        // ApiClient displays the request error and keeps the current conversation open.
    } finally {
        creatingConversation.value = false;
    }
}

function backToModel() {
    sidebarOpen.value = false;
    router.push({
        name: "free-ai-model.show",
        params: { lang: homeService.getLang(), slug: route.params.slug },
    });
}

watch(
    () => [route.params.slug, route.params.uuid],
    ([slug], [previousSlug] = []) => {
        if (slug !== previousSlug) {
            catalogModel.value = readSelectedCatalogModel(CATALOG_SOURCE, slug);
        }
        loadConversation();
    },
    { immediate: true }
);
</script>

<style scoped>
.free-chat-page {
    --navbar-offset: 70px;
    width: 100%;
    height: calc(100dvh - var(--navbar-offset));
    min-height: 0;
    padding: 10px 12px;
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

button:disabled,
textarea:disabled {
    cursor: not-allowed;
}

.chat-shell {
    width: min(100%, 1600px);
    height: 100%;
    min-height: 0;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 282px minmax(0, 1fr);
    overflow: hidden;
    border: 1px solid var(--theme-border);
    border-radius: 22px;
    background: var(--theme-surface);
    box-shadow: 0 16px 52px var(--theme-shadow);
}

.chat-sidebar {
    position: relative;
    z-index: 40;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 22px 18px;
    overflow-y: auto;
    color: #ffffff;
    background:
        radial-gradient(circle at 18% 4%, rgba(98, 200, 240, 0.2), transparent 31%),
        linear-gradient(165deg, #154677 0%, #11385f 100%);
}

.sidebar-close {
    display: none;
}

.model-card {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 11px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.14);
}

.model-card img,
.model-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border-radius: 14px;
    object-fit: cover;
}

.model-icon {
    display: grid;
    place-items: center;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
    font-size: 19px;
}

.model-card-copy {
    min-width: 0;
}

.model-card-copy small,
.model-card-copy strong {
    display: block;
}

.model-card-copy small {
    color: rgba(255, 255, 255, 0.62);
    font-size: 10px;
    text-transform: uppercase;
}

.model-card-copy strong {
    margin-top: 3px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
}

.sidebar-description {
    display: -webkit-box;
    margin: 0;
    overflow: hidden;
    color: rgba(255, 255, 255, 0.72);
    font-size: 12px;
    line-height: 1.65;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.sidebar-badges,
.header-badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
}

.sidebar-badges span,
.header-badges span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 800;
}

.sidebar-badges span {
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
}

.new-chat-button,
.primary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 45px;
    padding: 11px 15px;
    border: 0;
    border-radius: 13px;
    color: #154677;
    background: #ffffff;
    font-size: 13px;
    font-weight: 850;
    box-shadow: 0 11px 25px rgba(0, 0, 0, 0.15);
}

.new-chat-button:disabled {
    opacity: 0.65;
}

.conversation-info {
    display: grid;
    gap: 10px;
    margin-top: auto;
    padding: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.07);
}

.conversation-info > div {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
}

.info-icon {
    width: 29px;
    height: 29px;
    display: grid;
    place-items: center;
    flex: 0 0 29px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.1);
}

.conversation-info p {
    min-width: 0;
    margin: 0;
}

.conversation-info small,
.conversation-info strong,
.conversation-info code {
    display: block;
}

.conversation-info small {
    color: rgba(255, 255, 255, 0.58);
    font-size: 9px;
}

.conversation-info strong,
.conversation-info code {
    margin-top: 1px;
    overflow: hidden;
    color: #ffffff;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-info code {
    direction: ltr;
    text-align: start;
}

.chat-workspace {
    min-width: 0;
    min-height: 0;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    overflow: hidden;
    background: var(--theme-bg-secondary);
}

.chat-header {
    min-width: 0;
    min-height: 64px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 18px;
    border-bottom: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.header-model {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-model > div {
    min-width: 0;
}

.header-model small,
.header-model strong {
    display: block;
}

.header-model small {
    color: var(--app-success);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.header-model strong {
    max-width: min(42vw, 560px);
    margin-top: 2px;
    overflow: hidden;
    color: var(--theme-text-primary);
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.online-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 8px;
    border-radius: 50%;
    background: var(--app-success);
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.12);
}

.header-badges {
    margin-inline-start: auto;
}

.header-badges span {
    border: 1px solid var(--theme-border);
    background: var(--theme-surface-secondary);
}

.tier-free,
.free-state {
    color: var(--app-success);
}

.tier-standard {
    color: var(--theme-accent);
}

.tier-advanced {
    color: var(--theme-primary);
}

.paid-state {
    color: var(--theme-text-secondary);
}

.back-button,
.mobile-menu-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 39px;
    padding: 8px 11px;
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

.mobile-menu-button {
    display: none;
}

.messages-area {
    min-height: 0;
    overflow-y: auto;
    padding: 24px;
}

.empty-chat {
    width: min(100%, 650px);
    min-height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    margin: 0 auto;
    padding: 28px 20px;
    text-align: center;
}

.empty-icon {
    width: 70px;
    height: 70px;
    display: grid;
    place-items: center;
    margin-bottom: 15px;
    border: 1px solid var(--theme-border);
    border-radius: 22px;
    color: #ffffff;
    background: linear-gradient(145deg, #154677, #2ba6de);
    box-shadow: 0 15px 32px rgba(21, 70, 119, 0.2);
    font-size: 26px;
}

.ready-label,
.coming-soon {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 10px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    background: var(--theme-surface);
    font-size: 10px;
    font-weight: 800;
}

.ready-label {
    color: var(--app-success);
}

.empty-chat h1 {
    margin: 14px 0 8px;
    overflow-wrap: anywhere;
    color: var(--theme-text-primary);
    font-size: clamp(1.65rem, 3.2vw, 2.5rem);
}

.empty-chat p {
    max-width: 570px;
    margin: 5px 0;
    color: var(--theme-text-muted);
    font-size: 13px;
    line-height: 1.7;
}

.empty-chat .empty-description {
    color: var(--theme-text-secondary);
    font-size: 14px;
}

.coming-soon {
    margin-top: 14px;
    color: var(--theme-accent);
    background: var(--theme-surface-secondary);
}

.composer {
    padding: 12px max(18px, calc((100% - 980px) / 2)) 10px;
    border-top: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.composer-box {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7px;
    border: 1px solid var(--theme-border);
    border-radius: 16px;
    background: var(--theme-input-bg);
    box-shadow: 0 7px 22px var(--theme-shadow);
    opacity: 0.72;
}

.composer textarea {
    min-width: 0;
    min-height: 42px;
    flex: 1;
    resize: none;
    padding: 10px;
    border: 0;
    outline: 0;
    color: var(--theme-text-muted);
    background: transparent;
}

.composer button {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 13px;
    color: var(--theme-text-muted);
    background: var(--theme-surface-elevated);
}

.composer > p {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin: 7px 0 0;
    color: var(--theme-text-muted);
    font-size: 10px;
}

.chat-state {
    width: min(100%, 760px);
    height: 100%;
    margin: 0 auto;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 13px;
    color: var(--theme-text-primary);
    text-align: center;
}

.chat-state p,
.chat-state h1 {
    margin: 0;
}

.chat-state p {
    color: var(--theme-text-secondary);
}

.error-state > i {
    color: var(--app-danger);
    font-size: 42px;
}

.error-state .primary-button {
    margin-top: 6px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
}

.sidebar-overlay {
    display: none;
}

@media (max-width: 900px) {
    .free-chat-page {
        padding: 0;
    }

    .chat-shell {
        grid-template-columns: minmax(0, 1fr);
        border-inline: 0;
        border-radius: 0;
    }

    .chat-sidebar {
        position: fixed;
        inset-block-start: var(--navbar-offset);
        inset-block-end: 0;
        inset-inline-start: 0;
        width: min(310px, 87vw);
        transform: translateX(-110%);
        transition: transform 0.22s ease;
        box-shadow: 18px 0 44px rgba(0, 0, 0, 0.22);
    }

    .free-chat-page[dir="rtl"] .chat-sidebar {
        transform: translateX(110%);
    }

    .chat-sidebar.open {
        transform: translateX(0);
    }

    .sidebar-close {
        position: absolute;
        inset-block-start: 10px;
        inset-inline-end: 10px;
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 10px;
        color: #ffffff;
        background: rgba(255, 255, 255, 0.08);
    }

    .model-card {
        padding-inline-end: 38px;
    }

    .sidebar-overlay {
        position: fixed;
        inset-block-start: var(--navbar-offset);
        inset-block-end: 0;
        inset-inline: 0;
        z-index: 30;
        display: block;
        border: 0;
        background: var(--theme-overlay);
    }

    .mobile-menu-button {
        display: inline-flex;
        flex: 0 0 auto;
        padding-inline: 10px;
    }
}

@media (max-width: 600px) {
    .chat-header {
        gap: 9px;
        padding: 9px 10px;
    }

    .header-model strong {
        max-width: calc(100vw - 190px);
        font-size: 12px;
    }

    .header-badges {
        display: none;
    }

    .back-button {
        margin-inline-start: auto;
    }

    .back-button span {
        display: none;
    }

    .messages-area {
        padding: 14px 11px;
    }

    .empty-chat {
        padding: 18px 10px;
    }

    .empty-icon {
        width: 60px;
        height: 60px;
        border-radius: 18px;
    }

    .composer {
        padding-inline: 10px;
    }

    .composer > p {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>
