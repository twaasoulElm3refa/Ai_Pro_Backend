<template>
    <section class="home-tools-page min-h-screen px-4 py-12 sm:px-6 lg:px-10">
        <div class="mx-auto max-w-7xl">

            <!-- HEADER -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <span class="badge">Tool Overview</span>
                    <h1 class="mt-6 text-4xl font-bold sm:text-5xl text-main">
                        {{ loading ? "Loading tool..." : tool.title }}
                    </h1>
                    <p class="mt-4 text-base sm:text-lg text-muted">
                        {{ tool.description }}
                    </p>
                </div>

                <button type="button" class="back-button" @click="router.push(`/${route.params.lang}`)">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back</span>
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

                <!-- MAIN TOOL HERO -->
                <!-- <article class="tool-hero-card">
                    <div class="tool-hero-image-wrap">
                        <img
                            v-if="tool.imageUrl"
                            :src="tool.imageUrl"
                            :alt="tool.title"
                            class="tool-hero-img"
                        />
                        <div v-else class="tool-hero-fallback">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                        </div>
                    </div>

                    <div class="tool-hero-info">
                        <h2 class="tool-hero-title">{{ tool.title }}</h2>
                        <p class="tool-hero-desc">{{ tool.description }}</p>
                    </div>
                </article> -->

                <!-- SUBTOOLS -->
                <div>
                    <div class="subtools-header">
                        <div>
                            <h2 class="section-title">Related Subtools</h2>
                            <p class="section-sub">Connected experiences you can chat with</p>
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
                                <div class="subtool-icon">
                                    <i class="bi bi-cpu"></i>
                                </div>
                                <span :class="['status-chip', subtool.is_active ? 'active' : 'inactive']">
                                    {{ subtool.is_active ? "Active" : "Inactive" }}
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
                                    class="chat-button"
                                    @click="router.push(`/${route.params.lang}/subtool/${subtool.slug}/chat`)"
                                >
                                    <i class="bi bi-chat-fill"></i>
                                    Chat
                                </button>
                            </div>
                        </article>
                    </TransitionGroup>

                    <div v-else class="empty-box mt-6">
                        <i class="bi bi-inbox text-2xl mb-2 block"></i>
                        No related subtools found
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import homeService from "@/services/home/homeService";

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const rawTool = ref({});

const fallbackText = {
    title: "Untitled Tool",
    description: "No description available",
};

const mapMainTool = (payload = {}) => {
    const translation = payload?.translation;
    return {
        ...payload,
        title: translation?.name || fallbackText.title,
        description: translation?.description || fallbackText.description,
        imageUrl: payload?.image ? `/storage/${payload.image}` : "",
    };
};

const mapSubtool = (payload = {}) => {
    return {
        id: payload?.id,
        slug: payload?.slug || "-",
        title: payload?.name || "Untitled",
        description: payload?.description || "No description",
        promptPlaceholder: payload?.prompt_placeholder || "",
        is_active: Boolean(payload?.is_active),
        providerName: payload?.provider?.name || "",
    };
};

const tool = computed(() => mapMainTool(rawTool.value));
const subtools = computed(() => (rawTool.value?.sub_tools || []).map(mapSubtool));

const loadTool = async () => {
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

onMounted(loadTool);
</script>

<style scoped>
.home-tools-page {
    background: #f8fafc;
}

/* TEXT */
.text-main { color: #154677; }
.text-muted { color: #6b7280; }

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
.back-button:hover { background: #2ba6de; }

/* ─── HERO CARD ─────────────────────────────── */
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

/* ─── SUBTOOLS SECTION ─────────────────────── */
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

/* ─── SUBTOOL CARD ─────────────────────────── */
.subtool-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.subtool-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(7, 67, 119, 0.1);
}

.subtool-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.subtool-icon {
    width: 40px;
    height: 40px;
    background: #eef3fa;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #154677;
    font-size: 18px;
}

.status-chip {
    padding: 3px 10px;
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

.subtool-title {
    font-size: 15px;
    font-weight: 700;
    color: #154677;
    margin: 0;
}

.subtool-slug {
    font-size: 11px;
    color: #9ca3af;
    margin: 0;
}

.subtool-desc {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.55;
    flex: 1;
}

/* FOOTER */
.subtool-footer {
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
}
</style>
