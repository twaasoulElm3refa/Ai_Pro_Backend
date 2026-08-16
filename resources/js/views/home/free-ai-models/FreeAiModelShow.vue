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
        // ApiClient displays the request error and the model page remains usable.
    } finally {
        startingChat.value = false;
    }
}

onMounted(loadModel);
watch(() => route.params.slug, loadModel);
</script>

<style scoped>
.free-model-page {
    min-height: 78vh;
    padding: 3rem 1rem 5rem;
    background: radial-gradient(circle at 15% 10%, rgba(98, 75, 255, .12), transparent 34%);
}
.free-model-shell { max-width: 1120px; margin: 0 auto; }
.back-link { display: inline-flex; align-items: center; gap: .55rem; border: 0; background: transparent; color: var(--text-color, #444); font-weight: 700; margin-bottom: 1.5rem; }
[dir="rtl"] .back-link i { transform: rotate(180deg); }
.model-hero { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(260px, .75fr); gap: 3rem; align-items: center; padding: clamp(2rem, 5vw, 4.5rem); border: 1px solid rgba(109, 89, 255, .16); border-radius: 32px; background: var(--card-bg, #fff); box-shadow: 0 28px 80px rgba(41, 31, 104, .12); overflow: hidden; }
.eyebrow { display: inline-flex; padding: .45rem .8rem; border-radius: 999px; color: #6654db; background: rgba(102, 84, 219, .1); font-weight: 800; }
h1 { margin: 1.2rem 0 1rem; font-size: clamp(2.35rem, 6vw, 4.5rem); line-height: 1.05; color: var(--text-color, #1e1b35); }
.model-copy > p { max-width: 680px; font-size: 1.1rem; line-height: 1.9; color: var(--muted-color, #68657b); white-space: pre-line; }
.model-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 2rem; }
.primary-button, .secondary-button { display: inline-flex; align-items: center; justify-content: center; gap: .6rem; border: 0; border-radius: 14px; padding: .9rem 1.3rem; font-weight: 800; }
.primary-button { color: #fff; background: linear-gradient(135deg, #725cff, #4b35d1); box-shadow: 0 12px 28px rgba(84, 61, 220, .28); }
.primary-button:disabled { opacity: .65; cursor: wait; }
.secondary-button { color: #fff; background: #5b46da; }
.action-note { display: inline-flex; gap: .45rem; align-items: center; color: var(--muted-color, #747084); font-size: .9rem; }
.model-visual { min-height: 330px; display: grid; place-items: center; border-radius: 26px; background: linear-gradient(145deg, rgba(114, 92, 255, .14), rgba(81, 193, 255, .12)); overflow: hidden; }
.model-visual img { width: 100%; height: 100%; min-height: 330px; object-fit: cover; }
.model-placeholder { font-size: 6rem; color: #6a55e5; }
.state-card { min-height: 360px; display: grid; place-items: center; align-content: center; gap: 1rem; text-align: center; padding: 2rem; border-radius: 28px; background: var(--card-bg, #fff); }
.state-card > i { font-size: 2.5rem; color: #d04b62; }
.state-card h1 { margin: 0; font-size: 2rem; }
.state-card p { margin: 0; color: var(--muted-color, #68657b); }
@media (max-width: 800px) { .model-hero { grid-template-columns: 1fr; padding: 1.6rem; } .model-visual { min-height: 230px; order: -1; } .model-visual img { min-height: 230px; } }
</style>
