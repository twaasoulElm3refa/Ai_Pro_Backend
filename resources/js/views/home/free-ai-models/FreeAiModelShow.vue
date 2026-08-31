<template>
    <section class="free-model-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="free-model-shell">
            <button type="button" class="back-link" @click="router.push(`/${homeService.getLang()}`)">
                <i class="bi bi-arrow-left"></i>
                {{ t("freeAiModels.back") }}
            </button>

            <div v-if="loading" class="state-card" aria-live="polite">
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
                    <p>{{ model.description || t("freeAiModels.noDescription") }}</p>

                    <div class="model-actions">
                        <button type="button" class="primary-button" :disabled="startingChat" @click="startChat">
                            <span v-if="startingChat" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            <i v-else class="bi bi-chat-dots-fill"></i>
                            {{ startingChat ? t("freeAiModels.startingChat") : t("freeAiModels.startChat") }}
                        </button>
                        <span class="action-note">
                            <i class="bi bi-shield-check"></i>
                            {{ t("freeAiModels.privateConversation") }}
                        </span>
                    </div>
                </div>

                <div class="model-visual">
                    <img v-if="model.image_url" :src="model.image_url" :alt="model.name" />
                    <div v-else class="model-placeholder" aria-hidden="true">
                        <i class="bi bi-stars"></i>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";
import freeAiModelService from "@/services/freeAiModels/freeAiModelService";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const model = ref({});
const loading = ref(true);
const loadError = ref(false);
const startingChat = ref(false);

const seoTitle = computed(() => model.value.meta_title || model.value.name || "AI Pro");
const seoDescription = computed(() => model.value.meta_description || model.value.description || "");
const seoKeywords = computed(() => model.value.seo_keywords || "");

useSeoMeta({ title: seoTitle, description: seoDescription, keywords: seoKeywords });

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

async function startChat() {
    if (startingChat.value) return;

    if (!localStorage.getItem("auth_token")) {
        await router.push(`/${homeService.getLang()}/auth`);
        return;
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
        // ApiClient handles the request error and leaves the landing page usable.
    } finally {
        startingChat.value = false;
    }
}

onMounted(loadModel);

watch(
    () => route.params.slug,
    (slug, previousSlug) => {
        if (!slug || slug === previousSlug) return;
        loadModel();
    }
);
</script>

<style scoped>
.free-model-page {
    min-height: 100vh;
    padding: 124px 16px 64px;
    color: var(--theme-text-primary);
    background:
        radial-gradient(circle at 12% 8%, rgba(43, 166, 222, 0.13), transparent 30%),
        radial-gradient(circle at 88% 4%, rgba(21, 70, 119, 0.1), transparent 26%),
        var(--theme-bg);
}

.free-model-shell {
    width: min(1240px, 100%);
    margin: 0 auto;
}

button {
    font: inherit;
    cursor: pointer;
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
    grid-template-columns: minmax(0, 1.25fr) minmax(280px, 0.75fr);
    gap: clamp(28px, 5vw, 64px);
    align-items: center;
    padding: clamp(28px, 5vw, 64px);
    border: 1px solid var(--theme-border);
    border-radius: 30px;
    background: var(--theme-surface);
    box-shadow: 0 24px 70px var(--theme-shadow);
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

.model-copy h1 {
    margin: 17px 0 12px;
    color: var(--theme-text-primary);
    font-size: clamp(2.3rem, 5vw, 4.5rem);
    font-weight: 900;
    line-height: 1.06;
}

.model-copy > p {
    max-width: 720px;
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 16px;
    line-height: 1.85;
    white-space: pre-line;
}

.model-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 30px;
}

.primary-button,
.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 48px;
    padding: 11px 18px;
    border: 0;
    border-radius: 14px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-weight: 800;
    box-shadow: 0 11px 25px rgba(21, 70, 119, 0.2);
}

.primary-button:disabled {
    cursor: wait;
    opacity: 0.65;
}

.action-note {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--theme-text-muted);
    font-size: 12px;
}

.model-visual {
    min-height: 360px;
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
    min-height: 360px;
    object-fit: cover;
}

.model-placeholder {
    color: var(--theme-accent);
    font-size: clamp(4rem, 8vw, 7rem);
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

@media (max-width: 800px) {
    .free-model-page {
        padding-top: 104px;
    }

    .model-hero {
        grid-template-columns: 1fr;
        padding: 24px;
    }

    .model-visual {
        min-height: 240px;
        order: -1;
    }

    .model-visual img {
        min-height: 240px;
    }
}

@media (max-width: 560px) {
    .free-model-page {
        padding: 94px 10px 38px;
    }

    .model-hero {
        padding: 18px;
        border-radius: 22px;
    }
}
</style>
