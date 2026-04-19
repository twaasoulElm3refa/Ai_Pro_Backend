<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center position-relative overflow-hidden">

        <!-- Animated wavy background overlay -->
        <div class="position-absolute w-100 h-100 waves"></div>

        <!-- Main content wrapper -->
        <div class="auth-container position-relative z-2 d-flex flex-column flex-lg-row w-100 h-100">

            <!-- Left branding side -->
            <div class="d-none d-lg-flex col-lg-5 align-items-center justify-content-center p-5 position-relative">
                <div class="text-center">
                    <img src="/images/Ai_logo.png" alt="AI PRO Logo" class="height-auto logo-glow" style="width: auto;">

                    <h1 class="display-3 fw-black mb-3">AI PRO</h1>

                    <p class="lead fs-4 fw-medium opacity-90">
                        Build. Learn. Evolve with AI
                    </p>
                </div>
            </div>

            <!-- Form side -->
            <div class="col-12 col-lg-7 d-flex align-items-center justify-content-center p-4 p-md-5">
                <div class="glass-card rounded-4 shadow-glow p-4 p-md-5 w-100"
                    style="max-width: 480px; backdrop-filter: blur(16px);">

                    <!-- Tabs with animated underline -->
                    <div class="position-relative mb-4"
                        style="border-bottom: 1px solid rgba(255,255,255,0.15); max-width: 400px;">
                        <ul class="nav nav-pills nav-fill">
                            <li class="nav-item text-center flex-grow-1">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'login' }" @click.prevent="tab = 'login'">
                                    Login
                                </button>
                            </li>

                            <li class="nav-item text-center flex-grow-1">
                                <button class="nav-link px-0 fs-5 fw-semibold position-relative"
                                    :class="{ 'active-tab': tab === 'register' }" @click.prevent="tab = 'register'">
                                    Register
                                </button>
                            </li>
                        </ul>

                        <!-- Animated underline -->
                        <div class="position-absolute rounded-pill"
                            style="height: 3px; bottom: 0; transition: all 0.4s ease;">
                        </div>
                    </div>

                    <!-- Success Message -->
                    <transition name="fade">
                        <p v-if="successMessage" class="text-center text-success mb-4">{{ successMessage }}</p>
                    </transition>

                    <!-- Login Form -->
                    <form v-if="tab === 'login'" @submit.prevent="handleLogin" class="d-flex flex-column gap-4">
                        <input v-model="loginForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required>

                        <div class="input-group input-group-lg">
                            <input v-model="loginForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 py-3 px-4 fs-5 border-end-0"
                                placeholder="Password" required>
                            <span class="input-group-text glass-input rounded-3 border-start-0"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? '🙈' : '👁️' }}</span>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5 mt-3"
                            :disabled="loading">
                            {{ loading ? 'Loading...' : 'Login' }}
                        </button>
                        <p class="text-right">
                            <a href="#" class="text-glow fw-medium fs-6" @click.prevent="tab = 'forgot'">
                                هل نسيت كلمة المرور؟
                            </a>
                        </p>


                        <p v-if="error" class="text-center text-danger text-sm ">{{ error }}</p>

                        <!-- Google Login Button -->
                        <div class="d-flex flex-column gap-3">
                            <button @click.prevent="handleGoogleLogin"
                                class="btn btn-google btn-lg d-flex align-items-center justify-content-center gap-2">
                                <img src="/images/google_logo.png" alt="Google Logo" style="width:36px; height:36px;">
                                Continue with Google
                            </button>
                        </div>
                    </form>

                    <!-- Register Form -->
                    <form v-else @submit.prevent="handleRegister" class="d-flex flex-column gap-4">
                        <input v-model="registerForm.name" type="text"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Full Name" required>
                        <input v-model="registerForm.email" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required>

                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 py-3 px-4 fs-5 border-end-0"
                                placeholder="Password" required>
                            <span class="input-group-text glass-input rounded-3 border-start-0"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? '🙈' : '👁️' }}</span>
                            </span>
                        </div>

                        <div class="input-group input-group-lg">
                            <input v-model="registerForm.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                class="form-control glass-input rounded-3 py-3 px-4 fs-5 border-end-0"
                                placeholder="Confirm Password" required>
                            <span class="input-group-text glass-input rounded-3 border-start-0"
                                @click="showPassword = !showPassword">
                                <span class="fs-4">{{ showPassword ? '🙈' : '👁️' }}</span>
                            </span>
                        </div>

                        <button type="submit" class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5 mt-3"
                            :disabled="loading">
                            {{ loading ? 'Creating...' : 'Create Account' }}
                        </button>

                        <p v-if="error" class="text-center text-danger text-sm mt-2">{{ error }}</p>

                        <!-- Google Register Button -->
                        <div class="d-flex flex-column gap-3 mt-3">
                            <button @click.prevent="handleGoogleLogin"
                                class="btn btn-google btn-lg d-flex align-items-center justify-content-center gap-2">
                                <img src="/images/google_logo.png" alt="Google Logo" style="width:36px; height:36px;">
                                Continue with Google
                            </button>
                        </div>
                    </form>

                    <form v-if="tab === 'forgot'" @submit.prevent="handleForgot" class="d-flex flex-column gap-4">

                        <input v-model="forgotEmail" type="email"
                            class="form-control form-control-lg glass-input rounded-3 py-3 px-4 fs-5"
                            placeholder="Email Address" required>

                        <button class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5">
                            إرسال الكود
                        </button>

                        <p class="text-center">
                            <a href="#" class="text-glow" @click.prevent="tab = 'login'">
                                رجوع لتسجيل الدخول
                            </a>
                        </p>
                    </form>

                    <form v-if="tab === 'reset'" @submit.prevent="handleReset" class="d-flex flex-column gap-4">

                        <input v-model="resetForm.email" type="email" class="form-control form-control-lg glass-input"
                            placeholder="Email" required>

                        <input v-model="resetForm.otp" type="text" class="form-control form-control-lg glass-input"
                            placeholder="OTP Code" required>

                        <input v-model="resetForm.password" type="password"
                            class="form-control form-control-lg glass-input" placeholder="New Password" required>

                        <input v-model="resetForm.password_confirmation" type="password"
                            class="form-control form-control-lg glass-input" placeholder="Confirm Password" required>

                        <button class="btn btn-glow btn-lg rounded-3 py-3 fw-bold fs-5">
                            تغيير كلمة المرور
                        </button>
                    </form>


                    <!-- Switch link -->
                    <div class="text-center mt-4 text-white fs-6">
                        <span v-if="tab === 'register'">
                            Already have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="tab = 'login'">Login</a>
                        </span>
                        <span v-else>
                            Don't have an account?
                            <a href="#" class="text-glow fw-medium" @click.prevent="tab = 'register'">Register</a>
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useRouter, useRoute } from 'vue-router'

const tab = ref('login')
const forgotEmail = ref('')

const resetForm = ref({
    email: '',
    otp: '',
    password: '',
    password_confirmation: ''
})

const showPassword = ref(false)
const router = useRouter()
const route = useRoute()

const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const error = ref('')
const successMessage = ref('')

const saveTokenAndRedirect = (token, role = 'user') => {
    if (!token) return

    localStorage.setItem('auth_token', token)
    localStorage.setItem('user_role', role)

    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

    const url = new URL(window.location.href)
    url.searchParams.delete('token')
    window.history.replaceState({}, document.title, url)

    if (role === 'admin') {
        router.push('/admin')
    } else {
        router.push('/')
    }
}

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const token = urlParams.get('token')
    const role = urlParams.get('role')
    const errorParam = urlParams.get('error')

    if (token) {
        saveTokenAndRedirect(token, role)
    } else if (errorParam) {
        alert(errorParam)
    }
})


const handleLogin = async () => {
    loading.value = true
    error.value = ''

    try {
        const res = await axios.post('/v1/users/login', loginForm.value)

        if (res.data.status === 'success') {
            saveTokenAndRedirect(res.data.data.token, res.data.data.user.role)
        } else {
            error.value = res.data.message || 'Login failed'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Error during login'
    } finally {
        loading.value = false
    }
}

const handleRegister = async () => {
    loading.value = true
    error.value = ''

    try {
        const res = await axios.post('/v1/users/register', registerForm.value)

        if (res.data.status === 'success') {
            successMessage.value = 'Account created successfully'
            saveTokenAndRedirect(res.data.data.token)
        } else {
            error.value = res.data.message || 'Registration failed'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Error during registration'
    } finally {
        loading.value = false
    }
}


const handleForgot = async () => {
    error.value = ''
    try {
        await axios.post('/v1/users/forgot-password', {
            email: forgotEmail.value
        })

        resetForm.value.email = forgotEmail.value
        successMessage.value = 'تم إرسال الكود على الإيميل'
        tab.value = 'reset'
    } catch (err) {
        error.value = err.response?.data?.message || 'حصل خطأ'
    }
}

const handleReset = async () => {
    error.value = ''
    try {
        await axios.post('/v1/users/reset-password', resetForm.value)

        successMessage.value = 'تم تغيير كلمة المرور بنجاح'
        tab.value = 'login'
    } catch (err) {
        error.value = err.response?.data?.message || 'الكود غير صحيح'
    }
}

const handleGoogleLogin = async () => {
    try {
        const res = await axios.get('/v1/users/google-login');
        window.location.href = res.data.url;
    } catch (err) {
        console.log(err);
    }
}
</script>

<style scoped>
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
    border-color: #D4AF37;
    box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
    outline: none;
}

.glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

.btn-glow {
    background: linear-gradient(90deg, #D4AF37, #EAB308, #FBBF24);
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
    content: '';
    position: absolute;
    bottom: -4px;
    left: 50%;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #D4AF37, #EAB308);
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
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #D4AF37, #EAB308);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: -1;
}

.btn-google:hover::before {
    opacity: 1;
}

/* ─── Dark Theme (default) ─── */
[data-theme='dark'] .glass-card {
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(212, 175, 55, 0.18);
}

[data-theme='dark'] .glass-input {
    background: rgba(30, 41, 59, 0.45);
    border-color: rgba(212, 175, 55, 0.25);
    color: white;
}

[data-theme='dark'] .glass-input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

[data-theme='dark'] .btn-glow {
    background: linear-gradient(90deg, #D4AF37, #EAB308, #FBBF24);
    color: #0f172a;
}

/* ─── Light Theme ─── */
[data-theme='light'] .auth-container,
[data-theme='light'] .auth-container * {
    color: #111827;
}

[data-theme='light'] .glass-card {
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(12px);
}

[data-theme='light'] .glass-input {
    background: rgba(243, 244, 246, 0.9);
    border: 1px solid #d1d5db;
    color: #111827;
}

[data-theme='light'] .glass-input:focus {
    border-color: #111827;
    box-shadow: 0 0 0 0.25rem rgba(17, 24, 39, 0.15);
}

[data-theme='light'] .glass-input::placeholder {
    color: #6b7280;
}

[data-theme='light'] .btn-glow {
    background: #111827;
    color: white;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

[data-theme='light'] .btn-glow:hover {
    background: #1f2937;
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}

/* Light theme → tabs: black underline */
[data-theme='light'] .nav-link::after,
[data-theme='light'] .active-tab::after {
    background: #111827 !important;
}

/* Light theme → Google button hover becomes black */
[data-theme='light'] .btn-google {
    background: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.12);
    color: #111827;
}

[data-theme='light'] .btn-google:hover {
    color: white;
}

[data-theme='light'] .btn-google::before {
    background: #111827;
}

/* Disable gold glow effects in light mode */
[data-theme='light'] .text-glow,
[data-theme='light'] .logo-glow,
[data-theme='light'] .active-tab {
    filter: none !important;
    background: none !important;
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: #111827 !important;
}

[data-theme='light'] .logo-glow {
    filter: none;
}

/* Waves in light mode → very subtle or hidden */
[data-theme='light'] .waves {
    opacity: 0.03;
    filter: brightness(0.4);
}
</style>
