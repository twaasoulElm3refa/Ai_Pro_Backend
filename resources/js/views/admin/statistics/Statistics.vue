<template>
    <AdminLayout>
        <section class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#154677]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677]">
                        Admin Statistics
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677]">Dashboard Analytics</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-500">
                        High-level platform metrics for payments, users, wallets, and tools, plus the latest activity across the system.
                    </p>
                </div>
            </div>

            <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="item in 4" :key="item" class="h-36 animate-pulse rounded-2xl border border-[#154677]/10 bg-white shadow-sm shadow-[#154677]/10"></div>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in topCards" :key="card.label" class="rounded-2xl border border-[#154677]/10 bg-white p-5 shadow-sm shadow-[#154677]/10 transition duration-200 hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#154677]/10 text-[#154677]">
                            <i class="bi" :class="card.icon"></i>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ card.label }}</span>
                    </div>
                    <p class="mt-5 text-3xl font-bold tracking-tight text-[#154677]">{{ card.value }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ card.caption }}</p>
                </article>
            </div>

            <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                <section class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-[#154677]">Payments Status</h2>
                            <p class="mt-1 text-sm text-slate-500">Live distribution across completed, pending, and failed transactions.</p>
                        </div>
                    </div>

                    <div v-if="loading" class="mt-6 h-64 animate-pulse rounded-2xl bg-slate-100"></div>

                    <div v-else class="mt-6 grid gap-6 md:grid-cols-[0.9fr_1.1fr] md:items-center">
                        <div class="mx-auto flex h-52 w-52 items-center justify-center rounded-full border border-[#154677]/10 bg-slate-50" :style="pieStyle">
                            <div class="grid h-24 w-24 place-items-center rounded-full bg-white text-center shadow-sm">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Payments</span>
                                <span class="mt-1 text-2xl font-bold text-[#154677]">{{ stats.payments.total_payments }}</span>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div v-for="item in paymentBreakdown" :key="item.label" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: item.color }"></span>
                                        <span class="text-sm font-semibold text-slate-700">{{ item.label }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-[#154677]">{{ item.value }}</span>
                                </div>
                                <div class="mt-3 h-2 rounded-full bg-slate-200">
                                    <div class="h-full rounded-full transition-all duration-500" :style="{ width: `${item.percent}%`, backgroundColor: item.color }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                    <div>
                        <h2 class="text-xl font-semibold text-[#154677]">Revenue Snapshot</h2>
                        <p class="mt-1 text-sm text-slate-500">Quick comparison between total revenue and total wallet balance.</p>
                    </div>

                    <div v-if="loading" class="mt-6 h-64 animate-pulse rounded-2xl bg-slate-100"></div>

                    <div v-else class="mt-8 space-y-6">
                        <div v-for="item in revenueBars" :key="item.label" class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold text-slate-700">{{ item.label }}</span>
                                <span class="text-sm font-bold text-[#154677]">{{ item.display }}</span>
                            </div>
                            <div class="h-3 rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-[#154677] transition-all duration-500" :style="{ width: `${item.width}%` }"></div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active Users</p>
                                <p class="mt-2 text-2xl font-bold text-[#154677]">{{ stats.users.active_users }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">New Users</p>
                                <p class="mt-2 text-2xl font-bold text-[#154677]">{{ stats.users.new_users }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Subtools</p>
                                <p class="mt-2 text-2xl font-bold text-[#154677]">{{ stats.tools.total_subtools }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-[#154677]">Latest Payments</h2>
                            <p class="mt-1 text-sm text-slate-500">Most recent transaction activity across the platform.</p>
                        </div>
                    </div>

                    <div v-if="loading" class="mt-6 space-y-3">
                        <div v-for="item in 5" :key="item" class="h-16 animate-pulse rounded-2xl bg-slate-100"></div>
                    </div>

                    <div v-else-if="stats.latest.payments.length" class="mt-6 space-y-3">
                        <article v-for="payment in stats.latest.payments" :key="payment.id" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-sm">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-[#154677]">{{ payment.transaction_id || `Payment #${payment.id}` }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ payment.user?.name || "-" }} · {{ payment.user?.email || "-" }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-[#154677]">{{ formatCurrency(payment.amount, payment.currency) }}</span>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(payment.status)">
                                        {{ payment.status || "unknown" }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        No recent payments available.
                    </div>
                </section>

                <section class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                    <div>
                        <h2 class="text-xl font-semibold text-[#154677]">Latest Users</h2>
                        <p class="mt-1 text-sm text-slate-500">Newest signups and current account activity.</p>
                    </div>

                    <div v-if="loading" class="mt-6 space-y-3">
                        <div v-for="item in 5" :key="item" class="h-16 animate-pulse rounded-2xl bg-slate-100"></div>
                    </div>

                    <div v-else-if="stats.latest.users.length" class="mt-6 space-y-3">
                        <article v-for="user in stats.latest.users" :key="user.id" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-sm">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-[#154677]">{{ user.name || "-" }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ user.email || "-" }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ formatDate(user.created_at) }}</span>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="user.is_active ? 'bg-emerald-100 text-[#154677]' : 'bg-slate-200 text-slate-600'">
                                        {{ user.is_active ? "Active" : "Inactive" }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                        No recent users available.
                    </div>
                </section>
            </div>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import statsService from "@/services/admin/statistics/stats";

const loading = ref(true);
const stats = ref({
    payments: {
        total_payments: 0,
        completed_payments: 0,
        failed_payments: 0,
        pending_payments: 0,
        total_revenue: 0,
    },
    users: {
        total_users: 0,
        active_users: 0,
        new_users: 0,
    },
    wallets: {
        total_wallet_balance: 0,
        total_wallets: 0,
    },
    tools: {
        total_tools: 0,
        total_subtools: 0,
    },
    latest: {
        payments: [],
        users: [],
    },
});

const formatNumber = (value) => Number(value ?? 0).toLocaleString("en-US");

const formatCurrency = (amount, currency = "USD") => {
    const value = Number(amount ?? 0);
    return `${value.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency || ""}`.trim();
};

const formatDate = (value) => {
    if (!value) return "-";
    return new Intl.DateTimeFormat("en", {
        month: "short",
        day: "numeric",
        year: "numeric",
    }).format(new Date(value));
};

const topCards = computed(() => [
    {
        label: "Total Revenue",
        value: formatCurrency(stats.value.payments.total_revenue, "USD"),
        caption: "Completed payment volume",
        icon: "bi-cash-coin",
    },
    {
        label: "Total Users",
        value: formatNumber(stats.value.users.total_users),
        caption: `${formatNumber(stats.value.users.new_users)} new in 7 days`,
        icon: "bi-people",
    },
    {
        label: "Total Payments",
        value: formatNumber(stats.value.payments.total_payments),
        caption: `${formatNumber(stats.value.payments.completed_payments)} completed`,
        icon: "bi-credit-card",
    },
    {
        label: "Failed Payments",
        value: formatNumber(stats.value.payments.failed_payments),
        caption: `${formatNumber(stats.value.payments.pending_payments)} pending review`,
        icon: "bi-exclamation-octagon",
    },
]);

const paymentBreakdown = computed(() => {
    const total = Math.max(stats.value.payments.total_payments, 1);
    return [
        {
            label: "Completed",
            value: stats.value.payments.completed_payments,
            percent: Math.round((stats.value.payments.completed_payments / total) * 100),
            color: "#154677",
        },
        {
            label: "Pending",
            value: stats.value.payments.pending_payments,
            percent: Math.round((stats.value.payments.pending_payments / total) * 100),
            color: "#8aa9c4",
        },
        {
            label: "Failed",
            value: stats.value.payments.failed_payments,
            percent: Math.round((stats.value.payments.failed_payments / total) * 100),
            color: "#d0d8e0",
        },
    ];
});

const pieStyle = computed(() => {
    const [completed, pending, failed] = paymentBreakdown.value.map((item) => item.percent);
    return {
        background: `conic-gradient(#154677 0 ${completed}%, #8aa9c4 ${completed}% ${completed + pending}%, #d0d8e0 ${completed + pending}% 100%)`,
    };
});

const revenueBars = computed(() => {
    const items = [
        {
            label: "Total Revenue",
            value: Number(stats.value.payments.total_revenue || 0),
            display: formatCurrency(stats.value.payments.total_revenue, "USD"),
        },
        {
            label: "Wallet Balance",
            value: Number(stats.value.wallets.total_wallet_balance || 0),
            display: formatCurrency(stats.value.wallets.total_wallet_balance, "USD"),
        },
        {
            label: "Completed Payments",
            value: Number(stats.value.payments.completed_payments || 0),
            display: formatNumber(stats.value.payments.completed_payments),
        },
    ];

    const max = Math.max(...items.map((item) => item.value), 1);
    return items.map((item) => ({
        ...item,
        width: Math.max(Math.round((item.value / max) * 100), item.value > 0 ? 8 : 0),
    }));
});

const statusClass = (status) => {
    const normalized = String(status || "").toLowerCase();
    if (normalized === "completed") return "bg-emerald-100 text-[#154677]";
    if (normalized === "pending") return "bg-[#2ba6de]/15 text-[#154677]";
    if (normalized === "failed") return "bg-red-100 text-[#154677]";
    return "bg-slate-100 text-slate-600";
};

const loadStats = async () => {
    loading.value = true;
    try {
        const response = await statsService.getStats();
        stats.value = {
            ...stats.value,
            ...(response?.data || {}),
        };
    } catch (error) {
        console.error("Failed to fetch statistics:", error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadStats();
});
</script>
