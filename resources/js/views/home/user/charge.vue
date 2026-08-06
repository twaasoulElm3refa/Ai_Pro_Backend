<template>
    <main class="deposit-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="charge-wallet-title">
        <section class="balance-card" :aria-label="t('user.charge.balanceSummaryAria')">
            <p class="label">{{ t("user.charge.currentBalance") }}</p>
            <p class="amount">{{ formatPoints(walletSnapshot?.balance ?? walletSnapshot?.amount ?? 0) }}</p>
            <p class="currency">{{ t("user.charge.balancePoints") }}</p>
        </section>

        <section class="deposit-card" :aria-label="t('user.charge.formAria')">
            <h1 id="charge-wallet-title">{{ t("user.charge.title") }}</h1>

            <div class="method-options" role="radiogroup" :aria-label="t('user.charge.summaryMethod')">
                <button
                    v-for="method in paymentMethods"
                    :key="method.value"
                    type="button"
                    class="method-option"
                    :class="{ active: paymentMethod === method.value }"
                    role="radio"
                    :aria-checked="paymentMethod === method.value"
                    @click="selectPaymentMethod(method.value)"
                >
                    <span>{{ method.label }}</span>
                    <small>{{ method.caption }}</small>
                </button>
            </div>

            <div class="presets">
                <button
                    v-for="val in presets"
                    :key="val"
                    :class="['preset-btn', { active: selectedPreset === val }]"
                    type="button"
                    :aria-label="t('user.charge.selectPresetAria', { value: val })"
                    @click="selectPreset(val)"
                >
                    {{ currencySymbol }}{{ val }}
                </button>
            </div>

            <div class="input-group">
                <label>{{ t("user.charge.customAmountLabel") }}</label>
                <div class="amount-wrap">
                    <span class="currency-badge">{{ currencySymbol }}</span>
                    <input
                        id="charge-amount"
                        v-model="form.amount"
                        type="number"
                        min="1"
                        step="0.01"
                        placeholder="0.00"
                        :aria-label="t('user.charge.customAmountAria')"
                        @input="selectedPreset = null"
                    />
                </div>
                <p v-if="amountError" class="error">{{ amountError }}</p>
            </div>

            <div class="input-group">
                <label>{{ t("user.charge.descriptionLabel") }}</label>
                <input
                    id="charge-description"
                    v-model="form.description"
                    type="text"
                    maxlength="127"
                    :aria-label="t('user.charge.descriptionAria')"
                    :placeholder="t('user.charge.descriptionPlaceholder')"
                />
            </div>

            <div v-if="paymentMethod === 'moyasar'" class="card-brands" aria-label="Moyasar card brands">
                <span>mada</span>
                <span>Visa</span>
                <span>Mastercard</span>
                <span>American Express</span>
            </div>

            <hr />

            <div class="summary-row">
                <span>{{ t("user.charge.summaryAmount") }}</span>
                <span>{{ normalizedAmount ? currencySymbol + Number(normalizedAmount).toFixed(2) : "-" }}</span>
            </div>
            <div class="summary-row">
                <span>{{ t("user.charge.summaryCurrency") }}</span>
                <span>{{ currencyCode }}</span>
            </div>
            <div v-if="paymentMethod === 'moyasar'" class="summary-row">
                <span>{{ t("user.charge.expectedPoints") }}</span>
                <span>{{ formatPoints(expectedMoyasarPoints) }}</span>
            </div>
            <div class="summary-row">
                <span>{{ t("user.charge.summaryMethod") }}</span>
                <span :class="paymentMethod === 'paypal' ? 'paypal-text' : 'moyasar-text'">{{ methodSummary }}</span>
            </div>

            <button type="button" class="pay-btn" :disabled="!isValid || loading" @click="handlePay">
                <span v-if="loading">{{ t("user.charge.loading") }}</span>
                <span v-else>{{ payButtonLabel }}</span>
            </button>

            <div v-if="validationMessages.length" class="validation-list" role="alert">
                <p v-for="message in validationMessages" :key="message" class="error">{{ message }}</p>
            </div>

            <p v-if="serverError" class="error" style="margin-top:12px;text-align:center">
                {{ serverError }}
            </p>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue"
import { useRouter } from "vue-router"
import { useI18n } from "vue-i18n"
import { v4 as uuidv4 } from "uuid"
import useSeoMeta from "@/composables/useSeoMeta"
import homeService from "@/services/home/homeService"
import api from "@/services/ApiClient"
import walletService from "@/services/profile/walletService"

const props = defineProps({
    wallet: { type: Object, default: null },
})

const router = useRouter()
const { t, locale } = useI18n()

const PAYPAL_IDEMPOTENCY_STORAGE_KEY = "wallet_deposit_paypal_idempotency_key"
const MOYASAR_IDEMPOTENCY_STORAGE_KEY = "wallet_deposit_moyasar_idempotency_key"
const MOYASAR_DEPOSIT_ID_STORAGE_KEY = "wallet_moyasar_deposit_id"
const POINTS_PER_SAR = 1000000

const paymentMethod = ref("paypal")
const selectedPreset = ref(null)
const loading = ref(false)
const serverError = ref("")
const validationMessages = ref([])
const walletSnapshot = ref(props.wallet)

const form = ref({
    amount: "",
    description: "",
})

const paymentMethods = computed(() => [
    {
        value: "paypal",
        label: "PayPal",
        caption: t("user.charge.paypalCaption"),
    },
    {
        value: "moyasar",
        label: t("user.charge.moyasarMethod"),
        caption: t("user.charge.moyasarCaption"),
    },
])

const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar")
const seoTitle = computed(() => (isArabic.value ? "شحن المحفظة | Ai Pro" : "Charge Wallet | Ai Pro"))
const seoDescription = computed(() =>
    isArabic.value
        ? "اشحن محفظتك بأمان عبر PayPal أو بوابة ميسر، وراجع المبلغ وطريقة الدفع قبل إتمام عملية إضافة الرصيد إلى حسابك."
        : "Add funds to your wallet securely with PayPal or Moyasar, and review payment details before completing your balance charge."
)

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
})

const presets = [10, 25, 50, 100]
const currencyCode = computed(() => (paymentMethod.value === "moyasar" ? "SAR" : "USD"))
const currencySymbol = computed(() => (paymentMethod.value === "moyasar" ? "SAR " : "$"))
const methodSummary = computed(() => (paymentMethod.value === "moyasar" ? t("user.charge.moyasarMethod") : "PayPal"))
const payButtonLabel = computed(() => (paymentMethod.value === "moyasar" ? t("user.charge.moyasarPayButton") : t("user.charge.payButton")))
const normalizedAmount = computed(() => {
    const value = Number(form.value.amount)
    return Number.isFinite(value) && value > 0 ? value : 0
})
const expectedMoyasarPoints = computed(() => {
    if (paymentMethod.value !== "moyasar" || !normalizedAmount.value) return 0
    return Math.round(normalizedAmount.value * POINTS_PER_SAR)
})

const amountError = computed(() => {
    if (!form.value.amount) return ""
    const value = Number(form.value.amount)
    if (!Number.isFinite(value) || value < 1 || value > 999999.99) return t("user.charge.amountError")
    if (!/^\d+(?:\.\d{1,2})?$/.test(String(form.value.amount))) return t("user.charge.amountError")
    return ""
})

const isValid = computed(() => {
    return normalizedAmount.value >= 1 && !amountError.value
})

watch(
    () => props.wallet,
    (nextWallet) => {
        walletSnapshot.value = nextWallet
    }
)

watch(paymentMethod, () => {
    selectedPreset.value = null
    form.value.amount = ""
    serverError.value = ""
    validationMessages.value = []
})

function idempotencyKeyFor(method) {
    const storageKey = method === "moyasar" ? MOYASAR_IDEMPOTENCY_STORAGE_KEY : PAYPAL_IDEMPOTENCY_STORAGE_KEY
    const existing = sessionStorage.getItem(storageKey)
    if (existing) return existing

    const next = uuidv4()
    sessionStorage.setItem(storageKey, next)
    return next
}

function resetIdempotencyKey(method) {
    sessionStorage.removeItem(method === "moyasar" ? MOYASAR_IDEMPOTENCY_STORAGE_KEY : PAYPAL_IDEMPOTENCY_STORAGE_KEY)
}

function selectPaymentMethod(method) {
    if (loading.value) return
    paymentMethod.value = method
}

function selectPreset(val) {
    selectedPreset.value = val
    form.value.amount = String(val)
}

function extractPayload(data) {
    return data?.data && typeof data.data === "object" ? data.data : data
}

function extractValidationMessages(error) {
    const errors = error.response?.data?.errors
    if (!errors || typeof errors !== "object") return []

    return Object.values(errors)
        .flat()
        .filter((message) => typeof message === "string")
}

function networkMessage(error) {
    if (!error.response) return t("user.charge.networkError")
    if (["ECONNABORTED", "ETIMEDOUT"].includes(error.code)) return t("user.charge.timeoutError")
    return error.response?.data?.message || t("user.charge.genericError")
}

async function handlePay() {
    if (!isValid.value || loading.value) return

    loading.value = true
    serverError.value = ""
    validationMessages.value = []

    try {
        if (paymentMethod.value === "moyasar") {
            await createMoyasarPayment()
        } else {
            await createPaypalPayment()
        }
    } catch (err) {
        validationMessages.value = extractValidationMessages(err)
        serverError.value = validationMessages.value.length ? "" : networkMessage(err)
    } finally {
        loading.value = false
    }
}

async function createPaypalPayment() {
    const { data } = await api.post("/deposit/pay", {
        amount: String(form.value.amount),
        description: form.value.description || t("user.charge.defaultDescription"),
        idempotency_key: idempotencyKeyFor("paypal"),
        locale: homeService.getLang(),
    })

    if (data?.approval_url) {
        resetIdempotencyKey("paypal")
        window.location.assign(data.approval_url)
        return
    }

    if (!data?.order_id) {
        throw new Error("Missing local payment ID")
    }

    resetIdempotencyKey("paypal")
    if (data.status === "completed") {
        await router.replace(`/${homeService.getLang()}/deposit/success?order_id=${data.order_id}&provider=paypal`)
        return
    }

    await router.replace(`/${homeService.getLang()}/Deposit/waiting?provider=paypal&order_id=${data.order_id}`)
}

async function createMoyasarPayment() {
    const { data } = await api.post("/moyasar/pay", {
        amount: String(form.value.amount),
        description: form.value.description || t("user.charge.defaultDescription"),
        idempotency_key: idempotencyKeyFor("moyasar"),
        locale: homeService.getLang(),
    })
    const payload = extractPayload(data)
    const depositId = String(payload?.deposit_id || payload?.order_id || "")
    const redirectUrl = payload?.redirect_url || payload?.payment_url || payload?.transaction_url || payload?.invoice_url

    if (/^\d+$/.test(depositId)) {
        sessionStorage.setItem(MOYASAR_DEPOSIT_ID_STORAGE_KEY, depositId)
    }

    if (redirectUrl) {
        window.location.assign(redirectUrl)
        return
    }

    if (["paid", "completed"].includes(payload?.status)) {
        if (!/^\d+$/.test(depositId)) {
            throw new Error("Missing local Moyasar deposit ID")
        }

        resetIdempotencyKey("moyasar")
        sessionStorage.removeItem(MOYASAR_DEPOSIT_ID_STORAGE_KEY)
        await refreshWallet()
        await router.replace(`/${homeService.getLang()}/deposit/success?deposit_id=${depositId}&provider=moyasar`)
        return
    }

    if (!/^\d+$/.test(depositId)) {
        throw new Error("Missing local Moyasar deposit ID")
    }

    await router.replace(`/${homeService.getLang()}/Deposit/waiting?provider=moyasar&deposit_id=${depositId}`)
}

async function refreshWallet() {
    try {
        const response = await walletService.getWallet()
        walletSnapshot.value = response?.data || walletSnapshot.value
    } catch {
        homeService.clearAllCaches()
    }
}

function formatPoints(value) {
    return new Intl.NumberFormat("en-US").format(Number(value || 0))
}

onMounted(() => {
    locale.value = homeService.getLang()
    refreshWallet()
})
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

.method-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 1.25rem;
}

.method-option {
    min-height: 68px;
    padding: 10px 12px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 8px;
    text-align: start;
    cursor: pointer;
    transition: all .15s;
}

.method-option span,
.method-option small {
    display: block;
}

.method-option span {
    color: #1a1a1a;
    font-size: 14px;
    font-weight: 600;
}

.method-option small {
    margin-top: 4px;
    color: #888;
    font-size: 11px;
    line-height: 1.4;
}

.method-option.active {
    border: 2px solid #2ba6de;
    background: #E6F1FB;
}

.presets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 1.25rem;
}

.preset-btn {
    min-height: 42px;
    padding: 10px 6px;
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

[dir="rtl"] .currency-badge {
    left: auto;
    right: 12px;
}

.amount-wrap input {
    width: 100%;
    padding: 10px 12px 10px 58px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 500;
    outline: none;
}

[dir="rtl"] .amount-wrap input {
    padding: 10px 58px 10px 12px;
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

.card-brands {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 4px 0 14px;
}

.card-brands span {
    border: 1px solid #e5e5e5;
    border-radius: 999px;
    padding: 5px 9px;
    color: #154677;
    background: #f7fbfe;
    font-size: 12px;
    font-weight: 600;
}

hr {
    border: none;
    border-top: 1px solid #f0f0f0;
    margin: 1.25rem 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
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

.moyasar-text {
    color: #2ba6de;
    font-weight: 600;
}

.pay-btn {
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: opacity .15s, background-color .15s;
}

.pay-btn {
    width: 100%;
    padding: 12px;
    margin-top: 1.25rem;
    background: #2ba6de;
    color: #fff;
}

.pay-btn:hover {
    opacity: .9;
}

.pay-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.validation-list {
    margin-top: 12px;
}

.error {
    font-size: 13px;
    color: #e24b4a;
    margin-top: 6px;
}

@media (max-width: 520px) {
    .method-options,
    .presets {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* =========================
   DARK MODE
========================= */
:global(html[data-theme="dark"]) .deposit-page {
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

:global(html[data-theme="dark"]) .balance-card {
    background: #1b1f23;
    border: 1px solid rgba(255, 255, 255, 0.09);
    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.24);
}

:global(html[data-theme="dark"]) .balance-card .label,
:global(html[data-theme="dark"]) .balance-card .currency,
:global(html[data-theme="dark"]) .input-group label,
:global(html[data-theme="dark"]) .summary-row span:first-child,
:global(html[data-theme="dark"]) .currency-badge,
:global(html[data-theme="dark"]) .method-option small {
    color: #9299a1;
}

:global(html[data-theme="dark"]) .balance-card .amount,
:global(html[data-theme="dark"]) .deposit-card h1,
:global(html[data-theme="dark"]) .summary-row {
    color: #f1f3f5;
}

:global(html[data-theme="dark"]) .deposit-card {
    background: #1b1f23;
    border-color: rgba(255, 255, 255, 0.09);
    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.24);
}

:global(html[data-theme="dark"]) .method-option,
:global(html[data-theme="dark"]) .preset-btn {
    background: #20252a;
    border-color: rgba(255, 255, 255, 0.10);
    color: #c7ccd1;
}

:global(html[data-theme="dark"]) .method-option:hover,
:global(html[data-theme="dark"]) .preset-btn:hover {
    background: #2a3036;
}

:global(html[data-theme="dark"]) .method-option span {
    color: #f1f3f5;
}

:global(html[data-theme="dark"]) .method-option.active,
:global(html[data-theme="dark"]) .preset-btn.active {
    background: rgba(43, 166, 222, 0.13);
    border-color: rgba(43, 166, 222, 0.65);
    color: #2ba6de;
}

:global(html[data-theme="dark"]) .amount-wrap input,
:global(html[data-theme="dark"]) .input-group input[type="text"] {
    background: #171a1d;
    color: #f1f3f5;
    border-color: rgba(255, 255, 255, 0.11);
}

:global(html[data-theme="dark"]) .amount-wrap input::placeholder,
:global(html[data-theme="dark"]) .input-group input[type="text"]::placeholder {
    color: #747b83;
}

:global(html[data-theme="dark"]) .amount-wrap input:focus,
:global(html[data-theme="dark"]) .input-group input[type="text"]:focus {
    border-color: rgba(43, 166, 222, 0.65);
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.12);
}

:global(html[data-theme="dark"]) .card-brands span {
    background: #20252a;
    border-color: rgba(255, 255, 255, 0.10);
    color: #c7ccd1;
}

:global(html[data-theme="dark"]) hr {
    border-top-color: rgba(255, 255, 255, 0.09);
}

:global(html[data-theme="dark"]) .paypal-text {
    color: #c7ccd1;
}

:global(html[data-theme="dark"]) .moyasar-text {
    color: #2ba6de;
}

:global(html[data-theme="dark"]) .error {
    color: #f87171;
}
</style>
