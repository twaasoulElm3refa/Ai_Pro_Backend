<template>
    <main class="cancel-page" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <section class="card">
            <div class="icon" aria-hidden="true">×</div>
            <h1>{{ title }}</h1>
            <p>{{ message }}</p>
            <button type="button" @click="backToWallet">{{ action }}</button>
        </section>
    </main>
</template>

<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import homeService from "@/services/home/homeService";

const router = useRouter();
const { locale } = useI18n();
const isArabic = computed(() => String(locale.value).toLowerCase() === "ar");
const title = computed(() => isArabic.value ? "تم إلغاء الدفع" : "Payment cancelled");
const message = computed(() => isArabic.value
    ? "لم يتم شحن محفظتك ولم نعتبر العملية دفعة ناجحة."
    : "Your wallet was not credited and this was not treated as a successful payment.");
const action = computed(() => isArabic.value ? "العودة إلى المحفظة" : "Back to wallet");

function backToWallet() {
    router.replace(`/${homeService.getLang()}/wallet`);
}
</script>

<style scoped>
.cancel-page { min-height: 100vh; display: grid; place-items: center; background: #f5f5f5; padding: 20px; }
.card { max-width: 460px; width: 100%; text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
.icon { width: 64px; height: 64px; margin: 0 auto 20px; display: grid; place-items: center; border-radius: 50%; background: #eef2f7; color: #154677; font-size: 38px; }
h1 { color: #154677; }
p { color: #667085; margin: 14px 0 24px; }
button { border: 0; border-radius: 10px; background: #154677; color: white; padding: 12px 20px; cursor: pointer; }
</style>
