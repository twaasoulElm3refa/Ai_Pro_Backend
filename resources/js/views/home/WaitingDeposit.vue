<template>
    <main class="waiting-container" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="waiting-payment-title">
        <section class="card" role="status" aria-live="polite">
            <div v-if="viewState === 'waiting'" class="spinner" aria-hidden="true"></div>
            <h1 id="waiting-payment-title">{{ title }}</h1>
            <p>{{ message }}</p>
            <p v-if="statusLabel" class="status-label">{{ statusLabel }}</p>
            <p v-if="viewState === 'waiting'" class="hint">{{ t("user.deposit.waiting.hint") }}</p>
            <div v-else class="actions">
                <button v-if="viewState === 'timeout' || viewState === 'error'" type="button" @click="retryPolling">
                    {{ t("user.deposit.waiting.retry") }}
                </button>
                <button type="button" @click="goToWallet">
                    {{ t("user.deposit.waiting.backToWallet") }}
                </button>
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue"
import { useRouter, useRoute } from "vue-router"
import { useI18n } from "vue-i18n"
import useSeoMeta from "@/composables/useSeoMeta"
import homeService from "@/services/home/homeService"
import api from "@/services/ApiClient"
import walletService from "@/services/profile/walletService"

const router = useRouter()
const route = useRoute()
const { t, locale } = useI18n()

const MOYASAR_DEPOSIT_ID_STORAGE_KEY = "wallet_moyasar_deposit_id"
const MOYASAR_IDEMPOTENCY_STORAGE_KEY = "wallet_deposit_moyasar_idempotency_key"
const SUPPORTED_PROVIDERS = ["paypal", "moyasar"]
const SUCCESS_STATUSES = ["paid", "completed"]
const FAILURE_STATUSES = ["failed", "canceled", "expired", "refunded", "voided", "abandoned"]
const PENDING_STATUSES = {
    paypal: ["pending", "approved", "processing"],
    moyasar: ["pending", "initiated", "processing", "approved"],
}
const PROVIDER_CONFIG = {
    paypal: {
        idQuery: "order_id",
        endpoint: (id) => `/wallet/order-status/${encodeURIComponent(id)}`,
        successPath: (lang, id) => `/${lang}/deposit/success?order_id=${encodeURIComponent(id)}&provider=paypal`,
    },
    moyasar: {
        idQuery: "deposit_id",
        endpoint: (id) => `/wallet/moyasar-status/${encodeURIComponent(id)}`,
        successPath: (lang, id) => `/${lang}/deposit/success?deposit_id=${encodeURIComponent(id)}&provider=moyasar`,
    },
}

const pollIntervalMs = Math.max(1000, Number(import.meta.env.VITE_WALLET_POLL_INTERVAL_MS || 5000))
const pollTimeoutMs = Math.max(pollIntervalMs, Number(import.meta.env.VITE_WALLET_POLL_TIMEOUT_MS || 150000))
const MAX_ATTEMPTS = Math.max(1, Math.ceil(pollTimeoutMs / pollIntervalMs))
const viewState = ref("waiting")
const lastStatus = ref("")
const lastError = ref("")
const attempts = ref(0)
let timer = null
let controller = null
let isChecking = false
let stopped = false
let deadline = 0

const currentLang = () => homeService.getLang()
const isArabic = computed(() => String(locale.value || currentLang() || "ar").toLowerCase() === "ar")
const seoTitle = computed(() => (isArabic.value ? "Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„Ø¯ÙØ¹ | Ai Pro" : "Payment Verification | AI Pro"))
const seoDescription = computed(() =>
    isArabic.value
        ? "Ù†ØªØ­Ù‚Ù‚ Ù…Ù† Ø­Ø§Ù„Ø© Ø§Ù„Ø¯ÙØ¹ ÙˆÙ†Ø­ÙˆÙ„Ùƒ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§ Ø¹Ù†Ø¯ Ø§ÙƒØªÙ…Ø§Ù„Ù‡."
        : "We are confirming your payment and will redirect you when it completes."
)

useSeoMeta({ title: seoTitle, description: seoDescription })

const provider = computed(() => {
    const requested = String(route.query.provider || "").toLowerCase()
    if (SUPPORTED_PROVIDERS.includes(requested)) return requested
    if (requested) return "unsupported"
    if (route.query.deposit_id) return "moyasar"

    return "paypal"
})

const config = computed(() => PROVIDER_CONFIG[provider.value] || null)
const paymentId = computed(() => {
    if (provider.value === "moyasar") {
        const queryDepositId = String(route.query.deposit_id || "")
        if (/^\d+$/.test(queryDepositId)) return queryDepositId

        const storedDepositId = String(sessionStorage.getItem(MOYASAR_DEPOSIT_ID_STORAGE_KEY) || "")
        return /^\d+$/.test(storedDepositId) ? storedDepositId : ""
    }

    if (provider.value === "paypal") {
        return String(route.query.order_id || "")
    }

    return ""
})

const providerLabel = computed(() => {
    if (provider.value === "moyasar") return t("user.deposit.waiting.providers.moyasar")
    if (provider.value === "paypal") return t("user.deposit.waiting.providers.paypal")
    return t("user.deposit.waiting.providers.unknown")
})

const title = computed(() => {
    if (viewState.value === "timeout") return t("user.deposit.waiting.timeoutTitle")
    if (viewState.value === "error") return t("user.deposit.waiting.errorTitle")
    return t("user.deposit.waiting.title")
})

const message = computed(() => {
    if (viewState.value === "timeout") return t("user.deposit.waiting.timeoutMessage")
    if (viewState.value === "error") return lastError.value

    return t("user.deposit.waiting.subtitle", { provider: providerLabel.value })
})

const statusLabel = computed(() => {
    if (!lastStatus.value || viewState.value !== "waiting") return ""

    const labels = {
        pending: t("user.deposit.waiting.status.pending"),
        initiated: t("user.deposit.waiting.status.initiated"),
        processing: t("user.deposit.waiting.status.processing"),
        approved: t("user.deposit.waiting.status.approved"),
    }

    return labels[lastStatus.value] || labels.pending
})

function extractPayload(data) {
    return data?.data && typeof data.data === "object" ? data.data : data
}

function normalizeStatus(status) {
    const normalized = String(status || "").toLowerCase()
    return normalized === "cancelled" ? "canceled" : normalized
}

function responseId(payload) {
    return String(payload?.[config.value.idQuery] || payload?.order_id || payload?.deposit_id || paymentId.value)
}

function stopPolling() {
    stopped = true
    if (timer) clearTimeout(timer)
    timer = null
    controller?.abort()
    controller = null
}

function schedule(delay = pollIntervalMs) {
    if (stopped || timer) return

    timer = setTimeout(() => {
        timer = null
        checkStatus()
    }, delay)
}

async function finish(path) {
    stopPolling()
    await router.replace(path)
}

function showError(messageText) {
    stopPolling()
    viewState.value = "error"
    lastError.value = messageText
}

function failurePath() {
    return `/${currentLang()}/failed?error=PAYMENT_FAILED&provider=${encodeURIComponent(provider.value)}`
}

async function refreshWallet() {
    try {
        await walletService.getWallet()
        homeService.clearAllCaches()
    } catch {
        homeService.clearAllCaches()
    }
}

async function handleSuccess(payload) {
    const id = responseId(payload)
    stopPolling()
    if (provider.value === "moyasar") {
        sessionStorage.removeItem(MOYASAR_DEPOSIT_ID_STORAGE_KEY)
        sessionStorage.removeItem(MOYASAR_IDEMPOTENCY_STORAGE_KEY)
    }
    await refreshWallet()
    await router.replace(config.value.successPath(currentLang(), id))
}

async function checkStatus() {
    if (stopped || isChecking) return
    if (attempts.value >= MAX_ATTEMPTS || Date.now() >= deadline) {
        stopPolling()
        viewState.value = "timeout"
        return
    }

    isChecking = true
    attempts.value += 1
    controller = new AbortController()
    try {
        const { data } = await api.get(config.value.endpoint(paymentId.value), {
            signal: controller.signal,
        })
        const payload = extractPayload(data)
        const status = normalizeStatus(payload?.status)
        lastStatus.value = status

        if (SUCCESS_STATUSES.includes(status)) {
            await handleSuccess(payload)
            return
        }

        if (FAILURE_STATUSES.includes(status)) {
            if (provider.value === "moyasar") {
                sessionStorage.removeItem(MOYASAR_DEPOSIT_ID_STORAGE_KEY)
                sessionStorage.removeItem(MOYASAR_IDEMPOTENCY_STORAGE_KEY)
            }
            await finish(failurePath())
            return
        }

        if (!PENDING_STATUSES[provider.value]?.includes(status)) {
            showError(t("user.deposit.waiting.unknownStatus"))
            return
        }
    } catch (error) {
        if (error?.name === "CanceledError" || error?.code === "ERR_CANCELED") return
        const status = error.response?.status

        if (status === 401) {
            await finish(`/${currentLang()}/auth`)
            return
        }

        if (status === 403) {
            showError(t("user.deposit.waiting.forbidden"))
            return
        }

        if (status === 404 && attempts.value >= 3) {
            showError(t("user.deposit.waiting.notFound"))
            return
        }

        if (status === 429) {
            const retryAfter = Number(error.response?.headers?.["retry-after"] || 0) * 1000
            schedule(Math.max(pollIntervalMs, retryAfter))
            return
        }

        if (status && status < 500) {
            showError(t("user.deposit.waiting.errorMessage"))
            return
        }
    } finally {
        isChecking = false
        if (!stopped && !timer) schedule()
    }
}

function retryPolling() {
    stopPolling()
    stopped = false
    viewState.value = "waiting"
    lastError.value = ""
    attempts.value = 0
    deadline = Date.now() + pollTimeoutMs

    if (!config.value) {
        showError(t("user.deposit.waiting.unsupportedProvider"))
        return
    }

    if (!/^\d+$/.test(paymentId.value)) {
        showError(t("user.deposit.waiting.invalidPaymentId"))
        return
    }

    checkStatus()
}

function goToWallet() {
    finish(`/${currentLang()}/wallet`)
}

onMounted(() => {
    locale.value = currentLang()

    if (!config.value) {
        showError(t("user.deposit.waiting.unsupportedProvider"))
        return
    }

    if (!/^\d+$/.test(paymentId.value)) {
        showError(t("user.deposit.waiting.invalidPaymentId"))
        return
    }

    deadline = Date.now() + pollTimeoutMs
    checkStatus()
})

onUnmounted(stopPolling)
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
    border-radius: 12px;
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

.hint,
.status-label {
    margin-top: 8px;
    font-size: 0.82rem;
    color: #888;
}

.actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
    flex-wrap: wrap;
}

.actions button {
    border: 0;
    border-radius: 8px;
    padding: 10px 14px;
    cursor: pointer;
}

.actions button:first-child {
    background: #2ba6de;
    color: white;
}
</style>
