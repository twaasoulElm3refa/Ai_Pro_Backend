<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#154677]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677]">
                        Payment Details
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677]">
                        {{ loading ? "Loading payment..." : `Payment #${payment.id}` }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Inspect transaction details, user information, gateway response, and payment metadata.
                    </p>
                </div>

                <RouterLink
                    :to="{ name: 'admin.payments.index' }"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-[#154677]/10 bg-white px-4 py-3 text-sm font-semibold text-[#154677] shadow-sm transition hover:border-[#154677]/20 hover:bg-slate-50"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back
                </RouterLink>
            </div>

            <div v-if="loading" class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <div class="h-80 animate-pulse rounded-2xl border border-[#154677]/10 bg-white"></div>
                <div class="h-80 animate-pulse rounded-2xl border border-[#154677]/10 bg-white"></div>
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                    <div class="flex items-center justify-between gap-3 border-b border-[#154677]/10 pb-4">
                        <h2 class="text-lg font-semibold text-[#154677]">Payment Info</h2>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(payment.status)">
                            {{ payment.status || "unknown" }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div v-for="item in paymentInfo" :key="item.label" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                            <p class="mt-2 break-words text-sm font-semibold text-[#154677]">{{ item.value }}</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-[#154677]/10 bg-slate-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</p>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ payment.description || "No description provided." }}</p>
                    </div>
                </article>

                <div class="space-y-6">
                    <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                        <h2 class="text-lg font-semibold text-[#154677]">User Info</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Name</p>
                                <p class="mt-2 text-sm font-semibold text-[#154677]">{{ payment.user?.name || "-" }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</p>
                                <p class="mt-2 break-words text-sm font-semibold text-[#154677]">{{ payment.user?.email || "-" }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                        <h2 class="text-lg font-semibold text-[#154677]">Gateway Response</h2>
                        <pre class="mt-5 overflow-x-auto rounded-2xl bg-slate-50 p-4 text-xs leading-6 text-slate-700">{{ prettyGatewayResponse }}</pre>
                    </article>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import paymentService from "@/services/admin/payment/paymentService";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const payment = ref({});

const formatAmount = (amount) => {
    const value = Number(amount ?? 0);
    return Number.isFinite(value)
        ? value.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : "-";
};

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

const statusClass = (status) => {
    const normalized = String(status || "").toLowerCase();
    if (normalized === "completed") return "bg-emerald-100 text-[#154677]";
    if (normalized === "pending") return "bg-[#2ba6de]/15 text-[#154677]";
    if (normalized === "failed") return "bg-red-100 text-[#154677]";
    return "bg-slate-100 text-slate-600";
};

const paymentInfo = computed(() => [
    { label: "Transaction ID", value: payment.value.transaction_id || "-" },
    { label: "Amount", value: formatAmount(payment.value.amount) },
    { label: "Currency", value: payment.value.currency || "-" },
    { label: "Status", value: payment.value.status || "-" },
    { label: "PayPal Order ID", value: payment.value.paypal_order_id || "-" },
    { label: "Paid At", value: formatDate(payment.value.paid_at) },
]);

const prettyGatewayResponse = computed(() => {
    const value = payment.value.gateway_response;
    if (!value) return "{}";

    if (typeof value === "object") return JSON.stringify(value, null, 2);

    try {
        return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
        return String(value);
    }
});

const loadPayment = async () => {
    loading.value = true;
    try {
        const response = await paymentService.getPayment(route.params.id);
        payment.value = response?.data || {};
        if (!payment.value?.id) {
            router.push({ name: "admin.payments.index" });
        }
    } catch (error) {
        console.error("Failed to load payment:", error);
        router.push({ name: "admin.payments.index" });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadPayment();
});
</script>
