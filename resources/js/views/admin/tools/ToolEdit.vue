<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#2ba6de]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#2ba6de] dark:bg-[#2ba6de]/10 dark:text-[#2ba6de]">
                        Edit Tool
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677] dark:text-white">Update tool</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Refresh metadata, image, or ordering without leaving the admin flow.</p>
                </div>
            </div>

            <div v-if="loading" class="animate-pulse rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-[#154677]">
                <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-4">
                        <div class="h-12 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
                        <div class="h-40 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
                    </div>
                    <div class="space-y-4">
                        <div class="h-12 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
                        <div class="h-48 rounded-2xl bg-slate-200 dark:bg-slate-800"></div>
                    </div>
                </div>
            </div>

            <ToolFormCard
                v-else
                :form="form"
                :errors="errors"
                :submitting="submitting"
                submit-label="Save Changes"
                :cancel-to="{ name: 'admin.tools.show', params: { id: route.params.id } }"
                @update:form="updateForm"
                @submit="submit"
            />
        </section>
    </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import ToolFormCard from "@/components/admin/tools/ToolFormCard.vue";
import toolService from "@/services/admin/tools/toolService";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const submitting = ref(false);

const form = reactive({
    name: "",
    description: "",
    sort_order: "",
    image: null,
    image_url: "",
});

const errors = reactive({
    name: "",
    description: "",
    sort_order: "",
    image: "",
});

const updateForm = (value) => {
    Object.assign(form, value);
};

const clearErrors = () => {
    Object.keys(errors).forEach((key) => {
        errors[key] = "";
    });
};

const validate = () => {
    clearErrors();

    if (!form.name) errors.name = "Tool name is required.";
    if (form.description && form.description.length > 255) errors.description = "Description must be 255 characters or fewer.";
    if (form.sort_order !== "" && Number.isNaN(Number(form.sort_order))) errors.sort_order = "Sort order must be a number.";

    return !Object.values(errors).some(Boolean);
};

const loadTool = async () => {
    loading.value = true;
    try {
        const response = await toolService.getTool(route.params.id);
        const tool = response?.data || {};
        form.name = tool.name || "";
        form.description = tool.description || "";
        form.sort_order = tool.sort_order ?? "";
        form.image = null;
        form.image_url = tool.image ? `http://localhost:8000/storage/${tool.image}` : "";
    } catch (error) {
        console.error("Failed to load tool:", error);
        router.push({ name: "admin.tools.index" });
    } finally {
        loading.value = false;
    }
};

const applyServerErrors = (error) => {
    const serverErrors = error.response?.data?.errors || {};
    Object.keys(errors).forEach((key) => {
        errors[key] = serverErrors[key]?.[0] || errors[key];
    });
};

const submit = async () => {
    if (!validate()) return;

    submitting.value = true;
    try {
        const response = await toolService.updateTool(route.params.id, {
            name: form.name,
            description: form.description,
            sort_order: form.sort_order,
            image: form.image,
        });

        toastr.success(response?.message || "Tool updated successfully.");
        router.push({ name: "admin.tools.show", params: { id: route.params.id } });
    } catch (error) {
        applyServerErrors(error);
        console.error("Update tool failed:", error);
    } finally {
        submitting.value = false;
    }
};

onMounted(loadTool);
</script>
