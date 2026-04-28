<template>
    <AdminLayout>
        <section class="mx-auto max-w-3xl space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2ba6de]">Admin Settings</p>
                <h1 class="mt-1 text-3xl font-bold text-[#154677] dark:text-white">Change password</h1>
            </div>

            <form class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800" @submit.prevent="submit">
                <div class="grid gap-5">
                    <label v-for="field in fields" :key="field.name" class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ field.label }}</span>
                        <div class="relative mt-2">
                            <input
                                v-model="form[field.name]"
                                :type="visible[field.name] ? 'text' : 'password'"
                                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 pr-12 text-[#154677] shadow-sm outline-none transition focus:border-[#2ba6de] focus:ring-4 focus:ring-[#2ba6de]/15 dark:border-slate-700 dark:bg-[#154677] dark:text-white"
                                :class="{ 'border-[#2ba6de] focus:border-[#2ba6de] focus:ring-[#2ba6de]/15': errors[field.name] }"
                                :autocomplete="field.autocomplete"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-[#154677] dark:hover:bg-[#2ba6de] dark:hover:text-white"
                                :title="visible[field.name] ? 'Hide password' : 'Show password'"
                                @click="visible[field.name] = !visible[field.name]"
                            >
                                <i class="bi" :class="visible[field.name] ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                        <p v-if="errors[field.name]" class="mt-2 text-sm font-medium text-[#154677]">{{ errors[field.name] }}</p>
                    </label>
                </div>

                <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-100 dark:bg-[#154677]/70 dark:text-slate-300 dark:ring-slate-800">
                    Use at least 8 characters with uppercase, lowercase, and a number.
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <RouterLink to="/admin/profile" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-[#2ba6de]">
                        Cancel
                    </RouterLink>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#154677] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#2ba6de] disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:translate-y-0"
                        :disabled="submitting"
                    >
                        <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Update password
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import profileService from "@/services/admin/profile/profileService";

const router = useRouter();
const submitting = ref(false);
const form = reactive({
    current_password: "",
    new_password: "",
    confirm_password: "",
});
const errors = reactive({
    current_password: "",
    new_password: "",
    confirm_password: "",
});
const visible = reactive({
    current_password: false,
    new_password: false,
    confirm_password: false,
});

const fields = [
    { name: "current_password", label: "Current password", autocomplete: "current-password" },
    { name: "new_password", label: "New password", autocomplete: "new-password" },
    { name: "confirm_password", label: "Confirm password", autocomplete: "new-password" },
];

const clearErrors = () => {
    Object.keys(errors).forEach((key) => {
        errors[key] = "";
    });
};

const validate = () => {
    clearErrors();
    if (!form.current_password) errors.current_password = "Current password is required.";
    if (!form.new_password) errors.new_password = "New password is required.";
    else if (form.new_password.length < 8) errors.new_password = "New password must be at least 8 characters.";
    else if (!/[a-z]/.test(form.new_password) || !/[A-Z]/.test(form.new_password) || !/\d/.test(form.new_password)) {
        errors.new_password = "Use uppercase, lowercase, and at least one number.";
    }
    if (!form.confirm_password) errors.confirm_password = "Please confirm your new password.";
    else if (form.confirm_password !== form.new_password) errors.confirm_password = "Passwords do not match.";
    return !Object.values(errors).some(Boolean);
};

const submit = async () => {
    if (!validate()) return;

    submitting.value = true;
    try {
        const response = await profileService.updatePassword({ ...form });
        toastr.success(response?.message || "Password updated successfully.");
        router.push("/admin/profile");
    } catch (error) {
        const validationErrors = error.response?.data?.errors || {};
        Object.keys(errors).forEach((key) => {
            errors[key] = validationErrors[key]?.[0] || "";
        });
    } finally {
        submitting.value = false;
    }
};
</script>
