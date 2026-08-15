<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div>
                <span class="inline-flex items-center rounded-full bg-[#2ba6de]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677] dark:text-[#2ba6de]">
                    Create Free AI Model
                </span>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677] dark:text-white">Add a new free AI model</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Set up the core details and publish a model into the admin catalog.</p>
            </div>

            <ModelForm
                :form="form"
                :errors="errors"
                :submitting="submitting"
                submit-label="Create Model"
                :cancel-to="{ name: 'admin.free-ai-models.index' }"
                @update:form="updateForm"
                @submit="submit"
            />
        </section>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import freeAiModelService from "@/services/admin/freeAiModels/freeAiModelService";
import ModelForm from "./_Form.vue";

const router = useRouter();
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
        const response = await freeAiModelService.createModel({
            name: form.name,
            description: form.description,
            image: form.image,
            is_active: form.is_active,
            sort_order: form.sort_order,
        });

        toastr.success(response?.message || "Free AI model created successfully.");
        router.push({ name: "admin.free-ai-models.index" });
    } catch (error) {
        applyServerErrors(error);
        console.error("Create free AI model failed:", error);
    } finally {
        submitting.value = false;
    }
};
</script>
