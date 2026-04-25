<template>
    <AdminLayout>
        <section class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                        Tool Management
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 dark:text-white">Tools</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        Manage the tool catalog, review details, and keep the admin inventory clean and organized.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Tools</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ tools.length }}</p>
                    </div>
                    <RouterLink
                        :to="{ name: 'admin.tools.create' }"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                    >
                        <i class="bi bi-plus-lg"></i>
                        Create Tool
                    </RouterLink>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950 dark:shadow-none">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative w-full max-w-md">
                        <i class="bi bi-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input
                            v-model.trim="searchQuery"
                            type="search"
                            placeholder="Search tools by name or description"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-11 pr-4 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-amber-500 dark:focus:bg-slate-950 dark:focus:ring-amber-500/10"
                        />
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <i class="bi bi-funnel"></i>
                        UI search only
                    </div>
                </div>

                <div v-if="loading" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="item in 6" :key="item" class="animate-pulse rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div class="h-40 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
                        <div class="mt-4 h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-800"></div>
                        <div class="mt-3 h-3 w-full rounded bg-slate-200 dark:bg-slate-800"></div>
                        <div class="mt-2 h-3 w-4/5 rounded bg-slate-200 dark:bg-slate-800"></div>
                    </div>
                </div>

                <div v-else-if="filteredTools.length" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="tool in filteredTools"
                        :key="tool.id"
                        class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="relative h-48 overflow-hidden bg-slate-100 dark:bg-slate-900">
                            <img
                                v-if="tool.image_url"
                                :src="tool.image_url"
                                :alt="tool.name"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                            <div v-else class="flex h-full items-center justify-center text-slate-400 dark:text-slate-600">
                                <i class="bi bi-grid text-5xl"></i>
                            </div>
                        </div>

                        <div class="space-y-4 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="truncate text-lg font-bold text-slate-900 dark:text-white">{{ tool.name }}</h2>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">#{{ tool.id }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                    Sort {{ tool.sort_order ?? 0 }}
                                </span>
                            </div>

                            <p class="line-clamp-3 min-h-[4.5rem] text-sm leading-6 text-slate-500 dark:text-slate-400">
                                {{ tool.description || "No description available for this tool yet." }}
                            </p>

                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>{{ formatDate(tool.created_at) }}</span>
                                <span>{{ tool.slug || "No slug" }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 pt-2">
                                <RouterLink
                                    :to="{ name: 'admin.tools.show', params: { id: tool.id } }"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900"
                                >
                                    <i class="bi bi-eye"></i>
                                    View
                                </RouterLink>
                                <RouterLink
                                    :to="{ name: 'admin.tools.edit', params: { id: tool.id } }"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                    Edit
                                </RouterLink>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                                    @click="openDeleteModal(tool)"
                                >
                                    <i class="bi bi-trash3"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-500 shadow-sm dark:bg-slate-950 dark:text-slate-300">
                        <i class="bi bi-grid-3x3-gap text-2xl"></i>
                    </div>
                    <h2 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">
                        {{ tools.length ? "No matching tools" : "No tools yet" }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ tools.length ? "Try a different search term." : "Create your first tool to start building the catalog." }}
                    </p>
                    <RouterLink
                        v-if="!tools.length"
                        :to="{ name: 'admin.tools.create' }"
                        class="mt-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                    >
                        <i class="bi bi-plus-lg"></i>
                        Add Tool
                    </RouterLink>
                </div>
            </div>
        </section>

        <DeleteToolModal
            :open="deleteModal.open"
            :loading="deleting"
            :tool-name="deleteModal.tool?.name"
            @cancel="closeDeleteModal"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import DeleteToolModal from "@/components/admin/tools/DeleteToolModal.vue";
import toolService from "@/services/admin/tools/toolService";

const loading = ref(true);
const deleting = ref(false);
const tools = ref([]);
const searchQuery = ref("");
const deleteModal = reactive({
    open: false,
    tool: null,
});

const mapTool = (tool) => ({
    ...tool,
    image_url: tool?.image ? `http://localhost:8000/storage/${tool.image}` : "",
});

const filteredTools = computed(() => {
    const query = searchQuery.value.toLowerCase();

    if (!query) return tools.value;

    return tools.value.filter((tool) =>
        [tool.name, tool.description, tool.slug]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(query))
    );
});

const fetchTools = async () => {
    loading.value = true;
    try {
        const response = await toolService.getTools();
        const payload = response?.data;
        const rows = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : [];
        tools.value = rows.map(mapTool);
    } catch (error) {
        console.error("Failed to fetch tools:", error);
        tools.value = [];
    } finally {
        loading.value = false;
    }
};

const formatDate = (value) => {
    if (!value) return "Unknown date";
    return new Intl.DateTimeFormat("en", {
        year: "numeric",
        month: "short",
        day: "numeric",
    }).format(new Date(value));
};

const openDeleteModal = (tool) => {
    deleteModal.open = true;
    deleteModal.tool = tool;
};

const closeDeleteModal = () => {
    if (deleting.value) return;
    deleteModal.open = false;
    deleteModal.tool = null;
};

const confirmDelete = async () => {
    if (!deleteModal.tool) return;

    deleting.value = true;
    try {
        const response = await toolService.deleteTool(deleteModal.tool.id);
        tools.value = tools.value.filter((tool) => tool.id !== deleteModal.tool.id);
        toastr.success(response?.message || "Tool deleted successfully.");
        closeDeleteModal();
    } catch (error) {
        console.error("Delete failed:", error);
    } finally {
        deleting.value = false;
    }
};

onMounted(fetchTools);
</script>
