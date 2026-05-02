<template>
    <header class="nb-root" dir="rtl">
        <div class="nb-inner">
            <a class="nb-logo" href="/">
                <div class="nb-logo-mark">
                    <img src="/images/ai_logo.png" alt="AiPro Logo" width="42" height="42" fetchpriority="high"
                        decoding="async" />
                </div>

                <span class="nb-logo-text">
                    Ai<span>Pro</span>
                </span>
            </a>

            <div class="nb-spacer"></div>

            <div class="nb-actions">
                <a href="/en" class="nb-home-link">الرئيسية</a>
                <div class="nb-divider"></div>

                <button class="nb-wallet-btn" @click="goToWallet" title="المحفظة">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="6" width="20" height="14" rx="3" stroke="white" stroke-width="1.6" />
                        <path d="M16 13a1 1 0 1 0 2 0 1 1 0 0 0-2 0Z" fill="white" />
                        <path d="M2 10h20" stroke="white" stroke-width="1.6" />
                    </svg>
                    <span v-if="WalletBalance !== null" class="nb-badge">{{ WalletBalance }}</span>
                </button>

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
                                    <circle cx="12" cy="8" r="4" stroke="#154677" stroke-width="1.6" />
                                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="#154677" stroke-width="1.6" stroke-linecap="round" />
                                </svg>
                                الملف الشخصي
                            </a>
                            <div class="nb-dd-sep"></div>
                            <button class="nb-dd-item danger" @click="logout">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="#154677" stroke-width="1.6" stroke-linecap="round" />
                                    <polyline points="16 17 21 12 16 7" stroke="#154677" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    <line x1="21" y1="12" x2="9" y2="12" stroke="#154677" stroke-width="1.6" stroke-linecap="round" />
                                </svg>
                                تسجيل الخروج
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <a href="/en/auth" class="nb-login-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" stroke="white" stroke-width="1.8" stroke-linecap="round" />
                            <polyline points="10 17 15 12 10 7" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="15" y1="12" x2="3" y2="12" stroke="white" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        تسجيل الدخول
                    </a>
                </template>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import api from "@/services/ApiClient";

const isLoggedIn = ref(false);
const userName = ref("المستخدم");
const WalletBalance = ref(0);
const dropdownOpen = ref(false);
const PROFILE_CACHE_KEY = "navbar_profile_cache_v1";
const WALLET_CACHE_KEY = "navbar_wallet_cache_v1";
const NAV_CACHE_TTL = 60 * 1000;

const readCache = (key) => {
    try {
        const raw = sessionStorage.getItem(key);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed?.expiresAt || parsed.expiresAt < Date.now()) {
            sessionStorage.removeItem(key);
            return null;
        }
        return parsed.value;
    } catch {
        return null;
    }
};

const writeCache = (key, value) => {
    try {
        sessionStorage.setItem(
            key,
            JSON.stringify({
                value,
                expiresAt: Date.now() + NAV_CACHE_TTL,
            })
        );
    } catch {
        // Ignore sessionStorage edge cases.
    }
};

const deferToIdle = (task) => {
    if ("requestIdleCallback" in window) {
        window.requestIdleCallback(task, { timeout: 800 });
        return;
    }
    setTimeout(task, 120);
};

const fetchWallet = async () => {
    if (!localStorage.getItem("auth_token")) return;

    const cachedBalance = readCache(WALLET_CACHE_KEY);
    if (cachedBalance !== null) {
        WalletBalance.value = cachedBalance;
        return;
    }

    try {
        const res = await api.get("/users/wallet");
        if (res.data.status === "success") {
            WalletBalance.value = res.data.data?.balance ?? 0;
            writeCache(WALLET_CACHE_KEY, WalletBalance.value);
        }
    } catch (err) {
        console.log("Wallet error:", err);
    }
};

const goToWallet = () => {
    const lang = localStorage.getItem("lang") || "ar";
    window.location.href = `/${lang}/wallet`;
};

const fetchProfile = async () => {
    if (!localStorage.getItem("auth_token")) return;

    const cachedProfile = readCache(PROFILE_CACHE_KEY);
    if (cachedProfile?.name) {
        userName.value = cachedProfile.name;
        isLoggedIn.value = true;
        return;
    }

    try {
        const res = await api.get("/users/profile");
        if (res.data.status === "success") {
            userName.value = res.data.data.user.name || "????????";
            isLoggedIn.value = true;
            writeCache(PROFILE_CACHE_KEY, { name: userName.value });
        }
    } catch (err) {
        console.log("Profile error:", err);
    }
};

const logout = async () => {
    try {
        await api.post("/users/logout");
    } catch (err) {
        console.log("Logout error:", err);
    }
    localStorage.removeItem("user_role");
    localStorage.removeItem("auth_token");
    sessionStorage.removeItem(PROFILE_CACHE_KEY);
    sessionStorage.removeItem(WALLET_CACHE_KEY);
    isLoggedIn.value = false;
    userName.value = "المستخدم";
    WalletBalance.value = null;
    dropdownOpen.value = false;
    window.location.href = "/";
};

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

const handleDocumentClick = (e) => {
    if (!e.target.closest(".nb-user-btn")) {
        dropdownOpen.value = false;
    }
};

const refreshUserState = () => {
    fetchProfile();
    deferToIdle(() => {
        fetchWallet();
    });
};

onMounted(() => {
    refreshUserState();
    window.addEventListener("login", refreshUserState);
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    window.removeEventListener("login", refreshUserState);
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<style scoped>
.nb-root {
    font-family: 'Cairo', sans-serif;
    background: linear-gradient(135deg, #154677, #2ba6de);
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.16);
    box-shadow: 0 16px 36px rgba(21, 70, 119, 0.18);
    backdrop-filter: blur(14px);
}

.nb-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    padding: 0 24px;
    min-height: 72px;
    gap: 18px;
}

.nb-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    flex-shrink: 0;
}

.nb-logo-mark {
    width: 56px;
    height: 56px;
    background: rgba(255, 255, 255, 0.94);
    border-radius: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.12);
}

.nb-logo-mark img {
    width: 42px;
    height: 42px;
    object-fit: contain;
}

.nb-logo-text {
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
}

.nb-logo-text span {
    color: rgba(255, 255, 255, 0.82);
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

.nb-home-link,
.nb-wallet-btn,
.nb-user-btn {
    transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.nb-home-link {
    color: rgba(255, 255, 255, 0.92);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    padding: 9px 16px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    white-space: nowrap;
}

.nb-home-link:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateY(-1px);
}

.nb-divider {
    width: 1px;
    height: 28px;
    background: rgba(255, 255, 255, 0.18);
}

.nb-wallet-btn {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nb-wallet-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-1px);
}

.nb-badge {
    position: absolute;
    top: -6px;
    left: -6px;
    min-width: 20px;
    height: 20px;
    background: #ffffff;
    color: #154677;
    font-size: 10px;
    font-weight: 800;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid #2ba6de;
}

.nb-user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: #fff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    position: relative;
    white-space: nowrap;
}

.nb-user-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-1px);
}

.nb-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #ffffff;
    color: #154677;
    font-size: 13px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nb-chevron {
    font-size: 10px;
    opacity: 0.7;
    transition: transform 0.2s ease;
}

.nb-chevron.open {
    transform: rotate(180deg);
}

.nb-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    left: 0;
    min-width: 220px;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 1.25rem;
    box-shadow: 0 24px 50px rgba(21, 70, 119, 0.16);
    border: 1px solid rgba(21, 70, 119, 0.1);
    overflow: hidden;
    z-index: 9999;
}

.nb-dd-header {
    padding: 16px 18px 12px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
}

.nb-dd-name {
    font-size: 14px;
    font-weight: 800;
    color: #154677;
}

.nb-dd-label {
    font-size: 11px;
    color: #5f7288;
    margin-top: 4px;
}

.nb-dd-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    font-size: 13px;
    color: #154677;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: right;
    transition: background 0.2s ease;
}

.nb-dd-item:hover {
    background: #f4fbff;
}

.nb-dd-item.danger {
    color: #154677;
}

.nb-dd-item.danger:hover {
    background: rgba(43, 166, 222, 0.1);
}

.nb-dd-sep {
    height: 1px;
    background: rgba(21, 70, 119, 0.08);
    margin: 2px 0;
}

.nb-login-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 999px;
    background: #ffffff;
    color: #154677;
    font-size: 14px;
    font-weight: 800;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    white-space: nowrap;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.12);
}

.nb-login-btn:hover {
    background: #f4fbff;
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(21, 70, 119, 0.16);
}

@media (max-width: 768px) {
    .nb-inner {
        padding: 10px 16px;
        min-height: 68px;
        gap: 12px;
        flex-wrap: wrap;
    }

    .nb-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .nb-home-link,
    .nb-login-btn,
    .nb-user-btn {
        font-size: 13px;
    }
}
</style>






