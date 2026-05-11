<template>
    <AdminLayout>
        <section class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#154677]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677]">
                        Cost Details
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677]">{{ loading ? "Loading cost log..." : `Cost Log #${cost.id}` }}</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink :to="{ name: 'admin.cost.index' }" class="btn-secondary">Back</RouterLink>
                    <button v-if="!loading && cost.id" type="button" class="btn-secondary" @click="removeCost">Delete</button>
                </div>
            </div>

            <div v-if="loading" class="grid gap-6 lg:grid-cols-2">
                <div class="h-80 animate-pulse rounded-2xl border border-[#154677]/10 bg-white"></div>
                <div class="h-80 animate-pulse rounded-2xl border border-[#154677]/10 bg-white"></div>
            </div>
            <div v-else-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center text-sm font-semibold text-red-600">{{ errorMessage }}</div>

            <div v-else class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                    <h2 class="text-lg font-semibold text-[#154677]">Cost Log Information</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div v-for="item in infoItems" :key="item.label" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                            <p class="mt-2 break-words text-sm font-semibold text-[#154677]">{{ item.value }}</p>
                        </div>
                    </div>
                </article>

                <div class="space-y-6">
                    <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                        <h2 class="text-lg font-semibold text-[#154677]">Relations</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">User</p>
                                <p class="mt-2 text-sm font-semibold text-[#154677]">{{ cost.user?.name || "-" }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ cost.user?.email || "-" }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Conversation</p>
                                <p class="mt-2 text-sm font-semibold text-[#154677]">#{{ cost.conversation_id || "-" }}</p>
                                <p class="mt-1 break-all text-xs text-slate-500">{{ cost.conversation?.uuid || "-" }}</p>
                            </div>
                            <div class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4 sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sub Tool</p>
                                <p class="mt-2 text-sm font-semibold text-[#154677]">{{ cost.sub_tool?.name || cost.subTool?.name || "-" }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-[#154677]/10 bg-white p-6 shadow-sm shadow-[#154677]/10">
                        <h2 class="text-lg font-semibold text-[#154677]">Conversation Cost Summary</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div v-for="item in conversationSummaryItems" :key="item.label" class="rounded-2xl border border-[#154677]/10 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</p>
                                <p class="mt-2 text-sm font-semibold text-[#154677]">{{ item.value }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import costService from "@/services/admin/cost/costService";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const errorMessage = ref("");
const cost = ref({});

const extractCostItem = (response) => {
    const root = response || {};
    if (root?.data && !Array.isArray(root.data)) return root.data;
    return root;
};

const formatNumber = (value) => {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? number.toLocaleString("en-US") : "0";
};

const formatCurrency = (value, currency = "USD") => {
    const number = Number(value ?? 0);
    if (!Number.isFinite(number)) return `0.000000 ${currency}`;
    const decimals = Math.abs(number) > 0 && Math.abs(number) < 0.01 ? 8 : 6;
    return `${number.toFixed(decimals)} ${currency || "USD"}`;
};

const formatDate = (value) => {
    if (!value) return "-";
    return new Intl.DateTimeFormat("en", { year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }).format(new Date(value));
};

const infoItems = computed(() => [
    { label: "Cost ID", value: cost.value.id || "-" },
    { label: "Input Tokens", value: formatNumber(cost.value.input_tokens) },
    { label: "Output Tokens", value: formatNumber(cost.value.output_tokens) },
    { label: "Total Tokens", value: formatNumber(cost.value.total_tokens) },
    { label: "Input Cost", value: formatCurrency(cost.value.input_cost, cost.value.currency) },
    { label: "Output Cost", value: formatCurrency(cost.value.output_cost, cost.value.currency) },
    { label: "Web Search Cost", value: formatCurrency(cost.value.web_search_cost, cost.value.currency) },
    { label: "Total Cost", value: formatCurrency(cost.value.total_cost, cost.value.currency) },
    { label: "Currency", value: cost.value.currency || "USD" },
    { label: "Model Key", value: cost.value.model_key || "-" },
    { label: "Provider Request ID", value: cost.value.provider_request_id || "-" },
    { label: "Conversation Total Tokens", value: formatNumber(cost.value.conversation_total_tokens) },
    { label: "Limited", value: cost.value.is_limited ? "Limited" : "Normal" },
    { label: "Created At", value: formatDate(cost.value.created_at) },
]);

const conversationSummaryItems = computed(() => {
    const summary = cost.value.conversation_cost_summary || {};
    return [
        { label: "Logs Count", value: formatNumber(summary.logs_count) },
        { label: "Total Tokens", value: formatNumber(summary.total_tokens) },
        { label: "Input Tokens", value: formatNumber(summary.input_tokens) },
        { label: "Output Tokens", value: formatNumber(summary.output_tokens) },
        { label: "Total Cost", value: formatCurrency(summary.total_cost, cost.value.currency) },
        { label: "Input Cost", value: formatCurrency(summary.input_cost, cost.value.currency) },
        { label: "Output Cost", value: formatCurrency(summary.output_cost, cost.value.currency) },
    ];
});

const loadCost = async () => {
    loading.value = true;
    errorMessage.value = "";
    try {
        const response = await costService.show(route.params.id);
        const item = extractCostItem(response);
        if (!item?.id) {
            router.push({ name: "admin.cost.index" });
            return;
        }
        cost.value = item;
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || "Failed to load cost log.";
    } finally {
        loading.value = false;
    }
};

const removeCost = async () => {
    if (!cost.value.id) return;
    if (!window.confirm(`Delete cost log #${cost.value.id}?`)) return;
    try {
        const response = await costService.destroy(cost.value.id);
        toastr.success(response?.message || "Cost log deleted successfully.");
        router.push({ name: "admin.cost.index" });
    } catch (error) {
        toastr.error(error?.response?.data?.message || "Failed to delete cost log.");
    }
};

onMounted(() => {
    loadCost();
});
</script>

<style scoped>
.btn-secondary {
    border-radius: 0.75rem;
    border: 1px solid rgba(21, 70, 119, 0.15);
    background: #fff;
    color: #154677;
    padding: 0.55rem 0.9rem;
    font-size: 0.875rem;
    font-weight: 600;
}
</style>
