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
            <aside class="chat-sidebar">
                <div class="model-card">
                    <img v-if="conversation.model?.image_url" :src="conversation.model.image_url" :alt="conversation.model.name" />
                    <span v-else class="model-icon"><i class="bi bi-stars"></i></span>
                    <div>
                        <small>{{ t("freeAiModels.model") }}</small>
                        <strong>{{ conversation.model?.name }}</strong>
                    </div>
                </div>

                <button type="button" class="new-chat-button" :disabled="creatingConversation" @click="newConversation">
                    <span v-if="creatingConversation" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <i v-else class="bi bi-plus-lg"></i>
                    {{ creatingConversation ? t("freeAiModels.creatingConversation") : t("freeAiModels.newConversation") }}
                </button>

                <div class="conversation-info">
                    <span>{{ t("freeAiModels.signedInAs") }}</span>
                    <strong>{{ conversation.user?.name }}</strong>
                    <span>{{ t("freeAiModels.conversationId") }}</span>
                    <code>{{ shortUuid }}</code>
                </div>
            </aside>

            <main class="chat-workspace">
                <header class="chat-header">
                    <div>
                        <span class="online-dot"></span>
                        <strong>{{ conversation.model?.name }}</strong>
                    </div>
                    <button type="button" class="icon-button" :aria-label="t('freeAiModels.backToModel')" @click="backToModel">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                </header>

                <div class="empty-chat">
                    <span class="empty-icon"><i class="bi bi-chat-square-text"></i></span>
                    <h1>{{ t("freeAiModels.emptyChatTitle", { name: conversation.model?.name }) }}</h1>
                    <p>{{ t("freeAiModels.emptyChatDescription") }}</p>
                    <span class="coming-soon"><i class="bi bi-clock-history"></i>{{ t("freeAiModels.integrationPending") }}</span>
                </div>

                <footer class="composer">
                    <textarea :placeholder="t('freeAiModels.inputPlaceholder')" disabled rows="1"></textarea>
                    <button type="button" disabled :aria-label="t('freeAiModels.send')"><i class="bi bi-send-fill"></i></button>
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

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const conversation = ref({});
const loading = ref(true);
const loadError = ref(false);
const creatingConversation = ref(false);

const shortUuid = computed(() => String(conversation.value.uuid || "").slice(0, 12));
const seoTitle = computed(() => conversation.value.model?.meta_title || conversation.value.model?.name || "AI Pro");
const seoDescription = computed(() => conversation.value.model?.meta_description || conversation.value.model?.description || "");
const seoKeywords = computed(() => conversation.value.model?.seo_keywords || "");

useSeoMeta({ title: seoTitle, description: seoDescription, keywords: seoKeywords });

async function loadConversation() {
    loading.value = true;
    loadError.value = false;

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
    router.push({
        name: "free-ai-model.show",
        params: { lang: homeService.getLang(), slug: route.params.slug },
    });
}

watch(
    () => [route.params.slug, route.params.uuid],
    loadConversation,
    { immediate: true }
);
</script>

<style scoped>
.free-chat-page { min-height: calc(100vh - 70px); padding: 1rem; background: #f3f4fa; }
.chat-shell { max-width: 1440px; min-height: calc(100vh - 102px); margin: 0 auto; display: grid; grid-template-columns: 300px minmax(0, 1fr); border: 1px solid rgba(68, 54, 150, .12); border-radius: 24px; overflow: hidden; background: #fff; box-shadow: 0 18px 60px rgba(40, 32, 90, .1); }
.chat-sidebar { display: flex; flex-direction: column; gap: 1.25rem; padding: 1.25rem; background: #211b3d; color: #fff; }
.model-card { display: flex; align-items: center; gap: .8rem; padding-bottom: 1.2rem; border-bottom: 1px solid rgba(255,255,255,.12); }
.model-card img, .model-icon { width: 48px; height: 48px; border-radius: 14px; object-fit: cover; }
.model-icon { display: grid; place-items: center; background: rgba(255,255,255,.12); color: #bdb3ff; font-size: 1.35rem; }
.model-card div { display: grid; min-width: 0; }
.model-card small { color: #aaa3c4; }
.model-card strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.new-chat-button, .primary-button { display: inline-flex; justify-content: center; align-items: center; gap: .55rem; border: 0; border-radius: 12px; padding: .85rem 1rem; color: #fff; background: linear-gradient(135deg, #755fff, #5841dc); font-weight: 800; }
.new-chat-button:disabled { opacity: .65; }
.conversation-info { display: grid; gap: .35rem; margin-top: auto; padding: 1rem; border-radius: 14px; background: rgba(255,255,255,.07); }
.conversation-info span { color: #aaa3c4; font-size: .75rem; }
.conversation-info code { color: #c8c0ff; direction: ltr; text-align: start; }
.chat-workspace { min-width: 0; display: grid; grid-template-rows: auto 1fr auto; }
.chat-header { min-height: 72px; display: flex; justify-content: space-between; align-items: center; padding: 0 1.4rem; border-bottom: 1px solid #ecebf3; }
.chat-header > div { display: flex; align-items: center; gap: .6rem; }
.online-dot { width: 9px; height: 9px; border-radius: 50%; background: #29bd7b; box-shadow: 0 0 0 5px rgba(41,189,123,.12); }
.icon-button { width: 40px; height: 40px; border: 1px solid #e5e3ef; border-radius: 12px; background: #fff; }
[dir="rtl"] .icon-button i { display: inline-block; transform: rotate(180deg); }
.empty-chat { align-self: center; justify-self: center; max-width: 600px; padding: 3rem 1.5rem; text-align: center; }
.empty-icon { width: 78px; height: 78px; display: grid; place-items: center; margin: 0 auto 1.25rem; border-radius: 24px; color: #654fde; background: rgba(101,79,222,.1); font-size: 2rem; }
.empty-chat h1 { color: #242039; font-size: clamp(1.7rem, 4vw, 2.5rem); }
.empty-chat p { color: #777286; line-height: 1.8; }
.coming-soon { display: inline-flex; align-items: center; gap: .45rem; margin-top: .8rem; padding: .5rem .8rem; border-radius: 999px; color: #6553c4; background: #f0edff; font-size: .86rem; font-weight: 700; }
.composer { display: flex; gap: .75rem; padding: 1rem 1.25rem; border-top: 1px solid #ecebf3; background: #fbfbfe; }
.composer textarea { flex: 1; resize: none; border: 1px solid #dedce9; border-radius: 14px; padding: .9rem 1rem; background: #f0eff5; }
.composer button { width: 48px; border: 0; border-radius: 14px; color: #fff; background: #9b96b8; }
.chat-state { min-height: 70vh; display: grid; place-items: center; align-content: center; gap: 1rem; text-align: center; }
.error-state > i { font-size: 3rem; color: #c7435b; }
.error-state h1, .error-state p { margin: 0; }
.error-state p { color: #706b7e; }
@media (max-width: 780px) { .free-chat-page { padding: 0; } .chat-shell { min-height: calc(100vh - 70px); grid-template-columns: 1fr; border-radius: 0; } .chat-sidebar { display: grid; grid-template-columns: 1fr auto; } .conversation-info { display: none; } .chat-workspace { min-height: 70vh; } }
</style>
