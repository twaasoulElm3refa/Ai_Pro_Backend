<template>
    <header class="navbar-wrap shadow-lg my-3 rounded-4xl" dir="rtl">
        <div class="container d-flex justify-content-between align-items-center">

            <!-- Logo -->
            <img src="/images/Ai_logo.png" class="logo" alt="Logo" />

            <!-- Links -->
            <nav class="d-none d-md-flex flex-grow-1 justify-content-center">
                <a v-for="link in links" :key="link.href" :href="link.href" class="nav-link px-2"
                    :class="{ active: isActive(link.active) }">
                    {{ link.label }}
                </a>
            </nav>

            <!-- Hamburger for Mobile -->
            <button class="btn-icon d-md-none" @click="mobileMenu = !mobileMenu">
                <i class="bi" :class="mobileMenu ? 'bi-x-lg' : 'bi-list'"></i>
            </button>

            <!-- Actions -->
            <div class="d-flex align-items-center gap-2">

                <!-- Theme Toggle -->
                <button class="btn-icon" @click="toggleTheme" title="تغيير الثيم">
                    <i class="bi" :class="theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill'"></i>
                </button>

                <!-- Cart -->
                <button class="btn-icon cart-btn position-relative" @click="goToCart" title="السلة">
                    <i class="bi bi-cart3"></i>
                    <span v-if="cartCount" class="badge">{{ cartCount }}</span>
                </button>

                <!-- Auth Area -->
                <div class="d-flex gap-2 position-relative">
                    <template v-if="isLoggedIn">
                        <button class="btn-user user-hover fw-bold d-flex align-items-center gap-2 shadow-gold"
                            @click="toggleDropdown">
                            {{ userName }}
                            <i class="bi" :class="dropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <div v-if="dropdownOpen" class="dropdown-menu show shadow rounded mt-2 dropdown-dark"
                            style="position:absolute; right:0; top:100%; margin-top:8px; z-index: 1000; min-width:150px;">
                            <a class="dropdown-item" href="/profile">الملف الشخصي</a>
                            <hr class="dropdown-divider">
                            <button class="dropdown-item text-danger" @click="logout">
                                تسجيل الخروج
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <a href="/auth" class="btn-user user-hover fw-bold shadow-gold text-decoration-none">
                            تسجيل الدخول
                        </a>
                    </template>
                </div>

            </div>
        </div>

        <!-- Mobile Menu -->
        <div v-if="mobileMenu" class="d-md-none mt-2 bg-white dark:bg-dark shadow-lg rounded p-3">
            <a v-for="link in links" :key="link.href" :href="link.href" class="d-block nav-link py-2 px-1"
                :class="{ active: isActive(link.active) }">
                {{ link.label }}
            </a>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const theme = ref('dark')
const isLoggedIn = ref(false)
const userName = ref('المستخدم')
const cartCount = ref(3)
const dropdownOpen = ref(false)
const mobileMenu = ref(false)

const links = [
    { label: 'الرئيسية', href: '/', active: 'home' },
    { label: 'جميع النماذج', href: '/products', active: 'products' },
    { label: 'من نحن', href: '/who', active: 'who' },
    { label: 'اتصل بنا', href: '/contact', active: 'contact' },
]

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
    localStorage.setItem('theme', theme.value)
    applyTheme()
}

const applyTheme = () => {
    document.documentElement.setAttribute('data-theme', theme.value)
    document.documentElement.setAttribute('data-bs-theme', theme.value)
}

const isActive = (name) => document.body.dataset.route === name

const goToCart = () => window.location.href = '/cart'

const toggleDropdown = () => dropdownOpen.value = !dropdownOpen.value

const logout = () => {
    localStorage.removeItem('auth_token')
    axios.defaults.headers.common['Authorization'] = ''
    isLoggedIn.value = false
    userName.value = 'المستخدم'
    dropdownOpen.value = false
    window.location.href = '/'
}

const fetchProfile = async () => {
    const token = localStorage.getItem('auth_token')
    if (!token) return

    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    try {
        const res = await axios.get('/v1/users/profile')
        if (res.data.status === 'success') {
            userName.value = res.data.data.user.name || 'المستخدم'
            isLoggedIn.value = true
        }
    } catch (err) {
        console.log('Failed to fetch profile:', err)
    }
}

onMounted(() => {
    const savedTheme = localStorage.getItem('theme') || 'dark'
    theme.value = savedTheme
    applyTheme()

    fetchProfile()
})
</script>

<style scoped>
/* ─────────────────────────────────────────────── */
.navbar-wrap {
    background: var(--nav-bg);
    color: var(--text-main);
    padding: 5px 8px;
    transition: all 0.4s ease;
    margin-left: 8rem;
    /* ~48px */
    margin-right: 8rem;
}

/* Logo */
.logo {
    width: 120px;
    height: 60px;
    object-fit: cover;
}

/* ── Navigation Links ── */
.nav-link {
    margin: 0 3px;
    color: var(--text-main);
    text-decoration: none;
    font-weight: 500;
    position: relative;
    padding-bottom: 4px;
    transition: color 0.3s ease;
}

/* Animated Underline */
.nav-link::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2.5px;
    bottom: 0;
    right: 0;
    background: var(--gold);
    transition: width 0.4s cubic-bezier(0.22, 0.61, 0.36, 1);
    border-radius: 4px;
}

/* Light Mode → Cart hover أسود */
[data-theme='light'] .cart-btn:hover {
    background: #000000;
    /* أسود */
    color: #000000;
    /* أيقونة أبيض */
    border-color: #000000;
    transform: scale(1.08);
}

[data-theme='dark'] .nav-link:hover,
[data-theme='dark'] .nav-link.active {
    color: var(--gold);
}

[data-theme='dark'] .nav-link:hover::after,
[data-theme='dark'] .nav-link.active::after {
    width: 100%;
}

/* ── Light Mode → كل حاجة أبيض وأسود ── */
[data-theme='light'] .nav-link,
[data-theme='light'] .nav-link:hover,
[data-theme='light'] .nav-link.active {
    color: #111827;
}

[data-theme='light'] .nav-link::after {
    background: #111827;
}

[data-theme='light'] .nav-link:hover::after,
[data-theme='light'] .nav-link.active::after {
    width: 100%;
}

/* ── Buttons ── */
.btn-icon {
    width: 42px;
    position: relative;
    height: 42px;
    border-radius: 50%;
    background: var(--btn-bg);
    color: var(--gold);
    border: none;
    display: grid;
    place-items: center;
    transition: all 0.25s ease;
}

.btn-icon .badge {
    position: absolute;
    top: -6px;
    /* عدل الرقم حسب ما تحب */
    right: -6px;
    /* عدل الرقم حسب ما تحب */
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

/* Dark mode icon & bg */
[data-theme='dark'] .btn-icon {
    color: var(--gold);
}

[data-theme='dark'] .btn-icon:hover {
    background: var(--gold);
    color: #000;
    transform: scale(1.08);
}

/* ── Light mode: أبيض وأسود فقط ── */
[data-theme='light'] .btn-icon {
    background: #ffffff;
    color: #111827;
    /* أسود غامق */
    border: 1px solid #d1d5db;
    /* رمادي فاتح */
}

[data-theme='light'] .btn-icon:hover {
    background: #f3f4f6;
    /* رمادي فاتح جدًا */
    color: #111827;
    border-color: #9ca3af;
    transform: scale(1.08);
}

/* Cart Badge – في الـ light mode */
[data-theme='light'] .badge {
    background: #111827;
    /* أسود */
    color: #ffffff;
    /* أبيض */
}

/* ── Login / User Button ── */
.btn-user {
    background: transparent;
    border: 1.5px solid var(--gold);
    color: var(--gold);
    text-decoration: none;
    padding: 8px 18px;
    border-radius: 999px;
    font-weight: 500;
    transition: all 0.3s ease;
    white-space: nowrap;
}

/* Dark mode */
[data-theme='dark'] .btn-user {
    border-color: var(--gold);
    color: var(--gold);
}

[data-theme='dark'] .user-hover:hover {
    background: var(--gold);
    color: #000;
    transform: scale(1.06);
    box-shadow: 0 0 25px rgba(251, 191, 36, 0.7);
}

/* Light mode: أبيض وأسود فقط */
[data-theme='light'] .btn-user {
    border: 1.5px solid #111827;
    color: #111827;
}

[data-theme='light'] .user-hover:hover {
    background: #111827;
    color: #ffffff;
    transform: scale(1.06);
    box-shadow: 0 0 16px rgba(17, 24, 39, 0.35);
    /* shadow أسود خفيف */
}
</style>
