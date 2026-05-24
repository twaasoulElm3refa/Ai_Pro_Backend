<template>
    <section class="home-tools-page min-h-screen px-4 py-12 sm:px-6 lg:px-10" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="mx-auto max-w-7xl">

            <!-- HEADER -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <span class="badge">{{ t("user.toolShow.badge") }}</span>

                    <h1 class="mt-6 text-4xl font-bold sm:text-5xl text-main">
                        {{ loading ? t("user.toolShow.loadingTitle") : tool.title }}
                    </h1>

                    <p class="mt-4 text-base sm:text-lg text-muted">
                        {{ tool.description }}
                    </p>
                </div>

                <button
                    type="button"
                    class="back-button"
                    :aria-label="t('user.toolShow.backAria')"
                    @click="router.push(`/${homeService.getLang()}`)"
                >
                    <i class="bi bi-arrow-left"></i>
                    <span>{{ t("user.toolShow.back") }}</span>
                </button>
            </div>

            <!-- LOADING -->
            <div v-if="loading" class="mt-10 space-y-6">
                <div class="tool-card h-72 animate-pulse"></div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-6">
                    <div v-for="i in 3" :key="i" class="tool-card h-52 animate-pulse"></div>
                </div>
            </div>

            <!-- CONTENT -->
            <div v-else class="mt-10 space-y-10">
                <!-- SUBTOOLS -->
                <div>
                    <div class="subtools-header">
                        <div>
                            <h2 class="section-title">{{ t("user.toolShow.sectionTitle") }}</h2>
                            <p class="section-sub">{{ t("user.toolShow.sectionSubtitle") }}</p>
                        </div>

                        <span class="count-badge">{{ subtools.length }}</span>
                    </div>

                    <TransitionGroup
                        v-if="subtools.length"
                        name="subtool-card"
                        tag="div"
                        class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="subtool in subtools"
                            :key="subtool.id"
                            class="subtool-card"
                        >
                            <!-- TOP ROW -->
                            <div class="subtool-top">
                                <div
                                    class="subtool-icon"
                                    :class="getSubtoolIconClass(subtool)"
                                    :aria-label="subtool.title"
                                >
                                    <!-- TEXT EDITOR ICON -->
                                    <template v-if="isTextEditorSubtool(subtool)">
                                        <div class="icon-orbit"></div>

                                        <span class="icon-main">
                                            <i class="bi bi-pencil-square"></i>
                                        </span>

                                        <span class="icon-mini icon-mini-one">
                                            <i class="bi bi-type"></i>
                                        </span>

                                        <span class="icon-mini icon-mini-two">
                                            <i class="bi bi-magic"></i>
                                        </span>
                                    </template>

                                    <!-- TEXT SUMMARIZER ICON -->
                                    <template v-else-if="isTextSummarizerSubtool(subtool)">
                                        <div class="icon-orbit"></div>

                                        <span class="icon-main">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </span>

                                        <span class="icon-mini icon-mini-one">
                                            <i class="bi bi-filter-left"></i>
                                        </span>

                                        <span class="icon-mini icon-mini-two">
                                            <i class="bi bi-arrow-down-up"></i>
                                        </span>
                                    </template>

                                    <!-- DEFAULT ICON -->
                                    <template v-else>
                                        <div class="icon-orbit"></div>

                                        <span class="icon-main">
                                            <i class="bi bi-stars"></i>
                                        </span>

                                        <span class="icon-mini icon-mini-one">
                                            <i class="bi bi-cpu"></i>
                                        </span>

                                        <span class="icon-mini icon-mini-two">
                                            <i class="bi bi-tools"></i>
                                        </span>
                                    </template>
                                </div>

                                <span :class="['status-chip', subtool.is_active ? 'active' : 'inactive']">
                                    {{ subtool.is_active ? t("user.toolShow.statusActive") : t("user.toolShow.statusInactive") }}
                                </span>
                            </div>

                            <!-- TITLE & SLUG -->
                            <h3 class="subtool-title">{{ subtool.title }}</h3>
                            <p class="subtool-slug">{{ subtool.slug }}</p>

                            <!-- DESCRIPTION -->
                            <p class="subtool-desc">{{ subtool.description }}</p>

                            <!-- FOOTER -->
                            <div class="subtool-footer">
                                <div class="subtool-meta">
                                    <p v-if="subtool.promptPlaceholder" class="meta-text">
                                        <i class="bi bi-chat-dots"></i>
                                        {{ subtool.promptPlaceholder }}
                                    </p>

                                    <p v-if="subtool.providerName" class="meta-text">
                                        <i class="bi bi-server"></i>
                                        {{ subtool.providerName }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="chat-button"
                                    :aria-label="t('user.toolShow.chatAria', { name: subtool.title })"
                                    @click="router.push(`/${homeService.getLang()}/subtool/${subtool.slug}/chat`)"
                                >
                                    <i class="bi bi-chat-fill"></i>
                                    {{ t("user.toolShow.chatButton") }}
                                </button>
                            </div>
                        </article>
                    </TransitionGroup>

                    <div v-else class="empty-box mt-6">
                        <i class="bi bi-inbox text-2xl mb-2 block"></i>
                        {{ t("user.toolShow.empty") }}
                    </div>
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

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const loading = ref(true);
const rawTool = ref({});

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar"
);

const mapMainTool = (payload = {}) => {
    const translation = payload?.translation;

    return {
        ...payload,
        title: translation?.name || t("user.toolShow.untitledTool"),
        description: translation?.description || t("user.toolShow.noDescription"),
        imageUrl: payload?.image ? `/storage/${payload.image}` : "",
    };
};

const mapSubtool = (payload = {}) => {
    return {
        id: payload?.id,
        slug: payload?.slug || "-",
        title: payload?.translation.name || payload?.name || t("user.toolShow.untitledSubtool"),
        description: payload?.translation.description || payload?.description || t("user.toolShow.noDescription"),
        promptPlaceholder: payload?.translation.prompt_placeholder || payload?.prompt_placeholder || "",
        is_active: Boolean(payload?.is_active),
        providerName: payload?.website?.name || payload?.website || "",
    };
};

const normalizeText = (value = "") => {
    return String(value || "")
        .toLowerCase()
        .trim()
        .replace(/[\u064B-\u065F\u0670]/g, "")
        .replace(/[أإآ]/g, "ا")
        .replace(/ة/g, "ه")
        .replace(/ى/g, "ي");
};

const isTextEditorSubtool = (subtool = {}) => {
    const text = normalizeText(`${subtool.title || ""} ${subtool.slug || ""}`);

    return (
        text.includes("محرر النصوص") ||
        text.includes("محرر نصوص") ||
        text.includes("تحرير النصوص") ||
        text.includes("كاتب النصوص") ||
        text.includes("كاتب نصوص") ||
        text.includes("text editor") ||
        text.includes("text-editor") ||
        text.includes("editor") ||
        text.includes("writer")
    );
};

const isTextSummarizerSubtool = (subtool = {}) => {
    const text = normalizeText(`${subtool.title || ""} ${subtool.slug || ""}`);

    return (
        text.includes("ملخص النصوص") ||
        text.includes("ملخص نصوص") ||
        text.includes("تلخيص النصوص") ||
        text.includes("تلخيص") ||
        text.includes("summarizer") ||
        text.includes("summary") ||
        text.includes("summarize")
    );
};

const getSubtoolIconClass = (subtool = {}) => {
    if (isTextEditorSubtool(subtool)) {
        return "subtool-icon-editor";
    }

    if (isTextSummarizerSubtool(subtool)) {
        return "subtool-icon-summarizer";
    }

    return "subtool-icon-default";
};

const tool = computed(() => mapMainTool(rawTool.value));

const subtools = computed(() =>
    (rawTool.value?.sub_tools || []).map(mapSubtool)
);

const seoTitle = computed(() =>
    isArabic.value
        ? `${tool.value.title || "تفاصيل الأداة"} | Ai Pro`
        : `${tool.value.title || "Tool Details"} | Ai Pro`
);

const seoDescription = computed(() =>
    isArabic.value
        ? "اعرض تفاصيل الأداة بوضوح، استكشف الأدوات الفرعية المرتبطة، وابدأ تجربة الدردشة المناسبة بسرعة عبر معلومات منظمة وحالة تشغيل دقيقة."
        : "View detailed information for this AI tool, browse related subtools, and launch the right chat experience quickly with clear descriptions and status insights."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

const loadTool = async () => {
    locale.value = homeService.getLang();
    loading.value = true;

    try {
        const response = await homeService.showTool(route.params.slug);
        rawTool.value = response?.data || {};
    } catch (e) {
        rawTool.value = {};
    } finally {
        loading.value = false;
    }
};

const handleLangChanged = async () => {
    locale.value = homeService.getLang();
    await loadTool();
};

onMounted(async () => {
    await loadTool();
    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => [route.params.slug, route.params.lang],
    async ([nextSlug, nextLang], [prevSlug, prevLang]) => {
        if (nextSlug === prevSlug && nextLang === prevLang) return;

        await loadTool();
    }
);
</script>

<style scoped>
.home-tools-page {
    background: #f8fafc;
}

/* TEXT */
.text-main {
    color: #154677;
}

.text-muted {
    color: #6b7280;
}

/* BADGE */
.badge {
    background: #154677;
    color: #fff;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* BACK BUTTON */
.back-button {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #154677;
    color: #fff;
    padding: 10px 18px;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
    white-space: nowrap;
    align-self: flex-start;
}

.back-button:hover {
    background: #2ba6de;
}

/* HERO CARD */
.tool-hero-card {
    display: flex;
    gap: 24px;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 28px;
}

.tool-hero-image-wrap {
    flex-shrink: 0;
    width: 180px;
    height: 140px;
    border-radius: 14px;
    overflow: hidden;
}

.tool-hero-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tool-hero-fallback {
    width: 100%;
    height: 100%;
    background: #eef3fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #154677;
}

.tool-hero-info {
    flex: 1;
}

.tool-hero-title {
    font-size: 22px;
    font-weight: 700;
    color: #154677;
}

.tool-hero-desc {
    margin-top: 8px;
    font-size: 15px;
    color: #6b7280;
    line-height: 1.6;
}

/* SUBTOOLS SECTION */
.subtools-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #154677;
}

.section-sub {
    font-size: 13px;
    color: #9ca3af;
    margin-top: 2px;
}

.count-badge {
    background: #154677;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
}

/* SUBTOOL CARD */
.subtool-card {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 18% 10%, rgba(21, 70, 119, 0.06), transparent 30%),
        linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.subtool-card::after {
    content: "";
    position: absolute;
    right: -70px;
    bottom: -80px;
    width: 170px;
    height: 170px;
    border-radius: 999px;
    background: rgba(43, 166, 222, 0.07);
    pointer-events: none;
}

.subtool-card:hover {
    transform: translateY(-4px);
    border-color: rgba(21, 70, 119, 0.18);
    box-shadow: 0 12px 32px rgba(7, 67, 119, 0.1);
}

.subtool-top {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

/* ICONS */
.subtool-icon {
    position: relative;
    width: 72px;
    height: 72px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    isolation: isolate;
}

.icon-orbit {
    position: absolute;
    inset: 7px;
    border-radius: 20px;
    background:
        radial-gradient(circle at 35% 25%, rgba(255, 255, 255, 0.95), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.88), rgba(255, 255, 255, 0.48));
    border: 1px solid rgba(255, 255, 255, 0.75);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.95),
        0 16px 30px rgba(21, 70, 119, 0.12);
}

.icon-main {
    position: relative;
    z-index: 3;
    width: 43px;
    height: 43px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    box-shadow: 0 10px 20px rgba(21, 70, 119, 0.12);
}

.icon-main i {
    font-size: 22px;
}

.icon-mini {
    position: absolute;
    z-index: 4;
    width: 24px;
    height: 24px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.08);
    box-shadow: 0 8px 15px rgba(21, 70, 119, 0.11);
}

.icon-mini i {
    font-size: 12px;
}

.icon-mini-one {
    top: 2px;
    right: 0;
}

.icon-mini-two {
    left: 0;
    bottom: 4px;
}

/* EDITOR ICON */
.subtool-icon-editor {
    background:
        radial-gradient(circle at 30% 22%, rgba(43, 166, 222, 0.20), transparent 32%),
        linear-gradient(135deg, #eef8ff 0%, #edf4ff 48%, #ffffff 100%);
    color: #154677;
    border: 1px solid rgba(43, 166, 222, 0.22);
}

.subtool-icon-editor .icon-main {
    color: #154677;
}

.subtool-icon-editor .icon-mini {
    color: #2ba6de;
}

/* SUMMARIZER ICON */
.subtool-icon-summarizer {
    background:
        radial-gradient(circle at 70% 25%, rgba(21, 70, 119, 0.18), transparent 34%),
        linear-gradient(135deg, #f3f7fb 0%, #eef3fa 48%, #ffffff 100%);
    color: #154677;
    border: 1px solid rgba(21, 70, 119, 0.18);
}

.subtool-icon-summarizer .icon-main {
    color: #154677;
}

.subtool-icon-summarizer .icon-mini {
    color: #154677;
}

/* DEFAULT ICON */
.subtool-icon-default {
    background:
        radial-gradient(circle at 35% 25%, rgba(21, 70, 119, 0.15), transparent 34%),
        linear-gradient(135deg, #eef3fa 0%, #ffffff 100%);
    color: #154677;
    border: 1px solid rgba(21, 70, 119, 0.14);
}

.subtool-icon-default .icon-main,
.subtool-icon-default .icon-mini {
    color: #154677;
}

/* STATUS */
.status-chip {
    position: relative;
    z-index: 2;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
}

.status-chip.active {
    background: #d1fae5;
    color: #065f46;
}

.status-chip.inactive {
    background: #fee2e2;
    color: #154677;
}

/* TEXT */
.subtool-title {
    position: relative;
    z-index: 2;
    font-size: 16px;
    font-weight: 800;
    color: #154677;
    margin: 4px 0 0;
}

.subtool-slug {
    position: relative;
    z-index: 2;
    font-size: 11px;
    color: #9ca3af;
    margin: 0;
}

.subtool-desc {
    position: relative;
    z-index: 2;
    font-size: 13px;
    color: #6b7280;
    line-height: 1.55;
    flex: 1;
}

/* FOOTER */
.subtool-footer {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 10px;
    margin-top: 4px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.subtool-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    overflow: hidden;
}

.meta-text {
    font-size: 11px;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* CHAT BUTTON */
.chat-button {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #154677;
    color: #fff;
    border: none;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.2s, transform 0.15s;
    flex-shrink: 0;
}

.chat-button:hover {
    background: #2ba6de;
    transform: scale(1.04);
}

/* EMPTY */
.empty-box {
    border: 1px dashed #e5e7eb;
    padding: 40px;
    text-align: center;
    color: #9ca3af;
    border-radius: 14px;
    background: #fff;
}

/* TRANSITION */
.subtool-card-enter-active,
.subtool-card-leave-active {
    transition: all 0.3s ease;
}

.subtool-card-enter-from,
.subtool-card-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

/* RESPONSIVE */
@media (max-width: 640px) {
    .tool-hero-card {
        flex-direction: column;
    }

    .tool-hero-image-wrap {
        width: 100%;
        height: 180px;
    }

    .subtool-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .chat-button {
        justify-content: center;
        width: 100%;
    }
}
</style>
