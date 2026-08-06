<template>
    <main class="wallet-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="wallet-page-title">
        <div class="wallet-shell">
            <header class="wallet-header">
                <div>
                    <span class="wallet-kicker">{{ t("user.wallet.kicker") }}</span>
                    <h1 id="wallet-page-title">{{ t("user.wallet.title") }}</h1>
                    <p>{{ t("user.wallet.subtitle") }}</p>
                </div>

                <button type="button" class="charge-button" @click="chargeWallet" :disabled="!wallet?.uuid || isCharging"
                    :aria-label="t('user.wallet.chargeAria')">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>{{ isCharging ? t("user.wallet.preparing") : t("user.wallet.chargeButton") }}</span>
                </button>
            </header>

            <section class="balance-grid">
                <article class="balance-card">
                    <div class="balance-card__glow"></div>
                    <div class="balance-card__content">
                        <p class="balance-card__label">{{ t("user.wallet.currentBalance") }}</p>
                        <div class="balance-card__value">
                            {{ formatPoints(wallet?.balance) }}
                            <span>{{ t("user.wallet.points") }}</span>
                        </div>
                        <div class="balance-card__meta">
                            <span>{{ t("user.wallet.walletId") }}</span>
                            <strong>{{ shortUuid }}</strong>
                        </div>
                    </div>
                </article>

                <article class="summary-card">
                    <div class="summary-card__row">
                        <div>
                            <p class="summary-card__label">{{ t("user.wallet.creditsOnPage") }}</p>
                            <h3>{{ formatPoints(summary.credits) }}</h3>
                        </div>
                        <span class="summary-chip">{{ t("user.wallet.credit") }}</span>
                    </div>

                    <div class="summary-card__row">
                        <div>
                            <p class="summary-card__label">{{ t("user.wallet.debitsOnPage") }}</p>
                            <h3>{{ formatPoints(summary.debits) }}</h3>
                        </div>
                        <span class="summary-chip summary-chip--soft">{{ t("user.wallet.debit") }}</span>
                    </div>

                    <div class="summary-card__footer">
                        <div>
                            <span class="summary-card__small">{{ t("user.wallet.transactionsLoaded") }}</span>
                            <strong>{{ pagination.total || transactions.length }}</strong>
                        </div>
                        <div>
                            <span class="summary-card__small">{{ t("user.wallet.lastUpdate") }}</span>
                            <strong>{{ formatDate(wallet?.updated_at) }}</strong>
                        </div>
                    </div>
                </article>
            </section>

            <section class="wallet-tabs">
                <button
                    type="button"
                    class="wallet-tab"
                    :class="{ 'is-active': activeTab === 'wallet' }"
                    @click="activeTab = 'wallet'"
                >
                    {{ t("user.wallet.tabWallet") }}
                </button>
                <button
                    type="button"
                    class="wallet-tab"
                    :class="{ 'is-active': activeTab === 'transactions' }"
                    @click="activeTab = 'transactions'"
                >
                    {{ t("user.wallet.tabTransactions") }}
                </button>
            </section>

            <Transition name="wallet-tab-switch" mode="out-in">
                <section v-if="activeTab === 'wallet'" key="wallet" class="wallet-panel">
                    <div v-if="loadingWallet" class="panel-grid">
                        <div v-for="item in 4" :key="item" class="panel-skeleton"></div>
                    </div>

                    <div v-else-if="wallet" class="panel-grid">
                        <article class="info-card">
                            <span class="info-card__label">{{ t("user.wallet.accountHolder") }}</span>
                            <h3>{{ wallet.user?.name || t("user.wallet.unknownUser") }}</h3>
                            <p>{{ wallet.user?.email || t("user.wallet.noEmail") }}</p>
                        </article>

                        <article class="info-card">
                            <span class="info-card__label">{{ t("user.wallet.walletStatus") }}</span>
                            <h3>{{ wallet.is_active ? t("user.wallet.statusActive") : t("user.wallet.statusInactive") }}</h3>
                            <p>{{ wallet.is_active ? t("user.wallet.statusActiveDesc") : t("user.wallet.statusInactiveDesc") }}</p>
                        </article>

                        <article class="info-card">
                            <span class="info-card__label">{{ t("user.wallet.createdAt") }}</span>
                            <h3>{{ formatDate(wallet.created_at) }}</h3>
                            <p>{{ t("user.wallet.createdAtDesc") }}</p>
                        </article>

                        <article class="info-card">
                            <span class="info-card__label">{{ t("user.wallet.updatedAt") }}</span>
                            <h3>{{ formatDate(wallet.updated_at) }}</h3>
                            <p>{{ t("user.wallet.updatedAtDesc") }}</p>
                        </article>
                    </div>

                    <div v-else class="empty-state">
                        <div class="empty-state__icon"><i class="bi bi-wallet2"></i></div>
                        <h3>{{ t("user.wallet.unavailableTitle") }}</h3>
                        <p>{{ t("user.wallet.unavailableDesc") }}</p>
                        <button type="button" class="inline-action" @click="fetchWallet">{{ t("user.wallet.tryAgain") }}</button>
                    </div>
                </section>

                <section v-else key="transactions" class="wallet-panel">
                    <div class="transactions-header">
                        <div>
                            <h2>{{ t("user.wallet.transactionActivity") }}</h2>
                            <p>{{ t("user.wallet.transactionActivityDesc") }}</p>
                        </div>
                    </div>

                    <div v-if="loadingTransactions" class="transactions-list">
                        <div v-for="item in 5" :key="item" class="transaction-skeleton"></div>
                    </div>

                    <template v-else>
                        <div v-if="transactions.length" class="transactions-list">
                            <button
                                v-for="transaction in transactions"
                                :key="transaction.slug || transaction.id"
                                type="button"
                                class="transaction-card"
                                :aria-label="t('user.wallet.viewTransactionAria', { id: transaction.id })"
                                @click="openTransactionDetails(transaction.slug)"
                            >
                                <div class="transaction-card__left">
                                    <span class="type-badge" :class="transaction.type === 'credit' ? 'credit' : 'debit'">
                                        {{ transaction.type || t("user.wallet.unknownType") }}
                                    </span>
                                    <div>
                                        <h3>{{ formatSignedPoints(transaction.points, transaction.type) }}</h3>
                                        <p>
                                            {{ formatPoints(transaction.balance_before) }}
                                            <i class="bi bi-arrow-left-right"></i>
                                            {{ formatPoints(transaction.balance_after) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="transaction-card__right">
                                    <strong>{{ formatDateTime(transaction.created_at) }}</strong>
                                    <span>#{{ transaction.id }}</span>
                                </div>
                            </button>
                        </div>

                        <div v-else class="empty-state">
                            <div class="empty-state__icon"><i class="bi bi-receipt"></i></div>
                            <h3>{{ t("user.wallet.noTransactions") }}</h3>
                            <p>{{ t("user.wallet.noTransactionsDesc") }}</p>
                        </div>

                        <div v-if="pagination.lastPage > 1" class="pagination-bar">
                            <button
                                type="button"
                                class="pagination-button"
                                :disabled="pagination.currentPage <= 1 || loadingTransactions"
                                @click="changePage(pagination.currentPage - 1)"
                            >
                                {{ t("user.wallet.previous") }}
                            </button>

                            <div class="pagination-meta">
                                <span>{{ t("user.wallet.pagination", { current: pagination.currentPage, last: pagination.lastPage }) }}</span>
                                <strong>{{ t("user.wallet.transactionsCount", { count: pagination.total }) }}</strong>
                            </div>

                            <button
                                type="button"
                                class="pagination-button"
                                :disabled="pagination.currentPage >= pagination.lastPage || loadingTransactions"
                                @click="changePage(pagination.currentPage + 1)"
                            >
                                {{ t("user.wallet.next") }}
                            </button>
                        </div>
                    </template>
                </section>
            </Transition>

            <div v-if="error" class="error-banner">
                <i class="bi bi-exclamation-circle"></i>
                <span>{{ error }}</span>
            </div>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="detailsModalOpen" class="details-overlay" @click.self="closeTransactionDetails">
                    <div class="details-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-details-title">
                        <div class="details-modal__header">
                            <div>
                                <p class="details-modal__kicker">{{ t("user.wallet.transactionDetails") }}</p>
                                <h2 id="transaction-details-title">{{ selectedTransaction?.slug || t("user.wallet.walletTransaction") }}</h2>
                            </div>
                            <button type="button" class="details-close" :aria-label="t('user.wallet.closeDetailsAria')" @click="closeTransactionDetails">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <div v-if="loadingDetails" class="details-skeletons">
                            <div v-for="item in 4" :key="item" class="details-skeleton"></div>
                        </div>

                        <template v-else-if="selectedTransaction">
                            <div class="details-grid">
                                <article class="details-item">
                                    <span>{{ t("user.wallet.type") }}</span>
                                    <strong>{{ selectedTransaction.type || "-" }}</strong>
                                </article>
                                <article class="details-item">
                                    <span>{{ t("user.wallet.points") }}</span>
                                    <strong>{{ formatSignedPoints(selectedTransaction.points, selectedTransaction.type) }}</strong>
                                </article>
                                <article class="details-item">
                                    <span>{{ t("user.wallet.balanceBefore") }}</span>
                                    <strong>{{ formatPoints(selectedTransaction.balance_before) }}</strong>
                                </article>
                                <article class="details-item">
                                    <span>{{ t("user.wallet.balanceAfter") }}</span>
                                    <strong>{{ formatPoints(selectedTransaction.balance_after) }}</strong>
                                </article>
                                <article class="details-item">
                                    <span>{{ t("user.wallet.user") }}</span>
                                    <strong>{{ selectedTransaction.user?.name || wallet?.user?.name || "-" }}</strong>
                                </article>
                                <article class="details-item">
                                    <span>{{ t("user.wallet.email") }}</span>
                                    <strong>{{ selectedTransaction.user?.email || wallet?.user?.email || "-" }}</strong>
                                </article>
                            </div>

                            <div class="details-summary">
                                <div>
                                    <span>{{ t("user.wallet.transactionDate") }}</span>
                                    <strong>{{ formatDateTime(selectedTransaction.created_at) }}</strong>
                                </div>
                                <div>
                                    <span>{{ t("user.wallet.reference") }}</span>
                                    <strong>{{ selectedTransaction.slug || "-" }}</strong>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import walletService from "@/services/profile/walletService";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";

const router = useRouter();
const route = useRoute();
const { t, locale } = useI18n();
const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar");
const seoTitle = computed(() => (isArabic.value ? "المحفظة | Ai Pro" : "Wallet | Ai Pro"));
const seoDescription = computed(() =>
    isArabic.value
        ? "أدر محفظتك بوضوح، راقب الرصيد، تتبع عمليات الإضافة والسحب، وراجع تفاصيل كل معاملة بشكل منظم وآمن داخل لوحة واحدة سهلة الاستخدام."
        : "Manage your wallet, monitor balance, review credits and debits, and inspect full transaction details with pagination in a secure and organized dashboard."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

const wallet = ref(null);
const transactions = ref([]);
const loadingWallet = ref(true);
const loadingTransactions = ref(true);
const loadingDetails = ref(false);
const error = ref("");
const isCharging = ref(false);
const activeTab = ref("wallet");
const detailsModalOpen = ref(false);
const selectedTransaction = ref(null);

const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0,
});

const summary = computed(() => {
    return transactions.value.reduce(
        (acc, transaction) => {
            const value = Number(transaction.points || 0);
            if (transaction.type === "credit") acc.credits += value;
            if (transaction.type === "debit") acc.debits += value;
            return acc;
        },
        { credits: 0, debits: 0 }
    );
});

const shortUuid = computed(() => {
    if (!wallet.value?.uuid) return t("user.wallet.noWalletReference");
    return `${wallet.value.uuid.slice(0, 12)}...`;
});

const mapTransactionsPayload = (payload = {}) => {
    const rows = Array.isArray(payload?.data) ? payload.data : [];

    transactions.value = rows;
    pagination.value = {
        currentPage: payload.current_page || 1,
        lastPage: payload.last_page || 1,
        total: payload.total || rows.length,
    };
};

const fetchWallet = async () => {
    loadingWallet.value = true;
    error.value = "";

    try {
        const response = await walletService.getWallet();
        wallet.value = response?.data || null;
    } catch (err) {
        error.value = err.response?.data?.message || t("user.wallet.loadWalletError");
    } finally {
        loadingWallet.value = false;
    }
};

const fetchTransactions = async (page = 1) => {
    loadingTransactions.value = true;
    error.value = "";

    try {
        const response = await walletService.getTransactions(page);
        mapTransactionsPayload(response?.data || {});
    } catch (err) {
        transactions.value = [];
        pagination.value = { currentPage: 1, lastPage: 1, total: 0 };
        error.value = err.response?.data?.message || t("user.wallet.loadTransactionsError");
    } finally {
        loadingTransactions.value = false;
    }
};

const changePage = (page) => {
    if (page < 1 || page > pagination.value.lastPage || loadingTransactions.value) return;
    fetchTransactions(page);
};

const openTransactionDetails = async (slug) => {
    if (!slug) return;

    detailsModalOpen.value = true;
    loadingDetails.value = true;

    try {
        const response = await walletService.getTransactionDetails(slug);
        selectedTransaction.value = response?.data || null;
    } catch (err) {
        selectedTransaction.value = null;
        error.value = err.response?.data?.message || t("user.wallet.loadDetailsError");
    } finally {
        loadingDetails.value = false;
    }
};

const closeTransactionDetails = () => {
    detailsModalOpen.value = false;
    selectedTransaction.value = null;
};

const chargeWallet = () => {
    if (!wallet.value?.uuid) return;

    isCharging.value = true;
    router.push({
        name: "charge-wallet",
        params: {
            lang: homeService.getLang(),
            uuid: wallet.value.uuid,
        },
    }).finally(() => {
        isCharging.value = false;
    });
};

const formatPoints = (value) => {
    return new Intl.NumberFormat("en-US").format(Number(value || 0));
};

const formatSignedPoints = (value, type) => {
    const amount = formatPoints(value);
    return type === "debit" ? `- ${amount}` : `+ ${amount}`;
};

const formatDate = (value) => {
    if (!value) return t("user.wallet.na");

    return new Intl.DateTimeFormat("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    }).format(new Date(value));
};

const formatDateTime = (value) => {
    if (!value) return t("user.wallet.na");

    return new Intl.DateTimeFormat("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
};

onMounted(() => {
    locale.value = homeService.getLang();
    fetchWallet();
    fetchTransactions();
    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    window.removeEventListener("lang-changed", handleLangChanged);
});

const handleLangChanged = async () => {
    locale.value = homeService.getLang();
    await fetchWallet();
    await fetchTransactions(pagination.value.currentPage || 1);
};

watch(
    () => route.params.lang,
    async (nextLang, prevLang) => {
        if (!nextLang || nextLang === prevLang) return;
        await handleLangChanged();
    }
);
</script>

<style scoped>
.wallet-page {
    min-height: 100vh;
    padding: 32px 18px 48px;
    background: linear-gradient(180deg, #f8fbff 0%, #eef7fc 100%);
    font-family: 'Cairo', sans-serif;
}

.wallet-shell {
    max-width: 1120px;
    margin: 0 auto;
}

.wallet-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 26px;
}

.wallet-kicker {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.wallet-header h1 {
    margin: 14px 0 8px;
    font-size: clamp(2rem, 4vw, 2.8rem);
    line-height: 1.05;
    color: #154677;
    font-weight: 800;
}

.wallet-header p {
    margin: 0;
    max-width: 42rem;
    color: #5f7288;
    line-height: 1.8;
}

.charge-button {
    min-height: 50px;
    padding: 0 18px;
    border: none;
    border-radius: 1rem;
    background: #2ba6de;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    box-shadow: 0 18px 35px rgba(21, 70, 119, 0.18);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.charge-button:hover:not(:disabled) {
    transform: scale(1.02);
    background: #2398cb;
    box-shadow: 0 24px 42px rgba(21, 70, 119, 0.22);
}

.charge-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.balance-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.95fr);
    gap: 20px;
    margin-bottom: 24px;
}

.balance-card,
.summary-card,
.wallet-panel,
.error-banner,
.details-modal {
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.1);
    box-shadow: 0 24px 46px rgba(21, 70, 119, 0.1);
}

.balance-card {
    position: relative;
    overflow: hidden;
    min-height: 220px;
    border-radius: 1.75rem;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
}

.balance-card__glow {
    position: absolute;
    inset: auto -80px -80px auto;
    width: 220px;
    height: 220px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    filter: blur(12px);
}

.balance-card__content {
    position: relative;
    padding: 30px;
    z-index: 1;
}

.balance-card__label {
    margin: 0 0 14px;
    font-size: 0.9rem;
    opacity: 0.88;
}

.balance-card__value {
    font-size: clamp(2.4rem, 5vw, 3.8rem);
    font-weight: 800;
    letter-spacing: -0.04em;
}

.balance-card__value span {
    font-size: 1rem;
    font-weight: 600;
    opacity: 0.9;
    margin-inline-start: 8px;
}

.balance-card__meta {
    margin-top: 18px;
    display: inline-flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 14px;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(14px);
}

.balance-card__meta span {
    font-size: 0.76rem;
    opacity: 0.8;
}

.balance-card__meta strong {
    font-size: 0.92rem;
    letter-spacing: 0.04em;
}

.summary-card {
    border-radius: 1.75rem;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.summary-card__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
}

.summary-card__row:last-of-type {
    border-bottom: none;
    padding-bottom: 0;
}

.summary-card__label,
.summary-card__small {
    display: block;
    color: #5f7288;
    font-size: 0.85rem;
}

.summary-card h3 {
    margin: 8px 0 0;
    color: #154677;
    font-size: 1.7rem;
    font-weight: 800;
}

.summary-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 78px;
    padding: 0.55rem 0.9rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 700;
}

.summary-chip--soft {
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
}

.summary-card__footer {
    margin-top: auto;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    padding-top: 10px;
}

.summary-card__footer strong {
    display: block;
    margin-top: 6px;
    color: #154677;
}

.wallet-tabs {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px;
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 1.1rem;
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.08);
    margin-bottom: 22px;
}

.wallet-tab {
    min-width: 138px;
    min-height: 46px;
    border: none;
    border-radius: 0.9rem;
    background: transparent;
    color: #5f7288;
    font-weight: 700;
    transition: transform 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, background 0.2s ease;
}

.wallet-tab:hover {
    transform: scale(1.02);
    color: #154677;
}

.wallet-tab.is-active {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    box-shadow: 0 18px 32px rgba(21, 70, 119, 0.16);
}

.wallet-panel {
    border-radius: 1.75rem;
    padding: 24px;
}

.panel-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.panel-skeleton,
.transaction-skeleton,
.details-skeleton {
    border-radius: 1.25rem;
    background: linear-gradient(90deg, #eef5fb 25%, #e5eff7 50%, #eef5fb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.25s linear infinite;
}

.panel-skeleton {
    min-height: 150px;
}

.info-card {
    border-radius: 1.35rem;
    background: linear-gradient(180deg, #ffffff, #f9fcff);
    border: 1px solid rgba(21, 70, 119, 0.08);
    padding: 20px;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.info-card:hover {
    transform: scale(1.02);
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.08);
}

.info-card__label {
    display: block;
    color: #5f7288;
    font-size: 0.82rem;
    margin-bottom: 10px;
}

.info-card h3 {
    margin: 0;
    color: #154677;
    font-size: 1.2rem;
    font-weight: 800;
}

.info-card p {
    margin: 10px 0 0;
    color: #5f7288;
    line-height: 1.7;
}

.transactions-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.transactions-header h2 {
    margin: 0 0 6px;
    color: #154677;
    font-size: 1.5rem;
    font-weight: 800;
}

.transactions-header p {
    margin: 0;
    color: #5f7288;
}

.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.transaction-card,
.transaction-skeleton {
    min-height: 108px;
    width: 100%;
    border-radius: 1.25rem;
    border: 1px solid rgba(21, 70, 119, 0.08);
    background: linear-gradient(180deg, #ffffff, #f9fcff);
    padding: 18px 20px;
}

.transaction-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    text-align: start;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.transaction-card:hover {
    transform: scale(1.02);
    border-color: rgba(43, 166, 222, 0.26);
    box-shadow: 0 20px 38px rgba(21, 70, 119, 0.1);
}

.transaction-card__left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.transaction-card__left h3 {
    margin: 0 0 6px;
    color: #154677;
    font-size: 1.1rem;
    font-weight: 800;
}

.transaction-card__left p,
.transaction-card__right span {
    margin: 0;
    color: #5f7288;
    font-size: 0.9rem;
}

.transaction-card__left i {
    margin: 0 6px;
}

.transaction-card__right {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-end;
}

.transaction-card__right strong {
    color: #154677;
    font-size: 0.92rem;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 78px;
    padding: 0.65rem 0.85rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: capitalize;
}

.type-badge.credit {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
}

.type-badge.debit {
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
}

.pagination-bar {
    margin-top: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding-top: 18px;
    border-top: 1px solid rgba(21, 70, 119, 0.08);
}

.pagination-button,
.inline-action,
.details-close {
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.pagination-button,
.inline-action {
    min-height: 44px;
    padding: 0 16px;
    border-radius: 0.95rem;
    background: #2ba6de;
    color: #ffffff;
    font-weight: 700;
}

.pagination-button:hover:not(:disabled),
.inline-action:hover,
.details-close:hover {
    transform: scale(1.02);
}

.pagination-button:hover:not(:disabled),
.inline-action:hover {
    background: #2398cb;
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.16);
}

.pagination-button:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.pagination-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: center;
    color: #5f7288;
}

.pagination-meta strong {
    color: #154677;
}

.empty-state {
    min-height: 320px;
    border-radius: 1.35rem;
    border: 1px dashed rgba(21, 70, 119, 0.16);
    background: linear-gradient(180deg, #ffffff, #f8fbff);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 32px 20px;
}

.empty-state__icon {
    width: 72px;
    height: 72px;
    display: grid;
    place-items: center;
    border-radius: 1.25rem;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
    font-size: 1.8rem;
    margin-bottom: 16px;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #154677;
    font-size: 1.25rem;
    font-weight: 800;
}

.empty-state p {
    margin: 0;
    max-width: 28rem;
    color: #5f7288;
    line-height: 1.8;
}

.error-banner {
    margin-top: 18px;
    padding: 14px 18px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #154677;
}

.details-overlay {
    position: fixed;
    inset: 0;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(21, 70, 119, 0.22);
    backdrop-filter: blur(8px);
    z-index: 50;
}

.details-modal {
    width: min(100%, 760px);
    border-radius: 1.75rem;
    padding: 24px;
}

.details-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.details-modal__kicker {
    margin: 0 0 8px;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #5f7288;
}

.details-modal__header h2 {
    margin: 0;
    color: #154677;
    font-size: 1.4rem;
    word-break: break-word;
}

.details-close {
    width: 42px;
    height: 42px;
    border-radius: 999px;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.details-item,
.details-summary {
    border-radius: 1.2rem;
    border: 1px solid rgba(21, 70, 119, 0.08);
    background: linear-gradient(180deg, #ffffff, #f9fcff);
}

.details-item {
    padding: 16px;
}

.details-item span,
.details-summary span {
    display: block;
    color: #5f7288;
    font-size: 0.8rem;
    margin-bottom: 6px;
}

.details-item strong,
.details-summary strong {
    color: #154677;
    font-size: 1rem;
    word-break: break-word;
}

.details-summary {
    margin-top: 16px;
    padding: 18px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.details-skeletons {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.details-skeleton {
    min-height: 110px;
}

.wallet-tab-switch-enter-active,
.wallet-tab-switch-leave-active {
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.wallet-tab-switch-enter-from,
.wallet-tab-switch-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

@media (max-width: 960px) {
    .balance-grid,
    .details-grid,
    .details-summary,
    .panel-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .wallet-header,
    .pagination-bar,
    .transaction-card,
    .details-modal__header {
        flex-direction: column;
        align-items: stretch;
    }

    .wallet-tabs {
        display: grid;
        width: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .wallet-tab {
        width: 100%;
    }

    .transaction-card__left,
    .transaction-card__right {
        align-items: flex-start;
    }

    .transaction-card__right {
        width: 100%;
    }
}

@media (max-width: 560px) {
    .wallet-page {
        padding: 20px 14px 36px;
    }

    .balance-card__content,
    .summary-card,
    .wallet-panel,
    .details-modal {
        padding: 18px;
    }
}

/* =========================
   DARK MODE
========================= */
:global(html[data-theme="dark"]) .wallet-page {
    background:
        radial-gradient(
            circle at 85% 10%,
            rgba(43, 166, 222, 0.06),
            transparent 32%
        ),
        linear-gradient(
            180deg,
            #111315 0%,
            #15181b 100%
        );
    color: #f1f3f5;
}

:global(html[data-theme="dark"]) .wallet-header h1,
:global(html[data-theme="dark"]) .summary-card h3,
:global(html[data-theme="dark"]) .summary-card__footer strong,
:global(html[data-theme="dark"]) .info-card h3,
:global(html[data-theme="dark"]) .transactions-header h2,
:global(html[data-theme="dark"]) .transaction-card__left h3,
:global(html[data-theme="dark"]) .transaction-card__right strong,
:global(html[data-theme="dark"]) .pagination-meta strong,
:global(html[data-theme="dark"]) .empty-state h3,
:global(html[data-theme="dark"]) .details-modal__header h2,
:global(html[data-theme="dark"]) .details-item strong,
:global(html[data-theme="dark"]) .details-summary strong {
    color: #f1f3f5;
}

:global(html[data-theme="dark"]) .wallet-header p,
:global(html[data-theme="dark"]) .summary-card__label,
:global(html[data-theme="dark"]) .summary-card__small,
:global(html[data-theme="dark"]) .info-card p,
:global(html[data-theme="dark"]) .transactions-header p,
:global(html[data-theme="dark"]) .transaction-card__left p,
:global(html[data-theme="dark"]) .transaction-card__right span,
:global(html[data-theme="dark"]) .pagination-meta,
:global(html[data-theme="dark"]) .empty-state p,
:global(html[data-theme="dark"]) .details-modal__kicker,
:global(html[data-theme="dark"]) .details-item span,
:global(html[data-theme="dark"]) .details-summary span {
    color: #9299a1;
}

:global(html[data-theme="dark"]) .wallet-kicker {
    background: rgba(43, 166, 222, 0.12);
    color: #2ba6de;
}

:global(html[data-theme="dark"]) .summary-card,
:global(html[data-theme="dark"]) .wallet-panel,
:global(html[data-theme="dark"]) .wallet-tabs,
:global(html[data-theme="dark"]) .error-banner {
    background: #1b1f23;
    border-color: rgba(255, 255, 255, 0.09);
    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.24);
}

:global(html[data-theme="dark"]) .balance-card {
    border-color: rgba(255, 255, 255, 0.14);
    box-shadow: 0 22px 48px rgba(0, 0, 0, 0.28);
}

:global(html[data-theme="dark"]) .balance-card__meta {
    background: rgba(27, 31, 35, 0.42);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

:global(html[data-theme="dark"]) .summary-card__row,
:global(html[data-theme="dark"]) .pagination-bar {
    border-color: rgba(255, 255, 255, 0.09);
}

:global(html[data-theme="dark"]) .summary-chip--soft,
:global(html[data-theme="dark"]) .type-badge.debit {
    background: rgba(43, 166, 222, 0.13);
    color: #2ba6de;
}

:global(html[data-theme="dark"]) .wallet-tab {
    color: #c7ccd1;
}

:global(html[data-theme="dark"]) .wallet-tab:hover {
    color: #f1f3f5;
    background: #20252a;
}

:global(html[data-theme="dark"]) .wallet-tab.is-active {
    color: #ffffff;
}

:global(html[data-theme="dark"]) .info-card,
:global(html[data-theme="dark"]) .transaction-card,
:global(html[data-theme="dark"]) .empty-state,
:global(html[data-theme="dark"]) .details-item,
:global(html[data-theme="dark"]) .details-summary {
    background: #20252a;
    border-color: rgba(255, 255, 255, 0.09);
}

:global(html[data-theme="dark"]) .info-card:hover,
:global(html[data-theme="dark"]) .transaction-card:hover {
    background: #252b31;
    border-color: rgba(43, 166, 222, 0.42);
    box-shadow: 0 18px 38px rgba(0, 0, 0, 0.24);
}

:global(html[data-theme="dark"]) .empty-state {
    border-style: dashed;
}

:global(html[data-theme="dark"]) .empty-state__icon,
:global(html[data-theme="dark"]) .details-close {
    background: rgba(43, 166, 222, 0.12);
    color: #2ba6de;
}

:global(html[data-theme="dark"]) .error-banner {
    color: #f87171;
}

:global(html[data-theme="dark"]) .details-overlay {
    background: rgba(0, 0, 0, 0.62);
}

:global(html[data-theme="dark"]) .details-modal {
    background: #252b31;
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 28px 70px rgba(0, 0, 0, 0.38);
}

:global(html[data-theme="dark"]) .panel-skeleton,
:global(html[data-theme="dark"]) .transaction-skeleton,
:global(html[data-theme="dark"]) .details-skeleton {
    background: linear-gradient(90deg, #1b1f23 25%, #252b31 50%, #1b1f23 75%);
    background-size: 200% 100%;
}

:global(html[data-theme="dark"]) .wallet-panel,
:global(html[data-theme="dark"]) .transactions-list,
:global(html[data-theme="dark"]) .details-modal {
    scrollbar-color: #3a424a #171a1d;
}

:global(html[data-theme="dark"]) .wallet-panel::-webkit-scrollbar,
:global(html[data-theme="dark"]) .transactions-list::-webkit-scrollbar,
:global(html[data-theme="dark"]) .details-modal::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

:global(html[data-theme="dark"]) .wallet-panel::-webkit-scrollbar-track,
:global(html[data-theme="dark"]) .transactions-list::-webkit-scrollbar-track,
:global(html[data-theme="dark"]) .details-modal::-webkit-scrollbar-track {
    background: #171a1d;
}

:global(html[data-theme="dark"]) .wallet-panel::-webkit-scrollbar-thumb,
:global(html[data-theme="dark"]) .transactions-list::-webkit-scrollbar-thumb,
:global(html[data-theme="dark"]) .details-modal::-webkit-scrollbar-thumb {
    background: #3a424a;
    border-radius: 999px;
}
</style>


