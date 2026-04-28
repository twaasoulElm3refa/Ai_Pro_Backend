<template>
    <AdminLayout>
        <section class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                        Tool Details
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677] dark:text-white">
                        {{ loadingTool ? "Loading tool..." : tool.name || "Tool" }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Review the main tool and manage all related subtools from one place.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <RouterLink
                        :to="{ name: 'admin.tools.index' }"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-[#154677]/10 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Back to Tools
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'admin.tools.edit', params: { id: route.params.id } }"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#154677] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#2ba6de] dark:bg-[#2ba6de] dark:text-[#154677] dark:hover:bg-[#2ba6de]"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Edit Tool
                    </RouterLink>
                </div>
            </div>

            <div v-if="loadingTool" class="grid gap-6 xl:grid-cols-[1fr_1.25fr]">
                <div class="h-80 animate-pulse rounded-2xl border border-[#154677]/10 bg-white dark:border-slate-800 dark:bg-[#154677]"></div>
                <div class="h-80 animate-pulse rounded-2xl border border-[#154677]/10 bg-white dark:border-slate-800 dark:bg-[#154677]"></div>
            </div>

            <div v-else class="grid gap-6 xl:grid-cols-[1fr_1.25fr]">
                <article class="overflow-hidden rounded-2xl border border-[#154677]/10 bg-white shadow-sm shadow-[#154677]/10 dark:border-slate-800 dark:bg-[#154677] dark:shadow-none">
                    <div class="h-80 bg-slate-100 dark:bg-slate-900">
                        <img v-if="tool.image_url" :src="tool.image_url" :alt="tool.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full items-center justify-center text-slate-400 dark:text-slate-600">
                            <i class="bi bi-grid text-6xl"></i>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10 dark:border-slate-800 dark:bg-[#154677] dark:shadow-none">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="item in toolDetails" :key="item.label" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                            <p class="mt-2 break-words text-sm font-semibold text-[#154677] dark:text-slate-100">{{ item.value }}</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-[#154677]/10 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</p>
                        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
                            {{ tool.description || "No description has been added for this tool." }}
                        </p>
                    </div>
                </article>
            </div>

            <section class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10 dark:border-slate-800 dark:bg-[#154677] dark:shadow-none">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-[#154677] dark:text-white">Subtools</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create, edit, and remove subtools linked to this main tool.</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#154677] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#2ba6de] dark:bg-[#2ba6de] dark:text-[#154677] dark:hover:bg-[#2ba6de]"
                        @click="startCreate"
                    >
                        <i class="bi bi-plus-lg"></i>
                        New Subtool
                    </button>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <div v-if="loadingSubtools" class="grid gap-4 md:grid-cols-2">
                            <div v-for="item in 4" :key="item" class="h-48 animate-pulse rounded-2xl border border-[#154677]/10 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"></div>
                        </div>

                        <div v-else-if="subtools.length" class="grid gap-4 md:grid-cols-2">
                            <article
                                v-for="subtool in subtools"
                                :key="subtool.id"
                                class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-lg font-semibold text-[#154677] dark:text-white">{{ subtool.name }}</h3>
                                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">{{ subtool.slug || "No slug" }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="subtool.is_active ? 'bg-emerald-100 text-[#154677] dark:bg-[#2ba6de]/100/10 dark:text-[#2ba6de]' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300'">
                                        {{ subtool.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </div>

                                <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{ subtool.description || "No description available." }}
                                </p>

                                <div class="mt-4 rounded-2xl border border-[#154677]/10 bg-white p-4 text-sm dark:border-slate-800 dark:bg-[#154677]">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Prompt Placeholder</p>
                                    <p class="mt-2 text-slate-600 dark:text-slate-300">{{ subtool.prompt_placeholder || "-" }}</p>
                                </div>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-xl border border-[#154677]/10 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-[#154677]"
                                        @click="viewSubtool(subtool.id)"
                                    >
                                        <i class="bi bi-eye"></i>
                                        View
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-xl bg-[#2ba6de]/10 px-3 py-2 text-sm font-semibold text-[#154677] transition hover:bg-[#2ba6de]/15 dark:bg-[#2ba6de]/10 dark:text-[#2ba6de] dark:hover:bg-[#2ba6de]/20"
                                        @click="startEdit(subtool)"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-[#154677] transition hover:bg-slate-200 dark:bg-slate-1000/10 dark:text-slate-200 dark:hover:bg-slate-1000/20"
                                        @click="removeSubtool(subtool)"
                                    >
                                        <i class="bi bi-trash3"></i>
                                        Delete
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-500 shadow-sm dark:bg-[#154677] dark:text-slate-300">
                                <i class="bi bi-diagram-3"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-[#154677] dark:text-white">No subtools yet</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create the first subtool for this main tool to start building its workflow.</p>
                        </div>
                    </div>

                    <aside class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-[#154677] dark:text-white">{{ editor.mode === "edit" ? "Edit Subtool" : "Create Subtool" }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ editor.mode === "edit" ? "Update the selected subtool." : "Add a new subtool for this main tool." }}
                                </p>
                            </div>
                            <button
                                v-if="editor.mode === 'edit'"
                                type="button"
                                class="rounded-xl border border-[#154677]/10 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-white dark:border-slate-700 dark:text-slate-300 dark:hover:bg-[#154677]"
                                @click="resetEditor"
                            >
                                Reset
                            </button>
                        </div>

                        <form class="mt-5 space-y-4" @submit.prevent="submitSubtool">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Name</label>
                                <input v-model.trim="editor.form.name" type="text" class="w-full rounded-2xl border border-[#154677]/10 bg-white px-4 py-3 text-sm outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-[#154677] dark:text-white dark:focus:border-slate-500 dark:focus:ring-slate-800" />
                                <p v-if="editor.errors.name" class="mt-2 text-sm text-[#154677]">{{ editor.errors.name }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Description</label>
                                <textarea v-model.trim="editor.form.description" rows="4" class="w-full rounded-2xl border border-[#154677]/10 bg-white px-4 py-3 text-sm outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-[#154677] dark:text-white dark:focus:border-slate-500 dark:focus:ring-slate-800"></textarea>
                                <p v-if="editor.errors.description" class="mt-2 text-sm text-[#154677]">{{ editor.errors.description }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Prompt Placeholder</label>
                                <input v-model.trim="editor.form.prompt_placeholder" type="text" class="w-full rounded-2xl border border-[#154677]/10 bg-white px-4 py-3 text-sm outline-none transition focus:border-slate-400 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-[#154677] dark:text-white dark:focus:border-slate-500 dark:focus:ring-slate-800" />
                                <p v-if="editor.errors.prompt_placeholder" class="mt-2 text-sm text-[#154677]">{{ editor.errors.prompt_placeholder }}</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Image</label>
                                <input type="file" accept="image/*" class="block w-full rounded-2xl border border-[#154677]/10 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-xl file:border-0 file:bg-[#154677] file:px-3 file:py-2 file:text-white dark:border-slate-700 dark:bg-[#154677] dark:text-white dark:file:bg-[#2ba6de] dark:file:text-[#154677]" @change="onFileChange" />
                                <p v-if="editor.errors.image" class="mt-2 text-sm text-[#154677]">{{ editor.errors.image }}</p>
                            </div>

                            <div v-if="editor.previewUrl" class="overflow-hidden rounded-2xl border border-[#154677]/10 bg-white dark:border-slate-700 dark:bg-[#154677]">
                                <img :src="editor.previewUrl" alt="Subtool preview" class="h-44 w-full object-cover" />
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#154677] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#2ba6de] disabled:cursor-not-allowed disabled:opacity-70 dark:bg-[#2ba6de] dark:text-[#154677] dark:hover:bg-[#2ba6de]"
                                :disabled="submittingSubtool"
                            >
                                <span v-if="submittingSubtool" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white dark:border-slate-950/30 dark:border-t-slate-950"></span>
                                {{ editor.mode === "edit" ? "Save Changes" : "Create Subtool" }}
                            </button>
                        </form>

                        <div v-if="selectedSubtool" class="mt-6 rounded-2xl border border-[#154677]/10 bg-white p-4 dark:border-slate-700 dark:bg-[#154677]">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Selected Subtool</p>
                            <h4 class="mt-3 text-base font-semibold text-[#154677] dark:text-white">{{ selectedSubtool.name }}</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ selectedSubtool.description || "No description available." }}</p>
                        </div>
                    </aside>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import toolService from "@/services/admin/tools/toolService";
import subToolService from "@/services/admin/subTools/subToolService";

const route = useRoute();
const router = useRouter();

const loadingTool = ref(true);
const loadingSubtools = ref(true);
const submittingSubtool = ref(false);
const tool = ref({});
const subtools = ref([]);
const selectedSubtool = ref(null);

const editor = reactive({
    mode: "create",
    id: null,
    form: {
        name: "",
        description: "",
        prompt_placeholder: "",
        image: null,
    },
    previewUrl: "",
    errors: {
        name: "",
        description: "",
        prompt_placeholder: "",
        image: "",
    },
});

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

const mapSubtool = (payload = {}) => ({
    ...payload,
    image_url: payload?.image ? `/storage/${payload.image}` : "",
});

const toolDetails = computed(() => [
    { label: "ID", value: tool.value.id ?? "-" },
    { label: "Name", value: tool.value.name || "-" },
    { label: "Slug", value: tool.value.slug || "-" },
    { label: "Sort Order", value: tool.value.sort_order ?? 0 },
    { label: "Created At", value: formatDate(tool.value.created_at) },
    { label: "Updated At", value: formatDate(tool.value.updated_at) },
]);

const clearErrors = () => {
    Object.keys(editor.errors).forEach((key) => {
        editor.errors[key] = "";
    });
};

const resetEditor = () => {
    editor.mode = "create";
    editor.id = null;
    editor.form.name = "";
    editor.form.description = "";
    editor.form.prompt_placeholder = "";
    editor.form.image = null;
    editor.previewUrl = "";
    clearErrors();
};

const applyServerErrors = (error) => {
    const serverErrors = error.response?.data?.errors || {};
    Object.keys(editor.errors).forEach((key) => {
        editor.errors[key] = serverErrors[key]?.[0] || "";
    });
};

const validateEditor = () => {
    clearErrors();

    if (!editor.form.name) editor.errors.name = "Name is required.";
    if (editor.form.description && editor.form.description.length < 5) editor.errors.description = "Description must be at least 5 characters.";
    if (editor.form.prompt_placeholder && editor.form.prompt_placeholder.length < 5) editor.errors.prompt_placeholder = "Prompt placeholder must be at least 5 characters.";

    return !Object.values(editor.errors).some(Boolean);
};

const loadTool = async () => {
    loadingTool.value = true;
    try {
        const response = await toolService.getTool(route.params.id);
        const payload = response?.data || {};
        tool.value = {
            ...payload,
            image_url: payload?.image ? `/storage/${payload.image}` : "",
        };
    } catch (error) {
        console.error("Failed to fetch tool:", error);
        router.push({ name: "admin.tools.index" });
    } finally {
        loadingTool.value = false;
    }
};

const loadSubtools = async () => {
    loadingSubtools.value = true;
    try {
        const response = await subToolService.index(route.params.id);
        const payload = response?.data;
        const rows = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : [];
        subtools.value = rows.map(mapSubtool);
    } catch (error) {
        console.error("Failed to fetch subtools:", error);
        subtools.value = [];
    } finally {
        loadingSubtools.value = false;
    }
};

const viewSubtool = async (id) => {
    try {
        const response = await subToolService.show(id);
        selectedSubtool.value = mapSubtool(response?.data || {});
    } catch (error) {
        console.error("Failed to load subtool:", error);
    }
};

const startCreate = () => {
    resetEditor();
    selectedSubtool.value = null;
};

const startEdit = async (subtool) => {
    try {
        const response = await subToolService.show(subtool.id);
        const payload = mapSubtool(response?.data || {});
        selectedSubtool.value = payload;
        editor.mode = "edit";
        editor.id = payload.id;
        editor.form.name = payload.name || "";
        editor.form.description = payload.description || "";
        editor.form.prompt_placeholder = payload.prompt_placeholder || "";
        editor.form.image = null;
        editor.previewUrl = payload.image_url || "";
        clearErrors();
    } catch (error) {
        console.error("Failed to prepare subtool edit:", error);
    }
};

const onFileChange = (event) => {
    const [file] = event.target.files || [];
    editor.form.image = file || null;
    editor.previewUrl = file ? URL.createObjectURL(file) : editor.previewUrl;
};

const submitSubtool = async () => {
    if (!validateEditor()) return;

    submittingSubtool.value = true;
    try {
        const payload = {
            name: editor.form.name,
            description: editor.form.description,
            prompt_placeholder: editor.form.prompt_placeholder,
            image: editor.form.image,
        };

        const response = editor.mode === "edit"
            ? await subToolService.update(editor.id, payload)
            : await subToolService.store(route.params.id, payload);

        toastr.success(response?.message || (editor.mode === "edit" ? "Subtool updated successfully." : "Subtool created successfully."));
        await loadSubtools();
        if (editor.mode === "edit" && editor.id) {
            await viewSubtool(editor.id);
        }
        resetEditor();
    } catch (error) {
        applyServerErrors(error);
        console.error("Failed to save subtool:", error);
    } finally {
        submittingSubtool.value = false;
    }
};

const removeSubtool = async (subtool) => {
    if (!window.confirm(`Delete ${subtool.name}?`)) return;

    try {
        const response = await subToolService.destroy(subtool.id);
        toastr.success(response?.message || "Subtool deleted successfully.");
        if (selectedSubtool.value?.id === subtool.id) {
            selectedSubtool.value = null;
        }
        await loadSubtools();
        if (editor.id === subtool.id) {
            resetEditor();
        }
    } catch (error) {
        console.error("Failed to delete subtool:", error);
    }
};

onMounted(async () => {
    await Promise.all([loadTool(), loadSubtools()]);
});
</script>
