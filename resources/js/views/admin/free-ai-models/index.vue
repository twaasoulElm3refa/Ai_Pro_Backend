<template>
    <AdminLayout>
        <section class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#2ba6de]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#2ba6de]">
                        Model Management
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677] dark:text-white">Free AI Models</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        Manage the free model catalog, availability, and display order.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="rounded-2xl border border-[#154677]/10 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-[#154677]">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Models</p>
                        <p class="mt-1 text-2xl font-bold text-[#154677] dark:text-white">{{ models.length }}</p>
                    </div>
                    <RouterLink
                        :to="{ name: 'admin.free-ai-models.create' }"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#154677] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#2ba6de] dark:bg-[#2ba6de] dark:text-[#154677]"
                    >
                        <i class="bi bi-plus-lg"></i>
                        Create Model
                    </RouterLink>
                </div>
            </div>

            <div class="rounded-2xl border border-[#154677]/10 bg-white p-5 shadow-sm shadow-[#154677]/10 dark:border-slate-800 dark:bg-[#154677] dark:shadow-none">
                <div class="relative w-full max-w-md">
                    <i class="bi bi-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input
                        v-model.trim="searchQuery"
                        type="search"
                        placeholder="Search by name or slug"
                        class="w-full rounded-2xl border border-[#154677]/10 bg-slate-50 py-3 pl-11 pr-4 text-sm text-[#154677] outline-none transition focus:border-[#2ba6de] focus:bg-white focus:ring-4 focus:ring-[#2ba6de]/10 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <div v-if="loading" class="mt-6 space-y-3">
                    <div v-for="item in 5" :key="item" class="h-20 animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-900"></div>
                </div>

                <div v-else-if="loadError" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <i class="bi bi-exclamation-circle text-3xl text-slate-400"></i>
                    <h2 class="mt-4 text-lg font-bold text-[#154677] dark:text-white">Unable to load models</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ loadError }}</p>
                    <button type="button" class="theme-button mt-5 rounded-2xl px-5 py-2.5 text-sm font-semibold" @click="fetchModels">
                        Try Again
                    </button>
                </div>

                <div v-else-if="filteredModels.length" class="mt-6 overflow-x-auto rounded-2xl border border-[#154677]/10 dark:border-slate-800">
                    <table class="min-w-full divide-y divide-[#154677]/10 text-left text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">ID</th>
                                <th class="px-4 py-3 font-semibold">Image</th>
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Sort Order</th>
                                <th class="px-4 py-3 font-semibold">Slug</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#154677]/10 bg-white dark:divide-slate-800 dark:bg-[#154677]">
                            <tr v-for="model in filteredModels" :key="model.id" class="transition hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-500 dark:text-slate-300">#{{ model.id }}</td>
                                <td class="px-4 py-3">
                                    <img v-if="model.image_url" :src="model.image_url" :alt="model.name" class="h-12 w-12 rounded-xl object-cover" />
                                    <span v-else class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-900">
                                        <i class="bi bi-cpu"></i>
                                    </span>
                                </td>
                                <td class="max-w-xs px-4 py-3 font-semibold text-[#154677] dark:text-white">{{ model.name }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="model.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                    >
                                        {{ model.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-300">{{ model.sort_order ?? 0 }}</td>
                                <td class="max-w-xs px-4 py-3">
                                    <span class="block truncate font-mono text-xs text-slate-500 dark:text-slate-300" :title="model.slug">{{ model.slug }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <RouterLink
                                            :to="{ name: 'admin.free-ai-models.edit', params: { id: model.id } }"
                                            class="inline-flex items-center gap-2 rounded-xl bg-[#2ba6de]/10 px-3 py-2 font-semibold text-[#154677] transition hover:bg-[#2ba6de]/20 dark:text-[#2ba6de]"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                            Edit
                                        </RouterLink>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 font-semibold text-[#154677] transition hover:bg-slate-200 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                                            @click="openDeleteModal(model)"
                                        >
                                            <i class="bi bi-trash3"></i>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-500 shadow-sm dark:bg-[#154677] dark:text-slate-300">
                        <i class="bi bi-cpu text-2xl"></i>
                    </div>
                    <h2 class="mt-5 text-xl font-bold text-[#154677] dark:text-white">
                        {{ models.length ? "No matching models" : "No free AI models yet" }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ models.length ? "Try a different search term." : "Create your first model to start building the catalog." }}
                    </p>
                </div>
            </div>
        </section>

        <DeleteModal
            :open="deleteModal.open"
            :loading="deleting"
            :model-name="deleteModal.model?.name"
            @cancel="closeDeleteModal"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import freeAiModelService from "@/services/admin/freeAiModels/freeAiModelService";
import DeleteModal from "./_DeleteModal.vue";

const loading = ref(true);
const deleting = ref(false);
const loadError = ref("");
const models = ref([]);
const searchQuery = ref("");
const deleteModal = reactive({
    open: false,
    model: null,
});

const mapModel = (model) => ({
    ...model,
    image_url: model?.image ? `/storage/${model.image}` : "",
});

const filteredModels = computed(() => {
    const query = searchQuery.value.toLowerCase();
    if (!query) return models.value;

    return models.value.filter((model) =>
        [model.name, model.slug]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(query))
    );
});

const fetchModels = async () => {
    loading.value = true;
    loadError.value = "";

    try {
        const response = await freeAiModelService.getModels();
        models.value = (Array.isArray(response?.data) ? response.data : []).map(mapModel);
    } catch (error) {
        console.error("Failed to fetch free AI models:", error);
        models.value = [];
        loadError.value = error.response?.data?.message || "Please try again.";
    } finally {
        loading.value = false;
    }
};

const openDeleteModal = (model) => {
    deleteModal.open = true;
    deleteModal.model = model;
};

const closeDeleteModal = () => {
    if (deleting.value) return;
    deleteModal.open = false;
    deleteModal.model = null;
};

const confirmDelete = async () => {
    if (!deleteModal.model) return;

    deleting.value = true;
    try {
        const response = await freeAiModelService.deleteModel(deleteModal.model.id);
        models.value = models.value.filter((model) => model.id !== deleteModal.model.id);
        toastr.success(response?.message || "Free AI model deleted successfully.");
        deleting.value = false;
        closeDeleteModal();
    } catch (error) {
        console.error("Delete free AI model failed:", error);
    } finally {
        deleting.value = false;
    }
};

onMounted(fetchModels);
</script>
