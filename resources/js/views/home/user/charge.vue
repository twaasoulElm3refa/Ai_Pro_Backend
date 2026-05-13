<template>
    <main class="deposit-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="charge-wallet-title">
        <!-- Current Balance -->
        <section class="balance-card" :aria-label="t('user.charge.balanceSummaryAria')">
            <p class="label">{{ t("user.charge.currentBalance") }}</p>
            <p class="amount">${{ wallet?.amount ?? '0.00' }}</p>
            <p class="currency">USD</p>
        </section>

        <!-- Deposit Form -->
        <section class="deposit-card" :aria-label="t('user.charge.formAria')">
            <h1 id="charge-wallet-title">{{ t("user.charge.title") }}</h1>

            <!-- Preset Amounts -->
            <div class="presets">
                <button v-for="val in presets" :key="val" :class="['preset-btn', { active: selectedPreset === val }]"
                    type="button" :aria-label="t('user.charge.selectPresetAria', { value: val })" @click="selectPreset(val)">
                    ${{ val }}
                </button>
            </div>

            <!-- Custom Amount -->
            <div class="input-group">
                <label>{{ t("user.charge.customAmountLabel") }}</label>
                <div class="amount-wrap">
                    <span class="currency-badge">$</span>
                    <input id="charge-amount" v-model.number="form.amount" type="number" min="1" step="0.01"
                        placeholder="0.00" :aria-label="t('user.charge.customAmountAria')" @input="selectedPreset = null" />
                </div>
                <p v-if="amountError" class="error">{{ amountError }}</p>
            </div>

            <!-- Description -->
            <div class="input-group">
                <label>{{ t("user.charge.descriptionLabel") }}</label>
                <input id="charge-description" v-model="form.description" type="text" maxlength="255" :aria-label="t('user.charge.descriptionAria')" :placeholder="t('user.charge.descriptionPlaceholder')" />
            </div>

            <hr />

            <!-- Summary -->
            <div class="summary-row">
                <span>{{ t("user.charge.summaryAmount") }}</span>
                <span>{{ form.amount ? '$' + Number(form.amount).toFixed(2) : '—' }}</span>
            </div>
            <div class="summary-row">
                <span>{{ t("user.charge.summaryCurrency") }}</span>
                <span>USD</span>
            </div>
            <div class="summary-row">
                <span>{{ t("user.charge.summaryMethod") }}</span>
                <span class="paypal-text">PayPal</span>
            </div>

            <!-- Submit -->
            <button type="button" class="pay-btn" :disabled="!isValid || loading" @click="handlePay">
                <span v-if="loading">{{ t("user.charge.loading") }}</span>
                <span v-else>{{ t("user.charge.payButton") }}</span>
            </button>

            <p v-if="serverError" class="error" style="margin-top:12px;text-align:center">
                {{ serverError }}
            </p>
        </section>
    </main>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute } from "vue-router"
import { useI18n } from "vue-i18n"
import axios from 'axios'
import { v4 as uuidv4 } from 'uuid'
import useSeoMeta from "@/composables/useSeoMeta"
import homeService from "@/services/home/homeService"

/* =========================
   Axios Instance (with token)
========================= */
const api = axios.create({
    baseURL: "https://pro.aiarabic.com/api",
    headers: {
        Accept: "application/json",
    }
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem("auth_token")
    const Api_key = 'L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy'
    config.headers["Accept-Language"] = homeService.getLang();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    if (Api_key) {
        config.headers['x-api-key'] = Api_key
    }

    return config
})

/* =========================
   Props
========================= */
const props = defineProps({
    wallet: { type: Object, default: null },
})
const route = useRoute()
const { t, locale } = useI18n()

/* =========================
   State
========================= */
const idempotencyKey = ref(uuidv4())

const presets = [10, 25, 50, 100]
const selectedPreset = ref(null)
const loading = ref(false)
const serverError = ref('')

const form = ref({
    amount: '',
    description: '',
})

const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar")
const seoTitle = computed(() => (isArabic.value ? "شحن المحفظة | Ai Pro" : "Charge Wallet | Ai Pro"))
const seoDescription = computed(() =>
    isArabic.value
        ? "اشحن محفظتك بأمان عبر PayPal، اختر مبلغًا ثابتًا أو مخصصًا، وراجع تفاصيل الدفع بدقة قبل إتمام عملية إضافة الرصيد إلى حسابك."
        : "Add funds to your wallet securely with PayPal, select a preset or custom amount, and review payment details before completing your balance charge."
)

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
})

/* =========================
   Actions
========================= */
function selectPreset(val) {
    selectedPreset.value = val
    form.value.amount = val
}

/* =========================
   Validation
========================= */
const amountError = computed(() => {
    if (!form.value.amount) return ''
    if (form.value.amount < 1) return t("user.charge.amountError")
    return ''
})

const isValid = computed(() => {
    return form.value.amount >= 1 && !amountError.value
})

/* =========================
   Submit Payment
========================= */
async function handlePay() {
    if (!isValid.value) return

    loading.value = true
    serverError.value = ''

    try {
        const { data } = await api.post('/v1/deposit/pay', {
            amount: Number(form.value.amount).toFixed(2),
            description: form.value.description || t("user.charge.defaultDescription"),
            idempotency_key: idempotencyKey.value,
        })

        // redirect to PayPal approval
        if (data?.approval_url) {
            window.location.href = data.approval_url
        }

    } catch (err) {
        serverError.value =
            err.response?.data?.message || t("user.charge.genericError")
    } finally {
        loading.value = false
    }
}
</script>
<style scoped>
.deposit-page {
    max-width: 520px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.balance-card {
    background: #f5f5f5;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.balance-card .label {
    font-size: 13px;
    color: #888;
    margin-bottom: 4px;
}

.balance-card .amount {
    font-size: 32px;
    font-weight: 500;
}

.balance-card .currency {
    font-size: 13px;
    color: #aaa;
}

.deposit-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 1.5rem;
}

.deposit-card h1 {
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 1.25rem;
}

.presets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 1.25rem;
}

.preset-btn {
    padding: 10px 0;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
}

.preset-btn:hover {
    background: #f5f5f5;
}

.preset-btn.active {
    border: 2px solid #2ba6de;
    color: #2ba6de;
    background: #E6F1FB;
}

.input-group {
    margin-bottom: 1rem;
}

.input-group label {
    display: block;
    font-size: 13px;
    color: #888;
    margin-bottom: 6px;
}

.amount-wrap {
    position: relative;
}

.currency-badge {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: #888;
}

.amount-wrap input {
    width: 100%;
    padding: 10px 12px 10px 44px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 500;
    outline: none;
}

.amount-wrap input:focus {
    border-color: #2ba6de;
}

.input-group input[type="text"] {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
}

hr {
    border: none;
    border-top: 1px solid #f0f0f0;
    margin: 1.25rem 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin-bottom: 8px;
}

.summary-row span:first-child {
    color: #888;
}

.paypal-text {
    color: #154677;
    font-weight: 500;
}

.pay-btn {
    width: 100%;
    padding: 12px;
    margin-top: 1.25rem;
    background: #2ba6de;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: opacity .15s;
}

.pay-btn:hover {
    opacity: .9;
}

.pay-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.error {
    font-size: 13px;
    color: #e24b4a;
    margin-top: 6px;
}
</style>


