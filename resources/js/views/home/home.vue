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
                        <div class="skeleton-image"></div>
                        <div class="skeleton-line skeleton-line-lg"></div>
                        <div class="skeleton-line"></div>
                        <div class="skeleton-line skeleton-line-sm"></div>
                    </div>
                </div>

                <!-- TOOLS -->
                <TransitionGroup v-else-if="tools.length" :key="listKey" name="tool-card" tag="div" class="tools-layout">
                    <article v-for="(tool, index) in tools" :key="tool.id" class="tool-card group cursor-pointer" role="button"
                        tabindex="0" :aria-label="t('user.home.openAria', { name: tool.title || tool.slug })" @click="goToTool(tool.slug)"
                        @keyup.enter="goToTool(tool.slug)" @keyup.space.prevent="goToTool(tool.slug)">
                        <!-- IMAGE -->
                        <div class="relative overflow-hidden rounded-2xl">
                            <img v-if="tool.imageUrl" :src="tool.optimizedImageUrl || tool.imageUrl"
                                :alt="tool.title ? t('user.home.coverAltWithName', { name: tool.title }) : t('user.home.coverAlt')" class="tool-image"
                                :loading="index <= 1 ? 'eager' : 'lazy'" :fetchpriority="index === 0 ? 'high' : 'auto'"
                                decoding="async" width="640" height="160" @error="onToolImageError($event, tool.imageUrl)" />

                            <div v-else class="tool-image tool-image-fallback">
                                <i class="bi bi-grid-3x3-gap-fill text-2xl"></i>
                            </div>

                            <!-- BADGE -->
                            <div class="tool-overlay">
                                <span class="tool-chip" :class="tool.is_active ? 'active' : 'inactive'">
                                    {{ tool.is_active ? t("user.home.statusActive") : t("user.home.statusInactive") }}
                                </span>
                            </div>
                        </div>

                        <!-- CONTENT -->
                        <div class="mt-2.5">
                            <h2 class="text-lg font-bold text-black">
                                {{ tool.title }}
                            </h2>

                            <p class="mt-2 text-sm text-neutral-600">
                                {{ tool.description }}
                            </p>

                            <div class="mt-2.5 flex items-center justify-between">
                                <span class="text-xs text-neutral-500 uppercase">
                                    {{ tool.slug }}
                                </span>

                                <button type="button" class="show-btn" :aria-label="t('user.home.showAria', { name: tool.title || tool.slug })"
                                    @click.stop="goToTool(tool.slug)">
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
const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar");
const seoTitle = computed(() => (isArabic.value ? "الرئيسية | أدوات الذكاء الاصطناعي | Ai Pro" : "Home | AI Tools Directory | Ai Pro"));
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
    const t = tool?.translation;
    const imageUrl = tool.image ? `/storage/${tool.image}` : "";
    const optimizedImageUrl = imageUrl.replace(/\.(png|jpe?g)$/i, ".webp");

    return {
        id: tool.id,
        slug: tool.slug,
        imageUrl,
        optimizedImageUrl,
        is_active: tool.is_active,
        sort_order: tool.sort_order,
        title: t?.name,
        description: t?.description,
    };
};

const onToolImageError = (event, fallbackUrl) => {
    if (event?.target?.src !== fallbackUrl) {
        event.target.src = fallbackUrl;
    }
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

/* LAYOUT: Flexbox مع توسيط تلقائي وحد أقصى 4 */
.tools-layout {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
}

/* CARD (CLEAN WHITE PREMIUM) */
.tool-card {
    flex: 1 0 calc(50% - 10px);
    max-width: calc(50% - 10px);
    background: #ffffff;
    border-radius: 16px;
    padding: 10px;
    color: #154677;
    border: 1px solid rgba(0, 0, 0, 0.06);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
}

.tool-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
}

/* IMAGE */
.tool-image {
    width: 100%;
    height: 10rem;
    object-fit: cover;
    border-radius: 12px;
}

.tool-image-fallback {
    height: 10rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f3f5;
    border-radius: 12px;
    color: #154677;
}

/* OVERLAY TAGS */
.tool-overlay {
    position: absolute;
    top: 8px;
    left: 8px;
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

/* TITLE */
.tool-card h2 {
    color: #154677;
    font-weight: 700;
    margin: 0;
}

/* DESCRIPTION */
.tool-card p {
    color: #6b7280;
    margin: 8px 0 0;
    line-height: 1.4;
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

.skeleton-image {
    height: 10rem;
    background: #e5e7eb;
    border-radius: 12px;
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
</style>
