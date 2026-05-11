<template>
    <AdminLayout>
        <section class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full bg-[#154677]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677]">
                        AI Cost Management
                    </span>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#154677]">{{ mode === "today" ? "Today Cost Logs" : "Cost Logs" }}</h1>
                </div>
                <div class="rounded-2xl border border-[#154677]/10 bg-white px-5 py-4 shadow-sm shadow-[#154677]/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Logs</p>
                    <p class="mt-1 text-2xl font-bold text-[#154677]">{{ pagination.total }}</p>
                </div>
            </div>

            <section class="rounded-2xl border border-[#154677]/10 bg-white p-5 shadow-sm shadow-[#154677]/10">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <input v-model.trim="filters.search" type="text" placeholder="Search user/email/uuid/id" class="input" />
                    <input v-model.trim="filters.user_id" type="number" min="1" placeholder="User ID" class="input" />
                    <input v-model.trim="filters.conversation_id" type="number" min="1" placeholder="Conversation ID" class="input" />
                    <input v-model.trim="filters.conversation_uuid" type="text" placeholder="Conversation UUID" class="input" />
                    <input v-model.trim="filters.sub_tool_id" type="number" min="1" placeholder="Sub Tool ID" class="input" />
                    <select v-model="filters.limited" class="input">
                        <option value="">All Limit States</option>
                        <option value="true">Limited only</option>
                        <option value="false">Not limited</option>
                    </select>
                    <input v-model.trim="filters.min_total_tokens" type="number" min="0" placeholder="Min total tokens" class="input" />
                    <input v-model.trim="filters.max_total_tokens" type="number" min="0" placeholder="Max total tokens" class="input" />
                    <input v-model.trim="filters.min_total_cost" type="number" min="0" step="0.000001" placeholder="Min total cost" class="input" />
                    <input v-model.trim="filters.max_total_cost" type="number" min="0" step="0.000001" placeholder="Max total cost" class="input" />
                    <input v-if="mode !== 'today'" v-model="filters.date_from" type="date" class="input" />
                    <input v-if="mode !== 'today'" v-model="filters.date_to" type="date" class="input" />
                    <select v-model="filters.sort_by" class="input">
                        <option v-for="sortItem in sortOptions" :key="sortItem.value" :value="sortItem.value">{{ sortItem.label }}</option>
                    </select>
                    <select v-model="filters.sort_direction" class="input">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="btn-primary" @click="applyFilters">Apply Filters</button>
                    <button type="button" class="btn-secondary" @click="resetFilters">Reset Filters</button>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <article v-for="card in summaryCards" :key="card.label" class="rounded-2xl border border-[#154677]/10 bg-white p-4 shadow-sm shadow-[#154677]/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ card.label }}</p>
                    <p class="mt-2 text-xl font-bold text-[#154677]">{{ card.value }}</p>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[#154677]/10 bg-white shadow-sm shadow-[#154677]/10">
                <div class="border-b border-[#154677]/10 bg-slate-50/80 px-6 py-4">
                    <div class="text-sm font-semibold text-[#154677]">Cost Logs</div>
                </div>

                <div v-if="loading" class="space-y-3 p-6">
                    <div v-for="item in 6" :key="item" class="h-12 animate-pulse rounded-xl bg-slate-100"></div>
                </div>
                <div v-else-if="errorMessage" class="px-6 py-12 text-center text-sm font-semibold text-red-500">{{ errorMessage }}</div>
                <div v-else-if="rows.length" class="overflow-x-auto">
                    <table class="min-w-[1400px] text-left">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Conversation</th>
                                <th class="px-4 py-3">Subtool</th>
                                <th class="px-4 py-3">Input Tokens</th>
                                <th class="px-4 py-3">Output Tokens</th>
                                <th class="px-4 py-3">Total Tokens</th>
                                <th class="px-4 py-3">Input Cost</th>
                                <th class="px-4 py-3">Output Cost</th>
                                <th class="px-4 py-3">Total Cost</th>
                                <th class="px-4 py-3">Conversation Total</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Created At</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100 hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700">#{{ row.id }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600"><div class="font-semibold text-[#154677]">{{ row.user?.name || "-" }}</div><div>{{ row.user?.email || "-" }}</div></td>
                                <td class="px-4 py-3 text-sm text-slate-600"><div class="font-semibold text-[#154677]">#{{ row.conversation_id || "-" }}</div><div class="break-all">{{ row.conversation?.uuid || "-" }}</div></td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ row.sub_tool?.name || row.subTool?.name || "-" }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatNumber(row.input_tokens) }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatNumber(row.output_tokens) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-[#154677]">{{ formatNumber(row.total_tokens) }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatCurrency(row.input_cost, row.currency) }}</td>
                                <td class="px-4 py-3 text-sm">{{ formatCurrency(row.output_cost, row.currency) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-[#154677]">{{ formatCurrency(row.total_cost, row.currency) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold" :class="Number(row.conversation_total_tokens || 0) >= tokenLimit ? 'text-amber-600' : 'text-slate-700'">
                                    {{ formatNumber(row.conversation_total_tokens) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="isLimited(row.is_limited) ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                                        {{ isLimited(row.is_limited) ? "Limited" : "Normal" }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ formatDate(row.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <RouterLink :to="{ name: 'admin.cost.show', params: { id: row.id } }" class="btn-secondary">
                                            View
                                        </RouterLink>
                                        <button type="button" class="btn-secondary" @click="removeRow(row)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-6 py-16 text-center text-sm text-slate-500">No cost logs found.</div>

                <div v-if="!loading" class="flex flex-wrap items-center justify-between gap-3 border-t border-[#154677]/10 px-6 py-4">
                    <div class="text-sm text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }} · {{ pagination.total }} records</div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Per Page</label>
                        <select v-model.number="filters.per_page" class="input-small" @change="onPerPageChange">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-secondary" :disabled="loading || pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">Previous</button>
                        <button type="button" class="btn-secondary" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
                    </div>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import costService from "@/services/admin/cost/costService";

const props = defineProps({ mode: { type: String, default: "all" } });

const tokenLimit = 7000;
const loading = ref(false);
const errorMessage = ref("");
const rows = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
const summary = ref({ logs_count: 0, total_tokens: 0, input_tokens: 0, output_tokens: 0, total_cost: 0, input_cost: 0, output_cost: 0, limited_conversations_count: 0 });
const filters = reactive({ search: "", user_id: "", conversation_id: "", conversation_uuid: "", sub_tool_id: "", date_from: "", date_to: "", limited: "", min_total_tokens: "", max_total_tokens: "", min_total_cost: "", max_total_cost: "", sort_by: "created_at", sort_direction: "desc", per_page: 10, page: 1 });

const sortOptions = [
    { label: "Created At", value: "created_at" },
    { label: "Total Tokens", value: "total_tokens" },
    { label: "Input Tokens", value: "input_tokens" },
    { label: "Output Tokens", value: "output_tokens" },
    { label: "Total Cost", value: "total_cost" },
    { label: "Input Cost", value: "input_cost" },
    { label: "Output Cost", value: "output_cost" },
    { label: "Conversation Total Tokens", value: "conversation_total_tokens" },
];

const summaryCards = computed(() => [
    { label: "Logs Count", value: formatNumber(summary.value.logs_count) },
    { label: "Total Tokens", value: formatNumber(summary.value.total_tokens) },
    { label: "Input Tokens", value: formatNumber(summary.value.input_tokens) },
    { label: "Output Tokens", value: formatNumber(summary.value.output_tokens) },
    { label: "Total Cost", value: formatCurrency(summary.value.total_cost) },
    { label: "Limited Conversations", value: formatNumber(summary.value.limited_conversations_count) },
]);

const extractRows = (response) => {
    const root = response || {};
    const candidates = [root?.data?.data?.data, root?.data?.data, root?.data, root?.rows, root];
    const found = candidates.find((candidate) => Array.isArray(candidate));
    return Array.isArray(found) ? found : [];
};

const extractPagination = (response) => {
    const root = response || {};
    const candidates = [root?.data?.data, root?.data, root?.meta, root].filter((item) => item && typeof item === "object");
    const matched = candidates.find((item) => item.current_page !== undefined || item.last_page !== undefined || item.per_page !== undefined || item.total !== undefined) || {};
    return { current_page: Number(matched.current_page || 1), last_page: Number(matched.last_page || 1), per_page: Number(matched.per_page || filters.per_page || 10), total: Number(matched.total || 0) };
};

const extractSummary = (response) => {
    const root = response || {};
    const value = root?.data?.summary || root?.meta?.summary || root?.summary || {};
    return {
        logs_count: Number(value.logs_count || 0),
        total_tokens: Number(value.total_tokens || 0),
        input_tokens: Number(value.input_tokens || 0),
        output_tokens: Number(value.output_tokens || 0),
        total_cost: Number(value.total_cost || 0),
        input_cost: Number(value.input_cost || 0),
        output_cost: Number(value.output_cost || 0),
        limited_conversations_count: Number(value.limited_conversations_count || 0),
    };
};

const buildParams = () => {
    const params = {
        search: filters.search,
        user_id: filters.user_id,
        conversation_id: filters.conversation_id,
        conversation_uuid: filters.conversation_uuid,
        sub_tool_id: filters.sub_tool_id,
        limited: filters.limited,
        min_total_tokens: filters.min_total_tokens,
        max_total_tokens: filters.max_total_tokens,
        min_total_cost: filters.min_total_cost,
        max_total_cost: filters.max_total_cost,
        sort_by: filters.sort_by,
        sort_direction: filters.sort_direction,
        per_page: filters.per_page,
        page: filters.page,
    };
    if (props.mode !== "today") {
        params.date_from = filters.date_from;
        params.date_to = filters.date_to;
    }
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== "" && value !== null && value !== undefined));
};

const loadRows = async () => {
    loading.value = true;
    errorMessage.value = "";
    try {
        const params = buildParams();
        const response = props.mode === "today" ? await costService.today(params) : await costService.index(params);
        rows.value = extractRows(response);
        pagination.value = extractPagination(response);
        summary.value = extractSummary(response);
        filters.page = pagination.value.current_page;
    } catch (error) {
        rows.value = [];
        summary.value = extractSummary({});
        errorMessage.value = error?.response?.data?.message || "Failed to load cost logs.";
    } finally {
        loading.value = false;
    }
};

const applyFilters = () => {
    filters.page = 1;
    loadRows();
};

const resetFilters = () => {
    Object.assign(filters, { search: "", user_id: "", conversation_id: "", conversation_uuid: "", sub_tool_id: "", date_from: "", date_to: "", limited: "", min_total_tokens: "", max_total_tokens: "", min_total_cost: "", max_total_cost: "", sort_by: "created_at", sort_direction: "desc", per_page: 10, page: 1 });
    loadRows();
};

const changePage = (page) => {
    if (!page || page === filters.page) return;
    filters.page = page;
    loadRows();
};

const onPerPageChange = () => {
    filters.page = 1;
    loadRows();
};

const removeRow = async (row) => {
    if (!window.confirm(`Delete cost log #${row.id}?`)) return;
    try {
        const response = await costService.destroy(row.id);
        toastr.success(response?.message || "Cost log deleted successfully.");
        const targetPage = rows.value.length === 1 && pagination.value.current_page > 1 ? pagination.value.current_page - 1 : pagination.value.current_page;
        filters.page = targetPage;
        await loadRows();
    } catch (error) {
        toastr.error(error?.response?.data?.message || "Failed to delete cost log.");
    }
};

let searchDebounceTimer = null;
watch(() => filters.search, () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        filters.page = 1;
        loadRows();
    }, 400);
});

onUnmounted(() => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});

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

const isLimited = (value) => Boolean(value);

onMounted(() => {
    loadRows();
});
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgba(21, 70, 119, 0.1);
    background: #fff;
    padding: 0.55rem 0.75rem;
    font-size: 0.875rem;
    color: #154677;
}

.input-small {
    border-radius: 0.5rem;
    border: 1px solid rgba(21, 70, 119, 0.1);
    background: #fff;
    padding: 0.4rem 0.5rem;
    font-size: 0.875rem;
    color: #154677;
}

.btn-primary {
    border-radius: 0.75rem;
    background: #154677;
    color: #fff;
    padding: 0.55rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
}

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
