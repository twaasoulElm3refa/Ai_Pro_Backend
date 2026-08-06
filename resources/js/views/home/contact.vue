<template>
    <main class="contact-section py-5" :style="sectionStyle" :dir="locale === 'ar' ? 'rtl' : 'ltr'" aria-labelledby="contact-page-title">
        <div class="container">

            <!-- Title -->
            <div class="text-center mb-5">
                <span class="contact-badge">{{ t("user.contact.badge") }}</span>
                <h1 id="contact-page-title" class="fw-bold mb-3 mt-3 contact-title">
                    {{ t("user.contact.title") }}
                </h1>
                <p class="contact-subtitle">
                    {{ t("user.contact.subtitle") }}
                </p>
            </div>

            <!-- Card -->
            <div class="card shadow-lg border-0 overflow-hidden contact-card" :style="cardStyle">
                <div class="row g-0">

                    <!-- Contact Info -->
                    <div class="col-md-6 p-4 p-md-5 order-md-2 contact-info-panel" :style="infoStyle">
                        <h4 class="fw-bold mb-4">{{ t("user.contact.infoTitle") }}</h4>

                        <div class="mb-3 d-flex info-item">
                            <i class="bi bi-geo-alt-fill me-3 fs-5 info-icon"></i>
                            <div>
                                <strong>{{ t("user.contact.addressLabel") }}</strong>
                                <p class="mb-0 contact-muted">{{ t("user.contact.addressValue") }}</p>
                            </div>
                        </div>

                        <div class="mb-3 d-flex info-item">
                            <i class="bi bi-telephone-fill me-3 fs-5 info-icon"></i>
                            <div>
                                <strong>{{ t("user.contact.phoneLabel") }}</strong>
                                <p class="mb-0 contact-muted">{{ t("user.contact.phoneValue") }}</p>
                            </div>
                        </div>

                        <div class="mb-4 d-flex info-item">
                            <i class="bi bi-envelope-fill me-3 fs-5 info-icon"></i>
                            <div>
                                <strong>{{ t("user.contact.emailLabel") }}</strong>
                                <p class="mb-0 contact-muted">{{ t("user.contact.emailValue") }}</p>
                            </div>
                        </div>

                        <div>
                            <strong class="d-block mb-2">{{ t("user.contact.followUs") }}</strong>
                            <div class="d-flex gap-2">
                                <a v-for="social in socials" :key="social.name" :href="social.href"
                                    class="btn btn-sm rounded-circle social-btn" :style="socialBtnStyle"
                                    :aria-label="t('user.contact.socialAria', { name: social.name })">
                                    <i :class="social.iconClass"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Form -->
                    <div class="col-md-6 p-4 p-md-5 order-md-1">
                        <h4 class="fw-bold mb-4">{{ t("user.contact.formTitle") }}</h4>

                        <form @submit.prevent="submitForm">
                            <div class="mb-3">
                                <label for="contact-name" class="form-label">{{ t("user.contact.nameLabel") }}</label>
                                <input id="contact-name" v-model="form.name" type="text" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <div class="mb-3">
                                <label for="contact-email" class="form-label">{{ t("user.contact.emailFieldLabel") }}</label>
                                <input id="contact-email" v-model="form.email" type="email" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <div class="mb-3">
                                <label for="contact-subject" class="form-label">{{ t("user.contact.subjectLabel") }}</label>
                                <input id="contact-subject" v-model="form.subject" type="text" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <div class="mb-3">
                                <label for="contact-message" class="form-label">{{ t("user.contact.messageLabel") }}</label>
                                <textarea id="contact-message" v-model="form.message" rows="4" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <button type="submit" class="btn w-100 contact-submit" :style="btnStyle">
                                {{ t("user.contact.submit") }}
                            </button>
                        </form>

                        <div v-if="successMessage" class="alert mt-4" :style="alertStyle">
                            {{ successMessage }}
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import COLORS, { rgba } from "@/components/assets/colors.js";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";

const route = useRoute();
const { t, locale } = useI18n();
const isArabic = computed(() => String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar");
const seoTitle = computed(() => (isArabic.value ? "اتصل بنا | Ai Pro" : "Contact Us | Ai Pro"));
const seoDescription = computed(() =>
    isArabic.value
        ? "تواصل مع فريقنا للاستفسارات والدعم والشراكات عبر نموذج واضح وقنوات مباشرة، واحصل على استجابة سريعة تساعدك على الاستمرار بثقة."
        : "Contact our team for support, questions, or partnership requests using a clear form and direct channels, with fast responses to help you move forward."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

/* ---------------- state ---------------- */
const form = ref({
    name: "",
    email: "",
    subject: "",
    message: "",
});

const successMessage = ref("");

/* ---------------- dark / light ---------------- */
const isDark = ref(document.documentElement.dataset.theme === "dark");
let themeObserver = null;

const syncTheme = () => {
    isDark.value = document.documentElement.dataset.theme === "dark";
};

onMounted(() => {
    syncTheme();
    themeObserver = new MutationObserver(syncTheme);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-theme"],
    });
});

onUnmounted(() => {
    themeObserver?.disconnect();
});

/* ---------------- styles ---------------- */
const sectionStyle = computed(() => ({
    backgroundColor: isDark.value ? "var(--theme-bg)" : COLORS.BG,
    color: isDark.value ? "var(--theme-text-primary)" : COLORS.TEXT,
}));

const cardStyle = computed(() => ({
    backgroundColor: isDark.value ? "var(--theme-surface)" : COLORS.PRIMARY,
    color: isDark.value ? "var(--theme-text-primary)" : COLORS.TEXT,
    borderRadius: "0.5rem",
}));

const infoStyle = computed(() => ({
    backgroundColor: isDark.value ? COLORS.PRIMARY : COLORS.PRIMARY,
    color: COLORS.TEXT,
}));

const inputStyle = computed(() => ({
    backgroundColor: isDark.value ? "var(--theme-input-bg)" : COLORS.PRIMARY,
    color: isDark.value ? "var(--theme-text-primary)" : COLORS.SECONDARY,
    border: `1px solid ${isDark.value ? "var(--theme-border-strong)" : COLORS.CARD_BORDER}`,
}));

const btnStyle = computed(() => ({
    backgroundColor: COLORS.ACCENT,
    color: COLORS.BTN_TEXT,
    border: "none",
}));

const alertStyle = computed(() => ({
    backgroundColor: rgba(COLORS.SUCCESS, 0.15),
    color: COLORS.SUCCESS,
    border: `1px solid ${rgba(COLORS.SUCCESS, 0.3)}`,
}));

const socialBtnStyle = computed(() => ({
    width: "36px",
    height: "36px",
    display: "inline-flex",
    alignItems: "center",
    justifyContent: "center",
    color: isDark.value ? "var(--theme-text-primary)" : COLORS.TEXT,
    backgroundColor: isDark.value ? "var(--theme-surface-secondary)" : COLORS.PRIMARY,
    border: `1px solid ${isDark.value ? "var(--theme-border)" : rgba(COLORS.TEXT, 0.3)}`,
}));

const textMuted = computed(() =>
    rgba(isDark.value ? COLORS.TEXT_DARK : COLORS.TEXT, 0.7)
);

/* ---------------- logic ---------------- */
const submitForm = () => {
    successMessage.value = t("user.contact.successMessage");
    form.value = { name: "", email: "", subject: "", message: "" };
};

const socials = [
    { name: "facebook", href: "#", iconClass: "bi bi-facebook" },
    { name: "twitter", href: "#", iconClass: "bi bi-twitter" },
    { name: "instagram", href: "#", iconClass: "bi bi-instagram" },
    { name: "telegram", href: "#", iconClass: "bi bi-telegram" },
];
</script>

<style scoped>
.contact-section {
    min-height: 100vh;
}

.contact-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.45rem 1rem;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    box-shadow: 0 18px 35px rgba(21, 70, 119, 0.18);
}

.contact-title {
    color: #154677;
}

.contact-subtitle,
.contact-muted {
    color: #5f7288;
}

.contact-card {
    border-radius: 1.75rem;
    box-shadow: 0 28px 50px rgba(21, 70, 119, 0.1);
}

.contact-info-panel {
    background: linear-gradient(135deg, #154677, #2ba6de) !important;
    color: #ffffff !important;
}

.info-item {
    padding: 0.9rem 1rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.08);
    transition: transform 0.2s ease, background 0.2s ease;
}

.info-item:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.14);
}

.info-icon {
    color: #ffffff;
}

.social-btn {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.social-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(21, 70, 119, 0.14);
}

.contact-input {
    border-radius: 1rem;
    min-height: 3.25rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.contact-input:focus {
    transform: translateY(-1px);
}

.contact-submit {
    min-height: 3.25rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #154677, #2ba6de) !important;
    color: #ffffff !important;
    box-shadow: 0 18px 35px rgba(21, 70, 119, 0.18);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.contact-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 22px 40px rgba(21, 70, 119, 0.22);
}
/* =========================
   DARK MODE
========================= */
html[data-theme="dark"] .contact-section {
    background: var(--app-bg) !important;
    color: var(--app-text-primary) !important;
}

html[data-theme="dark"] .contact-title,
html[data-theme="dark"] .contact-card .form-label,
html[data-theme="dark"] .contact-card .fw-bold {
    color: var(--app-text-primary) !important;
}

html[data-theme="dark"] .contact-subtitle,
html[data-theme="dark"] .contact-muted {
    color: var(--app-text-secondary) !important;
}

html[data-theme="dark"] .contact-card {
    background: var(--app-surface) !important;
    border-color: var(--app-border) !important;
    color: var(--app-text-primary) !important;
    box-shadow: 0 24px 54px var(--app-shadow) !important;
}

html[data-theme="dark"] .contact-card > .row {
    background: var(--app-surface) !important;
}

html[data-theme="dark"] .contact-input {
    background: var(--app-input-bg) !important;
    color: var(--app-text-primary) !important;
    border-color: var(--app-border-strong) !important;
}

html[data-theme="dark"] .contact-input:focus {
    background: var(--app-input-bg) !important;
    color: var(--app-text-primary) !important;
    border-color: rgba(43, 166, 222, 0.72) !important;
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.13) !important;
}

html[data-theme="dark"] .contact-card .alert-success {
    background: rgba(74, 222, 128, 0.10) !important;
    border-color: rgba(74, 222, 128, 0.24) !important;
    color: var(--app-success) !important;
}

html[data-theme="dark"] .contact-card .alert-danger {
    background: rgba(248, 113, 113, 0.10) !important;
    border-color: rgba(248, 113, 113, 0.24) !important;
    color: var(--app-danger) !important;
}

html[data-theme="dark"] .social-btn {
    background: var(--app-surface-secondary) !important;
    color: var(--app-text-primary) !important;
    border-color: var(--app-border) !important;
}
</style>
