<template>
    <section class="tools-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="tools-container">
            <header class="tools-header">
                <span class="tools-badge">AI Tools</span>
                <h1>{{ isArabic ? "??????? ??????" : "Smart Tools" }}</h1>
                <p>{{ isArabic ? "???? ?????? ???????? ????? ?????." : "Pick a tool and start faster." }}</p>
            </header>

            <div v-if="loading" class="tools-grid">
                <article v-for="item in 6" :key="item" class="tool-card skeleton">
                    <div class="skeleton-box"></div>
                    <div class="skeleton-line line-lg"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line line-sm"></div>
                </article>
            </div>

            <div v-else-if="error" class="state-card error-state">
                <i class="bi bi-exclamation-triangle"></i>
                <h2>{{ isArabic ? "???? ????? ???????" : "Could not load tools" }}</h2>
                <button type="button" class="retry-btn" @click="fetchTools">
                    {{ isArabic ? "????? ????????" : "Retry" }}
                </button>
            </div>

            <div v-else-if="!tools.length" class="state-card empty-state">
                <i class="bi bi-grid"></i>
                <h2>{{ isArabic ? "?? ???? ????? ?????" : "No tools available" }}</h2>
            </div>

            <div v-else class="tools-grid">
                <article v-for="tool in tools" :key="tool.id" class="tool-card">
                    <div class="tool-visual">
                        <img v-if="tool.imageUrl" :src="tool.imageUrl" :alt="tool.title" />
                        <i v-else class="bi bi-stars"></i>
                    </div>

                    <div class="tool-body">
                        <div class="tool-top">
                            <h2>{{ tool.title }}</h2>
                            <span class="status-chip" :class="tool.is_active ? 'active' : 'inactive'">
                                {{ tool.is_active ? (isArabic ? "???" : "Active") : (isArabic ? "??? ???" : "Inactive") }}
                            </span>
                        </div>

                        <p class="tool-description">{{ tool.description || (isArabic ? "???? ???" : "No description") }}</p>

                        <div class="tool-footer">
                            <span class="tool-slug">{{ tool.slug }}</span>
                            <button type="button" class="show-btn" @click="openTool(tool.slug)">
                                {{ isArabic ? "??? ??????" : "Show" }}
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import homeService from "@/services/home/homeService";
import toolsService from "@/services/home/tools/toolServices";
import useSeoMeta from "@/composables/useSeoMeta";

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const loading = ref(true);
const error = ref(false);
const tools = ref([]);

const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar");

const seoTitle = computed(() => (isArabic.value ? "??????? ?????? | Ai Pro" : "Smart Tools | Ai Pro"));
const seoDescription = computed(() =>
    isArabic.value
        ? "???? ??????? ?????? ??????? ????? ?????? ?????? ?????."
        : "Browse available smart tools and open the right one quickly."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

const normalizeTool = (tool = {}) => {
    const translation = tool?.translation || {};

    return {
        id: tool.id,
        slug: tool.slug,
        title: translation.name || tool.name || "Untitled Tool",
        description: translation.description || tool.description || "",
        is_active: Boolean(tool.is_active),
        imageUrl: tool.image ? `/storage/${tool.image}` : "",
    };
};

const resolveToolsData = (payload) => {
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.tools)) return payload.tools;
    return [];
};

const fetchTools = async () => {
    locale.value = homeService.getLang();
    loading.value = true;
    error.value = false;

    try {
        const response = await toolsService.getTools();
        const rows = resolveToolsData(response?.data);
        tools.value = rows.map(normalizeTool);
    } catch {
        tools.value = [];
        error.value = true;
    } finally {
        loading.value = false;
    }
};

const openTool = (slug) => {
    router.push(`/${homeService.getLang()}/tool/${slug}`);
};

const handleLangChanged = async () => {
    locale.value = homeService.getLang();
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
    async (nextLang, prevLang) => {
        if (!nextLang || nextLang === prevLang) return;
        await fetchTools();
    }
);
</script>

<style scoped>
.tools-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at 15% 5%, rgba(43, 166, 222, 0.12), transparent 26%),
        radial-gradient(circle at 80% 12%, rgba(21, 70, 119, 0.1), transparent 24%),
        linear-gradient(180deg, #f8f9fb 0%, #f3f6fb 100%);
    padding: 128px 0 44px;
}

.tools-container {
    width: min(1220px, calc(100% - 28px));
    margin: 0 auto;
}

.tools-header {
    margin-bottom: 20px;
}

.tools-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 7px 14px;
    background: rgba(21, 70, 119, 0.09);
    border: 1px solid rgba(21, 70, 119, 0.16);
    color: #154677;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.13em;
    font-weight: 800;
}

.tools-header h1 {
    margin: 12px 0 6px;
    color: #154677;
    font-size: clamp(1.8rem, 2.8vw, 2.6rem);
    font-weight: 900;
}

.tools-header p {
    margin: 0;
    color: #5f7288;
    font-size: 15px;
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.tool-card {
    border-radius: 18px;
    border: 1px solid rgba(21, 70, 119, 0.12);
    background: linear-gradient(170deg, #ffffff, #f8fbff);
    box-shadow: 0 10px 24px rgba(21, 70, 119, 0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.tool-visual {
    height: 146px;
    background: linear-gradient(135deg, rgba(21, 70, 119, 0.12), rgba(43, 166, 222, 0.16));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #154677;
}

.tool-visual img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tool-visual i {
    font-size: 2rem;
}

.tool-body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tool-top {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    align-items: flex-start;
}

.tool-top h2 {
    margin: 0;
    font-size: 1rem;
    line-height: 1.5;
    color: #154677;
    font-weight: 800;
}

.status-chip {
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    padding: 5px 8px;
    white-space: nowrap;
}

.status-chip.active {
    background: #154677;
    color: #fff;
}

.status-chip.inactive {
    background: #e2e8f0;
    color: #154677;
}

.tool-description {
    margin: 0;
    color: #5f7288;
    font-size: 13px;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 42px;
}

.tool-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.tool-slug {
    font-size: 10px;
    text-transform: uppercase;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 55%;
}

.show-btn {
    border: none;
    border-radius: 999px;
    background: #154677;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    padding: 6px 11px;
    cursor: pointer;
}

.show-btn:hover {
    background: #2ba6de;
}

.state-card {
    border-radius: 16px;
    border: 1px dashed rgba(21, 70, 119, 0.22);
    background: rgba(255, 255, 255, 0.9);
    padding: 24px;
    text-align: center;
    color: #154677;
}

.state-card i {
    font-size: 1.4rem;
}

.state-card h2 {
    margin: 10px 0 0;
    font-size: 1rem;
    font-weight: 800;
}

.retry-btn {
    margin-top: 12px;
    border: none;
    border-radius: 999px;
    padding: 8px 14px;
    background: #154677;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.retry-btn:hover {
    background: #2ba6de;
}

.skeleton {
    padding: 14px;
    animation: pulse 1.3s infinite;
}

.skeleton-box {
    height: 120px;
    border-radius: 14px;
    background: #e5e7eb;
}

.skeleton-line {
    margin-top: 8px;
    height: 10px;
    border-radius: 999px;
    background: #e5e7eb;
}

.skeleton-line.line-lg {
    width: 75%;
}

.skeleton-line.line-sm {
    width: 52%;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.62;
    }
}

@media (max-width: 1024px) {
    .tools-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .tools-page {
        padding-top: 112px;
    }

    .tools-container {
        width: min(1220px, calc(100% - 16px));
    }

    .tools-grid {
        grid-template-columns: 1fr;
    }
}
/* =========================
   DARK MODE
========================= */
html[data-theme="dark"] .tools-page {
    background:
        radial-gradient(circle at 15% 5%, rgba(43, 166, 222, 0.06), transparent 26%),
        var(--theme-bg);
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .tools-header h1,
html[data-theme="dark"] .tool-top h2,
html[data-theme="dark"] .tool-slug,
html[data-theme="dark"] .state-card {
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .tools-header p,
html[data-theme="dark"] .tool-description {
    color: var(--theme-text-secondary);
}

html[data-theme="dark"] .tools-badge {
    background: rgba(43, 166, 222, 0.11);
    border-color: rgba(43, 166, 222, 0.24);
    color: var(--theme-accent);
}

html[data-theme="dark"] .tool-card,
html[data-theme="dark"] .state-card {
    background: var(--theme-surface);
    border-color: var(--theme-border);
    box-shadow: 0 18px 44px var(--theme-shadow);
}

html[data-theme="dark"] .tool-card:hover {
    background: var(--theme-surface-secondary);
    border-color: rgba(43, 166, 222, 0.34);
}

html[data-theme="dark"] .tool-visual {
    background: var(--theme-surface-secondary);
    color: var(--theme-accent);
}

html[data-theme="dark"] .status-chip.active {
    background: rgba(74, 222, 128, 0.10);
    color: #4ade80;
}

html[data-theme="dark"] .status-chip.inactive {
    background: rgba(248, 113, 113, 0.10);
    color: #f87171;
}

html[data-theme="dark"] .skeleton-box,
html[data-theme="dark"] .skeleton-line {
    background: var(--theme-surface-elevated);
}
</style>
