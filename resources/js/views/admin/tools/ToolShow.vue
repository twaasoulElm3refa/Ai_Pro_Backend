<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                        Tool Details
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">
                        {{ loading ? "Loading tool..." : tool.name || "Tool" }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Full admin view for reviewing metadata, assets, and system details.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <RouterLink
                        :to="{ name: 'admin.tools.index' }"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Back to Tools
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'admin.tools.edit', params: { id: route.params.id } }"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Edit Tool
                    </RouterLink>
                </div>
            </div>

            <div v-if="loading" class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
                <div class="h-80 animate-pulse rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"></div>
                <div class="h-80 animate-pulse rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"></div>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950 dark:shadow-none">
                    <div class="h-80 bg-slate-100 dark:bg-slate-900">
                        <img v-if="tool.image_url" :src="tool.image_url" :alt="tool.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full items-center justify-center text-slate-400 dark:text-slate-600">
                            <i class="bi bi-grid text-6xl"></i>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950 dark:shadow-none">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="item in details"
                            :key="item.label"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                            <p class="mt-2 break-words text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.value }}</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</p>
                        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
                            {{ tool.description || "No description has been added for this tool." }}
                        </p>
                    </div>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import toolService from "@/services/admin/tools/toolService";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const tool = ref({});

const formatDate = (value) => {
    if (!value) return "-";
    return new Intl.DateTimeFormat("en", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
};

const details = computed(() => [
    { label: "ID", value: tool.value.id ?? "-" },
    { label: "Name", value: tool.value.name || "-" },
    { label: "Slug", value: tool.value.slug || "-" },
    { label: "Sort Order", value: tool.value.sort_order ?? 0 },
    { label: "Created At", value: formatDate(tool.value.created_at) },
    { label: "Updated At", value: formatDate(tool.value.updated_at) },
]);

const loadTool = async () => {
    loading.value = true;
    try {
        const response = await toolService.getTool(route.params.id);
        const payload = response?.data || {};
        tool.value = {
            ...payload,
            image_url: payload?.image ? `http://localhost:8000/storage/${payload.image}` : "",
        };
    } catch (error) {
        console.error("Failed to fetch tool:", error);
        router.push({ name: "admin.tools.index" });
    } finally {
        loading.value = false;
    }
};

onMounted(loadTool);
</script>
