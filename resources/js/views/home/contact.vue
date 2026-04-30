<template>
    <section class="contact-section py-5" :style="sectionStyle">
        <div class="container">

            <!-- Title -->
            <div class="text-center mb-5">
                <span class="contact-badge">Get In Touch</span>
                <h2 class="fw-bold mb-3 mt-3 contact-title">
                    Contact Us
                </h2>
                <p class="contact-subtitle">
                    We are here to answer all your questions and help you with anything you need
                </p>
            </div>

            <!-- Card -->
            <div class="card shadow-lg border-0 overflow-hidden contact-card" :style="cardStyle">
                <div class="row g-0">

                    <!-- Contact Info -->
                    <div class="col-md-6 p-4 p-md-5 order-md-2 contact-info-panel" :style="infoStyle">
                        <h4 class="fw-bold mb-4">Contact Information</h4>

                        <div class="mb-3 d-flex info-item">
                            <i class="bi bi-geo-alt-fill me-3 fs-5 info-icon"></i>
                            <div>
                                <strong>Address</strong>
                                <p class="mb-0 contact-muted">Nile Street, Cairo, Egypt</p>
                            </div>
                        </div>

                        <div class="mb-3 d-flex info-item">
                            <i class="bi bi-telephone-fill me-3 fs-5 info-icon"></i>
                            <div>
                                <strong>Phone</strong>
                                <p class="mb-0 contact-muted">+20 123 456 789</p>
                            </div>
                        </div>

                        <div class="mb-4 d-flex info-item">
                            <i class="bi bi-envelope-fill me-3 fs-5 info-icon"></i>
                            <div>
                                <strong>Email</strong>
                                <p class="mb-0 contact-muted">info@example.com</p>
                            </div>
                        </div>

                        <div>
                            <strong class="d-block mb-2">Follow us</strong>
                            <div class="d-flex gap-2">
                                <a v-for="social in socials" :key="social.name" :href="social.href"
                                    class="btn btn-sm rounded-circle social-btn" :style="socialBtnStyle">
                                    <i :class="social.iconClass"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Form -->
                    <div class="col-md-6 p-4 p-md-5 order-md-1">
                        <h4 class="fw-bold mb-4">
                            Send us a message
                        </h4>

                        <form @submit.prevent="submitForm">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input v-model="form.name" type="text" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input v-model="form.email" type="email" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input v-model="form.subject" type="text" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea v-model="form.message" rows="4" class="form-control contact-input" :style="inputStyle" required />
                            </div>

                            <button type="submit" class="btn w-100 contact-submit" :style="btnStyle">
                                Send Message
                            </button>
                        </form>

                        <div v-if="successMessage" class="alert mt-4" :style="alertStyle">
                            {{ successMessage }}
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </section>
</template>

<script setup>
import { ref, computed } from "vue";
import COLORS, { rgba } from "@/components/assets/colors.js";

/* ---------------- state ---------------- */
const form = ref({
    name: "",
    email: "",
    subject: "",
    message: "",
});

const successMessage = ref("");

/* ---------------- dark / light ---------------- */
const isDark = computed(() =>
    document.documentElement.dataset.theme === "dark"
);

/* ---------------- styles ---------------- */
const sectionStyle = computed(() => ({
    backgroundColor: isDark.value ? COLORS.BG_DARK : COLORS.BG,
    color: isDark.value ? COLORS.TEXT_DARK : COLORS.TEXT,
}));

const cardStyle = computed(() => ({
    backgroundColor: isDark.value ? COLORS.CARD_DARK : COLORS.PRIMARY,
    color: isDark.value ? COLORS.TEXT_DARK : COLORS.TEXT,
    borderRadius: "0.5rem",
}));

const infoStyle = computed(() => ({
    backgroundColor: isDark.value ? COLORS.PRIMARY : COLORS.PRIMARY,
    color: COLORS.TEXT,
}));

const inputStyle = computed(() => ({
    backgroundColor: isDark.value ? COLORS.INPUT_DARK : COLORS.PRIMARY,
    color: isDark.value ? COLORS.TEXT_DARK : COLORS.SECONDARY,
    border: `1px solid ${COLORS.CARD_BORDER}`,
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
    color: COLORS.TEXT,
    backgroundColor: isDark.value ? COLORS.CARD_DARK : COLORS.PRIMARY,
    border: `1px solid ${rgba(COLORS.TEXT, 0.3)}`,
}));

const textMuted = computed(() =>
    rgba(isDark.value ? COLORS.TEXT_DARK : COLORS.TEXT, 0.7)
);

/* ---------------- logic ---------------- */
const submitForm = () => {
    successMessage.value = "Your message has been sent successfully!";
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
</style>
