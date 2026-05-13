<template>
    <section class="ai-overlap-section" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="ai-premium-container">

            <!-- TOP BADGES -->
            <div class="ai-container-badges">
                <span class="ai-main-badge">
                    AI PLATFORM
                </span>

                <span class="ai-soft-badge">
                    <span class="ai-dot"></span>
                    Smart Writing
                </span>
            </div>

            <!-- CONTENT GRID -->
            <div class="ai-container-grid">

                <!-- LEFT CONTENT -->
                <div class="ai-container-content">
                    <div class="ai-brand-line">
                        <div class="ai-mini-logo">
                            <img
                                src="/images/Ai_logo.png"
                                alt="AiPro Logo"
                                width="42"
                                height="42"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>

                        <span>PREMIUM AI PLATFORM</span>
                    </div>

                    <h1 class="ai-container-title">
                        أدوات ذكية لإنجاز أسرع
                    </h1>

                    <p class="ai-container-desc">
                        اكتب، لخص، وحرّر محتواك من مكان واحد بتجربة بسيطة وسريعة.
                    </p>

                    <div class="ai-info-cards">
                        <div class="ai-info-card">
                            <h3>كتابة أذكى</h3>
                            <p>أنشئ محتوى واضحًا ومنظمًا خلال ثوانٍ.</p>
                        </div>

                        <div class="ai-info-card">
                            <h3>تجربة سهلة</h3>
                            <p>اختر الأداة المناسبة وابدأ مباشرة بدون تعقيد.</p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT VISUAL -->
                <div class="ai-container-visual">
                    <div class="ai-feature-chip">
                        <div class="ai-feature-icon">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div>
                            <strong>AI Writer</strong>
                            <span>Smart Content Generation</span>
                        </div>
                    </div>

                    <div class="ai-preview-card">
                        <div class="ai-preview-overlay"></div>

                        <div class="ai-preview-content">
                            <span>AiPro</span>
                            <h2>Smart Writing Assistant</h2>
                            <p>Generate, refine and summarize content with AI.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="home-tools-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="home-shell">

            <!-- TOOLS PANEL -->
            <div class="tools-panel">

                <!-- HEADER -->
                <div class="tools-panel-header">
                    <span class="tools-eyebrow">
                        <i class="bi bi-stars"></i>
                        {{ t("user.home.badge") }}
                    </span>

                    <div class="tools-title-row">
                        <div>
                            <h2 class="tools-title">
                                {{ t("user.home.title") }}
                            </h2>

                            <p class="tools-subtitle">
                                {{ t("user.home.subtitle") }}
                            </p>
                        </div>

                        <div class="tools-count" v-if="!loading && tools.length">
                            <strong>{{ tools.length }}</strong>
                            <span>Tools</span>
                        </div>
                    </div>
                </div>

                <!-- LOADING -->
                <div v-if="loading" class="tools-layout">
                    <div v-for="item in skeletonCount" :key="item" class="tool-card tool-skeleton">
                        <div class="skeleton-icon"></div>
                        <div class="skeleton-line skeleton-line-lg"></div>
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
                        class="tool-card"
                        role="button"
                        tabindex="0"
                        :aria-label="t('user.home.openAria', { name: tool.title || tool.slug })"
                        @click="goToTool(tool.slug)"
                        @keyup.enter="goToTool(tool.slug)"
                        @keyup.space.prevent="goToTool(tool.slug)"
                    >
                        <div class="tool-status" :class="tool.is_active ? 'active' : 'inactive'">
                            {{ tool.is_active ? t("user.home.statusActive") : t("user.home.statusInactive") }}
                        </div>

                        <div class="tool-icon-wrap">
                            <div class="tool-icon">
                                <i class="bi bi-pencil-square"></i>
                            </div>

                            <span class="tool-spark spark-one">
                                <i class="bi bi-magic"></i>
                            </span>

                            <span class="tool-spark spark-two">
                                <i class="bi bi-file-text"></i>
                            </span>
                        </div>

                        <div class="tool-body">
                            <h3 class="tool-title">
                                {{ tool.title }}
                            </h3>

                            <p class="tool-description">
                                {{ tool.description }}
                            </p>
                        </div>

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
                                <i class="bi bi-arrow-right-short"></i>
                            </button>
                        </div>
                    </article>
                </TransitionGroup>

                <!-- EMPTY -->
                <div v-else class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>

                    <h2>
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
/* =========================
   INTRO OVERLAP SECTION
========================= */
.ai-overlap-section {
    position: relative;
    z-index: 20;
    margin-top: -92px;
    padding: 0 18px 46px;
}

.ai-premium-container {
    width: min(1580px, calc(100% - 24px));
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 34px;
    padding: 50px 54px;
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow:
        0 -10px 40px rgba(21, 70, 119, 0.08),
        0 28px 70px rgba(21, 70, 119, 0.12);
    position: relative;
    overflow: hidden;
}

.ai-premium-container::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    padding: 1px;
    background: linear-gradient(
        135deg,
        rgba(21, 70, 119, 0.28),
        rgba(43, 166, 222, 0.22),
        rgba(21, 70, 119, 0.08)
    );
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.ai-premium-container::after {
    content: "";
    position: absolute;
    width: 360px;
    height: 360px;
    inset-inline-end: -140px;
    top: -160px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.13), transparent 68%);
    pointer-events: none;
}

.ai-container-badges {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-bottom: 30px;
    position: relative;
    z-index: 2;
}

.ai-main-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 20px;
    border-radius: 999px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
}

.ai-soft-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 0 18px;
    border-radius: 999px;
    color: #154677;
    background: rgba(43, 166, 222, 0.08);
    border: 1px solid rgba(43, 166, 222, 0.18);
    font-size: 13px;
    font-weight: 800;
}

.ai-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #2ba6de;
    box-shadow: 0 0 0 5px rgba(43, 166, 222, 0.14);
}

.ai-container-grid {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1.08fr 0.92fr;
    gap: 46px;
    align-items: center;
}

.ai-brand-line {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
}

.ai-mini-logo {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 14px 26px rgba(21, 70, 119, 0.12);
}

.ai-mini-logo img {
    width: 42px;
    height: 42px;
    object-fit: contain;
}

.ai-brand-line span {
    color: #2ba6de;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.20em;
}

.ai-container-title {
    margin: 0;
    color: #154677;
    font-size: clamp(34px, 4vw, 56px);
    line-height: 1.1;
    font-weight: 950;
    letter-spacing: -0.045em;
}

.ai-container-desc {
    margin: 18px 0 0;
    color: #42566d;
    font-size: 17px;
    line-height: 1.8;
    max-width: 620px;
    font-weight: 600;
}

.ai-info-cards {
    margin-top: 28px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.ai-info-card {
    min-height: 112px;
    padding: 22px 24px;
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff, #f8fbfe);
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow: 0 12px 30px rgba(21, 70, 119, 0.06);
}

.ai-info-card h3 {
    margin: 0;
    color: #154677;
    font-size: 18px;
    font-weight: 900;
}

.ai-info-card p {
    margin: 10px 0 0;
    color: #5b6f84;
    font-size: 14px;
    line-height: 1.7;
    font-weight: 600;
}

.ai-container-visual {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.ai-feature-chip {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 22px;
    border-radius: 20px;
    background: linear-gradient(180deg, #ffffff, #f8fbfe);
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow: 0 16px 38px rgba(21, 70, 119, 0.08);
}

.ai-feature-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.18);
}

.ai-feature-chip strong {
    display: block;
    color: #154677;
    font-size: 16px;
    font-weight: 950;
}

.ai-feature-chip span {
    display: block;
    margin-top: 4px;
    color: #42566d;
    font-size: 13px;
    font-weight: 700;
}

.ai-preview-card {
    min-height: 235px;
    border-radius: 22px;
    position: relative;
    overflow: hidden;
    background:
        linear-gradient(135deg, rgba(21, 70, 119, 0.94), rgba(43, 166, 222, 0.74)),
        url("/images/ai-hero-bg.webp");
    background-size: cover;
    background-position: center;
    box-shadow:
        0 24px 50px rgba(21, 70, 119, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.22);
}

.ai-preview-overlay {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 78% 22%, rgba(255, 255, 255, 0.24), transparent 30%),
        linear-gradient(180deg, rgba(21, 70, 119, 0.08), rgba(21, 70, 119, 0.48));
}

.ai-preview-content {
    position: absolute;
    left: 30px;
    right: 30px;
    bottom: 28px;
    color: #ffffff;
}

.ai-preview-content span {
    display: block;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.16em;
    opacity: 0.9;
}

.ai-preview-content h2 {
    margin: 9px 0 0;
    font-size: 26px;
    line-height: 1.2;
    font-weight: 950;
}

.ai-preview-content p {
    margin: 9px 0 0;
    max-width: 520px;
    font-size: 14px;
    line-height: 1.7;
    font-weight: 700;
    opacity: 0.92;
}

/* =========================
   TOOLS SECTION
========================= */
.home-tools-page {
    background: #f4f8fb;
    padding: 0 16px 70px;
    box-sizing: border-box;
}

.home-shell {
    width: min(1500px, 100%);
    margin: 0 auto;
}

.tools-panel {
    position: relative;
    z-index: 20;
    margin-top: 0;
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(21, 70, 119, 0.12);
    border-radius: 34px;
    padding: 42px;
    box-shadow:
        0 18px 52px rgba(21, 70, 119, 0.08),
        0 24px 70px rgba(21, 70, 119, 0.08);
    overflow: hidden;
}

.tools-panel::before {
    content: "";
    position: absolute;
    top: -160px;
    inset-inline-end: -140px;
    width: 360px;
    height: 360px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.14), transparent 68%);
    pointer-events: none;
}

.tools-panel::after {
    content: "";
    position: absolute;
    bottom: -180px;
    inset-inline-start: -160px;
    width: 390px;
    height: 390px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(21, 70, 119, 0.10), transparent 68%);
    pointer-events: none;
}

.tools-panel-header {
    position: relative;
    z-index: 2;
    margin-bottom: 30px;
}

.tools-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 0 16px;
    border-radius: 999px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    box-shadow: 0 12px 26px rgba(21, 70, 119, 0.18);
}

.tools-title-row {
    margin-top: 18px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
}

.tools-title {
    margin: 0;
    color: #154677;
    font-size: clamp(30px, 4vw, 52px);
    line-height: 1.1;
    font-weight: 950;
    letter-spacing: -0.04em;
}

.tools-subtitle {
    max-width: 620px;
    margin: 12px 0 0;
    color: #5b6f84;
    font-size: 15px;
    line-height: 1.8;
    font-weight: 600;
}

.tools-count {
    width: 82px;
    height: 82px;
    border-radius: 24px;
    background: #f4fbff;
    border: 1px solid rgba(43, 166, 222, 0.18);
    color: #154677;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tools-count strong {
    font-size: 24px;
    font-weight: 950;
    line-height: 1;
}

.tools-count span {
    margin-top: 5px;
    font-size: 11px;
    font-weight: 800;
    color: #2ba6de;
    text-transform: uppercase;
}

.tools-layout {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.tool-card {
    position: relative;
    min-height: 260px;
    padding: 24px;
    border-radius: 26px;
    background:
        radial-gradient(circle at 80% 10%, rgba(43, 166, 222, 0.10), transparent 36%),
        linear-gradient(180deg, #ffffff 0%, #f9fcff 100%);
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow: 0 14px 34px rgba(21, 70, 119, 0.07);
    display: flex;
    flex-direction: column;
    cursor: pointer;
    overflow: hidden;
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
}

.tool-card:hover {
    transform: translateY(-6px);
    border-color: rgba(43, 166, 222, 0.32);
    box-shadow: 0 22px 48px rgba(21, 70, 119, 0.13);
}

.tool-card::before {
    content: "";
    position: absolute;
    width: 180px;
    height: 180px;
    border-radius: 999px;
    inset-inline-end: -82px;
    bottom: -96px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.13), transparent 70%);
    pointer-events: none;
}

.tool-status {
    position: absolute;
    top: 18px;
    inset-inline-end: 18px;
    min-height: 26px;
    padding: 0 11px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.tool-status.active {
    color: #154677;
    background: rgba(43, 166, 222, 0.12);
    border: 1px solid rgba(43, 166, 222, 0.18);
}

.tool-status.inactive {
    color: #6b7280;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
}

.tool-icon-wrap {
    position: relative;
    width: 112px;
    height: 112px;
    margin-bottom: 20px;
}

.tool-icon {
    position: absolute;
    inset: 18px;
    border-radius: 26px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 18px 38px rgba(21, 70, 119, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.24);
}

.tool-icon i {
    font-size: 34px;
}

.tool-spark {
    position: absolute;
    width: 34px;
    height: 34px;
    border-radius: 13px;
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.10);
    color: #2ba6de;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.12);
}

.spark-one {
    top: 0;
    inset-inline-end: 5px;
}

.spark-two {
    bottom: 4px;
    inset-inline-start: 0;
}

.tool-body {
    position: relative;
    z-index: 2;
    flex: 1;
}

.tool-title {
    margin: 0;
    color: #154677;
    font-size: 21px;
    line-height: 1.3;
    font-weight: 950;
    letter-spacing: -0.02em;
}

.tool-description {
    margin: 10px 0 0;
    color: #5b6f84;
    font-size: 14px;
    line-height: 1.7;
    font-weight: 600;
    max-width: 92%;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tool-footer {
    position: relative;
    z-index: 2;
    margin-top: 22px;
    padding-top: 16px;
    border-top: 1px solid rgba(21, 70, 119, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.tool-slug {
    max-width: 52%;
    color: #8a9bad;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.show-btn {
    border: none;
    min-height: 38px;
    padding: 0 15px;
    border-radius: 999px;
    background: #154677;
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition:
        transform 0.22s ease,
        background 0.22s ease,
        box-shadow 0.22s ease;
}

.show-btn i {
    font-size: 18px;
}

.show-btn:hover {
    background: #2ba6de;
    transform: translateY(-2px);
    box-shadow: 0 12px 22px rgba(43, 166, 222, 0.20);
}

.empty-state {
    position: relative;
    z-index: 2;
    min-height: 220px;
    border-radius: 24px;
    border: 1px dashed rgba(21, 70, 119, 0.18);
    background: #ffffff;
    color: #154677;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    background: #f4fbff;
    color: #2ba6de;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.empty-state h2 {
    margin: 14px 0 0;
    font-size: 16px;
    font-weight: 900;
}

.tool-skeleton {
    animation: pulse 1.5s infinite;
    cursor: default;
}

.skeleton-icon {
    width: 76px;
    height: 76px;
    border-radius: 24px;
    background: #e7eef5;
    margin-bottom: 20px;
}

.skeleton-line {
    height: 12px;
    background: #e7eef5;
    border-radius: 999px;
    margin-top: 10px;
}

.skeleton-line-lg {
    width: 70%;
}

.skeleton-line-sm {
    width: 48%;
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

.tool-card-enter-active {
    transition: all 0.3s ease;
}

.tool-card-enter-from {
    opacity: 0;
    transform: translateY(16px);
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .ai-premium-container {
        padding: 40px 28px 36px;
        border-radius: 28px;
    }

    .ai-container-grid {
        grid-template-columns: 1fr;
    }

    .ai-container-desc {
        max-width: 100%;
    }
}

@media (max-width: 900px) {
    .tools-panel {
        padding: 30px 20px;
        border-radius: 28px;
    }

    .tools-title-row {
        align-items: flex-start;
    }

    .tools-count {
        display: none;
    }

    .tools-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .ai-overlap-section {
        margin-top: -60px;
        padding-inline: 10px;
        padding-bottom: 30px;
    }

    .ai-premium-container {
        width: 100%;
        padding: 28px 18px 30px;
        border-radius: 24px;
    }

    .ai-container-badges {
        flex-wrap: wrap;
        justify-content: flex-start;
        margin-bottom: 24px;
    }

    .ai-brand-line {
        align-items: flex-start;
    }

    .ai-container-title {
        font-size: 34px;
    }

    .ai-container-desc {
        font-size: 15px;
    }

    .ai-info-cards {
        grid-template-columns: 1fr;
    }

    .ai-preview-card {
        min-height: 220px;
    }

    .ai-preview-content {
        left: 22px;
        right: 22px;
        bottom: 24px;
    }

    .ai-preview-content h2 {
        font-size: 23px;
    }

    .home-tools-page {
        padding-inline: 10px;
    }

    .tools-panel {
        padding: 24px 16px;
    }

    .tools-title {
        font-size: 31px;
    }

    .tools-subtitle {
        font-size: 14px;
        line-height: 1.7;
    }

    .tool-card {
        min-height: 240px;
        padding: 20px;
    }

    .tool-description {
        max-width: 100%;
    }

    .tool-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .tool-slug {
        max-width: 100%;
    }

    .show-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
