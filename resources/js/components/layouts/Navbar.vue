<template>
    <header class="nb-hero" :dir="currentDir">
        <!-- SEO / LAZY HERO IMAGE -->
        <img
            class="nb-hero-bg"
            :src="heroBackground"
            :alt="isArabic ? 'خلفية ذكاء اصطناعي لأدوات AiPro' : 'AI tools background for AiPro'"
            loading="lazy"
            decoding="async"
            width="1920"
            height="900"
        />

        <div class="nb-hero-overlay"></div>

        <div class="nb-shell">
            <div class="nb-inner">
                <a class="nb-logo" :href="homeUrl" :aria-label="isArabic ? 'العودة إلى الرئيسية' : 'Back to home'">
                    <div class="nb-logo-mark">
                        <img
                            src="/images/Ai_logo.png"
                            alt="AiPro Logo"
                            width="42"
                            height="42"
                            fetchpriority="high"
                            decoding="async"
                        />
                    </div>
                </a>

                <nav class="nb-center-links" :aria-label="isArabic ? 'روابط التنقل الرئيسية' : 'Main navigation links'">
                    <a :href="homeUrl" class="nb-nav-link">
                        {{ t("navbar.home") }}
                    </a>

                    <a :href="homeUrl" class="nb-nav-link">
                        {{ isArabic ? "الأدوات" : "Tools" }}
                    </a>

                    <a :href="homeUrl" class="nb-nav-link">
                        {{ isArabic ? "عن المنصة" : "About Us" }}
                    </a>

                    <a :href="homeUrl" class="nb-nav-link">
                        {{ isArabic ? "تواصل معنا" : "Contact Us" }}
                    </a>
                </nav>

                <div class="nb-actions">
                    <!-- Language Dropdown -->
                    <div class="nb-lang-wrap" @click.stop>
                        <button class="nb-lang-btn" type="button" @click="toggleLangDropdown">
                            <i class="bi bi-globe2"></i>
                            <span>{{ currentLanguage.nativeName }}</span>
                            <span class="nb-chevron" :class="{ open: langDropdownOpen }">▼</span>
                        </button>

                        <div v-if="langDropdownOpen" class="nb-lang-dropdown">
                            <button
                                v-for="language in languages"
                                :key="language.code"
                                type="button"
                                class="nb-lang-item"
                                :class="{ active: language.code === currentLocale }"
                                @click="changeLanguage(language.code)"
                            >
                                <span>{{ language.nativeName }}</span>
                                <small>{{ language.label }}</small>
                            </button>
                        </div>
                    </div>

                    <button class="nb-wallet-btn" type="button" @click="goToWallet" :title="t('navbar.wallet')">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <rect x="2" y="6" width="20" height="14" rx="3" stroke="white" stroke-width="1.6" />
                            <path d="M16 13a1 1 0 1 0 2 0 1 1 0 0 0-2 0Z" fill="white" />
                            <path d="M2 10h20" stroke="white" stroke-width="1.6" />
                        </svg>

                        <span v-if="WalletBalance !== null" class="nb-badge">
                            {{ WalletBalance }}
                        </span>
                    </button>

                    <template v-if="isLoggedIn">
                        <div class="nb-user-btn" @click.stop="toggleDropdown">
                            <div class="nb-avatar">
                                {{ userName ? userName.charAt(0) : t("navbar.user").charAt(0) }}
                            </div>

                            <span class="nb-user-name">{{ userName }}</span>

                            <span class="nb-chevron" :class="{ open: dropdownOpen }">
                                ▼
                            </span>

                            <div v-if="dropdownOpen" class="nb-dropdown">
                                <div class="nb-dd-header">
                                    <div class="nb-dd-name">{{ userName }}</div>
                                    <div class="nb-dd-label">
                                        {{ t("navbar.personalAccount") }}
                                    </div>
                                </div>

                                <a :href="profileUrl" class="nb-dd-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="8" r="4" stroke="#154677" stroke-width="1.6" />
                                        <path
                                            d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    {{ t("navbar.profile") }}
                                </a>

                                <div class="nb-dd-sep"></div>

                                <button class="nb-dd-item danger" type="button" @click="logout">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                        <path
                                            d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                        />
                                        <polyline
                                            points="16 17 21 12 16 7"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <line
                                            x1="21"
                                            y1="12"
                                            x2="9"
                                            y2="12"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    {{ t("navbar.logout") }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <a :href="authUrl" class="nb-login-btn">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"
                                    stroke="white"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                                <polyline
                                    points="10 17 15 12 10 7"
                                    stroke="white"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <line
                                    x1="15"
                                    y1="12"
                                    x2="3"
                                    y2="12"
                                    stroke="white"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                            </svg>

                            {{ t("navbar.login") }}
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <section class="nb-hero-content">
            <div class="nb-hero-copy">
                <span class="nb-hero-badge">
                    <i class="bi bi-stars"></i>
                    {{ isArabic ? "منصة أدوات ذكاء اصطناعي" : "AI Tools Platform" }}
                </span>

                <h1>
                    {{ isArabic ? "أدوات ذكية لإنجاز أسرع" : "Smart AI Tools for Faster Work" }}
                </h1>

                <p>
                    {{
                        isArabic
                            ? "اكتب، لخص، حرّر، ونظّم مهامك من مكان واحد بتجربة بسيطة وسريعة."
                            : "Write, summarize, edit, and organize your tasks from one simple workspace."
                    }}
                </p>

                <div class="nb-hero-actions">
                    <a :href="homeUrl" class="nb-primary-cta">
                        {{ isArabic ? "استكشف الأدوات" : "Explore Tools" }}
                    </a>

                    <a :href="authUrl" class="nb-secondary-cta">
                        {{ isArabic ? "ابدأ الآن" : "Get Started" }}
                    </a>
                </div>
            </div>
        </section>
    </header>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import api from "@/services/ApiClient";
import homeService from "@/services/home/homeService";

const { t, locale } = useI18n();
const router = useRouter();

const heroBackground = "/images/hero.png";

const isLoggedIn = ref(false);
const WalletBalance = ref(0);
const dropdownOpen = ref(false);
const langDropdownOpen = ref(false);

const PROFILE_CACHE_PREFIX = "navbar_profile_cache_v1";
const WALLET_CACHE_PREFIX = "navbar_wallet_cache_v1";
const NAV_CACHE_TTL = 60 * 1000;

const languages = [
    {
        code: "ar",
        label: "Arabic",
        nativeName: "العربية",
        dir: "rtl",
    },
    {
        code: "en",
        label: "English",
        nativeName: "English",
        dir: "ltr",
    },
    {
        code: "ru",
        label: "Russian",
        nativeName: "Русский",
        dir: "ltr",
    },
    {
        code: "fr",
        label: "French",
        nativeName: "Français",
        dir: "ltr",
    },
    {
        code: "zh",
        label: "Chinese",
        nativeName: "中文",
        dir: "ltr",
    },
];

const currentLocale = computed(() => locale.value || "ar");

const isArabic = computed(() => String(currentLocale.value || "ar").toLowerCase() === "ar");

const currentLanguage = computed(() => {
    return languages.find((language) => language.code === currentLocale.value) || languages[0];
});

const currentDir = computed(() => {
    return currentLanguage.value.dir || "ltr";
});

const homeUrl = computed(() => `/${currentLocale.value}`);
const profileUrl = computed(() => `/${currentLocale.value}/profile`);
const authUrl = computed(() => `/${currentLocale.value}/auth`);
const walletUrl = computed(() => `/${currentLocale.value}/wallet`);

const userName = ref(t("navbar.user"));

const scopedCacheKey = (prefix) => `${prefix}:${homeService.getLang()}`;

const clearNavbarCache = () => {
    for (let i = sessionStorage.length - 1; i >= 0; i -= 1) {
        const key = sessionStorage.key(i);

        if (!key) continue;

        if (key.startsWith(`${PROFILE_CACHE_PREFIX}:`) || key.startsWith(`${WALLET_CACHE_PREFIX}:`)) {
            sessionStorage.removeItem(key);
        }
    }
};

const syncHtmlDirection = () => {
    document.documentElement.setAttribute("lang", currentLocale.value);
    document.documentElement.setAttribute("dir", currentDir.value);
};

const replaceLocaleInPath = (newLocale) => {
    const currentPath = window.location.pathname;
    const supportedCodes = languages.map((language) => language.code);
    const pathParts = currentPath.split("/").filter(Boolean);

    if (pathParts.length && supportedCodes.includes(pathParts[0])) {
        pathParts[0] = newLocale;
        return `/${pathParts.join("/")}${window.location.search}`;
    }

    return `/${newLocale}${currentPath === "/" ? "" : currentPath}${window.location.search}`;
};

const changeLanguage = async (newLocale) => {
    if (newLocale === currentLocale.value) {
        langDropdownOpen.value = false;
        return;
    }

    const selectedLanguage = languages.find((language) => language.code === newLocale);

    if (!selectedLanguage) return;

    homeService.setLang(newLocale);
    locale.value = newLocale;
    document.documentElement.setAttribute("lang", newLocale);
    document.documentElement.setAttribute("dir", selectedLanguage.dir);

    langDropdownOpen.value = false;

    await router.push(replaceLocaleInPath(newLocale));

    clearNavbarCache();
    refreshUserState();
};

const toggleLangDropdown = () => {
    langDropdownOpen.value = !langDropdownOpen.value;
    dropdownOpen.value = false;
};

watch(locale, () => {
    syncHtmlDirection();

    if (!isLoggedIn.value) {
        userName.value = t("navbar.user");
    }
});

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

    const cachedBalance = readCache(scopedCacheKey(WALLET_CACHE_PREFIX));

    if (cachedBalance !== null) {
        WalletBalance.value = cachedBalance;
        return;
    }

    try {
        const res = await api.get("/users/wallet");

        if (res.data.status === "success") {
            WalletBalance.value = res.data.data?.balance ?? 0;
            writeCache(scopedCacheKey(WALLET_CACHE_PREFIX), WalletBalance.value);
        }
    } catch {
        // Ignore wallet fetch errors in navbar.
    }
};

const goToWallet = () => {
    window.location.href = walletUrl.value;
};

const fetchProfile = async () => {
    if (!localStorage.getItem("auth_token")) {
        isLoggedIn.value = false;
        userName.value = t("navbar.user");
        WalletBalance.value = null;
        return;
    }

    const cachedProfile = readCache(scopedCacheKey(PROFILE_CACHE_PREFIX));

    if (cachedProfile?.name) {
        userName.value = cachedProfile.name;
        isLoggedIn.value = true;
        return;
    }

    try {
        const res = await api.get("/users/profile");

        if (res.data.status === "success") {
            userName.value = res.data.data.user.name || t("navbar.user");
            isLoggedIn.value = true;

            writeCache(scopedCacheKey(PROFILE_CACHE_PREFIX), {
                name: userName.value,
            });
        }
    } catch {
        // Ignore profile fetch errors in navbar.
    }
};

const logout = async () => {
    try {
        await api.post("/users/logout");
    } catch {
        // Ignore logout request errors and continue local cleanup.
    }

    localStorage.removeItem("user_role");
    localStorage.removeItem("auth_token");

    clearNavbarCache();

    isLoggedIn.value = false;
    userName.value = t("navbar.user");
    WalletBalance.value = null;
    dropdownOpen.value = false;
    langDropdownOpen.value = false;

    window.location.href = homeUrl.value;
};

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
    langDropdownOpen.value = false;
};

const handleDocumentClick = (e) => {
    if (!e.target.closest(".nb-user-btn")) {
        dropdownOpen.value = false;
    }

    if (!e.target.closest(".nb-lang-wrap")) {
        langDropdownOpen.value = false;
    }
};

const refreshUserState = () => {
    fetchProfile();

    deferToIdle(() => {
        fetchWallet();
    });
};

const handleLangChanged = () => {
    locale.value = homeService.getLang();
    syncHtmlDirection();
    clearNavbarCache();
    refreshUserState();
};

onMounted(() => {
    syncHtmlDirection();

    refreshUserState();

    window.addEventListener("login", refreshUserState);
    window.addEventListener("lang-changed", handleLangChanged);
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    window.removeEventListener("login", refreshUserState);
    window.removeEventListener("lang-changed", handleLangChanged);
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<style scoped>
.nb-hero {
    position: relative;
    min-height: 760px;
    overflow: hidden;
    font-family: 'Cairo', sans-serif;
    background: #154677;
}

.nb-hero-bg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 1;
    transform: scale(1.02);
}

.nb-hero-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    background:
        linear-gradient(180deg, rgba(21, 70, 119, 0.28) 0%, rgba(21, 70, 119, 0.42) 52%, rgba(248, 250, 252, 1) 100%),
        radial-gradient(circle at 70% 20%, rgba(43, 166, 222, 0.22), transparent 34%);
}

.nb-shell {
    position: relative;
    z-index: 10;
    max-width: 1620px;
    margin: 0 auto;
    padding: 34px 24px 0;
}

.nb-inner {
    min-height: 84px;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 0 16px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 20px 55px rgba(15, 23, 42, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(16px);
}

.nb-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    flex-shrink: 0;
}

.nb-logo-mark {
    width: 138px;
    height: 50px;
    background: rgba(255, 255, 255, 0.96);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(21, 70, 119, 0.08);
    box-shadow: 0 10px 20px rgba(21, 70, 119, 0.08);
}

.nb-logo-mark img {
    width: 42px;
    height: 42px;
    object-fit: contain;
}

.nb-center-links {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 34px;
    min-width: 0;
}

.nb-nav-link {
    color: #1f2937;
    text-decoration: none;
    font-size: 14px;
    font-weight: 900;
    letter-spacing: 0.01em;
    white-space: nowrap;
    transition: color 0.2s ease, transform 0.2s ease;
}

.nb-nav-link:hover {
    color: #154677;
    transform: translateY(-1px);
}

.nb-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.nb-lang-wrap {
    position: relative;
}

.nb-lang-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1f2937;
    background: #eef3fa;
    border: 1px solid rgba(21, 70, 119, 0.08);
    font-size: 13px;
    font-weight: 900;
    padding: 10px 14px;
    border-radius: 999px;
    white-space: nowrap;
    cursor: pointer;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.nb-lang-btn i {
    color: #154677;
    font-size: 15px;
}

.nb-lang-btn:hover {
    background: #f4fbff;
    transform: translateY(-1px);
}

.nb-lang-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    min-width: 180px;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 1rem;
    box-shadow: 0 24px 50px rgba(21, 70, 119, 0.16);
    border: 1px solid rgba(21, 70, 119, 0.1);
    overflow: hidden;
    z-index: 9999;
}

.nb-hero[dir="rtl"] .nb-lang-dropdown {
    left: 0;
    right: auto;
}

.nb-hero[dir="ltr"] .nb-lang-dropdown {
    right: 0;
    left: auto;
}

.nb-lang-item {
    width: 100%;
    border: none;
    background: transparent;
    padding: 12px 14px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 3px;
    color: #154677;
    font-weight: 800;
    transition: background 0.2s ease;
}

.nb-hero[dir="rtl"] .nb-lang-item {
    text-align: right;
    align-items: flex-end;
}

.nb-hero[dir="ltr"] .nb-lang-item {
    text-align: left;
    align-items: flex-start;
}

.nb-lang-item small {
    font-size: 11px;
    color: #5f7288;
    font-weight: 600;
}

.nb-lang-item:hover,
.nb-lang-item.active {
    background: #f4fbff;
}

.nb-wallet-btn {
    position: relative;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: #154677;
    border: 1px solid rgba(21, 70, 119, 0.16);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.nb-wallet-btn:hover {
    background: #2ba6de;
    transform: translateY(-1px);
    box-shadow: 0 16px 28px rgba(21, 70, 119, 0.22);
}

.nb-badge {
    position: absolute;
    top: -7px;
    left: -7px;
    min-width: 21px;
    height: 21px;
    background: #ffffff;
    color: #154677;
    font-size: 10px;
    font-weight: 900;
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
    padding: 8px 13px;
    border-radius: 999px;
    background: #eef3fa;
    border: 1px solid rgba(21, 70, 119, 0.08);
    color: #154677;
    cursor: pointer;
    font-size: 14px;
    font-weight: 800;
    position: relative;
    white-space: nowrap;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.nb-user-btn:hover {
    background: #f4fbff;
    transform: translateY(-1px);
}

.nb-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #154677;
    color: #ffffff;
    font-size: 13px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nb-user-name {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nb-chevron {
    font-size: 10px;
    opacity: 0.75;
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
    padding: 11px 18px;
    border-radius: 999px;
    background: #154677;
    color: #ffffff;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    white-space: nowrap;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
}

.nb-login-btn:hover {
    background: #2ba6de;
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(21, 70, 119, 0.2);
}

/* HERO CONTENT */
.nb-hero-content {
    position: relative;
    z-index: 5;
    max-width: 1620px;
    margin: 0 auto;
    padding: 96px 24px 145px;
    display: flex;
    align-items: center;
}

.nb-hero-copy {
    width: min(680px, 100%);
    color: #ffffff;
}

.nb-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.94);
    color: #154677;
    padding: 8px 15px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
    box-shadow: 0 12px 25px rgba(15, 23, 42, 0.14);
}

.nb-hero-badge i {
    color: #2ba6de;
}

.nb-hero-copy h1 {
    margin: 22px 0 0;
    font-size: clamp(36px, 5vw, 74px);
    line-height: 1.08;
    font-weight: 900;
    letter-spacing: -0.04em;
    text-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
}

.nb-hero-copy p {
    margin: 18px 0 0;
    max-width: 600px;
    font-size: 17px;
    line-height: 1.8;
    color: rgba(255, 255, 255, 0.9);
    text-shadow: 0 12px 28px rgba(15, 23, 42, 0.2);
}

.nb-hero-actions {
    margin-top: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.nb-primary-cta,
.nb-secondary-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 22px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.nb-primary-cta {
    background: #154677;
    color: #ffffff;
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.28);
}

.nb-primary-cta:hover {
    background: #2ba6de;
    transform: translateY(-2px);
}

.nb-secondary-cta {
    background: rgba(255, 255, 255, 0.94);
    color: #154677;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.16);
}

.nb-secondary-cta:hover {
    transform: translateY(-2px);
    background: #ffffff;
}

/* RESPONSIVE */
@media (max-width: 1180px) {
    .nb-center-links {
        gap: 18px;
    }

    .nb-nav-link {
        font-size: 13px;
    }

    .nb-user-name {
        display: none;
    }
}

@media (max-width: 920px) {
    .nb-shell {
        padding: 18px 14px 0;
    }

    .nb-inner {
        min-height: auto;
        padding: 12px;
        flex-wrap: wrap;
        border-radius: 20px;
    }

    .nb-logo-mark {
        width: 92px;
        height: 46px;
    }

    .nb-center-links {
        order: 3;
        width: 100%;
        justify-content: flex-start;
        overflow-x: auto;
        padding: 8px 2px 2px;
        gap: 18px;
        scrollbar-width: none;
    }

    .nb-center-links::-webkit-scrollbar {
        display: none;
    }

    .nb-actions {
        margin-inline-start: auto;
    }

    .nb-hero-content {
        padding: 70px 18px 120px;
    }
}

@media (max-width: 640px) {
    .nb-hero {
        min-height: 610px;
    }

    .nb-inner {
        gap: 10px;
    }

    .nb-logo-mark {
        width: 76px;
        height: 42px;
    }

    .nb-logo-mark img {
        width: 36px;
        height: 36px;
    }

    .nb-lang-btn span:not(.nb-chevron) {
        display: none;
    }

    .nb-wallet-btn {
        width: 42px;
        height: 42px;
    }

    .nb-login-btn {
        padding: 10px 13px;
        font-size: 13px;
    }

    .nb-hero-content {
        padding-top: 58px;
    }

    .nb-hero-copy h1 {
        font-size: 38px;
    }

    .nb-hero-copy p {
        font-size: 15px;
    }

    .nb-primary-cta,
    .nb-secondary-cta {
        width: 100%;
    }
}
</style>
