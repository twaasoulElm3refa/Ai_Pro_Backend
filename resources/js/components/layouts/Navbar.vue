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

            <!-- Hamburger -->
            <button class="btn-icon d-md-none" @click="mobileMenu = !mobileMenu">
                <i class="bi" :class="mobileMenu ? 'bi-x-lg' : 'bi-list'"></i>
            </button>

            <!-- Actions -->
            <div class="d-flex align-items-center gap-2">

                <!-- Wallet -->
                <button class="btn-icon cart-btn position-relative" @click="goToWallet">
                    <i class="bi bi-wallet2"></i>
                    <span v-if="WalletBalance !== null" class="badge">
                        {{ WalletBalance }}
                    </span>
                </button>

                <!-- Auth -->
                <div class="d-flex gap-2 position-relative">
                    <template v-if="isLoggedIn">
                        <button class="btn-user user-hover fw-bold d-flex align-items-center gap-2 shadow-gold"
                            @click="toggleDropdown">
                            {{ userName }}
                            <i class="bi" :class="dropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>

                        <div v-if="dropdownOpen"
                             class="dropdown-menu show shadow rounded mt-2 dropdown-dark"
                             style="position:absolute; right:0; top:100%; z-index:1050;">
                            <a class="dropdown-item" href="/en/profile">الملف الشخصي</a>
                            <hr class="dropdown-divider">
                            <button class="dropdown-item text-danger" @click="logout">
                                تسجيل الخروج
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <a href="/en/auth" class="btn-user user-hover fw-bold shadow-gold text-decoration-none">
                            تسجيل الدخول
                        </a>
                    </template>
                </div>

            </div>
        </div>

        <!-- Mobile -->
        <div v-if="mobileMenu" class="d-md-none mt-2 bg-white dark:bg-dark shadow-lg rounded p-3">
            <a v-for="link in links" :key="link.href" :href="link.href" class="d-block nav-link py-2 px-1"
                :class="{ active: isActive(link.active) }">
                {{ link.label }}
            </a>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/ApiClient";

const theme = ref("dark");
const isLoggedIn = ref(false);
const userName = ref("المستخدم");
const WalletBalance = ref(null);
const dropdownOpen = ref(false);
const mobileMenu = ref(false);

const links = [
    { label: "الرئيسية", href: "/en", active: "home" },
    { label: "جميع النماذج", href: "/en/products", active: "products" },
    { label: "من نحن", href: "/en/who", active: "who" },
    { label: "اتصل بنا", href: "/en/contact", active: "contact" },
];

// ================= Wallet =================
const fetchWallet = async () => {
    const token = localStorage.getItem("auth_token");
    if (!token) return;

    try {
        const res = await api.get("/users/wallet");

        if (res.data.status === "success") {
            WalletBalance.value = res.data.data.balance;
        }
    } catch (err) {
        console.log("Wallet error:", err);
    }
};

const goToWallet = () => {
    const lang = localStorage.getItem("lang") || "ar";
    window.location.href = `/${lang}/wallet`;
};

// ================= Profile =================
const fetchProfile = async () => {
    const token = localStorage.getItem("auth_token");
    if (!token) return;
    try {
        const res = await api.get("/users/profile");
        if (res.data.status === "success") {
            userName.value = res.data.data.user.name || "المستخدم";
            isLoggedIn.value = true;
        }
    } catch (err) {
        console.log("Profile error:", err);
    }
};

// ================= Logout =================
const logout = () => {
    localStorage.removeItem("auth_token");
    isLoggedIn.value = false;
    userName.value = "المستخدم";
    WalletBalance.value = null;
    dropdownOpen.value = false;
    window.location.href = "/";
};

// ================= UI =================
const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

const isActive = (name) => document.body.dataset.route === name;

// ================= Lifecycle =================
onMounted(() => {
    fetchProfile();
    fetchWallet();

    window.addEventListener("login", () => {
        fetchProfile();
        fetchWallet();
    });

    document.addEventListener("click", (e) => {
        if (!e.target.closest(".position-relative")) {
            dropdownOpen.value = false;
        }
    });
});
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

    /* التعديلات المهمة هنا ↓ */
    position: relative;
    z-index: 1050;           /* رفع الـ header كله */
    overflow: visible !important;
}

/* Logo */
.logo {
    width: 120px;
    height: 80px;
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
.dropdown-menu {
    z-index: 1060 !important;   /* أعلى من الـ header */
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

/* إذا كان الـ header sticky أو fixed في مكان تاني، أضف كمان: */
.navbar-wrap {
    position: sticky;
    top: 0;
    z-index: 1050;
}
</style>
