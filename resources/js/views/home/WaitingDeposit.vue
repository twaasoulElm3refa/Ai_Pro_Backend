<template>
    <main class="waiting-container" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="waiting-payment-title">
        <section class="card" role="status" aria-live="polite">
            <div class="spinner" aria-hidden="true"></div>
            <h1 id="waiting-payment-title">{{ t("user.deposit.waiting.title") }}</h1>
            <p>{{ t("user.deposit.waiting.subtitle") }}</p>
            <p class="hint">{{ t("user.deposit.waiting.hint") }}</p>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import axios from "axios";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";

const router = useRouter();
const route = useRoute();
const { t, locale } = useI18n();
const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar");
const seoTitle = computed(() => (isArabic.value ? "انتظار تأكيد الدفع | Ai Pro" : "Payment Verification | Ai Pro"));
const seoDescription = computed(() =>
    isArabic.value
        ? "نقوم الآن بتأكيد حالة دفع PayPal. اترك الصفحة مفتوحة حتى نتحقق من العملية ونحوّلك تلقائيًا فور انتهاء المعالجة."
        : "We are confirming your PayPal payment status. Keep this page open while we verify the transaction and redirect you automatically once processing finishes."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});
const orderId = route.query.order_id;

const currentLang = () => homeService.getLang();

let interval = null;
const attempts = ref(0);
const MAX_ATTEMPTS = 30;

const checkStatus = async () => {
    try {
        attempts.value++;

        const url = `https://pro.aiarabic.com/api/v1/wallet/order-status/${orderId}`;
        const token = localStorage.getItem("auth_token");
        const { data } = await axios.get(url, {
            headers: token ? { Authorization: `Bearer ${token}` } : {},
        });
        // ✅ النجاح
        if (data.status === "completed") {
            clearInterval(interval);
            router.push(`/${currentLang()}/deposit/success`);
            return;
        }

        // ❌ فشل حقيقي بس
        if (data.status === "failed") {
            clearInterval(interval);
            router.push(`/${currentLang()}/failed`);
            return;
        }

        // ⚠️ تجاهل الحالات المؤقتة
        if (["pending", "approved", "not_found"].includes(data.status)) {
            return;
        }

        // fallback
        if (attempts.value >= MAX_ATTEMPTS) {
            clearInterval(interval);
            router.push(`/${currentLang()}/failed`);
        }

    } catch {
        // Keep polling on transient errors.
    }
};

onMounted(() => {
    locale.value = homeService.getLang();
    if (!orderId) {
        router.push(`/${currentLang()}/failed`);
        return;
    }

    checkStatus();
    interval = setInterval(checkStatus, 5000);
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});
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
    max-width: 420px;
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

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

h1 {
    font-size: 1.4rem;
    color: #1a1a1a;
    margin-bottom: 10px;
}

p {
    color: #666;
    font-size: 0.95rem;
}

.hint {
    margin-top: 8px;
    font-size: 0.82rem;
    color: #aaa;
}
</style>
