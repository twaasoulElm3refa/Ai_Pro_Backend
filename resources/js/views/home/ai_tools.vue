<template>
    <section class="ai-tools-page" :dir="isArabic ? 'rtl' : 'ltr'">
        <div class="ai-tools-shell">
            <header class="ai-tools-header">
                <span class="ai-tools-badge">{{ isArabic ? "أدوات مجانية" : "Free AI Tools" }}</span>
                <h1>{{ isArabic ? "اختر أداة الذكاء الاصطناعي" : "Choose Your AI Tool" }}</h1>
                <p>{{ isArabic ? "ابدأ من الأداة المناسبة ثم اختر النموذج والإعدادات." : "Browse available tools, then open the right workspace." }}</p>
            </header>

            <div v-if="loading" class="ai-tools-grid" aria-live="polite">
                <article v-for="item in 8" :key="item" class="ai-tool-card skeleton-card">
                    <div class="skeleton-media"></div>
                    <div class="skeleton-body">
                        <span class="skeleton-line line-title"></span>
                        <span class="skeleton-line"></span>
                        <span class="skeleton-line line-short"></span>
                    </div>
                </article>
            </div>

            <div v-else-if="error" class="tools-state error-state" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <h2>{{ isArabic ? "تعذر تحميل الأدوات" : "Could not load tools" }}</h2>
                <p>{{ isArabic ? "حدث خطأ أثناء جلب أدوات الذكاء الاصطناعي." : "Something went wrong while fetching the AI tools." }}</p>
                <button type="button" class="state-button" @click="fetchTools">
                    <i class="bi bi-arrow-clockwise"></i>
                    {{ isArabic ? "إعادة المحاولة" : "Retry" }}
                </button>
            </div>

            <div v-else-if="!tools.length" class="tools-state empty-state">
                <i class="bi bi-grid-3x3-gap"></i>
                <h2>{{ isArabic ? "لا توجد أدوات حالياً" : "No tools available" }}</h2>
                <p>{{ isArabic ? "ستظهر الأدوات هنا عند إضافتها." : "Tools will appear here when they are available." }}</p>
            </div>

            <div v-else class="ai-tools-grid">
                <button
                    v-for="tool in tools"
                    :key="tool.id || tool.slug"
                    type="button"
                    class="ai-tool-card"
                    @click="openTool(tool)"
                >
                    <span class="tool-image-wrap">
                        <img :src="defaultToolImage" :alt="tool.name" loading="lazy" />
                        <span class="tool-icon" aria-hidden="true">
                            <i class="bi bi-stars"></i>
                        </span>
                    </span>

                    <span class="tool-content">
                        <span class="tool-title-row">
                            <span class="tool-title">{{ tool.name }}</span>
                            <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                        </span>
                        <span class="tool-description">{{ tool.description || emptyDescription }}</span>
                    </span>
                </button>
            </div>
        </div>
    </section>
</template>


<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import homeService from "@/services/home/homeService";
import toolServices from "@/services/home/AiModels/toolServices";
import useSeoMeta from "@/composables/useSeoMeta";

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const loading = ref(true);
const error = ref(false);
const tools = ref([]);

const defaultToolImage = "/images/default_tool.webp";
const isArabic = computed(() => String(locale.value || homeService.getLang()).toLowerCase() === "ar");
const emptyDescription = computed(() => (isArabic.value ? "لا يوجد وصف متاح لهذه الأداة حالياً." : "No description is available for this tool yet."));

useSeoMeta({
    title: computed(() => (isArabic.value ? "أدوات الذكاء الاصطناعي | Ai Pro" : "AI Tools | Ai Pro")),
    description: computed(() =>
        isArabic.value
            ? "استعرض أدوات الذكاء الاصطناعي المجانية المتاحة في Ai Pro."
            : "Browse the free AI tools available in Ai Pro."
    ),
});

const normalizeTool = (tool = {}) => {
    const translation = tool.translation || {};

    return {
        id: tool.id,
        slug: tool.slug,
        name: translation.name || "AI Tool",
        description: translation.description || "",
    };
};

const resolveToolsData = (payload) => {
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload)) return payload;
    return [];
};

const fetchTools = async () => {
    locale.value = homeService.getLang();
    loading.value = true;
    error.value = false;

    try {
        const response = await toolServices.getAiTools();
        tools.value = resolveToolsData(response).map(normalizeTool).filter((tool) => tool.slug);
    } catch {
        tools.value = [];
        error.value = true;
    } finally {
        loading.value = false;
    }
};

const openTool = async (tool) => {
    if (!tool?.slug) return;

    await router.push({
        name: "free-ai-model.show",
        params: {
            lang: homeService.getLang(),
            slug: tool.slug,
        },
    });
};

const handleLangChanged = async (event) => {
    locale.value = event?.detail?.lang || homeService.getLang();
    await fetchTools();
};

onMounted(async () => {
    await fetchTools();
    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => route.params.lang,
    async (nextLang, previousLang) => {
        if (!nextLang || nextLang === previousLang) return;
        await fetchTools();
    }
);
</script>

<style scoped>
.ai-tools-page {
    min-height: 100vh;
    padding: 124px 16px 64px;
    color: var(--theme-text-primary);
    background:
        radial-gradient(circle at 12% 8%, rgba(43, 166, 222, 0.13), transparent 30%),
        radial-gradient(circle at 88% 4%, rgba(21, 70, 119, 0.1), transparent 26%),
        var(--theme-bg);
}

.ai-tools-shell {
    width: min(1240px, 100%);
    margin: 0 auto;
}

.ai-tools-header {
    max-width: 760px;
    margin-bottom: 24px;
}

.ai-tools-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 12px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    color: var(--theme-accent);
    background: var(--theme-surface-secondary);
    font-size: 12px;
    font-weight: 800;
}

.ai-tools-header h1 {
    margin: 14px 0 10px;
    color: var(--theme-text-primary);
    font-size: clamp(2rem, 4vw, 3.5rem);
    font-weight: 900;
    line-height: 1.08;
}

.ai-tools-header p {
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 16px;
    line-height: 1.8;
}

.ai-tools-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.ai-tool-card {
    min-width: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 0;
    border: 1px solid var(--theme-border);
    border-radius: 18px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 18px 44px var(--theme-shadow);
    text-align: start;
    cursor: pointer;
    transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.ai-tool-card:hover,
.ai-tool-card:focus-visible {
    border-color: rgba(43, 166, 222, 0.42);
    outline: 0;
    transform: translateY(-3px);
    box-shadow: 0 24px 56px rgba(21, 70, 119, 0.14);
}

.tool-image-wrap {
    position: relative;
    display: block;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background:
        radial-gradient(circle at 80% 25%, rgba(98, 200, 240, 0.28), transparent 34%),
        linear-gradient(135deg, #154677, #2ba6de);
}

.tool-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.84;
    transition: transform 0.24s ease;
}

.ai-tool-card:hover .tool-image-wrap img,
.ai-tool-card:focus-visible .tool-image-wrap img {
    transform: scale(1.04);
}

.tool-icon {
    position: absolute;
    inset-inline-start: 14px;
    bottom: 14px;
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 14px;
    color: #ffffff;
    background: rgba(21, 70, 119, 0.62);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18);
}

.tool-content {
    display: grid;
    gap: 10px;
    padding: 16px;
}

.tool-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.tool-title {
    min-width: 0;
    color: var(--theme-text-primary);
    font-size: 17px;
    font-weight: 900;
    line-height: 1.45;
}

.tool-title-row i {
    flex: 0 0 auto;
    margin-top: 2px;
    color: var(--theme-accent);
    font-size: 22px;
}

[dir="rtl"] .tool-title-row i {
    transform: rotate(180deg);
}

.tool-description {
    min-height: 50px;
    color: var(--theme-text-secondary);
    font-size: 13px;
    line-height: 1.65;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.tools-state {
    min-height: 340px;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 12px;
    padding: 32px 18px;
    border: 1px solid var(--theme-border);
    border-radius: 22px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 18px 44px var(--theme-shadow);
    text-align: center;
}

.tools-state > i {
    color: var(--theme-accent);
    font-size: 34px;
}

.error-state > i {
    color: var(--app-danger);
}

.tools-state h2,
.tools-state p {
    margin: 0;
}

.tools-state h2 {
    font-size: 20px;
    font-weight: 900;
}

.tools-state p {
    color: var(--theme-text-secondary);
    font-size: 14px;
}

.state-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    margin-top: 4px;
    padding: 10px 16px;
    border: 0;
    border-radius: 14px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-weight: 800;
    box-shadow: 0 11px 25px rgba(21, 70, 119, 0.2);
    cursor: pointer;
}

.skeleton-card {
    cursor: wait;
    pointer-events: none;
}

.skeleton-media,
.skeleton-line {
    position: relative;
    overflow: hidden;
    background: var(--theme-surface-secondary);
}

.skeleton-media {
    aspect-ratio: 16 / 9;
}

.skeleton-body {
    display: grid;
    gap: 10px;
    padding: 16px;
}

.skeleton-line {
    display: block;
    height: 11px;
    border-radius: 999px;
}

.line-title {
    width: 70%;
    height: 15px;
}

.line-short {
    width: 54%;
}

.skeleton-media::after,
.skeleton-line::after {
    position: absolute;
    inset: 0;
    content: "";
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.45), transparent);
    animation: shimmer 1.45s infinite;
}

html[data-theme="dark"] .skeleton-media::after,
html[data-theme="dark"] .skeleton-line::after {
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.08), transparent);
}

@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}

@media (max-width: 1180px) {
    .ai-tools-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .ai-tools-page {
        padding-top: 108px;
    }

    .ai-tools-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .ai-tools-page {
        padding: 96px 10px 38px;
    }

    .ai-tools-header {
        margin-bottom: 18px;
    }

    .ai-tools-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .tool-content {
        padding: 14px;
    }
}
</style>
