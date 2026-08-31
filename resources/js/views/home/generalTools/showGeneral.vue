<template>
    <section class="general-tools-page" :dir="isArabic ? 'rtl' : 'ltr'">
        <div class="general-tools-container">
            <header class="page-header">
                <span class="page-kicker">
                    <i class="bi bi-chat-square-dots"></i>
                    {{ copy.kicker }}
                </span>
                <h1>{{ copy.title }}</h1>
                <p>{{ copy.description }}</p>
            </header>

            <div v-if="loading" class="tools-grid" aria-live="polite" :aria-label="copy.loading">
                <article v-for="item in 8" :key="item" class="tool-card skeleton-card" aria-hidden="true">
                    <div class="skeleton-icon"></div>
                    <div class="skeleton-line skeleton-line-wide"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-footer">
                        <div class="skeleton-pill"></div>
                        <div class="skeleton-button"></div>
                    </div>
                </article>
            </div>

            <div v-else-if="hasError" class="state-card" role="alert">
                <span class="state-icon state-icon-error"><i class="bi bi-exclamation-triangle"></i></span>
                <h2>{{ copy.errorTitle }}</h2>
                <p>{{ copy.errorText }}</p>
                <button type="button" class="primary-button" @click="loadTools">
                    <i class="bi bi-arrow-clockwise"></i>
                    {{ copy.retry }}
                </button>
            </div>

            <div v-else-if="!tools.length" class="state-card">
                <span class="state-icon"><i class="bi bi-chat-square-text"></i></span>
                <h2>{{ copy.emptyTitle }}</h2>
                <p>{{ copy.emptyText }}</p>
            </div>

            <div v-else class="tools-grid">
                <article v-for="tool in tools" :key="tool.id || tool.slug" class="tool-card">
                    <div class="card-heading">
                        <span class="tool-icon"><i class="bi bi-stars"></i></span>
                        <div class="card-badges">
                            <span class="tier-badge" :class="`tier-${tool.tier}`">
                                {{ tierLabel(tool.tier) }}
                            </span>
                            <span class="access-badge" :class="tool.is_free ? 'is-free' : 'is-paid'">
                                <i :class="tool.is_free ? 'bi bi-unlock' : 'bi bi-lock'" aria-hidden="true"></i>
                                {{ tool.is_free ? copy.free : copy.paid }}
                            </span>
                        </div>
                    </div>

                    <div class="card-content">
                        <h2>{{ tool.name }}</h2>
                        <p v-if="showMetaName(tool)" class="meta-name">{{ tool.meta_name }}</p>
                        <p class="tool-summary">{{ copy.toolSummary }}</p>
                    </div>

                    <footer class="card-footer">
                        <span class="active-state">
                            <span class="active-dot"></span>
                            {{ copy.available }}
                        </span>
                        <button type="button" class="show-button" @click="openTool(tool)">
                            {{ copy.show }}
                            <i :class="isArabic ? 'bi bi-arrow-left' : 'bi bi-arrow-right'" aria-hidden="true"></i>
                        </button>
                    </footer>
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
import generalChatService from "@/services/generalChats/generalChatService";

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const loading = ref(true);
const hasError = ref(false);
const tools = ref([]);

const isArabic = computed(
    () => String(locale.value || route.params.lang || homeService.getLang() || "en").toLowerCase() === "ar"
);

const copy = computed(() =>
    isArabic.value
        ? {
              kicker: "محادثات الذكاء الاصطناعي",
              title: "اختر نموذج المحادثة المناسب",
              description: "نماذج ذكية لمحادثات أسرع وأكثر مرونة، مرتبة حسب مستوى الاستخدام.",
              loading: "جارٍ تحميل نماذج المحادثة",
              errorTitle: "تعذر تحميل النماذج",
              errorText: "حدثت مشكلة أثناء تحميل نماذج المحادثة. يمكنك المحاولة مرة أخرى.",
              retry: "إعادة المحاولة",
              emptyTitle: "لا توجد نماذج متاحة حالياً",
              emptyText: "ستظهر نماذج المحادثة النشطة هنا فور توفرها.",
              toolSummary: "نموذج محادثة ذكي جاهز لمساعدتك في مهامك اليومية.",
              free: "مجاني",
              paid: "مدفوع",
              available: "متاح",
              show: "عرض",
              tiers: { free: "مجاني", standard: "قياسي", advanced: "متقدم" },
          }
        : {
              kicker: "AI conversations",
              title: "Choose your general chat model",
              description: "Flexible AI models for faster conversations, organized by access tier.",
              loading: "Loading general chat models",
              errorTitle: "Could not load the models",
              errorText: "There was a problem loading the chat models. You can try again.",
              retry: "Try again",
              emptyTitle: "No models are available",
              emptyText: "Active general chat models will appear here when available.",
              toolSummary: "A capable AI chat model ready to help with your everyday tasks.",
              free: "Free",
              paid: "Paid",
              available: "Available",
              show: "Show",
              tiers: { free: "Free", standard: "Standard", advanced: "Advanced" },
          }
);

const normalizeBoolean = (value) => value === true || value === 1 || value === "1";

const normalizeTool = (tool = {}) => ({
    id: tool.id ?? null,
    main_tool_id: tool.main_tool_id ?? null,
    name: String(tool.name || tool.meta_name || tool.slug || "General Chat"),
    meta_name: String(tool.meta_name || ""),
    slug: String(tool.slug || "").trim(),
    prompt_placeholder: String(tool.prompt_placeholder || ""),
    is_active: normalizeBoolean(tool.is_active),
    tier: ["free", "standard", "advanced"].includes(String(tool.tier || "").toLowerCase())
        ? String(tool.tier).toLowerCase()
        : "standard",
    is_free: normalizeBoolean(tool.is_free),
});

const loadTools = async () => {
    loading.value = true;
    hasError.value = false;

    try {
        const rows = await generalChatService.getTools();
        tools.value = rows.map(normalizeTool).filter((tool) => tool.is_active && tool.slug);
    } catch {
        tools.value = [];
        hasError.value = true;
    } finally {
        loading.value = false;
    }
};

const showMetaName = (tool) => {
    const metaName = String(tool.meta_name || "").trim().toLocaleLowerCase();
    return Boolean(metaName) && metaName !== String(tool.name || "").trim().toLocaleLowerCase();
};

const tierLabel = (tier) => copy.value.tiers[tier] || copy.value.tiers.standard;

const openTool = async (tool) => {
    try {
        sessionStorage.setItem(`general-chat-tool:${tool.slug}`, JSON.stringify(tool));
    } catch {
        // Router state still carries the selection when storage is unavailable.
    }

    await router.push({
        name: "general-chat",
        params: {
            lang: String(route.params.lang || homeService.getLang()),
            slug: tool.slug,
        },
        state: { generalChatTool: tool },
    });
};

const syncLocale = () => {
    locale.value = String(route.params.lang || homeService.getLang() || "en").toLowerCase();
};

const handleLangChanged = async () => {
    syncLocale();
    await loadTools();
};

onMounted(async () => {
    syncLocale();
    window.addEventListener("lang-changed", handleLangChanged);
    await loadTools();
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => route.params.lang,
    async (nextLang, previousLang) => {
        if (!nextLang || nextLang === previousLang) return;
        syncLocale();
        await loadTools();
    }
);
</script>

<style scoped>
.general-tools-page {
    min-height: 100vh;
    padding: 124px 0 56px;
    color: var(--theme-text-primary);
    background:
        radial-gradient(circle at 12% 8%, rgba(43, 166, 222, 0.12), transparent 28%),
        radial-gradient(circle at 88% 2%, rgba(21, 70, 119, 0.1), transparent 25%),
        var(--theme-bg);
}

.general-tools-container {
    width: min(1240px, calc(100% - 32px));
    margin: 0 auto;
}

.page-header {
    max-width: 720px;
    margin-bottom: 30px;
}

.page-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 13px;
    border: 1px solid var(--theme-border-strong);
    border-radius: 999px;
    color: var(--theme-accent);
    background: var(--theme-surface-secondary);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.page-header h1 {
    margin: 16px 0 8px;
    color: var(--theme-text-primary);
    font-size: clamp(2rem, 4vw, 3.25rem);
    font-weight: 900;
    line-height: 1.2;
}

.page-header p {
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 16px;
    line-height: 1.75;
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
}

.tool-card {
    position: relative;
    min-width: 0;
    min-height: 292px;
    display: flex;
    flex-direction: column;
    padding: 20px;
    overflow: hidden;
    border: 1px solid var(--theme-border);
    border-radius: 22px;
    background: linear-gradient(155deg, var(--theme-surface), var(--theme-surface-secondary));
    box-shadow: 0 14px 35px var(--theme-shadow);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.tool-card::before {
    content: "";
    position: absolute;
    inset-block-start: 0;
    inset-inline: 22px;
    height: 3px;
    border-radius: 0 0 999px 999px;
    background: linear-gradient(90deg, var(--theme-primary), var(--theme-accent));
    opacity: 0.85;
}

.tool-card:hover {
    transform: translateY(-4px);
    border-color: var(--theme-border-strong);
    box-shadow: 0 20px 44px var(--theme-shadow);
}

.card-heading,
.card-footer,
.card-badges {
    display: flex;
    align-items: center;
}

.card-heading {
    justify-content: space-between;
    gap: 12px;
}

.tool-icon,
.state-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: #ffffff;
    background: linear-gradient(145deg, #154677, #2ba6de);
    box-shadow: 0 10px 24px rgba(21, 70, 119, 0.18);
}

.tool-icon {
    width: 48px;
    height: 48px;
    border-radius: 15px;
    font-size: 19px;
}

.card-badges {
    min-width: 0;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 6px;
}

.tier-badge,
.access-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 8px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    background: var(--theme-surface-elevated);
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
}

.tier-free,
.access-badge.is-free {
    color: var(--app-success);
}

.tier-standard {
    color: var(--theme-accent);
}

.tier-advanced {
    color: var(--theme-primary);
}

.access-badge.is-paid {
    color: var(--theme-text-muted);
}

.card-content {
    padding: 26px 0 22px;
}

.card-content h2 {
    margin: 0;
    overflow-wrap: anywhere;
    color: var(--theme-text-primary);
    font-size: 18px;
    font-weight: 850;
    line-height: 1.45;
}

.meta-name {
    margin: 5px 0 0;
    color: var(--theme-accent);
    font-size: 12px;
    font-weight: 700;
}

.tool-summary {
    margin: 12px 0 0;
    color: var(--theme-text-secondary);
    font-size: 13px;
    line-height: 1.65;
}

.card-footer {
    justify-content: space-between;
    gap: 12px;
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid var(--theme-border);
}

.active-state {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--theme-text-muted);
    font-size: 11px;
    font-weight: 700;
}

.active-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--app-success);
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.1);
}

.show-button,
.primary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    color: #ffffff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.show-button {
    padding: 8px 13px;
    border-radius: 11px;
    font-size: 12px;
}

.show-button:hover,
.primary-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(21, 70, 119, 0.2);
}

.show-button:focus-visible,
.primary-button:focus-visible {
    outline: 3px solid rgba(43, 166, 222, 0.28);
    outline-offset: 3px;
}

.state-card {
    max-width: 620px;
    margin: 54px auto;
    padding: 42px 28px;
    border: 1px dashed var(--theme-border-strong);
    border-radius: 24px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 18px 44px var(--theme-shadow);
    text-align: center;
}

.state-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    border-radius: 18px;
    font-size: 22px;
}

.state-icon-error {
    background: var(--theme-surface-secondary);
    color: var(--app-danger);
    box-shadow: none;
}

.state-card h2 {
    margin: 0 0 7px;
    font-size: 20px;
}

.state-card p {
    margin: 0;
    color: var(--theme-text-secondary);
    line-height: 1.7;
}

.primary-button {
    margin-top: 20px;
    padding: 10px 16px;
    border-radius: 12px;
    font-size: 13px;
}

.skeleton-card {
    pointer-events: none;
    animation: skeleton-pulse 1.25s ease-in-out infinite;
}

.skeleton-card::before {
    display: none;
}

.skeleton-icon,
.skeleton-line,
.skeleton-pill,
.skeleton-button {
    background: var(--theme-surface-elevated);
}

.skeleton-icon {
    width: 48px;
    height: 48px;
    border-radius: 15px;
}

.skeleton-line {
    width: 68%;
    height: 12px;
    margin-top: 14px;
    border-radius: 999px;
}

.skeleton-line-wide {
    width: 88%;
    height: 17px;
    margin-top: 34px;
}

.skeleton-footer {
    display: flex;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 18px;
    border-top: 1px solid var(--theme-border);
}

.skeleton-pill {
    width: 58px;
    height: 12px;
    border-radius: 999px;
}

.skeleton-button {
    width: 72px;
    height: 30px;
    border-radius: 10px;
}

@keyframes skeleton-pulse {
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.55;
    }
}

@media (max-width: 1120px) {
    .tools-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 840px) {
    .general-tools-page {
        padding-top: 108px;
    }

    .tools-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 580px) {
    .general-tools-page {
        padding: 98px 0 36px;
    }

    .general-tools-container {
        width: min(100% - 20px, 1240px);
    }

    .page-header {
        margin-bottom: 22px;
    }

    .page-header p {
        font-size: 14px;
    }

    .tools-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .tool-card {
        min-height: 270px;
        padding: 18px;
    }
}
</style>
