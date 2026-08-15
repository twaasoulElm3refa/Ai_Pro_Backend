<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div>
                <span class="inline-flex items-center rounded-full bg-[#2ba6de]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#2ba6de]">
                    Edit Free AI Model
                </span>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677] dark:text-white">Update free AI model</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Refresh model details, image, status, or ordering.</p>
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

            <ModelForm
                v-else
                :form="form"
                :errors="errors"
                :submitting="submitting"
                submit-label="Save Changes"
                :cancel-to="{ name: 'admin.free-ai-models.index' }"
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
import freeAiModelService from "@/services/admin/freeAiModels/freeAiModelService";
import ModelForm from "./_Form.vue";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const submitting = ref(false);

const form = reactive({
    name: "",
    description: "",
    image: null,
    image_url: "",
    is_active: true,
    sort_order: "",
});

const errors = reactive({
    name: "",
    description: "",
    image: "",
    is_active: "",
    sort_order: "",
});

const updateForm = (value) => Object.assign(form, value);

const clearErrors = () => {
    Object.keys(errors).forEach((key) => {
        errors[key] = "";
    });
};

const validate = () => {
    clearErrors();
    if (!form.name.trim()) errors.name = "Model name is required.";
    if (form.description && form.description.length > 255) errors.description = "Description must be 255 characters or fewer.";
    if (form.sort_order !== "" && !Number.isInteger(Number(form.sort_order))) errors.sort_order = "Sort order must be an integer.";

    return !Object.values(errors).some(Boolean);
};

const loadModel = async () => {
    loading.value = true;
    try {
        const response = await freeAiModelService.getModel(route.params.id);
        const model = response?.data || {};
        form.name = model.name || "";
        form.description = model.description || "";
        form.image = null;
        form.image_url = model.image ? `/storage/${model.image}` : "";
        form.is_active = Boolean(model.is_active);
        form.sort_order = model.sort_order ?? "";
    } catch (error) {
        console.error("Failed to load free AI model:", error);
        router.push({ name: "admin.free-ai-models.index" });
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
        const response = await freeAiModelService.updateModel(route.params.id, {
            name: form.name,
            description: form.description,
            image: form.image,
            is_active: form.is_active,
            sort_order: form.sort_order,
        });

        toastr.success(response?.message || "Free AI model updated successfully.");
        router.push({ name: "admin.free-ai-models.index" });
    } catch (error) {
        applyServerErrors(error);
        console.error("Update free AI model failed:", error);
    } finally {
        submitting.value = false;
    }
};

onMounted(loadModel);
</script>
