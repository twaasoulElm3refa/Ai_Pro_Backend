<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-500">Admin Profile</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-950 dark:text-white">Account overview</h1>
                </div>

                <RouterLink
                    to="/admin/profile/edit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300"
                >
                    <i class="bi bi-pencil-square"></i>
                    Edit Profile
                </RouterLink>
            </div>

            <div v-if="loading" class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
                <div class="h-72 animate-pulse rounded-xl bg-white/80 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900/70 dark:ring-slate-800"></div>
                <div class="h-72 animate-pulse rounded-xl bg-white/80 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900/70 dark:ring-slate-800"></div>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
                <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800">
                    <div class="flex flex-col items-center text-center">
                        <div class="grid h-24 w-24 place-items-center rounded-full bg-amber-100 text-3xl font-bold text-amber-700 ring-4 ring-white dark:bg-amber-500/15 dark:text-amber-300 dark:ring-slate-900">
                            {{ initials }}
                        </div>
                        <h2 class="mt-4 text-2xl font-bold text-slate-950 dark:text-white">{{ admin.name || "Admin" }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ admin.email }}</p>
                        <span class="mt-4 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">
                            {{ admin.role || "admin" }}
                        </span>
                    </div>
                </article>

                <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:shadow-lg dark:bg-slate-900 dark:ring-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
                        <h3 class="text-lg font-bold text-slate-950 dark:text-white">Profile details</h3>
                        <RouterLink to="/admin/password" class="text-sm font-semibold text-amber-600 transition hover:text-amber-700">
                            Change password
                        </RouterLink>
                    </div>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div v-for="item in profileItems" :key="item.label" class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-100 dark:bg-slate-950/60 dark:ring-slate-800">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ item.label }}</dt>
                            <dd class="mt-2 break-words text-sm font-semibold text-slate-950 dark:text-slate-100">{{ item.value || "-" }}</dd>
                        </div>
                    </dl>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import profileService from "@/services/admin/profile/profileService";

const admin = ref({});
const loading = ref(true);

const initials = computed(() => {
    const source = admin.value.name || admin.value.email || "A";
    return source
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join("");
});

const profileItems = computed(() => [
    { label: "Name", value: admin.value.name },
    { label: "Email", value: admin.value.email },
    { label: "Role", value: admin.value.role },
    { label: "Phone", value: admin.value.phone },
    { label: "Status", value: admin.value.is_active === false ? "Inactive" : "Active" },
    { label: "Last seen", value: formatDate(admin.value.last_seen) },
    { label: "Email verified", value: formatDate(admin.value.email_verified_at) },
    { label: "Created", value: formatDate(admin.value.created_at) },
]);

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

onMounted(async () => {
    try {
        const response = await profileService.getProfile();
        admin.value = response?.data?.user || {};
        localStorage.setItem("user_data", JSON.stringify(admin.value));
        if (admin.value.role) localStorage.setItem("user_role", admin.value.role);
    } finally {
        loading.value = false;
    }
});
</script>
