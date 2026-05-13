<template>
    <section class="home-tools-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="container pl-2.5 pr-2.5">

            <!-- HEADER -->
            <div class="text-left">
                <span
                    class="inline-flex items-center rounded-full border border-black/10 bg-black px-4 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-white backdrop-blur-xl">
                    {{ t("user.home.badge") }}
                </span>

                <h1 class="mt-2 text-3xl font-bold tracking-tight text-black sm:text-4xl">
                    {{ t("user.home.title") }}
                </h1>

                <p class="mt-2 text-sm leading-6 text-neutral-600 sm:text-base">
                    {{ t("user.home.subtitle") }}
                </p>
            </div>

            <!-- CONTENT -->
            <div class="mt-2.5">

                <!-- LOADING -->
                <div v-if="loading" class="tools-layout">
                    <div v-for="item in skeletonCount" :key="item" class="tool-card tool-skeleton">
                        <div class="skeleton-icon"></div>
                        <div class="skeleton-line skeleton-line-lg"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line skeleton-line-sm"></div>
                    </div>
                </div>

                <!-- TOOLS -->
                <TransitionGroup
                    v-else-if="tools.length"
                    :key="listKey"
                    name="tool-card"
                    tag="div"
                    class="tools-layout"
                >
                    <article
                        v-for="tool in tools"
                        :key="tool.id"
                        class="tool-card group cursor-pointer"
                        role="button"
                        tabindex="0"
                        :aria-label="t('user.home.openAria', { name: tool.title || tool.slug })"
                        @click="goToTool(tool.slug)"
                        @keyup.enter="goToTool(tool.slug)"
                        @keyup.space.prevent="goToTool(tool.slug)"
                    >
                        <!-- STATUS BADGE -->
                        <div class="tool-overlay">
                            <span class="tool-chip" :class="tool.is_active ? 'active' : 'inactive'">
                                {{ tool.is_active ? t("user.home.statusActive") : t("user.home.statusInactive") }}
                            </span>
                        </div>

                        <!-- ICON CENTER -->
                        <div class="tool-icon-wrapper">
                            <div class="tool-icon-bg"></div>

                            <div class="tool-icon-card">
                                <div class="tool-icon-glow"></div>

                                <div class="tool-mini-icons">
                                    <span class="mini-icon mini-icon-1">
                                        <i class="bi bi-magic"></i>
                                    </span>

                                    <span class="mini-icon mini-icon-2">
                                        <i class="bi bi-file-text"></i>
                                    </span>

                                    <span class="mini-icon mini-icon-3">
                                        <i class="bi bi-tools"></i>
                                    </span>
                                </div>

                                <div class="tool-icon-main">
                                    <i class="bi bi-pencil-square"></i>
                                </div>
                            </div>
                        </div>

                        <!-- CONTENT -->
                        <div class="tool-content">
                            <h2 class="tool-title">
                                {{ tool.title }}
                            </h2>

                            <p class="tool-description">
                                {{ tool.description }}
                            </p>

                            <div class="tool-footer">
                                <span class="tool-slug">
                                    {{ tool.slug }}
                                </span>

                                <button
                                    type="button"
                                    class="show-btn"
                                    :aria-label="t('user.home.showAria', { name: tool.title || tool.slug })"
                                    @click.stop="goToTool(tool.slug)"
                                >
                                    {{ t("user.home.showButton") }}
                                </button>
                            </div>
                        </div>
                    </article>
                </TransitionGroup>

                <!-- EMPTY -->
                <div v-else class="empty-state mt-2.5 text-left">
                    <h2 class="text-lg font-semibold text-black">
                        {{ t("user.home.empty") }}
                    </h2>
                </div>

            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import homeService from "@/services/home/homeService";
import useSeoMeta from "@/composables/useSeoMeta";

const router = useRouter();
const route = useRoute();
const { t, locale } = useI18n();

const loading = ref(true);
const tools = ref([]);
const skeletonCount = 4;

const listKey = computed(() => `${homeService.getLang()}-${tools.value.length}`);

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar"
);

const seoTitle = computed(() =>
    isArabic.value
        ? "الرئيسية | أدوات الذكاء الاصطناعي | Ai Pro"
        : "Home | AI Tools Directory | Ai Pro"
);

const seoDescription = computed(() =>
    isArabic.value
        ? "اكتشف أدوات الذكاء الاصطناعي للعمل بتركيز، قارن المزايا بسرعة، وافتح كل أداة بسهولة لبدء المحادثات والمهام وإنجاز سير عمل أكثر إنتاجية."
        : "Explore AI tools curated for focused work, compare capabilities, and open each experience quickly to start productive chats, tasks, and smart workflows."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

const normalizeTool = (tool) => {
    const translation = tool?.translation;

    return {
        id: tool.id,
        slug: tool.slug,
        is_active: tool.is_active,
        sort_order: tool.sort_order,
        title: translation?.name,
        description: translation?.description,
    };
};

const fetchTools = async () => {
    locale.value = homeService.getLang();
    loading.value = true;

    try {
        const res = await homeService.fetchTools();
        const data = res?.data || [];

        tools.value = data.map(normalizeTool);
    } catch (e) {
        tools.value = [];
    } finally {
        loading.value = false;
    }
};

const goToTool = (slug) => {
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
.home-tools-page {
    margin-top: 2%;
    background: #f8f9fb;
    padding: 10px 0;
    min-height: 85vh;
    box-sizing: border-box;
}

/* LAYOUT */
.tools-layout {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
}

/* CARD */
.tool-card {
    flex: 1 0 calc(50% - 10px);
    max-width: calc(50% - 10px);
    min-height: 270px;
    background:
        radial-gradient(circle at 50% 22%, rgba(21, 70, 119, 0.08), transparent 34%),
        linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
    border-radius: 18px;
    padding: 16px;
    color: #154677;
    border: 1px solid rgba(0, 0, 0, 0.06);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.tool-card::before {
    content: "";
    position: absolute;
    inset: -80px auto auto -80px;
    width: 170px;
    height: 170px;
    border-radius: 999px;
    background: rgba(21, 70, 119, 0.08);
    filter: blur(4px);
}

.tool-card::after {
    content: "";
    position: absolute;
    right: -70px;
    bottom: -80px;
    width: 180px;
    height: 180px;
    border-radius: 999px;
    background: rgba(59, 130, 246, 0.08);
    filter: blur(8px);
}

.tool-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
}

/* STATUS BADGE */
.tool-overlay {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 5;
    display: flex;
    gap: 6px;
}

.tool-chip {
    background: #154677;
    color: #fff;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: 0.3px;
    backdrop-filter: blur(10px);
}

.tool-chip.inactive {
    background: #e5e7eb;
    color: #154677;
}

/* ICON CENTER */
.tool-icon-wrapper {
    position: relative;
    z-index: 2;
    width: 100%;
    min-height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 8px;
}

.tool-icon-bg {
    position: absolute;
    width: 138px;
    height: 138px;
    border-radius: 999px;
    background:
        radial-gradient(circle, rgba(21, 70, 119, 0.13), transparent 68%);
}

.tool-icon-card {
    position: relative;
    width: 126px;
    height: 126px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tool-icon-glow {
    position: absolute;
    width: 92px;
    height: 92px;
    border-radius: 999px;
    background: rgba(21, 70, 119, 0.14);
    filter: blur(18px);
}

.tool-icon-main {
    position: relative;
    z-index: 3;
    width: 78px;
    height: 78px;
    border-radius: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #154677;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow:
        0 18px 38px rgba(21, 70, 119, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.tool-icon-main i {
    font-size: 35px;
}

.tool-mini-icons {
    position: absolute;
    inset: 0;
    z-index: 4;
    pointer-events: none;
}

.mini-icon {
    position: absolute;
    width: 34px;
    height: 34px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #154677;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.13);
}

.mini-icon i {
    font-size: 15px;
}

.mini-icon-1 {
    top: 8px;
    right: 10px;
}

.mini-icon-2 {
    bottom: 9px;
    left: 8px;
}

.mini-icon-3 {
    bottom: 7px;
    right: 11px;
}

/* CONTENT */
.tool-content {
    position: relative;
    z-index: 2;
    width: 100%;
    margin-top: 8px;
}

.tool-title {
    color: #154677;
    font-size: 18px;
    font-weight: 800;
    margin: 0;
}

.tool-description {
    color: #6b7280;
    margin: 8px auto 0;
    line-height: 1.5;
    font-size: 14px;
    max-width: 90%;
}

.tool-footer {
    margin-top: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.tool-slug {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    max-width: 55%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* SHOW BUTTON */
.show-btn {
    background: #154677;
    color: #fff;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.25s ease;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
}

.show-btn:hover {
    transform: translateY(-2px);
    background: #000;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

/* SKELETON */
.tool-skeleton {
    animation: pulse 1.5s infinite;
}

.skeleton-icon {
    width: 126px;
    height: 126px;
    background: #e5e7eb;
    border-radius: 32px;
    margin: 10px auto 0;
}

.skeleton-line {
    height: 12px;
    background: #e5e7eb;
    border-radius: 6px;
    margin-top: 8px;
    width: 100%;
}

.skeleton-line-lg {
    width: 75%;
}

.skeleton-line-sm {
    width: 50%;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.6;
    }
}

/* ANIMATION */
.tool-card-enter-active {
    transition: all 0.3s ease;
}

.tool-card-enter-from {
    opacity: 0;
    transform: translateY(15px);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .tool-card {
        flex: 1 0 100%;
        max-width: 100%;
    }

    .tool-footer {
        flex-direction: column;
        justify-content: center;
    }

    .tool-slug {
        max-width: 100%;
    }
}
</style>
