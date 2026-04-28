<template>
    <AdminLayout>
        <section class="grid min-h-[60vh] place-items-center">
            <div class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-[#154677]/10 border-t-amber-500"></div>
                <h1 class="mt-5 text-xl font-bold text-[#154677] dark:text-white">Signing you out</h1>
            </div>
        </section>
    </AdminLayout>
</template>

<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import profileService from "@/services/admin/profile/profileService";

const router = useRouter();

const clearSession = () => {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("user_role");
    localStorage.removeItem("user_data");
};

onMounted(async () => {
    try {
        const response = await profileService.logout();
        toastr.success(response?.message || "Logged out successfully.");
    } catch (error) {
        if (error.response?.status !== 401) {
            toastr.info("Your local session has been cleared.");
        }
    } finally {
        clearSession();
        router.replace("/login");
    }
});
</script>
