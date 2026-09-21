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

                <!-- DYNAMIC TREND TOOLS CAROUSEL -->
                <section v-if="trendToolsLoading || trendTools.length" class="home-trends-section"
                    :aria-busy="trendToolsLoading">
                    <div class="home-trends-header">
                        <div>
                            <span class="home-trends-eyebrow">
                                <i class="bi bi-stars" aria-hidden="true"></i>
                                {{ trendMainTool?.name || (isArabic ? "الترندات" : "Trends") }}
                            </span>
                            <h3>{{ trendToolsTitle }}</h3>
                        </div>

                    </div>

                    <div class="home-trends-slider" @mouseenter="stopTrendAutoplay"
                        @mouseleave="startTrendAutoplay" @focusin="stopTrendAutoplay"
                        @focusout="startTrendAutoplay" @touchstart.passive="stopTrendAutoplay"
                        @touchend.passive="startTrendAutoplay">
                        <button v-if="!trendToolsLoading && trendPageCount > 1" type="button"
                            class="home-trends-arrow is-previous" :aria-label="isArabic ? 'السابق' : 'Previous'"
                            @click="moveTrendPage(-1)">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </button>

                        <div ref="trendCarousel" class="home-trends-viewport" dir="ltr"
                            @scroll.passive="syncTrendPage">
                            <div class="home-trends-track">
                                <template v-if="trendToolsLoading">
                                    <article v-for="item in 3" :key="`trend-skeleton-${item}`"
                                        class="home-trend-card home-trend-skeleton" aria-hidden="true">
                                        <span class="home-trend-skeleton-line"></span>
                                    </article>
                                </template>

                                <template v-else>
                                    <button v-for="tool in trendTools" :key="tool.id" type="button"
                                        class="home-trend-card" :dir="isArabic ? 'rtl' : 'ltr'"
                                        :aria-label="t('user.home.openAria', { name: tool.name || tool.slug })"
                                        @click="goToTrendTool(tool)">
                                        <img :src="tool.imageUrl" :alt="tool.name" loading="lazy"
                                            @error="removeBrokenTrendTool(tool.id)" />
                                        <span class="home-trend-overlay" aria-hidden="true"></span>
                                        <span class="home-trend-name">{{ tool.name }}</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <button v-if="!trendToolsLoading && trendPageCount > 1" type="button"
                            class="home-trends-arrow is-next" :aria-label="isArabic ? 'التالي' : 'Next'"
                            @click="moveTrendPage(1)">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div v-if="!trendToolsLoading && trendPageCount > 1" class="home-trends-dots"
                        :aria-label="isArabic ? 'صفحات أدوات الترند' : 'Trend tool pages'">
                        <button v-for="page in trendPageCount" :key="page" type="button"
                            :class="{ active: trendCurrentPage === page - 1 }"
                            :aria-label="`${isArabic ? 'الصفحة' : 'Page'} ${page}`"
                            :aria-current="trendCurrentPage === page - 1 ? 'true' : undefined"
                            @click="selectTrendPage(page - 1)"></button>
                    </div>
                </section>

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

                <!-- TREND TOOLS FEATURE -->
                <router-link
                    v-if="!aiMainModelLoading && aiMainModel"
                    class="trend-tool-feature"
                    :to="aiModelsRoute"
                    :aria-label="t('user.home.openAria', { name: aiMainModel.title || aiMainModel.slug })"
                >
                    <span class="trend-tool-visual" aria-hidden="true">
                        <img
                            v-if="aiMainModel.imageUrl"
                            :src="aiMainModel.imageUrl"
                            :alt="aiMainModel.title"
                            @error="hideBrokenImage"
                        />
                        <i v-else class="bi bi-graph-up-arrow"></i>
                    </span>

                    <span class="trend-tool-copy">
                        <span class="trend-tool-title">{{ aiMainModel.title }}</span>
                        <span class="trend-tool-description">{{ aiMainModel.description }}</span>
                    </span>

                    <span class="trend-tool-cta">
                        {{ t("user.home.showButton") }}
                        <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                    </span>
                </router-link>

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
import trendServices from "@/services/chat/trendServices";
import useSeoMeta from "@/composables/useSeoMeta";

const router = useRouter();
const route = useRoute();
const { t, locale } = useI18n();

const loading = ref(true);
const tools = ref([]);
const aiMainModel = ref(null);
const aiMainModelLoading = ref(true);
const skeletonCount = 4;
const subToolsLoading = ref(true);
const randomSubTools = ref([]);
const subToolsSkeletonCount = 6;
const trendToolsLoading = ref(true);
const trendMainTool = ref(null);
const trendTools = ref([]);
const trendCarousel = ref(null);
const trendCurrentPage = ref(0);
const trendCardsPerView = ref(3);
let trendScrollFrame = 0;
let trendAutoplayTimer = 0;
const trendObjectUrls = new Set();

const listKey = computed(() => `${homeService.getLang()}-${tools.value.length}`);
const currentLang = computed(() => String(route.params.lang || homeService.getLang()));
const aiModelsRoute = computed(() => ({
    name: "ai-models",
    params: { lang: currentLang.value },
}));

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

const trendToolsTitle = computed(() =>
    isArabic.value ? "أحدث إبداعات أدوات الترند" : "Latest Trend Tool Creations"
);

const trendPageCount = computed(() => Math.max(
    1,
    Math.ceil(trendTools.value.length / trendCardsPerView.value)
));

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
        title: translation?.name || tool.name,
        description: translation?.description || tool.description || "",
        imageUrl: resolveToolImage(tool.image),
    };
};

const resolveToolImage = (image) => {
    const path = String(image || "").trim();
    if (!path) return "";
    if (/^(https?:)?\/\//i.test(path) || path.startsWith("/")) return path;

    return `/storage/${path.replace(/^storage\//i, "")}`;
};

const hideBrokenImage = () => {
    if (aiMainModel.value) aiMainModel.value.imageUrl = "";
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

        tools.value = data
            .map(normalizeTool)
            .filter((tool) => Number(tool.id) !== 7);
    } catch (e) {
        tools.value = [];
    } finally {
        loading.value = false;
    }
};

const fetchAiMainModel = async () => {
    aiMainModelLoading.value = true;

    try {
        const response = await homeService.fetchAiMainModel();
        const data = response?.data;

        aiMainModel.value = data?.id ? normalizeTool(data) : null;
    } catch {
        aiMainModel.value = null;
    } finally {
        aiMainModelLoading.value = false;
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

const normalizeHomeTrendTool = (tool = {}) => ({
    id: Number(tool.id),
    name: tool.name || tool.slug || "",
    slug: tool.slug || "",
    previewUrl: tool.image?.preview_url || "",
    imageUrl: "",
    endpoint: tool.endpoint || "",
});

const clearTrendObjectUrls = () => {
    trendObjectUrls.forEach((url) => URL.revokeObjectURL(url));
    trendObjectUrls.clear();
};

const fetchHomeTrendTools = async () => {
    trendToolsLoading.value = true;
    stopTrendAutoplay();
    clearTrendObjectUrls();

    try {
        const response = await homeService.fetchHomeTrendTools();
        const data = response?.data || {};
        trendMainTool.value = data.main_tool || null;
        const tools = Array.isArray(data.tools)
            ? data.tools.map(normalizeHomeTrendTool).filter((tool) => tool.id && tool.slug && tool.previewUrl)
            : [];
        const hydratedTools = await Promise.all(tools.map(async (tool) => {
            try {
                const blob = await trendServices.fetchProtectedImage(tool.previewUrl);
                const imageUrl = URL.createObjectURL(blob);
                trendObjectUrls.add(imageUrl);
                return { ...tool, imageUrl };
            } catch {
                return null;
            }
        }));

        trendTools.value = hydratedTools.filter(Boolean);
        trendCurrentPage.value = 0;
    } catch {
        trendMainTool.value = null;
        trendTools.value = [];
    } finally {
        trendToolsLoading.value = false;
        startTrendAutoplay();
    }
};

const removeBrokenTrendTool = (toolId) => {
    const tool = trendTools.value.find((item) => item.id === toolId);
    if (tool?.imageUrl) {
        URL.revokeObjectURL(tool.imageUrl);
        trendObjectUrls.delete(tool.imageUrl);
    }
    trendTools.value = trendTools.value.filter((item) => item.id !== toolId);
    trendCurrentPage.value = Math.min(trendCurrentPage.value, trendPageCount.value - 1);
};

const stopTrendAutoplay = () => {
    if (trendAutoplayTimer) window.clearInterval(trendAutoplayTimer);
    trendAutoplayTimer = 0;
};

const startTrendAutoplay = () => {
    stopTrendAutoplay();
    if (trendTools.value.length <= trendCardsPerView.value) return;

    trendAutoplayTimer = window.setInterval(() => {
        const nextPage = trendCurrentPage.value >= trendPageCount.value - 1
            ? 0
            : trendCurrentPage.value + 1;
        goToTrendPage(nextPage);
    }, 6000);
};

const updateTrendCardsPerView = () => {
    const width = window.innerWidth;
    trendCardsPerView.value = width < 640 ? 1 : width < 900 ? 2 : 3;
    trendCurrentPage.value = Math.min(trendCurrentPage.value, trendPageCount.value - 1);
    goToTrendPage(trendCurrentPage.value, "auto");
};

const goToTrendPage = (page, behavior = "smooth") => {
    const viewport = trendCarousel.value;
    const lastPage = Math.max(0, trendPageCount.value - 1);
    const nextPage = Math.min(Math.max(Number(page) || 0, 0), lastPage);
    trendCurrentPage.value = nextPage;

    if (!viewport) return;

    const maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
    const left = lastPage === 0 ? 0 : (nextPage / lastPage) * maxScroll;
    viewport.scrollTo({ left, behavior });
};

const moveTrendPage = (direction) => {
    const lastPage = Math.max(0, trendPageCount.value - 1);
    const nextPage = direction > 0 && trendCurrentPage.value >= lastPage
        ? 0
        : direction < 0 && trendCurrentPage.value === 0
            ? lastPage
            : trendCurrentPage.value + direction;
    goToTrendPage(nextPage);
    startTrendAutoplay();
};

const selectTrendPage = (page) => {
    goToTrendPage(page);
    startTrendAutoplay();
};

const syncTrendPage = () => {
    cancelAnimationFrame(trendScrollFrame);
    trendScrollFrame = requestAnimationFrame(() => {
        const viewport = trendCarousel.value;
        const lastPage = Math.max(0, trendPageCount.value - 1);
        if (!viewport || lastPage === 0) return;

        const maxScroll = Math.max(1, viewport.scrollWidth - viewport.clientWidth);
        trendCurrentPage.value = Math.min(
            lastPage,
            Math.max(0, Math.round((viewport.scrollLeft / maxScroll) * lastPage))
        );
    });
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
        5: "chat5",
        6: "chat6",
    };
    const chatPath = chatRouteMap[mainToolId] || "chat";
    const uuid = subTool?.uuid || subTool?.chat_uuid || null;
    const url = uuid
        ? `/${lang}/subtool/${subTool.slug}/${chatPath}/${uuid}`
        : `/${lang}/subtool/${subTool.slug}/${chatPath}`;
    return router.push(url);
};

const goToTrendTool = (tool) => {
    if (!tool?.slug) return;
    return router.push(`/${homeService.getLang()}/subtool/${tool.slug}/chat7`);
};

const handleLangChanged = async () => {
    locale.value = homeService.getLang();
    await Promise.all([fetchTools(), fetchAiMainModel(), fetchRandomSubTools(), fetchHomeTrendTools()]);
};

onMounted(async () => {
    await Promise.all([fetchTools(), fetchAiMainModel(), fetchRandomSubTools(), fetchHomeTrendTools()]);
    updateTrendCardsPerView();
    window.addEventListener("lang-changed", handleLangChanged);
    window.addEventListener("resize", updateTrendCardsPerView);
});

onUnmounted(() => {
    cancelAnimationFrame(trendScrollFrame);
    stopTrendAutoplay();
    clearTrendObjectUrls();
    window.removeEventListener("lang-changed", handleLangChanged);
    window.removeEventListener("resize", updateTrendCardsPerView);
});

watch(
    () => route.params.lang,
    async (nextLang, prevLang) => {
        if (!nextLang || nextLang === prevLang) return;
        locale.value = String(nextLang);
        await Promise.all([fetchTools(), fetchAiMainModel(), fetchRandomSubTools(), fetchHomeTrendTools()]);
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

.trend-tool-feature {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 108px minmax(0, 1fr) auto;
    align-items: center;
    gap: 24px;
    width: min(980px, 100%);
    min-height: 154px;
    margin: 0 auto 32px;
    padding: 22px 24px;
    overflow: hidden;
    border: 1px solid rgba(43, 166, 222, 0.3);
    border-radius: 28px;
    color: #154677;
    background:
        radial-gradient(circle at 90% 10%, rgba(98, 200, 240, 0.2), transparent 32%),
        linear-gradient(135deg, #ffffff, #f3faff);
    box-shadow: 0 18px 42px rgba(21, 70, 119, 0.1);
    text-decoration: none;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.trend-tool-feature:hover,
.trend-tool-feature:focus-visible {
    color: #154677;
    border-color: #2ba6de;
    outline: 3px solid rgba(43, 166, 222, 0.16);
    outline-offset: 3px;
    transform: translateY(-4px);
    box-shadow: 0 24px 52px rgba(21, 70, 119, 0.15);
}

.trend-tool-visual {
    position: relative;
    display: grid;
    width: 108px;
    height: 108px;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 26px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    box-shadow: 0 16px 30px rgba(21, 70, 119, 0.2);
    font-size: 38px;
}

.trend-tool-visual img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.trend-tool-copy {
    min-width: 0;
    display: grid;
    gap: 8px;
}

.trend-tool-title {
    color: #154677;
    font-size: clamp(22px, 2.4vw, 30px);
    font-weight: 950;
    line-height: 1.25;
}

.trend-tool-description {
    max-width: 650px;
    color: #5b6f84;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.75;
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.trend-tool-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 44px;
    padding: 10px 18px;
    border-radius: 14px;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    box-shadow: 0 11px 24px rgba(21, 70, 119, 0.2);
    font-size: 13px;
    font-weight: 900;
    white-space: nowrap;
}

.trend-tool-cta i {
    font-size: 20px;
}

[dir="rtl"] .trend-tool-cta i {
    transform: rotate(180deg);
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

    .trend-tool-feature {
        grid-template-columns: 88px minmax(0, 1fr) auto;
        gap: 18px;
        min-height: 134px;
        padding: 18px;
    }

    .trend-tool-visual {
        width: 88px;
        height: 88px;
        border-radius: 22px;
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

    .trend-tool-feature {
        grid-template-columns: 72px minmax(0, 1fr);
        gap: 14px;
        margin-bottom: 24px;
        padding: 16px;
        border-radius: 22px;
    }

    .trend-tool-visual {
        width: 72px;
        height: 72px;
        border-radius: 19px;
        font-size: 28px;
    }

    .trend-tool-title {
        font-size: 20px;
    }

    .trend-tool-description {
        font-size: 13px;
        line-height: 1.6;
    }

    .trend-tool-cta {
        grid-column: 1 / -1;
        width: 100%;
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
/* =========================
   DARK MODE
========================= */
html[data-theme="dark"] .home-shell,
html[data-theme="dark"] .home-tools-page {
    background: var(--theme-bg);
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .ai-premium-container,
html[data-theme="dark"] .tools-panel,
html[data-theme="dark"] .faq-section {
    background: var(--theme-surface);
    border-color: var(--theme-border);
    box-shadow: 0 18px 44px var(--theme-shadow);
}

html[data-theme="dark"] .ai-container-title,
html[data-theme="dark"] .ai-info-card h3,
html[data-theme="dark"] .ai-feature-chip strong,
html[data-theme="dark"] .tools-title,
html[data-theme="dark"] .popular-subtools-head h3,
html[data-theme="dark"] .popular-subtool-name,
html[data-theme="dark"] .tool-title,
html[data-theme="dark"] .faq-header h2,
html[data-theme="dark"] .faq-question {
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .ai-container-desc,
html[data-theme="dark"] .ai-info-card p,
html[data-theme="dark"] .ai-feature-chip span,
html[data-theme="dark"] .tools-subtitle,
html[data-theme="dark"] .popular-subtools-head p,
html[data-theme="dark"] .tool-description,
html[data-theme="dark"] .faq-header p,
html[data-theme="dark"] .faq-answer p {
    color: var(--theme-text-secondary);
}

html[data-theme="dark"] .tool-slug {
    color: var(--theme-text-muted);
}

html[data-theme="dark"] .ai-soft-badge,
html[data-theme="dark"] .tools-count,
html[data-theme="dark"] .tool-status.active {
    color: var(--theme-accent);
    background: rgba(43, 166, 222, 0.11);
    border-color: rgba(43, 166, 222, 0.24);
}

html[data-theme="dark"] .ai-info-card,
html[data-theme="dark"] .ai-feature-chip,
html[data-theme="dark"] .popular-subtool-card,
html[data-theme="dark"] .trend-tool-feature,
html[data-theme="dark"] .tool-card,
html[data-theme="dark"] .faq-item,
html[data-theme="dark"] .empty-state {
    background: var(--theme-surface-secondary);
    border-color: var(--theme-border);
    box-shadow: 0 12px 30px var(--theme-shadow);
}

html[data-theme="dark"] .popular-subtools-section {
    background: rgba(43, 166, 222, 0.055);
    border-color: var(--theme-border);
}

html[data-theme="dark"] .trend-tool-feature {
    color: var(--theme-text-primary);
    background:
        radial-gradient(circle at 90% 10%, rgba(43, 166, 222, 0.12), transparent 34%),
        var(--theme-surface-secondary);
}

html[data-theme="dark"] .trend-tool-title {
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .trend-tool-description {
    color: var(--theme-text-secondary);
}

html[data-theme="dark"] .popular-subtool-card:hover,
html[data-theme="dark"] .tool-card:hover,
html[data-theme="dark"] .faq-item.active {
    background: var(--theme-hover);
    border-color: rgba(43, 166, 222, 0.34);
    box-shadow: 0 18px 42px var(--theme-shadow);
}

html[data-theme="dark"] .ai-mini-logo,
html[data-theme="dark"] .tool-spark,
html[data-theme="dark"] .empty-icon,
html[data-theme="dark"] .faq-question i,
html[data-theme="dark"] .popular-subtool-icon {
    background: var(--theme-surface-elevated);
    color: var(--theme-accent);
    border-color: var(--theme-border);
}

html[data-theme="dark"] .tool-status.inactive {
    color: var(--theme-text-muted);
    background: var(--theme-surface-elevated);
    border-color: var(--theme-border);
}

html[data-theme="dark"] .skeleton-icon,
html[data-theme="dark"] .skeleton-line,
html[data-theme="dark"] .skeleton-popular-icon {
    background: var(--theme-surface-elevated);
}

/* =========================
   HOME TREND TOOLS CAROUSEL
========================= */
.home-trends-section {
    position: relative;
    z-index: 2;
    margin-bottom: 32px;
    padding: 26px 24px 22px;
    overflow: hidden;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 26px;
    background:
        radial-gradient(circle at 92% 8%, rgba(43, 166, 222, 0.15), transparent 32%),
        linear-gradient(135deg, rgba(21, 70, 119, 0.035), rgba(43, 166, 222, 0.075));
}

.home-trends-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.home-trends-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 7px;
    color: #2ba6de;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.home-trends-header h3 {
    margin: 0;
    color: #154677;
    font-size: clamp(22px, 2.2vw, 30px);
    font-weight: 950;
    line-height: 1.3;
}

.home-trends-slider {
    position: relative;
}

.home-trends-arrow {
    position: absolute;
    z-index: 5;
    top: 50%;
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    color: #154677;
    box-shadow: 0 10px 28px rgba(7, 25, 45, 0.2);
    transform: translateY(-50%);
    transition: color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.home-trends-arrow.is-previous {
    left: 12px;
}

.home-trends-arrow.is-next {
    right: 12px;
}

.home-trends-arrow:hover,
.home-trends-arrow:focus-visible {
    color: #2ba6de;
    border-color: #2ba6de;
    outline: none;
    transform: translateY(-50%) scale(1.07);
}

.home-trends-viewport {
    width: 100%;
    overflow-x: auto;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    touch-action: pan-x;
    -webkit-overflow-scrolling: touch;
}

.home-trends-viewport::-webkit-scrollbar {
    display: none;
}

.home-trends-track {
    display: flex;
    gap: 16px;
}

.home-trend-card {
    position: relative;
    flex: 0 0 calc((100% - 32px) / 3);
    min-width: 0;
    height: 250px;
    padding: 0;
    overflow: hidden;
    border: 0;
    border-radius: 23px;
    background: linear-gradient(145deg, #154677, #2ba6de);
    box-shadow: 0 16px 34px rgba(21, 70, 119, 0.14);
    color: #fff;
    cursor: pointer;
    scroll-snap-align: start;
    isolation: isolate;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.home-trend-card:not(.home-trend-skeleton):hover,
.home-trend-card:not(.home-trend-skeleton):focus-visible {
    transform: translateY(-5px);
    box-shadow: 0 22px 42px rgba(21, 70, 119, 0.22);
    outline: 3px solid rgba(43, 166, 222, 0.25);
    outline-offset: 3px;
}

.home-trend-card img,
.home-trend-overlay {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.home-trend-card img {
    object-fit: cover;
    transition: transform 0.5s ease;
}

.home-trend-card:hover img,
.home-trend-card:focus-visible img {
    transform: scale(1.055);
}

.home-trend-overlay {
    z-index: 1;
    background: linear-gradient(180deg, rgba(7, 25, 45, 0.06) 28%, rgba(7, 25, 45, 0.83) 100%);
}

.home-trend-name {
    position: absolute;
    z-index: 2;
    inset-inline: 20px;
    bottom: 19px;
    color: #fff;
    font-size: 20px;
    font-weight: 950;
    line-height: 1.4;
    text-align: start;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
}

.home-trend-skeleton {
    cursor: default;
    background: linear-gradient(100deg, #e5edf4 20%, #f5f8fb 40%, #e5edf4 60%);
    background-size: 220% 100%;
    animation: trendSkeleton 1.25s ease-in-out infinite;
}

.home-trend-skeleton-line {
    position: absolute;
    inset-inline: 20px;
    bottom: 22px;
    width: 58%;
    height: 18px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
}

.home-trends-dots {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 18px;
    direction: ltr;
}

.home-trends-dots button {
    width: 8px;
    height: 8px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: rgba(21, 70, 119, 0.22);
    transition: width 0.25s ease, background 0.25s ease;
}

.home-trends-dots button.active {
    width: 25px;
    background: #2ba6de;
}

@keyframes trendSkeleton {
    to {
        background-position: -220% 0;
    }
}

@media (max-width: 900px) {
    .home-trend-card {
        flex-basis: calc((100% - 16px) / 2);
        height: 230px;
    }
}

@media (max-width: 639px) {
    .home-trends-section {
        margin-inline: -4px;
        padding: 22px 16px 19px;
        border-radius: 22px;
    }

    .home-trends-header {
        align-items: center;
    }

    .home-trends-arrow {
        width: 40px;
        height: 40px;
    }

    .home-trends-arrow.is-previous {
        left: 8px;
    }

    .home-trends-arrow.is-next {
        right: 8px;
    }

    .home-trend-card {
        flex-basis: 100%;
        height: 225px;
    }

    .home-trend-name {
        font-size: 19px;
    }
}

html[data-theme="dark"] .home-trends-section {
    border-color: var(--theme-border);
    background:
        radial-gradient(circle at 92% 8%, rgba(43, 166, 222, 0.12), transparent 32%),
        var(--theme-surface-secondary);
}

html[data-theme="dark"] .home-trends-header h3 {
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .home-trends-arrow {
    color: var(--theme-text-primary);
    background: var(--theme-surface-elevated);
    border-color: var(--theme-border);
}
</style>
