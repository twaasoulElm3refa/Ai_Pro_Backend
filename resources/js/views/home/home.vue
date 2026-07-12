<template>
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

                <!-- POPULAR SUBTOOLS -->
                <section v-if="subToolsLoading || randomSubTools.length" class="popular-subtools-section"
                    :aria-busy="subToolsLoading">
                    <div class="popular-subtools-head">
                        <h3>{{ popularSubToolsTitle }}</h3>
                        <p>{{ popularSubToolsDescription }}</p>
                    </div>

                    <div v-if="subToolsLoading" class="popular-subtools-grid">
                        <div v-for="item in subToolsSkeletonCount" :key="item"
                            class="popular-subtool-card popular-subtool-skeleton" aria-hidden="true">
                            <span class="popular-subtool-icon skeleton-popular-icon"></span>
                            <span class="skeleton-line skeleton-popular-name"></span>
                        </div>
                    </div>

                    <div v-else class="popular-subtools-grid">
                        <button v-for="subTool in randomSubTools"
                            :key="subTool.id || `${subTool.parent_slug || 'subtool'}-${subTool.slug}`" type="button"
                            class="popular-subtool-card" :aria-label="subTool.title" @click="goToSubTool(subTool)">
                            <span class="popular-subtool-icon">
                                <i :class="subTool.icon"></i>
                            </span>
                            <span class="popular-subtool-name">{{ subTool.title }}</span>
                        </button>
                    </div>
                </section>

                <!-- LOADING -->
                <div v-if="loading" class="tools-layout">
                    <div v-for="item in skeletonCount" :key="item" class="tool-card tool-skeleton">
                        <div class="skeleton-icon"></div>
                        <div class="skeleton-line skeleton-line-lg"></div>
                        <div class="skeleton-line skeleton-line-sm"></div>
                    </div>
                </div>

                <!-- TOOLS -->
                <TransitionGroup v-else-if="tools.length" :key="listKey" name="tool-card" tag="div"
                    class="tools-layout">
                    <article v-for="tool in tools" :key="tool.id" class="tool-card" role="button" tabindex="0"
                        :aria-label="t('user.home.openAria', { name: tool.title || tool.slug })"
                        @click="goToTool(tool.slug)" @keyup.enter="goToTool(tool.slug)"
                        @keyup.space.prevent="goToTool(tool.slug)">
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

                            <button type="button" class="show-btn"
                                :aria-label="t('user.home.showAria', { name: tool.title || tool.slug })"
                                @click.stop="goToTool(tool.slug)">
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

    <!-- FAQ SECTION -->
    <section class="faq-section">
        <div class="faq-header">
            <span class="faq-eyebrow">
                <i class="bi bi-question-circle"></i>
                {{ isArabic ? "الأسئلة الشائعة" : "FAQ" }}
            </span>

            <h2>
                {{ isArabic ? "كل ما تحتاج معرفته قبل استخدام الأدوات" : "Everything you need to know before using the tools" }}
            </h2>

            <p>
                {{
                    isArabic
                        ? "إجابات سريعة تساعدك على فهم طريقة استخدام أدوات الذكاء الاصطناعي والاستفادة منها بشكل أفضل."
                        : "Quick answers to help you understand how to use the AI tools and get better results."
                }}
            </p>
        </div>

        <div class="faq-list">
            <article v-for="(faq, index) in faqs" :key="index" class="faq-item"
                :class="{ active: activeFaqIndex === index }">
                <button type="button" class="faq-question" @click="toggleFaq(index)">
                    <span>{{ faq.question }}</span>

                    <i class="bi" :class="activeFaqIndex === index ? 'bi-dash-lg' : 'bi-plus-lg'"></i>
                </button>

                <Transition name="faq-slide">
                    <div v-if="activeFaqIndex === index" class="faq-answer">
                        <p>{{ faq.answer }}</p>
                    </div>
                </Transition>
            </article>
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
const subToolsLoading = ref(true);
const randomSubTools = ref([]);
const subToolsSkeletonCount = 6;

const listKey = computed(() => `${homeService.getLang()}-${tools.value.length}`);

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar"
);

const popularSubToolsTitle = computed(() =>
    isArabic.value ? "أدوات شائعة" : "Popular Tools"
);

const popularSubToolsDescription = computed(() =>
    isArabic.value
        ? "اكتشف أدوات فرعية تساعدك على إنجاز مهامك بسرعة."
        : "Discover focused tools that help you complete tasks faster."
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

const subToolFallbackIcons = [
    "bi-stars",
    "bi-magic",
    "bi-pencil-square",
    "bi-chat-dots",
    "bi-file-text",
    "bi-lightning-charge",
    "bi-robot",
];

const normalizeSubToolIcon = (icon, subTool) => {
    const fallbackIndex = Math.abs(Number(subTool?.id) || String(subTool?.slug || "").length);
    const selectedIcon = icon || subToolFallbackIcons[fallbackIndex % subToolFallbackIcons.length];
    const classes = String(selectedIcon).trim().split(/\s+/).filter(Boolean);

    if (!classes.some((className) => className === "bi")) classes.unshift("bi");
    return classes;
};

const normalizeSubTool = (subTool = {}) => {
    const translation = subTool.translation || subTool.translations?.[0] || {};

    return {
        ...subTool,
        id: subTool.id,
        slug: subTool.slug,
        full_slug: subTool.full_slug,
        url: subTool.url,
        parent_slug: subTool.parent_slug || subTool.tool?.slug || subTool.parent?.slug,
        title:
            translation.name ||
            subTool.name ||
            subTool.title ||
            subTool.label ||
            subTool.slug ||
            "",
        description: translation.description || subTool.description || "",
        icon: normalizeSubToolIcon(
            subTool.icon || subTool.icon_class || subTool.bootstrap_icon,
            subTool
        ),
        is_active: subTool.is_active !== false,
    };
};

const extractSubTools = (response) => {
    const candidates = [
        response?.data?.data?.subtools,
        response?.data?.data?.sub_tools,
        response?.data?.data?.items,
        response?.data?.subtools,
        response?.data?.sub_tools,
        response?.data?.random_subtools,
        response?.data?.data,
        response?.data,
        response?.subtools,
        response?.sub_tools,
        response?.random_subtools,
        response,
    ];

    return candidates.find(Array.isArray) || [];
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

const fetchRandomSubTools = async () => {
    subToolsLoading.value = true;

    try {
        const response = await homeService.fetchRandomSubTools();
        randomSubTools.value = extractSubTools(response).map(normalizeSubTool);
    } catch {
        randomSubTools.value = [];
    } finally {
        subToolsLoading.value = false;
    }
};

const goToTool = (slug) => {
    router.push(`/${homeService.getLang()}/tool/${slug}`);
};

const goToSubTool = (subTool) => {
    if (!subTool?.slug) return;
    const lang = homeService.getLang();
    const mainToolId = Number(subTool?.main_tool_id);
    const chatRouteMap = {
        1: "chat",
        2: "chat2",
        3: "chat3",
        4: "chat4",
    };
    const chatPath = chatRouteMap[mainToolId] || "chat";
    const uuid = subTool?.uuid || subTool?.chat_uuid || null;
    const url = uuid
        ? `/${lang}/subtool/${subTool.slug}/${chatPath}/${uuid}`
        : `/${lang}/subtool/${subTool.slug}/${chatPath}`;
    return router.push(url);
};

const handleLangChanged = async () => {
    locale.value = homeService.getLang();
    await Promise.all([fetchTools(), fetchRandomSubTools()]);
};

onMounted(async () => {
    await Promise.all([fetchTools(), fetchRandomSubTools()]);
    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => route.params.lang,
    async (nextLang, prevLang) => {
        if (!nextLang || nextLang === prevLang) return;
        locale.value = String(nextLang);
        await Promise.all([fetchTools(), fetchRandomSubTools()]);
    }
);

const activeFaqIndex = ref(0);

const faqs = computed(() => {
    if (isArabic.value) {
        return [
            {
                question: "ما هي أدوات الذكاء الاصطناعي المتاحة في الموقع؟",
                answer: "يوفر الموقع مجموعة من الأدوات الذكية التي تساعدك في كتابة النصوص، إعادة الصياغة، التلخيص، إنشاء الأفكار، كتابة المحتوى، وتجهيز نصوص أكثر احترافية في وقت أقل.",
            },
            {
                question: "هل أحتاج إلى خبرة تقنية لاستخدام الأدوات؟",
                answer: "لا، تم تصميم الأدوات لتكون سهلة وواضحة. كل ما عليك هو اختيار الأداة المناسبة، كتابة المطلوب، ثم الحصول على نتيجة منظمة يمكنك تعديلها أو استخدامها مباشرة.",
            },
            {
                question: "هل يمكن استخدام الأدوات لإنشاء محتوى عربي؟",
                answer: "نعم، تدعم الأدوات المحتوى العربي وتساعدك على إنتاج نصوص مناسبة للسياق العربي سواء للمقالات، السوشيال ميديا، الرسائل، الوصف، أو الأفكار التسويقية.",
            },
            {
                question: "هل النتائج جاهزة للنشر مباشرة؟",
                answer: "النتائج تكون جاهزة كنقطة بداية قوية، ويمكنك مراجعتها وتعديلها بما يناسب أسلوبك أو هوية مشروعك قبل النشر النهائي.",
            },
            {
                question: "هل يتم حفظ المحادثات أو النتائج؟",
                answer: "يعتمد ذلك على طريقة إعداد كل أداة داخل النظام. بعض الأدوات قد تتيح الرجوع للمحادثات أو النتائج السابقة عند تسجيل الدخول.",
            },
        ];
    }

    return [
        {
            question: "What AI tools are available on the website?",
            answer: "The platform provides smart tools for writing, rewriting, summarizing, generating ideas, creating content, and improving text quality in less time.",
        },
        {
            question: "Do I need technical experience to use the tools?",
            answer: "No. The tools are designed to be simple and clear. Choose the tool, write your request, and get an organized result that you can edit or use.",
        },
        {
            question: "Can I create Arabic content with these tools?",
            answer: "Yes. The tools support Arabic content and can help create articles, social media posts, messages, descriptions, and marketing ideas.",
        },
        {
            question: "Are the results ready to publish?",
            answer: "The results are a strong starting point. You can review and adjust them to match your style or brand before publishing.",
        },
        {
            question: "Are conversations or results saved?",
            answer: "This depends on how each tool is configured. Some tools may allow logged-in users to access previous chats or generated results.",
        },
    ];
});

const toggleFaq = (index) => {
    activeFaqIndex.value = activeFaqIndex.value === index ? null : index;
};
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
    background: linear-gradient(135deg,
            rgba(21, 70, 119, 0.28),
            rgba(43, 166, 222, 0.22),
            rgba(21, 70, 119, 0.08));
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
    padding: 60px 16px 70px;
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

.popular-subtools-section {
    position: relative;
    z-index: 2;
    margin-bottom: 32px;
    padding: 26px 24px;
    border: 1px solid rgba(21, 70, 119, 0.10);
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(21, 70, 119, 0.04), rgba(43, 166, 222, 0.08));
}

.popular-subtools-head {
    margin-bottom: 20px;
}

.popular-subtools-head h3 {
    margin: 0;
    color: #154677;
    font-size: 23px;
    line-height: 1.3;
    font-weight: 950;
}

.popular-subtools-head p {
    margin: 7px 0 0;
    color: #5b6f84;
    font-size: 14px;
    line-height: 1.7;
    font-weight: 600;
}

.popular-subtools-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 14px;
}

.popular-subtool-card {
    min-width: 0;
    min-height: 132px;
    padding: 18px 12px;
    border: 1px solid rgba(21, 70, 119, 0.10);
    border-radius: 22px;
    background: #ffffff;
    color: #154677;
    box-shadow: 0 10px 26px rgba(21, 70, 119, 0.07);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font: inherit;
    cursor: pointer;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.popular-subtool-card:not(.popular-subtool-skeleton):hover,
.popular-subtool-card:not(.popular-subtool-skeleton):focus-visible {
    transform: translateY(-5px);
    border-color: #2ba6de;
    box-shadow: 0 17px 34px rgba(21, 70, 119, 0.12);
    outline: none;
}

.popular-subtool-icon {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(21, 70, 119, 0.10), rgba(43, 166, 222, 0.18));
    color: #154677;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.popular-subtool-name {
    color: #154677;
    font-size: 14px;
    font-weight: 900;
    line-height: 1.5;
    text-align: center;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.popular-subtool-skeleton {
    cursor: default;
    pointer-events: none;
}

.skeleton-popular-icon,
.skeleton-popular-name {
    animation: skeletonPulse 1.25s ease-in-out infinite;
}

.skeleton-popular-icon {
    background: #e5edf4;
}

.skeleton-popular-name {
    width: 72%;
    height: 12px;
    margin: 0;
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

@media (max-width: 1200px) {
    .popular-subtools-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
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
        padding: 42px 10px 70px;
    }

    .tools-panel {
        padding: 24px 16px;
    }

    .popular-subtools-section {
        padding: 22px 16px;
        margin-inline: -4px;
        overflow: hidden;
    }

    .popular-subtools-grid {
        display: flex;
        gap: 12px;
        margin-inline: -16px;
        padding: 2px 16px 12px;
        overflow-x: auto;
        scroll-snap-type: x proximity;
        scrollbar-width: thin;
    }

    .popular-subtool-card {
        flex: 0 0 138px;
        scroll-snap-align: start;
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

.faq-section {
    position: relative;
    margin-top: 36px;
    padding: 42px;
    border-radius: 34px;
    background:
        radial-gradient(circle at 88% 12%, rgba(43, 166, 222, 0.14), transparent 34%),
        radial-gradient(circle at 10% 90%, rgba(21, 70, 119, 0.10), transparent 34%),
        rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(21, 70, 119, 0.12);
    box-shadow:
        0 18px 52px rgba(21, 70, 119, 0.08),
        0 24px 70px rgba(21, 70, 119, 0.08);
    overflow: hidden;
}

.faq-header {
    position: relative;
    z-index: 2;
    max-width: 760px;
    margin-bottom: 28px;
}

.home-tools-page[dir="rtl"] .faq-header {
    text-align: right;
}

.home-tools-page[dir="ltr"] .faq-header {
    text-align: left;
}

.faq-eyebrow {
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
    letter-spacing: 0.08em;
    box-shadow: 0 12px 26px rgba(21, 70, 119, 0.18);
}

.faq-header h2 {
    margin: 18px 0 0;
    color: #154677;
    font-size: clamp(28px, 3.5vw, 46px);
    line-height: 1.15;
    font-weight: 950;
    letter-spacing: -0.035em;
}

.faq-header p {
    margin: 14px 0 0;
    color: #5b6f84;
    font-size: 15px;
    line-height: 1.8;
    font-weight: 600;
}

.faq-list {
    position: relative;
    z-index: 2;
    display: grid;
    gap: 14px;
}

.faq-item {
    border-radius: 22px;
    background:
        linear-gradient(180deg, #ffffff 0%, #f9fcff 100%);
    border: 1px solid rgba(21, 70, 119, 0.10);
    box-shadow: 0 12px 30px rgba(21, 70, 119, 0.06);
    overflow: hidden;
    transition:
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        transform 0.25s ease;
}

.faq-item.active {
    border-color: rgba(43, 166, 222, 0.34);
    box-shadow: 0 18px 42px rgba(21, 70, 119, 0.10);
}

.faq-item:hover {
    transform: translateY(-2px);
}

.faq-question {
    width: 100%;
    min-height: 70px;
    padding: 0 22px;
    border: none;
    background: transparent;
    color: #154677;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    text-align: inherit;
}

.faq-question span {
    font-size: 16px;
    font-weight: 950;
    line-height: 1.6;
}

.faq-question i {
    width: 38px;
    height: 38px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(21, 70, 119, 0.10), rgba(43, 166, 222, 0.16));
    color: #2ba6de;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.faq-answer {
    padding: 0 22px 22px;
}

.faq-answer p {
    margin: 0;
    padding-top: 2px;
    color: #5b6f84;
    font-size: 14px;
    line-height: 1.9;
    font-weight: 600;
}

.faq-slide-enter-active,
.faq-slide-leave-active {
    transition: all 0.22s ease;
}

.faq-slide-enter-from,
.faq-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (max-width: 900px) {
    .faq-section {
        padding: 30px 20px;
        border-radius: 28px;
    }
}

@media (max-width: 640px) {
    .faq-section {
        margin-top: 26px;
        padding: 24px 16px;
        border-radius: 24px;
    }

    .faq-header h2 {
        font-size: 29px;
    }

    .faq-question {
        min-height: 66px;
        padding: 0 16px;
        gap: 12px;
    }

    .faq-question span {
        font-size: 14px;
    }

    .faq-question i {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        font-size: 14px;
    }

    .faq-answer {
        padding: 0 16px 18px;
    }

    .faq-answer p {
        font-size: 13px;
    }
}

/* =========================
   PREMIUM MOTION
========================= */
@keyframes premiumFadeUp {
    from {
        opacity: 0;
        transform: translateY(22px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes softBlobMove {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }

    to {
        transform: translate3d(8px, -8px, 0) scale(1.025);
    }
}

@keyframes premiumIconFloat {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }

    50% {
        transform: translate3d(0, -3px, 0);
    }
}

.tools-panel {
    animation: premiumFadeUp 0.62s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.tools-panel::before {
    animation: softBlobMove 10s ease-in-out infinite alternate;
    will-change: transform;
}

.tools-panel::after {
    animation: softBlobMove 12s ease-in-out -3s infinite alternate-reverse;
    will-change: transform;
}

.popular-subtools-section {
    animation: premiumFadeUp 0.58s 0.16s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.popular-subtool-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    animation: premiumFadeUp 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
    transition:
        transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.25s ease,
        box-shadow 0.28s ease;
}

.popular-subtool-card:nth-child(1) { animation-delay: 0.24s; }
.popular-subtool-card:nth-child(2) { animation-delay: 0.30s; }
.popular-subtool-card:nth-child(3) { animation-delay: 0.36s; }
.popular-subtool-card:nth-child(4) { animation-delay: 0.42s; }
.popular-subtool-card:nth-child(5) { animation-delay: 0.48s; }
.popular-subtool-card:nth-child(6) { animation-delay: 0.54s; }

.popular-subtool-card::before {
    content: "";
    position: absolute;
    z-index: 0;
    top: -35%;
    bottom: -35%;
    left: -75%;
    width: 42%;
    background: linear-gradient(
        110deg,
        transparent 0%,
        rgba(255, 255, 255, 0.18) 28%,
        rgba(43, 166, 222, 0.15) 50%,
        rgba(255, 255, 255, 0.34) 72%,
        transparent 100%
    );
    transform: skewX(-16deg);
    transition: left 0.68s cubic-bezier(0.22, 1, 0.36, 1);
    pointer-events: none;
}

.popular-subtool-card > * {
    position: relative;
    z-index: 1;
}

.popular-subtool-card:not(.popular-subtool-skeleton):hover,
.popular-subtool-card:not(.popular-subtool-skeleton):focus-visible {
    transform: translateY(-6px) scale(1.015);
    border-color: #2ba6de;
    box-shadow: 0 20px 42px rgba(21, 70, 119, 0.16);
}

.popular-subtool-card:not(.popular-subtool-skeleton):hover::before,
.popular-subtool-card:not(.popular-subtool-skeleton):focus-visible::before {
    left: 135%;
}

.popular-subtool-icon {
    animation: premiumIconFloat 3.4s ease-in-out infinite;
    transition:
        transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.28s ease;
    will-change: transform;
}

.popular-subtool-card:nth-child(2n) .popular-subtool-icon { animation-delay: -0.8s; }
.popular-subtool-card:nth-child(3n) .popular-subtool-icon { animation-delay: -1.6s; }
.popular-subtool-card:nth-child(4n) .popular-subtool-icon { animation-delay: -2.4s; }

.popular-subtool-card:not(.popular-subtool-skeleton):hover .popular-subtool-icon,
.popular-subtool-card:not(.popular-subtool-skeleton):focus-visible .popular-subtool-icon {
    animation-play-state: paused;
    transform: translateY(-2px) scale(1.08) rotate(2deg);
    box-shadow:
        0 12px 26px rgba(43, 166, 222, 0.20),
        0 0 0 7px rgba(43, 166, 222, 0.07);
}

.tools-layout > .tool-card {
    animation: premiumFadeUp 0.52s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.tools-layout > .tool-card:nth-child(1) { animation-delay: 0.08s; }
.tools-layout > .tool-card:nth-child(2) { animation-delay: 0.14s; }
.tools-layout > .tool-card:nth-child(3) { animation-delay: 0.20s; }
.tools-layout > .tool-card:nth-child(4) { animation-delay: 0.26s; }
.tools-layout > .tool-card:nth-child(5) { animation-delay: 0.32s; }
.tools-layout > .tool-card:nth-child(6) { animation-delay: 0.38s; }
.tools-layout > .tool-card:nth-child(n + 7) { animation-delay: 0.44s; }

.tool-card::after {
    content: "";
    position: absolute;
    z-index: 1;
    top: -40%;
    bottom: -40%;
    left: -65%;
    width: 32%;
    background: linear-gradient(
        110deg,
        transparent,
        rgba(255, 255, 255, 0.34),
        rgba(43, 166, 222, 0.10),
        transparent
    );
    transform: skewX(-18deg);
    transition: left 0.72s cubic-bezier(0.22, 1, 0.36, 1);
    pointer-events: none;
}

.tool-card > * {
    z-index: 2;
}

.tool-card:hover::after,
.tool-card:focus-visible::after {
    left: 135%;
}

.tool-card:focus-visible {
    transform: translateY(-6px);
    border-color: #2ba6de;
    box-shadow: 0 22px 48px rgba(21, 70, 119, 0.13);
    outline: 3px solid rgba(43, 166, 222, 0.18);
    outline-offset: 3px;
}

.tool-spark {
    animation: premiumIconFloat 3.2s ease-in-out infinite;
    will-change: transform;
}

.tool-spark.spark-two {
    animation-delay: -1.45s;
}

.faq-section {
    animation: premiumFadeUp 0.62s 0.28s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.faq-item.active {
    border-color: rgba(43, 166, 222, 0.58);
    box-shadow:
        0 20px 46px rgba(21, 70, 119, 0.13),
        0 0 0 1px rgba(43, 166, 222, 0.08);
}

.faq-question:focus-visible {
    outline: 3px solid rgba(43, 166, 222, 0.20);
    outline-offset: -3px;
}

.faq-question i {
    transition:
        transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
        background-color 0.25s ease,
        box-shadow 0.25s ease;
}

.faq-item.active .faq-question i {
    transform: rotate(180deg);
    box-shadow: 0 8px 18px rgba(43, 166, 222, 0.16);
}

.faq-slide-enter-active,
.faq-slide-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.faq-slide-enter-from,
.faq-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        animation-delay: 0ms !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>
