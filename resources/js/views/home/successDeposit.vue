<template>
    <main class="success-page" :dir="$i18n.locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="success-payment-title">
        <div class="particles">
            <div v-for="i in 8" :key="i" class="particle"
                :style="{ left: (i * 12) + '%', animationDelay: (i * 0.3) + 's' }"></div>
        </div>

        <div class="card">
            <div class="paypal-badge">
                <div class="paypal-dot"></div>
                {{ $t("user.deposit.success.badge") }}
            </div>

            <div class="icon-wrap">
                <div class="icon-ring"></div>
                <div class="icon-circle">
                    <svg width="38" height="38" viewBox="0 0 38 38" fill="none">
                        <polyline class="checkmark" points="8,20 16,28 30,12" stroke="#fff" stroke-width="3.5"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>

            <h1 id="success-payment-title">{{ $t("user.deposit.success.title") }}</h1>
            <p class="subtitle" v-html="$t('user.deposit.success.subtitleHtml')"></p>

            <div class="info-row">
                <div class="info-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
                <div>
                    <div class="info-label">{{ $t("user.deposit.success.statusLabel") }}</div>
                    <div class="info-val">{{ $t("user.deposit.success.statusValue") }}</div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon"><i class="bi bi-bell-fill" aria-hidden="true"></i></div>
                <div>
                    <div class="info-label">{{ $t("user.deposit.success.notificationLabel") }}</div>
                    <div class="info-val">{{ $t("user.deposit.success.notificationValue") }}</div>
                </div>
            </div>

            <div class="info-row">
                <div class="info-icon"><i class="bi bi-hash" aria-hidden="true"></i></div>
                <div>
                    <div class="info-label">{{ $t("user.deposit.success.transactionIdLabel") }}</div>
                    <div class="info-val">{{ transactionId }}</div>
                </div>
            </div>

            <div class="divider"></div>

            <button type="button" @click="goToDownloads" class="btn-downloads" :aria-label="$t('user.deposit.success.walletAria')">
                <span class="btn-arrow"><i class="bi bi-arrow-left-short" aria-hidden="true"></i></span>
                {{ $t("user.deposit.success.walletButton") }}
            </button>

            <button type="button" @click="goHome" class="sec-link" :aria-label="$t('user.deposit.success.homeAria')">
                {{ $t("user.deposit.success.homeButton") }}
            </button>
        </div>
    </main>
</template>

<script>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";

export default {
    name: "PaymentSuccess",

    setup() {
        const { t } = useI18n();
        const seoTitle = computed(() => t("user.deposit.success.seoTitle"));
        const seoDescription = computed(() => t("user.deposit.success.seoDescription"));

        useSeoMeta({
            title: seoTitle,
            description: seoDescription,
        });
    },

    computed: {
        provider() {
            if (this.$route.query.provider) {
                return String(this.$route.query.provider).toLowerCase();
            }

            return this.$route.query.deposit_id ? "moyasar" : "paypal";
        },
        transactionId() {
            if (this.provider === "moyasar") {
                return this.$route.query.deposit_id || "-";
            }

            return this.$route.query.order_id || "-";
        },
    },

    methods: {
        goToDownloads() {
            const lang = homeService.getLang();
            this.$router.push(`/${lang}/wallet`);
        },
        goHome() {
            const lang = homeService.getLang();
            this.$router.push(`/${lang}`);
        },
    },
};
</script>

<style scoped>
.success-page {
    font-family: 'Cairo', sans-serif;
    background: #fff;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

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

.card {
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    width: 420px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

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

.info-row {
    display: flex;
    gap: 10px;
    background: #f7f7f7;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 10px;
    text-align: start;
}

.info-label {
    font-size: 12px;
    color: #888;
}

.info-val {
    font-weight: bold;
}

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
/* =========================
   DARK MODE
========================= */
:global(html[data-theme="dark"]) .success-page {
    background: var(--theme-bg);
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .success-page .card {
    background: var(--theme-surface);
    border: 1px solid var(--theme-border);
    box-shadow: 0 18px 44px var(--theme-shadow);
}

:global(html[data-theme="dark"]) .success-page h1,
:global(html[data-theme="dark"]) .info-val {
    color: var(--theme-text-primary);
}

:global(html[data-theme="dark"]) .success-page .subtitle {
    color: var(--theme-text-secondary);
}

:global(html[data-theme="dark"]) .paypal-badge,
:global(html[data-theme="dark"]) .info-row {
    background: var(--theme-surface-secondary);
    border: 1px solid var(--theme-border);
}

:global(html[data-theme="dark"]) .paypal-dot,
:global(html[data-theme="dark"]) .icon-circle {
    background: #4ade80;
}

:global(html[data-theme="dark"]) .info-icon {
    color: #4ade80;
}

:global(html[data-theme="dark"]) .info-label {
    color: var(--theme-text-muted);
}

:global(html[data-theme="dark"]) .sec-link {
    color: var(--theme-text-secondary);
}

:global(html[data-theme="dark"]) .sec-link:hover {
    color: var(--theme-accent);
}
</style>
