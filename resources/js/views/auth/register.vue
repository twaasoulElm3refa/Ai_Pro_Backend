<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center position-relative overflow-hidden">
        <div class="position-absolute w-100 h-100 waves"></div>

        <div class="auth-container position-relative z-2 d-flex flex-column flex-lg-row w-100 h-100">
            <div class="d-none d-lg-flex col-lg-5 align-items-center justify-content-center p-5 position-relative">
                <div class="text-center">
                    <img src="/images/ai_logo.png" alt="NEXTLEVEL Logo" class="height-auto logo-glow" />
                    <h1 class="display-3 fw-black mb-3">AI PRO</h1>
                    <p class="lead fs-4 fw-medium opacity-90">Build. Learn. Evolve with AI</p>
                </div>
            </div>

            <div class="col-12 col-lg-7 d-flex align-items-center justify-content-center p-4 p-md-5">
                <div class="glass-card rounded-4 shadow-glow p-4 p-md-5 w-100"
                    style="max-width: 480px; backdrop-filter: blur(16px)">

                    <!-- ── Tabs ── -->
                    <div class="position-relative mb-4" style="border-bottom: 1px solid rgba(255,255,255,0.15)">
                        <ul class="nav nav-pills nav-fill">
                            <li class="nav-item">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'login' }"
                                    @click.prevent="tab = 'login'">Login</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'register' }"
                                    @click.prevent="tab = 'register'">Register</button>
                            </li>
                        </ul>
                        <div class="position-absolute rounded-pill underline"
                            :style="{ left: tab === 'login' ? '0%' : '50%', width: '50%' }"></div>
                    </div>

                    <!-- ── Success Message ── -->
                    <transition name="fade">
                        <p v-if="successMessage" class="text-center text-success mb-4 fw-medium">
                            {{ successMessage }}
                        </p>
                    </transition>

                    <!-- ── Login Form ── -->
                    <form v-if="tab === 'login'" @submit.prevent="handleLogin" class="d-flex flex-column gap-4">

                        <input v-model="loginForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <div class="input-group input-group-lg">
                            <input v-model="loginForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 py-3 px-4 fs-5 border-end-0"
                                placeholder="Password" required />
                            <span class="input-group-text glass-input rounded-3 border-start-0 pointer"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? "👁️" : "👁️‍🗨️" }}</span>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5 mt-2"
                            :disabled="loading">
                            {{ loading ? "Loading..." : "Login" }}
                        </button>

                        <p class="text-end mb-0">
                            <a href="#" class="text-glow fw-medium fs-6" @click.prevent="tab = 'forgot'">
                                هل نسيت كلمة المرور؟
                            </a>
                        </p>

                        <button @click.prevent="handleGoogleLogin"
                            class="btn btn-google btn-lg d-flex align-items-center justify-content-center gap-2 mt-3">
                            <img src="/images/google_logo.png" alt="Google" style="width:35px;height:36px" />
                            Continue with Google
                        </button>
                    </form>

                    <!-- ── Register Form (Step 1: OTP) ── -->
                    <form v-else-if="tab === 'register' && registerStep === 1"
                        @submit.prevent="handleSendOtp" class="d-flex flex-column gap-4">

                        <input v-model="registerForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Email" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5"
                            :disabled="loading">
                            {{ loading ? "جاري الإرسال..." : "إرسال كود التفعيل" }}
                        </button>
                    </form>

                    <!-- ── Register Form (Step 2: Verify OTP) ── -->
                    <form v-else-if="tab === 'register' && registerStep === 2"
                        @submit.prevent="handleVerifyOtp" class="d-flex flex-column gap-4">

                        <p class="text-center text-white-50 mb-0">
                            تم إرسال كود على <strong class="text-white">{{ registerForm.email }}</strong>
                        </p>

                        <input v-model="registerForm.otp" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5 text-center ls-widest"
                            placeholder="أدخل كود التفعيل" maxlength="6" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5"
                            :disabled="loading">
                            {{ loading ? "جاري التحقق..." : "تحقق من الكود" }}
                        </button>

                        <p class="text-center mb-0">
                            <a href="#" class="text-glow fw-medium fs-6" @click.prevent="handleSendOtp">
                                إعادة إرسال الكود
                            </a>
                        </p>
                    </form>

                    <!-- ── Register Form (Step 3: Fill Data) ── -->
                    <form v-else-if="tab === 'register' && registerStep === 3"
                        @submit.prevent="handleRegister" class="d-flex flex-column gap-4">

                        <input v-model="registerForm.name" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Full Name" required />

                        <input v-model="registerForm.phone" type="text"
                            class="form-control form-control-lg glass-input rounded-3 px-4 fs-5"
                            placeholder="Phone (optional)" />

                        <input type="file" class="form-control glass-input rounded-3 px-4 fs-5"
                            @change="handleImageUpload" />

                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 px-4 fs-5 border-end-0"
                                placeholder="Password" required />
                            <span class="input-group-text glass-input border-start-0"
                                @click="showPassword = !showPassword">👁️</span>
                        </div>

                        <input v-model="registerForm.password_confirmation" :type="showPassword ? 'text' : 'password'"
                            class="form-control glass-input rounded-3 px-4 fs-5"
                            placeholder="Confirm Password" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5"
                            :disabled="loading">
                            {{ loading ? "Creating..." : "Create Account" }}
                        </button>
                    </form>

                    <!-- ── Forgot Password Form ── -->
                    <form v-else-if="tab === 'forgot'" @submit.prevent="handleForgot" class="d-flex flex-column gap-4">

                        <input v-model="forgotEmail" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5"
                            :disabled="loading">
                            {{ loading ? "جاري الإرسال..." : "إرسال الكود" }}
                        </button>

                        <p class="text-center mt-3">
                            <a href="#" class="text-glow" @click.prevent="tab = 'login'">
                                رجوع لتسجيل الدخول
                            </a>
                        </p>
                    </form>

                    <!-- ── Reset Password Form ── -->
                    <form v-else-if="tab === 'reset'" @submit.prevent="handleReset" class="d-flex flex-column gap-4">

                        <input v-model="resetForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email" required />

                        <input v-model="resetForm.otp" type="text"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="OTP Code" required />

                        <input v-model="resetForm.password" type="password"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="New Password" required />

                        <input v-model="resetForm.password_confirmation" type="password"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Confirm Password" required />

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5"
                            :disabled="loading">
                            {{ loading ? "جاري التغيير..." : "تغيير كلمة المرور" }}
                        </button>
                    </form>

                    <!-- ── Bottom Switch Link ── -->
                    <div class="text-center mt-4 text-white fs-6">
                        <span v-if="tab === 'register'">
                            Already have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="switchToLogin">Login</a>
                        </span>
                        <span v-else-if="tab === 'login'">
                            Don't have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="switchToRegister">Register</a>
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import authService from "@/services/auth/authService";

// ─────────────────────────────────────────────
//  State
// ─────────────────────────────────────────────

const tab            = ref("login");
const registerStep   = ref(1);   // 1 = email → 2 = OTP → 3 = full form
const showPassword   = ref(false);
const loading        = ref(false);
const successMessage = ref("");
const forgotEmail    = ref("");

const loginForm = ref({
    email: "",
    password: "",
});

const registerForm = ref({
    name: "",
    email: "",
    otp: "",
    phone: "",
    password: "",
    password_confirmation: "",
    image: null,
});

const resetForm = ref({
    email: "",
    otp: "",
    password: "",
    password_confirmation: "",
});

// ─────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────

const withLoading = async (fn) => {
    loading.value = true;
    try {
        await fn();
    } finally {
        loading.value = false;
    }
};

const switchToLogin = () => {
    tab.value = "login";
    registerStep.value = 1;
};

const switchToRegister = () => {
    tab.value = "register";
    registerStep.value = 1;
};

// ─────────────────────────────────────────────
//  Handlers
// ─────────────────────────────────────────────

const handleLogin = () =>
    withLoading(() => authService.login(loginForm.value));

// Register – Step 1
const handleSendOtp = () =>
    withLoading(async () => {
        await authService.sendOtp(registerForm.value.email);
        registerStep.value = 2;
        successMessage.value = "تم إرسال كود التفعيل على إيميلك";
        setTimeout(() => (successMessage.value = ""), 4000);
    });

// Register – Step 2
const handleVerifyOtp = () =>
    withLoading(async () => {
        await authService.verifyOtp(registerForm.value.email, registerForm.value.otp);
        registerStep.value = 3;
    });

// Register – Step 3
const handleRegister = () =>
    withLoading(() => authService.register(registerForm.value));

const handleImageUpload = (e) => {
    registerForm.value.image = e.target.files[0] ?? null;
};

const handleForgot = () =>
    withLoading(async () => {
        await authService.forgotPassword(forgotEmail.value);
        resetForm.value.email = forgotEmail.value;
        successMessage.value = "تم إرسال الكود على الإيميل";
        tab.value = "reset";
        setTimeout(() => (successMessage.value = ""), 4000);
    });

const handleReset = () =>
    withLoading(async () => {
        await authService.resetPassword(resetForm.value);
        successMessage.value = "تم تغيير كلمة المرور بنجاح";
        tab.value = "login";
        resetForm.value = { email: "", otp: "", password: "", password_confirmation: "" };
        setTimeout(() => (successMessage.value = ""), 4000);
    });

const handleGoogleLogin = async () => {
    const url = await authService.getGoogleAuthUrl();
    window.location.href = url;
};

// ─────────────────────────────────────────────
//  Lifecycle
// ─────────────────────────────────────────────

onMounted(() => authService.handleGoogleCallback());
</script>

<style scoped>
.btn-apple {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-apple:hover {
    transform: scale(1.04);
    color: white;
}

/* Underline color appears on hover */
.btn-apple::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #5c4033, #8b5e3c);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-apple:hover::before {
    opacity: 1;
}

/* ─── Common ─── */
.waves {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23D4AF37" fill-opacity="0.08" d="M0,96L48,112C96,128,192,160,288,176C384,192,480,192,576,186.7C672,181,768,171,864,154.7C960,139,1056,117,1152,122.7C1248,128,1344,160,1392,176L1440,192L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom no-repeat;
    background-size: cover;
    animation: wave 18s linear infinite alternate;
    opacity: 0.6;
}

.glass-card {
    background: rgba(30, 41, 59, 0.35);
    border: 1px solid rgba(212, 175, 55, 0.18);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45), inset 0 0 20px rgba(212, 175, 55, 0.08);
    transition: all 0.4s ease;
}

.glass-input {
    background: rgba(30, 41, 59, 0.45);
    border: 1px solid rgba(212, 175, 55, 0.25);
    color: white;
    transition: all 0.3s;
}

.glass-input:focus {
    background: rgba(51, 65, 85, 0.55);
    border-color: #d4af37;
    box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
    outline: none;
}

.glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.btn-glow {
    background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
    border: none;
    color: #0f172a;
    font-weight: 600;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    transition: all 0.35s;
}

.btn-glow:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(212, 175, 55, 0.55);
    filter: brightness(1.08);
}

/* ─── Tabs ─── */
.nav-link {
    position: relative;
    color: rgba(255, 255, 255, 0.75);
    transition: color 0.3s ease;
}

.nav-link:hover {
    color: white;
}

.nav-link::after {
    content: "";
    position: absolute;
    bottom: -4px;
    left: 50%;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #d4af37, #eab308);
    border-radius: 3px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-50%);
}

.nav-link:hover::after,
.active-tab::after {
    width: 70%;
}

/* Active tab always has underline */
.active-tab {
    color: white !important;
}

/* ─── Google Button ─── */
.btn-google {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-google:hover {
    transform: scale(1.04);
    color: white;
}

/* Underline color appears on hover */
.btn-google::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #d4af37, #eab308);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-google:hover::before {
    opacity: 1;
}

/* ─── Dark Theme (default) ─── */
[data-theme="dark"] .glass-card {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(212, 175, 55, 0.18);
}

[data-theme="dark"] .glass-input {
    background: rgba(30, 41, 59, 0.45);
    border-color: rgba(212, 175, 55, 0.25);
    color: white;
}

[data-theme="dark"] .glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

[data-theme="dark"] .btn-glow {
    background: linear-gradient(90deg, #d4af37, #eab308, #fbbf24);
    color: #0f172a;
}

/* ─── Light Theme ─── */
[data-theme="light"] .auth-container,
[data-theme="light"] .auth-container * {
    color: #111827;
}

[data-theme="light"] .glass-card {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(12px);
}

[data-theme="light"] .glass-input {
    background: rgba(243, 244, 246, 0.9);
    border: 1px solid #d1d5db;
    color: #111827;
}

[data-theme="light"] .glass-input:focus {
    border-color: #111827;
    box-shadow: 0 0 0 0.25rem rgba(17, 24, 39, 0.15);
}

[data-theme="light"] .glass-input::placeholder {
    color: #6b7280;
}

[data-theme="light"] .btn-glow {
    background: #111827;
    color: white;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

[data-theme="light"] .btn-glow:hover {
    background: #1f2937;
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}

/* Light theme → tabs: black underline */
[data-theme="light"] .nav-link::after,
[data-theme="light"] .active-tab::after {
    background: #111827 !important;
}

/* Light theme → Google button hover becomes black */
[data-theme="light"] .btn-google {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme="light"] .btn-google:hover {
    color: white;
}

[data-theme="light"] .btn-google::before {
    background: #111827;
}

[data-theme="light"] .btn-apple {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme="light"] .btn-apple:hover {
    color: white;
}

[data-theme="light"] .btn-apple::before {
    background: #111827;
}

/* Disable gold glow effects in light mode */
[data-theme="light"] .text-glow,
[data-theme="light"] .logo-glow,
[data-theme="light"] .active-tab {
    filter: none !important;
    background: none !important;
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: #111827 !important;
}

[data-theme="light"] .logo-glow {
    filter: none;
}

/* Waves in light mode → very subtle or hidden */
[data-theme="light"] .waves {
    opacity: 0.03;
    filter: brightness(0.4);
}

.btn-facebook {
    background: rgba(59, 89, 152, 0.08);
    border: 1px solid rgba(59, 89, 152, 0.15);
    color: white;
    transition: all 0.4s ease;
    overflow: hidden;
    position: relative;
}

.btn-facebook:hover {
    transform: scale(1.04);
    color: white;
}

.btn-facebook::before {
    content: "";
    position: absolute;
    inset: 0;
    background: #3b5998;
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-facebook:hover::before {
    opacity: 1;
}

.upload-avatar {
    cursor: pointer;
    display: inline-block;
}

.avatar-preview,
.avatar-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(212, 175, 55, 0.5);
}

.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(212, 175, 55, 0.15);
    color: white;
    font-size: 13px;
    font-weight: 600;
}
</style>
