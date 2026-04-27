<template>
    <header class="nb-root" dir="rtl">
        <div class="nb-inner">

            <!-- Logo -->
            <a class="nb-logo" href="/">
                <div class="nb-logo-mark">
                    <img src="/images/ai_logo.png" alt="AiPro Logo" />
                </div>

                <span class="nb-logo-text">
                    Ai<span>Pro</span>
                </span>
            </a>

            <div class="nb-spacer"></div>

            <div class="nb-actions">

                <!-- Home -->
                <a href="/en" class="nb-home-link">الرئيسية</a>
                <div class="nb-divider"></div>

                <!-- Wallet -->
                <button class="nb-wallet-btn" @click="goToWallet" title="المحفظة">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="6" width="20" height="14" rx="3" stroke="white" stroke-width="1.6" />
                        <path d="M16 13a1 1 0 1 0 2 0 1 1 0 0 0-2 0Z" fill="white" />
                        <path d="M2 10h20" stroke="white" stroke-width="1.6" />
                    </svg>
                    <span v-if="WalletBalance !== null" class="nb-badge">{{ WalletBalance }}</span>
                </button>

                <!-- Logged In -->
                <template v-if="isLoggedIn">
                    <div class="nb-user-btn" @click.stop="toggleDropdown">
                        <div class="nb-avatar">{{ userName.charAt(0) }}</div>
                        <span>{{ userName }}</span>
                        <span class="nb-chevron" :class="{ open: dropdownOpen }">▼</span>

                        <div v-if="dropdownOpen" class="nb-dropdown">
                            <div class="nb-dd-header">
                                <div class="nb-dd-name">{{ userName }}</div>
                                <div class="nb-dd-label">حساب شخصي</div>
                            </div>
                            <a href="/en/profile" class="nb-dd-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="8" r="4" stroke="#074377" stroke-width="1.6" />
                                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="#074377" stroke-width="1.6"
                                        stroke-linecap="round" />
                                </svg>
                                الملف الشخصي
                            </a>
                            <div class="nb-dd-sep"></div>
                            <button class="nb-dd-item danger" @click="logout">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="#c0392b"
                                        stroke-width="1.6" stroke-linecap="round" />
                                    <polyline points="16 17 21 12 16 7" stroke="#c0392b" stroke-width="1.6"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <line x1="21" y1="12" x2="9" y2="12" stroke="#c0392b" stroke-width="1.6"
                                        stroke-linecap="round" />
                                </svg>
                                تسجيل الخروج
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Guest -->
                <template v-else>
                    <a href="/en/auth" class="nb-login-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" stroke="#074377" stroke-width="1.8"
                                stroke-linecap="round" />
                            <polyline points="10 17 15 12 10 7" stroke="#074377" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="15" y1="12" x2="3" y2="12" stroke="#074377" stroke-width="1.8"
                                stroke-linecap="round" />
                        </svg>
                        تسجيل الدخول
                    </a>
                </template>

            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/ApiClient";

const isLoggedIn = ref(false);
const userName = ref("المستخدم");
const WalletBalance = ref(0);
const dropdownOpen = ref(false);

// ================= Wallet =================
const fetchWallet = async () => {
    if (!localStorage.getItem("auth_token")) return;
    try {
        const res = await api.get("/users/wallet");
        if (res.data.status === "success") {
            WalletBalance.value = res.data.data?.wallet?.balance ?? 0;
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
    if (!localStorage.getItem("auth_token")) return;
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
const logout = async () => {
    try {
        await api.post("/users/logout");
    } catch (err) {
        console.log("Logout error:", err);
    }
    localStorage.removeItem("user_role");
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

// ================= Lifecycle =================
onMounted(() => {
    fetchProfile();
    fetchWallet();

    window.addEventListener("login", () => {
        fetchProfile();
        fetchWallet();
    });

    document.addEventListener("click", (e) => {
        if (!e.target.closest(".nb-user-btn")) {
            dropdownOpen.value = false;
        }
    });
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap');

.nb-root {
    font-family: 'Cairo', sans-serif;
    background: #074377;
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 2px 20px rgba(7, 67, 119, 0.4);
}

.nb-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    padding: 0 24px;
    height: 64px;
    gap: 16px;
}

.nb-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    flex-shrink: 0;
}

.nb-logo-mark {
    width: 56px;
    height: 56px;
    background:white;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.nb-logo-mark svg {
    width: 20px;
    height: 20px;
}

.nb-logo-text {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.3px;
}

.nb-logo-text span {
    color: #5BC8F5;
}

.nb-spacer {
    flex: 1;
}

.nb-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.nb-home-link {
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    transition: all 0.2s;
    white-space: nowrap;
}

.nb-home-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.3);
}

.nb-divider {
    width: 1px;
    height: 28px;
    background: rgba(255, 255, 255, 0.15);
}

.nb-wallet-btn {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.nb-wallet-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-1px);
}

.nb-badge {
    position: absolute;
    top: -5px;
    left: -5px;
    min-width: 18px;
    height: 18px;
    background: #5BC8F5;
    color: #074377;
    font-size: 10px;
    font-weight: 700;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid #074377;
    font-family: 'Cairo', sans-serif;
}

.nb-user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    cursor: pointer;
    font-family: 'Cairo', sans-serif;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
    position: relative;
    white-space: nowrap;
}

.nb-user-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
}

.nb-avatar {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: #5BC8F5;
    color: #074377;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.nb-chevron {
    font-size: 10px;
    opacity: 0.7;
    transition: transform 0.2s;
}

.nb-chevron.open {
    transform: rotate(180deg);
}

.nb-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    min-width: 190px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(7, 67, 119, 0.18);
    border: 1px solid rgba(7, 67, 119, 0.1);
    overflow: hidden;
    z-index: 9999;
}

.nb-dd-header {
    padding: 14px 16px 10px;
    border-bottom: 1px solid #f0f4f8;
}

.nb-dd-name {
    font-size: 14px;
    font-weight: 700;
    color: #074377;
    font-family: 'Cairo', sans-serif;
}

.nb-dd-label {
    font-size: 11px;
    color: #888;
    font-family: 'Cairo', sans-serif;
    margin-top: 2px;
}

.nb-dd-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    font-size: 13px;
    color: #333;
    text-decoration: none;
    font-family: 'Cairo', sans-serif;
    font-weight: 500;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: right;
    transition: background 0.15s;
}

.nb-dd-item:hover {
    background: #f5f9fc;
}

.nb-dd-item.danger {
    color: #c0392b;
}

.nb-dd-item.danger:hover {
    background: #fef3f2;
}

.nb-dd-sep {
    height: 1px;
    background: #f0f4f8;
    margin: 2px 0;
}

.nb-login-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 10px;
    background: #5BC8F5;
    border: none;
    color: #074377;
    cursor: pointer;
    font-family: 'Cairo', sans-serif;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
}

.nb-login-btn:hover {
    background: #3db8e8;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(91, 200, 245, 0.35);
}
</style>
