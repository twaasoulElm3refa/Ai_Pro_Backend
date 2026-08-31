<template>
    <section class="free-model-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="free-model-shell">
            <button type="button" class="back-link" @click="router.push(`/${homeService.getLang()}`)">
                <i class="bi bi-arrow-left"></i>
                {{ t("freeAiModels.back") }}
            </button>

            <div v-if="loading" class="state-card loading-card" aria-live="polite">
                <span class="spinner-border" aria-hidden="true"></span>
                <p>{{ t("freeAiModels.loadingModel") }}</p>
            </div>

            <div v-else-if="loadError" class="state-card error-card">
                <i class="bi bi-exclamation-triangle"></i>
                <h1>{{ t("freeAiModels.loadErrorTitle") }}</h1>
                <p>{{ t("freeAiModels.loadError") }}</p>
                <button type="button" class="secondary-button" @click="loadModel">
                    {{ t("freeAiModels.tryAgain") }}
                </button>
            </div>

            <article v-else class="model-hero">
                <div class="model-copy">
                    <span class="eyebrow">{{ t("freeAiModels.badge") }}</span>
                    <h1>{{ model.name }}</h1>
                    <p class="page-description">{{ model.description || t("freeAiModels.noDescription") }}</p>

                    <section class="model-selection-panel" :aria-label="t('freeAiModels.modelSelector')">
                        <div class="selection-heading">
                            <div>
                                <span>{{ t("freeAiModels.modelSelector") }}</span>
                                <strong>{{ t("freeAiModels.chooseCatalogModel") }}</strong>
                            </div>
                            <span v-if="catalogLoading" class="catalog-loading">
                                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                {{ t("freeAiModels.catalogLoading") }}
                            </span>
                        </div>

                        <div class="launch-row">
                            <div ref="selectorRef" class="model-selector">
                                <button
                                    type="button"
                                    class="selector-trigger"
                                    :disabled="catalogLoading || !catalogModels.length"
                                    :aria-expanded="dropdownOpen"
                                    aria-haspopup="listbox"
                                    @click="toggleDropdown"
                                    @keydown.down.prevent="openDropdownAndFocus"
                                    @keydown.esc.prevent="dropdownOpen = false"
                                >
                                    <span class="selector-icon"><i class="bi bi-stars"></i></span>
                                    <span class="selector-copy">
                                        <small>{{ t("freeAiModels.selectedModel") }}</small>
                                        <strong>{{ selectorLabel }}</strong>
                                    </span>
                                    <span v-if="selectedModel" class="compact-tier" :class="`tier-${selectedModel.tier}`">
                                        {{ tierLabel(selectedModel.tier) }}
                                    </span>
                                    <i class="bi bi-chevron-up selector-chevron" :class="{ open: dropdownOpen }"></i>
                                </button>

                                <div v-if="dropdownOpen" class="selector-menu" role="listbox">
                                    <button
                                        v-for="catalogModel in catalogModels"
                                        :key="modelKey(catalogModel)"
                                        type="button"
                                        class="selector-option"
                                        :class="{
                                            selected: isSelected(catalogModel),
                                            unavailable: !catalogModel.isAvailable,
                                        }"
                                        :disabled="!catalogModel.isAvailable"
                                        :aria-selected="isSelected(catalogModel)"
                                        role="option"
                                        @click="selectModel(catalogModel)"
                                    >
                                        <span class="option-icon"><i class="bi bi-cpu"></i></span>
                                        <span class="option-copy">
                                            <strong>
                                                {{ catalogModel.name }}
                                                <i
                                                    v-if="catalogModel.isRecommended"
                                                    class="bi bi-patch-check-fill recommended-icon"
                                                    :title="t('freeAiModels.recommended')"
                                                ></i>
                                            </strong>
                                            <small v-if="!catalogModel.isAvailable">{{ t("freeAiModels.modelUnavailable") }}</small>
                                            <small v-else>{{ catalogModel.isFree ? t("freeAiModels.free") : t("freeAiModels.paid") }}</small>
                                        </span>
                                        <span class="option-tier" :class="`tier-${catalogModel.tier}`">
                                            {{ tierLabel(catalogModel.tier) }}
                                        </span>
                                        <i v-if="isSelected(catalogModel)" class="bi bi-check-lg option-check"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="prompt-field" :title="t('freeAiModels.integrationPending')">
                                <i class="bi bi-chat-left-text"></i>
                                <input :placeholder="t('freeAiModels.inputPlaceholder')" disabled />
                            </div>

                            <button type="button" class="primary-button" :disabled="startingChat" @click="startChat">
                                <span v-if="startingChat" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <i v-else class="bi bi-chat-dots-fill"></i>
                                <span>{{ startingChat ? t("freeAiModels.startingChat") : t("freeAiModels.startChat") }}</span>
                            </button>
                        </div>

                        <p v-if="catalogError" class="catalog-message" role="status">
                            <i class="bi bi-exclamation-circle"></i>
                            {{ t("freeAiModels.catalogUnavailable") }}
                            <button type="button" @click="loadCatalog">{{ t("freeAiModels.tryAgain") }}</button>
                        </p>

                        <div v-if="selectedModel" class="selected-model-details">
                            <div class="details-heading">
                                <span class="details-icon"><i class="bi bi-stars"></i></span>
                                <div>
                                    <small>{{ t("freeAiModels.model") }}</small>
                                    <h2>{{ selectedModel.name }}</h2>
                                </div>
                            </div>
                            <div class="details-badges">
                                <span :class="`tier-${selectedModel.tier}`">{{ tierLabel(selectedModel.tier) }}</span>
                                <span :class="selectedModel.isFree ? 'free-state' : 'paid-state'">
                                    {{ selectedModel.isFree ? t("freeAiModels.free") : t("freeAiModels.paid") }}
                                </span>
                                <span v-if="selectedModel.provider" class="provider-state">{{ selectedModel.provider }}</span>
                            </div>
                            <p>{{ selectedModel.description }}</p>
                        </div>
                    </section>

                    <span class="action-note">
                        <i class="bi bi-shield-check"></i>
                        {{ t("freeAiModels.privateConversation") }}
                    </span>
                </div>

                <div class="model-visual">
                    <img v-if="model.image_url" :src="model.image_url" :alt="model.name" />
                    <div v-else class="model-placeholder" aria-hidden="true">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div v-if="selectedModel" class="visual-model-chip">
                        <span class="online-dot"></span>
                        <span>{{ selectedModel.name }}</span>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";
import freeAiModelService from "@/services/freeAiModels/freeAiModelService";
import modelCatalogService from "@/services/modelCatalog/modelCatalogService";
import {
    readSelectedCatalogModel,
    saveSelectedCatalogModel,
} from "@/services/modelCatalog/selectedModelStorage";

const CATALOG_SOURCE = "general_chat";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const model = ref({});
const loading = ref(true);
const loadError = ref(false);
const startingChat = ref(false);
const catalogLoading = ref(false);
const catalogError = ref(false);
const catalogModels = ref([]);
const storedModel = readSelectedCatalogModel(CATALOG_SOURCE, route.params.slug);
const selectedModel = ref(storedModel?.isAvailable === false ? null : storedModel);
const dropdownOpen = ref(false);
const selectorRef = ref(null);
let catalogLoaded = false;

const seoTitle = computed(() => model.value.meta_title || model.value.name || "AI Pro");
const seoDescription = computed(() => model.value.meta_description || model.value.description || "");
const seoKeywords = computed(() => model.value.seo_keywords || "");
const selectorLabel = computed(() => {
    if (catalogLoading.value) return t("freeAiModels.catalogLoading");
    if (selectedModel.value) return selectedModel.value.name;
    return catalogError.value ? t("freeAiModels.catalogUnavailable") : t("freeAiModels.selectModel");
});

useSeoMeta({ title: seoTitle, description: seoDescription, keywords: seoKeywords });

const modelKey = (catalogModel) =>
    String(catalogModel.providerModelId || catalogModel.id || catalogModel.name);

const isSelected = (catalogModel) =>
    Boolean(selectedModel.value) && modelKey(selectedModel.value) === modelKey(catalogModel);

const tierLabel = (tier) => {
    const key = {
        free: "tierFree",
        standard: "tierStandard",
        advanced: "tierAdvanced",
    }[tier] || "tierStandard";

    return t(`freeAiModels.${key}`);
};

const chooseDefaultModel = () => {
    if (!catalogModels.value.length) {
        selectedModel.value = null;
        return;
    }

    const previous = readSelectedCatalogModel(CATALOG_SOURCE, route.params.slug);
    const previousMatch = previous
        ? catalogModels.value.find((item) => modelKey(item) === modelKey(previous) && item.isAvailable)
        : null;

    selectedModel.value =
        previousMatch
        || catalogModels.value.find((item) => item.isAvailable && item.isRecommended)
        || catalogModels.value.find((item) => item.isAvailable)
        || null;

    if (selectedModel.value?.isAvailable) {
        saveSelectedCatalogModel(CATALOG_SOURCE, route.params.slug, selectedModel.value);
    }
};

async function loadModel() {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await freeAiModelService.getModel(route.params.slug);
        model.value = response?.data || {};
    } catch {
        model.value = {};
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

async function loadCatalog() {
    if (catalogLoading.value || (catalogLoaded && !catalogError.value)) return;

    catalogLoading.value = true;
    catalogError.value = false;

    try {
        const result = await modelCatalogService.getModels(CATALOG_SOURCE, {
            fallbackDescription: t("freeAiModels.modelDescriptionFallback"),
        });
        catalogModels.value = result.models;
        catalogLoaded = true;
        chooseDefaultModel();
    } catch {
        catalogError.value = true;
    } finally {
        catalogLoading.value = false;
    }
}

const selectModel = (catalogModel) => {
    if (!catalogModel.isAvailable) return;

    selectedModel.value = catalogModel;
    saveSelectedCatalogModel(CATALOG_SOURCE, route.params.slug, catalogModel);
    dropdownOpen.value = false;
};

const toggleDropdown = () => {
    if (!catalogModels.value.length) return;
    dropdownOpen.value = !dropdownOpen.value;
};

const openDropdownAndFocus = async () => {
    if (!catalogModels.value.length) return;
    dropdownOpen.value = true;
    await nextTick();
    selectorRef.value?.querySelector(".selector-option:not(:disabled)")?.focus();
};

const handleOutsidePointer = (event) => {
    if (dropdownOpen.value && !selectorRef.value?.contains(event.target)) {
        dropdownOpen.value = false;
    }
};

const handleEscape = (event) => {
    if (event.key === "Escape") dropdownOpen.value = false;
};

async function startChat() {
    if (startingChat.value) return;

    if (!localStorage.getItem("auth_token")) {
        await router.push(`/${homeService.getLang()}/auth`);
        return;
    }

    if (selectedModel.value?.isAvailable) {
        saveSelectedCatalogModel(CATALOG_SOURCE, route.params.slug, selectedModel.value);
    }

    startingChat.value = true;

    try {
        const response = await freeAiModelService.createConversation(route.params.slug);
        const uuid = response?.data?.uuid;

        if (!uuid) throw new Error("Missing conversation UUID");

        await router.push({
            name: "free-ai-model.chat",
            params: {
                lang: homeService.getLang(),
                slug: route.params.slug,
                uuid,
            },
        });
    } catch {
        // ApiClient displays the request error and the model page remains usable.
    } finally {
        startingChat.value = false;
    }
}

onMounted(() => {
    document.addEventListener("pointerdown", handleOutsidePointer);
    document.addEventListener("keydown", handleEscape);
    loadModel();
    loadCatalog();
});

onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", handleOutsidePointer);
    document.removeEventListener("keydown", handleEscape);
});

watch(
    () => route.params.slug,
    (slug, previousSlug) => {
        if (!slug || slug === previousSlug) return;
        dropdownOpen.value = false;
        loadModel();
        chooseDefaultModel();
    }
);
</script>

<style scoped>
.free-model-page {
    min-height: 100vh;
    padding: 112px 16px 64px;
    color: var(--theme-text-primary);
    background:
        radial-gradient(circle at 12% 8%, rgba(43, 166, 222, 0.13), transparent 30%),
        radial-gradient(circle at 88% 4%, rgba(21, 70, 119, 0.1), transparent 26%),
        var(--theme-bg);
}

.free-model-shell {
    width: min(1380px, 100%);
    margin: 0 auto;
}

button,
input {
    font: inherit;
}

button {
    cursor: pointer;
}

button:disabled {
    cursor: not-allowed;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 18px;
    padding: 8px 0;
    border: 0;
    color: var(--theme-text-secondary);
    background: transparent;
    font-weight: 800;
}

[dir="rtl"] .back-link i {
    transform: rotate(180deg);
}

.model-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(290px, 0.65fr);
    gap: clamp(24px, 4vw, 54px);
    align-items: stretch;
    padding: clamp(24px, 4vw, 52px);
    border: 1px solid var(--theme-border);
    border-radius: 30px;
    background: var(--theme-surface);
    box-shadow: 0 24px 70px var(--theme-shadow);
}

.model-copy {
    min-width: 0;
}

.eyebrow {
    display: inline-flex;
    padding: 7px 12px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    color: var(--theme-accent);
    background: var(--theme-surface-secondary);
    font-size: 12px;
    font-weight: 800;
}

.model-copy > h1 {
    margin: 16px 0 10px;
    color: var(--theme-text-primary);
    font-size: clamp(2.2rem, 5vw, 4.25rem);
    font-weight: 900;
    line-height: 1.06;
}

.page-description {
    max-width: 780px;
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 16px;
    line-height: 1.8;
    white-space: pre-line;
}

.model-selection-panel {
    position: relative;
    z-index: 5;
    margin-top: 30px;
    padding: 18px;
    border: 1px solid var(--theme-border);
    border-radius: 22px;
    background: var(--theme-surface-secondary);
}

.selection-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 13px;
}

.selection-heading > div span,
.selection-heading > div strong {
    display: block;
}

.selection-heading > div span {
    color: var(--theme-accent);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}

.selection-heading > div strong {
    margin-top: 2px;
    color: var(--theme-text-primary);
    font-size: 14px;
}

.catalog-loading {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--theme-text-muted);
    font-size: 11px;
}

.launch-row {
    display: grid;
    grid-template-columns: minmax(250px, 0.8fr) minmax(220px, 1fr) auto;
    gap: 10px;
    align-items: stretch;
}

.model-selector {
    position: relative;
    min-width: 0;
}

.selector-trigger {
    width: 100%;
    height: 100%;
    min-height: 58px;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 11px;
    border: 1px solid var(--theme-border-strong);
    border-radius: 15px;
    color: var(--theme-text-primary);
    background: var(--theme-input-bg);
    text-align: start;
    box-shadow: 0 6px 18px var(--theme-shadow);
}

.selector-trigger:focus-visible,
.selector-trigger[aria-expanded="true"] {
    outline: 0;
    border-color: var(--theme-accent);
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.13);
}

.selector-trigger:disabled {
    opacity: 0.68;
}

.selector-icon,
.details-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: #ffffff;
    background: linear-gradient(145deg, #154677, #2ba6de);
}

.selector-icon {
    width: 36px;
    height: 36px;
    border-radius: 11px;
}

.selector-copy {
    min-width: 0;
    flex: 1;
}

.selector-copy small,
.selector-copy strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selector-copy small {
    color: var(--theme-text-muted);
    font-size: 9px;
    text-transform: uppercase;
}

.selector-copy strong {
    margin-top: 2px;
    font-size: 12px;
}

.compact-tier,
.option-tier,
.details-badges span {
    padding: 5px 8px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    background: var(--theme-surface-secondary);
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
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

.paid-state,
.provider-state {
    color: var(--theme-text-secondary);
}

.selector-chevron {
    color: var(--theme-text-muted);
    transition: transform 0.2s ease;
}

.selector-chevron.open {
    transform: rotate(180deg);
}

.selector-menu {
    position: absolute;
    inset-inline: 0;
    bottom: calc(100% + 10px);
    z-index: 80;
    max-height: min(380px, calc(100dvh - 190px));
    padding: 7px;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    border: 1px solid var(--theme-border-strong);
    border-radius: 16px;
    background: var(--theme-surface-elevated);
    box-shadow: 0 22px 55px var(--theme-shadow);
    scrollbar-width: thin;
    scrollbar-color: var(--theme-border-strong) transparent;
}

.selector-option {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px;
    border: 1px solid transparent;
    border-radius: 12px;
    color: var(--theme-text-primary);
    background: transparent;
    text-align: start;
}

.selector-option:hover:not(:disabled),
.selector-option:focus-visible,
.selector-option.selected {
    outline: 0;
    border-color: var(--theme-border);
    background: var(--theme-hover);
}

.selector-option.unavailable {
    opacity: 0.48;
}

.option-icon {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    flex: 0 0 32px;
    border-radius: 10px;
    color: var(--theme-accent);
    background: var(--theme-surface-secondary);
}

.option-copy {
    min-width: 0;
    flex: 1;
}

.option-copy strong,
.option-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.option-copy strong {
    font-size: 12px;
}

.option-copy small {
    margin-top: 2px;
    color: var(--theme-text-muted);
    font-size: 10px;
}

.recommended-icon,
.option-check {
    color: var(--theme-accent);
}

.prompt-field {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 14px;
    border: 1px solid var(--theme-border);
    border-radius: 15px;
    color: var(--theme-text-muted);
    background: var(--theme-input-bg);
}

.prompt-field input {
    min-width: 0;
    width: 100%;
    border: 0;
    outline: 0;
    color: var(--theme-text-muted);
    background: transparent;
}

.primary-button,
.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 14px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-size: 13px;
    font-weight: 800;
}

.primary-button {
    min-height: 58px;
    padding: 12px 18px;
    white-space: nowrap;
    box-shadow: 0 10px 24px rgba(21, 70, 119, 0.19);
}

.primary-button:disabled {
    opacity: 0.65;
}

.secondary-button {
    padding: 10px 16px;
}

.catalog-message {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 11px 2px 0;
    color: var(--theme-text-muted);
    font-size: 11px;
}

.catalog-message button {
    padding: 0;
    border: 0;
    color: var(--theme-accent);
    background: transparent;
    font-weight: 800;
}

.selected-model-details {
    display: grid;
    grid-template-columns: minmax(180px, auto) auto minmax(180px, 1fr);
    gap: 14px;
    align-items: center;
    margin-top: 14px;
    padding: 14px;
    border: 1px solid var(--theme-border);
    border-radius: 16px;
    background: var(--theme-surface);
}

.details-heading,
.details-badges {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.details-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
}

.details-heading > div {
    min-width: 0;
}

.details-heading small {
    color: var(--theme-text-muted);
    font-size: 9px;
    text-transform: uppercase;
}

.details-heading h2 {
    margin: 1px 0 0;
    overflow: hidden;
    color: var(--theme-text-primary);
    font-size: 13px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.details-badges {
    flex-wrap: wrap;
}

.selected-model-details > p {
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 12px;
    line-height: 1.6;
}

.action-note {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 16px;
    color: var(--theme-text-muted);
    font-size: 12px;
}

.model-visual {
    position: relative;
    min-height: 430px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--theme-border);
    border-radius: 25px;
    background:
        radial-gradient(circle at 50% 30%, rgba(43, 166, 222, 0.24), transparent 38%),
        linear-gradient(145deg, var(--theme-surface-secondary), var(--theme-bg-secondary));
}

.model-visual img {
    width: 100%;
    height: 100%;
    min-height: 430px;
    object-fit: cover;
}

.model-placeholder {
    color: var(--theme-accent);
    font-size: clamp(4rem, 8vw, 7rem);
}

.visual-model-chip {
    position: absolute;
    inset-inline: 16px;
    bottom: 16px;
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid var(--theme-border);
    border-radius: 14px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 12px 30px var(--theme-shadow);
    font-size: 12px;
    font-weight: 800;
}

.visual-model-chip span:last-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.online-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 8px;
    border-radius: 50%;
    background: var(--app-success);
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.11);
}

.state-card {
    min-height: 420px;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 14px;
    padding: 30px;
    border: 1px solid var(--theme-border);
    border-radius: 28px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 18px 50px var(--theme-shadow);
    text-align: center;
}

.state-card > i {
    color: var(--app-danger);
    font-size: 38px;
}

.state-card h1,
.state-card p {
    margin: 0;
}

.state-card p {
    color: var(--theme-text-secondary);
}

@media (max-width: 1120px) {
    .model-hero {
        grid-template-columns: minmax(0, 1fr) 290px;
        gap: 24px;
    }

    .launch-row {
        grid-template-columns: minmax(230px, 0.85fr) minmax(180px, 1fr);
    }

    .launch-row > .primary-button {
        grid-column: 1 / -1;
    }

    .selected-model-details {
        grid-template-columns: 1fr auto;
    }

    .selected-model-details > p {
        grid-column: 1 / -1;
    }
}

@media (max-width: 820px) {
    .free-model-page {
        padding-top: 96px;
    }

    .model-hero {
        grid-template-columns: 1fr;
    }

    .model-visual {
        min-height: 250px;
        order: -1;
    }

    .model-visual img {
        min-height: 250px;
    }
}

@media (max-width: 620px) {
    .free-model-page {
        padding: 88px 10px 38px;
    }

    .model-hero {
        padding: 18px;
        border-radius: 22px;
    }

    .model-selection-panel {
        padding: 12px;
        border-radius: 18px;
    }

    .selection-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
    }

    .launch-row {
        grid-template-columns: 1fr;
    }

    .launch-row > .primary-button {
        grid-column: auto;
    }

    .selector-trigger,
    .prompt-field,
    .primary-button {
        min-height: 54px;
    }

    .selector-menu {
        max-height: min(330px, calc(100dvh - 150px));
    }

    .selected-model-details {
        grid-template-columns: 1fr;
    }

    .selected-model-details > p {
        grid-column: auto;
    }

    .compact-tier {
        display: none;
    }
}
</style>
