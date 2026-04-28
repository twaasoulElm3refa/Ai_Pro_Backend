<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#2ba6de]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677] dark:bg-[#2ba6de]/100/10 dark:text-[#2ba6de]">
                        Create Tool
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677] dark:text-white">Add a new tool</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Set up the core details and publish a new tool into the admin catalog.</p>
                </div>
            </div>

            <ToolFormCard
                :form="form"
                :errors="errors"
                :submitting="submitting"
                submit-label="Create Tool"
                :cancel-to="{ name: 'admin.tools.index' }"
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
import ToolFormCard from "@/components/admin/tools/ToolFormCard.vue";
import toolService from "@/services/admin/tools/toolService";

const router = useRouter();
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
        const response = await toolService.createTool({
            name: form.name,
            description: form.description,
            sort_order: form.sort_order,
            image: form.image,
        });

        const tool = response?.data;
        toastr.success(response?.message || "Tool created successfully.");
        router.push({ name: "admin.tools.show", params: { id: tool.id } });
    } catch (error) {
        applyServerErrors(error);
        console.error("Create tool failed:", error);
    } finally {
        submitting.value = false;
    }
};
</script>
