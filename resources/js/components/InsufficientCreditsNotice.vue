<template>
    <Teleport to="body">
        <aside
            class="credit-notice shadow-sm"
            role="alert"
            :dir="isArabic ? 'rtl' : 'ltr'"
            aria-live="assertive"
        >
            <button
                type="button"
                class="credit-notice__close"
                :aria-label="t('errors.insufficientCredits.close')"
                @click="emit('close')"
            >
                ×
            </button>

            <div class="credit-notice__heading">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                <h2>{{ t('errors.insufficientCredits.title') }}</h2>
            </div>

            <p>{{ t('errors.insufficientCredits.description') }}</p>

            <dl class="credit-notice__amounts">
                <div v-if="hasAmount(error.available_credits)">
                    <dt>{{ t('errors.insufficientCredits.available') }}</dt>
                    <dd>{{ format(error.available_credits) }}</dd>
                </div>

                <div v-if="hasAmount(error.required_credits)">
                    <dt>{{ t('errors.insufficientCredits.required') }}</dt>
                    <dd>{{ format(error.required_credits) }}</dd>
                </div>

                <div v-if="hasAmount(error.shortfall_credits)">
                    <dt>{{ t('errors.insufficientCredits.shortfall') }}</dt>
                    <dd>{{ format(error.shortfall_credits) }}</dd>
                </div>
            </dl>

            <button
                type="button"
                class="btn btn-primary credit-notice__action"
                @click="recharge"
            >
                {{ t('errors.insufficientCredits.recharge') }}
            </button>
        </aside>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';

defineProps({
    error: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);
const { t, locale } = useI18n();
const router = useRouter();

const isArabic = computed(() => locale.value === 'ar');
const hasAmount = (value) => typeof value === 'number' && Number.isFinite(value);
const format = (value) => new Intl.NumberFormat(locale.value).format(value);

const recharge = async () => {
    await router.push(`/${locale.value}/wallet`);
    emit('close');
};
</script>

<style scoped>
.credit-notice {
    position: fixed !important;
    z-index: 2147483647 !important;
    inset-block-start: 1rem;
    inset-inline-end: 1rem;
    width: min(25rem, calc(100vw - 2rem));
    padding: 1.2rem;
    border: 1px solid #bfdbfe;
    border-radius: 1rem;
    background: #fff;
    color: #1e293b;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
}

.credit-notice__close {
    position: absolute;
    inset-block-start: 0.5rem;
    inset-inline-end: 0.7rem;
    border: 0;
    background: none;
    color: #64748b;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
}

.credit-notice__heading {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #1d4ed8;
    padding-inline-end: 1.5rem;
}

.credit-notice__heading h2 {
    font-size: 1.1rem;
    margin: 0;
    font-weight: 700;
}

.credit-notice p {
    font-size: 0.9rem;
    margin: 0.7rem 0 1rem;
    line-height: 1.6;
}

.credit-notice__amounts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
    margin: 0 0 1rem;
}

.credit-notice__amounts div {
    padding: 0.6rem;
    background: #eff6ff;
    border-radius: 0.6rem;
    min-width: 0;
}

.credit-notice__amounts dt {
    font-size: 0.7rem;
    color: #475569;
    font-weight: 500;
}

.credit-notice__amounts dd {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    overflow-wrap: anywhere;
}

.credit-notice__action {
    width: 100%;
    border-radius: 0.6rem;
}

@media (max-width: 380px) {
    .credit-notice__amounts {
        grid-template-columns: 1fr;
    }
}
</style>
