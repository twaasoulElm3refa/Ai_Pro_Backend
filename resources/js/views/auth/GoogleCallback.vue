<template>
    <div class="d-flex align-items-center justify-content-center min-vh-100">
        <div class="text-center">
            <h2>جارٍ تسجيل الدخول عبر Google...</h2>
            <p v-if="error" class="text-danger mt-3">{{ error }}</p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";

const router = useRouter();
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
        error.value = "No token received from Google login.";
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
