<template>
    <div class="google-callback d-flex align-items-center justify-content-center min-vh-100 px-4"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="callback-card text-center">
            <div class="spinner-ring mx-auto mb-4"></div>
            <h2 class="callback-title">{{ t("auth.googleCallback.signingInTitle") }}</h2>
            <p v-if="!error" class="callback-subtitle mt-3">{{ t("auth.googleCallback.signingInSubtitle") }}</p>
            <p v-else class="callback-error mt-3">{{ error }}</p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import axios from "axios";

const router = useRouter();
const { t, locale } = useI18n();
const error = ref("");

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get("token");
    const role = params.get("role");
    const errorMsg = params.get("error");

    if (errorMsg) {
        error.value = decodeURIComponent(errorMsg);
        return;
    }

    if (!token) {
        error.value = t("auth.googleCallback.missingToken");
        return;
    }

    localStorage.setItem("auth_token", token);
    localStorage.setItem("user_role", (role || "user").toLowerCase());
    axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;

    const cleanUrl = new URL(window.location.href);
    cleanUrl.search = "";
    window.history.replaceState({}, document.title, cleanUrl.toString());

    if ((role || "user").toLowerCase() === "admin") {
        router.replace("/admin");
    } else {
        router.replace("/");
    }
});
</script>

<style scoped>
.google-callback {
    background: linear-gradient(135deg, #f8fbff, #eef7fc);
}

.callback-card {
    width: min(100%, 30rem);
    padding: 2.5rem 2rem;
    border-radius: 1.75rem;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(21, 70, 119, 0.1);
    box-shadow: 0 28px 50px rgba(21, 70, 119, 0.1);
}

.spinner-ring {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 999px;
    border: 4px solid rgba(43, 166, 222, 0.18);
    border-top-color: #154677;
    animation: spin 0.8s linear infinite;
}

.callback-title {
    color: #154677;
    font-weight: 700;
}

.callback-subtitle {
    color: #5f7288;
}

.callback-error {
    color: #154677;
    background: rgba(21, 70, 119, 0.08);
    border: 1px solid rgba(21, 70, 119, 0.12);
    border-radius: 1rem;
    padding: 0.875rem 1rem;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
