<template>
    <main class="waiting-container" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="waiting-payment-title">
        <section class="card" role="status" aria-live="polite">
            <div v-if="viewState === 'waiting'" class="spinner" aria-hidden="true"></div>
            <h1 id="waiting-payment-title">{{ title }}</h1>
            <p>{{ message }}</p>
            <p v-if="viewState === 'waiting'" class="hint">{{ t("user.deposit.waiting.hint") }}</p>
            <div v-else class="actions">
                <button v-if="viewState === 'timeout'" type="button" @click="retryPolling">{{ retryLabel }}</button>
                <button type="button" @click="goToWallet">{{ walletLabel }}</button>
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";
import api from "@/services/ApiClient";

const router = useRouter();
const route = useRoute();
const { t, locale } = useI18n();
const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar");
const seoTitle = computed(() => isArabic.value ? "انتظار تأكيد الدفع | Ai Pro" : "Payment Verification | Ai Pro");
const seoDescription = computed(() => isArabic.value
    ? "نتحقق من حالة دفع PayPal ونحوّلك تلقائيًا عند اكتمالها."
    : "We are confirming your PayPal payment and will redirect you when it completes.");

useSeoMeta({ title: seoTitle, description: seoDescription });

const orderId = String(route.query.order_id || "");
const currentLang = () => homeService.getLang();
const pollIntervalMs = Math.max(1000, Number(import.meta.env.VITE_WALLET_POLL_INTERVAL_MS || 5000));
const pollTimeoutMs = Math.max(pollIntervalMs, Number(import.meta.env.VITE_WALLET_POLL_TIMEOUT_MS || 150000));
const MAX_ATTEMPTS = Math.max(1, Math.ceil(pollTimeoutMs / pollIntervalMs));
const attempts = ref(0);
const viewState = ref("waiting");
const lastError = ref("");
let timer = null;
let controller = null;
let isChecking = false;
let stopped = false;
let deadline = 0;

const title = computed(() => {
    if (viewState.value === "timeout") return isArabic.value ? "يستغرق التأكيد وقتًا أطول" : "Confirmation is taking longer";
    if (viewState.value === "error") return isArabic.value ? "تعذر التحقق من الدفع" : "Unable to verify payment";
    return t("user.deposit.waiting.title");
});
const message = computed(() => {
    if (viewState.value === "timeout") {
        return isArabic.value
            ? "لم نعتبر الدفع فاشلًا. يمكنك إعادة التحقق أو العودة للمحفظة، وسيستمر الخادم في المصالحة تلقائيًا."
            : "The payment was not marked as failed. Retry verification or return to your wallet; server reconciliation will continue.";
    }
    if (viewState.value === "error") return lastError.value;
    return t("user.deposit.waiting.subtitle");
});
const retryLabel = computed(() => isArabic.value ? "إعادة التحقق" : "Retry verification");
const walletLabel = computed(() => isArabic.value ? "العودة إلى المحفظة" : "Back to wallet");

function stopPolling() {
    stopped = true;
    if (timer) clearTimeout(timer);
    timer = null;
    controller?.abort();
}

function schedule(delay = pollIntervalMs) {
    if (!stopped) timer = setTimeout(() => {
        timer = null;
        checkStatus();
    }, delay);
}

async function finish(path) {
    stopPolling();
    await router.replace(path);
}

function showError(messageText) {
    stopPolling();
    viewState.value = "error";
    lastError.value = messageText;
}

async function checkStatus() {
    if (stopped || isChecking) return;
    if (attempts.value >= MAX_ATTEMPTS || Date.now() >= deadline) {
        stopPolling();
        viewState.value = "timeout";
        return;
    }

    isChecking = true;
    attempts.value++;
    controller = new AbortController();
    try {
        const { data } = await api.get(`/wallet/order-status/${encodeURIComponent(orderId)}`, {
            signal: controller.signal,
        });
        if (data.status === "completed") {
            await finish(`/${currentLang()}/deposit/success?order_id=${data.order_id}`);
            return;
        }
        if (data.status === "failed") {
            await finish(`/${currentLang()}/failed?error=PAYMENT_FAILED`);
            return;
        }
        if (!["pending", "approved"].includes(data.status)) {
            showError(isArabic.value ? "أعاد الخادم حالة دفع غير معروفة." : "The server returned an unknown payment state.");
        }
    } catch (error) {
        if (error?.name === "CanceledError" || error?.code === "ERR_CANCELED") return;
        const status = error.response?.status;
        if (status === 401) {
            await finish(`/${currentLang()}/auth`);
            return;
        }
        if (status === 403) {
            showError(isArabic.value ? "لا تملك صلاحية الوصول إلى طلب الدفع." : "You cannot access this payment order.");
            return;
        }
        if (status === 404 && attempts.value >= 3) {
            showError(isArabic.value ? "لم يتم العثور على طلب الدفع." : "The payment order was not found.");
            return;
        }
        if (status === 429) {
            const retryAfter = Number(error.response?.headers?.["retry-after"] || 0) * 1000;
            schedule(Math.max(pollIntervalMs, retryAfter));
            return;
        }
        if (status && status < 500) {
            showError(isArabic.value ? "تعذر التحقق من حالة الدفع." : "Payment verification failed.");
        }
    } finally {
        isChecking = false;
        if (!stopped && !timer) schedule();
    }
}

function retryPolling() {
    stopPolling();
    stopped = false;
    viewState.value = "waiting";
    attempts.value = 0;
    deadline = Date.now() + pollTimeoutMs;
    checkStatus();
}

function goToWallet() {
    finish(`/${currentLang()}/wallet`);
}

onMounted(() => {
    locale.value = currentLang();
    if (!/^\d+$/.test(orderId)) {
        showError(isArabic.value ? "معرّف طلب الدفع غير صالح." : "The payment order ID is invalid.");
        return;
    }
    deadline = Date.now() + pollTimeoutMs;
    checkStatus();
});

onUnmounted(stopPolling);
</script>

<style scoped>
.waiting-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
}
.card {
    background: white;
    border-radius: 16px;
    padding: 48px 40px;
    text-align: center;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
    max-width: 460px;
    width: 90%;
}
.spinner {
    width: 56px;
    height: 56px;
    border: 5px solid #e0e0e0;
    border-top-color: #2ba6de;
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
    margin: 0 auto 24px;
}
@keyframes spin { to { transform: rotate(360deg); } }
h1 { font-size: 1.4rem; color: #1a1a1a; margin-bottom: 10px; }
p { color: #666; font-size: 0.95rem; }
.hint { margin-top: 8px; font-size: 0.82rem; color: #888; }
.actions { display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
.actions button { border: 0; border-radius: 8px; padding: 10px 14px; cursor: pointer; }
.actions button:first-child { background: #2ba6de; color: white; }
</style>
