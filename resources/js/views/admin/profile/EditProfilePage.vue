<template>
    <AdminLayout>
        <section class="mx-auto max-w-3xl space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-500">Admin Settings</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-950 dark:text-white">Update profile</h1>
            </div>

            <div v-if="loading" class="h-80 animate-pulse rounded-xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800"></div>

            <form v-else class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800" @submit.prevent="submit">
                <div class="grid gap-5">
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Name</span>
                        <input
                            v-model.trim="form.name"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500/15': errors.name }"
                            autocomplete="name"
                        />
                        <p v-if="errors.name" class="mt-2 text-sm font-medium text-red-600">{{ errors.name }}</p>
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Email</span>
                        <input
                            v-model.trim="form.email"
                            type="email"
                            class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-950 shadow-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500/15': errors.email }"
                            autocomplete="email"
                        />
                        <p v-if="errors.email" class="mt-2 text-sm font-medium text-red-600">{{ errors.email }}</p>
                    </label>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <RouterLink to="/admin/profile" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        Cancel
                    </RouterLink>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:translate-y-0"
                        :disabled="submitting"
                    >
                        <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Save changes
                    </button>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import profileService from "@/services/admin/profile/profileService";

const router = useRouter();
const loading = ref(true);
const submitting = ref(false);
const form = reactive({ name: "", email: "" });
const errors = reactive({ name: "", email: "" });

const clearErrors = () => {
    errors.name = "";
    errors.email = "";
};

const validate = () => {
    clearErrors();
    if (!form.name) errors.name = "Name is required.";
    if (!form.email) errors.email = "Email is required.";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email = "Enter a valid email address.";
    return !errors.name && !errors.email;
};

onMounted(async () => {
    try {
        const response = await profileService.getProfile();
        const user = response?.data?.user || {};
        form.name = user.name || "";
        form.email = user.email || "";
    } finally {
        loading.value = false;
    }
});

const submit = async () => {
    if (!validate()) return;

    submitting.value = true;
    try {
        const response = await profileService.updateProfile({ ...form });
        const user = response?.data?.user || { ...form, role: localStorage.getItem("user_role") };
        localStorage.setItem("user_data", JSON.stringify(user));
        if (user.role) localStorage.setItem("user_role", user.role);
        toastr.success(response?.message || "Profile updated successfully.");
        router.push("/admin/profile");
    } catch (error) {
        const validationErrors = error.response?.data?.errors || {};
        errors.name = validationErrors.name?.[0] || "";
        errors.email = validationErrors.email?.[0] || "";
    } finally {
        submitting.value = false;
    }
};
</script>
