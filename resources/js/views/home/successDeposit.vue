<template>
    <main class="success-page" aria-labelledby="success-payment-title">
        <!-- Background particles -->
        <div class="particles">
            <div v-for="i in 8" :key="i" class="particle"
                :style="{ left: (i * 12) + '%', animationDelay: (i * 0.3) + 's' }"></div>
        </div>

        <div class="card">
            <!-- PayPal badge -->
            <div class="paypal-badge">
                <div class="paypal-dot"></div>
                تم شحن الرصيد عبر PayPal
            </div>

            <!-- Success icon -->
            <div class="icon-wrap">
                <div class="icon-ring"></div>
                <div class="icon-circle">
                    <svg width="38" height="38" viewBox="0 0 38 38" fill="none">
                        <polyline class="checkmark" points="8,20 16,28 30,12" stroke="#fff" stroke-width="3.5"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>

            <h1 id="success-payment-title">تم شحن الرصيد بنجاح 🎉</h1>
            <p class="subtitle">
                تم إضافة المبلغ إلى محفظتك بنجاح<br />
                يمكنك الآن استخدامه أو تحميل مشترياتك
            </p>

            <!-- Info -->
            <div class="info-row">
                <div class="info-icon">💰</div>
                <div>
                    <div class="info-label">حالة العملية</div>
                    <div class="info-val">ناجحة</div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon">📧</div>
                <div>
                    <div class="info-label">الإشعار</div>
                    <div class="info-val">تم إرسال رسالة لبريدك</div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon">💳</div>
                <div>
                    <div class="info-label">رقم العملية</div>
                    <div class="info-val">{{ transactionId }}</div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Buttons -->
            <button type="button" @click="goToDownloads" class="btn-downloads" aria-label="Go to wallet page">
                <span class="btn-arrow">⬇</span>
                الذهاب إلى المحفظة
            </button>

            <button type="button" @click="goHome" class="sec-link" aria-label="Back to home page">
                العودة إلى الصفحة الرئيسية
            </button>
        </div>
    </main>
</template>

<script>
import { computed } from "vue";
import { useRoute } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";

export default {
    name: "PaymentSuccess",

    setup() {
        const route = useRoute();
        const isArabic = computed(() => String(route.params.lang || localStorage.getItem("language") || "en").toLowerCase() === "ar");
        const seoTitle = computed(() => (isArabic.value ? "نجاح الشحن | Ai Pro" : "Deposit Success | Ai Pro"));
        const seoDescription = computed(() =>
            isArabic.value
                ? "تم شحن محفظتك بنجاح. راجع تفاصيل العملية، عد إلى المحفظة مباشرة، وواصل استخدام الأدوات برصيدك المحدث دون أي خطوات إضافية."
                : "Your wallet charge was successful. Confirm transaction details, return to your wallet, and continue using tools with your updated balance immediately."
        );

        useSeoMeta({
            title: seoTitle,
            description: seoDescription,
        });
    },

    computed: {
        transactionId() {
            return this.$route.query.token || "PP-" + Date.now();
        },
    },

    methods: {
        goToDownloads() {
            const lang = localStorage.getItem("language") || "en";
            this.$router.push(`/${lang}/wallet`);
        },
        goHome() {
            const lang = localStorage.getItem("language") || "en";
            this.$router.push(`/${lang}`);
        },
    },
};
</script>

<style scoped>
.success-page {
    font-family: 'Cairo', sans-serif;
    direction: rtl;
    background: #fff;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

/* particles */
.particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: #154677;
    border-radius: 50%;
    animation: float 4s infinite;
}

@keyframes float {
    from {
        bottom: -10px;
        opacity: 0;
    }

    to {
        bottom: 100%;
        opacity: 1;
    }
}

/* card */
.card {
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    width: 420px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

/* badge */
.paypal-badge {
    background: #f3f3f3;
    padding: 6px 14px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    margin-bottom: 20px;
}

.paypal-dot {
    width: 8px;
    height: 8px;
    background: #154677;
    border-radius: 50%;
}

/* icon */
.icon-circle {
    background: #154677;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* info */
.info-row {
    display: flex;
    gap: 10px;
    background: #f7f7f7;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 10px;
    text-align: right;
}

.info-label {
    font-size: 12px;
    color: #888;
}

.info-val {
    font-weight: bold;
}

/* buttons */
.btn-downloads {
    width: 100%;
    padding: 14px;
    background: #154677;
    color: white;
    border-radius: 12px;
    margin-top: 20px;
    cursor: pointer;
    border: none;
    font-weight: bold;
}

.btn-downloads:hover {
    background: #154677;
}

.sec-link {
    margin-top: 10px;
    background: none;
    border: none;
    color: #777;
    cursor: pointer;
}
</style>

