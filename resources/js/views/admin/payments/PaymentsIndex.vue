<template>
    <AdminLayout>
        <section class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#154677]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677]">
                        Payments Management
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677]">All Payments</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Review payment records, monitor statuses, and inspect user transactions across the admin dashboard.
                    </p>
                </div>

                <div class="rounded-2xl border border-[#154677]/10 bg-white px-5 py-4 shadow-sm shadow-[#154677]/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Payments</p>
                    <p class="mt-1 text-2xl font-bold text-[#154677]">{{ pagination.total }}</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-[#154677]/10 bg-white shadow-sm shadow-[#154677]/10">
                <div class="border-b border-[#154677]/10 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center gap-3 text-sm font-semibold text-[#154677]">
                        <i class="bi bi-credit-card-2-front"></i>
                        Payment Records
                    </div>
                </div>

                <div v-if="loading" class="space-y-3 p-6">
                    <div v-for="item in 6" :key="item" class="grid animate-pulse gap-4 rounded-2xl bg-slate-50 p-4 md:grid-cols-[0.5fr_1fr_1.1fr_0.7fr_0.6fr_0.8fr_0.9fr]">
                        <div class="h-4 rounded bg-slate-200"></div>
                        <div class="h-4 rounded bg-slate-200"></div>
                        <div class="h-4 rounded bg-slate-200"></div>
                        <div class="h-4 rounded bg-slate-200"></div>
                        <div class="h-4 rounded bg-slate-200"></div>
                        <div class="h-4 rounded bg-slate-200"></div>
                        <div class="h-4 rounded bg-slate-200"></div>
                    </div>
                </div>

                <div v-else-if="payments.length" class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">User Name</th>
                                <th class="px-6 py-4">Email</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">Currency</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="payment in payments"
                                :key="payment.id"
                                class="border-t border-slate-100 transition duration-200 hover:bg-slate-50/80"
                            >
                                <td class="px-6 py-4 text-sm font-semibold text-slate-700">#{{ payment.id }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-[#154677]">{{ payment.user?.name || "-" }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500">{{ payment.user?.email || "-" }}</td>
                                <td class="px-6 py-4 text-sm font-semibold text-[#154677]">{{ formatAmount(payment.amount) }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ payment.currency || "-" }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(payment.status)">
                                        {{ payment.status || "unknown" }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <RouterLink
                                            :to="{ name: 'admin.payments.show', params: { id: payment.id } }"
                                            class="inline-flex items-center gap-2 rounded-xl border border-[#154677]/10 px-3 py-2 text-sm font-semibold text-[#154677] transition hover:border-[#154677]/20 hover:bg-[#154677]/5"
                                        >
                                            <i class="bi bi-eye"></i>
                                            View
                                        </RouterLink>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-[#154677] transition hover:bg-slate-200"
                                            @click="deletePayment(payment)"
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

                <div v-else class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-[#154677]">
                        <i class="bi bi-receipt text-2xl"></i>
                    </div>
                    <h2 class="mt-5 text-xl font-semibold text-[#154677]">No payments found</h2>
                    <p class="mt-2 text-sm text-slate-500">Payment records will appear here once transactions start coming in.</p>
                </div>

                <div v-if="!loading && pagination.last_page > 1" class="flex flex-wrap items-center justify-between gap-4 border-t border-[#154677]/10 px-6 py-4">
                    <p class="text-sm text-slate-500">
                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="link in normalizedLinks"
                            :key="`${link.label}-${link.page || link.url}`"
                            type="button"
                            class="inline-flex min-w-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-semibold transition"
                            :class="link.active ? 'border-[#154677] bg-[#154677] text-white' : 'border-[#154677]/10 bg-white text-slate-600 hover:border-[#154677]/20 hover:bg-slate-50'"
                            :disabled="!link.url || loading"
                            @click="changePage(link.page)"
                        >{{ link.label }}</button>
                    </div>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import paymentService from "@/services/admin/payment/paymentService";

const payments = ref([]);
const loading = ref(false);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    links: [],
});

const cleanLabel = (label = "") =>
    label.replace("&laquo; Previous", "Prev").replace("Next &raquo;", "Next");

const extractPage = (url) => {
    if (!url) return null;
    try {
        return Number(new URL(url).searchParams.get("page"));
    } catch {
        return null;
    }
};

const normalizedLinks = computed(() =>
    (pagination.value.links || []).map((link) => ({
        ...link,
        page: extractPage(link.url),
        label: cleanLabel(link.label),
    }))
);

const formatAmount = (amount) => {
    const value = Number(amount ?? 0);
    return Number.isFinite(value)
        ? value.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : "-";
};

const statusClass = (status) => {
    const normalized = String(status || "").toLowerCase();
    if (normalized === "completed") return "bg-emerald-100 text-[#154677]";
    if (normalized === "pending") return "bg-[#2ba6de]/15 text-[#154677]";
    if (normalized === "failed") return "bg-red-100 text-[#154677]";
    return "bg-slate-100 text-slate-600";
};

const fetchPayments = async (page = 1) => {
    loading.value = true;
    try {
        const response = await paymentService.getPayments(page);
        const payload = response?.data || {};
        payments.value = payload.data || [];
        pagination.value = {
            current_page: payload.current_page || 1,
            last_page: payload.last_page || 1,
            total: payload.total || 0,
            links: payload.links || [],
        };
    } catch (error) {
        console.error("Failed to fetch payments:", error);
        payments.value = [];
    } finally {
        loading.value = false;
    }
};

const changePage = (page) => {
    if (!page || page === pagination.value.current_page) return;
    fetchPayments(page);
};

const deletePayment = async (payment) => {
    if (!window.confirm(`Delete payment #${payment.id}?`)) return;

    try {
        const response = await paymentService.deletePayment(payment.id);
        toastr.success(response?.message || "Payment deleted successfully.");

        const targetPage =
            payments.value.length === 1 && pagination.value.current_page > 1
                ? pagination.value.current_page - 1
                : pagination.value.current_page;

        await fetchPayments(targetPage);
    } catch (error) {
        console.error("Failed to delete payment:", error);
    }
};

onMounted(() => {
    fetchPayments();
});
</script>
